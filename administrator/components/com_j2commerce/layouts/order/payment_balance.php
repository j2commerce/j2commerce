<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use Joomla\CMS\Language\Text;

$orderId       = (int) ($displayData['order_id'] ?? 0);
$currencyCode  = (string) ($displayData['currency_code'] ?? 'USD');
$currencyValue = (float) ($displayData['currency_value'] ?? 1.0);
$currencyValue = $currencyValue > 0 ? $currencyValue : 1.0;

// order_total is stored in the store base currency; convert to the order's display
// currency to match the ledger-derived figures below (which are already display currency).
$orderTotalDisplay = (float) ($displayData['order_total'] ?? 0) * $currencyValue;

$summary    = OrderTransactionHelper::getBalanceSummary($orderId);
$captured   = $summary['captured'];
$refunded   = $summary['refunded'];
$netPaid    = $summary['net_paid'];
$balanceDue = max(0.0, $orderTotalDisplay - max(0.0, $netPaid));

// exchangeRate override of 1.0 skips CurrencyHelper::format()'s base->display multiplication —
// every value here is already in display currency.
$fmt = static fn (float $value): string => CurrencyHelper::format($value, $currencyCode, 1.0);

// Half a minor unit of the order's currency — a hardcoded 0.01 misfires for
// zero-decimal currencies (e.g. JPY) and is too coarse for 3-decimal ones.
$threshold = 10 ** -CurrencyHelper::getDecimalPlace($currencyCode) / 2;

$balanceClass = $balanceDue > $threshold ? 'text-danger fw-bold' : 'text-success fw-bold';
$balanceLabel = $balanceDue > $threshold ? $fmt($balanceDue) : Text::_('COM_J2COMMERCE_PAID_IN_FULL');
?>
<div class="alert alert-light border rounded-1 p-3 mb-3">
    <h6 class="mb-2">
        <span class="fa-solid fa-scale-balanced me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCE_PAYMENT_BALANCE'); ?>
    </h6>
    <table class="table table-sm table-borderless mb-0 small">
        <tbody>
            <tr>
                <td class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_TOTAL'); ?></td>
                <td class="text-end"><?php echo $fmt($orderTotalDisplay); ?></td>
            </tr>
            <tr>
                <td class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_AMOUNT_PAID'); ?></td>
                <td class="text-end"><?php echo $fmt($captured); ?></td>
            </tr>
            <?php if ($refunded > $threshold) : ?>
                <tr>
                    <td class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_REFUNDED'); ?></td>
                    <td class="text-end text-danger">-<?php echo $fmt($refunded); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="border-top">
                <td class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_NET_PAID'); ?></td>
                <td class="text-end fw-bold"><?php echo $fmt($netPaid); ?></td>
            </tr>
            <tr>
                <td class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_BALANCE_DUE'); ?></td>
                <td class="text-end <?php echo $balanceClass; ?>"><?php echo $balanceLabel; ?></td>
            </tr>
        </tbody>
    </table>
</div>
