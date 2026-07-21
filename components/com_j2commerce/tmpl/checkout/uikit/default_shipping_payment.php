<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$showShipping = $this->showShipping ?? false;
$showShippingMethods = $this->showShippingMethods ?? false;
$shippingRates = $this->shippingRates ?? [];
$shippingValues = $this->shippingValues ?? [];
$paymentMethods = $this->paymentMethods ?? [];
$selectedPayment = $this->selectedPayment ?? '';
$showPayment = $this->showPayment ?? true;
$showTerms = $this->showTerms ?? 0;
$termsDisplayType = $this->termsDisplayType ?? 'link';
$paymentFields = $this->paymentFields ?? [];
$paymentFieldValues = $this->paymentFieldValues ?? [];
$currency = J2CommerceHelper::currency();
$platform = J2CommerceHelper::platform();
?>
<div class="j2commerce-shipping-payment">

    <?php if ($showShippingMethods && !empty($shippingRates)) : ?>
    <h5 class="uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD'); ?></h5>
    <div id="shipping_error_div"></div>

    <div class="shipping-methods-group uk-margin-bottom uk-margin-top" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD', true); ?>">
        <input type="hidden" name="shippingrequired" value="1">
        <?php foreach ($shippingRates as $i => $rate) : ?>
            <?php
            $rateName = $rate['name'] ?? $rate->name ?? '';
            $ratePrice = $rate['price'] ?? $rate->price ?? 0;
            $rateCode = $rate['code'] ?? $rate->code ?? '';
            $rateElement = $rate['element'] ?? $rate->element ?? '';
            $rateTax = $rate['tax'] ?? $rate->tax ?? 0;
            $rateTaxClassId = $rate['tax_class_id'] ?? $rate->tax_class_id ?? 0;
            $rateExtra = $rate['extra'] ?? $rate->extra ?? '';
            $rawRateImage = (string) ($rate['image'] ?? $rate->image ?? '');
            $rateImage = $rawRateImage !== '' ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawRateImage))->url : '';
            $rateDesc = $rate['desc'] ?? $rate->desc ?? '';
            $isSelected = (!empty($shippingValues['shipping_name']) && $shippingValues['shipping_name'] === $rateName)
                || (\count($shippingRates) === 1);
            ?>
            <label class="shipping-method-item list-group-item uk-flex uk-flex-middle uk-padding-small uk-margin-small-bottom" for="shipping-rate-<?php echo $i; ?>" style="gap: 12px; border: 1px solid #e5e5e5; border-radius: 4px;">
                <input class="uk-radio uk-flex-none" type="radio" name="shipping_plugin" value="<?php echo htmlspecialchars($rateElement); ?>" id="shipping-rate-<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php if (!empty($rateImage)) : ?>
                    <img src="<?php echo htmlspecialchars($rateImage); ?>" alt="" class="uk-flex-none shipping-method-image">
                <?php endif; ?>
                <div class="shipping-method-display uk-flex-1">
                    <div class="shipping-method-name uk-text-bold"><?php echo htmlspecialchars($rateName); ?></div>
                    <?php if (!empty($rateDesc)) : ?>
                        <div class="shipping-method-desc"><small class="uk-text-meta"><?php echo htmlspecialchars($rateDesc); ?></small></div>
                    <?php endif; ?>
                </div>
                <span class="uk-text-bold uk-flex-none"><?php echo $currency->format($ratePrice); ?></span>
                <input type="hidden" name="shipping_name" value="<?php echo htmlspecialchars($rateName); ?>">
                <input type="hidden" name="shipping_price" value="<?php echo (float) $ratePrice; ?>">
                <input type="hidden" name="shipping_code" value="<?php echo htmlspecialchars($rateCode); ?>">
                <input type="hidden" name="shipping_tax" value="<?php echo (float) $rateTax; ?>">
                <input type="hidden" name="shipping_tax_class_id" value="<?php echo (int) $rateTaxClassId; ?>">
                <input type="hidden" name="shipping_extra" value="<?php echo htmlspecialchars($rateExtra); ?>">
            </label>
        <?php endforeach; ?>
    </div>
    <?php elseif ($showShippingMethods) : ?>
        <input type="hidden" name="shippingrequired" value="0">
    <?php endif; ?>

    <?php if ($showPayment) : ?>
        <h5 class="uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?></h5>
        <p class="uk-text-meta uk-text-small uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_TRANSACTIONS_SECURE'); ?></p>

        <div class="payment-methods-group uk-margin-bottom" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD', true); ?>">
            <?php if (!empty($paymentMethods)) : ?>
                <?php foreach ($paymentMethods as $i => $method) : ?>
                    <?php
                    $element = $method['element'] ?? $method->element ?? '';
                    $name = $method['name'] ?? $method->name ?? $element;
                    $rawImage = (string) ($method['image'] ?? $method->image ?? '');
                    $image = $rawImage !== '' ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawImage))->url : '';
                    $isSelected = ($element === $selectedPayment) || (\count($paymentMethods) === 1);
                    ?>
                    <label class="payment-method-item uk-flex uk-flex-middle uk-padding-small uk-margin-small-bottom" for="payment-method-<?php echo $i; ?>" style="gap: 12px; border: 1px solid #e5e5e5; border-radius: 4px;">
                        <input class="uk-radio uk-flex-none" type="radio" name="payment_plugin" value="<?php echo htmlspecialchars($element); ?>" id="payment-method-<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                        <div class="uk-text-bold uk-flex-1"><?php echo htmlspecialchars($name); ?></div>

                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCheckoutPaymentImage', [$method, 'onJ2Commerce'])->getArgument('html', ''); ?>
                        <?php echo J2CommerceHelper::getPaymentCardIcons($element); ?>

                        <?php if (!empty($image)) : ?>
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="" class="uk-flex-none" style="height:24px;">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="uk-alert uk-alert-warning" uk-alert>
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NO_PAYMENT_METHODS'); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($paymentFields)) : ?>
            <div class="payment-custom-fields uk-grid uk-grid-small uk-margin-bottom" uk-grid>
                <?php foreach ($paymentFields as $field) : ?>
                    <?php echo CustomFieldHelper::renderField($field, (string) ($paymentFieldValues[$field->field_namekey] ?? ''), [], 'uikit'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayShippingPayment', [$this->order]); ?>

    <div class="uk-margin-top">
        <button type="button" id="button-payment-method" class="uk-button uk-button-primary btn-checkout-step">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
