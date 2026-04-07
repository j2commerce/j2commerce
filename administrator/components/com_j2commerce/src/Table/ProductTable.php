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

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Product table class.
 *
 * @since  6.0.3
 */
class ProductTable extends Table
{
    /**
     * An array of key names to be JSON encoded in the bind method.
     *
     * @var    array
     * @since  6.0.3
     */
    protected $_jsonEncode = ['params', 'plugins'];

    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.3
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_products', 'j2commerce_product_id', $db);

        // Map Joomla's 'published' to J2Commerce's 'enabled' column
        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Overloaded check method to ensure data integrity.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.3
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Set default visibility for new records (not using empty() — 0 is a valid value)
        if (!isset($this->visibility) || $this->visibility === '') {
            $this->visibility = 1;
        }

        // Set the default enabled state for new records
        if (!isset($this->enabled)) {
            $this->enabled = 1;
        }

        // Set default product_source if not provided
        if (empty($this->product_source)) {
            $this->product_source = 'com_content';
        }

        // Set default addtocart_text if not provided
        if (empty($this->addtocart_text)) {
            $this->addtocart_text = '';
        }

        // Set default empty strings for required varchar fields
        if (empty($this->up_sells)) {
            $this->up_sells = '';
        }

        if (empty($this->cross_sells)) {
            $this->cross_sells = '';
        }

        if (empty($this->productfilter_ids)) {
            $this->productfilter_ids = '';
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
     * @since   6.0.3
     */
    public function store($updateNulls = true): bool
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        // Set created date/user for new records
        if (empty($this->j2commerce_product_id)) {
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
     * Delete a product and cascade to child tables.
     *
     * @param   integer  $pk  An optional primary key value to delete.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.3
     */
    public function delete($pk = null): bool
    {
        $k  = $this->_tbl_key;
        $pk = (\is_null($pk)) ? $this->$k : $pk;

        // Load the record to verify it exists
        if (!$this->load($pk)) {
            return false;
        }

        // Delete child records first
        if (!$this->deleteChildRecords((int) $pk)) {
            return false;
        }

        // Delete the main product record
        return parent::delete($pk);
    }

    /**
     * Delete child records associated with a product.
     *
     * @param   integer  $productId  The product ID.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.3
     */
    protected function deleteChildRecords(int $productId): bool
    {
        $db = $this->getDatabase();

        // Child tables to cascade delete
        $childTables = [
            '#__j2commerce_variants'        => 'product_id',
            '#__j2commerce_product_options' => 'product_id',
            '#__j2commerce_productimages'   => 'product_id',
            '#__j2commerce_productfiles'    => 'product_id',
            '#__j2commerce_product_filters' => 'product_id',
        ];

        foreach ($childTables as $table => $column) {
            try {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName($column) . ' = :productId')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {
                $this->setError($e->getMessage());
                return false;
            }
        }

        return true;
    }
}
