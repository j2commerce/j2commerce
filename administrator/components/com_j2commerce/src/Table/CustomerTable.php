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
 * Customer table class.
 *
 * Maps to the #__j2commerce_addresses table for customer address management.
 *
 * @since  6.0.7
 */
class CustomerTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database driver object.
     *
     * @since   6.0.7
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_addresses', 'j2commerce_address_id', $db);
    }

    /**
     * Overloaded check function to validate data.
     *
     * @return  boolean  True if the data is valid, false otherwise.
     *
     * @since   6.0.7
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        // Validate required fields
        if (empty($this->first_name)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_FIRST_NAME')));

            return false;
        }

        if (empty($this->email)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', Text::_('COM_J2COMMERCE_FIELD_EMAIL')));

            return false;
        }

        // Validate email format
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_EMAIL'));

            return false;
        }

        // Set default address type if not set
        if (empty($this->type)) {
            $this->type = 'billing';
        }

        return true;
    }
}
