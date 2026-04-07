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
use Joomla\CMS\Response\JsonResponse;

/**
 * CartItems Controller (Read-only)
 *
 * @since  6.0.0
 */
class CartItemsController extends AdminController
{
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
     * Override add method to prevent adding new cart items from admin
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function add()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_ADD_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override edit method to prevent editing cart items from admin
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function edit()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_EDIT_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override publish method to prevent publishing/unpublishing cart items
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function publish()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_PUBLISH_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override unpublish method to prevent publishing/unpublishing cart items
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function unpublish()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_PUBLISH_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override delete method to prevent deleting cart items from admin list view
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function delete()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_DELETE_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override the save method to prevent saving cart items from admin
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function save()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_SAVE_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override the apply method to prevent applying changes to cart items from admin
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function apply()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_SAVE_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override trash method to prevent trashing cart items
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function trash()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_DELETE_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
    }

    /**
     * Override checkin method to prevent checking in cart items
     *
     * @return  void
     *
     * @since  6.0.0
     */
    public function checkin()
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_CARTITEMS_ERROR_CHECKIN_NOT_ALLOWED'), 'error');
        $this->setRedirect('index.php?option=com_j2commerce&view=cartitems');
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
        $model   = $this->getModel('CartItems');
        $options = $model->getProductTypeOptions();

        echo new JsonResponse($options);

        $this->app->close();
    }


}
