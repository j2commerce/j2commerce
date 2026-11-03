<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/** Filters of enabled filter groups, with the group's name and source. */
class FiltersModel extends ListModel
{
    public function getItems()
    {
        $items = parent::getItems();

        foreach ($items ?: [] as $item) {
            $item->j2commerce_filter_id = (int) $item->j2commerce_filter_id;
            $item->group_id             = (int) $item->group_id;
            $item->ordering             = (int) $item->ordering;
        }

        return $items;
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('f.j2commerce_filter_id'),
                $db->quoteName('f.filter_name'),
                $db->quoteName('f.group_id'),
                $db->quoteName('fg.group_name'),
                $db->quoteName('fg.source'),
                $db->quoteName('f.ordering'),
            ])
            ->from($db->quoteName('#__j2commerce_filters', 'f'))
            ->join('INNER', $db->quoteName('#__j2commerce_filtergroups', 'fg') . ' ON ' . $db->quoteName('fg.j2commerce_filtergroup_id') . ' = ' . $db->quoteName('f.group_id'))
            ->where($db->quoteName('fg.enabled') . ' = 1');

        if ($filterId = (int) $this->getState('filter.id')) {
            $query->where($db->quoteName('f.j2commerce_filter_id') . ' = :filterId')
                ->bind(':filterId', $filterId, ParameterType::INTEGER);
        }

        if ($groupId = (int) $this->getState('filter.group_id')) {
            $query->where($db->quoteName('f.group_id') . ' = :groupId')
                ->bind(':groupId', $groupId, ParameterType::INTEGER);
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            $like = '%' . $db->escape($search, true) . '%';
            $query->where('(' . $db->quoteName('f.filter_name') . ' LIKE :search1 OR ' . $db->quoteName('fg.group_name') . ' LIKE :search2)')
                ->bind(':search1', $like)
                ->bind(':search2', $like);
        }

        return $query->order([
            $db->quoteName('fg.ordering'),
            $db->quoteName('fg.group_name'),
            $db->quoteName('f.ordering'),
            $db->quoteName('f.filter_name'),
        ]);
    }
}
