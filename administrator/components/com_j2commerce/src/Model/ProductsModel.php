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

use J2Commerce\Component\J2commerce\Site\Helper\ProductFilterRequestHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Products list model class.
 *
 * @since  6.0.3
 */
class ProductsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.3
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_product_id', 'a.j2commerce_product_id',
                'product_source_id', 'a.product_source_id',
                'product_type', 'a.product_type',
                'visibility', 'a.visibility',
                'enabled', 'a.enabled',
                'state', 'c.state',
                'manufacturer_id', 'a.manufacturer_id',
                'vendor_id', 'a.vendor_id',
                'taxprofile_id', 'a.taxprofile_id',
                'product_name', 'c.title',
                'sku', 'v.sku',
                'price', 'v.price',
                'shipping_state',
                'quantity', 'q.quantity',
                'category_title', 'cat.title',
                'catid', 'c.catid',
                'filter_ids',
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
     * @since   6.0.3
     */
    protected function populateState($ordering = 'a.j2commerce_product_id', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $state = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string');
        $this->setState('filter.state', $state);

        $productType = $this->getUserStateFromRequest($this->context . '.filter.product_type', 'filter_product_type', '', 'string');
        $this->setState('filter.product_type', $productType);

        $manufacturer = $this->getUserStateFromRequest($this->context . '.filter.manufacturer_id', 'filter_manufacturer_id', '', 'int');
        $this->setState('filter.manufacturer_id', $manufacturer);

        $vendor = $this->getUserStateFromRequest($this->context . '.filter.vendor_id', 'filter_vendor_id', '', 'int');
        $this->setState('filter.vendor_id', $vendor);

        $taxprofile = $this->getUserStateFromRequest($this->context . '.filter.taxprofile_id', 'filter_taxprofile_id', '', 'int');
        $this->setState('filter.taxprofile_id', $taxprofile);

        $visibility = $this->getUserStateFromRequest($this->context . '.filter.visibility', 'filter_visibility', '', 'string');
        $this->setState('filter.visibility', $visibility);

        $shippingState = $this->getUserStateFromRequest($this->context . '.filter.shipping_state', 'filter_shipping_state', '', 'string');
        $this->setState('filter.shipping_state', $shippingState);

        $categoryId = $this->getUserStateFromRequest($this->context . '.filter.category_id', 'filter_category_id', '', 'int');
        $this->setState('filter.category_id', $categoryId);

        $dateFrom = $this->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string');
        $this->setState('filter.date_from', $dateFrom);

        $dateTo = $this->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string');
        $this->setState('filter.date_to', $dateTo);

        $productIdFrom = $this->getUserStateFromRequest($this->context . '.filter.product_id_from', 'filter_product_id_from', '', 'int');
        $this->setState('filter.product_id_from', $productIdFrom);

        $productIdTo = $this->getUserStateFromRequest($this->context . '.filter.product_id_to', 'filter_product_id_to', '', 'int');
        $this->setState('filter.product_id_to', $productIdTo);

        $priceFrom = $this->getUserStateFromRequest($this->context . '.filter.price_from', 'filter_price_from', '', 'float');
        $this->setState('filter.price_from', $priceFrom);

        $priceTo = $this->getUserStateFromRequest($this->context . '.filter.price_to', 'filter_price_to', '', 'float');
        $this->setState('filter.price_to', $priceTo);

        $this->setState('filter.filter_ids', $this->filterIdsFromRequest());

        parent::populateState($ordering, $direction);
    }

    /**
     * The selected filter values, kept in user state the way every other filter on this screen is.
     *
     * Not getUserStateFromRequest(): a multi-select posts nothing once its last option is
     * deselected, which is exactly what Clear does, so that helper reads the absent key as "this
     * request did not carry the field" and hands back the previous selection — the filter would
     * survive its own Clear. The search-tools `filter` array is present on every submit of the
     * bar, so its presence is the signal that the selection was stated, empty or not.
     *
     * @return  int[]
     *
     * @since   6.5.1
     */
    private function filterIdsFromRequest(): array
    {
        $app     = Factory::getApplication();
        $key     = $this->context . '.filter.filter_ids';
        $filters = $app->getInput()->get('filter', null, 'array');

        if (!\is_array($filters)) {
            return array_map('intval', (array) $app->getUserState($key, []));
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($filters['filter_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids !== array_map('intval', (array) $app->getUserState($key, []))) {
            $app->getInput()->set('limitstart', 0);
        }

        $app->setUserState($key, $ids);

        return $ids;
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.3
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.product_type');
        $id .= ':' . $this->getState('filter.manufacturer_id');
        $id .= ':' . $this->getState('filter.vendor_id');
        $id .= ':' . $this->getState('filter.taxprofile_id');
        $id .= ':' . $this->getState('filter.visibility');
        $id .= ':' . $this->getState('filter.shipping_state');
        $id .= ':' . $this->getState('filter.category_id');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');
        $id .= ':' . $this->getState('filter.product_id_from');
        $id .= ':' . $this->getState('filter.product_id_to');
        $id .= ':' . $this->getState('filter.price_from');
        $id .= ':' . $this->getState('filter.price_to');
        $id .= ':' . implode(',', (array) $this->getState('filter.filter_ids', []));

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\QueryInterface
     *
     * @since   6.0.3
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select fields from the products table
        $query->select($db->quoteName([
            'a.j2commerce_product_id',
            'a.product_source',
            'a.product_source_id',
            'a.product_type',
            'a.visibility',
            'a.enabled',
            'a.taxprofile_id',
            'a.manufacturer_id',
            'a.vendor_id',
            'a.has_options',
            'a.created_on',
            'a.modified_on',
        ]))
            ->from($db->quoteName('#__j2commerce_products', 'a'));

        // Add joins
        $this->addQueryJoins($query);

        // Add filters
        $this->addQueryFilters($query);

        // Add list ordering clause
        $orderCol = $this->state->get('list.ordering', 'a.j2commerce_product_id');
        $orderDir = $this->state->get('list.direction', 'DESC');

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Add JOIN clauses to the query.
     *
     * @param   \Joomla\Database\QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function addQueryJoins($query): void
    {
        $db = $this->getDatabase();

        // Join to content table to get article title (product name), article state, and checkout info
        $query->select([
                $db->quoteName('c.title', 'product_name'),
                $db->quoteName('c.state', 'article_state'),
                $db->quoteName('c.checked_out'),
                $db->quoteName('c.checked_out_time'),
            ])
            ->join('LEFT', $db->quoteName('#__content', 'c') . ' ON ' .
                $db->quoteName('c.id') . ' = ' . $db->quoteName('a.product_source_id'));

        // Join to categories table via content.catid for category name
        $query->select([
                $db->quoteName('cat.title', 'category_title'),
                $db->quoteName('cat.id', 'catid'),
            ])
            ->join('LEFT', $db->quoteName('#__categories', 'cat') . ' ON ' .
                $db->quoteName('cat.id') . ' = ' . $db->quoteName('c.catid'));

        // Join to users table to get editor name for checked out articles
        $query->select($db->quoteName('uc.name', 'editor'))
            ->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON ' .
                $db->quoteName('uc.id') . ' = ' . $db->quoteName('c.checked_out'));

        // Join to product images for thumbnail (subquery picks one image per product)
        $imgSubQuery = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('j2commerce_productimage_id') . ')')
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where($db->quoteName('product_id') . ' = ' . $db->quoteName('a.j2commerce_product_id'));

        $query->select($db->quoteName('i.thumb_image'))
            ->join('LEFT', $db->quoteName('#__j2commerce_productimages', 'i') . ' ON ' .
                $db->quoteName('i.j2commerce_productimage_id') . ' = (' . $imgSubQuery . ')');

        // Join to tax profiles for tax profile name
        $query->select($db->quoteName('tp.taxprofile_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_taxprofiles', 'tp') . ' ON ' .
                $db->quoteName('tp.j2commerce_taxprofile_id') . ' = ' . $db->quoteName('a.taxprofile_id'));

        // Join to master variant for SKU, price, shipping (subquery picks one variant per product)
        $variantSubQuery = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('j2commerce_variant_id') . ')')
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = ' . $db->quoteName('a.j2commerce_product_id'))
            ->where($db->quoteName('is_master') . ' = 1');

        $query->select($db->quoteName([
                'v.sku',
                'v.price',
                'v.shipping',
                'v.j2commerce_variant_id',
            ]))
            ->join('LEFT', $db->quoteName('#__j2commerce_variants', 'v') . ' ON ' .
                $db->quoteName('v.j2commerce_variant_id') . ' = (' . $variantSubQuery . ')');

        // Aggregate the shipping flag over the sellable variant rows, so the column answers for
        // what a shopper can actually buy rather than for the master placeholder above
        $shippingSubQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('product_id'),
                'MIN(' . $db->quoteName('shipping') . ') AS ' . $db->quoteName('shipping_min'),
                'MAX(' . $db->quoteName('shipping') . ') AS ' . $db->quoteName('shipping_max'),
            ])
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('is_master') . ' = 0')
            ->group($db->quoteName('product_id'));

        $query->select('(' . $this->shippingStateExpression() . ') AS ' . $db->quoteName('shipping_state'))
            ->join('LEFT', '(' . $shippingSubQuery . ') AS ' . $db->quoteName('vs') . ' ON ' .
                $db->quoteName('vs.product_id') . ' = ' . $db->quoteName('a.j2commerce_product_id'));

        // Join to product quantities for stock level
        $query->select($db->quoteName('q.quantity'))
            ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'q') . ' ON ' .
                $db->quoteName('q.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id'));

        // Join to manufacturer and address for manufacturer name
        $query->select($db->quoteName('ma.company', 'manufacturer_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_manufacturers', 'm') . ' ON ' .
                $db->quoteName('m.j2commerce_manufacturer_id') . ' = ' . $db->quoteName('a.manufacturer_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'ma') . ' ON ' .
                $db->quoteName('ma.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id'));

        // Join to vendor and address for the vendor name
        $query->select($db->quoteName('va.company', 'vendor_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_vendors', 'ven') . ' ON ' .
                $db->quoteName('ven.j2commerce_vendor_id') . ' = ' . $db->quoteName('a.vendor_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'va') . ' ON ' .
                $db->quoteName('va.j2commerce_address_id') . ' = ' . $db->quoteName('ven.address_id'));
    }

    /**
     * 2 = every sellable variant ships, 1 = only some do, 0 = none do. Variant products hold the
     * flag on their non-master rows; every other product type holds it on the master row and has
     * no non-master rows at all, so the aggregate falls back to that row rather than reading NULL.
     *
     * @since   6.5.1
     */
    private function shippingStateExpression(): string
    {
        $db = $this->getDatabase();

        return 'CASE WHEN COALESCE(' . $db->quoteName('vs.shipping_min') . ', ' . $db->quoteName('v.shipping') . ', 0) > 0 THEN 2'
            . ' WHEN COALESCE(' . $db->quoteName('vs.shipping_max') . ', ' . $db->quoteName('v.shipping') . ', 0) > 0 THEN 1'
            . ' ELSE 0 END';
    }

    /**
     * Add WHERE clauses to the query.
     *
     * @param   \Joomla\Database\QueryInterface  $query  The query object.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    protected function addQueryFilters($query): void
    {
        $db = $this->getDatabase();

        // Filter by published state (matches the Joomla content state shown in the Status column)
        $state = $this->getState('filter.state');
        if (is_numeric($state)) {
            $stateInt = (int) $state;
            $query->where($db->quoteName('c.state') . ' = :state')
                ->bind(':state', $stateInt, ParameterType::INTEGER);
        }

        // Filter by product type
        $productType = $this->getState('filter.product_type');
        if (!empty($productType)) {
            $query->where($db->quoteName('a.product_type') . ' = :productType')
                ->bind(':productType', $productType);
        }

        // Filter by manufacturer
        $manufacturerId = $this->getState('filter.manufacturer_id');
        if (!empty($manufacturerId)) {
            $manufacturerIdInt = (int) $manufacturerId;
            $query->where($db->quoteName('a.manufacturer_id') . ' = :manufacturerId')
                ->bind(':manufacturerId', $manufacturerIdInt, ParameterType::INTEGER);
        }

        // Filter by vendor
        $vendorId = $this->getState('filter.vendor_id');
        if (!empty($vendorId)) {
            $vendorIdInt = (int) $vendorId;
            $query->where($db->quoteName('a.vendor_id') . ' = :vendorId')
                ->bind(':vendorId', $vendorIdInt, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_product_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } elseif (stripos($search, 'sku:') === 0) {
                $searchSku = trim(substr($search, 4));
                $query->where($db->quoteName('v.sku') . ' = :searchSku')
                    ->bind(':searchSku', $searchSku);
            } else {
                $search = '%' . trim($search) . '%';
                $query->where('(' . $db->quoteName('c.title') . ' LIKE :search OR ' .
                    $db->quoteName('v.sku') . ' LIKE :search2)')
                    ->bind(':search', $search)
                    ->bind(':search2', $search);
            }
        }

        // Filter by tax profile
        $taxprofileId = $this->getState('filter.taxprofile_id');
        if (!empty($taxprofileId)) {
            $taxprofileIdInt = (int) $taxprofileId;
            $query->where($db->quoteName('a.taxprofile_id') . ' = :taxprofileId')
                ->bind(':taxprofileId', $taxprofileIdInt, ParameterType::INTEGER);
        }

        // Filter by category
        $categoryId = $this->getState('filter.category_id');
        if (!empty($categoryId)) {
            $categoryIdInt = (int) $categoryId;
            $query->where($db->quoteName('c.catid') . ' = :categoryId')
                ->bind(':categoryId', $categoryIdInt, ParameterType::INTEGER);
        }

        // Filter by visibility
        $visibility = $this->getState('filter.visibility');
        if ($visibility !== '' && $visibility !== null) {
            $visibilityInt = (int) $visibility;
            $query->where($db->quoteName('a.visibility') . ' = :visibility')
                ->bind(':visibility', $visibilityInt, ParameterType::INTEGER);
        }

        // Filter by the aggregated shipping state (repeats the expression rather than naming the
        // select alias, which WHERE cannot see)
        $shippingState = $this->getState('filter.shipping_state');
        if ($shippingState !== '' && $shippingState !== null) {
            $shippingStateInt = (int) $shippingState;
            $query->where('(' . $this->shippingStateExpression() . ') = :shippingState')
                ->bind(':shippingState', $shippingStateInt, ParameterType::INTEGER);
        }

        // Filter by date range
        $dateFrom = $this->getState('filter.date_from');
        if (!empty($dateFrom)) {
            $query->where($db->quoteName('a.created_on') . ' >= :dateFrom')
                ->bind(':dateFrom', $dateFrom);
        }

        $dateTo = $this->getState('filter.date_to');
        if (!empty($dateTo)) {
            $dateTo .= ' 23:59:59';
            $query->where($db->quoteName('a.created_on') . ' <= :dateTo')
                ->bind(':dateTo', $dateTo);
        }

        // Filter by product ID range
        $productIdFrom = $this->getState('filter.product_id_from');
        if (!empty($productIdFrom)) {
            $productIdFromInt = (int) $productIdFrom;
            $query->where($db->quoteName('a.j2commerce_product_id') . ' >= :productIdFrom')
                ->bind(':productIdFrom', $productIdFromInt, ParameterType::INTEGER);
        }

        $productIdTo = $this->getState('filter.product_id_to');
        if (!empty($productIdTo)) {
            $productIdToInt = (int) $productIdTo;
            $query->where($db->quoteName('a.j2commerce_product_id') . ' <= :productIdTo')
                ->bind(':productIdTo', $productIdToInt, ParameterType::INTEGER);
        }

        // Filter by price range
        $priceFrom = $this->getState('filter.price_from');
        if (!empty($priceFrom) || $priceFrom === '0' || $priceFrom === 0.0) {
            $priceFromFloat = (float) $priceFrom;
            $query->where($db->quoteName('v.price') . ' >= :priceFrom')
                ->bind(':priceFrom', $priceFromFloat);
        }

        $priceTo = $this->getState('filter.price_to');
        if (!empty($priceTo)) {
            $priceToFloat = (float) $priceTo;
            $query->where($db->quoteName('v.price') . ' <= :priceTo')
                ->bind(':priceTo', $priceToFloat);
        }

        // Filter by the values the product is tagged with, through the storefront's own matching
        // rule so both surfaces answer the same question about the same catalogue: OR within a
        // group, AND between groups. The within-group side is pinned to OR rather than read from
        // list_product_filter_search_logic_rel — that setting belongs to a storefront listing, and
        // this screen has no menu item behind it to carry one.
        $filterIds = (array) $this->getState('filter.filter_ids', []);

        if ($filterIds !== []) {
            ProductFilterRequestHelper::applyToQuery(
                $query,
                $db,
                ProductFilterRequestHelper::groupSelectedIds($db, $filterIds),
                new Registry(['list_product_filter_search_logic_rel' => 'OR']),
                0,
                'a'
            );
        }
    }
}
