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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Entry rule: an order enters a complete-type status only when OrderPaymentHelper says it is paid; shared by OrderModel::updateOrderStatus() and OrderTable::store(). */
final class PaymentRuleHelper
{
    public const MODE_OFF     = 0;
    public const MODE_LOG     = 1;
    public const MODE_ENFORCE = 2;

    /** A merchant's own change (admin screens, bulk, REST fulfilment): refused when unpaid unless the user holds j2commerce.confirmwithoutpayment. */
    public const SOURCE_MERCHANT = 'merchant';

    /** A payment plugin, automation or other caller: held when unpaid (on Pending from a New or Failed order, otherwise in place), never refused. */
    public const SOURCE_GATEWAY = 'gateway';

    public const MARKER = 'payment_rule';

    public static function mode(): int
    {
        $mode = (int) ComponentHelper::getParams('com_j2commerce')->get('order_payment_rule', self::MODE_LOG);

        return $mode === self::MODE_LOG || $mode === self::MODE_ENFORCE ? $mode : self::MODE_OFF;
    }

    /** action: allow | would_park (Log Only, move allowed) | refuse | override | park | no_hold (no status to hold on, move allowed); target differs from intended only for park. */
    public static function decide(
        object $order,
        int $oldStatusId,
        int $newStatusId,
        string $source,
        ?int $mode = null,
        ?bool $override = null,
        ?string $paymentState = null
    ): array {
        $allow = ['action' => 'allow', 'target' => $newStatusId, 'intended' => $newStatusId];
        $mode ??= self::mode();

        if ($mode === self::MODE_OFF || $oldStatusId === $newStatusId) {
            return $allow;
        }

        if (OrderStatusHelper::getType($newStatusId) !== OrderStatusHelper::TYPE_COMPLETE) {
            return $allow;
        }

        // Already on a Confirmed-type status: this is not an entry.
        if ($oldStatusId > 0 && OrderStatusHelper::getType($oldStatusId) === OrderStatusHelper::TYPE_COMPLETE) {
            return $allow;
        }

        if (($paymentState ?? OrderPaymentHelper::state($order)) === OrderPaymentHelper::STATE_PAID) {
            return $allow;
        }

        if ($mode === self::MODE_LOG) {
            return ['action' => 'would_park'] + $allow;
        }

        if ($source === self::SOURCE_MERCHANT) {
            return ['action' => ($override ?? self::canOverride()) ? 'override' : 'refuse'] + $allow;
        }

        $hold = self::holdStatusId($oldStatusId);

        if ($hold <= 0) {
            Log::add('Payment rule could not hold order on an open status: no open-type status resolves, so the move to ' . $newStatusId . ' was allowed', Log::WARNING, 'com_j2commerce');

            return ['action' => 'no_hold'] + $allow;
        }

        return ['action' => 'park', 'target' => $hold, 'intended' => $newStatusId];
    }

    /** Moves a parked order to the status it was meant for once payment evidence has landed; inert for an order with no marker. */
    public static function promoteIfSatisfied(int $orderPk): void
    {
        self::promote($orderPk);
    }

    /** @return int|null  The status the order moved to, null when nothing moved. */
    public static function promote(int $orderPk): ?int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['order_state_id', 'order_params']))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        $row    = $db->setQuery($query)->loadObject();
        $marker = $row ? self::readMarker($row->order_params) : null;

        // The marker proves the order was parked, so it is honored whatever the rule's mode is now.
        if ($marker === null) {
            return null;
        }

        // A manual move off the parked status invalidates the marker; drop it so a later payment cannot yank the order to the old target.
        if ((int) $row->order_state_id !== $marker['parked']) {
            self::clearMarker($orderPk);

            return null;
        }

        if (OrderPaymentHelper::state($orderPk) !== OrderPaymentHelper::STATE_PAID) {
            return null;
        }

        self::loadAdminLanguage();

        $comment = Text::sprintf(
            'COM_J2COMMERCE_HISTORY_PROMOTED_AFTER_PAYMENT',
            self::statusLabel($marker['parked']),
            self::statusLabel($marker['intended'])
        );

        $model = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()
            ->createModel('Order', 'Administrator', ['ignore_request' => true]);

        if (!$model->updateOrderStatus($orderPk, $marker['intended'], $marker['notify'], $comment, false, false)) {
            return null;
        }

        self::clearMarker($orderPk);

        return $marker['intended'];
    }

    /** Writes the history line (machine-readable `payment_rule` in params) and the log entry for a non-allow decision; `$stateId` is the status the order holds after the write. */
    public static function recordDecision(string $orderId, array $decision, int $stateId, int $userId): void
    {
        if (!\in_array($decision['action'], ['park', 'would_park', 'override', 'no_hold'], true)) {
            return;
        }

        self::loadAdminLanguage();

        $intended = self::statusLabel($decision['intended']);

        [$comment, $params] = match ($decision['action']) {
            'park' => [
                Text::sprintf('COM_J2COMMERCE_HISTORY_PARKED_NO_PAYMENT', self::statusLabel($decision['target']), $intended),
                ['payment_rule' => 'parked', 'intended' => $decision['intended']],
            ],
            'would_park' => [
                Text::sprintf('COM_J2COMMERCE_HISTORY_RULE_WOULD_HOLD', $intended),
                ['payment_rule' => 'would_park', 'intended' => $decision['intended']],
            ],
            'no_hold' => [
                Text::sprintf('COM_J2COMMERCE_HISTORY_RULE_NO_HOLD_STATUS', $intended),
                ['payment_rule' => 'no_hold', 'intended' => $decision['intended']],
            ],
            default => [
                Text::sprintf('COM_J2COMMERCE_HISTORY_CONFIRMED_WITHOUT_PAYMENT', $intended, self::userName()),
                ['payment_rule' => 'override', 'user_id' => $userId],
            ],
        };

        OrderHistoryHelper::add($orderId, $comment, $stateId, false, $userId, $params);

        if ($decision['action'] === 'override') {
            Log::add('Order ' . $orderId . ' moved to a Confirmed status without a recorded payment by user ' . $userId, Log::WARNING, 'com_j2commerce');
        }
    }

    /** Holds an order already on its held status: marker and "Held" history line are written once per identical marker, so a gateway redelivery adds nothing; evidence that landed in between then releases the order. */
    public static function holdInPlace(int $orderPk, string $orderId, array $decision, int $stateId, bool $notify, int $userId): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_params'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':pk', $orderPk, ParameterType::INTEGER);

        $marker = self::readMarker($db->setQuery($query)->loadResult());

        if ($marker === null || $marker['intended'] !== $decision['intended'] || $marker['parked'] !== $stateId) {
            self::writeMarker($orderPk, $decision['intended'], $stateId, $notify);
            self::recordDecision($orderId, $decision, $stateId, $userId);
        }

        self::promote($orderPk);
    }

    /** Language key for a refused move to a Confirmed-type status; an order with a refund has had payments, so it is not "no recorded payment". */
    public static function refusalKey(string $paymentState, bool $hasRefund = false): string
    {
        return $hasRefund || $paymentState === OrderPaymentHelper::STATE_REFUNDED
            ? 'COM_J2COMMERCE_ERR_RECORD_PAYMENT_REFUNDED'
            : 'COM_J2COMMERCE_ERR_RECORD_PAYMENT_FIRST';
    }

    /** Refusal text for an unpaid move into a Confirmed-type status; the toolbar button is named only to a user who can record payments. HTML-safe, because model errors render as HTML in the admin messages. */
    public static function refusalMessage(object $order, array $summary, int $newStatusId, bool $canRecord): string
    {
        self::loadAdminLanguage();

        $status = htmlspecialchars(self::statusLabel($newStatusId), ENT_QUOTES, 'UTF-8');
        $fmt    = static fn (float $value): string => CurrencyHelper::format($value, (string) ($order->currency_code ?? ''), 1.0);
        $suffix = $canRecord ? '' : '_NO_ACCESS';
        $state  = $summary['state'];

        return match (true) {
            $summary['refunded'] > 0 || $state === OrderPaymentHelper::STATE_REFUNDED => Text::sprintf(self::refusalKey($state, true), $status),
            $state === OrderPaymentHelper::STATE_PARTIAL                              => Text::sprintf('COM_J2COMMERCE_ERR_RECORD_PAYMENT_PARTIAL' . $suffix, $fmt($summary['net']), $fmt($summary['due_now']), $status),
            $state === OrderPaymentHelper::STATE_AUTHORIZED                           => Text::sprintf('COM_J2COMMERCE_ERR_RECORD_PAYMENT_AUTHORIZED' . $suffix, $status),
            default                                                                   => Text::sprintf('COM_J2COMMERCE_ERR_RECORD_PAYMENT_FIRST' . $suffix, $status),
        };
    }

    /** Raw check on purpose: canAccess() grants core.manage holders every custom action. */
    public static function canOverride(): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && !$user->guest && $user->authorise('j2commerce.confirmwithoutpayment', 'com_j2commerce');
    }

    /** `order_params` with the park marker set; read with objects so other keys keep their JSON shape. */
    public static function withMarker(mixed $orderParams, int $intended, int $parked, bool $notify = false): string
    {
        $data = self::decodeParams($orderParams);

        $data->{self::MARKER} = (object) [
            'intended' => $intended,
            'parked'   => $parked,
            'notify'   => $notify,
            'at'       => gmdate('Y-m-d H:i:s'),
        ];

        return self::encodeParams($data);
    }

    public static function withoutMarker(mixed $orderParams): string
    {
        $data = self::decodeParams($orderParams);

        unset($data->{self::MARKER});

        return self::encodeParams($data);
    }

    /** @return array{intended: int, parked: int, notify: bool}|null */
    public static function readMarker(mixed $orderParams): ?array
    {
        $marker = self::decodeParams($orderParams)->{self::MARKER} ?? null;

        if (!\is_object($marker) || !isset($marker->intended, $marker->parked)) {
            return null;
        }

        return ['intended' => (int) $marker->intended, 'parked' => (int) $marker->parked, 'notify' => !empty($marker->notify)];
    }

    public static function writeMarker(int $orderPk, int $intended, int $parked, bool $notify = false): void
    {
        self::mutateParams($orderPk, static fn (?string $raw): string => self::withMarker($raw, $intended, $parked, $notify));
    }

    public static function clearMarker(int $orderPk): void
    {
        self::mutateParams($orderPk, static fn (?string $raw): string => self::withoutMarker($raw));
    }

    private static function holdStatusId(int $oldStatusId): int
    {
        $pending = OrderStatusHelper::idOfType(OrderStatusHelper::TYPE_OPEN, 'J2COMMERCE_PENDING');

        if ($oldStatusId <= 0) {
            return $pending;
        }

        // Only an order not yet placed or one that failed is held on Pending. Approved, shipped,
        // delivered, cancelled and merchant-typed orders keep the status they have: sending them
        // back to Pending would re-run stock and redemption moves for an order that is further along.
        return \in_array(OrderStatusHelper::getType($oldStatusId), [OrderStatusHelper::TYPE_NEW, OrderStatusHelper::TYPE_FAILED], true)
            ? $pending
            : $oldStatusId;
    }

    /** Compare-and-swap on the bytes read, so a concurrent order_params write is never overwritten. */
    private static function mutateParams(int $orderPk, callable $mutate): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $select = $db->getQuery(true)
                ->select($db->quoteName('order_params'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $orderPk, ParameterType::INTEGER);

            $raw = $db->setQuery($select)->loadResult();
            $new = $mutate($raw === null ? null : (string) $raw);

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_orders'))
                ->set($db->quoteName('order_params') . ' = :new')
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':new', $new)
                ->bind(':pk', $orderPk, ParameterType::INTEGER);

            if ($raw === null) {
                $update->where($db->quoteName('order_params') . ' IS NULL');
            } else {
                // Binary compare: the swap must match the exact bytes read, not a collation-equal value.
                $update->where('CAST(' . $db->quoteName('order_params') . ' AS BINARY) = :expected')->bind(':expected', $raw);
            }

            $db->setQuery($update)->execute();

            // MySQL reports 0 affected rows when the value is unchanged; that is success, not a lost race.
            if ($db->getAffectedRows() > 0 || $new === (string) $raw) {
                return;
            }
        }

        Log::add('Payment rule marker not written for order ' . $orderPk . ': order_params kept changing', Log::WARNING, 'com_j2commerce');
    }

    private static function decodeParams(mixed $orderParams): \stdClass
    {
        $decoded = match (true) {
            \is_string($orderParams) && $orderParams !== '' => json_decode($orderParams),
            \is_object($orderParams)                        => json_decode((string) json_encode($orderParams)),
            \is_array($orderParams)                         => json_decode((string) json_encode($orderParams)),
            default                                         => null,
        };

        return $decoded instanceof \stdClass ? $decoded : new \stdClass();
    }

    private static function encodeParams(\stdClass $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Translated status name as plain text; callers escape it once at output. */
    public static function statusLabel(int $statusId): string
    {
        static $names = [];

        if (!isset($names[$statusId])) {
            self::loadAdminLanguage();

            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('orderstatus_name'))
                ->from($db->quoteName('#__j2commerce_orderstatuses'))
                ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :id')
                ->bind(':id', $statusId, ParameterType::INTEGER);

            $name             = (string) $db->setQuery($query)->loadResult();
            $names[$statusId] = $name !== '' ? Text::_($name) : '#' . $statusId;
        }

        return $names[$statusId];
    }

    /** Plain text: history comments are escaped once, where the history is rendered. */
    private static function userName(): string
    {
        return (string) (Factory::getApplication()->getIdentity()?->name ?? '');
    }

    /** A site-side webhook has not loaded the admin file, and Text::_() would store the raw key. */
    private static function loadAdminLanguage(): void
    {
        Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_ADMINISTRATOR);
    }
}
