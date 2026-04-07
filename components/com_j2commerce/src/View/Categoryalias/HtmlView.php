<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Categoryalias;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $catid = $app->getInput()->getInt('id', 0);

        if ($catid > 0) {
            $url = Route::_(RouteHelper::getCategoryRouteInContext($catid), false);
        } else {
            $url = Route::_('index.php?option=com_j2commerce&view=categories', false);
        }

        $app->redirect($url, 301);
    }
}
