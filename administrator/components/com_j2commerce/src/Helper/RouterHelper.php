<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Menu\MenuItem;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Router helper class for J2Commerce.
 *
 * Provides static methods for SEF URL routing including menu item lookup,
 * product/category resolution, and language-aware URL handling.
 *
 * @since  6.0.0
 */
class RouterHelper
{
    /**
     * Cached database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Cached menu instance
     *
     * @var   AbstractMenu|null
     * @since 6.0.0
     */
    private static ?AbstractMenu $menu = null;

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Get the site menu instance
     *
     * @return  AbstractMenu
     *
     * @since   6.0.0
     */
    private static function getMenu(): AbstractMenu
    {
        if (self::$menu === null) {
            self::$menu = AbstractMenu::getInstance('site');
        }

        return self::$menu;
    }

    /**
     * Get the current application language tag
     *
     * @return  string  Language tag (e.g., 'en-GB')
     *
     * @since   6.0.0
     */
    private static function getLanguageTag(): string
    {
        $app = Factory::getApplication();

        if ($app instanceof SiteApplication) {
            return $app->getLanguage()->getTag();
        }

        return 'en-GB';
    }

    // =========================================================================
    // QUERY HELPER METHODS
    // =========================================================================

    /**
     * Get and remove a value from the query array.
     *
     * Used during URL building to consume query parameters.
     *
     * @param   array       $query    The query array (modified by reference).
     * @param   string      $key      The key to retrieve and remove.
     * @param   mixed|null  $default  Default value if key not found.
     *
     * @return  mixed  The value or default.
     *
     * @since   6.0.0
     */
    public static function getAndPop(array &$query, string $key, mixed $default = null): mixed
    {
        if (isset($query[$key])) {
            $value = $query[$key];
            unset($query[$key]);

            return $value;
        }

        return $default;
    }

    /**
     * Precondition URL segments for SEF routing.
     *
     * Converts colons to hyphens and handles array segments.
     *
     * @param   array  $segments  Array of URL segments.
     *
     * @return  array  Processed segments.
     *
     * @since   6.0.0
     */
    public static function preconditionSegments(array $segments): array
    {
        $newSegments = [];

        foreach ($segments as $segment) {
            if (\is_string($segment) && str_contains($segment, ':')) {
                $segment = str_replace(':', '-', $segment);
            }

            if (\is_array($segment)) {
                $newSegments[] = implode('-', $segment);
            } else {
                $newSegments[] = (string) $segment;
            }
        }

        return $newSegments;
    }

    // =========================================================================
    // MENU FINDING METHODS
    // =========================================================================

    /**
     * Find a menu item matching the specified query options and parameters.
     *
     * @param   array       $qoptions  Query parameters to match.
     * @param   array|null  $params    Menu parameters to match.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findMenu(array $qoptions = [], ?array $params = null): ?MenuItem
    {
        $menu       = self::getMenu();
        $activeMenu = $menu->getActive();

        // First check the active menu item (fastest shortcut)
        if ($activeMenu instanceof MenuItem) {
            if (self::checkMenu($activeMenu, $qoptions, $params)) {
                return $activeMenu;
            }
        }

        // Search through all menu items
        foreach ($menu->getMenu() as $item) {
            if (self::checkMenu($item, $qoptions, $params)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Check if a menu item matches the specified query options and parameters.
     *
     * @param   MenuItem    $menuItem  The menu item to check.
     * @param   array       $qoptions  Query parameters to match.
     * @param   array|null  $params    Menu parameters to match.
     *
     * @return  bool  True if menu item matches.
     *
     * @since   6.0.0
     */
    public static function checkMenu(MenuItem $menuItem, array $qoptions, ?array $params = null): bool
    {
        $query = $menuItem->query ?? [];

        // Check all query options
        foreach ($qoptions as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (!isset($query[$key]) || $query[$key] !== $value) {
                return false;
            }
        }

        // Check menu parameters if specified
        if ($params !== null) {
            $menuParams = $menuItem->getParams();

            if (!$menuParams instanceof Registry) {
                $menuParams = new Registry($menuParams);
            }

            foreach ($params as $key => $value) {
                if ($value === null) {
                    continue;
                }

                if ($menuParams->get($key) !== $value) {
                    return false;
                }
            }
        }

        // Check language compatibility
        return self::checkMenuLanguage($menuItem, $qoptions);
    }

    /**
     * Check if a menu item's language matches the requested language.
     *
     * @param   MenuItem  $menuItem  The menu item.
     * @param   array     $qoptions  Query options (may contain 'lang').
     *
     * @return  bool  True if language matches.
     *
     * @since   6.0.0
     */
    private static function checkMenuLanguage(MenuItem $menuItem, array $qoptions): bool
    {
        $requestedLang = $qoptions['lang'] ?? self::getLanguageTag();
        $menuLang      = $menuItem->language ?? '*';

        // Menu language matches requested language
        if ($requestedLang === $menuLang) {
            return true;
        }

        // Menu is available for all languages
        if ($menuLang === '*') {
            return true;
        }

        return false;
    }

    // =========================================================================
    // ORDERS MENU METHODS
    // =========================================================================

    /**
     * Find a menu item for the orders view.
     *
     * @param   array  $qoptions  Query options (may contain 'lang').
     *
     * @return  int|null  Menu item ID or null if not found.
     *
     * @since   6.0.0
     */
    public static function findMenuOrders(array $qoptions): ?int
    {
        $menu   = self::getMenu();
        $menuId = null;

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && ($query['view'] ?? '') === 'orders'
            ) {
                if (self::checkMenuLanguage($item, $qoptions)) {
                    $menuId = (int) $item->id;
                }
            }
        }

        return $menuId;
    }

    /**
     * Find a menu item for the my profile view.
     *
     * @param   array  $qoptions  Query options (may contain 'lang').
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findMenuMyprofile(array $qoptions): ?MenuItem
    {
        $menu = self::getMenu();
        $user = Factory::getApplication()->getIdentity();

        if (!$user) {
            return null;
        }

        $userAccessLevels = $user->getAuthorisedViewLevels();

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && ($query['view'] ?? '') === 'myprofile'
                && \in_array($item->access, $userAccessLevels, true)
            ) {
                if (self::checkMenuLanguage($item, $qoptions)) {
                    return $item;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // CART MENU METHODS
    // =========================================================================

    /**
     * Find a menu item for the carts view.
     *
     * @param   array  $qoptions  Query options.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findMenuCarts(array $qoptions): ?MenuItem
    {
        $menu = self::getMenu();

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && ($query['view'] ?? '') === 'carts'
            ) {
                // Remove task from comparison
                $checkOptions = $qoptions;
                unset($checkOptions['task']);

                if (self::checkCartMenuLanguage($item, $checkOptions)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Check if a cart menu item's language matches.
     *
     * @param   MenuItem  $menuItem  The menu item.
     * @param   array     $qoptions  Query options (may contain 'lang').
     *
     * @return  bool  True if language matches.
     *
     * @since   6.0.0
     */
    private static function checkCartMenuLanguage(MenuItem $menuItem, array $qoptions): bool
    {
        $currentLang = self::getLanguageTag();
        $menuLang    = $menuItem->language ?? '*';

        // Explicit language in options
        if (isset($qoptions['lang']) && $qoptions['lang'] === $menuLang) {
            return true;
        }

        // Current application language matches
        if ($currentLang === $menuLang) {
            return true;
        }

        // Menu available for all languages
        if ($menuLang === '*') {
            return true;
        }

        return false;
    }

    // =========================================================================
    // CHECKOUT MENU METHODS
    // =========================================================================

    /**
     * Find a menu item for the checkout view.
     *
     * @param   array  $qoptions  Query options.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findCheckoutMenu(array $qoptions): ?MenuItem
    {
        $menu = self::getMenu();

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && \in_array($query['view'] ?? '', ['checkouts', 'checkout'], true)
                && (!isset($query['layout']) || $query['layout'] !== 'postpayment')
            ) {
                $checkOptions = $qoptions;
                unset($checkOptions['task']);

                if (self::checkCartMenuLanguage($item, $checkOptions)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Find a menu item for the thank you/post-payment page.
     *
     * @param   array  $qoptions  Query options.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findThankyouPageMenu(array $qoptions): ?MenuItem
    {
        $menu = self::getMenu();

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && \in_array($query['view'] ?? '', ['checkouts', 'checkout'], true)
                && ($query['layout'] ?? '') === 'postpayment'
                && ($query['task'] ?? '') === 'confirmPayment'
            ) {
                $checkOptions = $qoptions;
                unset($checkOptions['task']);

                if (self::checkMenuLanguage($item, $checkOptions)) {
                    return $item;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // PRODUCT MENU METHODS
    // =========================================================================

    /**
     * Find a menu item for the products view.
     *
     * @param   array  $qoptions  Query options with optional 'Itemid', 'id', 'view', 'task'.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findProductMenu(array $qoptions): ?MenuItem
    {
        $menu         = self::getMenu();
        $allMenuItems = $menu->getMenu();
        $foundMenu    = null;
        $otherTasks   = ['compare', 'wishlist'];

        // Check if specific Itemid was provided
        if (!empty($qoptions['Itemid'])) {
            $itemId = (int) $qoptions['Itemid'];

            if (isset($allMenuItems[$itemId])) {
                $selectedMenu = $allMenuItems[$itemId];

                if (self::checkMenuProducts($selectedMenu, $qoptions)) {
                    $foundMenu = $selectedMenu;
                }
            }
        }

        // Search all menus if not found
        if ($foundMenu === null) {
            foreach ($allMenuItems as $item) {
                $query = $item->query ?? [];

                if (($query['option'] ?? '') !== 'com_j2commerce'
                    || ($query['view'] ?? '') !== 'products'
                ) {
                    continue;
                }

                // Check for special task menus (compare, wishlist)
                if (!empty($query['task'])
                    && \in_array($query['task'], $otherTasks, true)
                    && ($query['task'] === ($qoptions['task'] ?? ''))
                ) {
                    $foundMenu = $item;
                    break;
                }

                if (self::checkMenuProducts($item, $qoptions)) {
                    $foundMenu = $item;
                    break;
                }
            }
        }

        // Allow plugins to modify the found menu
        // Note: Event dispatching would be done via Joomla's event system in a full implementation

        return $foundMenu;
    }

    /**
     * Check if a products menu item matches the product's category.
     *
     * @param   MenuItem  $menuItem  The menu item.
     * @param   array     $qoptions  Query options with 'id' and 'view'.
     *
     * @return  bool  True if menu item matches.
     *
     * @since   6.0.0
     */
    public static function checkMenuProducts(MenuItem $menuItem, array $qoptions): bool
    {
        $currentLang = self::getLanguageTag();

        // Check if we have a product ID and products view
        if (!isset($qoptions['id']) || ($qoptions['view'] ?? '') !== 'products') {
            return false;
        }

        $requestedLang = $qoptions['lang'] ?? $currentLang;
        $categoryId    = self::getProductCategory((int) $qoptions['id'], $requestedLang);

        // Get categories from menu query
        $menuQuery      = $menuItem->query ?? [];
        $menuCategories = $menuQuery['catid'] ?? [];

        if (!\is_array($menuCategories)) {
            $menuCategories = [];
        }

        // Check if product's category matches menu categories
        if (\in_array($categoryId, $menuCategories, true)) {
            return self::checkCartMenuLanguage($menuItem, $qoptions);
        }

        return false;
    }

    // =========================================================================
    // PRODUCT TAG MENU METHODS
    // =========================================================================

    /**
     * Find a menu item for the product tags view.
     *
     * @param   array  $qoptions  Query options with 'id' and 'view'.
     *
     * @return  MenuItem|null  Menu item or null if not found.
     *
     * @since   6.0.0
     */
    public static function findProductTagsMenu(array $qoptions): ?MenuItem
    {
        $menu = self::getMenu();

        foreach ($menu->getMenu() as $item) {
            $query = $item->query ?? [];

            if (($query['option'] ?? '') === 'com_j2commerce'
                && ($query['view'] ?? '') === 'producttags'
            ) {
                if (self::checkMenuProductTags($item, $qoptions)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Check if a product tags menu item matches.
     *
     * @param   MenuItem  $menuItem  The menu item.
     * @param   array     $qoptions  Query options with 'id' and 'view'.
     *
     * @return  bool  True if menu item matches.
     *
     * @since   6.0.0
     */
    public static function checkMenuProductTags(MenuItem $menuItem, array $qoptions): bool
    {
        $currentLang = self::getLanguageTag();

        if (($qoptions['view'] ?? '') !== 'producttags' || !isset($qoptions['id'])) {
            return false;
        }

        $requestedLang = $qoptions['lang'] ?? $currentLang;
        $productTags   = self::getProductTags((int) $qoptions['id'], $requestedLang);

        $menuQuery = $menuItem->query ?? [];
        $menuTag   = $menuQuery['tag'] ?? '';

        if (\in_array($menuTag, $productTags, true)) {
            $menuLang = $menuItem->language ?? '*';

            if ($requestedLang === $menuLang || $menuLang === '*') {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // PRODUCT DATA METHODS
    // =========================================================================

    /**
     * Get a product from the database.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  object|null  Product object or null if not found.
     *
     * @since   6.0.0
     */
    private static function getProduct(int $productId): ?object
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $product = $db->loadObject();

        $cache[$productId] = $product ?: null;

        return $cache[$productId];
    }

    /**
     * Get the category ID for a product.
     *
     * For com_content source products, returns the article's category.
     *
     * @param   int     $productId  The product ID.
     * @param   string  $lang       Language tag for multilingual support.
     *
     * @return  int|string  Category ID or empty string if not found.
     *
     * @since   6.0.0
     */
    public static function getProductCategory(int $productId, string $lang = ''): int|string
    {
        $product = self::getProduct($productId);

        if (!$product || ($product->product_source ?? '') !== 'com_content') {
            return '';
        }

        $articleId = (int) ($product->product_source_id ?? 0);

        // Handle multilingual association
        if (!empty($lang) && Multilanguage::isEnabled()) {
            $associatedId = self::getAssociatedArticle($articleId, $lang);

            if ($associatedId > 0) {
                $articleId = $associatedId;
            }
        }

        $article = self::getArticle($articleId);

        return $article ? (int) $article->catid : '';
    }

    /**
     * Get tags for a product.
     *
     * @param   int     $productId  The product ID.
     * @param   string  $lang       Language tag for multilingual support.
     *
     * @return  array  Array of tag aliases.
     *
     * @since   6.0.0
     */
    public static function getProductTags(int $productId, string $lang = ''): array
    {
        $product = self::getProduct($productId);

        if (!$product || ($product->product_source ?? '') !== 'com_content') {
            return [];
        }

        $articleId = (int) ($product->product_source_id ?? 0);

        // Handle multilingual association
        if (!empty($lang) && Multilanguage::isEnabled()) {
            $associatedId = self::getAssociatedArticle($articleId, $lang);

            if ($associatedId > 0) {
                $articleId = $associatedId;
            }
        }

        return self::getArticleTags($articleId);
    }

    /**
     * Get tags for an article.
     *
     * @param   int  $articleId  The article ID.
     *
     * @return  array  Array of tag aliases.
     *
     * @since   6.0.0
     */
    public static function getArticleTags(int $articleId): array
    {
        if ($articleId < 1) {
            return [];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('t.alias'))
            ->from($db->quoteName('#__contentitem_tag_map', 'ctm'))
            ->join(
                'LEFT',
                $db->quoteName('#__tags', 't') . ' ON ' .
                $db->quoteName('t.id') . ' = ' . $db->quoteName('ctm.tag_id')
            )
            ->where($db->quoteName('ctm.content_item_id') . ' = :articleId')
            ->where($db->quoteName('ctm.type_alias') . ' = ' . $db->quote('com_content.article'))
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];

        return array_filter($rows);
    }

    /**
     * Get tag alias for a product by item ID.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  string  Tag alias or empty string.
     *
     * @since   6.0.0
     */
    public static function getTagAliasByItem(int $productId): string
    {
        $product = self::getProduct($productId);

        if (!$product || ($product->product_source ?? '') !== 'com_content') {
            return '';
        }

        $db        = self::getDatabase();
        $articleId = (int) ($product->product_source_id ?? 0);

        $query = $db->getQuery(true)
            ->select($db->quoteName('t.alias'))
            ->from($db->quoteName('#__contentitem_tag_map', 'ctm'))
            ->join(
                'LEFT',
                $db->quoteName('#__tags', 't') . ' ON ' .
                $db->quoteName('ctm.tag_id') . ' = ' . $db->quoteName('t.id')
            )
            ->where($db->quoteName('ctm.content_item_id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    // =========================================================================
    // ARTICLE HELPER METHODS
    // =========================================================================

    /**
     * Get an article from the content table.
     *
     * @param   int  $articleId  The article ID.
     *
     * @return  object|null  Article object or null if not found.
     *
     * @since   6.0.0
     */
    private static function getArticle(int $articleId): ?object
    {
        static $cache = [];

        if ($articleId < 1) {
            return null;
        }

        if (isset($cache[$articleId])) {
            return $cache[$articleId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $article = $db->loadObject();

        $cache[$articleId] = $article ?: null;

        return $cache[$articleId];
    }

    /**
     * Get an article by alias.
     *
     * @param   string  $alias       The article alias.
     * @param   array   $categories  Optional category IDs to restrict search.
     *
     * @return  object|null  Article object or null if not found.
     *
     * @since   6.0.0
     */
    public static function getArticleByAlias(string $alias, array $categories = []): ?object
    {
        // Handle legacy URL format (id:alias)
        if (str_contains($alias, ':')) {
            $parts = explode(':', $alias);

            if (isset($parts[0]) && is_numeric($parts[0])) {
                // Legacy URL - extract product ID from first part
                if (!empty($parts[1])) {
                    $alias = $parts[1];
                } else {
                    // Return product ID for lookup
                    return (object) ['id' => (int) $parts[0]];
                }
            }
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $alias);

        // Restrict to specific categories if provided
        if (!empty($categories)) {
            $categories = array_map('intval', $categories);
            $query->whereIn($db->quoteName('catid'), $categories);
        }

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    /**
     * Get an associated article ID for a different language.
     *
     * @param   int     $articleId  The source article ID.
     * @param   string  $lang       Target language tag (e.g., 'fr-FR').
     *
     * @return  int  Associated article ID or 0 if not found.
     *
     * @since   6.0.0
     */
    private static function getAssociatedArticle(int $articleId, string $lang): int
    {
        if (!Multilanguage::isEnabled() || $articleId < 1 || empty($lang)) {
            return 0;
        }

        $db = self::getDatabase();

        // Get the association key for this article
        $query = $db->getQuery(true)
            ->select($db->quoteName('key'))
            ->from($db->quoteName('#__associations'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.item'))
            ->where($db->quoteName('id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $db->setQuery($query);
        $key = $db->loadResult();

        if (empty($key)) {
            return 0;
        }

        // Get the associated article ID for the target language
        $query = $db->getQuery(true)
            ->select($db->quoteName('a.id'))
            ->from($db->quoteName('#__associations', 'assoc'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'a') . ' ON ' .
                $db->quoteName('a.id') . ' = ' . $db->quoteName('assoc.id')
            )
            ->where($db->quoteName('assoc.context') . ' = ' . $db->quote('com_content.item'))
            ->where($db->quoteName('assoc.key') . ' = :key')
            ->where($db->quoteName('a.language') . ' = :lang')
            ->bind(':key', $key)
            ->bind(':lang', $lang);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get the alias for a product's source article.
     *
     * Handles multilingual and Falang translations.
     *
     * @param   int     $productId  The product ID.
     * @param   string  $lang       Language tag.
     *
     * @return  string  Article alias or empty string.
     *
     * @since   6.0.0
     */
    public static function getItemAlias(int $productId, string $lang = ''): string
    {
        $product = self::getProduct($productId);

        if (!$product || ($product->product_source ?? '') !== 'com_content') {
            return '';
        }

        $articleId = (int) ($product->product_source_id ?? 0);
        $article   = self::getArticle($articleId);

        if (!$article) {
            return '';
        }

        $contentAlias = $article->alias ?? '';

        // Handle multilingual
        if (!empty($lang)) {
            if (Multilanguage::isEnabled()) {
                $associatedId = self::getAssociatedArticle($articleId, $lang);

                if ($associatedId > 0) {
                    $associatedArticle = self::getArticle($associatedId);

                    if ($associatedArticle) {
                        $contentAlias = $associatedArticle->alias ?? $contentAlias;
                    }
                }
            }

            // Check for Falang support (third-party multilingual extension)
            // Note: Falang support would require additional implementation
        }

        return $contentAlias;
    }

    /**
     * Get article by alias and return the product ID.
     *
     * @param   string  $segment     URL segment (may be 'id:alias' or just 'alias').
     * @param   array   $categories  Optional category IDs to restrict search.
     *
     * @return  int|false  Product ID or false if not found.
     *
     * @since   6.0.0
     */
    public static function getProductIdByArticleAlias(string $segment, array $categories = []): int|false
    {
        // Handle legacy URL format
        $parts = explode(':', $segment);

        if (isset($parts[0]) && is_numeric($parts[0])) {
            // Legacy URL - we already have the product ID
            if (!empty($parts[1])) {
                $segment = $parts[1];
            } else {
                return (int) $parts[0];
            }
        }

        // Find article by alias
        $article = self::getArticleByAlias($segment, $categories);

        if (!$article || !isset($article->id)) {
            return false;
        }

        // Find product by article source
        return self::getProductIdBySource('com_content', (int) $article->id);
    }

    /**
     * Get product ID by source type and source ID.
     *
     * @param   string  $source    The source type (e.g., 'com_content').
     * @param   int     $sourceId  The source ID (e.g., article ID).
     *
     * @return  int|false  Product ID or false if not found.
     *
     * @since   6.0.0
     */
    private static function getProductIdBySource(string $source, int $sourceId): int|false
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = :source')
            ->where($db->quoteName('product_source_id') . ' = :sourceId')
            ->bind(':source', $source)
            ->bind(':sourceId', $sourceId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result ? (int) $result : false;
    }

    // =========================================================================
    // LANGUAGE AND TAG UTILITY METHODS
    // =========================================================================

    /**
     * Get language ID by language code.
     *
     * @param   string  $langCode  Language code (e.g., 'en-GB').
     *
     * @return  int  Language ID or 0 if not found.
     *
     * @since   6.0.0
     */
    public static function getLanguageId(string $langCode): int
    {
        if (empty($langCode)) {
            return 0;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('lang_id'))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_code') . ' = :langCode')
            ->bind(':langCode', $langCode);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Get filter tag alias by tag ID.
     *
     * @param   int  $tagId  The tag ID.
     *
     * @return  string  Tag alias or empty string.
     *
     * @since   6.0.0
     */
    public static function getFilterTagAlias(int $tagId): string
    {
        if ($tagId < 1) {
            return '';
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('alias'))
            ->from($db->quoteName('#__tags'))
            ->where($db->quoteName('id') . ' = :tagId')
            ->bind(':tagId', $tagId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    /**
     * Get tag ID by alias.
     *
     * @param   string  $alias  The tag alias.
     *
     * @return  int  Tag ID or 0 if not found.
     *
     * @since   6.0.0
     */
    public static function getTagByAlias(string $alias): int
    {
        if (empty($alias)) {
            return 0;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__tags'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $alias);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
