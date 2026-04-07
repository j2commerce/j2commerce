<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? 'j2commerce-product-options mb-3';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-options">
    <div class="j2commerce-option-group mb-2">
        <label class="form-label fw-semibold small">
            <j2c-token data-j2c-token="OPTION_NAME"><?php echo Text::_('COM_J2COMMERCE_OPTION'); ?></j2c-token>
        </label>
        <select class="form-select form-select-sm" disabled>
            <option><?php echo Text::_('COM_J2COMMERCE_SELECT_AN_OPTION'); ?></option>
        </select>
    </div>
</div>
