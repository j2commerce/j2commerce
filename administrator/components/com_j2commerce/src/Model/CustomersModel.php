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
 * Customers list model class.
 *
 * Displays store customers from the addresses table, grouped by email.
 * Joins with countries and zones for location data.
 *
 * @since  6.0.7
 */
class CustomersModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   6.0.7
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_address_id', 'a.j2commerce_address_id',
                'customer_name',
                'email', 'a.email',
                'first_name', 'a.first_name',
                'last_name', 'a.last_name',
                'address_1', 'a.address_1',
                'city', 'a.city',
                'zip', 'a.zip',
                'country_id', 'a.country_id',
                'zone_id', 'a.zone_id',
                'phone_1', 'a.phone_1',
                'company', 'a.company',
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
     * @since   6.0.7
     */
    protected function populateState($ordering = 'customer_name', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

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
     * @since   6.0.7
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.country_id');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  QueryInterface
     *
     * @since   6.0.7
     */
    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select required fields from addresses table
        $query->select(
            $db->quoteName([
                'a.j2commerce_address_id',
                'a.user_id',
                'a.first_name',
                'a.last_name',
                'a.email',
                'a.address_1',
                'a.address_2',
                'a.city',
                'a.zip',
                'a.country_id',
                'a.zone_id',
                'a.phone_1',
                'a.phone_2',
                'a.company',
                'a.type',
            ])
        );

        // Add computed customer_name field
        $query->select('CONCAT(' . $db->quoteName('a.first_name') . ', ' . $db->quote(' ') . ', ' . $db->quoteName('a.last_name') . ') AS ' . $db->quoteName('customer_name'));

        $query->from($db->quoteName('#__j2commerce_addresses', 'a'));

        // Join with countries table to get country name
        $query->select($db->quoteName('c.country_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_countries', 'c') . ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id'));

        // Join with zones table to get zone name
        $query->select($db->quoteName('z.zone_name'))
            ->join('LEFT', $db->quoteName('#__j2commerce_zones', 'z') . ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id'));

        // Subquery for order count
        $orderSubquery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orders', 'o'))
            ->where($db->quoteName('o.user_email') . ' = ' . $db->quoteName('a.email'));
        $query->select('(' . $orderSubquery . ') AS ' . $db->quoteName('order_count'));

        // Only show records with valid email and first name
        $query->where($db->quoteName('a.email') . ' != ' . $db->quote(''))
            ->where($db->quoteName('a.first_name') . ' != ' . $db->quote(''));

        // Group by email to show each customer once
        $query->group($db->quoteName('a.email'));

        // Filter by country
        $countryId = $this->getState('filter.country_id');

        if (is_numeric($countryId) && $countryId > 0) {
            $countryId = (int) $countryId;
            $query->where($db->quoteName('a.country_id') . ' = :country_id')
                ->bind(':country_id', $countryId, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_address_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.first_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.last_name') . ' LIKE :search2 OR ' .
                    'CONCAT(' . $db->quoteName('a.first_name') . ', ' . $db->quote(' ') . ', ' . $db->quoteName('a.last_name') . ') LIKE :search3 OR ' .
                    $db->quoteName('a.email') . ' LIKE :search4 OR ' .
                    $db->quoteName('a.company') . ' LIKE :search5 OR ' .
                    $db->quoteName('a.city') . ' LIKE :search6' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search)
                    ->bind(':search3', $search)
                    ->bind(':search4', $search)
                    ->bind(':search5', $search)
                    ->bind(':search6', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'customer_name');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }
}
