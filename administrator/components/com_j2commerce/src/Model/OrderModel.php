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
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
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
        if (empty($order->invoice_prefix) || empty($order->invoice_number) || $order->invoice_number == 0) {
            return (string) ($order->j2commerce_order_id ?? '');
        }

        return $order->invoice_prefix . $order->invoice_number;
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
                $db->quoteName('a.invoice_number') . ' = 0 THEN ' .
                $db->quoteName('a.j2commerce_order_id') .
                ' ELSE CONCAT(' . $db->quoteName('a.invoice_prefix') . ', ' .
                $db->quoteName('a.invoice_number') . ') END AS ' . $db->quoteName('invoice'),
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

        return $db->execute();
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
