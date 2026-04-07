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
 * Taxrules list model class.
 *
 * @since  6.0.3
 */
class TaxrulesModel extends ListModel
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
                'j2commerce_taxrule_id', 'a.j2commerce_taxrule_id',
                'taxprofile_id', 'a.taxprofile_id',
                'taxrate_id', 'a.taxrate_id',
                'address', 'a.address',
                'ordering', 'a.ordering',
                'taxprofile_name', 'p.taxprofile_name',
                'taxrate_name', 'r.taxrate_name',
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
    protected function populateState($ordering = 'a.ordering', $direction = 'asc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $taxprofile = $this->getUserStateFromRequest($this->context . '.filter.taxprofile_id', 'filter_taxprofile_id', '', 'int');
        $this->setState('filter.taxprofile_id', $taxprofile);

        $address = $this->getUserStateFromRequest($this->context . '.filter.address', 'filter_address', '', 'string');
        $this->setState('filter.address', $address);

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
        $id .= ':' . $this->getState('filter.taxprofile_id');
        $id .= ':' . $this->getState('filter.address');

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

        // Select required fields
        $query->select(
            $db->quoteName([
                'a.j2commerce_taxrule_id',
                'a.taxprofile_id',
                'a.taxrate_id',
                'a.address',
                'a.ordering',
            ])
        );

        $query->from($db->quoteName('#__j2commerce_taxrules', 'a'));

        // Join with taxprofiles table
        $query->select($db->quoteName('p.taxprofile_name'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_taxprofiles', 'p')
                . ' ON ' . $db->quoteName('a.taxprofile_id') . ' = ' . $db->quoteName('p.j2commerce_taxprofile_id')
            );

        // Join with taxrates table
        $query->select($db->quoteName('r.taxrate_name'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_taxrates', 'r')
                . ' ON ' . $db->quoteName('a.taxrate_id') . ' = ' . $db->quoteName('r.j2commerce_taxrate_id')
            );

        // Filter by taxprofile
        $taxprofile_id = $this->getState('filter.taxprofile_id');

        if (is_numeric($taxprofile_id) && $taxprofile_id > 0) {
            $taxprofile_id = (int) $taxprofile_id;
            $query->where($db->quoteName('a.taxprofile_id') . ' = :taxprofile_id')
                ->bind(':taxprofile_id', $taxprofile_id, ParameterType::INTEGER);
        }

        // Filter by address type
        $address = $this->getState('filter.address');

        if (!empty($address)) {
            $query->where($db->quoteName('a.address') . ' = :address')
                ->bind(':address', $address);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_taxrule_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('p.taxprofile_name') . ' LIKE :search1 OR ' .
                    $db->quoteName('r.taxrate_name') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search);
            }
        }

        // Add ordering clause
        $orderCol  = $this->state->get('list.ordering', 'a.ordering');
        $orderDir  = $this->state->get('list.direction', 'ASC');
        $ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDir);

        $query->order($ordering);

        return $query;
    }
}
