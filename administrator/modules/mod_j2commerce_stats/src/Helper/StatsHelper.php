<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_stats
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Stats\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper class for mod_j2commerce_stats
 *
 * Provides order statistics calculations for various date ranges.
 *
 * @since  6.0.0
 */
class StatsHelper
{
    /**
     * Get order statistics for a date range
     *
     * @param   array        $orderStatuses  Array of status IDs to filter (empty or ['*'] = all)
     * @param   string|null  $since          Start date (Y-m-d H:i:s format)
     * @param   string|null  $until          End date (Y-m-d H:i:s format)
     *
     * @return  object  Object with ->count and ->total properties
     *
     * @since   6.0.0
     */
    public function getOrderStats(array $orderStatuses = [], ?string $since = null, ?string $until = null): object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $normalType = 'normal';

        $query->select([
            'COUNT(*) AS ' . $db->quoteName('count'),
            'COALESCE(SUM(' . $db->quoteName('order_total') . '), 0) AS ' . $db->quoteName('total'),
        ])
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_type') . ' = :orderType')
            ->where($db->quoteName('order_total') . ' > 0')
            ->bind(':orderType', $normalType, ParameterType::STRING);

        // Date range filter
        if ($since !== null) {
            $query->where($db->quoteName('created_on') . ' >= :since')
                ->bind(':since', $since);
        }

        if ($until !== null) {
            $query->where($db->quoteName('created_on') . ' <= :until')
                ->bind(':until', $until);
        }

        // Order status filter - only apply if not empty and not '*'
        if (!empty($orderStatuses) && !\in_array('*', $orderStatuses, true)) {
            // Convert to integers for proper binding
            $statusIds = array_map('intval', $orderStatuses);
            $query->whereIn($db->quoteName('order_state_id'), $statusIds);
        }

        $db->setQuery($query);
        $result = $db->loadObject();

        return (object) [
            'count' => (int) ($result->count ?? 0),
            'total' => (float) ($result->total ?? 0.0),
        ];
    }

    /**
     * Get all statistics needed for the module
     *
     * @param   array  $orderStatuses  Array of status IDs to filter
     *
     * @return  array  Array of statistics with keys: total, lastYear, thisYear, lastMonth, thisMonth, last7Days, yesterday, today, daysInMonth
     *
     * @since   6.0.0
     */
    public function getAllStats(array $orderStatuses = []): array
    {
        $app = Factory::getApplication();
        $tz  = $app->get('offset', 'UTC');

        try {
            $now = Factory::getDate('now', $tz);
        } catch (\Exception $e) {
            $now = Factory::getDate('now', 'UTC');
        }

        $currentYear  = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');
        $currentDay   = (int) $now->format('d');
        $lastYear     = $currentYear - 1;

        // Calculate last month and year
        $lastMonth     = $currentMonth - 1;
        $lastMonthYear = $currentYear;

        if ($lastMonth < 1) {
            $lastMonth     = 12;
            $lastMonthYear = $currentYear - 1;
        }

        // Get last day of current month
        $lastDayThisMonth = (int) $now->format('t');

        // Get last day of last month
        $lastMonthDate    = new \DateTime(\sprintf('%d-%02d-01', $lastMonthYear, $lastMonth));
        $lastDayLastMonth = (int) $lastMonthDate->format('t');

        // Date strings for recent periods
        $today     = $now->format('Y-m-d');
        $yesterday = Factory::getDate('now -1 days', $tz)->format('Y-m-d');
        $weekAgo   = Factory::getDate('now -6 days', $tz)->format('Y-m-d');

        return [
            'total'    => $this->getOrderStats($orderStatuses),
            'lastYear' => $this->getOrderStats(
                $orderStatuses,
                \sprintf('%d-01-01 00:00:00', $lastYear),
                \sprintf('%d-12-31 23:59:59', $lastYear)
            ),
            'thisYear' => $this->getOrderStats(
                $orderStatuses,
                \sprintf('%d-01-01 00:00:00', $currentYear),
                \sprintf('%d-12-31 23:59:59', $currentYear)
            ),
            'lastMonth' => $this->getOrderStats(
                $orderStatuses,
                \sprintf('%d-%02d-01 00:00:00', $lastMonthYear, $lastMonth),
                \sprintf('%d-%02d-%02d 23:59:59', $lastMonthYear, $lastMonth, $lastDayLastMonth)
            ),
            'thisMonth' => $this->getOrderStats(
                $orderStatuses,
                \sprintf('%d-%02d-01 00:00:00', $currentYear, $currentMonth),
                \sprintf('%d-%02d-%02d 23:59:59', $currentYear, $currentMonth, $lastDayThisMonth)
            ),
            'last7Days' => $this->getOrderStats(
                $orderStatuses,
                $weekAgo . ' 00:00:00',
                $today . ' 23:59:59'
            ),
            'yesterday' => $this->getOrderStats(
                $orderStatuses,
                $yesterday . ' 00:00:00',
                $yesterday . ' 23:59:59'
            ),
            'today' => $this->getOrderStats(
                $orderStatuses,
                $today . ' 00:00:00',
                $today . ' 23:59:59'
            ),
            'daysInMonth' => $currentDay,
        ];
    }
}
