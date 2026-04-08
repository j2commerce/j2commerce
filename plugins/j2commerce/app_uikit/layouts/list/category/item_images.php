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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

if (!$showImage) {
    return;
}

$showDiscountPercentage = (bool) $params->get('list_show_discount_percentage', 1);
$imageType = $params->get('list_image_type', 'thumbnail');
$image_width  = (int) $params->get('list_image_thumbnail_width', 350);
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
$image = HTMLHelper::_('cleanImageURL', $image)->url;

if (empty($image)) {
    return;
}

$productName = htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8');
$imageAlt = htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8');

$basePrice = $product->pricing->base_price ?? 0;
$salePrice = $product->pricing->price ?? 0;

?>
<div class="j2commerce-product-image uk-position-relative uk-margin-small-bottom">
    <?php if ($showDiscountPercentage && $basePrice > 0): ?>
        <?php $discount = (1 - ($salePrice / $basePrice)) * 100; ?>
        <?php if ($discount > 0): ?>
            <span class="discount-percentage uk-badge uk-position-absolute uk-position-top-left uk-margin-small"><?php echo Text::sprintf('COM_J2COMMERCE_PRODUCT_OFFER', round($discount) . '%'); ?></span>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($linkImage): ?>
        <a href="<?php echo htmlspecialchars($productLink, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php echo ImageHelper::getProductImage($image, $image_width, 'html', $image_width, 'j2commerce-img-responsive uk-responsive-width uk-border', $imageAlt);?>

    <?php if ($linkImage): ?>
        </a>
    <?php endif; ?>
    <?php if ($showQuickview): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_quickview', $displayData); ?>
    <?php endif; ?>
</div>
