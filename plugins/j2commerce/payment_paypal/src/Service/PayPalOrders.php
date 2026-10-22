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

use J2Commerce\Plugin\J2Commerce\PaymentPaypal\Helper\PayPalCurrencyHelper;

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
        return $this->client->requestWithRetry('POST', '/v2/checkout/orders', $this->buildBody($orderData), [
            'Prefer: return=representation',
        ]);
    }

    /**
     * Merchant-initiated capture against a legacy (NVP Express Checkout) billing agreement id.
     * The request id is derived from the agreement and invoice so a repeated attempt for the same
     * renewal order is deduplicated by PayPal instead of charging twice.
     *
     * @param array<string, mixed> $orderData
     * @return array{status: int, body: array<string, mixed>}
     */
    public function chargeBillingAgreement(string $baid, array $orderData): array
    {
        $body                   = $this->buildBody($orderData);
        $body['payment_source'] = [
            'paypal' => [
                'billing_agreement_id' => $baid,
                'stored_credential'    => ['payment_initiator' => 'MERCHANT', 'usage' => 'SUBSEQUENT'],
            ],
        ];

        return $this->client->requestWithRetry('POST', '/v2/checkout/orders', $body, [
            'PayPal-Request-Id: baid-' . md5($baid . '|' . ($orderData['invoice_id'] ?? '')),
            'Prefer: return=representation',
        ]);
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array<string, mixed>
     */
    private function buildBody(array $orderData): array
    {
        $currencyCode = $orderData['currency_code'];
        $factor       = 10 ** PayPalCurrencyHelper::decimals($currencyCode);
        $minor        = static fn (mixed $amount): int => (int) round((float) PayPalCurrencyHelper::format((float) $amount, $currencyCode) * $factor);
        $money        = static fn (int $amount): array => [
            'currency_code' => $currencyCode,
            'value'         => PayPalCurrencyHelper::format($amount / $factor, $currencyCode),
        ];

        // PayPal requires item_total to equal the sum of the rounded unit amounts times their
        // quantities, so it is built from the same rounded figures the line items carry.
        $itemTotal = 0;
        $units     = 0;

        foreach ($orderData['items'] ?? [] as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $itemTotal += $minor($item['unit_amount'] ?? 0) * $quantity;
            $units += $quantity;
        }

        $total    = $minor($orderData['total']);
        $shipping = $minor($orderData['shipping'] ?? 0);
        $tax      = $minor($orderData['tax'] ?? 0);
        $handling = $minor($orderData['handling'] ?? 0);
        $discount = $minor($orderData['discount'] ?? 0);

        // Every figure is rounded on its own and each unit before it is multiplied, so the parts
        // can miss the total by up to half a minor unit per unit sold plus half per figure. That
        // remainder is settled into handling or discount; anything larger is a real mismatch.
        $residual = $total - ($itemTotal + $shipping + $tax + $handling - $discount);

        if (abs($residual) > intdiv($units + 6, 2)) {
            throw new \RuntimeException(
                'Amount breakdown validation failed. Calculated: ' . ($total - $residual) / $factor . ', '
                . 'Expected: ' . $total / $factor . ' (items: ' . $itemTotal / $factor . ', shipping: ' . $shipping / $factor
                . ', tax: ' . $tax / $factor . ', handling: ' . $handling / $factor . ', discount: ' . $discount / $factor . ')'
            );
        }

        if ($residual > 0) {
            $handling += $residual;
        } else {
            $discount -= $residual;
        }

        return [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderData['order_id'] ?? '',
                'custom_id'    => (string) ($orderData['j2commerce_order_id'] ?? ''),
                'invoice_id'   => $orderData['invoice_id'] ?? '',
                'amount'       => $money($total) + [
                    'breakdown' => [
                        'item_total' => $money($itemTotal),
                        'shipping'   => $money($shipping),
                        'tax_total'  => $money($tax),
                        'handling'   => $money($handling),
                        'discount'   => $money($discount),
                    ],
                ],
                'items' => $this->buildLineItems($orderData['items'] ?? [], $currencyCode),
            ]],
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
                $name = mb_strcut($name, 0, 124) . '...';
            }

            $lineItems[] = [
                'name'        => $name,
                'quantity'    => (string) ($item['quantity'] ?? 1),
                'unit_amount' => [
                    'currency_code' => $currencyCode,
                    'value'         => PayPalCurrencyHelper::format((float) ($item['unit_amount'] ?? 0), $currencyCode),
                ],
                'sku' => $item['sku'] ?? '',
            ];
        }

        return $lineItems;
    }
}
