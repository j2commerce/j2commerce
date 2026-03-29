<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Variant table class.
 *
 * Handles database operations for product variants including master and child variants
 * for flexivariable products.
 *
 * @since  6.0.0
 */
class VariantTable extends Table
{
    /**
     * An array of key names to be JSON encoded in the bind method.
     *
     * @var    array
     * @since  6.0.0
     */
    protected $_jsonEncode = ['params'];

    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_variants', 'j2commerce_variant_id', $db);
    }

    /**
     * Overloaded check method to ensure data integrity.
     *
     * Sets all variant fields with proper defaults matching J2Store behavior:
     * - Empty strings for varchar fields (upc, params)
     * - "0.00000" for decimal fields (price, dimensions, qty limits)
     * - Default weight/length class IDs from config
     * - 0 for integer boolean fields (manage_stock, use_store_config_*, availability)
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // === Integer boolean fields - default to 0 ===
        if (!isset($this->is_master) || $this->is_master === null) {
            $this->is_master = 0;
        }

        if (!isset($this->shipping) || $this->shipping === null) {
            $this->shipping = 1;
        }

        if (!isset($this->manage_stock) || $this->manage_stock === null) {
            $this->manage_stock = 0;
        }

        if (!isset($this->quantity_restriction) || $this->quantity_restriction === null) {
            $this->quantity_restriction = 0;
        }

        if (!isset($this->use_store_config_min_sale_qty) || $this->use_store_config_min_sale_qty === null) {
            $this->use_store_config_min_sale_qty = 0;
        }

        if (!isset($this->use_store_config_max_sale_qty) || $this->use_store_config_max_sale_qty === null) {
            $this->use_store_config_max_sale_qty = 0;
        }

        if (!isset($this->use_store_config_notify_qty) || $this->use_store_config_notify_qty === null) {
            $this->use_store_config_notify_qty = 0;
        }

        if (!isset($this->availability) || $this->availability === null) {
            $this->availability = 0;
        }

        if (!isset($this->allow_backorder) || $this->allow_backorder === null) {
            $this->allow_backorder = 0;
        }

        if (!isset($this->isdefault_variant) || $this->isdefault_variant === null) {
            $this->isdefault_variant = 0;
        }

        // === Varchar fields - default to empty strings (not null) ===
        if (!isset($this->sku) || $this->sku === null) {
            $this->sku = '';
        }

        if (!isset($this->upc) || $this->upc === null) {
            $this->upc = '';
        }

        if (empty($this->pricing_calculator)) {
            $this->pricing_calculator = 'standard';
        }

        // Params should default to variant_main_image structure (not null)
        if (!isset($this->params) || $this->params === null || $this->params === '') {
            $this->params = '{"variant_main_image":""}';
        }

        // === Decimal fields - default to "0.00000" (not null) ===
        if (!isset($this->price) || $this->price === null || $this->price === '') {
            $this->price = '0.00000';
        }

        if (!isset($this->length) || $this->length === null || $this->length === '') {
            $this->length = '0.00000';
        }

        if (!isset($this->width) || $this->width === null || $this->width === '') {
            $this->width = '0.00000';
        }

        if (!isset($this->height) || $this->height === null || $this->height === '') {
            $this->height = '0.00000';
        }

        if (!isset($this->weight) || $this->weight === null || $this->weight === '') {
            $this->weight = '0.00000';
        }

        if (!isset($this->min_sale_qty) || $this->min_sale_qty === null || $this->min_sale_qty === '') {
            $this->min_sale_qty = '0.00000';
        }

        if (!isset($this->max_sale_qty) || $this->max_sale_qty === null || $this->max_sale_qty === '') {
            $this->max_sale_qty = '0.00000';
        }

        if (!isset($this->notify_qty) || $this->notify_qty === null || $this->notify_qty === '') {
            $this->notify_qty = '0.00000';
        }

        // === Length and Weight class IDs from config defaults ===
        if (!isset($this->length_class_id) || $this->length_class_id === null || (int) $this->length_class_id === 0) {
            $this->length_class_id = ConfigHelper::getDefaultLengthClassId();
        }

        if (!isset($this->weight_class_id) || $this->weight_class_id === null || (int) $this->weight_class_id === 0) {
            $this->weight_class_id = ConfigHelper::getDefaultWeightClassId();
        }

        return true;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function store($updateNulls = true): bool
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        // Set created date/user for new records
        if (empty($this->j2commerce_variant_id)) {
            if (empty($this->created_on)) {
                $this->created_on = $date;
            }
            if (empty($this->created_by)) {
                $this->created_by = $user->id;
            }
        }

        // Always update modified date/user
        $this->modified_on = $date;
        $this->modified_by = $user->id;

        return parent::store($updateNulls);
    }

    /**
     * Delete a variant and cascade to child tables.
     *
     * @param   integer  $pk  An optional primary key value to delete.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function delete($pk = null): bool
    {
        $k = $this->_tbl_key;
        $pk = (is_null($pk)) ? $this->$k : $pk;

        // Load the record to verify it exists
        if (!$this->load($pk)) {
            return false;
        }

        // Delete child records first
        if (!$this->deleteChildRecords((int) $pk)) {
            return false;
        }

        // Delete the main variant record
        return parent::delete($pk);
    }

    /**
     * Delete child records associated with a variant.
     *
     * @param   integer  $variantId  The variant ID.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    protected function deleteChildRecords(int $variantId): bool
    {
        $db = $this->getDatabase();

        // Child tables to cascade delete
        $childTables = [
            '#__j2commerce_productquantities' => 'variant_id',
            '#__j2commerce_product_variant_optionvalues' => 'variant_id',
        ];

        foreach ($childTables as $table => $column) {
            try {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName($column) . ' = :variantId')
                    ->bind(':variantId', $variantId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Bind data to the table.
     *
     * Override to handle object/array conversion for complex data types.
     *
     * @param   array|object  $src     An associative array or object to bind to the Table instance.
     * @param   array|string  $ignore  An optional array or space separated list of properties to ignore while binding.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function bind($src, $ignore = []): bool
    {
        // Convert object to array if needed
        if (\is_object($src)) {
            $src = (array) $src;
        }

        // Handle params JSON encoding
        if (isset($src['params']) && \is_array($src['params'])) {
            $src['params'] = json_encode($src['params']);
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Save a variant record.
     *
     * @param   array|object  $src        The data to save.
     * @param   string        $orderingFilter  Filter for the ordering column.
     * @param   array|string  $ignore     Properties to ignore.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function save($src, $orderingFilter = '', $ignore = ''): bool
    {
        // Convert object to array
        if (\is_object($src)) {
            $src = (array) $src;
        }

        // Check if we're updating an existing record
        if (!empty($src['j2commerce_variant_id'])) {
            $this->load($src['j2commerce_variant_id']);
        }

        // Bind the data
        if (!$this->bind($src, $ignore)) {
            return false;
        }

        // Check the data
        if (!$this->check()) {
            return false;
        }

        // Store the record
        if (!$this->store()) {
            return false;
        }

        return true;
    }
}
