<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Context;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderPayGrantHelper;
use J2Commerce\Component\J2commerce\Site\Event\CheckoutContextInterface;
use Joomla\CMS\Factory;

/**
 * Pseudo-checkout context for the admin "Take Payment" flow (provider 'admin_order').
 *
 * Authorisation is the short-lived HMAC grant carried in the context payload —
 * re-verified on every request, so an expired or forged grant clears the context
 * and the visitor falls through to the normal cart checkout.
 */
final class AdminOrderCheckoutContext implements CheckoutContextInterface
{
    private ?bool $validatedCache = null;
    private ?object $order        = null;

    public function __construct(private readonly array $payload)
    {
    }

    public function getProvider(): string
    {
        return 'admin_order';
    }

    /** Re-verifies the grant + payable state each request; memoised per request instance. */
    public function validate(): bool
    {
        if ($this->validatedCache !== null) {
            return $this->validatedCache;
        }

        $orderPk = (int) ($this->payload['order_pk'] ?? 0);
        $expires = (int) ($this->payload['expires'] ?? 0);
        $sig     = (string) ($this->payload['sig'] ?? '');

        if ($orderPk < 1 || !OrderPayGrantHelper::verify($orderPk, $expires, $sig)) {
            return $this->validatedCache = false;
        }

        $table = Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()
            ->createTable('Order', 'Administrator');

        if (!$table->load($orderPk)
            || (string) $table->order_id !== (string) ($this->payload['order_id'] ?? '')
            || !OrderPayGrantHelper::isPayable($table)
        ) {
            return $this->validatedCache = false;
        }

        $this->order = $table;

        return $this->validatedCache = true;
    }

    /** The existing order (OrderTable row with all DB columns). */
    public function getOrder(): ?object
    {
        if ($this->order === null) {
            $this->validate();
        }

        return $this->order;
    }

    /** Admin wizard already captured the addresses. */
    public function getShowShipping(): bool
    {
        return false;
    }

    /** False also enables the no-login (guest) path for the store owner's site session. */
    public function getShowBilling(): bool
    {
        return false;
    }

    /** @return string[] Empty = every published gateway is offered. */
    public function getAllowedPaymentMethods(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function getConfirmation(): array
    {
        return [];
    }
}
