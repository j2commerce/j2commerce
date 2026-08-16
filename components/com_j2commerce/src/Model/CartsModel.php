<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartOrder;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Carts Model for site frontend (shopping cart view)
 *
 * Provides cart data and order calculations for the shopping cart page.
 * Delegates to the admin CartModel for core cart operations.
 *
 * @since  6.0.0
 */
class CartsModel extends BaseDatabaseModel
{
    /**
     * Model context string.
     *
     * @var   string
     * @since 6.0.0
     */
    protected $_context = 'com_j2commerce.carts';

    /**
     * Cached cart items.
     *
     * @var   array|null
     * @since 6.0.0
     */
    protected $_items = null;

    /**
     * Cached order object.
     *
     * @var   object|null
     * @since 6.0.0
     */
    protected $_order = null;

    /**
     * Admin CartModel instance.
     *
     * @var   CartModel|null
     * @since 6.0.0
     */
    protected $_cartModel = null;

    /**
     * Method to auto-populate the model state.
     *
     * @return void
     *
     * @since   6.0.0
     */
    protected function populateState(): void
    {
        $app     = Factory::getApplication();
        $session = $app->getSession();

        // Load the parameters
        $params = $app->getParams();
        $this->setState('params', $params);

        // Get country/zone from input or session
        $countryId = $app->getInput()->getInt('country_id', 0);
        $zoneId    = $app->getInput()->getInt('zone_id', 0);
        $postcode  = $app->getInput()->getAlnum('postcode', '');

        // Get store profile for defaults
        $store = J2CommerceHelper::storeProfile();

        // Handle country_id
        if ($countryId > 0) {
            $session->set('billing_country_id', $countryId, 'j2commerce');
            $session->set('shipping_country_id', $countryId, 'j2commerce');
        } elseif ($session->has('shipping_country_id', 'j2commerce')) {
            $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
        } else {
            $countryId = (int) $store->get('country_id', 0);
        }

        // Handle zone_id
        if ($zoneId > 0) {
            $session->set('billing_zone_id', $zoneId, 'j2commerce');
            $session->set('shipping_zone_id', $zoneId, 'j2commerce');
        } elseif ($session->has('shipping_zone_id', 'j2commerce')) {
            $zoneId = (int) $session->get('shipping_zone_id', 0, 'j2commerce');
        } else {
            $zoneId = (int) $store->get('zone_id', 0);
        }

        // Handle postcode
        $postcode = UtilitiesHelper::textSanitize($postcode);
        if (!empty($postcode)) {
            $session->set('shipping_postcode', $postcode, 'j2commerce');
        } elseif ($session->has('shipping_postcode', 'j2commerce')) {
            $postcode = $session->get('shipping_postcode', '', 'j2commerce');
        } else {
            $postcode = $store->get('zip', '');
        }

        $this->setState('country_id', $countryId);
        $this->setState('zone_id', $zoneId);
        $this->setState('postcode', $postcode);

        // Handle shipping calculation visibility
        $config = J2CommerceHelper::config();
        if ($config->get('hide_shipping_until_address_selection', 1) == 0) {
            $session->set('billing_country_id', $countryId, 'j2commerce');
            $session->set('shipping_country_id', $countryId, 'j2commerce');
            $session->set('billing_zone_id', $zoneId, 'j2commerce');
            $session->set('shipping_zone_id', $zoneId, 'j2commerce');
            $session->set('shipping_postcode', $postcode, 'j2commerce');
            $session->set('force_calculate_shipping', 1, 'j2commerce');
        }
    }

    /**
     * Get the admin CartModel instance.
     *
     * @return CartModel
     *
     * @since 6.0.0
     */
    protected function getCartModel(): CartModel
    {
        if ($this->_cartModel === null) {
            $this->_cartModel = new CartModel();
        }

        return $this->_cartModel;
    }

    /**
     * Get cart items.
     *
     * @param   bool  $force  Force reload of items
     *
     * @return  array  Cart items array
     *
     * @since   6.0.0
     */
    public function getItems(bool $force = false): array
    {
        if ($this->_items === null || $force) {
            $app        = Factory::getApplication();
            $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();

            // Initialize coupon via native CouponModel
            $couponModel = $mvcFactory->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
            if ($couponModel && method_exists($couponModel, 'getCoupon')) {
                $couponModel->getCoupon();
            }

            // Initialize voucher via native VoucherModel
            $voucherModel = $mvcFactory->createModel('Voucher', 'Administrator', ['ignore_request' => true]);
            if ($voucherModel && method_exists($voucherModel, 'getVoucherCode')) {
                $voucherModel->getVoucherCode();
            }

            $this->_items = $this->getCartModel()->getItems($force);
        }

        return $this->_items ?: [];
    }

    /**
     * Get order object with calculated totals.
     *
     * @return  object|null  Order object
     *
     * @since   6.0.0
     */
    public function getOrder(): ?object
    {
        if ($this->_order === null) {
            $items = $this->getItems();

            if (!empty($items)) {
                // Use native OrderHelper to populate order from cart items
                $this->_order = OrderHelper::getInstance()
                    ->populateOrder($items)
                    ->getOrder();

                // Validate stock
                if ($this->_order) {
                    $this->_order->validate_order_stock();
                }
            }
        }

        return $this->_order;
    }

    /**
     * Get checkout URL.
     *
     * @return  string  Checkout URL
     *
     * @since   6.0.0
     */
    public function getCheckoutUrl(): string
    {
        return $this->getCartModel()->getCheckoutUrl();
    }

    /**
     * Get continue shopping URL configuration.
     *
     * @return  object  Object with 'type' and 'url' properties
     *
     * @since   6.0.0
     */
    public function getContinueShoppingUrl(): object
    {
        return $this->getCartModel()->getContinueShoppingUrl();
    }

    /**
     * Get empty cart redirect URL if configured.
     *
     * @return  string|null  Redirect URL or null
     *
     * @since   6.0.0
     */
    public function getEmptyCartRedirectUrl(): ?string
    {
        return $this->getCartModel()->getEmptyCartRedirectUrl();
    }

    /**
     * Get currency helper instance.
     *
     * @return  object  Currency helper
     *
     * @since   6.0.0
     */
    public function getCurrency(): object
    {
        return J2CommerceHelper::currency();
    }

    /**
     * Get store profile.
     *
     * @return  object  Store profile
     *
     * @since   6.0.0
     */
    public function getStore(): object
    {
        return J2CommerceHelper::storeProfile();
    }

    /**
     * Get shipping methods from session.
     *
     * @return  array  Shipping methods
     *
     * @since   6.0.0
     */
    public function getShippingMethods(): array
    {
        $session = Factory::getApplication()->getSession();

        return $session->get('shipping_methods', [], 'j2commerce');
    }

    /**
     * Get shipping values from session.
     *
     * @return  array  Shipping values
     *
     * @since   6.0.0
     */
    public function getShippingValues(): array
    {
        $session = Factory::getApplication()->getSession();

        return $session->get('shipping_values', [], 'j2commerce');
    }

    /**
     * Validate that the current shipping selection still exists among available methods.
     *
     * If the previously selected method is no longer available (e.g., cart changes
     * pushed the order outside a rate range), auto-selects the first available method.
     * If no methods exist at all, clears the shipping values from session.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function validateShippingSelection(): void
    {
        $session = Factory::getApplication()->getSession();
        $stored  = $session->get('shipping_methods', [], 'j2commerce');
        $stored  = \is_array($stored) ? $stored : [];

        // This model consumes whatever another path stored, so the offer list is filtered on
        // read as well as on write — and written back, so the template loops the same list.
        $shippingMethods = array_values(array_filter($stored, [CartOrder::class, 'rateChargesAreValid']));

        if (\count($shippingMethods) !== \count($stored)) {
            $session->set('shipping_methods', $shippingMethods, 'j2commerce');
        }

        $shippingValues = $session->get('shipping_values', [], 'j2commerce');

        // Nothing selected -- nothing to validate
        if (empty($shippingValues)) {
            return;
        }

        // Selection exists but no methods available -- clear stale selection
        if (empty($shippingMethods)) {
            $session->clear('shipping_values', 'j2commerce');

            return;
        }

        // Check if the current selection matches any available method
        $selectedName = $shippingValues['shipping_name'] ?? '';
        $found        = false;

        foreach ($shippingMethods as $method) {
            if (($method['name'] ?? '') === $selectedName) {
                $found = true;
                break;
            }
        }

        // Current selection is still valid
        if ($found) {
            return;
        }

        // Auto-select the first method. The session list was already ordered when it was
        // stored, so this is the cheapest rate or the plugin-ordering leader accordingly.
        $default = array_values($shippingMethods)[0];

        $session->set('shipping_values', [
            'shipping_name'         => $default['name'],
            'shipping_price'        => $default['price'],
            'shipping_tax'          => $default['tax'],
            'shipping_tax_class_id' => $default['tax_class_id'] ?? 0,
            'shipping_extra'        => $default['extra'],
            'shipping_code'         => $default['code'],
            'shipping_plugin'       => $default['element'],
            'shipping_tax_resolved' => CartOrder::rateTaxIsResolved($default),
        ], 'j2commerce');
    }
}
