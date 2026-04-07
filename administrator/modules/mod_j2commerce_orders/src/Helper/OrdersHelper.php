<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_orders
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Orders\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class OrdersHelper
{
    public function getLatestOrders(int $limit = 5, array $statusIds = []): array
    {
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $query     = $db->getQuery(true);
        $orderType = 'normal';

        $query->select([
                $db->quoteName('a.j2commerce_order_id'),
                $db->quoteName('a.order_id'),
                $db->quoteName('a.user_email'),
                $db->quoteName('a.order_total'),
                $db->quoteName('a.currency_code'),
                $db->quoteName('a.currency_value'),
                $db->quoteName('a.order_state_id'),
                $db->quoteName('a.created_on'),
                $db->quoteName('oi.billing_first_name'),
                $db->quoteName('oi.billing_last_name'),
                $db->quoteName('os.orderstatus_name'),
                $db->quoteName('os.orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orders', 'a'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderinfos', 'oi'), $db->quoteName('a.order_id') . ' = ' . $db->quoteName('oi.order_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_orderstatuses', 'os'), $db->quoteName('a.order_state_id') . ' = ' . $db->quoteName('os.j2commerce_orderstatus_id'))
            ->where($db->quoteName('a.order_type') . ' = :order_type')
            ->bind(':order_type', $orderType)
            ->order($db->quoteName('a.created_on') . ' DESC')
            ->setLimit($limit);

        // Filter by selected status IDs
        $statusIds = array_filter(array_map('intval', $statusIds));

        if (!empty($statusIds)) {
            $placeholders = [];

            foreach ($statusIds as $i => $statusId) {
                $placeholder    = ':status_' . $i;
                $placeholders[] = $placeholder;
                $query->bind($placeholder, $statusIds[$i], ParameterType::INTEGER);
            }

            $query->where($db->quoteName('a.order_state_id') . ' IN (' . implode(',', $placeholders) . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }
}
