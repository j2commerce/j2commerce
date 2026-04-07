<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * J2Commerce master display controller.
 */
class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     */
    protected $default_view = 'dashboard';

    /** Views where directory casing differs from model class. */
    private const MODEL_NAME_MAP = [
        'productfiles' => 'ProductFiles',
    ];

    public function display($cachable = false, $urlparams = [])
    {
        $viewName = strtolower($this->input->get('view', $this->default_view));

        if (isset(self::MODEL_NAME_MAP[$viewName])) {
            $view  = $this->getView($viewName, 'html');
            $model = $this->getModel(self::MODEL_NAME_MAP[$viewName]);

            if ($model) {
                $view->setModel($model, true);
            }
        }

        return parent::display($cachable, $urlparams);
    }
}
