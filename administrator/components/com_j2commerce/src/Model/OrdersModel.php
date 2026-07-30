<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_j2commerce
 *
 * @copyright   Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use J2Commerce\Helper\InventoryHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\Exception\ExecutionException;

/**
 * Orders model.
 */
class OrdersModel extends \Joomla\CMS\MVC\Model\BaseModel
{
    /**
     * Cancel unpaid orders whose hold period has expired.
     *
     * The hold_stock configuration defines a time window (in minutes) after which an
     * unpaid order should be cancelled.  When an order is cancelled we must also
     * restore any stock that was reserved for that order.  Only orders that were in
     * state **4** (stock was deducted) should have their inventory restored – orders
     * in state **5** never deducted stock and must be left untouched.
     *
     * @return void
     * @throws ExecutionException
     */
    public function cancelUnpaidOrders(): void
    {
        // Get the hold period (minutes). If it is zero or not set we do nothing.
        $params = ComponentHelper::getParams('com_j2commerce');
        $hold   = (int) $params->get('hold_stock', 0);
        if ($hold <= 0) {
            return;
        }

        // Determine the cutoff date – orders older than this should be cancelled.
        $cutoff = Factory::getDate()->sub(new \DateInterval('PT' . $hold . 'M'))->toSql();

        /** @var DatabaseDriver $db */
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('modified_on') . ' < ' . $db->quote($cutoff))
            ->where($db->quoteName('order_state_id') . ' IN (4,5)');
        $db->setQuery($query);
        $orderIds = $db->loadColumn();

        if (empty($orderIds)) {
            return;
        }

        // Use the OrderModel to update the status – this fires the appropriate events
        // and writes an order‑history entry.
        $orderModel = new OrderModel();

        foreach ($orderIds as $orderId) {
            // Load the full order record – we need the original state to decide whether
            // stock was deducted.
            $order = $this->getItem($orderId);
            if (!$order) {
                continue;
            }

            // State 4 (e.g. "Pending") had its stock reserved and therefore must be
            // restored before we move the order to the Cancelled state.
            if ((int) $order->order_state_id === 4) {
                // Explicitly restore the reserved stock.  This mirrors the behaviour of
                // OrderTable::store() which calls InventoryHelper::restoreOrderStock().
                InventoryHelper::restoreOrderStock($order);
            }

            // Finally, move the order to the Cancelled state (6).  The updateOrderStatus()
            // method handles event dispatching and order‑history logging.
            $orderModel->updateOrderStatus((int) $orderId, 6);
        }
    }

    // ---------------------------------------------------------------------
    // Existing methods of the class remain unchanged – they are omitted here
    // for brevity.  The only change introduced is the call to
    // InventoryHelper::restoreOrderStock() for orders that were in state 4.
    // ---------------------------------------------------------------------
}
?>