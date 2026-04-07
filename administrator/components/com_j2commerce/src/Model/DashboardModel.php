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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Dashboard Model
 */
class DashboardModel extends BaseDatabaseModel
{
    private const COMPLETED_STATES = [1, 2, 7];

    private function getStoreTimezoneOffset(): string
    {
        $tz     = Factory::getApplication()->getConfig()->get('offset', 'UTC');
        $offset = (new \DateTimeZone($tz))->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $sign   = $offset >= 0 ? '+' : '-';
        $hours  = str_pad((string) (int) (abs($offset) / 3600), 2, '0', STR_PAD_LEFT);
        $mins   = str_pad((string) (int) ((abs($offset) % 3600) / 60), 2, '0', STR_PAD_LEFT);

        return $sign . $hours . ':' . $mins;
    }

    /**
     * Get total products count
     *
     * @return  int
     */
    public function getProductsCount(): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_products'));

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Get total orders count
     *
     * @return  int
     */
    public function getOrdersCount(): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders'));

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Get total customers count
     *
     * @return  int
     */
    public function getCustomersCount(): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $minUserId = 0;
        $query->select('COUNT(DISTINCT user_id)')
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('user_id') . ' > :minUserId')
            ->bind(':minUserId', $minUserId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Get recent orders
     *
     * @param   int  $limit  Number of orders to retrieve
     *
     * @return  array
     */
    /**
     * Get live users currently browsing the storefront (active within last 30 minutes)
     *
     * @return  array{total: int, guests: int, registered: int, users: array}
     */
    public function getLiveUsers(): array
    {
        $db        = $this->getDatabase();
        $threshold = time() - 1800;

        // Count active frontend sessions (client_id=0) within the last 30 minutes.
        // Backend requests (client_id=1) do NOT update frontend session timestamps,
        // so only genuine frontend activity is reflected here.
        $query = $db->getQuery(true)
            ->select('COUNT(*) AS total')
            ->select('SUM(CASE WHEN ' . $db->quoteName('guest') . ' = 1 THEN 1 ELSE 0 END) AS guests')
            ->select('SUM(CASE WHEN ' . $db->quoteName('guest') . ' = 0 THEN 1 ELSE 0 END) AS registered')
            ->from($db->quoteName('#__session'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('time') . ' >= :threshold')
            ->bind(':threshold', $threshold, ParameterType::INTEGER);

        $db->setQuery($query);
        $summary = $db->loadObject();

        // Recent logged-in frontend users (max 10)
        $query2 = $db->getQuery(true)
            ->select($db->quoteName(['s.userid', 's.username', 's.time']))
            ->from($db->quoteName('#__session', 's'))
            ->where($db->quoteName('s.client_id') . ' = 0')
            ->where($db->quoteName('s.guest') . ' = 0')
            ->where($db->quoteName('s.userid') . ' > 0')
            ->where($db->quoteName('s.time') . ' >= :threshold2')
            ->order($db->quoteName('s.time') . ' DESC')
            ->setLimit(10)
            ->bind(':threshold2', $threshold, ParameterType::INTEGER);

        $db->setQuery($query2);
        $users = $db->loadObjectList();

        return [
            'total'      => (int) ($summary->total ?? 0),
            'guests'     => (int) ($summary->guests ?? 0),
            'registered' => (int) ($summary->registered ?? 0),
            'users'      => $users ?: [],
        ];
    }

    public function getRecentOrders(int $limit = 5): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('order_id'),
                $db->quoteName('invoice_prefix'),
                $db->quoteName('invoice_number'),
                $db->quoteName('order_total'),
                $db->quoteName('created_on'),
            ])
             ->from($db->quoteName('#__j2commerce_orders'))
            ->order($db->quoteName('created_on') . ' DESC')
            ->setLimit($limit);

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    public function getMonthlySales(): array
    {
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true);
        $tzOffset = $this->getStoreTimezoneOffset();

        $normalType = 'normal';
        $monthExprA = 'DATE_FORMAT(CONVERT_TZ(' . $db->quoteName('a.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '), \'%Y-%m\')';

        $query->select([
                $monthExprA . ' AS ' . $db->quoteName('month'),
                'COALESCE(SUM(' . $db->quoteName('a.order_total') . '), 0) AS ' . $db->quoteName('revenue'),
                'COUNT(*) AS ' . $db->quoteName('orders'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->whereIn($db->quoteName('a.order_state_id'), self::COMPLETED_STATES, ParameterType::INTEGER)
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->bind(':orderType', $normalType, ParameterType::STRING)
            ->group($monthExprA)
            ->order($db->quoteName('month') . ' ASC');

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        // Items sold per month (separate query due to JOIN)
        $monthExprO = 'DATE_FORMAT(CONVERT_TZ(' . $db->quoteName('o.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '), \'%Y-%m\')';

        $itemsQuery = $db->getQuery(true)
            ->select([
                $monthExprO . ' AS ' . $db->quoteName('month'),
                'COALESCE(SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS UNSIGNED)), 0) AS ' . $db->quoteName('items'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o'), $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id'))
            ->whereIn($db->quoteName('o.order_state_id'), self::COMPLETED_STATES, ParameterType::INTEGER)
            ->where($db->quoteName('o.order_type') . ' = :orderType2')
            ->bind(':orderType2', $normalType, ParameterType::STRING)
            ->group($monthExprO)
            ->order($db->quoteName('month') . ' ASC');

        $itemRows = $db->setQuery($itemsQuery)->loadObjectList('month') ?: [];

        foreach ($rows as $row) {
            $row->items = (int) ($itemRows[$row->month]->items ?? 0);
        }

        return $rows;
    }

    public function getYearlySales(): array
    {
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true);
        $tzOffset = $this->getStoreTimezoneOffset();

        $normalType = 'normal';
        $yearExprA  = 'YEAR(CONVERT_TZ(' . $db->quoteName('a.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '))';

        $query->select([
                $yearExprA . ' AS ' . $db->quoteName('year'),
                'COALESCE(SUM(' . $db->quoteName('a.order_total') . '), 0) AS ' . $db->quoteName('revenue'),
                'COUNT(*) AS ' . $db->quoteName('orders'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->whereIn($db->quoteName('a.order_state_id'), self::COMPLETED_STATES, ParameterType::INTEGER)
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->bind(':orderType', $normalType, ParameterType::STRING)
            ->group($yearExprA)
            ->order($db->quoteName('year') . ' ASC');

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        // Items sold per year
        $yearExprO = 'YEAR(CONVERT_TZ(' . $db->quoteName('o.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '))';

        $itemsQuery = $db->getQuery(true)
            ->select([
                $yearExprO . ' AS ' . $db->quoteName('year'),
                'COALESCE(SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS UNSIGNED)), 0) AS ' . $db->quoteName('items'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->join('INNER', $db->quoteName('#__j2commerce_orders', 'o'), $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id'))
            ->whereIn($db->quoteName('o.order_state_id'), self::COMPLETED_STATES, ParameterType::INTEGER)
            ->where($db->quoteName('o.order_type') . ' = :orderType2')
            ->bind(':orderType2', $normalType, ParameterType::STRING)
            ->group($yearExprO)
            ->order($db->quoteName('year') . ' ASC');

        $itemRows = $db->setQuery($itemsQuery)->loadObjectList('year') ?: [];

        foreach ($rows as $row) {
            $row->items = (int) ($itemRows[$row->year]->items ?? 0);
        }

        return $rows;
    }
}
