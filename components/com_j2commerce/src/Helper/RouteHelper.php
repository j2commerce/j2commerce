<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;

/**
 * J2Commerce Component Route Helper.
 *
 * Provides static methods for generating component URLs.
 * These methods return raw URLs that should be passed through Route::_()
 * for proper SEF URL generation.
 *
 * Usage:
 * ```php
 * use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
 * use Joomla\CMS\Router\Route;
 *
 * $url = Route::_(RouteHelper::getProductRoute(1, 'my-product'));
 * $cartUrl = Route::_(RouteHelper::getCartRoute());
 * ```
 *
 * @since  6.0.0
 */
abstract class RouteHelper
{
    /** The provider chosen for this request; null when none answered. Valid only once $checkoutProviderResolved is set. */
    private static ?array $checkoutProvider = null;

    private static bool $checkoutProviderResolved = false;

    /**
     * Get the product route.
     *
     * @param   int          $id        The product ID (j2commerce_product_id)
     * @param   string|null  $alias     The product alias (optional, for SEO)
     * @param   int|null     $catid     The category ID (required for canonical URLs with category path)
     * @param   string|null  $language  The language code
     * @param   string|null  $layout    The layout value
     *
     * @return  string  The product route URL
     *
     * @since   6.0.0
     */
    public static function getProductRoute(int $id, ?string $alias = null, ?int $catid = null, ?string $language = null, ?string $layout = null): string
    {
        // Build ID with alias if provided (format: "1:my-product-alias")
        $idPart = $alias ? $id . ':' . $alias : $id;

        $link = 'index.php?option=com_j2commerce&view=product&id=' . $idPart;

        // Category ID is essential for canonical URLs with category path
        if ($catid) {
            $link .= '&catid=' . $catid;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        return $link;
    }

    /**
     * Get the products list route.
     *
     * @param   int|null     $catid     The category ID (optional)
     * @param   string|null  $language  The language code
     * @param   string|null  $layout    The layout value
     *
     * @return  string  The products list route URL
     *
     * @since   6.0.0
     */
    public static function getProductsRoute(?int $catid = null, ?string $language = null, ?string $layout = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=products';

        if ($catid) {
            $link .= '&catid=' . $catid;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        return $link;
    }

    /**
     * Get the categories list route.
     *
     * @param   int|null     $parentId  The parent category ID (optional, 0 or 1 for root)
     * @param   string|null  $language  The language code
     *
     * @return  string  The categories list route URL
     *
     * @since   6.0.0
     */
    public static function getCategoriesRoute(?int $parentId = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=categories';

        if ($parentId && $parentId > 1) {
            $link .= '&id=' . $parentId;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the category route within a menu context.
     *
     * This method generates category URLs that stay within the current menu hierarchy.
     * When browsing from a "categories" menu, links should stay in that menu context
     * rather than jumping to a different "products" menu that might match the category.
     *
     * @param   int          $catid       The category ID (required)
     * @param   object|null  $activeMenu  The active menu item (for context)
     * @param   string|null  $language    The language code
     *
     * @return  string  The category route URL that respects menu context
     *
     * @since   6.0.0
     */
    public static function getCategoryRouteInContext(int $catid, ?object $activeMenu = null, ?string $language = null): string
    {
        // If no menu provided, get the active menu
        if ($activeMenu === null) {
            $activeMenu = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
        }

        // Check if we're in a "categories" menu context
        if ($activeMenu && $activeMenu->component === 'com_j2commerce') {
            $menuView = $activeMenu->query['view'] ?? null;

            if ($menuView === 'categories') {
                // We're in a categories menu - stay within this hierarchy via products view
                $link = 'index.php?option=com_j2commerce&view=products&catid=' . $catid;

                if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
                    $link .= '&lang=' . $language;
                }

                return $link;
            }
        }

        // Fallback to getCategoryRoute which searches ALL menu items for the best match
        return self::getCategoryRoute($catid, null, $language);
    }

    /**
     * Get the category route (products in a category).
     *
     * This is different from getCategoriesRoute which shows a list of categories.
     * This shows products within a specific category.
     *
     * Priority for category URLs:
     * 1. Single category menu (products view with catid matching this category)
     * 2. Products view for this category (the nestable products route builds the path)
     *
     * @param   int          $catid      The category ID (required)
     * @param   int|null     $parentId   Ignored — retained for backward compatibility; the
     *                                    nestable products view now derives the path itself
     * @param   string|null  $language   The language code
     *
     * @return  string  The category route URL
     *
     * @since   6.0.0
     */
    public static function getCategoryRoute(int $catid, ?int $parentId = null, ?string $language = null): string
    {
        // PRIORITY 1: Check for a single category menu item (products view with this catid)
        $menu  = \Joomla\CMS\Factory::getApplication()->getMenu();
        $menus = $menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menuItem) {
            // Check for products view with matching catid
            if (isset($menuItem->query['view']) && $menuItem->query['view'] === 'products') {
                $menuCatid = null;

                // Check query array first
                if (isset($menuItem->query['catid'])) {
                    $menuCatid = (int) $menuItem->query['catid'];
                } elseif (!empty($menuItem->link)) {
                    // Fallback: parse from link
                    parse_str(parse_url($menuItem->link, PHP_URL_QUERY) ?: '', $linkQuery);
                    if (isset($linkQuery['catid'])) {
                        $menuCatid = (int) $linkQuery['catid'];
                    }
                }

                // If this menu matches our category, use it directly
                if ($menuCatid === $catid) {
                    $link = 'index.php?option=com_j2commerce&view=products&catid=' . $catid;

                    if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
                        $link .= '&lang=' . $language;
                    }

                    return $link;
                }
            }
        }

        // PRIORITY 2: no dedicated menu — the nestable products view builds the path.
        $link = 'index.php?option=com_j2commerce&view=products&catid=' . $catid;

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the product tags route.
     *
     * @param   int|null     $tagId     The tag ID (optional)
     * @param   string|null  $language  The language code
     *
     * @return  string  The product tags route URL
     *
     * @since   6.0.0
     */
    public static function getProductTagsRoute(?int $tagId = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=producttags';

        if ($tagId) {
            $link .= '&id=' . $tagId;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /** The Product Tags View landing for a parent tag; null means the menu item's own parent. */
    public static function getTagsRoute(?int $parentId = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=tags';

        if ($parentId) {
            $link .= '&id=' . $parentId;
        }

        return self::appendLanguage($link, $language);
    }

    /**
     * One tag's product listing. The router places it under the Product Tags View menu item
     * that roots the tag, or under a Product Tag List View item for that tag when none does.
     */
    public static function getTagRoute(int $tagId, ?string $language = null): string
    {
        return self::appendLanguage('index.php?option=com_j2commerce&view=producttags&id=' . $tagId, $language);
    }

    /** Like getTagRoute(), but stays inside the active Product Tags View menu item when it roots the tag. */
    public static function getTagRouteInContext(int $tagId, ?object $activeMenu = null, ?string $language = null): string
    {
        $activeMenu ??= Factory::getApplication()->getMenu()->getActive();
        $link         = self::getTagRoute($tagId, $language);

        if ($activeMenu && $activeMenu->component === 'com_j2commerce' && ($activeMenu->query['view'] ?? '') === 'tags') {
            $rootId = (int) ($activeMenu->query['id'] ?? 0) ?: TagTreeHelper::ROOT_ID;

            if (TagTreeHelper::isWithin($tagId, $rootId)) {
                $link .= '&Itemid=' . (int) $activeMenu->id;
            }
        }

        return $link;
    }

    private static function appendLanguage(string $link, ?string $language): string
    {
        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Point a listing's pagination links at that listing's own route.
     *
     * Joomla seeds pagination links from the request's router vars through a fixed
     * allow-list (Pagination::$paramsFromRequest). It carries `id`, `task` and `format`
     * but neither `catid` nor `tag_ids` — the only params this component's router builds
     * a listing URL from. So a category page's links collapse to the bare menu alias
     * carrying a stale `id`, and links rendered inside the `products.filter` AJAX request
     * inherit its task/format and address the JSON endpoint instead of a page.
     *
     * @param   array<string, mixed>  $routeParams  View-specific params, e.g. ['catid' => 5].
     *                                              A null value drops the param.
     */
    public static function applyListingPaginationRoute(
        Pagination $pagination,
        string $view,
        array $routeParams = []
    ): void {
        foreach (['id', 'task', 'format'] as $key) {
            $pagination->setAdditionalUrlParam($key, null);
        }

        $pagination->setAdditionalUrlParam('option', 'com_j2commerce');
        $pagination->setAdditionalUrlParam('view', $view);

        foreach ($routeParams as $key => $value) {
            $pagination->setAdditionalUrlParam($key, $value);
        }
    }

    /**
     * Get the cart route.
     *
     * @param   string|null  $task      The task to execute (e.g., 'addItem', 'update', 'remove')
     * @param   string|null  $language  The language code
     *
     * @return  string  The cart route URL
     *
     * @since   6.0.0
     */
    public static function getCartRoute(?string $task = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=carts';

        if ($task) {
            // Tasks should be prefixed with controller name
            $taskPart = strpos($task, '.') !== false ? $task : 'carts.' . $task;
            $link .= '&task=' . $taskPart;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the add to cart route for a specific product.
     *
     * @param   int  $productId  The product ID to add
     *
     * @return  string  The add to cart route URL
     *
     * @since   6.0.0
     */
    public static function getAddToCartRoute(int $productId): string
    {
        return 'index.php?option=com_j2commerce&view=carts&task=carts.addItem&product_id=' . $productId;
    }

    /**
     * Get the remove from cart route.
     *
     * @param   int  $cartItemId  The cart item ID to remove
     *
     * @return  string  The remove from cart route URL
     *
     * @since   6.0.0
     */
    public static function getRemoveFromCartRoute(int $cartItemId): string
    {
        return 'index.php?option=com_j2commerce&view=carts&task=carts.remove&cartitem_id=' . $cartItemId;
    }

    /**
     * Get the clear cart route.
     *
     * @return  string  The clear cart route URL
     *
     * @since   6.0.0
     */
    public static function getClearCartRoute(): string
    {
        return 'index.php?option=com_j2commerce&view=carts&task=carts.clearCart';
    }

    /**
     * Get the checkout route.
     *
     * @param   string|null  $step      The checkout step (optional)
     * @param   string|null  $language  The language code
     *
     * @return  string  The checkout route URL
     *
     * @since   6.0.0
     */
    public static function getCheckoutRoute(?string $step = null, ?string $language = null): string
    {
        $link = self::getActiveCheckoutLink() ?? 'index.php?option=com_j2commerce&view=checkout';

        if ($step) {
            $link .= '&step=' . $step;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Routed URL of the store's active checkout: the answering provider's menu item, or core's own
     * checkout when no provider answers.
     *
     * @param   array<string, scalar>  $query  Extra query parts (checkout_context, lang, ...).
     */
    public static function getActiveCheckoutUrl(array $query = []): string
    {
        $link = self::getActiveCheckoutLink($query);

        if ($link === null) {
            return J2CommerceHelper::platform()->getCheckoutUrl($query);
        }

        // Route::_() returns null on router error today and will throw from Joomla 7.
        try {
            return (string) (Route::_($link, false) ?? $link);
        } catch (\RuntimeException) {
            return $link;
        }
    }

    /**
     * Unrouted link to the active provider's menu item, or null when no provider answers
     * (the caller then builds core's own checkout link exactly as it always did).
     *
     * @param   array<string, scalar>  $query  Extra query parts appended after the menu item's own.
     */
    public static function getActiveCheckoutLink(array $query = []): ?string
    {
        $provider = self::getCheckoutProvider();

        if ($provider === null) {
            return null;
        }

        return $query === [] ? $provider['link'] : $provider['link'] . '&' . http_build_query($query);
    }

    /**
     * Dispatches onJ2CommerceGetCheckoutProvider once per request and picks the answer to use:
     * the one named by the checkout_provider option when it answered, otherwise the highest priority.
     *
     * A provider appends ['element' => string, 'menu_item_id' => int, 'priority' => int] to `result`, and
     * optionally 'supports_context' => true when its checkout can run an admin-order checkout_context
     * (otherwise the admin "Take Payment" link stays on core's checkout). An answer is ignored unless its
     * menu item resolves for this visitor (published, dates, access, language), belongs to com_j2commerce
     * and is view=checkout.
     *
     * @return  array{element: string, menu_item_id: int, priority: int, link: string, supports_context: bool}|null
     */
    public static function getCheckoutProvider(): ?array
    {
        if (self::$checkoutProviderResolved) {
            return self::$checkoutProvider;
        }

        // Set before the dispatch so a listener that builds a checkout URL cannot recurse into it.
        self::$checkoutProviderResolved = true;
        self::$checkoutProvider         = null;

        try {
            $answers = J2CommerceHelper::plugin()->eventWithArray('GetCheckoutProvider', ['result' => []]);
        } catch (\Throwable $e) {
            Log::add('checkout provider lookup failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            return null;
        }

        $candidates = [];

        foreach ($answers as $answer) {
            $element    = \is_array($answer) ? (string) ($answer['element'] ?? '') : '';
            $menuItemId = \is_array($answer) ? (int) ($answer['menu_item_id'] ?? 0) : 0;

            if ($element === '' || $menuItemId <= 0) {
                continue;
            }

            $link = self::providerMenuItemLink($menuItemId);

            if ($link === null) {
                continue;
            }

            $candidates[] = [
                'element'          => $element,
                'menu_item_id'     => $menuItemId,
                'priority'         => (int) ($answer['priority'] ?? 0),
                'link'             => $link,
                'supports_context' => !empty($answer['supports_context']),
            ];
        }

        if ($candidates === []) {
            return null;
        }

        $chosen = (string) J2CommerceHelper::config()->get('checkout_provider', '');
        $best   = null;

        foreach ($candidates as $candidate) {
            if ($chosen !== '' && $candidate['element'] === $chosen) {
                $best = $candidate;
                break;
            }

            if ($best === null || $candidate['priority'] > $best['priority']) {
                $best = $candidate;
            }
        }

        return self::$checkoutProvider = $best;
    }

    /** The menu item's own link plus its Itemid, or null unless this visitor can reach it as a com_j2commerce checkout item. */
    private static function providerMenuItemLink(int $menuItemId): ?string
    {
        try {
            $app  = Factory::getApplication();
            $item = $app->getMenu('site')->getItem($menuItemId);
        } catch (\Throwable) {
            return null;
        }

        if (!$item || $item->component !== 'com_j2commerce' || ($item->query['view'] ?? '') !== 'checkout') {
            return null;
        }

        if (!\in_array((int) $item->access, $app->getIdentity()->getAuthorisedViewLevels(), true)) {
            return null;
        }

        if (!\in_array((string) $item->language, ['*', '', $app->getLanguage()->getTag()], true)) {
            return null;
        }

        $link = (string) $item->link;

        if (!str_starts_with($link, 'index.php?')) {
            return null;
        }

        return str_contains($link, 'Itemid=') ? $link : $link . '&Itemid=' . $menuItemId;
    }

    /**
     * Get the thank you page route.
     *
     * @param   int|null     $orderId   The order ID
     * @param   string|null  $language  The language code
     *
     * @return  string  The thank you page route URL
     *
     * @since   6.0.0
     */
    public static function getThankYouRoute(?int $orderId = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=confirmation';

        if ($orderId) {
            $link .= '&order_id=' . $orderId;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the my profile route.
     *
     * @param   string|null  $layout    The layout (e.g., 'orders', 'addresses', 'edit')
     * @param   string|null  $language  The language code
     *
     * @return  string  The my profile route URL
     *
     * @since   6.0.0
     */
    public static function getMyProfileRoute(?string $layout = null, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=myprofile';

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the order details route (within my profile).
     *
     * @param   int          $orderId   The order ID
     * @param   string|null  $language  The language code
     *
     * @return  string  The order details route URL
     *
     * @since   6.0.0
     */
    public static function getOrderRoute(int $orderId, ?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . $orderId;

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the dashboard route.
     *
     * @param   string|null  $language  The language code
     *
     * @return  string  The dashboard route URL
     *
     * @since   6.0.0
     */
    public static function getDashboardRoute(?string $language = null): string
    {
        $link = 'index.php?option=com_j2commerce&view=dashboard';

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the continue shopping route.
     *
     * Returns the products list route as the default continue shopping destination.
     *
     * @param   string|null  $language  The language code
     *
     * @return  string  The continue shopping route URL
     *
     * @since   6.0.0
     */
    public static function getContinueShoppingRoute(?string $language = null): string
    {
        return self::getProductsRoute(null, $language);
    }
}
