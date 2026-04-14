<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/**
 * Carts Controller for site frontend
 *
 * Handles cart operations: add, update, remove, coupons, vouchers, shipping estimation
 *
 * @since  6.0.0
 */
class CartsController extends BaseController
{
    /**
     * Get the cart model from admin
     *
     * @return  CartModel
     *
     * @since   6.0.0
     */
    protected function getCartModel(): CartModel
    {
        /** @var CartModel $model */
        $model = $this->app->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createModel('Cart', 'Administrator', ['ignore_request' => true]);

        return $model;
    }

    /**
     * Validate CSRF token for AJAX endpoints without triggering redirects.
     *
     * Joomla's Session::checkToken() and BaseController::checkToken() both
     * redirect to the homepage when the session is new (e.g., after login or
     * session regeneration). This breaks AJAX endpoints that need JSON responses.
     * This method performs the same token validation without any redirect.
     *
     * @return  bool  True if the token is valid, false otherwise.
     *
     * @since   6.0.0
     */
    private function validateAjaxToken(): bool
    {
        $token = \Joomla\CMS\Session\Session::getFormToken();

        // Check X-CSRF-Token header first (for fetch/XHR)
        if ($token === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        // Then check POST body (truthy check, matching Joomla's Session::checkToken behavior)
        return (bool) $this->input->post->get($token, '', 'alnum');
    }

    /**
     * Start output buffering for AJAX methods.
     *
     * Captures any stray PHP output (warnings, notices, deprecations) that would
     * otherwise corrupt the JSON response. Must be paired with sendJsonResponse().
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function startAjaxBuffer(): void
    {
        ob_start();
    }

    /**
     * Send a JSON response and close the application.
     *
     * Cleans the AJAX output buffer started by startAjaxBuffer(), logs any stray
     * output (PHP warnings, etc.) that would have corrupted the JSON, sets the
     * proper Content-Type header, and sends the JSON response.
     *
     * @param   array|object  $data  The data to encode as JSON
     *
     * @return  never
     *
     * @since   6.0.0
     */
    private function sendJsonResponse(array|object $data): void
    {
        // Discard any stray output (PHP warnings, notices) that would corrupt JSON
        ob_end_clean();

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data);
        $this->app->close();
    }

    /**
     * Re-fetch shipping rates from plugins and validate the current selection.
     *
     * Called before rendering cart totals so that quantity/item changes that push
     * the order outside a shipping rate range are detected. If the previously
     * selected method is no longer offered, the lowest-price method is auto-selected.
     */
    private function refreshShippingMethods(): void
    {
        $session   = $this->app->getSession();
        $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
        $zoneId    = (int) $session->get('shipping_zone_id', 0, 'j2commerce');

        // No estimate was performed yet -- nothing to refresh
        if ($countryId === 0 && $zoneId === 0) {
            return;
        }

        /** @var \J2Commerce\Component\J2commerce\Site\Model\CartsModel $cartsModel */
        $cartsModel = $this->getModel('Carts');
        $items      = $cartsModel->getItems();

        // Cart is empty -- clear shipping session data
        if (empty($items)) {
            $session->clear('shipping_methods', 'j2commerce');
            $session->clear('shipping_values', 'j2commerce');

            return;
        }

        $order   = OrderHelper::getInstance()->populateOrder($items)->getOrder();
        $methods = J2CommerceHelper::plugin()->eventWithArray('GetShippingRates', [$order]);

        // Sort by price ascending so cheapest rates appear first
        usort($methods, fn (array $a, array $b) => ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0)));

        $session->set('shipping_methods', $methods, 'j2commerce');

        if (empty($methods)) {
            $session->clear('shipping_methods', 'j2commerce');
            $session->clear('shipping_values', 'j2commerce');

            return;
        }

        // Validate current selection against refreshed methods
        $shippingValues = $session->get('shipping_values', [], 'j2commerce');
        $selectedName   = $shippingValues['shipping_name'] ?? '';
        $found          = false;

        foreach ($methods as $method) {
            if (($method['name'] ?? '') === $selectedName) {
                $found = true;

                // Sync tax data from fresh rate (amounts/class may have changed)
                $shippingValues['shipping_tax']          = $method['tax'] ?? 0;
                $shippingValues['shipping_tax_class_id'] = $method['tax_class_id'] ?? 0;
                $shippingValues['shipping_price']        = $method['price'] ?? 0;
                $session->set('shipping_values', $shippingValues, 'j2commerce');
                break;
            }
        }

        // Current selection is still valid -- keep it
        if ($found) {
            return;
        }

        // Auto-select the lowest-price method
        $lowest = null;

        foreach ($methods as $method) {
            if ($lowest === null || (float) $method['price'] < (float) $lowest['price']) {
                $lowest = $method;
            }
        }

        if ($lowest !== null) {
            $session->set('shipping_values', [
                'shipping_name'         => $lowest['name'],
                'shipping_price'        => $lowest['price'],
                'shipping_tax'          => $lowest['tax'],
                'shipping_tax_class_id' => $lowest['tax_class_id'] ?? 0,
                'shipping_extra'        => $lowest['extra'],
                'shipping_code'         => $lowest['code'],
                'shipping_plugin'       => $lowest['element'],
            ], 'j2commerce');
        }
    }

    private function getProductNameByProductId(int $productId): string
    {
        if ($productId <= 0) {
            return 'Unknown Product';
        }

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('c.title'))
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->innerJoin($db->quoteName('#__content', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id'))
            ->where($db->quoteName('p.j2commerce_product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: 'Unknown Product';
    }

    /**
     * Add item to cart
     *
     * Handles both AJAX and regular form submissions. For AJAX requests,
     * returns JSON response with proper content-type header.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addItem(): void
    {
        // Check if AJAX submission early to ensure JSON response on errors
        $ajax = $this->input->getInt('ajax', 0);

        // Buffer output to prevent PHP warnings from corrupting JSON response
        if ($ajax) {
            $this->startAjaxBuffer();
        }

        try {
            $model  = $this->getCartModel();
            $result = $model->addCartItem();

            $json = [];
            if (\is_object($result)) {
                $json = (array) $result;
            } elseif (\is_array($result)) {
                $json = $result;
            }

            // Log to action log on success
            if (!empty($json['success'])) {
                $logProductId   = $this->input->getInt('product_id', 0);
                $logProductName = $this->getProductNameByProductId($logProductId);
                $logQuantity    = $this->input->getInt('product_qty', 1);
                PluginHelper::importPlugin('actionlog');
                $this->app->getDispatcher()->dispatch(
                    'onJ2CommerceAfterAddToCart',
                    new \Joomla\Event\Event('onJ2CommerceAfterAddToCart', [$logProductName, $logProductId, $logQuantity])
                );
            }

            $config  = J2CommerceHelper::config();
            $cartUrl = $model->getCartUrl();

            if ($ajax) {
                if (isset($json['success'])) {
                    if ($config->get('addtocart_action', 1) == 3) {
                        $json['redirect'] = $cartUrl;
                    }
                }

                $json['product_redirect'] = J2CommerceHelper::platform()->getProductUrl([
                    'task' => 'view',
                    'id'   => $this->input->getInt('product_id'),
                ]);

                $this->sendJsonResponse($json);
            } else {
                $return = $this->input->getBase64('return');

                if ($return !== null) {
                    $returnUrl = base64_decode($return);
                } else {
                    $returnUrl = $cartUrl;
                }

                if (!empty($json['success'])) {
                    $this->setRedirect($cartUrl, Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'), 'success');
                } elseif (!empty($json['error'])) {
                    $error = UtilitiesHelper::errorsToString($json['error']);
                    $this->setRedirect($returnUrl, $error, 'error');
                } else {
                    $this->setRedirect($returnUrl);
                }
            }
        } catch (\Exception $e) {
            // Handle any uncaught exceptions
            if ($ajax) {
                $this->sendJsonResponse([
                    'success' => 0,
                    'error'   => ['general' => $e->getMessage()],
                ]);
            } else {
                // For non-AJAX, redirect with error message
                $this->setRedirect(
                    Route::_('index.php?option=com_j2commerce&view=carts'),
                    $e->getMessage(),
                    'error'
                );
            }
        }
    }

    /**
     * Force shipping validation
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function forceshipping(): void
    {
        $this->startAjaxBuffer();

        $json = J2CommerceHelper::plugin()->eventWithArray('ValidateShipping');

        $this->sendJsonResponse($json);
    }

    /**
     * Update cart quantities
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function update(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();

        $model  = $this->getCartModel();
        $result = $model->update();

        if (isset($result['error'])) {
            $msg = $result['error'];
        } else {
            $msg = Text::_('COM_J2COMMERCE_CART_UPDATED_SUCCESSFULLY');
        }

        $url = $model->getCartUrl();
        $this->setRedirect($url, $msg, 'notice');
    }

    /**
     * Clear all items from cart
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function clearCart(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();

        $model = $this->getCartModel();
        $items = $model->getItems();

        foreach ($items as $item) {
            $cartitem = $this->factory->createTable('Cartitem', 'Administrator');

            if ($cartitem->delete($item->j2commerce_cartitem_id)) {
                J2CommerceHelper::plugin()->event('RemoveFromCart', [$item]);
            }
        }

        $msg = Text::_('COM_J2COMMERCE_CART_CLEAR_SUCCESSFULLY');
        $url = $model->getCartUrl();
        $this->setRedirect($url, $msg, 'notice');
    }

    /**
     * Clear all items from cart via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function clearCartAjax(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();
        $this->startAjaxBuffer();

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            $this->sendJsonResponse($json);
        }

        try {
            $model = $this->getCartModel();
            $items = $model->getItems();

            foreach ($items as $item) {
                $cartitem = $this->factory->createTable('Cartitem', 'Administrator');

                if ($cartitem->delete($item->j2commerce_cartitem_id)) {
                    J2CommerceHelper::plugin()->event('RemoveFromCart', [$item]);
                }
            }

            // Clear coupon, voucher, and shipping session data
            $session = $this->app->getSession();
            $session->clear('coupon', 'j2commerce');
            $session->clear('voucher', 'j2commerce');
            $session->clear('shipping_values', 'j2commerce');
            $session->clear('shipping_methods', 'j2commerce');
            $session->clear('order_fees', 'j2commerce');

            // Actionlog: track cart clear
            PluginHelper::importPlugin('actionlog');
            $this->app->getDispatcher()->dispatch(
                'onJ2CommerceAfterClearCart',
                new \Joomla\Event\Event('onJ2CommerceAfterClearCart', [])
            );

            $json['success'] = true;
            $json['message'] = Text::_('COM_J2COMMERCE_CART_CLEAR_SUCCESSFULLY');
        } catch (\Exception $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Remove single item from cart
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function remove(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();

        $model = $this->getCartModel();

        if ($model->deleteItem()) {
            $msg = Text::_('COM_J2COMMERCE_CART_UPDATED_SUCCESSFULLY');
        } else {
            $msg = $model->getError();
        }

        $url = $model->getCartUrl();
        $this->setRedirect($url, $msg, 'notice');
    }

    /**
     * Remove single item from cart via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeAjax(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();
        $this->startAjaxBuffer();

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            $this->sendJsonResponse($json);
        }

        $model      = $this->getCartModel();
        $cartitemId = $this->input->getInt('cartitem_id', 0);

        if (!$cartitemId) {
            $json['success'] = false;
            $json['message'] = Text::_('COM_J2COMMERCE_INVALID_CART_ITEM');
            $this->sendJsonResponse($json);
        }

        try {
            $cartitem = $this->factory->createTable('Cartitem', 'Administrator');
            $cartitem->load($cartitemId);

            // Ownership check: verify cartitem belongs to the current user's cart
            $cart          = $model->getCart(0, false);
            $currentCartId = $cart ? (int) $cart->j2commerce_cart_id : 0;

            if (!$cartitem->j2commerce_cartitem_id || (int) $cartitem->cart_id !== $currentCartId) {
                $json['success'] = false;
                $json['message'] = Text::_('COM_J2COMMERCE_CART_ITEM_NOT_FOUND');
                $this->sendJsonResponse($json);
            }

            if ($cartitem->j2commerce_cartitem_id) {
                if ($cartitem->delete($cartitemId)) {
                    J2CommerceHelper::plugin()->event('RemoveFromCart', [$cartitem]);

                    // Actionlog: track removal with product name
                    $logRemoveProductName = $this->getProductNameByProductId((int) ($cartitem->product_id ?? 0));
                    PluginHelper::importPlugin('actionlog');
                    $this->app->getDispatcher()->dispatch(
                        'onJ2CommerceAfterRemoveFromCart',
                        new \Joomla\Event\Event('onJ2CommerceAfterRemoveFromCart', [$logRemoveProductName, (int) ($cartitem->product_id ?? 0)])
                    );

                    $json['success']  = true;
                    $json['message']  = Text::_('COM_J2COMMERCE_CART_UPDATED_SUCCESSFULLY');
                    $json['redirect'] = $model->getCartUrl();
                } else {
                    $json['success'] = false;
                    $json['message'] = Text::_('COM_J2COMMERCE_ERROR_REMOVING_ITEM');
                }
            }
        } catch (\Exception $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Update item quantity via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function updateQuantityAjax(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();
        $this->startAjaxBuffer();

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            $this->sendJsonResponse($json);
        }

        $cartitemId = $this->input->getInt('cartitem_id', 0);
        $newQty     = $this->input->getInt('qty', 1);

        if (!$cartitemId) {
            $json['success'] = false;
            $json['message'] = Text::_('COM_J2COMMERCE_INVALID_CART_ITEM');
            $this->sendJsonResponse($json);
        }

        if ($newQty < 1) {
            $newQty = 1;
        }

        try {
            // Load the cart item
            $cartitem = $this->factory->createTable('Cartitem', 'Administrator');

            if (!$cartitem->load($cartitemId)) {
                $json['success'] = false;
                $json['message'] = Text::_('COM_J2COMMERCE_CART_ITEM_NOT_FOUND');
                $this->sendJsonResponse($json);
            }

            $originalQty = (int) $cartitem->product_qty;

            // Validate quantity against min/max constraints
            // Load product to get constraints
            $model     = $this->getCartModel();
            $db        = Factory::getContainer()->get('DatabaseDriver');
            $variantId = (int) $cartitem->variant_id;

            $query = $db->getQuery(true)
                ->select([
                    'v.min_sale_qty',
                    'v.max_sale_qty',
                    'v.manage_stock',
                    'v.price',
                    'COALESCE(pq.quantity, 0) as stock_qty',
                ])
                ->from($db->quoteName('#__j2commerce_variants', 'v'))
                ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'pq'), 'pq.variant_id = v.j2commerce_variant_id')
                ->where($db->quoteName('v.j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $variantId, ParameterType::INTEGER);

            $db->setQuery($query);
            $productInfo = $db->loadObject();

            $minQty = (int) ($productInfo->min_sale_qty ?? 0) ?: 1;
            $maxQty = (int) ($productInfo->max_sale_qty ?? 0);

            if ($newQty < $minQty) {
                $newQty          = $minQty;
                $json['message'] = Text::sprintf('COM_J2COMMERCE_MINIMUM_QUANTITY_REQUIRED', $minQty);
            }

            if ($maxQty > 0 && $newQty > $maxQty) {
                $newQty          = $maxQty;
                $json['message'] = Text::sprintf('COM_J2COMMERCE_MAXIMUM_QUANTITY_ALLOWED', $maxQty);
            }

            if ($productInfo && $productInfo->manage_stock) {
                $stockQty = (int) $productInfo->stock_qty;

                if ($newQty > $stockQty) {
                    $json['success']      = false;
                    $json['message']      = Text::sprintf('COM_J2COMMERCE_NOT_ENOUGH_STOCK', $stockQty);
                    $json['original_qty'] = $originalQty;
                    $this->sendJsonResponse($json);
                }
            }

            // Update the cart item quantity
            $cartitem->product_qty = $newQty;

            if ($cartitem->store()) {
                // Actionlog: track quantity change with product name
                // NOTE: Joomla's plg_system_actionlogs auto-imports actionlog plugins on onAfterInitialise,
                // so our plg_actionlog_j2commerce subscriber is already registered. We dispatch once here.
                // Do NOT also call J2CommerceHelper::plugin()->event('AfterUpdateCartQuantity', ...)
                // as it dispatches the same event name and would cause duplicate log entries.
                $logUpdateProductName = $this->getProductNameByProductId((int) ($cartitem->product_id ?? 0));
                $this->app->getDispatcher()->dispatch(
                    'onJ2CommerceAfterUpdateCartQuantity',
                    new \Joomla\Event\Event('onJ2CommerceAfterUpdateCartQuantity', [$logUpdateProductName, $originalQty, $newQty])
                );

                $json['success']  = true;
                $json['qty']      = $newQty;
                $json['redirect'] = $model->getCartUrl();

                // Get the refreshed order with recalculated totals
                // Use the site CartsModel to get the order with proper line item calculations
                /** @var \J2Commerce\Component\J2commerce\Site\Model\CartsModel $cartsModel */
                $cartsModel = $this->getModel('Carts');
                $order      = $cartsModel->getOrder();

                if ($order) {
                    $currency             = J2CommerceHelper::currency();
                    $params               = J2CommerceHelper::config();
                    $checkoutPriceDisplay = (int) $params->get('checkout_price_display_options', 0);

                    // Find the updated item in the order and get its calculated line total
                    $orderItems = $order->getItems();
                    foreach ($orderItems as $orderItem) {
                        $itemCartId = $orderItem->cartitem_id ?? $orderItem->j2commerce_cartitem_id ?? 0;
                        if ((int) $itemCartId === $cartitemId) {
                            $lineTotal          = $order->get_formatted_lineitem_total($orderItem, $checkoutPriceDisplay);
                            $json['line_total'] = $currency->format($lineTotal);
                            break;
                        }
                    }
                }

                if (!isset($json['message'])) {
                    $json['message'] = Text::_('COM_J2COMMERCE_CART_UPDATED_SUCCESSFULLY');
                }
            } else {
                $json['success']      = false;
                $json['message']      = Text::_('COM_J2COMMERCE_ERROR_UPDATING_CART');
                $json['original_qty'] = $originalQty;
            }
        } catch (\Exception $e) {
            $json['success']      = false;
            $json['message']      = $e->getMessage();
            $json['original_qty'] = $originalQty ?? 1;
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Get cart totals HTML via AJAX
     *
     * Returns the rendered cart totals section for updating without full page reload.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function getTotalsAjax(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();
        $this->startAjaxBuffer();

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            $this->sendJsonResponse($json);
        }

        // Refresh shipping methods so stale selections are corrected before
        // the order is built and totals rendered.
        $this->refreshShippingMethods();

        try {
            // Get the view and model
            $view = $this->getView('Carts', 'Html', '', ['base_path' => $this->basePath]);

            /** @var \J2Commerce\Component\J2commerce\Site\Model\CartsModel $model */
            $model = $this->getModel('Carts');
            $view->setModel($model, true);
            $view->document = $this->app->getDocument();

            // CRITICAL: Populate view properties needed by default_totals.php
            // The template requires $this->order and $this->checkout_url
            $view->params       = J2CommerceHelper::config();
            $view->currency     = $model->getCurrency();
            $view->order        = $model->getOrder();
            $view->checkout_url = $model->getCheckoutUrl();

            // Capture the totals template output
            ob_start();
            $view->setLayout('default');
            echo $view->loadTemplate('totals');
            $html = ob_get_clean();

            // Also render shipping methods for estimate flow
            $view->shipping_methods = $model->getShippingMethods();
            $view->shipping_values  = $model->getShippingValues();

            ob_start();
            echo $view->loadTemplate('shipping');
            $shippingHtml = ob_get_clean();

            $json['success']       = true;
            $json['html']          = $html;
            $json['shipping_html'] = $shippingHtml;
        } catch (\Exception $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        $this->sendJsonResponse($json);
    }

    /**
     * AJAX mini cart update
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function ajaxmini(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        $this->startAjaxBuffer();

        $db       = Factory::getContainer()->get('DatabaseDriver');
        $language = $this->app->getLanguage()->getTag();

        $query = $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_j2commerce_cart'))
            ->where($db->quoteName('published') . ' = 1')
            ->where('(' . $db->quoteName('language') . ' = ' . $db->quote('*') . ' OR ' . $db->quoteName('language') . ' = ' . $db->quote($language) . ')');

        $db->setQuery($query);
        $modules = $db->loadObjectList();

        if (\count($modules) < 1) {
            $query = $db->getQuery(true);
            $query->select('*')
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_j2commerce_cart'))
                ->where($db->quoteName('published') . ' = 1')
                ->where('(' . $db->quoteName('language') . ' = ' . $db->quote('*') . ' OR ' . $db->quoteName('language') . ' = ' . $db->quote('en-GB') . ')');

            $db->setQuery($query);
            $modules = $db->loadObjectList();
        }

        $json = [];

        if (\count($modules) < 1) {
            $json['response'] = ' ';
        } else {
            foreach ($modules as $module) {
                $this->app->setUserState('mod_j2commerce_mini_cart.isAjax', '1');
                $json['response'][$module->id] = ModuleHelper::renderModule($module, ['style' => 'none']);
            }
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Set currency
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setcurrency(): void
    {
        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();

        $currency     = J2CommerceHelper::currency();
        $currencyCode = $this->input->getString('currency_code', '');

        if (!empty($currencyCode)) {
            $currency->setCurrency($currencyCode);
        }

        $redirect = $this->input->getString('redirect', '');

        if (!empty($redirect)) {
            $url = base64_decode($redirect);
        } else {
            $url = 'index.php';
        }

        $this->app->redirect($url);
    }

    /**
     * Apply coupon code
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyCoupon(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $model  = $this->getCartModel();
        $coupon = $this->input->getString('coupon', '');

        if (!empty($coupon)) {
            $couponModel = $this->factory->createModel('Coupon', 'Administrator');
            $couponModel->setCoupon($coupon);
        }

        $redirect = $this->input->getBase64('redirect', '');

        if (!empty($redirect)) {
            $url = Route::_(base64_decode($redirect));
        } else {
            $url = $model->getCartUrl();
        }

        $this->setRedirect($url);
    }

    /**
     * Remove coupon code
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeCoupon(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $model       = $this->getCartModel();
        $couponModel = $this->factory->createModel('Coupon', 'Administrator');


        if ($couponModel->hasCoupon()) {
            $couponModel->removeCoupon();
            $msg     = Text::_('COM_J2COMMERCE_COUPON_REMOVED_SUCCESSFULLY');
            $msgType = 'success';
        } else {
            $msg     = Text::_('COM_J2COMMERCE_PROBLEM_REMOVING_COUPON');
            $msgType = 'notice';
        }

        $url = $model->getCartUrl();
        $this->setRedirect($url, $msg, $msgType);
    }

    /**
     * Apply voucher code
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyVoucher(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $model        = $this->getCartModel();
        $voucher      = $this->input->getString('voucher', '');
        $voucherModel = $this->factory->createModel('Voucher', 'Administrator');
        if (!empty($voucher)) {
            $voucherModel->setVoucher($voucher);
        }

        $redirect = $this->input->getBase64('redirect', '');

        if (!empty($redirect)) {
            $url = Route::_(base64_decode($redirect));
        } else {
            $url = $model->getCartUrl();
        }

        $this->setRedirect($url);
    }

    /**
     * Remove voucher code
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeVoucher(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        J2CommerceHelper::plugin()->event('BeforeRemoveVoucher');

        $model        = $this->getCartModel();
        $voucherModel = $this->factory->createModel('Voucher', 'Administrator');

        if ($voucherModel->hasVoucher()) {
            $voucherModel->removeVoucher();
            $msg     = Text::_('COM_J2COMMERCE_VOUCHER_REMOVED_SUCCESSFULLY');
            $msgType = 'success';
        } else {
            $msg     = Text::_('COM_J2COMMERCE_PROBLEM_REMOVING_VOUCHER');
            $msgType = 'notice';
        }

        $url = $model->getCartUrl();
        $this->setRedirect($url, $msg, $msgType);
    }

    /**
     * Apply coupon code via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyCouponAjax(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        if (!$this->validateAjaxToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
        }

        $coupon = $this->input->getString('coupon', '');

        if (empty($coupon)) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ENTER_COUPON_CODE')]);
        }

        try {
            $couponModel = $this->factory->createModel('Coupon', 'Administrator');
            $couponModel->setCoupon($coupon);
            $couponModel->init();

            // Build lightweight order context for validateMinimumAmount
            $orderContext                 = new \stdClass();
            $orderContext->subtotal       = 0;
            $orderContext->order_subtotal = 0;

            try {
                $cartModel = $this->factory->createModel('Cart', 'Administrator', ['ignore_request' => true]);
                $cartModel->getState();
                $items = $cartModel->getItems();

                foreach ($items as $item) {
                    $orderContext->subtotal += (float) ($item->product_subtotal ?? 0);
                }

                $orderContext->order_subtotal = $orderContext->subtotal;
            } catch (\Exception $e) {
                // Cart unavailable — subtotal stays 0, min-amount check may fail
            }

            if (!$couponModel->isValid($orderContext)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $couponModel->getError() ?: Text::_('COM_J2COMMERCE_COUPON_NOT_VALID'),
                ]);
            }

            $this->sendJsonResponse([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_COUPON_APPLIED_SUCCESSFULLY'),
                'coupon'  => $coupon,
            ]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove coupon code via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeCouponAjax(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        if (!$this->validateAjaxToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
        }

        $json = [];

        try {
            $couponModel = $this->factory->createModel('Coupon', 'Administrator');

            if ($couponModel->hasCoupon()) {
                $couponModel->removeCoupon();
                $json = [
                    'success' => true,
                    'message' => Text::_('COM_J2COMMERCE_COUPON_REMOVED_SUCCESSFULLY'),
                ];
            } else {
                $json = [
                    'success' => false,
                    'message' => Text::_('COM_J2COMMERCE_PROBLEM_REMOVING_COUPON'),
                ];
            }
        } catch (\Exception $e) {
            $json = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Apply voucher code via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyVoucherAjax(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        if (!$this->validateAjaxToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
        }

        $voucher = $this->input->getString('voucher', '');

        if (empty($voucher)) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ENTER_VOUCHER_CODE')]);
        }

        try {
            $voucherModel = $this->factory->createModel('Voucher', 'Administrator');
            $voucherModel->setVoucher($voucher);

            // Load voucher data and validate
            $voucherModel->voucher = $voucherModel->getVoucherByCode($voucher);

            if (!$voucherModel->isValid()) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => $voucherModel->getError() ?: Text::_('COM_J2COMMERCE_VOUCHER_NOT_VALID'),
                ]);
            }

            $this->sendJsonResponse([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_VOUCHER_APPLIED_SUCCESSFULLY'),
                'voucher' => $voucher,
            ]);
        } catch (\Exception $e) {
            $this->sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove voucher code via AJAX
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeVoucherAjax(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        if (!$this->validateAjaxToken()) {
            $this->sendJsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
        }

        $json = [];

        try {
            J2CommerceHelper::plugin()->event('BeforeRemoveVoucher');

            $voucherModel = $this->factory->createModel('Voucher', 'Administrator');

            if ($voucherModel->hasVoucher()) {
                $voucherModel->removeVoucher();
                $json = [
                    'success' => true,
                    'message' => Text::_('COM_J2COMMERCE_VOUCHER_REMOVED_SUCCESSFULLY'),
                ];
            } else {
                $json = [
                    'success' => false,
                    'message' => Text::_('COM_J2COMMERCE_PROBLEM_REMOVING_VOUCHER'),
                ];
            }
        } catch (\Exception $e) {
            $json = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Estimate shipping/tax based on location
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function estimate(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        $model   = $this->getCartModel();
        $session = $this->app->getSession();

        $countryId       = $this->input->getInt('country_id', 0);
        $zoneId          = $this->input->getInt('zone_id', 0);
        $postcode        = $this->input->getString('postcode', '');
        $city            = $this->input->getString('city', '');
        $countryRequired = $this->input->getInt('country_required', 1);
        $zoneRequired    = $this->input->getInt('zone_required', 1);
        $postalRequired  = $this->input->getInt('postal_required', 0);

        $json   = [];
        $params = J2CommerceHelper::config();

        if (!$countryId && $countryRequired) {
            $json['error']['country_id'] = Text::_('COM_J2COMMERCE_ESTIMATE_COUNTRY_REQUIRED');
        }

        if (!$zoneId && $zoneRequired) {
            $json['error']['zone_id'] = Text::_('COM_J2COMMERCE_ESTIMATE_ZONE_REQUIRED');
        }

        if (($postalRequired || $params->get('postalcode_required', 0)) && empty($postcode)) {
            $json['error']['postcode'] = Text::_('COM_J2COMMERCE_ESTIMATE_POSTALCODE_REQUIRED');
        }

        // Plugin validation event
        J2CommerceHelper::plugin()->event('BeforeShippingEstimate', [&$json]);

        if (empty($json)) {
            if ($countryId || $zoneId) {
                if ($countryId) {
                    $session->set('billing_country_id', $countryId, 'j2commerce');
                    $session->set('shipping_country_id', $countryId, 'j2commerce');
                }

                if ($zoneId) {
                    $session->set('billing_zone_id', $zoneId, 'j2commerce');
                    $session->set('shipping_zone_id', $zoneId, 'j2commerce');
                }

                $session->set('force_calculate_shipping', 1, 'j2commerce');
            }

            if ($postcode) {
                $session->set('shipping_postcode', $postcode, 'j2commerce');
                $session->set('billing_postcode', $postcode, 'j2commerce');
            }

            if ($city) {
                $session->set('shipping_city', $city, 'j2commerce');
                $session->set('billing_city', $city, 'j2commerce');
            }

            $url              = $model->getCartUrl();
            $json['redirect'] = $url;
        }

        // Plugin after event
        J2CommerceHelper::plugin()->event('AfterShippingEstimate', [&$json]);

        $this->sendJsonResponse($json);
    }

    /**
     * Estimate shipping/tax based on location via AJAX
     *
     * Returns JSON response with success status and optional error messages.
     * On success, the calling JavaScript should refresh the cart totals.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function estimateAjax(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        $json = [];

        if (!$this->validateAjaxToken()) {
            $json['success'] = false;
            $json['message'] = Text::_('JINVALID_TOKEN');
            $this->sendJsonResponse($json);
        }

        $model   = $this->getCartModel();
        $session = $this->app->getSession();

        $countryId       = $this->input->getInt('country_id', 0);
        $zoneId          = $this->input->getInt('zone_id', 0);
        $postcode        = $this->input->getString('postcode', '');
        $city            = $this->input->getString('city', '');
        $countryRequired = $this->input->getInt('country_required', 1);
        $zoneRequired    = $this->input->getInt('zone_required', 1);
        $postalRequired  = $this->input->getInt('postal_required', 0);

        $params = J2CommerceHelper::config();

        if (!$countryId && $countryRequired) {
            $json['error']['country_id'] = Text::_('COM_J2COMMERCE_ESTIMATE_COUNTRY_REQUIRED');
        }

        if (!$zoneId && $zoneRequired) {
            $json['error']['zone_id'] = Text::_('COM_J2COMMERCE_ESTIMATE_ZONE_REQUIRED');
        }

        if (($postalRequired || $params->get('postalcode_required', 0)) && empty($postcode)) {
            $json['error']['postcode'] = Text::_('COM_J2COMMERCE_ESTIMATE_POSTALCODE_REQUIRED');
        }

        // Plugin validation event
        J2CommerceHelper::plugin()->event('BeforeShippingEstimate', [&$json]);

        if (empty($json['error'])) {
            if ($countryId || $zoneId) {
                if ($countryId) {
                    $session->set('billing_country_id', $countryId, 'j2commerce');
                    $session->set('shipping_country_id', $countryId, 'j2commerce');
                }

                if ($zoneId) {
                    $session->set('billing_zone_id', $zoneId, 'j2commerce');
                    $session->set('shipping_zone_id', $zoneId, 'j2commerce');
                }

                $session->set('force_calculate_shipping', 1, 'j2commerce');
            }

            if ($postcode) {
                $session->set('shipping_postcode', $postcode, 'j2commerce');
                $session->set('billing_postcode', $postcode, 'j2commerce');
            }

            if ($city) {
                $session->set('shipping_city', $city, 'j2commerce');
                $session->set('billing_city', $city, 'j2commerce');
            }

            /** @var \J2Commerce\Component\J2commerce\Site\Model\CartsModel $cartsModel */
            $cartsModel = $this->getModel('Carts');
            $items      = $cartsModel->getItems();

            if (!empty($items)) {
                $order = OrderHelper::getInstance()->populateOrder($items)->getOrder();

                $methods = J2CommerceHelper::plugin()->eventWithArray('GetShippingRates', [$order]);

                // Sort by price ascending so cheapest rates appear first
                usort($methods, fn (array $a, array $b) => ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0)));

                $session->set('shipping_methods', $methods, 'j2commerce');

                // Auto-select first method (cheapest) so totals include shipping immediately
                if (!empty($methods)) {
                    $first = $methods[0];
                    $session->set('shipping_values', [
                        'shipping_name'         => $first['name'],
                        'shipping_price'        => $first['price'],
                        'shipping_tax'          => $first['tax'],
                        'shipping_tax_class_id' => $first['tax_class_id'] ?? 0,
                        'shipping_extra'        => $first['extra'],
                        'shipping_code'         => $first['code'],
                        'shipping_plugin'       => $first['element'],
                    ], 'j2commerce');
                } else {
                    $session->clear('shipping_methods', 'j2commerce');
                    $session->clear('shipping_values', 'j2commerce');
                }

                $json['shipping_method_count'] = \count($methods);
            }

            $json['success'] = true;
            $json['message'] = Text::_('COM_J2COMMERCE_SHIPPING_ESTIMATE_UPDATED');
        } else {
            $json['success'] = false;
        }

        // Plugin after event
        J2CommerceHelper::plugin()->event('AfterShippingEstimate', [&$json]);

        $this->sendJsonResponse($json);
    }

    /**
     * Update shipping method selection
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function shippingUpdate(): void
    {
        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();
        $this->startAjaxBuffer();

        $json    = [];
        $model   = $this->getCartModel();
        $session = $this->app->getSession();

        $shippingValues = [
            'shipping_price'        => $this->input->getFloat('shipping_price', 0),
            'shipping_extra'        => $this->input->getFloat('shipping_extra', 0),
            'shipping_code'         => $this->input->getString('shipping_code', ''),
            'shipping_name'         => $this->input->getString('shipping_name', ''),
            'shipping_tax'          => $this->input->getFloat('shipping_tax', 0),
            'shipping_tax_class_id' => $this->input->getInt('shipping_tax_class_id', 0),
            'shipping_plugin'       => $this->input->getString('shipping_plugin', ''),
        ];

        $session->set('shipping_values', $shippingValues, 'j2commerce');

        $redirect         = $model->getCartUrl();
        $json['redirect'] = $redirect;

        // Plugin event
        J2CommerceHelper::plugin()->event('AfterShippingUpdate', [&$json]);

        $this->sendJsonResponse($json);
    }

    /**
     * Get zones for a country (AJAX)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function getCountry(): void
    {
        $this->startAjaxBuffer();

        $session   = $this->app->getSession();
        $set       = $session->get('j2commerce_country_zone', [], 'j2commerce');
        $countryId = $this->input->getInt('country_id', 0);

        if (!isset($set[$countryId])) {
            // Load country via native MVC factory
            $countryModel = $this->app->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createModel('Country', 'Administrator', ['ignore_request' => true]);
            $countryInfo = $countryModel->getItem($countryId);

            $json = [];

            if ($countryInfo) {
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true);

                $query->select('a.*')
                    ->from($db->quoteName('#__j2commerce_zones', 'a'))
                    ->where($db->quoteName('a.enabled') . ' = 1')
                    ->where($db->quoteName('a.country_id') . ' = :countryId')
                    ->bind(':countryId', $countryId, ParameterType::INTEGER)
                    ->order($db->quoteName('a.zone_name') . ' ASC');

                $db->setQuery($query);

                try {
                    $zones = $db->loadObjectList();
                } catch (\Exception $e) {
                    $zones = [];
                }

                foreach ($zones as &$zone) {
                    $zone->zone_name = Text::_($zone->zone_name);
                }

                if (\is_array($zones)) {
                    $json = [
                        'country_id' => $countryInfo->j2commerce_country_id,
                        'name'       => $countryInfo->country_name,
                        'iso_code_2' => $countryInfo->country_isocode_2,
                        'iso_code_3' => $countryInfo->country_isocode_3,
                        'zone'       => $zones,
                    ];
                }
            }

            $set[$countryId] = $json;
            $session->set('j2commerce_country_zone', $set, 'j2commerce');
        }

        $this->sendJsonResponse($set[$countryId] ?? []);
    }

    /**
     * File upload handler
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function upload(): void
    {
        $this->startAjaxBuffer();

        $files = $this->input->files->get('file');
        $json  = [];

        if ($files) {
            $model = $this->getCartModel();
            $json  = $model->validate_files($files);
        }

        $this->sendJsonResponse($json);
    }

    /**
     * Add item to wishlist
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addtowishlist(): void
    {
        $this->startAjaxBuffer();

        $model = $this->getCartModel();
        $model->setCartType('wishlist');

        $result = $model->addCartItem();
        $json   = J2CommerceHelper::plugin()->eventWithArray('AfterAddingToWishlist', [$result]);

        $this->sendJsonResponse($json);
    }
}
