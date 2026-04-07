<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Checkout;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    public $params = null;
    public $currency;
    public $storeProfile;
    public $user;
    public bool $logged       = false;
    public bool $showShipping = false;
    public $menuItemParams    = null;
    public $order             = null;
    public array $items       = [];
    public array $taxes       = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        UtilitiesHelper::sendNoCacheHeaders();

        $this->params       = J2CommerceHelper::config();
        $this->currency     = J2CommerceHelper::currency();
        $this->storeProfile = J2CommerceHelper::storeProfile();
        $this->user         = $app->getIdentity();
        $this->logged       = ($this->user && $this->user->id > 0);

        $menu                 = $app->getMenu();
        $active               = $menu->getActive();
        $this->menuItemParams = \is_object($active) ? $active->getParams() : new Registry('{}');

        // Check cart has items — use cart model to get order with items
        $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();
        $cartsModel = $mvcFactory->createModel('Carts', 'Site', ['ignore_request' => true]);

        if ($cartsModel) {
            $cartsModel->getState();

            if ($this->user && $this->user->id) {
                $cartsModel->setState('filter.user_id', (int) $this->user->id);
            }
        }

        $order = $cartsModel ? $cartsModel->getOrder() : null;
        $items = $order ? $order->getItems() : [];

        $this->order = $order;
        $this->items = $items;
        $this->taxes = ($order && method_exists($order, 'getOrderTaxrates')) ? $order->getOrderTaxrates() : [];

        if (\count($items) < 1) {
            $cartUrl = Route::_('index.php?option=com_j2commerce&view=carts');
            $app->redirect($cartUrl);

            return;
        }

        // Validate stock
        if ($order->validate_order_stock() === false) {
            $cartUrl = Route::_('index.php?option=com_j2commerce&view=carts');
            $app->redirect($cartUrl);

            return;
        }

        // Determine if shipping is needed
        if ($this->params->get('show_shipping_address', 0)) {
            $this->showShipping = true;
        }

        // Check if any cart item has shipping enabled (variants.shipping = 1)
        if (!$this->showShipping) {
            foreach ($items as $item) {
                if (!empty($item->shipping)) {
                    $this->showShipping = true;
                    break;
                }
            }
        }

        // Fire plugin event
        J2CommerceHelper::plugin()->event('BeforeCheckout', [$order, &$this]);

        // Actionlog: track checkout page view (must be in View, not Controller,
        // because DisplayController handles ?view=checkout routing)
        $app->getDispatcher()->dispatch(
            'onJ2CommerceCheckoutStart',
            new Event('onJ2CommerceCheckoutStart', [])
        );

        $this->_prepareDocument();

        HTMLHelper::_('bootstrap.collapse', 'checkoutSidecartCollapse');

        parent::display($tpl);
    }

    protected function _prepareDocument(): void
    {
        $pageTitle = $this->menuItemParams->get('page_title', '');
        if (empty($pageTitle)) {
            $pageTitle = Text::_('COM_J2COMMERCE_CHECKOUT_PAGE_TITLE');
        }
        $this->getDocument()->setTitle($pageTitle);

        $metaDesc = $this->menuItemParams->get('menu-meta_description', '');
        if (empty($metaDesc)) {
            $metaDesc = Text::_('COM_J2COMMERCE_CHECKOUT_META_DESC');
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
