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
use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderTransactionHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

$item           = $this->item;
$orderItems     = $item->orderitems ?? [];
$orderDiscounts = $item->orderdiscounts ?? [];
$orderFees      = $item->orderfees ?? [];
$currency       = $item->currency_code ?? 'USD';
$fmt            = static fn (float $amount): string => CurrencyHelper::format($amount, $currency);
$unitCount      = array_sum(array_map(static fn ($line): int => (int) $line->orderitem_quantity, $orderItems));

// Currency affix for the money-modal amount fields (position follows the currency config).
$currencySymbol = CurrencyHelper::getSymbol($currency) ?: $currency;
$symbolPre      = CurrencyHelper::getSymbolPosition($currency) !== 'post';

?>
<div class="row g-0">
    <div class="col-lg-8 p-4">
        <?php // ── Order items readback ── ?>
        <?php if (!empty($orderItems)) : ?>
        <div class="text-body-secondary text-uppercase fw-bold mb-2" style="font-size:12px;letter-spacing:.5px;"><?php echo Text::_('COM_J2COMMERCE_ORDER_ITEMS'); ?></div>
        <div class="border rounded-3 overflow-hidden mb-4">
            <?php foreach ($orderItems as $orderItem) : ?>
            <?php $imageUrl = (string) ($orderItem->image_url ?? ''); ?>
            <div class="j2c-summary-line d-flex align-items-center gap-3 px-3 py-2 border-bottom" data-item-id="<?php echo (int) $orderItem->j2commerce_orderitem_id; ?>">
                <?php if ($imageUrl !== '') : ?>
                    <img class="j2c-line-icon j2c-line-img" src="<?php echo $this->escape($imageUrl); ?>" alt="<?php echo $this->escape($orderItem->orderitem_name); ?>" loading="lazy">
                <?php else : ?>
                    <span class="j2c-line-icon j2c-icon-tile bg-body-secondary text-body-secondary"><span class="fa-solid fa-box-open" aria-hidden="true"></span></span>
                <?php endif; ?>
                <div class="j2c-summary-line-info flex-grow-1" style="min-width:0;">
                    <div class="j2c-summary-line-name fw-semibold text-truncate" style="font-size:13px;color:#2b3542;">
                        <?php echo $this->escape($orderItem->orderitem_name); ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', array($orderItem, $item, $this->params))->getArgument('html', ''); ?>
                    </div>
                    <div class="j2c-summary-line-meta text-body-secondary" style="font-size:12px;">
                        <?php echo Text::_('COM_J2COMMERCE_HEADING_QTY'); ?> <?php echo (int) $orderItem->orderitem_quantity; ?> &middot; <?php echo $fmt((float) $orderItem->orderitem_price); ?> <?php echo Text::_('COM_J2COMMERCE_EACH'); ?>
                    </div>
                </div>
                <div class="j2c-summary-line-total fw-bold text-end j2c-tabnum" style="font-size:14px;color:#1f2b38;"><?php echo $fmt((float) $orderItem->orderitem_finalprice); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php // ── Voucher / Coupon ── ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label text-body-secondary text-uppercase fw-bold" style="font-size:12px;letter-spacing:.5px;"><?php echo Text::_('COM_J2COMMERCE_VOUCHER'); ?></label>
                <div class="input-group">
                    <input type="text" class="form-control" name="voucher_code" id="voucherCode"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_VOUCHER'); ?>">
                    <button type="button" class="btn btn-primary" id="applyVoucherBtn">
                        <?php echo Text::_('JAPPLY'); ?>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label text-body-secondary text-uppercase fw-bold" style="font-size:12px;letter-spacing:.5px;"><?php echo Text::_('COM_J2COMMERCE_COUPON'); ?></label>
                <div class="input-group">
                    <input type="text" class="form-control" name="coupon_code" id="couponCode"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_COUPON'); ?>">
                    <button type="button" class="btn btn-primary" id="applyCouponBtn">
                        <?php echo Text::_('JAPPLY'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php // ── Applied discounts ── ?>
        <?php if (!empty($orderDiscounts)) : ?>
        <ul class="list-group mt-3">
            <?php foreach ($orderDiscounts as $discount) : ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <strong><?php echo $this->escape($discount->discount_title ?? ''); ?></strong>
                    <span class="text-body-secondary">(<?php echo $this->escape($discount->discount_code ?? ''); ?>)</span>
                    &mdash; <?php echo $fmt((float) ($discount->discount_amount ?? 0)); ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-j2c-remove-discount="<?php echo (int) $discount->j2commerce_orderdiscount_id; ?>"
                        aria-label="<?php echo Text::_('JACTION_DELETE'); ?>">
                    <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php // ── Add fee ── ?>
        <div class="mt-3">
            <label class="form-label text-body-secondary text-uppercase fw-bold" style="font-size:12px;letter-spacing:.5px;"><?php echo Text::_('COM_J2COMMERCE_ADD_FEE'); ?></label>
            <div class="input-group">
                <input type="text" class="form-control" name="fee_name" id="feeName"
                       placeholder="<?php echo Text::_('COM_J2COMMERCE_FEE_NAME'); ?>">
                <input type="number" class="form-control" name="fee_amount" id="feeAmount" step="0.01" style="max-width:140px;"
                       placeholder="<?php echo Text::_('COM_J2COMMERCE_FEE_AMOUNT'); ?>">
                <button type="button" class="btn btn-primary" id="addFeeBtn">
                    <span class="fa-solid fa-plus me-1" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_FEE'); ?>
                </button>
            </div>
            <?php if (!empty($orderFees)) : ?>
            <?php foreach ($orderFees as $fee) : ?>
            <div class="d-flex align-items-center justify-content-between bg-light border rounded-3 px-3 py-2 mt-2">
                <span class="fw-semibold" style="font-size:13px;color:#3a4757;"><span class="fa-solid fa-tag text-body-secondary me-2" aria-hidden="true"></span><?php echo $this->escape($fee->name ?? ''); ?></span>
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold" style="font-size:13px;color:#1f2b38;"><?php echo $fmt((float) ($fee->amount ?? 0) + (float) ($fee->tax ?? 0)); ?></span>
                    <button type="button" class="btn btn-sm text-body-secondary p-1"
                            data-j2c-remove-fee="<?php echo (int) $fee->j2commerce_orderfee_id; ?>"
                            aria-label="<?php echo Text::_('JACTION_DELETE'); ?>">
                        <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php // ── Totals rail ── ?>
    <div class="col-lg-4 border-start bg-light d-flex flex-column p-0">
        <div class="p-3 j2c-bg-navy">
            <div class="fw-bold" style="font-size:15px;"><?php echo Text::_('COM_J2COMMERCE_CART_TOTALS'); ?></div>
            <div class="j2c-navy-sub" style="font-size:12px;"><?php echo Text::plural('COM_J2COMMERCE_N_ITEMS', $unitCount); ?></div>
        </div>
        <div class="p-3 flex-grow-1">
            <div class="d-flex justify-content-between py-1 text-body-secondary" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_SUBTOTAL'); ?></span>
                <span id="summarySubtotal" class="j2c-tabnum"><?php echo $fmt((float) $item->order_subtotal); ?></span>
            </div>
            <div id="summaryShippingRow" class="d-flex justify-content-between py-1 text-body-secondary <?php echo (float) $item->order_shipping > 0 ? '' : 'd-none'; ?>" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_SHIPPING'); ?></span>
                <span id="summaryShipping" class="j2c-tabnum"><?php echo $fmt((float) $item->order_shipping); ?></span>
            </div>
            <div id="summarySurchargeRow" class="d-flex justify-content-between py-1 text-body-secondary <?php echo (float) ($item->order_surcharge ?? 0) > 0 ? '' : 'd-none'; ?>" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_SURCHARGE'); ?></span>
                <span id="summarySurcharge" class="j2c-tabnum"><?php echo $fmt((float) $item->order_surcharge); ?></span>
            </div>
            <div id="summaryDiscountRow" class="d-flex justify-content-between py-1 text-body-secondary <?php echo (float) $item->order_discount > 0 ? '' : 'd-none'; ?>" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_DISCOUNT'); ?></span>
                <span id="summaryDiscount" class="text-danger j2c-tabnum">-<?php echo $fmt((float) $item->order_discount); ?></span>
            </div>
            <div id="summaryTaxRow" class="d-flex justify-content-between py-1 text-body-secondary <?php echo (float) $item->order_tax > 0 ? '' : 'd-none'; ?>" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_TAX'); ?></span>
                <span id="summaryTax" class="j2c-tabnum"><?php echo $fmt((float) $item->order_tax); ?></span>
            </div>
            <div id="summaryFeesRow" class="d-flex justify-content-between py-1 text-body-secondary <?php echo (float) ($item->order_fees ?? 0) > 0 ? '' : 'd-none'; ?>" style="font-size:13px;">
                <span><?php echo Text::_('COM_J2COMMERCE_FEES'); ?></span>
                <span id="summaryFees" class="j2c-tabnum"><?php echo $fmt((float) ($item->order_fees ?? 0)); ?></span>
            </div>
            <div class="d-flex justify-content-between pt-2 mt-1 border-top fw-bold" style="font-size:18px;color:#1f2b38;">
                <span><?php echo Text::_('COM_J2COMMERCE_TOTAL'); ?></span>
                <span id="summaryTotal" class="j2c-tabnum"><strong><?php echo $fmt((float) $item->order_total); ?></strong></span>
            </div>

            <?php
            // Payment method used + gateway-injected card details — the SAME
            // AfterAdminOrderPaymentInfo hook the order view fires, so the store
            // owner sees identical payment info here (brand, last4, etc.).
            $summaryPaymentType = (string) ($item->orderpayment_type ?? '');
            if ((int) ($item->j2commerce_order_id ?? 0) > 0 && $summaryPaymentType !== '') :
                $summaryPaymentName  = J2CommerceHelper::getPaymentDisplayName($summaryPaymentType);
                $summaryPaymentImage = '';
                $summaryPlugin       = PluginHelper::getPlugin('j2commerce', $summaryPaymentType);

                if ($summaryPlugin) {
                    $summaryPaymentImage = (new Registry($summaryPlugin->params ?? '{}'))->get('display_image', '');
                }

                if (empty($summaryPaymentImage)) {
                    $summaryPaymentImage = ImageHelper::getPluginImage($summaryPaymentType);
                }
            ?>
            <div class="j2c-summary-payment border rounded-3 p-2 mt-3">
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($summaryPaymentImage)) : ?>
                        <img class="j2c-summary-payment-img" src="<?php echo $this->escape(ImageHelper::getImageUrl($summaryPaymentImage)); ?>" alt="<?php echo $this->escape($summaryPaymentName); ?>" style="height:24px;flex:0 0 auto;">
                    <?php endif; ?>
                    <div class="j2c-summary-payment-name fw-bold" style="font-size:13px;color:#1f2b38;"><?php echo $this->escape($summaryPaymentName); ?></div>
                </div>
                <?php if (!empty($item->transaction_id)) : ?>
                    <div class="j2c-summary-payment-txn text-body-secondary small mt-1">
                        <?php echo Text::_('COM_J2COMMERCE_FIELD_TRANSACTION_ID'); ?>:
                        <strong class="text-body"><?php echo $this->escape($item->transaction_id); ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (!empty($item->transaction_status)) : ?>
                    <div class="j2c-summary-payment-status text-body-secondary small">
                        <?php echo Text::_('COM_J2COMMERCE_FIELD_TRANSACTION_STATUS'); ?>:
                        <strong class="text-body"><?php echo $this->escape($item->transaction_status); ?></strong>
                    </div>
                <?php endif; ?>
                <?php
                // Trusted gateway HTML — identical raw echo to view_details.php.
                try {
                    echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderPaymentInfo', array($item))->getArgument('html', '');
                } catch (\Throwable $e) {
                    // A misbehaving gateway must never break the summary step.
                }
                ?>
            </div>
            <?php endif; ?>

            <?php
            // Payment ledger reconciliation: when money has moved on this order,
            // show net paid / refunded / balance due so a total change made here
            // is immediately visible against what the customer actually paid.
            $ledgerOrderId = (int) ($item->j2commerce_order_id ?? 0);
            if ($ledgerOrderId > 0 && OrderTransactionHelper::hasLedger($ledgerOrderId)) :
                echo LayoutHelper::render('order.payment_balance', [
                    'order_id'       => $ledgerOrderId,
                    'order_total'    => (float) ($item->order_total ?? 0),
                    'currency_code'  => (string) ($item->currency_code ?? 'USD'),
                    'currency_value' => (float) ($item->currency_value ?? 1.0),
                ], JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts');
            ?>
            <?php if ($this->canChargeBalance || $this->canRefundPayment) : ?>
            <div class="d-flex gap-2 mt-2 j2c-payment-actions">
                <?php if ($this->canChargeBalance) : ?>
                    <button type="button" class="btn btn-sm btn-primary flex-grow-1 j2c-charge-balance-btn"
                            data-bs-toggle="modal" data-bs-target="#chargeBalanceModal">
                        <span class="fa-solid fa-credit-card me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CHARGE_BALANCE'); ?>
                    </button>
                <?php endif; ?>
                <?php if ($this->canRefundPayment) : ?>
                    <button type="button" class="btn btn-sm btn-outline-danger flex-grow-1 j2c-refund-btn"
                            data-bs-toggle="modal" data-bs-target="#refundPaymentModal">
                        <span class="fa-solid fa-rotate-left me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_REFUND'); ?>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="alert d-flex gap-2 mt-3 mb-0 p-2" role="alert" style="background:#fff7ed;border:1px solid #fbe0c2;color:#9a6413;font-size:12px;">
                <span class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></span>
                <div><?php echo Text::_('COM_J2COMMERCE_TAX_RECALC_WARNING'); ?></div>
            </div>

            <div class="mt-3 j2c-summary-status">
                <label for="order_state_id" class="form-label text-body-secondary fw-semibold" style="font-size:13px;"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_STATUS'); ?></label>
                <select name="order_state_id" id="order_state_id" class="form-select">
                    <?php foreach ($this->orderStatuses as $status) : ?>
                        <option value="<?php echo (int) $status->value; ?>" <?php echo ((int) ($item->order_state_id ?? 0) === (int) $status->value) ? 'selected' : ''; ?>>
                            <?php echo $this->escape(Text::_($status->text)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" class="form-check-input" name="notify_customer" id="notifyCustomerOnSave" value="1">
                <label class="form-check-label text-body-secondary" for="notifyCustomerOnSave" style="font-size:13px;"><?php echo Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER_ON_SAVE'); ?></label>
            </div>

            <button type="button" class="btn btn-outline-primary w-100 mt-3" id="recalculateBtn">
                <span class="fa-solid fa-rotate me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CALCULATE_TOTAL_TAX'); ?>
            </button>
            <button type="button" class="btn btn-primary w-100 mt-2" id="saveOrderBtn" style="height:48px;">
                <span class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SAVE_ORDER'); ?>
            </button>
        </div>
    </div>
</div>

<?php
// Amounts in the two money modals are ledger figures — already display currency,
// so format with exchangeRate 1.0 (no base→display multiplication).
$moneyDecimals = CurrencyHelper::getDecimalPlace($currency);
$fmtDisplay    = static fn (float $v): string => CurrencyHelper::format($v, $currency, 1.0);
$amountAttr    = static fn (float $v): string => number_format($v, $moneyDecimals, '.', '');
?>
<?php if ($this->canRefundPayment) : ?>
<div class="modal fade j2c-refund-modal" id="refundPaymentModal" tabindex="-1" aria-labelledby="refundPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="refundPaymentModalLabel"><?php echo Text::_('COM_J2COMMERCE_REFUND_PAYMENT_TITLE'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body">
                <label for="refundAmount" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FEE_AMOUNT'); ?></label>
                <div class="input-group">
                    <?php if ($symbolPre) : ?>
                        <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <?php endif; ?>
                    <input type="number" class="form-control" id="refundAmount" min="0.01" step="0.01"
                           max="<?php echo $amountAttr($this->refundableAmount); ?>"
                           value="<?php echo $amountAttr($this->refundableAmount); ?>">
                    <?php if (!$symbolPre) : ?>
                        <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-text"><?php echo Text::sprintf('COM_J2COMMERCE_REFUND_AMOUNT_MAX', $fmtDisplay($this->refundableAmount)); ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-danger" id="refundPaymentConfirmBtn"><?php echo Text::_('COM_J2COMMERCE_REFUND'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if ($this->canChargeBalance) : ?>
<div class="modal fade j2c-charge-modal" id="chargeBalanceModal" tabindex="-1" aria-labelledby="chargeBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="chargeBalanceModalLabel"><?php echo Text::_('COM_J2COMMERCE_CHARGE_BALANCE_TITLE'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body p-3">
                <label for="chargeAmount" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FEE_AMOUNT'); ?></label>
                <div class="input-group">
                    <?php if ($symbolPre) : ?>
                        <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <?php endif; ?>
                    <input type="number" class="form-control" id="chargeAmount" min="0.01" step="0.01"
                           max="<?php echo $amountAttr($this->balanceDueAmount); ?>"
                           value="<?php echo $amountAttr($this->balanceDueAmount); ?>">
                    <?php if (!$symbolPre) : ?>
                        <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-text"><?php echo Text::sprintf('COM_J2COMMERCE_CHARGE_AMOUNT_MAX', $fmtDisplay($this->balanceDueAmount)); ?></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-primary" id="chargeBalanceConfirmBtn"><?php echo Text::_('COM_J2COMMERCE_CHARGE_BALANCE'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
