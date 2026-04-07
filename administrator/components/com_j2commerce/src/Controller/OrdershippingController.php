<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Order Shipping Controller
 *
 * @since  6.0.0
 */

class OrdershippingController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $default_view = 'ordershipping';
}
