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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Payment Methods Controller
 *
 * @since  6.0.0
 */
class PaymentmethodsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since   6.0.0
     */
    public function getModel($name = 'Paymentmethod', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to checkin a list of items
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function checkin()
    {
        // Check for request forgeries
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Get items to checkin from the request.
        $cid = (array) $this->input->get('cid', [], 'int');

        if (empty($cid)) {
            $this->setMessage(Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel('Paymentmethod', 'Administrator');

            // Make sure the item ids are integers
            $cid = array_map('intval', $cid);

            // Checkin the items.
            try {
                $model->checkin($cid);
                $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_CHECKED_IN', \count($cid)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=paymentmethods', false));
    }
}
