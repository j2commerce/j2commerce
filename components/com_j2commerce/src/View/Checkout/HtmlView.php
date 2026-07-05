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
use J2Commerce\Component\J2commerce\Site\Helper\CheckoutContextHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Event\Event;

/**
 * HTML Checkout view class for site frontend.
 *
 * @since  6.0.0
 */
class HtmlView extends BaseHtmlView
{
    public $params = null;
    public $currency;
    public $storeProfile;
    public $user;
    public bool $logged           = false;
    public bool $showShipping     = false;
    public bool $showBilling      = true;
    public $order                 = null;
    public array $items           = [];
    public array $taxes           = [];
    public array $checkoutContext = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        UtilitiesHelper::sendNoCacheHeaders();

        $this->params       = $app->getParams();
        $this->currency     = J2CommerceHelper::currency();
        $this->storeProfile = J2CommerceHelper::storeProfile();
        $this->user         = $app->getIdentity();
        $this->logged       = ($this->user && $this->user->id > 0);

        // Nonce-activation: the consumer app redirects here with &checkout_context=<nonce>.
        // A matching nonce marks the context ACTIVATED so it owns subsequent steps.
        //
        // On EVERY View entry the nonce is re-checked, even for already-activated
        // contexts. If the buyer abandons mid-flow and navigates to a fresh checkout
        // URL (no nonce), the activated context is cleared so it cannot hijack the
        // subsequent normal cart checkout. A genuine refresh is safe because the nonce
        // remains in the address bar from the activation redirect.
        //
        // Phase 2 note: if SEF routing strips the checkout_context query parameter,
        // the nonce cannot survive refreshes and a server-side persistence mechanism
        // (e.g. session-stored nonce copy on activation) will be required.
        //
        // This guard applies to View::display() only. Step-AJAX and off-site return
        // paths hit the Controller directly and must preserve the activated context.
        $ctxPayload = CheckoutContextHelper::getContext();

        if ($ctxPayload !== null) {
            $urlNonce = $app->getInput()->getString('checkout_context', '');

            if (CheckoutContextHelper::isActivated()) {
                // Already-activated: still require the nonce each time so that a
                // fresh normal-checkout navigation (no nonce in URL) triggers teardown.
                $storedNonce = (string) ($ctxPayload['nonce'] ?? '');

                if ($urlNonce === '' || $storedNonce === '' || !hash_equals($storedNonce, $urlNonce)) {
                    CheckoutContextHelper::clearContext();
                }
            } elseif (!CheckoutContextHelper::checkNonce($urlNonce)) {
                // Not yet activated: a missing or non-matching nonce clears the stale context.
                CheckoutContextHelper::clearContext();
            }
        }

        // Single predicate: context owns this request when activated + resolved + valid.
        $contextOwns = CheckoutContextHelper::isOwningRequest();

        if ($contextOwns) {
            // Context mode: source order from the validated resolved context.
            $resolved    = CheckoutContextHelper::resolveContext();
            $order       = $resolved->getOrder();
            $items       = ($order && method_exists($order, 'getItems')) ? $order->getItems() : [];
            $this->order = $order;
            $this->items = $items;
            $this->taxes = ($order && method_exists($order, 'getOrderTaxrates')) ? $order->getOrderTaxrates() : [];

            CheckoutContextHelper::primeUserState($order);

            $this->showShipping = $resolved->getShowShipping();
            $this->showBilling  = $resolved->getShowBilling();

            $this->checkoutContext = [
                'active'       => true,
                'skipLogin'    => true,
                'skipBilling'  => !$this->showBilling,
                'skipShipping' => !$this->showShipping,
            ];
        } else {
            // Normal cart checkout — behaviour unchanged.
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
                $app->redirect(Route::_('index.php?option=com_j2commerce&view=carts'));

                return;
            }

            // Stock validation only for normal cart orders; context orders are already placed.
            if ($order && method_exists($order, 'validate_order_stock') && $order->validate_order_stock() === false) {
                $app->redirect(Route::_('index.php?option=com_j2commerce&view=carts'));

                return;
            }

            if ($this->params->get('show_shipping_address', 0)) {
                $this->showShipping = true;
            }

            if (!$this->showShipping) {
                foreach ($items as $item) {
                    if (!empty($item->shipping)) {
                        $this->showShipping = true;
                        break;
                    }
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
        HTMLHelper::_('bootstrap.modal');

        $this->registerFrameworkTemplatePaths($app);

        parent::display($tpl);
    }

    /** Register the bootstrap5/uikit subfolder; AJAX callers pass merged menu params as 2nd arg. */
    public function registerFrameworkTemplatePaths(
        \Joomla\CMS\Application\CMSApplicationInterface $app,
        ?\Joomla\Registry\Registry $params = null
    ): void {
        $params ??= $this->params;
        $framework = (string) ($params ? $params->get('framework', 'bootstrap5') : 'bootstrap5');
        $framework = preg_replace('/[^a-zA-Z0-9_-]/', '', $framework) ?? '';

        $viewName = $this->getName();
        $template = $app->getTemplate();

        $compRoot = JPATH_COMPONENT . '/tmpl/' . $viewName;
        $tplRoot  = JPATH_THEMES . '/' . $template . '/html/com_j2commerce/' . $viewName;

        $candidate = '';
        if ($framework !== '' && (is_dir($compRoot . '/' . $framework) || is_dir($tplRoot . '/' . $framework))) {
            $candidate = $framework;
        } elseif (is_dir($compRoot . '/bootstrap5') || is_dir($tplRoot . '/bootstrap5')) {
            $candidate = 'bootstrap5';
        } else {
            $entries = is_dir($compRoot) ? scandir($compRoot) : [];
            $entries = $entries ?: [];
            sort($entries);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }
                if (is_dir($compRoot . '/' . $entry) && is_file($compRoot . '/' . $entry . '/default.php')) {
                    $candidate = $entry;
                    break;
                }
            }
        }

        if ($candidate !== '' && is_dir($compRoot . '/' . $candidate)) {
            $this->addTemplatePath($compRoot . '/' . $candidate);
        }
        if (is_dir($tplRoot)) {
            $this->addTemplatePath($tplRoot);
        }
        if ($candidate !== '' && is_dir($tplRoot . '/' . $candidate)) {
            $this->addTemplatePath($tplRoot . '/' . $candidate);
        }
    }

    protected function _prepareDocument(): void
    {
        $menu = Factory::getApplication()->getMenu()->getActive();
        $this->params->def('page_heading', $menu ? $menu->title : '');

        $pageTitle = $this->params->get('page_title', '');
        if (empty($pageTitle)) {
            $pageTitle = Text::_('COM_J2COMMERCE_CHECKOUT_PAGE_TITLE');
        }
        $this->getDocument()->setTitle($pageTitle);

        $metaDesc = $this->params->get('menu-meta_description', '');
        if (empty($metaDesc)) {
            $metaDesc = Text::_('COM_J2COMMERCE_CHECKOUT_META_DESC');
        }
        $this->getDocument()->setDescription($metaDesc);

        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }
    }
}
