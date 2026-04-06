<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\DownloadHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Path;

class MyprofileController extends BaseController
{
    public function display($cachable = false, $urlparams = []): static
    {
        $user = $this->app->getIdentity();
        $session = $this->app->getSession();

        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');

        if ((!$user || !$user->id) && empty($guestToken)) {
            $this->input->set('layout', 'default_login');

            return parent::display($cachable, $urlparams);
        }

        if ($user && $user->id) {
            $session->clear('guest_order_token', 'j2commerce');
            $session->clear('guest_order_email', 'j2commerce');
        }

        return parent::display($cachable, $urlparams);
    }

    public function saveAddress(): void
    {
        if (!Session::checkToken('post')) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);

            return;
        }

        $user = $this->app->getIdentity();

        if (!$user || !$user->id) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JGLOBAL_YOU_MUST_LOGIN_FIRST')]);

            return;
        }

        $formData = $this->collectFormData();
        $addressId = $this->input->getInt('address_id', 0);
        $type = $this->input->getString('type', 'billing');
        $area = ($type === 'shipping') ? 'shipping' : 'billing';

        $fields = CustomFieldHelper::getFieldsByArea($area);
        $errors = CustomFieldHelper::validateFields($fields, $formData);

        if ($errors) {
            $this->jsonResponse([
                'success' => false,
                'errors' => $errors,
                'message' => Text::_('COM_J2COMMERCE_MYPROFILE_VALIDATION_FAILED'),
            ]);

            return;
        }

        $addressData = CustomFieldHelper::collectAddressData($fields, $formData);
        $addressData['user_id'] = (int) $user->id;
        $addressData['type'] = $type;
        $addressData['email'] = $formData['email'] ?? $user->email;

        $addressTable = $this->getMvcFactory()->createTable('Address', 'Administrator');

        if (!$addressTable) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ADDRESS_SAVE_ERROR')]);

            return;
        }

        if ($addressId > 0) {
            $addressTable->load($addressId);

            if ((int) $addressTable->user_id !== (int) $user->id) {
                $this->jsonResponse(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);

                return;
            }
        }

        if (!$addressTable->bind($addressData) || !$addressTable->check() || !$addressTable->store()) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_ADDRESS_SAVE_ERROR')]);

            return;
        }

        $this->triggerEvent('onJ2CommerceAfterAddressSave', [$addressTable]);

        $profileUrl = Route::_('index.php?option=com_j2commerce&view=myprofile', false);

        $this->jsonResponse([
            'success' => true,
            'message' => Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESS_SAVED'),
            'address_id' => (int) $addressTable->j2commerce_address_id,
            'redirect' => $profileUrl,
        ]);
    }

    public function deleteAddress(): void
    {
        if (!Session::checkToken('post')) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);

            return;
        }

        $user = $this->app->getIdentity();

        if (!$user || !$user->id) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JGLOBAL_YOU_MUST_LOGIN_FIRST')]);

            return;
        }

        $addressId = $this->input->getInt('address_id', 0);

        if ($addressId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_MYPROFILE_INVALID_ADDRESS')]);

            return;
        }

        $addressTable = $this->getMvcFactory()->createTable('Address', 'Administrator');

        if (!$addressTable || !$addressTable->load($addressId)) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESS_NOT_FOUND')]);

            return;
        }

        if ((int) $addressTable->user_id !== (int) $user->id) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')]);

            return;
        }

        if (!$addressTable->delete($addressId)) {
            $this->jsonResponse(['success' => false, 'message' => Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESS_DELETE_ERROR')]);

            return;
        }

        $this->triggerEvent('onJ2CommerceAfterAddressDelete', [$addressTable]);

        $this->jsonResponse([
            'success' => true,
            'message' => Text::_('COM_J2COMMERCE_MYPROFILE_ADDRESS_DELETED'),
        ]);
    }

    public function guestEntry(): void
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $email = trim($this->input->getString('email', ''));
        $orderToken = trim($this->input->getString('order_token', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_INVALID_EMAIL'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $orderType = 'normal';
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_order_id'))
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('token') . ' = :token')
            ->where($db->quoteName('user_email') . ' = :email')
            ->where($db->quoteName('order_type') . ' = :orderType')
            ->bind(':token', $orderToken)
            ->bind(':email', $email)
            ->bind(':orderType', $orderType)
            ->setLimit(1);

        $db->setQuery($query);
        $orderId = $db->loadResult();

        if ($orderId) {
            $session = $this->app->getSession();
            $session->set('guest_order_token', $orderToken, 'j2commerce');
            $session->set('guest_order_email', $email, 'j2commerce');

            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));
        } else {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_ORDER_NOT_FOUND'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));
        }
    }

    public function download(): void
    {
        $token = $this->input->getString('token', '');
        $fileId = $this->input->getInt('fid', 0);
        $redirectUrl = Route::_('index.php?option=com_j2commerce&view=myprofile', false);

        if (empty($token) || $fileId <= 0) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOAD_INVALID'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Load the specific product file
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_productfiles'))
            ->where($db->quoteName('j2commerce_productfile_id') . ' = :fileId')
            ->bind(':fileId', $fileId, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);
        $productFile = $db->loadObject();

        if (!$productFile || empty($productFile->product_file_save_name)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_FILE_NOT_FOUND'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Load order by order_id (the token parameter)
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $token)
            ->setLimit(1);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_ORDER_NOT_FOUND'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        if (!$this->validateOrderAccess($order)) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Load download record for this order + product
        $orderId = $order->order_id;
        $productId = (int) $productFile->product_id;
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderdownloads'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':orderId', $orderId)
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->setLimit(1);

        $db->setQuery($query);
        $download = $db->loadObject();

        if (!$download) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOAD_NOT_FOUND'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Check access has been granted
        $nullDate = $db->getNullDate();

        if (empty($download->access_granted) || $download->access_granted === $nullDate || $download->access_granted === '0000-00-00 00:00:00') {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOAD_NOT_FOUND'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Check expiry
        if (!empty($download->access_expires) && $download->access_expires !== $nullDate && $download->access_expires !== '0000-00-00 00:00:00') {
            if (strtotime($download->access_expires) < time()) {
                $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOAD_EXPIRED'), 'error');
                $this->app->redirect($redirectUrl);

                return;
            }
        }

        // Check download limit from product params (not from productfile download_total)
        $downloadLimit = DownloadHelper::getDownloadLimit($productId);

        if ($downloadLimit > 0 && (int) $download->limit_count >= $downloadLimit) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_DOWNLOAD_LIMIT_REACHED'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Build file path with path traversal protection
        $filePath = Path::clean(JPATH_SITE . '/' . ltrim($productFile->product_file_save_name, '/'));
        $realPath = @realpath($filePath);
        $siteRoot = realpath(JPATH_SITE);

        if (!$realPath || !$siteRoot || !str_starts_with($realPath, $siteRoot) || !is_readable($realPath)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_FILE_NOT_FOUND'), 'error');
            $this->app->redirect($redirectUrl);

            return;
        }

        // Increment download count on orderdownload + productfile
        $downloadId = (int) $download->j2commerce_orderdownload_id;
        $newCount = (int) $download->limit_count + 1;

        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orderdownloads'))
            ->set($db->quoteName('limit_count') . ' = :newCount')
            ->where($db->quoteName('j2commerce_orderdownload_id') . ' = :downloadId')
            ->bind(':newCount', $newCount, ParameterType::INTEGER)
            ->bind(':downloadId', $downloadId, ParameterType::INTEGER);
        $db->setQuery($updateQuery);
        $db->execute();

        $newTotal = (int) $productFile->download_total + 1;
        $pfId = (int) $productFile->j2commerce_productfile_id;
        $updateFileQuery = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_productfiles'))
            ->set($db->quoteName('download_total') . ' = :newTotal')
            ->where($db->quoteName('j2commerce_productfile_id') . ' = :pfId')
            ->bind(':newTotal', $newTotal, ParameterType::INTEGER)
            ->bind(':pfId', $pfId, ParameterType::INTEGER);
        $db->setQuery($updateFileQuery);
        $db->execute();

        // Stream file
        $displayName = $productFile->product_file_display_name ?: basename($realPath);
        $displayName = str_replace(['"', "\r", "\n"], '', $displayName);
        $fileSize = filesize($realPath);
        $mimeType = mime_content_type($realPath) ?: 'application/octet-stream';

        $this->app->clearHeaders();
        $this->app->setHeader('Content-Type', $mimeType);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $displayName . '"');
        $this->app->setHeader('Content-Length', (string) $fileSize);
        $this->app->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->app->setHeader('Pragma', 'no-cache');
        $this->app->setHeader('Expires', '0');
        $this->app->sendHeaders();

        @ob_end_clean();
        readfile($realPath);
        $this->app->close();
    }

    public function loadOrders(): void
    {
        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();
        $userId  = ($user && $user->id) ? (int) $user->id : 0;

        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');

        if ($userId === 0 && empty($guestToken)) {
            $this->jsonResponse(['success' => false]);

            return;
        }

        $search     = $this->input->getString('search', '');
        $limitStart = $this->input->getInt('limitstart', 0);
        $limit      = (int) $this->app->get('list_limit', 20);

        /** @var \J2Commerce\Component\J2commerce\Site\Model\MyprofileModel $model */
        $model = $this->getModel('Myprofile');
        $data  = $model->getOrders($userId, $guestToken, $guestEmail, $limitStart, $limit, $search);

        $params     = J2CommerceHelper::config();
        $dateFormat = $params->get('date_format', 'Y-m-d');

        $rows = [];

        foreach ($data['orders'] as $item) {
            // Dispatch AfterDisplayOrder event for plugin content (e.g., reorder buttons)
            $afterDisplayHtml = J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayOrder', [$item])->getArgument('html', '');

            $rows[] = [
                'order_id'    => $item->order_id,
                'date'        => HTMLHelper::_('date', $item->created_on, $dateFormat),
                'invoice'     => $item->order_id,
                'status_name' => !empty($item->orderstatus_name) ? Text::_($item->orderstatus_name) : htmlspecialchars($item->order_state ?? '', ENT_QUOTES, 'UTF-8'),
                'status_css'  => !empty($item->orderstatus_cssclass) ? $item->orderstatus_cssclass : 'bg-secondary',
                'amount'      => CurrencyHelper::format(
                    (float) $item->order_total,
                    $item->currency_code ?? '',
                    (float) ($item->currency_value ?? 1)
                ),
                'view_url'  => Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($item->order_id)),
                'print_url' => Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($item->order_id) . '&tmpl=component'),
                'after_display_html' => $afterDisplayHtml,
            ];
        }

        $this->jsonResponse([
            'success'    => true,
            'orders'     => $rows,
            'total'      => $data['total'],
            'limitStart' => $limitStart,
            'limit'      => $limit,
        ]);
    }

    public function loadDownloads(): void
    {
        $user    = $this->app->getIdentity();
        $session = $this->app->getSession();
        $userId  = ($user && $user->id) ? (int) $user->id : 0;

        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');

        if ($userId === 0 && empty($guestToken)) {
            $this->jsonResponse(['success' => false]);

            return;
        }

        $search     = $this->input->getString('search', '');
        $limitStart = $this->input->getInt('limitstart', 0);
        $limit      = (int) $this->app->get('list_limit', 20);

        /** @var \J2Commerce\Component\J2commerce\Site\Model\MyprofileModel $model */
        $model = $this->getModel('Myprofile');
        $data  = $model->getDownloads($userId, $guestEmail, $limitStart, $limit, $search);

        $params     = J2CommerceHelper::config();
        $dateFormat = $params->get('date_format', 'Y-m-d');
        $nullDate   = '0000-00-00 00:00:00';

        $rows = [];

        foreach ($data['downloads'] as $dl) {
            $notGranted = empty($dl->access_granted) || $dl->access_granted === $nullDate;
            $expired = false;
            $limitReached = false;

            if (!$notGranted && $dl->access_expires !== $nullDate && strtotime($dl->access_expires) < time()) {
                $expired = true;
            }

            $downloadLimit = 0;
            if (!empty($dl->product_params)) {
                $downloadLimit = (int) (new \Joomla\Registry\Registry($dl->product_params))->get('download_limit', 0);
            }

            $limitCount = (int) ($dl->limit_count ?? 0);
            if ($downloadLimit > 0 && $limitCount >= $downloadLimit) {
                $limitReached = true;
            }

            $remaining = $downloadLimit > 0 ? max(0, $downloadLimit - $limitCount) : -1;
            $canDownload = !$notGranted && !$expired && !$limitReached && !empty($dl->product_file_save_name);

            // Determine status badge
            $statusBadge = '';
            if ($notGranted) {
                $statusBadge = 'pending';
            } elseif ($expired) {
                $statusBadge = 'expired';
            } elseif ($limitReached) {
                $statusBadge = 'limit_reached';
            }

            $rows[] = [
                'order_id'            => $dl->order_id,
                'file_name'           => $dl->product_file_display_name ?? '',
                'access_granted'      => $notGranted ? false : true,
                'access_expires'      => $notGranted ? '' : ($dl->access_expires === $nullDate ? '' : HTMLHelper::_('date', $dl->access_expires, $dateFormat)),
                'never_expires'       => !$notGranted && $dl->access_expires === $nullDate,
                'remaining'           => $remaining,
                'can_download'        => $canDownload,
                'status'              => $statusBadge,
                'download_url'        => $canDownload ? Route::_('index.php?option=com_j2commerce&task=myprofile.download&token=' . urlencode($dl->order_id) . '&fid=' . (int) $dl->j2commerce_productfile_id) : '',
            ];
        }

        $this->jsonResponse([
            'success'    => true,
            'downloads'  => $rows,
            'total'      => $data['total'],
            'limitStart' => $limitStart,
            'limit'      => $limit,
        ]);
    }

    public function changePassword(): void
    {
        // Load the plugin's language file — not auto-loaded during direct AJAX calls
        $this->app->getLanguage()->load('plg_j2commerce_app_changepasswords', JPATH_PLUGINS . '/j2commerce/app_changepasswords');

        if (!Session::checkToken('post')) {
            $this->jsonResponse(['success' => false, 'error' => ['general' => Text::_('JINVALID_TOKEN')]]);

            return;
        }

        $user = $this->app->getIdentity();

        if (!$user || !$user->id) {
            $this->jsonResponse(['success' => false, 'error' => ['general' => Text::_('JGLOBAL_YOU_MUST_LOGIN_FIRST')]]);

            return;
        }

        $password        = $this->input->post->getRaw('password');
        $confirmPassword = $this->input->post->getRaw('confirm_password');

        $errors = [];

        if (empty($password)) {
            $errors['password'] = Text::_('J2COMMERCE_APP_CHANGEPASSWORD_NEW_PASSWORD_REQUIRED');
        }

        if (empty($confirmPassword)) {
            $errors['confirm_password'] = Text::_('J2COMMERCE_APP_CHANGEPASSWORD_CONFIRM_PASSWORD_REQUIRED');
        }

        if (!empty($password) && !empty($confirmPassword) && $password !== $confirmPassword) {
            $errors['confirm_password'] = Text::_('J2COMMERCE_APP_CHANGEPASSWORD_NEW_PASSWORD_MISMATCH');
        }

        if ($errors) {
            $this->jsonResponse(['success' => false, 'error' => $errors]);

            return;
        }

        // Hash and save the new password
        $table = $user->getTable();
        $table->load((int) $user->id);
        $table->password = UserHelper::hashPassword($password);

        if (!$table->store()) {
            $this->jsonResponse(['success' => false, 'error' => ['general' => Text::_('J2COMMERCE_APP_CHANGEPASSWORD_SAVE_PROBLEM')]]);

            return;
        }

        $this->jsonResponse(['success' => Text::_('J2COMMERCE_APP_CHANGEPASSWORD_UPDATED_SUCCESSFULLY')]);
    }

    /**
     * Reorder - add items from a previous order to the cart.
     *
     * @return  void
     */
    public function reorder(): void
    {
        // Validate token to prevent CSRF
        $this->checkToken();

        $orderId = $this->input->getString('order_id', '');

        if (empty($orderId)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_ORDER_NOT_FOUND'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Load the order
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orders'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId)
            ->setLimit(1);

        $db->setQuery($query);
        $order = $db->loadObject();

        if (!$order) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_ORDER_NOT_FOUND'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        // Validate access
        $user = $this->app->getIdentity();
        $session = $this->app->getSession();
        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');

        $hasAccess = false;

        if ($user && $user->id && (int) $order->user_id === (int) $user->id) {
            $hasAccess = true;
        } elseif (!empty($guestToken) && !empty($guestEmail)
            && $guestToken === $order->token
            && $guestEmail === $order->user_email) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        // Load order items
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);
        $orderItems = $db->loadObjectList();

        if (empty($orderItems)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_MYPROFILE_ORDER_ITEMS_NOT_FOUND'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        // Get or create cart for current user
        /** @var \J2Commerce\Component\J2commerce\Administrator\Model\CartModel $cartModel */
        $cartModel = $this->getMvcFactory()->createModel('Cart', 'Administrator');

        if (!$cartModel) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_CART_ERROR'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $cart = $cartModel->getCart();

        if (empty($cart) || empty($cart->j2commerce_cart_id)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_CART_ERROR'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $cartId = (int) $cart->j2commerce_cart_id;
        $addedCount = 0;
        $errors = [];

        foreach ($orderItems as $item) {
            // Skip non-product items (like shipping, handling fees)
            if (!in_array($item->orderitem_type, ['normal', 'variable', 'flexivariable'])) {
                continue;
            }

            $productId = (int) $item->product_id;
            $variantId = (int) $item->variant_id;
            $quantity = (float) $item->orderitem_quantity;

            // Validate product exists and is enabled
            $productQuery = $db->getQuery(true)
                ->select($db->quoteName('j2commerce_product_id'))
                ->from($db->quoteName('#__j2commerce_products'))
                ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
                ->where($db->quoteName('enabled') . ' = 1')
                ->bind(':productId', $productId, ParameterType::INTEGER)
                ->setLimit(1);

            $db->setQuery($productQuery);
            $productExists = $db->loadResult();

            if (!$productExists) {
                $errors[] = Text::sprintf('COM_J2COMMERCE_REORDER_PRODUCT_NOT_AVAILABLE', $item->orderitem_name);
                continue;
            }

            // Prepare item for cart
            $cartItem = new \stdClass();
            $cartItem->cart_id = $cartId;
            $cartItem->product_id = $productId;
            $cartItem->variant_id = $variantId;
            $cartItem->product_qty = $quantity;
            $cartItem->product_options = $item->orderitem_attributes;

            // Add to cart
            $result = $cartModel->addItem($cartItem);

            if ($result !== false) {
                $addedCount += (int) $quantity;
            } else {
                $errors[] = Text::sprintf('COM_J2COMMERCE_REORDER_FAILED_TO_ADD', $item->orderitem_name);
            }
        }

        // Determine redirect based on plugin params
        // Check URL param first (set by the plugin), default to cart
        $redirectView = $this->input->getString('redirect', 'carts');
        $itemId = $this->input->getInt('Itemid', 0);

        $urlParams = 'index.php?option=com_j2commerce';

        if ($redirectView === 'checkout') {
            $urlParams .= '&view=checkout';
        } else {
            $urlParams .= '&view=carts';
        }

        // Use the same menu item as the reorder URL if provided
        if ($itemId > 0) {
            $urlParams .= '&Itemid=' . $itemId;
        }

        $redirectUrl = Route::_($urlParams, false);

        // Set message
        if ($addedCount > 0) {
           // $this->app->enqueueMessage(Text::sprintf('COM_J2COMMERCE_REORDER_SUCCESS', $addedCount), 'message');
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error, 'warning');
            }
        }

        if ($addedCount === 0 && !empty($errors)) {
            $this->app->enqueueMessage(Text::_('COM_J2COMMERCE_REORDER_NO_ITEMS_ADDED'), 'error');
            $this->app->redirect(Route::_('index.php?option=com_j2commerce&view=myprofile', false));

            return;
        }

        $this->app->redirect($redirectUrl);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function jsonResponse(array $data): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data);
        $this->app->close();
    }

    private function getMvcFactory(): \Joomla\CMS\MVC\Factory\MVCFactoryInterface
    {
        return $this->app->bootComponent('com_j2commerce')->getMvcFactory();
    }

    private function triggerEvent(string $eventName, array $args = []): string
    {
        PluginHelper::importPlugin('j2commerce');
        $dispatcher = $this->app->getDispatcher();
        $event = new \Joomla\Event\Event($eventName, $args);
        $dispatcher->dispatch($eventName, $event);

        return implode("\n", array_filter($event->getArgument('result', [])));
    }

    private function validateOrderAccess(object $order): bool
    {
        $user = $this->app->getIdentity();

        if ($user && $user->id > 0 && (int) $order->user_id === (int) $user->id) {
            return true;
        }

        $session = $this->app->getSession();
        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');

        if (!empty($guestToken) && !empty($guestEmail)
            && $guestToken === $order->token
            && $guestEmail === $order->user_email) {
            return true;
        }

        return false;
    }

    private function collectFormData(): array
    {
        $data = [];
        $postData = $this->input->post->getArray();

        foreach ($postData as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

            if (\is_string($value)) {
                $data[$key] = $this->input->getString($key, '');
            } elseif (\is_array($value)) {
                // Handle checkbox/multi-select fields (e.g. CustomFieldHelper checkbox type)
                $data[$key] = implode(',', array_map(
                    fn($v) => preg_replace('/[^\w\s\-.,@]/', '', (string) $v),
                    $value
                ));
            }
        }

        return $data;
    }
}
