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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$paymentMethods  = $this->paymentMethods ?? [];
$selectedPayment = $this->selectedPayment ?? '';
$showPayment     = $this->showPayment ?? true;
$platform        = J2CommerceHelper::platform();
?>
<div class="payment-methods-group uk-margin-bottom" role="radiogroup" aria-label="<?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD', true); ?>">
    <?php if ($showPayment && !empty($paymentMethods)) : ?>
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
    <?php elseif ($showPayment) : ?>
        <div class="uk-alert uk-alert-warning" uk-alert>
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT_NO_PAYMENT_METHODS'); ?>
        </div>
    <?php endif; ?>
</div>
