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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\OrderModel;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item           = $this->item;
$orderShipping  = $item->ordershipping ?? null;
$currency       = $item->currency_code ?? 'USD';
$currencySymbol = CurrencyHelper::getSymbol($currency) ?: $currency;
$paymentType    = (string) ($item->orderpayment_type ?? '');
$decimals       = CurrencyHelper::getDecimalPlace($currency);

$shippingMethods = $this->shippingMethods ?? [];
$currentShipName = (string) ($orderShipping->ordershipping_name ?? '');
$matchedMethod   = false;

foreach ($shippingMethods as $m) {
    if ($currentShipName !== '' && (string) ($m['name'] ?? '') === $currentShipName) {
        $matchedMethod = true;
        break;
    }
}

// Manual rate/tax fields are shown when "Custom Shipping" is active (no matching published method).
$showCustomShipping = !$matchedMethod;

$labelClass = 'form-label text-body-secondary text-uppercase fw-bold';
$labelStyle = 'font-size:12px;letter-spacing:.5px;';

?>
<div class="row g-0 j2c-payment-shipping-step">
    <div class="col-lg-7 p-4 j2c-shipping-panel">
        <div class="fw-bold mb-3" style="font-size:13px;color:#1f2b38;"><?php echo Text::_('COM_J2COMMERCE_ENTER_SHIPPING_DETAILS'); ?></div>

        <div class="mb-3 j2c-shipping-method-wrap">
            <label for="ordershipping_method_select" class="<?php echo $labelClass; ?>" style="<?php echo $labelStyle; ?>"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_METHOD'); ?></label>
            <select class="form-select j2c-shipping-select" id="ordershipping_method_select">
                <?php foreach ($shippingMethods as $m) : ?>
                    <?php $mName = (string) ($m['name'] ?? ''); ?>
                    <option value="<?php echo $this->escape($mName); ?>"
                            data-price="<?php echo $this->escape(number_format((float) ($m['price'] ?? 0), $decimals, '.', '')); ?>"
                            data-tax="<?php echo $this->escape(number_format((float) ($m['tax'] ?? 0), $decimals, '.', '')); ?>"
                            <?php echo ($matchedMethod && $mName === $currentShipName) ? 'selected' : ''; ?>>
                        <?php echo $this->escape($mName); ?>
                    </option>
                <?php endforeach; ?>
                <option value="__custom__" <?php echo $showCustomShipping ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_CUSTOM_SHIPPING'); ?></option>
            </select>
        </div>

        <?php // Custom name: only shown when "Custom Shipping" is selected (a real method supplies its own name). ?>
        <div id="j2c-custom-shipping" class="mb-3 j2c-custom-shipping <?php echo $showCustomShipping ? '' : 'd-none'; ?>">
            <label for="ordershipping_name" class="<?php echo $labelClass; ?>" style="<?php echo $labelStyle; ?>"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_NAME'); ?></label>
            <input type="text" class="form-control j2c-shipping-name" name="jform[ordershipping_name]" id="ordershipping_name"
                   value="<?php echo $this->escape($currentShipName); ?>">
        </div>

        <?php // Price + tax: always visible so a selected method's calculated rate is shown (and editable). ?>
        <div class="row g-3 mb-3 j2c-shipping-rate-fields">
            <div class="col">
                <label for="ordershipping_price" class="<?php echo $labelClass; ?>" style="<?php echo $labelStyle; ?>"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_PRICE'); ?></label>
                <div class="input-group">
                    <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <input type="number" class="form-control" name="jform[ordershipping_price]" id="ordershipping_price"
                           value="<?php echo number_format((float) ($orderShipping->ordershipping_price ?? 0), $decimals, '.', ''); ?>" step="0.01" min="0">
                </div>
            </div>
            <div class="col">
                <label for="ordershipping_tax" class="<?php echo $labelClass; ?>" style="<?php echo $labelStyle; ?>"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_TAX'); ?></label>
                <div class="input-group">
                    <span class="input-group-text j2c-affix fw-semibold"><?php echo $this->escape($currencySymbol); ?></span>
                    <input type="number" class="form-control" name="jform[ordershipping_tax]" id="ordershipping_tax"
                           value="<?php echo number_format((float) ($orderShipping->ordershipping_tax ?? 0), $decimals, '.', ''); ?>" step="0.01" min="0">
                </div>
            </div>
        </div>

        <div class="mb-1 j2c-tracking-wrap">
            <label for="ordershipping_tracking_id" class="<?php echo $labelClass; ?>" style="<?php echo $labelStyle; ?>"><?php echo Text::_('COM_J2COMMERCE_FIELD_TRACKING_NUMBER'); ?></label>
            <div class="input-group">
                <span class="input-group-text j2c-affix"><span class="fa-solid fa-barcode" aria-hidden="true"></span></span>
                <input type="text" class="form-control" name="jform[ordershipping_tracking_id]" id="ordershipping_tracking_id"
                       value="<?php echo $this->escape($orderShipping->ordershipping_tracking_id ?? ''); ?>">
            </div>
            <?php if (OrderModel::isLabelSlotHeld($orderShipping)): ?>
                <?php // Only a saved tracking number releases the claim, so the operator has to be told one is held while they decide what to type here. ?>
                <div class="mt-1">
                    <span class="badge bg-warning text-dark">
                        <span class="icon-warning" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_ORDER_LABEL_CLAIM_HELD'); ?>
                    </span>
                    <span class="d-block small text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_ORDER_LABEL_CLAIM_HELD_EDIT_DESC'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5 border-start bg-light p-4 j2c-payment-panel">
        <div class="text-body-secondary text-uppercase fw-bold mb-2" style="font-size:12px;letter-spacing:.5px;">
            <?php echo Text::_('COM_J2COMMERCE_SELECT_PAYMENT_METHOD'); ?>
        </div>

        <?php
        $paymentMethods = $this->paymentMethods ?? [];
        $noneChecked    = $paymentType === 'none';
        $canMarkPaid    = !empty($this->canMarkPaid);
        $offlineKeys    = $canMarkPaid ? array_keys($this->markPaidMethods ?? []) : [];
        $checkedElement = $noneChecked ? 'none' : $paymentType;

        if (\count($paymentMethods) === 1 && !$noneChecked) {
            $checkedElement = (string) ($paymentMethods[array_key_first($paymentMethods)]['element'] ?? $checkedElement);
        }

        $offlineChecked = $canMarkPaid && \in_array($checkedElement, $offlineKeys, true);
        ?>
        <?php if (!empty($this->originalMethodMissing)) : ?>
            <div class="alert alert-warning j2c-original-method-missing">
                <?php echo Text::sprintf('COM_J2COMMERCE_ORIGINAL_METHOD_UNAVAILABLE', $this->escape((string) $this->originalMethodLabel)); ?>
            </div>
        <?php endif; ?>
        <?php if (empty($paymentMethods)) : ?>
            <div class="alert alert-info"><?php echo Text::_('COM_J2COMMERCE_NO_PAYMENT_METHODS'); ?></div>
        <?php endif; ?>
        <div class="d-flex flex-column gap-2 j2c-payment-list">
            <?php foreach ($paymentMethods as $i => $m) : ?>
                <?php
                $element    = (string) ($m['element'] ?? '');
                $pName      = (string) ($m['name'] ?? $element);
                $image      = (string) ($m['image'] ?? '');
                $isOriginal = $element !== '' && $element === $paymentType;
                // A lone published method auto-checks — unless "none" was explicitly saved.
                $checked = $isOriginal || (\count($paymentMethods) === 1 && !$noneChecked);
                ?>
                <label class="j2c-select-row j2c-payment-option <?php echo $checked ? 'selected' : ''; ?>" for="j2c-pay-<?php echo (int) $i; ?>">
                    <input type="radio" class="form-check-input j2c-payment-radio flex-shrink-0" name="jform[orderpayment_type]"
                           value="<?php echo $this->escape($element); ?>" id="j2c-pay-<?php echo (int) $i; ?>"<?php echo \in_array($element, $offlineKeys, true) ? ' data-j2c-offline="1"' : ''; ?> <?php echo $checked ? 'checked' : ''; ?>>
                    <span class="j2c-icon-tile bg-body-secondary text-body-secondary"><span class="fa-solid fa-credit-card" aria-hidden="true"></span></span>
                    <div class="flex-grow-1" style="min-width:0;line-height:1.4;">
                        <div class="fw-bold" style="font-size:14px;color:#1f2b38;">
                            <?php echo $this->escape(J2htmlHelper::translateKey($pName)); ?>
                            <?php if ($isOriginal) : ?>
                                <span class="badge j2c-badge-original ms-2"><?php echo Text::_('COM_J2COMMERCE_ORIGINAL_PAYMENT_METHOD'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($image !== '') : ?>
                        <img src="<?php echo $this->escape(ImageHelper::getImageUrl($image)); ?>" alt="" style="height:24px;flex:0 0 auto;">
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <?php // "No Payment Method Needed" — records orderpayment_type='none'; the JS hides Take Payment. ?>
            <label class="j2c-select-row j2c-payment-option j2c-payment-none <?php echo $noneChecked ? 'selected' : ''; ?>" for="j2c-pay-none">
                <input type="radio" class="form-check-input j2c-payment-radio flex-shrink-0" name="jform[orderpayment_type]"
                       value="none" id="j2c-pay-none" <?php echo $noneChecked ? 'checked' : ''; ?>>
                <span class="j2c-icon-tile bg-body-secondary text-body-secondary"><span class="fa-solid fa-ban" aria-hidden="true"></span></span>
                <div class="flex-grow-1" style="min-width:0;line-height:1.4;">
                    <div class="fw-bold" style="font-size:14px;color:#1f2b38;"><?php echo Text::_('COM_J2COMMERCE_NO_PAYMENT_METHOD_NEEDED'); ?></div>
                </div>
            </label>
        </div>
        <div class="form-text mt-2"><?php echo Text::_('COM_J2COMMERCE_ADMIN_PAYMENT_METHOD_NOTE'); ?></div>

        <?php $hasTakePayment = ($this->takePaymentUrl ?? '') !== ''; ?>
        <?php if ($hasTakePayment || !empty($this->isNew)) : ?>
            <?php // Rendered (hidden) for a brand-new order too: the create-on-first-Next
                  // response supplies the signed URL and the JS reveals the button. ?>
            <div id="j2c-take-payment-wrap" class="<?php echo ($hasTakePayment && !$noneChecked && !$offlineChecked) ? '' : 'd-none'; ?>">
                <a class="btn btn-primary w-100 mt-3 j2c-take-payment" href="<?php echo $this->escape($this->takePaymentUrl); ?>" target="_blank" rel="noopener">
                    <span class="fa-solid fa-credit-card me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_TAKE_PAYMENT'); ?>
                </a>
                <div class="form-text mt-2 j2c-take-payment-note"><?php echo Text::_('COM_J2COMMERCE_TAKE_PAYMENT_NOTE'); ?></div>
            </div>
        <?php endif; ?>
        <?php if ($canMarkPaid) : ?>
            <?php
            $mp     = $this->recordPayload;
            $today  = (string) HTMLHelper::_('date', 'now', 'Y-m-d');
            $placed = (string) HTMLHelper::_('date', (string) ($item->created_on ?? 'now'), 'Y-m-d');
            $noForm = 'j2c-mp-detached';
            ?>
            <?php // Not a form and no field is named: the inputs belong to no form, so Enter and Save cannot submit them, and jform never binds them. ?>
            <div id="j2c-mark-paid" class="mt-3 j2c-mark-paid<?php echo $offlineChecked ? '' : ' d-none'; ?>" data-saved-method="<?php echo $this->escape($paymentType); ?>">
                <fieldset class="options-form j2c-mark-paid-fields">
                    <legend><?php echo Text::_('COM_J2COMMERCE_MARK_PAYMENT_RECEIVED'); ?></legend>
                    <div class="form-grid">
                        <div class="control-group">
                            <div class="control-label"><label for="j2c-mp-amount"><?php echo Text::_('COM_J2COMMERCE_AMOUNT'); ?></label></div>
                            <div class="controls">
                                <input type="text" inputmode="decimal" autocomplete="off" form="<?php echo $noForm; ?>" id="j2c-mp-amount" class="form-control j2c-mp-input"
                                       aria-required="true" data-field="amount" value="<?php echo $this->escape((string) $mp['amountDefault']); ?>" aria-describedby="j2c-mp-balance-hint j2c-mp-error">
                                <div id="j2c-mp-balance-hint" class="form-text j2c-mp-balance-hint"><?php echo $this->escape((string) $mp['balanceHint']); ?></div>
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="j2c-mp-reference"><?php echo Text::_('COM_J2COMMERCE_FIELD_REFERENCE_OPTIONAL'); ?></label></div>
                            <div class="controls">
                                <input type="text" maxlength="255" autocomplete="off" form="<?php echo $noForm; ?>" id="j2c-mp-reference" class="form-control j2c-mp-input"
                                       data-field="reference" aria-describedby="j2c-mp-error">
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="control-label"><label for="j2c-mp-date"><?php echo Text::_('COM_J2COMMERCE_FIELD_DATE_RECEIVED'); ?></label></div>
                            <div class="controls">
                                <input type="date" form="<?php echo $noForm; ?>" id="j2c-mp-date" class="form-control j2c-mp-input" data-field="received_on"
                                       value="<?php echo $this->escape($today); ?>" min="<?php echo $this->escape($placed); ?>" max="<?php echo $this->escape($today); ?>" aria-describedby="j2c-mp-error">
                            </div>
                        </div>
                        <div class="control-group">
                            <div class="controls">
                                <div class="form-check">
                                    <input type="checkbox" form="<?php echo $noForm; ?>" id="j2c-mp-confirm" class="form-check-input j2c-mp-input" data-field="confirm" value="1">
                                    <label class="form-check-label" for="j2c-mp-confirm"><?php echo Text::_('COM_J2COMMERCE_FIELD_CONFIRM_WHEN_PAID'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="j2c-mp-error" class="alert alert-danger py-2 mb-2 j2c-mp-error" role="alert" hidden></div>
                    <button type="button" id="j2c-mp-submit" class="btn btn-primary w-100 j2c-mp-submit">
                        <?php echo Text::_('COM_J2COMMERCE_MARK_PAYMENT_RECEIVED'); ?><span class="fa-solid fa-chevron-right ms-2" aria-hidden="true"></span><span class="spinner-border spinner-border-sm ms-2 j2c-mp-spinner" aria-hidden="true" hidden></span>
                    </button>
                    <div class="form-text mt-2"><?php echo Text::_('COM_J2COMMERCE_MARK_PAYMENT_RECEIVED_NOTE'); ?></div>
                </fieldset>
            </div>
        <?php endif; ?>
        <?php // Not a form field: it reports a recorded payment and takes focus once the panel above has nothing left to take. ?>
        <?php if ($canMarkPaid) : ?>
            <div id="j2c-mp-status" class="mt-3 j2c-mp-status" role="status" aria-live="polite" tabindex="-1"></div>
        <?php endif; ?>
        <?php $paymentTaken = !$hasTakePayment && \in_array((string) ($item->transaction_status ?? ''), ['Completed', 'Authorized'], true); ?>
        <?php if ($paymentTaken || $canMarkPaid) : ?>
            <div id="j2c-payment-taken" class="mt-3 j2c-payment-taken"<?php echo $paymentTaken ? '' : ' hidden'; ?>>
                <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>"><span class="fa-solid fa-circle-check me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ORDER_PAYMENT_TAKEN'); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>
