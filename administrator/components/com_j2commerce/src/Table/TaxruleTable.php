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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Taxrule table class.
 *
 * @since  6.0.3
 */
class TaxruleTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.3
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_taxrules', 'j2commerce_taxrule_id', $db);
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
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

        // Set default ordering for new records
        if (empty($this->ordering)) {
            $this->ordering = 0;
        }

        // Validate taxprofile_id
        if (!isset($this->taxprofile_id) || $this->taxprofile_id === '' || $this->taxprofile_id <= 0) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_PLEASE_SELECT', Text::_('COM_J2COMMERCE_FIELD_TAXPROFILE')));

            return false;
        }

        // Validate taxrate_id
        if (!isset($this->taxrate_id) || $this->taxrate_id === '' || $this->taxrate_id <= 0) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_PLEASE_SELECT', Text::_('COM_J2COMMERCE_FIELD_TAXRATE')));

            return false;
        }

        // Validate address type
        if (empty($this->address)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_PLEASE_SELECT', Text::_('COM_J2COMMERCE_FIELD_ADDRESS_TYPE')));

            return false;
        }

        // Address must be either 'billing' or 'shipping'
        if (!\in_array($this->address, ['billing', 'shipping'])) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_ADDRESS_TYPE'));

            return false;
        }

        return true;
    }
}
