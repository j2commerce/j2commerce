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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Writes individual members of the com_j2commerce params blob.
 *
 * A caller that sets a member on the request-cached Registry and writes the whole blob back
 * reverts every other member to whatever it held when this request first read it. These
 * writers re-read the stored value, set only their own members, and commit the result only
 * while the stored value still matches what was read.
 *
 * This is for the component's own bookkeeping members, not a general params setter: only the
 * members named in self::ALLOWED can be written through it.
 */
class ComponentParamsHelper
{
    private const ELEMENT = 'com_j2commerce';

    private const TYPE = 'component';

    /** The bookkeeping members this helper owns. Anything else belongs to the Options form. */
    private const ALLOWED = [
        'cron_last_trigger',
        'queue_key',
        'plg_j2commerce_inventory_control_timestamp',
    ];

    /** Bounded so a busy row cannot hold a cron run open. */
    private const MAX_ATTEMPTS = 3;

    public static function set(string $key, mixed $value): bool
    {
        return self::setMultiple([$key => $value]);
    }

    /**
     * @param   array<string, mixed>  $values  Members to write; every other member is left as stored.
     */
    public static function setMultiple(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        $unknown = array_diff(array_keys($values), self::ALLOWED);

        if ($unknown !== []) {
            Log::add(
                'Refused to write unowned component params member(s): ' . implode(', ', $unknown),
                Log::WARNING,
                'com_j2commerce'
            );

            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $stored = self::readStored($db);
            $params = new Registry($stored);

            foreach ($values as $key => $value) {
                $params->set($key, $value);
            }

            $paramsJson = $params->toString();

            // An unchanged blob updates no row, which is indistinguishable from a lost race.
            if ($paramsJson === $stored || self::write($db, $paramsJson, $stored)) {
                self::syncRequestCache($values);

                return true;
            }
        }

        return false;
    }

    private static function readStored(DatabaseInterface $db): string
    {
        $element = self::ELEMENT;
        $type    = self::TYPE;

        $query = $db->createQuery()
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);

        return (string) $db->setQuery($query)->loadResult();
    }

    /**
     * The comparison is CAST to BINARY because the default utf8mb4_unicode_ci is
     * case-insensitive, accent-insensitive and PAD SPACE: a plain = accepts a concurrent save
     * that differs only in letter case, accents or trailing whitespace and overwrites it.
     */
    private static function write(DatabaseInterface $db, string $paramsJson, string $expected): bool
    {
        $element = self::ELEMENT;
        $type    = self::TYPE;

        $query = $db->createQuery()
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->where('CAST(' . $db->quoteName('params') . ' AS BINARY) = :expected')
            ->bind(':params', $paramsJson)
            ->bind(':expected', $expected)
            ->bind(':element', $element)
            ->bind(':type', $type);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * ComponentHelper hands back one shared Registry per request, so the caller reads its own
     * write without re-reading the row.
     */
    private static function syncRequestCache(array $values): void
    {
        $params = ComponentHelper::getParams(self::ELEMENT);

        foreach ($values as $key => $value) {
            $params->set($key, $value);
        }
    }
}
