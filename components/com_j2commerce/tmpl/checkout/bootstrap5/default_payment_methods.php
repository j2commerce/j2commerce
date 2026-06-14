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

$paymentMethods  = $this->paymentMethods ?? [];
$selectedPayment = $this->selectedPayment ?? '';
$showPayment     = $this->showPayment ?? true;
?>
<div class="payment-methods-group list-group mb-4" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD', true); ?>">
    <?php if ($showPayment && !empty($paymentMethods)) : ?>
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
    <?php elseif ($showPayment) : ?>
        <div class="alert alert-warning">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NO_PAYMENT_METHODS'); ?>
        </div>
    <?php endif; ?>
</div>
