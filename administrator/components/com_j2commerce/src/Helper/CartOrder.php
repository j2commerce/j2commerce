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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

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
     * Validate order stock availability.
     *
     * Checks if all items in the order have sufficient stock.
     *
     * @return  bool  True if stock is valid.
     *
     * @since   6.0.6
     */
    public function validate_order_stock(): bool
    {
        foreach ($this->items as $item) {
            // Get variant for stock check
            if (!empty($item->variant_id)) {
                $variantId = (int) $item->variant_id;
                $quantity  = (int) ($item->product_qty ?? 1);

                // Load variant object for stock check using Table directly
                try {
                    $mvcFactory = Factory::getApplication()
                        ->bootComponent('com_j2commerce')
                        ->getMVCFactory();
                    $variantTable = $mvcFactory->createTable('Variant', 'Administrator');

                    if ($variantTable && $variantTable->load($variantId)) {
                        // Create variant object from table data
                        $variant = (object) $variantTable->getProperties();

                        // Check if stock management is enabled
                        $manageStock = ProductHelper::managingStock($variant);
                        $stock       = ProductHelper::getStockQuantity($variantId);

                        if ($manageStock && $stock < $quantity) {
                            $item->stock_error = Text::sprintf(
                                'COM_J2COMMERCE_CART_ITEM_STOCK_NOT_ENOUGH_STOCK',
                                $item->product_name ?? '',
                                $stock
                            );
                        }
                    }
                } catch (\Exception $e) {
                    // Silent fail - stock validation skipped
                }
            }
        }

        return true;
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
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $taxRates = [];

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

            if ($pricing) {
                $itemPrice = (float) ($pricing->price ?? 0) + (float) ($item->option_price ?? 0);

                // Allow plugins to modify item price and add discounts
                // onJ2CommerceGetDiscountedPrice signature: (&$price, &$item, $add_totals, &$order)
                // Plugins can modify $price by reference and set $item->orderitem_discount
                J2CommerceHelper::plugin()->event('onJ2CommerceGetDiscountedPrice', [
                    &$itemPrice,
                    &$item,
                    true, // $add_totals - accumulate discount totals
                    $this, // $order - the CartOrder object
                ]);

                $subtotal += $itemPrice * $quantity;

                // Calculate tax using taxprofile_id and customer geozone
                $taxprofileId = (int) ($item->taxprofile_id ?? 0);

                if ($taxprofileId > 0 && !empty($customerGeozones)) {
                    $taxInfo = $this->getTaxRateForGeozone($taxprofileId, $customerGeozones);

                    if ($taxInfo !== null) {
                        $itemTax = $itemPrice * $quantity * ((float) $taxInfo->tax_percent / 100);
                        $taxTotal += $itemTax;

                        // Store per-item tax for line item display
                        $item->orderitem_tax         = $itemTax;
                        $item->orderitem_tax_percent = (float) $taxInfo->tax_percent;

                        $rateKey = $taxInfo->taxrate_name . '_' . $taxInfo->j2commerce_taxrate_id;

                        if (!isset($taxRates[$rateKey])) {
                            $taxRates[$rateKey] = (object) [
                                'taxprofile_id'   => $taxprofileId,
                                'taxprofile_name' => $taxInfo->taxprofile_name ?? '',
                                'taxrate_name'    => $taxInfo->taxrate_name,
                                'tax_amount'      => 0.0,
                                'tax_percent'     => (float) $taxInfo->tax_percent,
                            ];
                        }

                        $taxRates[$rateKey]->tax_amount += $itemTax;
                    } else {
                        $item->orderitem_tax         = 0.0;
                        $item->orderitem_tax_percent = 0.0;
                    }
                } else {
                    $item->orderitem_tax         = 0.0;
                    $item->orderitem_tax_percent = 0.0;
                }
            } else {
                $itemPrice = (float) ($item->price ?? $item->variant_price ?? 0) + (float) ($item->option_price ?? 0);

                // Allow plugins to modify item price even for items without pricing object
                J2CommerceHelper::plugin()->event('onJ2CommerceGetDiscountedPrice', [
                    &$itemPrice,
                    &$item,
                    true,
                    $this,
                ]);

                $subtotal += $itemPrice * $quantity;
                $item->orderitem_tax         = 0.0;
                $item->orderitem_tax_percent = 0.0;
            }
        }

        $this->order_subtotal = $subtotal;
        $this->order_tax      = $taxTotal;
        $this->taxRates       = array_values($taxRates);
        $this->order_total    = $subtotal + $taxTotal;
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
        // Combine order_discount (coupons/vouchers) and discount_cart (bulk discounts)
        $totalDiscount = $this->order_discount + $this->discount_cart;

        // No discounts or no subtotal — nothing to adjust
        if ($totalDiscount <= 0 || $this->order_subtotal <= 0) {
            return;
        }

        // Net taxable amount after all discounts (floored at zero)
        $netTaxable = max(0.0, $this->order_subtotal - $totalDiscount);
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

        // Subtract both the discount amount AND the tax reduction from total
        // order_total was set to (subtotal + tax) in calculateTotals()
        // After discount: total = (subtotal - discount) + (tax - taxReduction)
        // So we need to subtract: discount + taxReduction
        $this->order_total -= ($totalDiscount + $taxReduction);
    }

    private function getCustomerGeozones(): array
    {
        $session   = Factory::getApplication()->getSession();
        $countryId = 0;
        $zoneId    = 0;

        // Priority 1: saved shipping address
        $addressId = (int) $session->get('shipping_address_id', 0, 'j2commerce');

        if ($addressId > 0) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('country_id'), $db->quoteName('zone_id')])
                ->from($db->quoteName('#__j2commerce_addresses'))
                ->where($db->quoteName('j2commerce_address_id') . ' = :addrId')
                ->bind(':addrId', $addressId, ParameterType::INTEGER);

            $db->setQuery($query);
            $address = $db->loadObject();

            if ($address) {
                $countryId = (int) ($address->country_id ?? 0);
                $zoneId    = (int) ($address->zone_id ?? 0);
            }
        }

        // Priority 2: guest shipping address
        if ($countryId === 0) {
            $guestShipping = $session->get('guest_shipping', [], 'j2commerce');

            if (!empty($guestShipping) && \is_array($guestShipping)) {
                $countryId = (int) ($guestShipping['country_id'] ?? 0);
                $zoneId    = (int) ($guestShipping['zone_id'] ?? 0);
            }
        }

        // Priority 3: estimate flow flat session keys
        if ($countryId === 0) {
            $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
            $zoneId    = (int) $session->get('shipping_zone_id', 0, 'j2commerce');
        }

        if ($countryId === 0) {
            return [];
        }

        // Query geozonerules for matching geozones
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozonerules'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where(
                '(' . $db->quoteName('zone_id') . ' = 0 OR '
                . $db->quoteName('zone_id') . ' = :zoneId)'
            )
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadColumn() ?: [];
    }

    private function getTaxRateForGeozone(int $taxprofileId, array $geozoneIds): ?object
    {
        if (empty($geozoneIds)) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('rt.j2commerce_taxrate_id'),
                $db->quoteName('rt.taxrate_name'),
                $db->quoteName('rt.tax_percent'),
                $db->quoteName('rt.geozone_id'),
                $db->quoteName('tp.taxprofile_name'),
            ])
            ->from($db->quoteName('#__j2commerce_taxrules', 'tr'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_taxrates', 'rt'),
                $db->quoteName('rt.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tr.taxrate_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_taxprofiles', 'tp'),
                $db->quoteName('tp.j2commerce_taxprofile_id') . ' = ' . $db->quoteName('tr.taxprofile_id')
            )
            ->where($db->quoteName('tr.taxprofile_id') . ' = :profileId')
            ->whereIn($db->quoteName('rt.geozone_id'), array_map('intval', $geozoneIds))
            ->bind(':profileId', $taxprofileId, ParameterType::INTEGER)
            ->order($db->quoteName('tr.ordering') . ' ASC');

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
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
                            // Flag as expired — defer session removal to checkout validation
                            $this->coupons[] = (object) [
                                'coupon_code' => $couponCode,
                                'coupon_id'   => $coupon->j2commerce_coupon_id ?? 0,
                                'discount'    => 0.0,
                                'coupon_name' => $coupon->coupon_name ?? $couponCode,
                                'is_expired'  => true,
                                'error'       => $couponModel->getError(),
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

                        $balance  = (float) ($voucher->balance ?? 0);
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
            $shippingPrice = (float) ($shippingValues['shipping_price'] ?? 0);
            $shippingTax   = (float) ($shippingValues['shipping_tax'] ?? 0);
            $shippingExtra = (float) ($shippingValues['shipping_extra'] ?? 0);

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
            $this->order_total += $this->order_shipping + $this->order_shipping_tax;
        }
    }

    /**
     * Add a fee to the order (stored in session, keyed to prevent duplicates).
     */
    public static function addFee(string $key, float $amount, string $label, float $tax = 0.0): void
    {
        $session = Factory::getApplication()->getSession();
        $fees    = $session->get('order_fees', [], 'j2commerce');

        $fees[$key] = [
            'name'   => $label,
            'amount' => $amount,
            'tax'    => $tax,
            'plugin' => $key,
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
        self::addFee($key, $amount, $name);
    }

    /** Legacy compatibility — called by payment plugins via $order->get_payment_method(). */
    public function get_payment_method(): string
    {
        return $this->orderpayment_type;
    }

    protected function loadFees(): void
    {
        $fees = $this->get_fees();

        foreach ($fees as $fee) {
            $this->order_surcharge += (float) $fee->amount;
            $this->order_total += (float) $fee->amount + (float) $fee->tax;
        }
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
            $decoded = @unserialize(base64_decode($item->product_options));

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

        // Add tax when displayMode requests tax-inclusive display
        if ($displayMode == 1) {
            $taxProfileId = (int) ($item->taxprofile_id ?? $item->orderitem_taxprofile_id ?? 0);
            if ($taxProfileId > 0) {
                $productHelper = new ProductHelper();
                $unitPrice     = $productHelper->get_price_including_tax($unitPrice, $taxProfileId);
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

        // Add cart-level discounts (bulk discounts, etc.) from plugins
        if ($this->discount_cart > 0) {
            $bulkDiscountTitle = $this->coupon_discount_amounts['bulk_discount_title']
                ?? Text::_('COM_J2COMMERCE_CART_BULK_DISCOUNT');

            $discounts[] = (object) [
                'discount_type'   => 'cart_discount',
                'discount_code'   => 'bulk_discount',
                'discount_title'  => $bulkDiscountTitle,
                'discount_amount' => $this->discount_cart,
            ];
        }

        return $discounts;
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
        $app     = Factory::getApplication();
        $session = $app->getSession();
        $user    = $app->getIdentity();
        $db      = Factory::getContainer()->get(DatabaseInterface::class);

        $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();

        // Gather user info
        $userId    = ($user && $user->id) ? (int) $user->id : 0;
        $userEmail = '';

        if ($userId > 0) {
            $userEmail = $user->email;
        } else {
            // Guest checkout — get email from session guest data
            $guestData = $session->get('guest', [], 'j2commerce');
            $userEmail = $guestData['email'] ?? '';
        }

        // Fallback: resolve email from billing address if still empty
        if (empty($userEmail)) {
            $billingAddressId = (int) $session->get('billing_address_id', 0, 'j2commerce');

            if ($billingAddressId > 0) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('email'))
                    ->from($db->quoteName('#__j2commerce_addresses'))
                    ->where($db->quoteName('j2commerce_address_id') . ' = :addrId')
                    ->bind(':addrId', $billingAddressId, ParameterType::INTEGER);
                $db->setQuery($query);
                $userEmail = $db->loadResult() ?? '';
            }
        }

        // Last resort: use Joomla user email for logged-in users whose identity was resolved late
        if (empty($userEmail) && $userId > 0) {
            $userFactory = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class);
            $loadedUser  = $userFactory->loadUserById($userId);
            $userEmail   = $loadedUser->email ?? '';
        }

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
        $invoicePrefix  = $params->get('invoice_prefix', 'INV-');
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

        $orderData = [
            'user_id'               => $userId,
            'user_email'            => $userEmail,
            'cart_id'               => $this->cart_id,
            'order_total'           => $this->order_total,
            'order_subtotal'        => $this->order_subtotal,
            'order_subtotal_ex_tax' => $this->order_subtotal,
            'order_tax'             => $this->order_tax,
            'order_shipping'        => $this->order_shipping,
            'order_shipping_tax'    => $this->order_shipping_tax,
            'order_discount'        => $this->order_discount,
            'order_surcharge'       => $this->order_surcharge,
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
            'ip_address'            => $app->input->server->getString('REMOTE_ADDR', ''),
            'order_state_id'        => 5, // Incomplete
            'created_by'            => $userId,
            'modified_by'           => $userId,
        ];

        if (!$orderTable->bind($orderData) || !$orderTable->check() || !$orderTable->store()) {
            throw new \RuntimeException(
                Text::sprintf('COM_J2COMMERCE_ORDER_SAVE_ERROR', $orderTable->getError())
            );
        }

        // Two-pass save:
        // First store() gets the auto-increment PK, then we generate
        // order_id = time() . PK and token, then store() again.
        $orderTable->order_id = $orderTable->generateOrderId();
        $orderTable->token    = $orderTable->generateToken();

        if (!$orderTable->store()) {
            throw new \RuntimeException(
                Text::sprintf('COM_J2COMMERCE_ORDER_SAVE_ERROR', $orderTable->getError())
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

        // Save order discounts (coupons + vouchers)
        $this->saveOrderDiscounts($db, $orderId, $userId, $userEmail);

        // Save order fees (surcharges with names)
        $this->saveOrderFees($db, $orderId);

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
        $now = Factory::getDate()->toSql();

        foreach ($this->items as $item) {
            $pricing           = $item->pricing ?? null;
            $quantity          = (int) ($item->product_qty ?? 1);
            $basePrice         = (float) ($pricing->price ?? $item->variant_price ?? 0);
            $optionPrice       = (float) ($item->option_price ?? 0);
            $perItemTax        = (float) ($pricing->tax ?? 0);
            $itemTax           = $perItemTax * $quantity;
            $finalPrice        = ($basePrice + $optionPrice) * $quantity;
            $finalPriceWithTax = $finalPrice + $itemTax;

            // Serialize item attributes for storage
            $attributes = '';
            if (!empty($item->orderitemattributes)) {
                $attributes = json_encode($item->orderitemattributes);
            } elseif (!empty($item->product_options)) {
                $attributes = $item->product_options;
            }

            $columns = [
                'order_id', 'orderitem_type', 'cart_id', 'cartitem_id',
                'product_id', 'product_type', 'variant_id', 'vendor_id',
                'orderitem_sku', 'orderitem_name', 'orderitem_attributes',
                'orderitem_quantity', 'orderitem_taxprofile_id',
                'orderitem_per_item_tax', 'orderitem_tax',
                'orderitem_discount', 'orderitem_discount_tax',
                'orderitem_price', 'orderitem_option_price',
                'orderitem_finalprice', 'orderitem_finalprice_with_tax',
                'orderitem_finalprice_without_tax', 'orderitem_params',
                'created_on', 'created_by',
                'orderitem_weight', 'orderitem_weight_total',
            ];

            $values = [
                $db->quote($orderId),
                $db->quote('normal'),
                (int) ($this->cart_id),
                (int) ($item->j2commerce_cartitem_id ?? $item->cartitem_id ?? 0),
                (int) ($item->product_id ?? 0),
                $db->quote($item->product_type ?? 'simple'),
                (int) ($item->variant_id ?? 0),
                (int) ($item->vendor_id ?? 0),
                $db->quote($item->sku ?? ''),
                $db->quote($item->product_name ?? $item->orderitem_name ?? ''),
                $db->quote($attributes),
                $db->quote((string) $quantity),
                (int) ($item->taxprofile_id ?? $pricing->taxprofile_id ?? 0),
                $perItemTax,
                $itemTax,
                0, // orderitem_discount
                0, // orderitem_discount_tax
                $basePrice,
                $optionPrice,
                $finalPrice,
                $finalPriceWithTax,
                $finalPrice, // without tax
                $db->quote($item->cartitem_params ?? $item->orderitem_params ?? '{}'),
                $db->quote($now),
                $userId,
                $db->quote((string) ($item->weight ?? 0)),
                $db->quote((string) (($item->weight ?? 0) * $quantity)),
            ];

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__j2commerce_orderitems'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($query);
            $db->execute();
        }
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
            $db->quote('{}'),
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
        foreach ($this->taxRates as $taxRate) {
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
                    0, // discount_tax
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
        $totals     = [];
        $currency   = J2CommerceHelper::currency();
        $params     = J2CommerceHelper::config();
        $combineTax = (int) $params->get('combine_tax_calculations', 1);

        // Subtotal (before tax) - use keyed index so plugins can target it
        $totals['subtotal'] = [
            'label' => Text::_('COM_J2COMMERCE_CART_SUBTOTAL'),
            'value' => $currency->format($this->order_subtotal),
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
            $checkoutPriceDisplay = (int) $params->get('checkout_price_display_options', 0);
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
                        $link  = '<a class="j2commerce-remove j2commerce-remove-coupon remove-icon text-danger ms-2 text-decoration-none" href="#" data-id=" '
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
        if (!empty($this->taxRates) || ($combineTax && $this->order_shipping_tax > 0)) {
            $checkoutPriceDisplay = (int) $params->get('checkout_price_display_options', 0);

            // Clone taxRates for display to avoid mutating calculated values
            $displayRates = [];

            foreach ($this->taxRates as $taxRate) {
                if (\is_object($taxRate)) {
                    $displayRates[] = clone $taxRate;
                } else {
                    $displayRates[] = (object) $taxRate;
                }
            }

            // Combine shipping tax into matching product tax entry when enabled
            if ($combineTax && $this->order_shipping_tax > 0 && $this->shippingRate) {
                $shippingTaxClassId = $this->getShippingTaxClassId();

                if ($shippingTaxClassId > 0) {
                    $merged = false;

                    foreach ($displayRates as $rate) {
                        if ((int) ($rate->taxprofile_id ?? 0) === $shippingTaxClassId) {
                            $rate->tax_amount += $this->order_shipping_tax;
                            $merged = true;
                            break;
                        }
                    }

                    // Shipping uses a different tax profile — create a new entry
                    if (!$merged) {
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
                    }
                }
            }

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

        $this->increase_coupon_discount_amount($code, $amount, $tax);

        // Add to order_discount for tax recalculation
        $this->order_discount += $amount;
        $this->order_discount_tax += $tax;
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
        J2CommerceHelper::plugin()->event('onJ2CommerceCalculateDiscountTotals', [$this]);

        // If plugins added discounts, recalculate tax on discounted amount
        if ($this->discount_cart > 0) {
            $this->recalculateTaxAfterDiscounts();
        }
    }
}
