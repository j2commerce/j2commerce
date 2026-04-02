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
 * Currency table class.
 *
 * @since  6.0.4
 */
class CurrencyTable extends Table
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
        parent::__construct('#__j2commerce_currencies', 'j2commerce_currency_id', $db);

        // CRITICAL: J2Commerce uses 'enabled' column instead of Joomla's standard 'published'
        // This alias allows publish/unpublish toolbar actions to work correctly
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

        // Set default ordering for new records
        if (empty($this->ordering)) {
            $this->ordering = 0;
        }

        // Set default enabled state for new records
        if (!isset($this->enabled) || $this->enabled === '') {
            $this->enabled = 1;
        }

        // Validate currency title
        if (empty($this->currency_title)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_CURRENCY_TITLE')));
            return false;
        }

        // Validate currency code
        if (empty($this->currency_code)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_CURRENCY_CODE')));
            return false;
        }

        if (strlen($this->currency_code) !== 3) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_CURRENCY_CODE_LENGTH'));
            return false;
        }

        // Validate currency symbol
        if (empty($this->currency_symbol)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_CURRENCY_SYMBOL')));
            return false;
        }

        // Validate currency decimal separator
        if (empty($this->currency_decimal)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_CURRENCY_DECIMAL')));
            return false;
        }

        // Validate currency thousands separator
        if (empty($this->currency_thousands)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_CURRENCY_THOUSANDS')));
            return false;
        }

        // Validate currency number of decimals
        if (!isset($this->currency_num_decimals) || $this->currency_num_decimals < 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_CURRENCY_NUM_DECIMALS_INVALID'));
            return false;
        }

        // Validate currency value
        if (empty($this->currency_value) || $this->currency_value <= 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_CURRENCY_VALUE_INVALID'));
            return false;
        }

        // Ensure currency code is uppercase
        $this->currency_code = strtoupper($this->currency_code);

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

        if (empty($this->j2commerce_currency_id)) {
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
