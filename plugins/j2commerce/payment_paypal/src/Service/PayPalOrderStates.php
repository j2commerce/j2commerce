<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_paypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

/**
 * Resolves the order status a PayPal outcome writes. The merchant's param wins; an unset
 * param falls back to the one status mapped to the outcome's type, then to the core row by
 * name. Ids are install-dependent, so nothing here is a literal. 0 means leave the status.
 */
final class PayPalOrderStates
{
    public const CONFIRMED = ['payment_status', OrderStatusHelper::TYPE_COMPLETE, 'J2COMMERCE_CONFIRMED'];
    public const PENDING   = ['pending_state_id', OrderStatusHelper::TYPE_OPEN, 'J2COMMERCE_PENDING'];
    public const FAILED    = ['failed_state_id', OrderStatusHelper::TYPE_FAILED, 'J2COMMERCE_FAILED'];
    /** Core ships no refunded row, so an unmapped, unset store leaves the status alone. */
    public const REFUNDED  = ['refunded_state_id', OrderStatusHelper::TYPE_REFUNDED, ''];

    /** No param exposes this one; it resolves by type, then by the core row's name. */
    public const NEW = ['new_state_id', OrderStatusHelper::TYPE_NEW, 'J2COMMERCE_NEW'];

    /** The outcomes an order may still be captured from — nothing has settled yet. */
    private const AWAITING_PAYMENT = [self::NEW, self::PENDING, self::FAILED];

    /** @param array{0: string, 1: string, 2: string} $outcome One of the constants above. */
    public static function resolve(Registry $params, array $outcome): int
    {
        [$key, $type, $coreName] = $outcome;

        $id = (int) $params->get($key, 0);

        return $id > 0 ? $id : OrderStatusHelper::idOfType($type, $coreName);
    }

    /**
     * Is this order still awaiting payment, and so safe to capture, complete or finalize?
     *
     * Each leg goes through resolve(), never through a literal id and never through a bare
     * type union. j2commerce_orderstatus_id is AUTO_INCREMENT and the J2Store migrator
     * preserves source ids, so a literal names the wrong row on a migrated store. A type on
     * its own is no better here: a merchant may classify any number of their own rows 'open',
     * so a union over that type would admit rows this guard exists to exclude. The
     * disambiguation rule itself lives once, in OrderStatusHelper::idOfType().
     *
     * An outcome that resolves to nothing contributes nothing, so a partially classified
     * store still matches on its remaining legs.
     */
    public static function isAwaitingPayment(int $stateId, Registry $params): bool
    {
        return $stateId > 0 && \in_array($stateId, self::awaitingStateIds($params), true);
    }

    /** A held claim older than this is treated as abandoned, so another request may take it over. */
    private const CLAIM_TTL = 600;

    private const CLAIM_PREFIX = 'SETTLING@';

    private const FOLLOW_UPS         = 'after_payment';
    private const FOLLOW_UPS_PENDING = 'pending@';

    /**
     * The checkout capture, the capture webhook and the subscription approval can each see one
     * payment complete. Whichever writes its claim token here owns the settlement: it alone stores
     * the paid state and runs AfterPayment and the order emails. The token carries its time, so a
     * claim whose owner died before storing can be taken over once it is stale.
     *
     * @return string|null The claim token when this call owns the settlement.
     */
    public static function claimSettlement(DatabaseInterface $db, int $orderPk, Registry $params): ?string
    {
        $awaiting = self::awaitingStateIds($params);

        if ($awaiting === []) {
            return null;
        }

        $token = self::newToken();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_status') . ' = :token')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->whereIn($db->quoteName('order_state_id'), $awaiting)
            ->bind(':token', $token)
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        self::whereClaimable($db, $query);
        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1 ? $token : null;
    }

    /**
     * Runs the claim owner's store under a row lock that first proves the claim is still its own,
     * so a stale-claim takeover waits for the store instead of settling the order a second time,
     * and an owner that dies mid-store rolls back to a claim the takeover can reclaim.
     *
     * @param callable(): bool $store Writes the settled order (status, transaction fields).
     *
     * @return 'stored'|'lost'|'failed' On 'failed' the claim has been handed back.
     */
    public static function storeUnderClaim(
        DatabaseInterface $db,
        int $orderPk,
        string $claim,
        string $priorStatus,
        callable $store
    ): string {
        $db->transactionStart();

        try {
            if (self::lockedStatus($db, $orderPk) !== $claim) {
                $db->transactionRollback();

                return 'lost';
            }

            $stored = (bool) $store();
        } catch (\Throwable $e) {
            $stored = false;

            // A logger failure must not leave the transaction open.
            try {
                Factory::getApplication()->getLogger()->error(
                    'PayPal settlement store threw: ' . $e->getMessage(),
                    ['category' => 'j2commerce.paypal', 'order_pk' => $orderPk]
                );
            } catch (\Throwable) {
            }
        }

        // A store that throws or reports failure after its status listeners ran has already moved
        // stock or sent mail: keep a write that landed, so a retry does not repeat those side
        // effects. Every owner writes a completed status, so only that proves the write landed.
        try {
            if ($stored || strtoupper(self::lockedStatus($db, $orderPk)) === 'COMPLETED') {
                $db->transactionCommit();

                return 'stored';
            }
        } catch (\Throwable) {
        }

        $db->transactionRollback();
        self::releaseSettlement($db, $orderPk, $claim, $priorStatus);

        return 'failed';
    }

    /** A replayed event or request records the same note on the order's history only once. */
    public static function addHistoryOnce(DatabaseInterface $db, object $orderTable, string $comment): void
    {
        $orderId = (string) $orderTable->order_id;

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_orderhistories'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->where($db->quoteName('comment') . ' = :comment')
                ->bind(':orderId', $orderId)
                ->bind(':comment', $comment);

            if ((int) $db->setQuery($query)->loadResult() === 0) {
                OrderHistoryHelper::add(
                    orderId: $orderId,
                    comment: $comment,
                    orderStateId: (int) $orderTable->order_state_id,
                );
            }
        } catch (\Throwable $e) {
            try {
                Factory::getApplication()->getLogger()->error(
                    'PayPal history write failed: ' . $e->getMessage(),
                    ['category' => 'j2commerce.paypal', 'order_id' => $orderId]
                );
            } catch (\Throwable) {
            }
        }
    }

    /** Stored transaction details as an array; anything that is not a JSON object reads as empty. */
    public static function details(string $json): array
    {
        $decoded = json_decode($json !== '' ? $json : '{}', true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * The settlement owner stamps this inside its store and sets it to 'done' once AfterPayment has
     * run, so an owner that dies between the two leaves a trace a later request can finish from.
     * Orders settled before the stamp existed carry none and read as done.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function withFollowUpsPending(array $details): array
    {
        $details[self::FOLLOW_UPS] = self::FOLLOW_UPS_PENDING . time();

        return $details;
    }

    /** @return 'done'|'running'|'abandoned' */
    public static function followUps(object $orderTable): string
    {
        $value = (string) (self::details((string) ($orderTable->transaction_details ?? ''))[self::FOLLOW_UPS] ?? '');

        if (!str_starts_with($value, self::FOLLOW_UPS_PENDING)) {
            return 'done';
        }

        return (int) substr($value, \strlen(self::FOLLOW_UPS_PENDING)) < time() - self::CLAIM_TTL ? 'abandoned' : 'running';
    }

    /**
     * Runs AfterPayment for an owner that stored the order and died before marking it done. Only one
     * request takes the marker over, but an owner killed between AfterPayment and the done write has
     * it run again: delivery is at least once. An order since refunded, or moved off the status its
     * owner left it on (the paid status, or the awaiting one where none resolves), is left alone.
     * Bookkeeping only, so nothing here throws into the caller.
     */
    public static function recoverFollowUps(DatabaseInterface $db, object $orderTable, Registry $params): void
    {
        try {
            $paidStateId = self::resolve($params, self::CONFIRMED);
            $stateId     = (int) ($orderTable->order_state_id ?? 0);

            if (
                self::followUps($orderTable) !== 'abandoned'
                || (float) ($orderTable->order_refund ?? 0) > 0
                || ($paidStateId > 0 ? $stateId !== $paidStateId : !self::isAwaitingPayment($stateId, $params))
                || !self::swapFollowUps($db, $orderTable, self::FOLLOW_UPS_PENDING . time())
            ) {
                return;
            }
        } catch (\Throwable $e) {
            self::logError('PayPal AfterPayment recovery check failed: ' . $e->getMessage(), $orderTable);

            return;
        }

        try {
            J2CommerceHelper::plugin()->eventWithArray('AfterPayment', [$orderTable]);
        } catch (\Throwable $e) {
            self::logError('PayPal recovered AfterPayment listener failed: ' . $e->getMessage(), $orderTable);
        }

        self::completeFollowUps($db, $orderTable);
    }

    /** Under a row lock, so a concurrent write to the same details neither loses this nor is lost. Never throws. */
    public static function completeFollowUps(DatabaseInterface $db, object $orderTable): void
    {
        $pk = (int) $orderTable->j2commerce_order_id;

        try {
            $db->transactionStart();

            try {
                $query = $db->createQuery()
                    ->setQuery(
                        'SELECT ' . $db->quoteName('transaction_details')
                        . ' FROM ' . $db->quoteName('#__j2commerce_orders')
                        . ' WHERE ' . $db->quoteName('j2commerce_order_id') . ' = :pk FOR UPDATE'
                    )
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                $details = self::details((string) $db->setQuery($query)->loadResult());

                $details[self::FOLLOW_UPS] = 'done';

                $new    = json_encode($details);
                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_orders'))
                    ->set($db->quoteName('transaction_details') . ' = :new')
                    ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                    ->bind(':new', $new)
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                $db->setQuery($update)->execute();
                $db->transactionCommit();

                $orderTable->transaction_details = $new;
            } catch (\Throwable $e) {
                $db->transactionRollback();

                throw $e;
            }
        } catch (\Throwable $e) {
            self::logError('PayPal AfterPayment done marker failed: ' . $e->getMessage(), $orderTable);
        }
    }

    /** Writes the marker only if transaction_details is still what $orderTable holds, so no other write is lost. */
    private static function swapFollowUps(DatabaseInterface $db, object $orderTable, string $value): bool
    {
        $old     = (string) ($orderTable->transaction_details ?? '');
        $details = self::details($old);

        $details[self::FOLLOW_UPS] = $value;

        $new   = json_encode($details);
        $pk    = (int) $orderTable->j2commerce_order_id;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_details') . ' = :new')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->where($db->quoteName('transaction_details') . ' = :old')
            ->bind(':new', $new)
            ->bind(':pk', $pk, ParameterType::INTEGER)
            ->bind(':old', $old);

        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() !== 1) {
            return false;
        }

        $orderTable->transaction_details = $new;

        return true;
    }

    private static function logError(string $message, object $orderTable): void
    {
        try {
            Factory::getApplication()->getLogger()->error(
                $message,
                ['category' => 'j2commerce.paypal', 'order_id' => $orderTable->order_id ?? '']
            );
        } catch (\Throwable) {
        }
    }

    /** The query builder has no MySQL row-lock clause, so the statement is written out with a bound id. */
    private static function lockedStatus(DatabaseInterface $db, int $orderPk): string
    {
        $query = $db->createQuery()
            ->setQuery(
                'SELECT ' . $db->quoteName('transaction_status')
                . ' FROM ' . $db->quoteName('#__j2commerce_orders')
                . ' WHERE ' . $db->quoteName('j2commerce_order_id') . ' = :pk FOR UPDATE'
            )
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    /** The random suffix keeps two claims taken in the same second distinct. */
    private static function newToken(): string
    {
        return self::CLAIM_PREFIX . time() . '.' . bin2hex(random_bytes(4));
    }

    /**
     * Has a settlement owner stored the order? Its own marker counts even where no Complete status
     * resolves and the order status stays; a held payment's record says PENDING, so it does not.
     */
    public static function isSettlementStored(object $orderTable, Registry $params): bool
    {
        $details = json_decode((string) ($orderTable->transaction_details ?? '{}'), true) ?: [];

        return strtoupper((string) ($details['capture_status'] ?? '')) === 'COMPLETED'
            || isset($details['capture_completed_at'])
            || !self::isAwaitingPayment((int) ($orderTable->order_state_id ?? 0), $params);
    }

    /** Hands a claim back when its owner could not store the order, so a retry can settle it. */
    public static function releaseSettlement(
        DatabaseInterface $db,
        int $orderPk,
        string $token,
        string $priorStatus
    ): void {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_status') . ' = :prior')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->where($db->quoteName('transaction_status') . ' = :token')
            ->bind(':prior', $priorStatus)
            ->bind(':pk', $orderPk, ParameterType::INTEGER)
            ->bind(':token', $token);

        $db->setQuery($query)->execute();
    }

    /** Restricts a write to orders no request has settled, or holds a live claim on. */
    public static function whereClaimable(DatabaseInterface $db, QueryInterface $query): void
    {
        $column     = 'COALESCE(' . $db->quoteName('transaction_status') . ', ' . $db->quote('') . ')';
        $completed  = 'COMPLETED';
        $pattern    = self::CLAIM_PREFIX . '%';
        $staleClaim = self::CLAIM_PREFIX . (time() - self::CLAIM_TTL);

        // Upper-cased: the subscription and Express Checkout paths store 'Completed', which a
        // binary collation would otherwise not match.
        $query->where('UPPER(' . $column . ') <> :claimCompleted')
            ->where('(' . $column . ' NOT LIKE :claimPattern OR ' . $column . ' < :claimStale)')
            ->bind(':claimCompleted', $completed)
            ->bind(':claimPattern', $pattern)
            ->bind(':claimStale', $staleClaim);
    }

    /** True for a settled transaction, and for one a request is settling right now. */
    public static function isSettledOrSettling(string $transactionStatus): bool
    {
        $status = strtoupper($transactionStatus);

        if ($status === 'COMPLETED') {
            return true;
        }

        return str_starts_with($status, self::CLAIM_PREFIX)
            && $status >= self::CLAIM_PREFIX . (time() - self::CLAIM_TTL);
    }

    /** @return list<int> */
    public static function awaitingStateIds(Registry $params): array
    {
        return array_values(array_filter(array_map(
            static fn (array $outcome): int => self::resolve($params, $outcome),
            self::AWAITING_PAYMENT
        )));
    }
}
