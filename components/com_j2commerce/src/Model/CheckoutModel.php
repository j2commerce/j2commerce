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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Checkout Model for site frontend
 *
 * Provides checkout data by delegating to CartModel and OrderHelper.
 *
 * @since  6.0.0
 */
class CheckoutModel extends BaseDatabaseModel
{
    protected $_context = 'com_j2commerce.checkout';

    protected ?array $_items = null;

    protected ?object $_order = null;

    protected ?CartModel $_cartModel = null;

    protected function populateState(): void
    {
        $app     = Factory::getApplication();
        $session = $app->getSession();

        $params = $app->getParams();
        $this->setState('params', $params);

        $store = J2CommerceHelper::storeProfile();

        // Country
        $countryId = $app->getInput()->getInt('country_id', 0);

        if ($countryId > 0) {
            $session->set('billing_country_id', $countryId, 'j2commerce');
            $session->set('shipping_country_id', $countryId, 'j2commerce');
        } elseif ($session->has('shipping_country_id', 'j2commerce')) {
            $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
        } else {
            $countryId = (int) $store->get('country_id', 0);
        }

        // Zone
        $zoneId = $app->getInput()->getInt('zone_id', 0);

        if ($zoneId > 0) {
            $session->set('billing_zone_id', $zoneId, 'j2commerce');
            $session->set('shipping_zone_id', $zoneId, 'j2commerce');
        } elseif ($session->has('shipping_zone_id', 'j2commerce')) {
            $zoneId = (int) $session->get('shipping_zone_id', 0, 'j2commerce');
        } else {
            $zoneId = (int) $store->get('zone_id', 0);
        }

        // Postcode
        $postcode = $app->getInput()->getAlnum('postcode', '');
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
    }

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
     * @return  array
     *
     * @since   6.0.0
     */
    public function getItems(): array
    {
        if ($this->_items === null) {
            $mvcFactory = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();

            // Initialize coupon
            $couponModel = $mvcFactory->createModel('Coupon', 'Administrator', ['ignore_request' => true]);

            if ($couponModel && method_exists($couponModel, 'getCoupon')) {
                $couponModel->getCoupon();
            }

            // Initialize voucher
            $voucherModel = $mvcFactory->createModel('Voucher', 'Administrator', ['ignore_request' => true]);

            if ($voucherModel && method_exists($voucherModel, 'getVoucherCode')) {
                $voucherModel->getVoucherCode();
            }

            $this->_items = $this->getCartModel()->getItems();
        }

        return $this->_items ?: [];
    }

    /**
     * Get order with calculated totals.
     *
     * @return  object|null
     *
     * @since   6.0.0
     */
    public function getOrder(): ?object
    {
        if ($this->_order === null) {
            $items = $this->getItems();

            if (!empty($items)) {
                $this->_order = OrderHelper::getInstance()
                    ->populateOrder($items)
                    ->getOrder();

                if ($this->_order) {
                    $this->_order->validate_order_stock();
                }
            }
        }

        return $this->_order;
    }

    public function getCurrency(): object
    {
        return J2CommerceHelper::currency();
    }

    public function getStore(): object
    {
        return J2CommerceHelper::storeProfile();
    }

    public function getCheckoutUrl(): string
    {
        return $this->getCartModel()->getCheckoutUrl();
    }

    /**
     * Check if cart has shippable items.
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    public function hasShippableItems(): bool
    {
        $order = $this->getOrder();

        if ($order && property_exists($order, 'is_shippable')) {
            return (bool) $order->is_shippable;
        }

        return false;
    }

    /**
     * Get shipping methods from session.
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getShippingMethods(): array
    {
        return Factory::getApplication()->getSession()
            ->get('shipping_methods', [], 'j2commerce');
    }

    /**
     * Get shipping values from session.
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getShippingValues(): array
    {
        return Factory::getApplication()->getSession()
            ->get('shipping_values', [], 'j2commerce');
    }
}
