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

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\DatabaseInterface;

// No direct access
\defined('_JEXEC') or die;

/**
 * Utilities Helper class for J2Commerce
 *
 * @since  6.0.0
 */
class UtilitiesHelper
{
    /**
     * Singleton instance
     *
     * @var   UtilitiesHelper|null
     * @since 6.0.0
     */
    protected static ?UtilitiesHelper $instance = null;

    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Flag to track if cache has been cleaned this request
     *
     * @var   bool
     * @since 6.0.0
     */
    private static bool $isCacheCleaned = false;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  UtilitiesHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): UtilitiesHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

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

    // =========================================================================
    // CACHE MANAGEMENT METHODS
    // =========================================================================

    /**
     * Clear J2Commerce and related caches
     *
     * Only clears cache once per request to avoid redundant operations.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function clearCache(): void
    {
        if (self::$isCacheCleaned) {
            return;
        }

        try {
            $cache = Factory::getContainer()->get('cache.controller.factory')
                ->createCacheController('callback', ['defaultgroup' => 'com_j2commerce']);
            $cache->clean('com_j2commerce');
            $cache->clean('com_content');

            self::$isCacheCleaned = true;
        } catch (\Exception $e) {
            // Silently fail - cache clearing is not critical
        }
    }

    /**
     * Send no-cache headers to prevent browser caching
     *
     * @return  bool  True if headers were sent, false if headers were already sent
     *
     * @since   6.0.0
     */
    public static function sendNoCacheHeaders(): bool
    {
        if (headers_sent()) {
            return false;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        return true;
    }

    // =========================================================================
    // JSON METHODS
    // =========================================================================

    /**
     * Check if a string is valid JSON
     *
     * @param   string  $string  The string to validate
     *
     * @return  bool  True if the string is valid JSON
     *
     * @since   6.0.0
     */
    public static function isJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    // =========================================================================
    // ARRAY/STRING CONVERSION METHODS
    // =========================================================================

    /**
     * Convert an object or array to a comma-separated string
     *
     * @param   mixed  $data  The data to convert (array or object)
     *
     * @return  string  Comma-separated values
     *
     * @since   6.0.0
     */
    public static function toCsv(mixed $data): string
    {
        if ($data === null) {
            return '';
        }

        if (\is_object($data)) {
            $array = (array) $data;
        } elseif (\is_array($data)) {
            $array = $data;
        } else {
            $array = [(string) $data];
        }

        return implode(',', $array);
    }

    /**
     * Alias for toCsv() - snake_case version for backwards compatibility
     *
     * @param   mixed  $data  The data to convert (array or object)
     *
     * @return  string  Comma-separated values
     *
     * @since   6.0.0
     * @see     toCsv()
     */
    public function to_csv(mixed $data): string
    {
        return self::toCsv($data);
    }

    /**
     * Convert an array to a string representation
     *
     * Handles nested arrays recursively.
     *
     * @param   array|null   $array       The array to convert
     * @param   string       $innerGlue   Glue for key=value pairs (default: '=')
     * @param   string       $outerGlue   Glue between items (default: newline)
     * @param   bool         $keepOuterKey  Whether to include outer keys in output
     *
     * @return  string  The string representation
     *
     * @since   6.0.0
     */
    public static function toString(
        ?array $array = null,
        string $innerGlue = '=',
        string $outerGlue = "\n",
        bool $keepOuterKey = false
    ): string {
        if ($array === null || empty($array)) {
            return '';
        }

        $output = [];

        foreach ($array as $key => $item) {
            if (\is_array($item)) {
                if ($keepOuterKey) {
                    $output[] = (string) $key;
                }
                // Recursive call for nested arrays
                $output[] = self::toString($item, $innerGlue, $outerGlue, $keepOuterKey);
            } else {
                $output[] = (string) $item;
            }
        }

        return implode($outerGlue, $output);
    }

    /**
     * Convert an array of errors to a string
     *
     * Alias for toString() for semantic clarity when dealing with error arrays.
     *
     * @param   array  $errors  Array of error messages
     *
     * @return  string  The errors as a string
     *
     * @since   6.0.0
     */
    public static function errorsToString(array $errors): string
    {
        return self::toString($errors);
    }

    // =========================================================================
    // STRING MANIPULATION METHODS
    // =========================================================================

    /**
     * Limit a string to a specified number of characters
     *
     * Strips HTML tags and normalizes whitespace before truncating.
     *
     * @param   string  $str      The string to limit
     * @param   int     $limit    Maximum character length (default: 150)
     * @param   string  $endChar  Characters to append when truncated (default: '...')
     *
     * @return  string  The truncated string
     *
     * @since   6.0.0
     */
    public static function characterLimit(string $str, int $limit = 150, string $endChar = '...'): string
    {
        $str = trim($str);

        if ($str === '') {
            return $str;
        }

        // Strip HTML tags
        $str = strip_tags($str);

        // Normalize whitespace
        $str = preg_replace(["/\r|\n/u", "/\t/u", "/\s\s+/u"], [' ', ' ', ' '], $str) ?? $str;

        if (\strlen($str) > $limit) {
            return rtrim(substr($str, 0, $limit)) . $endChar;
        }

        return $str;
    }

    /**
     * Clean HTML entities in text
     *
     * @param   string  $text  The text to clean
     *
     * @return  string  The cleaned text with HTML entities encoded
     *
     * @since   6.0.0
     */
    public static function cleanHtml(string $text): string
    {
        return htmlentities($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Generate a URL-safe ID from a string
     *
     * Removes parentheses, periods, and converts to URL-safe format.
     *
     * @param   string  $string  The string to convert
     *
     * @return  string  URL-safe string
     *
     * @since   6.0.0
     */
    public static function generateId(string $string): string
    {
        if (empty($string)) {
            return '';
        }

        $string = str_replace(['(', ')', '.'], '', $string);

        return OutputFilter::stringURLSafe($string);
    }

    // =========================================================================
    // ARRAY CLEANING METHODS
    // =========================================================================

    /**
     * Clean an array of integers and quote them for database use
     *
     * @param   array<mixed>  $array  The array to clean
     *
     * @return  array<int|string>  Array of cleaned and quoted integers
     *
     * @since   6.0.0
     */
    public static function cleanIntArray(array $array): array
    {
        $db      = self::getDatabase();
        $results = [];

        foreach ($array as $id) {
            $clean = (int) $id;

            if (!\in_array($db->quote((string) $clean), $results, true)) {
                $results[] = $db->quote((string) $clean);
            }
        }

        return $results;
    }

    // =========================================================================
    // CONTEXT METHODS
    // =========================================================================

    /**
     * Get the current context string for state management
     *
     * @param   string  $prefix  Optional prefix to append to the context
     *
     * @return  string  The context string (e.g., 'j2commerce.site.products.display')
     *
     * @since   6.0.0
     */
    public static function getContext(string $prefix = ''): string
    {
        $app     = Factory::getApplication();
        $context = ['j2commerce'];

        // Determine client (site or admin)
        if ($app->isClient('site')) {
            $context[] = 'site';
        } else {
            $context[] = 'admin';
        }

        // Add view and task
        $input = $app->getInput();
        $view  = $input->getCmd('view', '');
        $task  = $input->getCmd('task', '');

        if (!empty($view)) {
            $context[] = $view;
        }

        if (!empty($task)) {
            $context[] = $task;
        }

        return implode('.', $context) . $prefix;
    }

    // =========================================================================
    // DATE/TIME METHODS
    // =========================================================================

    /**
     * Get the current date/time formatted according to options
     *
     * @param   bool   $local    Whether to use local timezone
     * @param   array  $options  Options array:
     *                           - 'formatted': bool - Whether to apply custom format
     *                           - 'format': string - PHP date format (default: 'Y-m-d')
     *
     * @return  string  The formatted date string
     *
     * @since   6.0.0
     */
    public static function getFormattedDate(bool $local = true, array $options = []): string
    {
        $config = Factory::getApplication()->getConfig();
        $tz     = $config->get('offset', 'UTC');
        $date   = Factory::getDate('now', $tz);

        // Default to SQL formatted date
        $result = $date->toSql($local);

        if (!empty($options['formatted'])) {
            $format = $options['format'] ?? 'Y-m-d';
            $result = $date->format($format, $local);
        }

        return $result;
    }

    /**
     * Convert a UTC date to the current timezone
     *
     * @param   string  $date    The UTC date string
     * @param   string  $format  Output format (default: 'Y-m-d H:i:s')
     *
     * @return  string  The converted date or null date if empty
     *
     * @since   6.0.0
     */
    public static function convertUtcToCurrent(string $date, string $format = 'Y-m-d H:i:s'): string
    {
        $db       = self::getDatabase();
        $nullDate = $db->getNullDate();

        if (empty($date) || $date === $nullDate) {
            return $nullDate;
        }

        $config = Factory::getApplication()->getConfig();
        $tz     = $config->get('offset', 'UTC');

        $fromDate = Factory::getDate($date, 'UTC');
        $timezone = new \DateTimeZone($tz);
        $fromDate->setTimezone($timezone);

        return $fromDate->format($format, true);
    }

    /**
     * Convert a date from the current timezone to UTC
     *
     * @param   string  $date    The local date string
     * @param   string  $format  Output format (default: 'Y-m-d H:i:s')
     *
     * @return  string  The converted UTC date or null date if empty
     *
     * @since   6.0.0
     */
    public static function convertCurrentToUtc(string $date, string $format = 'Y-m-d H:i:s'): string
    {
        $db       = self::getDatabase();
        $nullDate = $db->getNullDate();

        if (empty($date) || $date === $nullDate) {
            return $nullDate;
        }

        $config = Factory::getApplication()->getConfig();
        $tz     = $config->get('offset', 'UTC');

        $fromDate = Factory::getDate($date, $tz);
        $timezone = new \DateTimeZone('UTC');
        $fromDate->setTimezone($timezone);

        return $fromDate->format($format);
    }

    // =========================================================================
    // MENU METHODS
    // =========================================================================

    /**
     * Get the active menu item ID
     *
     * @return  int  The active menu item ID or 0 if not found
     *
     * @since   6.0.0
     */
    public static function getActiveMenuId(): int
    {
        try {
            $app = Factory::getApplication();

            // Only works on site application
            if (!$app->isClient('site')) {
                return 0;
            }

            $menu   = $app->getMenu();
            $active = $menu->getActive();

            return $active ? (int) $active->id : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    // =========================================================================
    // TEXT SANITIZATION METHODS
    // =========================================================================

    /**
     * Sanitize text by removing unwanted content
     *
     * @param   string  $str  The string to sanitize
     *
     * @return  string  The sanitized string
     *
     * @since   6.0.0
     */
    public static function textSanitize(string $str): string
    {
        return self::removeUnwantedText($str);
    }

    /**
     * Remove unwanted text including HTML, encoded characters, and excessive whitespace
     *
     * @param   string  $str           The string to clean
     * @param   bool    $keepNewlines  Whether to preserve newlines (default: true)
     *
     * @return  string  The cleaned string
     *
     * @since   6.0.0
     */
    public static function removeUnwantedText(string $str, bool $keepNewlines = true): string
    {
        $filtered = self::convertUtf8($str);

        // Strip HTML if present
        if (str_contains($filtered, '<')) {
            $filtered = self::stripAllTags($filtered, false);

            // Prevent functional tags from being created by newline stripping
            $filtered = str_replace("<\n", "&lt;\n", $filtered);
        }

        // Remove newlines if requested
        if (!$keepNewlines) {
            $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered) ?? $filtered;
        }

        $filtered = trim($filtered);

        // Remove URL-encoded octets
        $found = false;

        while (preg_match('/%[a-f0-9]{2}/i', $filtered, $match)) {
            $filtered = str_replace($match[0], '', $filtered);
            $found    = true;
        }

        if ($found) {
            $filtered = trim(preg_replace('/ +/', ' ', $filtered) ?? $filtered);
        }

        return $filtered;
    }

    /**
     * Convert a string to valid UTF-8
     *
     * @param   string  $string  The string to convert
     * @param   bool    $strip   Whether to strip invalid characters (default: false)
     *
     * @return  string  The converted string
     *
     * @since   6.0.0
     */
    public static function convertUtf8(string $string, bool $strip = false): string
    {
        // Check for UTF-8 support in PCRE
        static $utf8Pcre = null;

        if ($utf8Pcre === null) {
            $utf8Pcre = @preg_match('/^./u', 'a');
        }

        // Return as-is if PCRE doesn't support UTF-8
        if (!$utf8Pcre) {
            return $string;
        }

        // Check if string is already valid UTF-8
        if (@preg_match('/^./us', $string) === 1) {
            return $string;
        }

        // Attempt to strip invalid characters if requested
        if ($strip && \function_exists('iconv')) {
            $converted = @iconv('utf-8', 'utf-8', $string);

            return $converted !== false ? $converted : '';
        }

        return '';
    }

    /**
     * Strip all HTML tags including script and style content
     *
     * @param   string  $string        The string to strip
     * @param   bool    $removeBreaks  Whether to remove line breaks (default: false)
     *
     * @return  string  The stripped string
     *
     * @since   6.0.0
     */
    public static function stripAllTags(string $string, bool $removeBreaks = false): string
    {
        // Remove script and style tags with their content
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string) ?? $string;

        // Strip remaining tags
        $string = strip_tags($string);

        // Remove line breaks if requested
        if ($removeBreaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string) ?? $string;
        }

        return trim($string);
    }

    // =========================================================================
    // QUANTITY FORMATTING METHODS
    // =========================================================================

    public function stock_qty(float|int $qty): int
    {
        return self::formatStockQuantity($qty);
    }

    public static function formatStockQuantity(float|int $qty): int
    {
        // Allow plugins to modify the quantity
        $app        = Factory::getApplication();
        $dispatcher = $app->getDispatcher();

        // Trigger the event
        $event = new \Joomla\CMS\Event\GenericEvent('onJ2CommerceFilterQuantity', ['qty' => &$qty]);
        $dispatcher->dispatch('onJ2CommerceFilterQuantity', $event);

        return (int) $qty;
    }

    // =========================================================================
    // WORLD CURRENCIES REFERENCE DATA
    // =========================================================================

    /**
     * Get a list of world currencies with their names
     *
     * This provides reference data for currency setup and validation.
     *
     * @return  array<string, string>  Associative array of currency code => name
     *
     * @since   6.0.0
     */
    public static function getWorldCurrencies(): array
    {
        return [
            'USD' => 'United States Dollar',
            'EUR' => 'Euro Member Countries',
            'GBP' => 'United Kingdom Pound',
            'AUD' => 'Australia Dollar',
            'NZD' => 'New Zealand Dollar',
            'CHF' => 'Switzerland Franc',
            'RUB' => 'Russia Ruble',
            'ALL' => 'Albania Lek',
            'AED' => 'Emirati Dirham',
            'AFN' => 'Afghanistan Afghani',
            'ARS' => 'Argentina Peso',
            'AWG' => 'Aruba Guilder',
            'AZN' => 'Azerbaijan New Manat',
            'BSD' => 'Bahamas Dollar',
            'BBD' => 'Barbados Dollar',
            'BDT' => 'Bangladeshi Taka',
            'BYN' => 'Belarus Ruble',
            'BZD' => 'Belize Dollar',
            'BMD' => 'Bermuda Dollar',
            'BOB' => 'Bolivia Boliviano',
            'BAM' => 'Bosnia and Herzegovina Convertible Marka',
            'BWP' => 'Botswana Pula',
            'BGN' => 'Bulgaria Lev',
            'BRL' => 'Brazil Real',
            'BND' => 'Brunei Darussalam Dollar',
            'KHR' => 'Cambodia Riel',
            'CAD' => 'Canada Dollar',
            'KYD' => 'Cayman Islands Dollar',
            'CLP' => 'Chile Peso',
            'CNY' => 'China Yuan Renminbi',
            'COP' => 'Colombia Peso',
            'CRC' => 'Costa Rica Colon',
            'HRK' => 'Croatia Kuna',
            'CUP' => 'Cuba Peso',
            'CZK' => 'Czech Republic Koruna',
            'DKK' => 'Denmark Krone',
            'DOP' => 'Dominican Republic Peso',
            'XCD' => 'East Caribbean Dollar',
            'EGP' => 'Egypt Pound',
            'SVC' => 'El Salvador Colon',
            'EEK' => 'Estonia Kroon',
            'FKP' => 'Falkland Islands (Malvinas) Pound',
            'FJD' => 'Fiji Dollar',
            'GHC' => 'Ghana Cedis',
            'GIP' => 'Gibraltar Pound',
            'GTQ' => 'Guatemala Quetzal',
            'GGP' => 'Guernsey Pound',
            'GYD' => 'Guyana Dollar',
            'HNL' => 'Honduras Lempira',
            'HKD' => 'Hong Kong Dollar',
            'HUF' => 'Hungary Forint',
            'ISK' => 'Iceland Krona',
            'INR' => 'India Rupee',
            'IDR' => 'Indonesia Rupiah',
            'IRR' => 'Iran Rial',
            'IMP' => 'Isle of Man Pound',
            'ILS' => 'Israel Shekel',
            'JMD' => 'Jamaica Dollar',
            'JPY' => 'Japan Yen',
            'JEP' => 'Jersey Pound',
            'KZT' => 'Kazakhstan Tenge',
            'KPW' => 'Korea (North) Won',
            'KRW' => 'Korea (South) Won',
            'KGS' => 'Kyrgyzstan Som',
            'LAK' => 'Laos Kip',
            'LVL' => 'Latvia Lat',
            'LBP' => 'Lebanon Pound',
            'LRD' => 'Liberia Dollar',
            'LTL' => 'Lithuania Litas',
            'MKD' => 'Macedonia Denar',
            'MYR' => 'Malaysia Ringgit',
            'MUR' => 'Mauritius Rupee',
            'MXN' => 'Mexico Peso',
            'MNT' => 'Mongolia Tughrik',
            'MZN' => 'Mozambique Metical',
            'NAD' => 'Namibia Dollar',
            'NPR' => 'Nepal Rupee',
            'ANG' => 'Netherlands Antilles Guilder',
            'NIO' => 'Nicaragua Cordoba',
            'NGN' => 'Nigeria Naira',
            'NOK' => 'Norway Krone',
            'OMR' => 'Oman Rial',
            'PKR' => 'Pakistan Rupee',
            'PAB' => 'Panama Balboa',
            'PYG' => 'Paraguay Guarani',
            'PEN' => 'Peru Nuevo Sol',
            'PHP' => 'Philippines Peso',
            'PLN' => 'Poland Zloty',
            'QAR' => 'Qatar Riyal',
            'RON' => 'Romania New Leu',
            'SHP' => 'Saint Helena Pound',
            'SAR' => 'Saudi Arabia Riyal',
            'RSD' => 'Serbia Dinar',
            'SCR' => 'Seychelles Rupee',
            'SGD' => 'Singapore Dollar',
            'SBD' => 'Solomon Islands Dollar',
            'SOS' => 'Somalia Shilling',
            'ZAR' => 'South Africa Rand',
            'LKR' => 'Sri Lanka Rupee',
            'SEK' => 'Sweden Krona',
            'SRD' => 'Suriname Dollar',
            'SYP' => 'Syria Pound',
            'SDG' => 'Sudanese Pound',
            'TWD' => 'Taiwan New Dollar',
            'THB' => 'Thailand Baht',
            'TTD' => 'Trinidad and Tobago Dollar',
            'TRY' => 'Turkey Lira',
            'TRL' => 'Turkey Lira (Old)',
            'TVD' => 'Tuvalu Dollar',
            'UAH' => 'Ukraine Hryvna',
            'UYU' => 'Uruguay Peso',
            'UZS' => 'Uzbekistan Som',
            'VEF' => 'Venezuela Bolivar',
            'VND' => 'Viet Nam Dong',
            'YER' => 'Yemen Rial',
            'ZWD' => 'Zimbabwe Dollar',
        ];
    }

    // =========================================================================
    // RESET METHOD (for testing)
    // =========================================================================

    /**
     * Reset the helper state (useful for testing)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function reset(): void
    {
        self::$db             = null;
        self::$isCacheCleaned = false;
    }
}
