<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ShippingStandard\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Shipping Method table class.
 *
 * Maps to `#__j2commerce_shippingmethods` table.
 *
 * @since  6.0.0
 */
class ShippingMethodTable extends Table
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
        parent::__construct('#__j2commerce_shippingmethods', 'j2commerce_shippingmethod_id', $db);

        // This table uses 'published' column (not 'enabled')
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
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->setError(Text::_('COM_J2COMMERCE_ERR_GENERIC'));

            return false;
        }

        // Validate method name
        if (empty($this->shipping_method_name)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_SHIPPING_METHOD_NAME_REQUIRED'));

            return false;
        }

        // Set default published state for new records
        if (!isset($this->published) || $this->published === '') {
            $this->published = 1;
        }

        // Set default shipping method type
        if (!isset($this->shipping_method_type) || $this->shipping_method_type === '') {
            $this->shipping_method_type = 0;
        }

        // Set default tax class
        if (!isset($this->tax_class_id) || $this->tax_class_id === '') {
            $this->tax_class_id = 0;
        }

        // Set default subtotal limits
        if (!isset($this->subtotal_minimum) || $this->subtotal_minimum === '') {
            $this->subtotal_minimum = '0.000';
        }

        if (!isset($this->subtotal_maximum) || $this->subtotal_maximum === '') {
            $this->subtotal_maximum = '0.000';
        }

        // Set default address override
        if (!isset($this->address_override) || $this->address_override === null) {
            $this->address_override = '';
        }

        return true;
    }
}
