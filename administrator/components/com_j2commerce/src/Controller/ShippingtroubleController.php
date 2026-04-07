<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Shipping Trouble Controller
 *
 * @since  6.0.0
 */
class ShippingtroubleController extends BaseController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_SHIPPINGTROUBLE';

    /**
     * Method to display the troubleshooter view
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link InputFilter::clean()}.
     *
     * @return  static  This object to support chaining.
     *
     * @since   6.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $input = $this->app->getInput();
        $vName = $input->get('view', 'shippingtroubles');

        $input->set('view', $vName);

        return parent::display($cachable, $urlparams);
    }
}
