<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$settings      = $settings ?? [];
$showLabel     = $settings['show_label'] ?? true;
$cssClass      = $settings['css_class'] ?? 'j2commerce-product-sku small';
$productHelper = J2CommerceHelper::product();

if (!($showSku ?? true) || !$productHelper->canShowSku($params ?? null)) {
    return;
}

$sku = $product->variant->sku ?? $product->sku ?? '';
if (empty($sku)) {
    return;
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($showLabel): ?>
        <span class="sku-label"><?php echo Text::_('COM_J2COMMERCE_SKU'); ?></span>
    <?php endif; ?>
    <span class="sku-value fw-bold"><?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
