<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Inventory Model
 *
 * @since  6.0.0
 */
class InventoryModel extends ListModel
{
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
                'product_id', 'p.j2commerce_product_id',
                'product_name', 'a.title',
                'sku', 'v.sku',
                'quantity', 'pq.quantity',
                'manage_stock', 'v.manage_stock',
                'availability', 'v.availability',
                'product_type', 'p.product_type'
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
    protected function populateState($ordering = 'p.j2commerce_product_id', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $manage_stock = $this->getUserStateFromRequest($this->context . '.filter.manage_stock', 'filter_manage_stock', '');
        $this->setState('filter.manage_stock', $manage_stock);

        $availability = $this->getUserStateFromRequest($this->context . '.filter.availability', 'filter_availability', '');
        $this->setState('filter.availability', $availability);

        $product_type = $this->getUserStateFromRequest($this->context . '.filter.product_type', 'filter_product_type', '');
        $this->setState('filter.product_type', $product_type);

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
     * @since   6.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.manage_stock');
        $id .= ':' . $this->getState('filter.availability');
        $id .= ':' . $this->getState('filter.product_type');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required columns from the main tables
        $query->select(
            $this->getState(
                'list.select',
                'p.j2commerce_product_id, ' .
                'p.product_source_id, ' .
                'p.product_type, ' .
                'p.has_options, ' .
                'a.title as product_name, ' .
                'pq.quantity, ' .
                'pq.on_hold, ' .
                'pq.sold, ' .
                'v.manage_stock, ' .
                'v.availability, ' .
                'v.j2commerce_variant_id, ' .
                'v.sku, ' .
                'pq.variant_id'
            )
        );

        // From j2commerce_products table
        $query->from($db->quoteName('#__j2commerce_products', 'p'));

        // Join with variants table to get the master variant
        $query->join('LEFT', $db->quoteName('#__j2commerce_variants', 'v') . ' ON (' .
            $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id') .
            ' AND ' . $db->quoteName('v.is_master') . ' = 1)');

        // Join with productquantities table
        $query->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'pq') . ' ON (' .
            $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id') . ')');

        // Join with content table to get product names
        $query->join('LEFT', $db->quoteName('#__content', 'a') . ' ON (' .
            $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id') . ')');

        // Filter by search in product name or ID
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                // Search by product ID
                $productId = (int) substr($search, 3);
                $query->where($db->quoteName('p.j2commerce_product_id') . ' = :productId')
                    ->bind(':productId', $productId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true)) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' .
                    $db->quoteName('p.j2commerce_product_id') . ' LIKE :searchId)')
                    ->bind(':search', $search)
                    ->bind(':searchId', $search);
            }
        }

        // Filter by manage stock
        $manageStock = $this->getState('filter.manage_stock');
        if ($manageStock !== '') {
            $query->where($db->quoteName('v.manage_stock') . ' = :manageStock')
                ->bind(':manageStock', $manageStock, ParameterType::INTEGER);
        }

        // Filter by availability
        $availability = $this->getState('filter.availability');
        if ($availability !== '') {
            $query->where($db->quoteName('v.availability') . ' = :availability')
                ->bind(':availability', $availability, ParameterType::INTEGER);
        }

        // Filter by product type
        $productType = $this->getState('filter.product_type');
        if (!empty($productType)) {
            $query->where($db->quoteName('p.product_type') . ' = :productType')
                ->bind(':productType', $productType, ParameterType::STRING);
        }

        // Only show products that have content articles
        $query->where($db->quoteName('a.title') . ' IS NOT NULL');

        // Add the list ordering clause
        $orderCol = $this->getState('list.ordering', 'p.j2commerce_product_id');
        $orderDirn = $this->getState('list.direction', 'ASC');

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
     * @since   6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !is_array($items)) {
            // Log the error for debugging
            $app = Factory::getApplication();
            $app->enqueueMessage('Failed to retrieve inventory from database. Please check if the required tables exist.', 'warning');
            return [];
        }

        // Pre-load variant data for products with has_options = 1
        if (!empty($items)) {
            foreach ($items as $item) {
                // Set default values for null fields
                $item->quantity = $item->quantity ?? 0;
                $item->manage_stock = $item->manage_stock ?? 0;
                $item->availability = $item->availability ?? 1;
                $item->has_options = $item->has_options ?? 0;

                // Add SKU field to item if not present
                if (!isset($item->sku)) {
                    $item->sku = $this->getProductSku($item->j2commerce_product_id);
                }

                // Pre-load variants for products that have options
                if ($item->has_options == 1) {
                    $item->variants = $this->getProductVariants($item->j2commerce_product_id);
                } else {
                    $item->variants = [];
                }
            }
        }

        return $items;
    }

    /**
     * Save inventory data via AJAX
     *
     * @param   int    $productId     The product ID
     * @param   int    $variantId     The variant ID
     * @param   int    $quantity      The quantity
     * @param   int    $manageStock   Manage stock flag
     * @param   int    $availability  Stock status
     *
     * @return  bool   True on success, false on failure
     *
     * @since   6.0.0
     */
    public function saveInventoryItem($productId, $variantId, $quantity, $manageStock, $availability)
    {
        try {
            $db = $this->getDatabase();

            // Update or insert productquantities record
            if ($variantId && $quantity !== null) {
                $query = $db->getQuery(true);

                // Check if productquantities record exists
                $query->select('j2commerce_productquantity_id')
                    ->from($db->quoteName('#__j2commerce_productquantities'))
                    ->where($db->quoteName('variant_id') . ' = :variantId')
                    ->bind(':variantId', $variantId, ParameterType::INTEGER);

                $db->setQuery($query);
                $existingId = $db->loadResult();

                if ($existingId) {
                    // Update existing record
                    $query = $db->getQuery(true);
                    $query->update($db->quoteName('#__j2commerce_productquantities'))
                        ->set($db->quoteName('quantity') . ' = :quantity')
                        ->where($db->quoteName('variant_id') . ' = :variantId')
                        ->bind(':quantity', $quantity, ParameterType::INTEGER)
                        ->bind(':variantId', $variantId, ParameterType::INTEGER);
                } else {
                    // Insert new record
                    $query = $db->getQuery(true);
                    $query->insert($db->quoteName('#__j2commerce_productquantities'))
                        ->columns($db->quoteName(['variant_id', 'quantity', 'on_hold', 'sold', 'product_attributes']))
                        ->values(':variantId, :quantity, 0, 0, :productAttributes')
                        ->bind(':variantId', $variantId, ParameterType::INTEGER)
                        ->bind(':quantity', $quantity, ParameterType::INTEGER)
                        ->bind(':productAttributes', '');
                }

                $db->setQuery($query);
                $db->execute();
            }

            // Update variants record for manage_stock and availability
            if ($variantId) {
                $query = $db->getQuery(true);
                $query->update($db->quoteName('#__j2commerce_variants'))
                    ->set($db->quoteName('manage_stock') . ' = :manageStock')
                    ->set($db->quoteName('availability') . ' = :availability')
                    ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                    ->bind(':manageStock', $manageStock, ParameterType::INTEGER)
                    ->bind(':availability', $availability, ParameterType::INTEGER)
                    ->bind(':variantId', $variantId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            }

            return true;
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Error saving inventory: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Save inventory data with form validation support
     *
     * @param   array  $data  Form data to save
     *
     * @return  bool   True on success, false on failure
     *
     * @since   6.0.0
     */
    public function save($data)
    {
        // Get the form for validation
        $form = $this->getForm();

        if (!$form) {
            return false;
        }

        // Validate the form data
        $validData = $form->filter($data);

        if (!$form->validate($validData)) {
            $app = Factory::getApplication();

            // Get form errors and add them to the application message queue
            foreach ($form->getErrors() as $error) {
                $app->enqueueMessage($error->getMessage(), 'error');
            }

            return false;
        }

        // Extract the required fields
        $productId = (int) ($validData['j2commerce_product_id'] ?? 0);
        $variantId = (int) ($validData['j2commerce_variant_id'] ?? 0);
        $quantity = (int) ($validData['quantity'] ?? 0);
        $manageStock = (int) ($validData['manage_stock'] ?? 0);
        $availability = (int) ($validData['availability'] ?? 1);

        // Use the existing saveInventoryItem method for the actual database operations
        return $this->saveInventoryItem($productId, $variantId, $quantity, $manageStock, $availability);
    }

    /**
     * Method to validate form data.
     *
     * @param   Form   $form  The form to validate against.
     * @param   array  $data  The data to validate.
     *
     * @return  array|false  Array of filtered data if valid, false otherwise.
     *
     * @since   6.0.0
     */
    public function validate($form, $data)
    {
        // Filter the data
        $filteredData = $form->filter($data);

        // Validate the filtered data
        if (!$form->validate($filteredData)) {
            return false;
        }

        return $filteredData;
    }

    /**
     * Method to get the record form for inventory items.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm(
            'com_j2commerce.inventory',
            'inventory_item',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   6.0.0
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $app = Factory::getApplication();
        $data = $app->getUserState('com_j2commerce.edit.inventory.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        // Ensure data is in the correct format for forms
        if ($data instanceof \stdClass) {
            $data = (array) $data;
        }

        // Convert data to Registry object for form compatibility
        if (is_array($data)) {
            $data = new Registry($data);
        }

        return $data;
    }

    /**
     * Method to get a single inventory item for form editing.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     *
     * @since   6.0.0
     */
    public function getItem($pk = null)
    {
        // Get the product/variant ID from the request if not provided
        if ($pk === null) {
            $app = Factory::getApplication();
            $pk = $app->getInput()->getInt('j2commerce_product_id', 0);

            // Alternative: get variant ID if product ID not available
            if (!$pk) {
                $pk = $app->getInput()->getInt('j2commerce_variant_id', 0);
                $useVariantId = true;
            }
        }

        if (!$pk) {
            // Return empty object for new items
            $item = new \stdClass();
            $item->j2commerce_product_id = 0;
            $item->j2commerce_variant_id = 0;
            $item->quantity = 0;
            $item->manage_stock = 0;
            $item->availability = 1;
            return $item;
        }

        // Build query to get inventory item data
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            'p.j2commerce_product_id',
            'p.product_source_id',
            'a.title as product_name',
            'pq.quantity',
            'pq.on_hold',
            'pq.sold',
            'v.manage_stock',
            'v.availability',
            'v.j2commerce_variant_id',
            'pq.variant_id'
        ]);

        $query->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join('LEFT', $db->quoteName('#__j2commerce_variants', 'v') . ' ON (' .
                $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id') .
                ' AND ' . $db->quoteName('v.is_master') . ' = 1)')
            ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'pq') . ' ON (' .
                $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id') . ')')
            ->join('LEFT', $db->quoteName('#__content', 'a') . ' ON (' .
                $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id') . ')');

        // Use variant ID or product ID based on what was provided
        if (isset($useVariantId) && $useVariantId) {
            $query->where($db->quoteName('v.j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $pk, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('p.j2commerce_product_id') . ' = :productId')
                ->bind(':productId', $pk, ParameterType::INTEGER);
        }

        try {
            $db->setQuery($query);
            $item = $db->loadObject();

            if (!$item) {
                // Return empty object if item not found
                $item = new \stdClass();
                $item->j2commerce_product_id = $pk;
                $item->j2commerce_variant_id = 0;
                $item->quantity = 0;
                $item->manage_stock = 0;
                $item->availability = 1;
            } else {
                // Ensure default values for null fields
                $item->quantity = $item->quantity ?? 0;
                $item->manage_stock = $item->manage_stock ?? 0;
                $item->availability = $item->availability ?? 1;
            }

            return $item;
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Error retrieving inventory item: ' . $e->getMessage(), 'error');
            return false;
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
     * @since   6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        // Ensure custom field path is registered
        Form::addFieldPath(JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Field');

        return parent::getFilterForm($data, $loadData);
    }

    /**
     * Get unique product types from the database for dynamic filtering
     *
     * @return  array  An array of product type options
     *
     * @since   6.0.0
     */
    public function getProductTypes()
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            // Select distinct product types that are not null/empty and have content articles
            $query->select('DISTINCT ' . $db->quoteName('p.product_type'))
                ->from($db->quoteName('#__j2commerce_products', 'p'))
                ->join('LEFT', $db->quoteName('#__content', 'a') . ' ON (' .
                    $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id') . ')')
                ->where($db->quoteName('p.product_type') . ' IS NOT NULL')
                ->where($db->quoteName('p.product_type') . ' != ' . $db->quote(''))
                ->where($db->quoteName('a.title') . ' IS NOT NULL')
                ->order($db->quoteName('p.product_type') . ' ASC');

            $db->setQuery($query);
            $productTypes = $db->loadColumn();

            // Prepare options array for the dropdown
            $options = [];

            if (!empty($productTypes)) {
                foreach ($productTypes as $productType) {
                    $options[] = [
                        'value' => htmlspecialchars(trim($productType), ENT_QUOTES, 'UTF-8'),
                        'text' => htmlspecialchars(ucfirst(trim($productType)), ENT_QUOTES, 'UTF-8')
                    ];
                }
            }

            return $options;
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Error retrieving product types: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get all variants for a product that has options (has_options = 1)
     *
     * @param   int  $productId  The product ID to get variants for
     *
     * @return  array  An array of variant objects
     *
     * @since   6.0.0
     */
    public function getProductVariants($productId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            // Select variant data with quantity information
            $query->select([
                'v.j2commerce_variant_id',
                'v.product_id',
                'v.sku',
                'v.manage_stock',
                'v.availability',
                'v.is_master',
                'pq.quantity',
                'pq.on_hold',
                'pq.sold'
            ]);

            $query->from($db->quoteName('#__j2commerce_variants', 'v'))
                ->join('LEFT', $db->quoteName('#__j2commerce_productquantities', 'pq') . ' ON (' .
                    $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id') . ')')
                ->where($db->quoteName('v.product_id') . ' = :productId')
                ->where($db->quoteName('v.is_master') . ' != 1')
                ->order($db->quoteName('v.j2commerce_variant_id') . ' ASC')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $variants = $db->loadObjectList();

            // Ensure we return an array with default values for null fields
            if (!empty($variants)) {
                foreach ($variants as $variant) {
                    $variant->quantity = $variant->quantity ?? 0;
                    $variant->manage_stock = $variant->manage_stock ?? 0;
                    $variant->availability = $variant->availability ?? 1;
                    $variant->on_hold = $variant->on_hold ?? 0;
                    $variant->sold = $variant->sold ?? 0;
                }
            }

            return $variants ?? [];
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Error retrieving product variants: ' . $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * Get the SKU for a product (from master variant)
     *
     * @param   int  $productId  The product ID to get SKU for
     *
     * @return  string  The product SKU
     *
     * @since   6.0.0
     */
    public function getProductSku($productId)
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            // Get SKU from master variant
            $query->select($db->quoteName('sku'))
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('product_id') . ' = :productId')
                ->where($db->quoteName('is_master') . ' = 1')
                ->bind(':productId', $productId, ParameterType::INTEGER);

            $db->setQuery($query);
            $sku = $db->loadResult();

            return $sku ?? '';
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Error retrieving product SKU: ' . $e->getMessage(), 'error');
            return '';
        }
    }

    /**
     * Method to get variant form for rendering variant fields
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getVariantForm($data = [], $loadData = true)
    {
        // Get the form from the variants model
        $form = $this->loadForm(
            'com_j2commerce.variant',
            'variant_item',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    public function getIsEmptyState(): bool
    {
        $filters = $this->getActiveFilters();

        return empty($filters);
    }
}
