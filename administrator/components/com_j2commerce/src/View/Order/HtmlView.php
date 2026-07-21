<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\View\Order;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderPayGrantHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\TaxHelper;
use J2Commerce\Component\J2commerce\Administrator\View\AdminAssetsTrait;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
    use AdminAssetsTrait;

    protected $form;
    protected $item;
    protected $state;
    protected bool $isNew           = false;
    protected array $orderStatuses   = [];
    protected string $dateFormat     = 'Y-m-d';
    protected string $timeFormat     = 'H:i:s';
    protected int $customerDays      = 0;
    protected int $totalSales        = 0;
    protected string $currencySymbol = '';
    protected string $currencyCode   = '';
    protected bool $hasPackingSlip   = false;
    protected ?Registry $params      = null;
    protected array $countries       = [];
    protected array $shippingMethods = [];
    protected array $paymentMethods  = [];
    protected string $takePaymentUrl = '';
    protected bool $originalMethodMissing = false;
    protected string $originalMethodLabel = '';
    protected bool $canRefundPayment = false;
    protected bool $canChargeBalance = false;
    protected float $refundableAmount = 0.0;
    protected float $balanceDueAmount = 0.0;

    /** Set by the billing/shipping tab templates before loading the shared address form. */
    protected string $addressFormType = 'billing';

    public function display($tpl = null): void
    {
        if (!J2CommerceHelper::canAccess('j2commerce.vieworders')) {
            throw new \Exception(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $this->loadAdminAssets();

        $model = $this->getModel();
        $model->setUseExceptions(true);

        $this->form          = $model->getForm();
        $this->item          = $model->getItem();
        $this->state         = $model->getState();
        $this->isNew         = empty((int) $this->item->j2commerce_order_id);
        $this->orderStatuses = $this->getOrderStatuses();
        $this->params        = ComponentHelper::getParams('com_j2commerce');

        $this->dateFormat = $this->params->get('date_format', 'Y-m-d');
        $this->timeFormat = $this->params->get('time_format', 'H:i:s');

        // Currency formatting
        $this->currencyCode   = $this->item->currency_code ?? '';
        $this->currencySymbol = CurrencyHelper::getSymbol($this->currencyCode) ?: $this->currencyCode;

        if (!empty($this->item->user_id) && (int) $this->item->user_id > 0) {
            $firstOrder         = $model->getFirstOrderDate((int) $this->item->user_id);
            $this->customerDays = $firstOrder ? (new \DateTime())->diff(new \DateTime($firstOrder))->days : 0;
            $this->totalSales   = $model->getOrderCountByUser((int) $this->item->user_id);
        }

        // Check if any published packing slip template exists
        $db      = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $psQuery = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__j2commerce_invoicetemplates'))
            ->where($db->quoteName('invoice_type') . ' = ' . $db->quote('packingslip'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->setLimit(1);
        $db->setQuery($psQuery);
        $this->hasPackingSlip = (bool) $db->loadResult();

        if (\is_array($errors = $model->getErrors()) && \count($errors)) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // Load payment plugin language files for translated names
        $this->loadPaymentPluginLanguages();

        // Bootstrap 5 collapse for address "View More"
        HTMLHelper::_('bootstrap.collapse', 'billingAddressCollapse');
        HTMLHelper::_('bootstrap.collapse', 'shippingAddressCollapse');

        // Bootstrap 5 modal for transaction details
        HTMLHelper::_('bootstrap.modal', 'transactionDetailsModal');

        // Bootstrap 5 tooltips for plugin-supplied history icons
        HTMLHelper::_('bootstrap.tooltip', '[data-bs-toggle="tooltip"]', ['trigger' => 'hover focus']);

        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_j2commerce.admin-order',
            'media/com_j2commerce/css/administrator/admin-order.css',
            [],
            ['version' => '6.0.8']
        );

        $layout = Factory::getApplication()->getInput()->getString('layout', 'view');

        if ($layout === 'edit') {
            $this->setLayout('edit');
            $wa->registerAndUseScript(
                'com_j2commerce.admin-order-edit',
                'media/com_j2commerce/js/administrator/admin-order-edit.js',
                [],
                ['defer' => true]
            );
            $wa->registerAndUseStyle(
                'com_j2commerce.order-wizard',
                'media/com_j2commerce/css/administrator/order-wizard.css'
            );
            // Customer/guest fields render via getInput() for label parity with the
            // hand-rolled fields, so load the core showon script explicitly to keep
            // the Registered/Guest data-showon toggle working.
            $wa->useScript('showon');
            HTMLHelper::_('bootstrap.modal', 'newCustomerModal');
            Text::script('COM_J2COMMERCE_ERROR_NETWORK');
            Text::script('COM_J2COMMERCE_ERROR_INVALID_REQUEST');
            Text::script('COM_J2COMMERCE_CONFIRM_REMOVE_ITEMS');
            Text::script('COM_J2COMMERCE_ADD_TO_ORDER');
            Text::script('JGLOBAL_NO_MATCHING_RESULTS');
            Text::script('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST');
            Text::script('COM_J2COMMERCE_CONFIRM_REMOVE_FEE');
            Text::script('COM_J2COMMERCE_CONFIRM_REMOVE_DISCOUNT');
            Text::script('COM_J2COMMERCE_NO_SAVED_ADDRESSES');
            Text::script('COM_J2COMMERCE_USE_THIS_ADDRESS');
            Text::script('COM_J2COMMERCE_HEADING_QTY');
            Text::script('JGLOBAL_SELECT_AN_OPTION');
            Text::script('COM_J2COMMERCE_ERROR_CUSTOMER_REQUIRED');
            Text::script('COM_J2COMMERCE_ADD');
            Text::script('COM_J2COMMERCE_N_ITEMS_1');
            Text::script('COM_J2COMMERCE_N_ITEMS_MORE');
            Text::script('COM_J2COMMERCE_INVENTORY');
            Text::script('COM_J2COMMERCE_FIELD_UNIT_PRICE');
            Text::script('COM_J2COMMERCE_EACH');
            Text::script('COM_J2COMMERCE_EMAIL_SKU');
            Text::script('JACTION_DELETE');
            Text::script('JPREVIOUS');
            Text::script('JNEXT');

            $this->countries = $model->getCountries();

            // Payment plugin forms/messages reference frontend com_j2commerce strings
            // (e.g. COM_J2COMMERCE_CART_RETURN); load the site language so they resolve in admin.
            Factory::getApplication()->getLanguage()->load('com_j2commerce', JPATH_SITE);

            $this->shippingMethods = $this->loadShippingMethods();
            $this->paymentMethods  = $this->loadPaymentMethods();

            // Original payment method: badge when still published, warning when it
            // is unpublished/uninstalled ('none' = the No-Payment-Needed marker).
            $savedType = (string) ($this->item->orderpayment_type ?? '');

            if ($savedType !== '' && $savedType !== 'none') {
                $available = false;

                foreach ($this->paymentMethods as $pm) {
                    if ((string) ($pm['element'] ?? '') === $savedType) {
                        $available = true;
                        break;
                    }
                }

                if (!$available) {
                    $this->originalMethodMissing = true;
                    $this->originalMethodLabel   = $this->resolvePaymentMethodLabel($savedType);
                }
            }

            // "Take Payment" hands off to the site pseudo-checkout via a short-lived
            // HMAC grant — gateways charge natively there, never inside this panel.
            if (!$this->isNew && OrderPayGrantHelper::isPayable($this->item)) {
                $this->takePaymentUrl = OrderPayGrantHelper::buildUrl((int) $this->item->j2commerce_order_id);
            }

            // Add-new-address modal (billing/shipping steps) reuses the Customer
            // view's form/save endpoints; only meaningful for registered customers.
            if (!$this->isNew && (int) ($this->item->user_id ?? 0) > 0) {
                $baseUri = Uri::base(true);
                $this->getDocument()->addScriptOptions('com_j2commerce.order_addresses', [
                    'token'    => Session::getFormToken(),
                    'formUrl'  => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxGetAddressForm&format=raw',
                    'saveUrl'  => $baseUri . '/index.php?option=com_j2commerce&task=customer.ajaxSaveAddress&format=raw',
                    'zonesUrl' => $baseUri . '/index.php?option=com_j2commerce&task=manufacturer.getZones',
                ]);
            }

            // Refund / charge-balance actions on the Summary totals rail. All figures
            // are ledger (display-currency) values, matching the payment_balance layout.
            $orderPk = (int) ($this->item->j2commerce_order_id ?? 0);

            if (!$this->isNew && $orderPk > 0 && OrderTransactionHelper::hasLedger($orderPk)) {
                $paymentType = (string) ($this->item->orderpayment_type ?? '');
                $rate        = (float) ($this->item->currency_value ?? 1.0);
                $rate        = $rate > 0 ? $rate : 1.0;
                $decimals    = CurrencyHelper::getDecimalPlace((string) ($this->item->currency_code ?? ''));
                $threshold   = 10 ** -$decimals / 2;

                $orderTotalDisplay      = round((float) ($this->item->order_total ?? 0) * $rate, $decimals);
                $this->refundableAmount = OrderTransactionHelper::getRefundable($orderPk);
                $this->balanceDueAmount = max(0.0, $orderTotalDisplay - max(0.0, OrderTransactionHelper::getNetPaid($orderPk)));

                $this->canRefundPayment = $this->refundableAmount > $threshold && $paymentType !== '' && $paymentType !== 'none';
                $this->canChargeBalance = $this->balanceDueAmount > $threshold
                    && $model->getSupplementalCapability($this->item) === 'token_charge'
                    && $model->resolveStoredPaymentProfile((int) ($this->item->user_id ?? 0), $paymentType) !== null;

                if ($this->canRefundPayment) {
                    HTMLHelper::_('bootstrap.modal', 'refundPaymentModal');
                }

                if ($this->canChargeBalance) {
                    HTMLHelper::_('bootstrap.modal', 'chargeBalanceModal');
                }
            }
        } elseif ($layout === 'packingslip') {
            $this->setLayout('packingslip');
        } elseif ($layout === 'invoice') {
            $this->setLayout('invoice');
        } else {
            $this->setLayout('view');
            $wa->registerAndUseScript(
                'com_j2commerce.admin-order-view',
                'media/com_j2commerce/js/administrator/admin-order.js',
                [],
                ['defer' => true]
            );
            Text::script('JACTION_DELETE');
            Text::script('COM_J2COMMERCE_ORDER_HISTORY_ROW_ICONS_LABEL');
            Text::script('COM_J2COMMERCE_ORDER_NOTE');
        }

        $this->addToolbar($layout);

        parent::display($tpl);
    }

    protected function addToolbar(string $layout = 'view'): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $canDo         = ContentHelper::getActions('com_j2commerce');
        $canEditOrders = J2CommerceHelper::canAccess('j2commerce.editorders');
        $user          = Factory::getApplication()->getIdentity();
        $checkedOut    = !empty($this->item->checked_out) && (int) $this->item->checked_out !== (int) $user->id;
        $toolbar       = $this->getDocument()->getToolbar();

        $orderDisplay = $this->item->order_id ?? $this->item->invoice ?? Text::_('COM_J2COMMERCE_ORDER');

        if ($layout === 'edit') {
            $title = $this->isNew
                ? Text::_('COM_J2COMMERCE_NEW_ORDER')
                : Text::_('COM_J2COMMERCE_CREATE_EDIT_ORDER') . ': #' . $orderDisplay;

            ToolbarHelper::title($title, 'fa-solid fa-list-alt');

            $backUrl = $this->isNew
                ? 'index.php?option=com_j2commerce&view=orders'
                : 'index.php?option=com_j2commerce&view=order&layout=view&id=' . (int) $this->item->j2commerce_order_id;

            // Core back() ignores its URL argument (always history.back()) — use an
            // explicit link so Back reliably lands on the order view (list when new).
            $toolbar->linkButton('back')
                ->text('JTOOLBAR_BACK')
                ->url($backUrl)
                ->icon('fa-solid fa-arrow-left');
            $toolbar->cancel('order.cancel', 'JTOOLBAR_CLOSE');

            // A brand-new order is created via the Basic tab's "Next" button, not
            // the toolbar — Save/Apply have nothing to persist until it exists.
            if (!$this->isNew && !$checkedOut && $canEditOrders && $canDo->get('core.edit')) {
                $toolbar->apply('order.apply');
                $toolbar->save('order.save');
            }
        } else {
            ToolbarHelper::title(
                Text::_('COM_J2COMMERCE_ORDER') . ': #' . $orderDisplay,
                'fa-solid fa-list-alt'
            );

            $toolbar->cancel('order.cancel', 'JTOOLBAR_CLOSE');

            if ($canEditOrders && $canDo->get('core.edit')) {
                $toolbar->linkButton('edit')
                    ->text('COM_J2COMMERCE_EDIT_ORDER')
                    ->url('index.php?option=com_j2commerce&view=order&layout=edit&id=' . (int) $this->item->j2commerce_order_id)
                    ->icon('fa-solid fa-pen-to-square');
            }

            $toolbar->linkButton('print')
                ->text('COM_J2COMMERCE_PRINT_INVOICE')
                ->url('index.php?option=com_j2commerce&view=order&layout=invoice&tmpl=component&id=' . (int) $this->item->j2commerce_order_id)
                ->icon('icon-print')
                ->attributes(['target' => '_blank']);

            if ($this->hasPackingSlip) {
                $toolbar->linkButton('packingslip')
                    ->text('COM_J2COMMERCE_PRINT_PACKING_SLIP')
                    ->url('index.php?option=com_j2commerce&task=order.packingSlip&id=' . (int) $this->item->j2commerce_order_id)
                    ->icon('icon-list')
                    ->attributes(['target' => '_blank']);
            }
        }

        $toolbar->divider();
        ToolbarHelper::help(Text::_('COM_J2COMMERCE_ORDERS'), true, 'https://docs.j2commerce.com/v6/sales/orders');
    }

    protected function getOrderStatuses(): array
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_orderstatus_id', 'value'),
                $db->quoteName('orderstatus_name', 'text'),
                $db->quoteName('orderstatus_cssclass', 'cssclass'),
            ])
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    protected function loadPaymentPluginLanguages(): void
    {
        $lang = Factory::getLanguage();

        foreach (PluginHelper::getPlugin('j2commerce') as $plugin) {
            if (str_starts_with($plugin->name, 'payment_')) {
                $lang->load('plg_j2commerce_' . $plugin->name, JPATH_ADMINISTRATOR);
                $lang->load('plg_j2commerce_' . $plugin->name, JPATH_PLUGINS . '/j2commerce/' . $plugin->name);
            }
        }
    }

    /**
     * Published shipping rates for this order via GetShippingRates.
     *
     * Shipping plugins expect a checkout context: they resolve the destination
     * geozone from the 'j2commerce' session namespace and read line items via
     * $order->getItems(). Seed that session from THIS order's ship-to address and
     * wrap the order so the plugins compute the same rates they would at checkout.
     */
    protected function loadShippingMethods(): array
    {
        $order = $this->item;

        if (empty($order->order_id) || empty($order->orderitems)) {
            return [];
        }

        $info    = $order->orderinfo ?? null;
        $session = Factory::getApplication()->getSession();
        $session->set('shipping_address_id', 0, 'j2commerce');
        $session->set('shipping_country_id', (int) ($info->shipping_country_id ?? 0), 'j2commerce');
        $session->set('shipping_zone_id', (int) ($info->shipping_zone_id ?? 0), 'j2commerce');
        // Carrier plugins (UPS/USPS/FedEx/DHL…) additionally read the postcode/city.
        $session->set('shipping_postal_code', (string) ($info->shipping_zip ?? ''), 'j2commerce');
        $session->set('shipping_city', (string) ($info->shipping_city ?? ''), 'j2commerce');

        // Adapter: exposes getItems() + getShippingAddress() (the two order methods the
        // shipping plugins probe) and proxies every other order property they may read.
        $shippingOrder = new class ($order) {
            public function __construct(private object $order)
            {
            }

            public function getItems(): array
            {
                $items = $this->order->orderitems ?? [];

                // Alias the cart-item field names the standard rate calculators read.
                foreach ($items as $it) {
                    $it->product_subtotal ??= (float) ($it->orderitem_finalprice ?? 0);
                    $it->weight_total     ??= (float) ($it->orderitem_weight_total ?? 0);
                }

                return $items;
            }

            /** Ship-to in the shape carrier plugins probe first (address_1/city/zone_id/zip/country_id). */
            public function getShippingAddress(): object
            {
                $info = $this->order->orderinfo ?? null;

                return (object) [
                    'address_1'  => (string) ($info->shipping_address_1 ?? ''),
                    'city'       => (string) ($info->shipping_city ?? ''),
                    'zone_id'    => (int) ($info->shipping_zone_id ?? 0),
                    'zip'        => (string) ($info->shipping_zip ?? ''),
                    'country_id' => (int) ($info->shipping_country_id ?? 0),
                ];
            }

            public function __get(string $name)
            {
                return $this->order->$name ?? null;
            }

            public function __isset(string $name): bool
            {
                return isset($this->order->$name);
            }
        };

        try {
            $rates = [];

            foreach (J2CommerceHelper::plugin()->eventWithArray('GetShippingRates', [$shippingOrder]) as $result) {
                if (\is_array($result) && isset($result['element'])) {
                    $rates[] = $result;
                } elseif (\is_array($result)) {
                    $rates = array_merge($rates, $result);
                }
            }

            // Carrier plugins (UPS/FedEx/…) return tax=0 and defer to the tax engine via
            // tax_class_id. Compute the shipping tax from that class + the order's ship-to
            // geozone so every method honors its tax setting (shipping_standard already does).
            $geozones    = TaxHelper::getCustomerGeozones((object) [
                'country_id' => (int) ($info->shipping_country_id ?? 0),
                'zone_id'    => (int) ($info->shipping_zone_id ?? 0),
            ]);
            $isIncluding = (int) ComponentHelper::getParams('com_j2commerce')->get('config_including_tax', 0) === 1;

            foreach ($rates as &$rate) {
                $taxClassId = (int) ($rate['tax_class_id'] ?? 0);

                if ((float) ($rate['tax'] ?? 0) > 0 || $taxClassId < 1 || empty($geozones)) {
                    continue;
                }

                $pct = 0.0;

                foreach (TaxHelper::getAllTaxRatesForGeozone($taxClassId, $geozones) as $r) {
                    $pct += (float) ($r->tax_percent ?? 0);
                }

                $price       = (float) ($rate['price'] ?? 0);
                $rate['tax'] = $pct <= 0
                    ? 0.0
                    : ($isIncluding ? $price * $pct / (100 + $pct) : $price * $pct / 100);
            }
            unset($rate);

            return $rates;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Published payment methods via GetPaymentPlugins (mirrors checkout); [] if none. */
    protected function loadPaymentMethods(): array
    {
        try {
            $methods = [];

            foreach (J2CommerceHelper::plugin()->eventWithArray('GetPaymentPlugins', [$this->item]) as $result) {
                if (\is_array($result) && isset($result['element'])) {
                    $methods[] = $result;
                } elseif (\is_array($result)) {
                    $methods = array_merge($methods, $result);
                }
            }

            return $methods;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Display name for a payment plugin element that is not among the published
     * methods: installed-but-unpublished → its translated extension name;
     * uninstalled → the raw stored element (all that remains of it).
     */
    protected function resolvePaymentMethodLabel(string $element): string
    {
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element);
        $db->setQuery($query);
        $name = (string) ($db->loadResult() ?? '');

        if ($name === '') {
            return $element;
        }

        // Unpublished plugins are skipped by loadPaymentPluginLanguages() — load
        // the sys language here so the name key translates.
        $lang = Factory::getApplication()->getLanguage();
        $ext  = 'plg_j2commerce_' . $element;
        $lang->load($ext . '.sys', JPATH_ADMINISTRATOR)
            || $lang->load($ext . '.sys', JPATH_PLUGINS . '/j2commerce/' . $element);

        return Text::_($name);
    }
}
