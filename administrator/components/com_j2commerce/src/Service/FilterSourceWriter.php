<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The only way a filter source plugin writes values: bound to one group, and refusing to act
 * unless that group still carries the caller's source key, so a plugin can never alter values
 * a store owner entered by hand or another plugin owns. Values are keyed by `source_ref`, so a
 * rename updates the row in place and the filter id product links point at never changes.
 * Transactions are savepoints, so a sync that calls several methods commits them as one unit.
 *
 * @since  6.6.4
 */
final class FilterSourceWriter
{
    private DatabaseInterface $db;

    public function __construct(private int $groupId, private string $source)
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Make the group's values exactly $values. Values whose ref is absent are removed with their
     * product links.
     *
     * @param   array<string|int, array{name: string, ordering?: int}>  $values  ref => value
     *
     * @return  array<string, int>  ref => filter_id
     */
    public function replaceValues(array $values): array
    {
        if (!$this->ownsGroup()) {
            return [];
        }

        $existing = $this->valueMap();
        $kept     = [];

        $this->db->transactionStart(true);

        try {
            $ordering = 0;

            foreach ($values as $ref => $value) {
                $ref  = substr(trim((string) $ref), 0, 100);
                $name = trim((string) ($value['name'] ?? ''));

                if ($ref === '' || $name === '' || isset($kept[$ref])) {
                    continue;
                }

                $order = (int) ($value['ordering'] ?? ++$ordering);

                if (isset($existing[$ref])) {
                    $filterId = $existing[$ref];
                    $query    = $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__j2commerce_filters'))
                        ->set($this->db->quoteName('filter_name') . ' = :name')
                        ->set($this->db->quoteName('ordering') . ' = :ordering')
                        ->where($this->db->quoteName('j2commerce_filter_id') . ' = :id')
                        ->bind(':name', $name)
                        ->bind(':ordering', $order, ParameterType::INTEGER)
                        ->bind(':id', $filterId, ParameterType::INTEGER);
                    $this->db->setQuery($query)->execute();
                } else {
                    $empty = '';
                    $query = $this->db->getQuery(true)
                        ->insert($this->db->quoteName('#__j2commerce_filters'))
                        ->columns($this->db->quoteName(['group_id', 'filter_name', 'filter_color', 'ordering', 'source_ref']))
                        ->values(':group, :name, :color, :ordering, :ref')
                        ->bind(':group', $this->groupId, ParameterType::INTEGER)
                        ->bind(':name', $name)
                        ->bind(':color', $empty)
                        ->bind(':ordering', $order, ParameterType::INTEGER)
                        ->bind(':ref', $ref);
                    $this->db->setQuery($query)->execute();
                    $filterId = (int) $this->db->insertid();
                }

                $kept[$ref] = (int) $filterId;
            }

            // Everything else in the group goes, including values entered by hand before the group
            // was switched to this source: the group now holds exactly what the source supplies.
            $this->deleteFilters(array_values(array_diff($this->groupFilterIds(), $kept)));

            $this->db->transactionCommit(true);
        } catch (\Throwable $e) {
            $this->db->transactionRollback(true);

            throw $e;
        }

        return $kept;
    }

    /**
     * Make the group's product links exactly $links. Refs the group has no value for are ignored.
     *
     * @param   array<string|int, int[]>  $links  ref => product ids
     */
    public function replaceLinks(array $links): void
    {
        if (!$this->ownsGroup()) {
            return;
        }

        $map = $this->valueMap();

        $this->db->transactionStart(true);

        try {
            if ($map !== []) {
                $query = $this->db->getQuery(true)
                    ->delete($this->db->quoteName('#__j2commerce_product_filters'))
                    ->whereIn($this->db->quoteName('filter_id'), array_values($map));
                $this->db->setQuery($query)->execute();
            }

            $rows = [];

            foreach ($links as $ref => $productIds) {
                $filterId = $map[(string) $ref] ?? 0;

                if (!$filterId) {
                    continue;
                }

                foreach (array_unique(array_filter(array_map('intval', (array) $productIds))) as $productId) {
                    $rows[] = $productId . ', ' . $filterId;
                }
            }

            $this->insertLinks($rows);

            $this->db->transactionCommit(true);
        } catch (\Throwable $e) {
            $this->db->transactionRollback(true);

            throw $e;
        }
    }

    /**
     * Set one product's links within this group to exactly $refs (an article saved with new tags).
     *
     * @param   array<string|int>  $refs
     */
    public function linkProduct(int $productId, array $refs): void
    {
        if ($productId < 1 || !$this->ownsGroup()) {
            return;
        }

        $map = $this->valueMap();

        if ($map === []) {
            return;
        }

        $this->db->transactionStart(true);

        try {
            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__j2commerce_product_filters'))
                ->where($this->db->quoteName('product_id') . ' = :product')
                ->whereIn($this->db->quoteName('filter_id'), array_values($map))
                ->bind(':product', $productId, ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            $rows = [];

            foreach (array_unique(array_map('strval', $refs)) as $ref) {
                if (isset($map[$ref])) {
                    $rows[] = $productId . ', ' . $map[$ref];
                }
            }

            $this->insertLinks($rows);

            $this->db->transactionCommit(true);
        } catch (\Throwable $e) {
            $this->db->transactionRollback(true);

            throw $e;
        }
    }

    /** @return array<string, int>  ref => filter_id for this group */
    private function valueMap(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['source_ref', 'j2commerce_filter_id']))
            ->from($this->db->quoteName('#__j2commerce_filters'))
            ->where($this->db->quoteName('group_id') . ' = :group')
            ->where($this->db->quoteName('source_ref') . ' != ' . $this->db->quote(''))
            ->bind(':group', $this->groupId, ParameterType::INTEGER);

        return array_map('intval', $this->db->setQuery($query)->loadAssocList('source_ref', 'j2commerce_filter_id') ?: []);
    }

    /** @return int[] */
    private function groupFilterIds(): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('j2commerce_filter_id'))
            ->from($this->db->quoteName('#__j2commerce_filters'))
            ->where($this->db->quoteName('group_id') . ' = :group')
            ->bind(':group', $this->groupId, ParameterType::INTEGER);

        return array_map('intval', $this->db->setQuery($query)->loadColumn() ?: []);
    }

    private function ownsGroup(): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_filtergroups'))
            ->where($this->db->quoteName('j2commerce_filtergroup_id') . ' = :group')
            ->where($this->db->quoteName('source') . ' = :source')
            ->bind(':group', $this->groupId, ParameterType::INTEGER)
            ->bind(':source', $this->source);

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    /** @param int[] $filterIds */
    private function deleteFilters(array $filterIds): void
    {
        if ($filterIds === []) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_product_filters'))
            ->whereIn($this->db->quoteName('filter_id'), $filterIds);
        $this->db->setQuery($query)->execute();

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_filters'))
            ->whereIn($this->db->quoteName('j2commerce_filter_id'), $filterIds);
        $this->db->setQuery($query)->execute();
    }

    /** @param string[] $rows  "productId, filterId" pairs of integers */
    private function insertLinks(array $rows): void
    {
        foreach (array_chunk(array_values(array_unique($rows)), 500) as $chunk) {
            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__j2commerce_product_filters'))
                ->columns($this->db->quoteName(['product_id', 'filter_id']))
                ->values($chunk);
            $this->db->setQuery($query)->execute();
        }
    }
}
