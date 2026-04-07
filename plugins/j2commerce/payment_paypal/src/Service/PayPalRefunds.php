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

final class PayPalRefunds
{
    public function __construct(private PayPalClient $client)
    {
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function refundCapture(string $captureId, ?float $amount = null, ?string $currency = null): array
    {
        $body = [];

        if ($amount !== null) {
            $body['amount'] = [
                'value'         => $this->formatAmount($amount),
                'currency_code' => $currency ?? 'USD',
            ];
        }

        $requestId = 'refund-' . $captureId . '-' . time();

        return $this->client->requestWithRetry(
            'POST',
            "/v2/payments/captures/$captureId/refund",
            $body ?: null,
            ['PayPal-Request-Id: ' . $requestId]
        );
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getRefund(string $refundId): array
    {
        return $this->client->request('GET', "/v2/payments/refunds/$refundId");
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
