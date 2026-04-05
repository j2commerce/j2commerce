<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\View\Myprofile;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\PaymentMethodsHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\UtilitiesHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    public array $orders = [];
    public ?Pagination $pagination = null;
    public array $addresses = [];
    public array $downloads = [];
    public int $downloadsTotal = 0;
    public ?object $params = null;
    public $currency = null;
    public ?\Joomla\CMS\User\User $user = null;
    public bool $isGuest = false;
    public ?object $order = null;
    public array $orderItems = [];
    public ?object $orderInfo = null;
    public array $orderHistory = [];
    public array $orderShippings = [];
    public array $orderTaxes = [];
    public array $orderFees = [];
    public ?object $address = null;
    public array $customFields = [];
    public string $pluginTabHtml = '';
    public string $pluginContentHtml = '';
    public string $topMessagesHtml = '';
    public ?Registry $menuItemParams = null;
    public bool $useUnifiedPaymentTab = false;
    public array $paymentMethodsGrouped = [];

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        UtilitiesHelper::sendNoCacheHeaders();

        $this->params   = J2CommerceHelper::config();
        $this->currency = J2CommerceHelper::currency();
        $this->user     = $app->getIdentity();

        $menu   = $app->getMenu();
        $active = $menu->getActive();
        $this->menuItemParams = \is_object($active) ? $active->getParams() : new Registry('{}');

        $layout  = $this->getLayout();
        $session = $app->getSession();

        // Check if guest access via session tokens
        $guestToken = $session->get('guest_order_token', '', 'j2commerce');
        $guestEmail = $session->get('guest_order_email', '', 'j2commerce');
        $this->isGuest = (!$this->user || (int) $this->user->id === 0) && $guestToken && $guestEmail;

        // If not logged in and not guest, show login page
        if ((!$this->user || (int) $this->user->id === 0) && !$this->isGuest) {
            $this->setLayout('default_login');
            $this->_prepareDocument();
            parent::display($tpl);

            return;
        }

        /** @var \J2Commerce\Component\J2commerce\Site\Model\MyprofileModel $model */
        $model  = $this->getModel();
        $userId = $this->user ? (int) $this->user->id : 0;

        if ($layout === 'order' || $layout === 'packingslip') {
            $orderId     = $app->getInput()->getString('order_id', '');
            $this->order = $model->getOrder($orderId);

            if ($this->order && !$this->validateOrderAccess($this->order, $userId, $guestToken, $guestEmail)) {
                $this->order = null;
            }

            if ($this->order) {
                $this->orderItems     = $model->getOrderItems($orderId);
                $this->orderInfo      = $model->getOrderInfo($orderId);
                $this->orderHistory   = $model->getOrderHistory($orderId);
                $this->orderShippings = $model->getOrderShippings($orderId);
                $this->orderTaxes     = $model->getOrderTaxes($orderId);
                $this->orderFees      = $model->getOrderFees($orderId);
            }
        } elseif ($layout === 'address') {
            $addressId = $app->getInput()->getInt('address_id', 0);

            if ($addressId > 0) {
                $mvcFactory = $app->bootComponent('com_j2commerce')->getMVCFactory();
                $table      = $mvcFactory->createTable('Address', 'Administrator');
                $table->load($addressId);

                // Verify ownership
                if ((int) $table->user_id === $userId && $userId > 0) {
                    $this->address = $table;
                }
            }

            // Get custom fields for the address type
            $type               = $app->getInput()->getString('type', $this->address->type ?? 'billing');
            $area               = ($type === 'shipping') ? 'shipping' : 'billing';
            $this->customFields = CustomFieldHelper::getFieldsByArea($area);
        } else {
            // Default layout -- load orders + addresses + downloads
            $limitStart = $app->getInput()->getInt('limitstart', 0);
            $limit      = (int) $app->get('list_limit', 20);

            $orderData       = $model->getOrders($userId, $guestToken, $guestEmail, $limitStart, $limit);
            $this->orders    = $orderData['orders'];

            $total            = $orderData['total'];
            $this->pagination = new Pagination($total, $limitStart, $limit);

            // Load addresses (only for logged-in users)
            if ($userId > 0) {
                $mvcFactory      = $app->bootComponent('com_j2commerce')->getMVCFactory();
                $addressModel    = $mvcFactory->createModel('Myprofiles', 'Administrator', ['ignore_request' => true]);
                $this->addresses = $addressModel->getAddressesByUser($userId);
            }

            // Load downloads if enabled
            if ($this->params->get('download_area', 1)) {
                $downloadData = $model->getDownloads($userId, $guestEmail, 0, $limit);
                $this->downloads = $downloadData['downloads'];
                $this->downloadsTotal = $downloadData['total'];
            }

            // Unified Payment Methods tab - check first before dispatching legacy events
            // This must be done BEFORE the legacy MyProfileTab events are dispatched
            if ($userId > 0) {
                $paymentMethods = PaymentMethodsHelper::getPaymentMethods($userId);

                if (!empty($paymentMethods)) {
                    $this->useUnifiedPaymentTab = true;
                    $this->paymentMethodsGrouped = PaymentMethodsHelper::groupByProvider($paymentMethods);
                }
            }

            // Payment plugins use the unified GetSavedPaymentMethods event instead
            $this->pluginTabHtml     = J2CommerceHelper::plugin()->eventWithHtml('MyProfileTab')->getArgument('html', '');
            $this->pluginContentHtml = J2CommerceHelper::plugin()->eventWithHtml('MyProfileTabContent', [$this->orders])->getArgument('html', '');

            // These plugin events should always run regardless of payment tab state
            $this->topMessagesHtml   = J2CommerceHelper::plugin()->eventWithHtml('MyProfileTopMessages', [$this->orders])->getArgument('html', '');
            $this->afterDisplayOrder = J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayOrder', [$this->orders])->getArgument('html', '');
        }

        // Initialize Bootstrap 5 tabs and modal via Joomla HTMLHelper
        HTMLHelper::_('bootstrap.tab', 'j2commerceProfileTabs');
        HTMLHelper::_('bootstrap.modal', 'j2commerceOrderModal');

        // Register JS
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript('com_j2commerce.myprofile', 'media/com_j2commerce/js/site/myprofile.js', [], ['defer' => true]);

        // Register payment methods JS if unified tab is active
        if ($this->useUnifiedPaymentTab) {
            $wa->registerAndUseScript('com_j2commerce.payment-methods', 'media/com_j2commerce/js/site/payment-methods.js', [], ['defer' => true]);

            // Payment methods language strings for JS
            Text::script('COM_J2COMMERCE_PAYMENT_METHODS_CONFIRM_DELETE');
            Text::script('COM_J2COMMERCE_PAYMENT_METHODS_DELETED');
            Text::script('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT_SET');
            Text::script('COM_J2COMMERCE_PAYMENT_METHODS_ERROR');
            Text::script('COM_J2COMMERCE_PAYMENT_METHODS_NETWORK_ERROR');
        }

        $this->getDocument()->addScriptOptions('com_j2commerce.myprofile', [
            'baseUrl'   => Route::_('index.php?option=com_j2commerce', false),
            'csrfToken' => Session::getFormToken(),
            'listLimit' => (int) $app->get('list_limit', 20),
        ]);

        // Register language strings for JS (used in AJAX-rebuilt table headers)
        Text::script('COM_J2COMMERCE_ORDER_DATE');
        Text::script('COM_J2COMMERCE_INVOICE_NO');
        Text::script('COM_J2COMMERCE_ORDER_STATUS');
        Text::script('COM_J2COMMERCE_ORDER_AMOUNT');
        Text::script('COM_J2COMMERCE_ACTIONS');
        Text::script('COM_J2COMMERCE_NO_ORDERS');
        Text::script('COM_J2COMMERCE_ITEMS');
        // Pre-compute sprintf string for JS since Text::script() doesn't support sprintf
        Text::script('COM_J2COMMERCE_SELECT_ZONE', Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE')));
        Text::script('COM_J2COMMERCE_MYPROFILE_DELETE_CONFIRM');
        Text::script('COM_J2COMMERCE_MYPROFILE_DISCARD_CHANGES');
        Text::script('COM_J2COMMERCE_ORDER_VIEW');
        Text::script('COM_J2COMMERCE_ORDER_PRINT');
        Text::script('JLIB_HTML_PAGINATION');

        // Download-related language strings for JS
        Text::script('COM_J2COMMERCE_ORDER');
        Text::script('COM_J2COMMERCE_FILES');
        Text::script('COM_J2COMMERCE_ACCESS_EXPIRES');
        Text::script('COM_J2COMMERCE_DOWNLOADS_REMAINING');
        Text::script('COM_J2COMMERCE_DOWNLOAD');
        Text::script('COM_J2COMMERCE_DOWNLOAD_PENDING');
        Text::script('COM_J2COMMERCE_EXPIRED');
        Text::script('COM_J2COMMERCE_LIMIT_REACHED');
        Text::script('COM_J2COMMERCE_NEVER_EXPIRES');
        Text::script('COM_J2COMMERCE_NO_DOWNLOADS');

        Text::script('COM_J2COMMERCE_LOADING');

        $this->_prepareDocument();
        parent::display($tpl);
    }

    private function validateOrderAccess(object $order, int $userId, string $guestToken, string $guestEmail): bool
    {
        if ($userId > 0 && (int) $order->user_id === $userId) {
            return true;
        }

        if (!empty($guestToken) && !empty($guestEmail)
            && $guestToken === $order->token
            && $guestEmail === $order->user_email) {
            return true;
        }

        return false;
    }

    protected function _prepareDocument(): void
    {
        $title = $this->menuItemParams->get('page_title', '');

        if (empty($title)) {
            $title = Text::_('COM_J2COMMERCE_MYPROFILE');
        }

        $this->getDocument()->setTitle($title);

        if ($this->menuItemParams->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->menuItemParams->get('menu-meta_description'));
        }

        if ($this->menuItemParams->get('menu-meta_keywords')) {
            $this->getDocument()->setMetaData('keywords', $this->menuItemParams->get('menu-meta_keywords'));
        }

        if ($this->menuItemParams->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->menuItemParams->get('robots'));
        }
    }
}
