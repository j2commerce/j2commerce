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

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Option Values Model
 *
 * Handles querying option values (e.g., Small, Medium, Large for a Size option).
 * This model returns the base option values defined for an Option, NOT the
 * product-specific option values (use ProductoptionvaluesModel for those).
 *
 * @since  6.0.0
 */
class OptionvaluesModel extends ListModel
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
                'j2commerce_optionvalue_id', 'a.j2commerce_optionvalue_id',
                'option_id', 'a.option_id',
                'optionvalue_name', 'a.optionvalue_name',
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
        // Filter by option_id
        $optionId = $this->getUserStateFromRequest($this->context . '.filter.option_id', 'filter_option_id', 0, 'int');
        $this->setState('filter.option_id', $optionId);

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
        $id .= ':' . $this->getState('filter.option_id');

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
            $db->quoteName('a.j2commerce_optionvalue_id'),
            $db->quoteName('a.option_id'),
            $db->quoteName('a.optionvalue_name'),
            $db->quoteName('a.optionvalue_image'),
            $db->quoteName('a.ordering'),
        ])
            ->from($db->quoteName('#__j2commerce_optionvalues', 'a'));

        // Filter by option_id
        $optionId = $this->getState('filter.option_id');

        if (!empty($optionId)) {
            $optionIdInt = (int) $optionId;
            $query->where($db->quoteName('a.option_id') . ' = :optionId')
                ->bind(':optionId', $optionIdInt, ParameterType::INTEGER);
        }

        // Ordering
        $orderCol = $this->state->get('list.ordering', 'a.ordering');
        $orderDir = $this->state->get('list.direction', 'ASC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    /**
     * Get option values by option ID.
     *
     * This method is designed for programmatic use where you need to fetch
     * option values for a specific option without session state interference.
     *
     * @param   int  $optionId  The option ID.
     *
     * @return  array  Array of option value objects.
     *
     * @since   6.0.0
     */
    public function getValuesByOptionId(int $optionId): array
    {
        // Force state initialization to prevent populateState from overriding our values
        $this->getState();

        // Now safely override with programmatic values
        $this->setState('filter.option_id', $optionId);
        $this->setState('list.limit', 0); // No limit
        $this->setState('list.start', 0);

        return $this->getItems() ?: [];
    }
}
