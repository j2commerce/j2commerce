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

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\TableSaveHelper;
use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Helper\PayPalCurrencyHelper;
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
                'BILLING.SUBSCRIPTION.CANCELLED'      => $this->handleSubscriptionStatusChange($event, 'canceled'),
                'BILLING.SUBSCRIPTION.SUSPENDED'      => $this->handleSubscriptionStatusChange($event, 'pending'),
                'BILLING.SUBSCRIPTION.EXPIRED'        => $this->handleSubscriptionStatusChange($event, 'expired'),
                'PAYMENT.SALE.COMPLETED'              => $this->handleSubscriptionRenewalSuccess($event),
                'PAYMENT.SALE.REFUNDED'               => $this->handleSaleRefunded($event, $params),
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
            || !PayPalCurrencyHelper::sameAmount($saleAmount, $expectedAmount, $saleCurrency)
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

        // The first cycle's sale is also announced here. It belongs to the checkout order and is no
        // renewal: approval already recorded it (or does so now when it posted late), and advancing the
        // cycle for it would bill-date the subscription one period ahead.
        $saleId   = (string) ($resource['id'] ?? '');
        $parentPk = (int) ($parentOrder->j2commerce_order_id ?? 0);
        $charges  = array_column(OrderTransactionHelper::getCharges($parentPk), 'gateway_txn_id');

        if ($saleId !== '' && \in_array($saleId, $charges, true)) {
            return ['status' => 200, 'message' => 'First-cycle sale already recorded'];
        }

        // Both sides UTC: orders.created_on is stored in UTC and create_time is ISO 8601.
        $orderedAt = strtotime((string) ($parentOrder->created_on ?? '') . ' UTC') ?: 0;
        $saleAt    = strtotime((string) ($resource['create_time'] ?? '')) ?: 0;

        if ($saleId !== '' && $charges === []) {
            // Most first sales post within minutes. A later (or redelivered) one is the first cycle
            // when PayPal lists no other sale on the subscription before it.
            $isFirstCycle = $orderedAt > 0 && $saleAt > 0 && abs($saleAt - $orderedAt) < 86400;

            if (!$isFirstCycle && $orderedAt > 0 && $saleAt > $orderedAt) {
                $list = $this->client->request(
                    'GET',
                    '/v1/billing/subscriptions/' . rawurlencode($paypalSubId) . '/transactions?start_time='
                    . rawurlencode(gmdate('Y-m-d\TH:i:s\Z', $orderedAt - 86400)) . '&end_time=' . rawurlencode(gmdate('Y-m-d\TH:i:s\Z', $saleAt + 60))
                );

                if ((int) ($list['status'] ?? 0) !== 200) {
                    return ['status' => 503, 'message' => 'Subscription transactions unavailable'];
                }

                $earlier = array_filter(
                    (array) ($list['body']['transactions'] ?? []),
                    static fn ($t) => ($t['id'] ?? '') !== $saleId && (strtotime((string) ($t['time'] ?? '')) ?: PHP_INT_MAX) < $saleAt
                );
                $isFirstCycle = $earlier === [];
            }

            if ($isFirstCycle) {
                OrderTransactionHelper::addCharge($parentPk, 'payment_paypal', $saleId, $saleAmount, $saleCurrency);

                return ['status' => 200, 'message' => 'First-cycle sale recorded on the checkout order'];
            }
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

        // The capture itself must say so too, not only the event type. Acknowledged without action,
        // since redelivering the same event cannot change its status.
        if (strtoupper((string) ($resource['status'] ?? '')) !== 'COMPLETED') {
            return ['status' => 200, 'message' => 'Capture not completed; ignored'];
        }

        // Prior-state guard: only an order still awaiting payment may be captured; a settled,
        // cancelled or refunded one cannot be flipped. Matches capturePayPalOrder().
        if (
            !PayPalOrderStates::isAwaitingPayment((int) $orderTable->order_state_id, $params)
            || (float) ($orderTable->order_refund ?? 0) > 0
        ) {
            if (!PayPalOrderStates::isSettledOrSettling((string) ($orderTable->transaction_status ?? ''))) {
                $this->noteCaptureOnClosedOrder($orderTable, $captureId);
            }

            return ['status' => 409, 'message' => 'Order not in a capturable state'];
        }

        // Compare the captured amount and currency against the local order before
        // writing any paid state — a mismatched capture is logged for manual review.
        $captureAmount   = (float) ($resource['amount']['value'] ?? 0);
        $captureCurrency = (string) ($resource['amount']['currency_code'] ?? '');
        $expectedCcy     = $this->orderCurrency($orderTable);
        $expectedAmount  = $this->roundToCurrency(CurrencyHelper::gatewayAmount($orderTable), $expectedCcy);

        if (
            $captureCurrency !== $expectedCcy
            || !PayPalCurrencyHelper::sameAmount($captureAmount, $expectedAmount, $expectedCcy)
        ) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook capture amount mismatch — manual review required',
                [
                    'category' => 'j2commerce.paypal',
                    'order_id' => $order->order_id,
                    'captured' => $captureAmount . ' ' . $captureCurrency,
                    'expected' => $expectedAmount . ' ' . $expectedCcy,
                ]
            );

            PayPalOrderStates::addHistoryOnce($this->db, $orderTable, Text::sprintf(
                'COM_J2COMMERCE_ORDER_HISTORY_PAYMENT_MISMATCH',
                $captureAmount . ' ' . $captureCurrency,
                $expectedAmount . ' ' . $expectedCcy
            ));

            return ['status' => 409, 'message' => 'Amount/currency mismatch'];
        }

        // Ahead of the store: a retry after a failed store passes the guard again and dedupes
        // on the capture id, where a retry after the store would stop at the 409 above.
        if ($captureId !== '') {
            OrderTransactionHelper::addCharge(
                (int) $orderTable->j2commerce_order_id,
                'payment_paypal',
                $captureId,
                $captureAmount,
                $captureCurrency
            );
        }

        $confirmedStateId = PayPalOrderStates::resolve($params, PayPalOrderStates::CONFIRMED);

        // The checkout capture may settle this order too. Only the claim owner stores it and runs
        // AfterPayment and the emails; that includes a held payment, and a checkout response the
        // buyer never received.
        $orderPk     = (int) $orderTable->j2commerce_order_id;
        $priorStatus = (string) ($orderTable->transaction_status ?? '');

        $claim = PayPalOrderStates::claimSettlement($this->db, $orderPk, $params);

        if ($claim === null) {
            // The claim is held but the owner has not stored the order yet (or died before it
            // could): ask PayPal to deliver this event again.
            if ($orderTable->load($orderPk) && PayPalOrderStates::isSettlementStored($orderTable, $params)) {
                if (!PayPalOrderStates::isSettledOrSettling((string) ($orderTable->transaction_status ?? ''))) {
                    $this->noteCaptureOnClosedOrder($orderTable, $captureId);
                }

                return ['status' => 200, 'message' => 'Order already settled'];
            }

            Factory::getApplication()->getLogger()->error(
                'PayPal capture settlement claimed but not stored — staff review if this repeats',
                ['category' => 'j2commerce.paypal', 'order_id' => $orderTable->order_id ?? $orderPk]
            );

            return ['status' => 503, 'message' => 'Settlement in progress'];
        }

        // Reloaded under the row lock, so an edit made since the first load is not written back;
        // state and transaction fields then go out in one store().
        $outcome = PayPalOrderStates::storeUnderClaim(
            $this->db,
            $orderPk,
            $claim,
            $priorStatus,
            static function () use ($orderTable, $orderPk, $confirmedStateId, $captureId, $event): bool {
                if (!$orderTable->load($orderPk)) {
                    return false;
                }

                if ($confirmedStateId > 0) {
                    $orderTable->order_state_id = $confirmedStateId;
                }

                $details = PayPalOrderStates::details((string) $orderTable->transaction_details);

                $details['webhook_event_id']     = $event['id'] ?? '';
                $details['capture_completed_at'] = date('Y-m-d H:i:s');

                $orderTable->transaction_id      = $captureId;
                $orderTable->transaction_status  = 'COMPLETED';
                $orderTable->transaction_details = json_encode($details);

                return TableSaveHelper::store($orderTable, 'paypal.webhook.capture_completed');
            }
        );

        // A takeover replaced a claim this delivery held too long: the new owner settles it.
        if ($outcome === 'lost') {
            return ['status' => 503, 'message' => 'Settlement taken over'];
        }

        // The claim was handed back, so PayPal's redelivery can settle the order.
        if ($outcome === 'failed') {
            return ['status' => 500, 'message' => 'Capture write failed'];
        }

        // The order is stored: nothing past this point may stop AfterPayment and the emails.
        try {
            OrderHistoryHelper::add(
                orderId: (string) $orderTable->order_id,
                comment: Text::sprintf('COM_J2COMMERCE_PAYPAL_PAYMENT_COMPLETED', $captureId),
                orderStateId: (int) $orderTable->order_state_id,
            );
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook history write failed: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal', 'order_id' => $orderTable->order_id]
            );
        }

        try {
            J2CommerceHelper::plugin()->eventWithArray('AfterPayment', [$orderTable]);
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook AfterPayment listener failed: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal', 'order_id' => $orderTable->order_id]
            );
        }

        $this->sendOrderEmails((string) $orderTable->order_id, true);

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

        $pendingStateId = PayPalOrderStates::resolve($params, PayPalOrderStates::PENDING);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if (
            $pendingStateId > 0
            && (int) $order->order_state_id === $pendingStateId
            && strtoupper((string) ($order->transaction_status ?? '')) === 'PENDING'
        ) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        // Recorded as held even when the checkout never saved its own result, so no second
        // PayPal order can be started for it.
        $write = $this->updateOrderStatus(
            $order,
            $pendingStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_PENDING'),
            'PENDING',
            (string) ($resource['id'] ?? ''),
            true
        );

        if ($write === 'failed') {
            return ['status' => 500, 'message' => 'Order update failed'];
        }

        if ($write === 'skipped') {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

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

        $failedStateId = PayPalOrderStates::resolve($params, PayPalOrderStates::FAILED);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if (
            $failedStateId > 0
            && (int) $order->order_state_id === $failedStateId
            && strtoupper((string) ($order->transaction_status ?? '')) === 'DENIED'
        ) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        // The checkout told this buyer the payment was pending and emptied the cart, so the
        // decline is the first news they get of it.
        $wasHeld = strtoupper((string) ($order->transaction_status ?? '')) === 'PENDING';

        $write = $this->updateOrderStatus(
            $order,
            $failedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_DENIED'),
            'DENIED',
            '',
            true
        );

        if ($write === 'failed') {
            return ['status' => 500, 'message' => 'Order update failed'];
        }

        if ($write === 'skipped') {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

        if ($wasHeld) {
            $this->sendOrderEmails((string) $order->order_id, false);
        }

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

        return $this->applyRefund($order, $resource, $params);
    }

    /**
     * A refund of a subscription sale, made in PayPal or by the order view. The resource is a v1
     * refund (sale_id, amount.total); its id is the refund id the order view already ledgered, so
     * that case dedupes.
     *
     * @param array<string, mixed> $event
     * @return array{status: int, message: string}
     */
    private function handleSaleRefunded(array $event, Registry $params): array
    {
        $resource = $event['resource'] ?? [];
        $saleId   = (string) ($resource['sale_id'] ?? '');
        $order    = $saleId === '' ? null : $this->loadOrderBySaleId($saleId);

        if ($order === null) {
            // Not a sale this store charged (e.g. an Orders v2 capture, handled by CAPTURE.REFUNDED).
            return ['status' => 200, 'message' => 'Sale not linked to a local order'];
        }

        return $this->applyRefund($order, [
            'id'     => (string) ($resource['id'] ?? ''),
            'amount' => [
                'value'         => abs((float) ($resource['amount']['total'] ?? 0)),
                'currency_code' => (string) ($resource['amount']['currency'] ?? ''),
            ],
            'supplementary_data' => ['related_ids' => ['capture_id' => $saleId]],
        ], $params);
    }

    /** The order whose ledger charged this sale, else the one whose transaction id it is. */
    private function loadOrderBySaleId(string $saleId): ?\stdClass
    {
        $plugin = 'payment_paypal';
        $type   = 'DEBIT';
        $query  = $this->db->getQuery(true)
            ->select($this->db->quoteName('order_id'))
            ->from($this->db->quoteName('#__j2commerce_ordertransactions'))
            ->where($this->db->quoteName('gateway_txn_id') . ' = :sale')
            ->where($this->db->quoteName('plugin') . ' = :plugin')
            ->where($this->db->quoteName('type') . ' = :type')
            ->bind(':sale', $saleId)
            ->bind(':plugin', $plugin)
            ->bind(':type', $type)
            ->setLimit(1);
        $orderPk = (int) $this->db->setQuery($query)->loadResult();

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->setLimit(1);

        if ($orderPk > 0) {
            $query->where($this->db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $orderPk, ParameterType::INTEGER);
        } else {
            $query->where($this->db->quoteName('transaction_id') . ' = :sale')
                ->where($this->db->quoteName('orderpayment_type') . ' = :plugin')
                ->bind(':sale', $saleId)
                ->bind(':plugin', $plugin);
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param array<string, mixed> $resource
     * @return array{status: int, message: string}
     */
    private function applyRefund(\stdClass $order, array $resource, Registry $params): array
    {
        $currency = $this->orderCurrency($order);

        if (!$this->matchesOrderCurrency($order, $resource, $currency)) {
            return ['status' => 409, 'message' => 'Currency mismatch'];
        }

        $refundAmount  = $this->roundToCurrency((float) ($resource['amount']['value'] ?? 0), $currency);
        $chargedAmount = CurrencyHelper::gatewayAmount($order);

        if ($refundAmount <= 0) {
            return ['status' => 400, 'message' => 'Refund amount missing'];
        }

        // order_refund is not written directly: it is a base-currency column that addReversal()
        // re-syncs from the ledger.
        $recorded = $this->recordReversal($order, $resource, $refundAmount);

        // A partial refund is not a refunded order: the status moves only when the whole
        // charge has come back, counting earlier partial refunds from the ledger.
        $orderPk         = (int) $order->j2commerce_order_id;
        $refundedTotal   = OrderTransactionHelper::hasLedger($orderPk)
            ? OrderTransactionHelper::getRefunded($orderPk) + ($recorded ? 0.0 : $refundAmount)
            : $refundAmount;
        $isFullyRefunded = $chargedAmount > 0
            && PayPalCurrencyHelper::covers($refundedTotal, $chargedAmount, $currency);
        $refundedStateId = $isFullyRefunded
            ? PayPalOrderStates::resolve($params, PayPalOrderStates::REFUNDED)
            : 0;

        if ($refundedStateId > 0 && (int) $order->order_state_id === $refundedStateId) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $write = $this->updateOrderStatus(
            $order,
            $refundedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_REFUNDED'),
            $refundedStateId > 0 ? 'REFUNDED' : ''
        );

        if ($write === 'failed') {
            return ['status' => 500, 'message' => 'Order update failed'];
        }

        if ($write === 'skipped') {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

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

        $currency = $this->orderCurrency($order);

        if (!$this->matchesOrderCurrency($order, $resource, $currency)) {
            return ['status' => 409, 'message' => 'Currency mismatch'];
        }

        $this->recordReversal(
            $order,
            $resource,
            $this->roundToCurrency((float) ($resource['amount']['value'] ?? 0), $currency)
        );

        $failedStateId = PayPalOrderStates::resolve($params, PayPalOrderStates::FAILED);

        // A redelivered or out-of-order event must not rewrite a status the order already
        // holds: OrderTable::store() moves stock and grants downloads on every transition.
        if (
            $failedStateId > 0
            && (int) $order->order_state_id === $failedStateId
            && strtoupper((string) ($order->transaction_status ?? '')) === 'REVERSED'
        ) {
            return ['status' => 200, 'message' => 'Order already in the requested state'];
        }

        $write = $this->updateOrderStatus(
            $order,
            $failedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_REVERSED') . ' - FLAG FOR REVIEW',
            'REVERSED'
        );

        if ($write === 'failed') {
            return ['status' => 500, 'message' => 'Order update failed'];
        }

        if ($write === 'skipped') {
            return ['status' => 409, 'message' => 'Order already settled'];
        }

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
     * Keyed on the resource's own id, so a replayed webhook — or the webhook that follows an
     * admin refund, which already wrote this id — dedupes. Orders captured before the ledger
     * existed have no parent row to reverse against and keep their pre-ledger behaviour. A parent
     * mismatch is logged, not thrown: a 500 would make PayPal retry an event that can never succeed
     * and hold back the status change the money movement calls for.
     *
     * @param array<string, mixed> $resource
     */
    private function recordReversal(\stdClass $order, array $resource, float $amount): bool
    {
        $orderPk   = (int) $order->j2commerce_order_id;
        $txnId     = (string) ($resource['id'] ?? '');
        $captureId = (string) ($resource['supplementary_data']['related_ids']['capture_id'] ?? '')
            ?: (string) ($order->transaction_id ?? '');

        if ($amount <= 0 || $txnId === '' || $captureId === '' || !OrderTransactionHelper::hasLedger($orderPk)) {
            return false;
        }

        try {
            OrderTransactionHelper::addReversal($orderPk, 'payment_paypal', $txnId, $captureId, $amount);

            return true;
        } catch (\InvalidArgumentException $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal reversal not recorded in the ledger: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal']
            );

            return false;
        }
    }

    /**
     * Has this order already reached a settled outcome? Resolved by type, never by a literal
     * id -- PayPalOrderStates is explicit that ids are install-dependent.
     */
    private function hasSettled(\stdClass $order, Registry $params): bool
    {
        $current   = (int) $order->order_state_id;
        $confirmed = PayPalOrderStates::resolve($params, PayPalOrderStates::CONFIRMED);
        $refunded  = PayPalOrderStates::resolve($params, PayPalOrderStates::REFUNDED);

        // A completed transaction counts even where no Complete status resolves and the order
        // status was left unchanged.
        return ($confirmed > 0 && $current === $confirmed)
            || ($refunded > 0 && $current === $refunded)
            || (float) ($order->order_refund ?? 0) > 0
            || PayPalOrderStates::isSettledOrSettling((string) ($order->transaction_status ?? ''));
    }

    /**
     * True only when every value the write intended is on the row: an order already in the target
     * status is not proof the write landed while its transaction status still differs.
     */
    private function rowShowsWrite(int $orderPk, int $stateId, string $transactionStatus, string $transactionId): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(['order_state_id', 'transaction_status', 'transaction_id']))
                ->from($this->db->quoteName('#__j2commerce_orders'))
                ->where($this->db->quoteName('j2commerce_order_id') . ' = :pk')
                ->bind(':pk', $orderPk, ParameterType::INTEGER);
            $row = $this->db->setQuery($query)->loadObject();
        } catch (\Throwable) {
            return false;
        }

        // A status alone could already have been on the row, so the transaction status is required.
        return $row !== null
            && $transactionStatus !== ''
            && ($stateId <= 0 || (int) $row->order_state_id === $stateId)
            && (string) $row->transaction_status === $transactionStatus
            && ($transactionId === '' || (string) $row->transaction_id === $transactionId);
    }

    private function sendOrderEmails(string $orderId, bool $notifyAdmin): void
    {
        try {
            Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()
                ->createModel('Order', 'Administrator')
                ->sendOrderNotification($orderId, true, $notifyAdmin);
        } catch (\Throwable $e) {
            Factory::getApplication()->getLogger()->error(
                'PayPal webhook order email failed: ' . $e->getMessage(),
                ['category' => 'j2commerce.paypal', 'order_id' => $orderId]
            );
        }
    }

    /** PayPal is billed the plan's formatted price, so the expectation is rounded the same way. */
    private function roundToCurrency(float $amount, string $currency): float
    {
        return (float) PayPalCurrencyHelper::format($amount, $currency);
    }

    /**
     * Mirrors the fallback the plugin prices a plan with: currency_code is NOT NULL but may
     * be empty on a migrated row, and the plan would have been priced in USD in that case.
     */
    private function orderCurrency(object $order): string
    {
        return PayPalCurrencyHelper::orderCurrency($order);
    }

    /** Capture and renewal already check currency; a refund in another currency is not this order's money. */
    private function matchesOrderCurrency(\stdClass $order, array $resource, string $currency): bool
    {
        $eventCurrency = strtoupper(trim((string) ($resource['amount']['currency_code'] ?? '')));

        if ($eventCurrency === '' || $eventCurrency === $currency) {
            return true;
        }

        Factory::getApplication()->getLogger()->error(
            'PayPal webhook refund/reversal currency mismatch — manual review required',
            [
                'category' => 'j2commerce.paypal',
                'order_id' => $order->order_id,
                'received' => $eventCurrency,
                'expected' => $currency,
            ]
        );

        return false;
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

    /**
     * A non-empty $transactionStatus replaces the stored one, so a denied capture no longer reads as held.
     *
     * @return 'done'|'skipped'|'failed'
     */
    private function updateOrderStatus(
        \stdClass $order,
        int $newStateId,
        string $comment,
        string $transactionStatus = '',
        string $transactionId = '',
        bool $unlessSettled = false
    ): string {
        if ($newStateId <= 0 && $transactionStatus === '') {
            return 'done';
        }

        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        // The row is locked from the settled check to the store, so a settlement claim that lands
        // meanwhile waits rather than being overwritten by a pending or denied status.
        $this->db->transactionStart();

        try {
            // The query builder has no MySQL row-lock clause, so the statement is written out; it
            // stays a query object so the order id remains a bound value.
            $orderPk = (int) $order->j2commerce_order_id;
            $lock    = $this->db->createQuery()
                ->setQuery(
                    'SELECT ' . $this->db->quoteName('transaction_status')
                    . ' FROM ' . $this->db->quoteName('#__j2commerce_orders')
                    . ' WHERE ' . $this->db->quoteName('j2commerce_order_id') . ' = :pk FOR UPDATE'
                )
                ->bind(':pk', $orderPk, ParameterType::INTEGER);
            $current = (string) $this->db->setQuery($lock)->loadResult();

            if ($unlessSettled && PayPalOrderStates::isSettledOrSettling($current)) {
                $this->db->transactionCommit();

                return 'skipped';
            }

            // An unloaded table is treated as new by store(), which would insert a placeholder
            // order rather than update this one.
            if (!$orderTable->load(['order_id' => $order->order_id])) {
                $this->db->transactionRollback();

                return 'failed';
            }

            if ($newStateId > 0) {
                $orderTable->order_state_id = $newStateId;
            }

            if ($transactionStatus !== '') {
                $orderTable->transaction_status = $transactionStatus;
            }

            if ($transactionId !== '') {
                $orderTable->transaction_id = $transactionId;
            }

            $stored = TableSaveHelper::store($orderTable, 'paypal.webhook.order_status');
        } catch (\Throwable $e) {
            $stored = false;

            // A logger failure must not leave the transaction open.
            try {
                Factory::getApplication()->getLogger()->error(
                    'PayPal webhook status write failed: ' . $e->getMessage(),
                    ['category' => 'j2commerce.paypal', 'order_id' => $order->order_id]
                );
            } catch (\Throwable) {
            }
        }

        // A store that throws or reports failure after its status listeners ran has already moved
        // stock or sent mail: keep a write that landed, so PayPal's redelivery does not repeat them.
        $orderPk = (int) $order->j2commerce_order_id;

        if (!$stored && !$this->rowShowsWrite($orderPk, $newStateId, $transactionStatus, $transactionId)) {
            $this->db->transactionRollback();

            return 'failed';
        }

        $this->db->transactionCommit();

        try {
            OrderHistoryHelper::add(
                orderId: (string) $order->order_id,
                comment: $comment,
                orderStateId: (int) $orderTable->order_state_id,
            );
        } catch (\Throwable $e) {
            try {
                Factory::getApplication()->getLogger()->error(
                    'PayPal webhook history write failed: ' . $e->getMessage(),
                    ['category' => 'j2commerce.paypal', 'order_id' => $order->order_id]
                );
            } catch (\Throwable) {
            }
        }

        return 'done';
    }

    /**
     * Money captured on an order that is closed without being paid (cancelled or refunded before
     * PayPal completed it) needs a person to refund or reinstate it. An order staff marked paid by
     * hand is not closed, and one that was paid and then refunded has had its money returned, so
     * neither raises anything.
     */
    private function noteCaptureOnClosedOrder(object $orderTable, string $captureId): void
    {
        // A reversal (chargeback) records a ledger refund too, but it still needs a person.
        $reversed = strtoupper((string) ($orderTable->transaction_status ?? '')) === 'REVERSED';

        if (
            !$reversed
            && (
                OrderStatusHelper::isFinalized(OrderStatusHelper::getType((int) $orderTable->order_state_id))
                || (float) ($orderTable->order_refund ?? 0) > 0
                || strtoupper((string) ($orderTable->transaction_status ?? '')) === 'REFUNDED'
            )
        ) {
            return;
        }

        // The history note below is what staff act on, so a logger failure must not skip it.
        try {
            Factory::getApplication()->getLogger()->error(
                'PayPal captured a payment for an order that is no longer awaiting payment',
                ['category' => 'j2commerce.paypal', 'order_id' => $orderTable->order_id, 'capture' => $captureId]
            );
        } catch (\Throwable) {
        }

        PayPalOrderStates::addHistoryOnce(
            $this->db,
            $orderTable,
            Text::sprintf('PLG_J2COMMERCE_PAYMENT_PAYPAL_CAPTURE_ON_CLOSED_ORDER', $captureId)
        );
    }
}
