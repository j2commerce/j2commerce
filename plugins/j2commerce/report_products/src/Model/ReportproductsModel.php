<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ReportProducts
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ReportProducts\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Model\BaseReportModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Report Products list model.
 *
 * Provides aggregated product sales data from order items, with filters
 * for SKU search, order status, and date range presets.
 *
 * Self-contained in the plugin directory — extends BaseReportModel which
 * handles form path resolution for the plugin's filter XML.
 *
 * @since  6.0.0
 */
class ReportproductsModel extends BaseReportModel
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
                'orderitem_name',
                'total_qty',
                'total_item_discount',
                'total_item_tax',
                'total_final_price_without_tax',
                'total_final_price_with_tax',
            ];
        }

        parent::__construct($config);

        // Register plugin's forms directory for filter XML discovery
        $this->setFormPath(JPATH_PLUGINS . '/j2commerce/report_products/forms');
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
    protected function populateState($ordering = 'total_final_price_with_tax', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $orderstatus = $this->getUserStateFromRequest($this->context . '.filter.orderstatus', 'filter_orderstatus', '', 'string');
        $this->setState('filter.orderstatus', $orderstatus);

        $datetype = $this->getUserStateFromRequest($this->context . '.filter.datetype', 'filter_datetype', '', 'string');
        $this->setState('filter.datetype', $datetype);

        $fromDate = $this->getUserStateFromRequest($this->context . '.filter.order_from_date', 'filter_order_from_date', '', 'string');
        $this->setState('filter.order_from_date', $fromDate);

        $toDate = $this->getUserStateFromRequest($this->context . '.filter.order_to_date', 'filter_order_to_date', '', 'string');
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
                $db->quoteName('oi.orderitem_name'),
                $db->quoteName('oi.orderitem_sku'),
                // orderitem_quantity is varchar(255) in the schema — CAST required for numeric SUM
                'SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS UNSIGNED)) AS ' . $db->quoteName('total_qty'),
                'SUM(' . $db->quoteName('oi.orderitem_finalprice_without_tax') . ') AS ' . $db->quoteName('total_final_price_without_tax'),
                'SUM(' . $db->quoteName('oi.orderitem_tax') . ') AS ' . $db->quoteName('total_item_tax'),
                'SUM(' . $db->quoteName('oi.orderitem_discount') . ') AS ' . $db->quoteName('total_item_discount'),
                'SUM(' . $db->quoteName('oi.orderitem_discount_tax') . ') AS ' . $db->quoteName('total_item_discount_tax'),
                'SUM(' . $db->quoteName('oi.orderitem_finalprice_with_tax') . ') AS ' . $db->quoteName('total_final_price_with_tax'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o') . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('oi.order_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderstatuses', 'os') . ' ON ' . $db->quoteName('o.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id'))
            ->where($db->quoteName('oi.orderitem_type') . ' != :excludeType')
            ->bind(':excludeType', $excludeType)
            ->group($db->quoteName('oi.variant_id'));

        // Apply filters
        $this->applySearchFilter($query);
        $this->applyOrderStatusFilter($query);

        // Apply date filter using base class helper
        $dateType = $this->getState('filter.datetype', '');
        if (!empty($dateType)) {
            $this->applyDateFilter($query, $dateType, 'o.created_on');
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'total_final_price_with_tax');
        $orderDir = $this->state->get('list.direction', 'DESC');

        // Whitelist order columns to prevent SQL injection
        $allowedOrderColumns = [
            'orderitem_name',
            'total_qty',
            'total_item_discount',
            'total_item_tax',
            'total_final_price_without_tax',
            'total_final_price_with_tax',
        ];

        if (!\in_array($orderCol, $allowedOrderColumns, true)) {
            $orderCol = 'total_final_price_with_tax';
        }

        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $query->order($db->quoteName($orderCol) . ' ' . $orderDir);

        return $query;
    }

    /**
     * Get the total number of items (overridden for GROUP BY compatibility).
     *
     * Standard ListModel wraps the query in COUNT(*) which fails with GROUP BY.
     * We clone the query and use COUNT(DISTINCT variant_id) to preserve bindings.
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

        // Clone the list query to preserve JOINs, WHEREs, and bound parameters.
        // Replace SELECT/GROUP BY/ORDER BY with COUNT(DISTINCT variant_id).
        $countQuery = clone $this->getListQuery();
        $countQuery->clear('select')
            ->clear('group')
            ->clear('order')
            ->select('COUNT(DISTINCT ' . $db->quoteName('oi.variant_id') . ')');

        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        $this->cache[$store] = $total;

        return $total;
    }

    /**
     * Apply SKU search filter.
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

        if (!empty($search)) {
            $db     = $this->getDatabase();
            $search = '%' . trim(strtolower($search)) . '%';
            $query->where('LOWER(' . $db->quoteName('oi.orderitem_sku') . ') LIKE :search')
                ->bind(':search', $search);
        }
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
            $db        = $this->getDatabase();
            $statusId  = (int) $orderstatus;
            $query->where($db->quoteName('o.order_state_id') . ' = :orderstatus')
                ->bind(':orderstatus', $statusId, ParameterType::INTEGER);
        }
    }
}
