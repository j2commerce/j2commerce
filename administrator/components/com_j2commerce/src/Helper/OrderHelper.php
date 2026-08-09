<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Order helper class.
 *
 * Provides functionality for cart-to-order operations including
 * order population from cart items and calculations.
 *
 * @since  6.0.6
 */
class OrderHelper
{
    /**
     * Singleton instance.
     *
     * @var    OrderHelper|null
     * @since  6.0.6
     */
    protected static ?OrderHelper $instance = null;

    /**
     * Current cart order object.
     *
     * @var    CartOrder|null
     * @since  6.0.6
     */
    protected ?CartOrder $order = null;

    /**
     * Cart items.
     *
     * @var    array
     * @since  6.0.6
     */
    protected array $items = [];

    /**
     * Get singleton instance.
     *
     * @return  OrderHelper
     *
     * @since   6.0.6
     */
    public static function getInstance(): OrderHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Populate order from cart items.
     *
     * Creates a CartOrder object with calculated totals from cart items.
     *
     * @param   array  $items  Cart items array.
     *
     * @return  OrderHelper  Self for chaining.
     *
     * @since   6.0.6
     */
    public function populateOrder(array $items): OrderHelper
    {
        $this->items = $items;
        $this->order = new CartOrder($items);

        return $this;
    }

    /**
     * Get the populated order object.
     *
     * @return  CartOrder|null
     *
     * @since   6.0.6
     */
    public function getOrder(): ?CartOrder
    {
        return $this->order;
    }

    /**
     * Restore any order-item column a BeforeAddOrderItem handler left unusable.
     *
     * insertObject() skips properties that are unset, null, an array or an object, and every
     * orderitem_* column is NOT NULL without a default — so a skipped property drops the column
     * from the INSERT and the write fails. Handler substitutions that are scalars are kept.
     *
     * @param   object  $row       Row as it stands after the dispatch.
     * @param   array   $baseline  Row as it stood before the dispatch.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public static function normalizeOrderItemRow(object $row, array $baseline): void
    {
        foreach ($baseline as $column => $value) {
            if (!isset($row->$column) || \is_array($row->$column) || \is_object($row->$column)) {
                $row->$column = $value;
            }
        }
    }
}
