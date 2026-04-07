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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

class ProducttagsModel extends ListModel
{
    protected $_context = 'com_j2commerce.producttags';

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

    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $app    = Factory::getApplication();
        $input  = $app->getInput();
        $params = $app->getParams();

        $this->setState('params', $params);

        // Tag IDs come from menu item params (request fields), not URL input
        $menu   = $app->getMenu()->getActive();
        $tagIds = [];

        if ($menu) {
            if (isset($menu->query['tag_ids'])) {
                $raw    = $menu->query['tag_ids'];
                $tagIds = \is_array($raw)
                    ? array_map('intval', $raw)
                    : [(int) $raw];
            } elseif (!empty($menu->link)) {
                parse_str(parse_url($menu->link, PHP_URL_QUERY) ?: '', $linkQuery);
                if (isset($linkQuery['tag_ids'])) {
                    $raw    = $linkQuery['tag_ids'];
                    $tagIds = \is_array($raw)
                        ? array_map('intval', $raw)
                        : [(int) $raw];
                }
            }
        }

        $tagIds = array_filter(array_unique($tagIds));
        $this->setState('filter.tag_ids', $tagIds);

        // Tag match mode: 'any' or 'all' (from request field)
        $tagMatch = 'any';
        if ($menu && isset($menu->query['tag_match'])) {
            $tagMatch = $menu->query['tag_match'] === 'all' ? 'all' : 'any';
        }
        $this->setState('filter.tag_match', $tagMatch);

        // Show only featured products
        $this->setState('filter.featured', (int) $params->get('show_feature_only', 0));

        // Ordering from menu item params
        $orderBy        = $params->get('orderby_sec', 'order');
        $orderDirection = $params->get('list_order_direction', 'ASC');
        $orderDate      = match ($params->get('order_date', 'created')) {
            'published' => 'publish_up',
            default     => $params->get('order_date', 'created'),
        };

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

        $listOrdering  = $input->get('filter_order', '', 'cmd');
        $listDirection = $input->get('filter_order_Dir', '', 'cmd');

        if (empty($listOrdering)) {
            $sortParam = $input->getString('sort', '');
            if (!empty($sortParam)) {
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

        if (empty($listOrdering)) {
            $sortby = $input->getString('sortby', '');
            if (!empty($sortby)) {
                if (preg_match('/^([a-z_.]+)\s+(ASC|DESC)$/i', $sortby, $matches)) {
                    $listOrdering  = $matches[1];
                    $listDirection = strtoupper($matches[2]);
                } elseif (\in_array($sortby, ['a.ordering', 'a.title', 'a.created', 'a.hits', 'v.price'])) {
                    $listOrdering = $sortby;
                }
            }
        }

        $listOrdering  = $listOrdering ?: $orderMapping;
        $listDirection = $listDirection ?: $orderDirection;
        $listDirection = strtoupper($listDirection) === 'DESC' ? 'DESC' : 'ASC';

        $this->setState('list.ordering', $listOrdering);
        $this->setState('list.direction', $listDirection);

        $sortbyForTemplate = $listOrdering !== 'a.ordering' ? $listOrdering . ' ' . $listDirection : $listOrdering;
        $this->setState('sortby', $sortbyForTemplate);

        // Search filter
        $search = $input->getString('filter_search', '') ?: $input->getString('search', '');
        $this->setState('filter.search', $search);
        $this->setState('search', $search);

        // Pagination
        $limit      = $params->get('page_limit', $app->get('list_limit', 20));
        $limitstart = $input->getInt('limitstart', 0);
        $this->setState('list.limit', (int) $limit);
        $this->setState('list.start', $limitstart);

        // Language filter
        $this->setState('filter.language', Multilanguage::isEnabled());
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . serialize($this->getState('filter.tag_ids', []));
        $id .= ':' . $this->getState('filter.tag_match', 'any');
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

    protected function getListQuery(): QueryInterface
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);
        $user   = $this->getCurrentUser();

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

        $query->select($db->quoteName('v.price'));
        $query->select($db->quoteName('v.sku'));
        $query->select($db->quoteName('v.upc'));

        $query->from($db->quoteName('#__j2commerce_products', 'p'));

        $query->join(
            'INNER',
            $db->quoteName('#__content', 'a'),
            $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
                . ' AND ' . $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content')
        );

        $query->join(
            'LEFT',
            $db->quoteName('#__categories', 'c'),
            $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
        );

        $query->join(
            'LEFT',
            $db->quoteName('#__j2commerce_variants', 'v'),
            $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
                . ' AND ' . $db->quoteName('v.is_master') . ' = 1'
        );

        $query->where($db->quoteName('p.enabled') . ' = 1');
        $query->where($db->quoteName('p.visibility') . ' = 1');
        $query->where($db->quoteName('a.state') . ' = 1');

        $groups = $user->getAuthorisedViewLevels();
        $query->whereIn($db->quoteName('a.access'), $groups);
        $query->whereIn($db->quoteName('c.access'), $groups);

        // Tag filtering
        $tagIds   = $this->getState('filter.tag_ids', []);
        $tagMatch = $this->getState('filter.tag_match', 'any');

        if (!empty($tagIds)) {
            $sanitizedTagIds = implode(',', array_map('intval', $tagIds));

            if ($tagMatch === 'all') {
                $tagCount = \count($tagIds);
                $query->where(
                    $db->quoteName('a.id') . ' IN ('
                    . 'SELECT ' . $db->quoteName('content_item_id')
                    . ' FROM ' . $db->quoteName('#__contentitem_tag_map')
                    . ' WHERE ' . $db->quoteName('tag_id') . ' IN (' . $sanitizedTagIds . ')'
                    . ' AND ' . $db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article')
                    . ' GROUP BY ' . $db->quoteName('content_item_id')
                    . ' HAVING COUNT(DISTINCT ' . $db->quoteName('tag_id') . ') = ' . $tagCount
                    . ')'
                );
            } else {
                $query->where(
                    $db->quoteName('a.id') . ' IN ('
                    . 'SELECT ' . $db->quoteName('content_item_id')
                    . ' FROM ' . $db->quoteName('#__contentitem_tag_map')
                    . ' WHERE ' . $db->quoteName('tag_id') . ' IN (' . $sanitizedTagIds . ')'
                    . ' AND ' . $db->quoteName('type_alias') . ' = ' . $db->quote('com_content.article')
                    . ')'
                );
            }
        }

        // Filter by featured
        if ((int) $this->getState('filter.featured', 0) === 1) {
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

        // Search filter
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

        // Filter by product filter IDs
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

        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = $this->state->get('list.direction', 'ASC');

        if ($orderCol === 'price' || $orderCol === 'v.price') {
            $orderCol = 'v.price';
        }

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function getItems(): array
    {
        $items = parent::getItems();

        if (empty($items)) {
            return [];
        }

        $hydratedItems = [];

        foreach ($items as $item) {
            $product = ProductHelper::getFullProduct(
                (int) $item->j2commerce_product_id,
                false,
                false
            );

            if ($product) {
                $product->article_ordering = $item->ordering ?? 0;
                $product->article_hits     = $item->hits ?? 0;
                $product->article_featured = $item->featured ?? 0;
                $hydratedItems[]           = $product;
            }
        }

        return $hydratedItems;
    }

    public function getParent(): ?object
    {
        return null;
    }

    public function getFilters(array $items = []): array
    {
        $filters = ProductHelper::getFilters($items, []);

        // Override price range with actual prices from tag-filtered items
        if (!empty($items)) {
            $prices = [];
            foreach ($items as $item) {
                $price = (float) ($item->pricing->price ?? $item->price ?? 0);
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
            if (!empty($prices)) {
                $filters['pricefilters'] = [
                    'min_price' => min($prices),
                    'max_price' => max($prices),
                ];
            }
        }

        return $filters;
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
