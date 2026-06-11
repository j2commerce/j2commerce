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

class ZonesController extends J2CommerceApiController
{
    protected $contentType = 'zones';

    protected $default_view = 'zones';

    public function displayList()
    {
        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter = InputFilter::getInstance();

        $countryId = $this->input->get('id', 0, 'int');

        if ($countryId) {
            $this->modelState->set('filter.country_id', $countryId);
        }

        if (\array_key_exists('search', $apiFilterInfo)) {
            $this->modelState->set('filter.search', $filter->clean($apiFilterInfo['search'], 'STRING'));
        }

        return parent::displayList();
    }
}
