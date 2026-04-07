<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderItemAttributeHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

class ConfirmationModel extends BaseDatabaseModel
{
    protected string $_context = 'com_j2commerce.confirmation';

    protected ?object $_order         = null;
    protected ?array $_orderItems     = null;
    protected ?object $_orderInfo     = null;
    protected ?array $_orderShippings = null;
    protected ?array $_orderTaxes     = null;
    protected ?array $_orderDiscounts = null;
    protected ?array $_orderFees      = null;

    protected function populateState(): void
    {
        $app = Factory::getApplication();

        $orderId = $app->getInput()->getString('order_id', '');
        $this->setState('order_id', $orderId);

        $token = $app->getInput()->getString('token', '');
        $this->setState('token', $token);

        $params = $app->getParams();
        $this->setState('params', $params);
    }

    /**
     * Load the order and verify ownership/authorisation.
     *
     * @return  object|null  The OrderTable object, or null if not found / not authorised.
     */
    public function getOrder(): ?object
    {
        if ($this->_order !== null) {
            return $this->_order;
        }

        $orderId = (string) $this->getState('order_id');

        if ($orderId === '') {
            // No order_id in URL — try most recent order for logged-in users
            $recentOrder = $this->getMostRecentOrder();

            if ($recentOrder) {
                $this->_order = $recentOrder;
                $this->setState('showing_recent', true);

                return $this->_order;
            }

            return null;
        }

        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        if (!$orderTable || !$orderTable->load(['order_id' => $orderId])) {
            return null;
        }

        if (!$this->isAuthorised($orderTable)) {
            return null;
        }

        $this->_order = $orderTable;

        return $this->_order;
    }

    public function getPluginHtml(): string
    {
        $html = Factory::getApplication()->getUserState('j2commerce.confirmation_plugin_html', '');

        return \is_string($html) ? $html : '';
    }

    public function getOrderItems(): array
    {
        if ($this->_orderItems !== null) {
            return $this->_orderItems;
        }

        $order = $this->getOrder();

        if (!$order) {
            return $this->_orderItems = [];
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        if (empty($items)) {
            return $this->_orderItems = [];
        }

        // Batch-load attributes from the orderitemattributes table
        $itemIds = array_map(fn ($i) => (int) $i->j2commerce_orderitem_id, $items);

        $attrQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderitemattributes'))
            ->whereIn($db->quoteName('orderitem_id'), $itemIds);

        $db->setQuery($attrQuery);
        $allAttrs = $db->loadObjectList() ?: [];

        // Group attributes by orderitem_id
        $attrMap = [];
        foreach ($allAttrs as $attr) {
            $attrMap[(int) $attr->orderitem_id][] = $attr;
        }

        // Attach attributes to each item, falling back to inline column parsing
        foreach ($items as $item) {
            $itemId = (int) $item->j2commerce_orderitem_id;

            if (!empty($attrMap[$itemId])) {
                $item->orderitemattributes = $attrMap[$itemId];
                continue;
            }

            // Fallback: parse the orderitem_attributes column via shared helper
            $item->orderitemattributes = OrderItemAttributeHelper::parseRawAttributes(
                $item->orderitem_attributes ?? '',
                (int) ($item->product_id ?? 0)
            );
        }

        return $this->_orderItems = $items;
    }

    public function getOrderInfo(): ?object
    {
        if ($this->_orderInfo !== null) {
            return $this->_orderInfo;
        }

        $order = $this->getOrder();

        if (!$order) {
            return null;
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderinfos'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $this->_orderInfo = $db->loadObject();
    }

    public function getOrderShippings(): array
    {
        if ($this->_orderShippings !== null) {
            return $this->_orderShippings;
        }

        $order = $this->getOrder();

        if (!$order) {
            return $this->_orderShippings = [];
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $this->_orderShippings = $db->loadObjectList() ?: [];
    }

    public function getOrderTaxes(): array
    {
        if ($this->_orderTaxes !== null) {
            return $this->_orderTaxes;
        }

        $order = $this->getOrder();

        if (!$order) {
            return $this->_orderTaxes = [];
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_ordertaxes'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $this->_orderTaxes = $db->loadObjectList() ?: [];
    }

    public function getOrderFees(): array
    {
        if ($this->_orderFees !== null) {
            return $this->_orderFees;
        }

        $order = $this->getOrder();

        if (!$order) {
            return $this->_orderFees = [];
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $this->_orderFees = $db->loadObjectList() ?: [];
    }

    public function getOrderDiscounts(): array
    {
        if ($this->_orderDiscounts !== null) {
            return $this->_orderDiscounts;
        }

        $order = $this->getOrder();

        if (!$order) {
            return $this->_orderDiscounts = [];
        }

        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $orderId = $order->order_id;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        return $this->_orderDiscounts = $db->loadObjectList() ?: [];
    }

    /**
     * Load the most recent order for the current logged-in user.
     */
    public function getMostRecentOrder(): ?object
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->id <= 0) {
            return null;
        }

        $db     = $this->getDatabase();
        $userId = $user->id;

        $query = $db->getQuery(true)
            ->select($db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->order($db->quoteName('created_on') . ' DESC')
            ->setLimit(1);

        $db->setQuery($query);
        $orderId = $db->loadResult();

        if (!$orderId) {
            return null;
        }

        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        if (!$orderTable || !$orderTable->load(['order_id' => $orderId])) {
            return null;
        }

        return $orderTable;
    }

    protected function isAuthorised(object $orderTable): bool
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        // Logged-in user owns this order
        if ($user && $user->id > 0 && (int) $orderTable->user_id === $user->id) {
            return true;
        }

        // URL token check — allows bookmarks, back button, email links
        $urlToken = (string) $this->getState('token');

        if ($urlToken !== '' && hash_equals($orderTable->token ?? '', $urlToken)) {
            return true;
        }

        $session      = $app->getSession();
        $sessionToken = $session->get('guest_order_token', '', 'j2commerce');

        if (!empty($sessionToken) && $sessionToken === ($orderTable->token ?? '')) {
            return true;
        }

        $sessionEmail = $session->get('guest_order_email', '', 'j2commerce');

        if (!empty($sessionEmail)
            && !empty($orderTable->user_email)
            && strtolower($sessionEmail) === strtolower($orderTable->user_email)
        ) {
            return true;
        }

        return false;
    }
}
