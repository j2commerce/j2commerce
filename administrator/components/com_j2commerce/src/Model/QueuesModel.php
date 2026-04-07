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

class QueuesModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'j2commerce_queue_id', 'a.j2commerce_queue_id',
                'queue_type', 'a.queue_type',
                'item_type', 'a.item_type',
                'status', 'a.status',
                'priority', 'a.priority',
                'attempt_count', 'a.attempt_count',
                'next_attempt_at', 'a.next_attempt_at',
                'created_on', 'a.created_on',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.created_on', $direction = 'desc'): void
    {
        $search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $queueType = $this->getUserStateFromRequest($this->context . '.filter.queue_type', 'filter_queue_type', '', 'string');
        $this->setState('filter.queue_type', $queueType);

        $itemType = $this->getUserStateFromRequest($this->context . '.filter.item_type', 'filter_item_type', '', 'string');
        $this->setState('filter.item_type', $itemType);

        $status = $this->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string');
        $this->setState('filter.status', $status);

        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.queue_type');
        $id .= ':' . $this->getState('filter.item_type');
        $id .= ':' . $this->getState('filter.status');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
            'a.j2commerce_queue_id',
            'a.relation_id',
            'a.queue_type',
            'a.item_type',
            'a.priority',
            'a.status',
            'a.error_message',
            'a.attempt_count',
            'a.max_attempts',
            'a.next_attempt_at',
            'a.created_on',
        ]));

        $query->from($db->quoteName('#__j2commerce_queues', 'a'));

        $queueType = $this->getState('filter.queue_type');

        if (!empty($queueType)) {
            $query->where($db->quoteName('a.queue_type') . ' = :queue_type')
                ->bind(':queue_type', $queueType);
        }

        $itemType = $this->getState('filter.item_type');

        if (!empty($itemType)) {
            $query->where($db->quoteName('a.item_type') . ' = :item_type')
                ->bind(':item_type', $itemType);
        }

        $status = $this->getState('filter.status');

        if (!empty($status)) {
            $query->where($db->quoteName('a.status') . ' = :status')
                ->bind(':status', $status);
        }

        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_queue_id') . ' = :searchId')
                    ->bind(':searchId', $searchId, ParameterType::INTEGER);
            } else {
                $searchStr = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' .
                    $db->quoteName('a.relation_id') . ' LIKE :search1 OR ' .
                    $db->quoteName('a.error_message') . ' LIKE :search2' .
                    ')'
                )
                    ->bind(':search1', $searchStr)
                    ->bind(':search2', $searchStr);
            }
        }

        $orderCol = $this->state->get('list.ordering', 'a.created_on');
        $orderDir = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }
}
