<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_images.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

extract($displayData);

$productName = htmlspecialchars($product->product_name ?? 'Product', ENT_QUOTES, 'UTF-8');
$basePrice   = $product->pricing->base_price ?? 0;
$salePrice   = $product->pricing->price ?? 0;
$showDiscountPercentage = true;
?>
<j2c-conditional data-condition="$showImage">
    <div class="j2commerce-product-image position-relative border mb-3">
        <j2c-conditional data-condition="$showDiscountPercentage && $basePrice > 0">
            <span class="discount-percentage <?php echo J2htmlHelper::badgeClass('badge text-bg-info'); ?> position-absolute top-0 start-0 mt-2 ms-2 mt-lg-3 ms-lg-3">
                <?php
                $discount = ($basePrice > 0) ? (1 - ($salePrice / $basePrice)) * 100 : 0;
                echo ($discount > 0) ? round($discount) . '% Off' : '';
                ?>
            </span>
        </j2c-conditional>

        <j2c-conditional data-condition="$linkImage">
            <a href="<j2c-token data-token="PRODUCT_LINK"><?php echo htmlspecialchars($productLink ?? '#', ENT_QUOTES, 'UTF-8'); ?></j2c-token>">
        </j2c-conditional>

        <img src="<j2c-token data-token="PRODUCT_IMAGE_URL"><?php echo htmlspecialchars($product->main_image ?? '', ENT_QUOTES, 'UTF-8'); ?></j2c-token>"
             alt="<j2c-token data-token="PRODUCT_IMAGE_ALT"><?php echo $productName; ?></j2c-token>"
             class="j2commerce-img-responsive img-fluid"
             style="width:100%;" />

        <j2c-conditional data-condition="$linkImage">
            </a>
        </j2c-conditional>

        <j2c-conditional data-condition="$showQuickview">
            <span data-j2c-locked="quickview-layout"></span>
        </j2c-conditional>
    </div>
</j2c-conditional>
