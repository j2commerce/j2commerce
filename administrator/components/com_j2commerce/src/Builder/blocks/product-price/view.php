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

extract($displayData);

$settings       = $settings ?? [];
$showSpecial    = $settings['show_special'] ?? true;
$showTaxInfo    = $settings['show_tax_info'] ?? false;
$cssClass       = $settings['css_class'] ?? 'j2commerce-product-price';
$format         = $settings['format'] ?? 'standard';
$showSaleBadge  = $settings['show_sale_badge'] ?? true;
$salePriceClass = match ($format) {
    'large'   => 'sale-price lh-1 fs-3 fw-semibold',
    'compact' => 'sale-price lh-1 fs-6 fw-semibold',
    default   => 'sale-price lh-1 fs-5 fw-semibold',
};
$productHelper = J2CommerceHelper::product();

if (!($showPrice ?? true) || !$productHelper->canShowprice($params ?? null)) {
    return;
}

$pricing = $product->pricing ?? null;
if (!$pricing) {
    return;
}

$showBasePrice    = $showSpecial;
$showSpecialPrice = $showSpecial;

if (!$showBasePrice && !$showSpecialPrice) {
    return;
}

$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$product])->getArgument('html', '');
$afterHtml  = J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$product])->getArgument('html', '');
$basePrice  = $pricing->base_price ?? 0;
$salePrice  = $pricing->price ?? 0;
?>
<?php echo $beforeHtml; ?>

<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?> d-flex align-items-center gap-1">
    <?php if ($showSpecialPrice && isset($pricing->price)): ?>
        <div class="<?php echo $salePriceClass; ?>">
            <?php echo $productHelper->displayPrice((float) $salePrice, $product, $params); ?>
        </div>
    <?php endif; ?>

    <?php if ($showBasePrice && $basePrice > 0 && $basePrice != $salePrice): ?>
        <del class="base-price fs-6 fw-normal text-body-tertiary lh-1">
            <?php echo $productHelper->displayPrice((float) $basePrice, $product, $params); ?>
        </del>
    <?php endif; ?>

    <?php if ($showTaxInfo): ?>
        <div class="tax-text">
            <small class="fw-normal text-body-tertiary"><?php echo $productHelper->get_tax_text(); ?></small>
        </div>
    <?php endif; ?>
</div>

<?php echo $afterHtml; ?>
