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
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Checkout Model for site frontend
 *
 * Provides checkout data by delegating to CartModel and OrderHelper.
 *
 * Country / zone / postcode state resolution is NOT here — it lives in
 * CartsModel::populateState(), which is the model the checkout view actually builds.
 *
 * @since  6.0.0
 */
class CheckoutModel extends BaseDatabaseModel
{
    protected $_context = 'com_j2commerce.checkout';

    protected ?array $_items = null;

    protected ?object $_order = null;

    protected ?CartModel $_cartModel = null;

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
