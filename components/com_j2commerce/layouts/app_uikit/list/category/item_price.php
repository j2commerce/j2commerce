<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;


use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();

if (!$showPrice || !$productHelper->canShowprice($params)) {
    return;
}

$pricing = $product->pricing ?? null;
if (!$pricing) {
    return;
}

$showBasePrice = (bool) $params->get('list_show_product_base_price', 1);
$showSpecialPrice = (bool) $params->get('list_show_product_special_price', 1);
$showTaxInfo = (bool) $params->get('display_price_with_tax_info', 0);


if (!$showBasePrice && !$showSpecialPrice) {
    return;
}

$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [$product])->getArgument('html', '');
$afterHtml = J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [$product])->getArgument('html', '');
$basePrice = $pricing->base_price ?? 0;
$salePrice = $pricing->price ?? 0;
?>
<?php echo $beforeHtml; ?>

<div class="j2commerce-product-price-container uk-flex uk-flex-middle" style="gap: .25rem;">
    <?php if ($showSpecialPrice && isset($pricing->price)): ?>
        <div class="sale-price uk-text-large uk-text-bold">
            <?php echo $productHelper->displayPrice((float) $salePrice, $product, $params); ?>
        </div>
    <?php endif; ?>

    <?php if ($showBasePrice && $basePrice > 0 && $basePrice != $salePrice): ?>
        <del class="base-price uk-text-muted">
            <?php echo $productHelper->displayPrice((float) $basePrice, $product, $params); ?>
        </del>
    <?php endif; ?>

    <?php if ($showTaxInfo): ?>
        <div class="tax-text">
            <small class="uk-text-muted"><?php echo $productHelper->get_tax_text(); ?></small>
        </div>
    <?php endif; ?>
</div>

<?php echo $afterHtml; ?>


