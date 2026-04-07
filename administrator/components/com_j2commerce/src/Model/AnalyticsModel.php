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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

/**
 * Analytics Model
 *
 * Provides aggregated analytics data for the J2Commerce admin dashboard
 * including revenue, order counts, top products, and checkout funnel metrics.
 *
 * @since  6.0.0
 */
class AnalyticsModel extends BaseDatabaseModel
{
    /**
     * Completed order state IDs (Confirmed, Processed, Shipped)
     *
     * @var    array
     * @since  6.0.0
     */
    private const COMPLETED_STATES = [1, 2, 7];

    /**
     * Get total revenue for completed orders within a date range.
     *
     * @param   string  $from  Start date (inclusive, Y-m-d H:i:s or Y-m-d format)
     * @param   string  $to    End date (inclusive, Y-m-d H:i:s or Y-m-d format)
     *
     * @return  float  Total revenue amount
     *
     * @since   6.0.0
     */
    public function getTotalRevenue(string $from, string $to): float
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COALESCE(SUM(' . $db->quoteName('a.order_total') . '), 0)')
            ->from($db->quoteName('#__j2commerce_orders', 'a'));

        $this->addCompletedOrdersFilter($query, $from, $to, 'a');

        $db->setQuery($query);

        return (float) $db->loadResult();
    }

    /**
     * Get order count (all states) within a date range.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  int  Number of orders
     *
     * @since   6.0.0
     */
    public function getOrderCount(string $from, string $to): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $normalType = 'normal';

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->where($db->quoteName('a.created_on') . ' >= :from')
            ->where($db->quoteName('a.created_on') . ' <= :to')
            ->bind(':orderType', $normalType, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get average order value for completed orders within a date range.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  float  Average order value
     *
     * @since   6.0.0
     */
    public function getAverageOrderValue(string $from, string $to): float
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COALESCE(AVG(' . $db->quoteName('a.order_total') . '), 0)')
            ->from($db->quoteName('#__j2commerce_orders', 'a'));

        $this->addCompletedOrdersFilter($query, $from, $to, 'a');

        $db->setQuery($query);

        return (float) $db->loadResult();
    }

    /**
     * Get total items sold for completed orders within a date range.
     *
     * Note: orderitem_quantity is varchar(255) in the database, so CAST is required.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  int  Total items sold
     *
     * @since   6.0.0
     */
    public function getItemsSold(string $from, string $to): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('COALESCE(SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS UNSIGNED)), 0)')
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id')
            );

        $this->addCompletedOrdersFilter($query, $from, $to, 'o');

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get revenue grouped by day for completed orders within a date range.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Array of objects with ->day (Y-m-d) and ->revenue (float)
     *
     * @since   6.0.0
     */
    public function getRevenueByDay(string $from, string $to): array
    {
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true);
        $tzOffset = $this->getStoreTimezoneOffset();

        $dateExpr = 'DATE(CONVERT_TZ(' . $db->quoteName('a.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '))';

        $query->select([
                $dateExpr . ' AS ' . $db->quoteName('day'),
                'COALESCE(SUM(' . $db->quoteName('a.order_total') . '), 0) AS ' . $db->quoteName('revenue'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'));

        $this->addCompletedOrdersFilter($query, $from, $to, 'a');

        $query->group($dateExpr)
            ->order($db->quoteName('day') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get order count grouped by day (all states) within a date range.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Array of objects with ->day (Y-m-d) and ->count (int)
     *
     * @since   6.0.0
     */
    public function getOrdersByDay(string $from, string $to): array
    {
        $db       = $this->getDatabase();
        $query    = $db->getQuery(true);
        $tzOffset = $this->getStoreTimezoneOffset();

        $normalType = 'normal';
        $dateExpr   = 'DATE(CONVERT_TZ(' . $db->quoteName('a.created_on') . ', ' . $db->quote('+00:00') . ', ' . $db->quote($tzOffset) . '))';

        $query->select([
                $dateExpr . ' AS ' . $db->quoteName('day'),
                'COUNT(*) AS ' . $db->quoteName('count'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->where($db->quoteName('a.created_on') . ' >= :from')
            ->where($db->quoteName('a.created_on') . ' <= :to')
            ->bind(':orderType', $normalType, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group($dateExpr)
            ->order($db->quoteName('day') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get top-selling products for completed orders within a date range.
     *
     * Note: orderitem_quantity is varchar(255) in the database, so CAST is required.
     *
     * @param   string  $from   Start date (inclusive)
     * @param   string  $to     End date (inclusive)
     * @param   int     $limit  Maximum number of products to return
     *
     * @return  array  Array of objects with ->name, ->product_id, ->total_qty, ->total_revenue
     *
     * @since   6.0.0
     */
    public function getTopProducts(string $from, string $to, int $limit = 10): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('oi.orderitem_name', 'name'),
                $db->quoteName('oi.product_id'),
                'SUM(CAST(' . $db->quoteName('oi.orderitem_quantity') . ' AS UNSIGNED)) AS ' . $db->quoteName('total_qty'),
                'SUM(' . $db->quoteName('oi.orderitem_finalprice') . ') AS ' . $db->quoteName('total_revenue'),
            ])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_orders', 'o')
                . ' ON ' . $db->quoteName('oi.order_id') . ' = ' . $db->quoteName('o.order_id')
            );

        $this->addCompletedOrdersFilter($query, $from, $to, 'o');

        $query->group([
                $db->quoteName('oi.product_id'),
                $db->quoteName('oi.orderitem_name'),
            ])
            ->order($db->quoteName('total_qty') . ' DESC')
            ->setLimit($limit);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get order status distribution within a date range.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Array of objects with ->state_id, ->status_name, ->count
     *
     * @since   6.0.0
     */
    public function getOrderStatusDistribution(string $from, string $to): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $normalType = 'normal';

        $query->select([
                $db->quoteName('a.order_state_id', 'state_id'),
                'COALESCE(' . $db->quoteName('s.orderstatus_name') . ', ' . $db->quote('Unknown') . ') AS '
                    . $db->quoteName('status_name'),
                'COUNT(*) AS ' . $db->quoteName('count'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_orderstatuses', 's')
                . ' ON ' . $db->quoteName('a.order_state_id') . ' = ' . $db->quoteName('s.j2commerce_orderstatus_id')
            )
            ->where($db->quoteName('a.order_type') . ' = :orderType')
            ->where($db->quoteName('a.created_on') . ' >= :from')
            ->where($db->quoteName('a.created_on') . ' <= :to')
            ->bind(':orderType', $normalType, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group([
                $db->quoteName('a.order_state_id'),
                $db->quoteName('s.orderstatus_name'),
            ])
            ->order($db->quoteName('count') . ' DESC');

        $db->setQuery($query);

        $results = $db->loadObjectList() ?: [];

        // Translate status names — DB stores language constants like J2COMMERCE_CONFIRMED
        foreach ($results as $row) {
            $row->status_name = Text::_($row->status_name);
        }

        return $results;
    }

    /**
     * Get checkout funnel data from action logs within a date range.
     *
     * Returns an associative array mapping action log language keys to their
     * occurrence count, representing each step of the checkout process.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Associative array of message_language_key => count
     *
     * @since   6.0.0
     */
    public function getCheckoutFunnel(string $from, string $to): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';

        $funnelSteps = [
            'PLG_ACTIONLOG_J2COMMERCE_CART_ADD',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_START',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_LOGIN',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_BILLING',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_SHIPPING',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_PAYMENT',
            'PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_CONFIRM',
            'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS',
        ];

        $query->select([
                $db->quoteName('message_language_key'),
                'COUNT(*) AS ' . $db->quoteName('count'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->whereIn($db->quoteName('message_language_key'), $funnelSteps)
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group($db->quoteName('message_language_key'));

        $db->setQuery($query);

        return $db->loadAssocList('message_language_key', 'count') ?: [];
    }

    /**
     * Get hourly session counts from action logs for a single date.
     *
     * A "session" is a distinct user_id that triggered any j2commerce action
     * within a given hour. Guest actions (user_id=0) are each counted as a
     * separate session since we cannot distinguish individual guests.
     *
     * @param   string  $date  Date in Y-m-d format
     *
     * @return  array  Associative array of hour (0-23) => session count
     *
     * @since   6.0.0
     */
    public function getSessionsByHour(string $date): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';
        $from      = $date . ' 00:00:00';
        $to        = $date . ' 23:59:59';

        // For registered users: count DISTINCT user_id per hour
        // For guests (user_id=0): count each log entry as a separate session
        $query->select([
                'HOUR(' . $db->quoteName('log_date') . ') AS ' . $db->quoteName('hour'),
                'COUNT(DISTINCT CASE WHEN ' . $db->quoteName('user_id') . ' > 0 THEN '
                    . $db->quoteName('user_id') . ' ELSE ' . $db->quoteName('id') . ' END) AS '
                    . $db->quoteName('sessions'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group('HOUR(' . $db->quoteName('log_date') . ')');

        $db->setQuery($query);

        $rows   = $db->loadObjectList() ?: [];
        $result = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            $result[(int) $row->hour] = (int) $row->sessions;
        }

        return $result;
    }

    /**
     * Get hourly conversion counts (completed orders) from action logs for a single date.
     *
     * @param   string  $date  Date in Y-m-d format
     *
     * @return  array  Associative array of hour (0-23) => conversion count
     *
     * @since   6.0.0
     */
    public function getConversionsByHour(string $date): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';
        $langKey   = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS';
        $from      = $date . ' 00:00:00';
        $to        = $date . ' 23:59:59';

        $query->select([
                'HOUR(' . $db->quoteName('log_date') . ') AS ' . $db->quoteName('hour'),
                'COUNT(*) AS ' . $db->quoteName('conversions'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('message_language_key') . ' = :langKey')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':langKey', $langKey, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group('HOUR(' . $db->quoteName('log_date') . ')');

        $db->setQuery($query);

        $rows   = $db->loadObjectList() ?: [];
        $result = array_fill(0, 24, 0);

        foreach ($rows as $row) {
            $result[(int) $row->hour] = (int) $row->conversions;
        }

        return $result;
    }

    /**
     * Get total session counts by hour of day across a date range.
     *
     * Shows when sessions occur during the day by summing all sessions
     * per hour across the entire period.
     *
     * @param   string  $from  Start datetime (Y-m-d H:i:s)
     * @param   string  $to    End datetime (Y-m-d H:i:s)
     *
     * @return  array  ['hourly' => int[24] total per hour, 'total' => int total sessions]
     *
     * @since   6.0.0
     */
    public function getSessionsByHourAvg(string $from, string $to): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';

        $query->select([
                'HOUR(' . $db->quoteName('log_date') . ') AS ' . $db->quoteName('hour'),
                'COUNT(DISTINCT CASE WHEN ' . $db->quoteName('user_id') . ' > 0 THEN '
                    . $db->quoteName('user_id') . ' ELSE ' . $db->quoteName('id') . ' END) AS '
                    . $db->quoteName('sessions'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group('HOUR(' . $db->quoteName('log_date') . ')');

        $db->setQuery($query);

        $rows   = $db->loadObjectList() ?: [];
        $hourly = array_fill(0, 24, 0);
        $total  = 0;

        foreach ($rows as $row) {
            $hourly[(int) $row->hour] = (int) $row->sessions;
            $total += (int) $row->sessions;
        }

        return ['hourly' => $hourly, 'total' => $total];
    }

    /**
     * Get total conversion counts by hour of day across a date range.
     *
     * Shows when conversions occur during the day by summing all completed
     * orders per hour across the entire period.
     *
     * @param   string  $from  Start datetime (Y-m-d H:i:s)
     * @param   string  $to    End datetime (Y-m-d H:i:s)
     *
     * @return  array  ['hourly' => int[24] total per hour, 'total' => int total conversions]
     *
     * @since   6.0.0
     */
    public function getConversionsByHourAvg(string $from, string $to): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';
        $langKey   = 'PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS';

        $query->select([
                'HOUR(' . $db->quoteName('log_date') . ') AS ' . $db->quoteName('hour'),
                'COUNT(*) AS ' . $db->quoteName('conversions'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('message_language_key') . ' = :langKey')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':langKey', $langKey, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group('HOUR(' . $db->quoteName('log_date') . ')');

        $db->setQuery($query);

        $rows   = $db->loadObjectList() ?: [];
        $hourly = array_fill(0, 24, 0);
        $total  = 0;

        foreach ($rows as $row) {
            $hourly[(int) $row->hour] = (int) $row->conversions;
            $total += (int) $row->conversions;
        }

        return ['hourly' => $hourly, 'total' => $total];
    }

    /**
     * Get conversion rate breakdown as funnel percentages.
     *
     * Returns the total session count and the percentage of sessions that
     * reached each funnel stage: added to cart, reached checkout, completed order.
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Associative array with total, rates, and counts
     *
     * @since   6.0.0
     */
    public function getConversionBreakdown(string $from, string $to): array
    {
        $funnel = $this->getCheckoutFunnel($from, $to);

        // Total sessions = distinct users with any action in the period
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';

        $query->select(
            'COUNT(DISTINCT CASE WHEN ' . $db->quoteName('user_id') . ' > 0 THEN '
                    . $db->quoteName('user_id') . ' ELSE ' . $db->quoteName('id') . ' END) AS '
                    . $db->quoteName('total')
        )
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING);

        $db->setQuery($query);
        $totalSessions = max(1, (int) $db->loadResult());

        $addedToCart      = (int) ($funnel['PLG_ACTIONLOG_J2COMMERCE_CART_ADD'] ?? 0);
        $reachedCheckout  = (int) ($funnel['PLG_ACTIONLOG_J2COMMERCE_CHECKOUT_START'] ?? 0);
        $completedOrder   = (int) ($funnel['PLG_ACTIONLOG_J2COMMERCE_ORDER_PAYMENT_SUCCESS'] ?? 0);

        $overallRate = $totalSessions > 0 ? round(($completedOrder / $totalSessions) * 100, 1) : 0.0;

        return [
            'overallRate'     => $overallRate,
            'totalSessions'   => $totalSessions,
            'addedToCart'     => $addedToCart,
            'reachedCheckout' => $reachedCheckout,
            'completedOrder'  => $completedOrder,
            'rates'           => [
                'sessions'        => 100.0,
                'addedToCart'     => $totalSessions > 0 ? round(($addedToCart / $totalSessions) * 100, 1) : 0.0,
                'reachedCheckout' => $totalSessions > 0 ? round(($reachedCheckout / $totalSessions) * 100, 1) : 0.0,
                'completedOrder'  => $overallRate,
            ],
        ];
    }

    /**
     * Get device type distribution from action log message JSON.
     *
     * Parses the `device_type` field from each action log entry's message JSON.
     * Entries without a device_type are categorised as "Unknown".
     *
     * @param   string  $from  Start date (inclusive)
     * @param   string  $to    End date (inclusive)
     *
     * @return  array  Array of objects with ->device and ->count
     *
     * @since   6.0.0
     */
    public function getDeviceTypes(string $from, string $to): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $extension = 'com_j2commerce';

        $query->select([
                'COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(' . $db->quoteName('message')
                    . ', \'$.device_type\')), \'null\'), \'Unknown\') AS ' . $db->quoteName('device'),
                'COUNT(DISTINCT CASE WHEN ' . $db->quoteName('user_id') . ' > 0 THEN '
                    . $db->quoteName('user_id') . ' ELSE ' . $db->quoteName('id') . ' END) AS '
                    . $db->quoteName('count'),
            ])
            ->from($db->quoteName('#__action_logs'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('log_date') . ' >= :from')
            ->where($db->quoteName('log_date') . ' <= :to')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':from', $from, ParameterType::STRING)
            ->bind(':to', $to, ParameterType::STRING)
            ->group($db->quoteName('device'))
            ->order($db->quoteName('count') . ' DESC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get KPI data for a previous period of equal length for comparison.
     *
     * Given the current from/to range, calculates the equivalent previous period
     * and returns the same KPI metrics for comparison (% change arrows).
     *
     * @param   string  $from  Current period start date (Y-m-d H:i:s)
     * @param   string  $to    Current period end date (Y-m-d H:i:s)
     *
     * @return  array  Associative array with totalRevenue, orderCount, averageOrderValue, itemsSold
     *
     * @since   6.0.0
     */
    public function getPreviousPeriodData(string $from, string $to): array
    {
        // Calculate the number of days in the current period
        $fromTs = strtotime($from);
        $toTs   = strtotime($to);
        $days   = max(1, (int) round(($toTs - $fromTs) / 86400));

        // Calculate previous period of equal length
        $prevTo   = date('Y-m-d H:i:s', $fromTs - 1);
        $prevFrom = date('Y-m-d H:i:s', $fromTs - ($days * 86400));

        $prevBreakdown = $this->getConversionBreakdown($prevFrom, $prevTo);

        return [
            'totalRevenue'   => $this->getTotalRevenue($prevFrom, $prevTo),
            'orderCount'     => $this->getOrderCount($prevFrom, $prevTo),
            'conversionRate' => (float) ($prevBreakdown['overallRate'] ?? 0.0),
            'totalSessions'  => (int) ($prevBreakdown['totalSessions'] ?? 0),
        ];
    }

    /**
     * Add completed orders filter to a query.
     *
     * Adds the following conditions:
     * - order_state_id IN (1, 2, 7) (Confirmed, Processed, Shipped)
     * - order_type = 'normal'
     * - created_on within the specified date range
     *
     * @param   DatabaseQuery  $query  The query to modify
     * @param   string         $from   Start date (inclusive)
     * @param   string         $to     End date (inclusive)
     * @param   string         $alias  Table alias (default 'a')
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function getStoreTimezoneOffset(): string
    {
        $tz     = Factory::getApplication()->getConfig()->get('offset', 'UTC');
        $offset = (new \DateTimeZone($tz))->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $sign   = $offset >= 0 ? '+' : '-';
        $hours  = str_pad((string) (int) (abs($offset) / 3600), 2, '0', STR_PAD_LEFT);
        $mins   = str_pad((string) (int) ((abs($offset) % 3600) / 60), 2, '0', STR_PAD_LEFT);

        return $sign . $hours . ':' . $mins;
    }

    private function addCompletedOrdersFilter(DatabaseQuery $query, string $from, string $to, string $alias = 'a'): void
    {
        $db = $this->getDatabase();

        $normalType = 'normal';

        // Generate unique parameter names using the alias to avoid collisions
        // when the same query has multiple calls (not expected, but defensive)
        $fromParam      = ':from_' . $alias;
        $toParam        = ':to_' . $alias;
        $orderTypeParam = ':orderType_' . $alias;

        $query->whereIn($db->quoteName($alias . '.order_state_id'), self::COMPLETED_STATES, ParameterType::INTEGER)
            ->where($db->quoteName($alias . '.order_type') . ' = ' . $orderTypeParam)
            ->where($db->quoteName($alias . '.created_on') . ' >= ' . $fromParam)
            ->where($db->quoteName($alias . '.created_on') . ' <= ' . $toParam)
            ->bind($orderTypeParam, $normalType, ParameterType::STRING)
            ->bind($fromParam, $from, ParameterType::STRING)
            ->bind($toParam, $to, ParameterType::STRING);
    }
}
