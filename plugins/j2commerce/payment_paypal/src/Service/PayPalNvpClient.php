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

/**
 * PayPal Classic NVP API client.
 *
 * Used for charging legacy J2Store-issued Billing Agreement IDs (BAID) via
 * DoReferenceTransaction, and for cancelling them via BillAgreementUpdate.
 * NVP API endpoint is `api-3t.{sandbox.}paypal.com/nvp`.
 *
 * **NVP is deprecated by PayPal for new merchants** — only PayPal Business
 * accounts that obtained NVP API credentials before mid-2023 can still use
 * this surface. New merchants must use the modern REST flow (PayPalClient).
 *
 * This class is only required for hybrid Path C: legacy J2Store data with
 * `billing_agreement_id` metakey continues to renew via NVP, while new
 * subscriptions go through the modern REST/Vault path.
 *
 * @since  6.1.7
 */
final class PayPalNvpClient
{
    private const SIGNATURE_VERSION = '204.0';

    public function __construct(
        private readonly string $apiUsername,
        private readonly string $apiPassword,
        private readonly string $apiSignature,
        private readonly bool $sandbox = false,
        private readonly bool $debug = false,
        private readonly ?\Closure $logger = null
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiUsername !== ''
            && $this->apiPassword !== ''
            && $this->apiSignature !== '';
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    private function endpoint(): string
    {
        return $this->sandbox
            ? 'https://api-3t.sandbox.paypal.com/nvp'
            : 'https://api-3t.paypal.com/nvp';
    }

    public function log(string $message, int $priority = \Joomla\CMS\Log\Log::INFO): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message, $priority);
        }
    }

    /**
     * Charge a saved BAID off-session. PayPal returns ACK=Success on approval,
     * ACK=Failure with L_ERRORCODE0=10412 on duplicate INVNUM, or ACK=Failure
     * with various 10xxx codes on decline / expired card.
     *
     * @param  array<string, scalar>  $extra  Extra NVP fields (e.g. invoice line items)
     * @return array<string, mixed>
     */
    public function doReferenceTransaction(
        string $referenceId,
        float $amount,
        string $currencyCode,
        string $invoiceNumber = '',
        string $description = '',
        string $paymentAction = 'Sale',
        array $extra = []
    ): array {
        $fields = array_merge([
            'METHOD'        => 'DoReferenceTransaction',
            'REFERENCEID'   => $referenceId,
            'PAYMENTACTION' => $paymentAction,
            'AMT'           => number_format($amount, 2, '.', ''),
            'CURRENCYCODE'  => $currencyCode,
        ], $extra);

        if ($invoiceNumber !== '') {
            $fields['INVNUM'] = $invoiceNumber;
        }

        if ($description !== '') {
            $fields['DESC'] = substr($description, 0, 127);
        }

        return $this->request($fields);
    }

    /**
     * Cancel a BAID at PayPal. After this, no further DoReferenceTransaction
     * against the BAID will succeed — PayPal returns ACK=Failure with
     * L_ERRORCODE0=11451 (BillingAgreement is canceled).
     *
     * @return array<string, mixed>
     */
    public function billAgreementUpdate(string $referenceId, string $action = 'Cancel', string $note = ''): array
    {
        $fields = [
            'METHOD'                 => 'BillAgreementUpdate',
            'REFERENCEID'            => $referenceId,
            'BILLINGAGREEMENTSTATUS' => $action === 'Cancel' ? 'Canceled' : 'Active',
        ];

        if ($note !== '') {
            $fields['DESC'] = substr($note, 0, 127);
        }

        return $this->request($fields);
    }

    /**
     * Get details of a saved BAID. Returns ACK=Success and BILLINGAGREEMENTSTATUS
     * (Active|Canceled) when the BAID exists.
     *
     * @return array<string, mixed>
     */
    public function getBillAgreementDetails(string $referenceId): array
    {
        return $this->request([
            'METHOD'      => 'BillAgreementUpdate',
            'REFERENCEID' => $referenceId,
        ]);
    }

    /**
     * SetExpressCheckout — start the legacy NVP Express Checkout flow. Used to
     * create a Billing Agreement (BAID) for subscription products.
     *
     * Returns ACK=Success + TOKEN. The caller redirects the customer to:
     *   https://www.{sandbox.}paypal.com/cgi-bin/webscr?cmd=_express-checkout&token={TOKEN}
     * After approval, PayPal redirects to ReturnURL with TOKEN + PayerID query params.
     *
     * @param  array<int, array{name: string, amt: float, qty: int}>  $items
     * @return array<string, mixed>
     */
    public function setExpressCheckout(
        float $amount,
        string $currencyCode,
        string $returnUrl,
        string $cancelUrl,
        string $invoiceNumber = '',
        string $billingDescription = 'Automatic payments for subscription product',
        string $custom = '',
        array $items = []
    ): array {
        $fields = [
            'METHOD'                         => 'SetExpressCheckout',
            'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
            'PAYMENTREQUEST_0_AMT'           => number_format($amount, 2, '.', ''),
            'PAYMENTREQUEST_0_CURRENCYCODE'  => $currencyCode,
            'RETURNURL'                      => $returnUrl,
            'CANCELURL'                      => $cancelUrl,
            'L_BILLINGTYPE0'                 => 'MerchantInitiatedBillingSingleAgreement',
            'L_BILLINGAGREEMENTDESCRIPTION0' => substr(strip_tags($billingDescription), 0, 127),
            'NOSHIPPING'                     => '1',
            'ALLOWNOTE'                      => '0',
        ];

        if ($invoiceNumber !== '') {
            $fields['PAYMENTREQUEST_0_INVNUM'] = substr($invoiceNumber, 0, 127);
        }

        if ($custom !== '') {
            $fields['PAYMENTREQUEST_0_CUSTOM'] = substr($custom, 0, 255);
        }

        $i         = 0;
        $itemTotal = 0.0;

        foreach ($items as $item) {
            $name = (string) ($item['name'] ?? '');
            $amt  = (float) ($item['amt'] ?? 0);
            $qty  = (int) ($item['qty'] ?? 1);

            if ($name === '' || $amt <= 0 || $qty <= 0) {
                continue;
            }

            $fields['L_PAYMENTREQUEST_0_NAME' . $i] = substr($name, 0, 126);
            $fields['L_PAYMENTREQUEST_0_AMT' . $i]  = number_format($amt, 2, '.', '');
            $fields['L_PAYMENTREQUEST_0_QTY' . $i]  = (string) $qty;
            $itemTotal += $amt * $qty;
            $i++;
        }

        if ($itemTotal > 0) {
            $fields['PAYMENTREQUEST_0_ITEMAMT'] = number_format($itemTotal, 2, '.', '');
        }

        return $this->request($fields);
    }

    /**
     * GetExpressCheckoutDetails — verify a TOKEN after customer approval and
     * read the PayerID + email + buyer details. Called between Return URL hit
     * and DoExpressCheckoutPayment.
     *
     * @return array<string, mixed>
     */
    public function getExpressCheckoutDetails(string $token): array
    {
        return $this->request([
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN'  => $token,
        ]);
    }

    /**
     * DoExpressCheckoutPayment — finalize the Express Checkout payment AND
     * create the Billing Agreement. ACK=Success returns BILLINGAGREEMENTID
     * (the BAID) and PAYMENTINFO_0_TRANSACTIONID for the first-cycle charge.
     *
     * @return array<string, mixed>
     */
    public function doExpressCheckoutPayment(
        string $token,
        string $payerId,
        float $amount,
        string $currencyCode,
        string $invoiceNumber = '',
        string $custom = ''
    ): array {
        $fields = [
            'METHOD'                         => 'DoExpressCheckoutPayment',
            'TOKEN'                          => $token,
            'PAYERID'                        => $payerId,
            'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
            'PAYMENTREQUEST_0_AMT'           => number_format($amount, 2, '.', ''),
            'PAYMENTREQUEST_0_CURRENCYCODE'  => $currencyCode,
            'PAYMENTREQUEST_0_ITEMAMT'       => number_format($amount, 2, '.', ''),
        ];

        if ($invoiceNumber !== '') {
            $fields['PAYMENTREQUEST_0_INVNUM'] = substr($invoiceNumber, 0, 127);
        }

        if ($custom !== '') {
            $fields['PAYMENTREQUEST_0_CUSTOM'] = substr($custom, 0, 255);
        }

        return $this->request($fields);
    }

    /**
     * Build the customer-facing approval URL for a TOKEN returned by
     * SetExpressCheckout. Customer is redirected here to authorize the BAID.
     */
    public function getApprovalUrl(string $token): string
    {
        $base = $this->sandbox
            ? 'https://www.sandbox.paypal.com'
            : 'https://www.paypal.com';

        return $base . '/cgi-bin/webscr?cmd=_express-checkout&token=' . urlencode($token);
    }

    /**
     * @param  array<string, scalar>  $fields
     * @return array<string, mixed>
     */
    private function request(array $fields): array
    {
        if (!$this->isConfigured()) {
            return [
                'ACK'             => 'Failure',
                'L_ERRORCODE0'    => '_PLUGIN_NO_CREDS',
                'L_SHORTMESSAGE0' => 'NVP credentials not configured',
                'L_LONGMESSAGE0'  => 'api_username, api_password, and api_signature are required for the NVP renewal path. Configure them in payment_paypal plugin params.',
            ];
        }

        $payload = array_merge([
            'USER'      => $this->apiUsername,
            'PWD'       => $this->apiPassword,
            'SIGNATURE' => $this->apiSignature,
            'VERSION'   => self::SIGNATURE_VERSION,
        ], $fields);

        $body = http_build_query($payload, '', '&');

        if ($this->debug) {
            $this->log('NVP request: ' . ($fields['METHOD'] ?? 'unknown') . ' to ' . $this->endpoint());
        }

        $ch = curl_init($this->endpoint());

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: */*',
            ],
        ]);

        $rawResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        $httpStatus  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($rawResponse === false || $rawResponse === '') {
            $this->log('NVP transport error: ' . $curlError, \Joomla\CMS\Log\Log::ERROR);

            return [
                'ACK'             => 'Failure',
                'L_ERRORCODE0'    => '_TRANSPORT',
                'L_SHORTMESSAGE0' => 'PayPal NVP unreachable',
                'L_LONGMESSAGE0'  => $curlError ?: 'No response from PayPal NVP API',
                '_http_status'    => $httpStatus,
            ];
        }

        parse_str((string) $rawResponse, $parsed);

        if ($this->debug) {
            $this->log(\sprintf(
                'NVP response: ACK=%s ERR=%s SHORT=%s',
                $parsed['ACK'] ?? '?',
                $parsed['L_ERRORCODE0'] ?? '',
                $parsed['L_SHORTMESSAGE0'] ?? ''
            ));
        }

        $parsed['_http_status'] = $httpStatus;

        return $parsed;
    }

    public static function isSuccess(array $response): bool
    {
        $ack = strtoupper((string) ($response['ACK'] ?? ''));

        return $ack === 'SUCCESS' || $ack === 'SUCCESSWITHWARNING';
    }

    public static function getErrorMessage(array $response): string
    {
        $short = (string) ($response['L_SHORTMESSAGE0'] ?? '');
        $long  = (string) ($response['L_LONGMESSAGE0'] ?? '');
        $code  = (string) ($response['L_ERRORCODE0'] ?? '');

        $parts = [];

        if ($code !== '') {
            $parts[] = '[' . $code . ']';
        }

        if ($short !== '') {
            $parts[] = $short;
        }

        if ($long !== '' && $long !== $short) {
            $parts[] = $long;
        }

        return $parts !== [] ? implode(' ', $parts) : 'Unknown NVP error';
    }
}
