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
    private const EXPORT_CHUNK_SIZE = 200;

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
     * Stream a CSV export of orders matching the export panel filters.
     *
     * One row per order, plus a repeated block of item columns sized to the
     * largest item count in the result set. Data is fetched and written in
     * chunks so the full result set is never held in memory at once.
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

        $model = $this->getModel('Orders', 'Administrator', ['ignore_request' => true]);
        $model->setExportFilters($this->getExportFiltersFromRequest());

        $orderRefs = $model->getExportOrderIds();
        $orderIds  = array_map(static fn ($ref) => (string) $ref->order_id, $orderRefs);
        $maxItems  = $model->getExportMaxItemCount($orderIds);

        $filename = 'orders_export_' . Factory::getDate('now')->format('Ymd_His') . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $this->buildExportHeader($maxItems), ',', '"', '');

        foreach (array_chunk($orderIds, self::EXPORT_CHUNK_SIZE) as $chunk) {
            $orders = $model->getExportOrderDetails($chunk);
            $items  = $model->getExportOrderItems($chunk);

            foreach ($chunk as $orderId) {
                if (!isset($orders[$orderId])) {
                    continue;
                }

                fputcsv($out, $this->buildExportRow($orders[$orderId], $items[$orderId] ?? [], $maxItems), ',', '"', '');
            }

            flush();
        }

        fclose($out);

        $this->app->close();
    }

    /**
     * AJAX endpoint: live count of orders matching the current export panel filters.
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public function exportCount(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->validateAjaxToken()) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $this->app->close();
            return;
        }

        if (!J2CommerceHelper::canAccess('j2commerce.editorders')) {
            echo json_encode(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);
            $this->app->close();
            return;
        }

        $model = $this->getModel('Orders', 'Administrator', ['ignore_request' => true]);
        $model->setExportFilters($this->getExportFiltersFromRequest());

        echo json_encode(['success' => true, 'count' => (int) $model->getOrdersTotal()]);
        $this->app->close();
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

        // Timing-safe comparison for the header variant; the POST variant only
        // checks for the token's presence as a key, matching Session::checkToken().
        $headerToken = (string) $this->input->server->get('HTTP_X_CSRF_TOKEN', '', 'alnum');

        if ($headerToken !== '' && hash_equals($token, $headerToken)) {
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

    /**
     * @return  array<string, mixed>
     */
    private function getExportFiltersFromRequest(): array
    {
        $post = $this->input->post;

        $paymentTypes = array_values(array_filter(array_map(
            static fn ($value) => preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $value),
            (array) $post->get('payment_type', [], 'array')
        )));

        $statusIds = array_values(array_filter(array_map('intval', (array) $post->get('order_state_id', [], 'array'))));

        // The Modal_UserMultiselect field posts its selection as jform[request][user_ids][].
        $jform   = (array) $post->get('jform', [], 'array');
        $userIds = array_values(array_filter(array_map('intval', (array) ($jform['request']['user_ids'] ?? []))));

        return [
            'search'                   => $post->getString('search', ''),
            'since'                    => $post->getString('since', ''),
            'until'                    => $post->getString('until', ''),
            'from_j2commerce_order_id' => $post->getInt('from_j2commerce_order_id', 0),
            'to_j2commerce_order_id'   => $post->getInt('to_j2commerce_order_id', 0),
            'user_ids'                 => $userIds,
            'amount_from'              => $post->getFloat('amount_from', 0),
            'amount_to'                => $post->getFloat('amount_to', 0),
            'payment_type'             => $paymentTypes,
            'order_state_id'           => $statusIds,
            'coupon_code'              => $post->getString('coupon_code', ''),
        ];
    }

    /**
     * @return  array<int, string>
     */
    private function buildExportHeader(int $maxItems): array
    {
        $header = [
            Text::_('COM_J2COMMERCE_EXPORT_COL_ORDER_ID'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_ORDER_NUMBER'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_INVOICE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_DATE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_STATUS'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_PAYMENT_METHOD'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SUBTOTAL'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_TAX'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_DISCOUNT'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_TOTAL'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_CURRENCY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_COUPON_CODE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_CUSTOMER_NAME'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_CUSTOMER_EMAIL'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_COMPANY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_ADDRESS_1'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_ADDRESS_2'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_CITY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_STATE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_COUNTRY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_ZIP'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_BILLING_PHONE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_COMPANY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_FIRST_NAME'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_LAST_NAME'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_ADDRESS_1'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_ADDRESS_2'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_CITY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_STATE'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_COUNTRY'),
            Text::_('COM_J2COMMERCE_EXPORT_COL_SHIPPING_ZIP'),
        ];

        for ($i = 1; $i <= $maxItems; $i++) {
            $header[] = Text::sprintf('COM_J2COMMERCE_EXPORT_COL_ITEM_NAME', $i);
            $header[] = Text::sprintf('COM_J2COMMERCE_EXPORT_COL_ITEM_SKU', $i);
            $header[] = Text::sprintf('COM_J2COMMERCE_EXPORT_COL_ITEM_PRICE', $i);
            $header[] = Text::sprintf('COM_J2COMMERCE_EXPORT_COL_ITEM_QTY', $i);
            $header[] = Text::sprintf('COM_J2COMMERCE_EXPORT_COL_ITEM_TOTAL', $i);
        }

        return $header;
    }

    /**
     * @param   array<int, object>  $items
     *
     * @return  array<int, mixed>
     */
    private function buildExportRow(object $order, array $items, int $maxItems): array
    {
        $customerName   = trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? ''));
        $paymentDisplay = !empty($order->payment_plugin_name)
            ? Text::_($order->payment_plugin_name)
            : (string) ($order->orderpayment_type ?? '');

        $row = [
            (int) $order->j2commerce_order_id,
            (string) $order->order_id,
            (string) ($order->invoice ?? ''),
            (string) $order->created_on,
            Text::_($order->orderstatus_name ?? 'COM_J2COMMERCE_UNKNOWN'),
            $this->sanitizeCsvCell(strip_tags($paymentDisplay)),
            number_format((float) $order->order_subtotal, 2, '.', ''),
            number_format((float) $order->order_shipping, 2, '.', ''),
            number_format((float) $order->order_tax, 2, '.', ''),
            number_format((float) $order->order_discount, 2, '.', ''),
            number_format((float) $order->order_total, 2, '.', ''),
            $this->sanitizeCsvCell((string) $order->currency_code),
            $this->sanitizeCsvCell((string) ($order->discount_code ?? '')),
            $this->sanitizeCsvCell($customerName),
            $this->sanitizeCsvCell((string) $order->user_email),
            $this->sanitizeCsvCell((string) ($order->billing_company ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_address_1 ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_address_2 ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_city ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_zone_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_country_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_zip ?? '')),
            $this->sanitizeCsvCell((string) ($order->billing_phone_1 ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_company ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_first_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_last_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_address_1 ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_address_2 ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_city ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_zone_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_country_name ?? '')),
            $this->sanitizeCsvCell((string) ($order->shipping_zip ?? '')),
        ];

        for ($i = 0; $i < $maxItems; $i++) {
            $item = $items[$i] ?? null;

            $row[] = $this->sanitizeCsvCell((string) ($item->orderitem_name ?? ''));
            $row[] = $this->sanitizeCsvCell((string) ($item->orderitem_sku ?? ''));
            $row[] = $item !== null ? number_format((float) $item->orderitem_price, 2, '.', '') : '';
            $row[] = $this->sanitizeCsvCell((string) ($item->orderitem_quantity ?? ''));
            $row[] = $item !== null ? number_format((float) $item->orderitem_finalprice, 2, '.', '') : '';
        }

        return $row;
    }

    /**
     * Neutralizes CSV formula injection (Excel/Sheets DDE attack) by prefixing
     * cells that start with =, +, -, or @ with a leading tab.
     */
    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && \in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "\t" . $value;
        }

        return $value;
    }
}
