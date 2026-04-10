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

use J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\PackingSlipHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\QueueHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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

    public function save($key = null, $urlVar = 'id')
    {
        return parent::save($key, $urlVar);
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

            if ($model->updateOrderStatus($orderId, $statusId, $notify, $comment)) {
                $this->setMessage(Text::_('COM_J2COMMERCE_ORDER_STATUS_UPDATED'));
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

            if (!$model->updateOrderStatus($orderId, $statusId, $notify)) {
                $errors = $model->getErrors();
                throw new \Exception(implode("\n", $errors));
            }

            $status = $this->getStatusInfo($statusId);

            echo json_encode([
                'success' => true,
                'message' => Text::_('COM_J2COMMERCE_ORDER_STATUS_UPDATED'),
                'data'    => [
                    'statusName' => Text::_($status->orderstatus_name ?? ''),
                    'cssclass'   => $status->orderstatus_cssclass ?? 'secondary',
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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

            $dateFormat = \Joomla\CMS\Component\ComponentHelper::getParams('com_j2commerce')
                ->get('date_format', 'Y-m-d H:i:s');

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

                $items[] = [
                    'id'               => (int) ($history->j2commerce_orderhistory_id ?? 0),
                    'orderstatus_name' => Text::_($history->orderstatus_name ?? 'Unknown'),
                    'order_state_id'   => (int) ($history->order_state_id ?? 0),
                    'color'            => $color,
                    'date'             => \Joomla\CMS\HTML\HTMLHelper::_('date', $history->created_on, $dateFormat),
                    'time'             => \Joomla\CMS\HTML\HTMLHelper::_('date', $history->created_on, 'g:i A'),
                    'comment'          => $comment,
                    'createdBy'        => (int) ($history->created_by ?? 0),
                    'isFirst'          => ($offset === 0 && $i === $firstKey),
                    'isLast'           => ($i === $lastKey),
                    'isNotification'   => stripos($comment, 'notified with') !== false,
                    'isItemRemoved'    => stripos($comment, 'item removed') !== false,
                    'isAdminNote'      => $isAdminNote,
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
            . '.no-print{margin-bottom:20px;text-align:center;}'
            . '.no-print button{padding:10px 24px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:14px;}'
            . '.no-print button:hover{background:#f3f4f6;}'
            . $extractedStyles
            . ($customCss !== '' ? $customCss : '')
            . '@media print{.no-print{display:none!important;}body{margin:0;padding:0;background:#fff;}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}}'
            . '</style>'
            . '</head><body>'
            . '<div class="no-print">'
            . '<button onclick="window.print()">' . Text::_('COM_J2COMMERCE_PRINT') . '</button>'
            . '<button onclick="window.close()" style="margin-left:8px;">' . Text::_('JCLOSE') . '</button>'
            . '<span style="margin-left:16px;color:#6b7280;font-size:14px;">' . Text::sprintf('COM_J2COMMERCE_PRINT_PACKING_SLIPS_COUNT', \count($slipBodies)) . '</span>'
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
            . '.no-print{margin-bottom:20px;text-align:center;}'
            . '.no-print button{padding:10px 24px;border:1px solid #d1d5db;border-radius:6px;background:#fff;cursor:pointer;font-size:14px;}'
            . '.no-print button:hover{background:#f3f4f6;}'
            . $extractedStyles
            . ($customCss !== '' ? $customCss : '')
            . '@media print{.no-print{display:none!important;}body{margin:0;padding:0;background:#fff;}*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important;}}'
            . '</style>'
            . '</head><body>'
            . '<div class="no-print">'
            . '<button onclick="window.print()">' . Text::_('COM_J2COMMERCE_PRINT') . '</button>'
            . '<button onclick="window.close()" style="margin-left:8px;">' . Text::_('JCLOSE') . '</button>'
            . '</div>'
            . $bodyHtml
            . '<script>window.onload=function(){window.print();};</script>'
            . '</body></html>';
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

    public function ajaxQueueFaker(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
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
