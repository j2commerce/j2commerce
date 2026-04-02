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

/**
 * Customfield table class.
 *
 * @since  6.0.4
 */
class CustomfieldTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.4
     */
    public function __construct(DatabaseDriver $db)
    {
        // IMPORTANT: The primary key here ('j2commerce_customfield_id') MUST match the $key property in CustomfieldController for edit/save to work correctly
        parent::__construct('#__j2commerce_customfields', 'j2commerce_customfield_id', $db);

        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.4
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Validate field_name
        if (empty($this->field_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_FIELD_NAME')));
            return false;
        }

        // Auto-generate field_namekey from field_name if empty
        if (empty($this->field_namekey) && !empty($this->field_name)) {
            $this->field_namekey = preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', strtolower(trim($this->field_name))));
        }

        if (empty($this->field_namekey)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_FIELD_NAMEKEY')));
            return false;
        }

        // Set default ordering for new records
        if (empty($this->ordering)) {
            $this->ordering = 0;
        }

        // Set default enabled state for new records
        if (!isset($this->enabled) || $this->enabled === '') {
            $this->enabled = 1;
        }

        // Set default for field_value (NOT NULL column) - stores dropdown/radio/checkbox options
        if (!isset($this->field_value) || $this->field_value === null) {
            $this->field_value = '';
        }

        // Set default for field_display (NOT NULL column)
        if (!isset($this->field_display) || $this->field_display === null) {
            $this->field_display = '';
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
     * @since   6.1.3
     */
    public function store($updateNulls = true): bool
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (empty($this->j2commerce_customfield_id)) {
            if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
                $this->created_on = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = (int) $user->id;
            }
        }

        $this->modified_on = $date;
        $this->modified_by = (int) $user->id;

        return parent::store($updateNulls);
    }

    /**
     * Method to delete a row from the database table by primary key value.
     *
     * CRITICAL: Core fields (field_core = 1) cannot be deleted.
     *
     * @param   mixed  $pk  An optional primary key value to delete.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.4
     */
    public function delete($pk = null): bool
    {
        // Load the record first
        $this->load($pk);

        // Prevent deletion of core fields - use strict comparison
        if ((int) $this->field_core === 1) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_CANNOT_DELETE_CORE_FIELD'));
            return false;
        }

        // If this is a non-core custom field with field_table='address' and field_type != 'customtext',
        // drop the column from the addresses table before deleting the field
        if ((int) $this->field_core !== 1 &&
            $this->field_table === 'address' &&
            $this->field_type !== 'customtext' &&
            !empty($this->field_namekey)) {

            $this->dropAddressColumn($this->field_namekey);
        }

        return parent::delete($pk);
    }

    /**
     * Drop a custom field column from the addresses table.
     *
     * @param   string  $fieldNamekey  The field namekey to drop as a column.
     *
     * @return  void
     *
     * @throws  \RuntimeException  If the column cannot be dropped.
     *
     * @since   6.0.7
     */
    protected function dropAddressColumn(string $fieldNamekey): void
    {
        $db = $this->getDatabase();

        // Check if column exists before attempting to drop
        $columns = $db->getTableColumns('#__j2commerce_addresses');
        if (!isset($columns[$fieldNamekey])) {
            // Column doesn't exist, nothing to drop
            return;
        }

        // Drop the column from the addresses table
        $query = 'ALTER TABLE ' . $db->quoteName('#__j2commerce_addresses') .
                 ' DROP ' . $db->quoteName($fieldNamekey);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                Text::_('COM_J2COMMERCE_ERR_CUSTOMFIELD_DROP_COLUMN_FAILED') .
                ': ' . $e->getMessage()
            );
        }
    }
}
