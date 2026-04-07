<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Carts;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Site\Model\CartsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * HTML Carts View class for shopping cart display
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The cart items array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $items = [];

    /**
     * The order object with calculated totals
     *
     * @var  object|null
     *
     * @since  6.0.0
     */
    public $order = null;

    /**
     * The page parameters
     *
     * @var    Registry|null
     *
     * @since  6.0.0
     */
    public $params = null;

    /**
     * Menu item parameters
     *
     * @var    Registry|null
     *
     * @since  6.0.0
     */
    public $menuItemParams = null;

    /**
     * Currency helper instance
     *
     * @var  object
     *
     * @since  6.0.0
     */
    public $currency;

    /**
     * Store profile object
     *
     * @var  object
     *
     * @since  6.0.0
     */
    public $store;

    /**
     * Order taxes array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $taxes = [];

    /**
     * Order shipping rate
     *
     * @var  object|null
     *
     * @since  6.0.0
     */
    public $shipping = null;

    /**
     * Order coupons array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $coupons = [];

    /**
     * Order vouchers array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $vouchers = [];

    /**
     * Shipping methods array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $shipping_methods = [];

    /**
     * Shipping values array
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $shipping_values = [];

    /**
     * Checkout URL
     *
     * @var  string
     *
     * @since  6.0.0
     */
    public $checkout_url = '';

    /**
     * Continue shopping URL configuration
     *
     * @var  object
     *
     * @since  6.0.0
     */
    public $continue_shopping_url;

    /**
     * Country ID for shipping calculator
     *
     * @var  int
     *
     * @since  6.0.0
     */
    public $country_id = 0;

    /**
     * Zone ID for shipping calculator
     *
     * @var  int
     *
     * @since  6.0.0
     */
    public $zone_id = 0;

    /**
     * Postcode for shipping calculator
     *
     * @var  string
     *
     * @since  6.0.0
     */
    public $postcode = '';

    /**
     * HTML content before cart display (from plugins)
     *
     * @var  string
     *
     * @since  6.0.0
     */
    public $before_display_cart = '';

    /**
     * HTML content after cart display (from plugins)
     *
     * @var  string
     *
     * @since  6.0.0
     */
    public $after_display_cart = '';

    /**
     * Plugin content for individual cart items
     *
     * @var  array
     *
     * @since  6.0.0
     */
    public $onDisplayCartItem = [];

    /**
     * Tax model for tax calculations
     *
     * @var  object|null
     *
     * @since  6.0.0
     */
    public $taxModel = null;

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        // Disable caching for cart pages
        UtilitiesHelper::sendNoCacheHeaders();

        /** @var CartsModel $model */
        $model = $this->getModel();

        // Check for errors
        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Get component and menu parameters
        $this->params   = J2CommerceHelper::config();
        $this->currency = $model->getCurrency();
        $this->store    = $model->getStore();

        // Get menu item params
        $menu                 = $app->getMenu();
        $active               = $menu->getActive();
        $this->menuItemParams = \is_object($active) ? $active->getParams() : new Registry('{}');

        // Get location state
        $this->country_id = (int) $model->getState('country_id');
        $this->zone_id    = (int) $model->getState('zone_id');
        $this->postcode   = (string) $model->getState('postcode');

        // Get cart items
        $items = $model->getItems();

        // Handle empty cart redirect
        if (empty($items)) {
            $redirectUrl = $model->getEmptyCartRedirectUrl();
            if ($redirectUrl) {
                $app->redirect($redirectUrl);
                return;
            }
        }

        // Import J2Commerce plugins
        PluginHelper::importPlugin('j2commerce');

        // Trigger BeforeDisplayCart event
        $this->before_display_cart = '';
        $beforeResults             = J2CommerceHelper::plugin()->event('BeforeDisplayCart', [&$items]);
        foreach ($beforeResults as $result) {
            $this->before_display_cart .= $result;
        }

        // Trigger DisplayCartItem event for each item
        $this->onDisplayCartItem = [];
        $i                       = 0;
        foreach ($items as $item) {
            ob_start();
            J2CommerceHelper::plugin()->event('DisplayCartItem', [$i, $item]);
            $cartItemContents = ob_get_contents();
            ob_end_clean();

            if (!empty($cartItemContents)) {
                $this->onDisplayCartItem[$i] = $cartItemContents;
            }
            $i++;
        }

        // Validate shipping selection before building the order so stale
        // selections are corrected and the order uses the right shipping cost.
        $model->validateShippingSelection();

        // Get order with calculated totals
        $this->order = $model->getOrder();

        if ($this->order) {
            // Get items from order (processed with attributes)
            $this->items = $this->order->getItems();

            // Process file upload attributes to show original names
            $mvcFactory = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
            foreach ($this->items as $item) {
                if (isset($item->orderitemattributes) && \count($item->orderitemattributes)) {
                    foreach ($item->orderitemattributes as &$attribute) {
                        if ($attribute->orderitemattribute_type === 'file') {
                            $uploadTable = $mvcFactory->createTable('Upload', 'Administrator');
                            if ($uploadTable && $uploadTable->load(['mangled_name' => $attribute->orderitemattribute_value])) {
                                $attribute->orderitemattribute_value = $uploadTable->original_name;
                            }
                        }
                    }
                }
            }

            // Get order details
            $this->taxes    = $this->order->getOrderTaxrates();
            $this->shipping = $this->order->getOrderShippingRate();
            $this->coupons  = $this->order->getOrderCoupons();
            $this->vouchers = $this->order->getOrderVouchers();

            // Trigger AfterDisplayCart event
            $this->after_display_cart = '';
            $afterResults             = J2CommerceHelper::plugin()->event('AfterDisplayCart', [$this->order]);
            foreach ($afterResults as $result) {
                $this->after_display_cart .= $result;
            }
        } else {
            $this->items = $items;
        }

        // Get tax model via native MVC factory
        $this->taxModel = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Taxprofiles', 'Administrator', ['ignore_request' => true]);

        // Get shipping data
        $this->shipping_methods = $model->getShippingMethods();
        $this->shipping_values  = $model->getShippingValues();

        // Get URLs
        $this->checkout_url          = $model->getCheckoutUrl();
        $this->continue_shopping_url = $model->getContinueShoppingUrl();

        // Trigger BeforeCartView event (allows plugins to modify view)
        $cartView = $this;
        J2CommerceHelper::plugin()->event('BeforeCartView', [&$cartView]);

        // Trigger plugin event for custom HTML output
        $viewHtml = null;
        $app->triggerEvent('onJ2CommerceViewCartsHtml', [&$viewHtml, &$this, $model]);

        // If a plugin provided HTML output, display it and return
        if (!empty($viewHtml)) {
            echo $viewHtml;
            return;
        }

        // Prepare document
        $this->_prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function _prepareDocument(): void
    {
        $pageTitle = $this->menuItemParams->get('page_title', '');
        if (empty($pageTitle)) {
            $pageTitle = Text::_('COM_J2COMMERCE_CARTS_PAGE_TITLE');
        }
        $this->getDocument()->setTitle($pageTitle);

        $metaDesc = $this->menuItemParams->get('menu-meta_description', '');
        if (empty($metaDesc)) {
            $metaDesc = Text::_('COM_J2COMMERCE_CARTS_META_DESC');
        }
        $this->getDocument()->setDescription($metaDesc);

        if ($this->menuItemParams->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->menuItemParams->get('menu-meta_keywords'));
        }

        if ($this->menuItemParams->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->menuItemParams->get('robots'));
        }
    }
}
