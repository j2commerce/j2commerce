<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Invoicetemplates Controller
 *
 * @since  6.0.0
 */
class InvoicetemplatesController extends AdminController
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
    public function getModel($name = 'Invoicetemplate', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to publish a list of records.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function publish()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Get items to publish from the request.
        $cid = $this->input->get('cid', [], 'array');
        $data = ['publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2, 'report' => -3];
        $task = $this->getTask();
        $value = \Joomla\Utilities\ArrayHelper::getValue($data, $task, 0, 'int');

        if (empty($cid)) {
            $this->setMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Make sure the item ids are integers
            $cid = \Joomla\Utilities\ArrayHelper::toInteger($cid);

            // Publish the items.
            try {
                $model->publish($cid, $value);
                $ntext = $this->text_prefix;

                if ($value === 1) {
                    $ntext .= '_N_ITEMS_PUBLISHED';
                } elseif ($value === 0) {
                    $ntext .= '_N_ITEMS_UNPUBLISHED';
                } elseif ($value === 2) {
                    $ntext .= '_N_ITEMS_ARCHIVED';
                } else {
                    $ntext .= '_N_ITEMS_TRASHED';
                }

                $this->setMessage(Text::plural($ntext, \count($cid)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));
    }

    /**
     * Method to unpublish a list of records.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function unpublish()
    {
        $this->publish();
    }

    /**
     * Removes an item.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function delete()
    {
        // Check for request forgeries
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        // Get items to remove from the request.
        $cid = $this->input->get('cid', [], 'array');

        if (!\is_array($cid) || \count($cid) < 1) {
            $this->setMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Make sure the item ids are integers
            $cid = \Joomla\Utilities\ArrayHelper::toInteger($cid);

            // Remove the items.
            if ($model->delete($cid)) {
                $this->setMessage(Text::plural($this->text_prefix . '_N_ITEMS_DELETED', \count($cid)));
            } else {
                $this->setMessage($model->getError(), 'error');
            }

            // Invoke the postDelete method to allow for the child class to access the model.
            $this->postDeleteHook($model, $cid);
        }

        $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));
    }

    /**
     * Method to duplicate one or more records.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function duplicate()
    {
        // Check for request forgeries
        Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

        // Get items to duplicate from the request.
        $pks = $this->input->get('cid', [], 'array');

        if (empty($pks)) {
            $this->setMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Make sure the item ids are integers
            $pks = \Joomla\Utilities\ArrayHelper::toInteger($pks);

            try {
                $model->duplicate($pks);
                $this->setMessage(Text::plural($this->text_prefix . '_N_ITEMS_DUPLICATED', \count($pks)));
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));
    }

    /**
     * Check in of one or more records.
     *
     * @return  boolean  True on success
     *
     * @since   6.0.0
     */
    public function checkin()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ids = $this->input->get('cid', [], 'post', 'array');
        $model = $this->getModel();
        $return = $model->checkin($ids);

        if ($return === false) {
            // Checkin failed.
            $message = Text::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError());
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

            return false;
        } else {
            // Checkin succeeded.
            $message = Text::plural($this->text_prefix . '_N_ITEMS_CHECKED_IN', \count($ids));
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);

            return true;
        }
    }

    /**
     * Method to save the submitted ordering values for records via AJAX.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function saveOrderAjax()
    {
        // Get the input
        $pks = $this->input->post->get('cid', [], 'array');
        $order = $this->input->post->get('order', [], 'array');

        // Sanitize the input
        $pks = \Joomla\Utilities\ArrayHelper::toInteger($pks);
        $order = \Joomla\Utilities\ArrayHelper::toInteger($order);

        // Get the model
        $model = $this->getModel();

        // Save the ordering
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo '1';
        }

        // Close the application
        \Joomla\CMS\Factory::getApplication()->close();
    }

    /**
     * Method to reorder a single record.
     *
     * @return  boolean  True on success
     *
     * @since   6.0.0
     */
    public function reorder()
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $ids = $this->input->get('cid', [], 'post', 'array');
        $inc = $this->getTask() === 'orderup' ? -1 : 1;

        $model = $this->getModel();
        $return = $model->reorder($ids, $inc);

        if ($return === false) {
            // Reorder failed.
            $message = Text::sprintf('JLIB_APPLICATION_ERROR_REORDER_FAILED', $model->getError());
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

            return false;
        } else {
            // Reorder succeeded.
            $message = Text::_('JLIB_APPLICATION_SUCCESS_ITEM_REORDERED');
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);

            return true;
        }
    }

    /**
     * Method to run batch operations.
     *
     * @param   object  $model  The model.
     *
     * @return  boolean   True if successful, false otherwise and the model will be set the error
     *
     * @since   6.0.0
     */
    public function batch($model = null)
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Set the model
        $model = $this->getModel('Invoicetemplate', '', []);

        // Preset the redirect
        $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false));

        return parent::batch($model);
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been deleted.
     *
     * @param   \Joomla\CMS\MVC\Model\BaseDatabaseModel  $model  The data model object.
     * @param   integer[]                                $ids    The array of ids for items being deleted.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function postDeleteHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model, $ids = null)
    {
    }
}
