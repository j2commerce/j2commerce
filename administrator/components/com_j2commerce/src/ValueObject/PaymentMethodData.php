<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\ValueObject;

\defined('_JEXEC') or die;

/**
 * Value object representing a saved payment method from any provider
 *
 * @since  6.1.3
 */
final class PaymentMethodData
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $type,
        public readonly string $displayName,
        public readonly string $brand,
        public readonly string $last4,
        public readonly int $expMonth,
        public readonly int $expYear,
        public readonly bool $isDefault = false,
        public readonly array $actions = ['delete'],
        public readonly array $metadata = []
    ) {
    }

    /**
     * Create from array data
     *
     * @param array $data Payment method data from plugin
     *
     * @return static
     * @since  6.1.3
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            provider: $data['provider'] ?? 'unknown',
            type: $data['type'] ?? 'card',
            displayName: $data['display_name'] ?? '',
            brand: strtolower($data['brand'] ?? 'unknown'),
            last4: $data['last4'] ?? '****',
            expMonth: (int) ($data['exp_month'] ?? 0),
            expYear: (int) ($data['exp_year'] ?? 0),
            isDefault: (bool) ($data['is_default'] ?? false),
            actions: $data['actions'] ?? ['delete'],
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Get the brand icon path for display
     *
     * @return string Path to brand icon SVG, or generic card icon
     * @since  6.1.3
     */
    public function getBrandIcon(): string
    {
        $brandIcons = [
            'visa'       => 'media/com_j2commerce/images/payment-methods/visa.svg',
            'mastercard' => 'media/com_j2commerce/images/payment-methods/mastercard.svg',
            'amex'       => 'media/com_j2commerce/images/payment-methods/amex.svg',
            'discover'   => 'media/com_j2commerce/images/payment-methods/discover.svg',
            'maestro'    => 'media/com_j2commerce/images/payment-methods/maestro.svg',
        ];

        return $brandIcons[$this->brand] ?? 'media/com_j2commerce/images/payment-methods/visa.svg';
    }

    /**
     * Get formatted expiry date
     *
     * @return string Formatted expiry (MM/YY)
     * @since  6.1.3
     */
    public function getFormattedExpiry(): string
    {
        if ($this->expMonth === 0 || $this->expYear === 0) {
            return '';
        }

        $yearShort = $this->expYear % 100;

        return \sprintf('%02d/%02d', $this->expMonth, $yearShort);
    }

    /**
     * Check if this payment method supports set-as-default action
     *
     * @return bool
     * @since  6.1.3
     */
    public function canSetDefault(): bool
    {
        return \in_array('set_default', $this->actions, true);
    }

    /**
     * Check if this payment method supports delete action
     *
     * @return bool
     * @since  6.1.3
     */
    public function canDelete(): bool
    {
        return \in_array('delete', $this->actions, true);
    }

    /**
     * Get the AJAX endpoint for this payment method's actions
     *
     * @return string Plugin name for AJAX calls
     * @since  6.1.3
     */
    public function getAjaxPlugin(): string
    {
        return $this->provider;
    }
}
