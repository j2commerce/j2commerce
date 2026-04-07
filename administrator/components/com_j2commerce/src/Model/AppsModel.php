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

use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Apps Model
 *
 * @since  6.0.0
 */
class AppsModel extends ListModel
{
    private ?array $externalAppIds = null;

    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'extension_id', 'a.extension_id',
                'name', 'a.name',
                'element', 'a.element',
                'enabled', 'a.enabled',
                'type', 'plugin_type',
                'ordering', 'a.ordering',
                'access', 'a.access',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.name', $direction = 'asc')
    {
        // Load the filter state.
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '');
        $this->setState('filter.enabled', $enabled);

        $pluginType = $this->getUserStateFromRequest($this->context . '.filter.plugin_type', 'filter_plugin_type', '');
        $this->setState('filter.plugin_type', $pluginType);

        // List state information.
        parent::populateState($ordering, $direction);
    }

    public function getRegisteredExternalAppIds(): array
    {
        if ($this->externalAppIds !== null) {
            return $this->externalAppIds;
        }

        $this->externalAppIds = [];

        try {
            $dispatcher = Factory::getApplication()->getDispatcher();
            $event      = new GenericEvent('onJ2CommerceRegisterApps', ['subject' => $this]);
            $dispatcher->dispatch('onJ2CommerceRegisterApps', $event);

            $results      = $event->getArgument('result', []);
            $candidateIds = [];

            foreach ($results as $result) {
                if (\is_array($result) && !empty($result['extension_id'])) {
                    $id = (int) $result['extension_id'];

                    if ($id > 0) {
                        $candidateIds[] = $id;
                    }
                }
            }

            if (!empty($candidateIds)) {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true)
                    ->select($db->quoteName('extension_id'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->whereIn($db->quoteName('extension_id'), $candidateIds);
                $db->setQuery($query);

                $this->externalAppIds = array_map('intval', $db->loadColumn() ?: []);
            }
        } catch (\Throwable $e) {
            // Silently fail — no external apps is safe default
        }

        return $this->externalAppIds;
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.plugin_type');
        $id .= ':' . implode(',', $this->getRegisteredExternalAppIds());

        return parent::getStoreId($id);
    }

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

        // Filter by plugin type
        $query->where($db->quoteName('a.type') . ' = ' . $db->quote('plugin'));

        $externalIds = $this->getRegisteredExternalAppIds();

        if (empty($externalIds)) {
            $query->where($db->quoteName('a.folder') . ' = ' . $db->quote('j2commerce'));
            $query->where($db->quoteName('a.element') . ' LIKE ' . $db->quote('app_%'));
        } else {
            $placeholders = [];

            foreach ($externalIds as $idx => $extId) {
                $paramName      = ':extId' . $idx;
                $placeholders[] = $paramName;
                $query->bind($paramName, $externalIds[$idx], ParameterType::INTEGER);
            }

            $query->where(
                '((' . $db->quoteName('a.folder') . ' = ' . $db->quote('j2commerce') .
                ' AND ' . $db->quoteName('a.element') . ' LIKE ' . $db->quote('app_%') . ')' .
                ' OR ' . $db->quoteName('a.extension_id') . ' IN (' . implode(',', $placeholders) . '))'
            );
        }

        // Filter by enabled state
        $enabled = (string) $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by plugin type (app, payment, shipping, report)
        $pluginType = (string) $this->getState('filter.plugin_type');

        if ($pluginType !== '') {
            $pluginTypeLike = $pluginType . '%';

            $query->where($db->quoteName('a.element') . ' LIKE :plugintype')
                ->bind(':plugintype', $pluginTypeLike, ParameterType::STRING);
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

        // Add the list ordering clause (name sorting handled in PHP after translation)
        $orderCol  = $this->getState('list.ordering', 'a.name');
        $orderDirn = $this->getState('list.direction', 'ASC');

        if ($orderCol && $orderDirn && $orderCol !== 'a.name') {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        } elseif ($orderCol === 'a.name') {
            $query->order($db->quoteName('a.extension_id') . ' ASC');
        }

        return $query;
    }

    public function getItems()
    {
        $orderCol     = $this->getState('list.ordering', 'a.name');
        $isSortByName = ($orderCol === 'a.name');

        // When sorting by name, fetch ALL items so PHP can sort by translated name
        // across pages (SQL can only sort by raw language keys). Safe because plugin
        // counts are always small (<100).
        if ($isSortByName) {
            $origStart = $this->getState('list.start');
            $origLimit = $this->getState('list.limit');
            $this->setState('list.start', 0);
            $this->setState('list.limit', 0);
        }

        $items = parent::getItems();

        if ($isSortByName) {
            $this->setState('list.start', $origStart);
            $this->setState('list.limit', $origLimit);
        }

        if ($items === false || !\is_array($items)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_APPS_NO_PLUGINS_FOUND'), 'warning');
            return [];
        }

        foreach ($items as $item) {
            if (str_starts_with($item->element, 'app_')) {
                $item->plugin_type         = 'app';
                $item->plugin_type_display = Text::_('COM_J2COMMERCE_APP_TYPE_APP');
            } elseif (str_starts_with($item->element, 'payment_')) {
                $item->plugin_type         = 'payment';
                $item->plugin_type_display = Text::_('COM_J2COMMERCE_APP_TYPE_PAYMENT');
            } elseif (str_starts_with($item->element, 'shipping_')) {
                $item->plugin_type         = 'shipping';
                $item->plugin_type_display = Text::_('COM_J2COMMERCE_APP_TYPE_SHIPPING');
            } elseif (str_starts_with($item->element, 'report_')) {
                $item->plugin_type         = 'report';
                $item->plugin_type_display = Text::_('COM_J2COMMERCE_APP_TYPE_REPORT');
            } else {
                $item->plugin_type         = 'other';
                $item->plugin_type_display = Text::_('COM_J2COMMERCE_APP_TYPE_OTHER');
            }

            $pluginPath        = JPATH_SITE . '/plugins/' . $item->folder . '/' . $item->element;
            $item->files_exist = is_dir($pluginPath);

            $manifest          = !empty($item->manifest_cache) ? json_decode($item->manifest_cache) : null;
            $item->version     = $manifest?->version ?? '';
            $item->author      = $manifest?->author ?? '';
            $item->description = $manifest?->description ?? '';

            // All plugins edit via Joomla Plugin Manager (no App singular view exists)
            $item->edit_link = 'index.php?option=com_plugins&task=plugin.edit&extension_id=' . $item->extension_id;

            $item->display_name = Text::_($item->name);
        }

        // Sort by translated name and manually paginate
        if ($isSortByName) {
            $direction = strtoupper($this->getState('list.direction', 'ASC'));
            usort(
                $items,
                static fn ($a, $b) => $direction === 'ASC'
                ? strcasecmp($a->display_name, $b->display_name)
                : strcasecmp($b->display_name, $a->display_name)
            );

            $start = (int) $this->getState('list.start', 0);
            $limit = (int) $this->getState('list.limit', 0);

            if ($limit > 0) {
                $items = \array_slice($items, $start, $limit);
            }
        }

        return $items;
    }

    public function getFilterForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_j2commerce.apps.filter', 'filter_apps', ['control' => '', 'load_data' => $loadData]);
    }

    public function getActiveFilters()
    {
        $activeFilters = [];

        if (!empty($this->getState('filter.search'))) {
            $activeFilters['search'] = $this->getState('filter.search');
        }

        if ($this->getState('filter.enabled') !== '') {
            $activeFilters['enabled'] = $this->getState('filter.enabled');
        }

        if (!empty($this->getState('filter.plugin_type'))) {
            $activeFilters['plugin_type'] = $this->getState('filter.plugin_type');
        }

        return $activeFilters;
    }

    public function getIsEmptyState(): bool
    {
        $filters = $this->getActiveFilters();

        return empty($filters);
    }
}
