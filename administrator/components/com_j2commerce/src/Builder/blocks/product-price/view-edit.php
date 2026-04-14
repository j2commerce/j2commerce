<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

extract($displayData);

$settings      = $settings ?? [];
$showSpecial   = $settings['show_special'] ?? true;
$showDiscount  = $settings['show_discount'] ?? true;
$showTaxInfo   = $settings['show_tax_info'] ?? false;
$cssClass      = $settings['css_class'] ?? 'j2commerce-product-price';
$format        = $settings['format'] ?? 'standard';
$showSaleBadge = $settings['show_sale_badge'] ?? true;
$priceClass    = match ($format) {
    'large'   => 'sale-price lh-1 fs-3 fw-semibold',
    'compact' => 'sale-price lh-1 fs-6 fw-semibold',
    default   => 'sale-price lh-1 fs-5 fw-semibold',
};
$pricing      = $product->pricing ?? null;
$basePrice    = (float) ($pricing->base_price ?? 0);
$salePrice    = (float) ($pricing->price ?? 0);
$hasSpecial   = $showSpecial && $basePrice > 0 && $basePrice != $salePrice;
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?> d-flex align-items-center gap-1" data-j2c-block="product-price">
    <?php if ($hasSpecial): ?>
        <div class="<?php echo $priceClass; ?>">
            <j2c-token data-j2c-token="PRODUCT_SPECIAL_PRICE">$<?php echo number_format($salePrice, 2); ?></j2c-token>
        </div>
        <del class="base-price fs-6 fw-normal text-body-tertiary lh-1">
            <j2c-token data-j2c-token="PRODUCT_PRICE">$<?php echo number_format($basePrice, 2); ?></j2c-token>
        </del>
        <?php if (($showDiscount || $showSaleBadge) && $basePrice > 0): ?>
            <span class="badge bg-danger ms-1">
                <j2c-token data-j2c-token="PRODUCT_DISCOUNT">-<?php echo (int) round((1 - $salePrice / $basePrice) * 100); ?>%</j2c-token>
            </span>
        <?php endif; ?>
    <?php else: ?>
        <div class="<?php echo $priceClass; ?>">
            <j2c-token data-j2c-token="PRODUCT_PRICE">$<?php echo number_format($salePrice ?: 9.99, 2); ?></j2c-token>
        </div>
    <?php endif; ?>

    <?php if ($showTaxInfo): ?>
        <div class="tax-text">
            <small class="fw-normal text-body-tertiary"><j2c-token data-j2c-token="TAX_INFO">incl. tax</j2c-token></small>
        </div>
    <?php endif; ?>
</div>
