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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

class MyprofileModel extends BaseDatabaseModel
{
    /**
     * @return array{orders: array, total: int}
     */
    /**
     * @return array{orders: array, total: int}
     */
    public function getOrders(
        int $userId,
        string $guestToken = '',
        string $guestEmail = '',
        int $limitStart = 0,
        int $limit = 20,
        string $search = ''
    ): array {
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $orderType = 'normal';

        $query->select($db->quoteName([
                'a.j2commerce_order_id',
                'a.order_id',
                'a.order_total',
                'a.order_state_id',
                'a.order_state',
                'a.created_on',
                'a.invoice_prefix',
                'a.invoice_number',
                'a.currency_code',
                'a.currency_value',
                'a.user_id',
                'a.user_email',
                'a.token',
            ]))
            ->select($db->quoteName('s.orderstatus_name'))
            ->select($db->quoteName('s.orderstatus_cssclass'))
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderstatuses', 's')
                . ' ON ' . $db->quoteName('s.j2commerce_orderstatus_id') . ' = ' . $db->quoteName('a.order_state_id'))
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->bind(':orderType', $orderType, ParameterType::STRING);

        if ($userId > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('a.token') . ' = :token')
                ->bind(':token', $guestToken, ParameterType::STRING);
            $query->where($db->quoteName('a.user_email') . ' = :email')
                ->bind(':email', $guestEmail, ParameterType::STRING);
        }

        // Search filter: order_id, orderitem_name, orderitem_sku, variant UPC
        if (!empty($search)) {
            $like = '%' . trim($search) . '%';
            $query->where(
                '(' . $db->quoteName('a.order_id') . ' LIKE :srch1'
                . ' OR EXISTS ('
                    . 'SELECT 1 FROM ' . $db->quoteName('#__j2commerce_orderitems', 'oi')
                    . ' WHERE ' . $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('a.order_id')
                    . ' AND (' . $db->quoteName('oi.orderitem_name') . ' LIKE :srch2'
                    . ' OR ' . $db->quoteName('oi.orderitem_sku') . ' LIKE :srch3)'
                . ')'
                . ' OR EXISTS ('
                    . 'SELECT 1 FROM ' . $db->quoteName('#__j2commerce_orderitems', 'oi2')
                    . ' JOIN ' . $db->quoteName('#__j2commerce_variants', 'v')
                    . ' ON ' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('oi2.variant_id')
                    . ' WHERE ' . $db->quoteName('oi2.order_id') . ' = ' . $db->quoteName('a.order_id')
                    . ' AND ' . $db->quoteName('v.upc') . ' LIKE :srch4'
                . ')'
                . ')'
            )
                ->bind(':srch1', $like)
                ->bind(':srch2', $like)
                ->bind(':srch3', $like)
                ->bind(':srch4', $like);
        }

        // Filter by allowed order statuses (config: limit_orderstatuses)
        $params        = ComponentHelper::getParams('com_j2commerce');
        $limitStatuses = $params->get('limit_orderstatuses', '');

        if (!empty($limitStatuses)) {
            $statusIds = \is_array($limitStatuses)
                ? array_map('intval', $limitStatuses)
                : array_map('intval', explode(',', (string) $limitStatuses));

            $statusIds = array_filter($statusIds, fn ($v) => $v > 0);

            if (\count($statusIds) > 0) {
                $placeholders = [];

                foreach ($statusIds as $i => $id) {
                    $placeholder    = ':orderStatus' . $i;
                    $placeholders[] = $placeholder;
                    $query->bind($placeholder, $statusIds[$i], ParameterType::INTEGER);
                }

                $query->where($db->quoteName('a.order_state_id') . ' IN (' . implode(',', $placeholders) . ')');
            }
        }

        $query->order($db->quoteName('a.created_on') . ' DESC');

        // Get total count first (clone before setting limits)
        $countQuery = clone $query;
        $countQuery->clear('select')->clear('order')->select('COUNT(*)');
        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        // Apply pagination
        $db->setQuery($query, $limitStart, $limit);
        $orders = $db->loadObjectList();

        return ['orders' => $orders, 'total' => $total];
    }

    public function getOrder(string $orderId): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('a') . '.*')
            ->select($db->quoteName('s.orderstatus_name'))
            ->select($db->quoteName('s.orderstatus_cssclass'))
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderstatuses', 's')
                . ' ON ' . $db->quoteName('s.j2commerce_orderstatus_id') . ' = ' . $db->quoteName('a.order_state_id'))
            ->where($db->quoteName('a.order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    public function getOrderItems(string $orderId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'j2commerce_orderitem_id',
                'order_id',
                'product_id',
                'orderitem_name',
                'orderitem_sku',
                'orderitem_quantity',
                'orderitem_price',
                'orderitem_finalprice',
                'orderitem_finalprice_with_tax',
                'orderitem_finalprice_without_tax',
                'orderitem_tax',
                'orderitem_discount',
                'orderitem_attributes',
                'orderitem_option_price',
                'orderitem_params',
            ]))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        foreach ($items as $item) {
            $item->orderitemattributes = OrderItemAttributeHelper::parseRawAttributes(
                $item->orderitem_attributes ?? '',
                (int) ($item->product_id ?? 0)
            );
        }

        return $items;
    }

    public function getOrderInfo(string $orderId): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_orderinfos'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    public function getOrderHistory(string $orderId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'h.j2commerce_orderhistory_id',
                'h.order_id',
                'h.order_state_id',
                'h.notify_customer',
                'h.comment',
                'h.created_on',
            ]))
            ->select($db->quoteName('s.orderstatus_name'))
            ->from($db->quoteName('#__j2commerce_orderhistories', 'h'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderstatuses', 's')
                . ' ON ' . $db->quoteName('s.j2commerce_orderstatus_id') . ' = ' . $db->quoteName('h.order_state_id'))
            ->where($db->quoteName('h.order_id') . ' = :orderId')
            ->where($db->quoteName('h.params') . ' NOT LIKE ' . $db->quote('%"type":"admin_note"%'))
            ->bind(':orderId', $orderId, ParameterType::STRING)
            ->order($db->quoteName('h.created_on') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getOrderShippings(string $orderId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'ordershipping_name',
                'ordershipping_price',
                'ordershipping_tax',
                'ordershipping_type',
            ]))
            ->from($db->quoteName('#__j2commerce_ordershippings'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getOrderTaxes(string $orderId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'ordertax_title',
                'ordertax_percent',
                'ordertax_amount',
            ]))
            ->from($db->quoteName('#__j2commerce_ordertaxes'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getOrderFees(string $orderId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['name', 'amount', 'tax', 'fee_type']))
            ->from($db->quoteName('#__j2commerce_orderfees'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_orderfee_id') . ' ASC')
            ->bind(':orderId', $orderId, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get downloads for a user with optional search and pagination.
     *
     * @param   int     $userId      User ID (0 for guest)
     * @param   string  $guestEmail  Guest email
     * @param   int     $limitStart  Pagination offset
     * @param   int     $limit       Number of items per page
     * @param   string  $search      Search term (filters by order_id or file name)
     *
     * @return  array{downloads: array, total: int}
     */
    public function getDownloads(
        int $userId,
        string $guestEmail = '',
        int $limitStart = 0,
        int $limit = 20,
        string $search = ''
    ): array {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('d') . '.*')
            ->select($db->quoteName('f.j2commerce_productfile_id'))
            ->select($db->quoteName([
                'f.product_file_display_name',
                'f.product_file_save_name',
                'f.download_total',
            ]))
            ->select($db->quoteName([
                'o.invoice_prefix',
                'o.invoice_number',
                'o.order_state_id',
            ]))
            ->select($db->quoteName('p.params', 'product_params'))
            ->from($db->quoteName('#__j2commerce_orderdownloads', 'd'))
            ->join('LEFT', $db->quoteName('#__j2commerce_productfiles', 'f')
                . ' ON ' . $db->quoteName('f.product_id') . ' = ' . $db->quoteName('d.product_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('o.order_id') . ' = ' . $db->quoteName('d.order_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('d.product_id'));

        if ($userId > 0) {
            $query->where($db->quoteName('d.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('d.user_email') . ' = :email')
                ->bind(':email', $guestEmail, ParameterType::STRING);
        }

        // Search filter: order_id or file name
        if (!empty($search)) {
            $like = '%' . trim($search) . '%';
            $query->where(
                '(' . $db->quoteName('d.order_id') . ' LIKE :srch1'
                . ' OR ' . $db->quoteName('f.product_file_display_name') . ' LIKE :srch2'
                . ')'
            )
                ->bind(':srch1', $like)
                ->bind(':srch2', $like);
        }

        // Filter downloads by allowed order statuses (config: limit_orderstatuses)
        $params        = ComponentHelper::getParams('com_j2commerce');
        $limitStatuses = $params->get('limit_orderstatuses', '');

        if (!empty($limitStatuses)) {
            $statusIds = \is_array($limitStatuses)
                ? array_map('intval', $limitStatuses)
                : array_map('intval', explode(',', (string) $limitStatuses));

            $statusIds = array_filter($statusIds, fn ($v) => $v > 0);

            if (\count($statusIds) > 0) {
                $placeholders = [];

                foreach ($statusIds as $i => $id) {
                    $placeholder    = ':dlStatus' . $i;
                    $placeholders[] = $placeholder;
                    $query->bind($placeholder, $statusIds[$i], ParameterType::INTEGER);
                }

                $query->where($db->quoteName('o.order_state_id') . ' IN (' . implode(',', $placeholders) . ')');
            }
        }

        $query->order($db->quoteName('d.j2commerce_orderdownload_id') . ' DESC');

        // Get total count first (clone before setting limits)
        $countQuery = clone $query;
        $countQuery->clear('select')->clear('order')->select('COUNT(*)');
        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        // Apply pagination
        $db->setQuery($query, $limitStart, $limit);
        $downloads = $db->loadObjectList();

        return ['downloads' => $downloads, 'total' => $total];
    }

}
