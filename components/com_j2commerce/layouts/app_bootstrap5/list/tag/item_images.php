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

if (!$showImage) {
    return;
}

$imageType = $params->get('list_image_type', 'thumbnail');
$platform = J2CommerceHelper::platform();

$image = '';
$imageAlt = '';

if ($imageType === 'thumbimage' || $imageType === 'thumbnail') {
    $image = $platform->getImagePath($product->thumb_image ?? '');
    $imageAlt = $product->thumb_image_alt ?? $product->product_name ?? '';
} else {
    $image = $platform->getImagePath($product->main_image ?? '');
    $imageAlt = $product->main_image_alt ?? $product->product_name ?? '';
}

if (empty($image)) {
    return;
}

$productName = htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8');
$imageAlt = htmlspecialchars($imageAlt ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="j2commerce-product-image">
    <?php if ($linkImage): ?>
        <a href="<?php echo htmlspecialchars($productLink ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <img src="<?php echo $image; ?>"
         alt="<?php echo $imageAlt; ?>"
         title="<?php echo $productName; ?>"
         class="j2commerce-img-responsive"
         width="<?php echo (int) $imageWidth; ?>"
         loading="lazy" />

    <?php if ($linkImage): ?>
        </a>
    <?php endif; ?>
</div>
