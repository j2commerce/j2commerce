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

use Joomla\CMS\Language\Text;

$item = $this->item;
$orderItems = $item->orderitems ?? [];
$orderDiscounts = $item->orderdiscounts ?? [];
$currency = $item->currency_code ?? 'USD';

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
                    <td><?php echo $this->escape($orderItem->orderitem_name); ?></td>
                    <td class="text-center"><?php echo (int) $orderItem->orderitem_quantity; ?></td>
                    <td class="text-end"><?php echo $this->escape($currency); ?> <?php echo number_format((float) $orderItem->orderitem_finalprice, 2); ?></td>
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

        <?php // Cart Totals ?>
        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_CART_TOTALS'); ?></h2>
        <table class="table table-sm j2c-summary-table mb-4">
            <tbody>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_SUBTOTAL'); ?></td>
                    <td class="text-end" id="summarySubtotal"><?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_subtotal, 2); ?></td>
                </tr>
                <?php if ((float) $item->order_shipping > 0) : ?>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_SHIPPING'); ?></td>
                    <td class="text-end" id="summaryShipping"><?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_shipping, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ((float) ($item->order_surcharge ?? 0) > 0) : ?>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_SURCHARGE'); ?></td>
                    <td class="text-end" id="summarySurcharge"><?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_surcharge, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ((float) $item->order_discount > 0) : ?>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_DISCOUNT'); ?></td>
                    <td class="text-end text-danger" id="summaryDiscount">-<?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_discount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ((float) $item->order_tax > 0) : ?>
                <tr>
                    <td><?php echo Text::_('COM_J2COMMERCE_TAX'); ?></td>
                    <td class="text-end" id="summaryTax"><?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_tax, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td><strong><?php echo Text::_('COM_J2COMMERCE_TOTAL'); ?></strong></td>
                    <td class="text-end" id="summaryTotal"><strong><?php echo $this->escape($currency); ?> <?php echo number_format((float) $item->order_total, 2); ?></strong></td>
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
            <div class="col-md-3">
                <input type="number" class="form-control" name="fee_amount" id="feeAmount" step="0.01"
                       placeholder="<?php echo Text::_('COM_J2COMMERCE_FEE_AMOUNT'); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="fee_taxable" id="feeTaxable">
                    <option value="0"><?php echo Text::_('COM_J2COMMERCE_NOT_TAXABLE'); ?></option>
                    <option value="1"><?php echo Text::_('COM_J2COMMERCE_TAXABLE'); ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-primary w-100" id="addFeeBtn">
                    <?php echo Text::_('COM_J2COMMERCE_ADD_FEE'); ?>
                </button>
            </div>
        </div>

        <div class="alert alert-warning small">
            <span class="icon-warning" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_TAX_RECALC_WARNING'); ?>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-info" id="recalculateBtn">
                <span class="icon-loop" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CALCULATE_TOTAL_TAX'); ?>
            </button>
            <button type="button" class="btn btn-success" onclick="Joomla.submitbutton('order.save')">
                <span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SAVE_ORDER'); ?>
            </button>
        </div>
    </div>
</div>
