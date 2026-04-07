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

use J2Commerce\Component\J2commerce\Administrator\ValueObject\PaymentMethodData;

/**
 * Helper class for unified payment methods display
 *
 * Aggregates saved payment methods from multiple payment plugins
 * into a single unified interface for the My Account profile.
 *
 * @since  6.1.3
 */
class PaymentMethodsHelper
{
    /**
     * Provider display names for human-readable labels
     *
     * @var array<string, string>
     * @since 6.1.3
     */
    private static array $providerNames = [
        'payment_stripe'       => 'Stripe',
        'payment_authorizenet' => 'Authorize.net',
        'payment_paytrace'     => 'PayTrace',
        'payment_braintree'    => 'Braintree',
        'payment_paypal'       => 'PayPal',
    ];

    /**
     * Get all saved payment methods for a user across all providers
     *
     * Dispatches the onJ2CommerceGetSavedPaymentMethods event to all
     * enabled payment plugins and aggregates the results.
     *
     * @param int $userId The user ID to fetch payment methods for
     *
     * @return PaymentMethodData[] Array of payment method data objects
     * @since  6.1.3
     */
    public static function getPaymentMethods(int $userId): array
    {
        $methods = [];

        $event = J2CommerceHelper::plugin()->event(
            'GetSavedPaymentMethods',
            [$userId]
        );

        $results = $event->getArgument('result', []);

        foreach ($results as $pluginResult) {
            if (!\is_array($pluginResult)) {
                continue;
            }

            foreach ($pluginResult as $methodData) {
                if (!\is_array($methodData)) {
                    continue;
                }

                try {
                    $methods[] = PaymentMethodData::fromArray($methodData);
                } catch (\Throwable $e) {
                    // Skip invalid method data
                    continue;
                }
            }
        }

        return $methods;
    }

    /**
     * Group payment methods by provider
     *
     * @param PaymentMethodData[] $methods Array of payment methods
     *
     * @return array<string, PaymentMethodData[]> Methods grouped by provider
     * @since  6.1.3
     */
    public static function groupByProvider(array $methods): array
    {
        $grouped = [];

        foreach ($methods as $method) {
            $provider = $method->provider;

            if (!isset($grouped[$provider])) {
                $grouped[$provider] = [];
            }

            $grouped[$provider][] = $method;
        }

        return $grouped;
    }

    /**
     * Get human-readable display name for a provider
     *
     * @param string $provider The provider plugin name
     *
     * @return string Human-readable provider name
     * @since  6.1.3
     */
    public static function getProviderDisplayName(string $provider): string
    {
        return self::$providerNames[$provider] ?? ucfirst(str_replace('payment_', '', $provider));
    }

    /**
     * Check if any payment plugin supports saved payment methods
     *
     * Dispatches event and checks if any plugin responds.
     *
     * @return bool True if at least one plugin supports saved methods
     * @since  6.1.3
     */
    public static function hasPaymentMethodsEnabled(): bool
    {
        $event = J2CommerceHelper::plugin()->event(
            'GetSavedPaymentMethods',
            [0] // Use 0 to check availability without fetching real data
        );

        $results = $event->getArgument('result', []);

        return !empty($results);
    }
}
