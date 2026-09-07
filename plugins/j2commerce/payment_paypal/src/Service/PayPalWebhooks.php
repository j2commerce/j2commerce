<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_paypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\TableSaveHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

final class PayPalWebhooks
{
    /** Ledger of handled webhook event ids, kept in the generic metafields store. */
    private const EVENT_NAMESPACE = 'paypal';
    private const EVENT_RESOURCE  = 'paypal_webhook_events';
    private const EVENT_METAKEY   = 'webhook_event_id';

    public function __construct(
        private PayPalClient $client,
        private string $webhookId,
        private DatabaseInterface $db
    ) {
    }

    public function verifySignature(string $rawBody): bool
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_PAYPAL_')) {
                $headerName           = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        $verifyBody = [
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id'        => $this->webhookId,
            'webhook_event'     => json_decode($rawBody, true),
        ];

        $result = $this->client->request('POST', '/v1/notifications/verify-webhook-signature', $verifyBody);

        return ($result['body']['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * @return array{status: int, message: string}
     */
    public function handleEvent(string $rawBody, Registry $params): array
    {
        $event = json_decode($rawBody, true);
        if (!$event || !isset($event['event_type'])) {
            return ['status' => 400, 'message' => 'Invalid event payload'];
        }

        $eventId = $event['id'] ?? '';
        if ($this->isAlreadyProcessed($eventId)) {
            return ['status' => 200, 'message' => 'Event already processed'];
        }

        try {
            $result = match ($event['event_type']) {
                'PAYMENT.CAPTURE.COMPLETED'           => $this->handleCaptureCompleted($event, $params),
                'PAYMENT.CAPTURE.PENDING'             => $this->handleCapturePending($event, $params),
                'PAYMENT.CAPTURE.DENIED'              => $this->handleCaptureDenied($event, $params),
                'PAYMENT.CAPTURE.REFUNDED'            => $this->handleCaptureRefunded($event, $params),
                'PAYMENT.CAPTURE.REVERSED'            => $this->handleCaptureReversed($event, $params),
                'CUSTOMER.DISPUTE.CREATED'            => $this->handleDisputeCreated($event, $params),
                'CUSTOMER.DISPUTE.RESOLVED'           => $this->handleDisputeResolved($event, $params),
                'BILLING.SUBSCRIPTION.ACTIVATED'      => $this->handleSubscriptionActivated($event),
                'BILLING.SUBSCRIPTION.CANCELLED'      => $this->handleSubscriptionStatusChange($event, 'cancelled'),
                'BILLING.SUBSCRIPTION.SUSPENDED'      => $this->handleSubscriptionStatusChange($event, 'pending'),
                'BILLING.SUBSCRIPTION.EXPIRED'        => $this->handleSubscriptionStatusChange($event, 'expired'),
                'PAYMENT.SALE.COMPLETED'              => $this->handleSubscriptionRenewalSuccess($event),
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->handleSubscriptionRenewalFailed($event),
                default                               => ['status' => 200, 'message' => 'Event type not handled: ' . $event['event_type']],
            };

            if ($eventId !== '' && $result['status'] >= 200 && $result['status'] < 300) {
                // Recorded only on a 2xx: PayPal retries anything else, and a claim taken
                // before the handler ran would answer that retry "already processed".
                $this->recordProcessedEvent($eventId);
            }

            return $result;
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook handler error: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return ['status' => 500, 'message' => 'Internal error'];
        }
    }

    /**
     * Map a PayPal subscription id (I-XXX...) to a local J2Commerce subscription id
     * by reading the paypal_subscription_id metafield set at onJ2CommerceAfterPayment.
     */
    private function loadLocalSubscriptionByPayPalId(string $paypalSubscriptionId): ?\stdClass
    {
        if ($paypalSubscriptionId === '') {
            return null;
        }

        $metakey       = 'paypal_subscription_id';
        $namespace     = 'subscription';
        $ownerResource = 'subscriptions';

        $query = $this->db->getQuery(true)
            ->select('s.*')
            ->from($this->db->quoteName('#__j2commerce_metafields', 'm'))
            ->innerJoin(
                $this->db->quoteName('#__j2commerce_subscriptions', 's')
                . ' ON ' . $this->db->quoteName('s.id') . ' = ' . $this->db->quoteName('m.owner_id')
            )
            ->where($this->db->quoteName('m.metakey') . ' = :metakey')
            ->where($this->db->quoteName('m.namespace') . ' = :ns')
            ->where($this->db->quoteName('m.owner_resource') . ' = :res')
            ->where($this->db->quoteName('m.metavalue') . ' = :pps')
            ->bind(':metakey', $metakey)
            ->bind(':ns', $namespace)
            ->bind(':res', $ownerResource)
            ->bind(':pps', $paypalSubscriptionId)
            ->setLimit(1);

        $this->db->setQuery($query);

        return $this->db->loadObject() ?: null;
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleSubscriptionActivated(array $event): array
    {
        $paypalSubId  = (string) ($event['resource']['id'] ?? '');
        $subscription = $this->loadLocalSubscriptionByPayPalId($paypalSubId);

        if ($subscription === null) {
            return ['status' => 404, 'message' => 'Local subscription not found for ' . $paypalSubId];
        }

        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceChangeSubscriptionStatus',
            new Event('onJ2CommerceChangeSubscriptionStatus', [(int) $subscription->id, 'active', 1])
        );

        return ['status' => 200, 'message' => 'Subscription #' . $subscription->id . ' activated'];
    }

    /**
     * Handle CANCELLED, SUSPENDED, EXPIRED — direct status changes on the local sub.
     *
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleSubscriptionStatusChange(array $event, string $newStatus): array
    {
        $paypalSubId  = (string) ($event['resource']['id'] ?? '');
        $subscription = $this->loadLocalSubscriptionByPayPalId($paypalSubId);

        if ($subscription === null) {
            return ['status' => 404, 'message' => 'Local subscription not found for ' . $paypalSubId];
        }

        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceChangeSubscriptionStatus',
            new Event('onJ2CommerceChangeSubscriptionStatus', [(int) $subscription->id, $newStatus, 1])
        );

        return ['status' => 200, 'message' => 'Subscription #' . $subscription->id . ' → ' . $newStatus];
    }

    /**
     * Recurring sale completed — dispatch SuccessRenewalPayment so the local sub
     * advances its billing cycle and (optionally) creates a renewal order shell.
     *
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleSubscriptionRenewalSuccess(array $event): array
    {
        $resource    = $event['resource'] ?? [];
        $paypalSubId = (string) ($resource['billing_agreement_id'] ?? '');

        if ($paypalSubId === '') {
            // Not a recurring sale event (could be a regular Orders v2 capture echo) — ignore.
            return ['status' => 200, 'message' => 'Sale event not subscription-related'];
        }

        $subscription = $this->loadLocalSubscriptionByPayPalId($paypalSubId);

        if ($subscription === null) {
            return ['status' => 404, 'message' => 'Local subscription not found for ' . $paypalSubId];
        }

        // Compare the sale against the subscription before advancing the cycle, the way
        // handleCaptureCompleted() compares a capture against its order. Without this the
        // cycle advances for the local renewal amount whatever the sale actually was.
        $saleAmount   = (float) ($resource['amount']['total'] ?? 0);
        $saleCurrency = strtoupper(trim((string) ($resource['amount']['currency'] ?? '')));
        $parentOrder  = $this->loadSubscriptionParentOrder($subscription);

        // The expectation is the plan's fixed_price, which createBillingPlan() derives from
        // the parent order's total in the order's own currency -- not renewal_amount, which
        // is a different quantity and would reject every renewal carrying tax, shipping, a
        // discount, more than one unit, or a display currency other than the base one.
        $expectedCurrency = $parentOrder === null ? '' : $this->orderCurrency($parentOrder);
        $expectedAmount   = $parentOrder === null
            ? null
            : $this->roundToCurrency(
                CurrencyHelper::convertForOrder((float) ($parentOrder->order_total ?? 0), $parentOrder),
                $expectedCurrency
            );

        if (
            $expectedAmount === null
            || $expectedCurrency === ''
            || $saleCurrency !== $expectedCurrency
            || abs($this->roundToCurrency($saleAmount, $saleCurrency) - $expectedAmount) > 0.001
        ) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook renewal amount mismatch — manual review required',
                [
                    'category'     => 'j2commerce.paypal',
                    'subscription' => $subscription->id ?? '',
                    'received'     => $saleAmount . ' ' . $saleCurrency,
                    'expected'     => ($expectedAmount ?? 'unresolved') . ' ' . $expectedCurrency,
                ]
            );

            return ['status' => 409, 'message' => 'Amount/currency mismatch'];
        }

        // Build a minimal order-like object the renewal helper can consume.
        $renewalOrder = (object) [
            'order_id'       => (string) ($subscription->order_id ?? ''),
            'order_total'    => (float) $expectedAmount,
            'currency_code'  => $expectedCurrency,
            'transaction_id' => (string) ($resource['id'] ?? ''),
            'paypal_sale_id' => (string) ($resource['id'] ?? ''),
        ];

        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceSuccessRenewalPayment',
            new Event('onJ2CommerceSuccessRenewalPayment', [
                'subscription'      => $subscription,
                'order'             => $renewalOrder,
                'updateRenewalDate' => true,
            ])
        );

        return ['status' => 200, 'message' => 'Subscription #' . $subscription->id . ' renewal recorded'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleSubscriptionRenewalFailed(array $event): array
    {
        $resource    = $event['resource'] ?? [];
        $paypalSubId = (string) ($resource['id'] ?? '');

        $subscription = $this->loadLocalSubscriptionByPayPalId($paypalSubId);

        if ($subscription === null) {
            return ['status' => 404, 'message' => 'Local subscription not found for ' . $paypalSubId];
        }

        $renewalOrder = (object) [
            'order_id'      => (string) ($subscription->order_id ?? ''),
            'order_total'   => (float) ($subscription->renewal_amount ?? 0),
            'currency_code' => 'USD',
        ];

        Factory::getApplication()->getDispatcher()->dispatch(
            'onJ2CommerceFailedRenewalPayment',
            new Event('onJ2CommerceFailedRenewalPayment', [
                'subscription' => $subscription,
                'order'        => $renewalOrder,
            ])
        );

        return ['status' => 200, 'message' => 'Subscription #' . $subscription->id . ' renewal failure recorded'];
    }

    public function isAlreadyProcessed(string $eventId): bool
    {
        if ($eventId === '') {
            return false;
        }

        $namespace = self::EVENT_NAMESPACE;
        $resource  = self::EVENT_RESOURCE;
        $metakey   = self::EVENT_METAKEY;

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_metafields'))
            ->where($this->db->quoteName('namespace') . ' = :ns')
            ->where($this->db->quoteName('owner_resource') . ' = :res')
            ->where($this->db->quoteName('metakey') . ' = :metakey')
            ->where($this->db->quoteName('metavalue') . ' = :event_id')
            ->bind(':ns', $namespace)
            ->bind(':res', $resource)
            ->bind(':metakey', $metakey)
            ->bind(':event_id', $eventId);

        if ((int) $this->db->setQuery($query)->loadResult() > 0) {
            return true;
        }

        // Capture-completed also stamps the id onto the order itself, and did so before this
        // ledger existed, so an order still carrying it counts as handled.
        $likePattern = '%"webhook_event_id":"' . addcslashes($eventId, '%_\\') . '"%';

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where($this->db->quoteName('transaction_details') . ' LIKE :event_id')
            ->bind(':event_id', $likePattern);

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    /**
     * Ledger an event id so a redelivery is recognised whatever branch handled it. Only
     * handleCaptureCompleted() used to write one, which left every subscription branch
     * un-deduped: a redelivered renewal advanced the billing cycle a second time.
     */
    private function recordProcessedEvent(string $eventId): void
    {
        $namespace = self::EVENT_NAMESPACE;
        $resource  = self::EVENT_RESOURCE;
        $metakey   = self::EVENT_METAKEY;
        $valuetype = 'string';
        $scope     = '';
        $desc      = '';
        $now       = date('Y-m-d H:i:s');

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__j2commerce_metafields'))
            ->columns($this->db->quoteName([
                'metakey', 'namespace', 'scope', 'metavalue', 'valuetype',
                'description', 'owner_id', 'owner_resource', 'created_at', 'updated_at',
            ]))
            ->values(':metakey, :ns, :scope, :event_id, :vtype, :desc, 0, :res, :created, :updated')
            ->bind(':metakey', $metakey)
            ->bind(':ns', $namespace)
            ->bind(':scope', $scope)
            ->bind(':event_id', $eventId)
            ->bind(':vtype', $valuetype)
            ->bind(':desc', $desc)
            ->bind(':res', $resource)
            ->bind(':created', $now)
            ->bind(':updated', $now);

        $this->db->setQuery($query)->execute();

        $this->pruneProcessedEvents();
    }

    /**
     * One row per delivery, so the ledger is trimmed to a window comfortably wider than
     * PayPal's retry schedule. Anything older than that can no longer be redelivered.
     *
     * Run on a fraction of deliveries: created_at carries no index, so a sweep on every
     * callback would put a scan of the whole metafields table on the webhook's own request.
     */
    private function pruneProcessedEvents(): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        $namespace = self::EVENT_NAMESPACE;
        $resource  = self::EVENT_RESOURCE;
        $cutoff    = date('Y-m-d H:i:s', strtotime('-90 days'));

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_metafields'))
            ->where($this->db->quoteName('namespace') . ' = :ns')
            ->where($this->db->quoteName('owner_resource') . ' = :res')
            ->where($this->db->quoteName('created_at') . ' < :cutoff')
            ->bind(':ns', $namespace)
            ->bind(':res', $resource)
            ->bind(':cutoff', $cutoff);

        $this->db->setQuery($query)->execute();
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleCaptureCompleted(array $event, Registry $params): array
    {
        $resource  = $event['resource'] ?? [];
        $customId  = $resource['custom_id'] ?? '';
        $captureId = $resource['id'] ?? '';

        if (!$customId) {
            return ['status' => 400, 'message' => 'Missing custom_id'];
        }

        $order = $this->loadOrderByLocalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        if (!$orderTable->load(['order_id' => $order->order_id])) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        // Bind: the captured PayPal order id must match the one stored on this local order.
        $storedDetails = json_decode($orderTable->transaction_details ?? '{}', true);
        $boundPayPalId = (string) ($storedDetails['paypal_order_id'] ?? '');
        $eventOrderId  = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');

        if ($boundPayPalId === '' || $eventOrderId === '' || !hash_equals($boundPayPalId, $eventOrderId)) {
            return ['status' => 400, 'message' => 'PayPal order id not bound to local order'];
        }

        // Prior-state guard: only an order still awaiting payment may be captured; a settled,
        // cancelled or refunded one cannot be flipped. Matches capturePayPalOrder().
        if (
            !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $params, $this->db)
            || (float) ($orderTable->order_refund ?? 0) > 0
        ) {
            return ['status' => 409, 'message' => 'Order not in a capturable state'];
        }

        // Compare the captured amount and currency against the local order before
        // writing any paid state — a mismatched capture is logged for manual review.
        $captureAmount   = (float) ($resource['amount']['value'] ?? 0);
        $captureCurrency = (string) ($resource['amount']['currency_code'] ?? '');
        $expectedAmount  = CurrencyHelper::gatewayAmount($orderTable);
        $expectedCcy     = strtoupper(trim((string) ($orderTable->currency_code ?? 'USD')));

        if ($captureCurrency !== $expectedCcy || abs($captureAmount - $expectedAmount) > 0.01) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook capture amount mismatch — manual review required',
                [
                    'category' => 'j2commerce.paypal',
                    'order_id' => $order->order_id,
                    'captured' => $captureAmount . ' ' . $captureCurrency,
                    'expected' => $expectedAmount . ' ' . $expectedCcy,
                ]
            );

            return ['status' => 409, 'message' => 'Amount/currency mismatch'];
        }

        $confirmedStateId = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::CONFIRMED);

        // State change and transaction fields are written on the same table instance in one
        // store() so a second, independent load/store pair can't clobber either write.
        if ($confirmedStateId > 0) {
            $orderTable->order_state_id = $confirmedStateId;
        }

        $orderTable->transaction_id                 = $captureId;
        $orderTable->transaction_status             = 'COMPLETED';
        $transactionDetails                         = json_decode($orderTable->transaction_details ?? '{}', true);
        $transactionDetails['webhook_event_id']     = $event['id'] ?? '';
        $transactionDetails['capture_completed_at'] = date('Y-m-d H:i:s');
        $orderTable->transaction_details            = json_encode($transactionDetails);

        if (!TableSaveHelper::store($orderTable, 'paypal.webhook.capture_completed')) {
            return ['status' => 500, 'message' => 'Capture write failed'];
        }

        OrderHistoryHelper::add(
            orderId: (string) $orderTable->order_id,
            comment: Text::sprintf('COM_J2COMMERCE_PAYPAL_PAYMENT_COMPLETED', $captureId),
            orderStateId: (int) $orderTable->order_state_id,
        );

        return ['status' => 200, 'message' => 'Capture completed'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleCapturePending(array $event, Registry $params): array
    {
        $resource = $event['resource'] ?? [];
        $customId = $resource['custom_id'] ?? '';

        if (!$customId) {
            return ['status' => 400, 'message' => 'Missing custom_id'];
        }

        $order = $this->loadOrderByLocalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        // Same binding the completed handler applies. A capture resource always carries the
        // related order id, so an absent one is treated as a mismatch here.
        if ($this->matchesBinding($order, $resource) !== true) {
            return ['status' => 400, 'message' => 'PayPal order id not bound to local order'];
        }

        // A late or out-of-order pre-settlement event must not demote an order that has
        // already settled: store() fires the transition, which releases stock while the
        // download grant it issued stays live. A reversal or refund may still demote.
        if ($this->hasSettled($order, $params)) {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

        $pendingStateId = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::PENDING);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if ($pendingStateId > 0 && (int) $order->order_state_id === $pendingStateId) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $this->updateOrderStatus(
            $order,
            $pendingStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING')
        );

        return ['status' => 200, 'message' => 'Capture pending'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleCaptureDenied(array $event, Registry $params): array
    {
        $resource = $event['resource'] ?? [];
        $customId = $resource['custom_id'] ?? '';

        if (!$customId) {
            return ['status' => 400, 'message' => 'Missing custom_id'];
        }

        $order = $this->loadOrderByLocalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        // Same binding the completed handler applies. A capture resource always carries the
        // related order id, so an absent one is treated as a mismatch here.
        if ($this->matchesBinding($order, $resource) !== true) {
            return ['status' => 400, 'message' => 'PayPal order id not bound to local order'];
        }

        // A late or out-of-order pre-settlement event must not demote an order that has
        // already settled: store() fires the transition, which releases stock while the
        // download grant it issued stays live. A reversal or refund may still demote.
        if ($this->hasSettled($order, $params)) {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

        $failedStateId = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::FAILED);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if ($failedStateId > 0 && (int) $order->order_state_id === $failedStateId) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $this->updateOrderStatus(
            $order,
            $failedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_DENIED')
        );

        return ['status' => 200, 'message' => 'Capture denied'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleCaptureRefunded(array $event, Registry $params): array
    {
        $resource = $event['resource'] ?? [];
        $customId = $resource['custom_id'] ?? '';

        if (!$customId) {
            return ['status' => 400, 'message' => 'Missing custom_id'];
        }

        $order = $this->loadOrderByLocalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        // A refund or reversal revokes value already delivered, so it is discarded only on a
        // positive mismatch. An event carrying neither identifier is still acted on -- the
        // custom_id already resolved it, and dropping it would leave the order reading paid.
        if ($this->matchesBinding($order, $resource) === false) {
            return ['status' => 400, 'message' => 'PayPal order id not bound to local order'];
        }

        // A partial refund is not a refunded order: the status moves only when the whole
        // charge has come back.
        $refundAmount  = $this->roundToCurrency((float) ($resource['amount']['value'] ?? 0), $this->orderCurrency($order));
        $chargedAmount = $this->roundToCurrency(CurrencyHelper::gatewayAmount($order), $this->orderCurrency($order));

        if ($refundAmount <= 0) {
            return ['status' => 400, 'message' => 'Refund amount missing'];
        }

        // order_refund is deliberately not written here: it is a base-currency column owned by
        // OrderTransactionHelper, so a display-currency amount written straight into it would
        // be wrong by currency_value and would bypass the reversal ledger.
        $isFullyRefunded = $chargedAmount > 0 && $refundAmount + 0.001 >= $chargedAmount;
        $refundedStateId = $isFullyRefunded
            ? PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::REFUNDED)
            : 0;

        if ($refundedStateId > 0 && (int) $order->order_state_id === $refundedStateId) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $this->updateOrderStatus(
            $order,
            $refundedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_REFUNDED')
        );

        return ['status' => 200, 'message' => $isFullyRefunded ? 'Capture refunded' : 'Partial refund, order status unchanged'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleCaptureReversed(array $event, Registry $params): array
    {
        $resource = $event['resource'] ?? [];
        $customId = $resource['custom_id'] ?? '';

        if (!$customId) {
            return ['status' => 400, 'message' => 'Missing custom_id'];
        }

        $order = $this->loadOrderByLocalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        // A refund or reversal revokes value already delivered, so it is discarded only on a
        // positive mismatch. An event carrying neither identifier is still acted on -- the
        // custom_id already resolved it, and dropping it would leave the order reading paid.
        if ($this->matchesBinding($order, $resource) === false) {
            return ['status' => 400, 'message' => 'PayPal order id not bound to local order'];
        }

        $failedStateId = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::FAILED);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if ($failedStateId > 0 && (int) $order->order_state_id === $failedStateId) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $this->updateOrderStatus(
            $order,
            $failedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_REVERSED') . ' - FLAG FOR REVIEW'
        );

        return ['status' => 200, 'message' => 'Capture reversed'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleDisputeCreated(array $event, Registry $params): array
    {
        $resource  = $event['resource'] ?? [];
        $disputeId = $resource['dispute_id'] ?? '';

        Factory::getApplication()->getLogger()->warning(
            "PayPal dispute created: $disputeId",
            ['category' => 'j2commerce.paypal']
        );

        return ['status' => 200, 'message' => 'Dispute logged'];
    }

    /**
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleDisputeResolved(array $event, Registry $params): array
    {
        $resource  = $event['resource'] ?? [];
        $disputeId = $resource['dispute_id'] ?? '';

        Factory::getApplication()->getLogger()->info(
            "PayPal dispute resolved: $disputeId",
            ['category' => 'j2commerce.paypal']
        );

        return ['status' => 200, 'message' => 'Dispute resolved logged'];
    }

    /**
     * Has this order already reached a settled outcome? Resolved by type, never by a literal
     * id -- PayPalOrderStates is explicit that ids are install-dependent.
     */
    private function hasSettled(\stdClass $order, Registry $params): bool
    {
        $current   = (int) $order->order_state_id;
        $confirmed = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::CONFIRMED);
        $refunded  = PayPalOrderStates::resolve($params, $this->db, PayPalOrderStates::REFUNDED);

        return ($confirmed > 0 && $current === $confirmed)
            || ($refunded > 0 && $current === $refunded)
            || (float) ($order->order_refund ?? 0) > 0;
    }

    /**
     * PayPal is billed the plan's formatted price, so the expectation is rounded the same way
     * before comparison. A zero-decimal currency rounds to whole units, and comparing an
     * unrounded product against that differs by up to 0.5 -- fifty times the old tolerance.
     */
    private function roundToCurrency(float $amount, string $currency): float
    {
        $zeroDecimal = ['JPY', 'KRW', 'TWD', 'HUF', 'CLP', 'ISK'];

        $decimals = \in_array(strtoupper($currency), $zeroDecimal, true) ? 0 : 2;

        // number_format, not round: this is the exact call the plan price is built with, so
        // the two cannot disagree on a tie.
        return (float) number_format($amount, $decimals, '.', '');
    }

    /**
     * Mirrors the fallback the plugin prices a plan with: currency_code is NOT NULL but may
     * be empty on a migrated row, and the plan would have been priced in USD in that case.
     */
    private function orderCurrency(\stdClass $order): string
    {
        $currency = (string) ($order->currency_code ?? '');

        return strtoupper(trim($currency)) ?: 'USD';
    }

    /**
     * The subscription carries neither the billed amount nor a currency, so the parent order
     * it was created from is authoritative for both.
     */
    private function loadSubscriptionParentOrder(\stdClass $subscription): ?\stdClass
    {
        $parentOrderId = (string) ($subscription->order_id ?? '');

        if ($parentOrderId === '') {
            return null;
        }

        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where($this->db->quoteName('order_id') . ' = :parent_order_id')
            ->bind(':parent_order_id', $parentOrderId)
            ->setLimit(1);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    /**
     * Resolves the order from the event's custom_id, which PayPalOrders sets to the local
     * primary key. It is neither the PayPal order id held in transaction_details nor the
     * generated order_id string, so it is matched against the key it actually holds.
     */
    private function loadOrderByLocalId(string $customId): ?\stdClass
    {
        if (!ctype_digit($customId)) {
            return null;
        }

        $orderPk = (int) $customId;

        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where($this->db->quoteName('j2commerce_order_id') . ' = :order_pk')
            ->bind(':order_pk', $orderPk, ParameterType::INTEGER)
            ->setLimit(1);

        $this->db->setQuery($query);
        return $this->db->loadObject();
    }

    /**
     * Does the event name the gateway identifiers this local order was bound to?
     *
     * Returns null when the event carries neither identifier. A capture resource always
     * carries the related order id, but a refund or reversal resource is a different shape,
     * so the callers decide what an absent identifier means rather than this method
     * assuming a mismatch and discarding an event that revokes delivered value.
     *
     * @param array<string, mixed> $resource
     */
    private function matchesBinding(\stdClass $order, array $resource): ?bool
    {
        $storedDetails = json_decode($order->transaction_details ?? '{}', true);
        $storedDetails = \is_array($storedDetails) ? $storedDetails : [];
        $relatedIds    = $resource['supplementary_data']['related_ids'] ?? [];

        $eventOrderId   = (string) ($relatedIds['order_id'] ?? '');
        $eventCaptureId = (string) ($relatedIds['capture_id'] ?? '');

        $boundPayPalId = (string) ($storedDetails['paypal_order_id'] ?? '');
        $boundCapture  = (string) ($order->transaction_id ?? '');

        if ($eventOrderId !== '' && $boundPayPalId !== '') {
            return hash_equals($boundPayPalId, $eventOrderId);
        }

        if ($eventCaptureId !== '' && $boundCapture !== '') {
            return hash_equals($boundCapture, $eventCaptureId);
        }

        return null;
    }

    private function updateOrderStatus(\stdClass $order, int $newStateId, string $comment): void
    {
        if ($newStateId <= 0) {
            return;
        }

        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        // An unloaded table is treated as new by store(), which would insert a placeholder
        // order rather than update this one.
        if (!$orderTable->load(['order_id' => $order->order_id])) {
            return;
        }
        $orderTable->order_state_id = $newStateId;

        if (!TableSaveHelper::store($orderTable, 'paypal.webhook.order_status')) {
            return;
        }

        OrderHistoryHelper::add(
            orderId: (string) $order->order_id,
            comment: $comment,
            orderStateId: $newStateId,
        );
    }
}
