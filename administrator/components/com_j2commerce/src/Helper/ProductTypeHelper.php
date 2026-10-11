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

\defined('_JEXEC') or die;

/**
 * Which product types share a behaviour, asked by capability instead of by type name, so a type a
 * plugin adds is handled everywhere core handles its siblings. Plugins extend the map through
 * onJ2CommerceGetProductTypeCapabilities, receiving it by reference:
 *
 *     $capabilities[ProductTypeHelper::VARIANTS][] = 'mytype';
 */
final class ProductTypeHelper
{
    /** Has child variants chosen from its options; sold-out, SKU suffixing and order variant swap follow them. */
    public const VARIANTS = 'variants';

    /** The core site script resolves the selected variant from the option CSV (getMatchingVariant). */
    public const CLIENT_VARIANT = 'client_variant';

    /** A listing card prices the default child variant rather than the master. */
    public const DEFAULT_VARIANT_PRICE = 'default_variant_price';

    /** Option values generate the variant matrix, so the option-value editor shows attribs and hides parent/price fields. */
    public const VARIANT_OPTION_EDITOR = 'variant_option_editor';

    /** Variants built from select-type options: inventory lists them, the price index covers them, the option picker narrows to them. */
    public const VARIANT_MATRIX = 'variant_matrix';

    /** Option values carry a "default" flag in the option-value editor. */
    public const OPTION_VALUE_DEFAULT = 'option_value_default';

    /** A listing card sends the shopper to the product page to choose options instead of adding to cart. */
    public const CHOOSE_OPTIONS = 'choose_options';

    /** Bills on a recurring schedule, so it never appears on a guest order. */
    public const SUBSCRIPTION = 'subscription';

    private const DEFAULTS = [
        self::VARIANTS              => ['variable', 'advancedvariable', 'flexivariable', 'variablesubscriptionproduct'],
        self::CLIENT_VARIANT        => ['variable', 'advancedvariable', 'variablesubscriptionproduct'],
        self::DEFAULT_VARIANT_PRICE => ['variable', 'flexivariable', 'variablesubscriptionproduct'],
        self::VARIANT_OPTION_EDITOR => ['variable', 'variablesubscriptionproduct'],
        self::VARIANT_MATRIX        => ['variable', 'flexivariable'],
        self::OPTION_VALUE_DEFAULT  => ['simple', 'advancedvariable', 'booking', 'configurable'],
        self::CHOOSE_OPTIONS        => ['variable', 'flexivariable', 'configurable', 'advancedvariable'],
        self::SUBSCRIPTION          => ['subscriptionproduct', 'variablesubscriptionproduct'],
    ];

    private static ?array $capabilities = null;

    private static bool $resolving = false;

    /** @return string[] */
    public static function typesWith(string $capability): array
    {
        return self::capabilities()[$capability] ?? [];
    }

    public static function has(?string $productType, string $capability): bool
    {
        return $productType !== null && \in_array($productType, self::typesWith($capability), true);
    }

    /** @return array<string, string[]> */
    public static function capabilities(): array
    {
        if (self::$capabilities !== null) {
            return self::$capabilities;
        }

        // A listener that queries this helper while the map is being built gets the core defaults.
        if (self::$resolving) {
            return self::DEFAULTS;
        }

        $capabilities    = self::DEFAULTS;
        self::$resolving = true;

        try {
            J2CommerceHelper::plugin()->event('GetProductTypeCapabilities', [&$capabilities]);
        } finally {
            self::$resolving = false;
        }

        // Tokens reach SQL and JS, so keep only well-formed type names.
        foreach ($capabilities as $capability => $types) {
            $capabilities[$capability] = \is_array($types)
                ? array_values(array_unique(array_filter($types, static fn ($type): bool => \is_string($type) && preg_match('/^[a-z0-9_]+$/', $type) === 1)))
                : [];
        }

        return self::$capabilities = $capabilities;
    }

    /** Forget the resolved map, e.g. after enabling a plugin in the same request. */
    public static function reset(): void
    {
        self::$capabilities = null;
    }
}
