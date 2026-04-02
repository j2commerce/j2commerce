<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentPaypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Extension;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Base;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Payment;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Helper\PayPalCurrencyHelper;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalClient;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalOrders;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalRefunds;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalWebhooks;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * PayPal Payment Plugin for J2Commerce - REST API v2 with Smart Payment Buttons
 *
 * Provides PayPal Smart Payment Buttons integration with REST API v2, webhooks, and refund support
 */
final class PaymentPaypal extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

    protected $_name = 'payment_paypal';

    protected $_type = 'j2commerce';

    private Language $language;

    private Payment $payment;

    private Base $base;

    private ?PayPalClient $paypalClient = null;

    private ?PayPalOrders $paypalOrders = null;

    private ?PayPalWebhooks $paypalWebhooks = null;

    private ?PayPalRefunds $paypalRefunds = null;

    private static bool $loggerAdded = false;

    public function __construct(
        DispatcherInterface $dispatcher,
        array $config,
        Language $language,
        DatabaseInterface $db
    ) {
        parent::__construct($dispatcher, $config);

        $this->language = $language;
        $this->setDatabase($db);

        $this->language->load('com_j2commerce', JPATH_ADMINISTRATOR);
        $this->payment = new Payment($dispatcher, $config);
        $this->payment->_element = $this->_name;
        $this->base = new Base($dispatcher, $config);
    }

    private function log(string $message, int $priority = Log::DEBUG): void
    {
        $debug = (int) $this->params->get('debug', 0);

        if ($priority === Log::ERROR || $debug === 1) {
            if (!self::$loggerAdded) {
                Log::addLogger(
                    ['text_file' => 'payment_paypal.php'],
                    Log::ALL,
                    ['payment_paypal', 'j2commerce.paypal']
                );
                self::$loggerAdded = true;
            }

            Log::add($message, $priority, 'payment_paypal');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceAcceptSubscriptionPayment' => 'onAcceptSubscriptionPayment',
            'onJ2CommerceCalculateFees'             => 'onCalculateFees',
            'onJ2CommerceGetPaymentOptions'         => 'onGetPaymentOptions',
            'onJ2CommerceGetPaymentPlugins'         => 'onGetPaymentPlugins',
            'onJ2CommercePrePayment'                => 'onPrePayment',
            'onJ2CommercePostPayment'               => 'onPostPayment',
            'onJ2CommerceProcessWebhook'            => 'onProcessWebhook',
            'onJ2CommerceRefundPayment'             => 'onRefundPayment',
            'onJ2CommerceAfterSubscriptionCanceled' => 'onAfterSubscriptionCanceled',
            'onJ2CommercePaymentCreateOrder'        => 'onPaymentCreateOrder',
            'onJ2CommercePaymentCaptureOrder'       => 'onPaymentCaptureOrder',
            'onJ2CommerceCheckoutStart'             => 'onCheckoutStart',
            'onJ2CommerceGetQuickIcons'             => 'onGetQuickIcons',
            'onJ2CommerceGetDashboardMessages'      => 'onGetDashboardMessages',
        ];
    }

    public function onAcceptSubscriptionPayment(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args = $event->getArguments();
        $element = $args[0] ?? null;

        if ($element !== $this->_name) {
            return;
        }

        $event->setArgument('result', true);
    }

    public function onCalculateFees(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args = $event->getArguments();
        $element = $args[0] ?? null;
        $order = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $paymentMethod = '';

        if (method_exists($order, 'get_payment_method')) {
            $paymentMethod = $order->get_payment_method();
        } elseif (isset($order->orderpayment_type)) {
            $paymentMethod = $order->orderpayment_type;
        }

        if ($paymentMethod !== $this->_name) {
            return;
        }

        $total = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
        $surcharge = 0.0;
        $surchargePercent = (float) $this->params->get('surcharge_percent', 0);
        $surchargeFixed = (float) $this->params->get('surcharge_fixed', 0);

        if ($surchargePercent > 0 || $surchargeFixed > 0) {
            if ($surchargePercent > 0) {
                $surcharge += ($total * $surchargePercent) / 100;
            }

            if ($surchargeFixed > 0) {
                $surcharge += $surchargeFixed;
            }

            $name = $this->params->get('surcharge_name', Text::_('COM_J2COMMERCE_CART_SURCHARGE'));
            $taxClassId = $this->params->get('surcharge_tax_class_id', '');
            $taxable = !empty($taxClassId) && (int) $taxClassId > 0;

            if ($surcharge > 0) {
                $order->add_fee($name, round($surcharge, 2), $taxable, $taxClassId);
            }
        }
    }

    public function onGetPaymentOptions(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args = $event->getArguments();
        $element = $args[0] ?? null;
        $order = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $found = true;

        $geozoneId = (int) $this->params->get('geozone_id', 0);

        if ($geozoneId > 0) {
            $order->setAddress();
            $address = $order->getBillingAddress();
            $found = $this->checkGeozone($geozoneId, $address);
        }

        if ($found) {
            $found = $this->checkSubtotalLimits($order->order_subtotal);
        }

        $event->setArgument('result', $found);
    }

    public function onGetPaymentPlugins(Event $event): void
    {
        $result = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_name,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL')),
            'image'   => $this->params->get('display_image', ''),
        ];
        $event->setArgument('result', $result);
    }

    public function onCheckoutStart(Event $event): void
    {
        // Register PayPal checkout JS during initial checkout page load.
        // This event fires in the checkout View::display(), before the page renders.
        // The JS uses MutationObserver to detect when the PayPal button container
        // appears in the DOM (via AJAX innerHTML) and dynamically loads the PayPal SDK.
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'plg_j2commerce_payment_paypal.checkout',
            'media/plg_j2commerce_payment_paypal/js/paypal-checkout.js',
            [],
            ['defer' => true]
        );
    }

    public function onPrePayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result = $event->getArgument('result', []);
        $result[] = $this->_prePayment($data);
        $event->setArgument('result', $result);
    }

    public function onPostPayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result = $event->getArgument('result', []);
        $result[] = $this->_postPayment((object) $data);
        $event->setArgument('result', $result);
    }

    public function onProcessWebhook(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';

        if ($element !== $this->_name) {
            return;
        }

        $rawBody = file_get_contents('php://input');

        if (!$rawBody) {
            $event->setArgument('result', ['status' => 400, 'message' => 'No body']);
            return;
        }

        $webhookId = $this->params->get('webhook_id', '');

        if (empty($webhookId)) {
            $event->setArgument('result', ['status' => 400, 'message' => 'Webhook ID not configured']);
            return;
        }

        try {
            $webhooks = $this->getPayPalWebhooks();

            if (!$webhooks->verifySignature($rawBody)) {
                $event->setArgument('result', ['status' => 401, 'message' => 'Invalid signature']);
                return;
            }

            $result = $webhooks->handleEvent($rawBody, $this->params);
            $event->setArgument('result', $result);
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook error: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            $event->setArgument('result', ['status' => 500, 'message' => 'Internal error']);
        }
    }

    public function onRefundPayment(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $orderId = $args[1] ?? 0;
        $amount = $args[2] ?? null;

        if ($element !== $this->_name) {
            return;
        }

        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                $event->setArgument('result', ['success' => false, 'error' => 'Order not found']);
                return;
            }

            $captureId = $orderTable->transaction_id;

            if (empty($captureId)) {
                $event->setArgument('result', ['success' => false, 'error' => 'No capture ID found']);
                return;
            }

            $currency = $this->getCurrency($orderTable);

            $refunds = $this->getPayPalRefunds();
            $result = $refunds->refundCapture($captureId, $amount, $currency);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $refundId = $result['body']['id'] ?? '';
                $refundedStateId = (int) $this->params->get('refunded_state_id', 7);

                $orderTable->order_state_id = $refundedStateId;
                $transactionDetails = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['refund_id'] = $refundId;
                $transactionDetails['refunded_at'] = date('Y-m-d H:i:s');
                $transactionDetails['refund_amount'] = $amount;
                $orderTable->transaction_details = json_encode($transactionDetails);
                $orderTable->store();

                OrderHistoryHelper::add(
                    orderId: $orderTable->order_id,
                    comment: Text::sprintf(
                        'COM_J2COMMERCE_ORDER_HISTORY_REFUND_PROCESSED',
                        $amount ?? $orderTable->order_total,
                        $currency
                    ),
                    orderStateId: $refundedStateId
                );

                $event->setArgument('result', ['success' => true, 'refund_id' => $refundId]);
            } else {
                $errorMessage = $result['body']['message'] ?? 'Refund failed';
                Factory::getApplication()->getLogger()->error(
                    "PayPal refund failed: $errorMessage",
                    ['category' => 'j2commerce.paypal']
                );

                $event->setArgument('result', ['success' => false, 'error' => $errorMessage]);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal refund exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            $event->setArgument('result', ['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function onAfterSubscriptionCanceled(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';

        if ($element !== $this->_name) {
            return;
        }

        $event->setArgument('result', ['status' => 'subscription_cancellation_registered']);
    }

    public function onPaymentCreateOrder(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $this->log('onPaymentCreateOrder: Starting PayPal order creation for order_id: ' . ($data['order_id'] ?? 'N/A'));

        $result = $this->createPayPalOrder($data);
        $event->setArgument('result', $result);
    }

    public function onPaymentCaptureOrder(Event $event): void
    {
        $args = $event->getArguments();
        $element = $args[0] ?? '';
        $data = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $paypalOrderId = $data['paypal_order_id'] ?? '';
        $orderId = $data['order_id'] ?? '';

        $result = $this->capturePayPalOrder($paypalOrderId, $orderId);
        $event->setArgument('result', $result);
    }

    public function _prePayment(array $data): string
    {
        $this->log('_prePayment: Preparing PayPal payment form for order_id: ' . ($data['order_id'] ?? 'N/A'));

        $vars = new \stdClass();

        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'] ?? 0;
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type = $this->_name;

        $vars->display_name = Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL'));
        $vars->display_image = $this->params->get('display_image', '');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');

        $order = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');
        $order->load(['order_id' => $vars->order_id]);

        $sandbox = (int) $this->params->get('sandbox', 0);
        $vars->sandbox = (bool) $sandbox;
        $vars->client_id = $sandbox
            ? $this->params->get('sandbox_client_id', '')
            : $this->params->get('client_id', '');

        $vars->currency_code = $this->getCurrency($order);

        $vars->create_order_url = Route::_(
            'index.php?option=com_j2commerce&task=checkout.createPayPalOrder&format=json',
            false
        );
        $vars->capture_order_url = Route::_(
            'index.php?option=com_j2commerce&task=checkout.capturePayPalOrder&format=json',
            false
        );
        $vars->csrf_token = Session::getFormToken();
        $vars->debug = (int) $this->params->get('debug', 0);

        $this->log('_prePayment: Prepared vars - order_id: ' . $vars->order_id . ', currency: ' . $vars->currency_code . ', amount: ' . $vars->orderpayment_amount);

        return $this->_getLayout('prepayment', $vars);
    }

    public function _postPayment(object $data): string
    {
        $app = Factory::getApplication();
        $vars = new \stdClass();
        $paction = $app->input->getString('paction');
        $html = '';

        $this->log('_postPayment: Processing payment response with paction: ' . $paction);

        switch ($paction) {
            case 'display':
                $vars->onafterpayment_text = Text::_($this->params->get('onafterpayment', ''));
                $html = $this->_getLayout('postpayment', $vars);
                $html .= $this->base->_displayArticle();
                $this->log('_postPayment: Displaying success message');
                break;

            case 'cancel':
                $vars->message = Text::_($this->params->get('oncancelpayment', 'PLG_J2COMMERCE_PAYMENT_PAYPAL_CANCELLED'));
                $html = $this->_getLayout('message', $vars);
                $this->log('_postPayment: Payment cancelled by user');
                break;

            default:
                $vars->message = Text::_($this->params->get('onerrorpayment', 'PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'));
                $html = $this->_getLayout('message', $vars);
                $this->log('_postPayment: Payment error - paction: ' . $paction, Log::ERROR);
                break;
        }

        return $html;
    }

    public function createPayPalOrder(array $data): array
    {
        try {
            $orderId = $data['order_id'] ?? '';

            if (empty($orderId)) {
                $this->log('createPayPalOrder: Missing order_id in request', Log::ERROR);
                return ['success' => false, 'error' => 'Missing order_id'];
            }

            $this->log('createPayPalOrder: Starting order creation for order_id: ' . $orderId);

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                $this->log('createPayPalOrder: Order not found - order_id: ' . $orderId, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')];
            }

            $currency = $this->getCurrency($orderTable);

            if (!PayPalCurrencyHelper::isValid($currency)) {
                $this->log('createPayPalOrder: Unsupported currency - ' . $currency, Log::ERROR);
                return [
                    'success' => false,
                    'error'   => Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_CURRENCY_NOT_SUPPORTED', $currency),
                ];
            }

            $db = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select('*')
                ->from($db->quoteName('#__j2commerce_orderitems'))
                ->where($db->quoteName('order_id') . ' = :order_id')
                ->bind(':order_id', $orderId);

            $db->setQuery($query);
            $orderItems = $db->loadObjectList();

            $items = [];
            $itemTotal = 0.0;

            foreach ($orderItems as $item) {
                $unitAmount = (float) $item->orderitem_price;
                $quantity = (int) $item->orderitem_quantity;

                $items[] = [
                    'name'        => $item->orderitem_name,
                    'quantity'    => $quantity,
                    'unit_amount' => $unitAmount,
                    'sku'         => $item->orderitem_sku ?? '',
                ];

                $itemTotal += $unitAmount * $quantity;
            }

            $shipping = (float) $orderTable->order_shipping + (float) $orderTable->order_shipping_tax;
            $tax = (float) $orderTable->order_tax;
            $discount = (float) $orderTable->order_discount;
            $total = (float) $orderTable->order_total;

            $orderData = [
                'order_id'            => $orderId,
                'j2commerce_order_id' => $orderTable->j2commerce_order_id ?? $orderId,
                'invoice_id'          => $orderTable->invoice_prefix . $orderId,
                'currency_code'       => $currency,
                'total'               => $total,
                'item_total'          => $itemTotal,
                'shipping'            => $shipping,
                'tax'                 => $tax,
                'discount'            => $discount,
                'items'               => $items,
            ];

            $this->log('createPayPalOrder: Sending order data to PayPal API - order_id: ' . $orderId . ', total: ' . $total . ' ' . $currency . ', items: ' . count($items));

            $orders = $this->getPayPalOrders();
            $result = $orders->createOrder($orderData);

            $this->log('createPayPalOrder: PayPal API response - status: ' . $result['status']);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $paypalOrderId = $result['body']['id'] ?? '';

                $transactionDetails = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['paypal_order_id'] = $paypalOrderId;
                $transactionDetails['created_at'] = date('Y-m-d H:i:s');
                $orderTable->transaction_details = json_encode($transactionDetails);
                $orderTable->store();

                $this->log('createPayPalOrder: Success - paypal_order_id: ' . $paypalOrderId);
                return ['success' => true, 'paypal_order_id' => $paypalOrderId];
            }

            $errorMessage = $result['body']['message'] ?? 'PayPal order creation failed';
            $details = $result['body']['details'] ?? [];
            $detailStr = !empty($details) ? ' Details: ' . json_encode($details) : '';
            $this->log("createPayPalOrder: Failed (HTTP {$result['status']}) - $errorMessage$detailStr", Log::ERROR);

            return ['success' => false, 'error' => $errorMessage];
        } catch (\Exception $e) {
            $this->log('createPayPalOrder: Exception - ' . $e->getMessage(), Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                'PayPal order creation exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function capturePayPalOrder(string $paypalOrderId, string $orderId): array
    {
        try {
            $this->log('capturePayPalOrder: Starting capture - paypal_order_id: ' . $paypalOrderId . ', order_id: ' . $orderId);

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                $this->log('capturePayPalOrder: Order not found - order_id: ' . $orderId, Log::ERROR);
                return [
                    'success' => false,
                    'error'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND'),
                ];
            }

            $this->log('capturePayPalOrder: Sending capture request to PayPal API');

            $orders = $this->getPayPalOrders();
            $result = $orders->captureOrder($paypalOrderId);

            $this->log('capturePayPalOrder: PayPal API response - status: ' . $result['status']);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $capture = $result['body']['purchase_units'][0]['payments']['captures'][0] ?? null;

                if (!$capture) {
                    $this->log('capturePayPalOrder: No capture data in response', Log::ERROR);
                    return ['success' => false, 'error' => 'No capture data in response'];
                }

                $captureId = $capture['id'] ?? '';
                $captureStatus = $capture['status'] ?? '';

                $this->log('capturePayPalOrder: Capture successful - capture_id: ' . $captureId . ', status: ' . $captureStatus);

                $orderStateId = (int) $this->params->get('payment_status', 4);
                $orderTable->order_state_id = $orderStateId;
                $orderTable->transaction_id = $captureId;
                $orderTable->transaction_status = $captureStatus;

                $transactionDetails = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['capture_id'] = $captureId;
                $transactionDetails['capture_status'] = $captureStatus;
                $transactionDetails['captured_at'] = date('Y-m-d H:i:s');
                $transactionDetails['capture_response'] = $result['body'];
                $orderTable->transaction_details = json_encode($transactionDetails);

                if ($orderTable->store()) {
                    OrderHistoryHelper::add(
                        orderId: $orderTable->order_id,
                        comment: Text::sprintf(
                            'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                            Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                        ),
                        orderStateId: (int) $orderTable->order_state_id
                    );

                    $this->log('capturePayPalOrder: Order updated successfully');
                    return [
                        'success'  => true,
                        'redirect' => $this->payment->getReturnUrl(),
                    ];
                }

                $this->log('capturePayPalOrder: Failed to store order - ' . $orderTable->getError(), Log::ERROR);
                return ['success' => false, 'error' => $orderTable->getError()];
            }

            $errorMessage = $result['body']['message'] ?? 'PayPal capture failed';
            $this->log("capturePayPalOrder: Capture failed - $errorMessage", Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                "PayPal capture failed: $errorMessage",
                ['category' => 'j2commerce.paypal', 'result' => $result]
            );

            return ['success' => false, 'error' => $errorMessage];
        } catch (\Exception $e) {
            $this->log('capturePayPalOrder: Exception - ' . $e->getMessage(), Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                'PayPal capture exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getPayPalClient(): PayPalClient
    {
        if (!$this->paypalClient) {
            $sandbox = (bool) $this->params->get('sandbox', 0);
            $clientId = $sandbox
                ? $this->params->get('sandbox_client_id', '')
                : $this->params->get('client_id', '');
            $clientSecret = $sandbox
                ? $this->params->get('sandbox_client_secret', '')
                : $this->params->get('client_secret', '');

            $this->paypalClient = new PayPalClient($clientId, $clientSecret, $sandbox);
        }

        return $this->paypalClient;
    }

    private function getPayPalOrders(): PayPalOrders
    {
        if (!$this->paypalOrders) {
            $this->paypalOrders = new PayPalOrders($this->getPayPalClient());
        }

        return $this->paypalOrders;
    }

    private function getPayPalWebhooks(): PayPalWebhooks
    {
        if (!$this->paypalWebhooks) {
            $webhookId = $this->params->get('webhook_id', '');
            $this->paypalWebhooks = new PayPalWebhooks(
                $this->getPayPalClient(),
                $webhookId,
                $this->getDatabase()
            );
        }

        return $this->paypalWebhooks;
    }

    private function getPayPalRefunds(): PayPalRefunds
    {
        if (!$this->paypalRefunds) {
            $this->paypalRefunds = new PayPalRefunds($this->getPayPalClient());
        }

        return $this->paypalRefunds;
    }

    private function checkGeozone(int $geozoneId, array $address): bool
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $countryId = (int) ($address['country_id'] ?? 0);
        $zoneId = (int) ($address['zone_id'] ?? 0);

        $query->select($db->quoteName('gz.j2commerce_geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozones', 'gz'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_geozonerules', 'gzr')
                . ' ON ' . $db->quoteName('gzr.geozone_id') . ' = ' . $db->quoteName('gz.j2commerce_geozone_id')
            )
            ->where($db->quoteName('gz.j2commerce_geozone_id') . ' = :geozoneId')
            ->where($db->quoteName('gzr.country_id') . ' = :countryId')
            ->where('(' . $db->quoteName('gzr.zone_id') . ' = 0 OR ' . $db->quoteName('gzr.zone_id') . ' = :zoneId)')
            ->bind(':geozoneId', $geozoneId, ParameterType::INTEGER)
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        return !empty($result);
    }

    private function checkSubtotalLimits(float $subtotal): bool
    {
        $minSubtotal = (float) $this->params->get('min_subtotal', 0);
        $maxSubtotal = (float) $this->params->get('max_subtotal', -1);

        if ($minSubtotal > 0 && $subtotal < $minSubtotal) {
            return false;
        }

        if ($maxSubtotal >= 0 && $subtotal > $maxSubtotal) {
            return false;
        }

        return true;
    }

    private function getCurrency(object $order): string
    {
        $currency = 'USD';

        if (isset($order->currency_code) && !empty($order->currency_code)) {
            $currency = $order->currency_code;
        } elseif (isset($order->order_currency_code) && !empty($order->order_currency_code)) {
            $currency = $order->order_currency_code;
        }

        return strtoupper(trim($currency));
    }

    protected function _getLayout(string $layout, ?\stdClass $vars = null): string
    {
        return $this->resolvePluginLayout($layout, ['vars' => $vars]);
    }

    public function onGetQuickIcons(Event $event): void
    {
        if (!$this->params->get('show_dashboard_icon', 0)) {
            return;
        }

        $result = $event->getArgument('result', []);

        $isSandbox = (bool) $this->params->get('sandbox', 0);
        $label     = $this->params->get('dashboard_icon_label', '') ?: Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL');

        $icon = [
            'id'    => 'j2commerce-paypal',
            'link'  => Route::_('index.php?option=com_plugins&task=plugin.edit&layout=edit&extension_id=' . (int) $this->getExtensionId()),
            'image' => 'fa-brands fa-paypal',
            'text'  => $label,
            'class' => $isSandbox ? 'warning' : 'success',
            'badge' => $isSandbox ? Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX') : Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_LIVE'),
        ];

        $result[] = $icon;
        $event->setArgument('result', $result);
    }

    public function onGetDashboardMessages(Event $event): void
    {
        if (!(bool) $this->params->get('sandbox', 0)) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = [
            'id'          => 'plg_payment_paypal_sandbox_warning',
            'text'        => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_SANDBOX_WARNING'),
            'type'        => 'warning',
            'icon'        => 'fa-brands fa-paypal',
            'dismissible' => 'session',
            'link'  => Route::_('index.php?option=com_plugins&task=plugin.edit&layout=edit&extension_id=' . (int) $this->getExtensionId()),
            'linkText'    => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_CONFIGURE'),
            'priority'    => 100,
        ];
        $event->setArgument('result', $result);
    }

    private function getExtensionId(): int
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($this->_name))
            ->where($db->quoteName('folder') . ' = ' . $db->quote($this->_type))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        return (int) $db->setQuery($query)->loadResult();
    }
}
