<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_paypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Helper;

\defined('_JEXEC') or die;

final class PayPalCurrencyHelper
{
    /**
     * @var array<string>
     */
    private const ACCEPTED_CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF',
        'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP',
        'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD',
    ];

    /**
     * @var array<string>
     */
    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW', 'TWD', 'HUF', 'CLP', 'ISK'];

    public static function isValid(string $currencyCode): bool
    {
        return \in_array(strtoupper($currencyCode), self::ACCEPTED_CURRENCIES, true);
    }

    public static function decimals(string $currencyCode): int
    {
        return \in_array(strtoupper($currencyCode), self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    /** number_format, not round(): one call for every PayPal amount, so a tie cannot round two ways. */
    public static function format(float $amount, string $currencyCode): string
    {
        return number_format($amount, self::decimals($currencyCode), '.', '');
    }

    /** Compared as the formatted strings PayPal is sent, so only the exact charged figure matches. */
    public static function sameAmount(float $charged, float $expected, string $currencyCode): bool
    {
        return self::format($charged, $currencyCode) === self::format($expected, $currencyCode);
    }

    /** At or above the target at currency precision; a sum of ledger rows can sit a float hair under it. */
    public static function covers(float $amount, float $target, string $currencyCode): bool
    {
        return $amount > $target || self::sameAmount($amount, $target, $currencyCode);
    }

    public static function orderCurrency(object $order): string
    {
        return strtoupper(trim((string) ($order->currency_code ?? ''))) ?: 'USD';
    }

    /**
     * @return array<string>
     */
    public static function getAcceptedCurrencies(): array
    {
        return self::ACCEPTED_CURRENCIES;
    }
}
