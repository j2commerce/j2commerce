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

use J2Commerce\Component\J2commerce\Api\Controller\J2CommerceApiController;

class OrderitemsController extends J2CommerceApiController
{
    protected $contentType = 'orderitems';

    protected $default_view = 'orderitems';

    public function displayList()
    {
        $orderId = $this->input->get('id', 0, 'int');
        $this->modelState->set('filter.order_id', $orderId);

        return parent::displayList();
    }
}
