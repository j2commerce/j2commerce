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

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Product Quantity table class.
 *
 * Manages inventory quantity tracking for product variants.
 *
 * @since  6.0.0
 */
class ProductquantityTable extends Table
{
    /**
     * Constructor.
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   6.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__j2commerce_productquantities', 'j2commerce_productquantity_id', $db);
    }

    /**
     * Overloaded check method to ensure data integrity.
     *
     * @return  boolean  True on success.
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

        // Set default values
        if (empty($this->product_attributes)) {
            $this->product_attributes = '';
        }

        if (!isset($this->quantity)) {
            $this->quantity = 0;
        }

        if (!isset($this->on_hold)) {
            $this->on_hold = 0;
        }

        if (!isset($this->sold)) {
            $this->sold = 0;
        }

        return true;
    }

    /**
     * Save a product quantity record.
     *
     * @param   array|object  $src        The data to save.
     * @param   string        $orderingFilter  Filter for the ordering column.
     * @param   array|string  $ignore     Properties to ignore.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.0
     */
    public function save($src, $orderingFilter = '', $ignore = ''): bool
    {
        // Convert object to array
        if (\is_object($src)) {
            $src = (array) $src;
        }

        // Check if we're updating an existing record
        if (!empty($src['j2commerce_productquantity_id'])) {
            $this->load($src['j2commerce_productquantity_id']);
        } elseif (!empty($src['variant_id'])) {
            // Try to load by variant_id
            $this->load(['variant_id' => $src['variant_id']]);
        }

        // Bind the data
        if (!$this->bind($src, $ignore)) {
            return false;
        }

        // Check the data
        if (!$this->check()) {
            return false;
        }

        // Store the record
        if (!$this->store()) {
            return false;
        }

        return true;
    }
}
