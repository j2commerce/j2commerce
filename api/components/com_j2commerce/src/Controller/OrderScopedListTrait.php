<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Confines a nested list route to the order named in its path.
 *
 * The child tables key on #__j2commerce_orders.order_id, a varchar reference, while the route
 * carries j2commerce_order_id, the primary key — the two are unrelated values, so the id has to
 * be resolved rather than passed through. Resolving it here also gives the route a 404 for an
 * order that does not exist, which a filter applied in the model could only answer as an empty
 * list. populateState() cannot do either: the API builds the model with ignore_request, which
 * sets __state_set and stops it running on this surface.
 */
trait OrderScopedListTrait
{
    protected function scopeToRouteOrder(): void
    {
        // Before the lookup, not after: parent::displayList() would authorise only once the
        // 404 had already told an unauthorised caller whether the order exists. The sibling
        // nested routes assert first for the same reason.
        $this->assertAllowed($this->readAction);

        $pk = $this->input->get('id', 0, 'int');

        if ($pk <= 0) {
            throw new ResourceNotFound('JGLOBAL_RESOURCE_NOT_FOUND', 404);
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->bind(':pk', $pk, ParameterType::INTEGER);

        $db->setQuery($query);
        $orderId = (string) $db->loadResult();

        // An order row with an empty reference cannot be matched against either child table, so
        // it is treated as no order rather than allowed through as "every row".
        if ($orderId === '') {
            throw new ResourceNotFound('JGLOBAL_RESOURCE_NOT_FOUND', 404);
        }

        $this->modelState->set('filter.order_id', $orderId);
    }
}
