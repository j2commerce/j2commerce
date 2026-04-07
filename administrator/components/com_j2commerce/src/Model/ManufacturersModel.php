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
 * Manufacturers list model class.
 *
 * @since  6.0.6
 */
class ManufacturersModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.6
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_manufacturer_id', 'a.j2commerce_manufacturer_id',
                'address_id', 'a.address_id',
                'brand_desc_id', 'a.brand_desc_id',
                'enabled', 'a.enabled',
                'ordering', 'a.ordering',
                'company', 'addr.company',
                'city', 'addr.city',
                'country_id', 'addr.country_id',
                'zone_id', 'addr.zone_id',
                'country_name', 'c.country_name',
                'zone_name', 'z.zone_name',
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
     * @since   6.0.6
     */
    protected function populateState($ordering = 'addr.company', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $enabled = $this->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'string');
        $this->setState('filter.enabled', $enabled);

        $countryId = $this->getUserStateFromRequest($this->context . '.filter.country_id', 'filter_country_id', '', 'string');
        $this->setState('filter.country_id', $countryId);

        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   6.0.6
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
     * @since   6.0.6
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields from the manufacturers table
        $query->select(
            $db->quoteName([
                'a.j2commerce_manufacturer_id',
                'a.address_id',
                'a.brand_desc_id',
                'a.enabled',
                'a.ordering',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_manufacturers', 'a'));

        // Join with the addresses table to get company, city, country_id, zone_id
        $query->select(
            $db->quoteName([
                'addr.company',
                'addr.city',
                'addr.country_id',
                'addr.zone_id',
            ])
        )
            ->join('LEFT', $db->quoteName('#__j2commerce_addresses', 'addr') . ' ON ' . $db->quoteName('addr.j2commerce_address_id') . ' = ' . $db->quoteName('a.address_id'));

        // Join with countries table to get country name
        $query->select($db->quoteName('c.country_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('addr.country_id'));

        // Join with zones table to get zone name
        $query->select($db->quoteName('z.zone_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_zones', 'z') . ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('addr.zone_id'));

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

        if (is_numeric($countryId) && $countryId > 0) {
            $countryId = (int) $countryId;
            $query->where($db->quoteName('addr.country_id') . ' = :country_id')
                ->bind(':country_id', $countryId, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_manufacturer_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('addr.company') . ' LIKE :search1 OR ' .
                    $db->quoteName('addr.city') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'addr.company');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }
}
