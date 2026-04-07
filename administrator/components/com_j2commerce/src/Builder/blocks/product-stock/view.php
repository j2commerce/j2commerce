<?php
\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$settings      = $settings ?? [];
$cssClass      = $settings['css_class'] ?? 'j2commerce-product-stock';
$productHelper = J2CommerceHelper::product();

if (!($showStock ?? true)) {
    return;
}

$variant = $product->variant ?? null;
if (!$variant || !\is_object($variant)) {
    return;
}

$manageStock = $productHelper->managing_stock($variant);
$inStock     = !$manageStock || !empty($variant->availability);
$stockClass  = $inStock ? 'in-stock' : 'out-of-stock';

if (!$inStock) {
    $stockText = Text::_('COM_J2COMMERCE_OUT_OF_STOCK');
} elseif ($manageStock) {
    $componentParams = ComponentHelper::getParams('com_j2commerce');
    $stockText       = ProductHelper::displayStock($variant, $componentParams);

    if (empty($stockText)) {
        return;
    }
} else {
    $stockText = Text::_('COM_J2COMMERCE_IN_STOCK');
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?> small p-2 text-center <?php echo $stockClass; ?>">
    <?php echo $stockText; ?>
</div>
