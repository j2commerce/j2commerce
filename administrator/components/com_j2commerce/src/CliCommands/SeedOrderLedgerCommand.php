<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\CliCommands;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-time, idempotent backfill of #__j2commerce_ordertransactions from legacy
 * transaction_details JSON. Guarded per-order by OrderTransactionHelper::hasLedger()
 * so re-running never double-seeds. Per-order failures are logged and skipped —
 * never aborts. See docs/plans/order_transactions_ledger_prd.md §8.
 */
class SeedOrderLedgerCommand extends AbstractCommand
{
    protected static $defaultName = 'j2commerce:seed:order-ledger';

    private const SEEDABLE_STATUSES = ['Completed', 'Refunded', 'Partially Refunded'];

    private const REVERSAL_EPSILON = 0.00001;

    private const BATCH_SIZE = 500;

    protected function configure(): void
    {
        $this->setDescription('One-time backfill of the order-transaction ledger from legacy transaction_details JSON');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $result = self::run();

        $io->table(
            ['Seeded', 'Skipped (already has ledger)', 'Failed', 'Reversals skipped (unattributable)'],
            [[$result['seeded'], $result['skipped'], $result['failed'], $result['reversalsSkipped']]]
        );
        $io->success('Order ledger seed complete.');

        return 0;
    }

    /**
     * @return array{seeded: int, skipped: int, failed: int, reversalsSkipped: int}
     */
    public static function run(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $seeded           = 0;
        $skipped          = 0;
        $failed           = 0;
        $reversalsSkipped = 0;
        $offset           = 0;

        // Paged in fixed-size batches (ordered by PK) rather than loading every
        // seedable order into memory at once — this table can grow large in
        // production, and transaction_status doesn't change mid-run, so the
        // paging window stays stable across iterations.
        do {
            $query        = $db->getQuery(true)
                ->select($db->quoteName([
                    'j2commerce_order_id', 'order_total', 'order_refund', 'transaction_id',
                    'transaction_status', 'transaction_details', 'currency_code',
                    'currency_value', 'orderpayment_type', 'created_by',
                ]))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->order($db->quoteName('j2commerce_order_id') . ' ASC');
            $placeholders = [];
            $statuses     = self::SEEDABLE_STATUSES;

            // bind() holds references — bind the array slots, not the loop variable.
            foreach ($statuses as $i => $status) {
                $placeholder    = ':status' . $i;
                $placeholders[] = $placeholder;
                $query->bind($placeholder, $statuses[$i]);
            }

            $query->where($db->quoteName('transaction_status') . ' IN (' . implode(',', $placeholders) . ')');

            $db->setQuery($query, $offset, self::BATCH_SIZE);
            $orders = $db->loadObjectList() ?: [];

            foreach ($orders as $order) {
                $orderId = (int) $order->j2commerce_order_id;

                try {
                    if (OrderTransactionHelper::hasLedger($orderId)) {
                        $skipped++;
                        continue;
                    }

                    $reversalsSkipped += self::seedOrder($order);
                    $seeded++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::add(
                        \sprintf('j2commerce:seed:order-ledger failed for order %d: %s', $orderId, $e->getMessage()),
                        Log::WARNING,
                        'j2commerce'
                    );
                }
            }

            $batchCount = \count($orders);
            $offset += self::BATCH_SIZE;
        } while ($batchCount === self::BATCH_SIZE);

        return ['seeded' => $seeded, 'skipped' => $skipped, 'failed' => $failed, 'reversalsSkipped' => $reversalsSkipped];
    }

    /** @return int 1 if the order's refund could not be attributed to any charge and was skipped, else 0. */
    private static function seedOrder(object $order): int
    {
        $orderId       = (int) $order->j2commerce_order_id;
        $plugin        = (string) ($order->orderpayment_type ?? '');
        $currencyCode  = (string) ($order->currency_code ?? '');
        $currencyValue = (float) ($order->currency_value ?: 1.0);
        $createdBy     = (int) ($order->created_by ?? 0);
        $transactionId = (string) ($order->transaction_id ?? '');

        $details = [];
        $json    = (string) ($order->transaction_details ?? '');

        if ($json !== '') {
            try {
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                $details = \is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $details = [];
            }
        }

        $hasChargesArray = !empty($details['charges']) && \is_array($details['charges']);

        // Priority order mirrors legacy transaction_details shapes oldest-to-newest
        // plugin generations: charges[] (multi-charge gateways) > captured > amount
        // > order_total fallback (pre-ledger single-transaction orders).
        $chargeLegs = [];

        if ($hasChargesArray) {
            foreach ($details['charges'] as $charge) {
                $amount = is_numeric($charge['amount'] ?? null) ? (float) $charge['amount'] : 0.0;

                if ($amount <= 0.0) {
                    continue;
                }

                $gatewayTxnId = (string) ($charge['id'] ?? $charge['gateway_txn_id'] ?? '');
                OrderTransactionHelper::addCharge(
                    orderId: $orderId,
                    pluginEl: $plugin,
                    gatewayTxnId: $gatewayTxnId,
                    amount: $amount,
                    currencyCode: $currencyCode,
                    createdBy: $createdBy
                );

                if ($gatewayTxnId !== '') {
                    $chargeLegs[] = ['gatewayTxnId' => $gatewayTxnId, 'amount' => $amount];
                }
            }
        } elseif (is_numeric($details['captured'] ?? null) && (float) $details['captured'] > 0.0) {
            OrderTransactionHelper::addCharge(
                orderId: $orderId,
                pluginEl: $plugin,
                gatewayTxnId: $transactionId,
                amount: (float) $details['captured'],
                currencyCode: $currencyCode,
                createdBy: $createdBy
            );
        } elseif (is_numeric($details['amount'] ?? null) && (float) $details['amount'] > 0.0) {
            OrderTransactionHelper::addCharge(
                orderId: $orderId,
                pluginEl: $plugin,
                gatewayTxnId: $transactionId,
                amount: (float) $details['amount'],
                currencyCode: $currencyCode,
                createdBy: $createdBy
            );
        } else {
            $orderTotalDisplay = round((float) $order->order_total * $currencyValue, self::amountDecimals($currencyCode));

            if ($orderTotalDisplay > 0.0) {
                OrderTransactionHelper::addCharge(
                    orderId: $orderId,
                    pluginEl: $plugin,
                    gatewayTxnId: $transactionId,
                    amount: $orderTotalDisplay,
                    currencyCode: $currencyCode,
                    createdBy: $createdBy
                );
            }
        }

        $orderRefundBase = (float) ($order->order_refund ?? 0);

        if ($orderRefundBase <= 0.0) {
            return 0;
        }

        $refundDisplay = round($orderRefundBase * $currencyValue, self::amountDecimals($currencyCode));

        if ($hasChargesArray) {
            return self::seedChargesReversal($orderId, $plugin, $transactionId, $chargeLegs, $refundDisplay, $createdBy);
        }

        // Legacy single-transaction orders recorded exactly one charge, so
        // $transactionId doubles as both the reversal's own gateway id and its
        // parent capture id (both branches above wrote the capture with this id).
        OrderTransactionHelper::addReversal(
            orderId: $orderId,
            pluginEl: $plugin,
            refundTxnId: $transactionId,
            parentTxnId: $transactionId,
            amount: $refundDisplay,
            createdBy: $createdBy
        );

        return 0;
    }

    /** currencyCode may be '' when the order row lookup yields no currency — fall back to 2dp. */
    private static function amountDecimals(string $currencyCode): int
    {
        return $currencyCode !== '' ? CurrencyHelper::getDecimalPlace($currencyCode) : 2;
    }

    /**
     * Splits a legacy order-level refund across its charges[] captures, newest
     * first, mirroring OrderTransactionHelper::capturesWithRemainders()' LIFO
     * order. Each leg gets a distinct gateway_txn_id so the D2 unique key
     * (order_id, gateway_txn_id, type) doesn't collide across legs.
     *
     * @param array<int, array{gatewayTxnId: string, amount: float}> $chargeLegs
     *
     * @return int 1 if no charge id was attributable and the reversal was skipped, else 0.
     */
    private static function seedChargesReversal(
        int $orderId,
        string $plugin,
        string $refundTxnId,
        array $chargeLegs,
        float $refundDisplay,
        int $createdBy
    ): int {
        if ($chargeLegs === []) {
            // No charge in charges[] carried an id/gateway_txn_id — writing an
            // unattributed reversal wouldn't reduce any capture's remainder
            // (the exact C1 defect), so skip and flag for manual reconciliation.
            Log::add(
                \sprintf(
                    'j2commerce:seed:order-ledger: order %d has order_refund %.5f but no attributable charge id — reversal skipped.',
                    $orderId,
                    $refundDisplay
                ),
                Log::WARNING,
                'j2commerce'
            );

            return 1;
        }

        $legsNewestFirst = array_reverse($chargeLegs);
        $remaining       = $refundDisplay;
        $leg             = 0;

        foreach ($legsNewestFirst as $charge) {
            if ($remaining <= self::REVERSAL_EPSILON) {
                break;
            }

            $take = min($remaining, $charge['amount']);
            $leg++;

            OrderTransactionHelper::addReversal(
                orderId: $orderId,
                pluginEl: $plugin,
                refundTxnId: $refundTxnId . '-seed-' . $leg,
                parentTxnId: $charge['gatewayTxnId'],
                amount: $take,
                createdBy: $createdBy
            );

            $remaining -= $take;
        }

        if ($remaining > self::REVERSAL_EPSILON) {
            Log::add(
                \sprintf(
                    'j2commerce:seed:order-ledger: order %d refund %.5f exceeds total attributable charge amount by %.5f.',
                    $orderId,
                    $refundDisplay,
                    $remaining
                ),
                Log::WARNING,
                'j2commerce'
            );
        }

        return 0;
    }
}
