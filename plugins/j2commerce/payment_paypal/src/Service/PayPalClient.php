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

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;

final class PayPalClient
{
    private ?string $accessToken = null;
    private int $tokenExpiry     = 0;

    private const LOG_CATEGORY = 'j2commerce.paypal';

    /**
     * Scalars are kept only when their key is named here; everything else becomes
     * '[redacted]'. Arrays are always walked, so the payload keeps its shape.
     *
     * An allow-list rather than a deny-list because the fields that must not be logged
     * cannot be enumerated from the key alone: `name` is the cardholder on
     * payment_source.card and on every alternative payment method, but the product on
     * purchase_units[].items[]. Naming what is safe keeps anything PayPal adds later --
     * and anything a deny-list would have missed, such as last_digits, expiry,
     * birth_date, tax_id or a vault id -- out of the log by default.
     */
    private const LOGGABLE_KEYS = [
        'create_time',
        'currency_code',
        'custom_id',
        'debug_id',
        'description',
        'disbursement_mode',
        'error',
        'error_description',
        'expiration_time',
        'expires_in',
        'final_capture',
        'id',
        'information_link',
        'intent',
        'invoice_id',
        'issue',
        'message',
        'method',
        'reference_id',
        'rel',
        'status',
        'update_time',
        'value',
    ];

    /**
     * Parents under which a scalar `value` is a money amount. PayPal's error schema reuses
     * `value` as a verbatim echo of the field it rejected, and createSubscription() sends the
     * subscriber's name and email address -- so `value` is safe by position, never by name.
     */
    private const MONEY_CONTAINERS = [
        'amount',
        'breakdown',
        'discount',
        'gross_amount',
        'handling',
        'insurance',
        'item_total',
        'net_amount',
        'paypal_fee',
        'shipping',
        'shipping_discount',
        'tax_total',
        'unit_amount',
    ];

    /**
     * Parents under which a scalar `id` is a handle to a stored credential rather than a
     * gateway object reference.
     */
    private const CREDENTIAL_CONTAINERS = [
        'customer',
        'payer',
        'payment_source',
        'payment_tokens',
        'subscriber',
        'token',
        'tokens',
        'vault',
    ];

    private const MAX_LOGGED_PAYLOAD = 2000;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox = true,
        private bool $debug = false
    ) {
        $this->loadCachedToken();
    }

    public function getBaseUrl(): string
    {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $ch = curl_init($this->getBaseUrl() . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Accept-Language: en_US',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->_log("OAuth failed: HTTP $httpCode - $error", 'ERROR');
            throw new \RuntimeException("PayPal auth failed: HTTP $httpCode");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            $this->_logPayload('OAuth response missing access_token: ', (string) $response, 'ERROR');
            throw new \RuntimeException('PayPal auth failed: Invalid response');
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 32400) - 60;
        $this->cacheToken();

        $this->_log('Access token obtained, expires in ' . ($data['expires_in'] ?? 0) . 's', 'INFO');
        return $this->accessToken;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string> $extraHeaders
     * @return array{status: int, body: array<string, mixed>}
     */
    public function request(string $method, string $endpoint, ?array $body = null, array $extraHeaders = [], bool $isRetry = false): array
    {
        $token = $this->getAccessToken();
        $url   = $this->getBaseUrl() . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        $headers = array_merge($headers, $extraHeaders);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($body !== null) {
            $jsonBody = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            $this->_logPayload("$method $endpoint: ", (string) $jsonBody);
        } else {
            $this->_log("$method $endpoint", 'DEBUG');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $responseBody = json_decode($response, true) ?? [];
        $this->_logPayload("Response HTTP $httpCode: ", (string) $response);

        if ($httpCode === 401 && !$isRetry) {
            $this->_log('Received 401, refreshing token and retrying', 'INFO');
            $this->accessToken = null;
            $this->tokenExpiry = 0;

            return $this->request($method, $endpoint, $body, $extraHeaders, true);
        }

        if ($error) {
            $this->_log("cURL error: $error", 'ERROR');
        }

        return [
            'status' => $httpCode,
            'body'   => $responseBody,
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string> $extraHeaders
     * @return array{status: int, body: array<string, mixed>}
     */
    public function requestWithRetry(string $method, string $endpoint, ?array $body = null, array $extraHeaders = []): array
    {
        $requestId = bin2hex(random_bytes(16));
        $headers   = array_merge($extraHeaders, ['PayPal-Request-Id: ' . $requestId]);

        for ($attempt = 0; $attempt <= 3; $attempt++) {
            if ($attempt > 0) {
                $delaySeconds = 2 ** ($attempt - 1);
                $this->_log("Retry attempt $attempt after {$delaySeconds}s", 'INFO');
                usleep($delaySeconds * 1_000_000);
            }

            $result = $this->request($method, $endpoint, $body, $headers);

            if ($result['status'] >= 200 && $result['status'] < 300) {
                return $result;
            }

            if (!\in_array($result['status'], [429, 500, 502, 503], true)) {
                $this->_log("Non-retryable status {$result['status']}, aborting", 'WARNING');
                break;
            }

            $this->_log("Retryable status {$result['status']}, will retry", 'WARNING');
        }

        return $result;
    }

    private function loadCachedToken(): void
    {
        try {
            $session = Factory::getApplication()->getSession();
            $cached  = $session->get('paypal_token', null, 'j2commerce');

            if ($cached && ($cached['expiry'] ?? 0) > time()) {
                $this->accessToken = $cached['token'];
                $this->tokenExpiry = $cached['expiry'];
                $this->_log('Using cached access token', 'DEBUG');
            }
        } catch (\Throwable) {
            // Session not available, skip cache
        }
    }

    private function cacheToken(): void
    {
        try {
            $session = Factory::getApplication()->getSession();
            $session->set('paypal_token', [
                'token'  => $this->accessToken,
                'expiry' => $this->tokenExpiry,
            ], 'j2commerce');
        } catch (\Throwable) {
            // Session not available, skip cache
        }
    }

    private function shouldLog(string $type): bool
    {
        // Same set the plugin registers as ALWAYS_LOGGED: WARNING carries the gateway
        // degradation notices, which a merchant needs without turning debug on.
        return $this->debug || $type === 'ERROR' || $type === 'WARNING';
    }

    /**
     * Redaction is built here rather than at the call site so a debug-off install does not
     * decode, walk and re-encode every PayPal response only to discard the result.
     */
    private function _logPayload(string $prefix, string $payload, string $type = 'DEBUG'): void
    {
        if (!$this->shouldLog($type)) {
            return;
        }

        $this->_log($prefix . $this->redact($payload), $type);
    }

    private function _log(string $message, string $type = 'INFO'): void
    {
        if (!$this->shouldLog($type)) {
            return;
        }

        $priorities = [
            'DEBUG'   => Log::DEBUG,
            'INFO'    => Log::INFO,
            'WARNING' => Log::WARNING,
            'ERROR'   => Log::ERROR,
        ];

        // A request value reaches some of these messages, and the text-file logger writes the
        // message into a tab-delimited line verbatim -- a newline would forge a second entry.
        Log::add(
            str_replace(["\r", "\n"], ' ', $message),
            $priorities[$type] ?? Log::INFO,
            self::LOG_CATEGORY
        );
    }

    /**
     * Redacts the buyer's identifying fields out of a JSON payload before it is logged.
     * Falls back to the payload's length when it does not decode, so a malformed body is
     * never echoed verbatim.
     */
    private function redact(string $json): string
    {
        $decoded = json_decode($json, true);

        // Covers invalid JSON, a bare scalar and anything past json_decode()'s depth limit.
        // Only the length is reported, never the text.
        if (!\is_array($decoded)) {
            return '[unloggable payload, ' . \strlen($json) . ' bytes]';
        }

        $redacted = json_encode($this->redactValue($decoded));

        if ($redacted === false) {
            return '[unloggable payload, ' . \strlen($json) . ' bytes]';
        }

        return \strlen($redacted) > self::MAX_LOGGED_PAYLOAD
            ? substr($redacted, 0, self::MAX_LOGGED_PAYLOAD) . '... [truncated]'
            : $redacted;
    }

    private function redactValue(mixed $value, string $parentKey = ''): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                // A list index carries no meaning, so the nearest NAMED ancestor is passed on
                // instead: without this, `tokens[0].id` would be judged against the parent "0"
                // and escape the credential rule below.
                $value[$key] = $this->redactValue($item, is_numeric($key) ? $parentKey : (string) $key);
                continue;
            }

            $value[$key] = $this->isLoggableScalar((string) $key, $parentKey) ? $item : '[redacted]';
        }

        return $value;
    }

    private function isLoggableScalar(string $key, string $parentKey): bool
    {
        if ($key === 'value') {
            return \in_array($parentKey, self::MONEY_CONTAINERS, true);
        }

        // `id` names an order, capture or subscription everywhere except under a credential
        // block, where it is a reusable handle for the buyer's stored payment method.
        if ($key === 'id') {
            return !\in_array($parentKey, self::CREDENTIAL_CONTAINERS, true);
        }

        return \in_array($key, self::LOGGABLE_KEYS, true);
    }
}
