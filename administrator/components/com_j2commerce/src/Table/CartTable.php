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
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Cart table class.
 *
 * @since  6.0.0
 */
class CartTable extends Table
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
        $this->typeAlias = 'com_j2commerce.cart';

        parent::__construct('#__j2commerce_carts', 'j2commerce_cart_id', $db);
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

        $date = Factory::getDate()->toSql();

        // Set created_on for new records
        if (empty($this->created_on) || $this->created_on === '0000-00-00 00:00:00') {
            $this->created_on = $date;
        }

        // Always update modified_on
        $this->modified_on = $date;

        // Set default cart_type
        if (empty($this->cart_type)) {
            $this->cart_type = 'cart';
        }

        // Set default empty values for text fields
        if (!isset($this->cart_voucher)) {
            $this->cart_voucher = '';
        }

        if (!isset($this->cart_coupon)) {
            $this->cart_coupon = '';
        }

        if (!isset($this->customer_ip)) {
            $this->customer_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        if (!isset($this->cart_params)) {
            $this->cart_params = '';
        }

        if (!isset($this->cart_browser)) {
            $this->cart_browser = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        if (!isset($this->cart_analytics)) {
            $this->cart_analytics = '';
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
