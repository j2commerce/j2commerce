<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_products
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Module\Products\Site\Library;

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;

class ProductSourceJoomla implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    private $type = 'joomla';

    public function getProductIdsByTags($params)
    {
        static $cache      = [];
        static $staticData = null;

        // Initialize static data once per request
        if ($staticData === null) {
            $tz   = Factory::getApplication()->getConfig()->get('offset');
            $date = Factory::getDate('now', $tz);
            $user = Factory::getApplication()->getIdentity();

            $staticData = [
                'nowDate'    => $date->toSql(),
                'viewLevels' => $user->getAuthorisedViewLevels(),
            ];
        }

        $product_ids = [];

        $limit   = $params->get('number_of_items', 6);
        $sort_by = $params->get('sort_by', '');

        $tag_list = $params->get('tag_list', []);
        if (!empty($tag_list)) {
            // Create cache key based on all parameters that affect the query
            $cacheKey = md5(serialize([
                'tag_list'   => $tag_list,
                'limit'      => $limit,
                'sort_by'    => $sort_by,
                'viewLevels' => $staticData['viewLevels'],
                'nowDate'    => $staticData['nowDate'],
            ]));

            // Return cached result if available
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select($db->quoteName('p.j2commerce_product_id'));
            $query->from($db->quoteName('#__j2commerce_products', 'p'));
            $query->join('LEFT', $db->quoteName('#__content', 'a'), $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id'));
            $query->join('LEFT', $db->quoteName('#__contentitem_tag_map', 'tag_map'), $db->quoteName('a.id') . ' = ' . $db->quoteName('tag_map.content_item_id'));
            $query->join('LEFT', $db->quoteName('#__tags', 'tag'), $db->quoteName('tag_map.tag_id') . ' = ' . $db->quoteName('tag.id'));
            $query->whereIn($db->quoteName('tag.alias'), $tag_list);

            $query->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'));
            $query->where($db->quoteName('tag_map.type_alias') . ' = ' . $db->quote('com_content.article'));
            $query->where($db->quoteName('a.state') . ' = 1');
            $query->group($db->quoteName('p.j2commerce_product_id'));

            // Use pre-calculated static data
            $nowDate = $db->quote($staticData['nowDate']);

            $query->where('(' . $query->isNullDatetime('a.publish_up') . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')');
            $query->where('(' . $query->isNullDatetime('a.publish_down') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

            $query->whereIn($db->quoteName('a.access'), $staticData['viewLevels']);

            $this->buildSortQuery($db, $query, $sort_by);

            $db->setQuery($query, 0, $limit);

            try {
                $product_ids = $db->loadColumn();
            } catch (\RuntimeException $e) {
                $product_ids = [];
            }

            // Cache the result
            $cache[$cacheKey] = $product_ids;
        }

        return $product_ids;
    }

    /**
     * Summary of getProductIdsByIds
     *
     * @param mixed $params
     */
    public function getProductIdsByIds($params)
    {
        static $cache      = [];
        static $staticData = null;

        // Initialize static data once per request
        if ($staticData === null) {
            $tz   = Factory::getApplication()->getConfig()->get('offset');
            $date = Factory::getDate('now', $tz);
            $user = Factory::getApplication()->getIdentity();

            $staticData = [
                'nowDate'    => $date->toSql(),
                'viewLevels' => $user->getAuthorisedViewLevels(),
            ];
        }

        $product_ids = [];

        $limit = $params->get('number_of_items', 6);

        $p_ids = $params->get('product_ids', []);
        if (!\is_array($p_ids)) {
            $p_ids = explode(',', $p_ids);
        }

        if (!empty($p_ids)) {
            $sort_by = $params->get('sort_by', '');

            // Create cache key based on all parameters that affect the query
            $cacheKey = md5(serialize([
                'p_ids'      => $p_ids,
                'limit'      => $limit,
                'sort_by'    => $sort_by,
                'viewLevels' => $staticData['viewLevels'],
                'nowDate'    => $staticData['nowDate'],
            ]));

            // Return cached result if available
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);

            $query->select($db->quoteName('p.j2commerce_product_id'));
            $query->from($db->quoteName('#__j2commerce_products', 'p'));
            $query->join('LEFT', $db->quoteName('#__content', 'a'), $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id'));
            $query->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'));
            $query->whereIn($db->quoteName('p.j2commerce_product_id'), $p_ids);
            $query->where($db->quoteName('a.state') . ' = 1');

            //default to the sql formatted date
            $nowDate = $db->quote($staticData['nowDate']);

            $query->where('(' . $query->isNullDatetime('a.publish_up') . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')');
            $query->where('(' . $query->isNullDatetime('a.publish_down') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

            $query->whereIn($db->quoteName('a.access'), $staticData['viewLevels']);

            $this->buildSortQuery($db, $query, $sort_by);

            $db->setQuery($query, 0, $limit);

            try {
                $product_ids = $db->loadColumn();
            } catch (\RuntimeException $e) {
                $product_ids = [];
            }

            // Cache the result
            $cache[$cacheKey] = $product_ids;
        }

        return $product_ids;
    }

    /**
     * Summary of getProductIdsByBestSelling
     *
     * @param mixed $params
     */
    public function getProductIdsByBestSelling($params)
    {
        static $cache      = [];
        static $staticData = null;

        // Initialize static data once per request
        if ($staticData === null) {
            $tz   = Factory::getApplication()->getConfig()->get('offset');
            $date = Factory::getDate('now', $tz);
            $user = Factory::getApplication()->getIdentity();

            $staticData = [
                'nowDate'    => $date->toSql(),
                'viewLevels' => $user->getAuthorisedViewLevels(),
            ];
        }

        $product_ids = [];

        $limit   = $params->get('number_of_items', 6);
        $sort_by = $params->get('sort_by', '');

        // Create cache key based on all parameters that affect the query
        $cacheKey = md5(serialize([
            'limit'      => $limit,
            'sort_by'    => $sort_by,
            'viewLevels' => $staticData['viewLevels'],
            'nowDate'    => $staticData['nowDate'],
        ]));

        // Return cached result if available
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select('b.product_id');
        $query->from('(SELECT product_id, count( product_id ) AS bestsell_count
							FROM #__j2commerce_orderitems
							GROUP BY product_id
							ORDER BY bestsell_count DESC) as b');

        $query->join('LEFT', $db->quoteName('#__j2commerce_products', 'p'), $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('b.product_id'));
        $query->join('LEFT', $db->quoteName('#__content', 'a'), $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id'));
        $query->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'));

        $query->where($db->quoteName('a.state') . ' = 1');

        // Use pre-calculated static data
        $nowDate = $db->quote($staticData['nowDate']);

        $query->where('(' . $query->isNullDatetime('a.publish_up') . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')');
        $query->where('(' . $query->isNullDatetime('a.publish_down') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

        $query->whereIn($db->quoteName('a.access'), $staticData['viewLevels']);

        $this->buildSortQuery($db, $query, $sort_by);

        $db->setQuery($query, 0, $limit);

        try {
            $product_ids = $db->loadColumn();
        } catch (\RuntimeException $e) {
            $product_ids = [];
        }

        // Cache the result
        $cache[$cacheKey] = $product_ids;

        return $product_ids;
    }

    /**
     * Summary of getProductIdsByCategories
     *
     * @param mixed $params
     */
    public function getProductIdsByCategories($params)
    {
        static $cache      = [];
        static $staticData = null;

        // Initialize static data once per request
        if ($staticData === null) {
            $tz   = Factory::getApplication()->getConfig()->get('offset');
            $date = Factory::getDate('now', $tz);
            $user = Factory::getApplication()->getIdentity();

            $staticData = [
                'nowDate'    => $date->toSql(),
                'viewLevels' => $user->getAuthorisedViewLevels(),
            ];
        }

        $product_ids = [];

        $show_feature_only = $params->get('show_feature_only', 0);
        $limit             = $params->get('number_of_items', 6);
        $sort_by           = $params->get('sort_by', '');

        $cat_ids = $this->getCategoryArray($params);

        // Create cache key based on all parameters that affect the query
        $cacheKey = md5(serialize([
            'cat_ids'           => $cat_ids,
            'show_feature_only' => $show_feature_only,
            'limit'             => $limit,
            'sort_by'           => $sort_by,
            'viewLevels'        => $staticData['viewLevels'],
            'nowDate'           => $staticData['nowDate'],
        ]));

        // Return cached result if available
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        //$db = $this->getDatabase();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $query->select($db->quoteName('p.j2commerce_product_id'));
        $query->from($db->quoteName('#__j2commerce_products', 'p'));
        $query->join('LEFT', $db->quoteName('#__content', 'a'), $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id'));
        $query->where($db->quoteName('p.product_source') . ' = ' . $db->quote('com_content'));
        $query->where($db->quoteName('a.state') . ' = 1');
        if ($show_feature_only) {
            $query->where($db->quoteName('a.featured') . ' = 1');
        }

        if (!empty($cat_ids)) {
            $query->whereIn($db->quoteName('a.catid'), $cat_ids);
        }

        $query->where($db->quoteName('p.visibility').' = 1');
        $query->where($db->quoteName('p.enabled').' = 1');

        // Use pre-calculated static data
        $nowDate = $db->quote($staticData['nowDate']);

        $query->where('(' . $query->isNullDatetime('a.publish_up') . ' OR ' . $db->quoteName('a.publish_up') . ' <= ' . $nowDate . ')');
        $query->where('(' . $query->isNullDatetime('a.publish_down') . ' OR ' . $db->quoteName('a.publish_down') . ' >= ' . $nowDate . ')');

        $query->whereIn($db->quoteName('a.access'), $staticData['viewLevels']);

        $this->buildSortQuery($db, $query, $sort_by);

        $db->setQuery($query, 0, $limit);

        try {
            $product_ids = $db->loadColumn();
        } catch (\RuntimeException $e) {
            $product_ids = [];
        }

        // Cache the result
        $cache[$cacheKey] = $product_ids;

        return $product_ids;
    }

    /**
     * Summary of buildSortQuery
     *
     * @param mixed $db
     * @param mixed $query
     * @param mixed $sort_by
     *
     * @return void
     */
    protected function buildSortQuery($db, &$query, $sort_by = 'asc')
    {
        // Early return for simple sorting to avoid unnecessary JOINs
        switch ($sort_by) {
            case 'asc':
                $query->order($db->quoteName('p.j2commerce_product_id') . ' ASC');
                return;
            case 'desc':
                $query->order($db->quoteName('p.j2commerce_product_id') . ' DESC');
                return;
            case 'art_asc':
                $query->order($db->quoteName('a.ordering') . ' ASC');
                return;
            case 'art_desc':
                $query->order($db->quoteName('a.ordering') . ' DESC');
                return;
            case 'random':
                $query->order($query->rand());
                return;
        }

        // More complex sorting requiring additional JOINs
        if (\in_array($sort_by, ['sku', 'rsku'])) {
            $query->join('LEFT', $db->quoteName('#__j2commerce_variants', 'variants'), $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('variants.product_id'));
            $query->where($db->quoteName('variants.is_master') .' = 1');
            $query->group($db->quoteName('p.j2commerce_product_id'));

            $order = ($sort_by === 'sku') ? 'ASC' : 'DESC';
            $query->order($db->quoteName('variants.sku') . ' ' . $order);
        } elseif (\in_array($sort_by, ['min_price', 'rmin_price'])) {
            $query->join('LEFT OUTER', $db->quoteName('#__j2commerce_productprice_index', 'price_index'), $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('price_index.product_id'));
            $query->join('LEFT', $db->quoteName('#__j2commerce_variants', 'variants'), $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('variants.product_id'));
            $query->where($db->quoteName('variants.is_master') .' = 1');

            // Optimized: Use IN clause instead of LIKE for better performance
            $query->select('CASE WHEN ' . $db->quoteName('p.product_type') . ' IN (' . $db->quote('variable') . ', ' . $db->quote('variablesubscriptionproduct') . ', ' . $db->quote('advancedvariable') . ', ' . $db->quote('flexivariable') . ') THEN ' . $db->quoteName('price_index.min_price') . ' ELSE ' . $db->quoteName('variants.price') . ' END AS ' . $db->quoteName('min_price'));
            $query->group($db->quoteName('p.j2commerce_product_id'));

            $order = ($sort_by === 'min_price') ? 'ASC' : 'DESC';
            $query->order($db->quoteName('min_price') . ' ' . $order);
        }
    }

    /**
     * get the list of categories to select in params
     */
    public function getCategoryArray($params)
    {
        static $categoryCache = [];

        $categories_array   = $params->get('catids', []);
        $get_sub_categories = $params->get('include_subcategories', 0);
        $levels             = $params->get('include_subcat_level', 0);

        // Create cache key for category processing
        $cacheKey = md5(serialize([
            'catids'                => $categories_array,
            'include_subcategories' => $get_sub_categories,
            'include_subcat_level'  => $levels,
        ]));

        // Return cached result if available
        if (isset($categoryCache[$cacheKey])) {
            return $categoryCache[$cacheKey];
        }

        $array_of_category_values = array_count_values($categories_array);
        if (isset($array_of_category_values['*']) && $array_of_category_values['*'] > 0) { // '*' was selected among the list of categories
            // take all categories
            $result = [];
        } else {
            if (!empty($categories_array)) {
                $categories_ids_array = [];
                foreach ($categories_array as $category_id) {
                    $categories_ids_array[$category_id] = [$category_id];
                }
                // sub-category inclusion
                if ($get_sub_categories) {
                    $categories_object = Categories::getInstance('Content');
                    foreach ($categories_array as $category_id) {
                        $category_object = $categories_object->get($category_id); // if category unpublished, unset
                        if (isset($category_object) && $category_object->hasChildren()) {

                            $sub_categories_array = $category_object->getChildren(true); // get all levels recursively
                            foreach ($sub_categories_array as $subcategory_object) {
                                $condition = ($subcategory_object->level - $category_object->level) <= $levels;
                                if ($condition) {
                                    $categories_ids_array[$category_id][] = $subcategory_object->id;
                                }
                            }
                        }
                    }
                    $final_categories_array = [];
                    foreach ($categories_array as $category_id) {
                        $final_categories_array = array_merge($final_categories_array, $categories_ids_array[$category_id]);
                    }

                    $result = array_unique($final_categories_array);
                } else {
                    $result = $categories_array;
                }
            } else {
                $result = $categories_array;
            }
        }

        // Cache the result
        $categoryCache[$cacheKey] = $result;

        // take all categories by default
        return $result;
    }

    public function prepareProduct($module_params, &$product)
    {
        // after title, content events, product links
        if (isset($product->source, $product->source->category_title)) {
            $product->category_name = $product->source->category_title;
        }
    }

    /**
     * Method to get the category table name
     * TODO: add support for other component categories like zoo, dj catlog, easyblog, rs events, sobipro
     * */
    public function getTableData()
    {
        $table                      = new \stdClass();
        $table->category_key_field 	= 'id';
        $table->category_name_field = 'title';
        $table->category_table_name = '#__categories';
        $table->item_key_field 		   = 'id';
        $table->item_name_field 	   = 'title';
        $table->item_table_name 	   = '#__content';
        $table->item_cat_rel_field 	= 'catid';
        $table->item_cat_rel_table 	= '#__content';

        return $table;
    }
}
