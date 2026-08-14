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
     * @return  array<int, array{key: string, type: string, label: string, amount: float}>
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

        $tax = self::buildTaxRows($taxes, $itemTax, $shippingTax, $combineTax, $priceDisplay, $scale);

        if ($isShippable) {
            $shippingLabel = Text::_('COM_J2COMMERCE_CART_SHIPPING');
            $firstShipping = !empty($shippings) ? reset($shippings) : null;

            if ($firstShipping && !empty($firstShipping->ordershipping_name)) {
                $shippingLabel = Text::_(stripslashes($firstShipping->ordershipping_name));
            }

            $rows[] = [
                'key'    => 'shipping',
                'type'   => 'shipping',
                'label'  => $shippingLabel,
                'amount' => (float) ($order->order_shipping ?? 0),
            ];

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
        if (!empty($fees)) {
            foreach ($fees as $index => $fee) {
                $amount = (float) ($fee->amount ?? 0);

                if ($amount <= 0) {
                    continue;
                }

                $rows[] = [
                    'key'    => 'fee_' . $index,
                    'type'   => 'fee',
                    'label'  => Text::_($fee->name ?: 'COM_J2COMMERCE_CART_SURCHARGE'),
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

        return self::reconcileTotalRows($rows, $scale);
    }

    /**
     * Tax rows for a saved order. The persisted #__j2commerce_ordertaxes rows are written to
     * match `combine_tax_calculations` as it stood when the order was placed, so the setting
     * alone cannot be trusted for an older order — whether those rows already carry the
     * shipping tax is read from the figures themselves.
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
                        Text::_($title),
                        $percent . '%'
                    )
                    : Text::_($title),
                'amount' => $amount,
            ];
        }

        // No itemised rows at all — one line carrying whatever tax the order holds.
        if (empty($rows)) {
            $combined = round($itemTax + ($combineTax ? $shippingTax : 0.0), $scale);
            $split    = $combineTax ? 0.0 : round($shippingTax, $scale);

            return [
                'rows' => $combined > 0
                    ? [['type' => 'tax', 'label' => Text::_('COM_J2COMMERCE_CART_TAX'), 'amount' => $combined]]
                    : [],
                'shipping' => max(0.0, $split),
            ];
        }

        // Rows summing past the item tax already have the shipping tax folded into them, whatever
        // the setting says now; showing it again would present the same money twice.
        $carriesShippingTax = round($profileSum, $scale) > round($itemTax, $scale);
        $remainder          = round($itemTax + $shippingTax - $profileSum, $scale);

        if ($shippingTax <= 0 || $carriesShippingTax || $remainder <= 0) {
            return ['rows' => $rows, 'shipping' => 0.0];
        }

        // Combining, but the rows were written split (or by an engine that itemised only the
        // product tax) — the shipping share joins the tax block rather than standing apart.
        if ($combineTax) {
            $rows[] = [
                'type'   => 'tax',
                'label'  => Text::_('COM_J2COMMERCE_CART_TAX'),
                'amount' => $remainder,
            ];

            return ['rows' => $rows, 'shipping' => 0.0];
        }

        return ['rows' => $rows, 'shipping' => $remainder];
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
