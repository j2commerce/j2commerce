<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings     = $settings ?? [];
$showQuantity = $settings['show_quantity'] ?? false;
$cssClass     = $settings['css_class'] ?? 'j2commerce-product-stock';
$variant      = $product->variant ?? null;
$inStock      = ($variant->availability ?? 1) > 0;
$stockClass   = $inStock ? 'in-stock' : 'out-of-stock';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?> small p-2 text-center <?php echo $stockClass; ?>" data-j2c-block="product-stock">
    <span class="badge <?php echo $inStock ? 'bg-success' : 'bg-danger'; ?>">
        <j2c-token data-j2c-token="STOCK_STATUS"><?php echo $inStock ? Text::_('COM_J2COMMERCE_IN_STOCK') : Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?></j2c-token>
    </span>
    <?php if ($showQuantity && $inStock): ?>
        <span class="j2commerce-stock-qty ms-1">(<j2c-token data-j2c-token="STOCK_QUANTITY"><?php echo (int) ($variant->quantity ?? 0); ?></j2c-token>)</span>
    <?php endif; ?>
</div>
