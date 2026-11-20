<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Context;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\OrderModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Read-only checkout display of an order that already exists.
 *
 * A checkout context hands core a persisted order row, while the checkout templates
 * read the CartOrder interface (items, taxes, formatted totals and line prices) that
 * only a cart-built order has. This adapter answers that interface from the stored
 * order tables and proxies every other property to the row. It is for rendering only:
 * finalisation, payment events and saves keep working on the row itself (unwrap()).
 */
final class StoredOrderDisplay
{
    private ?array $items      = null;
    private ?OrderModel $model = null;

    public function __construct(private readonly object $order)
    {
    }

    public function unwrap(): object
    {
        return $this->order;
    }

    public function __get(string $name): mixed
    {
        return $this->order->$name ?? null;
    }

    /** Writes go to the wrapped row, so a plugin that stamps a property on the order changes the order. */
    public function __set(string $name, mixed $value): void
    {
        $this->order->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->order->$name);
    }

    public function currency(): OrderCurrency
    {
        return new OrderCurrency((string) $this->order->currency_code, (float) $this->order->currency_value);
    }

    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $items = $this->model()->getOrderItems((string) $this->order->order_id);

        // Cart-item names the checkout templates also read.
        foreach ($items as $item) {
            $item->product_qty     = (float) ($item->orderitem_quantity ?? 1);
            $item->product_row_id  = (int) ($item->product_id ?? 0);
            $item->product_enabled = 1;
        }

        return $this->items = $items;
    }

    public function getOrderTaxrates(): array
    {
        return array_map(
            static fn (object $tax): object => (object) [
                'taxprofile_name' => (string) $tax->ordertax_title,
                'taxrate_name'    => (string) $tax->ordertax_title,
                'tax_percent'     => (float) $tax->ordertax_percent,
                'tax_amount'      => (float) $tax->ordertax_amount,
            ],
            $this->model()->getOrderTaxes((string) $this->order->order_id)
        );
    }

    public function getOrderShippingRate(): ?object
    {
        return $this->model()->getOrderShipping((string) $this->order->order_id);
    }

    public function get_formatted_lineitem_price(object $item, int $displayMode = 0): float
    {
        $unit    = (float) ($item->orderitem_price ?? 0) + (float) ($item->orderitem_option_price ?? 0);
        $taxEach = (float) ($item->orderitem_per_item_tax ?? 0);
        $stored  = (int) ($this->order->is_including_tax ?? 0) === 1;

        return match (true) {
            $displayMode === 1 && !$stored => $unit + $taxEach,
            $displayMode === 0 && $stored  => $unit - $taxEach,
            default                        => $unit,
        };
    }

    public function get_formatted_lineitem_total(object $item, int $displayMode = 0): float
    {
        return $this->get_formatted_lineitem_price($item, $displayMode) * (float) ($item->orderitem_quantity ?? 1);
    }

    public function get_lineitem_discount_info(object $item, int $displayMode = 0): ?object
    {
        $discount = (float) ($item->orderitem_discount ?? 0);

        if ($discount <= 0) {
            return null;
        }

        $original = $this->get_formatted_lineitem_total($item, $displayMode);
        $final    = $original - $discount - ($displayMode === 1 ? (float) ($item->orderitem_discount_tax ?? 0) : 0.0);

        return (object) [
            'original_price'  => $original,
            'discount_amount' => $discount,
            'discount_tax'    => (float) ($item->orderitem_discount_tax ?? 0),
            'final_price'     => max(0.0, $final),
        ];
    }

    /** @return array<string, array{label: string, value: string}> Same rows, keys and plugin event as CartOrder::get_formatted_order_totals(), read from the stored order. */
    public function get_formatted_order_totals(): array
    {
        $params     = J2CommerceHelper::config();
        $currency   = $this->currency();
        $display    = (int) $params->get('checkout_price_display_options', 0);
        $combineTax = (int) $params->get('combine_tax_calculations', 1);
        $feeDisplay = (int) ($this->order->is_including_tax ?? 0) === 1 ? 1 : $display;
        $orderId    = (string) $this->order->order_id;
        $items      = $this->getItems();
        $fees       = $this->model()->getOrderFees($orderId);
        $taxRates   = $this->getOrderTaxrates();
        $totals     = [];

        // The stored tax rows are what is shown, so a tax they already carry must not be listed a second time,
        // and a fee's tax that no row carries is shown with the fee, or the rows would not add up to the total.
        $rowsTax   = array_sum(array_column($taxRates, 'tax_amount'));
        $shipTax   = (float) $this->order->order_shipping_tax;
        $feeTax    = array_sum(array_map(static fn (object $fee): float => (float) $fee->tax, $fees));
        $shipInRow = $shipTax > 0 && $rowsTax >= (float) $this->order->order_tax + $shipTax - 0.005;
        $feeInRow  = $feeTax > 0 && $rowsTax >= (float) $this->order->order_tax + ($shipInRow ? $shipTax : 0.0) + $feeTax - 0.005;

        $subtotal = (float) $this->order->order_subtotal;

        if ($display && $items !== []) {
            $subtotal = array_reduce(
                $items,
                fn (float $sum, object $item): float => $sum + $this->get_formatted_lineitem_total($item, $display),
                0.0
            );
        }

        $totals['subtotal'] = ['label' => Text::_('COM_J2COMMERCE_CART_SUBTOTAL'), 'value' => $currency->format($subtotal)];

        $shipping = $this->getOrderShippingRate();

        if ((float) $this->order->order_shipping > 0 || $shipping !== null) {
            $label = ($shipping->ordershipping_name ?? '') !== ''
                ? J2htmlHelper::translateKey(stripslashes((string) $shipping->ordershipping_name))
                : Text::_('COM_J2COMMERCE_CART_SHIPPING');

            $totals['shipping'] = ['label' => $label, 'value' => $currency->format((float) $this->order->order_shipping)];

            if ($shipTax > 0 && !$combineTax && !$shipInRow) {
                $totals['shipping_tax'] = [
                    'label' => Text::_('COM_J2COMMERCE_ORDER_SHIPPING_TAX'),
                    'value' => $currency->format($shipTax),
                ];
            }
        }

        // A taxable fee is stored net; with inclusive pricing the rows above are gross, so the fee is shown gross too.
        foreach ($fees as $fee) {
            $amount = (float) $fee->amount + ($feeDisplay === 1 || ($feeTax > 0 && !$feeInRow) ? (float) $fee->tax : 0.0);

            $totals['fee_' . preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($fee->name ?: 'custom')))] = [
                'label' => J2htmlHelper::translateKey((string) $fee->name),
                'value' => $currency->format($amount),
            ];
        }

        if ((float) $this->order->order_surcharge > 0 && $fees === []) {
            $totals['surcharge'] = [
                'label' => Text::_('COM_J2COMMERCE_CART_SURCHARGE'),
                'value' => $currency->format((float) $this->order->order_surcharge),
            ];
        }

        foreach ($this->model()->getOrderDiscounts($orderId) as $discount) {
            if ((float) $discount->discount_amount > 0) {
                $totals[$discount->discount_type . '_' . preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($discount->discount_code ?: 'discount')))] = [
                    'label' => (string) ($discount->discount_title ?: $discount->discount_code),
                    'value' => '-' . $currency->format((float) $discount->discount_amount),
                ];
            }
        }

        foreach ($taxRates as $key => $tax) {
            if ($tax->tax_amount > 0) {
                $title = trim($tax->taxprofile_name) !== '' ? $tax->taxprofile_name : Text::_('COM_J2COMMERCE_CART_TAX');

                $totals['tax_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($title)) . '_' . $key] = [
                    'label' => Text::sprintf(
                        $display ? 'COM_J2COMMERCE_CART_TAX_INCLUDED_TITLE' : 'COM_J2COMMERCE_CART_TAX_EXCLUDED_TITLE',
                        J2htmlHelper::translateKey($title),
                        $tax->tax_percent . '%'
                    ),
                    'value' => $currency->format($tax->tax_amount),
                ];
            }
        }

        $totals['grandtotal'] = ['label' => Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'), 'value' => $currency->format((float) $this->order->order_total)];

        J2CommerceHelper::plugin()->event('GetFormattedOrderTotals', [$this, &$totals]);

        return $totals;
    }

    private function model(): OrderModel
    {
        return $this->model ??= Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()
            ->createModel('Order', 'Administrator', ['ignore_request' => true]);
    }
}
