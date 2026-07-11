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

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * Shared order-transaction ledger accessor. See docs/plans/order_transactions_ledger_prd.md.
 *
 * All amounts are in the ORDER (display) currency — matching `orders.currency_code`,
 * NOT the store base currency that `orders.order_total` is stored in (PRD design
 * decision D1). `addReversal()` keeps `orders.order_refund` (base currency) synced.
 *
 * Manual/offline captures (`gateway_txn_id` NULL) count toward `getCaptured()` /
 * `getNetPaid()` (shown as paid) but are excluded from `getRefundable()` /
 * `allocateRefund()` — there's no gateway id to attribute a machine refund
 * against, so they require a manual reversal instead (PRD §10, H1).
 *
 * @since  6.4.0
 */
final class OrderTransactionHelper
{
    private const TABLE = '#__j2commerce_ordertransactions';

    private const EPSILON = 0.00001;

    private static ?DatabaseInterface $db = null;

    /**
     * Per-request memo for hasLedger(); refreshed by writeRow() after an insert.
     *
     * @var array<int, bool>
     */
    private static array $ledgerMemo = [];

    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    // =========================================================================
    // WRITE METHODS
    // =========================================================================

    public static function addCharge(
        int $orderId,
        string $pluginEl,
        string $gatewayTxnId,
        float $amount,
        string $currencyCode,
        int $createdBy = 0
    ): int {
        return self::writeRow($orderId, $pluginEl, 'DEBIT', $gatewayTxnId, '', $amount, $currencyCode, $createdBy);
    }

    /** Also re-syncs `orders.order_refund` (base currency) from the ledger. */
    public static function addReversal(
        int $orderId,
        string $pluginEl,
        string $refundTxnId,
        string $parentTxnId,
        float $amount,
        int $createdBy = 0
    ): int {
        self::assertReversalParent($orderId, $parentTxnId);

        $currencyCode = self::orderCurrencyCode($orderId);
        $id           = self::writeRow($orderId, $pluginEl, 'REVERSAL', $refundTxnId, $parentTxnId, $amount, $currencyCode, $createdBy);

        self::syncOrderRefund($orderId, $createdBy);

        return $id;
    }

    public static function addAuth(
        int $orderId,
        string $pluginEl,
        string $gatewayTxnId,
        float $amount,
        string $currencyCode,
        int $createdBy = 0
    ): int {
        return self::writeRow($orderId, $pluginEl, 'AUTH', $gatewayTxnId, '', $amount, $currencyCode, $createdBy);
    }

    public static function addCapture(
        int $orderId,
        string $pluginEl,
        string $gatewayTxnId,
        string $parentAuthTxnId,
        float $amount,
        int $createdBy = 0
    ): int {
        $currencyCode = self::orderCurrencyCode($orderId);

        return self::writeRow($orderId, $pluginEl, 'CAPTURE', $gatewayTxnId, $parentAuthTxnId, $amount, $currencyCode, $createdBy);
    }

    /**
     * `$gatewayTxnId` defaults to `$parentTxnId` when the gateway returns no distinct
     * void id, so replayed void webhooks dedupe via the same (order_id, gateway_txn_id,
     * type) contract every other row uses instead of always inserting fresh (H4).
     */
    public static function addVoid(
        int $orderId,
        string $pluginEl,
        string $parentTxnId,
        string $gatewayTxnId = '',
        int $createdBy = 0
    ): int {
        $currencyCode = self::orderCurrencyCode($orderId);
        $gatewayTxnId = $gatewayTxnId !== '' ? $gatewayTxnId : $parentTxnId;

        return self::writeRow($orderId, $pluginEl, 'VOID', $gatewayTxnId, $parentTxnId, 0.0, $currencyCode, $createdBy);
    }

    // =========================================================================
    // READ METHODS
    // =========================================================================

    public static function getCaptured(int $orderId): float
    {
        return self::getBalanceSummary($orderId)['captured'];
    }

    public static function getRefunded(int $orderId): float
    {
        return self::getBalanceSummary($orderId)['refunded'];
    }

    public static function getNetPaid(int $orderId): float
    {
        return self::getBalanceSummary($orderId)['net_paid'];
    }

    /**
     * Machine-refundable ceiling — MUST equal the total `allocateRefund()` will
     * authorize (both derive from `capturesWithRemainders()`). This is narrower
     * than `getNetPaid()` whenever the order has a manual (NULL gateway_txn_id)
     * capture: that capture is paid (counted in `getNetPaid()`) but not
     * machine-refundable, so it contributes 0 here (H1).
     */
    public static function getRefundable(int $orderId): float
    {
        if (!self::hasLedger($orderId)) {
            return max(0.0, self::getNetPaid($orderId));
        }

        return array_sum(array_column(self::capturesWithRemainders($orderId), 'remainder'));
    }

    /**
     * One `hasLedger()` + one grouped-by-type SUM, deriving captured/refunded/net_paid
     * in PHP. Voided captures are excluded in the same query via a correlated
     * NOT EXISTS on succeeded VOID rows (H5), so this stays a single round trip (H7).
     *
     * @return array{captured: float, refunded: float, net_paid: float}
     */
    public static function getBalanceSummary(int $orderId): array
    {
        if (!self::hasLedger($orderId)) {
            $captured = self::legacyCaptured($orderId);
            $refunded = self::legacyRefunded($orderId);

            return ['captured' => $captured, 'refunded' => $refunded, 'net_paid' => $captured - $refunded];
        }

        $db           = self::getDatabase();
        $state        = 'succeeded';
        $typeDebit    = 'DEBIT';
        $typeCapture  = 'CAPTURE';
        $typeReversal = 'REVERSAL';
        $typeVoid     = 'VOID';

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('t.type'),
                'SUM(' . $db->quoteName('t.amount') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName(self::TABLE, 't'))
            ->where($db->quoteName('t.order_id') . ' = :orderId')
            ->where($db->quoteName('t.state') . ' = :state')
            ->where(
                '(' . $db->quoteName('t.type') . ' NOT IN (:typeDebit2, :typeCapture2)'
                . ' OR ' . $db->quoteName('t.gateway_txn_id') . ' IS NULL'
                . ' OR ' . self::notVoidedCorrelatedClause('t') . ')'
            )
            ->group($db->quoteName('t.type'))
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':state', $state)
            ->bind(':typeDebit2', $typeDebit)
            ->bind(':typeCapture2', $typeCapture)
            ->bind(':voidOrderId', $orderId, ParameterType::INTEGER)
            ->bind(':voidType', $typeVoid)
            ->bind(':voidState', $state);

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $captured = 0.0;
        $refunded = 0.0;

        foreach ($rows as $row) {
            $total = (float) $row->total;

            if ($row->type === $typeDebit || $row->type === $typeCapture) {
                $captured += $total;
            } elseif ($row->type === $typeReversal) {
                $refunded += $total;
            }
        }

        return ['captured' => $captured, 'refunded' => $refunded, 'net_paid' => $captured - $refunded];
    }

    /**
     * Newest-first ledger rows. Empty for legacy orders with no ledger rows.
     *
     * @return array<int, object>
     */
    public static function getCharges(int $orderId): array
    {
        if (!self::hasLedger($orderId)) {
            return [];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_ordertransaction_id', 'order_id', 'plugin', 'type',
                'gateway_txn_id', 'parent_txn_id', 'amount', 'currency_code',
                'state', 'created_at', 'created_by',
            ]))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('created_at') . ' DESC')
            ->order($db->quoteName('j2commerce_ordertransaction_id') . ' DESC')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * LIFO refund allocation across prior succeeded captures (newest first), with
     * per-capture remainders. `$targetTxnId` forces the full amount against one
     * specific capture (validated against its own remainder). See PRD §10 D3.
     *
     * @return array<int, array{gateway_txn_id: string, amount: float}>
     *
     * @throws \RuntimeException  When the amount exceeds the refundable remainder.
     */
    public static function allocateRefund(int $orderId, float $amount, ?string $targetTxnId = null): array
    {
        if ($amount <= 0.0) {
            return [];
        }

        $captures = self::capturesWithRemainders($orderId);

        if ($targetTxnId !== null) {
            foreach ($captures as $capture) {
                if ($capture['gateway_txn_id'] !== $targetTxnId) {
                    continue;
                }

                if ($amount > $capture['remainder'] + self::EPSILON) {
                    throw new \RuntimeException(\sprintf(
                        'Refund amount %.5f exceeds the remaining refundable %.5f on transaction %s.',
                        $amount,
                        $capture['remainder'],
                        $targetTxnId
                    ));
                }

                return [['gateway_txn_id' => $targetTxnId, 'amount' => $amount]];
            }

            throw new \RuntimeException(\sprintf(
                'Transaction %s was not found or is not refundable for order %d.',
                $targetTxnId,
                $orderId
            ));
        }

        $totalRemainder = array_sum(array_column($captures, 'remainder'));

        if ($amount > $totalRemainder + self::EPSILON) {
            throw new \RuntimeException(\sprintf(
                'Refund amount %.5f exceeds the refundable total %.5f for order %d.',
                $amount,
                $totalRemainder,
                $orderId
            ));
        }

        $legs      = [];
        $remaining = $amount;

        foreach ($captures as $capture) {
            if ($remaining <= self::EPSILON) {
                break;
            }

            if ($capture['remainder'] <= self::EPSILON) {
                continue;
            }

            $take     = min($remaining, $capture['remainder']);
            $legs[]   = ['gateway_txn_id' => $capture['gateway_txn_id'], 'amount' => $take];
            $remaining -= $take;
        }

        return $legs;
    }

    /** False for legacy orders (no ledger rows) — callers fall back to order_total/order_refund. */
    public static function hasLedger(int $orderId): bool
    {
        if (isset(self::$ledgerMemo[$orderId])) {
            return self::$ledgerMemo[$orderId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);

        $result = ((int) $db->loadResult()) > 0;

        self::$ledgerMemo[$orderId] = $result;

        return $result;
    }

    // =========================================================================
    // INTERNAL — write path (D2 idempotency contract)
    // =========================================================================

    /**
     * These methods are only ever called after a gateway confirms an operation, so
     * every row is written with state='succeeded'. Duplicate calls (webhook replay)
     * select-first on (order_id, gateway_txn_id, type) and return the existing row's
     * id; the uq_order_txn_type unique index backstops the race window on a 1062
     * duplicate-key error. An empty `$gatewayTxnId` means a manual/offline entry
     * (stored as NULL) and always inserts fresh — MySQL unique indexes permit
     * multiple NULLs, and manual entries are admin-initiated, not webhook-replayed.
     */
    private static function writeRow(
        int $orderId,
        string $pluginEl,
        string $type,
        string $gatewayTxnId,
        string $parentTxnId,
        float $amount,
        string $currencyCode,
        int $createdBy
    ): int {
        $gatewayTxnId = $gatewayTxnId !== '' ? $gatewayTxnId : null;

        if ($gatewayTxnId !== null) {
            $existingId = self::findExisting($orderId, $gatewayTxnId, $type);

            if ($existingId !== null) {
                return $existingId;
            }
        }

        // VOID rows always carry amount 0.0 and may have no currencyCode to round
        // against (order lookup failure) — skip rounding rather than round against
        // the wrong currency's decimal places.
        if ($currencyCode !== '') {
            $amount = round($amount, CurrencyHelper::getDecimalPlace($currencyCode));
        }

        $db    = self::getDatabase();
        $now   = Factory::getDate()->toSql();
        $state = 'succeeded';

        $query = $db->getQuery(true)
            ->insert($db->quoteName(self::TABLE))
            ->columns($db->quoteName([
                'order_id', 'plugin', 'type', 'gateway_txn_id', 'parent_txn_id',
                'amount', 'currency_code', 'state', 'created_at', 'created_by',
            ]))
            ->values(
                ':orderId, :plugin, :type, :gatewayTxnId, :parentTxnId, :amount, :currencyCode, :state, :createdAt, :createdBy'
            )
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':plugin', $pluginEl)
            ->bind(':type', $type)
            ->bind(':gatewayTxnId', $gatewayTxnId, $gatewayTxnId === null ? ParameterType::NULL : ParameterType::STRING)
            ->bind(':parentTxnId', $parentTxnId)
            ->bind(':amount', $amount, ParameterType::STRING)
            ->bind(':currencyCode', $currencyCode)
            ->bind(':state', $state)
            ->bind(':createdAt', $now)
            ->bind(':createdBy', $createdBy, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            $db->execute();
        } catch (\Throwable $e) {
            if ($gatewayTxnId !== null && self::isDuplicateKeyError($e)) {
                $existingId = self::findExisting($orderId, $gatewayTxnId, $type);

                if ($existingId !== null) {
                    return $existingId;
                }
            }

            throw $e;
        }

        // A row now exists for this order — flip the memo live instead of waiting
        // for the next explicit hasLedger() call to re-query (H7).
        self::$ledgerMemo[$orderId] = true;

        return (int) $db->insertid();
    }

    private static function findExisting(int $orderId, string $gatewayTxnId, string $type): ?int
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_ordertransaction_id', 'state']))
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('gateway_txn_id') . ' = :gatewayTxnId')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':gatewayTxnId', $gatewayTxnId)
            ->bind(':type', $type);

        $db->setQuery($query);
        $row = $db->loadObject();

        if (!$row) {
            return null;
        }

        $id = (int) $row->j2commerce_ordertransaction_id;

        if ($row->state !== 'succeeded') {
            self::upgradeState($id, 'succeeded');
        }

        return $id;
    }

    private static function upgradeState(int $id, string $state): void
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName(self::TABLE))
            ->set($db->quoteName('state') . ' = :state')
            ->where($db->quoteName('j2commerce_ordertransaction_id') . ' = :id')
            ->bind(':state', $state)
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }

    private static function isDuplicateKeyError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, '1062') || str_contains($message, 'duplicate entry');
    }

    /**
     * A reversal's parent must be a succeeded DEBIT/CAPTURE on this order — an
     * empty/wrong parent silently reduces order-level totals without reducing any
     * capture's remainder (the exact defect fixed in the seeder's C1 attribution
     * chain). An empty parent is allowed ONLY when the order has at least one
     * manual (NULL gateway_txn_id) capture — an explicitly-manual reversal of a
     * capture with no gateway id to attribute to (H3).
     *
     * @throws \InvalidArgumentException
     */
    private static function assertReversalParent(int $orderId, string $parentTxnId): void
    {
        if ($parentTxnId !== '' && self::captureExists($orderId, $parentTxnId)) {
            return;
        }

        if ($parentTxnId === '' && self::hasManualCapture($orderId)) {
            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Reversal parent_txn_id "%s" does not match a succeeded capture on order %d.',
            $parentTxnId,
            $orderId
        ));
    }

    private static function captureExists(int $orderId, string $gatewayTxnId): bool
    {
        $db          = self::getDatabase();
        $state       = 'succeeded';
        $typeDebit   = 'DEBIT';
        $typeCapture = 'CAPTURE';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('type') . ' IN (:typeDebit, :typeCapture)')
            ->where($db->quoteName('state') . ' = :state')
            ->where($db->quoteName('gateway_txn_id') . ' = :gatewayTxnId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':typeDebit', $typeDebit)
            ->bind(':typeCapture', $typeCapture)
            ->bind(':state', $state)
            ->bind(':gatewayTxnId', $gatewayTxnId);

        $db->setQuery($query);

        return ((int) $db->loadResult()) > 0;
    }

    private static function hasManualCapture(int $orderId): bool
    {
        $db          = self::getDatabase();
        $state       = 'succeeded';
        $typeDebit   = 'DEBIT';
        $typeCapture = 'CAPTURE';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('type') . ' IN (:typeDebit, :typeCapture)')
            ->where($db->quoteName('state') . ' = :state')
            ->where($db->quoteName('gateway_txn_id') . ' IS NULL')
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':typeDebit', $typeDebit)
            ->bind(':typeCapture', $typeCapture)
            ->bind(':state', $state);

        $db->setQuery($query);

        return ((int) $db->loadResult()) > 0;
    }

    // =========================================================================
    // INTERNAL — reads
    // =========================================================================

    /** @param array<int, string> $types */
    private static function sumByTypes(int $orderId, array $types): float
    {
        $db    = self::getDatabase();
        $state = 'succeeded';
        $types = array_values($types);
        $query = $db->getQuery(true)
            ->select('SUM(' . $db->quoteName('amount') . ')')
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('state') . ' = :state')
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':state', $state);

        $placeholders = [];

        // bind() holds references — bind the array slots, not the loop variable,
        // or every placeholder ends up aliased to the final value.
        foreach ($types as $i => $type) {
            $placeholder    = ':type' . $i;
            $placeholders[] = $placeholder;
            $query->bind($placeholder, $types[$i]);
        }

        $query->where($db->quoteName('type') . ' IN (' . implode(',', $placeholders) . ')');

        $db->setQuery($query);

        return (float) ($db->loadResult() ?? 0.0);
    }

    /**
     * A correlated NOT EXISTS fragment excluding rows whose gateway_txn_id has a
     * succeeded VOID referencing it as parent_txn_id. Binds :voidOrderId/:voidType/
     * :voidState on the CALLER's query object — the caller must bind those three
     * (order id, 'VOID', 'succeeded') even though the placeholders appear inside
     * this subquery text (H5).
     */
    private static function notVoidedCorrelatedClause(string $tableAlias): string
    {
        $db = self::getDatabase();

        return 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName(self::TABLE) . ' AS ' . $db->quoteName('v')
            . ' WHERE ' . $db->quoteName('v.order_id') . ' = :voidOrderId'
            . ' AND ' . $db->quoteName('v.type') . ' = :voidType'
            . ' AND ' . $db->quoteName('v.state') . ' = :voidState'
            . ' AND ' . $db->quoteName('v.parent_txn_id') . ' = ' . $db->quoteName($tableAlias . '.gateway_txn_id') . ')';
    }

    /**
     * Succeeded DEBIT/CAPTURE rows with a gateway id, newest-first (LIFO order),
     * each paired with its remainder after prior succeeded REVERSALs. Captures with
     * a succeeded VOID referencing them are excluded entirely (H5) — a voided
     * capture never took money, so it can't be refunded.
     *
     * @return array<int, array{gateway_txn_id: string, amount: float, remainder: float}>
     */
    private static function capturesWithRemainders(int $orderId): array
    {
        $db           = self::getDatabase();
        $state        = 'succeeded';
        $typeDebit    = 'DEBIT';
        $typeCapture  = 'CAPTURE';
        $typeReversal = 'REVERSAL';
        $typeVoid     = 'VOID';

        $capturesQuery = $db->getQuery(true)
            ->select($db->quoteName(['t.gateway_txn_id', 't.amount', 't.created_at', 't.j2commerce_ordertransaction_id']))
            ->from($db->quoteName(self::TABLE, 't'))
            ->where($db->quoteName('t.order_id') . ' = :orderId')
            ->where($db->quoteName('t.type') . ' IN (:typeDebit, :typeCapture)')
            ->where($db->quoteName('t.state') . ' = :state')
            ->where($db->quoteName('t.gateway_txn_id') . ' IS NOT NULL')
            ->where(self::notVoidedCorrelatedClause('t'))
            ->order($db->quoteName('t.created_at') . ' DESC')
            ->order($db->quoteName('t.j2commerce_ordertransaction_id') . ' DESC')
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':typeDebit', $typeDebit)
            ->bind(':typeCapture', $typeCapture)
            ->bind(':state', $state)
            ->bind(':voidOrderId', $orderId, ParameterType::INTEGER)
            ->bind(':voidType', $typeVoid)
            ->bind(':voidState', $state);

        $db->setQuery($capturesQuery);
        $captureRows = $db->loadObjectList() ?: [];

        $reversalsQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('parent_txn_id'),
                'SUM(' . $db->quoteName('amount') . ') AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName(self::TABLE))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('state') . ' = :state')
            ->group($db->quoteName('parent_txn_id'))
            ->bind(':orderId', $orderId, ParameterType::INTEGER)
            ->bind(':type', $typeReversal)
            ->bind(':state', $state);

        $db->setQuery($reversalsQuery);
        $reversalRows = $db->loadObjectList() ?: [];

        $reversedByParent = [];

        foreach ($reversalRows as $row) {
            $reversedByParent[(string) $row->parent_txn_id] = (float) $row->total;
        }

        $captures = [];

        foreach ($captureRows as $row) {
            $gatewayTxnId = (string) $row->gateway_txn_id;
            $reversed     = $reversedByParent[$gatewayTxnId] ?? 0.0;

            $captures[] = [
                'gateway_txn_id' => $gatewayTxnId,
                'amount'         => (float) $row->amount,
                'remainder'      => max(0.0, (float) $row->amount - $reversed),
            ];
        }

        return $captures;
    }

    private static function orderCurrencyCode(int $orderId): string
    {
        $order = self::fetchOrderRow($orderId);

        return $order !== null ? (string) $order->currency_code : '';
    }

    private static function fetchOrderRow(int $orderId): ?object
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_order_id', 'transaction_status', 'order_total',
                'order_refund', 'currency_code', 'currency_value',
            ]))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * Re-syncs `orders.order_refund` (base currency) from the ledger's succeeded
     * REVERSAL total (display currency), dividing by the order's `currency_value`
     * and rounding to the STORE BASE currency's decimal places — `order_refund` is
     * a base-currency column, so its precision must follow the base currency, not
     * the order's display currency (H2, PRD §6 / §10 D3-note).
     *
     * Writes the orders row directly rather than through OrderTable::store() —
     * ConsoleApplication (CLI/seeder context) never calls loadIdentity(), so
     * getIdentity() is null there and OrderTable::store() would fault reading
     * $user->id. modified_on/modified_by are set here instead so that side effect
     * of going through the Table class isn't lost.
     */
    private static function syncOrderRefund(int $orderId, int $modifiedBy = 0): void
    {
        $order = self::fetchOrderRow($orderId);

        if ($order === null) {
            return;
        }

        $displayRefunded = self::sumByTypes($orderId, ['REVERSAL']);
        $rate            = (float) $order->currency_value;
        $rate            = $rate > 0 ? $rate : 1.0;
        $decimals        = CurrencyHelper::getDecimalPlace(ConfigHelper::getDefaultCurrency());
        $baseRefunded    = round($displayRefunded / $rate, $decimals);
        $now             = Factory::getDate()->toSql();

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set([
                $db->quoteName('order_refund') . ' = :orderRefund',
                $db->quoteName('modified_on') . ' = :modifiedOn',
                $db->quoteName('modified_by') . ' = :modifiedBy',
            ])
            ->where($db->quoteName('j2commerce_order_id') . ' = :orderId')
            ->bind(':orderRefund', $baseRefunded, ParameterType::STRING)
            ->bind(':modifiedOn', $now)
            ->bind(':modifiedBy', $modifiedBy, ParameterType::INTEGER)
            ->bind(':orderId', $orderId, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();
    }

    // =========================================================================
    // INTERNAL — legacy fallback (no ledger rows)
    // =========================================================================

    /** @return float Display-currency amount — order_total is stored in the base currency. */
    private static function legacyCaptured(int $orderId): float
    {
        $order = self::fetchOrderRow($orderId);

        if ($order === null) {
            return 0.0;
        }

        $base = match ((string) $order->transaction_status) {
            'Completed', 'Refunded', 'Partially Refunded' => (float) $order->order_total,
            default                                       => 0.0,
        };

        return CurrencyHelper::convertForOrder($base, $order);
    }

    /** @return float Display-currency amount — order_refund is stored in the base currency. */
    private static function legacyRefunded(int $orderId): float
    {
        $order = self::fetchOrderRow($orderId);

        if ($order === null) {
            return 0.0;
        }

        return CurrencyHelper::convertForOrder((float) ($order->order_refund ?? 0), $order);
    }
}
