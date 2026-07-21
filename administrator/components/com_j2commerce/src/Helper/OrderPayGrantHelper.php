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

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Self-contained HMAC grant authorising the admin → site "Take Payment" handoff.
 *
 * Admin and site sessions are separate, so the LINK itself is the authorisation:
 * a short-lived signature over the order PK lets the site checkout accept the
 * pseudo-checkout context without a logged-in site session.
 */
final class OrderPayGrantHelper
{
    /** @return array{expires: int, sig: string} */
    public static function sign(int $orderPk, int $ttl = 1800): array
    {
        $expires = time() + $ttl;

        return ['expires' => $expires, 'sig' => self::hmac($orderPk, $expires)];
    }

    public static function verify(int $orderPk, int $expires, string $sig): bool
    {
        return $expires > time() && $sig !== '' && hash_equals(self::hmac($orderPk, $expires), $sig);
    }

    /**
     * Whether payment may still be taken. Status 1 = Confirmed (already paid) and
     * 6 = Cancelled are excluded, as is an existing Completed/Authorized transaction
     * (Authorized = capture pending — charging again would double-pay).
     */
    public static function isPayable(object $order): bool
    {
        return !\in_array((int) ($order->order_state_id ?? 0), [1, 6], true)
            && !\in_array((string) ($order->transaction_status ?? ''), ['Completed', 'Authorized'], true);
    }

    /** Site entry URL for the Take Payment button (Uri::root() is the site root, even in admin). */
    public static function buildUrl(int $orderPk): string
    {
        $grant = self::sign($orderPk);

        return Uri::root() . 'index.php?option=com_j2commerce&task=checkout.adminPay&order=' . $orderPk
            . '&expires=' . $grant['expires'] . '&sig=' . $grant['sig'];
    }

    private static function hmac(int $orderPk, int $expires): string
    {
        return hash_hmac(
            'sha256',
            'j2c-adminpay:' . $orderPk . ':' . $expires,
            (string) Factory::getApplication()->get('secret')
        );
    }
}
