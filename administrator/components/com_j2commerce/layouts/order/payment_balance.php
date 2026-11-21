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
$currencyValue = CurrencyHelper::effectiveRate((float) ($displayData['currency_value'] ?? 1.0), $currencyCode);

// order_total is stored in the store base currency; convert to the order's display
// currency to match the ledger-derived figures below (which are already display currency),
// and round to the display currency's scale so the balance subtraction lines up exactly
// with the already-scaled netPaid (e.g. 39.925 → 39.93 for USD, not 9.995 balance).
$orderTotalDisplay = round(
    (float) ($displayData['order_total'] ?? 0) * $currencyValue,
    CurrencyHelper::getDecimalPlace($currencyCode)
);

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
        <caption class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_BALANCE'); ?></caption>
        <tbody>
            <tr>
                <th scope="row" class="fw-normal text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_TOTAL'); ?></th>
                <td class="text-end" data-j2c-balance="total"><?php echo $fmt($orderTotalDisplay); ?></td>
            </tr>
            <tr>
                <th scope="row" class="fw-normal text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_AMOUNT_PAID'); ?></th>
                <td class="text-end" data-j2c-balance="paid"><?php echo $fmt($captured); ?></td>
            </tr>
            <tr data-j2c-balance-row="refunded"<?php echo $refunded > $threshold ? '' : ' hidden'; ?>>
                <th scope="row" class="fw-normal text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_REFUNDED'); ?></th>
                <td class="text-end text-danger" data-j2c-balance="refunded">-<?php echo $fmt($refunded); ?></td>
            </tr>
            <tr class="border-top">
                <th scope="row" class="fw-normal text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_NET_PAID'); ?></th>
                <td class="text-end fw-bold" data-j2c-balance="net"><?php echo $fmt($netPaid); ?></td>
            </tr>
            <tr>
                <th scope="row" class="fw-normal text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_BALANCE_DUE'); ?></th>
                <td class="text-end <?php echo $balanceClass; ?>" data-j2c-balance="due"><?php echo $balanceLabel; ?></td>
            </tr>
        </tbody>
    </table>
    <?php if (\array_key_exists('recorded_payments', $displayData)) : ?>
        <?php $recorded = (array) $displayData['recorded_payments']; ?>
        <div class="mt-3" id="j2cRecordedPayments"<?php echo $recorded ? '' : ' hidden'; ?>>
            <h6 class="small fw-semibold mb-1"><?php echo Text::_('COM_J2COMMERCE_RECORDED_PAYMENTS'); ?></h6>
            <table class="table table-sm small mb-0">
                <caption class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_RECORDED_PAYMENTS'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_HEADING_DATE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_FIELD_PAYMENT_METHOD'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_FIELD_REFERENCE'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_AMOUNT'); ?></th>
                    </tr>
                </thead>
                <tbody data-j2c-recorded-rows>
                    <?php foreach ($recorded as $row) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['method'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if ($row['kind'] === 'refund') : ?>
                                    <span class="<?php echo htmlspecialchars($row['badgeClass'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_('COM_J2COMMERCE_REFUND'); ?></span>
                                <?php endif; ?>
                                <?php if ($row['by'] !== '') : ?>
                                    <div class="text-body-secondary"><?php echo htmlspecialchars(Text::sprintf('COM_J2COMMERCE_RECORDED_BY', $row['by']), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($row['amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
