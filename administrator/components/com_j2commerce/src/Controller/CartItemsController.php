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

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;

/**
 * CartItems Controller (Read-only)
 *
 * @since  6.0.0
 */
class CartItemsController extends AdminController
{
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editorders';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE_CARTITEMS';

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since  6.0.0
     */
    public function getModel($name = 'CartItem', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * The controller is read-only: every inherited write task is declined here.
     *
     * The redirect goes to the orders view because there is no cartitems view — the
     * component ships this controller, CartItemsModel, CartitemTable and the filter
     * form, and nothing else. Orders is where cart items are seen, and is the target
     * the CartsController twin settled on.
     *
     * Overriding the resolved method rather than the task string covers the aliases
     * AdminController registers: unpublish, archive, trash and report all resolve to
     * publish(), and orderup and orderdown to reorder().
     *
     * @since  6.0.0
     */
    private function declineTask(string $key): void
    {
        $this->setMessage(Text::_($key), 'error');
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders', false));
    }

    public function add()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_ADD_NOT_ALLOWED');
    }

    public function edit()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_EDIT_NOT_ALLOWED');
    }

    public function publish()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_PUBLISH_NOT_ALLOWED');
    }

    public function delete()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_DELETE_NOT_ALLOWED');
    }

    public function save()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_SAVE_NOT_ALLOWED');
    }

    public function apply()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_SAVE_NOT_ALLOWED');
    }

    public function checkin()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_CARTITEMS_ERROR_CHECKIN_NOT_ALLOWED');

        return false;
    }

    /**
     * Inherited from AdminController and never served: getModel() resolves CartItem,
     * for which there is no model class, so the inherited body calls reorder() on null.
     *
     * @since  6.0.0
     */
    public function reorder()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED');

        return false;
    }

    /** Same as reorder(): the inherited body calls saveorder() on a model that cannot be built. */
    public function saveorder()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED');

        return false;
    }

    /** An AJAX task: answer the caller rather than setting a redirect nothing follows. */
    public function saveOrderAjax()
    {
        $this->checkToken();

        $this->sendJson(['error' => Text::_('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED')]);
    }

    /**
     * Already survives the missing model — AdminController::runTransition() returns early
     * unless the model is a WorkflowModelInterface — but it returns with no message and no
     * redirect. Decline it here so the outcome is stated.
     *
     * @since  6.0.0
     */
    public function runTransition()
    {
        $this->checkToken();

        $this->declineTask('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED');

        return false;
    }

    /** JSON exit for the AJAX tasks. close() is exit(), so the headers flush first. */
    private function sendJson(mixed $data): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo json_encode($data);
        $this->app->close();
    }

    /**
     * Ajax method to get product type options for filter
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function getProductTypeOptions()
    {
        $this->checkToken();

        $model   = $this->getModel('CartItems');
        $options = $model->getProductTypeOptions();

        $this->sendJson(new JsonResponse($options));
    }
}
