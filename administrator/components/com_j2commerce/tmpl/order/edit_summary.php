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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;
$orderItems = $item->orderitems ?? [];
$orderDiscounts = $item->orderdiscounts ?? [];
$orderFees = $item->orderfees ?? [];
$currency = $item->currency_code ?? 'USD';
$fmt = static fn (float $amount): string => CurrencyHelper::format($amount, $currency);

?>
<div class="row">
    <div class="col-lg-8">
        <?php // Item summary table (readonly) ?>
        <?php if (!empty($orderItems)) : ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_ORDER_ITEMS'); ?></h2>
        <table class="table table-sm table-striped mb-4">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_J2COMMERCE_HEADING_PRODUCT'); ?></th>
                    <th class="text-center w-10"><?php echo Text::_('COM_J2COMMERCE_HEADING_QTY'); ?></th>
                    <th class="text-end w-15"><?php echo Text::_('COM_J2COMMERCE_HEADING_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $orderItem) : ?>
                <tr>
                    <td>
                        <?php echo $this->escape($orderItem->orderitem_name); ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', array($orderItem, $item, $this->params))->getArgument('html', ''); ?>
                    </td>
                    <td class="text-center"><?php echo (int) $orderItem->orderitem_quantity; ?></td>
                    <td class="text-end"><?php echo $fmt((float) $orderItem->orderitem_finalprice); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php // Voucher / Coupon inputs ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" name="voucher_code" id="voucherCode"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_VOUCHER'); ?>">
                    <button type="button" class="btn btn-outline-secondary" id="applyVoucherBtn">
                        <?php echo Text::_('COM_J2COMMERCE_VOUCHER'); ?>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" name="coupon_code" id="couponCode"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_COUPON'); ?>">
                    <button type="button" class="btn btn-outline-secondary" id="applyCouponBtn">
                        <?php echo Text::_('COM_J2COMMERCE_APPLY_COUPON'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php // Applied discounts ?>
        <?php if (!empty($orderDiscounts)) : ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_DISCOUNT'); ?></h2>
        <ul class="list-group mb-4">
            <?php foreach ($orderDiscounts as $discount) : ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <strong><?php echo $this->escape($discount->discount_title ?? ''); ?></strong>
                    <span class="text-body-secondary">(<?php echo $this->escape($discount->discount_code ?? ''); ?>)</span>
                    — <?php echo $fmt((float) ($discount->discount_amount ?? 0)); ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-j2c-remove-discount="<?php echo (int) $discount->j2commerce_orderdiscount_id; ?>"
                        aria-label="<?php echo Text::_('JACTION_DELETE'); ?>">
                    <span class="icon-trash" aria-hidden="true"></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php // Applied fees ?>
        <?php if (!empty($orderFees)) : ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_FEES'); ?></h2>
        <ul class="list-group mb-4">
            <?php foreach ($orderFees as $fee) : ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <strong><?php echo $this->escape($fee->name ?? ''); ?></strong>
                    — <?php echo $fmt((float) ($fee->amount ?? 0) + (float) ($fee->tax ?? 0)); ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-j2c-remove-fee="<?php echo (int) $fee->j2commerce_orderfee_id; ?>"
                        aria-label="<?php echo Text::_('JACTION_DELETE'); ?>">
                    <span class="icon-trash" aria-hidden="true"></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php // Cart Totals ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_CART_TOTALS'); ?></h2>
        <table class="table table-sm j2c-summary-table mb-4">
            <tbody>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_SUBTOTAL'); ?></td>
                    <td class="text-end" id="summarySubtotal"><?php echo $fmt((float) $item->order_subtotal); ?></td>
                </tr>
                <tr id="summaryShippingRow" class="<?php echo (float) $item->order_shipping > 0 ? '' : 'd-none'; ?>">
                    <td><?php echo Text::_('COM_J2COMMERCE_SHIPPING'); ?></td>
                    <td class="text-end" id="summaryShipping"><?php echo $fmt((float) $item->order_shipping); ?></td>
                </tr>
                <tr id="summarySurchargeRow" class="<?php echo (float) ($item->order_surcharge ?? 0) > 0 ? '' : 'd-none'; ?>">
                    <td><?php echo Text::_('COM_J2COMMERCE_SURCHARGE'); ?></td>
                    <td class="text-end" id="summarySurcharge"><?php echo $fmt((float) $item->order_surcharge); ?></td>
                </tr>
                <tr id="summaryDiscountRow" class="<?php echo (float) $item->order_discount > 0 ? '' : 'd-none'; ?>">
                    <td><?php echo Text::_('COM_J2COMMERCE_DISCOUNT'); ?></td>
                    <td class="text-end text-danger" id="summaryDiscount">-<?php echo $fmt((float) $item->order_discount); ?></td>
                </tr>
                <tr id="summaryTaxRow" class="<?php echo (float) $item->order_tax > 0 ? '' : 'd-none'; ?>">
                    <td><?php echo Text::_('COM_J2COMMERCE_TAX'); ?></td>
                    <td class="text-end" id="summaryTax"><?php echo $fmt((float) $item->order_tax); ?></td>
                </tr>
                <tr id="summaryFeesRow" class="<?php echo (float) ($item->order_fees ?? 0) > 0 ? '' : 'd-none'; ?>">
                    <td><?php echo Text::_('COM_J2COMMERCE_FEES'); ?></td>
                    <td class="text-end" id="summaryFees"><?php echo $fmt((float) ($item->order_fees ?? 0)); ?></td>
                </tr>
                <tr class="total-row">
                    <td><strong><?php echo Text::_('COM_J2COMMERCE_TOTAL'); ?></strong></td>
                    <td class="text-end" id="summaryTotal"><strong><?php echo $fmt((float) $item->order_total); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <?php // Add Fee section ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_ADD_FEE'); ?></h2>
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="fee_name" id="feeName"
                       placeholder="<?php echo Text::_('COM_J2COMMERCE_FEE_NAME'); ?>">
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control" name="fee_amount" id="feeAmount" step="0.01"
                       placeholder="<?php echo Text::_('COM_J2COMMERCE_FEE_AMOUNT'); ?>">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-primary w-100" id="addFeeBtn">
                    <?php echo Text::_('COM_J2COMMERCE_ADD_FEE'); ?>
                </button>
            </div>
        </div>

        <div class="alert alert-warning small">
            <span class="icon-warning" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_TAX_RECALC_WARNING'); ?>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="notify_customer" id="notifyCustomerOnSave" value="1">
            <label class="form-check-label" for="notifyCustomerOnSave"><?php echo Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER_ON_SAVE'); ?></label>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-info" id="recalculateBtn">
                <span class="icon-loop" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CALCULATE_TOTAL_TAX'); ?>
            </button>
            <button type="button" class="btn btn-success" id="saveOrderBtn">
                <span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SAVE_ORDER'); ?>
            </button>
        </div>
    </div>
</div>
