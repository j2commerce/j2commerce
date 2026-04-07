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

class AddressesModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_address_id', 'a.j2commerce_address_id',
                'user_id', 'a.user_id',
                'type', 'a.type',
            ];
        }

        parent::__construct($config);
    }

    /**
     * Get all addresses for a specific user with country and zone names.
     */
    public function getAddressesByUser(?int $userId = null): array
    {
        if ($userId === null) {
            $user   = Factory::getApplication()->getIdentity();
            $userId = (int) $user->id;
        }

        if ($userId <= 0) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_address_id', 'a.user_id', 'a.first_name', 'a.last_name',
            'a.email', 'a.address_1', 'a.address_2', 'a.city', 'a.zip',
            'a.zone_id', 'a.country_id', 'a.phone_1', 'a.phone_2', 'a.fax',
            'a.type', 'a.company', 'a.tax_number',
        ]))
            ->from($db->quoteName('#__j2commerce_addresses', 'a'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_countries', 'c') .
                ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
            )
            ->select($db->quoteName('c.country_name'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_zones', 'z') .
                ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id')
            )
            ->select($db->quoteName('z.zone_name'))
            ->where($db->quoteName('a.user_id') . ' = :userId')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->order($db->quoteName('a.j2commerce_address_id') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_address_id', 'a.user_id', 'a.first_name', 'a.last_name',
            'a.email', 'a.address_1', 'a.address_2', 'a.city', 'a.zip',
            'a.zone_id', 'a.country_id', 'a.phone_1', 'a.phone_2', 'a.fax',
            'a.type', 'a.company', 'a.tax_number',
        ]))
            ->from($db->quoteName('#__j2commerce_addresses', 'a'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_countries', 'c') .
                ' ON ' . $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('a.country_id')
            )
            ->select($db->quoteName('c.country_name'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_zones', 'z') .
                ' ON ' . $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('a.zone_id')
            )
            ->select($db->quoteName('z.zone_name'));

        $userId = $this->getState('filter.user_id');

        if (!empty($userId)) {
            $userId = (int) $userId;
            $query->where($db->quoteName('a.user_id') . ' = :userId')
                ->bind(':userId', $userId, ParameterType::INTEGER);
        }

        $type = $this->getState('filter.type');

        if (!empty($type)) {
            $query->where($db->quoteName('a.type') . ' = :type')
                ->bind(':type', $type);
        }

        $query->order($db->quoteName('a.j2commerce_address_id') . ' ASC');

        return $query;
    }

    protected function populateState($ordering = 'a.j2commerce_address_id', $direction = 'asc')
    {
        $userId = $this->getUserStateFromRequest($this->context . '.filter.user_id', 'filter_user_id', '');
        $this->setState('filter.user_id', $userId);

        $type = $this->getUserStateFromRequest($this->context . '.filter.type', 'filter_type', '');
        $this->setState('filter.type', $type);

        parent::populateState($ordering, $direction);
    }
}
