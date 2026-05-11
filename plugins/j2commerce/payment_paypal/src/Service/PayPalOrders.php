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

        // Inject payment_source when the JS has told us which funding source the customer chose.
        $paymentSource = $this->buildPaymentSource($orderData);

        if (!empty($paymentSource)) {
            $body['payment_source'] = $paymentSource;
        }

        return $this->client->requestWithRetry('POST', '/v2/checkout/orders', $body, [
            'Prefer: return=representation',
        ]);
    }

    /**
     * Build the PayPal Orders API `payment_source` object.
     *
     * - paypal / venmo / paylater  → payment_source.paypal  (experience_context)
     * - card / applepay / googlepay → omitted (SDK handles these automatically)
     * - local redirect methods      → payment_source.<method> with return_url / cancel_url
     *
     * @param  array<string, mixed> $orderData
     * @return array<string, mixed>
     */
    private function buildPaymentSource(array $orderData): array
    {
        $source = trim((string) ($orderData['payment_source'] ?? ''));

        if ($source === '') {
            return [];
        }

        // PayPal wallet, Pay Later and Venmo all use the 'paypal' key in payment_source.
        if (\in_array($source, ['paypal', 'venmo', 'paylater'], true)) {
            return [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'user_action'               => 'PAY_NOW',
                    ],
                ],
            ];
        }

        // Card, Apple Pay and Google Pay are handled entirely by the SDK; no payment_source
        // block is required (or allowed) in the Create Order body for these funding sources.
        if (\in_array($source, ['card', 'applepay', 'googlepay'], true)) {
            return [];
        }

        // Local / redirect payment methods (iDEAL, Bancontact, BLIK, …) need an
        // experience_context with return_url and cancel_url so PayPal can redirect
        // the customer back after they complete their bank flow.
        $experienceContext = [
            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
            'user_action'               => 'PAY_NOW',
        ];

        if (!empty($orderData['return_url'])) {
            $experienceContext['return_url'] = $orderData['return_url'];
        }

        if (!empty($orderData['cancel_url'])) {
            $experienceContext['cancel_url'] = $orderData['cancel_url'];
        }

        return [
            $source => [
                'experience_context' => $experienceContext,
            ],
        ];
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
