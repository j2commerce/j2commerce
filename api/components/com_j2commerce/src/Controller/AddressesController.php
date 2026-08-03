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

use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;

class AddressesController extends J2CommerceApiController
{
    protected $contentType = 'addresses';

    protected $default_view = 'addresses';

    protected string $readAction = 'j2commerce.vieworders';

    protected string $writeAction = 'j2commerce.editorders';

    public function displayList()
    {
        // This is the only route to the address book, and a list of it is the same bulk read of
        // customer detail the order and customer lists are, so it asserts the same pair.
        $this->assertAllowed('j2commerce.exportorders');

        // The model applies its user predicate only for a non-empty id, and populateState() does
        // not run on this surface, so a nested list has to reject an id that names no customer
        // rather than pass one through as "every row".
        $userId = $this->input->get('id', 0, 'int');

        if ($userId <= 0) {
            throw new ResourceNotFound('JGLOBAL_RESOURCE_NOT_FOUND', 404);
        }

        $this->modelState->set('filter.user_id', $userId);

        return parent::displayList();
    }
}
