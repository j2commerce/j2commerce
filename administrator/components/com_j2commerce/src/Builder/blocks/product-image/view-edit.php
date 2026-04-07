<?php
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
            <i class="fa-solid fa-image fa-3x text-muted"></i>
        </j2c-token>
    </div>

    <?php if ($linkEnabled): ?>
        </a>
    <?php endif; ?>
</div>
