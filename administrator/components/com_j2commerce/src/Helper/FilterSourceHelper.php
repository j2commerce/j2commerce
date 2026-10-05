<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Service\FilterSourceWriter;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

/**
 * Filter group value sources. A group's values are either entered by hand ('j2commerce') or
 * written by a plugin that registers a source key. Plugin values are stored as ordinary filter
 * rows and product links, so every storefront filter path reads them unchanged.
 *
 * Plugin contract:
 *   onJ2CommerceGetFilterSources  — addResult(['<plugin>.<type>' => 'Label', ...])
 *   onContentPrepareForm          — add the source's settings fields to com_j2commerce.filtergroup,
 *                                   under the `source_params` group with showon=".source:<key>"
 *   onJ2CommerceFilterSourceSync  — arguments group_id, source, params (Registry), writer
 *                                   (FilterSourceWriter); write the group's values and links
 *
 * @since  6.6.4
 */
final class FilterSourceHelper
{
    public const NATIVE = 'j2commerce';

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,23}\.[a-z][a-z0-9_]{0,23}$/';

    private static ?array $sources = null;

    /** bind() takes its value by reference; this keeps it alive until the query executes. */
    private static string $nativeSource = self::NATIVE;

    /** @return array<string, string>  source key => label, J2Commerce first */
    public static function sources(): array
    {
        if (self::$sources !== null) {
            return self::$sources;
        }

        // Assigned before the dispatch so a handler that re-enters sees the native set, not a loop.
        self::$sources = $sources = [self::NATIVE => Text::_('COM_J2COMMERCE_FILTERGROUP_SOURCE_NATIVE')];

        try {
            $results = J2CommerceHelper::plugin()->event('GetFilterSources', [])->getEventResult();
        } catch (\Throwable $e) {
            Log::add('onJ2CommerceGetFilterSources failed: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
            $results = null;
        }

        foreach (array_filter((array) $results, 'is_array') as $result) {
            foreach ($result as $key => $label) {
                if (\is_string($key) && preg_match(self::KEY_PATTERN, $key) && \is_string($label) && $label !== '' && !isset($sources[$key])) {
                    $sources[$key] = $label;
                }
            }
        }

        return self::$sources = $sources;
    }

    public static function isRegistered(string $source): bool
    {
        return isset(self::sources()[$source]);
    }

    /** The label to show for a stored source, including one whose plugin is no longer installed. */
    public static function label(string $source): string
    {
        return self::sources()[$source] ?? Text::sprintf('COM_J2COMMERCE_FILTERGROUP_SOURCE_MISSING', $source);
    }

    /**
     * Rebuild one plugin-sourced group's values and links. Returns false for a native group, a
     * missing group, or a source no enabled plugin answers for; the stored values then stay as
     * they are, so the storefront keeps filtering on the last good set.
     */
    public static function syncGroup(int $groupId): bool
    {
        $group = self::loadGroup($groupId);

        if (!$group || $group->source === self::NATIVE || !self::isRegistered($group->source)) {
            return false;
        }

        // One transaction around the whole sync: a plugin that replaces values and then fails on
        // the links leaves the last good set in place instead of new values with stale links.
        $db = self::db();
        $db->transactionStart(true);

        try {
            J2CommerceHelper::plugin()->event('FilterSourceSync', [
                'group_id' => $groupId,
                'source'   => $group->source,
                'params'   => new Registry($group->source_params ?: '{}'),
                'writer'   => new FilterSourceWriter($groupId, $group->source),
            ]);

            $db->transactionCommit(true);
        } catch (\Throwable $e) {
            $db->transactionRollback(true);
            Log::add('onJ2CommerceFilterSourceSync failed for group ' . $groupId . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return false;
        }

        return true;
    }

    /** @return int  how many groups were rebuilt */
    public static function syncAll(?string $source = null): int
    {
        $db     = self::db();
        $native = self::NATIVE;
        $query  = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_filtergroup_id'))
            ->from($db->quoteName('#__j2commerce_filtergroups'))
            ->where($db->quoteName('source') . ' != :native')
            ->bind(':native', $native);

        if ($source !== null) {
            $query->where($db->quoteName('source') . ' = :source')
                ->bind(':source', $source);
        }

        $count = 0;

        foreach (array_map('intval', $db->setQuery($query)->loadColumn() ?: []) as $groupId) {
            $count += (int) self::syncGroup($groupId);
        }

        return $count;
    }

    /**
     * The subset of $filterIds an editor may assign or remove by hand: values of native groups.
     * Plugin-sourced values are kept by their plugin, so hand edits to them would be overwritten.
     *
     * @param   int[]  $filterIds
     *
     * @return  int[]
     */
    public static function nativeFilterIds(array $filterIds): array
    {
        $filterIds = array_values(array_unique(array_filter(array_map('intval', $filterIds))));

        if ($filterIds === []) {
            return [];
        }

        $db     = self::db();
        $native = self::NATIVE;
        $query  = $db->getQuery(true)
            ->select($db->quoteName('f.j2commerce_filter_id'))
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join('INNER', $db->quoteName('#__j2commerce_filtergroups', 'fg'), $db->quoteName('fg.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id'))
            ->whereIn($db->quoteName('f.j2commerce_filter_id'), $filterIds)
            ->where($db->quoteName('fg.source') . ' = :native')
            ->bind(':native', $native);

        return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
    }

    /** SQL predicate (on the filters alias) matching values of native groups only; binds :j2cNativeSource on $query. */
    public static function nativeFilterCondition(QueryInterface $query, DatabaseInterface $db, string $filterAlias = 'f'): string
    {
        $query->bind(':j2cNativeSource', self::$nativeSource);

        return $db->quoteName($filterAlias . '.group_id') . ' IN (SELECT ' . $db->quoteName('j2commerce_filtergroup_id')
            . ' FROM ' . $db->quoteName('#__j2commerce_filtergroups')
            . ' WHERE ' . $db->quoteName('source') . ' = :j2cNativeSource)';
    }

    private static function loadGroup(int $groupId): ?object
    {
        if ($groupId < 1) {
            return null;
        }

        $db    = self::db();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['source', 'source_params']))
            ->from($db->quoteName('#__j2commerce_filtergroups'))
            ->where($db->quoteName('j2commerce_filtergroup_id') . ' = :id')
            ->bind(':id', $groupId, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
