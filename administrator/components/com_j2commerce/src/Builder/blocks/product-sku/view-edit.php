<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings  = $settings ?? [];
$showLabel = $settings['show_label'] ?? true;
$cssClass  = $settings['css_class'] ?? 'j2commerce-product-sku small';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-sku">
    <?php if ($showLabel): ?>
        <span class="sku-label"><?php echo Text::_('COM_J2COMMERCE_SKU'); ?>:</span>
    <?php endif; ?>
    <span class="sku-value fw-bold"><j2c-token data-j2c-token="PRODUCT_SKU"><?php echo htmlspecialchars($product->variant->sku ?? $product->sku ?? 'SKU-001', ENT_QUOTES, 'UTF-8'); ?></j2c-token></span>
</div>
