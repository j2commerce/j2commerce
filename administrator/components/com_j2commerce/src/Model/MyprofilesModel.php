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
 * My Profiles (User Addresses) model class.
 *
 * Provides methods to retrieve and search user address profiles with
 * associated country and zone name lookups.
 *
 * @since  6.0.2
 */
class MyprofilesModel extends ListModel
{
    /**
     * Get all addresses for a specific user with country and zone names.
     *
     * @param   int|null  $userId  The user ID to get addresses for (null = current user)
     *
     * @return  array  Array of address objects with country_name and zone_name
     *
     * @since   6.0.2
     */
    public function getAddressesByUser(?int $userId = null): array
    {
        if ($userId === null) {
            $user   = Factory::getApplication()->getIdentity();
            $userId = (int) $user->id;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Select all address fields
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
                'a.zone_id',
                'a.country_id',
                'a.phone_1',
                'a.phone_2',
                'a.fax',
                'a.type',
                'a.company',
                'a.tax_number',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_addresses', 'a'));

        // Join countries table for country name
        $query->leftJoin(
            $db->quoteName('#__j2commerce_countries', 'c') .
            ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
        );
        $query->select($db->quoteName('c.country_name'));

        // Join zones table for zone name
        $query->leftJoin(
            $db->quoteName('#__j2commerce_zones', 'z') .
            ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id')
        );
        $query->select($db->quoteName('z.zone_name'));

        // Filter by user ID (logged-in) or email (guest)
        if ($userId > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        } else {
            $email = (Factory::getApplication()->getIdentity()->email) ?? '';
            $query->where($db->quoteName('a.email') . ' = :email')
                ->bind(':email', $email);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get billing address from order information.
     *
     * Searches for an existing address matching the order's billing details.
     *
     * @param   object  $orderInfo  Order information object with billing_* properties
     * @param   string  $email      Customer email address
     *
     * @return  object|null  Address object if found, null otherwise
     *
     * @since   6.0.2
     */
    public function getBillingAddress(object $orderInfo, string $email): ?object
    {
        return $this->findAddressByOrderInfo($orderInfo, $email, 'billing');
    }

    /**
     * Get shipping address from order information.
     *
     * Searches for an existing address matching the order's shipping details.
     *
     * @param   object  $orderInfo  Order information object with shipping_* properties
     * @param   string  $email      Customer email address
     *
     * @return  object|null  Address object if found, null otherwise
     *
     * @since   6.0.2
     */
    public function getShippingAddress(object $orderInfo, string $email): ?object
    {
        return $this->findAddressByOrderInfo($orderInfo, $email, 'shipping');
    }

    /**
     * Find an address matching order information.
     *
     * @param   object  $orderInfo  Order information object
     * @param   string  $email      Customer email address
     * @param   string  $type       Address type ('billing' or 'shipping')
     *
     * @return  object|null  Address object if found, null otherwise
     *
     * @since   6.0.2
     */
    protected function findAddressByOrderInfo(object $orderInfo, string $email, string $type): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_address_id',
            'a.user_id',
            'a.first_name',
            'a.last_name',
            'a.email',
            'a.address_1',
            'a.address_2',
            'a.city',
            'a.zip',
            'a.zone_id',
            'a.country_id',
            'a.phone_1',
            'a.phone_2',
            'a.fax',
            'a.type',
            'a.company',
            'a.tax_number',
        ]));

        $query->from($db->quoteName('#__j2commerce_addresses', 'a'));

        // Scope to current user to prevent IDOR
        $user          = Factory::getApplication()->getIdentity();
        $currentUserId = (int) $user->id;

        if ($currentUserId > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $currentUserId, ParameterType::INTEGER);
        }

        // Build conditions based on type prefix
        $prefix = $type . '_';

        // First name
        $firstNameProperty = $prefix . 'first_name';
        if (!empty($orderInfo->$firstNameProperty)) {
            $firstName = $orderInfo->$firstNameProperty;
            $query->where($db->quoteName('a.first_name') . ' = :firstName')
                ->bind(':firstName', $firstName);
        }

        // Last name
        $lastNameProperty = $prefix . 'last_name';
        if (!empty($orderInfo->$lastNameProperty)) {
            $lastName = $orderInfo->$lastNameProperty;
            $query->where($db->quoteName('a.last_name') . ' = :lastName')
                ->bind(':lastName', $lastName);
        }

        // Country ID
        $countryIdProperty = $prefix . 'country_id';
        if (!empty($orderInfo->$countryIdProperty)) {
            $countryId = (int) $orderInfo->$countryIdProperty;
            $query->where($db->quoteName('a.country_id') . ' = :countryId')
                ->bind(':countryId', $countryId, ParameterType::INTEGER);
        }

        // Zone ID
        $zoneIdProperty = $prefix . 'zone_id';
        if (!empty($orderInfo->$zoneIdProperty)) {
            $zoneId = (int) $orderInfo->$zoneIdProperty;
            $query->where($db->quoteName('a.zone_id') . ' = :zoneId')
                ->bind(':zoneId', $zoneId, ParameterType::INTEGER);
        }

        // Email is always required
        if (!empty($email)) {
            $query->where($db->quoteName('a.email') . ' = :email')
                ->bind(':email', $email);
        }

        // Guard: require at least email + one other condition to prevent returning all addresses
        if (empty($email) && empty($orderInfo->$firstNameProperty) && empty($orderInfo->$lastNameProperty)) {
            return null;
        }

        $db->setQuery($query);

        $result = $db->loadObject();

        return $result ?: null;
    }

    /**
     * Get addresses for current user (alias for getAddressesByUser).
     *
     * Maintains compatibility with legacy getAddress() method name.
     *
     * @return  array  Array of address objects with country_name and zone_name
     *
     * @since   6.0.2
     */
    public function getAddress(): array
    {
        return $this->getAddressesByUser();
    }

    /**
     * Build an SQL query to load the list data.
     *
     * Required by ListModel parent class for pagination support.
     *
     * @return  QueryInterface
     *
     * @since   6.0.2
     */
    protected function getListQuery(): QueryInterface
    {
        $user   = Factory::getApplication()->getIdentity();
        $userId = (int) $user->id;

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

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
                'a.zone_id',
                'a.country_id',
                'a.phone_1',
                'a.phone_2',
                'a.fax',
                'a.type',
                'a.company',
                'a.tax_number',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_addresses', 'a'));

        // Join countries table for country name
        $query->leftJoin(
            $db->quoteName('#__j2commerce_countries', 'c') .
            ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
        );
        $query->select($db->quoteName('c.country_name'));

        // Join zones table for zone name
        $query->leftJoin(
            $db->quoteName('#__j2commerce_zones', 'z') .
            ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id')
        );
        $query->select($db->quoteName('z.zone_name'));

        // Filter by current user
        if ($userId > 0) {
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        // Add ordering
        $query->order($db->quoteName('a.j2commerce_address_id') . ' ASC');

        return $query;
    }
}
