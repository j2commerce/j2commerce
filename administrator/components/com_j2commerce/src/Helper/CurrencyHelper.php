<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// No direct access
\defined('_JEXEC') or die;

/**
 * Currency Helper class for J2Commerce
 *
 * Provides currency formatting, conversion, and management functionality.
 * This is a static helper class that caches currency data for performance.
 *
 * @since  6.0.0
 */
class CurrencyHelper
{
    /**
     * Singleton instance
     *
     * @var   CurrencyHelper|null
     * @since 6.0.0
     */
    protected static ?CurrencyHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  CurrencyHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): CurrencyHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Current active currency code
     *
     * @var   string
     * @since 6.0.0
     */
    private static string $currentCode = '';

    /**
     * Cached currencies data (indexed by currency_code)
     *
     * @var   array<string, array<string, mixed>>
     * @since 6.0.0
     */
    private static array $currencies = [];

    /**
     * Flag to track if currencies have been loaded
     *
     * @var   bool
     * @since 6.0.0
     */
    private static bool $initialized = false;


    private static bool $initializing = false;

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    private static function setCurrencyInternal(string $currencyCode): void
    {
        // Ensure currencies are loaded but do NOT call initialize()
        self::loadCurrencies();

        if (!self::has($currencyCode)) {
            return;
        }

        self::$currentCode = $currencyCode;

        $session = Factory::getApplication()->getSession();
        if ($session->get('j2commerce_currency', '') !== $currencyCode) {
            $session->set('j2commerce_currency', $currencyCode);
        }
    }

    /**
     * Initialize the currency helper by loading all enabled currencies
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private static function initialize(): void
    {
        if (self::$initialized || self::$initializing) {
            return;
        }

        self::$initializing = true;

        // Load all enabled currencies
        self::loadCurrencies();

        $app     = Factory::getApplication();
        $session = $app->getSession();
        $input   = $app->getInput();

        $requestedCurrency = $input->getString('currency', '');
        $params            = ComponentHelper::getParams('com_j2commerce');
        $defaultCurrency   = $params->get('config_currency', 'USD');

        if (!empty($requestedCurrency) && self::has($requestedCurrency)) {
            self::setCurrencyInternal($requestedCurrency);
        } elseif ($session->has('j2commerce_currency') && self::has($session->get('j2commerce_currency', ''))) {
            $sessionCurrency = $session->get('j2commerce_currency', '');

            // If the config default changed, update session to match
            if ($sessionCurrency !== $defaultCurrency && self::has($defaultCurrency)) {
                self::setCurrencyInternal($defaultCurrency);
            } else {
                self::$currentCode = $sessionCurrency;
            }
        } else {
            if (self::has($defaultCurrency)) {
                self::setCurrencyInternal($defaultCurrency);
            } elseif (!empty(self::$currencies)) {
                $firstCurrency = array_key_first(self::$currencies);
                self::setCurrencyInternal($firstCurrency);
            }
        }

        self::$initialized  = true;
        self::$initializing = false;
    }

    /**
     * Load all enabled currencies from the database
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private static function loadCurrencies(): void
    {
        if (!empty(self::$currencies)) {
            return;
        }

        $db = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_currencies'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        foreach ($rows as $row) {
            self::$currencies[$row->currency_code] = (array) $row;
        }
    }

    /**
     * Reset the helper state (useful for testing)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function reset(): void
    {
        self::$currencies = [];
        self::$currentCode = '';
        self::$initialized = false;
    }

    // =========================================================================
    // CURRENCY SETTING/GETTING METHODS
    // =========================================================================

    /**
     * Set the active currency
     *
     * @param   string  $currencyCode  The currency code to set (e.g., 'USD', 'EUR')
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function setCurrency(string $currencyCode): void
    {
        // Only ensure currencies are available
        self::loadCurrencies();

        if (!self::has($currencyCode)) {
            return;
        }

        self::$currentCode = $currencyCode;

        $session = \Joomla\CMS\Factory::getApplication()->getSession();
        if ($session->get('j2commerce_currency', '') !== $currencyCode) {
            $session->set('j2commerce_currency', $currencyCode);
        }
    }

    /**
     * Get the current active currency code
     *
     * @return  string  The current currency code
     *
     * @since   6.0.0
     */
    public static function getCode(): string
    {
        self::initialize();

        return self::$currentCode;
    }

    /**
     * Check if a currency exists and is enabled
     *
     * @param   string  $currencyCode  The currency code to check
     *
     * @return  bool  True if currency exists
     *
     * @since   6.0.0
     */
    public static function has(string $currencyCode): bool
    {
        self::loadCurrencies();

        return isset(self::$currencies[$currencyCode]);
    }

    /**
     * Get all enabled currencies
     *
     * @return  array<string, array<string, mixed>>  Array of currencies indexed by code
     *
     * @since   6.0.0
     */
    public static function getAll(): array
    {
        self::initialize();

        return self::$currencies;
    }

    // =========================================================================
    // CURRENCY INFORMATION METHODS
    // =========================================================================

    /**
     * Get the currency ID
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  int  The currency ID or 0 if not found
     *
     * @since   6.0.0
     */
    public static function getId(string $currencyCode = ''): int
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (int) (self::$currencies[$currencyCode]['j2commerce_currency_id'] ?? 0);
        }

        return 0;
    }

    /**
     * Get the currency symbol
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The currency symbol or empty string
     *
     * @since   6.0.0
     */
    public static function getSymbol(string $currencyCode = ''): string
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (string) (self::$currencies[$currencyCode]['currency_symbol'] ?? '');
        }

        return '';
    }

    /**
     * Get the currency symbol position ('pre' or 'post')
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The position ('pre' or 'post')
     *
     * @since   6.0.0
     */
    public static function getSymbolPosition(string $currencyCode = ''): string
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (string) (self::$currencies[$currencyCode]['currency_position'] ?? 'pre');
        }

        return 'pre';
    }

    /**
     * Get the number of decimal places for a currency
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  int  The number of decimal places
     *
     * @since   6.0.0
     */
    public static function getDecimalPlace(string $currencyCode = ''): int
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (int) (self::$currencies[$currencyCode]['currency_num_decimals'] ?? 2);
        }

        return 2;
    }

    /**
     * Get the decimal separator character
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The decimal separator
     *
     * @since   6.0.0
     */
    public static function getDecimalSeparator(string $currencyCode = ''): string
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (string) (self::$currencies[$currencyCode]['currency_decimal'] ?? '.');
        }

        return '.';
    }

    /**
     * Get the thousands separator character
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The thousands separator
     *
     * @since   6.0.0
     */
    public static function getThousandsSeparator(string $currencyCode = ''): string
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (string) (self::$currencies[$currencyCode]['currency_thousands'] ?? ',');
        }

        return ',';
    }

    /**
     * Get the thousands separator character (legacy alias for backwards compatibility)
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The thousands separator
     *
     * @since   6.0.0
     * @deprecated  Use getThousandsSeparator() instead
     */
    public static function getThousandSymbol(string $currencyCode = ''): string
    {
        return self::getThousandsSeparator($currencyCode);
    }

    /**
     * Get the exchange rate value for a currency
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  float  The exchange rate value
     *
     * @since   6.0.0
     */
    public static function getValue(string $currencyCode = ''): float
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (float) (self::$currencies[$currencyCode]['currency_value'] ?? 1.0);
        }

        return 1.0;
    }

    /**
     * Get the currency title
     *
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     *
     * @return  string  The currency title
     *
     * @since   6.0.0
     */
    public static function getTitle(string $currencyCode = ''): string
    {
        self::initialize();

        if (empty($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        if (isset(self::$currencies[$currencyCode])) {
            return (string) (self::$currencies[$currencyCode]['currency_title'] ?? '');
        }

        return '';
    }

    // =========================================================================
    // FORMATTING AND CONVERSION METHODS
    // =========================================================================

    /**
     * Format a number as a currency string
     *
     * @param   float   $number        The number to format
     * @param   string  $currencyCode  Optional currency code (uses current if empty)
     * @param   float   $exchangeRate  Optional exchange rate override
     * @param   bool    $format        Whether to apply full formatting (symbol, separators)
     *
     * @return  string  The formatted currency string
     *
     * @since   6.0.0
     */
    public static function format(
        float $number,
        string $currencyCode = '',
        float $exchangeRate = 0.0,
        bool $format = true
    ): string {
        self::initialize();

        // Determine which currency to use
        if (empty($currencyCode) || !self::has($currencyCode)) {
            $currencyCode = self::$currentCode;
        }

        // Get currency properties
        $currency = self::$currencies[$currencyCode] ?? [];
        $currencyPosition = (string) ($currency['currency_position'] ?? 'pre');
        $currencySymbol = (string) ($currency['currency_symbol'] ?? '');
        $decimalPlaces = (int) ($currency['currency_num_decimals'] ?? 2);

        // Determine exchange rate
        if ($exchangeRate > 0) {
            $value = $number * $exchangeRate;
        } else {
            $currencyValue = (float) ($currency['currency_value'] ?? 1.0);
            $value = $currencyValue > 0 ? $number * $currencyValue : $number;
        }

        // Build the formatted string
        $result = '';

        // Add symbol prefix if formatting
        if ($format && $currencyPosition === 'pre') {
            $result .= $currencySymbol;
        }

        // Determine separators
        if ($format) {
            $decimalPoint = (string) ($currency['currency_decimal'] ?? '.');
            $thousandsSeparator = (string) ($currency['currency_thousands'] ?? ',');
        } else {
            // For raw numbers, use standard format
            $decimalPoint = '.';
            $thousandsSeparator = '';
        }

        // Format the number
        $result .= number_format(
            round($value, $decimalPlaces),
            $decimalPlaces,
            $decimalPoint,
            $thousandsSeparator
        );

        // Add symbol suffix if formatting
        if ($format && $currencyPosition === 'post') {
            $result .= $currencySymbol;
        }

        return $result;
    }

    /**
     * Convert a value from one currency to another
     *
     * @param   float   $value  The value to convert
     * @param   string  $from   The source currency code
     * @param   string  $to     The target currency code
     *
     * @return  float  The converted value
     *
     * @since   6.0.0
     */
    public static function convert(float $value, string $from, string $to): float
    {
        self::initialize();

        // Get exchange rates
        $fromRate = self::has($from) ? self::getValue($from) : 0.0;
        $toRate = self::has($to) ? self::getValue($to) : 0.0;

        // Avoid division by zero
        if ($fromRate <= 0) {
            return 0.0;
        }

        return $value * ($toRate / $fromRate);
    }

    // =========================================================================
    // ISO 4217 NUMERIC CODE METHODS
    // =========================================================================

    /**
     * Get all ISO 4217 numeric currency codes
     *
     * @return  array<string, int>  Associative array of currency code => numeric code
     *
     * @since   6.0.0
     */
    public static function getNumericCodes(): array
    {
        return [
            'AFN' => 4,
            'ALL' => 8,
            'DZD' => 12,
            'USD' => 581,
            'EUR' => 336,
            'AOA' => 24,
            'XCD' => 670,
            'ARS' => 32,
            'AMD' => 51,
            'AWG' => 533,
            'AUD' => 798,
            'AZN' => 31,
            'BSD' => 44,
            'BHD' => 48,
            'BDT' => 50,
            'BBD' => 52,
            'BYN' => 112,
            'BZD' => 84,
            'XOF' => 768,
            'BMD' => 60,
            'BTN' => 64,
            'BOB' => 68,
            'BAM' => 70,
            'BWP' => 72,
            'NOK' => 744,
            'BRL' => 76,
            'BND' => 96,
            'BGN' => 100,
            'BIF' => 108,
            'KHR' => 116,
            'XAF' => 178,
            'CAD' => 124,
            'CVE' => 132,
            'KYD' => 136,
            'CLP' => 152,
            'CNY' => 156,
            'COP' => 170,
            'KMF' => 174,
            'NZD' => 772,
            'CRC' => 188,
            'HRK' => 191,
            'CUP' => 192,
            'CYP' => 196,
            'CZK' => 203,
            'CDF' => 180,
            'DKK' => 304,
            'DJF' => 262,
            'DOP' => 214,
            'EGP' => 818,
            'SVC' => 222,
            'ERN' => 232,
            'EEK' => 233,
            'ETB' => 231,
            'FKP' => 238,
            'FJD' => 242,
            'XPF' => 876,
            'GMD' => 270,
            'GEL' => 268,
            'GHS' => 288,
            'GIP' => 292,
            'GTQ' => 320,
            'GNF' => 324,
            'GYD' => 328,
            'HTG' => 332,
            'HNL' => 340,
            'HKD' => 344,
            'HUF' => 348,
            'ISK' => 352,
            'INR' => 356,
            'IDR' => 360,
            'IRR' => 364,
            'IQD' => 368,
            'ILS' => 275,
            'JMD' => 388,
            'JPY' => 392,
            'JOD' => 400,
            'KZT' => 398,
            'KES' => 404,
            'KWD' => 414,
            'KGS' => 417,
            'LAK' => 418,
            'LVL' => 428,
            'LBP' => 422,
            'LSL' => 426,
            'LRD' => 430,
            'LYD' => 434,
            'CHF' => 756,
            'LTL' => 440,
            'MOP' => 446,
            'MKD' => 807,
            'MGA' => 450,
            'MWK' => 454,
            'MYR' => 458,
            'MVR' => 462,
            'MTL' => 470,
            'MRO' => 478,
            'MUR' => 480,
            'MXN' => 484,
            'MDL' => 498,
            'MNT' => 496,
            'MAD' => 732,
            'MZN' => 508,
            'MMK' => 104,
            'NAD' => 516,
            'NPR' => 524,
            'ANG' => 530,
            'NIO' => 558,
            'NGN' => 566,
            'KPW' => 408,
            'OMR' => 512,
            'PKR' => 586,
            'PAB' => 591,
            'PGK' => 598,
            'PYG' => 600,
            'PEN' => 604,
            'PHP' => 608,
            'PLN' => 616,
            'QAR' => 634,
            'RON' => 642,
            'RUB' => 643,
            'RWF' => 646,
            'SHP' => 654,
            'WST' => 882,
            'STD' => 678,
            'SAR' => 682,
            'RSD' => 891,
            'SCR' => 690,
            'SLL' => 694,
            'SGD' => 702,
            'SKK' => 703,
            'SBD' => 90,
            'SOS' => 706,
            'ZAR' => 710,
            'GBP' => 826,
            'KRW' => 410,
            'LKR' => 144,
            'SDG' => 736,
            'SRD' => 740,
            'SZL' => 748,
            'SEK' => 752,
            'SYP' => 760,
            'TWD' => 158,
            'TJS' => 762,
            'TZS' => 834,
            'THB' => 764,
            'TOP' => 776,
            'TTD' => 780,
            'TND' => 788,
            'TRY' => 792,
            'TMM' => 795,
            'UGX' => 800,
            'UAH' => 804,
            'AED' => 784,
            'UYU' => 858,
            'UZS' => 860,
            'VUV' => 548,
            'VEF' => 862,
            'VND' => 704,
            'YER' => 887,
            'ZMK' => 894,
            'ZWD' => 716,
            'BTC' => 999,
        ];
    }

    /**
     * Get numeric currency codes as key-value pairs (code => code)
     *
     * @return  array<string, string>  Array of currency codes
     *
     * @since   6.0.0
     */
    public static function getNumericCurrencies(): array
    {
        $currencies = self::getNumericCodes();
        $result = [];

        foreach ($currencies as $code => $numericCode) {
            $result[$code] = $code;
        }

        return $result;
    }

    /**
     * Get the ISO 4217 numeric code for a currency
     *
     * @param   string  $currencyCode  The alpha-3 currency code
     *
     * @return  int  The numeric code or 0 if not found
     *
     * @since   6.0.0
     */
    public static function getCurrencyNumericCode(string $currencyCode): int
    {
        $codes = self::getNumericCodes();

        return $codes[$currencyCode] ?? 0;
    }

    // =========================================================================
    // CURRENCY SWITCHER / DROPDOWN METHODS
    // =========================================================================

    /**
     * Get currencies for display in a dropdown/switcher
     *
     * @return  array<int, object>  Array of currency objects with code, title, and symbol
     *
     * @since   6.0.0
     */
    public static function getCurrenciesForDropdown(): array
    {
        self::initialize();

        $options = [];

        foreach (self::$currencies as $code => $currency) {
            $option = new \stdClass();
            $option->value = $code;
            $option->text = $currency['currency_title'] . ' (' . $currency['currency_symbol'] . ')';
            $option->code = $code;
            $option->symbol = $currency['currency_symbol'];
            $options[] = $option;
        }

        return $options;
    }

    /**
     * Get a single currency by code
     *
     * @param   string  $currencyCode  The currency code
     *
     * @return  array<string, mixed>|null  The currency data or null if not found
     *
     * @since   6.0.0
     */
    public static function getCurrency(string $currencyCode): ?array
    {
        self::initialize();

        return self::$currencies[$currencyCode] ?? null;
    }

    /**
     * Get a currency by its ID
     *
     * @param   int  $currencyId  The currency ID
     *
     * @return  array<string, mixed>|null  The currency data or null if not found
     *
     * @since   6.0.0
     */
    public static function getCurrencyById(int $currencyId): ?array
    {
        self::initialize();

        foreach (self::$currencies as $currency) {
            if ((int) ($currency['j2commerce_currency_id'] ?? 0) === $currencyId) {
                return $currency;
            }
        }

        return null;
    }
}
