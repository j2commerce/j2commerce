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

final class PayPalOrders
{
    public function __construct(private PayPalClient $client)
    {
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array{status: int, body: array<string, mixed>}
     */
    public function createOrder(array $orderData): array
    {
        $currencyCode = $orderData['currency_code'];
        $total        = (float) $orderData['total'];
        $breakdown    = $this->buildAmountBreakdown($orderData, $currencyCode);

        $this->validateBreakdown($breakdown, $total);

        $body = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderData['order_id'] ?? '',
                'custom_id'    => (string) ($orderData['j2commerce_order_id'] ?? ''),
                'invoice_id'   => $orderData['invoice_id'] ?? '',
                'amount'       => [
                    'currency_code' => $currencyCode,
                    'value'         => $this->formatAmount($total),
                    'breakdown'     => $breakdown,
                ],
                'items' => $this->buildLineItems($orderData['items'] ?? [], $currencyCode),
            ]],
        ];

        return $this->client->requestWithRetry('POST', '/v2/checkout/orders', $body, [
            'Prefer: return=representation',
        ]);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $requestId = 'capture-' . $paypalOrderId . '-' . time();

        return $this->client->requestWithRetry('POST', "/v2/checkout/orders/$paypalOrderId/capture", null, [
            'PayPal-Request-Id: ' . $requestId,
            'Prefer: return=representation',
        ]);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function getOrder(string $paypalOrderId): array
    {
        return $this->client->request('GET', "/v2/checkout/orders/$paypalOrderId");
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function buildLineItems(array $items, string $currencyCode): array
    {
        $lineItems = [];

        foreach ($items as $item) {
            $name = $item['name'] ?? 'Item';
            if (\strlen($name) > 127) {
                $name = substr($name, 0, 124) . '...';
            }

            $lineItems[] = [
                'name'        => $name,
                'quantity'    => (string) ($item['quantity'] ?? 1),
                'unit_amount' => [
                    'currency_code' => $currencyCode,
                    'value'         => $this->formatAmount($item['unit_amount'] ?? 0),
                ],
                'sku' => $item['sku'] ?? '',
            ];
        }

        return $lineItems;
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array<string, array<string, string>>
     */
    private function buildAmountBreakdown(array $orderData, string $currencyCode): array
    {
        return [
            'item_total' => [
                'currency_code' => $currencyCode,
                'value'         => $this->formatAmount($orderData['item_total'] ?? 0),
            ],
            'shipping' => [
                'currency_code' => $currencyCode,
                'value'         => $this->formatAmount($orderData['shipping'] ?? 0),
            ],
            'tax_total' => [
                'currency_code' => $currencyCode,
                'value'         => $this->formatAmount($orderData['tax'] ?? 0),
            ],
            'discount' => [
                'currency_code' => $currencyCode,
                'value'         => $this->formatAmount($orderData['discount'] ?? 0),
            ],
        ];
    }

    private function validateBreakdown(array $breakdown, float $total): void
    {
        $itemTotal = (float) ($breakdown['item_total']['value'] ?? 0);
        $shipping  = (float) ($breakdown['shipping']['value'] ?? 0);
        $tax       = (float) ($breakdown['tax_total']['value'] ?? 0);
        $discount  = (float) ($breakdown['discount']['value'] ?? 0);

        $calculatedTotal = $itemTotal + $shipping + $tax - $discount;
        $difference      = abs($calculatedTotal - $total);

        if ($difference > 0.01) {
            throw new \RuntimeException(
                "Amount breakdown validation failed. Calculated: $calculatedTotal, " .
                "Expected: $total (items: $itemTotal, shipping: $shipping, tax: $tax, discount: $discount)"
            );
        }
    }

    private function formatAmount(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
