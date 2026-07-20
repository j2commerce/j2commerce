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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;

/**
 * Coupon item controller class.
 *
 * Handles single-item operations: edit, save, apply, cancel.
 * For bulk operations (publish, unpublish, delete, batch), see CouponsController.
 *
 * @since  6.0.6
 */
class CouponController extends FormController
{
    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $option = 'com_j2commerce';

    /**
     * The URL view item variable.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $view_item = 'coupon';

    /**
     * The URL view list variable.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $view_list = 'coupons';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.6
     */
    protected $text_prefix = 'COM_J2COMMERCE_COUPON';

    /**
     * Method to edit an existing record.
     *
     * CRITICAL: We must explicitly set $urlVar to 'id' because Joomla's FormController
     * defaults to using the Table's primary key name (j2commerce_coupon_id) as the URL
     * parameter. Since our URLs use 'id' (standard Joomla convention), we override here.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if access level check passes, false otherwise.
     *
     * @since   6.0.6
     */
    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable for the id.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   6.0.6
     */
    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.6
     */
    public function cancel($key = 'id')
    {
        $result = parent::cancel($key);

        // When editing inside a picker modal, land on the modalreturn layout so the dialog closes cleanly.
        if ($result && $this->input->get('layout') === 'modal') {
            $id     = $this->input->getInt('id');
            $return = 'index.php?option=' . $this->option . '&view=' . $this->view_item
                . $this->getRedirectToItemAppend($id) . '&layout=modalreturn&from-task=cancel';

            $this->setRedirect(Route::_($return, false));
        }

        return $result;
    }

    /**
     * Redirect a modal save to the modalreturn layout so the newly saved coupon
     * is reported back to the picker field.
     *
     * @param   BaseDatabaseModel  $model      The data model object.
     * @param   array              $validData  The validated data.
     *
     * @return  void
     *
     * @since   6.0.6
     */
    protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
    {
        if ($this->input->get('layout') === 'modal' && $this->task === 'save') {
            $id     = $model->getState($model->getName() . '.id', '');
            $return = 'index.php?option=' . $this->option . '&view=' . $this->view_item
                . $this->getRedirectToItemAppend($id) . '&layout=modalreturn&from-task=save';

            $this->setRedirect(Route::_($return, false));
        }
    }
}
