<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Helper;

use J2Commerce\Component\J2commerce\Site\Event\CheckoutContextEvent;
use J2Commerce\Component\J2commerce\Site\Event\CheckoutContextInterface;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Session-based checkout context for pseudo-checkout mode.
 *
 * ## Lifecycle
 *
 * 1. **Set** — the consumer app plugin validates ownership, calls setContext(), and
 *    redirects to the checkout URL with the one-time nonce:
 *      `index.php?option=com_j2commerce&view=checkout&checkout_context=<nonce>`
 *
 * 2. **Activate** — on the first checkout page load, core reads the `checkout_context`
 *    URL parameter and calls checkNonce(). A matching nonce marks the context
 *    ACTIVATED in session. A missing or non-matching nonce clears the context so a
 *    stale/abandoned context can never hijack a subsequent normal cart checkout.
 *
 * 3. **Own** — isOwningRequest() is the single predicate used identically in View,
 *    getCartOrder(), confirm(), and confirmPayment(). It returns true iff context is
 *    activated AND resolveContext() returns non-null AND validate() is true.
 *
 * 4. **Finalize** — every successful payment path calls clearContext() (terminal).
 *
 * ## Per-request resolve cache
 *
 * resolveContext() dispatches onJ2CommerceResolveCheckoutContext once and caches the
 * result for the PHP request lifetime. The cache is NOT suitable for long-running CLI
 * processes — it resets only via setContext()/clearContext(), not between request cycles.
 *
 * @since 6.0.0
 */
class CheckoutContextHelper
{
    private const SESSION_KEY = 'checkout_context';
    private const SESSION_NS  = 'j2commerce';
    private const DEFAULT_TTL = 3600;

    /** Per-request resolved cache — reset on setContext / clearContext. */
    private static ?CheckoutContextInterface $resolvedCache = null;
    private static bool $resolveAttempted                   = false;

    /**
     * Write a checkout context to session.
     *
     * Stores a one-time nonce and expiry automatically. The context starts
     * DEACTIVATED; it becomes active only when the consumer app's redirect
     * carries the matching nonce (see checkNonce()). The calling app plugin
     * is responsible for gating access before this call.
     *
     * @param array<string, mixed> $payload  Must include 'provider' and 'order_id'.
     * @param int                  $ttl      Context lifetime in seconds (default 1 hour).
     */
    public static function setContext(array $payload, int $ttl = self::DEFAULT_TTL): void
    {
        $payload['nonce']      = bin2hex(random_bytes(16));
        $payload['expires_at'] = time() + $ttl;
        $payload['activated']  = false;

        Factory::getApplication()->getSession()->set(self::SESSION_KEY, $payload, self::SESSION_NS);

        self::$resolvedCache    = null;
        self::$resolveAttempted = false;
    }

    /**
     * Read the active context from session.
     *
     * Returns null when absent, malformed, or expired.
     * An expired context is removed from session automatically.
     *
     * @return array<string, mixed>|null
     */
    public static function getContext(): ?array
    {
        $payload = Factory::getApplication()->getSession()->get(self::SESSION_KEY, null, self::SESSION_NS);

        if (!\is_array($payload)) {
            return null;
        }

        if (!empty($payload['expires_at']) && time() > (int) $payload['expires_at']) {
            self::clearContext();
            return null;
        }

        return $payload;
    }

    /** Whether a context payload exists in session (regardless of activation state). */
    public static function isActive(): bool
    {
        return self::getContext() !== null;
    }

    /** Whether the context has been activated via a matching nonce URL parameter. */
    public static function isActivated(): bool
    {
        $payload = self::getContext();
        return ($payload['activated'] ?? false) === true;
    }

    /**
     * Validate the one-time nonce from the consumer app's redirect URL.
     *
     * The consumer app calls setContext() then redirects to:
     *   index.php?option=com_j2commerce&view=checkout&checkout_context=<nonce>
     *
     * Core calls checkNonce() on every checkout view entry. A matching nonce
     * marks the context ACTIVATED for the remainder of the flow. A non-matching
     * or empty nonce returns false so the caller can clearContext() and fall
     * through to normal cart checkout.
     *
     * @param string $urlNonce  Value of the `checkout_context` URL query parameter.
     */
    public static function checkNonce(string $urlNonce): bool
    {
        $payload = self::getContext();

        if ($payload === null) {
            return false;
        }

        if (($payload['activated'] ?? false) === true) {
            return true;
        }

        $storedNonce = (string) ($payload['nonce'] ?? '');

        if ($urlNonce === '' || $storedNonce === '' || !hash_equals($storedNonce, $urlNonce)) {
            return false;
        }

        self::activate();
        return true;
    }

    /**
     * The single shared predicate: does context own the current request?
     *
     * Returns true iff the context is ACTIVATED, resolveContext() returns non-null,
     * and the resolved implementation's validate() returns true. On validate() failure
     * the context is cleared automatically so the caller falls through to the normal
     * cart path without any extra teardown.
     *
     * Use this identically in View::display(), getCartOrder(), confirm(), and
     * confirmPayment() — never invent a separate "is context active?" heuristic.
     */
    public static function isOwningRequest(): bool
    {
        if (!self::isActivated()) {
            return false;
        }

        $resolved = self::resolveContext();

        if ($resolved === null) {
            self::clearContext();
            return false;
        }

        if (!$resolved->validate()) {
            self::clearContext();
            return false;
        }

        return true;
    }

    /**
     * Dispatch onJ2CommerceResolveCheckoutContext and return the first valid resolution.
     *
     * Result is cached per PHP request so multiple callers (View + Controller) pay the
     * dispatch cost only once.
     *
     * @param array<string, mixed>|null $context  Omit to auto-read from session.
     */
    public static function resolveContext(?array $context = null): ?CheckoutContextInterface
    {
        if (self::$resolveAttempted) {
            return self::$resolvedCache;
        }

        self::$resolveAttempted = true;

        $context ??= self::getContext();

        if ($context === null) {
            return null;
        }

        $event = new CheckoutContextEvent('onJ2CommerceResolveCheckoutContext', [
            'context' => $context,
        ]);

        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceResolveCheckoutContext', $event);

        self::$resolvedCache = $event->getResolved();

        return self::$resolvedCache;
    }

    /**
     * Remove the checkout context from session, reset per-request cache,
     * and null the primed user-state so no stale order can be finalized later.
     *
     * Call on terminal success, validation failure, or explicit abandon.
     */
    public static function clearContext(): void
    {
        $app = Factory::getApplication();
        $app->getSession()->clear(self::SESSION_KEY, self::SESSION_NS);
        $app->setUserState('j2commerce.order_id', null);
        $app->setUserState('j2commerce.orderpayment_id', null);

        self::$resolvedCache    = null;
        self::$resolveAttempted = false;
    }

    /**
     * Prime Joomla user-state with IDs from a resolved order so that
     * confirmPayment() can load the correct order without a cart lookup.
     */
    public static function primeUserState(object $order): void
    {
        $app = Factory::getApplication();
        $app->setUserState('j2commerce.order_id', $order->order_id ?? null);
        $app->setUserState('j2commerce.orderpayment_id', $order->j2commerce_order_id ?? null);
    }

    /** Persist the activated flag inside the session payload. */
    private static function activate(): void
    {
        $payload = self::getContext();

        if ($payload === null) {
            return;
        }

        $payload['activated'] = true;
        Factory::getApplication()->getSession()->set(self::SESSION_KEY, $payload, self::SESSION_NS);

        self::$resolvedCache    = null;
        self::$resolveAttempted = false;
    }
}
