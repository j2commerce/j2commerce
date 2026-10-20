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

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
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
     * Renews the caller's claim immediately before its store, and returns the new token. Null when
     * a stale-claim takeover replaced it, so an owner that was merely slow cannot settle the order
     * a second time. The claim stays a timed token until the store writes the final status, so an
     * owner that dies mid-store can still be taken over.
     */
    public static function confirmClaim(DatabaseInterface $db, int $orderPk, string $token): ?string
    {
        $renewed = self::newToken();
        $query   = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_status') . ' = :renewed')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->where($db->quoteName('transaction_status') . ' = :token')
            ->bind(':renewed', $renewed)
            ->bind(':pk', $orderPk, ParameterType::INTEGER)
            ->bind(':token', $token);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1 ? $renewed : null;
    }

    /** The random suffix keeps a renewal within the same second a real change for the UPDATE. */
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

        $query->where($column . ' <> :claimCompleted')
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
