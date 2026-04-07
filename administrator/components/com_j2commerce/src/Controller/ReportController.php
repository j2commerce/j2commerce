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
 * Report Controller
 *
 * @since  6.0.0
 */
class ReportController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_REPORT';

    /**
     * The view list to return to after saving/canceling
     *
     * @var    string
     * @since  6.0.0
     */
    protected $view_list = 'reports';

    /**
     * Method to run batch operations.
     *
     * @param   object  $model  The model.
     *
     * @return  boolean  True if successful, false otherwise and internal error is set.
     *
     * @since   6.0.0
     */
    public function batch($model = null)
    {
        $this->checkToken();

        // Set the model
        $model = $this->getModel('Report', '', []);

        // Preset the redirect
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }

    /**
     * Function that allows child controller access to model data
     * after the data has been saved.
     *
     * @param   \Joomla\CMS\MVC\Model\BaseDatabaseModel  $model      The data model object.
     * @param   array                                     $validData  The validated data.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model, $validData = [])
    {
        $task = $this->getTask();

        if ($task == 'save') {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
        }
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   6.0.0
     */
    public function cancel($key = null)
    {
        $this->checkToken();

        $result = parent::cancel($key);

        if ($result) {
            $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=reports', false));
        }

        return $result;
    }

    /**
     * Method to edit an existing record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key
     *                           (sometimes required to avoid router collisions).
     *
     * @return  boolean  True if access level check and checkout passes, false otherwise.
     *
     * @since   6.0.0
     */
    public function edit($key = null, $urlVar = 'extension_id')
    {
        return parent::edit($key, $urlVar);
    }
}
