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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Coupon table class.
 *
 * @since  6.0.6
 */
class CouponTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.6
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.coupon';

        parent::__construct('#__j2commerce_coupons', 'j2commerce_coupon_id', $db);

        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array|object  $array   Named array or object
     * @param   mixed         $ignore  Optional array or list of parameters to ignore
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function bind($array, $ignore = ''): bool
    {
        $array = (array) $array;

        // Support for user_group (convert array to comma-separated string)
        if (isset($array['user_group'])) {
            if (\is_array($array['user_group'])) {
                $array['user_group'] = implode(',', $array['user_group']);
            }
        } elseif (isset($array['user_group']) && !empty($array['user_group'])) {
            if (str_contains($array['user_group'], ',')) {
                // Keep as string
            } elseif (\strlen($array['user_group']) === 0) {
                $array['user_group'] = '';
            }
        }

        // Support for brand_ids (convert array to comma-separated string)
        if (isset($array['brand_ids'])) {
            if (\is_array($array['brand_ids'])) {
                $array['brand_ids'] = implode(',', $array['brand_ids']);
            }
        } elseif (isset($array['brand_ids']) && !empty($array['brand_ids'])) {
            if (str_contains($array['brand_ids'], ',')) {
                // Keep as string
            } elseif (\strlen($array['brand_ids']) === 0) {
                $array['brand_ids'] = '';
            }
        }

        // Support for product_category (convert array to comma-separated string)
        if (isset($array['product_category'])) {
            if (\is_array($array['product_category'])) {
                $array['product_category'] = implode(',', $array['product_category']);
            }
        } elseif (isset($array['product_category']) && !empty($array['product_category'])) {
            if (str_contains($array['product_category'], ',')) {
                // Keep as string
            } elseif (\strlen($array['product_category']) === 0) {
                $array['product_category'] = '';
            }
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.6
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        // Validate coupon name
        if (empty(trim($this->coupon_name ?? ''))) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUPON_NAME')));

            return false;
        }

        // Validate coupon code
        if (empty(trim($this->coupon_code ?? ''))) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUPON_CODE')));

            return false;
        }

        // Convert coupon code to uppercase
        $this->coupon_code = strtoupper(trim($this->coupon_code));

        // Check for duplicate coupon code
        $db = $this->getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_coupon_id'))
            ->from($db->quoteName('#__j2commerce_coupons'))
            ->where($db->quoteName('coupon_code') . ' = :coupon_code')
            ->bind(':coupon_code', $this->coupon_code);

        if ($this->j2commerce_coupon_id) {
            $pk = (int) $this->j2commerce_coupon_id;
            $query->where($db->quoteName('j2commerce_coupon_id') . ' != :pk')
                ->bind(':pk', $pk, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        if ($db->loadResult()) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_COUPON_CODE_EXISTS'));

            return false;
        }

        // Set default ordering for new records
        if (!isset($this->ordering) || $this->ordering === '') {
            $this->ordering = $this->getNextOrder();
        }

        // Set default enabled state for new records
        if (!isset($this->enabled) || $this->enabled === '') {
            $this->enabled = 1;
        }

        // Set default values for required integer fields
        if (!isset($this->free_shipping) || $this->free_shipping === '') {
            $this->free_shipping = 0;
        }

        if (!isset($this->max_uses) || $this->max_uses === '') {
            $this->max_uses = 0;
        }

        if (!isset($this->max_customer_uses) || $this->max_customer_uses === '') {
            $this->max_customer_uses = 0;
        }

        if (!isset($this->logged) || $this->logged === '') {
            $this->logged = 0;
        }

        // Set default value for value field
        if (empty($this->value) || !is_numeric($this->value)) {
            $this->value = 0;
        }

        // Set default value_type
        if (empty($this->value_type)) {
            $this->value_type = 'percentage';
        }

        // Set default empty strings for text fields that cannot be null
        if (!isset($this->product_category)) {
            $this->product_category = '';
        }

        if (!isset($this->products)) {
            $this->products = '';
        }

        if (!isset($this->min_subtotal)) {
            $this->min_subtotal = '';
        }

        if (!isset($this->users)) {
            $this->users = '';
        }

        if (!isset($this->brand_ids)) {
            $this->brand_ids = '';
        }

        if (!isset($this->user_group)) {
            $this->user_group = '';
        }

        if (!isset($this->mycategory)) {
            $this->mycategory = '';
        }

        if (!isset($this->max_value)) {
            $this->max_value = '';
        }

        if (!isset($this->max_quantity)) {
            $this->max_quantity = 0;
        }

        return true;
    }

    /**
     * Method to return the next ordering value for a new record.
     *
     * @param   string  $where  Additional where clause to use for the query.
     *
     * @return  integer  The next ordering value.
     *
     * @since   6.0.6
     */
    public function getNextOrder($where = ''): int
    {
        // If there is no ordering column just return 1
        if (!property_exists($this, 'ordering')) {
            return 1;
        }

        $query = $this->_db->getQuery(true)
            ->select('MAX(ordering)')
            ->from($this->_tbl);

        if ($where) {
            $query->where($where);
        }

        $this->_db->setQuery($query);
        $max = (int) $this->_db->loadResult();

        return $max + 1;
    }

    /**
     * Method to reorder the rows.
     *
     * @param   string  $where  The WHERE clause for the reorder query.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function reorder($where = ''): bool
    {
        // If there is no ordering column do nothing.
        if (!property_exists($this, 'ordering')) {
            return false;
        }

        $query = $this->_db->getQuery(true)
            ->select($this->_tbl_key . ', ordering')
            ->from($this->_tbl)
            ->order('ordering ASC');

        if ($where) {
            $query->where($where);
        }

        $this->_db->setQuery($query);
        $rows = $this->_db->loadRowList();

        // Reorder the rows
        $order = 1;
        foreach ($rows as $row) {
            if ($row[1] >= 0) {
                // Only reorder if the ordering value is not negative
                $pk = (int) $row[0];
                $query = $this->_db->getQuery(true)
                    ->update($this->_tbl)
                    ->set($this->_db->quoteName('ordering') . ' = :ordering')
                    ->where($this->_tbl_key . ' = :pk')
                    ->bind(':ordering', $order, ParameterType::INTEGER)
                    ->bind(':pk', $pk, ParameterType::INTEGER);

                $this->_db->setQuery($query);
                $this->_db->execute();
                $order++;
            }
        }

        return true;
    }

    /**
     * Method to move a row up in the ordering.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function orderUp(): bool
    {
        $k = $this->_tbl_key;

        if (empty($this->$k)) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));

            return false;
        }

        // Get the previous row
        $query = $this->_db->getQuery(true)
            ->select('*')
            ->from($this->_tbl)
            ->where($this->_db->quoteName('ordering') . ' < :ordering')
            ->order('ordering DESC')
            ->bind(':ordering', $this->ordering, ParameterType::INTEGER);

        $this->_db->setQuery($query, 0, 1);
        $row = $this->_db->loadAssoc();

        if (!$row) {
            return true; // Already at the top
        }

        // Swap ordering values
        $pk = (int) $row[$k];
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :old_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':old_ordering', $this->ordering, ParameterType::INTEGER)
            ->bind(':pk', $pk, ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $thisPk = (int) $this->$k;
        $rowOrdering = (int) $row['ordering'];
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :new_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':new_ordering', $rowOrdering, ParameterType::INTEGER)
            ->bind(':pk', $thisPk, ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $this->ordering = $row['ordering'];

        return true;
    }

    /**
     * Method to move a row down in the ordering.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.6
     */
    public function orderDown(): bool
    {
        $k = $this->_tbl_key;

        if (empty($this->$k)) {
            $this->setError(Text::_('JLIB_DATABASE_ERROR_NO_ROWS_SELECTED'));

            return false;
        }

        // Get the next row
        $query = $this->_db->getQuery(true)
            ->select('*')
            ->from($this->_tbl)
            ->where($this->_db->quoteName('ordering') . ' > :ordering')
            ->order('ordering ASC')
            ->bind(':ordering', $this->ordering, ParameterType::INTEGER);

        $this->_db->setQuery($query, 0, 1);
        $row = $this->_db->loadAssoc();

        if (!$row) {
            return true; // Already at the bottom
        }

        // Swap ordering values
        $pk = (int) $row[$k];
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :old_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':old_ordering', $this->ordering, ParameterType::INTEGER)
            ->bind(':pk', $pk, ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $thisPk = (int) $this->$k;
        $rowOrdering = (int) $row['ordering'];
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :new_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':new_ordering', $rowOrdering, ParameterType::INTEGER)
            ->bind(':pk', $thisPk, ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $this->ordering = $row['ordering'];

        return true;
    }

    /**
     * Override store method to ensure NULL values are stored in database
     * instead of '0000-00-00 00:00:00'
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null
     *
     * @return  boolean  True on success
     */
    public function store($updateNulls = false)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (empty($this->j2commerce_coupon_id)) {
            if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
                $this->created_on = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = (int) $user->id;
            }
        }

        $this->modified_on = $date;
        $this->modified_by = (int) $user->id;

        $nullDate = $this->getDatabase()->getNullDate();

        // Convert '0000-00-00 00:00:00' to null before storing
        if (isset($this->valid_from) && ($this->valid_from === $nullDate || $this->valid_from === '0000-00-00 00:00:00' || empty($this->valid_from))) {
            $this->valid_from = null;
        }
        if (isset($this->valid_to) && ($this->valid_to === $nullDate || $this->valid_to === '0000-00-00 00:00:00' || empty($this->valid_to))) {
            $this->valid_to = null;
        }

        // Force update nulls to true so NULL values are actually stored
        return parent::store(true);
    }
}
