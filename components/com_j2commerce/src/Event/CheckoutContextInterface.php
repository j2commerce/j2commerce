<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Event;

\defined('_JEXEC') or die;

/**
 * Contract returned by onJ2CommerceResolveCheckoutContext listeners.
 *
 * @since 6.0.0
 */
interface CheckoutContextInterface
{
    /** Identifies the app plugin that owns this context (e.g. 'app_partialpayment'). */
    public function getProvider(): string;

    /** Re-validates ownership/expiry on every request; false clears the context. */
    public function validate(): bool;

    /** The existing order to process payment against. */
    public function getOrder(): ?object;

    /** Whether to render the shipping-address step. */
    public function getShowShipping(): bool;

    /** Whether to render the billing-address step. */
    public function getShowBilling(): bool;

    /**
     * Subset of payment plugin element names the shopper may use.
     * Empty array = all configured plugins are available.
     *
     * NOTE: Core does NOT consume this method. Phase 2 consumer apps filter the
     * payment plugin list via their onJ2CommerceGetPaymentPlugins handler, scoped
     * to the active context, using this value.
     *
     * @return string[]
     */
    public function getAllowedPaymentMethods(): array;

    /**
     * Extra data surfaced to the confirmation template.
     *
     * @return array<string, mixed>
     */
    public function getConfirmation(): array;
}
