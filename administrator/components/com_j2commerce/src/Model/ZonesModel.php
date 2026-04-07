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
 * Zones list model class.
 *
 * @since  6.0.3
 */
class ZonesModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.3
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_zone_id', 'a.j2commerce_zone_id',
                'zone_name', 'a.zone_name',
                'zone_code', 'a.zone_code',
                'country_id', 'a.country_id',
                'country_name', 'c.country_name',
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
     * @since   6.0.3
     */
    protected function populateState($ordering = 'c.country_name', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'string');
        $this->setState('filter.enabled', $enabled);

        $country = $this->getUserStateFromRequest($this->context . '.filter.country_id', 'filter_country_id', '', 'string');
        $this->setState('filter.country_id', $country);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.3
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.enabled');
        $id .= ':' . $this->getState('filter.country_id');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.3
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields from zones table
        $query->select(
            $db->quoteName([
                'a.j2commerce_zone_id',
                'a.zone_name',
                'a.zone_code',
                'a.country_id',
                'a.enabled',
                'a.ordering',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_zones', 'a'));

        // Join with countries table to get country name
        $query->select($db->quoteName('c.country_name', 'country_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('a.country_id') . ' = ' . $db->quoteName('c.j2commerce_country_id'));

        // Filter by enabled state
        $enabled = $this->getState('filter.enabled');

        if (is_numeric($enabled)) {
            $enabled = (int) $enabled;
            $query->where($db->quoteName('a.enabled') . ' = :enabled')
                ->bind(':enabled', $enabled, ParameterType::INTEGER);
        } elseif ($enabled === '') {
            $query->where($db->quoteName('a.enabled') . ' IN (0, 1)');
        }

        // Filter by country
        $countryId = $this->getState('filter.country_id');

        if (is_numeric($countryId)) {
            $countryId = (int) $countryId;
            $query->where($db->quoteName('a.country_id') . ' = :country_id')
                ->bind(':country_id', $countryId, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_zone_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.zone_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.zone_code') . ' LIKE :search2 OR ' .
                    $db->quoteName('c.country_name') . ' LIKE :search3' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search)
                    ->bind(':search3', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'c.country_name');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }
}
