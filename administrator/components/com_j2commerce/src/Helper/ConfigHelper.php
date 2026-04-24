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
use Joomla\Registry\Registry;

class ConfigHelper
{
    private static ?ConfigHelper $instance = null;
    private static ?Registry $params       = null;

    private function __construct()
    {
        self::getParams();
    }

    public static function getInstance(): ConfigHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function getParams(): Registry
    {
        if (self::$params === null) {
            self::$params = ComponentHelper::getParams('com_j2commerce');
        }

        return self::$params;
    }

    public static function get(string $key, mixed $default = ''): mixed
    {
        return self::getParams()->get($key, $default);
    }

    /**
     * Normalize the image_directories subform config into a predictable list.
     *
     * Joomla may return this field as a JSON string, array of arrays,
     * array of objects, or a single associative row depending on context.
     *
     * @param   array<int, array<string, mixed>>  $default  Fallback rows when config is empty/invalid.
     *
     * @return  array<int, array{directory: string, thumbs: int}>
     */
    public static function getImageDirectories(array $default = [['directory' => 'images', 'thumbs' => 1]]): array
    {
        $normalized = self::normalizeImageDirectories(self::get('image_directories', $default));

        if ($normalized !== []) {
            return $normalized;
        }

        $fallback = self::normalizeImageDirectories($default);

        return $fallback !== [] ? $fallback : [['directory' => 'images', 'thumbs' => 1]];
    }

    /**
     * Return only the configured image directory paths.
     *
     * @param   array<int, string>  $default  Fallback directory paths.
     *
     * @return  array<int, string>
     */
    public static function getImageDirectoryPaths(array $default = ['images']): array
    {
        $defaultRows = [];

        foreach ($default as $directory) {
            $directory = trim((string) $directory, '/');

            if ($directory !== '') {
                $defaultRows[] = ['directory' => $directory, 'thumbs' => 1];
            }
        }

        $directories = self::getImageDirectories($defaultRows ?: [['directory' => 'images', 'thumbs' => 1]]);
        $paths       = [];

        foreach ($directories as $directory) {
            if (!empty($directory['directory'])) {
                $paths[] = $directory['directory'];
            }
        }

        return $paths !== [] ? $paths : ($default ?: ['images']);
    }

    /** Sets a value in memory only — does not persist. */
    public static function set(string $key, mixed $value): void
    {
        self::getParams()->set($key, $value);
    }

    public static function toArray(): array
    {
        return self::getParams()->toArray();
    }

    public static function reset(): void
    {
        self::$params = null;
    }

    /**
     * @return  array<int, array{directory: string, thumbs: int}>
     */
    private static function normalizeImageDirectories(mixed $value): array
    {
        if ($value instanceof Registry) {
            $value = $value->toArray();
        }

        if (\is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = \is_array($decoded) ? $decoded : [];
        } elseif (\is_object($value)) {
            $value = (array) $value;
        }

        if (!\is_array($value)) {
            return [];
        }

        if (\array_key_exists('directory', $value)) {
            $value = [$value];
        }

        $normalized = [];

        foreach ($value as $row) {
            if ($row instanceof Registry) {
                $row = $row->toArray();
            } elseif (\is_object($row)) {
                $row = (array) $row;
            }

            if (!\is_array($row)) {
                continue;
            }

            $directory = trim((string) ($row['directory'] ?? ''), '/');

            if ($directory === '') {
                continue;
            }

            $normalized[] = [
                'directory' => $directory,
                'thumbs'    => (int) ($row['thumbs'] ?? 0),
            ];
        }

        return $normalized;
    }

    // =========================================================================
    // CONVENIENCE METHODS FOR COMMON CONFIGURATION VALUES
    // =========================================================================

    /**
     * Get the store name
     *
     * @return  string  Store name
     *
     * @since   6.0.0
     */
    public static function getStoreName(): string
    {
        return (string) self::get('store_name', '');
    }

    /**
     * Get the store address line 1
     *
     * @return  string  Store address
     *
     * @since   6.0.0
     */
    public static function getStoreAddress1(): string
    {
        return (string) self::get('store_address_1', '');
    }

    /**
     * Get the store address line 2
     *
     * @return  string  Store address line 2
     *
     * @since   6.0.0
     */
    public static function getStoreAddress2(): string
    {
        return (string) self::get('store_address_2', '');
    }

    /**
     * Get the store city
     *
     * @return  string  Store city
     *
     * @since   6.0.0
     */
    public static function getStoreCity(): string
    {
        return (string) self::get('store_city', '');
    }

    /**
     * Get the store zip/postal code
     *
     * @return  string  Store zip
     *
     * @since   6.0.0
     */
    public static function getStoreZip(): string
    {
        return (string) self::get('store_zip', '');
    }

    /**
     * Get the store country ID
     *
     * @return  int  Country ID
     *
     * @since   6.0.0
     */
    public static function getStoreCountryId(): int
    {
        return (int) self::get('country_id', 223);
    }

    /**
     * Get the store zone ID
     *
     * @return  int  Zone ID
     *
     * @since   6.0.0
     */
    public static function getStoreZoneId(): int
    {
        return (int) self::get('zone_id', 0);
    }

    public static function getStorePhone(): string
    {
        return (string) self::get('store_phone', '');
    }

    /**
     * Get the admin email address(es)
     *
     * @return  string  Admin email(s) - may be comma-separated
     *
     * @since   6.0.0
     */
    public static function getAdminEmail(): string
    {
        return (string) self::get('admin_email', '');
    }

    /**
     * Get the default currency code
     *
     * @return  string  Currency code (e.g., "USD")
     *
     * @since   6.0.0
     */
    public static function getDefaultCurrency(): string
    {
        return (string) self::get('config_currency', 'USD');
    }

    /**
     * Check if currency auto-update is enabled
     *
     * @return  bool  True if enabled
     *
     * @since   6.0.0
     */
    public static function isCurrencyAutoUpdateEnabled(): bool
    {
        return (int) self::get('config_currency_auto', 1) === 1;
    }

    /**
     * Get the default weight class ID
     *
     * @return  int  Weight class ID
     *
     * @since   6.0.0
     */
    public static function getDefaultWeightClassId(): int
    {
        return (int) self::get('config_weight_class_id', 2);
    }

    /**
     * Get the default length class ID
     *
     * @return  int  Length class ID
     *
     * @since   6.0.0
     */
    public static function getDefaultLengthClassId(): int
    {
        return (int) self::get('config_length_class_id', 1);
    }

    /**
     * Check if CSS loading is enabled
     *
     * @return  bool  True if CSS should be loaded
     *
     * @since   6.0.0
     */
    public static function isCssEnabled(): bool
    {
        return (int) self::get('j2commerce_enable_css', 1) === 1;
    }

    /**
     * Get the date format string
     *
     * @return  string  PHP date format string
     *
     * @since   6.0.0
     */
    public static function getDateFormat(): string
    {
        return (string) self::get('date_format', 'Y-m-d H:i:s');
    }

    /**
     * Get the attachment folder path
     *
     * @return  string  Folder path
     *
     * @since   6.0.0
     */
    public static function getAttachmentPath(): string
    {
        return (string) self::get('attachmentfolderpath', '');
    }

    /**
     * Get the queue processing key
     *
     * @return  string  Queue key
     *
     * @since   6.0.0
     */
    public static function getQueueKey(): string
    {
        return (string) self::get('queue_key', '');
    }

    /**
     * Get the queue repeat count
     *
     * @return  int  Repeat count
     *
     * @since   6.0.0
     */
    public static function getQueueRepeatCount(): int
    {
        return (int) self::get('queue_repeat_count', 10);
    }

    // =========================================================================
    // PRODUCT DISPLAY SETTINGS
    // =========================================================================

    /**
     * Check if catalog mode is enabled (hide prices and cart)
     *
     * @return  bool  True if catalog mode
     *
     * @since   6.0.0
     */
    public static function isCatalogMode(): bool
    {
        return (int) self::get('catalog_mode', 0) === 1;
    }

    /**
     * Check if SKU should be displayed
     *
     * @return  bool  True to show SKU
     *
     * @since   6.0.0
     */
    public static function showSku(): bool
    {
        return (int) self::get('show_sku', 0) === 1;
    }

    /**
     * Check if manufacturer should be displayed
     *
     * @return  bool  True to show manufacturer
     *
     * @since   6.0.0
     */
    public static function showManufacturer(): bool
    {
        return (int) self::get('show_manufacturer', 0) === 1;
    }

    /**
     * Check if quantity field should be displayed
     *
     * @return  bool  True to show quantity field
     *
     * @since   6.0.0
     */
    public static function showQuantityField(): bool
    {
        return (int) self::get('show_qty_field', 1) === 1;
    }

    /**
     * Check if price should be displayed
     *
     * @return  bool  True to show price
     *
     * @since   6.0.0
     */
    public static function showPrice(): bool
    {
        return (int) self::get('show_price_field', 1) === 1;
    }

    /**
     * Check if base price should be displayed (when special price exists)
     *
     * @return  bool  True to show base price
     *
     * @since   6.0.0
     */
    public static function showBasePrice(): bool
    {
        return (int) self::get('show_base_price', 1) === 1;
    }

    /**
     * Check if product option prices should be displayed
     *
     * @return  bool  True to show option prices
     *
     * @since   6.0.0
     */
    public static function showOptionPrice(): bool
    {
        return (int) self::get('product_option_price', 1) === 1;
    }

    /**
     * Check if option price prefix (+/-) should be displayed
     *
     * @return  bool  True to show prefix
     *
     * @since   6.0.0
     */
    public static function showOptionPricePrefix(): bool
    {
        return (int) self::get('product_option_price_prefix', 1) === 1;
    }

    /**
     * Check if option images should be displayed
     *
     * @return  bool  True to show option images
     *
     * @since   6.0.0
     */
    public static function showOptionImage(): bool
    {
        return (int) self::get('image_for_product_options', 0) === 1;
    }

    /**
     * Get the number of columns for related products
     *
     * @return  int  Number of columns (1-6)
     *
     * @since   6.0.0
     */
    public static function getRelatedProductColumns(): int
    {
        return (int) self::get('related_product_columns', 3);
    }

    /**
     * Check if registration is required to view products
     *
     * @return  bool  True if registration required
     *
     * @since   6.0.0
     */
    public static function isRegistrationRequired(): bool
    {
        return (int) self::get('isregister', 0) === 1;
    }

    /**
     * Check if prices are only shown to registered users
     *
     * @return  bool  True if prices are for registered only
     *
     * @since   6.0.0
     */
    public static function isPriceForRegisteredOnly(): bool
    {
        return (int) self::get('show_product_price_for_register_user', 0) === 1;
    }

    /**
     * Check if SKU is only shown to registered users
     *
     * @return  bool  True if SKU is for registered only
     *
     * @since   6.0.0
     */
    public static function isSkuForRegisteredOnly(): bool
    {
        return (int) self::get('show_product_sku_for_register_user', 0) === 1;
    }

    // =========================================================================
    // INVENTORY SETTINGS
    // =========================================================================

    /**
     * Check if inventory management is enabled
     *
     * @return  bool  True if inventory enabled
     *
     * @since   6.0.0
     */
    public static function isInventoryEnabled(): bool
    {
        return (int) self::get('enable_inventory', 0) === 1;
    }

    /**
     * Check if orders should be cancelled when inventory is zero
     *
     * @return  bool  True to cancel orders
     *
     * @since   6.0.0
     */
    public static function shouldCancelOutOfStockOrders(): bool
    {
        return (int) self::get('cancel_order', 0) === 1;
    }

    /**
     * Get the stock hold time in minutes
     *
     * @return  int  Hold time in minutes
     *
     * @since   6.0.0
     */
    public static function getStockHoldTime(): int
    {
        return (int) self::get('hold_stock', 60);
    }

    /**
     * Get the stock display format
     *
     * @return  string  'always_show', 'low_stock', or 'no_display'
     *
     * @since   6.0.0
     */
    public static function getStockDisplayFormat(): string
    {
        return (string) self::get('stock_display_format', 'always_show');
    }

    /**
     * Get the minimum sale quantity
     *
     * @return  int  Minimum quantity
     *
     * @since   6.0.0
     */
    public static function getMinSaleQuantity(): int
    {
        return (int) self::get('store_min_sale_qty', 1);
    }

    /**
     * Get the maximum sale quantity (0 = unlimited)
     *
     * @return  int  Maximum quantity
     *
     * @since   6.0.0
     */
    public static function getMaxSaleQuantity(): int
    {
        return (int) self::get('store_max_sale_qty', 0);
    }

    /**
     * Get the low stock notification quantity
     *
     * @return  int  Notify quantity
     *
     * @since   6.0.0
     */
    public static function getNotifyQuantity(): int
    {
        return (int) self::get('store_notify_qty', 0);
    }

    // =========================================================================
    // TAX SETTINGS
    // =========================================================================

    /**
     * Check if prices include tax
     *
     * @return  bool  True if prices include tax
     *
     * @since   6.0.0
     */
    public static function pricesIncludeTax(): bool
    {
        return (int) self::get('config_including_tax', 0) === 1;
    }

    /**
     * Get the tax calculation basis
     *
     * @return  string  'billing' or 'shipping'
     *
     * @since   6.0.0
     */
    public static function getTaxBasis(): string
    {
        return (string) self::get('config_tax_default', 'billing');
    }

    /**
     * Get the default tax address type
     *
     * @return  string  'noaddress' or 'store'
     *
     * @since   6.0.0
     */
    public static function getTaxDefaultAddress(): string
    {
        return (string) self::get('config_tax_default_address', 'store');
    }

    /**
     * Get the price display option for product pages
     *
     * @return  int  1 = price only, 2 = price plus tax
     *
     * @since   6.0.0
     */
    public static function getPriceDisplayOption(): int
    {
        return (int) self::get('price_display_options', 1);
    }

    /**
     * Check if tax info should be displayed with price
     *
     * @return  bool  True to show tax info
     *
     * @since   6.0.0
     */
    public static function showTaxInfo(): bool
    {
        return (int) self::get('display_price_with_tax_info', 0) === 1;
    }

    /**
     * Get the checkout price display option
     *
     * @return  int  0 = excluding tax, 1 = including tax
     *
     * @since   6.0.0
     */
    public static function getCheckoutPriceDisplayOption(): int
    {
        return (int) self::get('checkout_price_display_options', 0);
    }

    // =========================================================================
    // DISCOUNT SETTINGS
    // =========================================================================

    /**
     * Check if coupons are enabled
     *
     * @return  bool  True if coupons enabled
     *
     * @since   6.0.0
     */
    public static function areCouponsEnabled(): bool
    {
        return (int) self::get('enable_coupon', 0) === 1;
    }

    /**
     * Check if vouchers are enabled
     *
     * @return  bool  True if vouchers enabled
     *
     * @since   6.0.0
     */
    public static function areVouchersEnabled(): bool
    {
        return (int) self::get('enable_voucher', 0) === 1;
    }

    /**
     * Check if vouchers can be applied to shipping
     *
     * @return  bool  True if allowed
     *
     * @since   6.0.0
     */
    public static function canApplyVoucherToShipping(): bool
    {
        return (int) self::get('backend_voucher_to_shipping', 1) === 1;
    }

    // =========================================================================
    // CART SETTINGS
    // =========================================================================

    /**
     * Get the add to cart button placement
     *
     * @return  string  'default', 'tag', or 'both'
     *
     * @since   6.0.0
     */
    public static function getAddToCartPlacement(): string
    {
        return (string) self::get('addtocart_placement', 'default');
    }

    /**
     * Get the add to cart action
     *
     * @return  int  1 = inline, 3 = redirect to cart
     *
     * @since   6.0.0
     */
    public static function getAddToCartAction(): int
    {
        return (int) self::get('addtocart_action', 1);
    }

    /**
     * Get the add to cart button CSS class
     *
     * @return  string  CSS class(es)
     *
     * @since   6.0.0
     */
    public static function getAddToCartButtonClass(): string
    {
        return (string) self::get('addtocart_button_class', 'btn btn-primary');
    }

    /**
     * Get the continue shopping page setting
     *
     * @return  string  'previous', 'menu', or 'url'
     *
     * @since   6.0.0
     */
    public static function getContinueShoppingPage(): string
    {
        return (string) self::get('config_continue_shopping_page', 'previous');
    }

    /**
     * Get the continue shopping menu item ID
     *
     * @return  int  Menu item ID
     *
     * @since   6.0.0
     */
    public static function getContinueShoppingMenuId(): int
    {
        return (int) self::get('continue_shopping_page_menu', 0);
    }

    /**
     * Get the continue shopping custom URL
     *
     * @return  string  Custom URL
     *
     * @since   6.0.0
     */
    public static function getContinueShoppingUrl(): string
    {
        return (string) self::get('config_continue_shopping_page_url', '');
    }

    /**
     * Get the empty cart redirect setting
     *
     * @return  string  'cart', 'menu', or 'url'
     *
     * @since   6.0.0
     */
    public static function getEmptyCartRedirect(): string
    {
        return (string) self::get('config_cart_empty_redirect', 'cart');
    }

    /**
     * Check if cart thumbnails should be displayed
     *
     * @return  bool  True to show thumbnails
     *
     * @since   6.0.0
     */
    public static function showCartThumbnails(): bool
    {
        return (int) self::get('show_thumb_cart', 0) === 1;
    }

    /**
     * Check if item tax should be shown in cart
     *
     * @return  bool  True to show item tax
     *
     * @since   6.0.0
     */
    public static function showCartItemTax(): bool
    {
        return (int) self::get('show_item_tax', 0) === 1;
    }

    /**
     * Check if clear cart button should be shown
     *
     * @return  bool  True to show button
     *
     * @since   6.0.0
     */
    public static function showClearCartButton(): bool
    {
        return (int) self::get('show_clear_cart_button', 0) === 1;
    }

    /**
     * Get the clear cart timing
     *
     * @return  string  'order_placed' or 'order_confirmed'
     *
     * @since   6.0.0
     */
    public static function getClearCartTiming(): string
    {
        return (string) self::get('clear_cart', 'order_placed');
    }

    /**
     * Get the cart data expiry time in days
     *
     * @return  int  Days until cart data expires
     *
     * @since   6.0.0
     */
    public static function getCartExpiryDays(): int
    {
        return (int) self::get('clear_outdated_cart_data_term', 90);
    }

    // =========================================================================
    // CHECKOUT SETTINGS
    // =========================================================================

    /**
     * Check if login form should be shown at checkout
     *
     * @return  bool  True to show login form
     *
     * @since   6.0.0
     */
    public static function showLoginForm(): bool
    {
        return (int) self::get('show_login_form', 1) === 1;
    }

    /**
     * Check if registration is allowed at checkout
     *
     * @return  bool  True if allowed
     *
     * @since   6.0.0
     */
    public static function allowRegistration(): bool
    {
        return (int) self::get('allow_registration', 1) === 1;
    }

    /**
     * Check if password validation is required during registration
     *
     * @return  bool  True if required
     *
     * @since   6.0.0
     */
    public static function requirePasswordValidation(): bool
    {
        return (int) self::get('allow_password_validation', 1) === 1;
    }

    /**
     * Check if guest checkout is allowed
     *
     * @return  bool  True if allowed
     *
     * @since   6.0.0
     */
    public static function allowGuestCheckout(): bool
    {
        return (int) self::get('allow_guest_checkout', 0) === 1;
    }

    /**
     * Check if shipping address should be shown
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showShippingAddress(): bool
    {
        return (int) self::get('show_shipping_address', 1) === 1;
    }

    /**
     * Check if postal code is required
     *
     * @return  bool  True if required
     *
     * @since   6.0.0
     */
    public static function isPostalCodeRequired(): bool
    {
        return (int) self::get('postalcode_required', 1) === 1;
    }

    /**
     * Check if customer note field should be shown
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showCustomerNote(): bool
    {
        return (int) self::get('show_customer_note', 1) === 1;
    }

    /**
     * Check if tax calculator should be shown
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showTaxCalculator(): bool
    {
        return (int) self::get('show_tax_calculator', 1) === 1;
    }

    /**
     * Check if shipping is mandatory
     *
     * @return  bool  True if mandatory
     *
     * @since   6.0.0
     */
    public static function isShippingMandatory(): bool
    {
        return (int) self::get('shipping_mandatory', 0) === 1;
    }

    /**
     * Check if shipping rate should be auto-applied
     *
     * @return  bool  True to auto-apply
     *
     * @since   6.0.0
     */
    public static function autoApplyShippingRate(): bool
    {
        return (int) self::get('auto_apply_shipping_rate', 0) === 1;
    }

    /**
     * Check if shipping should be hidden until address is selected
     *
     * @return  bool  True to hide
     *
     * @since   6.0.0
     */
    public static function hideShippingUntilAddressSelected(): bool
    {
        return (int) self::get('hide_shipping_until_address_selection', 1) === 1;
    }

    /**
     * Get the default payment method
     *
     * @return  string  Payment method element name
     *
     * @since   6.0.0
     */
    public static function getDefaultPaymentMethod(): string
    {
        return (string) self::get('default_payment_method', '');
    }

    // =========================================================================
    // ORDER SETTINGS
    // =========================================================================

    /**
     * Get the invoice prefix
     *
     * @return  string  Invoice prefix
     *
     * @since   6.0.0
     */
    public static function getInvoicePrefix(): string
    {
        return (string) self::get('invoice_prefix', '');
    }

    /**
     * Check if order link should be shown after payment
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showPostPaymentOrderLink(): bool
    {
        return (int) self::get('show_postpayment_orderlink', 1) === 1;
    }

    /**
     * Check if downloads area should be shown
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showDownloadsArea(): bool
    {
        return (int) self::get('download_area', 1) === 1;
    }

    /**
     * Get the order statuses that allow downloads
     *
     * @return  array<int>  Array of order status IDs
     *
     * @since   6.0.0
     */
    public static function getDownloadAllowedStatuses(): array
    {
        $value = self::get('limit_orderstatuses', '1');

        if (\is_array($value)) {
            return array_map('intval', $value);
        }

        if (empty($value)) {
            return [1];
        }

        return array_map('intval', explode(',', (string) $value));
    }

    /**
     * Check if thumbnails should be shown in order emails
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showEmailThumbnails(): bool
    {
        return (int) self::get('show_thumb_email', 0) === 1;
    }

    /**
     * Check if logout link should be shown in my profile
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showLogoutInProfile(): bool
    {
        return (int) self::get('show_logout_myprofile', 0) === 1;
    }

    // =========================================================================
    // MISCELLANEOUS SETTINGS
    // =========================================================================

    /**
     * Get the email template mode
     *
     * @return  int  0 = configured templates only, 1 = use default
     *
     * @since   6.0.0
     */
    public static function getEmailTemplateMode(): int
    {
        return (int) self::get('send_default_email_template', 1);
    }

    /**
     * Check if terms and conditions should be shown
     *
     * @return  bool  True to show
     *
     * @since   6.0.0
     */
    public static function showTerms(): bool
    {
        return (int) self::get('show_terms', 1) === 1;
    }

    /**
     * Get the terms display type
     *
     * @return  string  'link' or 'checkbox'
     *
     * @since   6.0.0
     */
    public static function getTermsDisplayType(): string
    {
        return (string) self::get('terms_display_type', 'link');
    }

    /**
     * Get the terms and conditions article ID
     *
     * @return  int  Article ID
     *
     * @since   6.0.0
     */
    public static function getTermsArticleId(): int
    {
        return (int) self::get('termsid', 0);
    }

    /**
     * Get the download ID for updates
     *
     * @return  string  Download ID
     *
     * @since   6.0.0
     */
    public static function getDownloadId(): string
    {
        return (string) self::get('downloadid', '');
    }
}
