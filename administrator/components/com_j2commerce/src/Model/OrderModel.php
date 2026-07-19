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

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\DownloadHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\InventoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\TaxHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Order item model class.
 *
 * Provides single order functionality including retrieving order details,
 * related items, addresses, taxes, shipping, discounts, and status management.
 *
 * @since  6.0.7
 */
class OrderModel extends AdminModel
{
    public $typeAlias = 'com_j2commerce.order';

    protected $text_prefix = 'COM_J2COMMERCE_ORDER';

    /**
     * @var array|null Cached order items
     */
    protected ?array $orderItems = null;

    /**
     * @var object|null Cached order info (billing/shipping)
     */
    protected ?object $orderInfo = null;

    public function delete(&$pks): bool
    {
        $db = $this->getDatabase();

        foreach ($pks as $pk) {
            $pk = (int) $pk;

            // Get the varchar order_id for this PK
            $query = $db->getQuery(true)
                ->select($db->quoteName('order_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $pk, ParameterType::INTEGER);
            $db->setQuery($query);
            $orderId = $db->loadResult();

            if (!$orderId) {
                continue;
            }

            // Delete orderitemattributes - get orderitem IDs first to avoid subquery binding issue
            $orderitemIdsQuery = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_orderitem_id'))
                ->from($db->quoteName('#__j2commerce_orderitems'))
                ->where($db->quoteName('order_id') . ' = :oid')
                ->bind(':oid', $orderId);
            $db->setQuery($orderitemIdsQuery);
            $orderitemIds = $db->loadColumn();

            if (!empty($orderitemIds)) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                        ->whereIn($db->quoteName('orderitem_id'), $orderitemIds, ParameterType::INTEGER)
                );
                $db->execute();
            }

            // Delete from related tables that use order_id (varchar) FK
            $relatedTables = [
                '#__j2commerce_orderitems',
                '#__j2commerce_orderinfos',
                '#__j2commerce_orderhistories',
                '#__j2commerce_ordershippings',
                '#__j2commerce_orderdiscounts',
                '#__j2commerce_orderfees',
                '#__j2commerce_ordertaxes',
                '#__j2commerce_orderdownloads',
            ];

            foreach ($relatedTables as $table) {
                $delQuery = $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName('order_id') . ' = :orderId')
                    ->bind(':orderId', $orderId);
                $db->setQuery($delQuery);
                $db->execute();
            }
        }

        // Delete the main order rows
        return parent::delete($pks);
    }

    protected function populateState(): void
    {
        $app = Factory::getApplication();

        // Check for 'id' first (standard Joomla), then 'j2commerce_order_id'
        $pk = $app->getInput()->getInt('id', 0);

        if ($pk === 0) {
            $pk = $app->getInput()->getInt('j2commerce_order_id', 0);
        }

        $this->setState($this->getName() . '.id', $pk);

        $params = ComponentHelper::getParams('com_j2commerce');
        $this->setState('params', $params);
    }

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_j2commerce.order', 'order', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    public function getTable($name = 'Order', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    protected function loadFormData(): mixed
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.order.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_j2commerce.order', $data);

        return $data;
    }

    /**
     * Get a single order with all related data.
     *
     * Returns the order record enriched with:
     * - Order status name and CSS class
     * - Order items
     * - Billing and shipping info
     * - Order taxes
     * - Order shipping details
     * - Order discounts/coupons
     * - Order history
     * - Invoice number (computed)
     *
     * @param   int|null  $pk  The order primary key (j2commerce_order_id).
     *
     * @return  object|false  Order object or false on failure.
     */
    public function getItem($pk = null): object|false
    {
        if ($pk === null) {
            $pk = (int) $this->getState($this->getName() . '.id');
        }

        $item = parent::getItem($pk);

        if ($item === false || empty($item->j2commerce_order_id)) {
            return $item;
        }

        // Add order status info
        $item->orderstatus_name     = '';
        $item->orderstatus_cssclass = '';

        if (!empty($item->order_state_id)) {
            $status = $this->getOrderStatus((int) $item->order_state_id);
            if ($status) {
                $item->orderstatus_name     = $status->orderstatus_name;
                $item->orderstatus_cssclass = $status->orderstatus_cssclass;
            }
        }

        // Compute invoice number
        $item->invoice = $this->getInvoiceNumber($item);

        // Load related data
        $orderId              = $item->order_id;
        $item->orderitems     = $this->getOrderItems($orderId);
        $item->orderinfo      = $this->getOrderInfo($orderId);
        $item->ordertaxes     = $this->getOrderTaxes($orderId);
        $item->ordershipping  = $this->getOrderShipping($orderId);
        $item->orderdiscounts = $this->getOrderDiscounts($orderId);
        $item->orderfees      = $this->getOrderFees($orderId);
        $item->orderhistory   = $this->getOrderHistory($orderId);

        return $item;
    }

    /**
     * Get order status by ID.
     */
    protected function getOrderStatus(int $statusId): ?object
    {
        static $statuses = [];

        if (!isset($statuses[$statusId])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('orderstatus_name'),
                    $db->quoteName('orderstatus_cssclass'),
                ])
                ->from($db->quoteName('#__j2commerce_orderstatuses'))
                ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :statusId')
                ->bind(':statusId', $statusId, ParameterType::INTEGER);

            $db->setQuery($query);
            $statuses[$statusId] = $db->loadObject();
        }

        return $statuses[$statusId];
    }

    /**
     * Get order items for a given order.
     *
     * @param   string  $orderId  The order_id (varchar field).
     *
     * @return  array  Array of order item objects.
     */
    public function getOrderItems(string $orderId): array
    {
        if (empty($orderId)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_orderitem_id') . ' ASC')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        // Parse item attributes for each item
        foreach ($items as $item) {
            $item->orderitemattributes = OrderItemAttributeHelper::parseRawAttributes(
                $item->orderitem_attributes ?? '',
                (int) ($item->product_id ?? 0)
            );
        }

        return $items;
    }

    /**
     * Get order billing/shipping info.
     *
     * @param   string  $orderId  The order_id.
     * @param   string  $type     Optional: 'billing', 'shipping', or null for both.
     *
     * @return  object|null  Order info object.
     */
    public function getOrderInfo(string $orderId, ?string $type = null): ?object
    {
        if (empty($orderId)) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderinfos'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $info = $db->loadObject();

        if (!$info) {
            return null;
        }

        // Parse custom field JSON data
        foreach (['all_billing', 'all_shipping', 'all_payment'] as $field) {
            if (!empty($info->$field)) {
                try {
                    $info->{$field . '_parsed'} = new Registry(stripslashes($info->$field));
                } catch (\Exception $e) {
                    $info->{$field . '_parsed'} = new Registry();
                }
            }
        }

        return $info;
    }

    /**
     * Get order tax breakdown.
     *
     * @param   string  $orderId  The order_id.
     *
     * @return  array  Array of order tax objects.
     */
    public function getOrderTaxes(string $orderId): array
    {
        if (empty($orderId)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_ordertaxes'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_ordertax_id') . ' ASC')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get order shipping details.
     *
     * @param   string  $orderId  The order_id.
     *
     * @return  object|null  Order shipping object.
     */
    public function getOrderShipping(string $orderId): ?object
    {
        if (empty($orderId)) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Get order discounts/coupons.
     *
     * @param   string       $orderId  The order_id.
     * @param   string|null  $type     Optional: 'coupon', 'voucher', etc.
     *
     * @return  array  Array of order discount objects.
     */
    public function getOrderDiscounts(string $orderId, ?string $type = null): array
    {
        if (empty($orderId)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_orderdiscount_id') . ' ASC')
            ->bind(':orderId', $orderId);

        if ($type !== null) {
            $query->where($db->quoteName('discount_type') . ' = :type')
                ->bind(':type', $type);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getOrderFees(string $orderId): array
    {
        if (empty($orderId)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_orderfee_id') . ' ASC')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get order status change history.
     *
     * @param   string  $orderId  The order_id.
     *
     * @return  array  Array of order history objects.
     */
    public function getOrderHistory(string $orderId): array
    {
        if (empty($orderId)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('oh') . '.*',
                $db->quoteName('os.orderstatus_name'),
                $db->quoteName('os.orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderhistories', 'oh'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_orderstatuses', 'os') .
                ' ON ' . $db->quoteName('oh.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id')
            )
            ->where($db->quoteName('oh.order_id') . ' = :orderId')
            ->order($db->quoteName('oh.created_on') . ' DESC')
            ->order($db->quoteName('oh.j2commerce_orderhistory_id') . ' DESC')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getOrderHistoryPaginated(string $orderId, int $offset, int $limit): array
    {
        if (empty($orderId)) {
            return ['items' => [], 'total' => 0];
        }

        $db = $this->getDatabase();

        $countQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orderhistories'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);
        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('oh') . '.*',
                $db->quoteName('os.orderstatus_name'),
                $db->quoteName('os.orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderhistories', 'oh'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_orderstatuses', 'os') .
                ' ON ' . $db->quoteName('oh.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id')
            )
            ->where($db->quoteName('oh.order_id') . ' = :orderId')
            ->order($db->quoteName('oh.created_on') . ' DESC')
            ->order($db->quoteName('oh.j2commerce_orderhistory_id') . ' DESC')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query, $offset, $limit);

        return [
            'items' => $db->loadObjectList() ?: [],
            'total' => $total,
        ];
    }

    /**
     * Get formatted invoice number for an order.
     *
     * @param   object  $order  The order object.
     *
     * @return  string  Formatted invoice number.
     */
    public function getInvoiceNumber(object $order): string
    {
        $orderId = (string) ($order->j2commerce_order_id ?? '');

        if ($orderId === '') {
            return '';
        }

        return ($order->invoice_prefix ?? '') . $orderId;
    }

    /**
     * Generate the next invoice number for a new order.
     *
     * @return  array  Array with 'prefix' and 'number' keys.
     */
    public function generateInvoiceNumber(): array
    {
        $params = ComponentHelper::getParams('com_j2commerce');
        $prefix = $params->get('invoice_prefix', 'INV-');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('invoice_number') . ')')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('invoice_prefix') . ' = :prefix')
            ->bind(':prefix', $prefix);

        $db->setQuery($query);
        $lastNumber = (int) $db->loadResult();

        return [
            'prefix' => $prefix,
            'number' => $lastNumber + 1,
        ];
    }

    /**
     * Update order status.
     *
     * @param   int     $orderId        The j2commerce_order_id.
     * @param   int     $newStatusId    The new order_state_id.
     * @param   bool    $notify         Whether to notify the customer.
     * @param   string  $comment        Optional comment for history.
     *
     * @return  bool  True on success.
     */
    public function updateOrderStatus(int $orderId, int $newStatusId, bool $notify = false, string $comment = ''): bool
    {
        $db     = $this->getDatabase();
        $now    = Factory::getDate()->toSql();
        $user   = Factory::getApplication()->getIdentity();
        $userId = $user ? $user->id : 0;

        // Get order's order_id (varchar)
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('order_id'),
                $db->quoteName('order_state_id'),
            ])
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order) {
            $this->setError(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            return false;
        }

        $oldStatusId = (int) $order->order_state_id;

        // Skip if status hasn't changed
        if ($oldStatusId === $newStatusId) {
            return true;
        }

        // Get new status info
        $status = $this->getOrderStatus($newStatusId);
        if (!$status) {
            $this->setError(Text::_('COM_J2COMMERCE_ORDER_STATUS_NOT_FOUND'));
            return false;
        }

        // Update order status
        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('order_state_id') . ' = :newStatusId')
            ->set($db->quoteName('order_state') . ' = :stateName')
            ->set($db->quoteName('modified_on') . ' = :now')
            ->set($db->quoteName('modified_by') . ' = :userId')
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':newStatusId', $newStatusId, ParameterType::INTEGER)
            ->bind(':stateName', $status->orderstatus_name)
            ->bind(':now', $now)
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($updateQuery);

        if (!$db->execute()) {
            $this->setError($db->getErrorMsg());
            return false;
        }

        // Add history entry with status change comment if none provided
        $historyComment = $comment;

        if (empty($historyComment)) {
            $oldStatus      = $this->getOrderStatus($oldStatusId);
            $oldName        = Text::_($oldStatus->orderstatus_name ?? '') ?: '#' . $oldStatusId;
            $historyComment = Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_STATUS_CHANGED', $oldName, Text::_($status->orderstatus_name));
        }

        $this->addOrderHistory($order->order_id, $newStatusId, $notify, $historyComment);

        // Grant download access when status changes to an allowed download status
        if (\in_array($newStatusId, ConfigHelper::getDownloadAllowedStatuses(), true)) {
            DownloadHelper::grantDownloads($order->order_id);
        }

        // Trigger plugin event
        PluginHelper::importPlugin('j2commerce');
        Factory::getApplication()->triggerEvent('onJ2CommerceOrderStatusChange', [
            $orderId,
            $order->order_id,
            $oldStatusId,
            $newStatusId,
            $notify,
        ]);

        // Send notification emails when requested
        if ($notify) {
            $this->sendOrderNotification($order->order_id, true, true);
        }

        return true;
    }

    /**
     * Add entry to order history.
     *
     * @param   string  $orderId    The order_id (varchar).
     * @param   int     $statusId   The order_state_id.
     * @param   bool    $notify     Whether customer was notified.
     * @param   string  $comment    Comment text.
     *
     * @return  bool  True on success.
     */
    public function addOrderHistory(string $orderId, int $statusId, bool $notify = false, string $comment = ''): bool
    {
        $db          = $this->getDatabase();
        $now         = Factory::getDate()->toSql();
        $user        = Factory::getApplication()->getIdentity();
        $userId      = $user ? $user->id : 0;
        $notifyInt   = $notify ? 1 : 0;
        $emptyParams = '{}';

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_orderhistories'))
            ->columns([
                $db->quoteName('order_id'),
                $db->quoteName('order_state_id'),
                $db->quoteName('notify_customer'),
                $db->quoteName('comment'),
                $db->quoteName('created_on'),
                $db->quoteName('created_by'),
                $db->quoteName('params'),
            ])
            ->values(':orderId, :statusId, :notify, :comment, :createdOn, :createdBy, :params')
            ->bind(':orderId', $orderId)
            ->bind(':statusId', $statusId, ParameterType::INTEGER)
            ->bind(':notify', $notifyInt, ParameterType::INTEGER)
            ->bind(':comment', $comment)
            ->bind(':createdOn', $now)
            ->bind(':createdBy', $userId, ParameterType::INTEGER)
            ->bind(':params', $emptyParams);

        $db->setQuery($query);

        return $db->execute();
    }

    /** Insert an internal admin note into order history (never notifies customer). */
    public function addAdminNote(string $orderId, int $statusId, string $comment, string $type = 'admin_note'): bool
    {
        $db        = $this->getDatabase();
        $now       = Factory::getDate()->toSql();
        $user      = Factory::getApplication()->getIdentity();
        $userId    = $user ? $user->id : 0;
        $notifyInt = 0;
        $params    = json_encode(['type' => $type]);

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_orderhistories'))
            ->columns([
                $db->quoteName('order_id'),
                $db->quoteName('order_state_id'),
                $db->quoteName('notify_customer'),
                $db->quoteName('comment'),
                $db->quoteName('created_on'),
                $db->quoteName('created_by'),
                $db->quoteName('params'),
            ])
            ->values(':orderId, :statusId, :notify, :comment, :createdOn, :createdBy, :params')
            ->bind(':orderId', $orderId)
            ->bind(':statusId', $statusId, ParameterType::INTEGER)
            ->bind(':notify', $notifyInt, ParameterType::INTEGER)
            ->bind(':comment', $comment)
            ->bind(':createdOn', $now)
            ->bind(':createdBy', $userId, ParameterType::INTEGER)
            ->bind(':params', $params);

        $db->setQuery($query);

        return $db->execute();
    }

    /** Delete an admin note only if it belongs to the given user. */
    public function deleteAdminNote(int $historyId, int $userId): bool
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_orderhistories'))
            ->where($db->quoteName('j2commerce_orderhistory_id') . ' = :historyId')
            ->where($db->quoteName('created_by') . ' = :userId')
            ->where($db->quoteName('params') . ' LIKE ' . $db->quote('%"type":"admin_note"%'))
            ->bind(':historyId', $historyId, ParameterType::INTEGER)
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Check if order has a specific status or one of multiple statuses.
     *
     * @param   int        $orderId    The j2commerce_order_id.
     * @param   int|array  $statusIds  Status ID or array of status IDs to check.
     *
     * @return  bool  True if order has one of the specified statuses.
     */
    public function hasStatus(int $orderId, int|array $statusIds): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_state_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);
        $currentStatus = (int) $db->loadResult();

        if (\is_array($statusIds)) {
            return \in_array($currentStatus, $statusIds);
        }

        return $currentStatus === $statusIds;
    }

    /**
     * Get total item count in an order.
     *
     * @param   string  $orderId  The order_id.
     *
     * @return  int  Total quantity of items.
     */
    public function getItemCount(string $orderId): int
    {
        if (empty($orderId)) {
            return 0;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('SUM(CAST(' . $db->quoteName('orderitem_quantity') . ' AS UNSIGNED))')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Check if order requires shipping.
     *
     * @param   int  $orderId  The j2commerce_order_id.
     *
     * @return  bool  True if order is shippable.
     */
    public function isShippable(int $orderId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('is_shippable'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Load order by token (for guest order tracking).
     *
     * @param   string  $token  The order token.
     *
     * @return  object|null  Order object or null.
     */
    public function loadByToken(string $token): ?object
    {
        if (empty($token)) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('token') . ' = :token')
            ->bind(':token', $token);

        $db->setQuery($query);
        $orderId = (int) $db->loadResult();

        if ($orderId > 0) {
            $this->setState($this->getName() . '.id', $orderId);
            return $this->getItem($orderId);
        }

        return null;
    }

    /**
     * Get orders for a specific user.
     *
     * @param   int  $userId  The Joomla user ID.
     *
     * @return  array  Array of order objects.
     */
    public function getOrdersByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a') . '.*',
                'CASE WHEN ' . $db->quoteName('a.invoice_prefix') . ' IS NULL OR ' .
                $db->quoteName('a.invoice_prefix') . ' = ' . $db->quote('') . ' THEN ' .
                $db->quoteName('a.j2commerce_order_id') .
                ' ELSE CONCAT(' . $db->quoteName('a.invoice_prefix') . ', ' .
                $db->quoteName('a.j2commerce_order_id') . ') END AS ' . $db->quoteName('invoice'),
                $db->quoteName('os.orderstatus_name'),
                $db->quoteName('os.orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_orderstatuses', 'os') .
                ' ON ' . $db->quoteName('a.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id')
            )
            ->where($db->quoteName('a.user_id') . ' = :userId')
            ->where($db->quoteName('a.order_type') . ' = ' . $db->quote('normal'))
            ->where($db->quoteName('a.order_state_id') . ' != 5') // Exclude incomplete
            ->order($db->quoteName('a.created_on') . ' DESC')
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getFirstOrderDate(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('created_on') . ')')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('order_type') . ' = ' . $db->quote('normal'))
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: null;
    }

    public function getOrderCountByUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db              = $this->getDatabase();
        $excludeStatuses = [5, 6];
        $query           = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('order_type') . ' = ' . $db->quote('normal'))
            ->whereNotIn($db->quoteName('order_state_id'), $excludeStatuses)
            ->bind(':userId', $userId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    public function saveTrackingNumber(int $orderId, string $trackingId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);
        $orderIdStr = $db->loadResult();

        if (!$orderIdStr) {
            return false;
        }

        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_ordershippings'))
            ->set($db->quoteName('ordershipping_tracking_id') . ' = :trackingId')
            ->where($db->quoteName('order_id') . ' = :orderIdStr')
            ->bind(':trackingId', $trackingId)
            ->bind(':orderIdStr', $orderIdStr);

        $db->setQuery($updateQuery);
        $executed = $db->execute();

        // Let extensions (e.g. payment/fulfillment plugins) react to a tracking
        // number being added — there is otherwise no signal for this.
        if ($executed) {
            J2CommerceHelper::plugin()->event(
                'AfterSaveTrackingNumber',
                ['order_id' => $orderIdStr, 'tracking_id' => $trackingId]
            );
        }

        return (bool) $executed;
    }

    /**
     * Persist the editable order-edit form fields (order + shipping details).
     */
    public function saveOrderEditData(int $pk, array $data): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':pk', $pk, ParameterType::INTEGER);
        $db->setQuery($query);
        $orderId = $db->loadResult();

        if (!$orderId) {
            return false;
        }

        $email    = trim((string) ($data['user_email'] ?? ''));
        $language = (string) ($data['customer_language'] ?? '');
        $created  = trim((string) ($data['created_on'] ?? ''));
        $now      = Factory::getDate()->toSql();

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('modified_on') . ' = :modifiedOn')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':modifiedOn', $now)
            ->bind(':pk', $pk, ParameterType::INTEGER);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $update->set($db->quoteName('user_email') . ' = :userEmail')
                ->bind(':userEmail', $email);
        }

        if (\array_key_exists('customer_language', $data)) {
            $update->set($db->quoteName('customer_language') . ' = :customerLanguage')
                ->bind(':customerLanguage', $language);
        }

        if ($created !== '' && strtotime($created) !== false) {
            $createdSql = Factory::getDate($created)->toSql();
            $update->set($db->quoteName('created_on') . ' = :createdOn')
                ->bind(':createdOn', $createdSql);
        }

        $db->setQuery($update);
        $db->execute();

        if (\array_key_exists('ordershipping_name', $data)
            || \array_key_exists('ordershipping_price', $data)
            || \array_key_exists('ordershipping_tax', $data)
            || \array_key_exists('ordershipping_tracking_id', $data)
        ) {
            $this->saveOrderShipping($orderId, $data);
        }

        return true;
    }

    /** Update or create the ordershippings row for an order. */
    protected function saveOrderShipping(string $orderId, array $data): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_ordershipping_id'))
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $shippingId = (int) $db->loadResult();

        $name     = (string) ($data['ordershipping_name'] ?? '');
        $price    = number_format((float) ($data['ordershipping_price'] ?? 0), 5, '.', '');
        $tax      = number_format((float) ($data['ordershipping_tax'] ?? 0), 5, '.', '');
        $tracking = (string) ($data['ordershipping_tracking_id'] ?? '');

        if ($shippingId > 0) {
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_ordershippings'))
                ->set($db->quoteName('ordershipping_name') . ' = :name')
                ->set($db->quoteName('ordershipping_price') . ' = :price')
                ->set($db->quoteName('ordershipping_tax') . ' = :tax')
                ->set($db->quoteName('ordershipping_tracking_id') . ' = :tracking')
                ->where($db->quoteName('j2commerce_ordershipping_id') . ' = :shippingId')
                ->bind(':name', $name)
                ->bind(':price', $price)
                ->bind(':tax', $tax)
                ->bind(':tracking', $tracking)
                ->bind(':shippingId', $shippingId, ParameterType::INTEGER);
            $db->setQuery($update);
            $db->execute();

            return;
        }

        $row = (object) [
            'order_id'                  => $orderId,
            'ordershipping_type'        => '',
            'ordershipping_price'       => $price,
            'ordershipping_name'        => $name,
            'ordershipping_code'        => '',
            'ordershipping_tax'         => $tax,
            'ordershipping_extra'       => '0.00000',
            'ordershipping_tracking_id' => $tracking,
        ];
        $db->insertObject('#__j2commerce_ordershippings', $row, 'j2commerce_ordershipping_id');
    }

    /**
     * Update quantities and unit prices of existing order items, re-extending
     * line totals (orderitem_finalprice* are quantity-extended values).
     * Stock is intentionally NOT adjusted here — inventory changes only on
     * add/remove item and via the explicit Inventory ± buttons.
     */
    public function updateOrderItemLines(string $orderId, array $quantities, array $prices): int
    {
        $db      = $this->getDatabase();
        $query   = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_orderitem_id',
                'orderitem_quantity',
                'orderitem_price',
                'orderitem_option_price',
                'orderitem_per_item_tax',
                'orderitem_discount',
                'orderitem_weight',
            ]))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $items   = $db->loadObjectList('j2commerce_orderitem_id') ?: [];
        $updated = 0;

        foreach ($items as $itemId => $row) {
            $qty   = isset($quantities[$itemId]) ? max(1, (int) $quantities[$itemId]) : (int) $row->orderitem_quantity;
            $price = isset($prices[$itemId]) ? max(0.0, (float) $prices[$itemId]) : (float) $row->orderitem_price;

            if ($qty === (int) $row->orderitem_quantity && abs($price - (float) $row->orderitem_price) < 0.000001) {
                continue;
            }

            $optionPrice = (float) $row->orderitem_option_price;
            $perItemTax  = (float) $row->orderitem_per_item_tax;
            $discount    = (float) $row->orderitem_discount;
            $finalPrice  = max(0.0, ($price + $optionPrice) * $qty - $discount);
            $lineTax     = $perItemTax * $qty;

            $qtyStr         = (string) $qty;
            $priceStr       = number_format($price, 5, '.', '');
            $finalStr       = number_format($finalPrice, 5, '.', '');
            $taxStr         = number_format($lineTax, 5, '.', '');
            $withTaxStr     = number_format($finalPrice + $lineTax, 5, '.', '');
            $weightTotalStr = (string) ((float) $row->orderitem_weight * $qty);
            $rowId          = (int) $itemId;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orderitems'))
                ->set($db->quoteName('orderitem_quantity') . ' = :qty')
                ->set($db->quoteName('orderitem_price') . ' = :price')
                ->set($db->quoteName('orderitem_finalprice') . ' = :finalPrice')
                ->set($db->quoteName('orderitem_tax') . ' = :lineTax')
                ->set($db->quoteName('orderitem_finalprice_without_tax') . ' = :finalNoTax')
                ->set($db->quoteName('orderitem_finalprice_with_tax') . ' = :finalWithTax')
                ->set($db->quoteName('orderitem_weight_total') . ' = :weightTotal')
                ->where($db->quoteName('j2commerce_orderitem_id') . ' = :itemId')
                ->bind(':qty', $qtyStr)
                ->bind(':price', $priceStr)
                ->bind(':finalPrice', $finalStr)
                ->bind(':lineTax', $taxStr)
                ->bind(':finalNoTax', $finalStr)
                ->bind(':finalWithTax', $withTaxStr)
                ->bind(':weightTotal', $weightTotalStr)
                ->bind(':itemId', $rowId, ParameterType::INTEGER);
            $db->setQuery($update);
            $db->execute();
            $updated++;
        }

        return $updated;
    }

    /** Search enabled product variants by SKU or product title for the admin order editor. */
    public function searchProductVariants(string $term, int $limit = 10): array
    {
        if (trim($term) === '') {
            return [];
        }

        $db     = $this->getDatabase();
        $search = '%' . trim($term) . '%';

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('v.j2commerce_variant_id', 'variant_id'),
                $db->quoteName('v.sku'),
                $db->quoteName('v.price'),
                $db->quoteName('p.j2commerce_product_id', 'product_id'),
                $db->quoteName('p.product_type'),
                $db->quoteName('c.title', 'product_name'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('v.product_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->where($db->quoteName('p.enabled') . ' = 1')
            // Master rows of variable products are placeholders, not purchasable variants.
            ->where(
                'NOT (' . $db->quoteName('v.is_master') . ' = 1 AND '
                . $db->quoteName('p.product_type') . ' = ' . $db->quote('variable') . ')'
            )
            ->where(
                '(' . $db->quoteName('v.sku') . ' LIKE :sku'
                . ' OR ' . $db->quoteName('c.title') . ' LIKE :title' . ')'
            )
            ->bind(':sku', $search)
            ->bind(':title', $search)
            ->order($db->quoteName('c.title') . ' ASC')
            ->setLimit($limit);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /** Add a product variant to an order as a new line item. */
    public function addOrderItemFromVariant(string $orderId, int $variantId, int $qty = 1): ?object
    {
        $qty = max(1, $qty);
        $db  = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('v.j2commerce_variant_id'),
                $db->quoteName('v.product_id'),
                $db->quoteName('v.sku'),
                $db->quoteName('v.price'),
                $db->quoteName('v.weight'),
                $db->quoteName('v.manage_stock'),
                $db->quoteName('v.allow_backorder'),
                $db->quoteName('p.product_type'),
                $db->quoteName('p.taxprofile_id'),
                $db->quoteName('p.vendor_id'),
                $db->quoteName('c.title', 'product_name'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('v.product_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->where($db->quoteName('v.j2commerce_variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);
        $db->setQuery($query);
        $variant = $db->loadObject();

        if (!$variant) {
            return null;
        }

        if (InventoryHelper::isManagingStock($variant)
            && !InventoryHelper::isBackorderAllowed($variant)
            && InventoryHelper::getAvailableQuantity((int) $variant->j2commerce_variant_id) < $qty
        ) {
            throw new \RuntimeException(Text::_('COM_J2COMMERCE_NOT_ENOUGH_STOCK'));
        }

        $price      = (float) $variant->price;
        $finalPrice = $price * $qty;
        $user       = Factory::getApplication()->getIdentity();

        $row = (object) [
            'order_id'                         => $orderId,
            'orderitem_type'                   => 'normal',
            'cart_id'                          => 0,
            'cartitem_id'                      => 0,
            'product_id'                       => (int) $variant->product_id,
            'product_type'                     => (string) ($variant->product_type ?? 'simple'),
            'variant_id'                       => (int) $variant->j2commerce_variant_id,
            'vendor_id'                        => (int) ($variant->vendor_id ?? 0),
            'orderitem_sku'                    => (string) ($variant->sku ?? ''),
            'orderitem_name'                   => (string) ($variant->product_name ?? $variant->sku ?? ''),
            'orderitem_attributes'             => '',
            'orderitem_quantity'               => (string) $qty,
            'orderitem_taxprofile_id'          => (int) ($variant->taxprofile_id ?? 0),
            'orderitem_per_item_tax'           => '0.00000',
            'orderitem_tax'                    => '0.00000',
            'orderitem_discount'               => '0.00000',
            'orderitem_discount_tax'           => '0.00000',
            'orderitem_price'                  => number_format($price, 5, '.', ''),
            'orderitem_option_price'           => '0.00000',
            'orderitem_finalprice'             => number_format($finalPrice, 5, '.', ''),
            'orderitem_finalprice_with_tax'    => number_format($finalPrice, 5, '.', ''),
            'orderitem_finalprice_without_tax' => number_format($finalPrice, 5, '.', ''),
            'orderitem_params'                 => '',
            'created_on'                       => Factory::getDate()->toSql(),
            'created_by'                       => (int) ($user?->id ?? 0),
            'orderitem_weight'                 => (string) ((float) ($variant->weight ?? 0)),
            'orderitem_weight_total'           => (string) ((float) ($variant->weight ?? 0) * $qty),
        ];

        $db->insertObject('#__j2commerce_orderitems', $row, 'j2commerce_orderitem_id');

        $this->copyVariantAttributes((int) $row->j2commerce_orderitem_id, (int) $variant->j2commerce_variant_id);

        if (InventoryHelper::isManagingStock($variant)) {
            InventoryHelper::adjustStock(
                (int) $variant->j2commerce_variant_id,
                -$qty,
                InventoryHelper::isBackorderAllowed($variant)
            );
        }

        return $row;
    }

    /** Copy a variant's option values into orderitemattributes rows for display. */
    protected function copyVariantAttributes(int $orderitemId, int $variantId): void
    {
        if ($orderitemId < 1 || $variantId < 1) {
            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('product_optionvalue_ids'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);
        $db->setQuery($query);
        $ids = array_filter(array_map('intval', explode(',', (string) $db->loadResult())));

        if (empty($ids)) {
            return;
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                $db->quoteName('pov.productoption_id'),
                $db->quoteName('pov.product_optionvalue_price'),
                $db->quoteName('pov.product_optionvalue_prefix'),
                $db->quoteName('o.option_name'),
                $db->quoteName('o.type'),
                $db->quoteName('ov.optionvalue_name'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_options', 'po')
                . ' ON ' . $db->quoteName('po.j2commerce_productoption_id') . ' = ' . $db->quoteName('pov.productoption_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_options', 'o')
                . ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov')
                . ' ON ' . $db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('pov.optionvalue_id')
            )
            ->whereIn($db->quoteName('pov.j2commerce_product_optionvalue_id'), $ids, ParameterType::INTEGER);
        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $value) {
            $attr = (object) [
                'orderitem_id'                   => $orderitemId,
                'productattributeoption_id'      => (int) $value->productoption_id,
                'productattributeoptionvalue_id' => (int) $value->j2commerce_product_optionvalue_id,
                'orderitemattribute_name'        => (string) $value->option_name,
                'orderitemattribute_value'       => (string) ($value->optionvalue_name ?? ''),
                'orderitemattribute_prefix'      => substr((string) ($value->product_optionvalue_prefix ?: '+'), 0, 1),
                'orderitemattribute_price'       => number_format((float) $value->product_optionvalue_price, 5, '.', ''),
                'orderitemattribute_code'        => '',
                'orderitemattribute_type'        => (string) ($value->type ?? 'select'),
            ];
            $db->insertObject('#__j2commerce_orderitemattributes', $attr, 'j2commerce_orderitemattribute_id');
        }
    }

    /** Remove order items by primary key, returning the removed item names. */
    public function removeOrderItems(string $orderId, array $itemIds): array
    {
        $itemIds = array_values(array_filter(array_map('intval', $itemIds), static fn (int $id) => $id > 0));

        if (empty($itemIds)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('oi.j2commerce_orderitem_id'),
                $db->quoteName('oi.orderitem_name'),
                $db->quoteName('oi.orderitem_quantity'),
                $db->quoteName('oi.variant_id'),
                $db->quoteName('v.manage_stock'),
                $db->quoteName('v.allow_backorder'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_variants', 'v')
                . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('oi.variant_id')
            )
            ->where($db->quoteName('oi.order_id') . ' = :orderId')
            ->whereIn($db->quoteName('oi.j2commerce_orderitem_id'), $itemIds, ParameterType::INTEGER)
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        if (empty($rows)) {
            return [];
        }

        // Restore stock for managed variants before deleting the lines
        foreach ($rows as $row) {
            if ((int) ($row->variant_id ?? 0) > 0 && InventoryHelper::isManagingStock($row)) {
                InventoryHelper::adjustStock((int) $row->variant_id, (int) $row->orderitem_quantity);
            }
        }

        $foundIds = array_map(static fn (object $row) => (int) $row->j2commerce_orderitem_id, $rows);

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                ->whereIn($db->quoteName('orderitem_id'), $foundIds, ParameterType::INTEGER)
        );
        $db->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_orderitems'))
                ->whereIn($db->quoteName('j2commerce_orderitem_id'), $foundIds, ParameterType::INTEGER)
        );
        $db->execute();

        return array_map(static fn (object $row) => (string) $row->orderitem_name, $rows);
    }

    private const ADDRESS_FIELDS = [
        'first_name', 'last_name', 'middle_name', 'company', 'address_1', 'address_2',
        'city', 'zip', 'zone_id', 'country_id', 'phone_1', 'phone_2', 'fax', 'tax_number',
    ];

    /** Resolve country/zone display names for an address payload. */
    private function resolveGeoNames(int $countryId, int $zoneId): array
    {
        $db          = $this->getDatabase();
        $countryName = '';
        $zoneName    = '';

        if ($countryId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('country_name'))
                ->from($db->quoteName('#__j2commerce_countries'))
                ->where($db->quoteName('j2commerce_country_id') . ' = :countryId')
                ->bind(':countryId', $countryId, ParameterType::INTEGER);
            $db->setQuery($query);
            $countryName = (string) $db->loadResult();
        }

        if ($zoneId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('zone_name'))
                ->from($db->quoteName('#__j2commerce_zones'))
                ->where($db->quoteName('j2commerce_zone_id') . ' = :zoneId')
                ->bind(':zoneId', $zoneId, ParameterType::INTEGER);
            $db->setQuery($query);
            $zoneName = (string) $db->loadResult();
        }

        return [$countryName, $zoneName];
    }

    /** Update (or create) the billing/shipping block of the orderinfos row. */
    public function saveOrderAddress(string $orderId, string $type, array $data): bool
    {
        if (!\in_array($type, ['billing', 'shipping'], true)) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_orderinfo_id'))
            ->from($db->quoteName('#__j2commerce_orderinfos'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $infoId = (int) $db->loadResult();

        $countryId                = (int) ($data['country_id'] ?? 0);
        $zoneId                   = (int) ($data['zone_id'] ?? 0);
        [$countryName, $zoneName] = $this->resolveGeoNames($countryId, $zoneId);

        $row = new \stdClass();

        foreach (self::ADDRESS_FIELDS as $field) {
            if (!\array_key_exists($field, $data)) {
                continue;
            }

            $column         = $type . '_' . $field;
            $row->{$column} = \in_array($field, ['zone_id', 'country_id'], true)
                ? (int) $data[$field]
                : (string) $data[$field];
        }

        $row->{$type . '_country_name'} = $countryName;
        $row->{$type . '_zone_name'}    = $zoneName;

        if ($infoId > 0) {
            $row->j2commerce_orderinfo_id = $infoId;

            return (bool) $db->updateObject('#__j2commerce_orderinfos', $row, 'j2commerce_orderinfo_id');
        }

        $row->order_id     = $orderId;
        $row->all_billing  = '{}';
        $row->all_shipping = '{}';
        $row->all_payment  = '{}';

        // shipping_zip is NOT NULL without a DB default — seed it when only billing is saved.
        $row->shipping_zip ??= '';

        return (bool) $db->insertObject('#__j2commerce_orderinfos', $row, 'j2commerce_orderinfo_id');
    }

    /** Saved addresses of the order's customer, with resolved geo names. */
    public function getSavedAddresses(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_address_id', 'first_name', 'last_name', 'company', 'address_1', 'address_2',
                'city', 'zip', 'zone_id', 'country_id', 'phone_1', 'phone_2', 'fax', 'tax_number', 'type',
            ]))
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->order($db->quoteName('j2commerce_address_id') . ' DESC');
        $db->setQuery($query);
        $addresses = $db->loadObjectList() ?: [];

        foreach ($addresses as $address) {
            [$address->country_name, $address->zone_name] = $this->resolveGeoNames(
                (int) $address->country_id,
                (int) $address->zone_id
            );
        }

        return $addresses;
    }

    /** Copy one of the customer's saved addresses onto the order. */
    public function applySavedAddress(string $orderId, string $type, int $addressId, int $userId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('j2commerce_address_id') . ' = :addressId')
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':addressId', $addressId, ParameterType::INTEGER)
            ->bind(':userId', $userId, ParameterType::INTEGER);
        $db->setQuery($query);
        $address = $db->loadObject();

        if (!$address) {
            return false;
        }

        $data = [];

        foreach (self::ADDRESS_FIELDS as $field) {
            $data[$field] = $address->{$field} ?? '';
        }

        return $this->saveOrderAddress($orderId, $type, $data);
    }

    public function getCountries(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_country_id', 'country_name']))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('country_name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getZones(int $countryId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_zone_id', 'zone_name']))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->order($db->quoteName('zone_name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /** Add a manual (non-taxable) fee row; plugin-supplied fees carry their own tax. */
    public function addOrderFee(string $orderId, string $name, float $amount, bool $taxable = false): bool
    {
        if ($name === '' || $amount <= 0) {
            return false;
        }

        $row = (object) [
            'order_id'     => $orderId,
            'name'         => $name,
            'amount'       => number_format($amount, 5, '.', ''),
            'tax_class_id' => 0,
            'taxable'      => $taxable ? 1 : 0,
            'tax'          => '0.00000',
            'tax_data'     => '{}',
            'fee_type'     => 'admin',
        ];

        return (bool) $this->getDatabase()->insertObject('#__j2commerce_orderfees', $row, 'j2commerce_orderfee_id');
    }

    public function removeOrderFee(string $orderId, int $feeId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('j2commerce_orderfee_id') . ' = :feeId')
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':feeId', $feeId, ParameterType::INTEGER)
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Apply a coupon to the order, replacing any previously applied coupon.
     * Returns [success(bool), message(string)].
     */
    public function applyCouponToOrder(object $order, string $code): array
    {
        $couponModel = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Coupon', 'Administrator', ['ignore_request' => true]);

        $coupon = $couponModel->getCouponByCode($code);

        if (!$coupon) {
            return [false, Text::_('COM_J2COMMERCE_COUPON_NOT_VALID')];
        }

        $couponModel->setCoupon($code);
        $couponModel->init();

        $subtotal = (float) $order->order_subtotal;

        $context                   = new \stdClass();
        $context->order_id         = $order->order_id;
        $context->subtotal         = $subtotal;
        $context->order_subtotal   = $subtotal;
        $context->subtotal_ex_tax  = (float) ($order->order_subtotal_ex_tax ?? $subtotal);

        if (!$couponModel->isAdminValid($context)) {
            return [false, $couponModel->getError() ?: Text::_('COM_J2COMMERCE_COUPON_NOT_VALID')];
        }

        $valueType = (string) ($coupon->value_type ?? '');
        $value     = (float) ($coupon->value ?? 0);

        $discount = str_starts_with($valueType, 'percentage')
            ? $subtotal * ($value / 100)
            : min($value, $subtotal);

        $maxValue = (float) ($coupon->max_value ?? 0);

        if ($maxValue > 0) {
            $discount = min($discount, $maxValue);
        }

        $discount = round(min($discount, $subtotal), 2);

        if ($discount <= 0) {
            return [false, Text::_('COM_J2COMMERCE_COUPON_NOT_APPLICABLE')];
        }

        $this->removeDiscountsByType($order->order_id, 'coupon');
        $this->insertOrderDiscount($order, [
            'discount_type'       => 'coupon',
            'discount_entity_id'  => (int) $coupon->j2commerce_coupon_id,
            'discount_title'      => (string) ($coupon->coupon_name ?: $coupon->coupon_code),
            'discount_code'       => (string) $coupon->coupon_code,
            'discount_value'      => (string) $value,
            'discount_value_type' => $valueType,
            'discount_amount'     => $discount,
        ]);
        $this->syncOrderDiscountTotal($order->order_id);

        return [true, Text::_('COM_J2COMMERCE_COUPON_APPLIED_SUCCESSFULLY')];
    }

    /**
     * Apply a gift voucher to the order, replacing any previously applied voucher.
     * Returns [success(bool), message(string)].
     */
    public function applyVoucherToOrder(object $order, string $code): array
    {
        $voucherModel = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Voucher', 'Administrator', ['ignore_request' => true]);

        $voucher = $voucherModel->getVoucherByCode($code);

        if (!$voucher) {
            return [false, Text::_('COM_J2COMMERCE_VOUCHER_DOES_NOT_EXIST')];
        }

        // Assign directly instead of setVoucher() — admin edits must not touch the session cart.
        $voucherModel->voucher = $voucher;

        if (!$voucherModel->isAdminValid($order)) {
            return [false, $voucherModel->getError() ?: Text::_('COM_J2COMMERCE_VOUCHER_NOT_APPLICABLE')];
        }

        $balance  = $voucherModel->getRemainingBalance((int) $voucher->j2commerce_voucher_id, (string) $order->order_id);
        $subtotal = (float) $order->order_subtotal;
        $discount = round(max(0.0, min($balance, $subtotal)), 2);

        if ($discount <= 0) {
            return [false, Text::_('COM_J2COMMERCE_VOUCHER_USAGE_LIMIT_HAS_REACHED')];
        }

        $this->removeDiscountsByType($order->order_id, 'voucher');
        $this->insertOrderDiscount($order, [
            'discount_type'       => 'voucher',
            'discount_entity_id'  => (int) $voucher->j2commerce_voucher_id,
            'discount_title'      => Text::_('COM_J2COMMERCE_VOUCHER'),
            'discount_code'       => (string) $voucher->voucher_code,
            'discount_value'      => (string) $voucher->voucher_value,
            'discount_value_type' => 'voucher',
            'discount_amount'     => $discount,
        ]);
        $this->syncOrderDiscountTotal($order->order_id);

        return [true, Text::_('COM_J2COMMERCE_VOUCHER_APPLIED_SUCCESSFULLY')];
    }

    /** Delete one applied discount row and resync the order discount total. */
    public function removeOrderDiscount(string $orderId, int $discountId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('j2commerce_orderdiscount_id') . ' = :discountId')
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':discountId', $discountId, ParameterType::INTEGER)
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $db->execute();

        $removed = $db->getAffectedRows() > 0;

        if ($removed) {
            $this->syncOrderDiscountTotal($orderId);
        }

        return $removed;
    }

    private function removeDiscountsByType(string $orderId, string $type): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('discount_type') . ' = :type')
            ->bind(':orderId', $orderId)
            ->bind(':type', $type);
        $db->setQuery($query);
        $db->execute();
    }

    private function insertOrderDiscount(object $order, array $data): void
    {
        $row = (object) [
            'order_id'                => $order->order_id,
            'discount_type'           => $data['discount_type'],
            'discount_entity_id'      => (int) $data['discount_entity_id'],
            'discount_title'          => $data['discount_title'],
            'discount_code'           => $data['discount_code'],
            'discount_value'          => $data['discount_value'],
            'discount_value_type'     => $data['discount_value_type'],
            'discount_customer_email' => (string) ($order->user_email ?? ''),
            'user_id'                 => (int) ($order->user_id ?? 0),
            'discount_amount'         => number_format((float) $data['discount_amount'], 5, '.', ''),
            'discount_tax'            => '0.00000',
            'discount_params'         => '{}',
        ];

        $this->getDatabase()->insertObject('#__j2commerce_orderdiscounts', $row, 'j2commerce_orderdiscount_id');
    }

    /** Resync orders.order_discount with the sum of the orderdiscounts rows. */
    private function syncOrderDiscountTotal(string $orderId): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COALESCE(SUM(' . $db->quoteName('discount_amount') . '), 0)')
            ->from($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $total = number_format((float) $db->loadResult(), 5, '.', '');

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('order_discount') . ' = :total')
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':total', $total)
            ->bind(':orderId', $orderId);
        $db->setQuery($update);
        $db->execute();
    }

    /** Build the tax address from the order's shipping (fallback billing) address. */
    private function getOrderTaxAddress(string $orderId): ?\stdClass
    {
        $info = $this->getOrderInfo($orderId);

        if (!$info) {
            return null;
        }

        if ((int) ($info->shipping_country_id ?? 0) > 0) {
            return (object) [
                'country_id' => (int) $info->shipping_country_id,
                'zone_id'    => (int) ($info->shipping_zone_id ?? 0),
                'postcode'   => (string) ($info->shipping_zip ?? ''),
            ];
        }

        if ((int) ($info->billing_country_id ?? 0) > 0) {
            return (object) [
                'country_id' => (int) $info->billing_country_id,
                'zone_id'    => (int) ($info->billing_zone_id ?? 0),
                'postcode'   => (string) ($info->billing_zip ?? ''),
            ];
        }

        return null;
    }

    /**
     * Recompute per-line and fee tax from tax profiles against the order's own
     * address, rebuild the ordertaxes rows, then recalculate the totals.
     */
    public function recomputeOrderTax(string $orderId): array
    {
        $db      = $this->getDatabase();
        $address = $this->getOrderTaxAddress($orderId);

        $geozones = $address
            ? TaxHelper::getCustomerGeozones($address)
            : [];

        $query = $db->getQuery(true)
            ->select($db->quoteName('is_including_tax'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $isIncludingTax = (int) $db->loadResult() === 1;

        $taxRates = [];

        foreach ($this->getOrderItems($orderId) as $item) {
            $taxprofileId = (int) ($item->orderitem_taxprofile_id ?? 0);
            $qty          = max(1, (int) $item->orderitem_quantity);
            $lineAmount   = (float) $item->orderitem_finalprice;
            $lineTax      = 0.0;

            if ($taxprofileId > 0 && !empty($geozones) && $lineAmount > 0) {
                $ratesets = TaxHelper::getTaxRatesForProfile(
                    $taxprofileId,
                    $geozones,
                    $address
                );

                $totalPct = 0.0;

                foreach ($ratesets as $rate) {
                    $totalPct += (float) ($rate->rate ?? $rate->tax_percent ?? 0);
                }

                if ($totalPct > 0) {
                    $lineTax = $isIncludingTax
                        ? $lineAmount * $totalPct / (100 + $totalPct)
                        : $lineAmount * ($totalPct / 100);

                    foreach ($ratesets as $rate) {
                        $pct     = (float) ($rate->rate ?? $rate->tax_percent ?? 0);
                        $rateKey = ($rate->name ?? '') . '_' . ($rate->j2commerce_taxrate_id ?? 0);
                        $amount  = $lineTax * ($pct / $totalPct);

                        if (!isset($taxRates[$rateKey])) {
                            $taxRates[$rateKey] = (object) [
                                'title'   => (string) ($rate->name ?? $rate->taxrate_name ?? ''),
                                'percent' => $pct,
                                'amount'  => 0.0,
                            ];
                        }

                        $taxRates[$rateKey]->amount += $amount;
                    }
                }
            }

            $lineTaxStr    = number_format($lineTax, 5, '.', '');
            $perItemTaxStr = number_format($lineTax / $qty, 5, '.', '');
            $withTaxStr    = number_format($isIncludingTax ? $lineAmount : $lineAmount + $lineTax, 5, '.', '');
            $withoutTaxStr = number_format($isIncludingTax ? $lineAmount - $lineTax : $lineAmount, 5, '.', '');
            $itemId        = (int) $item->j2commerce_orderitem_id;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orderitems'))
                ->set($db->quoteName('orderitem_tax') . ' = :lineTax')
                ->set($db->quoteName('orderitem_per_item_tax') . ' = :perItemTax')
                ->set($db->quoteName('orderitem_finalprice_with_tax') . ' = :withTax')
                ->set($db->quoteName('orderitem_finalprice_without_tax') . ' = :withoutTax')
                ->where($db->quoteName('j2commerce_orderitem_id') . ' = :itemId')
                ->bind(':lineTax', $lineTaxStr)
                ->bind(':perItemTax', $perItemTaxStr)
                ->bind(':withTax', $withTaxStr)
                ->bind(':withoutTax', $withoutTaxStr)
                ->bind(':itemId', $itemId, ParameterType::INTEGER);
            $db->setQuery($update);
            $db->execute();
        }

        // Rebuild the per-rate tax summary rows
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_ordertaxes'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderId)
        );
        $db->execute();

        foreach ($taxRates as $rate) {
            if ($rate->amount <= 0) {
                continue;
            }

            $row = (object) [
                'order_id'         => $orderId,
                'ordertax_title'   => $rate->title,
                'ordertax_percent' => number_format($rate->percent, 5, '.', ''),
                'ordertax_amount'  => number_format($rate->amount, 5, '.', ''),
            ];
            $db->insertObject('#__j2commerce_ordertaxes', $row, 'j2commerce_ordertax_id');
        }

        return $this->recalculateOrderTotals($orderId);
    }

    /**
     * Recalculate and persist order totals from the current line items,
     * shipping, fees, surcharge and discounts. Returns the stored totals.
     */
    public function recalculateOrderTotals(string $orderId): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                'COALESCE(SUM(' . $db->quoteName('orderitem_finalprice') . '), 0) AS subtotal',
                'COALESCE(SUM(' . $db->quoteName('orderitem_tax') . '), 0) AS tax',
            ])
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $itemTotals = $db->loadObject();

        $query = $db->getQuery(true)
            ->select([
                'COALESCE(' . $db->quoteName('ordershipping_price') . ', 0) + COALESCE(' . $db->quoteName('ordershipping_extra') . ', 0) AS shipping',
                'COALESCE(' . $db->quoteName('ordershipping_tax') . ', 0) AS shipping_tax',
            ])
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $shippingTotals = $db->loadObject();

        $query = $db->getQuery(true)
            ->select([
                'COALESCE(SUM(' . $db->quoteName('amount') . '), 0) AS fee_amount',
                'COALESCE(SUM(' . $db->quoteName('tax') . '), 0) AS fee_tax',
            ])
            ->from($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $feeTotals = $db->loadObject();

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_order_id',
                'order_discount',
                'order_surcharge',
                'order_credit',
                'is_including_tax',
            ]))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);
        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order) {
            return [];
        }

        $subtotal    = round((float) ($itemTotals->subtotal ?? 0), 2);
        $tax         = round((float) ($itemTotals->tax ?? 0), 2);
        $shipping    = round((float) ($shippingTotals->shipping ?? 0), 2);
        $shippingTax = round((float) ($shippingTotals->shipping_tax ?? 0), 2);
        // Re-cap the applied discount if items were removed/reduced after it was applied.
        $discount    = min((float) $order->order_discount, $subtotal);
        $surcharge   = (float) $order->order_surcharge;
        $fees        = round((float) ($feeTotals->fee_amount ?? 0) + (float) ($feeTotals->fee_tax ?? 0), 2);
        $credit      = (float) $order->order_credit;

        // Tax-inclusive stores already carry item tax inside the subtotal.
        $itemTaxComponent = ((int) $order->is_including_tax === 1) ? 0.0 : $tax;

        $total = round(
            $subtotal + $itemTaxComponent + $shipping + $shippingTax + $surcharge + $fees - $discount - $credit,
            2
        );

        $subtotalEx = ((int) $order->is_including_tax === 1) ? round($subtotal - $tax, 2) : $subtotal;

        $subtotalStr   = number_format($subtotal, 5, '.', '');
        $subtotalExStr = number_format($subtotalEx, 5, '.', '');
        $taxStr        = number_format($tax, 5, '.', '');
        $shippingStr   = number_format($shipping, 5, '.', '');
        $shipTaxStr    = number_format($shippingTax, 5, '.', '');
        $feesStr       = number_format($fees, 5, '.', '');
        $totalStr      = number_format(max(0.0, $total), 5, '.', '');
        $now           = Factory::getDate()->toSql();
        $pk            = (int) $order->j2commerce_order_id;

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('order_subtotal') . ' = :subtotal')
            ->set($db->quoteName('order_subtotal_ex_tax') . ' = :subtotalEx')
            ->set($db->quoteName('order_tax') . ' = :tax')
            ->set($db->quoteName('order_shipping') . ' = :shipping')
            ->set($db->quoteName('order_shipping_tax') . ' = :shippingTax')
            ->set($db->quoteName('order_fees') . ' = :fees')
            ->set($db->quoteName('order_total') . ' = :total')
            ->set($db->quoteName('modified_on') . ' = :modifiedOn')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':subtotal', $subtotalStr)
            ->bind(':subtotalEx', $subtotalExStr)
            ->bind(':tax', $taxStr)
            ->bind(':shipping', $shippingStr)
            ->bind(':shippingTax', $shipTaxStr)
            ->bind(':fees', $feesStr)
            ->bind(':total', $totalStr)
            ->bind(':modifiedOn', $now)
            ->bind(':pk', $pk, ParameterType::INTEGER);
        $db->setQuery($update);
        $db->execute();

        return [
            'subtotal'     => $subtotal,
            'tax'          => $tax,
            'shipping'     => $shipping,
            'shipping_tax' => $shippingTax,
            'discount'     => round($discount, 2),
            'surcharge'    => round($surcharge, 2),
            'fees'         => round($fees, 2),
            'total'        => max(0.0, $total),
        ];
    }

    /**
     * Prepare the table before saving.
     */
    protected function prepareTable($table): void
    {
        $now = Factory::getDate()->toSql();

        if (empty($table->j2commerce_order_id)) {
            // New order
            $table->created_on  = $now;
            $table->modified_on = $now;

            // Generate order_id if not set
            if (empty($table->order_id)) {
                $table->order_id = $this->generateOrderId();
            }

            // Generate token if not set
            if (empty($table->token)) {
                $table->token = $this->generateToken();
            }
        } else {
            // Existing order - update modified
            $table->modified_on = $now;
        }
    }

    /**
     * Send order notification emails to customer and/or admin.
     *
     * Loads the order, retrieves matching email templates via EmailHelper,
     * sends each email, and logs results to order history.
     *
     * @param   string  $orderId         The order_id (varchar).
     * @param   bool    $notifyCustomer  Send to customer.
     * @param   bool    $notifyAdmin     Send to admin.
     *
     * @return  array{sent: int, customer_sent: int, admin_sent: int, errors: string[]}
     */
    public function sendOrderNotification(string $orderId, bool $notifyCustomer = true, bool $notifyAdmin = true): array
    {
        $result = ['sent' => 0, 'customer_sent' => 0, 'admin_sent' => 0, 'errors' => []];

        if (empty($orderId)) {
            $result['errors'][] = 'No order ID provided';
            return $result;
        }

        // Load full order record by order_id (varchar)
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order) {
            $result['errors'][] = Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND');
            return $result;
        }

        $emailHelper = EmailHelper::getInstance();

        PluginHelper::importPlugin('j2commerce');
        $app = Factory::getApplication();

        // Send customer emails
        if ($notifyCustomer) {
            $customerEmails = $emailHelper->getOrderEmails($order, 'customer');

            foreach ($customerEmails as $template) {
                if (!isset($template->mailer) || !$template->mailer instanceof Mail) {
                    continue;
                }

                try {
                    $app->getDispatcher()->dispatch(
                        'onJ2CommerceBeforeOrderNotification',
                        new \Joomla\Event\Event('onJ2CommerceBeforeOrderNotification', [
                            'order'  => $order,
                            'mailer' => $template->mailer,
                            'type'   => 'customer',
                        ])
                    );

                    $recipients = $template->mailer->getAllRecipientAddresses();

                    if (!empty($recipients) && $template->mailer->Send()) {
                        $this->addOrderHistory(
                            $orderId,
                            (int) $order->order_state_id,
                            true,
                            Text::sprintf('COM_J2COMMERCE_CUSTOMER_NOTIFIED', $template->mailer->Subject)
                        );
                        $emailHelper->logEmailSend($orderId, 'customer', $template->mailer->Subject, array_keys($recipients), true);
                        $result['sent']++;
                        $result['customer_sent']++;
                    }
                } catch (\Throwable $e) {
                    $errorMsg           = Text::sprintf('COM_J2COMMERCE_EMAIL_SEND_FAILED', $e->getMessage());
                    $result['errors'][] = $errorMsg;
                    $this->addOrderHistory($orderId, (int) $order->order_state_id, false, $errorMsg);
                    $emailHelper->logEmailSend($orderId, 'customer', $template->mailer->Subject ?? '', [], false, $e->getMessage());
                    Log::add('Order email send failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
                }
            }
        }

        // Send admin emails
        if ($notifyAdmin) {
            $adminEmails = $emailHelper->getOrderEmails($order, 'admin');

            foreach ($adminEmails as $template) {
                if (!isset($template->mailer) || !$template->mailer instanceof Mail) {
                    continue;
                }

                try {
                    $app->getDispatcher()->dispatch(
                        'onJ2CommerceBeforeOrderNotificationAdmin',
                        new \Joomla\Event\Event('onJ2CommerceBeforeOrderNotificationAdmin', [
                            'order'  => $order,
                            'mailer' => $template->mailer,
                        ])
                    );

                    $recipients = $template->mailer->getAllRecipientAddresses();

                    if (!empty($recipients) && $template->mailer->Send()) {
                        $this->addOrderHistory(
                            $orderId,
                            (int) $order->order_state_id,
                            false,
                            Text::sprintf('COM_J2COMMERCE_ADMIN_NOTIFIED', $template->mailer->Subject)
                        );
                        $emailHelper->logEmailSend($orderId, 'admin', $template->mailer->Subject, array_keys($recipients), true);
                        $result['sent']++;
                        $result['admin_sent']++;
                    }
                } catch (\Throwable $e) {
                    $errorMsg           = Text::sprintf('COM_J2COMMERCE_EMAIL_SEND_FAILED', $e->getMessage());
                    $result['errors'][] = $errorMsg;
                    $emailHelper->logEmailSend($orderId, 'admin', $template->mailer->Subject ?? '', [], false, $e->getMessage());
                    Log::add('Admin order email send failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
                }
            }
        }

        return $result;
    }

    /**
     * Generate unique order ID.
     *
     * @return  string  Unique order identifier.
     */
    protected function generateOrderId(): string
    {
        return date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Generate secure token for order.
     *
     * @return  string  Secure token.
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
