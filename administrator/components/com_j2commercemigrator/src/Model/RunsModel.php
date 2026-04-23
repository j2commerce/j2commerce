<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;

/**
 * List model for the migration run history view.
 */
class RunsModel extends ListModel
{
    protected function getListQuery(): DatabaseQuery
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'j2commerce_migrator_run_id',
            'adapter',
            'status',
            'conflict_mode',
            'batch_size',
            'started_on',
            'finished_on',
            'user_id',
            'counts',
            'error_count',
            'notes',
        ]))
            ->from($db->quoteName('#__j2commerce_migrator_runs'));

        $search = $this->getState('filter.search', '');

        if ($search !== '') {
            $search = '%' . $db->escape($search, true) . '%';
            $query->where(
                '(' . $db->quoteName('adapter') . ' LIKE ' . $db->quote($search) . ')'
            );
        }

        $adapter = $this->getState('filter.adapter', '');

        if ($adapter !== '') {
            $query->where($db->quoteName('adapter') . ' = :adapter')
                ->bind(':adapter', $adapter);
        }

        $status = $this->getState('filter.status', '');

        if ($status !== '') {
            $query->where($db->quoteName('status') . ' = :status')
                ->bind(':status', $status);
        }

        $orderCol = $this->state->get('list.ordering', 'started_on');
        $orderDir = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    protected function populateState($ordering = 'started_on', $direction = 'DESC'): void
    {
        $this->setState('list.ordering', $this->getUserStateFromRequest(
            $this->context . '.ordercol',
            'filter_order',
            $ordering
        ));

        $this->setState('list.direction', $this->getUserStateFromRequest(
            $this->context . '.orderdirn',
            'filter_order_Dir',
            $direction
        ));

        $this->setState('filter.search', $this->getUserStateFromRequest(
            $this->context . '.filter.search',
            'filter_search',
            ''
        ));

        $this->setState('filter.adapter', $this->getUserStateFromRequest(
            $this->context . '.filter.adapter',
            'filter_adapter',
            ''
        ));

        $this->setState('filter.status', $this->getUserStateFromRequest(
            $this->context . '.filter.status',
            'filter_status',
            ''
        ));

        parent::populateState($ordering, $direction);
    }
}
