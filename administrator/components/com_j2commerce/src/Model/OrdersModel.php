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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Orders list model class.
 *
 * Provides list functionality for orders including filtering by status, date range,
 * customer, payment method, and search across order ID, email, and customer name.
 *
 * @since  6.0.7
 */
class OrdersModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_order_id', 'a.j2commerce_order_id',
                'order_id', 'a.order_id',
                'invoice', 'invoice_number', 'a.invoice_number',
                'user_email', 'a.user_email',
                'user_id', 'a.user_id',
                'order_total', 'a.order_total',
                'order_state_id', 'a.order_state_id',
                'orderpayment_type', 'a.orderpayment_type',
                'created_on', 'a.created_on',
                'modified_on', 'a.modified_on',
                'billing_first_name', 'oi.billing_first_name',
                'billing_last_name', 'oi.billing_last_name',
                'orderstatus_name', 'os.orderstatus_name',
                'coupon_code',
                'amount_from', 'amount_to',
                'from_order_id', 'to_order_id',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.created_on', $direction = 'desc'): void
    {
        // Let parent handle all searchtools filter[*] and list[*] fields automatically.
        // Parent reads the filter array, sets filter.* states, and manages limitstart.
        // Manual getUserStateFromRequest calls for searchtools fields are redundant and
        // can cause limitstart to be reset to 0 on every request due to type mismatches
        // between default values and raw form strings (PHP 8+ strict comparison).
        parent::populateState($ordering, $direction);

        $app = Factory::getApplication();

        // Non-searchtools filters: read from URL query params only (not from filter[] form)
        $orderStatuses = $app->getInput()->get('orderstatus', [], 'array');
        $this->setState('filter.orderstatus', $orderStatuses);

        $this->setState('filter.token', $app->getInput()->getString('token', ''));
        $this->setState('filter.user_email', $app->getInput()->getString('user_email', ''));
        $this->setState('filter.nozero', $app->getInput()->getInt('nozero', 0));
        $this->setState('filter.parent_id', $app->getInput()->getInt('parent', 0));
        $this->setState('filter.moneysum', $app->getInput()->getInt('moneysum', 0));
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.order_state_id');
        $id .= ':' . serialize($this->getState('filter.orderstatus'));
        $id .= ':' . $this->getState('filter.payment_type');
        $id .= ':' . $this->getState('filter.user_id');
        $id .= ':' . $this->getState('filter.since');
        $id .= ':' . $this->getState('filter.until');
        $id .= ':' . $this->getState('filter.from_invoice');
        $id .= ':' . $this->getState('filter.to_invoice');
        $id .= ':' . $this->getState('filter.coupon_code');
        $id .= ':' . $this->getState('filter.amount_from');
        $id .= ':' . $this->getState('filter.amount_to');
        $id .= ':' . $this->getState('filter.from_order_id');
        $id .= ':' . $this->getState('filter.to_order_id');
        $id .= ':' . $this->getState('filter.token');
        $id .= ':' . $this->getState('filter.user_email');
        $id .= ':' . $this->getState('filter.parent_id');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select order fields
        $query->select([
            $db->quoteName('a.j2commerce_order_id'),
            $db->quoteName('a.order_id'),
            $db->quoteName('a.order_type'),
            $db->quoteName('a.parent_id'),
            $db->quoteName('a.invoice_prefix'),
            $db->quoteName('a.invoice_number'),
            $db->quoteName('a.token'),
            $db->quoteName('a.user_id'),
            $db->quoteName('a.user_email'),
            $db->quoteName('a.order_total'),
            $db->quoteName('a.order_subtotal'),
            $db->quoteName('a.order_tax'),
            $db->quoteName('a.order_shipping'),
            $db->quoteName('a.order_shipping_tax'),
            $db->quoteName('a.order_discount'),
            $db->quoteName('a.order_surcharge'),
            $db->quoteName('a.orderpayment_type'),
            $db->quoteName('a.transaction_id'),
            $db->quoteName('a.transaction_status'),
            $db->quoteName('a.currency_code'),
            $db->quoteName('a.currency_value'),
            $db->quoteName('a.is_shippable'),
            $db->quoteName('a.customer_note'),
            $db->quoteName('a.order_state_id'),
            $db->quoteName('a.order_state'),
            $db->quoteName('a.created_on'),
            $db->quoteName('a.modified_on'),
        ]);

        // Computed invoice field
        $query->select(
            'CASE WHEN ' . $db->quoteName('a.invoice_prefix') . ' IS NULL OR ' .
            $db->quoteName('a.invoice_number') . ' = 0 THEN ' .
            $db->quoteName('a.j2commerce_order_id') .
            ' ELSE CONCAT(' . $db->quoteName('a.invoice_prefix') . ', ' .
            $db->quoteName('a.invoice_number') . ') END AS ' . $db->quoteName('invoice')
        );

        $query->from($db->quoteName('#__j2commerce_orders', 'a'));

        // Join order status
        $query->select([
            $db->quoteName('os.orderstatus_name'),
            $db->quoteName('os.orderstatus_cssclass'),
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_orderstatuses', 'os') .
            ' ON ' . $db->quoteName('a.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id')
        );

        // Join order info for billing name
        $query->select([
            $db->quoteName('oi.billing_first_name'),
            $db->quoteName('oi.billing_last_name'),
            $db->quoteName('oi.billing_company'),
            $db->quoteName('oi.billing_phone_1'),
            $db->quoteName('oi.billing_city'),
            $db->quoteName('oi.billing_country_name'),
            $db->quoteName('oi.billing_zone_name'),
            $db->quoteName('oi.shipping_first_name'),
            $db->quoteName('oi.shipping_last_name'),
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_orderinfos', 'oi') .
            ' ON ' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('oi.order_id')
        );

        // Join order discounts for coupon info
        $query->select($db->quoteName('od.discount_code'));
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_orderdiscounts', 'od') .
            ' ON ' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('od.order_id') .
            ' AND ' . $db->quoteName('od.discount_type') . ' = ' . $db->quote('coupon')
        );

        // Join order shipping for shipping info
        $query->select([
            $db->quoteName('osh.ordershipping_name'),
            $db->quoteName('osh.ordershipping_tracking_id'),
        ]);
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_ordershippings', 'osh') .
            ' ON ' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('osh.order_id')
        );

        // Join extensions for payment plugin display name
        // Handle both short (payment_cash) and full format (plg_j2commerce_payment_cash)
        $query->select($db->quoteName('ext.name', 'payment_plugin_name'));
        $query->join(
            'LEFT',
            $db->quoteName('#__extensions', 'ext') .
            ' ON ' . $db->quoteName('ext.element') .
            ' = REPLACE(' . $db->quoteName('a.orderpayment_type') . ', ' . $db->quote('plg_j2commerce_') . ', ' . $db->quote('') . ')' .
            ' AND ' . $db->quoteName('ext.folder') . ' = ' . $db->quote('j2commerce') .
            ' AND ' . $db->quoteName('ext.type') . ' = ' . $db->quote('plugin')
        );

        // Build WHERE clause
        $this->buildWhereClause($query);

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'a.created_on');
        $orderDir = $this->state->get('list.direction', 'DESC');

        // Validate ordering column is in filter_fields
        if (!\in_array($orderCol, $this->filter_fields)) {
            $orderCol = 'a.created_on';
        }
        if (!\in_array(strtoupper($orderDir), ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Build WHERE clause for order list query.
     */
    protected function buildWhereClause(QueryInterface $query): void
    {
        $db = $this->getDatabase();

        // Parent/child order filter
        $parentId = (int) $this->getState('filter.parent_id', 0);
        if ($parentId > 0) {
            $query->where($db->quoteName('a.parent_id') . ' = :parentId')
                ->bind(':parentId', $parentId, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('a.order_type') . ' = ' . $db->quote('normal'));
        }

        // Order status filter (single value)
        $orderStateId = $this->getState('filter.order_state_id');
        if (is_numeric($orderStateId) && $orderStateId > 0) {
            $stateId = (int) $orderStateId;
            $query->where($db->quoteName('a.order_state_id') . ' = :orderStateId')
                ->bind(':orderStateId', $stateId, ParameterType::INTEGER);
        }

        // Multiple order statuses filter (array)
        $orderStatuses = $this->getState('filter.orderstatus', []);
        if (!empty($orderStatuses) && \is_array($orderStatuses)) {
            if (!\in_array('*', $orderStatuses)) {
                $statusIds = array_map('intval', $orderStatuses);
                $query->whereIn($db->quoteName('a.order_state_id'), $statusIds);
            }
        }

        // Payment type filter (exact match on plugin element name)
        $paymentType = $this->getState('filter.payment_type');
        if (!empty($paymentType)) {
            $query->where($db->quoteName('a.orderpayment_type') . ' = :paymentType')
                ->bind(':paymentType', $paymentType);
        }

        // User ID filter
        $userId = (int) $this->getState('filter.user_id', 0);
        if ($userId > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        // Token filter (for guest order lookup)
        $token = $this->getState('filter.token');
        if (!empty($token)) {
            $query->where($db->quoteName('a.token') . ' = :token')
                ->bind(':token', $token);
        }

        // User email filter
        $userEmail = $this->getState('filter.user_email');
        if (!empty($userEmail)) {
            $query->where($db->quoteName('a.user_email') . ' = :userEmail')
                ->bind(':userEmail', $userEmail);
        }

        // Date range: since
        $since = trim((string) $this->getState('filter.since', ''));
        if (!empty($since) && $since !== '0000-00-00' && $since !== '0000-00-00 00:00:00') {
            $sinceDate = $this->convertTimeToUtc($since);
            $query->where($db->quoteName('a.created_on') . ' >= :since')
                ->bind(':since', $sinceDate);
        }

        // Date range: until
        $until = trim((string) $this->getState('filter.until', ''));
        if (!empty($until) && $until !== '0000-00-00' && $until !== '0000-00-00 00:00:00') {
            $untilDate = $this->convertTimeToUtc($until);
            $query->where($db->quoteName('a.created_on') . ' <= :until')
                ->bind(':until', $untilDate);
        }

        // No zero totals
        $noZero = (int) $this->getState('filter.nozero', 0);
        if ($noZero > 0) {
            $query->where($db->quoteName('a.order_total') . ' > 0');
        }

        // Invoice number range: from
        $fromInvoice = (int) $this->getState('filter.from_invoice', 0);
        if ($fromInvoice > 0) {
            $query->where(
                'CASE WHEN ' . $db->quoteName('a.invoice_number') . ' = 0 THEN ' .
                $db->quoteName('a.j2commerce_order_id') . ' >= :fromInvoice1 ELSE ' .
                $db->quoteName('a.invoice_number') . ' >= :fromInvoice2 END'
            )
                ->bind(':fromInvoice1', $fromInvoice, ParameterType::INTEGER)
                ->bind(':fromInvoice2', $fromInvoice, ParameterType::INTEGER);
        }

        // Invoice number range: to
        $toInvoice = (int) $this->getState('filter.to_invoice', 0);
        if ($toInvoice > 0) {
            $query->where(
                'CASE WHEN ' . $db->quoteName('a.invoice_number') . ' = 0 THEN ' .
                $db->quoteName('a.j2commerce_order_id') . ' <= :toInvoice1 ELSE ' .
                $db->quoteName('a.invoice_number') . ' <= :toInvoice2 END'
            )
                ->bind(':toInvoice1', $toInvoice, ParameterType::INTEGER)
                ->bind(':toInvoice2', $toInvoice, ParameterType::INTEGER);
        }

        // Coupon code filter
        $couponCode = $this->getState('filter.coupon_code');
        if (!empty($couponCode)) {
            $couponLike = '%' . $couponCode . '%';
            $query->where($db->quoteName('od.discount_code') . ' LIKE :couponCode')
                ->bind(':couponCode', $couponLike);
            $query->where($db->quoteName('od.discount_type') . ' = ' . $db->quote('coupon'));
        }

        // Amount range: from
        $amountFrom = (float) $this->getState('filter.amount_from', 0);
        if ($amountFrom > 0) {
            $query->where($db->quoteName('a.order_total') . ' >= :amountFrom')
                ->bind(':amountFrom', $amountFrom);
        }

        // Amount range: to
        $amountTo = (float) $this->getState('filter.amount_to', 0);
        if ($amountTo > 0) {
            $query->where($db->quoteName('a.order_total') . ' <= :amountTo')
                ->bind(':amountTo', $amountTo);
        }

        // Order ID range: from
        $fromOrderId = (int) $this->getState('filter.from_order_id', 0);
        if ($fromOrderId > 0) {
            $query->where($db->quoteName('a.j2commerce_order_id') . ' >= :fromOrderId')
                ->bind(':fromOrderId', $fromOrderId, ParameterType::INTEGER);
        }

        // Order ID range: to
        $toOrderId = (int) $this->getState('filter.to_order_id', 0);
        if ($toOrderId > 0) {
            $query->where($db->quoteName('a.j2commerce_order_id') . ' <= :toOrderId')
                ->bind(':toOrderId', $toOrderId, ParameterType::INTEGER);
        }

        // Search filter
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = trim($search);

            // Check if search looks like an email
            if (filter_var($search, FILTER_VALIDATE_EMAIL) || str_contains($search, '@')) {
                $query->where($db->quoteName('a.user_email') . ' = :searchEmail')
                    ->bind(':searchEmail', $search);
            } else {
                $searchLike   = '%' . $search . '%';
                $searchQuoted = $db->quote($searchLike);
                $query->where(
                    '(' .
                    $db->quoteName('a.order_id') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.j2commerce_order_id') . ' LIKE :search2 OR ' .
                    $db->quoteName('a.user_email') . ' LIKE :search3 OR ' .
                    $db->quoteName('a.order_state') . ' LIKE :search4 OR ' .
                    $db->quoteName('a.orderpayment_type') . ' LIKE :search5 OR ' .
                    'CONCAT(' . $db->quoteName('oi.billing_first_name') . ', ' . $db->quote(' ') . ', ' .
                    $db->quoteName('oi.billing_last_name') . ') LIKE :search6 OR ' .
                    $db->quoteName('oi.billing_first_name') . ' LIKE :search7 OR ' .
                    $db->quoteName('oi.billing_last_name') . ' LIKE :search8 OR ' .
                    'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__j2commerce_orderitems', 'oitem') .
                    ' LEFT JOIN ' . $db->quoteName('#__j2commerce_variants', 'v') .
                    ' ON ' . $db->quoteName('oitem.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id') .
                    ' WHERE ' . $db->quoteName('oitem.order_id') . ' = ' . $db->quoteName('a.order_id') .
                    ' AND (' .
                        $db->quoteName('oitem.orderitem_name') . ' LIKE ' . $searchQuoted . ' OR ' .
                        $db->quoteName('oitem.orderitem_sku') . ' LIKE ' . $searchQuoted . ' OR ' .
                        $db->quoteName('v.upc') . ' LIKE ' . $searchQuoted .
                    '))' .
                    ')'
                )
                    ->bind(':search1', $searchLike)
                    ->bind(':search2', $searchLike)
                    ->bind(':search3', $searchLike)
                    ->bind(':search4', $searchLike)
                    ->bind(':search5', $searchLike)
                    ->bind(':search6', $searchLike)
                    ->bind(':search7', $searchLike)
                    ->bind(':search8', $searchLike);
            }
        }
    }

    /**
     * Get total order count or sum based on current filters.
     *
     * @param   bool  $sum  If true, return sum of order_total instead of count.
     *
     * @return  float|int  Order count or sum.
     */
    public function getOrdersTotal(bool $sum = false): float|int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        if ($sum) {
            $query->select('SUM(' . $db->quoteName('a.order_total') . ')');
        } else {
            $query->select('COUNT(*)');
        }

        $query->from($db->quoteName('#__j2commerce_orders', 'a'));

        // Join tables needed for filters
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_orderinfos', 'oi') .
            ' ON ' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('oi.order_id')
        );
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_orderdiscounts', 'od') .
            ' ON ' . $db->quoteName('a.order_id') . ' = ' . $db->quoteName('od.order_id')
        );

        $this->buildWhereClause($query);

        $db->setQuery($query);

        return $sum ? (float) $db->loadResult() : (int) $db->loadResult();
    }

    /**
     * Convert local datetime to UTC.
     */
    protected function convertTimeToUtc(string $datetime, string $format = 'Y-m-d H:i:s'): string
    {
        $tz   = Factory::getApplication()->get('offset', 'UTC');
        $date = Factory::getDate($datetime, $tz);
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format($format);
    }

    /**
     * Cancel unpaid orders that have exceeded the hold duration.
     *
     * Orders in pending (4) or incomplete (5) status that haven't been modified
     * within the configured hold_stock duration will be cancelled.
     *
     * @return  int  Number of orders cancelled.
     */
    public function cancelUnpaidOrders(): int
    {
        $params = ComponentHelper::getParams('com_j2commerce');

        $heldDuration     = (int) $params->get('hold_stock', 0);
        $inventoryEnabled = (int) $params->get('enable_inventory', 0);

        // Skip if hold stock is disabled or inventory management is off
        if ($heldDuration < 1 || $inventoryEnabled !== 1) {
            return 0;
        }

        $db         = $this->getDatabase();
        $now        = Factory::getDate();
        $cutoffTime = Factory::getDate('-' . $heldDuration . ' minutes')->toSql();

        // Find unpaid orders (pending=4, incomplete=5) older than hold duration
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('modified_on') . ' < :cutoff')
            ->where($db->quoteName('order_type') . ' = ' . $db->quote('normal'))
            ->where($db->quoteName('order_state_id') . ' IN (4, 5)')
            ->bind(':cutoff', $cutoffTime);

        $db->setQuery($query);
        $unpaidOrders = $db->loadColumn();

        if (empty($unpaidOrders)) {
            return 0;
        }

        $cancelledCount = 0;

        // Process each unpaid order
        // Note: Full order cancellation requires OrderModel which handles
        // status update, stock restoration, and customer notification.
        // This is a placeholder for the actual cancellation logic.
        foreach ($unpaidOrders as $orderId) {
            // TODO: Implement via OrderModel::cancel() when available
            // For now, just update status to cancelled (6)
            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orders'))
                ->set($db->quoteName('order_state_id') . ' = 6')
                ->set($db->quoteName('order_state') . ' = ' . $db->quote('CANCELLED'))
                ->set($db->quoteName('modified_on') . ' = :now')
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':now', $now->toSql())
                ->bind(':orderId', $orderId);

            $db->setQuery($updateQuery);

            if ($db->execute()) {
                $cancelledCount++;
            }
        }

        return $cancelledCount;
    }

    public function getPendingCount(): int
    {
        $db              = $this->getDatabase();
        $pendingStatusId = 4;

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_state_id') . ' = :statusId')
            ->bind(':statusId', $pendingStatusId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }
}
