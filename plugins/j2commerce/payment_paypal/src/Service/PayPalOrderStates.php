<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_payment_paypal
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\PaymentPaypal\Service;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\OrderStatusHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Resolves the order status a PayPal outcome writes. The merchant's param wins; an unset
 * param falls back to the one status mapped to the outcome's type, then to the core row by
 * name. Ids are install-dependent, so nothing here is a literal. 0 means leave the status.
 */
final class PayPalOrderStates
{
    public const CONFIRMED = ['payment_status', OrderStatusHelper::TYPE_COMPLETE, 'J2COMMERCE_CONFIRMED'];
    public const PENDING   = ['pending_state_id', OrderStatusHelper::TYPE_OPEN, 'J2COMMERCE_PENDING'];
    public const FAILED    = ['failed_state_id', OrderStatusHelper::TYPE_FAILED, 'J2COMMERCE_FAILED'];
    /** Core ships no refunded row, so an unmapped, unset store leaves the status alone. */
    public const REFUNDED  = ['refunded_state_id', OrderStatusHelper::TYPE_REFUNDED, ''];

    /** No param exposes this one; it resolves by type, then by the core row's name. */
    public const NEW = ['new_state_id', OrderStatusHelper::TYPE_NEW, 'J2COMMERCE_NEW'];

    /** The outcomes an order may still be captured from — nothing has settled yet. */
    private const AWAITING_PAYMENT = [self::NEW, self::PENDING, self::FAILED];

    /** @param array{0: string, 1: string, 2: string} $outcome One of the constants above. */
    public static function resolve(Registry $params, DatabaseInterface $db, array $outcome): int
    {
        [$key, $type, $coreName] = $outcome;

        $id = (int) $params->get($key, 0);

        if ($id > 0) {
            return $id;
        }

        $ids = OrderStatusHelper::idsOfType($type);

        if (\count($ids) === 1) {
            return $ids[0];
        }

        if ($coreName === '') {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_orderstatus_id'))
            ->from($db->quoteName('#__j2commerce_orderstatuses'))
            ->where($db->quoteName('orderstatus_core') . ' = 1')
            ->where($db->quoteName('orderstatus_name') . ' = :name')
            ->bind(':name', $coreName);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Is this order still awaiting payment, and so safe to capture, complete or finalize?
     *
     * Each leg goes through resolve(), never through a literal id and never through a bare
     * type union. j2commerce_orderstatus_id is AUTO_INCREMENT and the J2Store migrator
     * preserves source ids, so a literal names the wrong row on a migrated store. A type on
     * its own is no better here: the shipped taxonomy classifies Confirmed and Processed as
     * 'open' alongside Pending, so a union over that type would admit the settled rows this
     * guard exists to exclude. resolve() trusts a type only where it names exactly one row,
     * and otherwise falls back to the core row's name.
     *
     * An outcome that resolves to nothing contributes nothing, so a partially classified
     * store still matches on its remaining legs.
     */
    public static function isAwaitingPayment(int $stateId, Registry $params, DatabaseInterface $db): bool
    {
        if ($stateId <= 0) {
            return false;
        }

        foreach (self::AWAITING_PAYMENT as $outcome) {
            if (self::resolve($params, $db, $outcome) === $stateId) {
                return true;
            }
        }

        return false;
    }
}
