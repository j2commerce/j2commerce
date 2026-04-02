<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\DownloadHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Order table class.
 *
 * @since  6.0.7
 */
class OrderTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_orders', 'j2commerce_order_id', $db);
    }

    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $now = Factory::getDate()->toSql();

        // For new records, set temporary order_id and token to satisfy NOT NULL.
        // saveOrder() will overwrite them with the final values (using the PK)
        // after the first store() and do a second store().
        if (empty($this->order_id)) {
            $this->order_id = (string) time();
        }

        if (empty($this->token)) {
            $this->token = bin2hex(random_bytes(16));
        }

        // Set default order type
        if (empty($this->order_type)) {
            $this->order_type = 'normal';
        }

        // Set default status (5 = incomplete)
        if (!isset($this->order_state_id) || $this->order_state_id === '') {
            $this->order_state_id = 5;
        }

        // Validate user email
        if (empty($this->user_email)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_EMAIL')));
            return false;
        }

        // Set timestamps
        if (empty($this->j2commerce_order_id)) {
            $this->created_on = $now;
        }
        $this->modified_on = $now;

        // Set default numeric values
        $numericFields = [
            'order_total', 'order_subtotal', 'order_subtotal_ex_tax',
            'order_tax', 'order_shipping', 'order_shipping_tax',
            'order_discount', 'order_discount_tax', 'order_credit',
            'order_refund', 'order_surcharge', 'order_fees', 'currency_value',
        ];

        foreach ($numericFields as $field) {
            if (!isset($this->$field) || $this->$field === '') {
                $this->$field = 0;
            }
        }

        // Set default integer values
        $intFields = [
            'parent_id', 'subscription_id', 'cart_id', 'invoice_number',
            'user_id', 'currency_id', 'is_shippable', 'is_including_tax',
            'created_by', 'modified_by',
        ];

        foreach ($intFields as $field) {
            if (!isset($this->$field) || $this->$field === '') {
                $this->$field = 0;
            }
        }

        // Set default string values
        $stringFields = [
            'invoice_prefix', 'orderpayment_type', 'transaction_id',
            'transaction_status', 'transaction_details', 'currency_code',
            'ip_address', 'customer_note', 'customer_language',
            'customer_group', 'order_state',
        ];

        foreach ($stringFields as $field) {
            if (!isset($this->$field)) {
                $this->$field = '';
            }
        }

        return true;
    }

    /**
     * Store the order and trigger side effects when status changes.
     *
     * This ensures that download grants, order history, and plugin events
     * are triggered even when payment plugins call $order->store() directly
     * instead of using OrderModel::updateOrderStatus().
     */
    public function store($updateNulls = false): bool
    {
        $user = Factory::getApplication()->getIdentity();
        $isNew = empty($this->j2commerce_order_id);

        if ($isNew) {
            if (empty($this->created_by)) {
                $this->created_by = (int) $user->id;
            }
        }

        $this->modified_by = (int) $user->id;

        // Capture old status before storing (for new records, treat as status change from 5=Incomplete)
        $oldStatusId = null;

        if (!$isNew) {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('order_state_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('j2commerce_order_id') . ' = :id')
                ->bind(':id', $this->j2commerce_order_id, \Joomla\Database\ParameterType::INTEGER);
            $db->setQuery($query);
            $oldStatusId = (int) $db->loadResult();
        }

        // Perform the actual store
        $result = parent::store($updateNulls);

        if (!$result) {
            return false;
        }

        // After successful store, handle status-based side effects
        $newStatusId = (int) $this->order_state_id;

        // Skip if status didn't change (and not a new record)
        if (!$isNew && $oldStatusId === $newStatusId) {
            return true;
        }

        // Trigger plugin event for status change
        PluginHelper::importPlugin('j2commerce');
        Factory::getApplication()->triggerEvent('onJ2CommerceOrderStatusChange', [
            $this->order_id,
            $oldStatusId,
            $newStatusId,
        ]);

        // Grant download access when status changes to an allowed download status
        if (\in_array($newStatusId, ConfigHelper::getDownloadAllowedStatuses(), true)) {
            DownloadHelper::grantDownloads($this->order_id);
        }

        // Add order history entry for status change
        OrderHistoryHelper::add(
            orderId: $this->order_id,
            orderStateId: $newStatusId,
            comment: $isNew
                ? Text::_('COM_J2COMMERCE_ORDER_CREATED')
                : Text::sprintf('COM_J2COMMERCE_STATUS_CHANGED', (string) $oldStatusId, (string) $newStatusId),
        );

        return true;
    }

    /**
     * Generate unique order ID after the record has been stored.
     *
     * Uses the same pattern as J2Store: time() . primary_key
     * Must be called AFTER store() so the auto-increment PK is available.
     */
    public function generateOrderId(): string
    {
        return (string) (time() . $this->j2commerce_order_id);
    }

    /**
     * Generate secure token from order_id.
     *
     * Uses the same pattern as J2Store: md5 hash of order_id + secret.
     */
    public function generateToken(): string
    {
        return md5($this->order_id . bin2hex(random_bytes(8)));
    }

    /**
     * Check if order has specific status(es).
     *
     * @param   int|array  $statusIds  Status ID or array of status IDs.
     *
     * @return  bool  True if order has one of the statuses.
     */
    public function hasStatus(int|array $statusIds): bool
    {
        if (is_array($statusIds)) {
            return in_array((int) $this->order_state_id, $statusIds, true);
        }

        return (int) $this->order_state_id === $statusIds;
    }
}
