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
                'order_count',
                'first_order_on',
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

        $minOrders = $this->getUserStateFromRequest($this->context . '.filter.min_orders', 'filter_min_orders', '', 'string');
        $this->setState('filter.min_orders', $minOrders);

        $since = $this->getUserStateFromRequest($this->context . '.filter.since', 'filter_since', '', 'string');
        $this->setState('filter.since', $since);

        $until = $this->getUserStateFromRequest($this->context . '.filter.until', 'filter_until', '', 'string');
        $this->setState('filter.until', $until);

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
        $id .= ':' . $this->getState('filter.min_orders');
        $id .= ':' . $this->getState('filter.since');
        $id .= ':' . $this->getState('filter.until');

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

        // Subquery for the date the customer first ordered. The addresses table has no created
        // date, so acquisition date is derived from the earliest matching order.
        $firstOrderSubquery = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('o2.created_on') . ')')
            ->from($db->quoteName('#__j2commerce_orders', 'o2'))
            ->where($db->quoteName('o2.user_email') . ' = ' . $db->quoteName('a.email'));
        $query->select('(' . $firstOrderSubquery . ') AS ' . $db->quoteName('first_order_on'));

        // Resolve one address per customer email.
        //
        // Grouping the outer query by email is rejected by ONLY_FULL_GROUP_BY (MySQL's
        // default sql_mode since 5.7) because none of the selected columns are functionally
        // dependent on email. Pick the lowest address id per email in a subquery and join
        // the full row back instead — the same shape ProductsModel uses to resolve one image
        // and one master variant per product.
        //
        // Every list filter belongs on the subquery alias `ax`, NOT on `a`: a customer must
        // surface when ANY of their addresses matches, which is what filtering before the
        // GROUP BY used to give us. Filtering `a` would hide a customer whose lowest-id
        // address falls outside the filter.
        $addressSubquery = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('ax.j2commerce_address_id') . ')')
            ->from($db->quoteName('#__j2commerce_addresses', 'ax'))
            ->where($db->quoteName('ax.email') . ' != ' . $db->quote(''))
            ->where($db->quoteName('ax.first_name') . ' != ' . $db->quote(''))
            ->group($db->quoteName('ax.email'));

        // Filter by country
        $countryId = $this->getState('filter.country_id');

        if (is_numeric($countryId) && $countryId > 0) {
            $countryId = (int) $countryId;
            $addressSubquery->where($db->quoteName('ax.country_id') . ' = :country_id');
            $query->bind(':country_id', $countryId, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $addressSubquery->where($db->quoteName('ax.j2commerce_address_id') . ' = :searchId');
                $query->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $addressSubquery->where(
                    '(' .
                    $db->quoteName('ax.first_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('ax.last_name') . ' LIKE :search2 OR ' .
                    'CONCAT(' . $db->quoteName('ax.first_name') . ', ' . $db->quote(' ') . ', ' . $db->quoteName('ax.last_name') . ') LIKE :search3 OR ' .
                    $db->quoteName('ax.email') . ' LIKE :search4 OR ' .
                    $db->quoteName('ax.company') . ' LIKE :search5 OR ' .
                    $db->quoteName('ax.city') . ' LIKE :search6' .
                    ')'
                );
                $query->bind(':search1', $search)
                    ->bind(':search2', $search)
                    ->bind(':search3', $search)
                    ->bind(':search4', $search)
                    ->bind(':search5', $search)
                    ->bind(':search6', $search);
            }
        }

        $query->where($db->quoteName('a.j2commerce_address_id') . ' IN (' . $addressSubquery . ')');

        // Order-derived filters key off the customer's email, which every address in the
        // resolved group shares, so they belong on the outer query rather than on `ax`.
        // The outer query has no GROUP BY to hang a HAVING on, so each computed column's
        // subquery is repeated in the WHERE clause instead of referenced by its alias.

        // Filter by minimum order count
        $minOrders = $this->getState('filter.min_orders');

        if (is_numeric($minOrders) && (int) $minOrders > 0) {
            $minOrders = (int) $minOrders;
            $query->where('(' . $orderSubquery . ') >= :minOrders')
                ->bind(':minOrders', $minOrders, ParameterType::INTEGER);
        }

        // Date range on the first order
        $since = $this->normaliseFilterDate($this->getState('filter.since', ''));

        if ($since !== null) {
            $query->where('(' . $firstOrderSubquery . ') >= :since')
                ->bind(':since', $since);
        }

        $until = $this->normaliseFilterDate($this->getState('filter.until', ''), true);

        if ($until !== null) {
            $query->where('(' . $firstOrderSubquery . ') <= :until')
                ->bind(':until', $until);
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'customer_name');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }

    /**
     * ListModel::populateState() merges the request filter array into state unfiltered, so a
     * filter date arrives as arbitrary text. Returns null when it is not usable, which drops
     * the predicate rather than throwing out of getListQuery().
     */
    private function normaliseFilterDate(mixed $value, bool $endOfDay = false): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        // A date-only upper bound must cover the whole day, otherwise it excludes
        // almost all of the day the user asked for.
        if ($endOfDay && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $value .= ' 23:59:59';
        }

        try {
            return $this->convertTimeToUtc($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Filter dates are entered in the site timezone; created_on is stored in UTC. */
    protected function convertTimeToUtc(string $datetime, string $format = 'Y-m-d H:i:s'): string
    {
        $tz   = Factory::getApplication()->get('offset', 'UTC');
        $date = Factory::getDate($datetime, $tz);
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format($format);
    }
}
