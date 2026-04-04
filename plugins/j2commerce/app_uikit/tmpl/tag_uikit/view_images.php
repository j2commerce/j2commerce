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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$platform    = J2CommerceHelper::platform();
$productId   = (int) $this->product->j2commerce_product_id;
$productName = $this->escape($this->product->product_name);
$enableZoom  = (int) $this->params->get('item_enable_image_zoom', 1);
$main_width  = (int) $this->params->get('item_product_main_image_width', 700);
$additional_width  = (int) $this->params->get('item_product_additional_image_width', 100);


$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_j2commerce.vendor.swiper.css', 'media/com_j2commerce/vendor/swiper/css/swiper-bundle.min.css');
$wa->registerAndUseScript('com_j2commerce.vendor.swiper', 'media/com_j2commerce/vendor/swiper/js/swiper-bundle.min.js', [], ['defer' => true]);
if ($enableZoom) {
    $wa->registerAndUseStyle('com_j2commerce.vendor.zoom.css', 'media/com_j2commerce/vendor/zoom-vanilla/css/zoom.css');
    $wa->registerAndUseScript('com_j2commerce.vendor.zoom', 'media/com_j2commerce/vendor/zoom-vanilla/js/zoom-vanilla.min.js', [], ['defer' => true]);
}

$slides = [];

if ($this->params->get('item_show_product_main_image', 1) && !empty($this->product->main_image)) {
    $mainImagePath = $platform->getImagePath($this->product->main_image);
    if (!empty($mainImagePath)) {
        $slides[] = [
            'src' => $this->escape($mainImagePath),
            'alt' => !empty($this->product->main_image_alt)
                ? $this->escape($this->product->main_image_alt)
                : $productName,
        ];
    }
}

if ($this->params->get('item_show_product_additional_image', 1) && !empty($this->product->additional_images)) {
    try {
        $additionalImages = json_decode($this->product->additional_images, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        $additionalImages = [];
    }
    $additionalImages = array_filter((array) $additionalImages);

    $additionalImagesAlt = [];
    if (!empty($this->product->additional_images_alt)) {
        try {
            $additionalImagesAlt = json_decode($this->product->additional_images_alt, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $additionalImagesAlt = [];
        }
    }

    foreach ($additionalImages as $key => $image) {
        $imagePath = $platform->getImagePath($image);
        if (!empty($imagePath)) {
            $slides[] = [
                'src' => $this->escape($imagePath),
                'alt' => !empty($additionalImagesAlt[$key])
                    ? $this->escape($additionalImagesAlt[$key])
                    : $productName,
            ];
        }
    }
}

if (empty($slides)) {
    return;
}

$hasMultipleSlides = count($slides) > 1;
$galleryId = 'product-gallery-' . $productId;
$mainId    = 'product-gallery-main-' . $productId;
$thumbsId  = 'product-gallery-thumbs-' . $productId;
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductDetailImage', [$this->product])->getArgument('html', ''); ?>

<div class="product-gallery uk-position-relative" id="<?php echo $galleryId; ?>">
    <?php if ($this->params->get('item_show_discount_percentage', 1)
        && isset($this->product->pricing->is_discount_pricing_available)
        && isset($this->product->pricing->base_price)
        && !empty($this->product->pricing->base_price)
        && $this->product->pricing->base_price > 0) : ?>
        <?php $discount = (1 - ($this->product->pricing->price / $this->product->pricing->base_price)) * 100; ?>
        <?php if ($discount > 0) : ?>
            <span class="uk-label uk-label-success" style="position:absolute;top:.5rem;left:.5rem;z-index:3;">
                <?php echo Text::sprintf('COM_J2COMMERCE_PRODUCT_OFFER', round($discount) . '%'); ?>
            </span>
        <?php endif; ?>
    <?php endif; ?>

    <div class="product-gallery-main swiper" id="<?php echo $mainId; ?>"
         data-product-id="<?php echo $productId; ?>"
         data-enable-zoom="<?php echo $enableZoom; ?>">
        <div class="swiper-wrapper">
            <?php foreach ($slides as $slide) : ?>
                <div class="swiper-slide">
                    <img src="<?php echo ImageHelper::getProductImage($slide['src'], $main_width, 'raw'); ?>" alt="<?php echo $slide['alt']; ?>" class="j2commerce-product-main-image" <?php if ($enableZoom) : ?>data-action="zoom"<?php endif; ?> width="<?php echo $main_width; ?>" height="<?php echo $main_width; ?>" />
                    <?php if ($enableZoom) : ?>
                        <span class="product-zoom-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="swiper-button-prev" aria-label="<?php echo Text::_('JPREVIOUS'); ?>"<?php if (!$hasMultipleSlides) : ?> style="display:none"<?php endif; ?>></button>
        <button type="button" class="swiper-button-next" aria-label="<?php echo Text::_('JNEXT'); ?>"<?php if (!$hasMultipleSlides) : ?> style="display:none"<?php endif; ?>></button>
    </div>

    <div class="product-gallery-thumbs swiper" id="<?php echo $thumbsId; ?>"<?php if (!$hasMultipleSlides) : ?> style="display:none"<?php endif; ?>>
        <div class="swiper-wrapper">
            <?php if ($hasMultipleSlides) : ?>
                <?php foreach ($slides as $slide) : ?>
                    <div class="swiper-slide">
                        <?php echo ImageHelper::getProductImage($slide['src'], 100, 'html', 100, 'product-thumb', $slide['alt']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductDetailImage', [$this->product])->getArgument('html', ''); ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swiper === 'undefined') return;

        const mainEl = document.getElementById('<?php echo $mainId; ?>');
        const thumbsEl = document.getElementById('<?php echo $thumbsId; ?>');
        if (!mainEl) return;

        // Store original slides for variant gallery restoration
        mainEl.dataset.originalSlides = mainEl.querySelector('.swiper-wrapper').innerHTML;
        if (thumbsEl) {
            thumbsEl.dataset.originalSlides = thumbsEl.querySelector('.swiper-wrapper').innerHTML;
        }

        let thumbSwiper = null;
        <?php if ($hasMultipleSlides) : ?>
        thumbSwiper = new Swiper('#<?php echo $thumbsId; ?>', {
            spaceBetween: 12,
            slidesPerView: 5,
            freeMode: true,
            watchSlidesProgress: true,
            breakpoints: {
                0: { slidesPerView: 4, spaceBetween: 8 },
                768: { slidesPerView: 5, spaceBetween: 12 }
            }
        });
        <?php endif; ?>

        const mainSwiper = new Swiper('#<?php echo $mainId; ?>', {
            spaceBetween: 0,
            navigation: {
                nextEl: '#<?php echo $galleryId; ?> .swiper-button-next',
                prevEl: '#<?php echo $galleryId; ?> .swiper-button-prev'
            },
            thumbs: thumbSwiper ? { swiper: thumbSwiper } : undefined
        });

        // Store Swiper instances on DOM for JS gallery swap access
        mainEl._swiper = mainSwiper;
        if (thumbsEl) thumbsEl._swiper = thumbSwiper;
    });
</script>
