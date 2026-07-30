<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @author      J2Commerce Team
 * @copyright   Copyright (C) 2022 J2Commerce. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Model;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use J2Commerce\Helper\InventoryHelper;
use J2Commerce\Table\OrderTable;

/**
 * Model handling order related operations.
 */
class OrdersModel
{
    /** @var DatabaseDriver */
    protected $db;

    public function __construct()
    {
        $this->db = Factory::getDbo();
    }

    /**
     * Cancel unpaid orders whose hold period has expired.
     *
     * The method selects orders with a modified_on timestamp older than the
     * configured hold_stock window and whose order_state_id is either 4 (Pending)
     * or 5 (Incomplete). Orders in state 4 have already had their stock
     * reserved/deducted, therefore they must be restored when the order is
     * cancelled. Orders in state 5 never deducted stock, so they are simply
     * moved to the Cancelled state without any inventory changes.
     *
     * @return void
     */
    public function cancelUnpaidOrders()
    {
        // Retrieve the hold_stock configuration (in minutes).
        $params = Factory::getApplication()->getParams('com_j2commerce');
        $holdMinutes = (int) $params->get('hold_stock', 0);
        if ($holdMinutes <= 0) {
            // Feature disabled – nothing to do.
            return;
        }

        // Build the query to fetch orders that are older than the hold period
        // and are still in a pending or incomplete state.
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where($this->db->quoteName('order_state_id') . ' IN (4,5)')
            ->where($this->db->quoteName('modified_on') . ' < DATE_SUB(NOW(), INTERVAL ' . (int) $holdMinutes . ' MINUTE)');

        $orders = $this->db->setQuery($query)->loadObjectList();
        if (empty($orders)) {
            return;
        }

        foreach ($orders as $orderData) {
            // Load the full order table instance – this gives us access to the
            // OrderModel methods and ensures we work with a proper JTable object.
            /** @var OrderTable $order */
            $order = $this->db->getTable('OrderTable', 'J2Commerce\\Table\\');
            $order->load($orderData->id);

            // Determine whether we need to restore inventory. Only orders that
            // were in state 4 (stock already deducted) require a restore.
            $needsRestore = ((int) $order->order_state_id) === 4;

            // Update the order status to Cancelled (6). This uses the central
            // OrderModel method which also fires the appropriate events.
            $orderModel = new OrderModel();
            $orderModel->updateOrderStatus($order->id, 6);

            // If the order had its stock reserved, restore it now.
            if ($needsRestore) {
                // InventoryHelper::restoreOrderStock expects an OrderTable object.
                InventoryHelper::restoreOrderStock($order);
            }
        }
    }
}
