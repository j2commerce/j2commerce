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
 * Product Option Table class.
 *
 * Links products to options with ordering and required settings.
 *
 * @since  6.0.0
 */
class ProductoptionTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_product_options', 'j2commerce_productoption_id', $db);
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   6.0.0
     */
    public function check(): bool
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // Validate option_id is set
        if (empty($this->option_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Option ID'));
            return false;
        }

        // Validate product_id is set
        if (empty($this->product_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Product ID'));
            return false;
        }

        // Set default values for new records
        if (empty($this->ordering)) {
            $this->ordering = 0;
        }

        if (!isset($this->required) || $this->required === '') {
            $this->required = 0;
        }

        if (!isset($this->is_variant) || $this->is_variant === '') {
            $this->is_variant = 0;
        }

        if (!isset($this->parent_id) || $this->parent_id === '') {
            $this->parent_id = 0;
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
     * @since   6.0.0
     */
    public function store($updateNulls = true): bool
    {
        // Set default ordering value for new records
        if (!(int) $this->j2commerce_productoption_id && empty($this->ordering)) {
            $this->ordering = $this->getNextOrder(
                $this->_db->quoteName('product_id') . ' = ' . (int) $this->product_id
            );
        }

        return parent::store($updateNulls);
    }
}
