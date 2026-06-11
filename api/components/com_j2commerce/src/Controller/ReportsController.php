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

class ReportsController extends J2CommerceApiController
{
    protected $contentType = 'reports';

    protected $default_view = 'reports';

    public function displayList()
    {
        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter = InputFilter::getInstance();

        $reportType = $this->input->get('report_type', 'sales', 'string');
        $this->modelState->set('filter.report_type', $filter->clean($reportType, 'STRING'));

        if (\array_key_exists('date_from', $apiFilterInfo)) {
            $this->modelState->set('filter.since', $filter->clean($apiFilterInfo['date_from'], 'STRING'));
        }

        if (\array_key_exists('date_to', $apiFilterInfo)) {
            $this->modelState->set('filter.until', $filter->clean($apiFilterInfo['date_to'], 'STRING'));
        }

        if (\array_key_exists('period', $apiFilterInfo)) {
            $this->modelState->set('filter.period', $filter->clean($apiFilterInfo['period'], 'STRING'));
        }

        return parent::displayList();
    }
}
