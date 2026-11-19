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

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Presentation and guards for Record Payment / Record Refund and the paid indicator; arithmetic lives in OrderPaymentHelper. */
final class OrderRecordedPaymentHelper
{
    /** @var array<string, string> value stored in ordertransactions.method => language key */
    private const PRESET_METHODS = [
        'cash'          => 'COM_J2COMMERCE_PAYMENT_METHOD_CASH',
        'bank_transfer' => 'COM_J2COMMERCE_PAYMENT_METHOD_BANK_TRANSFER',
        'check'         => 'COM_J2COMMERCE_PAYMENT_METHOD_CHECK',
        'card_terminal' => 'COM_J2COMMERCE_PAYMENT_METHOD_CARD_TERMINAL',
        'other'         => 'COM_J2COMMERCE_PAYMENT_METHOD_OTHER',
    ];

    /** @var array<string, array{0: string, 1: string}> state => [icon, badge classes] */
    private const STATE_STYLE = [
        'paid'               => ['fa-solid fa-circle-check', 'badge text-bg-success'],
        'partially_paid'     => ['fa-solid fa-circle-half-stroke', 'badge text-bg-warning'],
        'partially_refunded' => ['fa-solid fa-rotate-left', 'badge text-bg-secondary'],
        'authorized'         => ['fa-solid fa-lock', 'badge text-bg-info'],
        'refunded'           => ['fa-solid fa-rotate-left', 'badge text-bg-secondary'],
        'unpaid'             => ['fa-solid fa-circle-xmark', 'badge text-bg-danger'],
    ];

    /** States whose badge says something the order status does not (Deposit Paid is a flavour of paid, handled in indicator()). */
    private const INFORMATIVE_STATES = ['partially_paid', 'partially_refunded', 'authorized', 'refunded'];

    /** @var array<string, string> */
    private static array $pluginLabels = [];

    /** @return array<string, string> value => translated label */
    public static function methodOptions(string $orderPaymentType = ''): array
    {
        $options = [];

        foreach (self::PRESET_METHODS as $value => $key) {
            $options[$value] = Text::_($key);
        }

        $own = self::ownMethod($orderPaymentType);

        if ($own !== '' && !\in_array(strtolower(self::pluginLabel($own)), array_map('strtolower', $options), true)) {
            $options[$own] = self::pluginLabel($own);
        }

        return $options;
    }

    public static function methodLabel(string $value): string
    {
        if (isset(self::PRESET_METHODS[$value])) {
            return Text::_(self::PRESET_METHODS[$value]);
        }

        return $value === '' ? '' : self::pluginLabel($value);
    }

    /** The order's own gateway element, usable as a method value; '' when none applies. */
    public static function ownMethod(string $orderPaymentType): string
    {
        return preg_match('/^[a-z0-9_]{1,100}$/', $orderPaymentType) === 1 && $orderPaymentType !== 'none'
            ? $orderPaymentType
            : '';
    }

    private static function pluginLabel(string $element): string
    {
        if (isset(self::$pluginLabels[$element])) {
            return self::$pluginLabels[$element];
        }

        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $type   = 'plugin';
        $folder = 'j2commerce';
        $query  = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $element);
        $name = (string) ($db->setQuery($query)->loadResult() ?? '');

        if ($name === '') {
            return self::$pluginLabels[$element] = $element;
        }

        $lang = Factory::getApplication()->getLanguage();
        $ext  = 'plg_j2commerce_' . $element;
        $lang->load($ext . '.sys', JPATH_ADMINISTRATOR) || $lang->load($ext . '.sys', JPATH_PLUGINS . '/j2commerce/' . $element);

        return self::$pluginLabels[$element] = Text::_($name);
    }

    /** Money figures in the order's display currency, as the payment_balance layout shows them; threshold is half the currency's smallest unit. */
    public static function figures(object $order): array
    {
        $currency = (string) ($order->currency_code ?? '');
        $decimals = CurrencyHelper::getDecimalPlace($currency);
        $rate     = (float) ($order->currency_value ?? 1.0);
        $rate     = $rate > 0 ? $rate : 1.0;
        $total    = round((float) ($order->order_total ?? 0) * $rate, $decimals);
        $summary  = OrderTransactionHelper::getBalanceSummary((int) $order->j2commerce_order_id);

        return [
            'currency'  => $currency,
            'decimals'  => $decimals,
            'threshold' => 10 ** -$decimals / 2,
            'total'     => $total,
            'captured'  => (float) $summary['captured'],
            'refunded'  => (float) $summary['refunded'],
            'net'       => (float) $summary['net_paid'],
            'balance'   => max(0.0, $total - max(0.0, (float) $summary['net_paid'])),
        ];
    }

    /** Manual ledger rows (no gateway id), newest first, each with the recording user's name in recorded_by. */
    public static function manualRows(int $orderPk): array
    {
        $rows = array_values(array_filter(
            OrderTransactionHelper::getCharges($orderPk),
            static fn (object $row): bool => ($row->gateway_txn_id ?? null) === null
                && (string) ($row->state ?? '') === 'succeeded'
                && \in_array((string) $row->type, ['DEBIT', 'CAPTURE', 'REVERSAL'], true)
        ));

        $ids = array_values(array_unique(array_filter(array_map(static fn (object $r): int => (int) ($r->created_by ?? 0), $rows))));
        $by  = [];

        if ($ids) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('name')])
                ->from($db->quoteName('#__users'))
                ->whereIn($db->quoteName('id'), $ids, ParameterType::INTEGER);

            foreach ($db->setQuery($query)->loadObjectList() ?: [] as $user) {
                $by[(int) $user->id] = (string) $user->name;
            }
        }

        foreach ($rows as $row) {
            $row->recorded_by = $by[(int) ($row->created_by ?? 0)] ?? '';
        }

        return $rows;
    }

    /** Manual captures minus manual reversals; what Record Refund may return. */
    public static function manualRefundable(array $manualRows, int $decimals): float
    {
        $sum = 0.0;

        foreach ($manualRows as $row) {
            $sum += ((string) $row->type === 'REVERSAL') ? -(float) $row->amount : (float) $row->amount;
        }

        return max(0.0, round($sum, $decimals));
    }

    /** Manual rows shaped for the Recorded Payments table, in the view render and the AJAX envelope alike. */
    public static function displayRows(array $manualRows, string $currency): array
    {
        $format = ConfigHelper::getDateFormat();
        $rows   = [];

        foreach ($manualRows as $row) {
            $received = (string) ($row->received_at ?? '');
            $refund   = (string) $row->type === 'REVERSAL';

            $rows[] = [
                'id'   => (int) $row->j2commerce_ordertransaction_id,
                'kind' => $refund ? 'refund' : 'payment',
                'date' => $received !== '' && !str_starts_with($received, '0000')
                    ? (new Date($received))->format($format, false)
                    : (string) HTMLHelper::_('date', (string) $row->created_at, $format),
                'method'     => self::methodLabel((string) ($row->method ?? '')),
                'reference'  => (string) ($row->reference ?? ''),
                'note'       => (string) ($row->note ?? ''),
                'amount'     => ($refund ? '-' : '') . CurrencyHelper::format((float) $row->amount, $currency, 1.0),
                'by'         => (string) ($row->recorded_by ?? ''),
                'badgeClass' => J2htmlHelper::badgeClass('badge text-bg-secondary'),
            ];
        }

        return $rows;
    }

    /** Everything the order view re-renders after a recorded payment or refund. */
    public static function payload(object $order): array
    {
        $fig        = self::figures($order);
        $manual     = self::manualRows((int) $order->j2commerce_order_id);
        $currency   = $fig['currency'];
        $fmt        = static fn (float $value): string => CurrencyHelper::format($value, $currency, 1.0);
        $plain      = static fn (float $value): string => number_format($value, $fig['decimals'], '.', '');
        $refundable = self::manualRefundable($manual, $fig['decimals']);
        $type       = OrderStatusHelper::getType((int) ($order->order_state_id ?? 0));
        $paidInFull = $fig['balance'] <= $fig['threshold'];

        return [
            'total'            => $fmt($fig['total']),
            'paid'             => $fmt($fig['captured']),
            'hasRefunded'      => $fig['refunded'] > $fig['threshold'],
            'refunded'         => '-' . $fmt($fig['refunded']),
            'net'              => $fmt($fig['net']),
            'balance'          => $paidInFull ? Text::_('COM_J2COMMERCE_PAID_IN_FULL') : $fmt($fig['balance']),
            'paidInFull'       => $paidInFull,
            'canRecordPayment' => !$paidInFull
                && $fig['total'] > $fig['threshold']
                && !\in_array($type, [OrderStatusHelper::TYPE_CANCELLED, OrderStatusHelper::TYPE_REFUNDED], true),
            'canRecordRefund' => $refundable > $fig['threshold'],
            'amountDefault'   => $plain($fig['balance']),
            'refundDefault'   => $plain($refundable),
            'balanceHint'     => Text::sprintf('COM_J2COMMERCE_RECORD_PAYMENT_BALANCE_HINT', $fmt($fig['balance'])),
            'refundInfo'      => Text::sprintf('COM_J2COMMERCE_RECORD_REFUND_INFO', $fmt($refundable)),
            'payments'        => self::displayRows($manual, $currency),
        ];
    }

    /**
     * Paid badge shown next to the order status, only where it adds information; conflict flags a finalized status with nothing paid. label is always set because screen readers read it.
     *
     * @param   array{state: string, refunded: float, net?: float}|null  $summary  OrderPaymentHelper::summary() of this order; read here when null.
     */
    public static function indicator(object $order, ?array $summary = null): array
    {
        $summary  ??= OrderPaymentHelper::summary($order);
        $state      = $summary['state'];
        $statusId   = (int) ($order->order_state_id ?? 0);
        $currency   = (string) ($order->currency_code ?? '');
        $decimals   = CurrencyHelper::getDecimalPlace($currency);
        $threshold  = 10 ** -$decimals / 2;
        $rate       = (float) ($order->currency_value ?? 1.0);
        $total      = round((float) ($order->order_total ?? 0) * ($rate > 0 ? $rate : 1.0), $decimals);
        $secondary  = '';
        $deposit    = false;

        // A partial refund leaves the order partially paid; the ledger figure tells the two apart.
        $key   = $state === 'partially_paid' && $summary['refunded'] > $threshold ? 'partially_refunded' : $state;
        $label = Text::_(match ($key) {
            'paid'               => 'COM_J2COMMERCE_PAYMENT_STATE_PAID',
            'partially_paid'     => 'COM_J2COMMERCE_PAYMENT_STATE_PARTIALLY_PAID',
            'partially_refunded' => 'COM_J2COMMERCE_PAYMENT_STATE_PARTIALLY_REFUNDED',
            'authorized'         => 'COM_J2COMMERCE_PAYMENT_STATE_AUTHORIZED',
            'refunded'           => 'COM_J2COMMERCE_PAYMENT_STATE_REFUNDED',
            default              => 'COM_J2COMMERCE_PAYMENT_STATE_UNPAID',
        });

        if ($state === 'paid') {
            if ($total <= $threshold) {
                $secondary = Text::_('COM_J2COMMERCE_PAYMENT_STATE_NOTHING_OWED');
            } elseif (property_exists($order, 'order_params') && CurrencyHelper::gatewayAmount($order) < $total - $threshold) {
                // Paid, but the gateway was charged less than the total: a deposit order, so show Deposit Paid and the amount still due.
                $deposit   = true;
                $label     = Text::_('COM_J2COMMERCE_PAYMENT_STATE_DEPOSIT_PAID');
                $net       = $summary['net'] ?? OrderTransactionHelper::getNetPaid((int) $order->j2commerce_order_id);
                $secondary = Text::sprintf(
                    'COM_J2COMMERCE_PAYMENT_STATE_AMOUNT_DUE',
                    CurrencyHelper::format(max(0.0, $total - $net), $currency, 1.0)
                );
            }
        }

        $conflict = $state === 'unpaid' && OrderStatusHelper::isFinalized(OrderStatusHelper::getType($statusId));

        [$icon, $class] = self::STATE_STYLE[$key] ?? self::STATE_STYLE['unpaid'];

        return [
            'state'        => $key,
            'label'        => $label,
            'icon'         => $icon,
            'class'        => J2htmlHelper::badgeClass($class),
            'secondary'    => $secondary,
            'show'         => $deposit || \in_array($key, self::INFORMATIVE_STATES, true),
            'conflict'     => $conflict,
            'conflictText' => $conflict ? Text::sprintf('COM_J2COMMERCE_PAYMENT_CONFLICT_NOTICE', PaymentRuleHelper::statusLabel($statusId)) : '',
        ];
    }

    /** Server-wide MySQL named lock, scoped to this site's database and prefix; null on timeout. */
    public static function acquireLock(int $orderPk, int $waitSeconds = 5): ?string
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $scope = substr(md5(Factory::getApplication()->get('db') . '.' . $db->getPrefix()), 0, 8);
        $name  = 'j2c' . $scope . ':recordpayment:' . $orderPk;

        try {
            $query = $db->getQuery(true)
                ->select('GET_LOCK(:name, :wait)')
                ->bind(':name', $name)
                ->bind(':wait', $waitSeconds, ParameterType::INTEGER);

            return (int) $db->setQuery($query)->loadResult() === 1 ? $name : null;
        } catch (\Throwable $e) {
            Log::add('Record payment lock failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return null;
        }
    }

    public static function releaseLock(string $name): void
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)->select('RELEASE_LOCK(:name)')->bind(':name', $name);
            $db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
            Log::add('Record payment lock release failed: ' . $e->getMessage(), Log::WARNING, 'com_j2commerce');
        }
    }
}
