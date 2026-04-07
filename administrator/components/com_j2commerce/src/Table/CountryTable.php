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
 * Country table class.
 *
 * @since  6.0.2
 */
class CountryTable extends Table
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
        parent::__construct('#__j2commerce_countries', 'j2commerce_country_id', $db);
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

        // Validate country name
        if (empty($this->country_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUNTRY_NAME')));

            return false;
        }

        // Validate 2-letter ISO code
        if (empty($this->country_isocode_2)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUNTRY_ISOCODE_2')));

            return false;
        }

        if (\strlen($this->country_isocode_2) !== 2) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_ISOCODE_2_LENGTH'));

            return false;
        }

        // Validate 3-letter ISO code
        if (empty($this->country_isocode_3)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_COUNTRY_ISOCODE_3')));

            return false;
        }

        if (\strlen($this->country_isocode_3) !== 3) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_ISOCODE_3_LENGTH'));

            return false;
        }

        // Ensure ISO codes are uppercase
        $this->country_isocode_2 = strtoupper($this->country_isocode_2);
        $this->country_isocode_3 = strtoupper($this->country_isocode_3);

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

        if (empty($this->j2commerce_country_id)) {
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
