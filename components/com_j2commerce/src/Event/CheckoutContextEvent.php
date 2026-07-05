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

use Joomla\CMS\Event\AbstractEvent;

\defined('_JEXEC') or die;

/**
 * Dispatched as 'onJ2CommerceResolveCheckoutContext'.
 *
 * The owning app plugin receives the session context payload and, after
 * validating ownership and nonce, calls assignResolved() with a
 * CheckoutContextInterface implementation.  The first listener to claim
 * the context wins; subsequent listeners should skip when getResolved()
 * is already non-null.
 *
 * NOTE: the resolution is stored under the argument key "resolution" (NOT
 * "resolved") and the setter is named assignResolved() (NOT setResolved()).
 * Joomla's AbstractEvent invokes a method named get<Arg>/set<Arg>/onSet<Arg>
 * as a value pre-processor for that argument. A getResolved()/setResolved()
 * method reading/writing a "resolved" argument therefore calls ITSELF via
 * getArgument()/setArgument() → infinite recursion (stack overflow), plus a
 * null initialiser would fatal. Keeping the argument key distinct from every
 * getter/setter method name avoids the collision entirely.
 *
 * @since 6.0.0
 */
class CheckoutContextEvent extends AbstractEvent
{
    /**
     * @return array<string, mixed>
     */
    public function getContextPayload(): array
    {
        return $this->getArgument('context', []);
    }

    public function getResolved(): ?CheckoutContextInterface
    {
        return $this->getArgument('resolution', null);
    }

    public function assignResolved(CheckoutContextInterface $resolved): void
    {
        $this->setArgument('resolution', $resolved);
    }
}
