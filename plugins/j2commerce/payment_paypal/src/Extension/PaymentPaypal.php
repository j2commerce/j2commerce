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

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductTypeHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\TableSaveHelper;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Base;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\Payment;
use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use J2Commerce\Component\J2commerce\Site\Helper\CheckoutContextHelper;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Helper\PayPalCurrencyHelper;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalClient;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalNvpClient;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalOrders;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service\PayPalOrderStates;
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

    private const LOG_CATEGORY = 'j2commerce.paypal';

    /**
     * Recorded even with the plugin's debug off. WARNING is in the set for the notice that a
     * sandbox-configured site is transacting against the live account, and for the gateway
     * degradation notices in PayPalClient -- signals a merchant needs whether or not they
     * are debugging. Deliberately one priority wider than the component's own policy, which
     * records ERROR and above -- a gateway a merchant cannot reach is worth a line either way.
     */
    private const ALWAYS_LOGGED = Log::WARNING | Log::ERROR | Log::CRITICAL | Log::ALERT | Log::EMERGENCY;

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

        $this->registerLogger();
    }

    /**
     * Registered up front, not lazily from log(): the PSR-3 call sites in this plugin and in
     * PayPalWebhooks emit straight to the category, so a registration that only happened as a
     * side effect of an earlier log() call left them with nowhere to go.
     */
    private function registerLogger(): void
    {
        if (self::$loggerAdded) {
            return;
        }

        self::$loggerAdded = true;

        // The plugin keeps its own file rather than folding into com_j2commerce.log.php:
        // Log::add() fans out to every logger whose category and priority match, so a
        // Log::ALL registration on com_j2commerce would pull all component call sites into
        // this file and override the priority policy the system plugin sets deliberately.
        Log::addLogger(
            ['text_file' => 'payment_paypal.php'],
            $this->isDebug() ? Log::ALL : self::ALWAYS_LOGGED,
            [self::LOG_CATEGORY]
        );
    }

    private function isDebug(): bool
    {
        // CMSPlugin assigns params only when the config carries them, and this now runs from
        // the constructor -- a null-safe read keeps a payment plugin from fataling site-wide.
        return (int) ($this->params?->get('debug', 0) ?? 0) === 1;
    }

    private function log(string $message, int $priority = Log::DEBUG): void
    {
        // Gated on emission as well as on registration: Log::add() fans out to every logger
        // whose category and priority match, and core registers a catch-all of its own when
        // the site has "Log Almost Everything" on. The registered mask governs this plugin's
        // file only, so without this guard a debug-off install would write these lines into
        // that catch-all, which is not what the debug parameter promises.
        if (!$this->isDebug() && !($priority & self::ALWAYS_LOGGED)) {
            return;
        }

        // A request value reaches some of these messages, and the text-file logger writes the
        // message into a tab-delimited line verbatim -- a newline would forge a second entry.
        Log::add(str_replace(["\r", "\n"], ' ', $message), $priority, self::LOG_CATEGORY);
    }

    /**
     * Writes the confirmed status only when one resolved. 0 means the store has no status
     * classified 'complete' and no core row named J2COMMERCE_CONFIRMED, and writing it would
     * leave a paid order in a status that does not exist. WARNING is in ALWAYS_LOGGED, so the
     * line survives a debug-off install -- a merchant has to be able to find this.
     */
    private function applyConfirmedState(object $orderTable, int $stateId): void
    {
        if ($stateId > 0) {
            $orderTable->order_state_id = $stateId;

            return;
        }

        $this->log(
            'Capture completed for order ' . $orderTable->order_id . ' but no order status'
            . ' resolved, so the status is left unchanged. Classify a status as Complete, or'
            . ' restore the core Confirmed status.',
            Log::WARNING
        );
    }

    /**
     * Records a payment PayPal has not settled without moving the order status, which stays
     * awaiting payment until the settlement webhook arrives.
     *
     * @param array<string, mixed> $details
     * @return array{success: bool, pending?: bool, error?: string, redirect?: string}
     */
    private function recordUnsettledPayment(
        object $orderTable,
        string $transactionId,
        string $status,
        array $details,
        string $idKey
    ): array {
        // Only a status naming a refusal is a decline. Any other status that came with a transaction
        // id, an unfamiliar or empty one included, may have moved money and is held for review.
        $pending = $transactionId !== ''
            && !\in_array(strtoupper($status), ['DENIED', 'DECLINED', 'FAILED', 'EXPIRED', 'VOIDED'], true);
        $message = $pending
            ? Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING')
            : Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED');
        $failed  = [
            'success' => false,
            'pending' => false,
            'error'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'),
        ];

        $this->log('Payment for order ' . $orderTable->order_id . ' not settled - status: ' . $status, Log::WARNING);

        $orderPk  = (int) $orderTable->j2commerce_order_id;
        $awaiting = PayPalOrderStates::awaitingStateIds($this->params);

        if ($awaiting === [] || !$orderTable->load($orderPk)) {
            $this->log('No awaiting-payment status resolves; order ' . $orderPk . ' cannot be held', Log::ERROR);

            return $failed;
        }

        // Only the transaction columns, and only while the order still awaits payment: a capture
        // webhook that settled it since the load must not have that settlement written back.
        $db         = $this->getDatabase();
        $storedId   = $transactionId;
        $stored     = $pending ? 'PENDING' : $status;
        $newDetails = json_encode(array_merge(
            PayPalOrderStates::details((string) ($orderTable->transaction_details ?? '')),
            $details
        ));
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_id') . ' = :tid')
            ->set($db->quoteName('transaction_status') . ' = :tstatus')
            ->set($db->quoteName('transaction_details') . ' = :tdetails')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->whereIn($db->quoteName('order_state_id'), $awaiting)
            ->bind(':tid', $storedId)
            ->bind(':tstatus', $stored)
            ->bind(':tdetails', $newDetails)
            ->bind(':pk', $orderPk, ParameterType::INTEGER);
        PayPalOrderStates::whereClaimable($db, $query);
        $db->setQuery($query)->execute();

        if (!$orderTable->load($orderPk)) {
            return $failed;
        }

        $ownDetails = json_decode((string) ($orderTable->transaction_details ?? '{}'), true) ?: [];
        $recorded   = (string) ($ownDetails[$idKey] ?? '') === $transactionId
            && strtoupper((string) $orderTable->transaction_status) === strtoupper($stored);

        if (!$recorded) {
            return $this->settledElsewhere($orderTable, $idKey, $pending);
        }

        // The hold is recorded: a failed history write must not turn it into a reported failure.
        try {
            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: $message,
                orderStateId: (int) $orderTable->order_state_id
            );
        } catch (\Throwable $e) {
            $this->log('Unsettled payment history write failed - ' . $e->getMessage(), Log::ERROR);
        }

        return ['success' => false, 'pending' => $pending, 'error' => $message];
    }

    /**
     * Another request settled this order while this one ran, and as the settlement owner it ran
     * AfterPayment and the emails. A REST checkout still sends the buyer to the thank-you page,
     * without a second set of emails. An NVP return runs inside confirmPayment(), which would
     * dispatch AfterPayment again on a success, so it reports the request as invalid instead.
     *
     * A declined attempt on an order an earlier payment settled is still reported as declined.
     *
     * @return array{success: bool, pending?: bool, error?: string, redirect?: string, send_emails?: bool}
     */
    private function settledElsewhere(object $orderTable, string $idKey, bool $paid): array
    {
        if (
            $paid
            && $idKey === 'capture_id'
            && PayPalOrderStates::isSettledOrSettling((string) ($orderTable->transaction_status ?? ''))
        ) {
            // Until the owner has stored the order, the buyer is told the payment is being
            // verified: an owner that fails hands the claim back and PayPal settles it later.
            if (!PayPalOrderStates::isSettlementStored($orderTable, $this->params)) {
                return [
                    'success' => false,
                    'pending' => true,
                    'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                ];
            }

            return ['success' => true, 'redirect' => $this->payment->getReturnUrl(), 'send_emails' => false];
        }

        return [
            'success' => false,
            'pending' => false,
            'error'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST'),
        ];
    }

    /** The money has moved and the order is stored; a failing listener must not report the payment as failed. */
    private function dispatchAfterPayment(object $orderTable): void
    {
        try {
            J2CommerceHelper::plugin()->eventWithArray('AfterPayment', [$orderTable]);
        } catch (\Throwable $e) {
            $this->log(
                'AfterPayment listener failed for order ' . $orderTable->order_id . ': ' . $e->getMessage(),
                Log::ERROR
            );
        }
    }

    /**
     * Records a new PayPal reference on the order by writing transaction_details alone, and only
     * while the order still awaits payment and nothing settles it. The row was loaded before a
     * PayPal round-trip; storing all of it could put a payment settled meanwhile back to awaiting.
     *
     * @param array<string, mixed> $keys
     */
    private function bindPayPalReference(object $orderTable, array $keys): bool
    {
        $awaiting = PayPalOrderStates::awaitingStateIds($this->params);

        if ($awaiting === []) {
            return false;
        }

        $db      = $this->getDatabase();
        $orderPk = (int) $orderTable->j2commerce_order_id;

        // Merged into the details as they are now, and written only if nobody changed them since:
        // a held payment recorded during the PayPal call keeps its capture reference.
        $current = (string) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('transaction_details'))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $orderPk, ParameterType::INTEGER)
        )->loadResult();
        $details = json_encode(array_merge(
            PayPalOrderStates::details($current),
            $keys,
            ['created_at' => date('Y-m-d H:i:s')]
        ));
        $pending    = 'PENDING';
        $empty      = $db->quote('');
        $detailsCol = 'COALESCE(' . $db->quoteName('transaction_details') . ', ' . $empty . ')';
        $statusCol  = 'UPPER(COALESCE(' . $db->quoteName('transaction_status') . ', ' . $empty . '))';
        $idCol      = 'COALESCE(' . $db->quoteName('transaction_id') . ', ' . $empty . ')';
        $query      = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_orders'))
            ->set($db->quoteName('transaction_details') . ' = :details')
            ->where($db->quoteName('j2commerce_order_id') . ' = :pk')
            ->whereIn($db->quoteName('order_state_id'), $awaiting)
            ->where($detailsCol . ' = :current')
            ->where('NOT (' . $statusCol . ' = :pending AND ' . $idCol . ' <> ' . $empty . ')')
            ->bind(':details', $details)
            ->bind(':pk', $orderPk, ParameterType::INTEGER)
            ->bind(':current', $current)
            ->bind(':pending', $pending);

        PayPalOrderStates::whereClaimable($db, $query);
        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1;
    }

    /** A payment PayPal is still holding must not be started again: a second one would charge twice. */
    private function hasPendingPayment(object $orderTable): bool
    {
        return strtoupper((string) ($orderTable->transaction_status ?? '')) === 'PENDING'
            && (string) ($orderTable->transaction_id ?? '') !== '';
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
            'name'    => J2htmlHelper::translateKey($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL')),
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
        $jsKeys = [
            'BUTTON_LOAD_FAILED',
            'CANCELLED',
            'CONTACT_SUPPORT',
            'DECLINED',
            'ERROR',
            'REDIRECT_MISSING',
            'TRY_AGAIN',
        ];

        foreach ($jsKeys as $key) {
            Text::script('PLG_J2COMMERCE_PAYMENT_PAYPAL_JS_' . $key);
        }

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

    /**
     * @return array{status: int, message: string}
     */
    private function handleWebhook(): array
    {
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || $rawBody === '') {
            return ['status' => 400, 'message' => 'No body'];
        }

        $webhookId = (bool) $this->params->get('sandbox', 0)
            ? $this->params->get('sandbox_webhook_id', '')
            : $this->params->get('webhook_id', '');

        if (empty($webhookId)) {
            return ['status' => 400, 'message' => 'Webhook ID not configured'];
        }

        try {
            $webhooks = $this->getPayPalWebhooks();

            if (!$webhooks->verifySignature($rawBody)) {
                return ['status' => 401, 'message' => 'Invalid signature'];
            }

            return $webhooks->handleEvent($rawBody, $this->params);
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook error: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['status' => 500, 'message' => 'Internal error'];
        }
    }

    public function onRefundPayment(Event $event): void
    {
        $args      = $event->getArguments();
        $element   = $args[0] ?? '';
        $orderId   = $args[1] ?? 0;
        $rawAmount = $args[2] ?? null;

        if ($element !== $this->_name) {
            return;
        }

        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => $orderId])) {
                $event->setArgument('result', ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')]);
                return;
            }

            $currency = $this->getCurrency($orderTable);
            $amount   = $rawAmount !== null
                ? CurrencyHelper::convertForOrder((float) $rawAmount, $orderTable)
                : null;

            // Read before the call: a refund webhook landing during it must not look like a replay.
            $orderPk        = (int) $orderTable->j2commerce_order_id;
            $hasLedger      = OrderTransactionHelper::hasLedger($orderPk);
            $refundedBefore = $hasLedger ? OrderTransactionHelper::getRefunded($orderPk) : 0.0;

            // The ledger names the refundable payment. transaction_id is not always one: a subscription
            // order stores the PayPal subscription id (I-...), whose sale is what can be refunded.
            $captureId = (string) $orderTable->transaction_id;

            if ($hasLedger) {
                $legs = OrderTransactionHelper::allocateRefund($orderPk, $amount ?? OrderTransactionHelper::getRefundable($orderPk));

                if (\count($legs) > 1) {
                    $event->setArgument('result', ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_REFUND_SPANS_PAYMENTS')]);
                    return;
                }

                $captureId = (string) ($legs[0]['gateway_txn_id'] ?? $captureId);
            }

            if ($captureId === '') {
                $event->setArgument('result', ['success' => false, 'error' => 'No capture ID found']);
                return;
            }

            $refunds = $this->getPayPalRefunds();
            $result  = $refunds->refundCapture($captureId, $amount, $currency);

            $refundStatus = strtoupper((string) ($result['body']['status'] ?? ''));

            $replied = $result['status'] >= 200 && $result['status'] < 300;

            if ($replied && \in_array($refundStatus, ['FAILED', 'CANCELLED'], true)) {
                $this->log('Refund for order ' . $orderId . ' returned status ' . $refundStatus, Log::ERROR);
                $event->setArgument('result', [
                    'success' => false,
                    'error'   => Text::_('COM_J2COMMERCE_ERR_REFUND_FAILED'),
                ]);

                return;
            }

            if ($replied) {
                $refundId        = $result['body']['id'] ?? '';
                $refundedStateId = PayPalOrderStates::resolve($this->params, PayPalOrderStates::REFUNDED);
                $replyCurrency   = strtoupper((string) ($result['body']['amount']['currency_code'] ?? $currency));
                $replyAmount     = $replyCurrency === $currency
                    ? (float) ($result['body']['amount']['value'] ?? 0)
                    : 0.0;
                $refundedAmount  = match (true) {
                    $replyAmount > 0 => (float) PayPalCurrencyHelper::format($replyAmount, $currency),
                    $amount !== null => (float) PayPalCurrencyHelper::format($amount, $currency),
                    default          => 0.0,
                };

                $recorded      = false;
                $chargedAmount = CurrencyHelper::gatewayAmount($orderTable);

                // A full refund whose reply carried no amount refunded whatever was left.
                if ($refundedAmount <= 0) {
                    $refundedAmount = (float) PayPalCurrencyHelper::format(
                        max(0.0, $chargedAmount - $refundedBefore),
                        $currency
                    );
                }

                // Keyed on PayPal's refund id so the PAYMENT.CAPTURE.REFUNDED webhook for this
                // same refund dedupes instead of adding a second reversal. The refund has already
                // happened at PayPal, so a ledger mismatch is logged rather than failing the order update.
                if ($refundId !== '' && $refundedAmount > 0 && $hasLedger) {
                    try {
                        OrderTransactionHelper::addReversal($orderPk, $this->_name, $refundId, $captureId, $refundedAmount);
                        $recorded = true;
                    } catch (\InvalidArgumentException $e) {
                        Factory::getApplication()->getLogger()->error(
                            'PayPal refund not recorded in the ledger: ' . $e->getMessage(),
                            ['category' => 'j2commerce.paypal']
                        );
                    }
                }

                // PayPal answers a repeated request id with the refund it already made. The ledger
                // holds that refund, so reporting success here would let the caller book it again.
                if (
                    $recorded
                    && PayPalCurrencyHelper::sameAmount(
                        OrderTransactionHelper::getRefunded($orderPk),
                        $refundedBefore,
                        $currency
                    )
                ) {
                    $this->log('Refund ' . $refundId . ' already recorded - order ' . $orderId, Log::WARNING);
                    $event->setArgument('result', [
                        'success' => false,
                        'error'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_REFUND_ALREADY_PROCESSED'),
                    ]);

                    return;
                }

                // addReversal() re-syncs order_refund in the database; storing the copy loaded
                // before it would write the pre-refund value back.
                if ($recorded && !$orderTable->load($orderPk)) {
                    $event->setArgument('result', ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_ORDER_NOT_FOUND')]);

                    return;
                }

                // A partial refund is not a refunded order: the status moves only when the whole
                // charge has come back, counting earlier partial refunds from the ledger.
                $refundedTotal   = $hasLedger
                    ? OrderTransactionHelper::getRefunded($orderPk) + ($recorded ? 0.0 : $refundedAmount)
                    : $refundedAmount;
                $isFullyRefunded = $amount === null
                    || PayPalCurrencyHelper::covers($refundedTotal, $chargedAmount, $currency);
                $newStateId      = $isFullyRefunded ? $refundedStateId : 0;

                if ($newStateId > 0) {
                    $orderTable->order_state_id = $newStateId;
                }

                $transactionDetails                  = json_decode($orderTable->transaction_details ?? '{}', true);
                $transactionDetails['refund_id']     = $refundId;
                $transactionDetails['refunded_at']   = date('Y-m-d H:i:s');
                $transactionDetails['refund_amount'] = $refundedAmount;
                $orderTable->transaction_details     = json_encode($transactionDetails);
                TableSaveHelper::store($orderTable, 'paypal.refund');

                OrderHistoryHelper::add(
                    orderId: $orderTable->order_id,
                    comment: Text::sprintf(
                        'PLG_J2COMMERCE_PAYMENT_PAYPAL_REFUND_PROCESSED',
                        PayPalCurrencyHelper::format($refundedAmount, $currency),
                        $currency
                    ),
                    orderStateId: $newStateId
                );

                $event->setArgument('result', ['success' => true, 'refund_id' => $refundId]);
            } else {
                $errorMessage = $result['body']['message'] ?? 'Refund failed';
                Factory::getApplication()->getLogger()->error(
                    "PayPal refund failed: $errorMessage",
                    ['category' => 'j2commerce.paypal']
                );

                $event->setArgument('result', [
                    'success' => false,
                    'error'   => Text::_('COM_J2COMMERCE_ERR_REFUND_FAILED'),
                ]);
            }
        } catch (\Exception $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal refund exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            $event->setArgument('result', ['success' => false, 'error' => Text::_('COM_J2COMMERCE_ERR_REFUND_FAILED')]);
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

    /** One billing agreement can back many local subscriptions; cancelling it remotely would stop every sibling's renewals. */
    private function baidHasLiveSiblings(int $subscriptionId, string $baid): bool
    {
        $db        = $this->getDatabase();
        $namespace = 'subscription';
        $resource  = 'subscriptions';
        $metakey   = 'billing_agreement_id';
        $canceled  = 'canceled';
        $expired   = 'expired';
        $statusCol = $db->quoteName('s.status');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_metafields', 'm'))
            ->join('INNER', $db->quoteName('#__j2commerce_subscriptions', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('m.owner_id'))
            ->where($db->quoteName('m.owner_resource') . ' = :res')
            ->where($db->quoteName('m.namespace') . ' = :ns')
            ->where($db->quoteName('m.metakey') . ' = :metakey')
            ->where($db->quoteName('m.metavalue') . ' = :metavalue')
            ->where($db->quoteName('m.owner_id') . ' != :sid')
            ->where('(' . $statusCol . ' IS NULL OR ' . $statusCol . ' NOT IN (:canceled, :expired))')
            ->bind(':canceled', $canceled)
            ->bind(':expired', $expired)
            ->bind(':res', $resource)
            ->bind(':ns', $namespace)
            ->bind(':metakey', $metakey)
            ->bind(':metavalue', $baid)
            ->bind(':sid', $subscriptionId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    /**
     * @return  array<string, mixed>
     */
    private function cancelNvpBaid(int $subscriptionId, string $baid, string $reason): array
    {
        if ($this->baidHasLiveSiblings($subscriptionId, $baid)) {
            $this->log(\sprintf('onAfterSubscriptionCanceled: sub=%d BAID=%s still backs other active subscriptions — remote cancel skipped', $subscriptionId, $baid));

            return [
                'status'        => 'subscription_cancellation_registered',
                'paypal_remote' => 'shared-kept',
                'legacy_baid'   => $baid,
            ];
        }

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
                'error'         => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'),
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
                'error'         => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'),
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
                $existing_int = (int)$existing;
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_metafields'))
                        ->set($db->quoteName('metavalue') . ' = :val')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':val', $metavalue)
                        ->bind(':id', $existing_int, ParameterType::INTEGER)
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
     * Capture-step branch for the NVP Express Checkout return flow. Customer
     * has approved at PayPal and bounced back with TOKEN+PayerID in URL params.
     * This calls DoExpressCheckoutPayment, extracts the BILLINGAGREEMENTID
     * (BAID), and stashes it in transaction_details so onAfterPayment writes
     * the billing_agreement_id metakey.
     *
     * Nothing starts an NVP Express Checkout any more; this finishes one a buyer began before the
     * upgrade. DELETE WHEN: no order has transaction_details.nvp_express_token without a payment.
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

            // The express token must match the one stored when this order's checkout was set up.
            $storedDetails = json_decode($orderTable->transaction_details ?? '{}', true);
            $boundToken    = (string) ($storedDetails['nvp_express_token'] ?? '');

            if ($boundToken === '' || !hash_equals($boundToken, $token)) {
                $this->log('completeNvpExpressCheckoutForOrder: express token not bound to local order - order_id: ' . $orderIdString, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            // Only an order still awaiting payment may be completed. A settled, cancelled or
            // refunded order cannot be flipped.
            if (
                !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $this->params)
                || (float) ($orderTable->order_refund ?? 0) > 0
            ) {
                $this->log('completeNvpExpressCheckoutForOrder: order not in a completable state - order_id: ' . $orderIdString . ', state: ' . $orderTable->order_state_id, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $nvp = $this->getPayPalNvp();

            if (!$nvp->isConfigured()) {
                $this->log('completeNvpExpressCheckoutForOrder: NVP credentials not configured', Log::ERROR);

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            $currency = $this->getCurrency($orderTable);
            $amount   = (float) PayPalCurrencyHelper::format(CurrencyHelper::gatewayAmount($orderTable), $currency);

            $response = $nvp->doExpressCheckoutPayment(
                token:         $token,
                payerId:       $payerId,
                amount:        $amount,
                currencyCode:  $currency,
                invoiceNumber: $orderIdString,
                custom:        $orderIdString
            );

            if (!PayPalNvpClient::isSuccess($response)) {
                $this->log(
                    'completeNvpExpressCheckoutForOrder: ' . PayPalNvpClient::getErrorMessage($response),
                    Log::ERROR
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            // Compare the charged amount and currency against the local order before
            // writing any paid state — a mismatch is logged for manual review.
            $chargedAmount   = (float) ($response['PAYMENTINFO_0_AMT'] ?? 0);
            $chargedCurrency = (string) ($response['PAYMENTINFO_0_CURRENCYCODE'] ?? '');

            if (
                $chargedCurrency !== $currency
                || !PayPalCurrencyHelper::sameAmount($chargedAmount, $amount, $currency)
            ) {
                $this->log(
                    'completeNvpExpressCheckoutForOrder: Amount/currency mismatch - charged ' . $chargedAmount . ' ' . $chargedCurrency
                    . ', expected ' . $amount . ' ' . $currency . ' for order_id: ' . $orderIdString,
                    Log::ERROR
                );

                // Surface it on the order page too — the buyer's money was charged
                // but the order stays unpaid until staff review.
                OrderHistoryHelper::add(
                    orderId: $orderIdString,
                    comment: Text::sprintf(
                        'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_MISMATCH',
                        $chargedAmount . ' ' . $chargedCurrency,
                        $amount . ' ' . $currency
                    ),
                    orderStateId: (int) $orderTable->order_state_id
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $baid    = (string) ($response['BILLINGAGREEMENTID'] ?? '');
            $transId = (string) ($response['PAYMENTINFO_0_TRANSACTIONID'] ?? '');

            if ($baid === '') {
                $this->log('completeNvpExpressCheckoutForOrder: NO BAID returned in DoExpressCheckoutPayment response', Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            // Same rule as the REST capture: only a completed payment confirms the order. This
            // runs inside the confirm step, where any status change reads as a finished payment.
            $paymentStatus = (string) ($response['PAYMENTINFO_0_PAYMENTSTATUS'] ?? '');

            if (!\in_array($paymentStatus, ['Completed', 'Completed-Funds-Held'], true)) {
                $unsettled = $this->recordUnsettledPayment($orderTable, $transId, $paymentStatus, [
                    'billing_agreement_id' => $baid,
                    'transaction_id'       => $transId,
                    'mode'                 => 'nvp_express',
                    'payment_status'       => $paymentStatus,
                ], 'transaction_id');

                // A classic (NVP) payment raises no REST webhook, so nothing settles this hold on
                // its own: staff confirm or cancel the order once PayPal decides.
                if (!empty($unsettled['pending'])) {
                    // The REST checkout empties the cart on a hold too, so it is not bought twice.
                    if (!CheckoutContextHelper::isOwningRequest()) {
                        CartHelper::emptyCart($orderIdString);
                    }

                    $this->log(
                        'completeNvpExpressCheckoutForOrder: payment held by PayPal for order ' . $orderIdString
                        . ' - manual review required',
                        Log::ERROR
                    );
                    try {
                        OrderHistoryHelper::add(
                            orderId: (string) $orderTable->order_id,
                            comment: Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_NVP_PENDING_REVIEW'),
                            orderStateId: (int) $orderTable->order_state_id
                        );
                    } catch (\Throwable $e) {
                        $this->log('NVP held-payment history write failed - ' . $e->getMessage(), Log::ERROR);
                    }
                }

                return $unsettled;
            }

            // Ahead of the claim, as the REST capture does: the money has moved either way.
            $this->recordLedgerCharge($orderIdString, $transId, $chargedAmount, $chargedCurrency);

            $confirmedStateId = PayPalOrderStates::resolve($this->params, PayPalOrderStates::CONFIRMED);
            $orderPk          = (int) $orderTable->j2commerce_order_id;
            $priorStatus      = (string) ($orderTable->transaction_status ?? '');

            // Same single-owner rule as the REST capture: a replayed return settles nothing twice.
            // The request that holds the claim finishes the order, so this one reports progress.
            $claim = PayPalOrderStates::claimSettlement($this->getDatabase(), $orderPk, $this->params);

            if ($claim === null) {
                return [
                    'success' => false,
                    'pending' => true,
                    'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                ];
            }

            $outcome = PayPalOrderStates::storeUnderClaim(
                $this->getDatabase(),
                $orderPk,
                $claim,
                $priorStatus,
                function () use ($orderTable, $orderPk, $baid, $transId, $paymentStatus, $confirmedStateId): bool {
                    // Reloaded under the row lock, so an edit made during the PayPal call stays.
                    if (!$orderTable->load($orderPk)) {
                        return false;
                    }

                    $details = PayPalOrderStates::details((string) $orderTable->transaction_details);

                    $details['billing_agreement_id'] = $baid;
                    $details['transaction_id']       = $transId;
                    $details['mode']                 = 'nvp_express';
                    $details['payment_status']       = $paymentStatus;
                    $details['completed_at']         = date('Y-m-d H:i:s');

                    $orderTable->orderpayment_type   = $this->_name;
                    $orderTable->transaction_id      = $transId;
                    $orderTable->transaction_status  = 'Completed';
                    $orderTable->transaction_details = json_encode($details);

                    $this->applyConfirmedState($orderTable, $confirmedStateId);

                    return TableSaveHelper::store($orderTable, 'paypal.nvp_payment_completed');
                }
            );

            if ($outcome !== 'stored') {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            try {
                OrderHistoryHelper::add(
                    orderId: (string) $orderTable->order_id,
                    comment: Text::sprintf(
                        'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                        Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                    ) . ' (NVP Express, BAID ' . $baid . ', TRANS ' . $transId . ')',
                    orderStateId: $confirmedStateId,
                );
            } catch (\Throwable $e) {
                $this->log(
                    'completeNvpExpressCheckoutForOrder: history write failed - ' . $e->getMessage(),
                    Log::ERROR
                );
            }

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

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
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
            [$status, $error, $extra] = $this->renewLegacyBaid($legacyBaid, $subscription, $order);
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
            // Same DB row and gatewayAmount() as the Orders API path, so both charge the identical figure.
            $orderTable   = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()->createTable('Order', 'Administrator');
            $source       = $orderTable->load(['order_id' => (string) ($order->order_id ?? '')]) ? $orderTable : $order;
            $amount       = CurrencyHelper::gatewayAmount($source);
            $currencyCode = $this->getCurrency($source);
            $description  = 'J2Commerce subscription renewal #' . ($subscription->id ?? '');
            $extra        = [];
            $names        = $this->getOrderItemNames((string) ($order->order_id ?? ''));

            if ($names !== '') {
                $suffix      = ' - renewal (subscription #' . ($subscription->id ?? '') . ')';
                $description = mb_strcut($names, 0, max(0, 127 - \strlen($suffix))) . $suffix;
                $formatted   = PayPalCurrencyHelper::format($amount, $currencyCode);
                // Single line equal to the total avoids NVP 10413 (item/tax sum mismatch).
                $extra       = [
                    'L_NAME0' => mb_strcut($names, 0, 127),
                    'L_QTY0'  => '1',
                    'L_AMT0'  => $formatted,
                    'ITEMAMT' => $formatted,
                ];
            }

            $response = $nvp->doReferenceTransaction(
                referenceId:    $baid,
                amount:         $amount,
                currencyCode:   $currencyCode,
                invoiceNumber:  ($source->invoice_prefix ?? '') . ($order->order_id ?? ''),
                description:    $description,
                extra:          $extra
            );
        } catch (\Throwable $e) {
            $this->log('NVP DoReferenceTransaction exception: ' . $e->getMessage(), Log::ERROR);
            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        if (PayPalNvpClient::isSuccess($response)) {
            $transId = (string) ($response['TRANSACTIONID'] ?? '');

            if (isset($response['AMT'])) {
                $this->flagRenewalAmountMismatch((string) ($order->order_id ?? ''), (float) $response['AMT'], (string) ($response['CURRENCYCODE'] ?? $currencyCode), $amount, $currencyCode);
            }

            $this->finalizeRenewalOrder(
                $order,
                $transId,
                (float) ($response['AMT'] ?? 0),
                (string) ($response['CURRENCYCODE'] ?? ''),
                [
                    'paymentinfo'    => $response['PAYMENTINFO_0_TRANSACTIONID'] ?? null,
                    'pending_reason' => $response['PENDINGREASON'] ?? null,
                    'reason_code'    => $response['REASONCODE'] ?? null,
                ],
                'renewal_nvp',
                'NVP DoReferenceTransaction'
            );
            return ['success', null, ['transaction_id' => $transId, 'gateway_response' => $response]];
        }

        $code = (string) ($response['L_ERRORCODE0'] ?? '');

        // Per legacy J2Store: 10412 = duplicate INVNUM. Treat as no_response so
        // the retry counter advances without flipping the sub to 'failed'.
        if ($code === '10412') {
            return ['no_response', PayPalNvpClient::getErrorMessage($response), ['gateway_response' => $response]];
        }

        // 10201 = agreement canceled (sandbox-proven), 11451/11452 = canceled/expired — terminal; 'failed'
        // is dispatched once and the subscription leaves the renewal queue (only no_response retries).
        $terminal = \in_array($code, ['10201', '11451', '11452'], true);

        return ['failed', PayPalNvpClient::getErrorMessage($response), ['gateway_response' => $response] + ($terminal ? ['agreement_canceled' => true] : [])];
    }

    /**
     * Orders v2 first (merchant-initiated capture on the BAID), NVP DoReferenceTransaction as the fallback.
     *
     * @return  array{0: string, 1: ?string, 2: array<string, mixed>}  [status, error, extra]
     */
    private function renewLegacyBaid(string $baid, object $subscription, object $order): array
    {
        $orderIdString = (string) ($order->order_id ?? '');

        // A renewal order that already holds a payment must never be charged again.
        try {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['j2commerce_order_id', 'transaction_id', 'transaction_status', 'order_state_id']))
                ->from($db->quoteName('#__j2commerce_orders'))
                ->where($db->quoteName('order_id') . ' = :orderId')
                ->bind(':orderId', $orderIdString)
                ->setLimit(1);
            $row = $db->setQuery($query)->loadObject();
        } catch (\Throwable $e) {
            $this->log('Renewal prior-state check failed: ' . $e->getMessage(), Log::ERROR);

            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        if ($row !== null && (string) $row->transaction_id !== '') {
            $confirmed = PayPalOrderStates::resolve($this->params, PayPalOrderStates::CONFIRMED);
            $txStatus  = strtoupper((string) $row->transaction_status);

            if (($confirmed > 0 && (int) $row->order_state_id === $confirmed) || \in_array($txStatus, ['COMPLETED', 'PENDING'], true)) {
                return ['success', null, ['transaction_id' => (string) $row->transaction_id] + ($txStatus === 'PENDING' ? ['pending' => true] : [])];
            }
        }

        // The ledger is written before the order is stored, so a failed store leaves an empty transaction_id but a charge row.
        if ($row !== null && (int) $row->j2commerce_order_id > 0) {
            try {
                foreach (OrderTransactionHelper::getCharges((int) $row->j2commerce_order_id) as $charge) {
                    if ($charge->type === 'DEBIT' && (string) $charge->gateway_txn_id !== '') {
                        return ['success', null, ['transaction_id' => (string) $charge->gateway_txn_id]];
                    }
                }
            } catch (\Throwable $e) {
                $this->log('Renewal ledger prior-state check failed: ' . $e->getMessage(), Log::ERROR);

                return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
            }
        }

        $outcome = $this->renewViaOrdersApi($baid, $order);

        return $outcome ?? $this->renewViaNvpReference($baid, $subscription, $order);
    }

    /** A paid amount or currency that differs from what was sent is logged and noted on the order; the money moved, so the order is still finalized. */
    private function flagRenewalAmountMismatch(string $orderIdString, float $paid, string $paidCurrency, float $expected, string $expectedCurrency): void
    {
        $paidCurrency = strtoupper($paidCurrency);

        if ($paidCurrency === strtoupper($expectedCurrency) && PayPalCurrencyHelper::sameAmount($paid, $expected, $expectedCurrency)) {
            return;
        }

        $captured = $paid . ' ' . $paidCurrency;
        $sent     = PayPalCurrencyHelper::format($expected, $expectedCurrency) . ' ' . strtoupper($expectedCurrency);

        $this->log('Renewal amount mismatch on order ' . $orderIdString . ': paid ' . $captured . ', sent ' . $sent . ' - manual review required', Log::ERROR);

        try {
            $orderTable = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()->createTable('Order', 'Administrator');

            if ($orderTable->load(['order_id' => $orderIdString])) {
                PayPalOrderStates::addHistoryOnce(
                    $this->getDatabase(),
                    $orderTable,
                    Text::sprintf('COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_MISMATCH', $captured, $sent)
                );
            }
        } catch (\Throwable $e) {
            $this->log('Renewal amount mismatch note failed for order ' . $orderIdString . ': ' . $e->getMessage(), Log::ERROR);
        }
    }

    /**
     * @return  array{0: string, 1: ?string, 2: array<string, mixed>}|null  null = let NVP try instead
     */
    private function renewViaOrdersApi(string $baid, object $order): ?array
    {
        $sandbox = (bool) (int) $this->params->get('sandbox', 0);
        $keys    = $sandbox ? ['sandbox_client_id', 'sandbox_client_secret'] : ['client_id', 'client_secret'];

        if ((string) $this->params->get($keys[0], '') === '' || (string) $this->params->get($keys[1], '') === '') {
            return null;
        }

        $nvpAvailable = $this->getPayPalNvp()->isConfigured();

        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            $orderIdString = (string) ($order->order_id ?? '');

            if (!$orderTable->load(['order_id' => $orderIdString])) {
                return null;
            }

            $currency = $this->getCurrency($orderTable);

            if (!PayPalCurrencyHelper::isValid($currency)) {
                $this->log('Orders API renewal skipped: invalid currency "' . $currency . '" on order ' . $orderIdString, Log::DEBUG);

                return null;
            }

            $payload = $this->buildOrderData($orderTable, $orderIdString, $currency);
            // Nothing has been sent yet, so an auth failure here is safe to hand to NVP.
            $this->getPayPalClient()->getAccessToken();
        } catch (\Throwable $e) {
            $this->log('Orders API renewal not attempted: ' . $e->getMessage(), Log::WARNING);

            return null;
        }

        try {
            $result = $this->getPayPalOrders()->chargeBillingAgreement($baid, $payload);
        } catch (\Throwable $e) {
            $this->log('Orders API BAID renewal exception: ' . $e->getMessage(), Log::ERROR);

            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        $status  = (int) $result['status'];
        $body    = $result['body'];
        $issue   = (string) ($body['details'][0]['issue'] ?? $body['name'] ?? '');
        $debugId = (string) ($body['debug_id'] ?? '');

        if ($status >= 200 && $status < 300) {
            $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? null;
            $capId   = (string) ($capture['id'] ?? '');
            $capStat = (string) ($capture['status'] ?? '');

            if ($capId === '') {
                $this->log('Orders API BAID renewal: no capture in response, order ' . $orderIdString . ' debug_id ' . $debugId, Log::ERROR);

                return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
            }

            if (\in_array($capStat, ['DECLINED', 'FAILED'], true)) {
                $this->log('Orders API BAID renewal capture ' . $capStat . ', order ' . $orderIdString . ' capture ' . $capId, Log::ERROR);

                return ['failed', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
            }

            $capAmount     = (float) ($capture['amount']['value'] ?? 0);
            $capCurrency   = (string) ($capture['amount']['currency_code'] ?? $currency);
            $pendingReason = (string) ($capture['status_details']['reason'] ?? '');
            $summary       = [
                'id'             => $body['id'] ?? null,
                'status'         => $body['status'] ?? null,
                'capture_id'     => $capId,
                'capture_status' => $capStat,
                'debug_id'       => $debugId ?: null,
            ];

            $this->flagRenewalAmountMismatch($orderIdString, $capAmount, $capCurrency, (float) $payload['total'], $currency);

            // Anything but a settled capture is held, never confirmed: the subscription advances on
            // 'success', but the order stays awaiting payment until the capture webhook settles or demotes it.
            if ($capStat !== 'COMPLETED' || ($body['status'] ?? '') !== 'COMPLETED') {
                $this->recordLedgerCharge($orderIdString, $capId, $capAmount, $capCurrency);

                $held = $this->recordUnsettledPayment(
                    $orderTable,
                    $capId,
                    $capStat,
                    ['paypal_order_id' => $body['id'] ?? null, 'capture_id' => $capId, 'pending_reason' => $pendingReason ?: null],
                    'capture_id'
                );

                if (empty($held['pending']) && empty($held['success'])) {
                    $this->log('Orders API BAID renewal capture ' . $capId . ' (' . $capStat . ') could not be held on order ' . $orderIdString, Log::ERROR);
                    PayPalOrderStates::addHistoryOnce(
                        $this->getDatabase(),
                        $orderTable,
                        Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_RENEWAL_CAPTURE_NOT_HELD', $capId)
                    );
                }

                return ['success', null, ['pending' => true, 'transaction_id' => $capId, 'gateway_response' => $summary]];
            }

            $this->finalizeRenewalOrder(
                $order,
                $capId,
                $capAmount,
                $capCurrency,
                ['paypal_order_id' => $body['id'] ?? null, 'pending_reason' => $pendingReason ?: null],
                'renewal_orders_api',
                'Orders API BAID capture'
            );

            return ['success', null, ['transaction_id' => $capId, 'gateway_response' => $summary]];
        }

        $this->log('Orders API BAID renewal HTTP ' . $status . ' issue ' . $issue . ' debug_id ' . $debugId . ' order ' . $orderIdString, Log::ERROR);

        if ($issue === 'AGREEMENT_ALREADY_CANCELLED') {
            return ['failed', 'PayPal billing agreement ' . $baid . ' was cancelled.', ['agreement_canceled' => true]];
        }

        if ($issue === 'DUPLICATE_INVOICE_ID') {
            $this->log('Renewal rejected as a duplicate invoice: a payment may already exist at PayPal for invoice ' . $payload['invoice_id'] . ' - reconcile manually', Log::ERROR);
            PayPalOrderStates::addHistoryOnce(
                $this->getDatabase(),
                $orderTable,
                Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_RENEWAL_DUPLICATE_INVOICE', $payload['invoice_id'])
            );

            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        // 0 / 3xx / 409 / 429 / 5xx and an in-flight duplicate request: PayPal may have charged, so NVP must not be tried.
        if ($status < 400 || $status === 409 || $status === 429 || $status >= 500 || str_contains($issue, 'PREVIOUS_REQUEST') || str_contains($issue, 'IN_PROGRESS')) {
            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        $declined = ['INSTRUMENT_DECLINED', 'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR', 'TRANSACTION_REFUSED', 'PAYER_ACTION_REQUIRED'];

        if (\in_array($issue, $declined, true)) {
            return ['failed', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), ['gateway_response' => ['issue' => $issue, 'debug_id' => $debugId ?: null]]];
        }

        // Any other rejection happened before a charge could exist, so NVP may try.
        return $nvpAvailable
            ? null
            : ['failed', 'PayPal rejected billing agreement ' . $baid . ' (' . ($issue ?: 'HTTP ' . $status) . ') and NVP credentials are not configured for a fallback.', []];
    }

    private function getOrderItemNames(string $orderIdString): string
    {
        if ($orderIdString === '') {
            return '';
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('orderitem_name'))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->order($db->quoteName('j2commerce_orderitem_id'))
            ->bind(':orderId', $orderIdString);

        return implode(', ', array_filter(array_map('trim', $db->setQuery($query)->loadColumn() ?: [])));
    }

    /** Ledger charge for a payment taken outside the Orders v2 capture, so the order view can refund it. Dedupes on the id. */
    private function recordLedgerCharge(string $orderIdString, string $txnId, float $amount, string $currency): void
    {
        if ($orderIdString === '' || $txnId === '' || $amount <= 0 || $currency === '') {
            return;
        }

        try {
            $db      = $this->getDatabase();
            $orderPk = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('j2commerce_order_id'))
                    ->from($db->quoteName('#__j2commerce_orders'))
                    ->where($db->quoteName('order_id') . ' = :orderId')
                    ->bind(':orderId', $orderIdString)
            )->loadResult();

            if ($orderPk > 0) {
                OrderTransactionHelper::addCharge($orderPk, $this->_name, $txnId, $amount, strtoupper($currency));
            }
        } catch (\Throwable $e) {
            $this->log('Ledger charge not recorded for order ' . $orderIdString . ': ' . $e->getMessage(), Log::ERROR);
        }
    }

    /** @param array<string, mixed> $details */
    private function finalizeRenewalOrder(object $order, string $transactionId, float $amount, string $currency, array $details, string $type, string $label): bool
    {
        try {
            $orderTable = Factory::getApplication()
                ->bootComponent('com_j2commerce')
                ->getMVCFactory()
                ->createTable('Order', 'Administrator');

            if (!$orderTable->load(['order_id' => (string) ($order->order_id ?? '')])) {
                $this->log('finalizeRenewalOrder: order ' . ($order->order_id ?? '') . ' not found for payment ' . $transactionId, Log::ERROR);

                return false;
            }

            $this->recordLedgerCharge((string) $orderTable->order_id, $transactionId, $amount, $currency);

            $orderTable->transaction_id      = $transactionId;
            $orderTable->transaction_status  = 'Completed';
            $orderTable->orderpayment_type   = $this->_name;

            $this->applyConfirmedState($orderTable, PayPalOrderStates::resolve($this->params, PayPalOrderStates::CONFIRMED));

            $orderTable->transaction_details = json_encode(['transaction_id' => $transactionId] + $details + [
                'type'         => $type,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            if (!TableSaveHelper::store($orderTable, 'paypal.' . $type)) {
                $this->log('finalizeRenewalOrder: order ' . $orderTable->order_id . ' not stored after payment ' . $transactionId, Log::ERROR);

                return false;
            }

            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: Text::sprintf(
                    'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                    Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                ) . ' (' . $label . ' ' . $transactionId . ')',
                orderStateId: (int) $orderTable->order_state_id,
            );

            return true;
        } catch (\Throwable $e) {
            $this->log('finalizeRenewalOrder exception for payment ' . $transactionId . ': ' . $e->getMessage(), Log::ERROR);

            return false;
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
            return ['no_response', Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'), []];
        }

        if (($outcome['status'] ?? '') === 'success') {
            $this->recordLedgerCharge(
                (string) ($order->order_id ?? ''),
                (string) ($outcome['transaction_id'] ?? ''),
                CurrencyHelper::convertForOrder((float) ($order->order_total ?? 0), $order),
                $this->getCurrency($order)
            );
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
                $existing1 = (int)$existing;
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__j2commerce_metafields'))
                        ->set($db->quoteName('metavalue') . ' = :val')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':val', $paypalSubId)
                        ->bind(':id', $existing1, ParameterType::INTEGER)
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

        // New subscriptions always use the Subscriptions API. Legacy NVP billing agreements
        // only renew (onProcessRenewalPayment routes them by their stored BAID).
        if ($this->isSubscriptionOrder($orderId)) {
            $this->log('onPaymentCreateOrder: subscription detected for order_id: ' . $orderId);

            $result = $this->createPayPalSubscriptionForOrder($data);
        } else {
            $this->log('onPaymentCreateOrder: Starting PayPal order creation for order_id: ' . ($data['order_id'] ?? 'N/A'));
            $result = $this->createPayPalOrder($data);
        }

        $event->setArgument('result', $result);
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

        // A subscription is recognised from the order's own items, not from a request field, and
        // each path checks the PayPal identifier it is handed against the one bound to the order.
        if ($nvpToken !== '') {
            $this->log('onPaymentCaptureOrder: completing NVP Express Checkout for order_id: ' . $orderId);
            $result = $this->completeNvpExpressCheckoutForOrder($orderId, $nvpToken, $nvpPayerId);
        } elseif ($this->isSubscriptionOrder($orderId)) {
            $this->log('onPaymentCaptureOrder: subscription detected for order_id: ' . $orderId . ' — finalizing via subscription approval');
            $result = $this->finalizePayPalSubscriptionApproval($orderId, $paypalSubscriptionId);
        } else {
            $result = $this->capturePayPalOrder($paypalOrderId, $orderId);
        }

        // A pending result means the buyer has paid and the payment settles later through a
        // webhook, which has no session: the cart is emptied now so it is not bought twice.
        if (!empty($result['pending']) && !CheckoutContextHelper::isOwningRequest()) {
            CartHelper::emptyCart($orderId);
        }

        $event->setArgument('result', $result);
    }

    /**
     * True when the cart for this order contains at least one subscription product
     * (a type registered with the subscription capability).
     */
    private function isSubscriptionOrder(string $orderIdString): bool
    {
        $types = ProductTypeHelper::typesWith(ProductTypeHelper::SUBSCRIPTION);

        if ($orderIdString === '' || $types === []) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :oid')
            ->where($db->quoteName('product_type') . ' IN (' . implode(',', $query->bindArray($types, ParameterType::STRING)) . ')')
            ->bind(':oid', $orderIdString);

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
        $types = ProductTypeHelper::typesWith(ProductTypeHelper::SUBSCRIPTION);

        if ($types === []) {
            return null;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select(['oi.product_id', 'oi.orderitem_finalprice', 'oi.orderitem_name', 'p.params'])
            ->from($db->quoteName('#__j2commerce_orderitems', 'oi'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_products', 'p')
                . ' ON ' . $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('oi.product_id')
            )
            ->where($db->quoteName('oi.order_id') . ' = :oid')
            ->where($db->quoteName('oi.product_type') . ' IN (' . implode(',', $query->bindArray($types, ParameterType::STRING)) . ')')
            ->bind(':oid', $orderIdString)
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

            if ($this->hasPendingPayment($orderTable)) {
                return [
                    'success' => false,
                    'pending' => true,
                    'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                ];
            }

            // The same entry rule the REST create and the NVP start apply.
            if (
                !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $this->params)
                || (float) ($orderTable->order_refund ?? 0) > 0
                || PayPalOrderStates::isSettledOrSettling((string) ($orderTable->transaction_status ?? ''))
            ) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $stub = $this->buildSubscriptionStubFromOrder($orderId);

            if ($stub === null) {
                $this->log(
                    'createPayPalSubscriptionForOrder: no subscription product in order ' . $orderId,
                    Log::ERROR
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
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
                $this->log('createPayPalSubscriptionForOrder: ' . ($created['error'] ?? 'create failed'), Log::ERROR);

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            $bound = $this->bindPayPalReference($orderTable, [
                'paypal_subscription_id' => $created['id'],
                'paypal_plan_id'         => $planId,
                'paypal_product_id'      => $productId,
                'subscription_status'    => $created['status'],
            ]);

            if (!$bound) {
                $this->log(
                    'createPayPalSubscriptionForOrder: order ' . $orderId . ' stopped awaiting payment during the call',
                    Log::ERROR
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

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

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
        }
    }

    /**
     * onApprove branch for subscription carts. PayPal already auto-charged the
     * first cycle as part of subscription activation — we just verify it went
     * ACTIVE and mark the local order paid. No Orders v2 capture call needed.
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

            // Re-assert the subscription was created for this local order — a body
            // subscription id is accepted only when it matches the one stored at
            // creation. A request-supplied id on an order that has none is rejected.
            $storedSubId = (string) ($details['paypal_subscription_id'] ?? '');

            if ($storedSubId === '') {
                $this->log('finalizePayPalSubscriptionApproval: no stored subscription id on order ' . $orderIdString, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            if ($paypalSubscriptionId === '') {
                $paypalSubscriptionId = $storedSubId;
            }

            if (!hash_equals($storedSubId, $paypalSubscriptionId)) {
                $this->log('finalizePayPalSubscriptionApproval: subscription id mismatch on order ' . $orderIdString, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            // Only an order still awaiting payment may be finalized. A settled, cancelled or
            // refunded order cannot be flipped.
            if (
                !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $this->params)
                || (float) ($orderTable->order_refund ?? 0) > 0
            ) {
                $this->log('finalizePayPalSubscriptionApproval: order not in a finalizable state - order_id: ' . $orderIdString . ', state: ' . $orderTable->order_state_id, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $info   = $this->getPayPalSubscriptions()->getSubscriptionDetails($paypalSubscriptionId);
            $status = strtoupper((string) ($info['body']['status'] ?? ''));

            // ACTIVE only: APPROVAL_PENDING means the buyer never approved (nothing
            // charged), and APPROVED means authorized but not yet billed. PayPal can
            // transiently report APPROVED for a few seconds right after a successful
            // approval — re-fetch once before rejecting so an honest buyer isn't
            // bounced into creating a duplicate subscription.
            if ($status === 'APPROVED') {
                sleep(2);
                $info   = $this->getPayPalSubscriptions()->getSubscriptionDetails($paypalSubscriptionId);
                $status = strtoupper((string) ($info['body']['status'] ?? ''));
            }

            if ($status !== 'ACTIVE') {
                $this->log(
                    'finalizePayPalSubscriptionApproval: unexpected PayPal sub status=' . $status . ' for ' . $paypalSubscriptionId,
                    Log::WARNING
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            // The plan was priced from this order when it was created, so the subscription must
            // still bill on that plan.
            $storedPlanId = (string) ($details['paypal_plan_id'] ?? '');
            $activePlanId = (string) ($info['body']['plan_id'] ?? '');

            if ($storedPlanId === '' || !hash_equals($storedPlanId, $activePlanId)) {
                $this->log('finalizePayPalSubscriptionApproval: plan mismatch on order ' . $orderIdString, Log::ERROR);

                // PayPal has billed the first cycle by now, so staff see it on the order too.
                PayPalOrderStates::addHistoryOnce(
                    $this->getDatabase(),
                    $orderTable,
                    Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_SUBSCRIPTION_PLAN_MISMATCH', $paypalSubscriptionId)
                );

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            // The first cycle's sale is the refundable payment (the I- subscription id is not), so it
            // goes on the ledger ahead of the claim, as the capture path does. PayPal bills it as part
            // of activation; a sale that has not posted yet is recorded by the sale webhook instead.
            foreach ($this->getPayPalSubscriptions()->listTransactions($paypalSubscriptionId) as $sale) {
                if (\in_array(strtoupper((string) ($sale['status'] ?? '')), ['COMPLETED', 'PENDING'], true)) {
                    $this->recordLedgerCharge(
                        $orderIdString,
                        (string) ($sale['id'] ?? ''),
                        (float) ($sale['amount_with_breakdown']['gross_amount']['value'] ?? 0),
                        (string) ($sale['amount_with_breakdown']['gross_amount']['currency_code'] ?? '')
                    );
                }
            }

            $confirmedStateId = PayPalOrderStates::resolve($this->params, PayPalOrderStates::CONFIRMED);
            $orderPk          = (int) $orderTable->j2commerce_order_id;
            $priorStatus      = (string) ($orderTable->transaction_status ?? '');

            // A double submit must not settle the order, or create its subscription, twice.
            $claim = PayPalOrderStates::claimSettlement($this->getDatabase(), $orderPk, $this->params);

            if ($claim === null) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $outcome = PayPalOrderStates::storeUnderClaim(
                $this->getDatabase(),
                $orderPk,
                $claim,
                $priorStatus,
                function () use ($orderTable, $orderPk, $paypalSubscriptionId, $status, $confirmedStateId): bool {
                    // Reloaded under the row lock, so an edit made during the PayPal call stays.
                    if (!$orderTable->load($orderPk)) {
                        return false;
                    }

                    $details = PayPalOrderStates::details((string) $orderTable->transaction_details);

                    $orderTable->orderpayment_type   = $this->_name;
                    $orderTable->transaction_id      = $paypalSubscriptionId;
                    $orderTable->transaction_status  = 'Completed';

                    $this->applyConfirmedState($orderTable, $confirmedStateId);

                    $details['paypal_subscription_id'] = $paypalSubscriptionId;
                    $details['subscription_status']    = $status;
                    $details['approved_at']            = date('Y-m-d H:i:s');

                    $orderTable->transaction_details = json_encode($details);

                    return TableSaveHelper::store($orderTable, 'paypal.subscription_approved');
                }
            );

            if ($outcome !== 'stored') {
                // A takeover now owns the claim and settles the payment the buyer has made.
                if ($outcome === 'lost') {
                    return [
                        'success' => false,
                        'pending' => true,
                        'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                    ];
                }

                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            try {
                OrderHistoryHelper::add(
                    orderId: (string) $orderTable->order_id,
                    comment: Text::sprintf(
                        'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                        Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                    ) . ' (subscription ' . $paypalSubscriptionId . ', status ' . $status . ')',
                    orderStateId: $confirmedStateId,
                );
            } catch (\Throwable $e) {
                $this->log(
                    'finalizePayPalSubscriptionApproval: history write failed - ' . $e->getMessage(),
                    Log::ERROR
                );
            }

            // This approval is reached from the Smart Buttons onApprove call, which never passes
            // through confirmPayment(): the subscription row and its PayPal link are written here.
            $this->dispatchAfterPayment($orderTable);

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
                'redirect'               => $this->payment->getReturnUrl(),
            ];
        } catch (\Throwable $e) {
            $this->log('finalizePayPalSubscriptionApproval exception: ' . $e->getMessage(), Log::ERROR);

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
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

        $vars->display_name         = J2htmlHelper::translateKey($this->params->get('display_name', 'PLG_J2COMMERCE_PAYMENT_PAYPAL'));
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

        $vars->is_subscription = $this->isSubscriptionOrder((string) $vars->order_id);

        $this->log('_prePayment: Prepared vars - order_id: ' . $vars->order_id . ', currency: ' . $vars->currency_code . ', amount: ' . $vars->orderpayment_amount . ', is_subscription: ' . ($vars->is_subscription ? '1' : '0'));

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
        // call and render the postpayment template. For the vault and NVP flows
        // CheckoutController then dispatches onJ2CommerceAfterPayment via the
        // no-paction branch; a Smart Buttons capture dispatched it itself. Without
        // this guard, a refresh on the return URL would re-call
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
            $this->log('_postPayment NVP return: order=' . $orderIdFromUrl);

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

            if ($this->hasPendingPayment($orderTable)) {
                return [
                    'success' => false,
                    'pending' => true,
                    'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                ];
            }

            // The same entry rule capturePayPalOrder() applies, so a new PayPal order cannot
            // replace the binding of an order that is no longer awaiting payment.
            if (
                !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $this->params)
                || (float) ($orderTable->order_refund ?? 0) > 0
                || PayPalOrderStates::isSettledOrSettling((string) ($orderTable->transaction_status ?? ''))
            ) {
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $currency = $this->getCurrency($orderTable);

            if (!PayPalCurrencyHelper::isValid($currency)) {
                $this->log('createPayPalOrder: Unsupported currency - ' . $currency, Log::ERROR);
                return [
                    'success' => false,
                    'error'   => Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_CURRENCY_NOT_SUPPORTED', $currency),
                ];
            }

            $orderData = $this->buildOrderData($orderTable, $orderId, $currency);

            $this->log('createPayPalOrder: Sending order data to PayPal API - order_id: ' . $orderId . ', total: ' . $orderData['total'] . ' ' . $currency . ', items: ' . \count($orderData['items']));

            $orders = $this->getPayPalOrders();
            $result = $orders->createOrder($orderData);

            $this->log('createPayPalOrder: PayPal API response - status: ' . $result['status']);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $paypalOrderId = $result['body']['id'] ?? '';

                if (!$this->bindPayPalReference($orderTable, ['paypal_order_id' => $paypalOrderId])) {
                    $this->log(
                        'createPayPalOrder: order ' . $orderId . ' stopped awaiting payment during the call',
                        Log::ERROR
                    );

                    return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
                }

                $this->log('createPayPalOrder: Success - paypal_order_id: ' . $paypalOrderId);
                return ['success' => true, 'paypal_order_id' => $paypalOrderId];
            }

            $errorMessage = $result['body']['message'] ?? 'PayPal order creation failed';
            $details      = $result['body']['details'] ?? [];
            $detailStr    = !empty($details) ? ' Details: ' . json_encode($details) : '';
            $this->log("createPayPalOrder: Failed (HTTP {$result['status']}) - $errorMessage$detailStr", Log::ERROR);

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
        } catch (\Exception $e) {
            $this->log('createPayPalOrder: Exception - ' . $e->getMessage(), Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                'PayPal order creation exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
        }
    }

    /** @return array<string, mixed> */
    private function buildOrderData(object $orderTable, string $orderId, string $currency): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['orderitem_name', 'orderitem_quantity', 'orderitem_finalprice', 'orderitem_sku']))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :order_id')
            ->bind(':order_id', $orderId);

        $db->setQuery($query);
        $orderItems = $db->loadObjectList();

        $items = [];

        foreach ($orderItems as $item) {
            $quantity = max(1, (int) $item->orderitem_quantity);

            // orderitem_finalprice is the line as order_subtotal counts it: option prices
            // included and any plugin line discount already taken off.
            $items[] = [
                'name'        => $item->orderitem_name,
                'quantity'    => $quantity,
                'unit_amount' => CurrencyHelper::convertForOrder((float) $item->orderitem_finalprice / $quantity, $orderTable),
                'sku'         => $item->orderitem_sku ?? '',
            ];
        }

        $shipping = CurrencyHelper::convertForOrder((float) $orderTable->order_shipping, $orderTable);

        if (ConfigHelper::pricesIncludeTax()) {
            // Prices are tax-inclusive: orderitem_price and order_shipping already contain
            // the tax. Sending order_tax / order_shipping_tax as a separate tax_total would
            // double-charge the customer. Set tax_total to zero so the breakdown is:
            // item_total (incl.) + shipping (incl.) + 0 = order_total.
            $tax = 0.0;
        } else {
            // Prices are tax-exclusive: add both product and shipping taxes explicitly.
            $tax = CurrencyHelper::convertForOrder((float) $orderTable->order_tax + (float) $orderTable->order_shipping_tax, $orderTable);
        }
        $discount = CurrencyHelper::convertForOrder((float) $orderTable->order_discount, $orderTable);
        // Fees and their tax are carried in order_surcharge, not order_tax.
        $handling = CurrencyHelper::convertForOrder((float) $orderTable->order_surcharge, $orderTable);
        $total    = CurrencyHelper::gatewayAmount($orderTable);

        return [
            'order_id'            => $orderId,
            'j2commerce_order_id' => $orderTable->j2commerce_order_id ?? $orderId,
            'invoice_id'          => $orderTable->invoice_prefix . $orderId,
            'currency_code'       => $currency,
            'total'               => $total,
            'shipping'            => $shipping,
            'tax'                 => $tax,
            'discount'            => $discount,
            'handling'            => $handling,
            'items'               => $items,
        ];
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

            // The PayPal order id must match the one stored on this local order.
            $storedDetails = json_decode($orderTable->transaction_details ?? '{}', true);
            $boundPayPalId = (string) ($storedDetails['paypal_order_id'] ?? '');

            if ($boundPayPalId === '' || !hash_equals($boundPayPalId, $paypalOrderId)) {
                $this->log('capturePayPalOrder: PayPal order id not bound to local order - order_id: ' . $orderId, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            if ($this->hasPendingPayment($orderTable)) {
                return [
                    'success' => false,
                    'pending' => true,
                    'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                ];
            }

            // Only an order still awaiting payment may be captured. A settled, cancelled or
            // refunded order cannot be flipped.
            if (
                !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $this->params)
                || (float) ($orderTable->order_refund ?? 0) > 0
            ) {
                $this->log('capturePayPalOrder: Order not in a capturable state - order_id: ' . $orderId . ', state: ' . $orderTable->order_state_id, Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
            }

            $this->log('capturePayPalOrder: Sending capture request to PayPal API');

            $orders = $this->getPayPalOrders();
            $result = $orders->captureOrder($paypalOrderId);

            $this->log('capturePayPalOrder: PayPal API response - status: ' . $result['status']);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                $capture = $result['body']['purchase_units'][0]['payments']['captures'][0] ?? null;

                if (!$capture) {
                    $this->log('capturePayPalOrder: No capture data in response', Log::ERROR);
                    return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
                }

                $captureId     = $capture['id'] ?? '';
                $captureStatus = $capture['status'] ?? '';

                // Compare the captured amount and currency against the local order before
                // writing any paid state — a mismatched capture is logged for manual review.
                $captureAmount   = (float) ($capture['amount']['value'] ?? 0);
                $captureCurrency = (string) ($capture['amount']['currency_code'] ?? '');
                $expectedCcy     = $this->getCurrency($orderTable);
                $expectedAmount  = (float) PayPalCurrencyHelper::format(CurrencyHelper::gatewayAmount($orderTable), $expectedCcy);

                if (
                    $captureCurrency !== $expectedCcy
                    || !PayPalCurrencyHelper::sameAmount($captureAmount, $expectedAmount, $expectedCcy)
                ) {
                    $this->log(
                        'capturePayPalOrder: Amount/currency mismatch - captured ' . $captureAmount . ' ' . $captureCurrency
                        . ', expected ' . $expectedAmount . ' ' . $expectedCcy . ' for order_id: ' . $orderId,
                        Log::ERROR
                    );
                    Factory::getApplication()->getLogger()->error(
                        'PayPal capture amount mismatch — manual review required',
                        ['category' => 'j2commerce.paypal', 'order_id' => $orderId, 'captured' => $captureAmount . ' ' . $captureCurrency, 'expected' => $expectedAmount . ' ' . $expectedCcy]
                    );

                    // Surface it on the order page too — the buyer's money was captured
                    // but the order stays unpaid until staff review.
                    OrderHistoryHelper::add(
                        orderId: (string) $orderTable->order_id,
                        comment: Text::sprintf(
                            'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_MISMATCH',
                            $captureAmount . ' ' . $captureCurrency,
                            $expectedAmount . ' ' . $expectedCcy
                        ),
                        orderStateId: (int) $orderTable->order_state_id
                    );

                    return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
                }

                // Only a COMPLETED capture has moved the money. A pending one (an eCheck, a held
                // payment) is settled by the capture webhooks, so the order stays awaiting payment:
                // a status change here would read as a finished payment to the confirm step.
                if ($captureStatus !== 'COMPLETED') {
                    return $this->recordUnsettledPayment($orderTable, $captureId, $captureStatus, [
                        'capture_id'       => $captureId,
                        'capture_status'   => $captureStatus,
                        'captured_at'      => date('Y-m-d H:i:s'),
                        'capture_response' => $result['body'],
                    ], 'capture_id');
                }

                // The capture webhook, or a second checkout request for the same PayPal order, may
                // settle this order too. Only the claim owner stores it and runs the follow-ups.
                $orderPk     = (int) $orderTable->j2commerce_order_id;
                $priorStatus = (string) ($orderTable->transaction_status ?? '');

                $this->log('capturePayPalOrder: Capture successful - capture_id: ' . $captureId . ', status: ' . $captureStatus);

                // Written before the claim and the store, so a retry after either fails still
                // dedupes on the capture id; the money has moved either way.
                if ($captureId !== '') {
                    OrderTransactionHelper::addCharge(
                        $orderPk,
                        $this->_name,
                        $captureId,
                        $captureAmount,
                        $captureCurrency
                    );
                }

                $claim = PayPalOrderStates::claimSettlement($this->getDatabase(), $orderPk, $this->params);

                if ($claim === null) {
                    return $orderTable->load($orderPk)
                        ? $this->settledElsewhere($orderTable, 'capture_id', true)
                        : ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_INVALID_REQUEST')];
                }

                // Any failure from here on hands the claim back: a claim left held would stop the
                // capture webhook from ever settling an order the buyer has paid for.
                $outcome = PayPalOrderStates::storeUnderClaim(
                    $this->getDatabase(),
                    $orderPk,
                    $claim,
                    $priorStatus,
                    function () use ($orderTable, $orderPk, $captureId, $captureStatus, $result): bool {
                        // Reloaded under the row lock, so an edit made during the PayPal call stays.
                        if (!$orderTable->load($orderPk)) {
                            return false;
                        }

                        $orderTable->transaction_id     = $captureId;
                        $orderTable->transaction_status = $captureStatus;

                        $this->applyConfirmedState(
                            $orderTable,
                            PayPalOrderStates::resolve($this->params, PayPalOrderStates::CONFIRMED)
                        );

                        $transactionDetails = PayPalOrderStates::details((string) $orderTable->transaction_details);

                        $transactionDetails['capture_id']       = $captureId;
                        $transactionDetails['capture_status']   = $captureStatus;
                        $transactionDetails['captured_at']      = date('Y-m-d H:i:s');
                        $transactionDetails['capture_response'] = $result['body'];
                        $orderTable->transaction_details        = json_encode($transactionDetails);

                        return (bool) $orderTable->store();
                    }
                );

                if ($outcome === 'stored') {
                    // The order is stored: nothing past this point may report the payment as failed.
                    try {
                        OrderHistoryHelper::add(
                            orderId: $orderTable->order_id,
                            comment: Text::sprintf(
                                'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_RECEIVED',
                                Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL')
                            ),
                            orderStateId: (int) $orderTable->order_state_id
                        );
                    } catch (\Throwable $e) {
                        $this->log('capturePayPalOrder: history write failed - ' . $e->getMessage(), Log::ERROR);
                    }

                    // The Smart Buttons flow lands on the confirmation view and never reaches
                    // confirmPayment(), which is what dispatches AfterPayment on every other path.
                    $this->dispatchAfterPayment($orderTable);

                    $this->log('capturePayPalOrder: Order updated successfully');
                    return [
                        'success'  => true,
                        'redirect' => $this->payment->getReturnUrl(),
                    ];
                }

                // A takeover now owns the claim and settles the payment the buyer has made.
                if ($outcome === 'lost') {
                    return [
                        'success' => false,
                        'pending' => true,
                        'error'   => Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
                    ];
                }

                $this->log('capturePayPalOrder: Failed to store order - ' . $orderTable->getError(), Log::ERROR);
                return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
            }

            $errorMessage = $result['body']['message'] ?? 'PayPal capture failed';
            $this->log("capturePayPalOrder: Capture failed - $errorMessage", Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                "PayPal capture failed: $errorMessage",
                [
                    'category' => 'j2commerce.paypal',
                    'name'     => $result['body']['name'] ?? '',
                    'debug_id' => $result['body']['debug_id'] ?? '',
                ]
            );

            // The checkout script restarts PayPal's own flow on a declined card, so the issue code
            // travels on its own field; the message stays a translated string.
            return [
                'success' => false,
                'error'   => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED'),
                'code'    => (string) ($result['body']['details'][0]['issue'] ?? ''),
            ];
        } catch (\Exception $e) {
            $this->log('capturePayPalOrder: Exception - ' . $e->getMessage(), Log::ERROR);
            Factory::getApplication()->getLogger()->error(
                'PayPal capture exception: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['success' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
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

            $this->paypalClient = new PayPalClient($clientId, $clientSecret, $sandbox, $this->isDebug());
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
            $sandbox              = (bool) $this->params->get('sandbox', 0);
            $webhookId            = $sandbox
                ? $this->params->get('sandbox_webhook_id', '')
                : $this->params->get('webhook_id', '');
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

        // The webhook is a server-to-server POST: it carries no Joomla session and no form
        // token, so it is answered before the session gates. Its authentication is PayPal's
        // own signature, verified inside the handler. The status is sent explicitly because
        // com_ajax sets one only for a Throwable, and PayPal retries anything not 2xx.
        if ($task === 'webhook') {
            $outcome = $this->handleWebhook();

            $app->setHeader('status', (int) $outcome['status'], true);
            $app->sendHeaders();

            echo json_encode(['message' => $outcome['message']]);
            $app->close();
        }

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
            $result = ['ok' => false, 'error' => Text::_('PLG_J2COMMERCE_PAYMENT_PAYPAL_PAYMENT_FAILED')];
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
        return PayPalCurrencyHelper::orderCurrency($order);
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
