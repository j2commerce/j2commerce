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

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Single filter of an enabled group; an unknown or disabled one comes back without an id (404). */
class FilterModel extends BaseDatabaseModel
{
    public function getItem(): object
    {
        $id = (int) $this->getState('filter.id');

        if ($id <= 0) {
            return (object) ['id' => null];
        }

        $list = $this->getMVCFactory()->createModel('Filters', 'Api', ['ignore_request' => true]);
        $list->setState('filter.id', $id);
        $list->setState('list.start', 0);
        $list->setState('list.limit', 1);

        return $list->getItems()[0] ?? (object) ['id' => null];
    }
}
