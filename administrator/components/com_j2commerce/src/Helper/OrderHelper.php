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

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

/**
 * Order helper class.
 *
 * Provides functionality for cart-to-order operations including
 * order population from cart items and calculations.
 *
 * @since  6.0.6
 */
class OrderHelper
{
    /**
     * Singleton instance.
     *
     * @var    OrderHelper|null
     * @since  6.0.6
     */
    protected static ?OrderHelper $instance = null;

    /**
     * Current cart order object.
     *
     * @var    CartOrder|null
     * @since  6.0.6
     */
    protected ?CartOrder $order = null;

    /**
     * Cart items.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $items = [];

    /**
     * Get singleton instance.
     *
     * @return  OrderHelper
     *
     * @since   6.0.6
     */
    public static function getInstance(): OrderHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Populate order from cart items.
     *
     * Creates a CartOrder object with calculated totals from cart items.
     *
     * @param   array  $items  Cart items array.
     *
     * @return  OrderHelper  Self for chaining.
     *
     * @since   6.0.6
     */
    public function populateOrder(array $items): OrderHelper
    {
        $this->items = $items;
        $this->order = new CartOrder($items);

        return $this;
    }

    /**
     * Get the populated order object.
     *
     * @return  CartOrder|null
     *
     * @since   6.0.6
     */
    public function getOrder(): ?CartOrder
    {
        return $this->order;
    }

    /**
     * Restore any order-item column a BeforeAddOrderItem handler left unusable.
     *
     * insertObject() skips properties that are unset, null, an array or an object, and every
     * orderitem_* column is NOT NULL without a default — so a skipped property drops the column
     * from the INSERT and the write fails. Handler substitutions that are scalars are kept.
     *
     * @param   object  $row       Row as it stands after the dispatch.
     * @param   array   $baseline  Row as it stood before the dispatch.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public static function normalizeOrderItemRow(object $row, array $baseline): void
    {
        foreach ($baseline as $column => $value) {
            if (!isset($row->$column) || \is_array($row->$column) || \is_object($row->$column)) {
                $row->$column = $value;
            }
        }
    }

    /**
     * The summary rows for a saved order, in the same order and under the same labels the live
     * cart shows through CartOrder::get_formatted_order_totals() — including the same answer to
     * `combine_tax_calculations`, so a shopper quoted a split tax at checkout is not shown a
     * combined one on the confirmation page.
     *
     * Amounts are returned as floats rather than formatted strings because a saved order carries
     * its own currency and exchange rate, which need not be the ones in force now.
     *
     * @param   object  $order  Row from #__j2commerce_orders.
     * @param   array   $parts  Related rows: shippings, taxes, fees, discounts.
     *
     * @return  array<int, array{key: string, type: string, label: string, amount: float, display: float}>  `display` is `amount` converted to the order currency, rows footed to the Total.
     *
     * @since   6.1.0
     */
    public static function getFormattedOrderTotals(object $order, array $parts = []): array
    {
        $shippings = $parts['shippings'] ?? [];
        $taxes     = $parts['taxes'] ?? [];
        $fees      = $parts['fees'] ?? [];
        $discounts = $parts['discounts'] ?? [];

        $params       = J2CommerceHelper::config();
        $combineTax   = (int) $params->get('combine_tax_calculations', 1);
        $priceDisplay = (int) $params->get('checkout_price_display_options', 0);
        $scale        = CurrencyHelper::getDecimalPlace((string) ($order->currency_code ?? ''));

        $itemTax     = (float) ($order->order_tax ?? 0);
        $shippingTax = (float) ($order->order_shipping_tax ?? 0);
        $isShippable = (int) ($order->is_shippable ?? 0) === 1;

        // A shipping with neither a price nor a name is an empty carrier slot (tracking only).
        $printable = array_values(array_filter(
            $shippings,
            static fn ($shipping): bool => (float) ($shipping->ordershipping_price ?? 0) > 0 || !empty($shipping->ordershipping_name)
        ));

        $rows = [];

        // Tax-inclusive stores carry the item tax inside order_subtotal — show the ex-tax figure
        // so the subtotal row and the tax rows below it do not present the same money twice.
        $rows[] = [
            'key'    => 'subtotal',
            'type'   => 'subtotal',
            'label'  => Text::_('COM_J2COMMERCE_CART_SUBTOTAL'),
            'amount' => (int) ($order->is_including_tax ?? 0) === 1
                ? (float) ($order->order_subtotal_ex_tax ?? ((float) ($order->order_subtotal ?? 0) - $itemTax))
                : (float) ($order->order_subtotal ?? 0),
        ];

        $feeTax = (float) array_sum(array_map(static fn ($fee): float => (float) ($fee->tax ?? 0), $fees));
        $tax    = self::buildTaxRows($taxes, $itemTax, $shippingTax, $feeTax, $combineTax, $priceDisplay, $scale);

        if ($isShippable || (float) ($order->order_shipping ?? 0) > 0 || !empty($printable)) {
            $shippingName = static fn (object $shipping): string => J2htmlHelper::translateKey(stripslashes((string) $shipping->ordershipping_name));
            $tracking     = static fn (array $list): string => implode("\n", array_filter(
                array_map(static fn ($shipping): string => (string) ($shipping->ordershipping_tracking_id ?? ''), $list)
            ));

            // One row per shipping only while their prices account for the whole charge.
            $pricedSum = array_sum(array_map(static fn ($shipping): float => (float) ($shipping->ordershipping_price ?? 0), $printable));

            if (\count($printable) > 1 && round($pricedSum, $scale) === round((float) ($order->order_shipping ?? 0), $scale)) {
                foreach ($printable as $index => $shipping) {
                    $rows[] = [
                        'key'      => 'shipping_' . $index,
                        'type'     => 'shipping',
                        'label'    => !empty($shipping->ordershipping_name) ? $shippingName($shipping) : Text::_('COM_J2COMMERCE_CART_SHIPPING'),
                        'amount'   => (float) ($shipping->ordershipping_price ?? 0),
                        'tracking' => $tracking([$shipping]),
                    ];
                }
            } else {
                $first = $printable[0] ?? null;

                $rows[] = [
                    'key'      => 'shipping',
                    'type'     => 'shipping',
                    'label'    => $first && !empty($first->ordershipping_name) ? $shippingName($first) : Text::_('COM_J2COMMERCE_CART_SHIPPING'),
                    'amount'   => (float) ($order->order_shipping ?? 0),
                    'tracking' => $tracking($shippings),
                ];
            }

            // Split tax display puts shipping tax directly under the shipping charge it belongs
            // to, the same place the cart shows it.
            if ($tax['shipping'] > 0) {
                $rows[] = [
                    'key'    => 'shipping_tax',
                    'type'   => 'tax',
                    'label'  => Text::_('COM_J2COMMERCE_ORDER_SHIPPING_TAX'),
                    'amount' => $tax['shipping'],
                ];
            }
        }
        // The fee rows and order_surcharge are the same money recorded two ways — rows win where
        // they exist, the column is the only record where they do not.
        //
        // The fee's own amount is shown bare, same as shipping's: its tax is not baked in here
        // because CartOrder::buildDisplayTaxRates() already folds it into the matching tax
        // profile row in $taxes (persisted to #__j2commerce_ordertaxes the same way shipping tax
        // is), so it already reads as part of the combined tax total below.
        if (!empty($fees)) {
            foreach ($fees as $index => $fee) {
                $amount = (float) ($fee->amount ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                $rows[] = [
                    'key'    => 'fee_' . $index,
                    'type'   => 'fee',
                    'label'  => J2htmlHelper::translateKey($fee->name ?: 'COM_J2COMMERCE_CART_SURCHARGE'),
                    'amount' => $amount,
                ];
            }
        } elseif ((float) ($order->order_surcharge ?? 0) > 0) {
            $rows[] = [
                'key'    => 'surcharge',
                'type'   => 'fee',
                'label'  => Text::_('COM_J2COMMERCE_CART_SURCHARGE'),
                'amount' => (float) $order->order_surcharge,
            ];
        }

        if (!empty($discounts)) {
            foreach ($discounts as $index => $discount) {
                $amount = (float) ($discount->discount_amount ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                $rows[] = [
                    'key'   => 'discount_' . $index,
                    'type'  => 'discount',
                    'label' => (string) ($discount->discount_title
                        ?: ($discount->discount_code ?: Text::_('COM_J2COMMERCE_CART_DISCOUNT'))),
                    'amount' => $amount,
                ];
            }
        } elseif ((float) ($order->order_discount ?? 0) > 0) {
            $rows[] = [
                'key'    => 'discount',
                'type'   => 'discount',
                'label'  => Text::_('COM_J2COMMERCE_CART_DISCOUNT'),
                'amount' => (float) $order->order_discount,
            ];
        }

        // recalculateOrderTotals() subtracts order_credit from order_total, so it prints as a deduction.
        if ((float) ($order->order_credit ?? 0) > 0) {
            $rows[] = [
                'key'    => 'credit',
                'type'   => 'discount',
                'label'  => Text::_('COM_J2COMMERCE_CART_CREDIT'),
                'amount' => (float) $order->order_credit,
            ];
        }

        foreach ($tax['rows'] as $index => $taxRow) {
            $taxRow['key'] = 'tax_' . $index;
            $rows[]        = $taxRow;
        }

        $rows[] = [
            'key'    => 'grandtotal',
            'type'   => 'grandtotal',
            'label'  => Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'),
            'amount' => (float) ($order->order_total ?? 0),
        ];

        $rows = self::reconcileTotalRows($rows, $scale);

        // 'amount' stays in the base currency; 'display' is the order-currency figure, footed so
        // the printed rows add up to the printed Total.
        $rate     = (float) ($order->currency_value ?? 0);
        $rate     = $rate > 0 ? $rate : (CurrencyHelper::getValue((string) ($order->currency_code ?? '')) ?: 1.0);
        $declared = (float) ($order->order_total ?? 0);
        $bodyKeys = [];

        foreach ($rows as $index => $row) {
            if ($row['type'] === 'grandtotal') {
                $declared = $row['amount'];
            } else {
                $bodyKeys[] = $index;
            }
        }

        $footed = CurrencyHelper::footConverted(
            array_map(static fn (int $index): float => $rows[$index]['type'] === 'discount' ? -$rows[$index]['amount'] : $rows[$index]['amount'], $bodyKeys),
            $declared,
            $rate,
            $scale
        );

        foreach ($rows as $index => &$row) {
            $position       = array_search($index, $bodyKeys, true);
            $row['display'] = $position === false ? round($declared * $rate, $scale) : abs($footed[$position]);
        }

        unset($row);

        return $rows;
    }

    /**
     * Tax rows for a saved order. The persisted #__j2commerce_ordertaxes rows are written to
     * match `combine_tax_calculations` as it stood when the order was placed, so the setting
     * alone cannot be trusted for an older order — whether those rows already carry the
     * shipping and fee tax is read from the figures themselves.
     *
     * @return  array{rows: array<int, array{type: string, label: string, amount: float}>, shipping: float}
     *          `shipping` is the figure owed its own line under the shipping charge, and is 0.0
     *          whenever the profile rows already account for it.
     *
     * @since   6.1.0
     */
    private static function buildTaxRows(
        array $taxes,
        float $itemTax,
        float $shippingTax,
        float $feeTax,
        int $combineTax,
        int $priceDisplay,
        int $scale
    ): array {
        $rows       = [];
        $profileSum = 0.0;

        foreach ($taxes as $tax) {
            $amount = (float) ($tax->ordertax_amount ?? 0);
            $profileSum += $amount;

            if ($amount <= 0) {
                continue;
            }

            $title   = $tax->ordertax_title ?: Text::_('COM_J2COMMERCE_CART_TAX');
            $percent = (float) ($tax->ordertax_percent ?? 0);

            $rows[] = [
                'type'  => 'tax',
                'label' => $percent > 0
                    ? Text::sprintf(
                        $priceDisplay
                            ? 'COM_J2COMMERCE_CART_TAX_INCLUDED_TITLE'
                            : 'COM_J2COMMERCE_CART_TAX_EXCLUDED_TITLE',
                        J2htmlHelper::translateKey($title),
                        $percent . '%'
                    )
                    : J2htmlHelper::translateKey($title),
                'amount' => $amount,
            ];
        }

        // No itemised rows at all — one line carrying whatever tax the order holds.
        if (empty($rows)) {
            $combined = round($itemTax + $feeTax + ($combineTax ? $shippingTax : 0.0), $scale);
            $split    = $combineTax ? 0.0 : round($shippingTax, $scale);

            return [
                'rows' => $combined > 0
                    ? [['type' => 'tax', 'label' => Text::_('COM_J2COMMERCE_CART_TAX'), 'amount' => $combined]]
                    : [],
                'shipping' => max(0.0, $split),
            ];
        }

        // A fee's tax is always merged into the rows (CartOrder::buildDisplayTaxRates()); shipping tax
        // only under combine_tax_calculations — and older orders were written either way. Read which
        // of them the rows carry from the figures, using the flag only to break a tie.
        $shipCarried = false;
        $feeCarried  = false;
        $matched     = false;
        $bestScore   = -1;

        foreach ([[false, false], [false, true], [true, false], [true, true]] as [$withShip, $withFee]) {
            if (($withShip && $shippingTax <= 0) || ($withFee && $feeTax <= 0)) {
                continue;
            }

            $expected = round($itemTax + ($withShip ? $shippingTax : 0.0) + ($withFee ? $feeTax : 0.0), $scale);

            if (round($profileSum, $scale) !== $expected) {
                continue;
            }

            // Prefer the reading that matches the convention: fee tax always merged, shipping tax
            // merged only when combining.
            $score = ($withFee === ($feeTax > 0) ? 2 : 0) + ($withShip === (bool) $combineTax ? 1 : 0);

            if ($score > $bestScore) {
                [$shipCarried, $feeCarried, $matched, $bestScore] = [$withShip, $withFee, true, $score];
            }
        }

        if (!$matched) {
            $feeCarried  = $feeTax > 0 && round($profileSum, $scale) > round($itemTax, $scale);
            $shipCarried = $shippingTax > 0
                && round($profileSum, $scale) > round($itemTax + ($feeCarried ? $feeTax : 0.0), $scale);
        }

        $feeRemainder  = $feeCarried ? 0.0 : round($feeTax, $scale);
        $shipRemainder = $shipCarried ? 0.0 : round($shippingTax, $scale);

        // A fee has no line of its own, so its tax joins the tax block; shipping tax does only
        // when combining.
        if ($feeRemainder > 0 || ($combineTax && $shipRemainder > 0)) {
            $rows[] = [
                'type'   => 'tax',
                'label'  => Text::_('COM_J2COMMERCE_CART_TAX'),
                'amount' => $feeRemainder + ($combineTax ? $shipRemainder : 0.0),
            ];
        }

        return ['rows' => $rows, 'shipping' => $combineTax ? 0.0 : max(0.0, $shipRemainder)];
    }

    /**
     * Last line of defence for the summary block: the rows printed must foot to order_total.
     * Where they do not — a legacy order, or a plugin that recorded its money somewhere these
     * rows do not read — the tax rows are replaced by a single derived figure rather than
     * showing a customer a block that does not add up.
     *
     * @param   array<int, array{key: string, type: string, label: string, amount: float}>  $rows
     *
     * @return  array<int, array{key: string, type: string, label: string, amount: float}>
     *
     * @since   6.1.0
     */
    private static function reconcileTotalRows(array $rows, int $scale): array
    {
        $signed   = static fn (array $row): float => $row['type'] === 'discount' ? -$row['amount'] : $row['amount'];
        $body     = array_filter($rows, static fn (array $row): bool => $row['type'] !== 'grandtotal');
        $declared = 0.0;

        foreach ($rows as $row) {
            if ($row['type'] === 'grandtotal') {
                $declared = $row['amount'];
            }
        }

        $printed = array_sum(array_map($signed, $body));

        if (round($printed, $scale) === round($declared, $scale)) {
            return $rows;
        }

        $nonTax     = array_sum(array_map($signed, array_filter($body, static fn (array $row): bool => $row['type'] !== 'tax')));
        $derivedTax = round($declared - $nonTax, $scale);
        $rebuilt    = array_values(array_filter($body, static fn (array $row): bool => $row['type'] !== 'tax'));

        if ($derivedTax > 0) {
            $rebuilt[] = [
                'key'    => 'tax_0',
                'type'   => 'tax',
                'label'  => Text::_('COM_J2COMMERCE_CART_TAX'),
                'amount' => $derivedTax,
            ];
        }

        $rebuilt[] = [
            'key'    => 'grandtotal',
            'type'   => 'grandtotal',
            'label'  => Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'),
            'amount' => $declared,
        ];

        return $rebuilt;
    }
}
