<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Filtergroups Model
 *
 * @since  6.0.0
 */
class FiltergroupsModel extends ListModel
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
                'j2commerce_filtergroup_id', 'a.j2commerce_filtergroup_id',
                'group_name', 'a.group_name',
                'ordering', 'a.ordering',
                'enabled', 'a.enabled',
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
    protected function populateState($ordering = 'a.group_name', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        // List state information.
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
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');

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
        // Create a new query object.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.*'
            )
        );
        $query->from($db->quoteName('#__j2commerce_filtergroups', 'a'));

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_filtergroup_id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%');
                $query->where($db->quoteName('a.group_name') . ' LIKE :search')
                    ->bind(':search', $search);
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'a.group_name');
        $orderDirn = $this->getState('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Method to get an array of data items.
     *
     * @return  array  An array of data items
     *
     * @since  6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !\is_array($items)) {
            // Log the error for debugging
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_FILTERGROUPS_ERROR_RETRIEVING_DATA'), 'warning');
            return [];
        }

        return $items;
    }
}
