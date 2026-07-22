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
        </div>
    </div>

    <div class="col-lg-5 border-start bg-light p-4 j2c-payment-panel">
        <div class="text-body-secondary text-uppercase fw-bold mb-2" style="font-size:12px;letter-spacing:.5px;">
            <?php echo Text::_('COM_J2COMMERCE_SELECT_PAYMENT_METHOD'); ?>
        </div>

        <?php
        $paymentMethods = $this->paymentMethods ?? [];
        $noneChecked    = $paymentType === 'none';
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
                           value="<?php echo $this->escape($element); ?>" id="j2c-pay-<?php echo (int) $i; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                    <span class="j2c-icon-tile bg-body-secondary text-body-secondary"><span class="fa-solid fa-credit-card" aria-hidden="true"></span></span>
                    <div class="flex-grow-1" style="min-width:0;line-height:1.4;">
                        <div class="fw-bold" style="font-size:14px;color:#1f2b38;">
                            <?php echo $this->escape(Text::_($pName)); ?>
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
            <div id="j2c-take-payment-wrap" class="<?php echo ($hasTakePayment && !$noneChecked) ? '' : 'd-none'; ?>">
                <a class="btn btn-primary w-100 mt-3 j2c-take-payment" href="<?php echo $this->escape($this->takePaymentUrl); ?>" target="_blank" rel="noopener">
                    <span class="fa-solid fa-credit-card me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_TAKE_PAYMENT'); ?>
                </a>
                <div class="form-text mt-2 j2c-take-payment-note"><?php echo Text::_('COM_J2COMMERCE_TAKE_PAYMENT_NOTE'); ?></div>
            </div>
        <?php endif; ?>
        <?php if (!$hasTakePayment && \in_array((string) ($item->transaction_status ?? ''), ['Completed', 'Authorized'], true)) : ?>
            <div class="mt-3 j2c-payment-taken">
                <span class="badge text-bg-success"><span class="fa-solid fa-circle-check me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_ORDER_PAYMENT_TAKEN'); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>
