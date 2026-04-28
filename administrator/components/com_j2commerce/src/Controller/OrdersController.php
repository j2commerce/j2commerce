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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;
use Joomla\Input\Input;

/**
 * Orders list controller class.
 *
 * @since  6.0.7
 */
class OrdersController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * CRITICAL: Use general 'COM_J2COMMERCE' prefix so bulk action messages
     * like N_ITEMS_PUBLISHED use shared language strings, not view-specific ones.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $text_prefix = 'COM_J2COMMERCE';

    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);
    }

    public function getModel($name = 'Order', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to update order status for one or more orders.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function updatestatus(): void
    {
        $this->checkToken();

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $pks      = (array) $this->input->post->get('cid', [], 'int');
        $pks      = array_filter($pks);
        $statusId = $this->input->post->getInt('order_state_id', 0);
        $notify   = $this->input->post->getInt('notify_customer', 0) === 1;
        $comment  = $this->input->post->getString('status_comment', '');

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NO_ORDERS_SELECTED'));
            }

            if ($statusId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NO_STATUS_SELECTED'));
            }

            $model              = $this->getModel();
            $updatedCount       = 0;
            $missingTemplateIds = [];

            foreach ($pks as $pk) {
                if (!$model->updateOrderStatus($pk, $statusId, false, $comment)) {
                    continue;
                }

                $updatedCount++;

                if (!$notify) {
                    continue;
                }

                $order = $model->getItem($pk);

                if (!$order || empty($order->order_id)) {
                    continue;
                }

                $notifyResult = $model->sendOrderNotification($order->order_id, true, true);

                if (($notifyResult['customer_sent'] ?? 0) === 0) {
                    $missingTemplateIds[] = $order->order_id;
                }
            }

            if ($updatedCount > 0) {
                $this->setMessage(Text::plural('COM_J2COMMERCE_N_ORDERS_STATUS_UPDATED', $updatedCount));
            }

            if (!empty($missingTemplateIds)) {
                $this->app->enqueueMessage(
                    Text::sprintf(
                        'COM_J2COMMERCE_ORDERS_STATUS_UPDATED_NO_CUSTOMER_EMAIL',
                        implode(', ', $missingTemplateIds)
                    ),
                    'warning'
                );
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders' . $this->getRedirectToListAppend(), false));
    }

    /**
     * Method to export orders.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function export(): void
    {
        $this->checkToken();

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        // Export functionality will be implemented in a future version
        $this->setMessage(Text::_('COM_J2COMMERCE_EXPORT_NOT_IMPLEMENTED'), 'notice');
        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders' . $this->getRedirectToListAppend(), false));
    }

    public function delete(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_j2commerce')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $pks = (array) $this->input->post->get('cid', [], 'int');
        $pks = array_filter($pks);

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NO_ORDERS_SELECTED'));
            }

            $model = $this->getModel();
            $model->delete($pks);

            $this->setMessage(Text::plural('COM_J2COMMERCE_N_ITEMS_DELETED', \count($pks)));
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=orders' . $this->getRedirectToListAppend(), false));
    }

    public function ajaxUpdateStatus(): void
    {
        if (!$this->validateAjaxToken()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
            return;
        }

        $orderId   = $this->input->post->getInt('order_id', 0);
        $newStatus = $this->input->post->getInt('new_status', 0);
        $notify    = $this->input->post->getInt('notify', 0) === 1;

        // Buffer output — plugin events (onJ2CommerceOrderStatusChange) can produce
        // stray output that would corrupt the JSON response on the first status change.
        ob_start();

        try {
            if ($orderId < 1 || $newStatus < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();

            if (!$model->updateOrderStatus($orderId, $newStatus, false)) {
                $errors = $model->getErrors();
                throw new \Exception(implode("\n", $errors));
            }

            $statusInfo  = $this->getStatusInfo($newStatus);
            $message     = Text::sprintf('COM_J2COMMERCE_ORDER_STATUS_UPDATED_TO', Text::_($statusInfo->orderstatus_name ?? ''));
            $messageType = 'success';

            if ($notify) {
                $order        = $model->getItem($orderId);
                $notifyResult = $order && !empty($order->order_id)
                    ? $model->sendOrderNotification($order->order_id, true, true)
                    : ['customer_sent' => 0, 'errors' => []];

                if (($notifyResult['customer_sent'] ?? 0) === 0) {
                    $reason      = !empty($notifyResult['errors'])
                        ? implode('; ', $notifyResult['errors'])
                        : Text::_('COM_J2COMMERCE_NO_EMAIL_TEMPLATES_FOUND');
                    $message     = Text::sprintf('COM_J2COMMERCE_ORDER_STATUS_UPDATED_NO_CUSTOMER_EMAIL', $reason);
                    $messageType = 'warning';
                }
            }

            $response = [
                'success'     => true,
                'message'     => $message,
                'messageType' => $messageType,
                'data'        => [
                    'statusName' => Text::_($statusInfo->orderstatus_name ?? ''),
                    'cssclass'   => J2htmlHelper::badgeClass($statusInfo->orderstatus_cssclass ?? 'badge text-bg-secondary'),
                ],
            ];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        // Discard any stray output from plugins/model operations
        ob_end_clean();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        $this->app->close();
    }

    public function getQuickiconContent(): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.manage', 'com_j2commerce')) {
            echo new JsonResponse(null, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), true);
            $app->close();
            return;
        }

        $model = $this->getModel('Orders', 'Administrator', ['ignore_request' => true]);
        $count = $model->getPendingCount();

        $result = [
            'amount' => $count,
            'name'   => $count > 0
                ? Text::sprintf('COM_J2COMMERCE_ORDERS_PENDING_COUNT', $count)
                : Text::_('COM_J2COMMERCE_ORDERS'),
            'sronly' => $count > 0
                ? Text::sprintf('COM_J2COMMERCE_ORDERS_PENDING_COUNT', $count)
                : Text::_('COM_J2COMMERCE_ORDERS_NONE_PENDING'),
        ];

        echo new JsonResponse($result);
        $app->close();
    }

    protected function validateAjaxToken(): bool
    {
        $token = \Joomla\CMS\Session\Session::getFormToken();

        if ($token === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        return $this->input->post->get($token, '', 'alnum') === '1';
    }

    private function getStatusInfo(int $statusId): ?object
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('orderstatus_name'),
                $db->quoteName('orderstatus_cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('j2commerce_orderstatus_id') . ' = :statusId')
            ->bind(':statusId', $statusId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject();
    }
}
