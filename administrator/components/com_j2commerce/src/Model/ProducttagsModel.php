<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

/**
 * Producttags model for tag-based storefront product listing.
 *
 * This model handles product queries filtered by Joomla's native tag system.
 * It is primarily used for frontend storefront views to display products
 * associated with specific tags.
 *
 * @since  6.0.6
 */
class ProducttagsModel extends ListModel
{
    /**
     * Cache for storefront products list
     *
     * @var   array|null
     * @since 6.0.6
     */
    protected ?array $sfList = null;

    /**
     * Cache for storefront pagination
     *
     * @var   Pagination|null
     * @since 6.0.6
     */
    protected ?Pagination $sfPagination = null;

    /**
     * Cache for storefront total count
     *
     * @var   int|null
     * @since 6.0.6
     */
    protected ?int $sfPageTotal = null;

    /**
     * Cache for all products list (for facets/aggregations)
     *
     * @var   array|null
     * @since 6.0.6
     */
    protected ?array $sfAllList = null;

    /**
     * Static cache for product source lookups
     *
     * @var   array
     * @since 6.0.6
     */
    protected static array $sourceCache = [];

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.6
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title', 'product_name',
                'catid', 'a.catid',
                'state', 'a.state',
                'created', 'a.created',
                'ordering', 'a.ordering',
                'hits', 'a.hits',
                'featured', 'a.featured',
                'j2commerce_product_id', 'p.j2commerce_product_id',
                'product_type', 'p.product_type',
                'manufacturer_id', 'p.manufacturer_id',
                'vendor_id', 'p.vendor_id',
                'sku', 'v.sku',
                'price', 'v.price',
                'min_price', 'min_price',
                'max_price', 'max_price',
                'brand_name', 'brand_name',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Get tags by alias array from Joomla's native tags table.
     *
     * @param   array|null  $tagAliases  Array of tag aliases to look up.
     *
     * @return  array  Array of tag objects.
     *
     * @since   6.0.6
     */
    public function getTags(?array $tagAliases): array
    {
        if (empty($tagAliases)) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__tags'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('lft') . ' ASC');

        // Filter by aliases using whereIn
        $query->whereIn($db->quoteName('alias'), $tagAliases, ParameterType::STRING);

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get storefront products filtered by tags.
     *
     * @return  array  Array of product objects.
     *
     * @since   6.0.6
     */
    public function getSFProducts(): array
    {
        if ($this->sfList === null) {
            $query        = $this->getSFQuery();
            $this->sfList = $this->executeSFQuery(
                $query,
                $this->getStart(),
                (int) $this->getState('list.limit', 20)
            );
        }

        return $this->sfList;
    }

    /**
     * Get all product IDs matching the current filters (for aggregations).
     *
     * @return  array  Array of product ID objects.
     *
     * @since   6.0.6
     */
    public function getSFAllProducts(): array
    {
        if ($this->sfAllList === null) {
            $query = $this->getSFQuery();
            $query->clear('select')
                ->clear('order')
                ->select($this->getDatabase()->quoteName('p.j2commerce_product_id'));

            $this->sfAllList = $this->executeSFQuery($query);
        }

        return $this->sfAllList;
    }

    /**
     * Get storefront pagination object.
     *
     * @return  Pagination  The pagination object.
     *
     * @since   6.0.6
     */
    public function getSFPagination(): Pagination
    {
        if ($this->sfPagination === null) {
            $total = $this->getSFPageTotal();
            $start = $this->getStart();
            $limit = (int) $this->getState('list.limit', 20);

            $this->sfPagination = new Pagination($total, $start, $limit);
        }

        return $this->sfPagination;
    }

    /**
     * Get the total number of products matching current filters.
     *
     * @return  int  Total count of products.
     *
     * @since   6.0.6
     */
    public function getSFPageTotal(): int
    {
        if ($this->sfPageTotal === null) {
            $query      = $this->getSFQuery();
            $countQuery = clone $query;
            $countQuery->clear('select')
                ->clear('order')
                ->select('COUNT(DISTINCT ' . $this->getDatabase()->quoteName('a.id') . ')');

            try {
                $this->getDatabase()->setQuery($countQuery);
                $this->sfPageTotal = (int) $this->getDatabase()->loadResult();
            } catch (\RuntimeException $e) {
                $this->sfPageTotal = 0;
            }
        }

        return $this->sfPageTotal;
    }

    /**
     * Get the start position for pagination.
     *
     * @return  int  The start position.
     *
     * @since   6.0.6
     */
    public function getStart(): int
    {
        $start = (int) $this->getState('list.start', 0);
        $limit = (int) $this->getState('list.limit', 20);
        $total = $this->getSFPageTotal();

        if ($start > $total - $limit) {
            $start = max(0, (int) (ceil($total / $limit) - 1) * $limit);
        }

        return $start;
    }

    /**
     * Build the master query for storefront product listing.
     *
     * This method builds a complex query that joins Joomla content articles
     * with J2Commerce products, filtering by tags.
     *
     * @return  QueryInterface  The query object.
     *
     * @since   6.0.6
     */
    public function getSFQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $user  = Factory::getApplication()->getIdentity();
        $query = $db->getQuery(true);

        // Select from content (articles)
        $this->buildContentSelect($query);

        // Add tag join
        $query->select($db->quoteName('tag.tag_id'))
            ->join('LEFT', $db->quoteName('#__contentitem_tag_map', 'tag') . ' ON ' .
                $db->quoteName('a.id') . ' = ' . $db->quoteName('tag.content_item_id'));

        // Join categories
        $query->select([
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.path', 'category_route'),
                $db->quoteName('c.access', 'category_access'),
                $db->quoteName('c.alias', 'category_alias'),
            ])
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON ' .
                $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'));

        // Join users for author
        $query->select([
                'CASE WHEN ' . $db->quoteName('a.created_by_alias') . ' > ' . $db->quote(' ') .
                ' THEN ' . $db->quoteName('a.created_by_alias') .
                ' ELSE ' . $db->quoteName('ua.name') . ' END AS ' . $db->quoteName('author'),
                $db->quoteName('ua.email', 'author_email'),
            ])
            ->join('LEFT', $db->quoteName('#__users', 'ua') . ' ON ' .
                $db->quoteName('ua.id') . ' = ' . $db->quoteName('a.created_by'))
            ->join('LEFT', $db->quoteName('#__users', 'uam') . ' ON ' .
                $db->quoteName('uam.id') . ' = ' . $db->quoteName('a.modified_by'));

        // Add modified_by_name
        $query->select($db->quoteName('uam.name', 'modified_by_name'));

        // Join parent category
        $query->select([
                $db->quoteName('parent.title', 'parent_title'),
                $db->quoteName('parent.id', 'parent_id'),
                $db->quoteName('parent.path', 'parent_route'),
                $db->quoteName('parent.alias', 'parent_alias'),
            ])
            ->join('LEFT', $db->quoteName('#__categories', 'parent') . ' ON ' .
                $db->quoteName('parent.id') . ' = ' . $db->quoteName('c.parent_id'));

        // Join content rating
        $query->select([
                'ROUND(' . $db->quoteName('v.rating_sum') . ' / ' .
                $db->quoteName('v.rating_count') . ', 0) AS ' . $db->quoteName('rating'),
                $db->quoteName('v.rating_count', 'rating_count'),
            ])
            ->join('LEFT', $db->quoteName('#__content_rating', 'v') . ' ON ' .
                $db->quoteName('a.id') . ' = ' . $db->quoteName('v.content_id'));

        // Check for unpublished parent categories
        $query->select([
                $db->quoteName('c.published'),
                'CASE WHEN ' . $db->quoteName('badcats.id') . ' IS NULL THEN ' .
                $db->quoteName('c.published') . ' ELSE 0 END AS ' . $db->quoteName('parents_published'),
            ]);

        // Subquery for bad categories
        $subQuery = $db->getQuery(true)
            ->select($db->quoteName('cat.id'))
            ->from($db->quoteName('#__categories', 'cat'))
            ->join('INNER', $db->quoteName('#__categories', 'parent2') . ' ON ' .
                $db->quoteName('cat.lft') . ' BETWEEN ' . $db->quoteName('parent2.lft') .
                ' AND ' . $db->quoteName('parent2.rgt'))
            ->where($db->quoteName('parent2.extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('parent2.published') . ' != 1')
            ->group($db->quoteName('cat.id'));

        $query->join('LEFT OUTER', '(' . $subQuery . ') AS ' . $db->quoteName('badcats') . ' ON ' .
            $db->quoteName('badcats.id') . ' = ' . $db->quoteName('c.id'));

        // Filter published articles
        $publishedWhere = 'CASE WHEN ' . $db->quoteName('badcats.id') . ' IS NULL THEN ' .
            $db->quoteName('a.state') . ' ELSE 0 END';
        $query->where($publishedWhere . ' = 1');

        // Add J2Commerce joins
        $this->buildSFQueryJoins($query);

        // Add WHERE filters
        $this->buildSFWhereQuery($query);

        // Add ORDER BY
        $this->buildSFQueryOrderBy($query);

        // Group by article and product
        $query->group($db->quoteName('a.id'))
            ->group($db->quoteName('p.j2commerce_product_id'));

        return $query;
    }

    /**
     * Build the content article SELECT clause.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function buildContentSelect(QueryInterface $query): void
    {
        $db       = $this->getDatabase();
        $nullDate = $db->quote($db->getNullDate());

        // Build the select list from state or default
        $selectList = $this->getState(
            'list.select',
            implode(', ', [
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.title', 'product_name'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.introtext'),
                $db->quoteName('a.fulltext'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.created'),
                $db->quoteName('a.created_by'),
                $db->quoteName('a.created_by_alias'),
                'CASE WHEN ' . $db->quoteName('a.modified') . ' = ' . $nullDate .
                ' THEN ' . $db->quoteName('a.created') . ' ELSE ' . $db->quoteName('a.modified') .
                ' END AS ' . $db->quoteName('modified'),
                $db->quoteName('a.modified_by'),
                'CASE WHEN ' . $db->quoteName('a.publish_up') . ' = ' . $nullDate .
                ' THEN ' . $db->quoteName('a.created') . ' ELSE ' . $db->quoteName('a.publish_up') .
                ' END AS ' . $db->quoteName('publish_up'),
                $db->quoteName('a.publish_down'),
                $db->quoteName('a.images'),
                $db->quoteName('a.urls'),
                $db->quoteName('a.attribs'),
                $db->quoteName('a.metadata'),
                $db->quoteName('a.metakey'),
                $db->quoteName('a.metadesc'),
                $db->quoteName('a.access'),
                $db->quoteName('a.hits'),
                $db->quoteName('a.featured'),
                $db->quoteName('a.language'),
                $query->length($db->quoteName('a.fulltext')) . ' AS ' . $db->quoteName('readmore'),
            ])
        );

        $query->select($selectList)
            ->from($db->quoteName('#__content', 'a'));
    }

    /**
     * Build J2Commerce product JOINs for storefront query.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function buildSFQueryJoins(QueryInterface $query): void
    {
        $db = $this->getDatabase();

        // Join products table
        $query->select($db->quoteName('p', 'j2commerce_products') . '.*')
            ->join('INNER', $db->quoteName('#__j2commerce_products', 'p') . ' ON ' .
                $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content') .
                ' AND ' . $db->quoteName('p.product_source_id') . ' = ' . $db->quoteName('a.id'));

        // Join master variant for price, SKU etc.
        $query->select([
                $db->quoteName('var.price'),
                $db->quoteName('var.sku'),
                $db->quoteName('var.upc'),
                $db->quoteName('var.manage_stock'),
                $db->quoteName('var.availability'),
            ])
            ->join('INNER', $db->quoteName('#__j2commerce_variants', 'var') . ' ON ' .
                $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('var.product_id'));

        // Join product images
        $query->select([
                $db->quoteName('img.thumb_image'),
                $db->quoteName('img.main_image'),
                $db->quoteName('img.additional_images'),
                $db->quoteName('img.thumb_image_alt'),
                $db->quoteName('img.main_image_alt'),
                $db->quoteName('img.additional_images_alt'),
            ])
            ->join('LEFT OUTER', $db->quoteName('#__j2commerce_productimages', 'img') . ' ON ' .
                $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('img.product_id'));

        // Join product quantities
        $query->select([
                $db->quoteName('qty.j2commerce_productquantity_id'),
                $db->quoteName('qty.quantity'),
            ])
            ->join('LEFT OUTER', $db->quoteName('#__j2commerce_productquantities', 'qty') . ' ON ' .
                $db->quoteName('qty.variant_id') . ' = ' . $db->quoteName('var.j2commerce_variant_id'));

        // Join price index for variable products
        $query->join('LEFT OUTER', $db->quoteName('#__j2commerce_productprice_index', 'ppi') . ' ON ' .
            $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('ppi.product_id'));

        // Select min/max prices with CASE for variable products
        $query->select([
            'CASE ' . $db->quoteName('p.product_type') .
            ' WHEN ' . $db->quote('variable') . ' THEN ' . $db->quoteName('ppi.min_price') .
            ' ELSE ' . $db->quoteName('var.price') . ' END AS ' . $db->quoteName('min_price'),
            'CASE ' . $db->quoteName('p.product_type') .
            ' WHEN ' . $db->quote('variable') . ' THEN ' . $db->quoteName('ppi.max_price') .
            ' ELSE ' . $db->quoteName('var.price') . ' END AS ' . $db->quoteName('max_price'),
        ]);

        // Join product filters
        $query->select($db->quoteName('pf.filter_id'))
            ->join('LEFT OUTER', $db->quoteName('#__j2commerce_product_filters', 'pf') . ' ON ' .
                $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('pf.product_id'));

        // Join manufacturer and address for brand name
        $query->join('LEFT OUTER', $db->quoteName('#__j2commerce_manufacturers', 'mfr') . ' ON ' .
                $db->quoteName('p.manufacturer_id') . ' = ' . $db->quoteName('mfr.j2commerce_manufacturer_id'))
            ->select($db->quoteName('addr.company', 'brand_name'))
            ->join('LEFT OUTER', $db->quoteName('#__j2commerce_addresses', 'addr') . ' ON ' .
                $db->quoteName('mfr.address_id') . ' = ' . $db->quoteName('addr.j2commerce_address_id'));
    }

    /**
     * Build WHERE clause for storefront query.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function buildSFWhereQuery(QueryInterface $query): void
    {
        $db    = $this->getDatabase();
        $app   = Factory::getApplication();
        $user  = $app->getIdentity();
        $state = $this->getSFFilterValues();

        // Filter by tag ID
        $tagId = $this->getState('tagid');
        if (is_numeric($tagId)) {
            $tagIdInt       = (int) $tagId;
            $includeSubtags = $this->getState('filter.subtags', false);
            $typeAlias      = 'com_content.article';

            if ($includeSubtags) {
                $levels = (int) $this->getState('filter.max_tag_levels', 1);

                // Subquery to get sub-tag IDs
                $subQuery = $db->getQuery(true)
                    ->select($db->quoteName('sub.id'))
                    ->from($db->quoteName('#__tags', 'sub'))
                    ->join('INNER', $db->quoteName('#__tags', 'this') . ' ON ' .
                        $db->quoteName('sub.lft') . ' > ' . $db->quoteName('this.lft') .
                        ' AND ' . $db->quoteName('sub.rgt') . ' < ' . $db->quoteName('this.rgt'))
                    ->where($db->quoteName('this.id') . ' = :tagIdSub')
                    ->bind(':tagIdSub', $tagIdInt, ParameterType::INTEGER);

                if ($levels >= 0) {
                    $subQuery->where($db->quoteName('sub.level') . ' <= ' .
                        $db->quoteName('this.level') . ' + :levels')
                        ->bind(':levels', $levels, ParameterType::INTEGER);
                }

                // Filter: tag_id = X OR tag_id IN (sub-tags) AND type_alias = com_content.article
                $query->where(
                    '((' . $db->quoteName('tag.tag_id') . ' = :tagId' .
                    ' AND ' . $db->quoteName('tag.type_alias') . ' = :typeAlias1)' .
                    ' OR (' . $db->quoteName('tag.tag_id') . ' IN (' . $subQuery . ')' .
                    ' AND ' . $db->quoteName('tag.type_alias') . ' = :typeAlias2))'
                )
                    ->bind(':tagId', $tagIdInt, ParameterType::INTEGER)
                    ->bind(':typeAlias1', $typeAlias)
                    ->bind(':typeAlias2', $typeAlias);
            } else {
                // Simple tag filter
                $query->where($db->quoteName('tag.tag_id') . ' = :tagId')
                    ->where($db->quoteName('tag.type_alias') . ' = :typeAlias')
                    ->bind(':tagId', $tagIdInt, ParameterType::INTEGER)
                    ->bind(':typeAlias', $typeAlias);
            }
        }

        // Access filter
        $groups = implode(',', $user->getAuthorisedViewLevels());
        $query->where($db->quoteName('a.access') . ' IN (' . $groups . ')')
            ->where($db->quoteName('c.access') . ' IN (' . $groups . ')');

        // Publish date filters
        $nullDate = $db->quote($db->getNullDate());
        $nowDate  = $db->quote(Factory::getDate()->toSql());

        $query->where('(' . $db->quoteName('a.publish_up') . ' = ' . $nullDate .
            ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate .
            ' OR ' . $db->quoteName('a.publish_up') . ' IS NULL)')
            ->where('(' . $db->quoteName('a.publish_down') . ' = ' . $nullDate .
            ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate .
            ' OR ' . $db->quoteName('a.publish_down') . ' IS NULL)');

        // Language filter
        if ($this->getState('filter.language')) {
            $langTag = $this->getState('lang_tag', Factory::getLanguage()->getTag());
            $query->where($db->quoteName('a.language') . ' IN (' .
                $db->quote($langTag) . ',' . $db->quote('*') . ')');
        }

        // Only master variants
        $query->where($db->quoteName('var.is_master') . ' = 1');

        // Search filter
        if (!empty($state->search)) {
            $search = '%' . $state->search . '%';
            $query->where(
                '(' . $db->quoteName('a.title') . ' LIKE :search1 OR ' .
                $db->quoteName('a.introtext') . ' LIKE :search2 OR ' .
                $db->quoteName('a.fulltext') . ' LIKE :search3 OR ' .
                $db->quoteName('p.j2commerce_product_id') . ' LIKE :search4 OR ' .
                $db->quoteName('p.product_source') . ' LIKE :search5 OR ' .
                $db->quoteName('var.sku') . ' LIKE :search6 OR ' .
                $db->quoteName('var.upc') . ' LIKE :search7 OR ' .
                $db->quoteName('var.price') . ' LIKE :search8 OR ' .
                $db->quoteName('p.product_type') . ' LIKE :search9)'
            )
                ->bind(':search1', $search)
                ->bind(':search2', $search)
                ->bind(':search3', $search)
                ->bind(':search4', $search)
                ->bind(':search5', $search)
                ->bind(':search6', $search)
                ->bind(':search7', $search)
                ->bind(':search8', $search)
                ->bind(':search9', $search);
        }

        // Product type filter
        if (!empty($state->product_type)) {
            $productType = '%' . $state->product_type . '%';
            $query->where($db->quoteName('p.product_type') . ' LIKE :productType')
                ->bind(':productType', $productType);
        }

        // Date range filters
        $this->addDateFilters($query, $state);

        // Manufacturer filter
        if (!empty($state->manufacturer_id)) {
            $query->where($db->quoteName('p.manufacturer_id') . ' IN (' . $state->manufacturer_id . ')');
        }

        // Vendor filter
        if (!empty($state->vendor_id)) {
            $query->where($db->quoteName('p.vendor_id') . ' IN (' . $state->vendor_id . ')');
        }

        // Tax profile filter
        if (!empty($state->taxprofile_id)) {
            $taxprofileId = (int) $state->taxprofile_id;
            $query->where($db->quoteName('p.taxprofile_id') . ' = :taxprofileId')
                ->bind(':taxprofileId', $taxprofileId, ParameterType::INTEGER);
        }

        // Visibility filter
        if (!empty($state->visible)) {
            $visibility = (int) $state->visible;
            $query->where($db->quoteName('p.visibility') . ' = :visibility')
                ->bind(':visibility', $visibility, ParameterType::INTEGER);
        }

        // Enabled filter
        if (!empty($state->enabled)) {
            $query->where($db->quoteName('p.enabled') . ' IN (' . $state->enabled . ')');
        }

        // SKU filter
        if (!empty($state->sku)) {
            $sku = '%' . $state->sku . '%';
            $query->where($db->quoteName('var.sku') . ' LIKE :sku')
                ->bind(':sku', $sku);
        }

        // Price range filter
        $this->addPriceRangeFilter($query, $state);

        // Product filter filter
        $this->addProductFilterFilter($query, $state);

        // Product types filter (multiple types)
        if (!empty($state->product_types) && \is_array($state->product_types)) {
            $query->whereIn($db->quoteName('p.product_type'), $state->product_types, ParameterType::STRING);
        }

        // Featured only filter
        if (!empty($state->show_feature_only)) {
            $featured = (int) $state->show_feature_only;
            $query->where($db->quoteName('a.featured') . ' = :featured')
                ->bind(':featured', $featured, ParameterType::INTEGER);
        }
    }

    /**
     * Add date range filters to query.
     *
     * @param   QueryInterface  $query  The query object.
     * @param   object          $state  The filter state object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function addDateFilters(QueryInterface $query, object $state): void
    {
        $db = $this->getDatabase();

        // Since date filter
        $since = trim($state->since ?? '');
        if ($this->isValidDate($since)) {
            $sinceDate = Factory::getDate($since)->toSql();
            $query->where($db->quoteName('p.created_on') . ' >= :since')
                ->bind(':since', $sinceDate);
        }

        // Until date filter
        $until = trim($state->until ?? '');
        if ($this->isValidDate($until)) {
            $untilDate = Factory::getDate($until)->toSql();
            $query->where($db->quoteName('p.created_on') . ' <= :until')
                ->bind(':until', $untilDate);
        }
    }

    /**
     * Check if a date string is valid.
     *
     * @param   string  $date  The date string to validate.
     *
     * @return  bool  True if valid, false otherwise.
     *
     * @since   6.0.6
     */
    protected function isValidDate(string $date): bool
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return false;
        }

        $regex = '/^\d{1,4}(\/|-)\d{1,2}(\/|-)\d{2,4}[[:space:]]{0,}(\d{1,2}:\d{1,2}(:\d{1,2}){0,1}){0,1}$/';

        return (bool) preg_match($regex, $date);
    }

    /**
     * Add price range filter to query.
     *
     * @param   QueryInterface  $query  The query object.
     * @param   object          $state  The filter state object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function addPriceRangeFilter(QueryInterface $query, object $state): void
    {
        if (($state->pricefrom !== null && ($state->pricefrom >= 0 || $state->pricefrom !== ''))
            && !empty($state->priceto)) {
            $db        = $this->getDatabase();
            $priceFrom = (int) $state->pricefrom;
            $priceTo   = (int) $state->priceto;

            // Subquery to find products within price range
            $subQuery = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('product_id'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('price') . ' >= :priceFromSub')
                ->where($db->quoteName('price') . ' <= :priceToSub')
                ->bind(':priceFromSub', $priceFrom, ParameterType::INTEGER)
                ->bind(':priceToSub', $priceTo, ParameterType::INTEGER);

            $query->where($db->quoteName('p.j2commerce_product_id') . ' IN (' . $subQuery . ')');
        }
    }

    /**
     * Add product filter filter to query.
     *
     * @param   QueryInterface  $query  The query object.
     * @param   object          $state  The filter state object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function addProductFilterFilter(QueryInterface $query, object $state): void
    {
        if (empty($state->productfilter_id)) {
            return;
        }

        $db      = $this->getDatabase();
        $session = Factory::getApplication()->getSession();

        $filterIds = \is_array($state->productfilter_id)
            ? $state->productfilter_id
            : (array) $state->productfilter_id;

        $filterCondition = $session->get('list_product_filter_search_logic_rel', 'OR', 'j2commerce');

        if ($filterCondition === 'AND') {
            // For AND logic, product must have ALL selected filters
            $allFilterIds = [];
            foreach ($filterIds as $ids) {
                if (!empty($ids)) {
                    $arrIds       = explode(',', $ids);
                    $allFilterIds = array_merge($arrIds, $allFilterIds);
                }
            }
            $allFilterIds = array_unique($allFilterIds);
            $countIds     = \count($allFilterIds);

            if ($countIds > 0) {
                $filterList = implode(',', array_map('intval', $allFilterIds));
                $subQuery   = $db->getQuery(true)
                    ->select($db->quoteName('product_id'))
                    ->from($db->quoteName('#__j2commerce_product_filters'))
                    ->where($db->quoteName('filter_id') . ' IN (' . $filterList . ')')
                    ->group($db->quoteName('product_id'))
                    ->having('COUNT(*) = ' . $countIds);

                $query->where($db->quoteName('pf.product_id') . ' IN (' . $subQuery . ')');
            }
        } else {
            // For OR logic, product can have ANY of the selected filters
            $filterList = implode(',', array_map('intval', $filterIds));
            $query->where($db->quoteName('pf.filter_id') . ' IN (' . $filterList . ')');
        }
    }

    /**
     * Build ORDER BY clause for storefront query.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function buildSFQueryOrderBy(QueryInterface $query): void
    {
        $db = $this->getDatabase();

        // Apply custom sort from state
        $this->buildSFSortQuery($query);

        // Get merged params for default ordering
        $params                  = $this->getMergedParams();
        $articleCategoryOrdering = (int) $params->get('consider_category', 0);
        $articleOrderby          = $params->get('orderby_sec', 'rdate');
        $articleOrderDate        = $params->get('order_date', 'created');

        $secondary = $this->getOrderbySecondary($articleOrderby, $articleOrderDate);
        $orderby   = empty($secondary) ? 'a.created' : trim($secondary);

        if ($articleCategoryOrdering) {
            $query->order($db->quoteName('category_title') . ' ' .
                $db->escape($this->getState('list.direction', 'ASC')));
        }

        $this->setState('list.ordering', $orderby);

        $query->order(
            $db->escape($this->getState('list.ordering', 'a.ordering')) . ' ' .
            $db->escape($this->getState('list.direction', 'ASC'))
        );
    }

    /**
     * Build sort query from state.
     *
     * @param   QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function buildSFSortQuery(QueryInterface $query): void
    {
        $db     = $this->getDatabase();
        $sortby = $this->getState('sortby', '');

        if (empty($sortby)) {
            return;
        }

        $sortOrder = match ($sortby) {
            'pname'      => 'product_name ASC',
            'rpname'     => 'product_name DESC',
            'min_price'  => 'min_price ASC',
            'rmin_price' => 'min_price DESC',
            'sku'        => $db->quoteName('var.sku') . ' ASC',
            'rsku'       => $db->quoteName('var.sku') . ' DESC',
            'brand'      => 'brand_name ASC',
            'rbrand'     => 'brand_name DESC',
            default      => '',
        };

        if (!empty($sortOrder)) {
            $query->order($sortOrder);
        }
    }

    /**
     * Get secondary ordering clause.
     *
     * @param   string  $orderby    The ordering type.
     * @param   string  $orderDate  The date field to use.
     *
     * @return  string  The ORDER BY clause.
     *
     * @since   6.0.6
     */
    protected function getOrderbySecondary(string $orderby, string $orderDate = 'created'): string
    {
        $queryDate = $this->getQueryDate($orderDate);

        return match ($orderby) {
            'date'    => $queryDate,
            'rdate'   => $queryDate . ' DESC',
            'alpha'   => 'a.title',
            'ralpha'  => 'a.title DESC',
            'hits'    => 'a.hits DESC',
            'rhits'   => 'a.hits',
            'order'   => 'a.ordering',
            'author'  => 'a.created_by_alias',
            'rauthor' => 'a.created_by_alias DESC',
            'front'   => 'a.featured DESC',
            default   => 'a.ordering',
        };
    }

    /**
     * Get date field query for ordering.
     *
     * @param   string  $orderDate  The date field type.
     *
     * @return  string  The date field expression.
     *
     * @since   6.0.6
     */
    protected function getQueryDate(string $orderDate): string
    {
        $db       = $this->getDatabase();
        $nullDate = $db->quote($db->getNullDate());

        return match ($orderDate) {
            'modified' => 'CASE WHEN ' . $db->quoteName('a.modified') . ' = ' . $nullDate .
                ' THEN ' . $db->quoteName('a.created') . ' ELSE ' . $db->quoteName('a.modified') . ' END',
            'published' => 'CASE WHEN ' . $db->quoteName('a.publish_up') . ' = ' . $nullDate .
                ' THEN ' . $db->quoteName('a.created') . ' ELSE ' . $db->quoteName('a.publish_up') . ' END',
            default => $db->quoteName('a.created'),
        };
    }

    /**
     * Get filter values from state.
     *
     * @return  object  Object with all filter values.
     *
     * @since   6.0.6
     */
    protected function getSFFilterValues(): object
    {
        return (object) [
            'search'            => $this->getState('search', ''),
            'product_ids'       => $this->getState('product_ids', ''),
            'product_type'      => $this->getState('product_type', ''),
            'visible'           => $this->getState('visible', ''),
            'vendor_id'         => $this->getState('vendor_id', ''),
            'manufacturer_id'   => $this->getState('manufacturer_id', ''),
            'productid_from'    => $this->getState('productid_from', 0),
            'productid_to'      => $this->getState('productid_to', 0),
            'pricefrom'         => $this->getState('pricefrom'),
            'priceto'           => $this->getState('priceto'),
            'since'             => $this->getState('since', ''),
            'until'             => $this->getState('until', ''),
            'taxprofile_id'     => $this->getState('taxprofile_id', 0),
            'enabled'           => $this->getState('enabled', ''),
            'shippingmethod'    => $this->getState('shippingmethod', 0),
            'sku'               => $this->getState('sku', ''),
            'tagid'             => $this->getState('tagid'),
            'sortby'            => $this->getState('sortby', ''),
            'instock'           => $this->getState('instock', 0),
            'productfilter_id'  => $this->getState('productfilter_id'),
            'product_types'     => $this->getState('product_types', []),
            'show_feature_only' => $this->getState('show_feature_only', 0),
        ];
    }

    /**
     * Execute storefront query and return results.
     *
     * @param   QueryInterface  $query       The query to execute.
     * @param   int             $limitstart  The starting offset.
     * @param   int             $limit       The maximum number of results.
     *
     * @return  array  Array of result objects.
     *
     * @since   6.0.6
     */
    protected function executeSFQuery(QueryInterface $query, int $limitstart = 0, int $limit = 0): array
    {
        try {
            $this->getDatabase()->setQuery($query, $limitstart, $limit);

            return $this->getDatabase()->loadObjectList() ?: [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get merged configuration and menu parameters.
     *
     * @return  Registry  The merged parameters.
     *
     * @since   6.0.6
     */
    public function getMergedParams(): Registry
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return new Registry('{}');
        }

        // Get menu params
        $aparams    = $app->getParams();
        $menuParams = new Registry('{}');

        if ($menu = $app->getMenu()->getActive()) {
            $menuParams->loadString($menu->getParams());
        }

        $mergedParams = clone $menuParams;
        $mergedParams->merge($aparams);

        $configParams = ComponentHelper::getParams('com_j2commerce');
        $configParams->merge($mergedParams);

        return $configParams;
    }

    /**
     * Get available sort fields for storefront.
     *
     * @return  array  Array of sort options.
     *
     * @since   6.0.6
     */
    public function getSortFields(): array
    {
        return [
            ''           => Text::_('COM_J2COMMERCE_PRODUCT_SORT_BY'),
            'pname'      => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_NAME_ASCENDING'),
            'rpname'     => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_NAME_DESCENDING'),
            'min_price'  => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_PRICE_ASCENDING'),
            'rmin_price' => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_PRICE_DESCENDING'),
            'sku'        => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_SKU_ASCENDING'),
            'rsku'       => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_SKU_DESCENDING'),
            'brand'      => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_BRAND_ASCENDING'),
            'rbrand'     => Text::_('COM_J2COMMERCE_PRODUCT_FILTER_SORT_BRAND_DESCENDING'),
        ];
    }

    /**
     * Get products by source type and source ID.
     *
     * @param   string      $source    The product source (e.g., 'com_content').
     * @param   int|string  $sourceId  The source ID.
     *
     * @return  array  Array of product objects.
     *
     * @since   6.0.6
     */
    public function getProductsBySource(string $source, int|string $sourceId): array
    {
        if (empty($source) || empty($sourceId)) {
            return [];
        }

        $cacheKey = $source . ':' . $sourceId;

        if (!isset(self::$sourceCache[$cacheKey])) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('product_source') . ' = :source')
                ->where($db->quoteName('product_source_id') . ' = :sourceId')
                ->bind(':source', $source)
                ->bind(':sourceId', $sourceId, ParameterType::INTEGER);

            $db->setQuery($query);
            self::$sourceCache[$cacheKey] = $db->loadObjectList() ?: [];
        }

        return self::$sourceCache[$cacheKey];
    }

    /**
     * Get manufacturers by product ID(s).
     *
     * @param   int|array|null  $productId  Product ID or array of IDs.
     *
     * @return  array  Array of manufacturer objects.
     *
     * @since   6.0.6
     */
    public function getManufacturersByProduct(int|array|null $productId = null): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('p') . '.*')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->select($db->quoteName('m') . '.*')
            ->join('LEFT', $db->quoteName('#__j2commerce_manufacturers', 'm') . ' ON ' .
                $db->quoteName('m.j2commerce_manufacturer_id') . ' = ' . $db->quoteName('p.manufacturer_id'));

        if ($productId !== null) {
            $productIds = \is_array($productId) ? $productId : [$productId];
            $productIds = array_filter($productIds, fn ($id) => $id > 0);

            if (!empty($productIds)) {
                $query->whereIn($db->quoteName('p.j2commerce_product_id'), $productIds, ParameterType::INTEGER);
            }
        }

        $query->where($db->quoteName('p.manufacturer_id') . ' = ' . $db->quoteName('m.j2commerce_manufacturer_id'))
            ->where($db->quoteName('m.enabled') . ' = 1')
            ->select($db->quoteName('a') . '.*')
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id'))
            ->group($db->quoteName('p.manufacturer_id'))
            ->order($db->quoteName('m.ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get all enabled manufacturers.
     *
     * @return  array  Array of manufacturer objects.
     *
     * @since   6.0.6
     */
    public function getManufacturers(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('m') . '.*')
            ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
            ->select($db->quoteName('a.company', 'name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id'))
            ->where($db->quoteName('m.enabled') . ' = 1')
            ->order($db->quoteName('m.ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get vendors by product ID(s).
     *
     * @param   int|array|null  $productId  Product ID or array of IDs.
     *
     * @return  array  Array of vendor objects.
     *
     * @since   6.0.6
     */
    public function getVendorsByProduct(int|array|null $productId = null): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('p') . '.*')
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->select($db->quoteName('v') . '.*')
            ->join('LEFT', $db->quoteName('#__j2commerce_vendors', 'v') . ' ON ' .
                $db->quoteName('v.j2commerce_vendor_id') . ' = ' . $db->quoteName('p.vendor_id'));

        if ($productId !== null) {
            $productIds = \is_array($productId) ? $productId : [$productId];
            $productIds = array_filter($productIds, fn ($id) => $id > 0);

            if (!empty($productIds)) {
                $query->whereIn($db->quoteName('p.j2commerce_product_id'), $productIds, ParameterType::INTEGER);
            }
        }

        $query->where($db->quoteName('p.vendor_id') . ' = ' . $db->quoteName('v.j2commerce_vendor_id'))
            ->where($db->quoteName('v.enabled') . ' = 1')
            ->select($db->quoteName('a') . '.*')
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('v.address_id'))
            ->group($db->quoteName('p.vendor_id'))
            ->order($db->quoteName('v.ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get vendors by vendor IDs.
     *
     * @param   string  $vendorIds  Comma-separated list of vendor IDs.
     *
     * @return  array  Array of vendor objects with address info.
     *
     * @since   6.0.6
     */
    public function getVendors(string $vendorIds = ''): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('a') . '.*')
            ->select($db->quoteName('v') . '.*')
            ->from($db->quoteName('#__j2commerce_vendors', 'v'))
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('v.address_id') . ' = ' . $db->quoteName('a.j2commerce_address_id'))
            ->where($db->quoteName('v.enabled') . ' = 1')
            ->order($db->quoteName('v.ordering') . ' ASC');

        if (!empty($vendorIds)) {
            $idArray = array_map('intval', explode(',', $vendorIds));
            $query->whereIn($db->quoteName('v.j2commerce_vendor_id'), $idArray, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $app = Factory::getApplication();

        // Tag ID filter
        $tagId = $app->getUserStateFromRequest($this->context . '.tagid', 'tagid', null, 'int');
        $this->setState('tagid', $tagId);

        // Include subtags
        $subtags = $app->getUserStateFromRequest($this->context . '.filter.subtags', 'filter_subtags', false, 'bool');
        $this->setState('filter.subtags', $subtags);

        // Max tag levels
        $maxLevels = $app->getUserStateFromRequest($this->context . '.filter.max_tag_levels', 'filter_max_tag_levels', 1, 'int');
        $this->setState('filter.max_tag_levels', $maxLevels);

        // Search
        $search = $app->getUserStateFromRequest($this->context . '.search', 'search', '', 'string');
        $this->setState('search', $search);

        // Product type
        $productType = $app->getUserStateFromRequest($this->context . '.product_type', 'product_type', '', 'string');
        $this->setState('product_type', $productType);

        // Visibility
        $visible = $app->getUserStateFromRequest($this->context . '.visible', 'visible', '', 'string');
        $this->setState('visible', $visible);

        // Vendor ID
        $vendorId = $app->getUserStateFromRequest($this->context . '.vendor_id', 'vendor_id', '', 'string');
        $this->setState('vendor_id', $vendorId);

        // Manufacturer ID
        $manufacturerId = $app->getUserStateFromRequest($this->context . '.manufacturer_id', 'manufacturer_id', '', 'string');
        $this->setState('manufacturer_id', $manufacturerId);

        // Price range
        $priceFrom = $app->getUserStateFromRequest($this->context . '.pricefrom', 'pricefrom', null, 'int');
        $this->setState('pricefrom', $priceFrom);

        $priceTo = $app->getUserStateFromRequest($this->context . '.priceto', 'priceto', null, 'int');
        $this->setState('priceto', $priceTo);

        // Date range
        $since = $app->getUserStateFromRequest($this->context . '.since', 'since', '', 'string');
        $this->setState('since', $since);

        $until = $app->getUserStateFromRequest($this->context . '.until', 'until', '', 'string');
        $this->setState('until', $until);

        // Tax profile
        $taxprofileId = $app->getUserStateFromRequest($this->context . '.taxprofile_id', 'taxprofile_id', 0, 'int');
        $this->setState('taxprofile_id', $taxprofileId);

        // Enabled
        $enabled = $app->getUserStateFromRequest($this->context . '.enabled', 'enabled', '', 'string');
        $this->setState('enabled', $enabled);

        // SKU
        $sku = $app->getUserStateFromRequest($this->context . '.sku', 'sku', '', 'string');
        $this->setState('sku', $sku);

        // Sort by
        $sortby = $app->getUserStateFromRequest($this->context . '.sortby', 'sortby', '', 'string');
        $this->setState('sortby', $sortby);

        // Product filter
        $productfilterId = $app->getUserStateFromRequest($this->context . '.productfilter_id', 'productfilter_id', '', 'string');
        $this->setState('productfilter_id', $productfilterId);

        // Product types array
        $productTypes = $app->getUserStateFromRequest($this->context . '.product_types', 'product_types', [], 'array');
        $this->setState('product_types', $productTypes);

        // Featured only
        $showFeatureOnly = $app->getUserStateFromRequest($this->context . '.show_feature_only', 'show_feature_only', 0, 'int');
        $this->setState('show_feature_only', $showFeatureOnly);

        // Language filter
        $langFilter = $app->getLanguageFilter();
        $this->setState('filter.language', $langFilter);
        $this->setState('lang_tag', Factory::getLanguage()->getTag());

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.6
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('tagid');
        $id .= ':' . $this->getState('filter.subtags');
        $id .= ':' . $this->getState('search');
        $id .= ':' . $this->getState('product_type');
        $id .= ':' . $this->getState('visible');
        $id .= ':' . $this->getState('vendor_id');
        $id .= ':' . $this->getState('manufacturer_id');
        $id .= ':' . $this->getState('pricefrom');
        $id .= ':' . $this->getState('priceto');
        $id .= ':' . $this->getState('taxprofile_id');
        $id .= ':' . $this->getState('enabled');
        $id .= ':' . $this->getState('sortby');
        $id .= ':' . serialize($this->getState('product_types'));

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data (for admin use).
     *
     * This is the standard ListModel method. For storefront use, see getSFQuery().
     *
     * @return  QueryInterface  The query object.
     *
     * @since   6.0.6
     */
    protected function getListQuery(): QueryInterface
    {
        // For admin usage, delegate to storefront query
        return $this->getSFQuery();
    }
}
