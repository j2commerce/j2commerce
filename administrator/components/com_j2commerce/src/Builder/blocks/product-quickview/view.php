<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings  = $settings ?? [];
$cssClass  = $settings['css_class'] ?? 'j2commerce-quickview';
$productId = $product->j2commerce_product_id ?? 0;

if (!($showQuickview ?? false)) {
    return;
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="btn btn-sm btn-light j2commerce-quickview-btn" data-product-id="<?php echo $productId; ?>">
        <i class="fa-solid fa-eye me-1"></i>
        <?php echo Text::_('COM_J2COMMERCE_QUICK_VIEW'); ?>
    </button>
</div>
