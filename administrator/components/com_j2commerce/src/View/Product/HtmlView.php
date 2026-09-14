<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Product;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * Products are edited only in their com_content article. A direct view=product request, which
 * DisplayController renders without passing through ProductController, is sent there too.
 */
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        $app->redirect(Route::_(ProductHelper::getArticleEditRoute($app->getInput()->getInt('id', 0)), false));
    }
}
