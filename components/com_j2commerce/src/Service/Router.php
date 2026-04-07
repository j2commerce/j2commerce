<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Plugin\PluginHelper as JoomlaPluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;

/**
 * J2Commerce Router
 *
 * Generates canonical URLs based on product's actual Joomla category hierarchy.
 * Handles menu item priority matching in preprocess() and builds SEF segments.
 *
 * URL Priority (highest to lowest):
 * 1. Single product menu item - shortest URL
 * 2. Products menu (catid match) - category-specific listing
 * 3. Categories menu (ancestor) - full category path
 *
 * @since  6.0.0
 */
class Router extends RouterView
{
    /**
     * The database instance
     *
     * @var    DatabaseInterface
     * @since  6.0.0
     */
    private DatabaseInterface $db;

    /**
     * The category factory for building category paths
     *
     * @var    CategoryFactoryInterface|null
     * @since  6.0.0
     */
    private ?CategoryFactoryInterface $categoryFactory;

    /**
     * Category tree cache to avoid repeated lookups
     *
     * @var    array
     * @since  6.0.0
     */
    private array $categoryCache = [];

    /**
     * Remove IDs from URLs (like com_content's sef_ids)
     *
     * @var    bool
     * @since  6.0.0
     */
    protected bool $noIDs = false;

    /**
     * Constructor
     *
     * @param   SiteApplication                $app              The application object
     * @param   AbstractMenu                   $menu             The menu object
     * @param   CategoryFactoryInterface|null  $categoryFactory  The category factory for category paths
     * @param   DatabaseInterface              $db               The database object
     *
     * @since   6.0.0
     */
    public function __construct(
        SiteApplication $app,
        AbstractMenu $menu,
        ?CategoryFactoryInterface $categoryFactory,
        DatabaseInterface $db
    ) {
        $this->db              = $db;
        $this->categoryFactory = $categoryFactory;

        $this->noIDs = true;

        // Register view hierarchy for canonical URL generation
        // Following com_content pattern: categories -> category (nestable) -> product

        // Categories view - shows list of product categories (top-level for categories menu)
        $categories = new RouterViewConfiguration('categories');
        $categories->setKey('id');
        $this->registerView($categories);

        // Category view - nestable, parent is categories
        // Key is 'id' because getCategoryRoute uses id= not catid=
        // This enables: /categories/chocolate and /categories/chocolate/subcategory
        $category = new RouterViewConfiguration('category');
        $category->setKey('id')->setParent($categories, 'catid')->setNestable();
        $this->registerView($category);

        // Single product view - parent is category, linked via 'catid'
        // This enables: /categories/chocolate/product-alias
        $product = new RouterViewConfiguration('product');
        $product->setKey('id')->setParent($category, 'catid');
        $this->registerView($product);

        // Products list view (alternative root for products menu)
        // Note: Products menu uses catid filter, not category hierarchy in URL
        $products = new RouterViewConfiguration('products');
        $this->registerView($products);

        // Other views without category hierarchy
        $this->registerView(new RouterViewConfiguration('producttags'));
        $this->registerView(new RouterViewConfiguration('dashboard'));
        $this->registerView(new RouterViewConfiguration('carts'));
        $this->registerView(new RouterViewConfiguration('checkout'));
        $this->registerView(new RouterViewConfiguration('myprofile'));
        $this->registerView(new RouterViewConfiguration('confirmation'));
        $this->registerView(new RouterViewConfiguration('categoryalias'));

        // Allow J2Commerce plugins to register additional frontend views
        // so app plugins can add SEF routes without modifying this core file.
        JoomlaPluginHelper::importPlugin('j2commerce');
        $app->getDispatcher()->dispatch(
            'onJ2CommerceRegisterRouterViews',
            new Event('onJ2CommerceRegisterRouterViews', ['router' => $this])
        );

        parent::__construct($app, $menu);

        // Attach rules in correct order:
        // 1. MenuRules - Selects the canonical menu item
        // 2. StandardRules - Builds URL segments from view hierarchy
        // 3. NomenuRules - Fallback when no menu item exists
        //
        // Note: We don't use PreprocessRules because J2Commerce products
        // require custom lookup (product -> content article -> category)
        // which we handle in getPath() and segment methods
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * Preprocess URL query parameters — sets correct Itemid BEFORE buildSefRoute()
     *
     * SiteRouter's buildSefRoute() gets the menu item BEFORE calling our build().
     * We MUST set the correct Itemid here based on menu priority.
     *
     * @param   array  $query  URL query parameters
     *
     * @return  array  Preprocessed query with correct Itemid
     *
     * @since   6.0.0
     */
    public function preprocess($query): array
    {
        // For product views, find the correct menu item based on priority
        if (isset($query['view']) && $query['view'] === 'product' && isset($query['id'])) {
            $productId = $query['id'];

            if (strpos((string) $productId, ':') !== false) {
                [$productId] = explode(':', $productId, 2);
            }
            $productId = (int) $productId;

            $menuItem = $this->findSingleProductMenu($productId);

            if (!$menuItem) {
                $catid = isset($query['catid']) ? (int) $query['catid'] : null;

                if (!$catid) {
                    $catid = $this->lookupProductCategory($productId);
                }

                if ($catid) {
                    $menuItem = $this->findProductsMenuByCatid($catid);

                    if (!$menuItem) {
                        $result   = $this->findCategoriesMenuForCategory($catid);
                        $menuItem = $result ? $result['menu'] : null;
                    }
                }
            }

            if ($menuItem) {
                $query['Itemid'] = $menuItem->id;
            }
        }

        // For products views with catid, find matching menu
        if (isset($query['view']) && $query['view'] === 'products' && isset($query['catid'])) {
            $catid    = (int) $query['catid'];
            $menuItem = $this->findProductsMenuByCatid($catid);

            if ($menuItem) {
                $query['Itemid'] = $menuItem->id;
            }
        }

        // For categories views with id, find matching menu
        if (isset($query['view']) && $query['view'] === 'categories') {
            $parentId = isset($query['id']) ? (int) $query['id'] : null;
            $menuItem = $this->findCategoriesMenu($parentId);

            if ($menuItem) {
                $query['Itemid'] = $menuItem->id;
            }
        }

        // For category view, find matching menu
        if (isset($query['view']) && $query['view'] === 'category' && isset($query['id'])) {
            $catid = (int) $query['id'];

            $result         = $this->findCategoriesMenuForCategory($catid);
            $categoriesMenu = $result ? $result['menu'] : null;

            if ($categoriesMenu) {
                $query['Itemid'] = $categoriesMenu->id;
            } else {
                $productsMenu = $this->findProductsMenuByCatid($catid);

                if ($productsMenu) {
                    $query['Itemid'] = $productsMenu->id;
                }
            }
        }

        // For categoryalias view, rewrite to category view with correct Itemid
        if (($query['view'] ?? '') === 'categoryalias' && !empty($query['id'])) {
            $catid          = (int) $query['id'];
            $result         = $this->findCategoriesMenuForCategory($catid);
            $categoriesMenu = $result ? $result['menu'] : null;

            if ($categoriesMenu) {
                $query['view']   = 'category';
                $query['Itemid'] = $categoriesMenu->id;
            } else {
                $query['view'] = 'category';
                $menuItem      = $this->findProductsMenuByCatid($catid);

                if ($menuItem) {
                    $query['Itemid'] = $menuItem->id;
                }
            }
        }

        // For simple view-only menu items (carts, checkout, myprofile, etc.)
        $simpleViews = ['carts', 'checkout', 'myprofile', 'confirmation', 'dashboard'];
        if (isset($query['view']) && \in_array($query['view'], $simpleViews, true) && !isset($query['Itemid'])) {
            $menuItem = $this->findViewMenu($query['view']);

            if ($menuItem) {
                $query['Itemid'] = $menuItem->id;
            }
        }

        // Store our Itemid before delegating to parent
        $ourItemid = $query['Itemid'] ?? null;

        $query = parent::preprocess($query);

        // Restore our Itemid if parent changed it
        if ($ourItemid !== null && isset($query['Itemid']) && $query['Itemid'] !== $ourItemid) {
            $query['Itemid'] = $ourItemid;
        }

        return $query;
    }

    /**
     * Build the route segments
     *
     * Overrides parent to handle special cases with correct priority:
     * 1. Single product menu - If exists, use it (shortest URL)
     * 2. Products view with catid - Find matching menu
     * 3. Product view - Find category-based menu and build path
     *
     * URL Priority (highest to lowest):
     * 1. Single product menu item - If a product has its own menu, ALWAYS use it
     * 2. Products menu (catid match) - Category-specific product listing menu
     * 3. Categories menu (ancestor) - Parent category menu with category path
     *
     * @param   array  $query  The query array
     *
     * @return  array  The URL segments
     *
     * @since   6.0.0
     */
    public function build(&$query): array
    {
        // CASE 0: Producttags view - absorb tag_ids and tag_match when they match the menu item
        if (isset($query['view']) && $query['view'] === 'producttags') {
            $menuItem = $this->findProductTagsMenu(
                isset($query['tag_ids']) ? (array) $query['tag_ids'] : [],
                $query['tag_match'] ?? 'any'
            );

            if ($menuItem) {
                $query['Itemid'] = $menuItem->id;
                unset($query['view'], $query['tag_ids'], $query['tag_match']);
                return [];
            }
        }

        // CASE 1: Products view with catid - find matching menu item
        if (isset($query['view']) && $query['view'] === 'products' && isset($query['catid'])) {
            $catid = (int) $query['catid'];

            // Try to find a menu item that matches this products view + catid
            $menuItem = $this->findProductsMenuByCatid($catid);

            if ($menuItem) {
                // Set the Itemid to the found menu
                $query['Itemid'] = $menuItem->id;

                // Remove view and catid from query as they're implicit in the menu item
                unset($query['view'], $query['catid']);

                // Return empty segments - the menu alias will be the URL
                return [];
            }
        }

        // CASE 2: Product view - find matching menu with priority
        if (isset($query['view']) && $query['view'] === 'product' && isset($query['id'])) {
            $productId    = $query['id'];
            $productAlias = null;
            $menuItem     = null;
            $categoryPath = [];

            // Extract numeric ID and alias if format is "id:alias"
            if (strpos((string) $productId, ':') !== false) {
                [$productId, $productAlias] = explode(':', $productId, 2);
            }
            $productId = (int) $productId;

            // PRIORITY 1: Check for single product menu FIRST
            $menuItem = $this->findSingleProductMenu($productId);

            if ($menuItem) {
                // Single product menu found - use it directly (shortest URL)
                $query['Itemid'] = $menuItem->id;

                // Remove all query params - menu item provides everything
                unset($query['view'], $query['id'], $query['catid']);

                // Return empty segments - just the menu alias will be the URL
                return [];
            }

            // PRIORITY 2 & 3: No single product menu - look for category-based menus
            // Get product's category if not provided
            $catid = isset($query['catid']) ? (int) $query['catid'] : null;

            if (!$catid) {
                $catid = $this->lookupProductCategory($productId);
            }

            if ($catid) {
                // PRIORITY 2: Try to find a products menu item that matches this category
                $menuItem = $this->findProductsMenuByCatid($catid);

                // PRIORITY 3: If no products menu, try to find a categories menu that contains this category
                if (!$menuItem) {
                    $categoriesMenu = $this->findCategoriesMenuForCategory($catid);
                    if ($categoriesMenu) {
                        $menuItem     = $categoriesMenu['menu'];
                        $categoryPath = $categoriesMenu['path'];
                    }
                }

                if ($menuItem) {
                    // Set Itemid to the category-matching menu
                    $query['Itemid'] = $menuItem->id;

                    // For categories menu, we need to manually build segments
                    // and remove all query params that are implicit in the menu + segments
                    if (!empty($categoryPath)) {
                        // Get product alias
                        $alias = $productAlias ?: $this->lookupProductAlias($productId);

                        if ($alias) {
                            // Remove all query parameters - everything is encoded in:
                            // 1. Menu item (Itemid) - provides view=categories and id (parent)
                            // 2. Segments - category path + product alias
                            unset($query['view'], $query['id'], $query['catid']);

                            // Return segments: category path + product alias
                            return array_merge($categoryPath, [$alias]);
                        }
                    } else {
                        // Products menu - get product alias and return as segment
                        $alias = $productAlias ?: $this->lookupProductAlias($productId);

                        if ($alias) {
                            // Remove all query parameters - everything is encoded in:
                            // 1. Menu item (Itemid) - provides view=products and catid
                            // 2. Segment - product alias
                            unset($query['view'], $query['id'], $query['catid']);

                            // Return just the product alias as segment
                            return [$alias];
                        }
                    }
                }
            }
        }

        // Let parent handle all cases through StandardRules
        // Parent will use the Itemid we set above for product views
        return parent::build($query);
    }

    /**
     * Lookup product's category ID from database
     *
     * @param   int  $productId  Product ID
     *
     * @return  int|null  Category ID or null
     *
     * @since   6.0.0
     */
    private function lookupProductCategory(int $productId): ?int
    {
        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('c.catid'))
            ->from($this->db->quoteName('#__content', 'c'))
            ->join(
                'INNER',
                $this->db->quoteName('#__j2commerce_products', 'p'),
                $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
            )
            ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('p.j2commerce_product_id') . ' = :id')
            ->bind(':id', $productId, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        $result = $this->db->loadResult();

        return $result ? (int) $result : null;
    }

    /**
     * Lookup product's alias from database
     *
     * @param   int  $productId  Product ID
     *
     * @return  string|null  Product alias or null
     *
     * @since   6.0.0
     */
    private function lookupProductAlias(int $productId): ?string
    {
        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('c.alias'))
            ->from($this->db->quoteName('#__content', 'c'))
            ->join(
                'INNER',
                $this->db->quoteName('#__j2commerce_products', 'p'),
                $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
            )
            ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('p.j2commerce_product_id') . ' = :id')
            ->bind(':id', $productId, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        return $this->db->loadResult() ?: null;
    }

    /**
     * Find single product menu item by product ID
     *
     * Searches for a menu item with view=product and id matching the product ID.
     * This is the highest priority menu type for product URLs.
     *
     * @param   int  $productId  Product ID
     *
     * @return  object|null  Menu item or null
     *
     * @since   6.0.0
     */
    private function findSingleProductMenu(int $productId): ?object
    {
        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            $menuProductId = null;

            // Check link first (most reliable source)
            if (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);

                if (isset($linkQuery['view']) && $linkQuery['view'] === 'product' && isset($linkQuery['id'])) {
                    $menuProductId = $linkQuery['id'];
                }
            }

            // Fallback: check query array if link didn't have view=product
            if ($menuProductId === null && isset($menu->query['view']) && $menu->query['view'] === 'product') {
                $menuProductId = $menu->query['id'] ?? null;
            }

            // If we found a product ID, check if it matches
            if ($menuProductId !== null) {
                // Extract numeric ID if format is "id:alias"
                if (strpos((string) $menuProductId, ':') !== false) {
                    [$menuProductId] = explode(':', $menuProductId, 2);
                }

                if ((int) $menuProductId === $productId) {
                    return $menu;
                }
            }
        }

        return null;
    }

    /**
     * Find a products menu item by category ID
     *
     * Searches all J2Commerce menu items for a products view that matches the given catid.
     * The catid can be in the query array (parsed) or needs to be extracted from the link.
     *
     * @param   int  $catid  Category ID
     *
     * @return  object|null  Menu item or null
     *
     * @since   6.0.0
     */
    private function findProductsMenuByCatid(int $catid): ?object
    {
        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            if (!isset($menu->query['view']) || $menu->query['view'] !== 'products') {
                continue;
            }

            // Check if this menu's catid matches - try query array first
            $menuCatid = null;

            if (isset($menu->query['catid'])) {
                // catid is in the parsed query array
                $menuCatid = (int) $menu->query['catid'];
            } elseif (!empty($menu->link)) {
                // Fallback: parse catid from the link URL
                // Link format: index.php?option=com_j2commerce&view=products&catid=9
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                if (isset($linkQuery['catid'])) {
                    $menuCatid = (int) $linkQuery['catid'];
                }
            }

            if ($menuCatid === $catid) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * Find a producttags menu item whose stored tag_ids and tag_match match the given values
     *
     * @param   array   $tagIds    Tag IDs from the current query
     * @param   string  $tagMatch  Match mode ('any' or 'all')
     *
     * @return  object|null  Menu item or null
     *
     * @since   6.0.0
     */
    private function findProductTagsMenu(array $tagIds, string $tagMatch): ?object
    {
        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            if (!isset($menu->query['view']) || $menu->query['view'] !== 'producttags') {
                continue;
            }

            // Get stored tag_ids and tag_match from menu query or link
            $menuTagIds   = [];
            $menuTagMatch = 'any';

            if (isset($menu->query['tag_ids'])) {
                $menuTagIds = (array) $menu->query['tag_ids'];
            } elseif (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                if (isset($linkQuery['tag_ids'])) {
                    $menuTagIds = (array) $linkQuery['tag_ids'];
                }
            }

            if (isset($menu->query['tag_match'])) {
                $menuTagMatch = $menu->query['tag_match'];
            } elseif (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                if (isset($linkQuery['tag_match'])) {
                    $menuTagMatch = $linkQuery['tag_match'];
                }
            }

            // Compare tag_ids (order-independent) and tag_match
            $sortedQuery = $tagIds;
            $sortedMenu  = $menuTagIds;
            sort($sortedQuery);
            sort($sortedMenu);

            if ($sortedQuery === $sortedMenu && $tagMatch === $menuTagMatch) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * Find a categories menu item that contains a given category
     *
     * Searches for a categories menu where the menu's parent category (id parameter)
     * is an ancestor of the target category. Returns both the menu item and the
     * path of category aliases needed to reach the target category from the menu.
     *
     * @param   int  $catid  Target category ID
     *
     * @return  array|null  ['menu' => menu object, 'path' => [alias1, alias2, ...]] or null
     *
     * @since   6.0.0
     */
    private function findCategoriesMenuForCategory(int $catid): ?array
    {
        // Get the target category's ancestors (path from root)
        $categoryPath = $this->getCategoryAncestorPath($catid);

        if (empty($categoryPath)) {
            return null;
        }

        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            if (!isset($menu->query['view']) || $menu->query['view'] !== 'categories') {
                continue;
            }

            // Get the menu's parent category ID (from id parameter)
            $menuParentId = null;

            if (isset($menu->query['id'])) {
                $menuParentId = (int) $menu->query['id'];
            } elseif (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                if (isset($linkQuery['id'])) {
                    $menuParentId = (int) $linkQuery['id'];
                }
            }

            // If no id or id=0, treat as root categories menu (parent_id = 1)
            if (!$menuParentId || $menuParentId <= 1) {
                $menuParentId = 1;
            }

            // Check if the menu's parent is in our category's path (or is root)
            // categoryPath includes the target category itself
            $ancestorIds  = array_column($categoryPath, 'id');
            $pathPosition = array_search($menuParentId, $ancestorIds);

            // Also check if menu is for root and category path starts at root
            if ($pathPosition === false && $menuParentId === 1 && !empty($categoryPath)) {
                $pathPosition = -1; // Start from the beginning
            }

            if ($pathPosition !== false) {
                // Menu's parent is an ancestor - build path from menu to target category
                $pathSegments = [];

                // Skip ancestors up to and including the menu's parent
                $startIndex = ($pathPosition === -1) ? 0 : $pathPosition + 1;

                for ($i = $startIndex; $i < \count($categoryPath); $i++) {
                    $pathSegments[] = $categoryPath[$i]['alias'];
                }

                return [
                    'menu' => $menu,
                    'path' => $pathSegments,
                ];
            }
        }

        return null;
    }

    /**
     * Get the ancestor path for a category (from root to parent)
     *
     * @param   int  $catid  Category ID
     *
     * @return  array  Array of ['id' => int, 'alias' => string] from root to parent
     *
     * @since   6.0.0
     */
    private function getCategoryAncestorPath(int $catid): array
    {
        $category = $this->getCategoryById($catid);

        if (!$category || empty($category['path'])) {
            return [];
        }

        // Path is like "shop/chocolate" - we need to get the IDs
        $pathAliases = explode('/', $category['path']);
        $ancestors   = [];

        // Walk through path and lookup each ID
        $currentParent = 1; // Start from root

        foreach ($pathAliases as $alias) {
            // Look up the category by alias and parent
            $dbquery = $this->db->getQuery(true);
            $dbquery->select([$this->db->quoteName('id'), $this->db->quoteName('alias')])
                ->from($this->db->quoteName('#__categories'))
                ->where($this->db->quoteName('alias') . ' = :alias')
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
                ->where($this->db->quoteName('parent_id') . ' = :parent')
                ->bind(':alias', $alias)
                ->bind(':parent', $currentParent, ParameterType::INTEGER);

            $this->db->setQuery($dbquery);
            $cat = $this->db->loadAssoc();

            if ($cat) {
                $ancestors[]   = $cat;
                $currentParent = (int) $cat['id'];
            }
        }

        return $ancestors;
    }

    /**
     * Get category data by ID
     *
     * @param   int  $catid  Category ID
     *
     * @return  array|null  ['id' => int, 'alias' => string, 'path' => string, 'parent_id' => int] or null
     *
     * @since   6.0.0
     */
    private function getCategoryById(int $catid): ?array
    {
        $dbquery = $this->db->getQuery(true);
        $dbquery->select([
                $this->db->quoteName('id'),
                $this->db->quoteName('alias'),
                $this->db->quoteName('path'),
                $this->db->quoteName('parent_id'),
            ])
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->bind(':id', $catid, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        return $this->db->loadAssoc();
    }

    private function getCategoryParams(int $catid): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('params'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $catid, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $json = $this->db->loadResult();

        return $json ? json_decode($json, true) : [];
    }

    /**
     * Get the category tree with caching
     *
     * @param   array  $options  Options for the category tree
     *
     * @return  CategoryInterface|null  The category tree or null
     *
     * @since   6.0.0
     */
    private function getCategories(array $options = []): ?CategoryInterface
    {
        if (!$this->categoryFactory) {
            return null;
        }

        $key = serialize($options);

        if (!isset($this->categoryCache[$key])) {
            $this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
        }

        return $this->categoryCache[$key];
    }

    /**
     * Get URL segments for a category (builds full category path)
     *
     * This is called by StandardRules when building category URLs.
     * Returns all segments from root to the category.
     *
     * @param   string  $id     Category ID (may include alias as "id:alias")
     * @param   array   $query  Current query
     *
     * @return  array  Segments as [id => alias, ...]
     *
     * @since   6.0.0
     */
    public function getCategorySegment($id, $query): array
    {
        $categories = $this->getCategories(['access' => true]);

        if (!$categories) {
            // Fallback: database lookup if no category factory
            return $this->getCategorySegmentFromDb($id);
        }

        // Extract numeric ID if format is "id:alias"
        if (strpos((string) $id, ':') !== false) {
            [$id] = explode(':', $id, 2);
        }

        $category = $categories->get((int) $id);

        if (!$category) {
            return [];
        }

        // Get the path from root to this category
        $path = array_reverse($category->getPath(), true);

        // Remove root category (ID 1) from path - it's not part of the URL
        unset($path[1]);

        // Build segments based on noIDs setting
        $segments = [];

        foreach ($path as $catId => $segment) {
            // Segment format is "id:alias" - extract the alias
            if (strpos($segment, ':') !== false) {
                [$segmentId, $alias] = explode(':', $segment, 2);

                if ($this->noIDs) {
                    // Clean URLs: category-alias
                    $segments[(int) $catId] = $alias;
                } else {
                    // With IDs: 9-category-alias
                    $segments[(int) $catId] = $segmentId . ':' . $alias;
                }
            } else {
                $segments[(int) $catId] = $segment;
            }
        }

        return $segments;
    }

    /**
     * Fallback: Get category segment from database
     *
     * @param   mixed  $id  Category ID
     *
     * @return  array  Segments
     *
     * @since   6.0.0
     */
    private function getCategorySegmentFromDb($id): array
    {
        // Extract numeric ID if format is "id:alias"
        if (strpos((string) $id, ':') !== false) {
            [$id] = explode(':', $id, 2);
        }

        $dbquery = $this->db->getQuery(true);
        $dbquery->select([$this->db->quoteName('id'), $this->db->quoteName('alias'), $this->db->quoteName('path')])
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        $category = $this->db->loadObject();

        if (!$category) {
            return [];
        }

        // Build path from category's path field (e.g., "uncategorised/electronics/phones")
        $segments = [];

        if (!empty($category->path)) {
            $pathParts = explode('/', $category->path);

            // Look up each path segment to get IDs
            foreach ($pathParts as $alias) {
                $lookupQuery = $this->db->getQuery(true);
                $lookupQuery->select($this->db->quoteName('id'))
                    ->from($this->db->quoteName('#__categories'))
                    ->where($this->db->quoteName('alias') . ' = :alias')
                    ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
                    ->bind(':alias', $alias);

                $this->db->setQuery($lookupQuery);
                $catId = (int) $this->db->loadResult();

                if ($catId && $catId !== 1) {
                    $segments[$catId] = $alias;
                }
            }
        }

        return $segments;
    }

    /**
     * Get category ID from URL segment (alias lookup)
     *
     * @param   string  $segment  URL segment (alias)
     * @param   array   $query    Current query
     *
     * @return  int  Category ID
     *
     * @since   6.0.0
     */
    public function getCategoryId($segment, $query): int
    {
        $categories = $this->getCategories(['access' => true]);

        // If we have a parent category from previous segment, search within it
        $parentId = isset($query['id']) ? (int) $query['id'] : 1;

        if ($categories) {
            $parent = $categories->get($parentId);

            if ($parent) {
                // Search for category with matching alias under parent
                foreach ($parent->getChildren() as $child) {
                    if ($child->alias === $segment) {
                        return (int) $child->id;
                    }
                }
            }
        }

        // Fallback: database lookup by alias
        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->bind(':alias', $segment);

        // If we have parent context, use it
        if ($parentId > 1) {
            $dbquery->where($this->db->quoteName('parent_id') . ' = :parent')
                ->bind(':parent', $parentId, ParameterType::INTEGER);
        }

        $this->db->setQuery($dbquery);
        $id = (int) $this->db->loadResult();

        return $id ?: (int) $segment;
    }

    /**
     * Get URL segments for categories view
     *
     * For root category (id <= 1), returns empty array (no segment needed).
     * For specific category, returns the category alias as segment.
     *
     * @param   string  $id     Category ID (may include alias as "id:alias")
     * @param   array   $query  Current query
     *
     * @return  array  Segments as [id => alias] or empty array
     *
     * @since   6.0.0
     */
    public function getCategoriesSegment($id, $query): array
    {
        // Extract numeric ID if format is "id:alias"
        $numericId     = $id;
        $providedAlias = null;

        if (strpos((string) $id, ':') !== false) {
            [$numericId, $providedAlias] = explode(':', $id, 2);
        }
        $numericId = (int) $numericId;

        // Root category (0 or 1) - return empty (no segment needed)
        if ($numericId <= 1) {
            return [];
        }

        // Look up category alias if not provided
        if (!$providedAlias) {
            $dbquery = $this->db->getQuery(true);
            $dbquery->select($this->db->quoteName('alias'))
                ->from($this->db->quoteName('#__categories'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
                ->bind(':id', $numericId, ParameterType::INTEGER);

            $this->db->setQuery($dbquery);
            $providedAlias = $this->db->loadResult();
        }

        // Build segment based on noIDs setting
        if ($this->noIDs) {
            // Clean URLs: category-alias
            return [$numericId => $providedAlias ?: (string) $numericId];
        }
        // With IDs: 9:category-alias
        return [$numericId => $numericId . ':' . ($providedAlias ?: (string) $numericId)];

    }

    /**
     * Get category ID from URL segment for categories view
     *
     * @param   string  $segment  URL segment (alias)
     * @param   array   $query    Current query
     *
     * @return  int  Category ID
     *
     * @since   6.0.0
     */
    public function getCategoriesId($segment, $query): int
    {
        // If segment is numeric (old URL format), return it
        if (is_numeric($segment)) {
            return (int) $segment;
        }

        // If segment contains colon (ID:alias format), extract ID
        if (strpos($segment, ':') !== false) {
            [$id] = explode(':', $segment, 2);
            return (int) $id;
        }

        // Segment is alias - lookup category ID
        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('alias') . ' = :segment')
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->bind(':segment', $segment);

        $this->db->setQuery($dbquery);
        $id = (int) $this->db->loadResult();

        return $id ?: (int) $segment;
    }

    /**
     * Get URL segment for a product
     *
     * @param   string  $id     Product ID (may include alias as "id:alias")
     * @param   array   $query  Current query
     *
     * @return  array  Segments as [id => segment]
     *
     * @since   6.0.0
     */
    public function getProductSegment($id, $query): array
    {
        // Extract numeric ID and alias if format is "id:alias"
        $numericId     = $id;
        $providedAlias = null;

        if (strpos((string) $id, ':') !== false) {
            [$numericId, $providedAlias] = explode(':', $id, 2);
        }

        // Look up alias from content article if not provided
        if (!$providedAlias) {
            $dbquery = $this->db->getQuery(true);
            $dbquery->select($this->db->quoteName('c.alias'))
                ->from($this->db->quoteName('#__content', 'c'))
                ->join(
                    'INNER',
                    $this->db->quoteName('#__j2commerce_products', 'p'),
                    $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
                )
                ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
                ->where($this->db->quoteName('p.j2commerce_product_id') . ' = :id')
                ->bind(':id', $numericId, ParameterType::INTEGER);

            $this->db->setQuery($dbquery);
            $providedAlias = $this->db->loadResult();
        }

        // Build segment based on noIDs setting
        if ($this->noIDs) {
            // Clean URLs: product-alias
            return [(int) $numericId => $providedAlias ?: (string) $numericId];
        }
        // With IDs: 42:product-alias
        return [(int) $numericId => $numericId . ':' . ($providedAlias ?: (string) $numericId)];

    }

    /**
     * Get product ID from URL segment (alias lookup)
     *
     * @param   string  $segment  URL segment (alias)
     * @param   array   $query    Current query
     *
     * @return  int  Product ID
     *
     * @since   6.0.0
     */
    public function getProductId($segment, $query): int
    {
        // If segment is numeric (old URL format), return it
        if (is_numeric($segment)) {
            return (int) $segment;
        }

        // If segment contains colon (ID:alias format), extract ID
        if (strpos($segment, ':') !== false) {
            [$id] = explode(':', $segment, 2);
            return (int) $id;
        }

        // Segment is alias - lookup product ID
        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('p.j2commerce_product_id'))
            ->from($this->db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $this->db->quoteName('#__content', 'c'),
                $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
            )
            ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('c.alias') . ' = :segment')
            ->bind(':segment', $segment);

        // If we have category context, use it to narrow the search
        if (!empty($query['catid'])) {
            $catid = (int) $query['catid'];
            $dbquery->where($this->db->quoteName('c.catid') . ' = :catid')
                ->bind(':catid', $catid, ParameterType::INTEGER);
        }

        $this->db->setQuery($dbquery);
        $id = (int) $this->db->loadResult();

        return $id ?: (int) $segment;
    }

    /**
     * Build the path for URL generation
     *
     * Overrides parent to ensure products always include their category.
     * This is the key to canonical URL generation - we look up the product's
     * actual category and include it in the path regardless of how the user
     * navigated to the product.
     *
     * @param   array  $query  The query parameters
     *
     * @return  array  The path segments by view
     *
     * @since   6.0.0
     */
    public function getPath($query): array
    {
        // If we have a product view, ensure we have the category
        if (isset($query['view']) && $query['view'] === 'product' && isset($query['id'])) {
            $productId = $query['id'];

            // Extract numeric ID if format is "id:alias"
            if (strpos((string) $productId, ':') !== false) {
                [$productId] = explode(':', $productId, 2);
            }

            // Look up the product's category if not already in query
            if (empty($query['catid'])) {
                $dbquery = $this->db->getQuery(true);
                $dbquery->select([$this->db->quoteName('c.catid'), $this->db->quoteName('c.alias')])
                    ->from($this->db->quoteName('#__content', 'c'))
                    ->join(
                        'INNER',
                        $this->db->quoteName('#__j2commerce_products', 'p'),
                        $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
                    )
                    ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
                    ->where($this->db->quoteName('p.j2commerce_product_id') . ' = :id')
                    ->bind(':id', $productId, ParameterType::INTEGER);

                $this->db->setQuery($dbquery);
                $content = $this->db->loadObject();

                if ($content) {
                    // Set the category - this ensures canonical routing through the category
                    $query['catid'] = $content->catid;

                    // Also ensure we have the alias for the product ID
                    if (strpos((string) $query['id'], ':') === false && !empty($content->alias)) {
                        $query['id'] = $productId . ':' . $content->alias;
                    }
                }
            }
        }

        // Now build the path using parent logic
        return parent::getPath($query);
    }

    /**
     * Parse URL segments into query parameters
     *
     * Overrides parent to handle J2Commerce-specific cases:
     * 1. Categories menu + segment(s) = category path (possibly with product at end)
     * 2. Single segment from products menu = product alias (not category)
     * 3. Multiple segments = category path + product alias
     *
     * @param   array  &$segments  URL segments
     *
     * @return  array  Query parameters
     *
     * @since   6.0.0
     */
    public function parse(&$segments): array
    {
        $vars   = [];
        $active = $this->menu->getActive();

        // No segments - return empty (menu provides the query)
        if (empty($segments)) {
            return $vars;
        }

        // Check if active menu is a products view with a specific catid
        $menuView  = $active->query['view'] ?? null;
        $menuCatid = isset($active->query['catid']) ? (int) $active->query['catid'] : null;
        $menuId    = isset($active->query['id']) ? (int) $active->query['id'] : null;

        // CASE 1: Categories menu - segments are category aliases (possibly with product at end)
        if ($menuView === 'categories') {
            // The categories menu may have an id parameter indicating the parent category
            // If no id or id <= 1, start from root (parent_id = 1)
            $parentCatid = ($menuId && $menuId > 1) ? $menuId : 1;

            // Try to resolve each segment as a category
            $resolvedCategories = [];
            $remainingSegments  = $segments;

            foreach ($segments as $index => $segment) {
                $catId = $this->getCategoryIdByAliasUnderParent($segment, $parentCatid);

                if ($catId) {
                    $resolvedCategories[] = $catId;
                    $parentCatid          = $catId;
                    array_shift($remainingSegments);
                } else {
                    // This segment is not a category - might be a product
                    break;
                }
            }

            // If we resolved at least one category segment
            if (!empty($resolvedCategories)) {
                $lastCatId = end($resolvedCategories);

                // Check if there's a remaining segment that could be a product
                if (!empty($remainingSegments)) {
                    $productSegment = array_shift($remainingSegments);
                    $productId      = $this->getProductIdByAliasInCategory($productSegment, $lastCatId);

                    if ($productId) {
                        // It's a product under this category
                        $vars['view']  = 'product';
                        $vars['id']    = $productId;
                        $vars['catid'] = $lastCatId;
                        $segments      = $remainingSegments;
                        return $vars;
                    }

                    // Not a product - put segment back (might be handled by parent)
                    array_unshift($remainingSegments, $productSegment);
                }

                // Check if this category has a display mode override that needs the categories view
                $catParams      = $this->getCategoryParams($lastCatId);
                $catDisplayMode = $catParams['subcategory_display_mode'] ?? '';

                if ($catDisplayMode !== '' && $catDisplayMode !== 'products') {
                    // Route to categories view so display mode logic applies
                    $vars['view'] = 'categories';
                    $vars['id']   = $lastCatId;
                    $segments     = $remainingSegments;
                    return $vars;
                }

                // Default: show products in this category
                $vars['view']  = 'products';
                $vars['catid'] = $lastCatId;
                $segments      = $remainingSegments;
                return $vars;
            }

            // No categories resolved — segment may be a product directly under the menu's category tree
            if (\count($remainingSegments) === 1) {
                $productId = $this->getProductId($remainingSegments[0], []);

                if ($productId) {
                    $vars['view'] = 'product';
                    $vars['id']   = $productId;
                    $segments     = [];
                    return $vars;
                }
            }
        }

        // CASE 2: Products menu with catid
        if ($menuView === 'products' && $menuCatid) {
            // Single segment from a category-specific products menu = product alias
            if (\count($segments) === 1) {
                $productId = $this->getProductIdByAliasInCategory($segments[0], $menuCatid);

                if ($productId) {
                    $vars['view']  = 'product';
                    $vars['id']    = $productId;
                    $vars['catid'] = $menuCatid;
                    array_shift($segments);
                    return $vars;
                }
            }

            // Two or more segments: first could be subcategory, last is product
            if (\count($segments) >= 2) {
                // Try to parse category path first
                $lastSegment = array_pop($segments);
                $catid       = $menuCatid;

                // Walk through category segments
                foreach ($segments as $segment) {
                    $newCatid = $this->getCategoryId($segment, ['id' => $catid]);
                    if ($newCatid) {
                        $catid = $newCatid;
                    }
                }

                // Last segment should be product
                $productId = $this->getProductIdByAliasInCategory($lastSegment, $catid);

                if ($productId) {
                    $vars['view']  = 'product';
                    $vars['id']    = $productId;
                    $vars['catid'] = $catid;
                    $segments      = []; // Clear all segments
                    return $vars;
                }

                // Restore last segment if not found
                $segments[] = $lastSegment;
            }
        }

        // Let parent handle other cases
        return parent::parse($segments);
    }

    /**
     * Get category ID by alias under a specific parent category
     *
     * @param   string  $alias     Category alias
     * @param   int     $parentId  Parent category ID
     *
     * @return  int|null  Category ID or null if not found
     *
     * @since   6.0.0
     */
    private function getCategoryIdByAliasUnderParent(string $alias, int $parentId): ?int
    {
        // Handle "id:alias" format
        if (strpos($alias, ':') !== false) {
            [$id] = explode(':', $alias, 2);
            if (is_numeric($id)) {
                return (int) $id;
            }
        }

        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('parent_id') . ' = :parent')
            ->bind(':alias', $alias)
            ->bind(':parent', $parentId, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        $result = $this->db->loadResult();

        return $result ? (int) $result : null;
    }

    /**
     * Get product ID by alias within a specific category
     *
     * @param   string  $alias  Product alias
     * @param   int     $catid  Category ID to search within
     *
     * @return  int|null  Product ID or null if not found
     *
     * @since   6.0.0
     */
    private function getProductIdByAliasInCategory(string $alias, int $catid): ?int
    {
        // Handle "id:alias" format
        if (strpos($alias, ':') !== false) {
            [$id] = explode(':', $alias, 2);
            if (is_numeric($id)) {
                return (int) $id;
            }
        }

        $dbquery = $this->db->getQuery(true);
        $dbquery->select($this->db->quoteName('p.j2commerce_product_id'))
            ->from($this->db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $this->db->quoteName('#__content', 'c'),
                $this->db->quoteName('p.product_source_id') . ' = ' . $this->db->quoteName('c.id')
            )
            ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
            ->where($this->db->quoteName('c.alias') . ' = :alias')
            ->where($this->db->quoteName('c.catid') . ' = :catid')
            ->bind(':alias', $alias)
            ->bind(':catid', $catid, ParameterType::INTEGER);

        $this->db->setQuery($dbquery);
        $result = $this->db->loadResult();

        return $result ? (int) $result : null;
    }

    /**
     * Find menu item for categories view by parent category ID
     *
     * @param   int|null  $parentId  Parent category ID
     *
     * @return  object|null  Menu item or null
     *
     * @since   6.0.0
     */
    private function findCategoriesMenu(?int $parentId): ?object
    {
        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            if (!isset($menu->query['view']) || $menu->query['view'] !== 'categories') {
                continue;
            }

            if ($parentId !== null) {
                $menuId = null;

                if (isset($menu->query['id'])) {
                    $menuId = (int) $menu->query['id'];
                } elseif (!empty($menu->link)) {
                    parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                    if (isset($linkQuery['id'])) {
                        $menuId = (int) $linkQuery['id'];
                    }
                }

                $normalizedMenuId   = ($menuId === null || $menuId <= 1) ? 1 : $menuId;
                $normalizedParentId = ($parentId <= 1) ? 1 : $parentId;

                if ($normalizedMenuId === $normalizedParentId) {
                    return $menu;
                }
            } else {
                return $menu;
            }
        }

        return null;
    }

    /**
     * Find a menu item by view name (exact match)
     *
     * @param   string  $viewName  The view name to match
     *
     * @return  object|null  Menu item or null
     *
     * @since   6.0.0
     */
    private function findViewMenu(string $viewName): ?object
    {
        $menus = $this->menu->getItems('component', 'com_j2commerce');

        foreach ($menus as $menu) {
            if (isset($menu->query['view']) && $menu->query['view'] === $viewName) {
                return $menu;
            }

            if (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);

                if (isset($linkQuery['view']) && $linkQuery['view'] === $viewName) {
                    return $menu;
                }
            }
        }

        return null;
    }
}
