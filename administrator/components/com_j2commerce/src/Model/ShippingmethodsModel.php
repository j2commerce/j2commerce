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
 * Shipping Methods Model
 *
 * @since  6.0.0
 */
class ShippingmethodsModel extends ListModel
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
                'extension_id', 'a.extension_id',
                'name', 'a.name',
                'element', 'a.element',
                'enabled', 'a.enabled',
                'ordering', 'a.ordering',
                'access', 'a.access',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
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
    protected function populateState($ordering = 'a.name', $direction = 'asc')
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
     * @since   6.0.0
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
     * @since   6.0.0
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
                'a.extension_id, a.name, a.element, a.folder, a.enabled, ' .
                'a.access, a.ordering, a.params, a.checked_out, a.checked_out_time, ' .
                'a.manifest_cache'
            )
        );
        $query->from($db->quoteName('#__extensions', 'a'));

        // Filter by J2Commerce shipping plugins only
        $query->where($db->quoteName('a.type') . ' = ' . $db->quote('plugin'));
        $query->where($db->quoteName('a.folder') . ' = ' . $db->quote('j2commerce'));

        // Filter to show only shipping methods (plugins that start with shipping_)
        $query->where($db->quoteName('a.element') . ' LIKE ' . $db->quote('shipping_%'));

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by search in name or element
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.extension_id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%');
                $query->where(
                    '(' . $db->quoteName('a.name') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.element') . ' LIKE :search2)'
                )
                ->bind(':search1', $search)
                ->bind(':search2', $search);
            }
        }

        // Add the list ordering clause.
        $orderCol  = $this->getState('list.ordering', 'a.name');
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
     * @since   6.0.0
     */
    public function getItems()
    {
        $items = parent::getItems();

        // Ensure we always return an array
        if ($items === false || !\is_array($items)) {
            $app = Factory::getApplication();
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_SHIPPING_METHODS_NO_PLUGINS_FOUND'), 'warning');
            return [];
        }

        // Add computed properties for display
        foreach ($items as $item) {
            // Check if plugin files exist on filesystem
            $pluginPath        = JPATH_SITE . '/plugins/j2commerce/' . $item->element;
            $item->files_exist = is_dir($pluginPath);

            // Get plugin manifest information if available
            if (!empty($item->manifest_cache)) {
                $manifest = json_decode($item->manifest_cache);
                if (\is_object($manifest)) {
                    $item->version     = isset($manifest->version) ? $manifest->version : '';
                    $item->author      = isset($manifest->author) ? $manifest->author : '';
                    $item->description = isset($manifest->description) ? $manifest->description : '';
                } else {
                    $item->version     = '';
                    $item->author      = '';
                    $item->description = '';
                }
            } else {
                $item->version     = '';
                $item->author      = '';
                $item->description = '';
            }

            // Create edit link
            $item->edit_link = 'index.php?option=com_j2commerce&task=shippingmethod.edit&extension_id=' . $item->extension_id;
        }

        return $items;
    }

    /**
     * Method to get the filter form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
     *
     * @since   6.0.0
     */
    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_j2commerce.shippingmethods.filter', 'filter_shippingmethods', ['control' => '', 'load_data' => $loadData]);
    }

    /**
     * Method to get an array of data items for the active filters.
     *
     * @return  array
     *
     * @since   6.0.0
     */
    public function getActiveFilters()
    {
        $activeFilters = [];

        if (!empty($this->getState('filter.search'))) {
            $activeFilters['search'] = $this->getState('filter.search');
        }

        if ($this->getState('filter.enabled') !== '') {
            $activeFilters['enabled'] = $this->getState('filter.enabled');
        }

        return $activeFilters;
    }

    /**
     * Method to get the Is Empty State status
     *
     * @return  bool
     *
     * @since   6.0.0
     */
    public function getIsEmptyState(): bool
    {
        $filters = $this->getActiveFilters();

        return empty($filters);
    }
}
