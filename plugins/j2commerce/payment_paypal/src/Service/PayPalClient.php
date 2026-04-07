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

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox = true
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
            $this->_log('OAuth response missing access_token: ' . $response, 'ERROR');
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
            $this->_log("$method $endpoint: " . $jsonBody, 'DEBUG');
        } else {
            $this->_log("$method $endpoint", 'DEBUG');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        $responseBody = json_decode($response, true) ?? [];
        $this->_log("Response HTTP $httpCode: " . substr($response, 0, 500), 'DEBUG');

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

    private function _log(string $message, string $type = 'INFO'): void
    {
        $priorities = [
            'DEBUG'   => Log::DEBUG,
            'INFO'    => Log::INFO,
            'WARNING' => Log::WARNING,
            'ERROR'   => Log::ERROR,
        ];

        Log::add(
            $message,
            $priorities[$type] ?? Log::INFO,
            'j2commerce.paypal'
        );
    }
}
