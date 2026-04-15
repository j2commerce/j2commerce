<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportItemised
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ReportItemised\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\BaseReportModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Report Itemised list model.
 *
 * Provides itemised order data grouped by product_id and orderitem_attributes,
 * with category name via content/categories JOINs, total quantity, and purchase count.
 *
 * Self-contained in the plugin directory — extends BaseReportModel which
 * handles form path resolution for the plugin's filter XML.
 *
 * @since  6.0.0
 */
class ReportitemisedModel extends BaseReportModel
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
                'oi.product_id',
                'oi.orderitem_name',
                'total_qty',
                'order_count',
            ];
        }

        parent::__construct($config);

        // Register plugin's forms directory for filter XML discovery
        $this->setFormPath(JPATH_PLUGINS . '/j2commerce/report_itemised/forms');
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
    protected function populateState($ordering = 'total_qty', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest(
            $this->context . '.filter.search',
            'filter_search',
            '',
            'string'
        );
        $this->setState('filter.search', $search);

        $orderstatus = $this->getUserStateFromRequest(
            $this->context . '.filter.orderstatus',
            'filter_orderstatus',
            '',
            'string'
        );
        $this->setState('filter.orderstatus', $orderstatus);

        $datetype = $this->getUserStateFromRequest(
            $this->context . '.filter.datetype',
            'filter_datetype',
            '',
            'string'
        );
        $this->setState('filter.datetype', $datetype);

        $fromDate = $this->getUserStateFromRequest(
            $this->context . '.filter.order_from_date',
            'filter_order_from_date',
            '',
            'string'
        );
        $this->setState('filter.order_from_date', $fromDate);

        $toDate = $this->getUserStateFromRequest(
            $this->context . '.filter.order_to_date',
            'filter_order_to_date',
            '',
            'string'
        );
        $this->setState('filter.order_to_date', $toDate);

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
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.orderstatus');
        $id .= ':' . $this->getState('filter.datetype');
        $id .= ':' . $this->getState('filter.order_from_date');
        $id .= ':' . $this->getState('filter.order_to_date');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * Groups by product_id + orderitem_attributes to show each product+option
     * combination as a separate row. Joins to products, content, and categories
     * tables to get the Joomla article category name.
     *
     * @return  QueryInterface
     *
     * @since   6.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $excludeType = 'subscription';

        $query->select([
                // MIN() wrappers for ONLY_FULL_GROUP_BY compatibility —
                // these columns are functionally dependent on the GROUP BY pair
                'MIN(' . $db->quoteName('oi.j2commerce_orderitem_id')
                    . ') AS ' . $db->quoteName('j2commerce_orderitem_id'),
                $db->quoteName('oi.product_id'),
                'MIN(' . $db->quoteName('oi.orderitem_name')
                    . ') AS ' . $db->quoteName('orderitem_name'),
                'MIN(' . $db->quoteName('oi.orderitem_sku')
                    . ') AS ' . $db->quoteName('orderitem_sku'),
                $db->quoteName('oi.orderitem_attributes'),
                'MIN(' . $db->quoteName('category.title')
                    . ') AS ' . $db->quoteName('category_name'),
                // orderitem_quantity is varchar(255) — CAST required for numeric SUM
                'SUM(CAST(' . $db->quoteName('oi.orderitem_quantity')
                    . ' AS UNSIGNED)) AS ' . $db->quoteName('total_qty'),
                'COUNT(' . $db->quoteName('oi.product_id')
                    . ') AS ' . $db->quoteName('order_count'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_products', 'product')
                . ' ON ' . $db->quoteName('product.j2commerce_product_id') . ' = ' . $db->quoteName('oi.product_id'))
            ->join('LEFT', $db->quoteName('#__content', 'cont')
                . ' ON ' . $db->quoteName('cont.id') . ' = ' . $db->quoteName('product.product_source_id'))
            ->join('LEFT', $db->quoteName('#__categories', 'category')
                . ' ON ' . $db->quoteName('category.id') . ' = ' . $db->quoteName('cont.catid'))
            ->where($db->quoteName('oi.orderitem_type') . ' != :excludeType')
            ->bind(':excludeType', $excludeType)
            ->group([
                $db->quoteName('oi.product_id'),
                $db->quoteName('oi.orderitem_attributes'),
            ]);

        // Apply filters
        $this->applySearchFilter($query);
        $this->applyOrderStatusFilter($query);

        // Apply date filter using base class helper
        $dateType = $this->getState('filter.datetype', '');

        if (!empty($dateType)) {
            $this->applyDateFilter($query, $dateType, 'o.created_on');
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'total_qty');
        $orderDir = $this->state->get('list.direction', 'DESC');

        // Whitelist order columns to prevent SQL injection
        $allowedOrderColumns = [
            'oi.product_id',
            'oi.orderitem_name',
            'total_qty',
            'order_count',
        ];

        if (!\in_array($orderCol, $allowedOrderColumns, true)) {
            $orderCol = 'total_qty';
        }

        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $query->order($db->escape($orderCol) . ' ' . $orderDir);

        // Secondary sort by order_id for deterministic results
        $query->order($db->quoteName('oi.order_id'));

        return $query;
    }

    /**
     * Get the total number of items (overridden for GROUP BY compatibility).
     *
     * Standard ListModel wraps the query in COUNT(*) which fails with GROUP BY.
     * We clone the list query to preserve JOINs, WHEREs, and bound parameters,
     * then replace SELECT/GROUP BY/ORDER BY with COUNT(DISTINCT ...).
     *
     * @return  int
     *
     * @since   6.0.0
     */
    public function getTotal(): int
    {
        $store = $this->getStoreId('getTotal');

        if (isset($this->cache[$store])) {
            return $this->cache[$store];
        }

        $db = $this->getDatabase();

        // Clone preserves JOINs, WHEREs, and — critically — bound parameters.
        $countQuery = clone $this->getListQuery();
        $countQuery->clear('select')
            ->clear('group')
            ->clear('order')
            ->select(
                'COUNT(DISTINCT ' . $db->quoteName('oi.product_id')
                . ', ' . $db->quoteName('oi.orderitem_attributes') . ')'
            );

        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        $this->cache[$store] = $total;

        return $total;
    }

    public function getItems(): array
    {
        $items = parent::getItems();

        if (empty($items)) {
            return [];
        }

        foreach ($items as $item) {
            $item->attributes = OrderItemAttributeHelper::parseRawAttributes(
                $item->orderitem_attributes ?? '',
                (int) $item->product_id
            );
        }

        return $items;
    }

    /**
     * Apply search filter on name, SKU, and product_id.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function applySearchFilter(QueryInterface $query): void
    {
        $search = $this->getState('filter.search');

        if (empty($search)) {
            return;
        }

        $db     = $this->getDatabase();
        $search = '%' . trim(strtolower($search)) . '%';

        $query->extendWhere(
            'AND',
            [
                'LOWER(' . $db->quoteName('oi.orderitem_name') . ') LIKE :searchName',
                'LOWER(' . $db->quoteName('oi.orderitem_sku') . ') LIKE :searchSku',
            ],
            'OR'
        )
            ->bind(':searchName', $search)
            ->bind(':searchSku', $search);
    }

    /**
     * Apply order status filter.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function applyOrderStatusFilter(QueryInterface $query): void
    {
        $orderstatus = $this->getState('filter.orderstatus');

        if (!empty($orderstatus) && is_numeric($orderstatus)) {
            $db       = $this->getDatabase();
            $statusId = (int) $orderstatus;
            $query->where($db->quoteName('o.order_state_id') . ' = :orderstatus')
                ->bind(':orderstatus', $statusId, ParameterType::INTEGER);
        }
    }
}
