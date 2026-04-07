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
 * Weight Helper class for J2Commerce
 *
 * Provides weight conversion and formatting functionality.
 * This is a static helper class that caches weight data for performance.
 *
 * @since  6.0.0
 */
class WeightHelper
{
    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Cached weights data indexed by weight_id
     *
     * @var   array<int, array<string, mixed>>
     * @since 6.0.0
     */
    private static array $weights = [];

    /**
     * Flag to track if weights have been loaded
     *
     * @var   bool
     * @since 6.0.0
     */
    private static bool $initialized = false;

    protected static ?WeightHelper $instance = null;

    /**
     * Get singleton instance of WeightHelper
     *
     * @return WeightHelper The WeightHelper instance
     * @since 6.0.0
     */
    public static function getInstance(): WeightHelper
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
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

    /**
     * Initialize the weight helper by loading all enabled weights
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

        self::loadWeights();
        self::$initialized = true;
    }

    /**
     * Load all enabled weights from the database
     *
     * Note: Uses #__j2commerce_weights table with j2commerce_weight_id as primary key.
     * Fields: j2commerce_weight_id, weight_title, weight_unit, weight_value, enabled, ordering
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private static function loadWeights(): void
    {
        if (!empty(self::$weights)) {
            return;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'j2commerce_weight_id',
                'weight_title',
                'weight_unit',
                'weight_value',
                'num_decimals',
                'enabled',
                'ordering',
            ]))
            ->from($db->quoteName('#__j2commerce_weights'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        foreach ($rows as $row) {
            self::$weights[(int) $row->j2commerce_weight_id] = [
                'weight_class_id' => (int) $row->j2commerce_weight_id,
                'title'           => (string) $row->weight_title,
                'unit'            => (string) $row->weight_unit,
                'value'           => (float) $row->weight_value,
                'num_decimals'    => (int) ($row->num_decimals ?? 2),
            ];
        }
    }

    /**
     * Reset the helper state (useful for testing or reloading data)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function reset(): void
    {
        self::$weights     = [];
        self::$initialized = false;
    }

    // =========================================================================
    // WEIGHT CONVERSION AND FORMATTING METHODS
    // =========================================================================

    /**
     * Convert a weight value from one weight class to another
     *
     * The conversion uses the weight_value field which represents the ratio
     * relative to the base weight unit. For example, if Kilogram has value 1.0
     * and Gram has value 1000.0, converting 1 kg to grams gives 1 * (1000/1) = 1000g
     *
     * @param   float  $value  The weight value to convert
     * @param   int    $from   The source weight class ID
     * @param   int    $to     The target weight class ID
     *
     * @return  float  The converted weight value
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

        // Get the conversion factor for the source unit
        $fromValue = 1.0;
        if (isset(self::$weights[$from])) {
            $fromValue = (float) self::$weights[$from]['value'];
        }

        // Get the conversion factor for the target unit
        $toValue = 1.0;
        if (isset(self::$weights[$to])) {
            $toValue = (float) self::$weights[$to]['value'];
        }

        // Avoid division by zero
        if ($fromValue <= 0) {
            $fromValue = 1.0;
        }

        return $value * ($toValue / $fromValue);
    }

    /**
     * Format a weight value with the appropriate unit
     *
     * @param   float   $value          The weight value to format
     * @param   int     $weightClassId  The weight class ID
     * @param   string  $decimalPoint   The decimal point character (default: '.')
     * @param   string  $thousandPoint  The thousands separator (default: ',')
     * @param   int     $decimals       Number of decimal places (default: 2)
     *
     * @return  string  The formatted weight string with unit suffix
     *
     * @since   6.0.0
     */
    public static function format(
        float $value,
        int $weightClassId,
        string $decimalPoint = '.',
        string $thousandPoint = ',',
        int $decimals = 2
    ): string {
        self::initialize();

        $formattedNumber = number_format($value, $decimals, $decimalPoint, $thousandPoint);

        if (isset(self::$weights[$weightClassId])) {
            return $formattedNumber . self::$weights[$weightClassId]['unit'];
        }

        return $formattedNumber;
    }

    /**
     * Get the unit abbreviation for a weight class
     *
     * @param   int  $weightClassId  The weight class ID
     *
     * @return  string  The unit abbreviation (e.g., 'kg', 'lb') or empty string if not found
     *
     * @since   6.0.0
     */
    public static function getUnit(int $weightClassId): string
    {
        self::initialize();

        if (isset(self::$weights[$weightClassId])) {
            return self::$weights[$weightClassId]['unit'];
        }

        return '';
    }

    // =========================================================================
    // ADDITIONAL UTILITY METHODS (NEW IN J2COMMERCE)
    // =========================================================================

    /**
     * Get the title for a weight class
     *
     * @param   int  $weightClassId  The weight class ID
     *
     * @return  string  The weight title (e.g., 'Kilogram', 'Pound') or empty string if not found
     *
     * @since   6.0.0
     */
    public static function getTitle(int $weightClassId): string
    {
        self::initialize();

        if (isset(self::$weights[$weightClassId])) {
            return self::$weights[$weightClassId]['title'];
        }

        return '';
    }

    /**
     * Get the conversion value/factor for a weight class
     *
     * @param   int  $weightClassId  The weight class ID
     *
     * @return  float  The conversion value or 1.0 if not found
     *
     * @since   6.0.0
     */
    public static function getValue(int $weightClassId): float
    {
        self::initialize();

        if (isset(self::$weights[$weightClassId])) {
            return (float) self::$weights[$weightClassId]['value'];
        }

        return 1.0;
    }

    /**
     * Check if a weight class exists and is enabled
     *
     * @param   int  $weightClassId  The weight class ID to check
     *
     * @return  bool  True if weight class exists and is enabled
     *
     * @since   6.0.0
     */
    public static function has(int $weightClassId): bool
    {
        self::initialize();

        return isset(self::$weights[$weightClassId]);
    }

    /**
     * Get all enabled weight classes
     *
     * @return  array<int, array<string, mixed>>  Array of weight classes indexed by ID
     *
     * @since   6.0.0
     */
    public static function getAll(): array
    {
        self::initialize();

        return self::$weights;
    }

    /**
     * Get weight classes for display in a dropdown/select
     *
     * @return  array<int, object>  Array of weight class objects with value and text properties
     *
     * @since   6.0.0
     */
    public static function getWeightsForDropdown(): array
    {
        self::initialize();

        $options = [];

        foreach (self::$weights as $id => $weight) {
            $option        = new \stdClass();
            $option->value = $id;
            $option->text  = $weight['title'] . ' (' . $weight['unit'] . ')';
            $option->unit  = $weight['unit'];
            $options[]     = $option;
        }

        return $options;
    }

    /**
     * Get a single weight class by ID
     *
     * @param   int  $weightClassId  The weight class ID
     *
     * @return  array<string, mixed>|null  The weight class data or null if not found
     *
     * @since   6.0.0
     */
    public static function getWeight(int $weightClassId): ?array
    {
        self::initialize();

        return self::$weights[$weightClassId] ?? null;
    }

    // =========================================================================
    // DECIMAL PLACES METHODS
    // =========================================================================

    /**
     * Get the configured number of decimal places for weight display
     *
     * Retrieves the num_decimals value from the weight unit record.
     * If a weight class ID is provided, returns that unit's decimal setting.
     * Otherwise returns the default of 2.
     *
     * @param   int|null  $weightClassId  Optional weight class ID to get decimals for
     *
     * @return  int  The number of decimal places (default: 2)
     *
     * @since   6.0.0
     */
    public static function getDecimalPlaces(?int $weightClassId = null): int
    {
        if ($weightClassId !== null) {
            self::initialize();

            if (isset(self::$weights[$weightClassId])) {
                return (int) self::$weights[$weightClassId]['num_decimals'];
            }
        }

        // Default fallback
        return 2;
    }

    /**
     * Format a weight value using the unit's decimal places setting
     *
     * This method safely casts string values to float to avoid PHP 8 strict type errors,
     * then formats the value using the unit's configured decimal places.
     *
     * @param   mixed     $value          The weight value (can be string or numeric)
     * @param   string    $unit           Optional unit suffix to append
     * @param   int|null  $weightClassId  Optional weight class ID to get decimal places from
     *
     * @return  string  The formatted weight value
     *
     * @since   6.0.0
     */
    public static function formatValue(mixed $value, string $unit = '', ?int $weightClassId = null): string
    {
        $floatValue = (float) $value;
        $decimals   = self::getDecimalPlaces($weightClassId);

        $formatted = number_format($floatValue, $decimals, '.', '');

        if (!empty($unit)) {
            return $formatted . ' ' . $unit;
        }

        return $formatted;
    }
}
