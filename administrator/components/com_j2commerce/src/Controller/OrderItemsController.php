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

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Order controller class.
 *
 * @since  6.0.0
 */
class OrderItemsController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_ORDERITEMS';

    /**
     * Method to run batch operations.
     *
     * @param   object  $model  The model.
     *
     * @return  boolean   True if successful, false otherwise and internal error is set.
     *
     * @since  6.0.0
     */
    public function batch($model = null)
    {
        $this->checkToken();

        // Set the model
        $model = $this->getModel('OrderItems', 'Administrator', []);

        // Preset the redirect
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }

    /**
     * Method override to check if you can add a new record.
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     *
     * @since  6.0.0
     */
    protected function allowAdd($data = [])
    {
        return $this->app->getIdentity()->authorise('core.create', $this->option);
    }

    /**
     * Method to check if you can add a new record.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     *
     * @since  6.0.0
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        $recordId = (int) isset($data[$key]) ? $data[$key] : 0;
        $user     = $this->app->getIdentity();

        // Check general edit permission first.
        if ($user->authorise('core.edit', $this->option . '.order.' . $recordId)) {
            return true;
        }

        // Fallback on edit.own.
        // First test if the permission is available.
        if ($user->authorise('core.edit.own', $this->option . '.order.' . $recordId)) {
            // Now test the owner is the user.
            $ownerId = (int) isset($data['created_by']) ? $data['created_by'] : 0;

            if (empty($ownerId) && $recordId) {
                // Need to do a lookup from the model.
                $record = $this->getModel()->getItem($recordId);

                if (empty($record)) {
                    return false;
                }

                $ownerId = $record->created_by;
            }

            // If the owner matches 'me' then do the test.
            if ($ownerId == $user->get('id')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the URL arguments to append to an item redirect.
     *
     * @param   integer  $recordId  The primary key id for the item.
     * @param   string   $urlVar    The name of the URL variable for the id.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since  6.0.0
     */
    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        $tmpl   = $this->input->get('tmpl');
        $layout = $this->input->get('layout', 'edit', 'string');
        $append = '';

        // Setup redirect info.
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        if ($layout) {
            $append .= '&layout=' . $layout;
        }

        if ($recordId) {
            $append .= '&' . $urlVar . '=' . $recordId;
        }

        return $append;
    }

    /**
     * Gets the URL arguments to append to a list redirect.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since  6.0.0
     */
    protected function getRedirectToListAppend()
    {
        $tmpl   = $this->input->get('tmpl');
        $append = '';

        // Setup redirect info.
        if ($tmpl) {
            $append .= '&tmpl=' . $tmpl;
        }

        return $append;
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     *
     * @param   \Joomla\CMS\MVC\Model\BaseDatabaseModel  $model      The data model object.
     * @param   array                                    $validData  The validated data.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model, $validData = [])
    {
        $task = $this->getTask();

        if ($task === 'save') {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders', false));
        }
    }

    /**
     * Method to cancel an edit operation.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since  6.0.0
     */
    public function cancel($key = 'j2commerce_order_id')
    {
        $this->checkToken();

        $recordId = $this->input->getInt($key);

        // If we have a record ID, we need to check it out
        if ($recordId > 0) {
            $model = $this->getModel();
            $model->checkin($recordId);
        }

        // Set the redirect to orders list view
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders' . $this->getRedirectToListAppend(), false));

        return true;
    }

    /**
     * Method to add a history entry to an order.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function addHistory()
    {
        // Check for request forgeries
        $this->checkToken();

        $user    = $this->app->getIdentity();
        $id      = $this->input->getInt('id');
        $status  = $this->input->getInt('status');
        $comment = $this->input->getString('comment', '');
        $notify  = $this->input->getBool('notify', false);

        // Access checks.
        if (!$user->authorise('core.edit.state', 'com_j2commerce.order.' . $id)) {
            $this->app->enqueueMessage(\Joomla\CMS\Language\Text::_('JERROR_CORE_EDIT_STATE_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=order&id=' . $id, false));
            return;
        }

        // Get the model.
        $model = $this->getModel();

        // Add history entry
        if ($model->addOrderHistory($id, $status, $comment, $notify)) {
            $this->app->enqueueMessage(\Joomla\CMS\Language\Text::_('COM_J2COMMERCE_ORDER_HISTORY_ADDED'), 'success');
        } else {
            $this->app->enqueueMessage($model->getError(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=order&id=' . $id, false));
    }
}
