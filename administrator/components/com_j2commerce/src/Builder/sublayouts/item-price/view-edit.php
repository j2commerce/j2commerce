<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_price.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();
$pricing       = $product->pricing ?? null;

// Provide fallback preview values when no pricing data is available
$salePrice  = $pricing->price ?? 29.99;
$basePrice  = $pricing->base_price ?? 0;
?>
<j2c-conditional data-condition="$showPrice && $productHelper->canShowprice($params)">
    <span data-j2c-locked="before-html"></span>

    <div class="j2commerce-product-price-container d-flex align-items-center gap-1">
        <j2c-conditional data-condition="$showSpecialPrice && isset($pricing->price)">
            <div class="sale-price lh-1 fs-5 fw-semibold">
                <j2c-token data-token="PRODUCT_PRICE_FORMATTED"><?php echo $productHelper->displayPrice((float) $salePrice, $product, $params); ?></j2c-token>
            </div>
        </j2c-conditional>

        <j2c-conditional data-condition="$showBasePrice && $basePrice > 0 && $basePrice != $salePrice">
            <del class="base-price fs-6 fw-normal text-body-tertiary lh-1">
                <j2c-token data-token="PRODUCT_BASE_PRICE"><?php echo $productHelper->displayPrice((float) $basePrice, $product, $params); ?></j2c-token>
            </del>
        </j2c-conditional>

        <j2c-conditional data-condition="$showTaxInfo">
            <div class="tax-text">
                <small class="fw-normal text-body-tertiary">
                    <j2c-token data-token="PRODUCT_TAX_TEXT"><?php echo $productHelper->get_tax_text(); ?></j2c-token>
                </small>
            </div>
        </j2c-conditional>
    </div>

    <span data-j2c-locked="after-html"></span>
</j2c-conditional>
