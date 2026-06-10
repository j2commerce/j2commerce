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

use Joomla\CMS\Filter\InputFilter;
use J2Commerce\Component\J2commerce\Api\Controller\J2CommerceApiController;

class OrdersController extends J2CommerceApiController
{
    protected $contentType = 'orders';

    protected $default_view = 'orders';

    public function displayList()
    {
        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter = InputFilter::getInstance();

        if (\array_key_exists('search', $apiFilterInfo)) {
            $this->modelState->set('filter.search', $filter->clean($apiFilterInfo['search'], 'STRING'));
        }

        if (\array_key_exists('status', $apiFilterInfo)) {
            $this->modelState->set('filter.order_state_id', $filter->clean($apiFilterInfo['status'], 'INT'));
        }

        if (\array_key_exists('customer_id', $apiFilterInfo)) {
            $this->modelState->set('filter.user_id', $filter->clean($apiFilterInfo['customer_id'], 'INT'));
        }

        if (\array_key_exists('date_from', $apiFilterInfo)) {
            $this->modelState->set('filter.since', $filter->clean($apiFilterInfo['date_from'], 'STRING'));
        }

        if (\array_key_exists('date_to', $apiFilterInfo)) {
            $this->modelState->set('filter.until', $filter->clean($apiFilterInfo['date_to'], 'STRING'));
        }

        if (\array_key_exists('payment_type', $apiFilterInfo)) {
            $this->modelState->set('filter.payment_type', $filter->clean($apiFilterInfo['payment_type'], 'STRING'));
        }

        $apiListInfo = $this->input->get('list', [], 'array');

        if (\array_key_exists('ordering', $apiListInfo)) {
            $this->modelState->set('list.ordering', $filter->clean($apiListInfo['ordering'], 'STRING'));
        }

        if (\array_key_exists('direction', $apiListInfo)) {
            $this->modelState->set('list.direction', $filter->clean($apiListInfo['direction'], 'STRING'));
        }

        return parent::displayList();
    }
}
