<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;

/**
 * Single source of truth for parsing product-list filter state from an HTTP request.
 *
 * Reads:
 *   - ?filters=group:alias,group:alias    (SEF tokens — composite or bare aliases, or numeric IDs)
 *   - ?productfilter_ids[]=N              (legacy array form)
 *   - ?manufacturer_ids[]=N  | ?brands=1,2
 *   - ?vendor_ids[]=N        | ?vendors=1,2
 *   - ?tag_ids[]=N           | ?tag_match=any|all
 *   - ?pricefrom=N           | ?priceto=N
 *
 * Called from BOTH:
 *   - ProductsModel::populateState()  (every page load — fixes cold-paste of filtered URL)
 *   - ProductsController::filter()    (AJAX sidebar)
 *
 * Alias→ID catalog is loaded once per request and cached statically.
 */
class ProductFilterRequestHelper
{
    private static ?array $aliasCatalog = null;

    public static function resolveFromRequest(?Input $input = null): array
    {
        $input ??= Factory::getApplication()->getInput();

        return [
            'manufacturer_ids'  => self::readIdList($input, 'manufacturer_ids', 'brands'),
            'vendor_ids'        => self::readIdList($input, 'vendor_ids', 'vendors'),
            'productfilter_ids' => self::readProductFilterIds($input),
            'tag_ids'           => array_values(array_filter(array_map('intval', $input->get('tag_ids', [], 'array')))),
            'tag_match'         => $input->getString('tag_match', 'any') === 'all' ? 'all' : 'any',
            'price_from'        => $input->getFloat('pricefrom', 0.0),
            'price_to'          => $input->getFloat('priceto', 0.0),
        ];
    }

    /**
     * Bucket the selected filter IDs by the group that owns them.
     *
     * The group is the unit of facet logic — OR inside one, AND between them — and the
     * request carries a flat list that has thrown that membership away, so it is read back
     * from the filter rows. IDs with no surviving row are dropped.
     *
     * @return  array<int, int[]>  group_id => filter IDs
     */
    public static function groupSelectedIds(DatabaseInterface $db, array $filterIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $filterIds))));

        if ($ids === []) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_filter_id', 'group_id']))
            ->from($db->quoteName('#__j2commerce_filters'))
            ->whereIn($db->quoteName('j2commerce_filter_id'), $ids);

        $db->setQuery($query);

        $grouped = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            $grouped[(int) $row->group_id][] = (int) $row->j2commerce_filter_id;
        }

        return $grouped;
    }

    /**
     * Constrain a listing query to the selected product filters: OR within a group, AND
     * between groups, so every group the visitor touches narrows the result instead of
     * widening it. `list_product_filter_search_logic_rel` = AND tightens the within-group
     * side too — the product must then carry every value picked inside that one group.
     *
     * $skipGroupId leaves one group out, which is how a facet recount asks "what would
     * still be available in this group" without the group's own selection collapsing it.
     *
     * ID lists are interpolated rather than bound: these are cast integers, and a bound
     * parameter would live on the subquery object rather than on the outer query it is
     * embedded in as a string, so it would never reach the driver.
     *
     * @param  array<int, int[]>  $grouped  As returned by groupSelectedIds()
     */
    public static function applyToQuery(
        QueryInterface $query,
        DatabaseInterface $db,
        array $grouped,
        ?Registry $params = null,
        int $skipGroupId = 0
    ): void {
        $matchAllInGroup = self::wantsAllFilters($params);

        foreach ($grouped as $groupId => $ids) {
            if ((int) $groupId === $skipGroupId) {
                continue;
            }

            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

            if ($ids === []) {
                continue;
            }

            // Alias distinct from the facet query's own 'pf' join: legal to shadow, but the
            // two mean different things and reading it that way is a trap.
            $subQuery = $db->getQuery(true)
                ->select($db->quoteName('pfs.product_id'))
                ->from($db->quoteName('#__j2commerce_product_filters', 'pfs'))
                ->where($db->quoteName('pfs.filter_id') . ' IN (' . implode(',', $ids) . ')');

            if ($matchAllInGroup && \count($ids) > 1) {
                $subQuery->group($db->quoteName('pfs.product_id'))
                    ->having('COUNT(DISTINCT ' . $db->quoteName('pfs.filter_id') . ') = ' . \count($ids));
            }

            $query->where($db->quoteName('p.j2commerce_product_id') . ' IN (' . $subQuery . ')');
        }
    }

    /**
     * The filter values a listing can still offer, each with the number of products behind it.
     *
     * $listingQuery must hand back a fresh copy of the listing's own query with the product
     * filter selection left off, so category, tag, search, manufacturer, vendor, price,
     * publish-window and access predicates all carry over and the sidebar describes the same
     * set of products the listing does — not one pagination page of it.
     *
     * The value list itself ignores the product-filter selection entirely, so it is the same
     * set before and after any tick. That keeps it a stable superset the AJAX path can update
     * in place instead of rebuilding the sidebar out of nodes it would have to invent.
     *
     * The COUNT on each value is the narrower thing: a group is counted against the listing
     * narrowed by the OTHER groups only, so ticking a value never collapses the group it was
     * ticked in. Values the current selection puts out of reach come back as zero.
     *
     * @param  callable():QueryInterface  $listingQuery
     *
     * @return array<int, array{group_name: string, filters: object[]}>
     */
    public static function facetsForListing(
        DatabaseInterface $db,
        callable $listingQuery,
        array $selectedIds,
        ?Registry $params = null
    ): array {
        $grouped = self::groupSelectedIds($db, $selectedIds);
        $rows    = self::facetRows($db, $listingQuery, [], $params, 0);
        $counts  = self::facetCounts($db, $listingQuery, $grouped, $params, $rows);

        $groups = [];

        foreach ($rows as $row) {
            $groupId  = (int) $row->j2commerce_filtergroup_id;
            $filterId = (int) $row->j2commerce_filter_id;

            $groups[$groupId] ??= ['group_name' => $row->group_name, 'filters' => []];

            $groups[$groupId]['filters'][] = (object) [
                'filter_id'     => $filterId,
                'filter_name'   => $row->filter_name,
                'product_count' => $counts[$filterId] ?? 0,
            ];
        }

        return $groups;
    }

    /**
     * How many products each value would still reach under the current selection.
     *
     * @return array<int, int>  filter_id => product count
     */
    private static function facetCounts(
        DatabaseInterface $db,
        callable $listingQuery,
        array $grouped,
        ?Registry $params,
        array $unfilteredRows
    ): array {
        if ($grouped === []) {
            return self::countMap($unfilteredRows);
        }

        // One shared pass with everything applied covers the groups holding no selection.
        $counts = array_diff_key(
            self::countMap(self::facetRows($db, $listingQuery, $grouped, $params, 0)),
            self::countMap(self::rowsInGroups($unfilteredRows, array_keys($grouped)))
        );

        // Then one pass per selected group, that group left out of its own count.
        foreach (array_keys($grouped) as $groupId) {
            $rows   = self::facetRows($db, $listingQuery, $grouped, $params, (int) $groupId);
            $counts += self::countMap(self::rowsInGroups($rows, [(int) $groupId]));
        }

        return $counts;
    }

    /** @return array<int, int> */
    private static function countMap(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row->j2commerce_filter_id] = (int) $row->product_count;
        }

        return $map;
    }

    private static function rowsInGroups(array $rows, array $groupIds): array
    {
        $groupIds = array_map('intval', $groupIds);

        return array_values(array_filter(
            $rows,
            static fn ($row) => \in_array((int) $row->j2commerce_filtergroup_id, $groupIds, true)
        ));
    }

    /**
     * One counting pass over the listing, optionally with one group's selection left out.
     */
    private static function facetRows(
        DatabaseInterface $db,
        callable $listingQuery,
        array $grouped,
        ?Registry $params,
        int $skipGroupId
    ): array {
        $query = $listingQuery();

        self::applyToQuery($query, $db, $grouped, $params, $skipGroupId);

        $query->clear('select')->clear('order')->clear('group')
            ->select([
                $db->quoteName('fg.j2commerce_filtergroup_id'),
                $db->quoteName('fg.group_name'),
                $db->quoteName('fg.ordering', 'group_ordering'),
                $db->quoteName('f.j2commerce_filter_id'),
                $db->quoteName('f.filter_name'),
                $db->quoteName('f.ordering', 'filter_ordering'),
                'COUNT(DISTINCT ' . $db->quoteName('p.j2commerce_product_id') . ') AS ' . $db->quoteName('product_count'),
            ])
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_filters', 'pf') . ' ON '
                . $db->quoteName('pf.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_filters', 'f') . ' ON '
                . $db->quoteName('f.j2commerce_filter_id') . ' = ' . $db->quoteName('pf.filter_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_filtergroups', 'fg') . ' ON '
                . $db->quoteName('fg.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id')
            )
            ->where($db->quoteName('fg.enabled') . ' = 1')
            ->group($db->quoteName([
                'fg.j2commerce_filtergroup_id',
                'fg.group_name',
                'fg.ordering',
                'f.j2commerce_filter_id',
                'f.filter_name',
                'f.ordering',
            ]))
            ->order([
                $db->quoteName('fg.ordering') . ' ASC',
                $db->quoteName('f.ordering') . ' ASC',
            ]);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Whether a product must carry every value selected inside a single group, rather than
     * any one of them. Between groups the match is always AND.
     */
    public static function wantsAllFilters(?Registry $params = null): bool
    {
        $params ??= Factory::getApplication()->getParams();

        return strtoupper((string) $params->get('list_product_filter_search_logic_rel', 'OR')) === 'AND';
    }

    public static function resolveAliasesToIds(array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        $catalog = self::loadAliasCatalog();
        $ids     = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            if (is_numeric($token)) {
                $ids[] = (int) $token;
                continue;
            }

            // Composite token "groupAlias:filterAlias" — disambiguates same filter name across groups.
            if (str_contains($token, ':')) {
                [$groupAlias, $filterAlias] = explode(':', $token, 2);
                foreach ($catalog as $row) {
                    if ($row['group_alias'] === $groupAlias && $row['filter_alias'] === $filterAlias) {
                        $ids[] = $row['id'];
                        break;
                    }
                }
                continue;
            }

            foreach ($catalog as $row) {
                if ($row['filter_alias'] === $token) {
                    $ids[] = $row['id'];
                    break;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public static function clearAliasCache(): void
    {
        self::$aliasCatalog = null;
    }

    private static function readIdList(Input $input, string $arrayKey, string $commaKey): array
    {
        $ids = $input->get($arrayKey, [], 'array');
        if (empty($ids)) {
            $comma = $input->getString($commaKey, '');
            if ($comma !== '') {
                $ids = array_filter(explode(',', $comma), 'is_numeric');
            }
        }
        return array_values(array_filter(array_map('intval', $ids)));
    }

    private static function readProductFilterIds(Input $input): array
    {
        $ids = $input->get('productfilter_ids', [], 'array');
        if (!empty($ids)) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        $filtersParam = $input->getString('filters', '');
        if ($filtersParam === '') {
            return [];
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $filtersParam)), 'strlen'));
        if (empty($tokens)) {
            return [];
        }

        $allNumeric = true;
        foreach ($tokens as $t) {
            if (!is_numeric($t)) {
                $allNumeric = false;
                break;
            }
        }
        if ($allNumeric) {
            return array_values(array_filter(array_map('intval', $tokens)));
        }

        return self::resolveAliasesToIds($tokens);
    }

    private static function loadAliasCatalog(): array
    {
        if (self::$aliasCatalog !== null) {
            return self::$aliasCatalog;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['f.j2commerce_filter_id', 'f.filter_name', 'g.group_name']))
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_filtergroups', 'g')
                . ' ON ' . $db->quoteName('g.j2commerce_filtergroup_id')
                . ' = ' . $db->quoteName('f.group_id')
            );
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $catalog = [];
        foreach ($rows as $row) {
            $catalog[] = [
                'id'           => (int) $row->j2commerce_filter_id,
                'filter_alias' => OutputFilter::stringURLSafe((string) ($row->filter_name ?? '')),
                'group_alias'  => OutputFilter::stringURLSafe((string) ($row->group_name ?? '')),
            ];
        }

        return self::$aliasCatalog = $catalog;
    }
}
