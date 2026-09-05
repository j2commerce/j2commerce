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
}
