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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

/**
 * CartItems Model
 *
 * @since  6.0.0
 */
class CartItemsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_cartitem_id', 'a.j2commerce_cartitem_id',
                'cart_id', 'a.cart_id',
                'product_id', 'a.product_id',
                'variant_id', 'a.variant_id',
                'product_type', 'a.product_type',
                'product_qty', 'a.product_qty',
                'product_name', 'c.product_name',
                'product_options', 'a.product_options',
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
     * @since  6.0.0
     */
    protected function populateState($ordering = 'a.j2commerce_cartitem_id', $direction = 'desc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        // Cart ID filter is required for this view
        $cart_id = $this->getUserStateFromRequest($this->context . '.filter.cart_id', 'filter_cart_id', '');
        $this->setState('filter.cart_id', $cart_id);

        $product_id = $this->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', '');
        $this->setState('filter.product_id', $product_id);

        $product_type = $this->getUserStateFromRequest($this->context . '.filter.product_type', 'filter_product_type', '');
        $this->setState('filter.product_type', $product_type);

        /* $vendor_id = $this->getUserStateFromRequest($this->context . '.filter.vendor_id', 'filter_vendor_id', '');
         $this->setState('filter.vendor_id', $vendor_id);*/

        // List state information.
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since  6.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.cart_id');
        $id .= ':' . $this->getState('filter.product_id');
        $id .= ':' . $this->getState('filter.product_type');


        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since  6.0.0
     */
    protected function getListQuery(): DatabaseQuery
    {
        // Create a new query object.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required columns from the cartitems table.
        $query->select(
            $this->getState(
                'list.select',
                'a.j2commerce_cartitem_id, a.cart_id, a.product_id, a.variant_id, ' .
                'a.product_type, a.vendor_id, a.product_qty, a.product_options, ' .
                'a.cartitem_params'
            )
        );
        $query->from($db->quoteName('#__j2commerce_cartitems', 'a'));

        // Join with products table to get product information
        $query->select([
            'p.product_type AS product_type_name',
            'p.vendor_id    AS product_vendor_id',
            'c.title        AS product_name',
        ])
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_products', 'p') .
                ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('a.product_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'c') .
                ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
            );

        // Join with variants table to get variant information (shipping needed for isShippingEnabled check)
        $query->select('v.sku, v.price as variant_price, v.shipping')
            ->join('LEFT', $db->quoteName('#__j2commerce_variants', 'v') . ' ON (' . $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('a.variant_id') . ')');

        // Filter by cart ID (required)
        $cart_id = $this->getState('filter.cart_id');
        if (!empty($cart_id)) {
            $query->where($db->quoteName('a.cart_id') . ' = :cart_id')
                ->bind(':cart_id', $cart_id, ParameterType::INTEGER);
        } else {
            // If no cart_id is provided, return empty result
            $query->where('1 = 0');
        }

        // Filter by product ID
        $product_id = $this->getState('filter.product_id');
        if (!empty($product_id)) {
            $query->where($db->quoteName('a.product_id') . ' = :product_id')
                ->bind(':product_id', $product_id, ParameterType::INTEGER);
        }

        // Filter by product type
        $product_type = $this->getState('filter.product_type');
        if (!empty($product_type)) {
            $query->where($db->quoteName('a.product_type') . ' = :product_type')
                ->bind(':product_type', $product_type);
        }

        // Filter by vendor ID
        $vendor_id = $this->getState('filter.vendor_id');
        if (!empty($vendor_id)) {
            $query->where($db->quoteName('a.vendor_id') . ' = :vendor_id')
                ->bind(':vendor_id', $vendor_id, ParameterType::INTEGER);
        }

        // Filter by search in product names
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_cartitem_id') . ' = :search_id')
                    ->bind(':search_id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true)) . '%';
                $query->where(
                    '(' . $db->quoteName('c.title') . ' LIKE :search1 OR ' .
                    $db->quoteName('v.sku') . ' LIKE :search2)'
                )
                ->bind(':search1', $search)
                ->bind(':search2', $search);
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'a.j2commerce_cartitem_id');
        $orderDirn = $this->getState('list.direction', 'DESC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get an array of data items.
     *
     * @return  array  An array of data items
     *
     * @since  6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !\is_array($items)) {
            // Log the error for debugging
            $app = Factory::getApplication();
            $app->enqueueMessage('Failed to retrieve cart items from database. Please check if the j2commerce_cartitems table exists.', 'warning');
            return [];
        }

        // Process product options for each item
        foreach ($items as &$item) {
            // Decode product options from base64 encoded serialized data
            $item->decoded_options = $this->decodeProductOptions($item->product_options);
        }

        return $items;
    }

    /**
     * Decode product options from base64 encoded serialized data
     *
     * @param   string  $encoded_options  Base64 encoded serialized product options
     *
     * @return  array  Decoded product options array
     *
     * @since  6.0.0
     */
    public function decodeProductOptions($encoded_options)
    {
        if (empty($encoded_options)) {
            return [];
        }

        try {
            // First, try to decode from base64
            $decoded = base64_decode($encoded_options, true);

            if ($decoded === false) {
                // If base64 decode fails, try to treat as direct serialized data
                $decoded = $encoded_options;
            }

            // Now try to unserialize the data
            $unserialized = unserialize($decoded, ['allowed_classes' => false]);

            if ($unserialized === false) {
                // If unserialize fails, try to decode as JSON (fallback)
                $json_decoded = json_decode($decoded, true);
                return $json_decoded ?: [];
            }

            return \is_array($unserialized) ? $unserialized : [];
        } catch (\Exception $e) {
            // Log the error and return empty array
            $app = Factory::getApplication();
            $app->enqueueMessage('Error decoding product options: ' . $e->getMessage(), 'warning');
            return [];
        }
    }

    /**
     * Get cart information
     *
     * @return  object|null
     *
     * @since  6.0.0
     */
    public function getCartInfo()
    {
        $cart_id = $this->getState('filter.cart_id');
        if (empty($cart_id)) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('c.j2commerce_cart_id, c.user_id, c.session_id, c.cart_type, c.created_on')
            ->select('u.username, u.name as user_name, u.email')
            ->from($db->quoteName('#__j2commerce_carts', 'c'))
            ->join('LEFT', $db->quoteName('#__users', 'u') . ' ON c.user_id = u.id')
            ->where($db->quoteName('c.j2commerce_cart_id') . ' = :cart_id')
            ->bind(':cart_id', $cart_id, ParameterType::INTEGER);

        $db->setQuery($query);

        try {
            return $db->loadObject();
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * Get distinct product type options for filter dropdown
     *
     * @return  array
     *
     * @since  6.0.0
     */
    public function getProductTypeOptions()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('DISTINCT product_type AS value, product_type AS text')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where('product_type IS NOT NULL')
            ->where('product_type != ' . $db->quote(''))
            ->order('product_type ASC');

        $db->setQuery($query);

        try {
            $options = $db->loadObjectList();
            return $options ?: [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }



    /**
     * Method to get the filter form.
     *
     * @param   array    $data      data
     * @param   boolean  $loadData  load current data
     *
     * @return  \Joomla\CMS\Form\Form|bool  The \Joomla\CMS\Form\Form object or false on error
     *
     * @since  6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return parent::getFilterForm($data, $loadData);
    }
}
