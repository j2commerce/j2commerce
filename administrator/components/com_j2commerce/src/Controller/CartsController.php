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

use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use J2Commerce\Component\J2commerce\Administrator\Model\CouponModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VoucherModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * Carts list controller class.
 *
 * Handles admin cart operations: order item management, coupon/voucher application.
 * These are AJAX endpoints used in admin order editing.
 *
 * @since  6.0.0
 */
class CartsController extends AdminController
{
    use WriteAccessTrait;

    protected string $writeAction = 'j2commerce.editorders';

    /**
     * The prefix to use with controller messages.
     *
     * Use general prefix for bulk action messages.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $config  Configuration array for model.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   6.0.0
     */
    public function getModel($name = 'Cart', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * The inherited AdminController list tasks all call a method on the resolved model.
     * CartModel is a BaseDatabaseModel, so none of those methods exist and each task ends
     * in an undefined-method error. The controller extends AdminController to host the
     * admin order-editing AJAX endpoints, not to manage a carts list — there is no carts
     * view for any of them to return to. Decline them instead of failing hard.
     *
     * Overriding the resolved method rather than the task string covers the aliases
     * AdminController registers: unpublish, archive, trash and report all resolve to
     * publish(), and orderup and orderdown to reorder().
     */
    private function declineTask(): void
    {
        $this->setMessage(Text::_('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders', false));
    }

    public function delete()
    {
        $this->checkToken();

        $this->declineTask();
    }

    public function publish()
    {
        $this->checkToken();

        $this->declineTask();
    }

    public function reorder()
    {
        $this->checkToken();

        $this->declineTask();

        return false;
    }

    public function saveorder()
    {
        $this->checkToken();

        $this->declineTask();

        return false;
    }

    public function checkin()
    {
        $this->checkToken();

        $this->declineTask();

        return false;
    }

    /** An AJAX task: answer the caller rather than setting a redirect nothing follows. */
    public function saveOrderAjax()
    {
        $this->checkToken();

        $this->sendJson(['error' => Text::_('COM_J2COMMERCE_ERROR_TASK_NOT_SUPPORTED')]);
    }

    /**
     * Already survives the missing method — AdminController::runTransition() returns early
     * unless the model is a WorkflowModelInterface, which CartModel is not — but it returns
     * with no message and no redirect. Decline it here so the outcome is stated.
     */
    public function runTransition()
    {
        $this->checkToken();

        $this->declineTask();

        return false;
    }

    /**
     * Get MVC Factory.
     *
     * @return  \Joomla\CMS\MVC\Factory\MVCFactoryInterface
     *
     * @since   6.0.0
     */
    protected function getMVCFactory()
    {
        return $this->app->bootComponent('com_j2commerce')->getMVCFactory();
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
     * Add product to order items (admin order editing).
     *
     * AJAX endpoint for adding items to orders from admin interface.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addOrderitems(): void
    {
        $this->checkToken();

        $json = [];

        try {
            /** @var CartModel $model */
            $model = $this->getMVCFactory()->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($model && method_exists($model, 'addCartItem')) {
                $result = $model->addCartItem();

                if (\is_array($result)) {
                    $json = $result;
                } elseif (\is_object($result)) {
                    $json = (array) $result;
                }

                if (isset($json['success']) && $json['success']) {
                    $json['message'] = Text::_('COM_J2COMMERCE_ITEM_ADDED_SUCCESS');
                }
            } else {
                $json['error'] = 'Cart model not available';
            }
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $this->sendJson($json);
    }

    /**
     * Apply coupon to admin order.
     *
     * AJAX endpoint for applying coupons in admin order editing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyCoupon(): void
    {
        $this->checkToken();

        $json = [];

        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $id         = $this->input->getInt('oid', 0);
        $postCoupon = $this->input->getString('coupon', '');

        if (!empty($postCoupon)) {
            // Load coupon model and set coupon via native model
            /** @var CouponModel $couponModel */
            $couponModel = $this->getMVCFactory()->createModel('Coupon', 'Administrator', ['ignore_request' => true]);

            if ($couponModel) {
                $couponModel->setCoupon($postCoupon);
            }
        }

        $url              = 'index.php?option=com_j2commerce&view=orders&task=saveAdminOrder&layout=summary&oid=' . $id;
        $json['success']  = 1;
        $json['redirect'] = $url;

        $this->sendJson($json);
    }

    /**
     * Remove coupon from admin order.
     *
     * AJAX endpoint for removing coupons in admin order editing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeCoupon(): void
    {
        $this->checkToken();

        $json = [];

        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $id      = $this->input->getInt('oid', 0);
        $orderId = $this->input->getString('order_id', '');

        // Remove coupon from session via native model
        /** @var CouponModel $couponModel */
        $couponModel = $this->getMVCFactory()->createModel('Coupon', 'Administrator', ['ignore_request' => true]);

        if ($couponModel) {
            $couponModel->removeCoupon();
        }

        // Remove coupon discount from order via direct query
        if (!empty($orderId)) {
            $this->removeOrderDiscount($orderId, 'coupon');
        }

        $url              = 'index.php?option=com_j2commerce&view=orders&task=saveAdminOrder&layout=summary&oid=' . $id;
        $json['success']  = 1;
        $json['redirect'] = $url;

        $this->sendJson($json);
    }

    /**
     * Apply voucher to admin order.
     *
     * AJAX endpoint for applying vouchers in admin order editing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function applyVoucher(): void
    {
        $this->checkToken();

        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        $voucher = $this->input->getString('voucher', '');

        if (!empty($voucher)) {
            // Load voucher model and set voucher via native model
            /** @var VoucherModel $voucherModel */
            $voucherModel = $this->getMVCFactory()->createModel('Voucher', 'Administrator', ['ignore_request' => true]);

            if ($voucherModel) {
                $voucherModel->setVoucher($voucher);
            }
        }

        $id  = $this->input->getInt('oid', 0);
        $url = 'index.php?option=com_j2commerce&view=orders&task=saveAdminOrder&layout=summary&oid=' . $id;

        $json             = [];
        $json['success']  = 1;
        $json['redirect'] = $url;

        $this->sendJson($json);
    }

    /**
     * Remove voucher from admin order.
     *
     * AJAX endpoint for removing vouchers in admin order editing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function removeVoucher(): void
    {
        $this->checkToken();

        UtilitiesHelper::sendNoCacheHeaders();
        UtilitiesHelper::clearCache();

        // Remove voucher from session via native model
        /** @var VoucherModel $voucherModel */
        $voucherModel = $this->getMVCFactory()->createModel('Voucher', 'Administrator', ['ignore_request' => true]);

        if ($voucherModel) {
            $voucherModel->removeVoucher();
        }

        $id      = $this->input->getInt('oid', 0);
        $orderId = $this->input->getString('order_id', '');

        // Remove voucher discount from order via direct query
        if (!empty($orderId)) {
            $this->removeOrderDiscount($orderId, 'voucher');
        }

        $url = 'index.php?option=com_j2commerce&view=orders&task=saveAdminOrder&layout=summary&oid=' . $id;

        $json             = [];
        $json['redirect'] = $url;
        $json['success']  = 1;

        $this->sendJson($json);
    }

    /**
     * Update admin cart quantities.
     *
     * AJAX endpoint for updating cart quantities in admin order editing.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function update(): void
    {
        $this->checkToken();

        UtilitiesHelper::clearCache();
        UtilitiesHelper::sendNoCacheHeaders();

        $json = [];

        try {
            /** @var CartModel $model */
            $model = $this->getMVCFactory()->createModel('Cart', 'Administrator', ['ignore_request' => true]);

            if ($model && method_exists($model, 'update')) {
                $result = $model->update();

                if (!empty($result['error'])) {
                    $json['error'] = $result['error'];
                } else {
                    $json['success'] = Text::_('COM_J2COMMERCE_CART_UPDATED_SUCCESSFULLY');
                }
            } else {
                $json['error'] = 'Update method not available';
            }
        } catch (\Exception $e) {
            $json['error'] = $e->getMessage();
        }

        $id  = $this->input->getInt('oid', 0);
        $url = 'index.php?option=com_j2commerce&view=orders&task=saveAdminOrder&layout=items&next_layout=items&oid=' . $id;

        $this->sendJson($json);
    }

    /**
     * Remove order discount by type.
     *
     * @param   string  $orderId      The order ID (string format).
     * @param   string  $discountType The discount type (coupon, voucher).
     *
     * @return  bool  True on success.
     *
     * @since   6.0.0
     */
    protected function removeOrderDiscount(string $orderId, string $discountType): bool
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__j2commerce_orderdiscounts'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('discount_type') . ' = :discountType')
            ->bind(':orderId', $orderId)
            ->bind(':discountType', $discountType);

        $db->setQuery($query);

        try {
            $db->execute();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
