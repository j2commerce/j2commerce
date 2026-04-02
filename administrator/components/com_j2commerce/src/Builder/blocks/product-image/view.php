<?php
defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;

extract($displayData);

$settings    = $settings ?? [];
$linkEnabled = $settings['link'] ?? true;
$cssClass    = $settings['css_class'] ?? 'j2commerce-product-image position-relative border mb-3';
$aspectRatio = $settings['aspect_ratio'] ?? 'auto';
$objectFit   = $settings['object_fit'] ?? 'cover';
$maxHeight   = $settings['max_height'] ?? '200px';

if (!($showImage ?? true)) {
    return;
}

$platform   = J2CommerceHelper::platform();
$imageWidth = (int) $params->get('list_image_thumbnail_width', 350);
$image      = $platform->getImagePath($product->thumb_image ?? '');
$imageAlt   = htmlspecialchars($product->thumb_image_alt ?? $product->product_name ?? '', ENT_QUOTES, 'UTF-8');
$image      = HTMLHelper::_('cleanImageURL', $image)->url;

if (empty($image)) {
    return;
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($linkEnabled && !empty($productLink)): ?>
        <a href="<?php echo htmlspecialchars($productLink, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <?php
    $imgStyle = '';
    if ($maxHeight !== 'auto' && $maxHeight !== '') {
        $imgStyle .= 'max-height:' . htmlspecialchars($maxHeight, ENT_QUOTES, 'UTF-8') . ';';
    }
    if ($objectFit !== 'cover') {
        $imgStyle .= 'object-fit:' . htmlspecialchars($objectFit, ENT_QUOTES, 'UTF-8') . ';';
    }
    $imgClass = 'j2commerce-img-responsive img-fluid';
    if ($aspectRatio !== 'auto') {
        $ratioMap = ['1:1' => '1x1', '4:3' => '4x3', '16:9' => '16x9', '3:4' => '3x4'];
        $ratioClass = $ratioMap[$aspectRatio] ?? '';
        if ($ratioClass) {
            $imgClass .= ' ratio ratio-' . $ratioClass;
        }
    }
    echo ImageHelper::getProductImage($image, $imageWidth, 'html', $imageWidth, $imgClass, $imageAlt, $imgStyle ? ' style="' . $imgStyle . '"' : '');
    ?>

    <?php if ($linkEnabled && !empty($productLink)): ?>
        </a>
    <?php endif; ?>
</div>
