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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
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
$currency = J2CommerceHelper::currency();
?>
<div class="j2commerce-shipping-payment">

    <?php if ($showShippingMethods && !empty($shippingRates)) : ?>
    <h5 class="mb-1"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD'); ?></h5>
    <div id="shipping_error_div"></div>

    <div class="shipping-methods-group list-group mb-4 mt-3" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD', true); ?>">
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
            $rateImage = $rate['image'] ?? $rate->image ?? '';
            $rateDesc = $rate['desc'] ?? $rate->desc ?? '';
            $isSelected = (!empty($shippingValues['shipping_name']) && $shippingValues['shipping_name'] === $rateName)
                || (\count($shippingRates) === 1);
            ?>
            <label class="shipping-method-item list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" for="shipping-rate-<?php echo $i; ?>">
                <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="shipping_plugin" value="<?php echo htmlspecialchars($rateElement); ?>" id="shipping-rate-<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php if (!empty($rateImage)) : ?>
                    <img src="<?php echo htmlspecialchars($rateImage); ?>" alt="" class="flex-shrink-0 shipping-method-image">
                <?php endif; ?>
                <div class="shipping-method-display flex-grow-1">
                    <div class="shipping-method-name fw-medium"><?php echo htmlspecialchars($rateName); ?></div>
                    <?php if (!empty($rateDesc)) : ?>
                        <div class="shipping-method-desc"><small class="text-muted"><?php echo htmlspecialchars($rateDesc); ?></small></div>
                    <?php endif; ?>
                </div>
                <span class="fw-semibold flex-shrink-0"><?php echo $currency->format($ratePrice); ?></span>
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
        <h5 class="mb-1"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?></h5>
        <p class="text-muted small mb-3"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_TRANSACTIONS_SECURE'); ?></p>

        <div class="payment-methods-group list-group mb-4" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD', true); ?>">
            <?php if (!empty($paymentMethods)) : ?>
                <?php foreach ($paymentMethods as $i => $method) : ?>
                    <?php
                    $element = $method['element'] ?? $method->element ?? '';
                    $name = $method['name'] ?? $method->name ?? $element;
                    $image = $method['image'] ?? $method->image ?? '';
                    $isSelected = ($element === $selectedPayment) || (\count($paymentMethods) === 1);
                    ?>
                    <label class="payment-method-item list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" for="payment-method-<?php echo $i; ?>">
                        <input class="form-check-input flex-shrink-0 mt-0" type="radio" name="payment_plugin" value="<?php echo htmlspecialchars($element); ?>" id="payment-method-<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                        <div class="fw-medium flex-grow-1"><?php echo htmlspecialchars($name); ?></div>

                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCheckoutPaymentImage', [$method, 'onJ2Commerce'])->getArgument('html', ''); ?>
                        <?php echo J2CommerceHelper::getPaymentCardIcons($element); ?>

                        <?php if (!empty($image)) : ?>
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="" class="flex-shrink-0" style="height:24px;">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="alert alert-warning">
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NO_PAYMENT_METHODS'); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <button type="button" id="button-payment-method" class="btn btn-primary btn-checkout-step">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CONTINUE'); ?>
        </button>
    </div>
</div>
