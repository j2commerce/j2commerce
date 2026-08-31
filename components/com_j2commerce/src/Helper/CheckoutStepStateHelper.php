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

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Server-side record of which checkout steps the current cart has completed.
 *
 * The step order otherwise exists only in the template JavaScript, so each validate task
 * enforces its own rules and a step that never runs is a step whose rules never run.
 */
class CheckoutStepStateHelper
{
    public const BILLING  = 'billing';
    public const SHIPPING = 'shipping';
    public const PAYMENT  = 'payment';

    private const SESSION_KEY = 'completed_steps';

    /**
     * Ordered so that completing a step invalidates every step after it. Editing billing must
     * force the shipping step to be re-done rather than inheriting a stale answer.
     */
    private const STEP_ORDER = [
        self::BILLING,
        self::SHIPPING,
        self::PAYMENT,
    ];

    private const STEP_LABELS = [
        self::BILLING  => 'COM_J2COMMERCE_BILLING_ADDRESS',
        self::SHIPPING => 'COM_J2COMMERCE_SHIPPING_ADDRESS',
        self::PAYMENT  => 'COM_J2COMMERCE_PAYMENT_METHOD',
    ];

    public static function label(string $step): string
    {
        return self::STEP_LABELS[$step] ?? $step;
    }

    public static function markComplete(string $step): void
    {
        $position = array_search($step, self::STEP_ORDER, true);

        if ($position === false) {
            return;
        }

        $state = self::read();

        foreach (self::STEP_ORDER as $index => $key) {
            if ($index > $position) {
                unset($state[$key]);
            }
        }

        $state[$step] = true;

        self::write($state);
    }

    /**
     * Absent state means no step has been completed, not that every step may be assumed — the
     * only writers are the validate tasks, so a caller that ran none of them reaches this with
     * nothing recorded, which is exactly the case the assertion exists to answer. read() also
     * returns nothing once the cart or the shopper has changed underneath the flags, and that
     * has to deny for the same reason: the steps were completed against a cart or an identity
     * that is no longer the one being confirmed.
     *
     * @param  string[]  $required
     * @return string[]  the required steps that are not complete
     */
    public static function missing(array $required): array
    {
        $state = self::read();

        return array_values(array_filter($required, static fn (string $step): bool => empty($state[$step])));
    }

    public static function clear(): void
    {
        Factory::getApplication()->getSession()->clear(self::SESSION_KEY, 'j2commerce');
    }

    /**
     * State belongs to a cart and to the shopper who filled it in. A rebuilt cart must not
     * inherit the old flags, and neither must a different identity: logging in mid-checkout
     * clears the guest address keys the billing flag stands for, and may merge a different
     * cart in, so a flag written before that point no longer describes anything that is
     * still loaded.
     */
    private static function read(): array
    {
        $stored = Factory::getApplication()->getSession()->get(self::SESSION_KEY, [], 'j2commerce');

        if (!\is_array($stored) || empty($stored['steps']) || !\is_array($stored['steps'])) {
            return [];
        }

        if ((int) ($stored['cart_id'] ?? 0) !== self::currentCartId()
            || (int) ($stored['user_id'] ?? -1) !== self::currentUserId()) {
            self::clear();

            return [];
        }

        return $stored['steps'];
    }

    private static function write(array $steps): void
    {
        Factory::getApplication()->getSession()->set(self::SESSION_KEY, [
            'cart_id' => self::currentCartId(),
            'user_id' => self::currentUserId(),
            'steps'   => $steps,
        ], 'j2commerce');
    }

    private static function currentCartId(): int
    {
        try {
            return (int) (CartHelper::getInstance()->getCart()->j2commerce_cart_id ?? 0);
        } catch (\Throwable) {
            // A cart that cannot be read cannot vouch for the flags stored against it. This is
            // not a value only reads produce — write() resolves the id the same way — so two
            // consecutive failures do compare equal. Confirm does not rely on that: an
            // unreadable cart fails buildCartOrder(), and both the assertion and the save are
            // already skipped when there is no order.
            return -1;
        }
    }

    private static function currentUserId(): int
    {
        return (int) (Factory::getApplication()->getIdentity()->id ?? 0);
    }
}
