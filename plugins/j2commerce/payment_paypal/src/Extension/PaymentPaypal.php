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
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalNvpClient;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalOrders;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalRefunds;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalSubscriptions;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalWebhooks;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
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

    private ?PayPalSubscriptions $paypalSubscriptions = null;

    private ?PayPalNvpClient $paypalNvp = null;

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
        $this->payment           = new Payment($dispatcher, $config);
        $this->payment->_element = $this->_name;
        $this->base              = new Base($dispatcher, $config);
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
            'onJ2CommerceAfterPayment'              => ['onAfterPayment', -100],
            'onJ2CommerceProcessWebhook'            => 'onProcessWebhook',
            'onJ2CommerceRefundPayment'             => 'onRefundPayment',
            'onJ2CommerceAfterSubscriptionCanceled' => 'onAfterSubscriptionCanceled',
            'onJ2CommerceProcessRenewalPayment'     => 'onProcessRenewalPayment',
            'onJ2CommercePaymentCreateOrder'        => 'onPaymentCreateOrder',
            'onJ2CommercePaymentCaptureOrder'       => 'onPaymentCaptureOrder',
            'onJ2CommerceCheckoutStart'             => 'onCheckoutStart',
            'onJ2CommerceGetQuickIcons'             => 'onGetQuickIcons',
            'onJ2CommerceGetDashboardMessages'      => 'onGetDashboardMessages',
            'onAjaxPayment_paypal'                  => 'onAjaxHandler',
        ];
    }

    public function onAcceptSubscriptionPayment(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args    = $event->getArguments();
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

        $args    = $event->getArguments();
        $element = $args[0] ?? null;
        $order   = $args[1] ?? null;

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

        $total            = $order->order_subtotal + $order->order_shipping + $order->order_shipping_tax;
        $surcharge        = 0.0;
        $surchargePercent = (float) $this->params->get('surcharge_percent', 0);
        $surchargeFixed   = (float) $this->params->get('surcharge_fixed', 0);

        if ($surchargePercent > 0 || $surchargeFixed > 0) {
            if ($surchargePercent > 0) {
                $surcharge += ($total * $surchargePercent) / 100;
            }

            if ($surchargeFixed > 0) {
                $surcharge += $surchargeFixed;
            }

            $name       = $this->params->get('surcharge_name', Text::_('COM_J2COMMERCE_CART_SURCHARGE'));
            $taxClassId = $this->params->get('surcharge_tax_class_id', '');
            $taxable    = !empty($taxClassId) && (int) $taxClassId > 0;

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

        $args    = $event->getArguments();
        $element = $args[0] ?? null;
        $order   = $args[1] ?? null;

        if ($element !== $this->_name || $order === null) {
            return;
        }

        $found = true;

        $geozoneId = (int) $this->params->get('geozone_id', 0);

        if ($geozoneId > 0) {
            $order->setAddress();
            $address = $order->getBillingAddress();
            $found   = $this->checkGeozone($geozoneId, $address);
        }

        if ($found) {
            $found = $this->checkSubtotalLimits($order->order_subtotal);
        }

        $event->setArgument('result', $found);
    }

    public function onGetPaymentPlugins(Event $event): void
    {
        $result   = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_name,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL')),
            'image'   => $this->params->get('display_image', ''),
        ];
        $event->setArgument('result', $result);
    }

    public function onCheckoutStart(Event $event): void
    {
        // PayPal wallet popup requires opener access across origins.
        Factory::getApplication()->setHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups', true);

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
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->_prePayment($data);
        $event->setArgument('result', $result);
    }

    public function onPostPayment(Event $event): void
    {
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $result   = $event->getArgument('result', []);
        $result[] = $this->_postPayment((object) $data);
        $event->setArgument('result', $result);
    }

    public function onProcessWebhook(Event $event): void
    {
        $args    = $event->getArguments();
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
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $orderId = $args[1] ?? 0;
        $amount  = $args[2] ?? null;

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
            $result  = $refunds->refundCapture($captureId, $amount, $currency);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $refundId        = $result['body']['id'] ?? '';
                $refundedStateId = (int) $this->params->get('refunded_state_id', 7);

                $orderTable->order_state_id          = $refundedStateId;
                $transactionDetails                  = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['refund_id']     = $refundId;
                $transactionDetails['refunded_at']   = date('Y-m-d H:i:s');
                $transactionDetails['refund_amount'] = $amount;
                $orderTable->transaction_details     = json_encode($transactionDetails);
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
        $args    = $event->getArguments();
        $element = $args[0] ?? '';

        if ($element !== $this->_name) {
            return;
        }

        $subscriptionId = (int) ($args[1] ?? 0);
        $reason         = (string) ($args[2] ?? 'Cancelled via J2Commerce admin');

        if ($subscriptionId <= 0) {
            $event->setArgument('result', ['status' => 'subscription_cancellation_registered']);
            return;
        }

        $legacyBaid  = $this->loadLegacyBillingAgreementId($subscriptionId);
        $paypalSubId = $this->loadPayPalSubscriptionId($subscriptionId);

        if ($legacyBaid !== '') {
            $event->setArgument('result', $this->cancelNvpBaid($subscriptionId, $legacyBaid, $reason));
            return;
        }

        if ($paypalSubId !== '') {
            $event->setArgument('result', $this->cancelModernSubscription($subscriptionId, $paypalSubId, $reason));
            return;
        }

        $this->log('onAfterSubscriptionCanceled: no PayPal credential meta for sub #' . $subscriptionId, Log::WARNING);
        $event->setArgument('result', ['status' => 'subscription_cancellation_registered', 'paypal_remote' => 'no-id']);
    }

    /**
     * @return  array<string, mixed>
     */
    private function cancelNvpBaid(int $subscriptionId, string $baid, string $reason): array
    {
        $nvp = $this->getPayPalNvp();

        if (!$nvp->isConfigured()) {
            $this->log(\sprintf(
                'onAfterSubscriptionCanceled: sub=%d has BAID=%s but NVP credentials not configured — manual cancellation required at PayPal',
                $subscriptionId,
                $baid
            ), Log::WARNING);

            return [
                'status'                 => 'subscription_cancellation_registered',
                'paypal_remote'          => 'nvp-creds-missing',
                'legacy_baid'            => $baid,
                'manual_cancel_required' => true,
            ];
        }

        try {
            $response = $nvp->billAgreementUpdate($baid, 'Cancel', $reason);
            $ok       = PayPalNvpClient::isSuccess($response);

            $this->log(\sprintf(
                'onAfterSubscriptionCanceled: sub=%d nvp_baid=%s remote_cancel=%s',
                $subscriptionId,
                $baid,
                $ok ? 'ok' : 'failed'
            ));

            return [
                'status'        => 'subscription_cancellation_registered',
                'paypal_remote' => $ok ? 'cancelled-nvp' : 'failed-nvp',
                'legacy_baid'   => $baid,
                'gateway_error' => $ok ? null : PayPalNvpClient::getErrorMessage($response),
            ];
        } catch (\Throwable $e) {
            $this->log('NVP BillAgreementUpdate exception: ' . $e->getMessage(), Log::ERROR);
            return [
                'status'        => 'subscription_cancellation_registered',
                'paypal_remote' => 'exception-nvp',
                'error'         => $e->getMessage(),
            ];
        }
    }

    /**
     * @return  array<string, mixed>
     */
    private function cancelModernSubscription(int $subscriptionId, string $paypalSubId, string $reason): array
    {
        try {
            $ok = $this->getPayPalSubscriptions()->cancelSubscription($paypalSubId, $reason);
            $this->log(\sprintf(
                'onAfterSubscriptionCanceled: sub=%d paypal=%s remote_cancel=%s',
                $subscriptionId,
                $paypalSubId,
                $ok ? 'ok' : 'failed'
            ));
            return [
                'status'        => 'subscription_cancellation_registered',
                'paypal_remote' => $ok ? 'cancelled' : 'failed',
            ];
        } catch (\Throwable $e) {
            $this->log('Modern cancelSubscription exception: ' . $e->getMessage(), Log::ERROR);
            return [
                'status'        => 'subscription_cancellation_registered',
                'paypal_remote' => 'exception',
                'error'         => $e->getMessage(),
            ];
        }
    }

    /**
     * Subscription rows are created by app_subscriptionproduct in its own
     * onJ2CommerceAfterPayment listener. The Smart Buttons subscription branch
     * stashes the PayPal subscription id in the order's transaction_details
     * JSON (key `paypal_subscription_id`). This handler runs at priority -100,
     * after the subscription row exists, and writes the metakey that links
     * the local sub to the PayPal subscription resource.
     */
    public function onAfterPayment(Event $event): void
    {
        $args  = $event->getArguments();
        $order = $args[0] ?? null;

        if (!$order || empty($order->order_id)) {
            return;
        }

        if (((string) ($order->orderpayment_type ?? '')) !== $this->_name) {
            return;
        }

        $userId = (int) ($order->user_id ?? 0);

        if ($userId <= 0) {
            return;
        }

        $details = json_decode((string) ($order->transaction_details ?? ''), true);

        if (!\is_array($details)) {
            return;
        }

        $paypalSubId   = trim((string) ($details['paypal_subscription_id'] ?? ''));
        $legacyBaid    = trim((string) ($details['billing_agreement_id'] ?? ''));

        if ($legacyBaid !== '') {
            try {
                $this->linkMetakeyToOrder((string) $order->order_id, $userId, 'billing_agreement_id', $legacyBaid);
            } catch (\Throwable $e) {
                $this->log('onAfterPayment NVP-link exception for order ' . $order->order_id . ': ' . $e->getMessage(), Log::ERROR);
            }
            return;
        }

        if ($paypalSubId === '') {
            // Not a subscription order — no link to write. Normal one-off Orders v2 capture.
            return;
        }

        try {
            $this->linkPayPalSubscriptionToOrder((string) $order->order_id, $userId, $paypalSubId);
        } catch (\Throwable $e) {
            $this->log('onAfterPayment link exception for order ' . $order->order_id . ': ' . $e->getMessage(), Log::ERROR);
        }
    }

    /**
     * Generic metakey writer used by the NVP path to stamp billing_agreement_id
     * onto every local sub matching this order. Mirrors linkPayPalSubscriptionToOrder
     * but with the metakey/metavalue passed in.
     */
    private function linkMetakeyToOrder(string $orderIdString, int $userId, string $metakey, string $metavalue): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__j2commerce_subscriptions'))
            ->where($db->quoteName('order_id') . ' = :oid')
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':oid', $orderIdString)
            ->bind(':uid', $userId, ParameterType::INTEGER);
        $db->setQuery($query);
        $localSubs = $db->loadColumn();

        if (empty($localSubs)) {
            $this->log('linkMetakeyToOrder(' . $metakey . '): no local subs for order ' . $orderIdString . ' user ' . $userId, Log::WARNING);
            return;
        }

        $namespace = 'subscription';
        $resource  = 'subscriptions';
        $now       = date('Y-m-d H:i:s');
        $valuetype = 'string';
        $scope     = '';
        $desc      = '';

        foreach ($localSubs as $subId) {
            $subIdInt = (int) $subId;

            $existing = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__j2commerce_metafields'))
                    ->where($db->quoteName('owner_id') . ' = :sid')
                    ->where($db->quoteName('owner_resource') . ' = :res')
                    ->where($db->quoteName('namespace') . ' = :ns')
                    ->where($db->quoteName('metakey') . ' = :metakey')
                    ->bind(':sid', $subIdInt, ParameterType::INTEGER)
                    ->bind(':res', $resource)
                    ->bind(':ns', $namespace)
                    ->bind(':metakey', $metakey)
            )->loadResult();

            if ($existing !== null) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_metafields'))
                        ->set($db->quoteName('metavalue') . ' = :val')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':val', $metavalue)
                        ->bind(':id', (int) $existing, ParameterType::INTEGER)
                )->execute();
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__j2commerce_metafields'))
                        ->columns($db->quoteName(['metakey', 'namespace', 'scope', 'metavalue', 'valuetype', 'description', 'owner_id', 'owner_resource', 'created_at', 'updated_at']))
                        ->values(':metakey, :ns, :scope, :val, :vtype, :desc, :sid, :res, :now1, :now2')
                        ->bind(':metakey', $metakey)
                        ->bind(':ns', $namespace)
                        ->bind(':scope', $scope)
                        ->bind(':val', $metavalue)
                        ->bind(':vtype', $valuetype)
                        ->bind(':desc', $desc)
                        ->bind(':sid', $subIdInt, ParameterType::INTEGER)
                        ->bind(':res', $resource)
                        ->bind(':now1', $now)
                        ->bind(':now2', $now)
                )->execute();
            }
        }

        $this->log(\sprintf(
            'linkMetakeyToOrder: wrote %s=%s on %d local sub(s)',
            $metakey,
            $metavalue,
            \count($localSubs)
        ));
    }

    /**
     * createOrder branch for subscription_mode=nvp + subscription cart. Calls
     * SetExpressCheckout to obtain a TOKEN, stashes the TOKEN in the order's
     * transaction_details, and returns a redirect URL the JS sends the
     * customer to. After approval, PayPal redirects back with TOKEN+PayerID
     * in query params; the capture step calls completeNvpExpressCheckoutForOrder.
     */
    public function createNvpExpressCheckoutForOrder(array $data): array
    {
        try {
            $orderId = (string) ($data['order_id'] ?? '');

            if ($orderId === '') {
                return ['success' => false, 'error' => 'Missing order_id'];
            }

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')];
            }

            $nvp = $this->getPayPalNvp();

            if (!$nvp->isConfigured()) {
                return [
                    'success' => false,
                    'error'   => 'NVP credentials not configured. Switch subscription_mode to "rest" or fill api_username/password/signature in plugin params.',
                ];
            }

            $currency = $this->getCurrency($orderTable);
            $amount   = (float) ($orderTable->order_total ?? 0);

            $returnUrl = Route::_(
                'index.php?option=com_j2commerce&task=checkout.confirmPayment'
                . '&orderpayment_type=' . $this->_name
                . '&order_id=' . urlencode($orderId)
                . '&nvp=1',
                false,
                Route::TLS_IGNORE,
                true
            );
            $cancelUrl = Route::_(
                'index.php?option=com_j2commerce&view=checkout',
                false,
                Route::TLS_IGNORE,
                true
            );

            $brand = (string) Factory::getApplication()->get('sitename', 'J2Commerce');

            $response = $nvp->setExpressCheckout(
                amount:             $amount,
                currencyCode:       $currency,
                returnUrl:          $returnUrl,
                cancelUrl:          $cancelUrl,
                invoiceNumber:      $orderId,
                billingDescription: $brand . ' subscription',
                custom:             $orderId
            );

            if (!PayPalNvpClient::isSuccess($response)) {
                return [
                    'success'          => false,
                    'error'            => PayPalNvpClient::getErrorMessage($response),
                    'gateway_response' => $response,
                ];
            }

            $token = (string) ($response['TOKEN'] ?? '');

            if ($token === '') {
                return ['success' => false, 'error' => 'PayPal SetExpressCheckout returned no TOKEN'];
            }

            $details = json_decode((string) ($orderTable->transaction_details ?? '{}'), true);

            if (!\is_array($details)) {
                $details = [];
            }

            $details['nvp_express_token']  = $token;
            $details['mode']               = 'nvp_express';
            $details['created_at']         = date('Y-m-d H:i:s');

            $orderTable->transaction_details = json_encode($details);
            $orderTable->store();

            $approveUrl = $nvp->getApprovalUrl($token);

            $this->log(\sprintf(
                'createNvpExpressCheckoutForOrder: order=%s token=%s approve=%s',
                $orderId,
                $token,
                $approveUrl
            ));

            return [
                'success'      => true,
                'mode'         => 'nvp',
                'redirect_url' => $approveUrl,
                'token'        => $token,
            ];
        } catch (\Throwable $e) {
            $this->log('createNvpExpressCheckoutForOrder exception: ' . $e->getMessage(), Log::ERROR);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Capture-step branch for the NVP Express Checkout return flow. Customer
     * has approved at PayPal and bounced back with TOKEN+PayerID in URL params.
     * This calls DoExpressCheckoutPayment, extracts the BILLINGAGREEMENTID
     * (BAID), and stashes it in transaction_details so onAfterPayment writes
     * the billing_agreement_id metakey.
     */
    public function completeNvpExpressCheckoutForOrder(string $orderIdString, string $token, string $payerId): array
    {
        try {
            if ($orderIdString === '' || $token === '' || $payerId === '') {
                return ['success' => false, 'error' => 'Missing order_id, nvp_token, or nvp_payer_id'];
            }

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderIdString])) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')];
            }

            $nvp = $this->getPayPalNvp();

            if (!$nvp->isConfigured()) {
                return ['success' => false, 'error' => 'NVP credentials not configured'];
            }

            $currency = $this->getCurrency($orderTable);
            $amount   = (float) ($orderTable->order_total ?? 0);

            $response = $nvp->doExpressCheckoutPayment(
                token:         $token,
                payerId:       $payerId,
                amount:        $amount,
                currencyCode:  $currency,
                invoiceNumber: $orderIdString,
                custom:        $orderIdString
            );

            if (!PayPalNvpClient::isSuccess($response)) {
                return [
                    'success'          => false,
                    'error'            => PayPalNvpClient::getErrorMessage($response),
                    'gateway_response' => $response,
                ];
            }

            $baid    = (string) ($response['BILLINGAGREEMENTID'] ?? '');
            $transId = (string) ($response['PAYMENTINFO_0_TRANSACTIONID'] ?? '');

            if ($baid === '') {
                $this->log('completeNvpExpressCheckoutForOrder: NO BAID returned in DoExpressCheckoutPayment response', Log::ERROR);
                return [
                    'success'          => false,
                    'error'            => 'PayPal did not return a BILLINGAGREEMENTID. Subscription will not be able to renew.',
                    'gateway_response' => $response,
                ];
            }

            $confirmedStateId = (int) $this->params->get('payment_status', 1);

            $details                          = json_decode((string) ($orderTable->transaction_details ?? '{}'), true) ?: [];
            $details['billing_agreement_id']  = $baid;
            $details['transaction_id']        = $transId;
            $details['mode']                  = 'nvp_express';
            $details['completed_at']          = date('Y-m-d H:i:s');

            $orderTable->orderpayment_type   = $this->_name;
            $orderTable->order_state_id      = $confirmedStateId;
            $orderTable->transaction_id      = $transId;
            $orderTable->transaction_status  = 'Completed';
            $orderTable->transaction_details = json_encode($details);
            $orderTable->store();

            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: Text::sprintf(
                    'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                    Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                ) . ' (NVP Express, BAID ' . $baid . ', TRANS ' . $transId . ')',
                orderStateId: $confirmedStateId,
            );

            $this->log(\sprintf(
                'completeNvpExpressCheckoutForOrder: order=%s baid=%s transId=%s',
                $orderIdString,
                $baid,
                $transId
            ));

            return [
                'success'              => true,
                'mode'                 => 'nvp',
                'billing_agreement_id' => $baid,
                'transaction_id'       => $transId,
                'redirect'             => Route::_(
                    'index.php?option=com_j2commerce&view=checkout&task=checkout.confirmPayment'
                    . '&orderpayment_type=' . $this->_name
                    . '&order_id=' . urlencode($orderIdString),
                    false
                ),
            ];
        } catch (\Throwable $e) {
            $this->log('completeNvpExpressCheckoutForOrder exception: ' . $e->getMessage(), Log::ERROR);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cron / debug-button trigger: read paypal_subscription_id from metafields,
     * call PayPal's /v1/billing/subscriptions/{id}/capture, dispatch the
     * Success / Failed / NoResponse follow-up event so app_subscriptionproduct
     * flips the local subscription status.
     *
     * Note: PayPal renewals primarily flow via webhooks (BILLING.SUBSCRIPTION.* /
     * PAYMENT.SALE.COMPLETED). This handler is for manual force-charge from the
     * subscription debug fieldset and edge cases where the cron job runs faster
     * than the webhook.
     */
    public function onProcessRenewalPayment(Event $event): void
    {
        $element      = (string) $event->getArgument('payment_method', '');
        $subscription = $event->getArgument('subscription');
        $order        = $event->getArgument('order');

        if ($element !== $this->_name || $subscription === null || $order === null) {
            return;
        }

        $result      = $event->getArgument('result', []);
        $localSubId  = (int) ($subscription->id ?? 0);

        // Path C hybrid — branch on which metakey the sub has:
        //  1. billing_agreement_id (legacy J2Store NVP)         → DoReferenceTransaction
        //  2. paypal_subscription_id (modern Subscriptions API) → /v1/billing/subscriptions/{id}/capture
        //  3. neither                                           → fail with clear error
        $legacyBaid  = $this->loadLegacyBillingAgreementId($localSubId);
        $paypalSubId = $this->loadPayPalSubscriptionId($localSubId);

        if ($legacyBaid !== '') {
            [$status, $error, $extra] = $this->renewViaNvpReference($legacyBaid, $subscription, $order);
        } elseif ($paypalSubId !== '') {
            [$status, $error, $extra] = $this->renewViaModernSubscriptionsApi($paypalSubId, $subscription, $order);
        } else {
            $result[] = ['success' => false, 'error' => 'No PayPal payment credential stored — neither billing_agreement_id (legacy NVP) nor paypal_subscription_id (modern REST) metakey exists for this subscription.'];
            $event->setArgument('result', $result);
            $this->dispatchRenewalOutcome('onJ2CommerceFailedRenewalPayment', $subscription, $order);
            return;
        }

        $result[] = array_merge([
            'success' => $status === 'success',
            'error'   => $error,
            'outcome' => $status,
        ], $extra);
        $event->setArgument('result', $result);

        $followUp = match ($status) {
            'success'     => 'onJ2CommerceSuccessRenewalPayment',
            'no_response' => 'onJ2CommerceNoResponseForRenewalPayment',
            default       => 'onJ2CommerceFailedRenewalPayment',
        };

        $this->dispatchRenewalOutcome($followUp, $subscription, $order, $status === 'success' ? true : null);
    }

    /**
     * NVP DoReferenceTransaction — charges a saved BAID for a J2Commerce
     * renewal order. Mirrors the legacy J2Store byReference() pattern.
     *
     * @return  array{0: string, 1: ?string, 2: array<string, mixed>}  [status, error, extra]
     *          status is 'success' | 'failed' | 'no_response'
     */
    private function renewViaNvpReference(string $baid, object $subscription, object $order): array
    {
        $nvp = $this->getPayPalNvp();

        if (!$nvp->isConfigured()) {
            return [
                'failed',
                'Subscription #' . ($subscription->id ?? '?') . ' uses a legacy J2Store BAID (' . $baid . ') but NVP credentials (api_username/password/signature) are not configured in payment_paypal plugin params. Either configure NVP credentials or have the customer re-authorize.',
                ['needs_nvp_credentials' => true],
            ];
        }

        try {
            $response = $nvp->doReferenceTransaction(
                referenceId:    $baid,
                amount:         (float) ($subscription->renewal_amount ?? $order->order_total ?? 0),
                currencyCode:   strtoupper((string) ($order->currency_code ?? 'USD')),
                invoiceNumber:  (string) ($order->order_id ?? ''),
                description:    'J2Commerce subscription renewal #' . ($subscription->id ?? '')
            );
        } catch (\Throwable $e) {
            $this->log('NVP DoReferenceTransaction exception: ' . $e->getMessage(), Log::ERROR);
            return ['no_response', $e->getMessage(), []];
        }

        if (PayPalNvpClient::isSuccess($response)) {
            $transId = (string) ($response['TRANSACTIONID'] ?? '');
            $this->finalizeNvpRenewalOrder($order, $transId, $response);
            return ['success', null, ['transaction_id' => $transId, 'gateway_response' => $response]];
        }

        $code = (string) ($response['L_ERRORCODE0'] ?? '');

        // Per legacy J2Store: 10412 = duplicate INVNUM. Treat as no_response so
        // the retry counter advances without flipping the sub to 'failed'.
        if ($code === '10412') {
            return ['no_response', PayPalNvpClient::getErrorMessage($response), ['gateway_response' => $response]];
        }

        // 11451 = BAID already canceled, 11452 = BAID expired — terminal failures.
        return ['failed', PayPalNvpClient::getErrorMessage($response), ['gateway_response' => $response]];
    }

    private function finalizeNvpRenewalOrder(object $order, string $transactionId, array $nvpResponse): void
    {
        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => (string) ($order->order_id ?? '')])) {
                return;
            }

            $orderTable->transaction_id      = $transactionId;
            $orderTable->transaction_status  = 'Completed';
            $orderTable->orderpayment_type   = $this->_name;
            $orderTable->order_state_id      = (int) $this->params->get('payment_status', 1);
            $orderTable->transaction_details = json_encode([
                'transaction_id' => $transactionId,
                'paymentinfo'    => $nvpResponse['PAYMENTINFO_0_TRANSACTIONID'] ?? null,
                'pending_reason' => $nvpResponse['PENDINGREASON'] ?? null,
                'reason_code'    => $nvpResponse['REASONCODE'] ?? null,
                'type'           => 'renewal_nvp',
                'completed_at'   => date('Y-m-d H:i:s'),
            ]);
            $orderTable->store();

            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: Text::sprintf(
                    'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                    Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                ) . ' (NVP DoReferenceTransaction ' . $transactionId . ')',
                orderStateId: (int) $orderTable->order_state_id,
            );
        } catch (\Throwable $e) {
            $this->log('finalizeNvpRenewalOrder exception: ' . $e->getMessage(), Log::ERROR);
        }
    }

    /**
     * Modern REST Subscriptions API renewal — for subs created post-migration.
     * Wraps the existing PayPalSubscriptions::captureRenewalPayment() shape so
     * onProcessRenewalPayment can route uniformly.
     *
     * @return  array{0: string, 1: ?string, 2: array<string, mixed>}  [status, error, extra]
     */
    private function renewViaModernSubscriptionsApi(string $paypalSubId, object $subscription, object $order): array
    {
        try {
            $outcome = $this->getPayPalSubscriptions()->captureRenewalPayment($subscription, $order, $paypalSubId);
        } catch (\Throwable $e) {
            $this->log('Modern subscription capture exception: ' . $e->getMessage(), Log::ERROR);
            return ['no_response', $e->getMessage(), []];
        }

        return [
            (string) ($outcome['status'] ?? 'failed'),
            isset($outcome['error']) ? (string) $outcome['error'] : null,
            ['gateway_response' => $outcome['gateway_response'] ?? null, 'transaction_id' => $outcome['transaction_id'] ?? null],
        ];
    }

    private function dispatchRenewalOutcome(string $eventName, object $subscription, object $order, ?bool $updateRenewalDate = null): void
    {
        $args = ['subscription' => $subscription, 'order' => $order];

        if ($updateRenewalDate !== null) {
            $args['updateRenewalDate'] = $updateRenewalDate;
        }

        Factory::getApplication()->getDispatcher()->dispatch($eventName, new Event($eventName, $args));
    }

    /**
     * Read the PayPal subscription id from metafields linked to a local sub.
     */
    private function loadPayPalSubscriptionId(int $subscriptionId): string
    {
        if ($subscriptionId <= 0) {
            return '';
        }

        $db        = $this->getDatabase();
        $metakey   = 'paypal_subscription_id';
        $namespace = 'subscription';
        $resource  = 'subscriptions';

        $query = $db->getQuery(true)
            ->select($db->quoteName('metavalue'))
            ->from($db->quoteName('#__j2commerce_metafields'))
            ->where($db->quoteName('owner_id') . ' = :sid')
            ->where($db->quoteName('owner_resource') . ' = :res')
            ->where($db->quoteName('namespace') . ' = :ns')
            ->where($db->quoteName('metakey') . ' = :metakey')
            ->bind(':sid', $subscriptionId, ParameterType::INTEGER)
            ->bind(':res', $resource)
            ->bind(':ns', $namespace)
            ->bind(':metakey', $metakey)
            ->setLimit(1);

        $db->setQuery($query);

        return (string) ($db->loadResult() ?? '');
    }

    /**
     * Detect a legacy J2Store paypal `billing_agreement_id` metakey on this sub.
     * Used by renewal/cancel flows to emit a clear "needs re-authorization"
     * outcome instead of silently failing — the BAID was issued via PayPal NVP
     * (deprecated) and is incompatible with the REST Subscriptions API used by
     * this plugin.
     */
    private function loadLegacyBillingAgreementId(int $subscriptionId): string
    {
        if ($subscriptionId <= 0) {
            return '';
        }

        $db        = $this->getDatabase();
        $metakey   = 'billing_agreement_id';
        $namespace = 'subscription';
        $resource  = 'subscriptions';

        $query = $db->getQuery(true)
            ->select($db->quoteName('metavalue'))
            ->from($db->quoteName('#__j2commerce_metafields'))
            ->where($db->quoteName('owner_id') . ' = :sid')
            ->where($db->quoteName('owner_resource') . ' = :res')
            ->where($db->quoteName('namespace') . ' = :ns')
            ->where($db->quoteName('metakey') . ' = :metakey')
            ->bind(':sid', $subscriptionId, ParameterType::INTEGER)
            ->bind(':res', $resource)
            ->bind(':ns', $namespace)
            ->bind(':metakey', $metakey)
            ->setLimit(1);

        $db->setQuery($query);

        return (string) ($db->loadResult() ?? '');
    }

    /**
     * Link a PayPal subscription resource id to all local subs sharing this order.
     * Writes the paypal_subscription_id metafield so renewal/cancel flows can find it.
     */
    private function linkPayPalSubscriptionToOrder(string $orderIdString, int $userId, string $paypalSubId): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__j2commerce_subscriptions'))
            ->where($db->quoteName('order_id') . ' = :oid')
            ->where($db->quoteName('user_id') . ' = :uid')
            ->bind(':oid', $orderIdString)
            ->bind(':uid', $userId, ParameterType::INTEGER);
        $db->setQuery($query);
        $localSubs = $db->loadColumn();

        if (empty($localSubs)) {
            $this->log('linkPayPalSubscriptionToOrder: no local subs for order ' . $orderIdString . ' user ' . $userId, Log::WARNING);
            return;
        }

        $metakey   = 'paypal_subscription_id';
        $namespace = 'subscription';
        $resource  = 'subscriptions';
        $now       = date('Y-m-d H:i:s');
        $valuetype = 'string';
        $scope     = '';
        $desc      = '';

        foreach ($localSubs as $subId) {
            $subIdInt = (int) $subId;

            $existing = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__j2commerce_metafields'))
                    ->where($db->quoteName('owner_id') . ' = :sid')
                    ->where($db->quoteName('owner_resource') . ' = :res')
                    ->where($db->quoteName('namespace') . ' = :ns')
                    ->where($db->quoteName('metakey') . ' = :metakey')
                    ->bind(':sid', $subIdInt, ParameterType::INTEGER)
                    ->bind(':res', $resource)
                    ->bind(':ns', $namespace)
                    ->bind(':metakey', $metakey)
            )->loadResult();

            if ($existing !== null) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_metafields'))
                        ->set($db->quoteName('metavalue') . ' = :val')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':val', $paypalSubId)
                        ->bind(':id', (int) $existing, ParameterType::INTEGER)
                )->execute();
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__j2commerce_metafields'))
                        ->columns($db->quoteName(['metakey', 'namespace', 'scope', 'metavalue', 'valuetype', 'description', 'owner_id', 'owner_resource', 'created_at', 'updated_at']))
                        ->values(':metakey, :ns, :scope, :val, :vtype, :desc, :sid, :res, :now1, :now2')
                        ->bind(':metakey', $metakey)
                        ->bind(':ns', $namespace)
                        ->bind(':scope', $scope)
                        ->bind(':val', $paypalSubId)
                        ->bind(':vtype', $valuetype)
                        ->bind(':desc', $desc)
                        ->bind(':sid', $subIdInt, ParameterType::INTEGER)
                        ->bind(':res', $resource)
                        ->bind(':now1', $now)
                        ->bind(':now2', $now)
                )->execute();
            }
        }

        $this->log(\sprintf(
            'linkPayPalSubscriptionToOrder: linked PayPal sub %s to %d local sub(s)',
            $paypalSubId,
            \count($localSubs)
        ));
    }

    public function onPaymentCreateOrder(Event $event): void
    {
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $orderId = (string) ($data['order_id'] ?? '');

        if ($this->isSubscriptionOrder($orderId)) {
            $mode = $this->getSubscriptionMode();
            $this->log('onPaymentCreateOrder: subscription detected for order_id: ' . $orderId . ' — mode=' . $mode);

            $result = $mode === 'nvp'
                ? $this->createNvpExpressCheckoutForOrder($data)
                : $this->createPayPalSubscriptionForOrder($data);
        } else {
            $this->log('onPaymentCreateOrder: Starting PayPal order creation for order_id: ' . ($data['order_id'] ?? 'N/A'));
            $result = $this->createPayPalOrder($data);
        }

        $event->setArgument('result', $result);
    }

    /**
     * Effective subscription creation mode for new checkouts.
     * Returns 'nvp' or 'rest'. Defaults to 'rest'.
     */
    private function getSubscriptionMode(): string
    {
        $mode = strtolower((string) $this->params->get('subscription_mode', 'rest'));

        return $mode === 'nvp' ? 'nvp' : 'rest';
    }

    public function onPaymentCaptureOrder(Event $event): void
    {
        $args    = $event->getArguments();
        $element = $args[0] ?? '';
        $data    = $args[1] ?? [];

        if ($element !== $this->_name) {
            return;
        }

        $orderId              = (string) ($data['order_id'] ?? '');
        $paypalOrderId        = (string) ($data['paypal_order_id'] ?? '');
        $paypalSubscriptionId = (string) ($data['paypal_subscription_id'] ?? '');
        $nvpToken             = (string) ($data['nvp_token'] ?? '');
        $nvpPayerId           = (string) ($data['nvp_payer_id'] ?? '');

        if ($nvpToken !== '' || (string) ($data['mode'] ?? '') === 'nvp') {
            $this->log('onPaymentCaptureOrder: completing NVP Express Checkout for order_id: ' . $orderId);
            $result = $this->completeNvpExpressCheckoutForOrder($orderId, $nvpToken, $nvpPayerId);
        } elseif ($paypalSubscriptionId !== '' || $this->isSubscriptionOrder($orderId)) {
            $this->log('onPaymentCaptureOrder: subscription detected for order_id: ' . $orderId . ' — finalizing via subscription approval');
            $result = $this->finalizePayPalSubscriptionApproval($orderId, $paypalSubscriptionId);
        } else {
            $result = $this->capturePayPalOrder($paypalOrderId, $orderId);
        }

        $event->setArgument('result', $result);
    }

    /**
     * True when the cart for this order contains at least one subscription product.
     * Detected via orderitems.product_type IN (subscriptionproduct, variablesubscriptionproduct).
     */
    private function isSubscriptionOrder(string $orderIdString): bool
    {
        if ($orderIdString === '') {
            return false;
        }

        $db    = $this->getDatabase();
        $type1 = 'subscriptionproduct';
        $type2 = 'variablesubscriptionproduct';

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :oid')
            ->where($db->quoteName('product_type') . ' IN (:t1, :t2)')
            ->bind(':oid', $orderIdString)
            ->bind(':t1', $type1)
            ->bind(':t2', $type2);

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * Build a synthetic subscription stub from the FIRST subscription product in
     * an order. Reads period/period_units/subscription_length from the product's
     * params JSON (subscriptionproduct namespace).
     *
     * Returned object satisfies the shape PayPalSubscriptions::createBillingPlan
     * expects: id, product_id, period, period_units, subscription_length, renewal_amount.
     */
    private function buildSubscriptionStubFromOrder(string $orderIdString): ?\stdClass
    {
        $db    = $this->getDatabase();
        $type1 = 'subscriptionproduct';
        $type2 = 'variablesubscriptionproduct';

        $query = $db->getQuery(true)
            ->select(['oi.product_id', 'oi.orderitem_finalprice', 'oi.orderitem_name', 'p.params'])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('oi.product_id')
            )
            ->where($db->quoteName('oi.order_id') . ' = :oid')
            ->where($db->quoteName('oi.product_type') . ' IN (:t1, :t2)')
            ->bind(':oid', $orderIdString)
            ->bind(':t1', $type1)
            ->bind(':t2', $type2)
            ->setLimit(1);

        $db->setQuery($query);

        $row = $db->loadObject();

        if (!$row) {
            return null;
        }

        $productParams = json_decode((string) ($row->params ?? '{}'), true);
        $subParams     = $productParams['subscriptionproduct'] ?? [];

        $period      = strtoupper((string) ($subParams['subscription_period'] ?? 'M'));
        $periodMap   = ['D' => 'DAY', 'W' => 'WEEK', 'M' => 'MONTH', 'Y' => 'YEAR'];
        $periodNorm  = $periodMap[$period] ?? 'MONTH';
        $periodUnits = max(1, (int) ($subParams['subscription_period_units'] ?? 1));
        $length      = max(0, (int) ($subParams['subscription_length'] ?? 0));

        return (object) [
            'id'                  => (int) ($row->product_id ?? 0),
            'product_id'          => (int) ($row->product_id ?? 0),
            'period'              => $periodNorm,
            'period_units'        => $periodUnits,
            'subscription_length' => $length,
            'renewal_amount'      => (float) ($row->orderitem_finalprice ?? 0),
            'product_name'        => (string) ($row->orderitem_name ?? 'Subscription'),
        ];
    }

    /**
     * createOrder branch for subscription carts. Builds a PayPal Subscription
     * (Catalog Product → Billing Plan → Subscription resource) instead of a
     * one-off Orders v2 order. Returns the PayPal subscription id which the
     * Smart Buttons SDK hands to its createSubscription callback.
     *
     * Stashes paypal_subscription_id, paypal_plan_id, paypal_product_id in the
     * order's transaction_details JSON so onPaymentCaptureOrder + onAfterPayment
     * can pick them up.
     */
    public function createPayPalSubscriptionForOrder(array $data): array
    {
        try {
            $orderId = (string) ($data['order_id'] ?? '');

            if ($orderId === '') {
                return ['success' => false, 'error' => 'Missing order_id'];
            }

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')];
            }

            $stub = $this->buildSubscriptionStubFromOrder($orderId);

            if ($stub === null) {
                return ['success' => false, 'error' => 'No subscription product found in order'];
            }

            $currency = $this->getCurrency($orderTable);

            if (!PayPalCurrencyHelper::isValid($currency)) {
                return [
                    'success' => false,
                    'error'   => Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_CURRENCY_NOT_SUPPORTED', $currency),
                ];
            }

            $brand   = (string) Factory::getApplication()->get('sitename', 'J2Commerce');
            $context = [
                'brand_name'     => $brand,
                'product_name'   => $stub->product_name,
                'currency_code'  => $currency,
                'catalog_prefix' => 'j2c',
            ];

            $subs = $this->getPayPalSubscriptions();

            $productId = $subs->getOrCreateCatalogProduct($stub, $context);
            $planId    = $subs->createBillingPlan($stub, $orderTable, $context);

            $userId = (int) ($orderTable->user_id ?? 0);
            $user   = $userId > 0
                ? Factory::getApplication()->getIdentity()
                : null;

            // Best-effort name + email from the buyer. Falls back to order's user_email
            // and order info billing address if available.
            $given   = $user && !empty($user->name) ? trim(explode(' ', $user->name, 2)[0] ?? 'Customer') : 'Customer';
            $surname = $user && !empty($user->name) && str_contains($user->name, ' ')
                ? trim(substr($user->name, strpos($user->name, ' ') + 1))
                : 'PayPal';
            $email   = (string) ($orderTable->user_email ?? '');

            if ($email === '' && $user) {
                $email = (string) ($user->email ?? '');
            }

            $returnUrl = Route::_(
                'index.php?option=com_j2commerce&view=checkout&task=checkout.confirmPayment'
                . '&orderpayment_type=' . $this->_name
                . '&order_id=' . urlencode($orderId),
                false,
                Route::TLS_IGNORE,
                true
            );
            $cancelUrl = Route::_(
                'index.php?option=com_j2commerce&view=checkout',
                false,
                Route::TLS_IGNORE,
                true
            );

            $context += [
                'subscriber_given_name' => $given,
                'subscriber_surname'    => $surname,
                'subscriber_email'      => $email,
                'return_url'            => $returnUrl,
                'cancel_url'            => $cancelUrl,
            ];

            $created = $subs->createSubscription($planId, $stub, $context);

            if (empty($created['success'])) {
                return [
                    'success' => false,
                    'error'   => $created['error'] ?? 'PayPal subscription create failed',
                ];
            }

            $details = json_decode((string) ($orderTable->transaction_details ?? '{}'), true);

            if (!\is_array($details)) {
                $details = [];
            }

            $details['paypal_subscription_id'] = $created['id'];
            $details['paypal_plan_id']         = $planId;
            $details['paypal_product_id']      = $productId;
            $details['subscription_status']    = $created['status'];
            $details['created_at']             = date('Y-m-d H:i:s');

            $orderTable->transaction_details = json_encode($details);
            $orderTable->store();

            $this->log(\sprintf(
                'createPayPalSubscriptionForOrder: order=%s paypal_sub=%s plan=%s product=%s',
                $orderId,
                $created['id'],
                $planId,
                $productId
            ));

            return [
                'success'                => true,
                'is_subscription'        => true,
                'paypal_subscription_id' => $created['id'],
                'plan_id'                => $planId,
                'approve_url'            => $created['approve_url'],
            ];
        } catch (\Throwable $e) {
            $this->log('createPayPalSubscriptionForOrder exception: ' . $e->getMessage(), Log::ERROR);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * onApprove branch for subscription carts. PayPal already auto-charged the
     * first cycle as part of subscription activation — we just verify it went
     * ACTIVE / APPROVED and mark the local order paid. No Orders v2 capture
     * call needed.
     *
     * The subsequent onJ2CommerceAfterPayment listener (priority -100) writes
     * the paypal_subscription_id metakey to link the local subscription row.
     */
    public function finalizePayPalSubscriptionApproval(string $orderIdString, string $paypalSubscriptionId): array
    {
        try {
            if ($orderIdString === '') {
                return ['success' => false, 'error' => 'Missing order_id'];
            }

            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderIdString])) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')];
            }

            $details = json_decode((string) ($orderTable->transaction_details ?? '{}'), true) ?: [];

            if ($paypalSubscriptionId === '') {
                $paypalSubscriptionId = (string) ($details['paypal_subscription_id'] ?? '');
            }

            if ($paypalSubscriptionId === '') {
                return ['success' => false, 'error' => 'No PayPal subscription id stored on order'];
            }

            $info   = $this->getPayPalSubscriptions()->getSubscriptionDetails($paypalSubscriptionId);
            $status = strtoupper((string) ($info['body']['status'] ?? ''));

            if (!\in_array($status, ['ACTIVE', 'APPROVED', 'APPROVAL_PENDING'], true)) {
                $this->log(
                    'finalizePayPalSubscriptionApproval: unexpected PayPal sub status=' . $status . ' for ' . $paypalSubscriptionId,
                    Log::WARNING
                );

                return [
                    'success' => false,
                    'error'   => 'PayPal subscription is not active (status: ' . $status . ')',
                ];
            }

            $confirmedStateId = (int) $this->params->get('order_state_id', 1);

            $orderTable->orderpayment_type   = $this->_name;
            $orderTable->order_state_id      = $confirmedStateId;
            $orderTable->transaction_id      = $paypalSubscriptionId;
            $orderTable->transaction_status  = 'Completed';

            $details['paypal_subscription_id'] = $paypalSubscriptionId;
            $details['subscription_status']    = $status;
            $details['approved_at']            = date('Y-m-d H:i:s');

            $orderTable->transaction_details = json_encode($details);
            $orderTable->store();

            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: Text::sprintf(
                    'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                    Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                ) . ' (subscription ' . $paypalSubscriptionId . ', status ' . $status . ')',
                orderStateId: $confirmedStateId,
            );

            $this->log(\sprintf(
                'finalizePayPalSubscriptionApproval: order=%s paypal_sub=%s status=%s',
                $orderIdString,
                $paypalSubscriptionId,
                $status
            ));

            return [
                'success'                => true,
                'is_subscription'        => true,
                'paypal_subscription_id' => $paypalSubscriptionId,
                'remote_status'          => $status,
                'redirect'               => Route::_(
                    'index.php?option=com_j2commerce&view=checkout&task=checkout.confirmPayment'
                    . '&orderpayment_type=' . $this->_name
                    . '&order_id=' . urlencode($orderIdString),
                    false
                ),
            ];
        } catch (\Throwable $e) {
            $this->log('finalizePayPalSubscriptionApproval exception: ' . $e->getMessage(), Log::ERROR);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function _prePayment(array $data): string
    {
        $this->log('_prePayment: Preparing PayPal payment form for order_id: ' . ($data['order_id'] ?? 'N/A'));

        $vars = new \stdClass();

        $vars->order_id            = $data['order_id'];
        $vars->orderpayment_id     = $data['orderpayment_id'] ?? 0;
        $vars->orderpayment_amount = $data['orderpayment_amount'];
        $vars->orderpayment_type   = $this->_name;

        $vars->display_name         = Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL'));
        $vars->display_image        = $this->params->get('display_image', '');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');

        $order = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');
        $order->load(['order_id' => $vars->order_id]);

        $sandbox         = (int) $this->params->get('sandbox', 0);
        $vars->sandbox   = (bool) $sandbox;
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
        $vars->debug      = (int) $this->params->get('debug', 0);

        $vars->is_subscription   = $this->isSubscriptionOrder((string) $vars->order_id);
        $vars->subscription_mode = $vars->is_subscription ? $this->getSubscriptionMode() : 'rest';

        $this->log('_prePayment: Prepared vars - order_id: ' . $vars->order_id . ', currency: ' . $vars->currency_code . ', amount: ' . $vars->orderpayment_amount . ', is_subscription: ' . ($vars->is_subscription ? '1' : '0') . ', subscription_mode: ' . $vars->subscription_mode);

        return $this->_getLayout('prepayment', $vars);
    }

    public function _postPayment(object $data): string
    {
        $app     = Factory::getApplication();
        $vars    = new \stdClass();
        $paction = $app->input->getString('paction');
        $html    = '';

        $this->log('_postPayment: Processing payment response with paction: ' . $paction);

        // isAlreadyFinalized short-circuit — re-entry from a side-finalised flow
        // (Smart Buttons capture, vault subscription approval, replayed NVP
        // return). Order has transaction_status=Completed; skip the gateway
        // call and render the postpayment template. CheckoutController then
        // dispatches onJ2CommerceAfterPayment via the no-paction branch, which
        // creates the subscription row, sends the order email, and clears the
        // cart. Without this guard, a refresh on the return URL would re-call
        // DoExpressCheckoutPayment and fail.
        $orderIdFromUrl = (string) $app->input->getString('order_id', '');

        if ($orderIdFromUrl === '') {
            $orderIdFromUrl = (string) ($data->order_id ?? '');
        }

        if ($paction === '' && $orderIdFromUrl !== '' && $this->isAlreadyFinalized($orderIdFromUrl)) {
            $this->log('_postPayment: Order ' . $orderIdFromUrl . ' already finalized — rendering postpayment template');
            $vars->onafterpayment_text = Text::_($this->params->get('onafterpayment', ''));

            return $this->_getLayout('postpayment', $vars) . $this->base->_displayArticle();
        }

        // NVP Express Checkout return handling — customer just bounced back from
        // PayPal with TOKEN + PayerID query params. Run DoExpressCheckoutPayment
        // BEFORE the standard postpayment flow so the BAID gets stored and
        // onJ2CommerceAfterPayment fires with the right transaction_details.
        $nvpFlag    = $app->input->getInt('nvp', 0);
        $nvpToken   = (string) $app->input->getString('token', '');
        $nvpPayerId = (string) $app->input->getString('PayerID', '');

        if ($nvpFlag === 1 && $nvpToken !== '' && $nvpPayerId !== '') {
            $this->log('_postPayment NVP return: order=' . $orderIdFromUrl . ' token=' . $nvpToken . ' payerid=' . $nvpPayerId);

            $completion = $this->completeNvpExpressCheckoutForOrder($orderIdFromUrl, $nvpToken, $nvpPayerId);

            if (empty($completion['success'])) {
                $vars->message = (string) ($completion['error'] ?? Text::_($this->params->get('onerrorpayment', 'PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')));
                return $this->_getLayout('message', $vars);
            }

            // NVP completion stamped billing_agreement_id and
            // transaction_status=Completed onto the order. Render the
            // postpayment template now and let CheckoutController dispatch
            // onJ2CommerceAfterPayment via the no-paction branch (which writes
            // the metakey, creates the subscription row, and sends emails).
            $vars->onafterpayment_text = Text::_($this->params->get('onafterpayment', ''));

            return $this->_getLayout('postpayment', $vars) . $this->base->_displayArticle();
        }

        switch ($paction) {
            case 'display':
                $vars->onafterpayment_text = Text::_($this->params->get('onafterpayment', ''));
                $html                      = $this->_getLayout('postpayment', $vars);
                $html .= $this->base->_displayArticle();
                $this->log('_postPayment: Displaying success message');
                break;

            case 'cancel':
                $vars->message = Text::_($this->params->get('oncancelpayment', 'PLG_J2COMMERCE_PAYMENT_PAYPAL_CANCELLED'));
                $html          = $this->_getLayout('message', $vars);
                $this->log('_postPayment: Payment cancelled by user');
                break;

            default:
                $vars->message = Text::_($this->params->get('onerrorpayment', 'PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'));
                $html          = $this->_getLayout('message', $vars);
                $this->log('_postPayment: Payment error - paction: ' . $paction, Log::ERROR);
                break;
        }

        return $html;
    }

    /**
     * True when the given order has already been finalised at PayPal
     * (transaction_status === 'Completed'). Used as a short-circuit in
     * _postPayment so a refresh / replay of the return URL doesn't re-call
     * the gateway.
     */
    private function isAlreadyFinalized(string $orderIdString): bool
    {
        if ($orderIdString === '') {
            return false;
        }

        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderIdString])) {
                return false;
            }

            return strtolower((string) ($orderTable->transaction_status ?? '')) === 'completed';
        } catch (\Throwable $e) {
            $this->log('isAlreadyFinalized exception: ' . $e->getMessage(), Log::ERROR);

            return false;
        }
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

            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select('*')
                ->from($db->quoteName('#__j2commerce_orderitems'))
                ->where($db->quoteName('order_id') . ' = :order_id')
                ->bind(':order_id', $orderId);

            $db->setQuery($query);
            $orderItems = $db->loadObjectList();

            $items     = [];
            $itemTotal = 0.0;

            foreach ($orderItems as $item) {
                $unitAmount = (float) $item->orderitem_price;
                $quantity   = (int) $item->orderitem_quantity;

                $items[] = [
                    'name'        => $item->orderitem_name,
                    'quantity'    => $quantity,
                    'unit_amount' => $unitAmount,
                    'sku'         => $item->orderitem_sku ?? '',
                ];

                $itemTotal += $unitAmount * $quantity;
            }

            $shipping = (float) $orderTable->order_shipping + (float) $orderTable->order_shipping_tax;
            $tax      = (float) $orderTable->order_tax;
            $discount = (float) $orderTable->order_discount;
            $total    = (float) $orderTable->order_total;

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

            $this->log('createPayPalOrder: Sending order data to PayPal API - order_id: ' . $orderId . ', total: ' . $total . ' ' . $currency . ', items: ' . \count($items));

            $orders = $this->getPayPalOrders();
            $result = $orders->createOrder($orderData);

            $this->log('createPayPalOrder: PayPal API response - status: ' . $result['status']);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $paypalOrderId = $result['body']['id'] ?? '';

                $transactionDetails                    = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['paypal_order_id'] = $paypalOrderId;
                $transactionDetails['created_at']      = date('Y-m-d H:i:s');
                $orderTable->transaction_details       = json_encode($transactionDetails);
                $orderTable->store();

                $this->log('createPayPalOrder: Success - paypal_order_id: ' . $paypalOrderId);
                return ['success' => true, 'paypal_order_id' => $paypalOrderId];
            }

            $errorMessage = $result['body']['message'] ?? 'PayPal order creation failed';
            $details      = $result['body']['details'] ?? [];
            $detailStr    = !empty($details) ? ' Details: ' . json_encode($details) : '';
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

                $captureId     = $capture['id'] ?? '';
                $captureStatus = $capture['status'] ?? '';

                $this->log('capturePayPalOrder: Capture successful - capture_id: ' . $captureId . ', status: ' . $captureStatus);

                $orderStateId                   = (int) $this->params->get('payment_status', 4);
                $orderTable->order_state_id     = $orderStateId;
                $orderTable->transaction_id     = $captureId;
                $orderTable->transaction_status = $captureStatus;

                $transactionDetails                     = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['capture_id']       = $captureId;
                $transactionDetails['capture_status']   = $captureStatus;
                $transactionDetails['captured_at']      = date('Y-m-d H:i:s');
                $transactionDetails['capture_response'] = $result['body'];
                $orderTable->transaction_details        = json_encode($transactionDetails);

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
            $sandbox  = (bool) $this->params->get('sandbox', 0);
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
            $webhookId            = $this->params->get('webhook_id', '');
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

    private function getPayPalSubscriptions(): PayPalSubscriptions
    {
        if (!$this->paypalSubscriptions) {
            $this->paypalSubscriptions = new PayPalSubscriptions($this->getPayPalClient());
        }

        return $this->paypalSubscriptions;
    }

    /**
     * NVP client for legacy J2Store BAID renewals (Path C hybrid).
     *
     * Credential selection follows availability, NOT the site's REST sandbox
     * toggle:
     *   - When site is sandbox=1: prefer sandbox NVP creds; fall back to live
     *     NVP creds if sandbox creds are blank (PayPal stopped issuing sandbox
     *     NVP to new merchants — live-only is common for J2Store migrants).
     *   - When site is sandbox=0: live NVP creds only.
     *
     * The fallback logs a warning each time it's used so admins know they're
     * hitting live PayPal endpoints during sandbox testing. Migrated BAIDs are
     * live-account-issued anyway, so a live-NVP fallback is the only way to
     * actually exercise renewals against real BAIDs.
     *
     * Returns a configured client even when ALL credentials are blank — caller
     * checks ->isConfigured() before invoking renewal methods.
     */
    private function getPayPalNvp(): PayPalNvpClient
    {
        if (!$this->paypalNvp) {
            $siteSandbox = (bool) (int) $this->params->get('sandbox', 0);

            $sandboxUser = (string) $this->params->get('sandbox_api_username', '');
            $sandboxPwd  = (string) $this->params->get('sandbox_api_password', '');
            $sandboxSig  = (string) $this->params->get('sandbox_api_signature', '');
            $liveUser    = (string) $this->params->get('api_username', '');
            $livePwd     = (string) $this->params->get('api_password', '');
            $liveSig     = (string) $this->params->get('api_signature', '');

            $sandboxConfigured = $sandboxUser !== '' && $sandboxPwd !== '' && $sandboxSig !== '';
            $liveConfigured    = $liveUser !== '' && $livePwd !== '' && $liveSig !== '';

            // Pick which credential set + endpoint to use:
            //   - Sandbox creds available → use sandbox endpoint
            //   - No sandbox creds but live creds available → use live endpoint
            //     (warn when this happens during sandbox-mode testing)
            //   - Neither → empty client; isConfigured() returns false
            if ($siteSandbox && $sandboxConfigured) {
                $useSandbox = true;
                $useUser    = $sandboxUser;
                $usePwd     = $sandboxPwd;
                $useSig     = $sandboxSig;
            } elseif ($liveConfigured) {
                $useSandbox = false;
                $useUser    = $liveUser;
                $usePwd     = $livePwd;
                $useSig     = $liveSig;

                if ($siteSandbox) {
                    $this->log(
                        'NVP fallback: site is sandbox=1 but only LIVE NVP credentials are configured — NVP renewals will hit api-3t.paypal.com against your LIVE PayPal account. This is intentional for testing migrated J2Store BAIDs (which are live-account-issued).',
                        Log::WARNING
                    );
                }
            } else {
                $useSandbox = $siteSandbox;
                $useUser    = '';
                $usePwd     = '';
                $useSig     = '';
            }

            $logger = function (string $message, int $priority): void {
                $this->log($message, $priority);
            };

            $this->paypalNvp = new PayPalNvpClient(
                apiUsername:  $useUser,
                apiPassword:  $usePwd,
                apiSignature: $useSig,
                sandbox:      $useSandbox,
                debug:        (bool) (int) $this->params->get('debug', 0),
                logger:       \Closure::fromCallable($logger)
            );
        }

        return $this->paypalNvp;
    }

    private function checkGeozone(int $geozoneId, array $address): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $countryId = (int) ($address['country_id'] ?? 0);
        $zoneId    = (int) ($address['zone_id'] ?? 0);

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

    /**
     * AJAX entry point for the PayPal sandbox subscription test harness.
     *
     * Routed via:
     *   index.php?option=com_ajax&group=j2commerce&plugin=payment_paypal&format=json&task=<task>
     *
     * Tasks:
     *   testCredentials       — exercise OAuth (validates client_id + secret)
     *   testCreateSubscription — full flow: catalog product → billing plan → subscription, returns approve_url
     *   testCheckStatus       — given paypal_subscription_id query param, returns PayPal subscription details
     *   testCancel            — given paypal_subscription_id query param, cancels at PayPal
     *
     * All tasks gate on core.admin ACL + form token. Sandbox-only by design — refuses to run when sandbox=0.
     */
    public function onAjaxHandler(Event $event): void
    {
        $app  = Factory::getApplication();
        $task = $app->getInput()->getCmd('task', '');
        $user = $app->getIdentity();

        if (!$user || $user->guest || !$user->authorise('core.admin')) {
            $event->setArgument('result', ['ok' => false, 'error' => 'Forbidden', 'status' => 403]);
            return;
        }

        if (!Session::checkToken('request')) {
            $event->setArgument('result', ['ok' => false, 'error' => 'Invalid token', 'status' => 403]);
            return;
        }

        if ((int) $this->params->get('sandbox', 0) !== 1) {
            $event->setArgument('result', [
                'ok'    => false,
                'error' => 'Test harness only runs in sandbox mode. Enable "Use sandbox" in plugin params first.',
            ]);
            return;
        }

        try {
            $result = match ($task) {
                'testCredentials'        => $this->testSandboxCredentials(),
                'testCreateSubscription' => $this->testSandboxCreateSubscription(),
                'testCheckStatus'        => $this->testSandboxCheckStatus(),
                'testCancel'             => $this->testSandboxCancel(),
                'testNvpCredentials'     => $this->testSandboxNvpCredentials(),
                default                  => ['ok' => false, 'error' => 'Unknown task: ' . $task],
            };
        } catch (\Throwable $e) {
            $this->log('Sandbox test ' . $task . ' exception: ' . $e->getMessage(), Log::ERROR);
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }

        $event->setArgument('result', $result);
    }

    /**
     * Step 1 — exercise OAuth via a single GET against an endpoint that requires a token.
     * Bad credentials → 401; good → 200 with the bearer working.
     */
    private function testSandboxCredentials(): array
    {
        $client = $this->getPayPalClient();

        // GET /v1/billing/plans?page_size=1 is cheap and only succeeds with a valid token.
        $response = $client->request('GET', '/v1/billing/plans?page_size=1');
        $status   = (int) ($response['status'] ?? 0);

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'message' => 'Sandbox credentials valid (bearer token authenticated against /v1/billing/plans)'];
        }

        return [
            'ok'     => false,
            'status' => $status,
            'error'  => 'Credentials test failed — HTTP ' . $status,
            'detail' => $response['body'] ?? null,
            'hint'   => 'Verify sandbox_client_id and sandbox_secret in plugin params; sandbox account must have Subscriptions enabled.',
        ];
    }

    /**
     * Step 2 — full create flow. Creates a real catalog product + plan + subscription
     * in the connected PayPal sandbox account. Plan is $1.00/day (Subscriptions API
     * minimum interval is DAY), set to 5 cycles total so it auto-expires if forgotten.
     *
     * Returns the approve URL — the admin clicks it, logs into a sandbox PERSONAL
     * account, approves, and PayPal redirects back. After approval, click "Check
     * Status" to verify ACTIVE + read the next billing date.
     */
    private function testSandboxCreateSubscription(): array
    {
        $subs = $this->getPayPalSubscriptions();

        $fakeSubscription = (object) [
            'id'                  => 9999000 + random_int(1, 9999),
            'product_id'          => 99,
            'period'              => 'DAY',
            'period_units'        => 1,
            'subscription_length' => 5,
            'renewal_amount'      => 1.00,
        ];

        $fakeOrder = (object) [
            'order_id'    => 'TEST-' . date('YmdHis'),
            'order_total' => 1.00,
        ];

        $brand = Factory::getApplication()->get('sitename', 'J2Commerce');

        $context = [
            'brand_name'     => $brand,
            'product_name'   => 'J2Commerce Sandbox Test Subscription',
            'currency_code'  => 'USD',
            'catalog_prefix' => 'j2c-sandboxtest',
        ];

        $productId = $subs->getOrCreateCatalogProduct($fakeSubscription, $context);
        $planId    = $subs->createBillingPlan($fakeSubscription, $fakeOrder, $context);

        $returnUrl = Factory::getApplication()->get('live_site') ?: rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
        $context += [
            'subscriber_given_name' => 'Sandbox',
            'subscriber_surname'    => 'Tester',
            'subscriber_email'      => 'sandbox-tester@example.com',
            'return_url'            => $returnUrl . '/index.php?paypal_test=approved',
            'cancel_url'            => $returnUrl . '/index.php?paypal_test=cancelled',
        ];

        $created = $subs->createSubscription($planId, $fakeSubscription, $context);

        if (empty($created['success'])) {
            return [
                'ok'         => false,
                'error'      => $created['error'] ?? 'Unknown error during createSubscription',
                'product_id' => $productId,
                'plan_id'    => $planId,
            ];
        }

        return [
            'ok'                     => true,
            'message'                => 'Subscription created in PayPal sandbox. Open the approve_url in a new tab, log in with a sandbox PERSONAL account, approve, then run "testCheckStatus".',
            'paypal_subscription_id' => $created['id'],
            'status'                 => $created['status'],
            'approve_url'            => $created['approve_url'],
            'product_id'             => $productId,
            'plan_id'                => $planId,
        ];
    }

    private function testSandboxCheckStatus(): array
    {
        $paypalSubId = trim((string) Factory::getApplication()->getInput()->getString('paypal_subscription_id', ''));

        if ($paypalSubId === '') {
            return ['ok' => false, 'error' => 'paypal_subscription_id query param required'];
        }

        $details = $this->getPayPalSubscriptions()->getSubscriptionDetails($paypalSubId);
        $body    = $details['body'] ?? [];
        $status  = (string) ($body['status'] ?? 'UNKNOWN');

        return [
            'ok'                     => ($details['status'] >= 200 && $details['status'] < 300),
            'http_status'            => $details['status'],
            'paypal_subscription_id' => $paypalSubId,
            'remote_status'          => $status,
            'next_billing_time'      => $body['billing_info']['next_billing_time'] ?? null,
            'plan_id'                => $body['plan_id'] ?? null,
            'subscriber_email'       => $body['subscriber']['email_address'] ?? null,
            'last_payment'           => $body['billing_info']['last_payment'] ?? null,
            'failed_payments_count'  => $body['billing_info']['failed_payments_count'] ?? null,
            'raw'                    => $body,
        ];
    }

    private function testSandboxCancel(): array
    {
        $paypalSubId = trim((string) Factory::getApplication()->getInput()->getString('paypal_subscription_id', ''));

        if ($paypalSubId === '') {
            return ['ok' => false, 'error' => 'paypal_subscription_id query param required'];
        }

        $ok = $this->getPayPalSubscriptions()->cancelSubscription($paypalSubId, 'Sandbox test cleanup');

        return [
            'ok'                     => $ok,
            'paypal_subscription_id' => $paypalSubId,
            'message'                => $ok ? 'Cancelled at PayPal' : 'Cancel failed — see plugin log',
        ];
    }

    /**
     * Test the NVP transport + credentials. Calls BillAgreementUpdate against a
     * deliberately bogus REFERENCEID — PayPal will return ACK=Failure with a
     * specific error code (10201 / 10004) when credentials are GOOD but the BAID
     * doesn't exist. That's the success indicator: the request was authenticated
     * and parsed by PayPal, just rejected because the BAID is bogus.
     *
     * If credentials are bad, PayPal returns a different error: 10002 "Security
     * header is not valid" — which the test surfaces as configuration error.
     *
     * Credential fallback: tries sandbox creds first; if any blank, falls back
     * to live creds against the LIVE PayPal NVP endpoint (clearly labelled in
     * the response so the admin knows). PayPal stopped issuing sandbox NVP
     * credentials to new merchants — many existing J2Store merchants only have
     * live NVP creds, and the BillAgreementUpdate-with-bogus-BAID test is
     * harmless against live (no charge, no state change).
     */
    private function testSandboxNvpCredentials(): array
    {
        $sandboxUser = (string) $this->params->get('sandbox_api_username', '');
        $sandboxPwd  = (string) $this->params->get('sandbox_api_password', '');
        $sandboxSig  = (string) $this->params->get('sandbox_api_signature', '');

        $liveUser = (string) $this->params->get('api_username', '');
        $livePwd  = (string) $this->params->get('api_password', '');
        $liveSig  = (string) $this->params->get('api_signature', '');

        $sandboxConfigured = $sandboxUser !== '' && $sandboxPwd !== '' && $sandboxSig !== '';
        $liveConfigured    = $liveUser !== '' && $livePwd !== '' && $liveSig !== '';

        if (!$sandboxConfigured && !$liveConfigured) {
            return [
                'ok'    => false,
                'error' => 'No NVP credentials configured. Fill EITHER the live (api_*) OR sandbox (sandbox_api_*) NVP credential fields.',
                'hint'  => 'NVP credentials are needed only if you have migrated J2Store subscriptions with billing_agreement_id metakey. PayPal stopped issuing NVP credentials to new merchants in 2023 — existing J2Store merchants typically have LIVE creds (sandbox NVP is rarer to obtain).',
            ];
        }

        $useLive = !$sandboxConfigured && $liveConfigured;
        $logger  = function (string $message, int $priority): void {
            $this->log($message, $priority);
        };

        $client = new PayPalNvpClient(
            apiUsername:  $useLive ? $liveUser : $sandboxUser,
            apiPassword:  $useLive ? $livePwd : $sandboxPwd,
            apiSignature: $useLive ? $liveSig : $sandboxSig,
            sandbox:      !$useLive,
            debug:        (bool) (int) $this->params->get('debug', 0),
            logger:       \Closure::fromCallable($logger)
        );

        $bogusReferenceId = 'B-NVPTEST' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $response         = $client->getBillAgreementDetails($bogusReferenceId);

        $code     = (string) ($response['L_ERRORCODE0'] ?? '');
        $short    = (string) ($response['L_SHORTMESSAGE0'] ?? '');
        $endpoint = $useLive ? 'api-3t.paypal.com (LIVE)' : 'api-3t.sandbox.paypal.com (sandbox)';

        // Auth failure codes — credentials are wrong / signature mismatch
        $authFailureCodes = ['10002', '10001'];

        if (\in_array($code, $authFailureCodes, true)) {
            return [
                'ok'               => false,
                'used_credentials' => $useLive ? 'live' : 'sandbox',
                'tested_endpoint'  => $endpoint,
                'error'            => 'NVP authentication failed: [' . $code . '] ' . $short,
                'gateway_response' => $response,
                'hint'             => 'Verify ' . ($useLive ? 'live' : 'sandbox') . ' api_username, api_password, and api_signature match your PayPal account exactly. They are case-sensitive and have no surrounding whitespace.',
            ];
        }

        $message = 'NVP credentials authenticated against ' . $endpoint
            . ' (PayPal accepted the request — error ' . $code . ' / "' . $short
            . '" is expected for the bogus test BAID, which means transport + auth work correctly).';

        if ($useLive) {
            $message = '⚠️ Tested with LIVE NVP credentials (sandbox creds were blank). The bogus BAID test does NOT charge or change anything at PayPal — it is safe against live. ' . $message;
        }

        return [
            'ok'               => true,
            'used_credentials' => $useLive ? 'live' : 'sandbox',
            'tested_endpoint'  => $endpoint,
            'message'          => $message,
            'gateway_response' => $response,
            'note'             => 'Now ready to renew migrated J2Store subscriptions with billing_agreement_id metakey via the renewal cron.'
                . ($useLive ? ' Note: live NVP creds will be used for ALL renewals (sandbox toggle in plugin params controls REST sandbox/live, NOT NVP — NVP routes by which credential set is configured).' : ''),
        ];
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
            'link'        => Route::_('index.php?option=com_plugins&task=plugin.edit&layout=edit&extension_id=' . (int) $this->getExtensionId()),
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
