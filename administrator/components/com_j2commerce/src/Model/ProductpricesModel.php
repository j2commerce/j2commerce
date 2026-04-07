<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Product Prices List Model
 *
 * @since  6.0.0
 */
class ProductpricesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_productprice_id', 'pp.j2commerce_productprice_id',
                'variant_id', 'pp.variant_id',
                'customer_group_id', 'pp.customer_group_id',
                'quantity_from', 'pp.quantity_from',
                'quantity_to', 'pp.quantity_to',
                'date_from', 'pp.date_from',
                'date_to', 'pp.date_to',
                'price', 'pp.price',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function populateState($ordering = 'pp.customer_group_id', $direction = 'ASC')
    {
        $app = Factory::getApplication();

        // Get variant_id from request
        $variant_id = $app->input->getInt('variant_id', 0);
        $this->setState('filter.variant_id', $variant_id);

        // List state information
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since  6.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.variant_id');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since  6.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select fields from productprices table
        $query->select(
            $this->getState(
                'list.select',
                'pp.*'
            )
        );
        $query->from($db->quoteName('#__j2commerce_product_prices', 'pp'));

        // Filter by variant_id
        $variant_id = $this->getState('filter.variant_id');
        if ($variant_id) {
            $query->where($db->quoteName('pp.variant_id') . ' = :variant_id')
                ->bind(':variant_id', $variant_id, ParameterType::INTEGER);
        }

        // Add the list ordering clause
        $orderCol  = $this->state->get('list.ordering', 'pp.customer_group_id');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

        // Secondary ordering by quantity
        $query->order($db->quoteName('pp.quantity_from') . ' ASC');

        return $query;
    }

    /**
     * Method to get an array of data items.
     *
     * @return  mixed  An array of data items on success, false on failure.
     *
     * @since  6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        if ($items === false) {
            return [];
        }

        // Process items if needed
        foreach ($items as &$item) {
            // Convert date fields if they're null dates
            if (isset($item->date_from) && ($item->date_from === '0000-00-00 00:00:00' || $item->date_from === $this->getDatabase()->getNullDate())) {
                $item->date_from = '';
            }

            if (isset($item->date_to) && ($item->date_to === '0000-00-00 00:00:00' || $item->date_to === $this->getDatabase()->getNullDate())) {
                $item->date_to = '';
            }

            // Ensure numeric fields are properly typed
            if (isset($item->price)) {
                $item->price = (float) $item->price;
            }

            if (isset($item->quantity_from)) {
                $item->quantity_from = (int) $item->quantity_from;
            }

            if (isset($item->quantity_to)) {
                $item->quantity_to = (int) $item->quantity_to;
            }

            if (isset($item->customer_group_id)) {
                $item->customer_group_id = (int) $item->customer_group_id;
            }
        }

        return $items;
    }

    /**
     * Get all product prices for a specific variant
     *
     * @param   int  $variant_id  The variant ID
     *
     * @return  array  List of price records
     *
     * @since  6.0.0
     */
    public function getPricesByVariantId($variant_id)
    {
        if (!$variant_id) {
            return [];
        }

        $this->setState('filter.variant_id', $variant_id);
        return $this->getItems();
    }
}
