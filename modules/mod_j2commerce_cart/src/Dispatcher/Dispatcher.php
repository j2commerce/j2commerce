<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_cart
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Cart\Site\Dispatcher;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

class Dispatcher extends AbstractModuleDispatcher
{
    private function findMenuItemId(string $link): int
    {
        $menu  = Factory::getApplication()->getMenu();
        $items = $menu->getItems('link', $link);

        foreach ($items as $item) {
            if ((int) ($item->published ?? 0) === 1) {
                return (int) $item->id;
            }
        }

        return 0;
    }

    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];
        $app    = Factory::getApplication();

        // Guarantees j2commerce.js loads even when the rendering page is not a J2C view —
        // without it, add-to-cart forms fall back to server-side redirect and mini-cart only refreshes on reload.
        $app->getDocument()->getWebAssetManager()
            ->registerAndUseScript(
                'com_j2commerce.site',
                'media/com_j2commerce/js/site/j2commerce.js',
                [],
                ['defer' => true]
            );

        // Fallback load from component path — root language/ may not have com_j2commerce.ini.
        $language = $app->getLanguage();
        $language->load('com_j2commerce', JPATH_SITE);
        $language->load('com_j2commerce', JPATH_SITE . '/components/com_j2commerce');
        $language->load('mod_j2commerce_cart', JPATH_SITE . '/modules/mod_j2commerce_cart');

        $items          = [];
        $order          = null;
        $productCount   = 0;
        $total          = 0.0;
        $formattedTotal = '';
        $cartUrl        = '';
        $checkoutUrl    = '';
        $ajaxUrl        = '';

        try {
            $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();
            $cartModel  = $mvcFactory->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($cartModel) {
                $items = $cartModel->getItems();
            }

            if (!empty($items)) {
                // Cart items use product_qty, order items use orderitem_quantity.
                if ((int) $params->get('quantity_count', 1) === 1) {
                    foreach ($items as $item) {
                        $productCount += (int) ($item->orderitem_quantity ?? $item->product_qty ?? 0);
                    }
                } else {
                    $productCount = \count($items);
                }

                $order = OrderHelper::getInstance()
                    ->populateOrder($items)
                    ->getOrder();

                if ($order && isset($order->order_total)) {
                    $total = (float) $order->order_total;
                }

                if ($order && method_exists($order, 'getItems')) {
                    $items = $order->getItems();
                }
            }

            $formattedTotal = CurrencyHelper::format($total);

            $cartMenuItemId     = (int) $params->get('cart_menu_item', 0);
            $checkoutMenuItemId = (int) $params->get('checkout_menu_item', 0);

            if ($cartMenuItemId > 0) {
                $cartUrl = Route::_('index.php?option=com_j2commerce&view=carts&Itemid=' . $cartMenuItemId);
            }

            if ($checkoutMenuItemId > 0) {
                $checkoutUrl = Route::_('index.php?option=com_j2commerce&view=checkout&Itemid=' . $checkoutMenuItemId);
            }

            $ajaxUrl = Route::_('index.php?option=com_j2commerce&task=carts.ajaxmini&format=raw', false);
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add(
                'mod_j2commerce_cart: ' . $e->getMessage(),
                \Joomla\CMS\Log\Log::ERROR,
                'mod_j2commerce_cart'
            );
        }

        $isAjax = $app->getUserState('mod_j2commerce_mini_cart.isAjax', false);

        $data['productCount']   = $productCount;
        $data['cartTotal']      = $total;
        $data['formattedTotal'] = $formattedTotal;
        $data['cartUrl']        = $cartUrl;
        $data['checkoutUrl']    = $checkoutUrl;
        $data['ajaxUrl']        = $ajaxUrl;
        $data['isAjax']         = $isAjax;
        $data['items']          = $items;
        $data['order']          = $order;

        $subtemplate = $params->get('subtemplate', 'app_bootstrap5');
        $layout      = $params->get('layout', 'default');

        if ($subtemplate === 'app_uikit' && !str_ends_with($layout, '_uikit')) {
            $params->set('layout', $layout . '_uikit');
        }

        return $data;
    }
}
