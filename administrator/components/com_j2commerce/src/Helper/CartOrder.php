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

use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;

/**
 * Cart Order value object.
 *
 * Represents the current cart as an order for calculation and display purposes.
 * This is not a saved order but a temporary object for cart view rendering.
 *
 * @since  6.0.6
 */
class CartOrder
{
    /**
     * Cart items with calculated data.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $items = [];

    /**
     * Tax rates data.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $taxRates = [];

    /**
     * Shipping rate data.
     *
     * @var    object|null
     * @since  6.0.6
     */
    protected ?object $shippingRate = null;

    /**
     * Applied coupons.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $coupons = [];

    /**
     * Applied vouchers.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $vouchers = [];

    /**
     * Order subtotal.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_subtotal = 0.0;

    /**
     * Order tax.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_tax = 0.0;

    /**
     * Order shipping.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_shipping = 0.0;

    /**
     * Order shipping tax.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_shipping_tax = 0.0;

    /**
     * Order discount.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_discount = 0.0;

    /**
     * Order discount tax (for tax-inclusive pricing).
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_discount_tax = 0.0;

    /**
     * Cumulative cart discount from plugins (bulk discounts, etc.).
     *
     * @var    float
     * @since  6.0.6
     */
    public float $discount_cart = 0.0;

    /**
     * Cumulative cart discount tax from plugins.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $discount_cart_tax = 0.0;

    /**
     * Coupon/voucher discount amounts by code.
     *
     * @var    array
     * @since  6.0.6
     */
    public array $coupon_discount_amounts = [];

    /**
     * Coupon/voucher discount tax amounts by code.
     *
     * @var    array
     * @since  6.0.6
     */
    public array $coupon_discount_tax_amounts = [];

    /**
     * Order surcharge.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_surcharge = 0.0;

    /**
     * Order total.
     *
     * @var    float
     * @since  6.0.6
     */
    public float $order_total = 0.0;

    /**
     * Database order ID (string, e.g. "20260206-ABCDEF12").
     *
     * @var    string
     * @since  6.0.6
     */
    public string $order_id = '';

    /**
     * Database auto-increment primary key.
     *
     * @var    int
     * @since  6.0.6
     */
    public int $j2commerce_order_id = 0;

    /**
     * Order security token.
     *
     * @var    string
     * @since  6.0.6
     */
    public string $token = '';

    /**
     * Payment method element name.
     *
     * @var    string
     * @since  6.0.6
     */
    public string $orderpayment_type = '';

    /**
     * User email for the order.
     *
     * @var    string
     * @since  6.0.6
     */
    public string $user_email = '';

    /**
     * User ID for the order.
     *
     * @var    int
     * @since  6.0.6
     */
    public int $user_id = 0;

    /**
     * Cart ID associated with this order.
     *
     * @var    int
     * @since  6.0.6
     */
    public int $cart_id = 0;

    /**
     * Customer note.
     *
     * @var    string
     * @since  6.0.6
     */
    public string $customer_note = '';

    /**
     * Resolved customer country_id (set by getCustomerGeozones).
     *
     * @var    int
     * @since  6.0.7
     */
    protected int $customerCountryId = 0;

    /**
     * Resolved customer zone_id (set by getCustomerGeozones).
     *
     * @var    int
     * @since  6.0.7
     */
    protected int $customerZoneId = 0;

    /**
     * Resolved customer postcode (set by getCustomerGeozones).
     *
     * @var    string
     * @since  6.0.7
     */
    protected string $customerPostcode = '';

    /**
     * Constructor.
     *
     * @param   array  $items  Cart items.
     *
     * @since   6.0.6
     */
    public function __construct(array $items)
    {
        // Transform cart items to order item format
        $this->items = $this->transformCartItems($items);
        $this->calculateTotals();
        $this->loadCoupons();
        $this->loadVouchers();
        $this->calculateDiscountTotals();
        $this->loadShipping();
        $this->loadFees();
        $this->quantizeTotals();
    }

    /**
     * Transform cart items to order item format.
     *
     * Maps cart item properties to order item properties for template compatibility.
     *
     * @param   array  $cartItems  Cart items from CartItemsModel.
     *
     * @return  array  Items with order item property names.
     *
     * @since   6.0.6
     */
    protected function transformCartItems(array $cartItems): array
    {
        $transformedItems = [];

        foreach ($cartItems as $item) {
            // Map cart item properties to order item properties
            $item->cartitem_id             = $item->j2commerce_cartitem_id ?? 0;
            $item->orderitem_name          = $item->product_name ?? '';
            $item->orderitem_sku           = $item->sku ?? '';
            $item->orderitem_quantity      = $item->product_qty ?? 1;
            $item->orderitem_params        = $item->cartitem_params ?? '{}';
            $item->orderitem_price         = $item->pricing->price ?? $item->variant_price ?? 0;
            $item->orderitem_option_price  = $item->option_price ?? 0;
            $item->orderitem_tax           = $item->taxes->taxtotal ?? 0;
            $item->orderitem_taxprofile_id = $item->taxprofile_id ?? 0;

            // Calculate product subtotal if not already set
            if (!isset($item->product_subtotal)) {
                $basePrice              = (float) ($item->pricing->price ?? $item->variant_price ?? 0);
                $optionPrice            = (float) ($item->option_price ?? 0);
                $quantity               = (float) ($item->product_qty ?? 1);
                $item->product_subtotal = ($basePrice + $optionPrice) * $quantity;
            }

            $transformedItems[] = $item;
        }

        return $transformedItems;
    }

    /**
     * Get order items with attributes.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    public function getItems(): array
    {
        // Process items to add orderitemattributes
        foreach ($this->items as &$item) {
            if (!isset($item->orderitemattributes)) {
                $item->orderitemattributes = $this->getItemAttributes($item);
            }
        }

        return $this->items;
    }

    /**
     * Get order tax rates.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    public function getOrderTaxrates(): array
    {
        return $this->taxRates;
    }

    /**
     * Get order shipping rate.
     *
     * @return  object|null
     *
     * @since   6.0.6
     */
    public function getOrderShippingRate(): ?object
    {
        return $this->shippingRate;
    }

    /**
     * Get order coupons.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    public function getOrderCoupons(): array
    {
        return $this->coupons;
    }

    /**
     * Get order vouchers.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    public function getOrderVouchers(): array
    {
        return $this->vouchers;
    }

    /**
     * Re-check stock and the per-customer sale limits for every managed line at order-build time.
     *
     * Add-to-cart and quantity-update enforce both rules, but time passes before confirm,
     * so without this two shoppers can both buy the last unit, and any path that reaches
     * confirm without going back through a `Cart*` behavior leaves the limits unasserted.
     * Quantities are summed per variant first: two lines of the same variant with different
     * options must not each be measured against the full stock, and together they are what
     * this shopper is asking to buy. Authority is `ProductHelper::checkStockStatus()` and
     * `ProductHelper::validateQuantityRestriction()` — the same helpers the cart behaviors
     * use, so backorder-enabled variants stay purchasable. A load or query failure is logged
     * and the line skipped — deliberate, so an infrastructure error cannot block every
     * shopper at checkout.
     *
     * A line whose stock is carried elsewhere — a composite parent whose components hold it —
     * reports available on its own variant and passes the checks above unexamined, so
     * `ValidateOrderStock` is dispatched for every line and a listener that owns one writes
     * its own message onto `$item->stock_error`, which `getStockErrors()` then reports
     * alongside core's findings.
     *
     * @return  bool  True when every managed line still has enough stock and is within its limits.
     *
     * @since   6.0.6
     */
    public function validate_order_stock(): bool
    {
        $wanted = [];

        foreach ($this->items as $item) {
            // Stale messages from a previous run would otherwise be re-reported by
            // getStockErrors() even once the shortfall is resolved.
            unset($item->stock_error);

            if (!empty($item->variant_id)) {
                $variantId            = (int) $item->variant_id;
                $wanted[$variantId] ??= 0;
                $wanted[$variantId] += (int) ($item->product_qty ?? 1);
            }
        }

        $short   = [];
        $limited = [];

        foreach ($wanted as $variantId => $quantity) {
            try {
                $variantTable = Factory::getApplication()
                    ->bootComponent('com_j2commerce')
                    ->getMVCFactory()
                    ->createTable('Variant', 'Administrator');

                if (!$variantTable || !$variantTable->load($variantId)) {
                    continue;
                }

                $variant = (object) $variantTable->getProperties();
                // The variants table carries no quantity column — stock lives in
                // #__j2commerce_productquantities, and validateStock() reads
                // $variant->quantity.
                $variant->quantity = ProductHelper::getStockQuantity($variantId);

                if (!ProductHelper::checkStockStatus($variant, $quantity)) {
                    $short[$variantId] = (int) $variant->quantity;
                    continue;
                }

                $limitError = ProductHelper::validateQuantityRestriction($variant, (float) $quantity);

                if ($limitError !== '') {
                    $limited[$variantId] = $limitError;
                }
            } catch (\Exception $e) {
                Log::add(
                    'Stock re-validation skipped for variant ' . $variantId . ': ' . $e->getMessage(),
                    Log::WARNING,
                    'com_j2commerce'
                );
            }
        }

        $delegated = false;

        foreach ($this->items as $item) {
            try {
                // Dispatched for every line, not only managed ones: the parent a listener
                // speaks for deliberately leaves manage-stock off, so gating on that would
                // hide exactly the lines this exists for. Caught for the same reason the
                // checks above are: this runs on cart and checkout renders as well as at
                // confirm, so a listener throwing on a transient fault must cost one line
                // its check rather than take every shopper's cart down with it.
                $event = J2CommerceHelper::plugin()->event('ValidateOrderStock', [$item, $this]);

                // Listeners written against the legacy contract refuse by argument rather
                // than by message. Honour that refusal, and give the line a message of its
                // own so the verdict and what the shopper is told cannot come apart.
                if ($event->getArgument('result') === false && empty($item->stock_error)) {
                    $item->stock_error = Text::sprintf(
                        'COM_J2COMMERCE_CART_ITEM_STOCK_NOT_AVAILABLE',
                        $item->product_name ?? ''
                    );
                }
            } catch (\Throwable $e) {
                Log::add(
                    'ValidateOrderStock listener failed for product ' . ($item->product_id ?? 0) . ': ' . $e->getMessage(),
                    Log::WARNING,
                    'com_j2commerce'
                );
            }

            if (!empty($item->stock_error)) {
                $delegated = true;
            }
        }

        if ($short === [] && $limited === [] && !$delegated) {
            return true;
        }

        foreach ($this->items as $item) {
            $variantId = (int) ($item->variant_id ?? 0);

            if (isset($short[$variantId])) {
                $item->stock_error = Text::sprintf(
                    'COM_J2COMMERCE_CART_ITEM_STOCK_NOT_ENOUGH_STOCK',
                    $item->product_name ?? '',
                    $short[$variantId]
                );

                continue;
            }

            if (isset($limited[$variantId])) {
                $item->stock_error = $limited[$variantId];
            }
        }

        return false;
    }

    /**
     * Collected per-line stock messages from the last validate_order_stock() run.
     *
     * @return  string[]
     *
     * @since   6.3.0
     */
    public function getStockErrors(): array
    {
        $errors = [];

        foreach ($this->items as $item) {
            if (!empty($item->stock_error)) {
                $errors[] = (string) $item->stock_error;
            }
        }

        return $errors;
    }

    /**
     * Calculate order totals.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function calculateTotals(): void
    {
        $subtotal       = 0.0;
        $taxTotal       = 0.0;
        $taxRates       = [];
        $isIncludingTax = (int) J2CommerceHelper::config()->get('config_including_tax', 0);

        // Resolve customer geozones once for all items
        $customerGeozones = $this->getCustomerGeozones();

        foreach ($this->items as $item) {
            $pricing  = $item->pricing ?? null;
            $quantity = (float) ($item->product_qty ?? 1);

            // Initialize discount tracking on item
            if (!isset($item->orderitem_discount)) {
                $item->orderitem_discount = 0.0;
            }
            if (!isset($item->orderitem_discount_tax)) {
                $item->orderitem_discount_tax = 0.0;
            }

            // A product type with no cart behaviour of its own reaches here with no pricing
            // object, so the stored variant price stands in for it. That line is charged like
            // any other, so it is taxed like any other: the tax below runs off taxprofile_id,
            // which the cart-items query carries for every line whatever its type.
            $itemPrice = $pricing
                ? (float) ($pricing->price ?? 0)
                : (float) ($item->price ?? $item->variant_price ?? 0);

            $itemPrice += (float) ($item->option_price ?? 0);

            // Allow plugins to modify item price and add discounts
            // onJ2CommerceGetDiscountedPrice signature: (&$price, &$item, $add_totals, &$order)
            // Plugins can modify $price by reference and set $item->orderitem_discount
            J2CommerceHelper::plugin()->event('GetDiscountedPrice', [
                &$itemPrice,
                &$item,
                true, // $add_totals - accumulate discount totals
                $this, // $order - the CartOrder object
            ]);

            // The price the subtotal is built from, after any plugin reduced it. saveOrderItems()
            // reads it back so the persisted line cannot silently revert to the undiscounted
            // pricing->price and leave SUM(orderitem_finalprice) above order_subtotal.
            $item->orderitem_effective_price = $itemPrice;

            $subtotal += $itemPrice * $quantity;
            $taxTotal += $this->applyLineTax($item, $itemPrice * $quantity, $customerGeozones, (bool) $isIncludingTax, $taxRates);
        }

        $this->order_subtotal = $subtotal;
        $this->order_tax      = $taxTotal;
        $this->taxRates       = array_values($taxRates);
        // When prices are stored inclusive of tax the subtotal already contains the tax;
        // adding taxTotal again would double-count it.
        $this->order_total = $isIncludingTax ? $subtotal : $subtotal + $taxTotal;
    }

    /**
     * Resolve one line's tax, accumulate it into the per-rate rows and return its total.
     *
     * @param   object  $item              The cart line (orderitem_tax and orderitem_tax_percent are set on it).
     * @param   float   $lineTotal         The line's charged amount, quantity included.
     * @param   array   $customerGeozones  Geozones resolved once for the whole order.
     * @param   bool    $isIncludingTax    Whether stored prices already contain the tax.
     * @param   array   $taxRates          Per-rate rows, keyed by rate name and id, accumulated by reference.
     *
     * @return  float  The tax owed on this line.
     *
     * @since   6.5.2
     */
    private function applyLineTax(
        object $item,
        float $lineTotal,
        array $customerGeozones,
        bool $isIncludingTax,
        array &$taxRates
    ): float {
        $taxprofileId = (int) ($item->taxprofile_id ?? 0);

        $ratesets = $taxprofileId > 0 && !empty($customerGeozones)
            ? $this->getTaxRatesForProfile($taxprofileId, $customerGeozones)
            : [];

        if (empty($ratesets)) {
            $item->orderitem_tax         = 0.0;
            $item->orderitem_tax_percent = 0.0;

            return 0.0;
        }

        // Sum total rate percent for inclusive extraction
        $totalRatePct = 0.0;

        foreach ($ratesets as $rate) {
            $totalRatePct += (float) ($rate->rate ?? $rate->tax_percent ?? 0);
        }

        // Total tax for this line (inclusive extraction or exclusive addition)
        $lineTaxTotal = $isIncludingTax
            ? ($totalRatePct > 0 ? $lineTotal * $totalRatePct / (100 + $totalRatePct) : 0.0)
            : $lineTotal * ($totalRatePct / 100);

        $itemTaxTotal = 0.0;
        $effectivePct = 0.0;

        foreach ($ratesets as $rate) {
            $rateName    = (string) ($rate->name ?? $rate->taxrate_name ?? '');
            $ratePercent = (float) ($rate->rate ?? $rate->tax_percent ?? 0);
            $rateId      = (int) ($rate->j2commerce_taxrate_id ?? 0);

            // Distribute total tax proportionally across rates
            $rateAmount = $totalRatePct > 0
                ? $lineTaxTotal * ($ratePercent / $totalRatePct)
                : 0.0;

            $itemTaxTotal += $rateAmount;
            $effectivePct += $ratePercent;

            $rateKey = $rateName . '_' . $rateId;

            if (!isset($taxRates[$rateKey])) {
                $taxRates[$rateKey] = (object) [
                    'taxprofile_id'   => $taxprofileId,
                    'taxprofile_name' => (string) ($rate->taxprofile_name ?? ''),
                    'taxrate_name'    => $rateName,
                    'tax_amount'      => 0.0,
                    'tax_percent'     => $ratePercent,
                ];
            }

            $taxRates[$rateKey]->tax_amount += $rateAmount;
        }

        $item->orderitem_tax         = $itemTaxTotal;
        $item->orderitem_tax_percent = $effectivePct;

        return $itemTaxTotal;
    }

    /**
     * Recalculate tax after coupons/vouchers/bulk discounts reduce the taxable amount.
     *
     * When discounts are applied, the taxable base shrinks proportionally.
     * Each tax rate's amount is scaled by the ratio of the net taxable amount
     * to the original subtotal, and order_total is adjusted by the difference.
     *
     * Per-item orderitem_tax is intentionally left unchanged because it reflects
     * the product's tax rate applied to its unit price; the order-level discount
     * is shown as a separate line in Cart Totals.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function recalculateTaxAfterDiscounts(): void
    {
        // A voucher is a payment instrument, not a price reduction: it settles part of what is
        // owed and leaves the goods valued — and taxed — in full. Coupons and plugin cart
        // discounts do reduce the price, so only those shrink the taxable base.
        $voucherDiscount = 0.0;

        foreach ($this->vouchers as $voucher) {
            $voucherDiscount += (float) ($voucher->discount ?? 0);
        }

        $totalDiscount   = $this->order_discount + $this->discount_cart;
        $taxableDiscount = max(0.0, $totalDiscount - $voucherDiscount);

        // loadCoupons()/loadVouchers() already took their own discount off order_total the
        // moment they applied it, so only the cart-discount half is still owed here. The tax
        // ratio below uses the price-reducing figure — subtracting the full one from the total
        // charged the coupon twice.
        $pendingDiscount = $this->discount_cart;

        // No discounts or no subtotal — nothing to adjust
        if ($totalDiscount <= 0 || $this->order_subtotal <= 0) {
            return;
        }

        // Net taxable amount after the price-reducing discounts (floored at zero)
        $netTaxable = max(0.0, $this->order_subtotal - $taxableDiscount);
        $ratio      = $netTaxable / $this->order_subtotal;

        // Scale each tax rate entry proportionally
        $oldTaxTotal = $this->order_tax;
        $newTaxTotal = 0.0;

        foreach ($this->taxRates as $taxRate) {
            $taxRate->tax_amount = round($taxRate->tax_amount * $ratio, 4);
            $newTaxTotal += $taxRate->tax_amount;
        }

        // Adjust order tax and total by the difference
        $taxReduction    = $oldTaxTotal - $newTaxTotal;
        $this->order_tax = $newTaxTotal;

        $isIncludingTax = (int) J2CommerceHelper::config()->get('config_including_tax', 0);

        if ($isIncludingTax) {
            // For inclusive pricing, order_total = subtotal (tax is embedded).
            // Only the discount reduces the total; the tax reduction is already
            // captured proportionally within that discount amount.
            $this->order_total -= $pendingDiscount;
        } else {
            // For exclusive pricing, order_total = subtotal + tax.
            // Both the discount and the proportional tax reduction must be subtracted.
            $this->order_total -= ($pendingDiscount + $taxReduction);
        }

        // Discounts worth more than the cart floor the order at zero, the same as
        // OrderModel::recalculateOrderTotals(). Left negative, OrderTable::check() refuses the row
        // and the shopper meets a generic failure at the moment of paying.
        $this->order_total = max(0.0, $this->order_total);
    }

    /**
     * The single point at which money on this order acquires its final precision.
     *
     * Every component is rounded to the base currency's scale, and the total is then derived
     * as the exact sum of those rounded components rather than rounded independently from the
     * running float. Without this the total and the rows beneath it are rounded separately —
     * six roundings against one — and are under no obligation to agree, so the figure quoted
     * could differ by a cent from both the lines printed above it and the figure saveOrder()
     * stores. Deriving the total from the parts makes those three the same number by
     * construction, which is also what the confirm-time guard compares.
     *
     * Idempotent: re-running it on already-rounded components changes nothing, so callers that
     * adjust the order after construction (applyPaymentSurcharge) can simply call it again.
     *
     * @return  void
     *
     * @since   6.1.0
     */
    public function quantizeTotals(): void
    {
        $scale = CurrencyHelper::getDecimalPlace(ConfigHelper::getDefaultCurrency());
        $round = static fn (float $value): float => round($value, $scale);

        // A line's tax is rounded where it is charged, saveOrderItems() persists it at that
        // scale, and OrderModel::recalculateOrderTotals() sums those stored rows back into
        // order_tax. So the cart settles on the same basis: round each line, then add. Rounding
        // the per-rate total once instead gave the same cart two answers a cent apart depending
        // on which side asked, and the confirm-step guard that compares a saved order against
        // the cart it came from read that cent as a changed cart.
        $lineTaxTotal = 0.0;
        $hasLineTax   = false;

        foreach ($this->items as $item) {
            $lineTax             = $round((float) ($item->orderitem_tax ?? 0));
            $item->orderitem_tax = $lineTax;
            $lineTaxTotal += $lineTax;

            if ($lineTax > 0.0) {
                $hasLineTax = true;
            }
        }

        // Per-profile tax rows are what the customer reads, so they are settled onto the same
        // figure the total carries rather than rounded independently of it.
        if ($hasLineTax) {
            $this->order_tax = $lineTaxTotal;
            $this->settleTaxRatesTo($lineTaxTotal, $scale);
        } elseif (!empty($this->taxRates)) {
            $taxTotal = 0.0;

            foreach ($this->taxRates as $taxRate) {
                $taxRate->tax_amount = $round((float) ($taxRate->tax_amount ?? 0));
                $taxTotal += $taxRate->tax_amount;
            }

            $this->order_tax = $taxTotal;
        } else {
            $this->order_tax = $round($this->order_tax);
        }

        // Same treatment for the discount lines: each coupon, voucher and plugin cart discount
        // is rounded where it is displayed, and the two aggregate columns are rebuilt from them.
        $couponVoucherTotal = 0.0;

        foreach ($this->coupons as $coupon) {
            $coupon->discount = $round((float) ($coupon->discount ?? 0));
            $couponVoucherTotal += $coupon->discount;
        }

        foreach ($this->vouchers as $voucher) {
            $voucher->discount = $round((float) ($voucher->discount ?? 0));
            $couponVoucherTotal += $voucher->discount;
        }

        $this->order_discount = $couponVoucherTotal;

        $cartDiscountTotal = 0.0;
        $renderedCodes     = false;

        foreach ($this->coupon_discount_amounts as $code => $amount) {
            if (!\is_string($code) || str_ends_with($code, '_title') || !is_numeric($amount)) {
                continue;
            }

            $this->coupon_discount_amounts[$code] = $round((float) $amount);
            $cartDiscountTotal += $this->coupon_discount_amounts[$code];
            $renderedCodes = true;
        }

        // A legacy plugin may bump discount_cart without writing a per-code entry; keep its
        // figure rather than zeroing a discount the shopper was given.
        $this->discount_cart = $renderedCodes ? $cartDiscountTotal : $round($this->discount_cart);

        $this->order_subtotal     = $round($this->order_subtotal);
        $this->order_shipping     = $round($this->order_shipping);
        $this->order_shipping_tax = $round($this->order_shipping_tax);

        if ($this->shippingRate !== null) {
            $this->shippingRate->ordershipping_price = $round((float) ($this->shippingRate->ordershipping_price ?? 0));
            $this->shippingRate->ordershipping_extra = $round((float) ($this->shippingRate->ordershipping_extra ?? 0));
            $this->shippingRate->ordershipping_tax   = $this->order_shipping_tax;
        }

        // Fees live in the session and are read fresh on every call, so they are rounded here
        // rather than written back — order_surcharge is the sum of what each fee row displays.
        $surcharge = 0.0;

        foreach ($this->get_fees() as $fee) {
            $surcharge += $round((float) ($fee->amount ?? 0)) + $round((float) ($fee->tax ?? 0));
        }

        $this->order_surcharge = $surcharge;

        // Tax-inclusive prices already carry the tax inside the subtotal and the shipping price,
        // so neither tax component is added again.
        $isIncludingTax = (int) J2CommerceHelper::config()->get('config_including_tax', 0);

        $total = $this->order_subtotal
            + $this->order_shipping
            + $this->order_surcharge
            - $this->order_discount
            - $this->discount_cart;

        if (!$isIncludingTax) {
            $total += $this->order_tax + $this->order_shipping_tax;
        }

        $this->order_total = max(0.0, $round($total));
    }

    /**
     * Round the displayed tax rows and put the rounding residual on the largest of them, so the
     * rows the customer reads add up to the tax the total carries.
     */
    private function settleTaxRatesTo(float $target, int $scale): void
    {
        if (empty($this->taxRates)) {
            return;
        }

        $assigned   = 0.0;
        $largestKey = null;
        $largestAmt = -1.0;

        foreach ($this->taxRates as $key => $taxRate) {
            $amount              = round((float) ($taxRate->tax_amount ?? 0), $scale);
            $taxRate->tax_amount = $amount;
            $assigned += $amount;

            if ($amount > $largestAmt) {
                $largestAmt = $amount;
                $largestKey = $key;
            }
        }

        $residual = round($target - $assigned, $scale);

        if ($largestKey !== null && abs($residual) >= 10 ** -$scale / 2) {
            $this->taxRates[$largestKey]->tax_amount = max(0.0, round($largestAmt + $residual, $scale));
        }
    }

    private function getCustomerGeozones(): array
    {
        $address = TaxHelper::getCustomerAddress();

        $this->customerCountryId = (int) $address->country_id;
        $this->customerZoneId    = (int) $address->zone_id;
        $this->customerPostcode  = (string) $address->postcode;

        $geozones = TaxHelper::getCustomerGeozones($address);

        // No shipping address entered yet — fall back to the store's own address so that
        // tax rates (and the tax line in the cart totals) are visible from the first page load,
        // consistent with how displayPrice() computes tax on product/category pages.
        if (empty($geozones)) {
            $storeAddress            = TaxHelper::getStoreAddress();
            $this->customerCountryId = (int) $storeAddress->country_id;
            $this->customerZoneId    = (int) $storeAddress->zone_id;
            $this->customerPostcode  = (string) $storeAddress->postcode;
            $geozones                = TaxHelper::getCustomerGeozones($storeAddress);
        }

        return $geozones;
    }

    /**
     * Resolve tax rates for a given tax profile against the current customer address.
     *
     * Builds an initial rateset from the geozone-matched DB rate (if any), then
     * fires `onJ2CommerceAfterGetTaxRateItems` so plugins (Avalara, app_taxrate,
     * etc.) can append, override, or replace rates based on the raw country/zone/
     * postcode + address_type + taxprofile_id context.
     *
     * Each returned rate object exposes: j2commerce_taxrate_id, name, rate
     * (percentage as float). Optional: taxprofile_name, tax_percent (alias),
     * taxrate_name (alias).
     *
     * @param   int    $taxprofileId  The tax profile ID for the line item.
     * @param   array  $geozoneIds    Resolved geozone IDs for the customer.
     *
     * @return  array  Array of rate objects (may be empty).
     *
     * @since   6.0.7
     */
    private function getTaxRatesForProfile(int $taxprofileId, array $geozoneIds): array
    {
        $address = (object) [
            'country_id' => $this->customerCountryId,
            'zone_id'    => $this->customerZoneId,
            'postcode'   => $this->customerPostcode,
        ];

        return TaxHelper::getTaxRatesForProfile($taxprofileId, $geozoneIds, $address);
    }

    private function getTaxRateForGeozone(int $taxprofileId, array $geozoneIds): ?object
    {
        return TaxHelper::getTaxRateForGeozone($taxprofileId, $geozoneIds);
    }

    /**
     * Get the tax_class_id (taxprofile_id) for the selected shipping method.
     *
     * @return  int  The tax_class_id or 0 if not found.
     *
     * @since   6.0.6
     */
    private function getShippingTaxClassId(): int
    {
        if (!$this->shippingRate) {
            return 0;
        }

        // Direct tax_class_id from plugin (e.g., AtoShip passes it through the rate pipeline)
        if (!empty($this->shippingRate->ordershipping_tax_class_id)) {
            return (int) $this->shippingRate->ordershipping_tax_class_id;
        }

        if (empty($this->shippingRate->ordershipping_code)) {
            return 0;
        }

        // Fallback: numeric code lookup (standard shipping methods)
        $methodId = (int) $this->shippingRate->ordershipping_code;

        if ($methodId <= 0) {
            return 0;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('tax_class_id'))
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('j2commerce_shippingmethod_id') . ' = :methodId')
            ->bind(':methodId', $methodId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) ($db->loadResult() ?? 0);
    }

    /**
     * Get tax profile info for a given tax class ID, resolved against customer geozones.
     *
     * Used when shipping tax uses a different profile than any product tax.
     *
     * @param   int  $taxClassId  The tax profile ID (tax_class_id).
     *
     * @return  object|null  Object with taxprofile_name, taxrate_name, tax_percent or null.
     *
     * @since   6.0.6
     */
    private function getTaxProfileInfo(int $taxClassId): ?object
    {
        $geozoneIds = $this->getCustomerGeozones();

        if (empty($geozoneIds) || $taxClassId <= 0) {
            return null;
        }

        return $this->getTaxRateForGeozone($taxClassId, $geozoneIds);
    }

    /**
     * The tax rows this order presents, as opposed to the rates it was calculated from.
     *
     * With `combine_tax_calculations` on, shipping tax joins the product tax entry that shares
     * its profile (or takes an entry of its own where the profile differs) and there is one
     * tax figure per profile. With it off, shipping tax stays out of these rows and is shown
     * on its own line from order_shipping_tax.
     *
     * One method answers for the live cart, the persisted #__j2commerce_ordertaxes rows and
     * the confirmation page, so a store cannot be quoted a split total and sent a combined one.
     *
     * @return  array<int, object>
     *
     * @since   6.1.0
     */
    private function buildDisplayTaxRates(): array
    {
        $displayRates = [];

        foreach ($this->taxRates as $taxRate) {
            $displayRates[] = \is_object($taxRate) ? clone $taxRate : (object) $taxRate;
        }

        $combineTax = (int) J2CommerceHelper::config()->get('combine_tax_calculations', 1);

        if (!$combineTax || $this->order_shipping_tax <= 0 || !$this->shippingRate) {
            return $displayRates;
        }

        $shippingTaxClassId = $this->getShippingTaxClassId();

        if ($shippingTaxClassId <= 0) {
            return $displayRates;
        }

        foreach ($displayRates as $rate) {
            if ((int) ($rate->taxprofile_id ?? 0) === $shippingTaxClassId) {
                $rate->tax_amount += $this->order_shipping_tax;

                return $displayRates;
            }
        }

        // Shipping uses a different tax profile — give it an entry of its own
        $profileInfo = $this->getTaxProfileInfo($shippingTaxClassId);

        if ($profileInfo) {
            $displayRates[] = (object) [
                'taxprofile_id'   => $shippingTaxClassId,
                'taxprofile_name' => $profileInfo->taxprofile_name ?? '',
                'taxrate_name'    => $profileInfo->taxrate_name ?? '',
                'tax_amount'      => $this->order_shipping_tax,
                'tax_percent'     => (float) ($profileInfo->tax_percent ?? 0),
            ];
        }

        return $displayRates;
    }

    /**
     * Load applied coupons from session/cart.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function loadCoupons(): void
    {
        $session    = Factory::getApplication()->getSession();
        $couponCode = $session->get('coupon', '', 'j2commerce');

        if (!empty($couponCode)) {
            try {
                $mvcFactory = Factory::getApplication()
                    ->bootComponent('com_j2commerce')
                    ->getMVCFactory();
                $couponModel = $mvcFactory->createModel('Coupon', 'Administrator', ['ignore_request' => true]);

                if ($couponModel) {
                    $coupon = $couponModel->getCouponByCode($couponCode);

                    if ($coupon) {
                        $couponModel->init();

                        if (!$couponModel->isValid($this)) {
                            // Flag as expired — defer session removal to checkout validation.
                            // The reason is safe to carry: isValid() only relays its own
                            // CouponRejection and answers everything else generically.
                            $this->coupons[] = (object) [
                                'coupon_code' => $couponCode,
                                'coupon_id'   => $coupon->j2commerce_coupon_id ?? 0,
                                'discount'    => 0.0,
                                'coupon_name' => $coupon->coupon_name ?? $couponCode,
                                'is_expired'  => true,
                                'error'       => $couponModel->getError() ?: Text::_('COM_J2COMMERCE_COUPON_NOT_VALID'),
                            ];

                            return;
                        }

                        // Allow plugins to reject coupon based on cart context.
                        // Subscribers MUST setEventResult(false) to reject; any other result accepts.
                        // Optionally pass a 'rejection_reason' argument for customer-facing message.
                        $couponEvent = J2CommerceHelper::plugin()->event('BeforeApplyCoupon', [$coupon, $this]);

                        if ($couponEvent->getEventResult() === false) {
                            $this->coupons[] = (object) [
                                'coupon_code' => $couponCode,
                                'coupon_id'   => $coupon->j2commerce_coupon_id ?? 0,
                                'discount'    => 0.0,
                                'coupon_name' => $coupon->coupon_name ?? $couponCode,
                                'is_expired'  => false,
                                'is_rejected' => true,
                                'error'       => $couponEvent->getArgument(
                                    'rejection_reason',
                                    Text::_('COM_J2COMMERCE_COUPON_REJECTED_BY_PLUGIN')
                                ),
                            ];

                            return;
                        }

                        $discount        = $this->calculateCouponDiscount($coupon);
                        $this->coupons[] = (object) [
                            'coupon_code' => $couponCode,
                            'coupon_id'   => $coupon->j2commerce_coupon_id ?? 0,
                            'discount'    => $discount,
                            'coupon_name' => $coupon->coupon_name ?? $couponCode,
                            'is_expired'  => false,
                        ];
                        $this->order_discount += $discount;
                        $this->order_total -= $discount;
                    }
                }
            } catch (\Exception $e) {
                // Silent fail - coupon not applied
            }
        }
    }

    /**
     * Calculate coupon discount amount.
     *
     * @param   object  $coupon  Coupon object.
     *
     * @return  float
     *
     * @since   6.0.6
     */
    protected function calculateCouponDiscount(object $coupon): float
    {
        $discountType  = $coupon->value_type ?? 'percentage';
        $discountValue = (float) ($coupon->value ?? 0);

        // percentage, percentage_cart, percentage_product are all percentage-based
        if (str_starts_with($discountType, 'percentage')) {
            return ($this->order_subtotal * $discountValue) / 100;
        }

        // Fixed amount — cap at subtotal so discount never exceeds order value
        return min($discountValue, $this->order_subtotal);
    }

    /**
     * Load applied vouchers from session/cart.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function loadVouchers(): void
    {
        $session     = Factory::getApplication()->getSession();
        $voucherCode = $session->get('voucher', '', 'j2commerce');

        if (!empty($voucherCode)) {
            try {
                $mvcFactory = Factory::getApplication()
                    ->bootComponent('com_j2commerce')
                    ->getMVCFactory();
                $voucherModel = $mvcFactory->createModel('Voucher', 'Administrator', ['ignore_request' => true]);

                if ($voucherModel) {
                    $voucher = $voucherModel->getVoucherByCode($voucherCode);

                    if ($voucher) {
                        // Validate voucher before applying discount
                        $voucherModel->voucher = $voucher;

                        if (!$voucherModel->isValid()) {
                            // isValid() already removed the invalid voucher from session/cart
                            return;
                        }

                        $balance  = $voucherModel->getRemainingBalance((int) ($voucher->j2commerce_voucher_id ?? 0));
                        $discount = min($balance, $this->order_total);

                        $this->vouchers[] = (object) [
                            'voucher_code' => $voucherCode,
                            'voucher_id'   => $voucher->j2commerce_voucher_id ?? 0,
                            'discount'     => $discount,
                            'voucher_name' => $voucher->voucher_name ?? $voucherCode,
                            'balance'      => $balance,
                        ];
                        $this->order_discount += $discount;
                        $this->order_total -= $discount;
                    }
                }
            } catch (\Exception $e) {
                // Silent fail - voucher not applied
            }
        }
    }

    /**
     * Load shipping rate from session.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function loadShipping(): void
    {
        $session        = Factory::getApplication()->getSession();
        $shippingValues = $session->get('shipping_values', [], 'j2commerce');

        if (!empty($shippingValues)) {
            // Price/tax/extra are session data — a negative must never reach the
            // total, even if some writer (or a plugin event) put one there.
            $shippingPrice = max(0.0, (float) ($shippingValues['shipping_price'] ?? 0));
            $shippingTax   = max(0.0, (float) ($shippingValues['shipping_tax'] ?? 0));
            $shippingExtra = max(0.0, (float) ($shippingValues['shipping_extra'] ?? 0));

            $this->shippingRate = (object) [
                'ordershipping_name'         => $shippingValues['shipping_name'] ?? '',
                'ordershipping_code'         => $shippingValues['shipping_code'] ?? '',
                'ordershipping_price'        => $shippingPrice,
                'ordershipping_tax'          => $shippingTax,
                'ordershipping_extra'        => $shippingExtra,
                'ordershipping_plugin'       => $shippingValues['shipping_plugin'] ?? '',
                'ordershipping_tax_class_id' => (int) ($shippingValues['shipping_tax_class_id'] ?? 0),
            ];

            $this->order_shipping     = $shippingPrice + $shippingExtra;
            $this->order_shipping_tax = $shippingTax;

            $isIncludingTax = (int) ComponentHelper::getParams('com_j2commerce')->get('config_including_tax', 0);

            // A carrier that prices its rate remotely returns no tax and passes the method's
            // tax class through instead, leaving shipping the one charge on the order whose
            // class nothing resolves. Put it through the same rate lookup the product lines
            // use, so one source answers for both and the figure shown here is the figure
            // saveOrderShipping() writes.
            if ($shippingTax <= 0.0 && $this->order_shipping > 0) {
                $shippingTaxClassId = $this->getShippingTaxClassId();

                if ($shippingTaxClassId > 0) {
                    $this->order_shipping_tax = TaxHelper::computeTax(
                        $this->order_shipping,
                        $shippingTaxClassId,
                        $this->getCustomerGeozones(),
                        (bool) $isIncludingTax
                    )->taxtotal;

                    $this->shippingRate->ordershipping_tax = $this->order_shipping_tax;
                }
            }

            // For inclusive pricing, the shipping price already embeds the tax so we must
            // not add order_shipping_tax again — doing so would double-count and inflate
            // the order total by the VAT amount.

            if ($isIncludingTax) {
                $this->order_total += $this->order_shipping;
            } else {
                $this->order_total += $this->order_shipping + $this->order_shipping_tax;
            }
        }
    }

    /**
     * Order shipping rates for display. With auto-apply on, the cheapest rate leads.
     * With it off, the plugin dispatch order (#__extensions.ordering) is preserved
     * between plugins and the rates one plugin returned are priced ascending inside
     * its own block. Either way the first entry is the default selection.
     *
     * @param   array<int, array<string, mixed>>  $rates      Rates from GetShippingRates.
     * @param   bool                              $autoApply  Value of auto_apply_shipping_rate.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   6.1.0
     */
    public static function sortShippingRates(array $rates, bool $autoApply): array
    {
        $byPrice = static fn (array $a, array $b): int => ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0));

        if ($autoApply) {
            usort($rates, $byPrice);

            return $rates;
        }

        $groups = [];

        foreach ($rates as $rate) {
            $groups[(string) ($rate['element'] ?? '')][] = $rate;
        }

        foreach ($groups as &$group) {
            usort($group, $byPrice);
        }

        unset($group);

        return array_merge(...array_values($groups));
    }

    /**
     * Canonical empty shipping_values array (no method selected).
     *
     * @return  array<string, mixed>
     *
     * @since   6.1.0
     */
    public static function emptyShippingValues(): array
    {
        return [
            'shipping_plugin'       => '',
            'shipping_name'         => '',
            'shipping_price'        => '0',
            'shipping_code'         => '',
            'shipping_tax'          => '0',
            'shipping_tax_class_id' => 0,
            'shipping_extra'        => '',
        ];
    }

    /**
     * A plugin returning a negative price, tax, or extra is broken — refuse to bind it.
     * Takes mixed so filtering a rate list cannot fatal on a malformed member.
     *
     * @since   6.1.0
     */
    public static function rateChargesAreValid(mixed $rate): bool
    {
        return \is_array($rate)
            && (float) ($rate['price'] ?? 0) >= 0
            && (float) ($rate['tax'] ?? 0) >= 0
            && (float) ($rate['extra'] ?? 0) >= 0;
    }

    /**
     * What the plugins offer this order, after the exclusions the merchant configured. A
     * carrier plugin answers this with a billed API call, so a caller that needs both the
     * list and a selection out of it takes the list once and matches against it.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   6.1.0
     */
    public static function collectShippingRates(object $order): array
    {
        $rates = [];

        foreach (J2CommerceHelper::plugin()->eventWithArray('GetShippingRates', [$order]) as $result) {
            if (\is_array($result) && isset($result['element'])) {
                $rates[] = $result;
            } elseif (\is_array($result)) {
                $rates = array_merge($rates, $result);
            }
        }

        // Apply the same exclusion filter the offer pipeline applies — a rate the
        // merchant excluded (user group, shipping method) must not be resolvable.
        $filterEvent = new Event('onJ2CommerceFilterShippingRates', [
            'rates' => $rates,
            'order' => $order,
        ]);
        Factory::getContainer()->get(DispatcherInterface::class)->dispatch('onJ2CommerceFilterShippingRates', $filterEvent);

        return $filterEvent->getArgument('rates', $rates);
    }

    /**
     * Re-resolve a shipping selection against a fresh GetShippingRates dispatch; monetary
     * values always come from the plugin's rate, never from request input. Null on no match.
     *
     * @return  array<string, mixed>|null  Canonical shipping_values, or null when no rate matches.
     *
     * @since   6.1.0
     */
    public static function resolvePluginShippingRate(object $order, string $plugin, string $name, string $code): ?array
    {
        return self::matchShippingRate(self::collectShippingRates($order), $plugin, $name, $code);
    }

    /**
     * Bind a selection to a rate out of an offer list already in hand — the same match
     * resolvePluginShippingRate() makes, for a caller that has paid for the dispatch.
     *
     * @param   array<int, mixed>  $rates  Offer list as collectShippingRates() returns it.
     *
     * @return  array<string, mixed>|null  Canonical shipping_values, or null when no rate matches.
     *
     * @since   6.1.0
     */
    public static function matchShippingRate(array $rates, string $plugin, string $name, string $code): ?array
    {
        foreach ($rates as $rate) {
            if (!\is_array($rate)) {
                continue;
            }

            if ((string) ($rate['element'] ?? '') !== $plugin || (string) ($rate['name'] ?? '') !== $name) {
                continue;
            }

            $rateCode = (string) ($rate['code'] ?? '');

            if ($rateCode !== '' && $code !== '' && $rateCode !== $code) {
                continue;
            }

            if (!self::rateChargesAreValid($rate)) {
                continue;
            }

            return [
                'shipping_plugin'       => (string) ($rate['element'] ?? ''),
                'shipping_name'         => (string) ($rate['name'] ?? ''),
                'shipping_price'        => (string) (float) ($rate['price'] ?? 0),
                'shipping_code'         => $rateCode,
                'shipping_tax'          => (string) (float) ($rate['tax'] ?? 0),
                'shipping_tax_class_id' => (int) ($rate['tax_class_id'] ?? 0),
                'shipping_extra'        => (string) (float) ($rate['extra'] ?? 0),
            ];
        }

        return null;
    }

    /**
     * Add a fee to the order (stored in session, keyed to prevent duplicates).
     */
    public static function addFee(string $key, float $amount, string $label, float $tax = 0.0, int $taxClassId = 0): void
    {
        $session = Factory::getApplication()->getSession();
        $fees    = $session->get('order_fees', [], 'j2commerce');

        $fees[$key] = [
            'name'          => $label,
            'amount'        => $amount,
            'tax'           => $tax,
            'taxprofile_id' => $taxClassId,
            'plugin'        => $key,
        ];

        $session->set('order_fees', $fees, 'j2commerce');
    }

    /**
     * Remove a fee by key from the session.
     */
    public static function removeFee(string $key): void
    {
        $session = Factory::getApplication()->getSession();
        $fees    = $session->get('order_fees', [], 'j2commerce');

        unset($fees[$key]);

        $session->set('order_fees', $fees, 'j2commerce');
    }

    /** Legacy compatibility — called by payment plugins via $order->add_fee(). */
    public function add_fee(string $name, float $amount, bool $taxable = false, $taxClassId = 0): void
    {
        $key = 'payment_' . $this->orderpayment_type;
        // tax stays 0.0 for v1 (tax-free surcharge); taxClassId stored for forward use
        self::addFee($key, $amount, $name, 0.0, (int) $taxClassId);
    }

    /** Legacy compatibility — called by payment plugins via $order->get_payment_method(). */
    public function get_payment_method(): string
    {
        return $this->orderpayment_type;
    }

    /**
     * Fee tax is attributed to order_surcharge, not order_tax: order_tax is the
     * product tax (it mirrors SUM(orderitem_tax) and the #__j2commerce_ordertaxes
     * rows), so folding fee tax into it would be dropped by the first admin
     * recalculation. Same treatment shipping tax gets — see loadShipping().
     */
    protected function loadFees(): void
    {
        $fees = $this->get_fees();

        foreach ($fees as $fee) {
            $this->order_surcharge += (float) $fee->amount + (float) $fee->tax;
            $this->order_total += (float) $fee->amount + (float) $fee->tax;
        }
    }

    /**
     * Apply the selected payment method's surcharge.
     *
     * Call AFTER orderpayment_type is set (post payment-method selection). Rolls back any
     * prior payment_* fees (handles method-switching + the stale fee the constructor's
     * loadFees() folded in), dispatches onJ2CommerceCalculateFees so the selected plugin
     * calls add_fee(), then folds the new fee into the running total.
     */
    public function applyPaymentSurcharge(): void
    {
        $session = Factory::getApplication()->getSession();
        $fees    = $session->get('order_fees', [], 'j2commerce');

        // Drop ALL prior payment_* fees from the session
        foreach ($fees as $key => $fee) {
            if (str_starts_with((string) $key, 'payment_')) {
                unset($fees[$key]);
            }
        }

        $session->set('order_fees', $fees, 'j2commerce');

        if ($this->orderpayment_type !== '') {
            // Selected payment plugin's onCalculateFees() calls $order->add_fee() → session
            J2CommerceHelper::plugin()->event('CalculateFees', [$this->orderpayment_type, $this]);
        }

        // The surcharge and the total are rebuilt from the fees now in the session rather than
        // adjusted by hand, so switching payment method cannot leave a stale half of a fee
        // behind and the total stays the sum of the parts the customer is shown.
        $this->quantizeTotals();
    }

    /**
     * Get item attributes (options) for display.
     *
     * @param   object  $item  Cart item.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    protected function getItemAttributes(object $item): array
    {
        $attributes = [];

        // If behavior already processed options, use those directly (avoids duplicates)
        if (!empty($item->options) && \is_array($item->options)) {
            foreach ($item->options as $option) {
                $attributes[] = (object) [
                    'orderitemattribute_name'  => $option['name'] ?? '',
                    'orderitemattribute_value' => $option['option_value'] ?? $option['value'] ?? '',
                    'orderitemattribute_type'  => $option['type'] ?? 'select',
                    'orderitemattribute_price' => $option['price'] ?? 0,
                ];
            }

            return $attributes;
        }

        // Fallback: decode raw product_options if behavior didn't process them
        if (!empty($item->product_options)) {
            $decoded = @unserialize(base64_decode($item->product_options), ['allowed_classes' => false]);

            if ($decoded !== false && \is_array($decoded)) {
                $attributes = $this->processProductOptions($decoded, (int) ($item->product_id ?? 0));
            }
        }

        return $attributes;
    }

    /**
     * Process product options into attribute format.
     *
     * @param   array  $options    Decoded options array.
     * @param   int    $productId  Product ID.
     *
     * @return  array
     *
     * @since   6.0.6
     */
    protected function processProductOptions(array $options, int $productId): array
    {
        $attributes = [];

        if (empty($options) || $productId <= 0) {
            return $attributes;
        }

        // Load product options from database
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($options as $optionId => $optionValue) {
            if (empty($optionValue)) {
                continue;
            }

            // Get option details
            $query = $db->getQuery(true)
                ->select(['o.option_name', 'o.type'])
                ->from($db->quoteName('#__j2commerce_product_options', 'po'))
                ->join('LEFT', $db->quoteName('#__j2commerce_options', 'o') .
                    ' ON ' . $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id'))
                ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :optionId')
                ->bind(':optionId', $optionId, ParameterType::INTEGER);

            $db->setQuery($query);
            $optionInfo = $db->loadObject();

            if (!$optionInfo) {
                continue;
            }

            $optionName = Text::_($optionInfo->option_name ?? '');
            $optionType = $optionInfo->type ?? 'select';

            // Handle array values (multiselect, checkbox)
            if (\is_array($optionValue)) {
                $valueNames = [];
                foreach ($optionValue as $valueId) {
                    $valueName = $this->getOptionValueName((int) $valueId);
                    if ($valueName) {
                        $valueNames[] = $valueName;
                    }
                }
                $displayValue = implode(', ', $valueNames);
            } else {
                // Single value
                if (\in_array($optionType, ['select', 'radio', 'checkbox'])) {
                    $displayValue = $this->getOptionValueName((int) $optionValue);
                } else {
                    $displayValue = (string) $optionValue;
                }
            }

            $attributes[] = (object) [
                'orderitemattribute_name'  => $optionName,
                'orderitemattribute_value' => $displayValue,
                'orderitemattribute_type'  => $optionType,
                'orderitemattribute_price' => 0,
            ];
        }

        return $attributes;
    }

    /**
     * Get option value display name.
     *
     * @param   int  $valueId  Option value ID.
     *
     * @return  string
     *
     * @since   6.0.6
     */
    protected function getOptionValueName(int $valueId): string
    {
        if ($valueId <= 0) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov'),
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :valueId')
            ->bind(':valueId', $valueId, ParameterType::INTEGER);

        $db->setQuery($query);

        return Text::_($db->loadResult() ?? '');
    }

    /**
     * Get formatted line item unit price based on tax display setting.
     *
     * @param   object  $item          Cart item.
     * @param   int     $displayMode   Price display mode (0=excl tax, 1=incl tax).
     *
     * @return  float  The formatted unit price.
     *
     * @since   6.0.6
     */
    public function get_formatted_lineitem_price(object $item, int $displayMode = 0): float
    {
        // Get base price from pricing object or direct properties
        $basePrice = 0.0;

        if (isset($item->pricing) && \is_object($item->pricing)) {
            $basePrice = (float) ($item->pricing->price ?? 0);
        } elseif (isset($item->orderitem_price)) {
            $basePrice = (float) $item->orderitem_price;
        }

        // Add option price
        $optionPrice = (float) ($item->option_price ?? $item->orderitem_option_price ?? 0);
        $unitPrice   = $basePrice + $optionPrice;

        $taxProfileId = (int) ($item->taxprofile_id ?? $item->orderitem_taxprofile_id ?? 0);

        if ($taxProfileId > 0) {
            $productHelper  = new ProductHelper();
            $isIncludingTax = (int) J2CommerceHelper::config()->get('config_including_tax', 0);

            if ($isIncludingTax) {
                // Stored price already includes tax; strip it for tax-exclusive display only.
                if ($displayMode == 0) {
                    $unitPrice = $productHelper->get_price_excluding_tax($unitPrice, $taxProfileId);
                }
            } elseif ($displayMode == 1) {
                // Stored price is net; add tax for tax-inclusive display.
                $unitPrice = $productHelper->get_price_including_tax($unitPrice, $taxProfileId);
            }
        }

        return $unitPrice;
    }

    /**
     * Get formatted line item total price based on tax display setting.
     *
     * @param   object  $item          Cart item.
     * @param   int     $displayMode   Price display mode (0=excl tax, 1=incl tax).
     *
     * @return  float  The formatted total price (unit price x quantity).
     *
     * @since   6.0.6
     */
    public function get_formatted_lineitem_total(object $item, int $displayMode = 0): float
    {
        $unitPrice = $this->get_formatted_lineitem_price($item, $displayMode);
        $quantity  = (float) ($item->product_qty ?? $item->orderitem_quantity ?? 1);

        return $unitPrice * $quantity;
    }

    /**
     * Get line item discount information for displaying crossed-out prices.
     *
     * Returns an object with original price, discount amount, and final price
     * for items that have discounts applied.
     *
     * @param   object  $item          Cart item.
     * @param   int     $displayMode   Price display mode (0=excl tax, 1=incl tax).
     *
     * @return  object|null  Object with original, discount, and final prices, or null if no discount.
     *
     * @since   6.0.6
     */
    public function get_lineitem_discount_info(object $item, int $displayMode = 0): ?object
    {
        $discount    = (float) ($item->orderitem_discount ?? 0);
        $discountTax = (float) ($item->orderitem_discount_tax ?? 0);

        if ($discount <= 0) {
            return null;
        }

        $unitPrice = $this->get_formatted_lineitem_price($item, $displayMode);
        $quantity  = (float) ($item->product_qty ?? $item->orderitem_quantity ?? 1);

        $originalTotal = $unitPrice * $quantity;

        // Calculate discounted total
        // When displayMode is 1 (tax-inclusive), discount already includes tax
        $discountedTotal = $originalTotal - $discount;
        if ($displayMode == 0) {
            // Tax-exclusive display - discount is also tax-exclusive
            $discountedTotal = $originalTotal - $discount;
        } else {
            // Tax-inclusive display - adjust for discount tax
            $discountedTotal = $originalTotal - ($discount + $discountTax);
        }

        return (object) [
            'original_price'  => $originalTotal,
            'discount_amount' => $discount,
            'discount_tax'    => $discountTax,
            'final_price'     => max(0.0, $discountedTotal),
        ];
    }

    /**
     * Get fees applied to the order.
     *
     * Fees are additional charges that plugins can add (handling fees, payment fees, etc.).
     *
     * @return  array  Array of fee objects.
     *
     * @since   6.0.6
     */
    public function get_fees(): array
    {
        $fees = [];

        // Load fees from session if any plugins have set them
        $session     = Factory::getApplication()->getSession();
        $sessionFees = $session->get('order_fees', [], 'j2commerce');

        if (!empty($sessionFees) && \is_array($sessionFees)) {
            foreach ($sessionFees as $fee) {
                $fees[] = (object) [
                    'name'          => $fee['name'] ?? 'Fee',
                    'amount'        => (float) ($fee['amount'] ?? 0),
                    'tax'           => (float) ($fee['tax'] ?? 0),
                    'taxprofile_id' => (int) ($fee['taxprofile_id'] ?? 0),
                    'plugin'        => $fee['plugin'] ?? '',
                ];
            }
        }

        return $fees;
    }

    /**
     * Get formatted fee amount based on tax display setting.
     *
     * @param   object  $fee           Fee object.
     * @param   int     $displayMode   Price display mode (0=excl tax, 1=incl tax).
     *
     * @return  float  The formatted fee amount.
     *
     * @since   6.0.6
     */
    public function get_formatted_fees(object $fee, int $displayMode = 0): float
    {
        $amount = (float) ($fee->amount ?? 0);
        $tax    = (float) ($fee->tax ?? 0);

        if ($displayMode == 1) {
            // Include tax in the fee display
            return $amount + $tax;
        }

        return $amount;
    }

    /**
     * Get all order discounts (coupons, vouchers, and other discounts).
     *
     * Returns a unified array of discount objects for display in order totals.
     *
     * @return  array  Array of discount objects.
     *
     * @since   6.0.6
     */
    public function getOrderDiscounts(): array
    {
        $discounts = [];

        // Add coupons as discounts
        foreach ($this->coupons as $coupon) {
            $discounts[] = (object) [
                'discount_type'   => 'coupon',
                'discount_code'   => $coupon->coupon_code ?? '',
                'discount_title'  => $coupon->coupon_name ?? $coupon->coupon_code ?? '',
                'discount_amount' => (float) ($coupon->discount ?? 0),
            ];
        }

        // Add vouchers as discounts
        foreach ($this->vouchers as $voucher) {
            $discounts[] = (object) [
                'discount_type'   => 'voucher',
                'discount_code'   => $voucher->voucher_code ?? '',
                'discount_title'  => $voucher->voucher_name ?? $voucher->voucher_code ?? '',
                'discount_amount' => (float) ($voucher->discount ?? 0),
            ];
        }

        // Add cart-level discounts from plugins — one line per discount code with its own title.
        // Each plugin that calls addOrderDiscounts() / increase_coupon_discount_amount() can set
        // $order->coupon_discount_amounts['<code>_title'] to override the default label.
        $renderedAmount = 0.0;

        foreach ($this->coupon_discount_amounts as $code => $amount) {
            if (!\is_string($code) || str_ends_with($code, '_title')) {
                continue;
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            $title = $this->coupon_discount_amounts[$code . '_title']
                ?? ($code === 'bulk_discount' ? ($this->coupon_discount_amounts['bulk_discount_title'] ?? null) : null)
                ?? Text::_('COM_J2COMMERCE_CART_BULK_DISCOUNT');

            $discounts[] = (object) [
                'discount_type'   => 'cart_discount',
                'discount_code'   => $code,
                'discount_title'  => $title,
                'discount_amount' => (float) $amount,
            ];

            $renderedAmount += (float) $amount;
        }

        // Back-compat: legacy plugins may bump discount_cart without writing a per-code entry.
        $unrendered = $this->discount_cart - $renderedAmount;

        if ($unrendered > 0.001) {
            $discounts[] = (object) [
                'discount_type'  => 'cart_discount',
                'discount_code'  => 'bulk_discount',
                'discount_title' => $this->coupon_discount_amounts['bulk_discount_title']
                    ?? Text::_('COM_J2COMMERCE_CART_BULK_DISCOUNT'),
                'discount_amount' => $unrendered,
            ];
        }

        return $discounts;
    }

    /**
     * Single source of truth shared by saveOrder() (which persists the email) and coupon
     * per-customer limit validation (which counts prior uses against it), so the address
     * checked can never diverge from the address stored. A guest address is self-asserted
     * — an identity hint, not proof of identity.
     */
    public function resolveCheckoutEmail(): string
    {
        $app    = Factory::getApplication();
        $user   = $app->getIdentity();
        $userId = ($user && $user->id) ? (int) $user->id : 0;

        if ($userId === 0) {
            $guestData = (array) $app->getSession()->get('guest', [], 'j2commerce');

            return (string) ($guestData['email'] ?? '');
        }

        $email = (string) ($user->email ?? '');

        // Logged-in identities resolved late carry no email on the session object.
        if ($email === '') {
            $userFactory = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class);
            $email       = (string) ($userFactory->loadUserById($userId)->email ?? '');
        }

        return $email;
    }

    /**
     * Persist the cart order to the database.
     *
     * Creates records in orders, orderitems, orderinfos, ordertaxes,
     * ordershippings and orderdiscounts tables. Sets order_id,
     * j2commerce_order_id and token on this object.
     *
     * @return  self  This object with database fields populated.
     *
     * @throws  \RuntimeException  If the order cannot be saved.
     *
     * @since   6.0.6
     */
    public function saveOrder(): self
    {
        // Settle the figures before any of them is written, so the row stored is the row the
        // customer was shown. Idempotent — the constructor has already run it.
        $this->quantizeTotals();

        $app     = Factory::getApplication();
        $session = $app->getSession();
        $user    = $app->getIdentity();
        $db      = Factory::getContainer()->get(DatabaseInterface::class);

        $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();

        // Gather user info
        $userId    = ($user && $user->id) ? (int) $user->id : 0;
        $userEmail = $this->resolveCheckoutEmail();

        $this->user_id       = $userId;
        $this->user_email    = $userEmail;
        $this->customer_note = $session->get('customer_note', '', 'j2commerce');

        // Get cart ID
        $cartHelper    = CartHelper::getInstance();
        $cart          = $cartHelper->getCart();
        $this->cart_id = (int) ($cart->j2commerce_cart_id ?? 0);

        // Currency info
        $currency      = J2CommerceHelper::currency();
        $currencyCode  = $currency->getCode();
        $currencyId    = (int) $currency->getId();
        $currencyValue = (float) $currency->getValue();

        // Config
        $params         = J2CommerceHelper::config();
        $invoicePrefix  = (string) $params->get('invoice_prefix', '');
        $isIncludingTax = (int) $params->get('config_including_tax', 0);

        // Determine if order is shippable
        $isShippable = 0;
        foreach ($this->items as $item) {
            if (!empty($item->shipping)) {
                $isShippable = 1;
                break;
            }
        }

        // Create the order record via OrderTable
        $orderTable = $mvcFactory->createTable('Order', 'Administrator');

        // Backstop only: quantizeTotals() has already rounded every one of these to the base
        // currency's scale, so $money() is a no-op on anything it settled. It stays because a
        // plugin may write one of these fields directly between that call and this bind.
        $moneyScale = CurrencyHelper::getDecimalPlace(ConfigHelper::getDefaultCurrency());
        $money      = static fn (float $value): float => round($value, $moneyScale);

        // order_discount is written from the coupon and voucher half alone; a plugin discount
        // lives in discount_cart and reaches the column through syncOrderDiscountTotal() below,
        // which sums the persisted rows.
        $orderData = [
            'user_id'               => $userId,
            'user_email'            => $userEmail,
            'cart_id'               => $this->cart_id,
            'order_total'           => $money($this->order_total),
            'order_subtotal'        => $money($this->order_subtotal),
            'order_subtotal_ex_tax' => $money($this->order_subtotal),
            'order_tax'             => $money($this->order_tax),
            'order_shipping'        => $money($this->order_shipping),
            'order_shipping_tax'    => $money($this->order_shipping_tax),
            'order_discount'        => $money($this->order_discount),
            'order_surcharge'       => $money($this->order_surcharge),
            'orderpayment_type'     => $this->orderpayment_type,
            'currency_id'           => $currencyId,
            'currency_code'         => $currencyCode,
            'currency_value'        => $currencyValue,
            'invoice_prefix'        => $invoicePrefix,
            'is_shippable'          => $isShippable,
            'is_including_tax'      => $isIncludingTax,
            'customer_note'         => $this->customer_note,
            'customer_language'     => $app->getLanguage()->getTag(),
            'customer_group'        => $userId > 0
                ? implode(',', Access::getGroupsByUser($userId, false))
                : (string) (int) ComponentHelper::getParams('com_users')->get('guest_usergroup', 1),
            'ip_address'     => $app->input->server->getString('REMOTE_ADDR', ''),
            'order_state_id' => 5, // Incomplete
            'created_by'     => $userId,
            'modified_by'    => $userId,
        ];

        if (!$orderTable->bind($orderData) || !$orderTable->check() || !$orderTable->store()) {
            Log::add('CartOrder order create failed: ' . $orderTable->getError(), Log::ERROR, 'com_j2commerce');

            throw new \RuntimeException(
                Text::sprintf('COM_J2COMMERCE_ORDER_SAVE_ERROR', Text::_('COM_J2COMMERCE_ERR_GENERIC'))
            );
        }

        // Two-pass save:
        // First store() gets the auto-increment PK, then we generate
        // order_id = time() . PK and token, then store() again.
        $orderTable->order_id = $orderTable->generateOrderId();
        $orderTable->token    = $orderTable->generateToken();

        if ($invoicePrefix !== '') {
            $orderTable->invoice_number = (int) $orderTable->j2commerce_order_id;
        }

        if (!$orderTable->store()) {
            Log::add('CartOrder order id/token store failed: ' . $orderTable->getError(), Log::ERROR, 'com_j2commerce');

            throw new \RuntimeException(
                Text::sprintf('COM_J2COMMERCE_ORDER_SAVE_ERROR', Text::_('COM_J2COMMERCE_ERR_GENERIC'))
            );
        }

        // Set the generated values on this object
        $this->order_id            = $orderTable->order_id;
        $this->j2commerce_order_id = (int) $orderTable->j2commerce_order_id;
        $this->token               = $orderTable->token;

        $orderId = $this->order_id;

        // Save order items
        $this->saveOrderItems($db, $orderId, $userId);

        // Save order info (billing/shipping addresses)
        $this->saveOrderInfo($db, $orderId, $session, $mvcFactory, $userId);

        // Save order taxes
        $this->saveOrderTaxes($db, $orderId);

        // Save order shipping
        $this->saveOrderShipping($db, $orderId);

        // Save order discounts (coupons + vouchers + plugin cart discounts)
        $this->saveOrderDiscounts($db, $orderId, $userId, $userEmail);

        // orders.order_discount is a mirror of those rows, and it is the only discount input
        // OrderModel::recalculateOrderTotals() reads. $this->order_discount carries the coupon
        // and voucher half alone — a discount booked through increase_coupon_discount_amount()
        // lives in $discount_cart — so writing the property would leave every plugin discount
        // out of the column and any later recompute would rebuild order_total without it.
        // Summing the persisted rows instead is also immune to a discount that was booked into
        // both accumulators: saveOrderDiscounts() emits one row per code either way.
        $discountSyncModel = $mvcFactory->createModel('Order', 'Administrator', ['ignore_request' => true]);

        if ($discountSyncModel) {
            $discountSyncModel->syncOrderDiscountTotal($orderId);
        }

        // Save order fees (surcharges with names)
        $this->saveOrderFees($db, $orderId);

        // Move customer-uploaded files from tmp/ to orders/{order_id}/
        // Lookup is by mangled_name JOIN against orderitemattributes — cart_id alone is unreliable
        // because product-option uploads happen before a cart exists (getCartId() returns 0).
        OrderUploadHelper::attachUploadsToOrder(
            $this->j2commerce_order_id,
            $this->order_id
        );

        // Create download records for downloadable items (access not yet granted)
        DownloadHelper::createOrderDownloads($orderId, $userId, $userEmail);

        // Allow plugins to act after order save
        J2CommerceHelper::plugin()->event('AfterSaveOrder', [$this]);

        // Add order history entry
        OrderHistoryHelper::add(
            orderId: $this->order_id,
            comment: Text::_('COM_J2COMMERCE_ORDER_HISTORY_NEW_ORDER_CREATED'),
            orderStateId: (int) ($orderTable->order_state_id ?? 5),
            createdBy: $userId,
        );

        return $this;
    }

    /**
     * Save order items to the database.
     *
     * @param   DatabaseInterface  $db       Database driver.
     * @param   string             $orderId  The order_id string.
     * @param   int                $userId   User ID.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function saveOrderItems(DatabaseInterface $db, string $orderId, int $userId): void
    {
        $now  = Factory::getDate()->toSql();
        $rows = [];

        foreach ($this->items as $item) {
            // product_type must come from the cartitem. Never default to 'simple' —
            // that downgrades subscription / variable / configurable / etc. products
            // and breaks AfterPayment routing (e.g. subscription rows never created).
            $itemProductType = trim((string) ($item->product_type ?? ''));

            if ($itemProductType === '') {
                throw new \RuntimeException(
                    'CartOrder::createOrderItems requires $item->product_type — refusing to write orderitem with default "simple"'
                );
            }

            $pricing     = $item->pricing ?? null;
            $quantity    = (int) ($item->product_qty ?? 1);
            // Same fallback chain calculateTotals() used to build the price it handed the plugins,
            // so an item without a pricing object cannot read as discounted here when it is not.
            $basePrice   = (float) ($pricing->price ?? $item->price ?? $item->variant_price ?? 0);
            $optionPrice = (float) ($item->option_price ?? 0);
            $grossUnit   = $basePrice + $optionPrice;

            // Only the discount a plugin baked into the price is a LINE discount. One booked
            // through increase_coupon_discount_amount() is order-level money — it has its own
            // orderdiscounts row and its own column, and subtracting it here as well would take
            // it off the order twice.
            $effectiveUnit = isset($item->orderitem_effective_price)
                ? (float) $item->orderitem_effective_price
                : $grossUnit;
            $lineDiscount  = max(0.0, ($grossUnit - $effectiveUnit) * $quantity);
            $finalPrice    = $grossUnit * $quantity - $lineDiscount;

            // calculateTotals() already resolved this line's tax against the customer's geozone
            // and the price the plugins left behind; pricing->tax is the pre-plugin per-unit
            // figure and disagrees with order_tax whenever either applies.
            $itemTax = isset($item->orderitem_tax)
                ? (float) $item->orderitem_tax
                : (float) ($pricing->tax ?? 0) * $quantity;

            $lineDiscountTax = 0.0;

            if ($lineDiscount > 0.0 && $finalPrice > 0.0 && $itemTax > 0.0) {
                $lineDiscountTax = $lineDiscount * ($itemTax / $finalPrice);
            }

            $perItemTax        = $quantity > 0 ? $itemTax / $quantity : $itemTax;
            $finalPriceWithTax = (int) J2CommerceHelper::config()->get('config_including_tax', 0)
                ? $finalPrice
                : $finalPrice + $itemTax;

            // Serialize item attributes for storage
            $attributes = '';
            if (!empty($item->orderitemattributes)) {
                $attributes = json_encode($item->orderitemattributes);
            } elseif (!empty($item->product_options)) {
                $attributes = $item->product_options;
            }

            $row = (object) [
                'order_id'                         => $orderId,
                'orderitem_type'                   => 'normal',
                'cart_id'                          => (int) ($this->cart_id),
                'cartitem_id'                      => (int) ($item->j2commerce_cartitem_id ?? $item->cartitem_id ?? 0),
                'product_id'                       => (int) ($item->product_id ?? 0),
                'product_type'                     => $itemProductType,
                'variant_id'                       => (int) ($item->variant_id ?? 0),
                'vendor_id'                        => (int) ($item->vendor_id ?? 0),
                'orderitem_sku'                    => (string) ($item->sku ?? ''),
                'orderitem_name'                   => (string) ($item->product_name ?? $item->orderitem_name ?? ''),
                'orderitem_attributes'             => (string) $attributes,
                'orderitem_quantity'               => (string) $quantity,
                'orderitem_taxprofile_id'          => (int) ($item->taxprofile_id ?? $pricing->taxprofile_id ?? 0),
                'orderitem_per_item_tax'           => $perItemTax,
                'orderitem_tax'                    => $itemTax,
                'orderitem_discount'               => $lineDiscount,
                'orderitem_discount_tax'           => $lineDiscountTax,
                'orderitem_price'                  => $basePrice,
                'orderitem_option_price'           => $optionPrice,
                'orderitem_finalprice'             => $finalPrice,
                'orderitem_finalprice_with_tax'    => $finalPriceWithTax,
                'orderitem_finalprice_without_tax' => $finalPrice,
                'orderitem_params'                 => (string) ($item->cartitem_params ?? $item->orderitem_params ?? '{}'),
                'created_on'                       => $now,
                'created_by'                       => $userId,
                'orderitem_weight'                 => (string) ($item->weight ?? 0),
                'orderitem_weight_total'           => (string) (($item->weight ?? 0) * $quantity),
            ];

            $rows[] = [$row, $item];
        }

        $this->scaleLineTaxToOrderTax($rows);

        foreach ($rows as [$row, $item]) {
            // Storefront counterpart to the dispatch in OrderModel::addOrderItemFromVariant() — same event,
            // same by-reference contract, so one handler serves both write paths. The cart item travels with
            // the row because the shopper's selections live there, not on the order item being built.
            $baseline = (array) $row;

            J2CommerceHelper::plugin()->event('BeforeAddOrderItem', [&$row, $item]);

            OrderHelper::normalizeOrderItemRow($row, $baseline);

            $db->insertObject('#__j2commerce_orderitems', $row, 'j2commerce_orderitem_id');
        }
    }

    /**
     * Bring the per-line tax in step with the order-level tax before the rows are written.
     *
     * recalculateTaxAfterDiscounts() scales order_tax down to the discounted base but leaves the
     * per-line figures alone, and OrderModel::recalculateOrderTotals() prefers SUM(orderitem_tax)
     * whenever it is positive — so without this, any later recompute of a discounted order rebuilds
     * order_total with the undiscounted tax. The largest taxed line absorbs the rounding remainder,
     * so the sum matches the stored order_tax exactly and no line can be driven negative by it.
     *
     * @param  array<int, array{0: object, 1: object}>  $rows  Row/item pairs, mutated in place.
     */
    private function scaleLineTaxToOrderTax(array $rows): void
    {
        $scale     = CurrencyHelper::getDecimalPlace(ConfigHelper::getDefaultCurrency());
        $tolerance = 10 ** -$scale / 2;
        $target    = round($this->order_tax, $scale);
        $rawSum    = 0.0;

        foreach ($rows as [$row]) {
            $rawSum += (float) $row->orderitem_tax;
        }

        if ($rawSum <= 0.0 || abs($rawSum - $target) < $tolerance) {
            return;
        }

        $ratio       = $target / $rawSum;
        $assigned    = 0.0;
        $largestKey  = null;
        $largestTax  = 0.0;

        foreach ($rows as $key => [$row]) {
            if ((float) $row->orderitem_tax <= 0.0) {
                continue;
            }

            $lineTax = round((float) $row->orderitem_tax * $ratio, $scale);
            $this->setRowTax($row, $lineTax, $scale);
            $assigned += $lineTax;

            if ($lineTax > $largestTax) {
                $largestTax = $lineTax;
                $largestKey = $key;
            }
        }

        if ($largestKey === null || abs($assigned - $target) < $tolerance) {
            return;
        }

        $this->setRowTax($rows[$largestKey][0], max(0.0, round($largestTax + ($target - $assigned), $scale)), $scale);
    }

    /** Writes a line's tax and the two columns derived from it. */
    private function setRowTax(object $row, float $lineTax, int $scale): void
    {
        $quantity = (int) $row->orderitem_quantity;

        $row->orderitem_tax          = $lineTax;
        $row->orderitem_per_item_tax = $quantity > 0 ? round($lineTax / $quantity, $scale + 2) : $lineTax;
        // Tax-inclusive stores keep the tax inside the line price already; adding it again would
        // report the line at price + tax.
        $row->orderitem_finalprice_with_tax = (int) J2CommerceHelper::config()->get('config_including_tax', 0)
            ? (float) $row->orderitem_finalprice
            : (float) $row->orderitem_finalprice + $lineTax;
    }

    /**
     * Save billing and shipping address info to the orderinfos table.
     *
     * @param   DatabaseInterface  $db         Database driver.
     * @param   string             $orderId    The order_id string.
     * @param   object             $session    Session object.
     * @param   object             $mvcFactory MVC factory.
     * @param   int                $userId     User ID.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function saveOrderInfo(
        DatabaseInterface $db,
        string $orderId,
        object $session,
        object $mvcFactory,
        int $userId
    ): void {
        $billing  = $this->loadAddressData('billing', $session, $mvcFactory, $userId);
        $shipping = $this->loadAddressData('shipping', $session, $mvcFactory, $userId);

        // Payment-step custom field values captured by shippingPaymentMethodValidate()
        $paymentCustom = (array) $session->get('payment_custom_fields', [], 'j2commerce');

        $columns = [
            'order_id',
            'billing_first_name', 'billing_last_name', 'billing_company',
            'billing_address_1', 'billing_address_2', 'billing_city',
            'billing_zip', 'billing_zone_id', 'billing_zone_name',
            'billing_country_id', 'billing_country_name',
            'billing_phone_1', 'billing_phone_2', 'billing_fax',
            'billing_tax_number',
            'shipping_first_name', 'shipping_last_name', 'shipping_company',
            'shipping_address_1', 'shipping_address_2', 'shipping_city',
            'shipping_zip', 'shipping_zone_id', 'shipping_zone_name',
            'shipping_country_id', 'shipping_country_name',
            'shipping_phone_1', 'shipping_phone_2', 'shipping_fax',
            'shipping_tax_number',
            'all_billing', 'all_shipping', 'all_payment',
        ];

        $values = [
            $db->quote($orderId),
            $db->quote($billing['first_name'] ?? ''),
            $db->quote($billing['last_name'] ?? ''),
            $db->quote($billing['company'] ?? ''),
            $db->quote($billing['address_1'] ?? ''),
            $db->quote($billing['address_2'] ?? ''),
            $db->quote($billing['city'] ?? ''),
            $db->quote($billing['zip'] ?? ''),
            (int) ($billing['zone_id'] ?? 0),
            $db->quote($billing['zone_name'] ?? ''),
            (int) ($billing['country_id'] ?? 0),
            $db->quote($billing['country_name'] ?? ''),
            $db->quote($billing['phone_1'] ?? ''),
            $db->quote($billing['phone_2'] ?? ''),
            $db->quote($billing['fax'] ?? ''),
            $db->quote($billing['tax_number'] ?? ''),
            $db->quote($shipping['first_name'] ?? ''),
            $db->quote($shipping['last_name'] ?? ''),
            $db->quote($shipping['company'] ?? ''),
            $db->quote($shipping['address_1'] ?? ''),
            $db->quote($shipping['address_2'] ?? ''),
            $db->quote($shipping['city'] ?? ''),
            $db->quote($shipping['zip'] ?? ''),
            (int) ($shipping['zone_id'] ?? 0),
            $db->quote($shipping['zone_name'] ?? ''),
            (int) ($shipping['country_id'] ?? 0),
            $db->quote($shipping['country_name'] ?? ''),
            $db->quote($shipping['phone_1'] ?? ''),
            $db->quote($shipping['phone_2'] ?? ''),
            $db->quote($shipping['fax'] ?? ''),
            $db->quote($shipping['tax_number'] ?? ''),
            $db->quote(json_encode($billing)),
            $db->quote(json_encode($shipping)),
            $db->quote($paymentCustom !== [] ? json_encode($paymentCustom) : '{}'),
        ];

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_orderinfos'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Load address data from session (address_id or guest data).
     *
     * @param   string  $type        Address type: 'billing' or 'shipping'.
     * @param   object  $session     Session object.
     * @param   object  $mvcFactory  MVC factory.
     * @param   int     $userId      User ID.
     *
     * @return  array  Address fields as associative array.
     *
     * @since   6.0.6
     */
    protected function loadAddressData(string $type, object $session, object $mvcFactory, int $userId): array
    {
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $data = [];

        $addressId = (int) $session->get($type . '_address_id', 0, 'j2commerce');

        if ($addressId > 0) {
            // Load from addresses table
            $addressTable = $mvcFactory->createTable('Address', 'Administrator');

            if ($addressTable && $addressTable->load($addressId)) {
                // Hold the row to the account the resolver was called for, matching
                // TaxHelper::getCustomerAddress() and ShippingStandard::getShippingGeozones().
                // Guest rows carry user_id = 0, so the match is only meaningful for a real account.
                if ($userId > 0 && (int) ($addressTable->user_id ?? 0) !== $userId) {
                    return [];
                }

                $data = [
                    'first_name' => $addressTable->first_name ?? '',
                    'last_name'  => $addressTable->last_name ?? '',
                    'company'    => $addressTable->company ?? '',
                    'address_1'  => $addressTable->address_1 ?? '',
                    'address_2'  => $addressTable->address_2 ?? '',
                    'city'       => $addressTable->city ?? '',
                    'zip'        => $addressTable->zip ?? '',
                    'zone_id'    => (int) ($addressTable->zone_id ?? 0),
                    'country_id' => (int) ($addressTable->country_id ?? 0),
                    'phone_1'    => $addressTable->phone_1 ?? '',
                    'phone_2'    => $addressTable->phone_2 ?? '',
                    'fax'        => $addressTable->fax ?? '',
                    'tax_number' => $addressTable->tax_number ?? '',
                    'email'      => $addressTable->email ?? '',
                ];
            }
        } else {
            // Guest checkout — use session data
            $sessionKey = ($type === 'shipping') ? 'guest_shipping' : 'guest';
            $guestData  = $session->get($sessionKey, [], 'j2commerce');

            if (!empty($guestData)) {
                $data = [
                    'first_name' => $guestData['first_name'] ?? '',
                    'last_name'  => $guestData['last_name'] ?? '',
                    'company'    => $guestData['company'] ?? '',
                    'address_1'  => $guestData['address_1'] ?? '',
                    'address_2'  => $guestData['address_2'] ?? '',
                    'city'       => $guestData['city'] ?? '',
                    'zip'        => $guestData['zip'] ?? '',
                    'zone_id'    => (int) ($guestData['zone_id'] ?? 0),
                    'country_id' => (int) ($guestData['country_id'] ?? 0),
                    'phone_1'    => $guestData['phone_1'] ?? $guestData['phone'] ?? '',
                    'phone_2'    => $guestData['phone_2'] ?? '',
                    'fax'        => $guestData['fax'] ?? '',
                    'tax_number' => $guestData['tax_number'] ?? '',
                    'email'      => $guestData['email'] ?? '',
                ];
            }
        }

        // Resolve country and zone names
        $countryId = (int) ($data['country_id'] ?? 0);
        $zoneId    = (int) ($data['zone_id'] ?? 0);

        if ($countryId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('country_name'))
                ->from($db->quoteName('#__j2commerce_countries'))
                ->where($db->quoteName('j2commerce_country_id') . ' = :cid')
                ->bind(':cid', $countryId, ParameterType::INTEGER);
            $db->setQuery($query);
            $data['country_name'] = $db->loadResult() ?? '';
        }

        if ($zoneId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName('zone_name'))
                ->from($db->quoteName('#__j2commerce_zones'))
                ->where($db->quoteName('j2commerce_zone_id') . ' = :zid')
                ->bind(':zid', $zoneId, ParameterType::INTEGER);
            $db->setQuery($query);
            $data['zone_name'] = $db->loadResult() ?? '';
        }

        return $data;
    }

    /**
     * Save order tax rates.
     *
     * @param   DatabaseInterface  $db       Database driver.
     * @param   string             $orderId  The order_id string.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function saveOrderTaxes(DatabaseInterface $db, string $orderId): void
    {
        // The persisted rows are the ones the confirmation page, the invoice and the order
        // emails read back, so they are built by the same method the live cart displays from —
        // including whether shipping tax belongs inside them or on a line of its own.
        foreach ($this->buildDisplayTaxRates() as $taxRate) {
            $taxAmount = (float) ($taxRate->tax_amount ?? 0);

            if ($taxAmount <= 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_ordertaxes'))
                ->columns($db->quoteName(['order_id', 'ordertax_title', 'ordertax_percent', 'ordertax_amount']))
                ->values(implode(',', [
                    $db->quote($orderId),
                    $db->quote($taxRate->taxprofile_name ?: ($taxRate->taxrate_name ?? 'Tax')),
                    (float) ($taxRate->tax_percent ?? 0),
                    $taxAmount,
                ]));

            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Save order shipping.
     *
     * @param   DatabaseInterface  $db       Database driver.
     * @param   string             $orderId  The order_id string.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function saveOrderShipping(DatabaseInterface $db, string $orderId): void
    {
        if ($this->shippingRate === null) {
            return;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__j2commerce_ordershippings'))
            ->columns($db->quoteName([
                'order_id', 'ordershipping_type', 'ordershipping_price',
                'ordershipping_name', 'ordershipping_code',
                'ordershipping_tax', 'ordershipping_extra',
                'ordershipping_tracking_id',
            ]))
            ->values(implode(',', [
                $db->quote($orderId),
                $db->quote($this->shippingRate->ordershipping_plugin ?? ''),
                (float) ($this->shippingRate->ordershipping_price ?? 0),
                $db->quote($this->shippingRate->ordershipping_name ?? ''),
                $db->quote($this->shippingRate->ordershipping_code ?? ''),
                (float) ($this->shippingRate->ordershipping_tax ?? 0),
                (float) ($this->shippingRate->ordershipping_extra ?? 0),
                $db->quote(''),
            ]));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Save order discounts (coupons and vouchers).
     *
     * @param   DatabaseInterface  $db         Database driver.
     * @param   string             $orderId    The order_id string.
     * @param   int                $userId     User ID.
     * @param   string             $userEmail  User email.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function saveOrderDiscounts(
        DatabaseInterface $db,
        string $orderId,
        int $userId,
        string $userEmail
    ): void {
        $allDiscounts = [];

        foreach ($this->coupons as $coupon) {
            $allDiscounts[] = [
                'type'       => 'coupon',
                'entity_id'  => (int) ($coupon->coupon_id ?? 0),
                'title'      => $coupon->coupon_name ?? $coupon->coupon_code ?? '',
                'code'       => $coupon->coupon_code ?? '',
                'amount'     => (float) ($coupon->discount ?? 0),
                'tax'        => 0.0,
                'value_type' => 'fixed',
            ];
        }

        foreach ($this->vouchers as $voucher) {
            $allDiscounts[] = [
                'type'       => 'voucher',
                'entity_id'  => (int) ($voucher->voucher_id ?? 0),
                'title'      => $voucher->voucher_name ?? $voucher->voucher_code ?? '',
                'code'       => $voucher->voucher_code ?? '',
                'amount'     => (float) ($voucher->discount ?? 0),
                'tax'        => 0.0,
                'value_type' => 'fixed',
            ];
        }

        // Persist plugin cart-level discounts (paymentdiscount, bulkdiscount, bogoboost, etc.).
        // Each code in coupon_discount_amounts that isn't a *_title sidecar and has a positive
        // amount gets its own orderdiscounts row, using the optional <code>_title sidecar value.
        foreach ($this->coupon_discount_amounts as $code => $amount) {
            if (!\is_string($code) || str_ends_with($code, '_title')) {
                continue;
            }

            if (!is_numeric($amount) || (float) $amount <= 0) {
                continue;
            }

            $title = (string) ($this->coupon_discount_amounts[$code . '_title']
                ?? ($code === 'bulk_discount' ? Text::_('COM_J2COMMERCE_CART_BULK_DISCOUNT') : $code));

            $allDiscounts[] = [
                'type'       => 'cart_discount',
                'entity_id'  => 0,
                'title'      => $title,
                'code'       => $code,
                'amount'     => (float) $amount,
                'tax'        => (float) ($this->coupon_discount_tax_amounts[$code] ?? 0),
                'value_type' => 'fixed',
            ];
        }

        foreach ($allDiscounts as $discount) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_orderdiscounts'))
                ->columns($db->quoteName([
                    'order_id', 'discount_type', 'discount_entity_id',
                    'discount_title', 'discount_code', 'discount_value',
                    'discount_value_type', 'discount_customer_email',
                    'user_id', 'discount_amount', 'discount_tax', 'discount_params',
                ]))
                ->values(implode(',', [
                    $db->quote($orderId),
                    $db->quote($discount['type']),
                    (int) $discount['entity_id'],
                    $db->quote($discount['title']),
                    $db->quote($discount['code']),
                    $db->quote((string) $discount['amount']),
                    $db->quote($discount['value_type']),
                    $db->quote($userEmail),
                    $userId,
                    (float) $discount['amount'],
                    (float) ($discount['tax'] ?? 0),
                    $db->quote('{}'),
                ]));

            $db->setQuery($query);
            $db->execute();
        }
    }

    protected function saveOrderFees(DatabaseInterface $db, string $orderId): void
    {
        $fees = $this->get_fees();

        foreach ($fees as $fee) {
            $amount = (float) ($fee->amount ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $name         = $fee->name ?? '';
            $tax          = (float) ($fee->tax ?? 0);
            $taxProfileId = (int) ($fee->taxprofile_id ?? 0);
            $taxable      = $tax > 0 ? 1 : 0;
            $feeType      = $fee->plugin ?? '';

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_orderfees'))
                ->columns($db->quoteName([
                    'order_id', 'name', 'amount', 'tax_class_id', 'taxable', 'tax', 'tax_data', 'fee_type',
                ]))
                ->values(implode(',', [
                    $db->quote($orderId),
                    $db->quote($name),
                    $amount,
                    $taxProfileId,
                    $taxable,
                    $tax,
                    $db->quote('{}'),
                    $db->quote($feeType),
                ]));

            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Get formatted order totals for display.
     *
     * Returns an array of totals with label and formatted value for display
     * in the cart/checkout summary. Keys are semantically named to allow plugins
     * to target specific totals (subtotal, shipping, tax_*, grandtotal, etc.).
     *
     * @return  array  Array of totals with 'label' and 'value' keys.
     *
     * @since   6.0.6
     */
    public function get_formatted_order_totals(): array
    {
        $totals               = [];
        $currency             = J2CommerceHelper::currency();
        $params               = J2CommerceHelper::config();
        $combineTax           = (int) $params->get('combine_tax_calculations', 1);
        $checkoutPriceDisplay = (int) $params->get('checkout_price_display_options', 0);

        // Subtotal row — when showing inclusive prices, sum displayed line-item totals
        // so the subtotal matches what the customer sees (regardless of geozone resolution)
        if ($checkoutPriceDisplay && !empty($this->items)) {
            $displaySubtotal = 0.0;
            foreach ($this->items as $item) {
                $displaySubtotal += $this->get_formatted_lineitem_total($item, $checkoutPriceDisplay);
            }
        } else {
            $displaySubtotal = $this->order_subtotal;
        }

        $totals['subtotal'] = [
            'label' => Text::_('COM_J2COMMERCE_CART_SUBTOTAL'),
            'value' => $currency->format($displaySubtotal),
        ];

        // Shipping - placed before tax
        if ($this->order_shipping > 0 || $this->shippingRate !== null) {
            $shippingLabel = Text::_('COM_J2COMMERCE_CART_SHIPPING');
            if ($this->shippingRate && !empty($this->shippingRate->ordershipping_name)) {
                $shippingLabel = Text::_(stripslashes($this->shippingRate->ordershipping_name));
            }

            $totals['shipping'] = [
                'label' => $shippingLabel,
                'value' => $currency->format($this->order_shipping),
            ];

            // Shipping tax as separate line — only when NOT combining taxes
            if ($this->order_shipping_tax > 0 && !$combineTax) {
                $totals['shipping_tax'] = [
                    'label' => Text::_('COM_J2COMMERCE_ORDER_SHIPPING_TAX'),
                    'value' => $currency->format($this->order_shipping_tax),
                ];
            }
        }

        // Fees - allow plugins to add custom fees via session
        $fees = $this->get_fees();
        if (!empty($fees)) {
            foreach ($fees as $fee) {
                $feeKey          = 'fee_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($fee->name ?? 'custom'));
                $totals[$feeKey] = [
                    'label' => Text::_($fee->name),
                    'value' => $currency->format($this->get_formatted_fees($fee, $checkoutPriceDisplay)),
                ];
            }
        }

        // Surcharge (deprecated — only show if no individual fees were rendered above)
        if ($this->order_surcharge > 0 && empty($fees)) {
            $totals['surcharge'] = [
                'label' => Text::_('COM_J2COMMERCE_CART_SURCHARGE'),
                'value' => $currency->format($this->order_surcharge),
            ];
        }

        // Coupons/Discounts - with remove links
        $discounts = $this->getOrderDiscounts();
        if (!empty($discounts)) {
            foreach ($discounts as $discount) {
                $discountAmount = (float) ($discount->discount_amount ?? 0);
                if ($discountAmount > 0) {
                    $link          = '';
                    $discountType  = $discount->discount_type ?? '';
                    $discountTitle = $discount->discount_title ?? $discount->discount_code ?? '';

                    if ($discountType === 'coupon') {
                        $label = $discountTitle;
                        $link  = '<a class="j2commerce-remove j2commerce-remove-coupon remove-icon text-danger ms-2 text-decoration-none" '
                            . 'href="javascript:void(0)" title="' . Text::_('COM_J2COMMERCE_REMOVE_COUPON') . '">x</a>';
                    } elseif ($discountType === 'voucher') {
                        $label = $discountTitle;
                        $link  = '<a class="j2commerce-remove j2commerce-remove-voucher remove-icon text-danger ms-2 text-decoration-none" '
                            . 'href="javascript:void(0)" title="' . Text::_('COM_J2COMMERCE_REMOVE_VOUCHER') . '">x</a>';
                    } else {
                        $label = $discountTitle;
                    }

                    // Allow plugins to add content after discount title
                    $label .= J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayDiscountTitle', [$this, $discount]);

                    $value = '-' . $currency->format($discountAmount);
                    // Allow plugins to add content after discount amount
                    $value .= J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayDiscountAmount', [$this, $discount]);

                    $discountKey          = $discountType . '_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($discount->discount_code ?? 'discount'));
                    $totals[$discountKey] = [
                        'label' => $label,
                        'link'  => $link,
                        'value' => $value,
                    ];
                }
            }
        }

        // Tax totals — always show Tax Profile Name; combine shipping tax when enabled
        $displayRates = $this->buildDisplayTaxRates();

        if (!empty($displayRates)) {
            foreach ($displayRates as $key => $taxRate) {
                $taxAmount = (float) ($taxRate->tax_amount ?? 0);
                // Use Tax Profile Name with Tax Rate Name as fallback
                $taxTitle = $taxRate->taxprofile_name ?? $taxRate->taxrate_name ?? Text::_('COM_J2COMMERCE_CART_TAX');

                // Skip empty profile names — fall back to taxrate_name
                if (empty(trim($taxTitle))) {
                    $taxTitle = $taxRate->taxrate_name ?? Text::_('COM_J2COMMERCE_CART_TAX');
                }

                $taxPercent = (float) ($taxRate->tax_percent ?? 0);

                if ($taxAmount > 0) {
                    // Format label based on price display option (included or excluded)
                    if ($checkoutPriceDisplay) {
                        $label = Text::sprintf('COM_J2COMMERCE_CART_TAX_INCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%');
                    } else {
                        $label = Text::sprintf('COM_J2COMMERCE_CART_TAX_EXCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%');
                    }

                    $taxKey          = 'tax_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($taxTitle)) . '_' . $key;
                    $totals[$taxKey] = [
                        'label' => $label,
                        'value' => $currency->format($taxAmount),
                    ];
                }
            }
        }

        // Grand Total
        $totals['grandtotal'] = [
            'label' => Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'),
            'value' => $currency->format($this->order_total),
        ];

        // Allow plugins to modify or add custom totals
        // This enables 3rd party plugins to add fees, discounts, or other line items
        // Plugins can:
        //   - Add new totals: $totals['custom_fee'] = ['label' => 'Fee', 'value' => '$5.00']
        //   - Modify existing: $totals['subtotal']['value'] = '$100.00'
        //   - Remove totals: unset($totals['shipping'])
        //   - Reorder totals: use array manipulation
        J2CommerceHelper::plugin()->event('GetFormattedOrderTotals', [$this, &$totals]);

        return $totals;
    }

    /**
     * Track discount amount by discount code.
     *
     * Used by bulk discount and other product-level discount plugins
     * to accumulate discount totals by discount type/code.
     *
     * @param   string  $code   Discount code (e.g., 'bulk_discount', 'volume_discount').
     * @param   float   $amount Discount amount (excluding tax).
     * @param   float   $tax    Discount tax amount.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function increase_coupon_discount_amount(string $code, float $amount, float $tax = 0.0): void
    {
        if (!isset($this->coupon_discount_amounts[$code])) {
            $this->coupon_discount_amounts[$code]     = 0.0;
            $this->coupon_discount_tax_amounts[$code] = 0.0;
        }

        $this->coupon_discount_amounts[$code] += $amount;
        $this->coupon_discount_tax_amounts[$code] += $tax;

        // Also track cumulative cart-level discount
        $this->discount_cart += $amount;
        $this->discount_cart_tax += $tax;
    }

    /**
     * Get discount amount for a specific discount code.
     *
     * @param   string  $code     Discount code.
     * @param   bool    $ex_tax   True to exclude tax, false to include tax.
     *
     * @return  float  Discount amount.
     *
     * @since   6.0.6
     */
    public function get_coupon_discount_amount(string $code, bool $ex_tax = true): float
    {
        $amount = $this->coupon_discount_amounts[$code] ?? 0.0;

        if (!$ex_tax) {
            $amount += $this->get_coupon_discount_tax_amount($code);
        }

        return $amount;
    }

    /**
     * Get discount tax amount for a specific discount code.
     *
     * @param   string  $code  Discount code.
     *
     * @return  float  Discount tax amount.
     *
     * @since   6.0.6
     */
    public function get_coupon_discount_tax_amount(string $code): float
    {
        return $this->coupon_discount_tax_amounts[$code] ?? 0.0;
    }

    /**
     * Add a discount object to the order.
     *
     * Used by plugins to register discount totals that will appear
     * in get_formatted_order_totals() and get_order_discounts().
     *
     * @param   object  $discount  Discount object with discount_type, discount_code,
     *                              discount_title, discount_amount, discount_tax.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    public function addOrderDiscounts(object $discount): void
    {
        // Track by discount code
        $code   = $discount->discount_code ?? 'unknown';
        $amount = (float) ($discount->discount_amount ?? 0);
        $tax    = (float) ($discount->discount_tax ?? 0);

        // increase_coupon_discount_amount() already books the amount into $discount_cart,
        // which recalculateTaxAfterDiscounts() subtracts. Adding it to $order_discount here
        // as well put the same money in both accumulators and took it off order_total twice.
        $this->increase_coupon_discount_amount($code, $amount, $tax);
    }

    /**
     * Dispatch CalculateDiscountTotals event for plugins.
     *
     * Called after coupons and vouchers are loaded to allow
     * product-level discount plugins (bulk, volume) to add their totals.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function calculateDiscountTotals(): void
    {
        // Allow plugins to add discount totals (bulk discounts, volume discounts, etc.)
        J2CommerceHelper::plugin()->event('CalculateDiscountTotals', [$this]);

        // Any discount shrinks the taxable base, so a coupon-only cart needs this pass too —
        // gating it on the plugin half alone left those carts taxed on the undiscounted subtotal.
        if (($this->order_discount + $this->discount_cart) > 0) {
            $this->recalculateTaxAfterDiscounts();
        }
    }
}
