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

use Joomla\CMS\Language\Multilanguage;

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
                // We're in a categories menu - generate URL within this hierarchy
                // Use category view which routes through the categories menu
                $link = 'index.php?option=com_j2commerce&view=category&id=' . $catid;

                // Include the parent from menu for proper path building
                $menuParentId = isset($activeMenu->query['id']) ? (int) $activeMenu->query['id'] : 1;
                if ($menuParentId > 1) {
                    // The router needs this to know where to start the category path
                    $link .= '&catid=' . $menuParentId;
                }

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
     * 2. Categories menu (categories view with ancestor hierarchy)
     *
     * @param   int          $catid      The category ID (required)
     * @param   int|null     $parentId   The parent category ID (optional, looked up if not provided)
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

        // PRIORITY 2: No single category menu found - use category view with hierarchy
        $link = 'index.php?option=com_j2commerce&view=category&id=' . $catid;

        // Include parent category ID for proper hierarchy building
        // This allows RouterView's StandardRules to build the path correctly
        if ($parentId === null) {
            // Look up the parent category ID
            $parentId = self::getCategoryParentId($catid);
        }

        if ($parentId && $parentId > 1) {
            $link .= '&catid=' . $parentId;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }

    /**
     * Get the parent category ID for a category.
     *
     * @param   int  $catid  The category ID
     *
     * @return  int|null  The parent category ID or null
     *
     * @since   6.0.0
     */
    private static function getCategoryParentId(int $catid): ?int
    {
        static $cache = [];

        if (isset($cache[$catid])) {
            return $cache[$catid];
        }

        $db    = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select($db->quoteName('parent_id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :catid')
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->bind(':catid', $catid, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        $cache[$catid] = $result ? (int) $result : null;

        return $cache[$catid];
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
        $link = 'index.php?option=com_j2commerce&view=checkout';

        if ($step) {
            $link .= '&step=' . $step;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
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
