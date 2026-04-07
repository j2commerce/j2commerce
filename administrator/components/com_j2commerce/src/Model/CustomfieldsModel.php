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
use Joomla\Database\QueryInterface;

/**
 * Customfields list model class.
 *
 * @since  6.0.4
 */
class CustomfieldsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.4
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_customfield_id', 'a.j2commerce_customfield_id',
                'field_namekey', 'a.field_namekey',
                'field_name', 'a.field_name',
                'field_table', 'a.field_table',
                'field_type', 'a.field_type',
                'field_core', 'a.field_core',
                'field_required', 'a.field_required',
                'enabled', 'a.enabled',
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
     * @since   6.0.4
     */
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'string');
        $this->setState('filter.enabled', $enabled);

        $fieldRequired = $this->getUserStateFromRequest($this->context . '.filter.field_required', 'filter_field_required', '', 'string');
        $this->setState('filter.field_required', $fieldRequired);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on the model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.4
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.field_required');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.4
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields
        $query->select(
            $db->quoteName([
                'a.j2commerce_customfield_id',
                'a.field_namekey',
                'a.field_name',
                'a.field_table',
                'a.field_type',
                'a.field_core',
                'a.field_required',
                'a.enabled',
                'a.ordering',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_customfields', 'a'));

        // Filter by enabled state
        $enabled = $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $enabled = (int) $enabled;
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by required
        $fieldRequired = $this->getState('filter.field_required');

        if (is_numeric($fieldRequired)) {
            $fieldRequired = (int) $fieldRequired;
            $query->where($db->quoteName('a.field_required') . ' = :fieldRequired')
                ->bind(':fieldRequired', $fieldRequired, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_customfield_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.field_namekey') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.field_name') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'a.j2commerce_customfield_id');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }
}
