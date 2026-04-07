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

    public static function isValid(string $currencyCode): bool
    {
        return \in_array(strtoupper($currencyCode), self::ACCEPTED_CURRENCIES, true);
    }

    /**
     * @return array<string>
     */
    public static function getAcceptedCurrencies(): array
    {
        return self::ACCEPTED_CURRENCIES;
    }
}
