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
use Joomla\Database\QueryInterface;

class QueuelogsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_queue_log_id', 'a.j2commerce_queue_log_id',
                'queue_type', 'a.queue_type',
                'status', 'a.status',
                'started_at', 'a.started_at',
                'duration_ms', 'a.duration_ms',
                'items_total', 'a.items_total',
                'items_success', 'a.items_success',
                'items_failed', 'a.items_failed',
                'items_skipped', 'a.items_skipped',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.started_at', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $queueType = $this->getUserStateFromRequest($this->context . '.filter.queue_type', 'filter_queue_type', '', 'string');
        $this->setState('filter.queue_type', $queueType);

        $status = $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
        $this->setState('filter.status', $status);

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.queue_type');
        $id .= ':' . $this->getState('filter.status');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_queue_log_id',
            'a.queue_type',
            'a.task_id',
            'a.started_at',
            'a.finished_at',
            'a.duration_ms',
            'a.items_total',
            'a.items_success',
            'a.items_failed',
            'a.items_skipped',
            'a.status',
            'a.error_message',
            'a.details',
        ]));

        $query->from($db->quoteName('#__j2commerce_queue_logs', 'a'));

        $queueType = $this->getState('filter.queue_type');

        if (!empty($queueType)) {
            $query->where($db->quoteName('a.queue_type') . ' = :queue_type')
                ->bind(':queue_type', $queueType);
        }

        $status = $this->getState('filter.status');

        if (!empty($status)) {
            $query->where($db->quoteName('a.status') . ' = :status')
                ->bind(':status', $status);
        }

        $search = $this->getState('filter.search');

        if (!empty($search)) {
            $searchStr = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where($db->quoteName('a.error_message') . ' LIKE :search')
                ->bind(':search', $searchStr);
        }

        $orderCol = $this->state->get('list.ordering', 'a.started_at');
        $orderDir = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
