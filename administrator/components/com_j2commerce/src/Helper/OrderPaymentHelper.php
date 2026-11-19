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
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** The single "is this order paid" predicate: the ledger decides where it has rows, else a normalized transaction_status. Amounts are in the order's display currency. */
final class OrderPaymentHelper
{
    public const STATE_UNPAID       = 'unpaid';
    public const STATE_AUTHORIZED   = 'authorized';
    public const STATE_PARTIAL      = 'partially_paid';
    public const STATE_PAID         = 'paid';
    public const STATE_REFUNDED     = 'refunded';

    private const LEDGER_TABLE = '#__j2commerce_ordertransactions';

    private const PAID_WORDS = ['completed', 'complete', 'captured', 'paid', 'success', 'succeeded', 'ok'];

    /** Orders-table columns the predicate reads. */
    private const ORDER_COLUMNS = [
        'j2commerce_order_id', 'order_total', 'order_params', 'transaction_status',
        'order_refund', 'currency_code', 'currency_value',
    ];

    public static function state(object|int $order): string
    {
        return self::summary($order)['state'];
    }

    public static function isPaid(object|int $order): bool
    {
        return self::state($order) === self::STATE_PAID;
    }

    /**
     * Evaluation behind state(), with the figures a screen shows; $order may hold unsaved values, the ledger is read by primary key.
     *
     * @return array{state: string, evidence: string, total: float, due_now: float, captured: float, refunded: float, net: float, balance: float}
     */
    public static function summary(object|int $order): array
    {
        if (\is_int($order)) {
            $order = self::loadOrders([$order])[$order] ?? null;

            if ($order === null) {
                return self::result(self::STATE_UNPAID, 'none', 0.0, 0.0, 0.0, 0.0, 2);
            }
        }

        $pk = (int) ($order->j2commerce_order_id ?? 0);

        // An object missing a column the predicate reads would evaluate as a zero-total (paid) order.
        if ($pk > 0 && self::lacksColumns($order)) {
            $order = self::loadOrders([$pk])[$pk] ?? $order;
        }

        $ledger   = $pk > 0 ? (self::ledgerFacts([$pk])[$pk] ?? null) : null;
        $decimals = CurrencyHelper::getDecimalPlace((string) ($order->currency_code ?? ''));

        return self::evaluate($order, $ledger, $decimals);
    }

    /**
     * One grouped ledger query for a page of orders, keyed by j2commerce_order_id.
     *
     * @param   array<int, int|object>  $orderPks  Primary keys or order objects; an object missing a predicate column is reloaded by key.
     */
    public static function stateForMany(array $orderPks): array
    {
        return array_map(static fn (array $summary): string => $summary['state'], self::summaryForMany($orderPks));
    }

    /**
     * stateForMany() with the figures summary() returns.
     *
     * @param   array<int, int|object>  $orderPks  Primary keys or order objects.
     */
    public static function summaryForMany(array $orderPks): array
    {
        $orders = [];
        $reload = [];

        foreach ($orderPks as $entry) {
            if (\is_object($entry) && self::hasColumns($entry)) {
                $orders[(int) $entry->j2commerce_order_id] = $entry;

                continue;
            }

            $pk = \is_object($entry) ? (int) ($entry->j2commerce_order_id ?? 0) : (int) $entry;

            if ($pk > 0) {
                $reload[] = $pk;
            }
        }

        if ($reload !== []) {
            $orders += self::loadOrders(array_values(array_unique($reload)));
        }

        if ($orders === []) {
            return [];
        }

        $facts     = self::ledgerFacts(array_keys($orders));
        $summaries = [];

        foreach ($orders as $pk => $order) {
            $decimals       = CurrencyHelper::getDecimalPlace((string) ($order->currency_code ?? ''));
            $summaries[$pk] = self::evaluate($order, $facts[$pk] ?? null, $decimals);
        }

        return $summaries;
    }

    /** Maps a gateway's transaction_status onto completed, authorized, pending, refunded, voided, failed or ''; an unknown word is '' so it can never confirm an unpaid order. Partially refunded is 'completed': order_refund carries the refunded part. */
    public static function normalizeTransactionStatus(?string $value): string
    {
        $word = strtolower(trim((string) $value));

        return match (true) {
            \in_array($word, self::PAID_WORDS, true)                             => 'completed',
            \in_array($word, ['partially refunded', 'partially_refunded'], true) => 'completed',
            \in_array($word, ['authorized', 'authorised'], true)                 => 'authorized',
            $word === 'refunded'                                                 => 'refunded',
            \in_array($word, ['pending', 'inprogress', 'in progress'], true)     => 'pending',
            \in_array($word, ['voided', 'void'], true)                           => 'voided',
            \in_array($word, ['failed', 'failure', 'declined'], true)            => 'failed',
            default                                                              => '',
        };
    }

    /**
     * Pure core, no database access, so it is unit-testable. $ledger is null when the order has no ledger rows.
     *
     * @param   array{captured: float, refunded: float, auth: bool}|null  $ledger
     */
    public static function evaluate(object $order, ?array $ledger, int $decimals): array
    {
        $rate = (float) ($order->currency_value ?? 1.0);
        $rate = $rate > 0 ? $rate : 1.0;
        // Half the currency's smallest unit: amounts closer than this are equal after rounding.
        $half = 0.5 * 10 ** -$decimals;

        $total = round((float) ($order->order_total ?? 0) * $rate, $decimals);
        $due   = round(CurrencyHelper::convertForOrder(CurrencyHelper::baseChargeAmount($order), $order), $decimals);
        $word  = self::normalizeTransactionStatus((string) ($order->transaction_status ?? ''));

        if ($total <= $half) {
            return self::result(self::STATE_PAID, 'zero_total', $total, $due, 0.0, 0.0, $decimals);
        }

        if ($ledger !== null) {
            $captured = $ledger['captured'];
            $refunded = $ledger['refunded'];
            $evidence = 'ledger';
        } else {
            [$captured, $refunded] = self::legacyFigures($order, $due, $rate, $decimals);
            $evidence              = \in_array($word, ['completed', 'refunded', 'authorized'], true) ? 'transaction_status' : 'none';
        }

        $net  = $captured - $refunded;
        $auth = ($ledger['auth'] ?? false) || $word === 'authorized';

        // An explicit zero amount_due_now owes nothing at checkout, so the order is settled unless money went back.
        if ($due <= $half && $refunded <= $half) {
            return self::result(self::STATE_PAID, 'nothing_due_now', $total, $due, $captured, $refunded, $decimals);
        }

        // Paid means net covers what is due now (a deposit order is paid at its deposit), not the full total; the first arm wins, so a fully refunded order is never "paid".
        $state = match (true) {
            $refunded > $half && $net <= $half   => self::STATE_REFUNDED,
            $due > $half && $net >= $due - $half => self::STATE_PAID,
            $net > $half                         => self::STATE_PARTIAL,
            $auth                                => self::STATE_AUTHORIZED,
            default                              => self::STATE_UNPAID,
        };

        return self::result($state, $evidence, $total, $due, $captured, $refunded, $decimals);
    }

    /**
     * [captured, refunded] in display currency for an order with no ledger rows; shared with OrderTransactionHelper so the balance card and the predicate agree.
     *
     * @return  array{0: float, 1: float}
     */
    public static function legacyFigures(object $order, ?float $due = null, ?float $rate = null, ?int $decimals = null): array
    {
        $decimals ??= CurrencyHelper::getDecimalPlace((string) ($order->currency_code ?? ''));
        $rate     ??= ((float) ($order->currency_value ?? 1.0)) > 0 ? (float) $order->currency_value : 1.0;
        $due      ??= round(CurrencyHelper::convertForOrder(CurrencyHelper::baseChargeAmount($order), $order), $decimals);
        $word       = self::normalizeTransactionStatus((string) ($order->transaction_status ?? ''));
        $half       = 0.5 * 10 ** -$decimals;

        $captured = \in_array($word, ['completed', 'refunded'], true) ? $due : 0.0;
        $refunded = round((float) ($order->order_refund ?? 0) * $rate, $decimals);

        // A gateway that says Refunded without recording order_refund means the whole capture was returned.
        if ($word === 'refunded' && $refunded <= $half) {
            $refunded = $captured;
        }

        return [$captured, $refunded];
    }

    /**
     * One-call success path for a payment plugin: ledger DEBIT (idempotent on `$gatewayTxnId`), transaction fields, then the move to the paid status.
     * The rule still applies as a gateway write; it allows the move because the evidence is written first.
     * `$amount` is in the order's display currency, as the gateway charged it; an empty `$comment` uses the default status-change text.
     * True only when the order ends on the paid status, including one already there; false when it was parked, left in place or the move failed. Never throws.
     */
    public static function markOrderPaid(
        int $orderPk,
        string $pluginElement,
        string $gatewayTxnId,
        float $amount,
        string $currencyCode,
        ?int $paidStatusId = null,
        bool $notify = true,
        string $transactionStatus = 'Completed',
        ?string $transactionDetails = null,
        ?string $comment = null
    ): bool {
        $statusId = 0;

        try {
            if ($gatewayTxnId !== '' && $amount > 0.0) {
                // No ledger-triggered promotion: the move below carries the plugin's comment and notify flag, once.
                OrderTransactionHelper::addCharge($orderPk, $pluginElement, $gatewayTxnId, $amount, $currencyCode, promote: false);
            }

            self::writeTransactionFields($orderPk, $pluginElement, $gatewayTxnId, $transactionStatus, $transactionDetails);

            $statusId = (int) ($paidStatusId ?: OrderStatusHelper::idOfType(OrderStatusHelper::TYPE_COMPLETE, 'J2COMMERCE_CONFIRMED'));

            if ($statusId > 0) {
                $model = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()
                    ->createModel('Order', 'Administrator', ['ignore_request' => true]);

                $model->updateOrderStatus($orderPk, $statusId, $notify, (string) $comment, false, false);
            }
        } catch (\Throwable $e) {
            Log::add('markOrderPaid failed for order ' . $orderPk . ': ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        // The stored status answers, not updateOrderStatus(): it returns true for a parked order, and a status-event subscriber can throw after the move.
        return $statusId > 0 && self::currentStatusId($orderPk) === $statusId;
    }

    public static function currentStatusId(int $orderPk): int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_state_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    private static function writeTransactionFields(int $orderPk, string $pluginElement, string $gatewayTxnId, string $status, ?string $details): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $now   = Factory::getDate()->toSql();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('orderpayment_type') . ' = :plugin')
            ->set($db->quoteName('transaction_status') . ' = :status')
            ->set($db->quoteName('modified_on') . ' = :now')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':plugin', $pluginElement)
            ->bind(':status', $status)
            ->bind(':now', $now)
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        if ($gatewayTxnId !== '') {
            $query->set($db->quoteName('transaction_id') . ' = :txn')->bind(':txn', $gatewayTxnId);
        }

        if ($details !== null) {
            $query->set($db->quoteName('transaction_details') . ' = :details')->bind(':details', $details);
        }

        $db->setQuery($query)->execute();
    }

    private static function hasColumns(object $order): bool
    {
        return (int) ($order->j2commerce_order_id ?? 0) > 0 && !self::lacksColumns($order);
    }

    private static function lacksColumns(object $order): bool
    {
        foreach (self::ORDER_COLUMNS as $column) {
            if (!property_exists($order, $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param   int[]  $pks
     *
     * @return  array<int, object>
     */
    private static function loadOrders(array $pks): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(self::ORDER_COLUMNS))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->whereIn($db->quoteName('j2commerce_order_id'), $pks, ParameterType::INTEGER);

        $orders = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $orders[(int) $row->j2commerce_order_id] = $row;
        }

        return $orders;
    }

    /**
     * One grouped query for any number of orders. A row of any state marks an order as having a ledger; only succeeded, non-voided DEBIT and CAPTURE rows count as captured, as in OrderTransactionHelper::getBalanceSummary(). Manual rows (NULL gateway_txn_id) are never voided, so they always count. An AUTH row is open when no succeeded VOID or CAPTURE names it as parent.
     *
     * @param   int[]  $pks
     *
     * @return  array<int, array{captured: float, refunded: float, auth: bool}>
     */
    private static function ledgerFacts(array $pks): array
    {
        $db           = Factory::getContainer()->get(DatabaseInterface::class);
        $succeeded    = 'succeeded';
        $typeDebit    = 'DEBIT';
        $typeCapture  = 'CAPTURE';
        $typeAuth     = 'AUTH';
        $typeVoid     = 'VOID';
        $t            = $db->quoteName('t.type');
        $gateway      = $db->quoteName('t.gateway_txn_id');
        $table        = $db->quoteName(self::LEDGER_TABLE);

        // The voided subquery is used twice (captured sum and open-auth test); each use needs its own placeholder names (%d).
        $voided = 'EXISTS (SELECT 1 FROM ' . $table . ' AS ' . $db->quoteName('v')
            . ' WHERE ' . $db->quoteName('v.order_id') . ' = ' . $db->quoteName('t.order_id')
            . ' AND ' . $db->quoteName('v.type') . ' = :voidType%d'
            . ' AND ' . $db->quoteName('v.state') . ' = :voidState%d'
            . ' AND ' . $db->quoteName('v.parent_txn_id') . ' = ' . $gateway . ')';

        $captureExists = 'EXISTS (SELECT 1 FROM ' . $table . ' AS ' . $db->quoteName('c')
            . ' WHERE ' . $db->quoteName('c.order_id') . ' = ' . $db->quoteName('t.order_id')
            . ' AND ' . $db->quoteName('c.type') . ' = :capType'
            . ' AND ' . $db->quoteName('c.state') . ' = :capState'
            . ' AND ' . $db->quoteName('c.parent_txn_id') . ' = ' . $gateway . ')';

        $countedSql = 'SUM(CASE WHEN ' . $db->quoteName('t.state') . ' = :countState AND ('
            . $t . ' NOT IN (:countDebit, :countCapture) OR ' . $gateway . ' IS NULL OR NOT '
            . \sprintf($voided, 1, 1) . ') THEN ' . $db->quoteName('t.amount') . ' ELSE 0 END)';

        $openAuthSql = 'SUM(CASE WHEN ' . $t . ' = :authType AND ' . $db->quoteName('t.state') . ' = :authState'
            . ' AND NOT ' . \sprintf($voided, 2, 2) . ' AND NOT ' . $captureExists . ' THEN 1 ELSE 0 END)';

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('t.order_id'),
                $t,
                'COUNT(*) AS ' . $db->quoteName('n'),
                $countedSql . ' AS ' . $db->quoteName('total'),
                $openAuthSql . ' AS ' . $db->quoteName('open_auth'),
            ])
            ->from($table . ' AS ' . $db->quoteName('t'))
            ->whereIn($db->quoteName('t.order_id'), $pks, ParameterType::INTEGER)
            ->group([$db->quoteName('t.order_id'), $t])
            ->bind(':countState', $succeeded)
            ->bind(':countDebit', $typeDebit)
            ->bind(':countCapture', $typeCapture)
            ->bind(':voidType1', $typeVoid)
            ->bind(':voidState1', $succeeded)
            ->bind(':voidType2', $typeVoid)
            ->bind(':voidState2', $succeeded)
            ->bind(':authType', $typeAuth)
            ->bind(':authState', $succeeded)
            ->bind(':capType', $typeCapture)
            ->bind(':capState', $succeeded);

        $facts = [];

        foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row) {
            $pk = (int) $row->order_id;

            $facts[$pk] ??= ['captured' => 0.0, 'refunded' => 0.0, 'auth' => false];

            match ((string) $row->type) {
                'DEBIT', 'CAPTURE' => $facts[$pk]['captured'] += (float) $row->total,
                'REVERSAL'         => $facts[$pk]['refunded'] += (float) $row->total,
                default            => null,
            };

            if ((int) $row->open_auth > 0) {
                $facts[$pk]['auth'] = true;
            }
        }

        return $facts;
    }

    private static function result(string $state, string $evidence, float $total, float $due, float $captured, float $refunded, int $decimals): array
    {
        $net = $captured - $refunded;

        return [
            'state'    => $state,
            'evidence' => $evidence,
            'total'    => $total,
            'due_now'  => $due,
            'captured' => $captured,
            'refunded' => $refunded,
            'net'      => $net,
            'balance'  => max(0.0, round($total - max(0.0, $net), $decimals)),
        ];
    }
}
