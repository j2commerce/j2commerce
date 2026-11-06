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

use J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * What an order's status changes do to its coupon and voucher redemptions.
 *
 * A voucher balance is derived from the orderdiscounts rows plus its adjustment ledger. An order that
 * is canceled or fails gets its voucher value back as a credit row naming the order, written at the
 * moment of that transition (see onStatusChange() for when that waits for a person); going back to
 * a counting status writes the matching debit. Orders released before this existed have no such row and are never revisited, so the
 * rule only acts on transitions made from now on. Coupons need no rows: their use counts already
 * read the order's current status.
 *
 * An order that starts counting again is re-checked against the limits under one named lock per
 * voucher and use-limited coupon, so two orders cannot both pass against the same remaining amount.
 */
final class RedemptionHelper
{
    /**
     * Status types that hand a placed order's voucher value back. Refunded is not one: partial
     * refunds share it, so the voucher part of a refund is credited by hand.
     */
    private const RELEASED_TYPES = [OrderStatusHelper::TYPE_CANCELLED, OrderStatusHelper::TYPE_FAILED];

    private const LOCK_WAIT = 10;

    public static function releases(int $statusId): bool
    {
        return \in_array(OrderStatusHelper::getType($statusId), self::RELEASED_TYPES, true);
    }

    /** New orders never drew on the voucher; an unclassified status counts, so a merchant row never hands value back. */
    public static function counts(int $statusId): bool
    {
        return !self::releases($statusId) && OrderStatusHelper::getType($statusId) !== OrderStatusHelper::TYPE_NEW;
    }

    /** The move makes the order's coupons and vouchers count against their limits again. */
    public static function entersCounting(int $oldStatusId, int $newStatusId): bool
    {
        return !self::counts($oldStatusId) && self::counts($newStatusId);
    }

    /**
     * Side effects of a status transition both status writers have already committed. Never
     * throws: a failure is logged and the next transition recomputes from the ledger.
     *
     * Value comes back on its own only for an order that was never paid, or when the merchant made
     * the change. A paid order failed or canceled by a gateway (a reversal, a dispute) is a refund
     * in all but name, so what it is owed is recorded for a person to credit, as with Refunded.
     */
    public static function onStatusChange(string $orderId, int $oldStatusId, int $newStatusId, bool $byMerchant = false): void
    {
        $credit = !self::releases($oldStatusId) && self::releases($newStatusId);
        $hold   = $credit && !$byMerchant
            && !\in_array(OrderStatusHelper::getType($oldStatusId), [OrderStatusHelper::TYPE_NEW, OrderStatusHelper::TYPE_OPEN], true);
        $debit  = self::releases($oldStatusId) && !self::releases($newStatusId);
        $check  = self::entersCounting($oldStatusId, $newStatusId);

        if ($orderId === '' || (!$credit && !$debit && !$check)) {
            return;
        }

        try {
            $order = self::loadOrder($orderId);

            if (!$order) {
                return;
            }

            // History notes are stored as text, so the strings must be loaded whatever app runs this.
            Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);

            if ($check && ($shortfall = self::findShortfall($order)) !== null) {
                self::note($orderId, Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_REDEMPTION_SHORT', $shortfall));
                Log::add('Order ' . $orderId . ' started counting past a redemption limit: ' . $shortfall, Log::WARNING, 'com_j2commerce');
            }

            if (!$credit && !$debit) {
                return;
            }

            $factory  = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
            $vouchers = $factory->createModel('Voucher', 'Administrator', ['ignore_request' => true]);
            $rows     = $factory->createModel('Order', 'Administrator', ['ignore_request' => true])->getOrderDiscounts($orderId, 'voucher');
        } catch (\Throwable $e) {
            Log::add('Redemption update failed for order ' . $orderId . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return;
        }

        // Each card on its own, so one that fails does not leave the others unsettled.
        foreach (array_unique(array_map(static fn ($row): int => (int) $row->discount_entity_id, $rows)) as $id) {
            try {
                self::settleVoucher($vouchers, $id, $order, $debit, $hold);
            } catch (\Throwable $e) {
                Log::add('Redemption update failed for voucher ' . $id . ' on order ' . $orderId . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            }
        }
    }

    private static function settleVoucher(VoucherModel $vouchers, int $id, object $order, bool $debit, bool $hold): void
    {
        $orderId = (string) $order->order_id;
        $label   = '#' . $id;

        if ($debit) {
            $result = $vouchers->reinstateForOrder($id, $orderId, Text::_('COM_J2COMMERCE_VOUCHER_REASON_ORDER_REINSTATED'));

            if ($result['amount'] > 0) {
                self::note($orderId, Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_VOUCHER_DEBITED', $label, self::money($result['amount'], $order), self::money($result['balance'], $order)));
            }

            if ($result['shortfall'] > 0) {
                self::note($orderId, Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_VOUCHER_DEBIT_SHORT', $label, self::money($result['shortfall'], $order)));
                Log::add('Voucher ' . $id . ' could not cover ' . $result['shortfall'] . ' taken back for order ' . $orderId, Log::WARNING, 'com_j2commerce');
            }

            return;
        }

        // A disabled card is closed, and a paid order's value waits for a person: either way what the
        // order is owed is recorded, not paid onto the card.
        if ($hold || !self::voucherEnabled($id)) {
            $owed = round($vouchers->getOrderRedemption($id, $orderId) - $vouchers->getReturnedForOrder($id, $orderId), 5);

            if ($owed > 0) {
                self::note($orderId, Text::sprintf(
                    $hold ? 'COM_J2COMMERCE_ORDER_HISTORY_VOUCHER_CREDIT_HELD' : 'COM_J2COMMERCE_ORDER_HISTORY_VOUCHER_NOT_CREDITED',
                    $label,
                    self::money($owed, $order)
                ));
            }

            return;
        }

        $result = $vouchers->creditForOrder($id, $orderId, null, Text::_('COM_J2COMMERCE_VOUCHER_REASON_ORDER_RELEASED'));

        if ($result['amount'] > 0) {
            self::noteCredit($orderId, $id, $result['amount'], $result['balance']);
        }
    }

    /**
     * The first of the order's coupon or voucher rows its limits no longer cover, as a message, or
     * null when every row is still redeemable. The order's own rows are excluded from the counts,
     * and value already credited back for it is not available to it. Needs order_id, user_id,
     * user_email, currency_code and currency_value on $order.
     */
    public static function findShortfall(object $order): ?string
    {
        $orderId = (string) ($order->order_id ?? '');
        $factory = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
        $rows    = $factory->createModel('Order', 'Administrator', ['ignore_request' => true])->getOrderDiscounts($orderId);

        if (!$rows) {
            return null;
        }

        $voucherModel = $factory->createModel('Voucher', 'Administrator', ['ignore_request' => true]);
        $couponModel  = $factory->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
        $vouchersSeen = [];

        foreach ($rows as $row) {
            $code = (string) $row->discount_code;

            if ($row->discount_type === 'voucher') {
                $id = (int) $row->discount_entity_id;

                if (isset($vouchersSeen[$id])) {
                    continue;
                }

                $vouchersSeen[$id] = true;
                $amount            = $voucherModel->getOrderRedemption($id, $orderId);
                $available         = max(0.0, $voucherModel->getRemainingBalance($id, $orderId) - $voucherModel->getReturnedForOrder($id, $orderId));

                if ($available + 0.005 < $amount) {
                    return Text::sprintf(
                        'COM_J2COMMERCE_ERR_VOUCHER_BALANCE_SHORT',
                        '#' . $id,
                        self::money($available, $order),
                        self::money($amount, $order)
                    );
                }

                continue;
            }

            if ($row->discount_type !== 'coupon') {
                continue;
            }

            $coupon = $couponModel->getCouponByCode($code);

            if (!$coupon) {
                return Text::sprintf('COM_J2COMMERCE_ERR_COUPON_NO_LONGER_VALID', $code);
            }

            $couponId = (int) $coupon->j2commerce_coupon_id;
            $maxUses  = (int) $coupon->max_uses;

            if ($maxUses > 0 && $couponModel->getCouponHistory($couponId, '', $orderId) >= $maxUses) {
                return Text::sprintf('COM_J2COMMERCE_ERR_COUPON_USES_EXHAUSTED', $code);
            }

            $perCustomer = (int) $coupon->max_customer_uses;

            if ($perCustomer < 1) {
                continue;
            }

            $userId = (int) ($order->user_id ?? 0);
            $email  = strtolower(trim((string) ($order->user_email ?? '')));

            if (($userId > 0 && $couponModel->getCouponHistory($couponId, (string) $userId, $orderId) >= $perCustomer)
                || ($email !== '' && $couponModel->getCouponHistoryByEmail($couponId, $email, $orderId) >= $perCustomer)
            ) {
                return Text::sprintf('COM_J2COMMERCE_ERR_COUPON_USES_EXHAUSTED', $code);
            }
        }

        return null;
    }

    /** Lock keys for the order's vouchers and use-limited coupons; an unresolvable coupon is locked too. */
    public static function lockKeys(string $orderId): array
    {
        $factory = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
        $coupons = $factory->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
        $keys    = [];

        foreach ($factory->createModel('Order', 'Administrator', ['ignore_request' => true])->getOrderDiscounts($orderId) as $row) {
            if ($row->discount_type === 'voucher') {
                $keys[] = 'g:' . (int) $row->discount_entity_id;

                continue;
            }

            if ($row->discount_type !== 'coupon') {
                continue;
            }

            $code   = (string) $row->discount_code;
            $coupon = $coupons->getCouponByCode($code);

            if (!$coupon || (int) $coupon->max_uses > 0 || (int) $coupon->max_customer_uses > 0) {
                $keys[] = 'c:' . md5(strtolower(trim($code)));
            }
        }

        return $keys;
    }

    /**
     * Take one server-wide named lock per key, in sorted order. Named rather than a row lock: a
     * caller may hold it across a gateway call, where an open transaction would roll back the
     * payment plugin's own writes on any failure. Returns the names taken, or null when one could
     * not be had (every lock taken so far is released). Non-MySQL servers get no lock.
     *
     * @return string[]|null
     */
    public static function acquireLocks(array $keys, int $wait = self::LOCK_WAIT): ?array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if ($db->getServerType() !== 'mysql') {
            return [];
        }

        $keys = array_values(array_unique(array_filter($keys)));
        sort($keys, SORT_STRING);

        // Lock names are server-wide, so they carry the site's database and prefix.
        $scope = substr(md5(Factory::getApplication()->get('db') . '.' . $db->getPrefix()), 0, 8);
        $taken = [];

        try {
            foreach ($keys as $key) {
                $name  = 'j2c' . $scope . ':' . $key;
                $query = $db->getQuery(true)
                    ->select('GET_LOCK(:name, :wait)')
                    ->bind(':name', $name)
                    ->bind(':wait', $wait, ParameterType::INTEGER);

                if ((int) $db->setQuery($query)->loadResult() !== 1) {
                    self::releaseLocks($taken);

                    return null;
                }

                $taken[] = $name;
            }
        } catch (\Throwable $e) {
            Log::add('Redemption lock failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
            self::releaseLocks($taken);

            return null;
        }

        return $taken;
    }

    /** @param string[] $names */
    public static function releaseLocks(array $names): void
    {
        if (!$names) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($names as $name) {
            try {
                $db->setQuery($db->getQuery(true)->select('RELEASE_LOCK(:name)')->bind(':name', $name))->loadResult();
            } catch (\Throwable $e) {
                Log::add('Redemption lock release failed: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
            }
        }
    }

    public static function loadOrder(string $orderId): ?object
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['order_id', 'order_state_id', 'user_id', 'user_email', 'currency_code', 'currency_value']))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /** Records on the order that value was credited back to one of its vouchers. */
    public static function noteCredit(string $orderId, int $voucherId, float $amount, float $balance): void
    {
        $order = self::loadOrder($orderId) ?? (object) [];

        self::note($orderId, Text::sprintf(
            'COM_J2COMMERCE_ORDER_HISTORY_VOUCHER_CREDITED',
            '#' . $voucherId,
            self::money($amount, $order),
            self::money($balance, $order)
        ));
    }

    /** In the currency the order was placed in. */
    private static function money(float $amount, object $order): string
    {
        return CurrencyHelper::format($amount, (string) ($order->currency_code ?? ''), (float) ($order->currency_value ?? 0));
    }

    private static function voucherEnabled(int $id): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__j2commerce_vouchers'))
            ->where($db->quoteName('j2commerce_voucher_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() === 1;
    }

    private static function note(string $orderId, string $comment): void
    {
        OrderHistoryHelper::add(orderId: $orderId, comment: $comment, params: OrderHistoryHelper::ADMIN_NOTE_PARAMS);
    }
}
