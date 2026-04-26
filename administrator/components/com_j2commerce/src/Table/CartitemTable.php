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
 * Cart Item table class.
 *
 * @since  6.0.0
 */
class CartitemTable extends Table
{
    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  6.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object.
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_j2commerce.cartitem';

        parent::__construct('#__j2commerce_cartitems', 'j2commerce_cartitem_id', $db);
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

        // Ensure required fields have values
        if (empty($this->cart_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Cart ID'));
            return false;
        }

        if (empty($this->product_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Product ID'));
            return false;
        }

        // Every product should have a variant_id - check variants or variant object
        if (empty($this->variant_id)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Variant ID'));
            return false;
        }

        // Set default values
        if (!isset($this->vendor_id) || $this->vendor_id === '') {
            $this->vendor_id = 0;
        }

        // product_type is required and must come from the caller. Never default to
        // 'simple' — that silently downgrades subscription/variable/configurable
        // products and breaks downstream AfterPayment routing.
        if (empty($this->product_type)) {
            $this->setError(Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', 'Product Type'));
            return false;
        }

        if (!isset($this->cartitem_params)) {
            $this->cartitem_params = '';
        }

        if (!isset($this->product_qty) || $this->product_qty === '') {
            $this->product_qty = 1;
        }

        if (!isset($this->product_options)) {
            $this->product_options = '';
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
        return parent::store($updateNulls);
    }
}
