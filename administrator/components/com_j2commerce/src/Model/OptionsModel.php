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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Options Model
 *
 * @since  6.0.0
 */
class OptionsModel extends ListModel
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
                'j2commerce_option_id', 'a.j2commerce_option_id',
                'type', 'a.type',
                'option_unique_name', 'a.option_unique_name',
                'option_name', 'a.option_name',
                'ordering', 'a.ordering',
                'enabled', 'a.enabled',
                'option_params', 'a.option_params',
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
    protected function populateState($ordering = 'a.ordering', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        $type = $this->getUserStateFromRequest($this->context . '.filter.type', 'filter_type', '');
        $this->setState('filter.type', $type);

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
        $id .= ':' . $this->getState('filter.type');

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
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
            $this->getState(
                'list.select',
                'a.j2commerce_option_id, a.type, a.option_unique_name, a.option_name, a.ordering, a.enabled, a.option_params'
            )
        );
        $query->from($db->quoteName('#__j2commerce_options', 'a'));

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by type
        $type = $this->getState('filter.type');
        if (!empty($type)) {
            $query->where($db->quoteName('a.type') . ' = :type')
                ->bind(':type', $type, ParameterType::STRING);
        }

        // Filter by search in option_name, option_unique_name, or type
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_option_id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.option_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.option_unique_name') . ' LIKE :search2 OR ' .
                    $db->quoteName('a.type') . ' LIKE :search3)'
                )
                ->bind(':search1', $search)
                ->bind(':search2', $search)
                ->bind(':search3', $search);
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'a.ordering');
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
            $app->enqueueMessage('Failed to retrieve options from database. Please check if the j2commerce_options table exists.', 'warning');
            return [];
        }

        // Process each item to ensure proper display
        foreach ($items as $item) {
            // Ensure we have proper IDs for the list view
            if (!isset($item->id)) {
                $item->id = $item->j2commerce_option_id;
            }

            // Ensure we have proper published state
            if (!isset($item->published)) {
                $item->published = $item->enabled;
            }

            // Provide fallbacks for missing data
            if (!isset($item->type)) {
                $item->type = '';
            }
            if (!isset($item->option_unique_name)) {
                $item->option_unique_name = '';
            }
            if (!isset($item->option_name)) {
                $item->option_name = '';
            }
            if (!isset($item->option_params)) {
                $item->option_params = '';
            }
        }

        return $items;
    }

    /**
     * Get the list of option types for filter dropdown
     *
     * @return  array  Array of option types
     *
     * @since  6.0.0
     */
    public function getOptionTypes()
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select('DISTINCT ' . $db->quoteName('type'))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('type') . ' != ""')
            ->order($db->quoteName('type') . ' ASC');

        $db->setQuery($query);

        try {
            $types = $db->loadColumn();

            if (!$types) {
                return [];
            }

            $options = [];
            foreach ($types as $type) {
                $options[] = (object) ['value' => $type, 'text' => ucfirst($type)];
            }

            return $options;
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage('Error loading option types: ' . $e->getMessage(), 'error');
            return [];
        }
    }
}
