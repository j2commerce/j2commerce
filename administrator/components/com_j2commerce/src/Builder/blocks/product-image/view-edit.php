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

$settings    = $settings ?? [];
$cssClass    = $settings['css_class'] ?? 'j2commerce-product-image position-relative border mb-3';
$linkEnabled = $settings['link'] ?? true;
$maxHeight   = $settings['max_height'] ?? '200px';
$objectFit   = $settings['object_fit'] ?? 'cover';
$productName = htmlspecialchars($product->product_name ?? 'Product', ENT_QUOTES, 'UTF-8');
$imgStyle    = 'height:' . htmlspecialchars($maxHeight, ENT_QUOTES, 'UTF-8') . '; object-fit:' . htmlspecialchars($objectFit, ENT_QUOTES, 'UTF-8') . ';';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-image">
    <?php if ($linkEnabled): ?>
        <a href="#">
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-center bg-light" style="<?php echo $imgStyle; ?>">
        <j2c-token data-j2c-token="PRODUCT_IMAGE">
            <span class="fa-solid fa-image fa-3x text-muted"></span>
        </j2c-token>
    </div>

    <?php if ($linkEnabled): ?>
        </a>
    <?php endif; ?>
</div>
