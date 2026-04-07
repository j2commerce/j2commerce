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
 * Length table class.
 *
 * @since  6.0.2
 */
class LengthTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.2
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_lengths', 'j2commerce_length_id', $db);
        $this->setColumnAlias('published', 'enabled');
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.2
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

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

        // Set default decimal places for new records
        if (!isset($this->num_decimals) || $this->num_decimals === '') {
            $this->num_decimals = 2;
        }

        // Validate length title
        if (empty($this->length_title)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_LENGTH_TITLE')));

            return false;
        }

        // Validate length unit
        if (empty($this->length_unit)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_LENGTH_UNIT')));

            return false;
        }

        // Validate length value
        if (!isset($this->length_value) || $this->length_value === '') {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_LENGTH_VALUE')));

            return false;
        }

        if ($this->length_value <= 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_LENGTH_VALUE_POSITIVE'));

            return false;
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

        if (empty($this->j2commerce_length_id)) {
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
}
