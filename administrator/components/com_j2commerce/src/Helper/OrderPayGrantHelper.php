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

    /** Payable only while not cancelled or refunded and wholly unpaid: Take Payment charges the full amount due now, so a partly paid or authorized order would be charged twice. */
    public static function isPayable(object $order): bool
    {
        $type = OrderStatusHelper::getType((int) ($order->order_state_id ?? 0));

        return !\in_array($type, [OrderStatusHelper::TYPE_CANCELLED, OrderStatusHelper::TYPE_REFUNDED], true)
            && OrderPaymentHelper::state($order) === OrderPaymentHelper::STATE_UNPAID;
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
