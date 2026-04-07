<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings     = $settings ?? [];
$showQuantity = $settings['show_quantity'] ?? true;
$btnClass     = $settings['btn_class'] ?? 'btn btn-primary';
$cssClass     = $settings['css_class'] ?? 'j2commerce-addtocart-form mt-auto';
$btnText      = $settings['btn_text'] ?? '';
$btnSize      = $settings['btn_size'] ?? 'default';
$btnSizeClass = ($btnSize !== 'default') ? ' ' . $btnSize : '';
$buttonLabel  = $btnText ?: Text::_('COM_J2COMMERCE_ADD_TO_CART');
?>
<form class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="j2commerce-product-options mb-3">
        <div class="j2commerce-option-group mb-2">
            <label class="form-label fw-semibold small">
                <?php echo Text::_('COM_J2COMMERCE_OPTION'); ?>
            </label>
            <select class="form-select form-select-sm" disabled>
                <option><?php echo Text::_('COM_J2COMMERCE_SELECT_AN_OPTION'); ?></option>
            </select>
        </div>
    </div>
    <div class="j2commerce-add-to-cart">
        <div class="j2commerce-cart-buttons d-flex align-items-center">
            <div class="input-group">
                <?php if ($showQuantity): ?>
                    <input type="number" value="1" min="1" class="form-control qty-input" style="max-width:70px;" disabled />
                <?php endif; ?>
                <button type="button" class="j2commerce-cart-button flex-fill <?php echo htmlspecialchars($btnClass . $btnSizeClass, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    <?php echo htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </div>
    </div>
</form>
