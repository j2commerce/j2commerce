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
use Joomla\Database\DatabaseInterface;

// No direct access
\defined('_JEXEC') or die;

/**
 * Length Helper class for J2Commerce
 *
 * Provides length unit conversion and formatting functionality.
 * This is a static helper class that caches length data for performance.
 *
 * @since  6.0.0
 */
class LengthHelper
{
    /**
     * Singleton instance
     *
     * @var   LengthHelper|null
     * @since 6.0.0
     */
    protected static ?LengthHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  LengthHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): LengthHelper
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
     * Cached lengths data (indexed by length ID)
     *
     * @var   array<int, array<string, mixed>>
     * @since 6.0.0
     */
    private static array $lengths = [];

    /**
     * Flag to track if lengths have been loaded
     *
     * @var   bool
     * @since 6.0.0
     */
    private static bool $initialized = false;

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

    /**
     * Initialize the length helper by loading all enabled lengths
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::loadLengths();
        self::$initialized = true;
    }

    /**
     * Load all enabled lengths from the database
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private static function loadLengths(): void
    {
        if (!empty(self::$lengths)) {
            return;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'j2commerce_length_id',
                'length_title',
                'length_unit',
                'length_value',
                'num_decimals',
                'enabled',
                'ordering',
            ]))
            ->from($db->quoteName('#__j2commerce_lengths'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        foreach ($rows as $row) {
            self::$lengths[(int) $row->j2commerce_length_id] = [
                'length_class_id' => (int) $row->j2commerce_length_id,
                'title'           => (string) $row->length_title,
                'unit'            => (string) $row->length_unit,
                'value'           => (float) $row->length_value,
                'num_decimals'    => (int) ($row->num_decimals ?? 2),
            ];
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
        self::$lengths     = [];
        self::$initialized = false;
    }

    // =========================================================================
    // LENGTH INFORMATION METHODS
    // =========================================================================

    /**
     * Check if a length class exists and is enabled
     *
     * @param   int  $lengthClassId  The length class ID to check
     *
     * @return  bool  True if length class exists
     *
     * @since   6.0.0
     */
    public static function has(int $lengthClassId): bool
    {
        self::initialize();

        return isset(self::$lengths[$lengthClassId]);
    }

    /**
     * Get all enabled lengths
     *
     * @return  array<int, array<string, mixed>>  Array of lengths indexed by ID
     *
     * @since   6.0.0
     */
    public static function getAll(): array
    {
        self::initialize();

        return self::$lengths;
    }

    /**
     * Get the length unit string (e.g., 'cm', 'in', 'mm')
     *
     * @param   int  $lengthClassId  The length class ID
     *
     * @return  string  The unit string or empty string if not found
     *
     * @since   6.0.0
     */
    public static function getUnit(int $lengthClassId): string
    {
        self::initialize();

        if (isset(self::$lengths[$lengthClassId])) {
            return self::$lengths[$lengthClassId]['unit'];
        }

        return '';
    }

    /**
     * Get the length title (e.g., 'Centimetre', 'Inch')
     *
     * @param   int  $lengthClassId  The length class ID
     *
     * @return  string  The title or empty string if not found
     *
     * @since   6.0.0
     */
    public static function getTitle(int $lengthClassId): string
    {
        self::initialize();

        if (isset(self::$lengths[$lengthClassId])) {
            return self::$lengths[$lengthClassId]['title'];
        }

        return '';
    }

    /**
     * Get the length conversion value
     *
     * @param   int  $lengthClassId  The length class ID
     *
     * @return  float  The conversion value or 1.0 if not found
     *
     * @since   6.0.0
     */
    public static function getValue(int $lengthClassId): float
    {
        self::initialize();

        if (isset(self::$lengths[$lengthClassId])) {
            return self::$lengths[$lengthClassId]['value'];
        }

        return 1.0;
    }

    /**
     * Get a length class by its ID
     *
     * @param   int  $lengthClassId  The length class ID
     *
     * @return  array<string, mixed>|null  The length data or null if not found
     *
     * @since   6.0.0
     */
    public static function getLength(int $lengthClassId): ?array
    {
        self::initialize();

        return self::$lengths[$lengthClassId] ?? null;
    }

    // =========================================================================
    // CONVERSION AND FORMATTING METHODS
    // =========================================================================

    /**
     * Convert a length value from one unit to another
     *
     * The conversion formula uses the stored conversion values:
     * result = value * (to_value / from_value)
     *
     * @param   float  $value  The value to convert
     * @param   int    $from   The source length class ID
     * @param   int    $to     The target length class ID
     *
     * @return  float  The converted value
     *
     * @since   6.0.0
     */
    public static function convert(float $value, int $from, int $to): float
    {
        self::initialize();

        // Same unit, no conversion needed
        if ($from === $to) {
            return $value;
        }

        // Get the conversion values
        $fromValue = isset(self::$lengths[$from]) ? self::$lengths[$from]['value'] : 1.0;
        $toValue   = isset(self::$lengths[$to]) ? self::$lengths[$to]['value'] : 1.0;

        // Avoid division by zero
        if ($fromValue <= 0.0) {
            return $value;
        }

        return $value * ($toValue / $fromValue);
    }

    /**
     * Format a length value with its unit
     *
     * @param   float   $value          The value to format
     * @param   int     $lengthClassId  The length class ID
     * @param   string  $decimalPoint   The decimal separator character
     * @param   string  $thousandPoint  The thousands separator character
     * @param   int     $decimals       The number of decimal places
     *
     * @return  string  The formatted length string with unit
     *
     * @since   6.0.0
     */
    public static function format(
        float $value,
        int $lengthClassId,
        string $decimalPoint = '.',
        string $thousandPoint = ',',
        int $decimals = 2
    ): string {
        self::initialize();

        $formatted = number_format($value, $decimals, $decimalPoint, $thousandPoint);

        if (isset(self::$lengths[$lengthClassId])) {
            return $formatted . ' ' . self::$lengths[$lengthClassId]['unit'];
        }

        return $formatted;
    }

    // =========================================================================
    // DROPDOWN / SELECT METHODS
    // =========================================================================

    /**
     * Get lengths for display in a dropdown/select
     *
     * @return  array<int, object>  Array of length objects with value, text, unit properties
     *
     * @since   6.0.0
     */
    public static function getLengthsForDropdown(): array
    {
        self::initialize();

        $options = [];

        foreach (self::$lengths as $id => $length) {
            $option        = new \stdClass();
            $option->value = $id;
            $option->text  = $length['title'] . ' (' . $length['unit'] . ')';
            $option->unit  = $length['unit'];
            $options[]     = $option;
        }

        return $options;
    }

    /**
     * Get length options as an associative array (id => title)
     *
     * @return  array<int, string>  Array of id => title pairs
     *
     * @since   6.0.0
     */
    public static function getLengthOptions(): array
    {
        self::initialize();

        $options = [];

        foreach (self::$lengths as $id => $length) {
            $options[$id] = $length['title'];
        }

        return $options;
    }

    // =========================================================================
    // DECIMAL PLACES METHODS
    // =========================================================================

    /**
     * Get the configured number of decimal places for length/dimension display
     *
     * Retrieves the num_decimals value from the length unit record.
     * If a length class ID is provided, returns that unit's decimal setting.
     * Otherwise returns the default of 2.
     *
     * @param   int|null  $lengthClassId  Optional length class ID to get decimals for
     *
     * @return  int  The number of decimal places (default: 2)
     *
     * @since   6.0.0
     */
    public static function getDecimalPlaces(?int $lengthClassId = null): int
    {
        if ($lengthClassId !== null) {
            self::initialize();

            if (isset(self::$lengths[$lengthClassId])) {
                return (int) self::$lengths[$lengthClassId]['num_decimals'];
            }
        }

        // Default fallback
        return 2;
    }

    /**
     * Format a length/dimension value using the unit's decimal places setting
     *
     * This method safely casts string values to float to avoid PHP 8 strict type errors,
     * then formats the value using the unit's configured decimal places.
     *
     * @param   mixed     $value          The length value (can be string or numeric)
     * @param   string    $unit           Optional unit suffix to append
     * @param   int|null  $lengthClassId  Optional length class ID to get decimal places from
     *
     * @return  string  The formatted length value
     *
     * @since   6.0.0
     */
    public static function formatValue(mixed $value, string $unit = '', ?int $lengthClassId = null): string
    {
        $floatValue = (float) $value;
        $decimals   = self::getDecimalPlaces($lengthClassId);

        $formatted = number_format($floatValue, $decimals, '.', '');

        if (!empty($unit)) {
            return $formatted . ' ' . $unit;
        }

        return $formatted;
    }

    /**
     * Format dimensions (length x width x height) using the unit's decimal places setting
     *
     * @param   mixed     $length         The length value
     * @param   mixed     $width          The width value
     * @param   mixed     $height         The height value
     * @param   string    $unit           Optional unit suffix to append
     * @param   int|null  $lengthClassId  Optional length class ID to get decimal places from
     *
     * @return  string  The formatted dimensions string (e.g., "10.00 x 5.00 x 3.00 cm")
     *
     * @since   6.0.0
     */
    public static function formatDimensions(mixed $length, mixed $width, mixed $height, string $unit = '', ?int $lengthClassId = null): string
    {
        $formatted = self::formatValue($length, '', $lengthClassId) . ' x ' . self::formatValue($width, '', $lengthClassId) . ' x ' . self::formatValue($height, '', $lengthClassId);

        if (!empty($unit)) {
            return $formatted . ' ' . $unit;
        }

        return $formatted;
    }
}
