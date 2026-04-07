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
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Filtergroup Controller
 *
 * @since  6.0.0
 */
class FiltergroupController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_FILTERGROUP';

    /**
     * Method to check if you can add a new record.
     *
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     *
     * @since  6.0.0
     */
    protected function allowAdd($data = [])
    {
        return $this->app->getIdentity()->authorise('core.create', 'com_j2commerce.filtergroup');
    }

    /**
     * Method to check if you can edit an existing record.
     *
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key; default is id.
     *
     * @return  boolean
     *
     * @since  6.0.0
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        $recordId = (int) isset($data[$key]) ? $data[$key] : 0;

        if ($recordId) {
            return $this->app->getIdentity()->authorise('core.edit', 'com_j2commerce.filtergroup.' . $recordId);
        }

        // Since there is no item, fall back to component permissions.
        return parent::allowEdit($data, $key);
    }

    /**
     * Method to save a record, redirecting new items to the edit view
     * so users can immediately add filters after the first save.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key.
     *
     * @return  boolean  True if save succeeded.
     *
     * @since  6.0.0
     */
    public function save($key = null, $urlVar = 'id')
    {
        $isNew = empty($this->input->getInt('id', 0));

        $result = parent::save($key, $urlVar);

        if ($result && $isNew && $this->getTask() === 'save2close') {
            $model    = $this->getModel();
            $recordId = (int) $model->getState('filtergroup.id');

            if ($recordId) {
                $this->setRedirect(
                    Route::_('index.php?option=com_j2commerce&task=filtergroup.edit&id=' . $recordId, false),
                    Text::_('COM_J2COMMERCE_FILTERGROUP_SAVED_NOW_ADD_FILTERS'),
                    'success'
                );
            }
        }

        return $result;
    }

    /**
     * Method to edit an existing record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key.
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function edit($key = null, $urlVar = null)
    {
        $app      = $this->app;
        $input    = $app->getInput();
        $recordId = $input->getInt('id', 0);

        if (!$this->allowEdit([], 'id')) {
            $this->setMessage(\JText::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $listUrl = 'index.php?option=' . $this->option . '&view=' . $this->view_list . $this->getRedirectToListAppend();
            $this->setRedirect(Route::_($listUrl, false));
            return;
        }

        $editUrl = 'index.php?option=' . $this->option . '&view=' . $this->view_item . '&layout=edit';

        if ($recordId > 0) {
            $editUrl .= '&id=' . $recordId;

            $model = $this->getModel();
            if (method_exists($model, 'checkout')) {
                if (!$model->checkout($recordId)) {
                    $this->setMessage(\JText::sprintf('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED', $model->getError()), 'error');
                    $listUrl = 'index.php?option=' . $this->option . '&view=' . $this->view_list . $this->getRedirectToListAppend();
                    $this->setRedirect(Route::_($listUrl, false));
                    return;
                }
            }
        }

        $this->setRedirect(Route::_($editUrl, false));
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
        // If no record ID is provided, try to get it from the model state
        if ($recordId === null) {
            $model    = $this->getModel();
            $recordId = $model->getState('filtergroup.id');
        }

        // Call parent method with the correct record ID
        return parent::getRedirectToItemAppend($recordId, 'id');
    }
}
