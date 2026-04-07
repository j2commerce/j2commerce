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
use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Products list model for site frontend.
 *
 * Loads products filtered by menu item parameters (categories, ordering, pagination)
 * and hydrates each product with full data via ProductHelper::getFullProduct().
 *
 * @since  6.0.0
 */
class ProductsModel extends ListModel
{
    /**
     * Model context string.
     *
     * @var   string
     * @since 6.0.0
     */
    protected $_context = 'com_j2commerce.products';

    /**
     * Parent category node.
     *
     * @var   CategoryNode|null
     * @since 6.0.0
     */
    protected $_parent = null;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_product_id', 'p.j2commerce_product_id',
                'product_source_id', 'p.product_source_id',
                'product_type', 'p.product_type',
                'enabled', 'p.enabled',
                'visibility', 'p.visibility',
                'a.title', 'title',
                'a.created', 'created',
                'a.ordering', 'ordering',
                'a.catid', 'catid',
                'p.hits', 'hits',
                'v.price', 'price',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $params = $app->getParams();

        // Set the parameters
        $this->setState('params', $params);

        // Category filter from menu item
        // The catid can come from two sources:
        // 1. URL query string (direct access or non-SEF URL)
        // 2. Active menu item's query parameters (SEF URL navigation)
        $catid = $input->getInt('catid', 0);


        // If not in input, check the active menu item's query parameters
        // This is needed for SEF URLs where catid is part of the menu item link, not the URL
        if (!$catid) {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                // Check query array first (populated by router)
                if (isset($menu->query['catid'])) {
                    $catid = (int) $menu->query['catid'];
                } elseif (!empty($menu->link)) {
                    // Fallback: parse catid from the link URL
                    parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                    if (isset($linkQuery['catid'])) {
                        $catid = (int) $linkQuery['catid'];
                    }
                }
            }
        }

        $catids = $catid ? [$catid] : [];
        $this->setState('filter.catids', $catids);

        // Show only featured products
        $showFeatured = $params->get('show_feature_only', 0);
        $this->setState('filter.featured', $showFeatured);

        // Subcategory depth
        $subcategoryLevels = $params->get('show_subcategory_content', 3);
        $this->setState('filter.subcategory_levels', (int) $subcategoryLevels);

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

        // Allow URL override of ordering
        // Support both standard Joomla params (filter_order) and SEF-friendly params (sort)
        $listOrdering  = $app->getInput()->get('filter_order', '', 'cmd');
        $listDirection = $app->getInput()->get('filter_order_Dir', '', 'cmd');

        // Check for SEF-friendly sort parameter (e.g., sort=name-asc, sort=price-desc)
        if (empty($listOrdering)) {
            $sortParam = $app->getInput()->getString('sort', '');
            if (!empty($sortParam)) {
                // Map SEF-friendly sort names to SQL column and direction
                $sortMapping = [
                    'name-asc'   => ['a.title', 'ASC'],
                    'name-desc'  => ['a.title', 'DESC'],
                    'price-asc'  => ['v.price', 'ASC'],
                    'price-desc' => ['v.price', 'DESC'],
                    'newest'     => ['a.created', 'DESC'],
                    'popular'    => ['p.hits', 'DESC'],
                    'default'    => ['a.ordering', 'ASC'],
                ];

                if (isset($sortMapping[$sortParam])) {
                    [$listOrdering, $listDirection] = $sortMapping[$sortParam];
                }
            }
        }

        // Also check for sortby parameter from form submission (full SQL format)
        if (empty($listOrdering)) {
            $sortby = $app->getInput()->getString('sortby', '');
            if (!empty($sortby)) {
                // Parse "column DIRECTION" format (e.g., "a.title ASC")
                if (preg_match('/^([a-z_.]+)\s+(ASC|DESC)$/i', $sortby, $matches)) {
                    $listOrdering  = $matches[1];
                    $listDirection = strtoupper($matches[2]);
                } elseif (\in_array($sortby, ['a.ordering', 'a.title', 'a.created', 'a.hits', 'v.price'])) {
                    $listOrdering = $sortby;
                }
            }
        }

        // Fall back to menu item ordering if no URL override
        if (empty($listOrdering)) {
            $listOrdering = $orderMapping;
        }
        if (empty($listDirection)) {
            $listDirection = $orderDirection;
        }

        $listDirection = strtoupper($listDirection) === 'DESC' ? 'DESC' : 'ASC';
        $this->setState('list.ordering', $listOrdering);
        $this->setState('list.direction', $listDirection);

        // Set sortby state for template dropdown selection (format: "column DIRECTION")
        // This matches the dropdown option values in ProductHelper::getSortingOptions()
        $sortbyForTemplate = $listOrdering;
        if ($listOrdering !== 'a.ordering') {
            $sortbyForTemplate = $listOrdering . ' ' . $listDirection;
        }
        $this->setState('sortby', $sortbyForTemplate);

        // Search filter from frontend - support both 'filter_search' and 'search' params
        $search = $app->getInput()->getString('filter_search', '');
        if (empty($search)) {
            $search = $app->getInput()->getString('search', '');
        }
        $this->setState('filter.search', $search);
        $this->setState('search', $search);  // Also set 'search' for template access

        // Pagination
        $limit = $params->get('page_limit', $app->get('list_limit', 20));
        $this->setState('list.limit', (int) $limit);

        $limitstart = $app->getInput()->getInt('limitstart', 0);
        $this->setState('list.start', $limitstart);

        // Language filter
        $this->setState('filter.language', Multilanguage::isEnabled());
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.0
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . serialize($this->getState('filter.catids'));
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.featured');
        $id .= ':' . $this->getState('filter.language');
        $id .= ':' . serialize($this->getState('filter.manufacturer_ids', []));
        $id .= ':' . serialize($this->getState('filter.vendor_ids', []));
        $id .= ':' . serialize($this->getState('filter.productfilter_ids', []));
        $id .= ':' . $this->getState('filter.price_from', 0);
        $id .= ':' . $this->getState('filter.price_to', 0);

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $params = $this->getState('params');
        $user   = $this->getCurrentUser();

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

        // Select master variant price, SKU, and UPC
        $query->select($db->quoteName('v.price'));
        $query->select($db->quoteName('v.sku'));
        $query->select($db->quoteName('v.upc'));

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

        // Filter by category
        $catids = $this->getState('filter.catids', []);
        if (!empty($catids)) {
            $subcategoryLevels = (int) $this->getState('filter.subcategory_levels', 3);

            if ($subcategoryLevels > 0) {
                // Include subcategories
                // CRITICAL: Subquery bindings (bind(), whereIn()) don't transfer when embedded
                // as a string in the main query. We must use raw SQL with sanitized integers.
                $sanitizedCatids = implode(',', array_map('intval', $catids));

                $subQuery = $db->getQuery(true);
                $subQuery->select('DISTINCT ' . $db->quoteName('sub.id'))
                    ->from($db->quoteName('#__categories', 'sub'))
                    ->join(
                        'INNER',
                        $db->quoteName('#__categories', 'this'),
                        $db->quoteName('sub.lft') . ' > ' . $db->quoteName('this.lft')
                            . ' AND ' . $db->quoteName('sub.lft') . ' < ' . $db->quoteName('this.rgt')
                    )
                    ->where($db->quoteName('this.id') . ' IN (' . $sanitizedCatids . ')')
                    ->where($db->quoteName('sub.level') . ' <= ' . $db->quoteName('this.level') . ' + ' . $subcategoryLevels);

                $query->where(
                    '(' . $db->quoteName('a.catid') . ' IN (' . $subQuery . ')'
                    . ' OR ' . $db->quoteName('a.catid') . ' IN (' . $sanitizedCatids . '))'
                );
            } else {
                // Only selected categories
                $query->whereIn($db->quoteName('a.catid'), array_map('intval', $catids));
            }
        }

        // Filter by featured
        $featured = (int) $this->getState('filter.featured', 0);
        if ($featured === 1) {
            $query->where($db->quoteName('a.featured') . ' = 1');
        }

        // Filter by language
        if ($this->getState('filter.language')) {
            $query->whereIn(
                $db->quoteName('a.language'),
                [Factory::getApplication()->getLanguage()->getTag(), '*'],
                ParameterType::STRING
            );
        }

        // Search filter - searches title, SKU, UPC, and description
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '(' . $db->quoteName('a.title') . ' LIKE :search1'
                . ' OR ' . $db->quoteName('v.sku') . ' LIKE :search2'
                . ' OR ' . $db->quoteName('v.upc') . ' LIKE :search3'
                . ' OR ' . $db->quoteName('a.introtext') . ' LIKE :search4)'
            )
                ->bind(':search1', $search)
                ->bind(':search2', $search)
                ->bind(':search3', $search)
                ->bind(':search4', $search);
        }

        // Filter by manufacturer IDs
        $manufacturerIds = $this->getState('filter.manufacturer_ids', []);
        if (!empty($manufacturerIds)) {
            $query->whereIn($db->quoteName('p.manufacturer_id'), array_map('intval', $manufacturerIds));
        }

        // Filter by vendor IDs
        $vendorIds = $this->getState('filter.vendor_ids', []);
        if (!empty($vendorIds)) {
            $query->whereIn($db->quoteName('p.vendor_id'), array_map('intval', $vendorIds));
        }

        // Filter by product filter IDs (custom attributes)
        $productfilterIds = $this->getState('filter.productfilter_ids', []);
        if (!empty($productfilterIds)) {
            $sanitizedFilterIds = implode(',', array_map('intval', $productfilterIds));
            $subQueryPf         = $db->getQuery(true);
            $subQueryPf->select('DISTINCT ' . $db->quoteName('pf.product_id'))
                ->from($db->quoteName('#__j2commerce_product_filters', 'pf'))
                ->where($db->quoteName('pf.filter_id') . ' IN (' . $sanitizedFilterIds . ')');
            $query->where($db->quoteName('p.j2commerce_product_id') . ' IN (' . $subQueryPf . ')');
        }

        // Filter by price range (considers advanced/special pricing)
        $priceFrom = (float) $this->getState('filter.price_from', 0);
        $priceTo   = (float) $this->getState('filter.price_to', 0);
        if ($priceFrom > 0 || $priceTo > 0) {
            $this->applyPriceRangeFilter($query, $db, $user, $priceFrom, $priceTo);
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = $this->state->get('list.direction', 'ASC');

        // Handle price ordering specially (use variant price)
        if ($orderCol === 'price' || $orderCol === 'v.price') {
            $orderCol = 'v.price';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Method to get an array of product items.
     *
     * Overrides parent to hydrate each product with full data including
     * images, pricing, stock, etc via ProductHelper::getFullProduct().
     *
     * @return  array  An array of product objects.
     *
     * @since   6.0.0
     */
    public function getItems(): array
    {
        $items = parent::getItems();

        if (empty($items)) {
            return [];
        }

        $params        = $this->getState('params');
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

        return $hydratedItems;
    }

    /**
     * Get parent category node.
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

        $catids = $this->getState('filter.catids', []);

        if (empty($catids)) {
            return null;
        }

        // Get the first category as parent
        $categories    = \Joomla\CMS\Categories\Categories::getInstance('Content');
        $this->_parent = $categories->get((int) reset($catids));

        return $this->_parent;
    }

    /**
     * Get filters for product listing sidebar.
     *
     * Returns filter data for categories, price range, manufacturers,
     * vendors, product filters (custom attributes), and sorting options.
     *
     * Uses `list_filter_selected_categories` menu item parameter for category filter display,
     * falling back to the current filter category if not specified.
     *
     * @param   array  $items  Array of product items (used for context-aware filtering).
     *
     * @return  array  Filter configuration array.
     *
     * @since   6.0.3
     */
    public function getFilters(array $items = []): array
    {
        $params     = $this->getState('params');
        $filterMode = $params->get('list_filter_category_mode', 'selected');

        if ($filterMode === 'siblings') {
            // Show sibling categories of the current category
            $catids            = $this->getState('filter.catids', []);
            $currentCatId      = !empty($catids) ? (int) $catids[0] : 0;
            $filterCategoryIds = $currentCatId ? $this->getSiblingCategoryIds($currentCatId) : [];
        } else {
            // Use manually selected categories from menu item settings
            $filterCategoryIds = $params->get('list_filter_selected_categories', []);

            if (!empty($filterCategoryIds)) {
                if (!\is_array($filterCategoryIds)) {
                    $filterCategoryIds = [$filterCategoryIds];
                }
                $filterCategoryIds = array_map('intval', array_filter($filterCategoryIds));
            }
        }

        // Fall back to current filter category if no specific categories configured
        if (empty($filterCategoryIds)) {
            $filterCategoryIds = $this->getState('filter.catids', []);
        }

        return ProductHelper::getFilters($items, $filterCategoryIds);
    }

    protected function getSiblingCategoryIds(int $categoryId): array
    {
        $categories = \Joomla\CMS\Categories\Categories::getInstance('Content', ['access' => true]);
        $node       = $categories->get($categoryId);

        if (!$node) {
            return [$categoryId];
        }

        $parent = $node->getParent();

        if (!$parent) {
            return [$categoryId];
        }

        $ids = [];
        foreach ($parent->getChildren() as $sibling) {
            $ids[] = (int) $sibling->id;
        }

        return $ids ?: [$categoryId];
    }

    protected function applyPriceRangeFilter(
        QueryInterface $query,
        \Joomla\Database\DatabaseInterface $db,
        \Joomla\CMS\User\User $user,
        float $priceFrom,
        float $priceTo
    ): void {
        $now        = Factory::getDate()->toSql();
        $userGroups = $user->getAuthorisedGroups();
        $groupList  = !empty($userGroups) ? implode(',', array_map('intval', $userGroups)) : '0';

        // Subquery: min child variant price per product (variable, flexivariable, etc.)
        $vcSub = $db->getQuery(true)
            ->select([
                $db->quoteName('vc.product_id'),
                'MIN(' . $db->quoteName('vc.price') . ') AS ' . $db->quoteName('min_child_price'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'vc'))
            ->where($db->quoteName('vc.is_master') . ' = 0')
            ->where($db->quoteName('vc.price') . ' > 0')
            ->group($db->quoteName('vc.product_id'));

        $query->join('LEFT', '(' . $vcSub . ') AS ' . $db->quoteName('vc') . ' ON ' . $db->quoteName('vc.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id'));

        // Subquery: min advanced/special pricing for the master variant
        $ppSub = $db->getQuery(true)
            ->select([
                $db->quoteName('pp.variant_id'),
                'MIN(' . $db->quoteName('pp.price') . ') AS ' . $db->quoteName('min_price'),
            ])
            ->from($db->quoteName('#__j2commerce_product_prices', 'pp'))
            ->where('(' . $db->quoteName('pp.quantity_from') . ' IS NULL OR ' . $db->quoteName('pp.quantity_from') . ' <= 1)')
            ->where('(' . $db->quoteName('pp.quantity_to') . ' IS NULL OR ' . $db->quoteName('pp.quantity_to') . ' = 0 OR ' . $db->quoteName('pp.quantity_to') . ' >= 1)')
            ->where('(' . $db->quoteName('pp.date_from') . ' IS NULL OR ' . $db->quoteName('pp.date_from') . ' = ' . $db->quote($db->getNullDate()) . ' OR ' . $db->quoteName('pp.date_from') . ' <= ' . $db->quote($now) . ')')
            ->where('(' . $db->quoteName('pp.date_to') . ' IS NULL OR ' . $db->quoteName('pp.date_to') . ' = ' . $db->quote($db->getNullDate()) . ' OR ' . $db->quoteName('pp.date_to') . ' >= ' . $db->quote($now) . ')')
            ->where('(' . $db->quoteName('pp.customer_group_id') . ' IS NULL OR ' . $db->quoteName('pp.customer_group_id') . ' IN (' . $groupList . '))')
            ->group($db->quoteName('pp.variant_id'));

        $query->join('LEFT', '(' . $ppSub . ') AS ' . $db->quoteName('pp') . ' ON ' . $db->quoteName('pp.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'));

        // Base price: use min child variant price when children exist, otherwise master price.
        // This handles variable/flexivariable products where master_price=$0.
        $basePrice = 'COALESCE(' . $db->quoteName('vc.min_child_price') . ', ' . $db->quoteName('v.price') . ')';

        // Effective price: lowest of base price and any active advanced pricing
        $effectivePrice = 'LEAST(' . $basePrice . ', COALESCE(' . $db->quoteName('pp.min_price') . ', ' . $basePrice . '))';

        if ($priceFrom > 0) {
            $query->where($effectivePrice . ' >= :price_from')
                ->bind(':price_from', $priceFrom, ParameterType::STRING);
        }
        if ($priceTo > 0) {
            $query->where($effectivePrice . ' <= :price_to')
                ->bind(':price_to', $priceTo, ParameterType::STRING);
        }
    }
}
