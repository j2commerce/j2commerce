<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Option Table
 *
 * @since  6.0.0
 */
class OptionTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since  6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.option';

        // Set up the table for publish/unpublish functionality
        $this->_columnAlias = ['published' => 'enabled'];

        parent::__construct('#__j2commerce_options', 'j2commerce_option_id', $db);
    }

    /**
     * Method to bind an associative array or object to the Table instance.
     *
     * @param   array|object  $src     An associative array or object to bind to the Table instance.
     * @param   array|string  $ignore  An optional array or space separated list of properties to ignore while binding.
     *
     * @return  boolean  True on success.
     *
     * @since  6.0.0
     */
    public function bind($src, $ignore = [])
    {
        if (\is_array($src)) {
            // Handle complex array fields that need JSON conversion
            $complexFields = ['option_params', 'option_values'];

            foreach ($complexFields as $field) {
                if (isset($src[$field]) && \is_array($src[$field])) {
                    // Use Registry to handle complex data structure
                    $registry    = new Registry($src[$field]);
                    $src[$field] = $registry->toString();
                } elseif (isset($src[$field]) && \is_string($src[$field])) {
                    // Validate JSON string
                    if (!empty($src[$field]) && !$this->isValidJson($src[$field])) {
                        // If invalid JSON, try to fix common issues
                        $decoded = json_decode($src[$field], true);
                        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                            // Reset to empty object if can't decode
                            $src[$field] = '{}';
                        }
                    }
                }
            }
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Method to perform sanity checks on the Table instance properties to ensure
     * they are safe to store in the database.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the database.
     *
     * @since  6.0.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        // Check required fields
        if (empty($this->option_name)) {
            throw new \Exception(Text::_('COM_J2COMMERCE_OPTION_ERROR_NAME_REQUIRED'));
        }

        if (empty($this->type)) {
            throw new \Exception(Text::_('COM_J2COMMERCE_OPTION_ERROR_TYPE_REQUIRED'));
        }

        // Generate unique name if not provided
        if (empty($this->option_unique_name)) {
            $this->option_unique_name = $this->generateUniqueAlias($this->option_name);
        }

        // Validate unique name uniqueness
        if (!$this->validateUniqueAlias($this->option_unique_name)) {
            throw new \Exception(Text::_('COM_J2COMMERCE_OPTION_ERROR_UNIQUE_NAME_EXISTS'));
        }

        // Validate type is one of the allowed types
        $allowedTypes = ['text', 'textarea', 'select', 'radio', 'checkbox', 'date', 'datetime', 'time', 'file', 'image', 'color', 'number', 'email', 'url'];
        if (!\in_array($this->type, $allowedTypes)) {
            throw new \Exception(Text::_('COM_J2COMMERCE_OPTION_ERROR_INVALID_TYPE'));
        }

        // Ensure option_params is valid JSON if provided
        if (!empty($this->option_params)) {
            if (!$this->isValidJson($this->option_params)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_OPTION_ERROR_INVALID_PARAMS'));
            }
        } else {
            $this->option_params = '{}';
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
     * @since  6.0.0
     */
    public function store($updateNulls = true)
    {
        $date = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();

        if (!(int) $this->j2commerce_option_id) {
            if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
                $this->created_on = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = (int) $user->id;
            }

            // Set default enabled value for new records
            if (!isset($this->enabled)) {
                $this->enabled = 1;
            }

            // Set default ordering value for new records
            if (!isset($this->ordering)) {
                $this->ordering = $this->getNextOrder();
            }
        }

        $this->modified_on = $date;
        $this->modified_by = (int) $user->id;

        // Ensure option_params is set
        if (!isset($this->option_params) || $this->option_params === '') {
            $this->option_params = '{}';
        }

        return parent::store($updateNulls);
    }

    /**
     * Method to delete a row from the database table.
     *
     * @param   mixed  $pk  An optional primary key value to delete.
     *
     * @return  boolean  True on success.
     *
     * @since  6.0.0
     */
    public function delete($pk = null)
    {
        // Load the record to get the option_id before deleting
        if ($pk) {
            $this->load($pk);
        }

        $optionId = $this->j2commerce_option_id ?? 0;

        // Delete the option record
        $result = parent::delete($pk);

        // If option deletion was successful, also delete related option values
        if ($result && $optionId > 0) {
            try {
                $db    = $this->getDbo();
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__j2commerce_optionvalues'))
                    ->where($db->quoteName('option_id') . ' = :option_id')
                    ->bind(':option_id', $optionId, ParameterType::INTEGER);

                $db->setQuery($query);
                $db->execute();
            } catch (\Exception $e) {
                // Log the error but don't fail the whole operation
                Factory::getApplication()->enqueueMessage(
                    'Warning: Failed to delete associated option values: ' . $e->getMessage(),
                    'warning'
                );
            }
        }

        return $result;
    }

    /**
     * Generate a unique alias from a name
     *
     * @param   string  $name  The name to generate alias from
     *
     * @return  string  The generated alias
     *
     * @since  6.0.0
     */
    protected function generateUniqueAlias($name)
    {
        // Convert to lowercase and replace spaces/special chars with underscores
        $alias = preg_replace('/[^a-z0-9_]/i', '_', strtolower(trim($name)));
        $alias = preg_replace('/_+/', '_', $alias);
        $alias = trim($alias, '_');

        // Ensure it's not empty
        if (empty($alias)) {
            $alias = 'option_' . time();
        }

        // Make it unique by adding a number if necessary
        $originalAlias = $alias;
        $counter       = 1;

        while (!$this->validateUniqueAlias($alias)) {
            $alias = $originalAlias . '_' . $counter;
            $counter++;

            // Prevent infinite loop
            if ($counter > 100) {
                $alias = 'option_' . time() . '_' . rand(1, 999);
                break;
            }
        }

        return $alias;
    }

    /**
     * Validate that the unique alias is actually unique
     *
     * @param   string  $alias  The alias to validate
     *
     * @return  boolean  True if unique, false otherwise
     *
     * @since  6.0.0
     */
    protected function validateUniqueAlias($alias)
    {
        $db    = $this->getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('option_unique_name') . ' = :alias')
            ->bind(':alias', $alias, ParameterType::STRING);

        // If this is an update, exclude the current record
        if ($this->j2commerce_option_id > 0) {
            $query->where($db->quoteName('j2commerce_option_id') . ' != :id')
                ->bind(':id', $this->j2commerce_option_id, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        try {
            $count = (int) $db->loadResult();
            return $count === 0;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Check if a string is valid JSON
     *
     * @param   string  $string  The string to check
     *
     * @return  boolean  True if valid JSON, false otherwise
     *
     * @since  6.0.0
     */
    protected function isValidJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Method to get the next ordering value in the sequence.
     *
     * @param   string  $where  An optional WHERE clause to add to the query.
     *
     * @return  integer  The next ordering value.
     *
     * @since  6.0.0
     */
    public function getNextOrder($where = '')
    {
        // Get the database
        $db = $this->getDbo();

        // Build the query
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('ordering') . ')')
            ->from($db->quoteName('#__j2commerce_options'));

        if ($where) {
            $query->where($where);
        }

        $db->setQuery($query);
        $max = (int) $db->loadResult();

        return $max + 1;
    }
}
