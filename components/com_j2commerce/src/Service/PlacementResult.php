<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Service;

\defined('_JEXEC') or die;

final class PlacementResult
{
    /**
     * @param   object        $order         The reused OrderTable row, the saved order, or the CartOrder when a listener vetoed.
     * @param   object        $chargeOrder   The reloaded row whose order_params resolve amount_due_now.
     * @param   string[]      $errors        Translated errors from onJ2CommerceBeforePlaceOrder; non-empty means nothing was placed.
     */
    public function __construct(
        public readonly object $order,
        public readonly object $chargeOrder,
        public readonly bool $reused,
        public readonly bool $showPayment,
        public readonly float $chargeAmount,
        public readonly string $pluginHtml,
        public readonly string $freeRedirect,
        public readonly array $errors = [],
    ) {
    }
}
