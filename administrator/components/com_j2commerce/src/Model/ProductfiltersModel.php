<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Product Filters Model
 *
 * Handles the junction table linking products to filters.
 * Provides methods to query, add, and remove product-filter associations.
 *
 * @since  6.0.0
 */
class ProductfiltersModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'product_id', 'pf.product_id',
                'filter_id', 'pf.filter_id',
                'filter_name', 'f.filter_name',
                'group_name', 'fg.group_name',
                'fg.ordering',
                'f.ordering',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState($ordering = 'fg.ordering', $direction = 'asc'): void
    {
        // Filter by product_id
        $productId = $this->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', 0, 'int');
        $this->setState('filter.product_id', $productId);

        // Filter by enabled filtergroups only
        $enabledOnly = $this->getUserStateFromRequest($this->context . '.filter.enabled_only', 'filter_enabled_only', 1, 'int');
        $this->setState('filter.enabled_only', $enabledOnly);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.0
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.product_id');
        $id .= ':' . $this->getState('filter.enabled_only');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select fields from the product_filters junction table
        $query->select([
            $db->quoteName('pf.product_id'),
            $db->quoteName('pf.filter_id'),
        ]);

        $query->from($db->quoteName('#__j2commerce_product_filters', 'pf'));

        // Join to filters table for filter_name and group_id
        $query->select([
            $db->quoteName('f.filter_name'),
            $db->quoteName('f.group_id'),
            $db->quoteName('f.ordering', 'filter_ordering'),
        ]);
        $query->leftJoin(
            $db->quoteName('#__j2commerce_filters', 'f') .
            ' ON ' . $db->quoteName('f.j2commerce_filter_id') . ' = ' . $db->quoteName('pf.filter_id')
        );

        // Join to filtergroups table for group_name
        $query->select([
            $db->quoteName('fg.group_name'),
            $db->quoteName('fg.ordering', 'group_ordering'),
            $db->quoteName('fg.enabled', 'group_enabled'),
        ]);
        $query->leftJoin(
            $db->quoteName('#__j2commerce_filtergroups', 'fg') .
            ' ON ' . $db->quoteName('fg.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id')
        );

        // Filter by product_id (single or array)
        $productId = $this->getState('filter.product_id');

        if (!empty($productId)) {
            if (\is_array($productId)) {
                $productId = array_map('intval', $productId);
                $query->whereIn($db->quoteName('pf.product_id'), $productId);
            } else {
                $productIdInt = (int) $productId;
                $query->where($db->quoteName('pf.product_id') . ' = :productId')
                    ->bind(':productId', $productIdInt, ParameterType::INTEGER);
            }
        }

        // Filter by enabled filtergroups only
        $enabledOnly = (int) $this->getState('filter.enabled_only', 1);

        if ($enabledOnly) {
            $query->where($db->quoteName('fg.enabled') . ' = 1');
        }

        // Group by filter_id to avoid duplicates
        $query->group($db->quoteName('pf.filter_id'));

        // Order by filtergroup ordering, then filter ordering
        $query->order($db->quoteName('fg.ordering') . ' ASC');
        $query->order($db->quoteName('f.ordering') . ' ASC');

        return $query;
    }

    /**
     * Get filters assigned to a specific product (grouped by filtergroup).
     *
     * This is the primary method used by templates to display product filters.
     *
     * @param   int  $productId  Product ID.
     *
     * @return  array  Associative array grouped by group_id with group_name and filters.
     *
     * @since   6.0.0
     */
    public function getFiltersByProduct(int $productId): array
    {
        return $this->getFiltersGroupedByGroup($productId);
    }

    /**
     * Get filters assigned to a specific product (flat list).
     *
     * @param   int|array  $productId  Product ID or array of product IDs.
     *
     * @return  array  Array of filter objects with group information.
     *
     * @since   6.0.0
     */
    public function getFiltersByProductId(int|array $productId): array
    {
        // CRITICAL: Force state initialization BEFORE setting programmatic values.
        // getState() triggers populateState() which reads from user session and sets __state_set = true.
        // Without this, getItems() would later call populateState() which could override
        // our programmatic product_id with a session-cached value from a previously viewed product.
        $this->getState();

        // Now safely override with programmatic values (won't be overwritten by session)
        $this->setState('filter.product_id', $productId);
        $this->setState('filter.enabled_only', 1);
        $this->setState('list.limit', 0); // No limit

        return $this->getItems() ?: [];
    }

    /**
     * Get filters grouped by filtergroup for display.
     *
     * @param   int  $productId  Product ID.
     *
     * @return  array  Associative array grouped by group_id.
     *
     * @since   6.0.0
     */
    public function getFiltersGroupedByGroup(int $productId): array
    {
        $filters = $this->getFiltersByProductId($productId);
        $grouped = [];

        foreach ($filters as $filter) {
            $groupId = (int) $filter->group_id;

            if (!isset($grouped[$groupId])) {
                $grouped[$groupId] = [
                    'group_name' => $filter->group_name,
                    'filters'    => [],
                ];
            }

            $grouped[$groupId]['filters'][] = $filter;
        }

        return $grouped;
    }

    /**
     * Add a filter to a product.
     *
     * @param   int  $productId  Product ID.
     * @param   int  $filterId   Filter ID.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function addFilterToProduct(int $productId, int $filterId): bool
    {
        if (!$productId || !$filterId) {
            return false;
        }

        $db = $this->getDatabase();

        // Check if already exists
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('filter_id') . ' = :filterId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->bind(':filterId', $filterId, ParameterType::INTEGER);

        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return true; // Already exists
        }

        // Insert new association
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_product_filters'))
            ->columns([
                $db->quoteName('product_id'),
                $db->quoteName('filter_id'),
            ])
            ->values(implode(',', [
                $db->quote($productId),
                $db->quote($filterId),
            ]));

        $db->setQuery($query);

        try {
            $db->execute();
            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Remove a filter from a product.
     *
     * @param   int  $productId  Product ID.
     * @param   int  $filterId   Filter ID.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function removeFilterFromProduct(int $productId, int $filterId): bool
    {
        if (!$productId || !$filterId) {
            return false;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('filter_id') . ' = :filterId')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->bind(':filterId', $filterId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $db->execute();
            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Remove all filters from a product.
     *
     * @param   int  $productId  Product ID.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function removeAllFiltersFromProduct(int $productId): bool
    {
        if (!$productId) {
            return false;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $db->execute();
            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Set filters for a product (replaces existing).
     *
     * @param   int    $productId  Product ID.
     * @param   array  $filterIds  Array of filter IDs.
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    public function setProductFilters(int $productId, array $filterIds): bool
    {
        if (!$productId) {
            return false;
        }

        // Remove all existing filters
        if (!$this->removeAllFiltersFromProduct($productId)) {
            return false;
        }

        // Add new filters
        foreach ($filterIds as $filterId) {
            $filterId = (int) $filterId;

            if ($filterId > 0) {
                $this->addFilterToProduct($productId, $filterId);
            }
        }

        return true;
    }

    /**
     * Search available filters by name.
     *
     * @param   string  $searchTerm  Search term.
     * @param   int     $limit       Maximum results.
     *
     * @return  array  Array of matching filters with group info.
     *
     * @since   6.0.0
     */
    public function searchFilters(string $searchTerm, int $limit = 20): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('f.j2commerce_filter_id'),
            $db->quoteName('f.filter_name'),
            $db->quoteName('fg.group_name'),
        ])
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_filtergroups', 'fg') .
                ' ON ' . $db->quoteName('f.group_id') . ' = ' . $db->quoteName('fg.j2commerce_filtergroup_id')
            );

        if (!empty($searchTerm)) {
            $search = '%' . $db->escape($searchTerm, true) . '%';
            $query->where(
                '(' . $db->quoteName('f.filter_name') . ' LIKE ' . $db->quote($search) .
                ' OR ' . $db->quoteName('fg.group_name') . ' LIKE ' . $db->quote($search) . ')'
            );
        }

        // Only enabled filtergroups
        $query->where($db->quoteName('fg.enabled') . ' = 1');

        $query->order($db->quoteName('fg.group_name') . ' ASC')
            ->order($db->quoteName('f.filter_name') . ' ASC')
            ->setLimit($limit);

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
