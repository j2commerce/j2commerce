<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? 'j2commerce-product-options mb-3';
$options  = $product->options ?? [];

if (empty($options)) {
    return;
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php foreach ($options as $option): ?>
        <div class="j2commerce-option-group mb-2">
            <label class="form-label fw-semibold small"><?php echo htmlspecialchars($option->option_name ?? '', ENT_QUOTES, 'UTF-8'); ?></label>
            <?php if (($option->type ?? 'select') === 'select' && !empty($option->optionvalues)): ?>
                <select class="form-select form-select-sm" disabled>
                    <option><?php echo Text::_('COM_J2COMMERCE_SELECT_AN_OPTION'); ?></option>
                    <?php foreach ($option->optionvalues as $val): ?>
                        <option><?php echo htmlspecialchars($val->option_value ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
