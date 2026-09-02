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

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The one list of what belongs to an order, and the one routine that removes it.
 *
 * Three places delete orders — the admin list, the scheduled abandoned-order cleanup, and the
 * sample-data teardown — and each used to carry its own copy of the child-table list. They drifted:
 * the cleanup omitted the payment ledger and the uploads, and the teardown cleared three tables of
 * the ten. A caller that forgets a table here leaves rows behind that only the database health card
 * can find, so the list lives in one place and every caller reads it.
 */
final class OrderCascadeHelper
{
    /** Order children keyed on the varchar order number. */
    private const VARCHAR_CHILDREN = [
        '#__j2commerce_orderitems',
        '#__j2commerce_orderinfos',
        '#__j2commerce_orderhistories',
        '#__j2commerce_ordershippings',
        '#__j2commerce_orderdiscounts',
        '#__j2commerce_orderfees',
        '#__j2commerce_ordertaxes',
        '#__j2commerce_orderdownloads',
    ];

    /**
     * Payment transaction rows for the given order primary keys. #__j2commerce_ordertransactions
     * keys on j2commerce_order_id, not on the varchar order number every other order child uses.
     *
     * @param  int[]  $orderPks
     */
    public static function countTransactions(DatabaseInterface $db, array $orderPks): int
    {
        $orderPks = self::intKeys($orderPks);

        if ($orderPks === []) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_ordertransactions'))
            ->whereIn($db->quoteName('order_id'), $orderPks);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Which of the given orders hold payment transaction records.
     *
     * @param  int[]  $orderPks
     *
     * @return int[]  The subset that has a ledger, so a caller with nobody to ask can skip them.
     */
    public static function withTransactions(DatabaseInterface $db, array $orderPks): array
    {
        $orderPks = self::intKeys($orderPks);

        if ($orderPks === []) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_ordertransactions'))
            ->whereIn($db->quoteName('order_id'), $orderPks);

        return self::intKeys((array) $db->setQuery($query)->loadColumn());
    }

    /**
     * Remove everything that belongs to the given orders, leaving the order rows themselves to the
     * caller. Uploads go through OrderUploadHelper so the files on disk go with the rows.
     *
     * @param  array<int, string>  $orderIds             Order primary key => varchar order number.
     * @param  bool                $includeTransactions  Whether to remove the payment ledger. Only
     *                                                   a caller that has confirmed the intent with
     *                                                   a human passes true.
     */
    public static function purgeChildren(DatabaseInterface $db, array $orderIds, bool $includeTransactions): void
    {
        foreach ($orderIds as $orderPk => $orderId) {
            $orderPk = (int) $orderPk;
            $orderId = (string) $orderId;

            if ($orderPk <= 0 || $orderId === '') {
                continue;
            }

            self::purgeOne($db, $orderPk, $orderId, $includeTransactions);
        }
    }

    private static function purgeOne(DatabaseInterface $db, int $orderPk, string $orderId, bool $includeTransactions): void
    {
        // The attributes hang off the items, so they go while those ids still resolve.
        $orderitemIds = self::intKeys((array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('j2commerce_orderitem_id'))
                ->from($db->quoteName('#__j2commerce_orderitems'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderId)
        )->loadColumn());

        if ($orderitemIds !== []) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_orderitemattributes'))
                    ->whereIn($db->quoteName('orderitem_id'), $orderitemIds)
            )->execute();
        }

        // Carries files on disk, so never a plain DELETE.
        OrderUploadHelper::purgeForOrder($db, $orderId);

        foreach (self::VARCHAR_CHILDREN as $table) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName('order_id') . ' = :orderId')
                    ->bind(':orderId', $orderId)
            )->execute();
        }

        if (!$includeTransactions) {
            return;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_ordertransactions'))
                ->where($db->quoteName('order_id') . ' = :orderPk')
                ->bind(':orderPk', $orderPk, ParameterType::INTEGER)
        )->execute();
    }

    /** @return int[] */
    private static function intKeys(array $values): array
    {
        return array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));
    }
}
