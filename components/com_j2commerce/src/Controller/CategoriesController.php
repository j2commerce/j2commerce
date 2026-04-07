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

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Categories list controller for site frontend.
 *
 * @since  6.0.0
 */
class CategoriesController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $default_view = 'categories';
}
