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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderPayGrantHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\PackingSlipHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\QueueHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Order item controller class.
 *
 * Handles single-item operations: view, edit status, add notes.
 * Orders are typically view-only after creation (cannot be edited like regular entities).
 *
 * @since  6.0.7
 */
class OrderController extends FormController
{
    protected $option = 'com_j2commerce';

    protected $view_item = 'order';

    protected $view_list = 'orders';

    protected $text_prefix = 'COM_J2COMMERCE';

    public function edit($key = null, $urlVar = 'id')
    {
        return parent::edit($key, $urlVar);
    }

    /**
     * Toolbar Save/Apply for the order edit layout. Routes through the same
     * persistence as the AJAX endpoints (the generic jform/Table path cannot
     * persist item lines or shipping details).
     */
    public function save($key = null, $urlVar = 'id')
    {
        $this->checkToken();

        $orderId = $this->input->getInt('id', 0);
        $editUrl = Route::_('index.php?option=com_j2commerce&view=order&layout=edit&id=' . $orderId, false);
        $listUrl = Route::_('index.php?option=com_j2commerce&view=orders', false);

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_j2commerce')
            || !J2CommerceHelper::canAccess('j2commerce.editorders')
        ) {
            $this->setRedirect($listUrl, Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');

            return false;
        }

        $model = $this->getModel();
        $order = $orderId > 0 ? $model->getItem($orderId) : null;

        if (!$order || empty($order->order_id)) {
            $this->setRedirect($listUrl, Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'), 'error');

            return false;
        }

        try {
            $jform = $this->input->post->get('jform', [], 'array');
            $qty   = $this->input->post->get('orderitem_qty', [], 'array');
            $price = $this->input->post->get('orderitem_price_edit', [], 'array');

            $model->saveOrderEditData($orderId, $jform);

            if (!empty($qty) || !empty($price)) {
                $model->updateOrderItemLines($order->order_id, $qty, $price, $model->isStockCommitted($order));
            }

            $model->recalculateOrderTotals($order->order_id);

            $notify    = $this->input->post->getInt('notify_customer', 0) === 1;
            $newStatus = $this->input->post->getInt('order_state_id', 0);

            if ($newStatus > 0 && $newStatus !== (int) $order->order_state_id) {
                // A status change writes its own history + fires the status event and
                // (when notifying) sends its own notification, so don't also fire a plain one.
                if (!$model->updateOrderStatus($orderId, $newStatus, $notify)) {
                    $this->app->enqueueMessage(
                        implode("\n", $model->getErrors()) ?: Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED'),
                        'warning'
                    );
                }
            } elseif ($notify) {
                $model->sendOrderNotification($order->order_id, true, false);
            }

            // Attribution: who edited this order from the admin editor. Written on
            // the deliberate toolbar Save only — the per-tab AJAX saves would spam.
            $actingUser = $this->app->getIdentity();
            $model->addAdminNote(
                (string) $order->order_id,
                $newStatus > 0 ? $newStatus : (int) $order->order_state_id,
                Text::sprintf('COM_J2COMMERCE_ORDER_EDITED_BY_ADMIN', (string) $actingUser->name),
                'system_note'
            );

            // Actions log — fire-and-forget; logging must never break saving.
            try {
                \Joomla\CMS\Plugin\PluginHelper::importPlugin('actionlog');
                $this->app->getDispatcher()->dispatch(
                    'onJ2CommerceAfterAdminOrderEdit',
                    new \Joomla\Event\Event('onJ2CommerceAfterAdminOrderEdit', [
                        (string) $order->order_id,
                        $orderId,
                        (int) $actingUser->id,
                    ])
                );
            } catch (\Throwable $e) {
                // Ignore.
            }

            $this->setMessage(Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED'));
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect($editUrl);

            return false;
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
            $this->setRedirect($editUrl);

            return false;
        }

        // Save & Close lands on the order VIEW (not the list) so the owner sees
        // the result of their edit; Apply stays on the editor.
        $viewUrl = Route::_('index.php?option=com_j2commerce&view=order&layout=view&id=' . $orderId, false);
        $this->setRedirect($this->getTask() === 'apply' ? $editUrl : $viewUrl);

        return true;
    }

    public function cancel($key = 'id')
    {
        return parent::cancel($key);
    }

    /**
     * Update order status from the single order view.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function updatestatus(): void
    {
        $this->checkToken();

        $orderId  = $this->input->getInt('id', 0);
        $statusId = $this->input->post->getInt('order_state_id', 0);
        $notify   = $this->input->post->getInt('notify_customer', 0) === 1;
        $comment  = $this->input->post->getString('status_comment', '');

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if ($statusId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NO_STATUS_SELECTED'));
            }

            $model = $this->getModel();

            if ($model->updateOrderStatus($orderId, $statusId, false, $comment)) {
                if ($notify) {
                    $order        = $model->getItem($orderId);
                    $notifyResult = $order && !empty($order->order_id)
                        ? $model->sendOrderNotification($order->order_id, true, true)
                        : ['customer_sent' => 0, 'errors' => []];

                    if (($notifyResult['customer_sent'] ?? 0) === 0) {
                        $reason = !empty($notifyResult['errors'])
                            ? implode('; ', $notifyResult['errors'])
                            : Text::_('COM_J2COMMERCE_NO_EMAIL_TEMPLATES_FOUND');

                        $this->app->enqueueMessage(
                            Text::sprintf('COM_J2COMMERCE_ORDER_STATUS_UPDATED_NO_CUSTOMER_EMAIL', $reason),
                            'warning'
                        );
                    } else {
                        $this->setMessage(Text::_('COM_J2COMMERCE_ORDER_STATUS_UPDATED'));
                    }
                } else {
                    $this->setMessage(Text::_('COM_J2COMMERCE_ORDER_STATUS_UPDATED'));
                }
            } else {
                $errors = $model->getErrors();
                throw new \Exception(implode("\n", $errors));
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=order&layout=edit&id=' . $orderId, false));
    }

    /**
     * Add a note to order history.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function addnote(): void
    {
        $this->checkToken();

        $orderId = $this->input->getInt('id', 0);
        $comment = $this->input->post->getString('order_note', '');
        $notify  = $this->input->post->getInt('notify_customer', 0) === 1;

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if (empty(trim($comment))) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NOTE_REQUIRED'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if ($model->addOrderHistory($order->order_id, (int) $order->order_state_id, $notify, $comment)) {
                $this->setMessage(Text::_('COM_J2COMMERCE_ORDER_NOTE_ADDED'));
            } else {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_ADDING_NOTE'));
            }
        } catch (\Exception $e) {
            $this->app->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect(Route::_('index.php?option=com_j2commerce&view=order&layout=edit&id=' . $orderId, false));
    }

    public function ajaxSaveTracking(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId    = $this->input->post->getInt('order_id', 0);
        $trackingId = $this->input->post->getString('tracking_id', '');

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model = $this->getModel();

            if ($model->saveTrackingNumber($orderId, $trackingId)) {
                echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_TRACKING_NUMBER_SAVED')]);
            } else {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_SAVING_TRACKING'));
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    public function ajaxUpdateStatus(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId  = $this->input->post->getInt('order_id', 0);
        $statusId = $this->input->post->getInt('order_state_id', 0);
        $notify   = $this->input->post->getInt('notify_customer', 0) === 1;

        try {
            if ($orderId < 1 || $statusId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();

            if (!$model->updateOrderStatus($orderId, $statusId, false)) {
                $errors = $model->getErrors();
                throw new \Exception(implode("\n", $errors));
            }

            $message     = Text::_('COM_J2COMMERCE_ORDER_STATUS_UPDATED');
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

            $status = $this->getStatusInfo($statusId);

            echo json_encode([
                'success'     => true,
                'message'     => $message,
                'messageType' => $messageType,
                'data'        => [
                    'statusName' => Text::_($status->orderstatus_name ?? ''),
                    'cssclass'   => J2htmlHelper::badgeClass($status->orderstatus_cssclass ?? 'badge text-bg-secondary'),
                ],
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    public function ajaxResendEmail(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $result = $model->sendOrderNotification($order->order_id, true, true);

            if ($result['sent'] > 0) {
                $message = Text::sprintf('COM_J2COMMERCE_EMAILS_SENT_SUCCESSFULLY', $result['sent']);

                if (!empty($result['errors'])) {
                    $message .= ' (' . implode('; ', $result['errors']) . ')';
                }

                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                $errorMsg = !empty($result['errors'])
                    ? implode('; ', $result['errors'])
                    : Text::_('COM_J2COMMERCE_NO_EMAIL_TEMPLATES_FOUND');
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    public function ajaxAddNote(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);
        $note    = trim($this->input->post->getString('admin_note', ''));

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if ($note === '') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_NOTE_REQUIRED'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if ($model->addAdminNote($order->order_id, (int) $order->order_state_id, $note)) {
                echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_ORDER_NOTE_ADDED')]);
            } else {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_ADDING_NOTE'));
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    public function ajaxDeleteNote(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $historyId = $this->input->post->getInt('history_id', 0);

        try {
            if ($historyId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model         = $this->getModel();
            $currentUserId = Factory::getApplication()->getIdentity()?->id ?? 0;

            if ($model->deleteAdminNote($historyId, $currentUserId)) {
                echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_ORDER_NOTE_DELETED')]);
            } else {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_DELETING_NOTE'));
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    public function ajaxGetHistory(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);
        $page    = max(1, $this->input->post->getInt('page', 1));
        $limit   = (int) $this->app->get('list_limit', 20);
        $offset  = ($page - 1) * $limit;

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $result     = $model->getOrderHistoryPaginated($order->order_id, $offset, $limit);
            $totalPages = $result['total'] > 0 ? (int) ceil($result['total'] / $limit) : 1;

            $componentParams = \Joomla\CMS\Component\ComponentHelper::getParams('com_j2commerce');
            $dateFormat      = $componentParams->get('date_format', 'Y-m-d');
            $timeFormat      = $componentParams->get('time_format', 'H:i:s');

            $items        = [];
            $historyItems = $result['items'];
            $firstKey     = array_key_first($historyItems);
            $lastKey      = array_key_last($historyItems);

            foreach ($historyItems as $i => $history) {
                $cssClass = $history->orderstatus_cssclass ?? 'badge text-bg-secondary';
                $keywords = ['success', 'info', 'primary', 'warning', 'danger'];
                $color    = 'secondary';
                foreach ($keywords as $kw) {
                    if (str_contains($cssClass, $kw)) {
                        $color = $kw;
                        break;
                    }
                }

                $comment = $history->comment ?? '';

                $params      = json_decode($history->params ?? '{}', true) ?: [];
                $isAdminNote = ($params['type'] ?? '') === 'admin_note';

                $iconEvent = J2CommerceHelper::plugin()->event(
                    'RenderOrderHistoryIcon',
                    ['order' => $order, 'historyItem' => $history, 'icon' => '']
                );
                $pluginIcon = J2CommerceHelper::sanitizeHistoryIconClass(
                    (string) $iconEvent->getArgument('icon', '')
                );

                $items[] = [
                    'id'               => (int) ($history->j2commerce_orderhistory_id ?? 0),
                    'orderstatus_name' => Text::_($history->orderstatus_name ?? 'Unknown'),
                    'order_state_id'   => (int) ($history->order_state_id ?? 0),
                    'color'            => $color,
                    'date'             => \Joomla\CMS\HTML\HTMLHelper::_('date', $history->created_on, $dateFormat),
                    'time'             => \Joomla\CMS\HTML\HTMLHelper::_('date', $history->created_on, $timeFormat),
                    'comment'          => $comment,
                    'createdBy'        => (int) ($history->created_by ?? 0),
                    'isFirst'          => ($offset === 0 && $i === $firstKey),
                    'isLast'           => ($i === $lastKey),
                    'isNotification'   => stripos($comment, 'notified with') !== false,
                    'isItemRemoved'    => stripos($comment, 'item removed') !== false,
                    'isAdminNote'      => $isAdminNote,
                    'pluginIcon'       => $pluginIcon,
                ];
            }

            echo json_encode([
                'success'    => true,
                'items'      => $items,
                'page'       => $page,
                'totalPages' => $totalPages,
                'total'      => $result['total'],
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Render a single packing slip as a standalone HTML document. */
    public function packingSlip(): void
    {
        $orderId = $this->input->getInt('id', 0);

        if ($orderId < 1) {
            echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND') . '</div>';
            $this->app->close();
            return;
        }

        $model = $this->getModel();
        $order = $model->getItem($orderId);

        if (!$order || empty($order->order_id)) {
            echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
            $this->app->close();
            return;
        }

        echo $this->renderPackingSlipHtml($order);
        $this->app->close();
    }

    /** Render multiple packing slips for checked orders (one per page). */
    public function printPackingSlips(): void
    {
        Session::checkToken('get') or Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $cid = $this->input->get('cid', [], 'array');
        $cid = array_map('intval', $cid);
        $cid = array_filter($cid, fn (int $id) => $id > 0);

        if (empty($cid)) {
            echo '<div class="alert alert-warning">' . Text::_('JGLOBAL_NO_ITEM_SELECTED') . '</div>';
            $this->app->close();
            return;
        }

        $model       = $this->getModel();
        $helper      = PackingSlipHelper::getInstance();
        $emailHelper = EmailHelper::getInstance();

        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $cssQuery = $db->getQuery(true)
            ->select($db->quoteName('custom_css'))
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($cssQuery, 0, 1);
        $customCss = trim((string) $db->loadResult());

        $slipBodies = [];

        foreach ($cid as $id) {
            $order = $model->getItem($id);

            if (!$order || empty($order->order_id)) {
                continue;
            }

            $packingSlipHtml = $helper->getFormattedPackingSlip($order);

            // Extract <style> blocks from each slip
            $packingSlipHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $packingSlipHtml);
            $slipBodies[]    = $packingSlipHtml;
        }

        if (empty($slipBodies)) {
            echo '<div class="alert alert-warning">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
            $this->app->close();
            return;
        }

        // Get extracted styles from the first slip's template (they're all the same template)
        $firstOrder      = $model->getItem($cid[0]);
        $extractedStyles = '';

        if ($firstOrder && !empty($firstOrder->order_id)) {
            $fullHtml = $helper->getFormattedPackingSlip($firstOrder);
            preg_replace_callback(
                '/<style\b[^>]*>(.*?)<\/style>/si',
                function (array $m) use (&$extractedStyles): string {
                    $extractedStyles .= $m[1] . "\n";
                    return '';
                },
                $fullHtml
            );
        }

        $body = implode("\n<div style=\"page-break-before: always;\"></div>\n", $slipBodies);

        $html = '<!DOCTYPE html><html><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . Text::_('COM_J2COMMERCE_PRINT_PACKING_SLIPS') . '</title>'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;margin:0;padding:20px;color:#333;background:#f8fafc;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . 'table{border-collapse:collapse;border-spacing:0;}img{max-width:100%;height:auto;border:0;}'
            . '.no-print{margin-bottom:20px;display:flex;gap:8px;justify-content:center;align-items:center;}'
            . '.no-print button{padding:10px 24px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#333;cursor:pointer;font-size:14px;font-family:inherit;line-height:1.5;-webkit-appearance:none;appearance:none;}'
            . '.no-print button:hover{background:#f3f4f6;}'
            . $extractedStyles
            . ($customCss !== '' ? $customCss : '')
            . '@media print{.no-print{display:none!important;}body{margin:0;padding:0;background:#fff;}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}}'
            . '</style>'
            . '</head><body>'
            . '<div class="no-print">'
            . '<button onclick="window.print()">' . Text::_('COM_J2COMMERCE_PRINT') . '</button>'
            . '<button onclick="window.close()">' . Text::_('JCLOSE') . '</button>'
            . '<span style="color:#6b7280;font-size:14px;">' . Text::sprintf('COM_J2COMMERCE_PRINT_PACKING_SLIPS_COUNT', \count($slipBodies)) . '</span>'
            . '</div>'
            . $body
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        $this->app->close();
    }

    /** Render a complete standalone HTML packing slip for a single order. */
    private function renderPackingSlipHtml(object $order): string
    {
        $helper          = PackingSlipHelper::getInstance();
        $packingSlipHtml = $helper->getFormattedPackingSlip($order);

        $extractedStyles = '';
        $bodyHtml        = preg_replace_callback(
            '/<style\b[^>]*>(.*?)<\/style>/si',
            function (array $m) use (&$extractedStyles): string {
                $extractedStyles .= $m[1] . "\n";
                return '';
            },
            $packingSlipHtml
        );

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('custom_css'))
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query, 0, 1);
        $customCss = trim((string) $db->loadResult());

        return '<!DOCTYPE html><html><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . Text::sprintf('COM_J2COMMERCE_PACKING_SLIP_TITLE', htmlspecialchars($order->order_id)) . '</title>'
            . '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;margin:0;padding:20px;color:#333;background:#f8fafc;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            . 'table{border-collapse:collapse;border-spacing:0;}img{max-width:100%;height:auto;border:0;}'
            . '.no-print{margin-bottom:20px;display:flex;gap:8px;justify-content:center;}'
            . '.no-print button{padding:10px 24px;border:1px solid #d1d5db;border-radius:6px;background:#fff;color:#333;cursor:pointer;font-size:14px;font-family:inherit;line-height:1.5;-webkit-appearance:none;appearance:none;}'
            . '.no-print button:hover{background:#f3f4f6;}'
            . $extractedStyles
            . ($customCss !== '' ? $customCss : '')
            . '@media print{.no-print{display:none!important;}body{margin:0;padding:0;background:#fff;}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}}'
            . '</style>'
            . '</head><body>'
            . '<div class="no-print">'
            . '<button onclick="window.print()">' . Text::_('COM_J2COMMERCE_PRINT') . '</button>'
            . '<button onclick="window.close()">' . Text::_('JCLOSE') . '</button>'
            . '</div>'
            . $bodyHtml
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';
    }

    /** CSRF + core.edit/core.create + the j2commerce.editorders gate for order-edit AJAX endpoints. */
    private function checkOrderEditAccess(string $action = 'core.edit'): bool
    {
        if (!$this->checkAjaxAccess($action)) {
            return false;
        }

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $this->app->close();

            return false;
        }

        return true;
    }

    /**
     * Save the editable order-edit fields (used by the tab Next/Back buttons).
     * When order_id is 0 (blank new-order form), the first call creates the
     * order row instead of updating one.
     */
    public function ajaxSaveOrderEdit(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $orderId = $this->input->post->getInt('order_id', 0);

        if (!$this->checkOrderEditAccess($orderId < 1 ? 'core.create' : 'core.edit')) {
            return;
        }

        $jform = $this->input->post->get('jform', [], 'array');
        $qty   = $this->input->post->get('orderitem_qty', [], 'array');
        $price = $this->input->post->get('orderitem_price_edit', [], 'array');

        try {
            $model = $this->getModel();

            if ($orderId < 1) {
                $created = $model->createOrderFromEdit($jform);
                $order   = $model->getItem($created['id']);
                $totals  = $model->recalculateOrderTotals($created['order_id']);

                echo json_encode([
                    'success'          => true,
                    'created'          => true,
                    'order_id'         => $created['id'],
                    'order_ref'        => $created['order_id'],
                    'message'          => Text::_('COM_J2COMMERCE_ORDER_CREATED'),
                    'totals'           => $this->totalsPayload($totals, (string) ($order->currency_code ?? '')),
                    'redirect'         => Route::_('index.php?option=com_j2commerce&view=order&layout=edit&id=' . $created['id'], false),
                    // Reveal the Take Payment button without a page reload.
                    'take_payment_url' => OrderPayGrantHelper::isPayable($order) ? OrderPayGrantHelper::buildUrl($created['id']) : '',
                ]);
                $this->app->close();

                return;
            }

            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            if (!$model->saveOrderEditData($orderId, $jform)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            if (!empty($qty) || !empty($price)) {
                $model->updateOrderItemLines($order->order_id, $qty, $price, $model->isStockCommitted($order));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Create a Joomla customer account from the order editor's "New Customer" modal. */
    public function ajaxCreateCustomer(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess('core.create')) {
            return;
        }

        $name      = trim($this->input->post->getString('name', ''));
        $email     = trim($this->input->post->getString('email', ''));
        $username  = trim($this->input->post->getString('username', ''));
        $sendEmail = $this->input->post->getInt('send_email', 0) === 1;

        try {
            $customer = $this->getModel()->createCustomer($name, $email, $username, $sendEmail);

            echo json_encode([
                'success' => true,
                'id'      => $customer['id'],
                'name'    => $customer['name'],
                'email'   => $customer['email'],
                'message' => Text::_('COM_J2COMMERCE_CUSTOMER_CREATED'),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Search enabled product variants by SKU or title for the order editor. */
    public function ajaxSearchProducts(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);
        $term    = trim($this->input->post->getString('term', ''));
        $page    = max(1, $this->input->post->getInt('page', 1));

        try {
            if ($term === '') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();

            // Guest orders (user_id < 1) must never offer subscription products.
            $excludeSubscription = false;
            $currency            = '';

            if ($orderId > 0) {
                $order = $model->getItem($orderId);

                if ($order && !empty($order->order_id)) {
                    $excludeSubscription = (int) $order->user_id < 1;
                    $currency            = (string) $order->currency_code;
                }
            }

            $limit      = (int) $this->app->get('list_limit', 20) ?: 20;
            $offset     = ($page - 1) * $limit;
            $total      = $model->countSearchProductVariants($term, $excludeSubscription);
            $results    = $model->searchProductVariants($term, $limit, $excludeSubscription, $offset);
            $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

            echo json_encode([
                'success'    => true,
                'page'       => $page,
                'totalPages' => $totalPages,
                'total'      => $total,
                'results'    => array_map(static fn (object $row) => [
                    'variant_id'      => (int) $row->variant_id,
                    'sku'             => (string) $row->sku,
                    'name'            => (string) $row->product_name,
                    'price'           => number_format((float) $row->price, 2, '.', ''),
                    'price_formatted' => CurrencyHelper::format((float) $row->price, $currency),
                    'image'           => $model->resolveThumbImage((int) $row->product_id),
                ], $results),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Add a product variant as a new order line item and recalculate totals. */
    public function ajaxAddOrderItem(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $orderId   = $this->input->post->getInt('order_id', 0);
        $variantId = $this->input->post->getInt('variant_id', 0);
        $qty       = $this->input->post->getInt('quantity', 1);

        try {
            if ($orderId < 1 || $variantId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $item = $model->addOrderItemFromVariant(
                $order->order_id,
                $variantId,
                $qty,
                (int) $order->user_id < 1,
                $model->isStockCommitted($order)
            );

            if ($item === null) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            $model->addAdminNote(
                $order->order_id,
                (int) $order->order_state_id,
                Text::sprintf('COM_J2COMMERCE_ORDER_ITEM_ADDED_NOTE', $item->orderitem_name),
                'system_note'
            );

            $currency = (string) $order->currency_code;

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_ITEM_ADDED'),
                'totals'  => $this->totalsPayload($totals, $currency),
                'line'    => [
                    'id'                   => (int) $item->j2commerce_orderitem_id,
                    'name'                 => (string) $item->orderitem_name,
                    'sku'                  => (string) ($item->orderitem_sku ?? ''),
                    'quantity'             => (int) $item->orderitem_quantity,
                    'price'                => number_format((float) $item->orderitem_price, 2, '.', ''),
                    'price_formatted'      => CurrencyHelper::format((float) $item->orderitem_price, $currency),
                    'finalprice_formatted' => CurrencyHelper::format((float) $item->orderitem_finalprice, $currency),
                    'stock'                => (int) ($item->stock_quantity ?? 0),
                    'manages_stock'        => (bool) ($item->manages_stock ?? false),
                    'image_url'            => (string) ($item->image_url ?? ''),
                    'attributes'           => $model->getOrderItemAttributePairs((int) $item->j2commerce_orderitem_id),
                ],
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Update line quantities/prices and recalculate totals. */
    public function ajaxUpdateItems(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);
        $qty     = $this->input->post->get('orderitem_qty', [], 'array');
        $price   = $this->input->post->get('orderitem_price_edit', [], 'array');

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model->updateOrderItemLines($order->order_id, $qty, $price, $model->isStockCommitted($order));
            $totals = $model->recalculateOrderTotals($order->order_id);

            // Return refreshed line totals + live stock so the UI can update rows in place.
            $lines = [];

            foreach ($model->getOrderItems($order->order_id) as $line) {
                $lines[(int) $line->j2commerce_orderitem_id] = [
                    'quantity'             => (int) $line->orderitem_quantity,
                    'price'                => number_format((float) $line->orderitem_price, 2, '.', ''),
                    'finalprice'           => number_format((float) $line->orderitem_finalprice, 2, '.', ''),
                    'finalprice_formatted' => CurrencyHelper::format((float) $line->orderitem_finalprice, (string) $order->currency_code),
                    'stock'                => (int) ($line->stock_quantity ?? 0),
                    'manages_stock'        => (bool) ($line->manages_stock ?? false),
                ];
            }

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
                'lines'   => $lines,
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Remove selected order items and recalculate totals. */
    public function ajaxRemoveItems(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);
        $itemIds = $this->input->post->get('cid', [], 'array');

        try {
            if ($orderId < 1 || empty($itemIds)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $removed = $model->removeOrderItems($order->order_id, $itemIds, $model->isStockCommitted($order));

            if (empty($removed)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            foreach ($removed as $name) {
                $model->addAdminNote(
                    $order->order_id,
                    (int) $order->order_state_id,
                    Text::sprintf('COM_J2COMMERCE_ORDER_ITEM_REMOVED_NOTE', $name),
                    'system_note'
                );
            }

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_ITEMS_REMOVED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Recalculate and persist order totals. */
    public function ajaxRecalculate(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $orderId = $this->input->post->getInt('order_id', 0);

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if (!$order || empty($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $totals = $model->recomputeOrderTax($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_TOTALS_RECALCULATED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Append CurrencyHelper-formatted strings to a totals array for the JS layer. */
    private function totalsPayload(array $totals, string $currencyCode): array
    {
        $formatted = [];

        foreach ($totals as $name => $value) {
            $formatted[$name] = CurrencyHelper::format((float) $value, $currencyCode);
        }

        $totals['formatted'] = $formatted;

        return $totals;
    }

    /** Load + validate the order for an order-edit AJAX call, or emit the JSON error. */
    private function loadOrderForAjax(): ?object
    {
        $orderId = $this->input->post->getInt('order_id', 0);
        $order   = $orderId > 0 ? $this->getModel()->getItem($orderId) : null;

        if (!$order || empty($order->order_id)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND')]);
            $this->app->close();

            return null;
        }

        return $order;
    }

    /** Save the billing or shipping address block from the address edit form. */
    public function ajaxSaveAddress(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $type    = $this->input->post->getWord('address_type', '');
        $address = $this->input->post->get('address', [], 'array');

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if (!\in_array($type, ['billing', 'shipping'], true) || empty($address)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            if (!$this->getModel()->saveOrderAddress($order->order_id, $type, $address)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED'));
            }

            echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED')]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** List the customer's saved addresses for the choose-alternate dialog. */
    public function ajaxGetSavedAddresses(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            $addresses = $this->getModel()->getSavedAddresses((int) $order->user_id);

            echo json_encode(['success' => true, 'addresses' => $addresses]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Copy a saved customer address onto the order (billing or shipping). */
    public function ajaxApplySavedAddress(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $type      = $this->input->post->getWord('address_type', '');
        $addressId = $this->input->post->getInt('address_id', 0);

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if (!\in_array($type, ['billing', 'shipping'], true) || $addressId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            if (!$this->getModel()->applySavedAddress($order->order_id, $type, $addressId, (int) $order->user_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED')]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Zones for a country (address form cascade). */
    public function ajaxGetZones(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $countryId = $this->input->post->getInt('country_id', 0);

        echo json_encode(['success' => true, 'zones' => $this->getModel()->getZones($countryId)]);
        $this->app->close();
    }

    /** Copy the billing address onto the shipping address ("same as billing"). */
    public function ajaxCopyBillingToShipping(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if (!$this->getModel()->copyBillingToShipping($order->order_id)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED'));
            }

            echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_ORDER_CHANGES_SAVED')]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /**
     * Refund part or all of the order's payment through its own gateway
     * (onJ2CommerceRefundPayment). Amounts arrive/display in the order (ledger)
     * currency; the event contract hands the gateway a BASE-currency figure.
     */
    public function ajaxRefundOrderPayment(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $amount = (float) $this->input->post->getString('amount', '0');

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            $orderPk = (int) $order->j2commerce_order_id;
            $element = (string) ($order->orderpayment_type ?? '');

            if ($element === '') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $currencyCode = (string) ($order->currency_code ?? '');
            $decimals     = CurrencyHelper::getDecimalPlace($currencyCode);
            $epsilon      = 10 ** -$decimals / 2;
            $amount       = round($amount, $decimals);
            $refundable   = OrderTransactionHelper::getRefundable($orderPk);

            if ($amount < $epsilon || $amount > $refundable + $epsilon) {
                throw new \Exception(Text::sprintf(
                    'COM_J2COMMERCE_ERR_REFUND_OUT_OF_RANGE',
                    CurrencyHelper::format($refundable, $currencyCode, 1.0)
                ));
            }

            $refundedBefore = OrderTransactionHelper::getRefunded($orderPk);

            // Event contract (all gateways): args = [element, (int) varchar order_id,
            // amount in STORE BASE currency] — gateways convert to display internally.
            $rate       = (float) ($order->currency_value ?? 1.0);
            $rate       = $rate > 0 ? $rate : 1.0;
            $baseAmount = $amount / $rate;

            $event  = J2CommerceHelper::plugin()->event('RefundPayment', [$element, (int) $order->order_id, $baseAmount]);
            $result = $event->getArgument('result');

            if (!\is_array($result) || empty($result['success'])) {
                $gatewayMessage = \is_array($result) ? (string) ($result['error'] ?? $result['message'] ?? '') : '';
                throw new \Exception($gatewayMessage !== '' ? $gatewayMessage : Text::_('COM_J2COMMERCE_ERR_REFUND_FAILED'));
            }

            // A 'void' outcome reversed the whole unsettled capture, not just the
            // requested figure — the ledger AND the audit note record what actually happened.
            $ledgerAmount = ($result['action'] ?? '') === 'void' ? $refundable : $amount;

            // Double-entry guard: only write the ledger when the gateway didn't
            // (airwallex/quickpay self-write reversals; authorize.net does not).
            if (abs(OrderTransactionHelper::getRefunded($orderPk) - $refundedBefore) < $epsilon) {
                $userId = (int) ($this->app->getIdentity()?->id ?? 0);
                $batch  = uniqid('', true);

                foreach (OrderTransactionHelper::allocateRefund($orderPk, $ledgerAmount) as $i => $leg) {
                    OrderTransactionHelper::addReversal(
                        $orderPk,
                        $element,
                        'admin-refund-' . $orderPk . '-' . $batch . '-' . $i,
                        $leg['gateway_txn_id'],
                        $leg['amount'],
                        $userId
                    );
                }
            }

            $this->getModel()->addAdminNote(
                $order->order_id,
                (int) $order->order_state_id,
                Text::sprintf('COM_J2COMMERCE_ORDER_REFUND_NOTE', CurrencyHelper::format($ledgerAmount, $currencyCode, 1.0), $element),
                'system_note'
            );

            echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_REFUND_DONE')]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /**
     * Charge the outstanding balance on the customer's stored payment method via
     * the gateway's supplemental-payment capability (same events the after-sale
     * specials app uses). Amounts are in the order (ledger/display) currency.
     */
    public function ajaxChargeOrderBalance(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $amount = (float) $this->input->post->getString('amount', '0');

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            $orderPk = (int) $order->j2commerce_order_id;
            $element = (string) ($order->orderpayment_type ?? '');
            $model   = $this->getModel();

            // Parity with the UI gate: balance charges only apply to ledgered orders.
            if (!OrderTransactionHelper::hasLedger($orderPk)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            // Capability + stored profile are re-resolved server-side — never trusted
            // from the client.
            $profile = $model->resolveStoredPaymentProfile((int) ($order->user_id ?? 0), $element);

            if ($element === '' || $profile === null || $model->getSupplementalCapability($order) !== 'token_charge') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $currencyCode = (string) ($order->currency_code ?? '');
            $decimals     = CurrencyHelper::getDecimalPlace($currencyCode);
            $epsilon      = 10 ** -$decimals / 2;
            $amount       = round($amount, $decimals);

            $rate              = (float) ($order->currency_value ?? 1.0);
            $rate              = $rate > 0 ? $rate : 1.0;
            $orderTotalDisplay = round((float) ($order->order_total ?? 0) * $rate, $decimals);
            $balanceDue        = max(0.0, $orderTotalDisplay - max(0.0, OrderTransactionHelper::getNetPaid($orderPk)));

            if ($amount < $epsilon || $amount > $balanceDue + $epsilon) {
                throw new \Exception(Text::sprintf(
                    'COM_J2COMMERCE_ERR_CHARGE_OUT_OF_RANGE',
                    CurrencyHelper::format($balanceDue, $currencyCode, 1.0)
                ));
            }

            $netPaidBefore = OrderTransactionHelper::getNetPaid($orderPk);
            $reference     = 'j2c-admin-balance-' . $orderPk . '-' . time();

            $results = J2CommerceHelper::plugin()->eventWithArray('ProcessSupplementalPayment', [
                'payment_method'  => $element,
                'order'           => $order,
                'amount'          => $amount,
                'payment_profile' => $profile,
                'reference'       => $reference,
                'result'          => [],
            ]);

            $outcome = null;

            foreach ($results as $result) {
                if (\is_array($result) && !empty($result['status'])) {
                    $outcome = $result;
                    break;
                }
            }

            if (($outcome['status'] ?? '') !== 'success') {
                $gatewayMessage = (string) ($outcome['message'] ?? '');
                throw new \Exception($gatewayMessage !== '' ? $gatewayMessage : Text::_('COM_J2COMMERCE_ERR_CHARGE_FAILED'));
            }

            $transactionId = (string) ($outcome['transaction_id'] ?? '');

            // Double-entry guard: write the DEBIT only when the gateway didn't.
            if (abs(OrderTransactionHelper::getNetPaid($orderPk) - $netPaidBefore) < $epsilon) {
                OrderTransactionHelper::addCharge(
                    $orderPk,
                    $element,
                    $transactionId !== '' ? $transactionId : $reference,
                    $amount,
                    $currencyCode,
                    (int) ($this->app->getIdentity()?->id ?? 0)
                );
            }

            $model->addAdminNote(
                $order->order_id,
                (int) $order->order_state_id,
                Text::sprintf(
                    'COM_J2COMMERCE_ORDER_BALANCE_CHARGE_NOTE',
                    CurrencyHelper::format($amount, $currencyCode, 1.0),
                    $transactionId !== '' ? $transactionId : $reference
                ),
                'system_note'
            );

            echo json_encode(['success' => true, 'message' => Text::_('COM_J2COMMERCE_CHARGE_DONE')]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Add a manual fee, recompute tax when taxable, and return fresh totals. */
    public function ajaxAddFee(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $name    = trim($this->input->post->getString('fee_name', ''));
        $amount  = (float) $this->input->post->getString('fee_amount', '0');
        $taxable = $this->input->post->getInt('fee_taxable', 0) === 1;

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if ($name === '' || $amount <= 0) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model = $this->getModel();

            if (!$model->addOrderFee($order->order_id, $name, $amount, $taxable)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED'));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_FEE_ADDED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Remove a fee row and return fresh totals. */
    public function ajaxRemoveFee(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $feeId = $this->input->post->getInt('fee_id', 0);

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            $model = $this->getModel();

            if ($feeId < 1 || !$model->removeOrderFee($order->order_id, $feeId)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_FEE_REMOVED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Apply a coupon code to the order. */
    public function ajaxApplyCoupon(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $code = trim($this->input->post->getString('coupon_code', ''));

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if ($code === '') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ENTER_COUPON_CODE'));
            }

            $model               = $this->getModel();
            [$success, $message] = $model->applyCouponToOrder($order, $code);

            if (!$success) {
                echo json_encode(['success' => false, 'message' => $message]);
                $this->app->close();

                return;
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => $message,
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Apply a gift voucher code to the order. */
    public function ajaxApplyVoucher(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $code = trim($this->input->post->getString('voucher_code', ''));

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            if ($code === '') {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $model               = $this->getModel();
            [$success, $message] = $model->applyVoucherToOrder($order, $code);

            if (!$success) {
                echo json_encode(['success' => false, 'message' => $message]);
                $this->app->close();

                return;
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => $message,
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    /** Remove an applied discount (coupon or voucher) from the order. */
    public function ajaxRemoveDiscount(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkOrderEditAccess()) {
            return;
        }

        $discountId = $this->input->post->getInt('discount_id', 0);

        try {
            if (!($order = $this->loadOrderForAjax())) {
                return;
            }

            $model = $this->getModel();

            if ($discountId < 1 || !$model->removeOrderDiscount($order->order_id, $discountId)) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ERROR_INVALID_REQUEST'));
            }

            $totals = $model->recalculateOrderTotals($order->order_id);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_DISCOUNT_REMOVED'),
                'totals'  => $this->totalsPayload($totals, (string) $order->currency_code),
            ]);
        } catch (\Joomla\Database\Exception\ExecutionFailureException $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_j2commerce');
            echo json_encode(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ERROR_SAVE_FAILED')]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }

    protected function validateAjaxToken(): bool
    {
        $token = \Joomla\CMS\Session\Session::getFormToken();

        if ($token === $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum')) {
            return true;
        }

        return $this->input->post->get($token, '', 'alnum') === '1';
    }

    /**
     * CSRF token + backend ACL gate for the order AJAX endpoints. On failure it
     * emits the JSON error and closes the app, returning false so the caller
     * can just `return`.
     */
    protected function checkAjaxAccess(string $action = 'core.edit'): bool
    {
        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();

            return false;
        }

        if (!$this->app->getIdentity()->authorise($action, 'com_j2commerce')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $this->app->close();

            return false;
        }

        return true;
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

    public function ajaxQueueFaker(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->checkAjaxAccess()) {
            return;
        }

        $orderId  = $this->input->post->getInt('order_id', 0);
        $orderRef = $this->input->post->getString('order_ref', '');

        try {
            if ($orderId < 1) {
                throw new \Exception(Text::_('COM_J2COMMERCE_ORDER_NOT_FOUND'));
            }

            $queueId = QueueHelper::enqueue('faker', (string) $orderId, [
                'order_id'  => $orderId,
                'order_ref' => $orderRef,
                'action'    => 'test_sync',
            ]);

            $model = $this->getModel();
            $order = $model->getItem($orderId);

            if ($order && !empty($order->order_id)) {
                $model->addAdminNote(
                    $order->order_id,
                    (int) $order->order_state_id,
                    Text::sprintf('COM_J2COMMERCE_ORDER_SUBMITTED_TO_FAKER_QUEUE', $queueId),
                    'system_note'
                );
            }

            echo json_encode([
                'success' => true,
                'message' => Text::sprintf('COM_J2COMMERCE_QUEUE_ORDER_ADDED', $orderRef),
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

        $this->app->close();
    }
}
