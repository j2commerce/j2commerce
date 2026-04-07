<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class OrderHistoryHelper
{
    public static function add(
        string $orderId,
        string $comment,
        int $orderStateId = 0,
        bool $notifyCustomer = false,
        int $createdBy = 0,
    ): bool {
        if (empty($orderId)) {
            return false;
        }

        // If no state ID provided, look up the current order state
        if ($orderStateId === 0) {
            $orderStateId = self::getCurrentStateId($orderId);
        }

        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $now = Factory::getDate()->toSql();

            if ($createdBy === 0) {
                $user      = Factory::getApplication()->getIdentity();
                $createdBy = $user?->id ?? 0;
            }

            $notifyInt   = $notifyCustomer ? 1 : 0;
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
                ->bind(':statusId', $orderStateId, ParameterType::INTEGER)
                ->bind(':notify', $notifyInt, ParameterType::INTEGER)
                ->bind(':comment', $comment)
                ->bind(':createdOn', $now)
                ->bind(':createdBy', $createdBy, ParameterType::INTEGER)
                ->bind(':params', $emptyParams);

            $db->setQuery($query);

            return $db->execute();
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function getCurrentStateId(string $orderId): int
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('order_state_id'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderId);

            $db->setQuery($query);

            return (int) ($db->loadResult() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
