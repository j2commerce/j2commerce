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

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Categories model for site frontend.
 *
 * Loads categories from com_content that contain J2Commerce products,
 * with optional subcategory depth filtering and product counts.
 *
 * @since  6.0.0
 */
class CategoriesModel extends BaseDatabaseModel
{
    /**
     * Model context string.
     *
     * @var   string
     * @since 6.0.0
     */
    protected $_context = 'com_j2commerce.categories';

    /**
     * Parent category node.
     *
     * @var   CategoryNode|null
     * @since 6.0.0
     */
    protected $_parent = null;

    /**
     * Category items.
     *
     * @var   array|null
     * @since 6.0.0
     */
    protected $_items = null;

    /**
     * Products in root category.
     *
     * @var   array|null
     * @since 6.0.0
     */
    protected $_products = null;

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState(): void
    {
        $app    = Factory::getApplication();
        $params = $app->getParams();

        $this->setState('params', $params);

        // Resolve parent category ID: URL input -> menu query -> menu params -> default
        $parentId = $app->getInput()->getInt("id", 0);

        if ($parentId === 0) {
            $menu = $app->getMenu()->getActive();
            if ($menu && isset($menu->query["id"])) {
                $parentId = (int) $menu->query["id"];
            }
        }

        if ($parentId === 0) {
            $parentId = (int) $params->get("id", 1);
        }

        $this->setState("filter.parent_id", $parentId);

        // Merge category-level param overrides for filter settings.
        // The HtmlView does this same merge for template params, but getItems() runs
        // before the view can apply overrides — so we must merge here in the model.
        if ($parentId > 1) {
            $categories = Categories::getInstance('Content');
            $parentNode = $categories->get($parentId);

            if ($parentNode) {
                $catParams = $parentNode->getParams();

                foreach (['category_view_type', 'show_subcategories', 'subcategory_levels', 'show_empty_categories'] as $key) {
                    $value = $catParams->get($key, '');
                    if ($value !== '' && $value !== null) {
                        $params->set($key, $value);
                    }
                }
            }
        }

        $this->setState('filter.show_subcategories', (int) $params->get('show_subcategories', 1));
        $this->setState('filter.subcategory_levels', (int) $params->get('subcategory_levels', 1));
        $this->setState('filter.show_empty', (int) $params->get('show_empty_categories', 0));

        $user = $this->getCurrentUser();
        $this->setState('filter.access', $user->getAuthorisedViewLevels());
    }

    /**
     * Get the parent category.
     *
     * @return  CategoryNode|null
     *
     * @since   6.0.0
     */
    public function getParent(): ?CategoryNode
    {
        if ($this->_parent !== null) {
            return $this->_parent;
        }

        $parentId = (int) $this->getState('filter.parent_id', 1);

        $categories    = Categories::getInstance('Content');
        $this->_parent = $categories->get($parentId);

        return $this->_parent;
    }

    /**
     * Get the list of categories.
     *
     * Returns categories with product counts. Each category object includes:
     * - Standard Joomla category properties (id, title, alias, description, etc.)
     * - product_count: Number of enabled products in this category
     * - children: Array of child categories (if show_subcategories is enabled)
     *
     * @return  array  Array of category objects
     *
     * @since   6.0.0
     */
    public function getItems(): array
    {
        if ($this->_items !== null) {
            return $this->_items;
        }

        $parent = $this->getParent();

        if (!$parent) {
            $this->_items = [];
            return $this->_items;
        }

        $showSubcategories = (int) $this->getState('filter.show_subcategories', 1);
        $subcategoryLevels = (int) $this->getState('filter.subcategory_levels', 1);
        $showEmpty         = (int) $this->getState('filter.show_empty', 0);
        $accessLevels      = $this->getState('filter.access', [1]);

        // Get children of parent category
        $children = $parent->getChildren();

        if (empty($children)) {
            $this->_items = [];
            return $this->_items;
        }

        // Get product counts for all categories
        $productCounts = $this->getProductCounts();

        // Process categories
        $this->_items = [];

        foreach ($children as $category) {
            // Check access level
            if (!\in_array((int) $category->access, $accessLevels, true)) {
                continue;
            }

            // Get product count for this category
            $productCount = $productCounts[$category->id] ?? 0;

            // Skip empty categories if not showing them
            if (!$showEmpty && $productCount === 0 && !$this->hasProductsInChildren($category, $productCounts)) {
                continue;
            }

            // Build category item
            $item                = new \stdClass();
            $item->id            = $category->id;
            $item->title         = $category->title;
            $item->alias         = $category->alias;
            $item->description   = $category->description ?? '';
            $item->path          = $category->path ?? '';
            $item->parent_id     = $category->parent_id;
            $item->level         = $category->level;
            $item->access        = $category->access;
            $item->language      = $category->language ?? '*';
            $item->product_count = $productCount;

            // Get category image from params
            $catParams       = $category->getParams();
            $item->image     = $catParams->get('image', '');
            $item->image_alt = $catParams->get('image_alt', '');

            // Get children if enabled
            $item->children = [];
            if ($showSubcategories && $subcategoryLevels > 0) {
                $item->children = $this->getChildCategories(
                    $category,
                    $subcategoryLevels - 1,
                    $productCounts,
                    $showEmpty,
                    $accessLevels
                );
            }

            $this->_items[] = $item;
        }

        return $this->_items;
    }

    /**
     * Get child categories recursively.
     *
     * @param   CategoryNode  $parent         Parent category
     * @param   int           $levelsRemain   Remaining levels to traverse
     * @param   array         $productCounts  Product counts by category ID
     * @param   int           $showEmpty      Whether to show empty categories
     * @param   array         $accessLevels   Allowed access levels
     *
     * @return  array  Array of child category objects
     *
     * @since   6.0.0
     */
    protected function getChildCategories(
        CategoryNode $parent,
        int $levelsRemain,
        array $productCounts,
        int $showEmpty,
        array $accessLevels
    ): array {
        if ($levelsRemain < 0) {
            return [];
        }

        $children = $parent->getChildren();

        if (empty($children)) {
            return [];
        }

        $items = [];

        foreach ($children as $category) {
            // Check access level
            if (!\in_array((int) $category->access, $accessLevels, true)) {
                continue;
            }

            $productCount = $productCounts[$category->id] ?? 0;

            // Skip empty categories if not showing them
            if (!$showEmpty && $productCount === 0 && !$this->hasProductsInChildren($category, $productCounts)) {
                continue;
            }

            $item                = new \stdClass();
            $item->id            = $category->id;
            $item->title         = $category->title;
            $item->alias         = $category->alias;
            $item->description   = $category->description ?? '';
            $item->path          = $category->path ?? '';
            $item->parent_id     = $category->parent_id;
            $item->level         = $category->level;
            $item->access        = $category->access;
            $item->language      = $category->language ?? '*';
            $item->product_count = $productCount;

            // Get category image from params
            $catParams       = $category->getParams();
            $item->image     = $catParams->get('image', '');
            $item->image_alt = $catParams->get('image_alt', '');

            // Get children if levels remain
            $item->children = [];
            if ($levelsRemain > 0) {
                $item->children = $this->getChildCategories(
                    $category,
                    $levelsRemain - 1,
                    $productCounts,
                    $showEmpty,
                    $accessLevels
                );
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Check if category has products in any children.
     *
     * @param   CategoryNode  $category       Category to check
     * @param   array         $productCounts  Product counts by category ID
     *
     * @return  bool  True if any child has products
     *
     * @since   6.0.0
     */
    protected function hasProductsInChildren(CategoryNode $category, array $productCounts): bool
    {
        $children = $category->getChildren();

        if (empty($children)) {
            return false;
        }

        foreach ($children as $child) {
            $count = $productCounts[$child->id] ?? 0;
            if ($count > 0) {
                return true;
            }

            if ($this->hasProductsInChildren($child, $productCounts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get product counts for all categories.
     *
     * @return  array  Array of product counts keyed by category ID
     *
     * @since   6.0.0
     */
    protected function getProductCounts(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('c.id', 'catid'),
                'COUNT(DISTINCT ' . $db->quoteName('p.j2commerce_product_id') . ') AS ' . $db->quoteName('product_count'),
            ])
            ->from($db->quoteName('#__categories', 'c'))
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'a'),
                $db->quoteName('a.catid') . ' = ' . $db->quoteName('c.id')
                    . ' AND ' . $db->quoteName('a.state') . ' = 1'
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_products', 'p'),
                $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('a.id')
                    . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
                    . ' AND ' . $db->quoteName('p.enabled') . ' = 1'
                    . ' AND ' . $db->quoteName('p.visibility') . ' = 1'
            )
            ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('c.published') . ' = 1')
            ->group($db->quoteName('c.id'));

        $db->setQuery($query);
        $results = $db->loadObjectList('catid');

        $counts = [];
        foreach ($results as $catid => $row) {
            $counts[(int) $catid] = (int) $row->product_count;
        }

        return $counts;
    }

    /**
     * Get products in the root category (not subcategories).
     *
     * Returns array of product objects with full data hydrated via ProductHelper.
     * Only loads products that are directly in the parent category, not subcategories.
     *
     * @return  array  Array of product objects
     *
     * @since   6.0.0
     */
    public function getProducts(): array
    {
        if ($this->_products !== null) {
            return $this->_products;
        }

        $db       = $this->getDatabase();
        $query    = $db->getQuery(true);
        $params   = $this->getState('params');
        $user     = $this->getCurrentUser();
        $parentId = (int) $this->getState('filter.parent_id', 1);

        // Select product fields
        $query->select(
            $db->quoteName([
                'p.j2commerce_product_id',
                'p.product_source',
                'p.product_source_id',
                'p.product_type',
                'p.visibility',
                'p.enabled',
                'p.manufacturer_id',
                'p.vendor_id',
                'p.taxprofile_id',
            ])
        );

        // Select article fields for com_content products
        $query->select([
            $db->quoteName('a.id', 'article_id'),
            $db->quoteName('a.title', 'product_name'),
            $db->quoteName('a.alias'),
            $db->quoteName('a.introtext', 'product_short_desc'),
            $db->quoteName('a.catid'),
            $db->quoteName('a.state', 'article_state'),
            $db->quoteName('a.access'),
            $db->quoteName('a.created'),
            $db->quoteName('a.ordering'),
            $db->quoteName('a.hits'),
            $db->quoteName('a.featured'),
            $db->quoteName('a.language'),
        ]);

        // Select master variant price
        $query->select($db->quoteName('v.price'));
        $query->select($db->quoteName('v.sku'));

        $query->from($db->quoteName('#__j2commerce_products', 'p'));

        // Join with content articles
        $query->join(
            'INNER',
            $db->quoteName('#__content', 'a'),
            $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
        );

        // Join with categories
        $query->join(
            'LEFT',
            $db->quoteName('#__categories', 'c'),
            $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
        );

        // Join with master variant for price
        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_variants', 'v'),
            $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
                . ' AND ' . $db->quoteName('v.is_master') . ' = 1'
        );

        // Filter by enabled products
        $query->where($db->quoteName('p.enabled') . ' = 1');
        $query->where($db->quoteName('p.visibility') . ' = 1');

        // Filter by published articles
        $query->where($db->quoteName('a.state') . ' = 1');

        // Filter by access level
        $groups = $user->getAuthorisedViewLevels();
        $query->whereIn($db->quoteName('a.access'), $groups);
        $query->whereIn($db->quoteName('c.access'), $groups);

        // CRITICAL: Filter by exact parent category (not subcategories)
        $query->where($db->quoteName('a.catid') . ' = :parent_id')
            ->bind(':parent_id', $parentId, ParameterType::INTEGER);

        // Filter by language
        if (Multilanguage::isEnabled()) {
            $query->whereIn(
                $db->quoteName('a.language'),
                [Factory::getApplication()->getLanguage()->getTag(), '*'],
                ParameterType::STRING
            );
        }

        // Ordering from menu item params
        $orderBy        = $params->get('orderby_sec', 'order');
        $orderDirection = $params->get('list_order_direction', 'ASC');
        $orderDate      = match ($params->get('order_date', 'created')) {
            'published' => 'publish_up',
            default     => $params->get('order_date', 'created'),
        };

        // Map ordering param to actual SQL ordering
        $orderMapping = match ($orderBy) {
            'date'      => 'a.' . $orderDate,
            'title'     => 'a.title',
            'author'    => 'a.created_by',
            'hits'      => 'a.hits',
            'price'     => 'v.price',
            'popular'   => 'p.hits',
            'order'     => 'a.ordering',
            'cat_order' => 'c.lft',
            'featured'  => 'a.featured',
            default     => 'a.ordering',
        };

        $query->order($db->escape($orderMapping) . ' ' . $db->escape(strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC'));

        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            $this->_products = [];
            return $this->_products;
        }

        // Hydrate each product with full data
        $hydratedItems = [];

        foreach ($items as $item) {
            // Get fully hydrated product data
            $product = ProductHelper::getFullProduct(
                (int) $item->j2commerce_product_id,
                false,  // Don't load variants for list view (performance)
                false   // Don't load options for list view
            );

            if ($product) {
                // Merge list-level data with full product
                $product->article_ordering = $item->ordering ?? 0;
                $product->article_hits     = $item->hits ?? 0;
                $product->article_featured = $item->featured ?? 0;

                $hydratedItems[] = $product;
            }
        }

        $this->_products = $hydratedItems;

        return $this->_products;
    }

    public function getPopularProducts(int $categoryId, int $limit = 12): array
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $user   = $this->getCurrentUser();
        $groups = $user->getAuthorisedViewLevels();

        // Get category and all its descendant IDs for deep product aggregation
        $categoryIds   = $this->getCategoryDescendants($categoryId);
        $categoryIds[] = $categoryId;

        $query->select([
                $db->quoteName('p.j2commerce_product_id'),
                $db->quoteName('a.title', 'product_name'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.hits'),
            ])
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'INNER',
                $db->quoteName('#__content', 'a'),
                $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__categories', 'c'),
                $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->where($db->quoteName('p.visibility') . ' = 1')
            ->where($db->quoteName('a.state') . ' = 1')
            ->whereIn($db->quoteName('a.access'), $groups)
            ->whereIn($db->quoteName('c.access'), $groups)
            ->whereIn($db->quoteName('a.catid'), $categoryIds)
            ->order($db->quoteName('a.hits') . ' DESC')
            ->setLimit($limit);

        if (Multilanguage::isEnabled()) {
            $query->whereIn(
                $db->quoteName('a.language'),
                [Factory::getApplication()->getLanguage()->getTag(), '*'],
                ParameterType::STRING
            );
        }

        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            return [];
        }

        $hydratedItems = [];
        foreach ($items as $item) {
            $product = ProductHelper::getFullProduct((int) $item->j2commerce_product_id, false, false);
            if ($product) {
                $hydratedItems[] = $product;
            }
        }

        return $hydratedItems;
    }

    protected function getCategoryDescendants(int $categoryId): array
    {
        $categories = Categories::getInstance('Content');
        $node       = $categories->get($categoryId);

        if (!$node) {
            return [];
        }

        $ids      = [];
        $children = $node->getChildren(true);

        foreach ($children as $child) {
            $ids[] = (int) $child->id;
        }

        return $ids;
    }

    public function getSiblingCategories(int $categoryId): array
    {
        $categories = Categories::getInstance('Content');
        $node       = $categories->get($categoryId);

        if (!$node || !$node->getParent()) {
            return [];
        }

        $parent        = $node->getParent();
        $siblings      = $parent->getChildren();
        $productCounts = $this->getProductCounts();
        $accessLevels  = $this->getState('filter.access', [1]);
        $items         = [];

        foreach ($siblings as $sibling) {
            if (!\in_array((int) $sibling->access, $accessLevels, true)) {
                continue;
            }

            $items[] = (object) [
                'id'            => (int) $sibling->id,
                'title'         => $sibling->title,
                'product_count' => $productCounts[$sibling->id] ?? 0,
            ];
        }

        return $items;
    }
}
