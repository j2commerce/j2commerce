<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Product Option Values Model
 *
 * Handles querying product-specific option values from the j2commerce_product_optionvalues table.
 * These are the option values assigned to a specific product's option (ProductOption),
 * with pricing, weight, and SKU overrides.
 *
 * @since  6.0.0
 */
class ProductoptionvaluesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_product_optionvalue_id', 'a.j2commerce_product_optionvalue_id',
                'productoption_id', 'a.productoption_id',
                'optionvalue_id', 'a.optionvalue_id',
                'ordering', 'a.ordering',
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
     * @since   6.0.0
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $app = Factory::getApplication();

        // Filter by productoption_id
        $productoptionId = $app->getUserStateFromRequest(
            $this->context . '.filter.productoption_id',
            'filter_productoption_id',
            0,
            'int'
        );
        $this->setState('filter.productoption_id', $productoptionId);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.0
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.productoption_id');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   6.0.0
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
            $db->quoteName('a.j2commerce_product_optionvalue_id'),
            $db->quoteName('a.productoption_id'),
            $db->quoteName('a.optionvalue_id'),
            $db->quoteName('a.parent_optionvalue'),
            $db->quoteName('a.product_optionvalue_price'),
            $db->quoteName('a.product_optionvalue_prefix'),
            $db->quoteName('a.product_optionvalue_weight'),
            $db->quoteName('a.product_optionvalue_weight_prefix'),
            $db->quoteName('a.product_optionvalue_sku'),
            $db->quoteName('a.product_optionvalue_default'),
            $db->quoteName('a.ordering'),
            $db->quoteName('a.product_optionvalue_attribs'),
        ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'a'));

        // Filter by productoption_id
        $productoptionId = $this->getState('filter.productoption_id');

        if (!empty($productoptionId)) {
            $productoptionIdInt = (int) $productoptionId;
            $query->where($db->quoteName('a.productoption_id') . ' = :productoptionId')
                ->bind(':productoptionId', $productoptionIdInt, ParameterType::INTEGER);
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Get product option values by product option ID.
     *
     * This method is designed for programmatic use where you need to fetch
     * product option values for a specific product option without session state interference.
     *
     * @param   int  $productoptionId  The product option ID.
     *
     * @return  array  Array of product option value objects.
     *
     * @since   6.0.0
     */
    public function getValuesByProductOptionId(int $productoptionId): array
    {
        // Force state initialization to prevent populateState from overriding our values
        $this->getState();

        // Now safely override with programmatic values
        $this->setState('filter.productoption_id', $productoptionId);
        $this->setState('list.limit', 0); // No limit
        $this->setState('list.start', 0);

        return $this->getItems() ?: [];
    }
}
