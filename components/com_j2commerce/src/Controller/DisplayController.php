<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'products';

    public function display($cachable = false, $urlparams = [])
    {
        $vName = $this->input->getCmd('view', $this->default_view);

        // Track product hits when viewing single product (same pattern as com_content articles)
        if ($vName === 'product' && \in_array($this->input->getMethod(), ['GET', 'POST'])) {
            if ($model = $this->getModel($vName)) {
                if (ComponentHelper::getParams('com_j2commerce')->get('record_hits', 1) == 1) {
                    $model->hit();
                }
            }
        }

        return parent::display($cachable, $urlparams);
    }
}
