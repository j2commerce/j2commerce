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

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHistoryHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class PayPalWebhooks
{
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
                'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($event, $params),
                'PAYMENT.CAPTURE.PENDING'   => $this->handleCapturePending($event, $params),
                'PAYMENT.CAPTURE.DENIED'    => $this->handleCaptureDenied($event, $params),
                'PAYMENT.CAPTURE.REFUNDED'  => $this->handleCaptureRefunded($event, $params),
                'PAYMENT.CAPTURE.REVERSED'  => $this->handleCaptureReversed($event, $params),
                'CUSTOMER.DISPUTE.CREATED'  => $this->handleDisputeCreated($event, $params),
                'CUSTOMER.DISPUTE.RESOLVED' => $this->handleDisputeResolved($event, $params),
                default                     => ['status' => 200, 'message' => 'Event type not handled'],
            };

            return $result;
        } catch (\Exception $e) {
            return ['status' => 500, 'message' => 'Internal error: ' . $e->getMessage()];
        }
    }

    public function isAlreadyProcessed(string $eventId): bool
    {
        $likePattern = '%"webhook_event_id":"' . $eventId . '"%';

        $query = $this->db->getQuery(true);
        $query->select('COUNT(*)')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where($this->db->quoteName('transaction_details') . ' LIKE :event_id')
            ->bind(':event_id', $likePattern);

        $this->db->setQuery($query);
        return (int) $this->db->loadResult() > 0;
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

        $order = $this->loadOrderByPayPalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $confirmedStateId = (int) $params->get('order_state_id', 5);

        $this->updateOrderStatus(
            $order,
            $confirmedStateId,
            Text::sprintf('COM_J2COMMERCE_PAYPAL_PAYMENT_COMPLETED', $captureId)
        );

        $order->transaction_id                      = $captureId;
        $order->transaction_status                  = 'COMPLETED';
        $transactionDetails                         = json_decode($order->transaction_details ?? '{}', true);
        $transactionDetails['webhook_event_id']     = $event['id'] ?? '';
        $transactionDetails['capture_completed_at'] = date('Y-m-d H:i:s');
        $order->transaction_details                 = json_encode($transactionDetails);
        $order->store();

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

        $order = $this->loadOrderByPayPalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $pendingStateId = (int) $params->get('pending_state_id', 3);

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

        $order = $this->loadOrderByPayPalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $failedStateId = (int) $params->get('failed_state_id', 4);

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

        $order = $this->loadOrderByPayPalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $refundedStateId = (int) $params->get('refunded_state_id', 7);

        $this->updateOrderStatus(
            $order,
            $refundedStateId,
            Text::_('COM_J2COMMERCE_PAYPAL_PAYMENT_REFUNDED')
        );

        return ['status' => 200, 'message' => 'Capture refunded'];
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

        $order = $this->loadOrderByPayPalId($customId);
        if (!$order) {
            return ['status' => 404, 'message' => 'Order not found'];
        }

        $failedStateId = (int) $params->get('failed_state_id', 4);

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

    private function loadOrderByPayPalId(string $paypalOrderId): ?\stdClass
    {
        $likePattern = '%"paypal_order_id":"' . $paypalOrderId . '"%';

        $query = $this->db->getQuery(true);
        $query->select('*')
            ->from($this->db->quoteName('#__j2commerce_orders'))
            ->where(
                $this->db->quoteName('transaction_details') . ' LIKE :paypal_id'
                . ' OR ' . $this->db->quoteName('order_id') . ' = :order_id_match'
            )
            ->bind(':paypal_id', $likePattern)
            ->bind(':order_id_match', $paypalOrderId);

        $this->db->setQuery($query);
        return $this->db->loadObject();
    }

    private function updateOrderStatus(\stdClass $order, int $newStateId, string $comment): void
    {
        $orderTable = Factory::getApplication()
            ->bootComponent('com_j2commerce')
            ->getMVCFactory()
            ->createTable('Order', 'Administrator');

        $orderTable->load(['order_id' => $order->order_id]);
        $orderTable->order_state_id = $newStateId;
        $orderTable->store();

        OrderHistoryHelper::add(
            orderId: (string) $order->order_id,
            comment: $comment,
            orderStateId: $newStateId,
        );
    }
}
