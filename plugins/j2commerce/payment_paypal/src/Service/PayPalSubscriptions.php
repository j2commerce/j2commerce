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

use Joomla\CMS\Log\Log;

/**
 * PayPal Billing v1 / Catalogs v1 / Subscriptions v1 wrapper.
 *
 * Provides REST-only subscription primitives backed by the existing
 * PayPalClient (OAuth + retry transport). All methods return arrays
 * shaped like {success: bool, ...} so the calling extension can map
 * directly to the J2Commerce renewal-event outcome contract.
 *
 * @since  6.1.0
 */
final class PayPalSubscriptions
{
    public function __construct(
        private readonly PayPalClient $client
    ) {
    }

    /**
     * Resolve or create a Catalogs Product representing this J2Commerce product.
     *
     * @param  array{product_name?: string, catalog_prefix?: string}  $context
     */
    public function getOrCreateCatalogProduct(object $subscription, array $context = []): string
    {
        $name        = (string) ($context['product_name'] ?? 'Subscription');
        $localId     = (int) ($subscription->product_id ?? 0);
        $prefix      = (string) ($context['catalog_prefix'] ?? 'j2c');
        $referenceId = $prefix . '-product-' . $localId;

        $existing = $this->client->request('GET', '/v1/catalogs/products/' . urlencode($referenceId));

        if (($existing['status'] ?? 0) === 200 && !empty($existing['body']['id'])) {
            return (string) $existing['body']['id'];
        }

        $body = [
            'id'          => $referenceId,
            'name'        => substr($name, 0, 127),
            'description' => 'J2Commerce subscription product #' . $localId,
            'type'        => 'SERVICE',
            'category'    => 'SOFTWARE',
        ];

        $created = $this->client->requestWithRetry('POST', '/v1/catalogs/products', $body);

        if (($created['status'] ?? 0) >= 300) {
            throw new \RuntimeException(
                'PayPal catalog product create failed: HTTP ' . ($created['status'] ?? 0)
                . ' — ' . ($created['body']['message'] ?? 'unknown error')
            );
        }

        return (string) ($created['body']['id'] ?? $referenceId);
    }

    /**
     * Create a Billing Plan derived from a J2Commerce subscription record.
     *
     * Required context keys: brand_name, product_name, currency_code.
     * Optional: plan_name_template, catalog_prefix.
     *
     * @param  array{brand_name: string, product_name: string, currency_code: string, plan_name_template?: string, catalog_prefix?: string}  $context
     */
    public function createBillingPlan(object $subscription, object $order, array $context): string
    {
        $productId = $this->getOrCreateCatalogProduct($subscription, $context);

        $period       = strtoupper((string) ($subscription->period ?? 'MONTH'));
        $intervalUnit = match ($period) {
            'DAY', 'D'    => 'DAY',
            'WEEK', 'W'   => 'WEEK',
            'YEAR', 'Y'   => 'YEAR',
            default       => 'MONTH',
        };

        $intervalCount = max(1, (int) ($subscription->period_units ?? 1));
        // total_cycles=0 means "infinite" per PayPal. That matches J2Commerce's
        // convention for open-ended subscriptions. NOTE: if a future change adds
        // trial/setup-fee cycles, PayPal requires infinite cycles to be the LAST
        // entry in billing_cycles[] — extend this array accordingly.
        $totalCycles   = max(0, (int) ($subscription->subscription_length ?? 0));
        $renewalAmount = $this->formatAmount(
            (float) ($subscription->renewal_amount ?? $order->order_total ?? 0),
            (string) $context['currency_code']
        );
        $sitename      = (string) $context['brand_name'];
        $productName   = (string) $context['product_name'];

        $planName = !empty($context['plan_name_template'])
            ? strtr((string) $context['plan_name_template'], [
                '{site}'         => $sitename,
                '{product_name}' => $productName,
            ])
            : ($sitename . ' — ' . $productName);

        $body = [
            'product_id'   => $productId,
            'name'         => substr($planName, 0, 127),
            'description'  => 'J2Commerce subscription #' . (int) ($subscription->id ?? 0),
            'status'       => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit'  => $intervalUnit,
                        'interval_count' => $intervalCount,
                    ],
                    'tenure_type'  => 'REGULAR',
                    'sequence'     => 1,
                    'total_cycles' => $totalCycles,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value'         => $renewalAmount,
                            'currency_code' => strtoupper((string) $context['currency_code']),
                        ],
                    ],
                ],
            ],
            'payment_preferences' => [
                'auto_bill_outstanding'     => true,
                'setup_fee_failure_action'  => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ];

        $response = $this->client->requestWithRetry('POST', '/v1/billing/plans', $body);

        if (($response['status'] ?? 0) >= 300) {
            throw new \RuntimeException(
                'PayPal billing plan create failed: HTTP ' . ($response['status'] ?? 0)
                . ' — ' . ($response['body']['message'] ?? 'unknown error')
            );
        }

        return (string) ($response['body']['id'] ?? '');
    }

    /**
     * Create a Subscription against an existing Plan.
     *
     * Required context keys: subscriber_given_name, subscriber_surname, subscriber_email,
     * brand_name, return_url, cancel_url.
     *
     * @param  array{subscriber_given_name: string, subscriber_surname: string, subscriber_email: string, brand_name: string, return_url: string, cancel_url: string}  $context
     */
    public function createSubscription(string $planId, object $subscription, array $context): array
    {
        $given   = trim((string) $context['subscriber_given_name']);
        $surname = trim((string) $context['subscriber_surname']);
        $email   = trim((string) $context['subscriber_email']);

        if ($given === '' || $surname === '' || $email === '') {
            return [
                'success' => false,
                'error'   => 'PayPal subscription create failed: subscriber name and email are required',
            ];
        }

        $body = [
            'plan_id' => $planId,
            'subscriber' => [
                'name' => [
                    'given_name' => $given,
                    'surname'    => $surname,
                ],
                'email_address' => $email,
            ],
            'application_context' => [
                'brand_name'         => substr((string) $context['brand_name'], 0, 127),
                'locale'             => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'        => 'SUBSCRIBE_NOW',
                'payment_method'     => [
                    'payer_selected'              => 'PAYPAL',
                    'payee_preferred'             => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => (string) $context['return_url'],
                'cancel_url' => (string) $context['cancel_url'],
            ],
            'custom_id' => 'j2c-sub-' . (int) ($subscription->id ?? 0),
        ];

        $response = $this->client->requestWithRetry('POST', '/v1/billing/subscriptions', $body);

        if (($response['status'] ?? 0) >= 300) {
            return [
                'success' => false,
                'error'   => 'PayPal subscription create failed: HTTP ' . ($response['status'] ?? 0)
                    . ' — ' . ($response['body']['message'] ?? 'unknown error'),
            ];
        }

        $approveUrl = '';

        foreach ($response['body']['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = (string) ($link['href'] ?? '');
                break;
            }
        }

        return [
            'success'     => true,
            'id'          => (string) ($response['body']['id'] ?? ''),
            'status'      => (string) ($response['body']['status'] ?? ''),
            'approve_url' => $approveUrl,
        ];
    }

    /**
     * Charge the next billing cycle on a stored subscription.
     *
     * PayPal exposes /v1/billing/subscriptions/{id}/capture for on-demand
     * charges against an active subscription. Returns one of:
     *   ['status' => 'success',     'gateway_response' => …]
     *   ['status' => 'failed',      'gateway_response' => …, 'error' => …]
     *   ['status' => 'no_response', 'gateway_response' => …, 'error' => …]
     */
    /**
     * Charge the next billing cycle on a stored subscription.
     *
     * NOTE: PayPal's preferred renewal path is its own auto-billing schedule
     * (PAYMENT.SALE.COMPLETED webhooks drive renewal state via the PayPalWebhooks
     * handlers). This synchronous capture is for cron/debug flows and takes two
     * separate HTTP calls (GET then POST) — there is a small race window where
     * PayPal could cancel/suspend the subscription between the two. Acceptable
     * for the cron path; documented here so callers know the trade-off.
     */
    public function captureRenewalPayment(object $subscription, object $order, string $paypalSubscriptionId): array
    {
        $details = $this->client->request('GET', '/v1/billing/subscriptions/' . urlencode($paypalSubscriptionId));

        if (($details['status'] ?? 0) === 0 || ($details['status'] ?? 0) >= 500) {
            return [
                'status'           => 'no_response',
                'gateway_response' => $details['body'] ?? [],
                'error'            => 'PayPal subscription lookup unreachable',
            ];
        }

        if (($details['status'] ?? 0) >= 300) {
            return [
                'status'           => 'failed',
                'gateway_response' => $details['body'] ?? [],
                'error'            => $details['body']['message'] ?? 'PayPal subscription lookup failed',
            ];
        }

        $remoteStatus = strtoupper((string) ($details['body']['status'] ?? ''));

        if ($remoteStatus !== 'ACTIVE') {
            return [
                'status'           => 'failed',
                'gateway_response' => $details['body'] ?? [],
                'error'            => 'PayPal subscription not active (status: ' . $remoteStatus . ')',
            ];
        }

        $currency = strtoupper((string) ($order->currency_code ?? 'USD'));
        $amount   = $this->formatAmount((float) ($subscription->renewal_amount ?? $order->order_total ?? 0), $currency);
        $body     = [
            'note'                => 'J2Commerce renewal — Order #' . ($order->order_id ?? ''),
            'capture_type'        => 'OUTSTANDING_BALANCE',
            'amount' => [
                'value'         => $amount,
                'currency_code' => $currency,
            ],
        ];

        $captured = $this->client->requestWithRetry(
            'POST',
            '/v1/billing/subscriptions/' . urlencode($paypalSubscriptionId) . '/capture',
            $body
        );

        $statusCode = (int) ($captured['status'] ?? 0);

        if ($statusCode === 0 || ($statusCode >= 500 && $statusCode <= 599)) {
            return [
                'status'           => 'no_response',
                'gateway_response' => $captured['body'] ?? [],
                'error'            => 'PayPal capture endpoint unreachable',
            ];
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return [
                'status'           => 'success',
                'gateway_response' => $captured['body'] ?? [],
                'transaction_id'   => (string) ($captured['body']['id'] ?? ''),
            ];
        }

        return [
            'status'           => 'failed',
            'gateway_response' => $captured['body'] ?? [],
            'error'            => $captured['body']['message'] ?? 'PayPal capture failed',
        ];
    }

    public function cancelSubscription(string $paypalSubscriptionId, string $reason): bool
    {
        $response = $this->client->request(
            'POST',
            '/v1/billing/subscriptions/' . urlencode($paypalSubscriptionId) . '/cancel',
            ['reason' => substr($reason, 0, 127)]
        );

        $status = (int) ($response['status'] ?? 0);

        if ($status >= 200 && $status < 300) {
            return true;
        }

        // PayPal returns 422 SUBSCRIPTION_STATUS_INVALID when the subscription is already
        // in a terminal state. That is the desired outcome — treat as idempotent success.
        if ($status === 422) {
            $details = $this->getSubscriptionDetails($paypalSubscriptionId);
            $remote  = strtoupper((string) ($details['body']['status'] ?? ''));

            if (\in_array($remote, ['CANCELLED', 'EXPIRED'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a monetary amount using PayPal's decimal rules.
     * JPY/KRW/TWD/HUF use 0 decimals; everything else 2.
     */
    private function formatAmount(float $amount, string $currency): string
    {
        $zeroDecimal = ['JPY', 'KRW', 'TWD', 'HUF', 'CLP', 'ISK'];
        $decimals    = \in_array(strtoupper($currency), $zeroDecimal, true) ? 0 : 2;

        return number_format($amount, $decimals, '.', '');
    }

    public function suspendSubscription(string $paypalSubscriptionId, string $reason): bool
    {
        $response = $this->client->request(
            'POST',
            '/v1/billing/subscriptions/' . urlencode($paypalSubscriptionId) . '/suspend',
            ['reason' => substr($reason, 0, 127)]
        );

        return ($response['status'] ?? 0) >= 200 && ($response['status'] ?? 0) < 300;
    }

    public function getSubscriptionDetails(string $paypalSubscriptionId): array
    {
        $response = $this->client->request(
            'GET',
            '/v1/billing/subscriptions/' . urlencode($paypalSubscriptionId)
        );

        return [
            'status'   => (int) ($response['status'] ?? 0),
            'body'     => $response['body'] ?? [],
        ];
    }
}
