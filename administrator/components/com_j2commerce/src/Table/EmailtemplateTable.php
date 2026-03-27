<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Emailtemplate Table class.
 *
 * @since  6.0.0
 */
class EmailtemplateTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.emailtemplate';

        parent::__construct('#__j2commerce_emailtemplates', 'j2commerce_emailtemplate_id', $db);

        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Overloaded check function
     *
     * @return  boolean  True on success, false on failure
     *
     * @since   6.0.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Check for valid email type
        if (empty($this->email_type)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_TYPE_REQUIRED'));
            return false;
        }

        // Check for valid receiver type
        if (empty($this->receiver_type)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_RECEIVER_TYPE_REQUIRED'));
            return false;
        }

        // Check for valid subject
        if (empty($this->subject)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_SUBJECT_REQUIRED'));
            return false;
        }

        // Check for valid body content (either body or body_source_file)
        if (empty($this->body) && empty($this->body_source_file)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_BODY_REQUIRED'));
            return false;
        }

        // Trim the text fields
        $this->email_type = trim($this->email_type);
        $this->receiver_type = trim($this->receiver_type);
        $this->subject = trim($this->subject);
        $this->orderstatus_id = trim($this->orderstatus_id);
        $this->group_id = trim($this->group_id);
        $this->paymentmethod = trim($this->paymentmethod);

        // Set default values if not provided
        if (!isset($this->enabled)) {
            $this->enabled = 1;
        }

        if (!isset($this->ordering)) {
            $this->ordering = $this->getNextOrder();
        }

        // Set default language if empty
        if (empty($this->language)) {
            $this->language = '*';
        }

        // Set default body_source if empty
        if (empty($this->body_source)) {
            $this->body_source = 'editor';
        }

        // Email type validation - allows core 'transactional' and plugin-registered types
        // The EmailTypeRegistry handles validation of registered types via onJ2CommerceRegisterEmailTypes event
        if (empty($this->email_type)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_TYPE_REQUIRED'));
            return false;
        }

        // Validate email type format (alphanumeric with underscores)
        if (!preg_match('/^[a-z0-9_]+$/i', $this->email_type)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_INVALID_EMAIL_TYPE'));
            return false;
        }

        // Validate receiver type against allowed values
        $allowedReceiverTypes = ['customer', 'admin', '*'];
        if (!in_array($this->receiver_type, $allowedReceiverTypes)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_INVALID_RECEIVER_TYPE'));
            return false;
        }

        // Validate body_source against allowed values
        $allowedBodySources = ['editor', 'visual', 'file'];
        if (!in_array($this->body_source, $allowedBodySources)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_INVALID_BODY_SOURCE'));
            return false;
        }

        // If using file source, validate that file exists (if provided)
        if ($this->body_source === 'file' && !empty($this->body_source_file)) {
            $filePath = JPATH_ROOT . '/' . ltrim($this->body_source_file, '/');
            if (!file_exists($filePath)) {
                $this->setError(Text::_('COM_J2COMMERCE_ERROR_EMAILTEMPLATE_FILE_NOT_FOUND'));
                return false;
            }
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
     * @since   6.0.0
     */
    public function getNextOrder($where = '')
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
     * @param   string   $where  The WHERE clause for the reorder query.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function reorder($where = '')
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
                $query = $this->_db->getQuery(true)
                    ->update($this->_tbl)
                    ->set($this->_db->quoteName('ordering') . ' = :ordering')
                    ->where($this->_tbl_key . ' = :pk')
                    ->bind(':ordering', $order, ParameterType::INTEGER)
                    ->bind(':pk', $row[0], ParameterType::INTEGER);

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
     * @since   6.0.0
     */
    public function orderUp()
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
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :old_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':old_ordering', $this->ordering, ParameterType::INTEGER)
            ->bind(':pk', $row[$k], ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :new_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':new_ordering', $row['ordering'], ParameterType::INTEGER)
            ->bind(':pk', $this->$k, ParameterType::INTEGER);

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
     * @since   6.0.0
     */
    public function orderDown()
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
        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :old_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':old_ordering', $this->ordering, ParameterType::INTEGER)
            ->bind(':pk', $row[$k], ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $query = $this->_db->getQuery(true)
            ->update($this->_tbl)
            ->set($this->_db->quoteName('ordering') . ' = :new_ordering')
            ->where($this->_tbl_key . ' = :pk')
            ->bind(':new_ordering', $row['ordering'], ParameterType::INTEGER)
            ->bind(':pk', $this->$k, ParameterType::INTEGER);

        $this->_db->setQuery($query);
        $this->_db->execute();

        $this->ordering = $row['ordering'];

        return true;
    }
}
