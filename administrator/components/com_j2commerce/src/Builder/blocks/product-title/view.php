<?php
\defined('_JEXEC') or die;

extract($displayData);

$settings    = $settings ?? [];
$tag         = $settings['tag'] ?? 'h3';
$fontSize    = $settings['font_size'] ?? 'fs-5';
$cssClass    = $settings['css_class'] ?? 'j2commerce-product-title';
$linkEnabled = $settings['link'] ?? true;

// Merge font_size class if not already present in css_class
if ($fontSize && strpos($cssClass, $fontSize) === false) {
    $cssClass .= ' ' . $fontSize;
}

if (!($showTitle ?? true)) {
    return;
}

$productName = htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8');
$linkHref    = htmlspecialchars($productLink ?? '#', ENT_QUOTES, 'UTF-8');
?>
<<?php echo $tag; ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($linkEnabled && !empty($productLink)): ?>
        <a href="<?php echo $linkHref; ?>" title="<?php echo $productName; ?>" class="text-decoration-none">
    <?php endif; ?>

    <?php echo $productName; ?>

    <?php if ($linkEnabled && !empty($productLink)): ?>
        </a>
    <?php endif; ?>
</<?php echo $tag; ?>>
