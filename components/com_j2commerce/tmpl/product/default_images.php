<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;


/*if ($this->params->get('item_enable_image_zoom', 1)) {
    HTMLHelper::_('script', 'com_j2commerce/zoom/jquery.zoom.min.js', ['version' => 'auto', 'relative' => true]);
}*/

$image_path = Uri::base();
$main_image = $this->item->main_image;
$thumb_image='';
$main_image_width = $this->params->get('item_product_main_image_width', '200');

$additional_image_width = $this->params->get('item_product_additional_image_width', '100');
$platform = J2CommerceHelper::platform();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$defaultLargeImage = 'images/default_800_full.webp';
$defaultThumbImage = 'images/default_800_thumb.webp';

$additional_images = json_decode($this->item->additional_images);
$additional_images = array_filter((array)$additional_images);

?>
<div class="position-relative j2commerce-product-image">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductDetailImage', [$this->item])->getArgument('html'); ?>

    <?php if ($this->params->get('item_show_product_main_image', 1) && !empty($this->item->main_image)) : ?>
        <div class="swiper w-100 mb-7 swiper-fade" data-swiper='{"loop": true,"navigation": {"prevEl": ".btn-prev","nextEl": ".btn-next"},"thumbs": {"swiper": "#thumbs"},"pagination": {"el": ".swiper-pagination","clickable": true}}'>
            <div class="swiper-wrapper">
                <?php if(!empty($main_image)):?>
                    <div class="swiper-slide">
                        <a class="j2c-product-image-link d-block cursor-zoom-in text-center" href="<?php echo $main_image;?>" data-fancybox="gallery" data-thumb="<?php echo $this->item->main_image;?>" title="<?php echo (!empty($this->item->main_image_alt)) ? $this->escape($this->item->main_image_alt) : $this->escape($this->item->product_name); ?>">
                            <img src="<?php echo $main_image;?>" data-zoom="<?php echo $main_image;?>" alt="<?php echo (!empty($this->item->main_image_alt)) ? $this->escape($this->item->main_image_alt) : $this->escape($this->item->product_name); ?>" class="img-fluid rounded-1 j2commerce-product-main-image-<?php echo $this->item->j2commerce_product_id; ?>">
                        </a>
                    </div>
                <?php endif;?>
                <?php if( $this->params->get('item_show_product_additional_image', 1) && isset($this->item->additional_images) && !empty($this->item->additional_images)):?>
                    <?php if(count($additional_images)):
                        $additional_images_alt = json_decode($this->item->additional_images_alt,true);
                        ?>
                        <?php foreach($additional_images as $key => $image):
                        $image_src = HTMLHelper::_('cleanImageURL', $image)->url;
                        ?>
                        <div class="swiper-slide">
                            <a class="j2c-product-image-link d-block cursor-zoom-in text-center" href="<?php echo $image_src;?>" data-fancybox="gallery" data-thumb="<?php echo $image_src;?>" title="<?php echo $this->escape($this->item->product_name).' '.$key;?>">
                                <img src="<?php echo $image_src;?>" data-zoom="<?php echo $image_src;?>" alt="<?php echo $this->escape($this->item->product_name).' '.$key;?>" class="j2commerce-item-additionalimage img-fluid rounded-1">
                            </a>
                        </div>
                        <?php //endif;?>
                    <?php endforeach;?>
                    <?php endif;?>
                <?php endif;?>
            </div>
            <?php if (!empty(array_filter($additional_images))):?>
                <div class="position-absolute top-50 start-0 z-2 translate-middle-y ms-sm-2 ms-lg-3">
                    <button type="button" class="btn btn-prev btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-start" aria-label="Prev">
                        <span class="si-chevron-left fs-4 animate-target align-self-center"></span>
                    </button>
                </div>

                <div class="position-absolute top-50 end-0 z-2 translate-middle-y me-sm-2 me-lg-3">
                    <button type="button" class="btn btn-next btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-end" aria-label="Next">
                        <span class="si-chevron-right fs-4 animate-target"></span>
                    </button>
                </div>
            <?php endif;?>
        </div>
    <?php endif; ?>
    <?php if( $this->params->get('item_show_product_additional_image', 1) && isset($this->item->additional_images) && !empty($this->item->additional_images)):
        $additional_images = json_decode($this->item->additional_images);
        $additional_images = array_filter((array)$additional_images);
        if(count($additional_images)) :
            $additional_images_alt = json_decode($this->item->additional_images_alt,true);
            ?>
            <div class="px-lg-4f mt-3 pt-3">
                <div class="swiper swiper-load swiper-thumbs d-none d-lg-block w-100 j2commerce-product-additional-images" id="thumbs" data-swiper='{"direction": "horizontal","spaceBetween": 12,"slidesPerView": 5,"watchSlidesProgress": true,"loop": true,"navigation": {"nextEl": ".swiper-button-next","prevEl": ".swiper-button-prev"}}'>
                    <div class="swiper-wrapper flex-row">
                        <?php if(!empty($mainImage)):?>
                            <div class="swiper-slide swiper-thumb border rounded-2">
                                <div class="j2commerce-image-container ratio ratio-1x1">
                                    <img src="<?php echo $mainImage;?>" alt="<?php echo (!empty($this->item->main_image_alt)) ? $this->escape($this->item->main_image_alt) : $this->escape($this->item->product_name); ?>" class="img-fluid rounded-1 additional-mainimage j2commerce-item-additionalimage-preview swiper-thumb-img">
                                </div>
                            </div>
                        <?php elseif (!empty($this->item->main_image)):?>
                            <?php echo J2CommerceHelper::product()->displayImage($this->item,array('type'=>'AdditionalMain','params' => $this->params,'alt'=> $this->escape($this->item->main_image_alt))); ?>
                        <?php endif;?>

                        <?php foreach($additional_images as $key => $image):
                            $image = HTMLHelper::_('cleanImageURL', $image)->url;
                            if(!empty($image)):
                                $image_src = $image;
                                ?>
                                <div class="swiper-slide swiper-thumb border rounded-2">
                                    <div class="j2commerce-image-container ratio ratio-1x1">
                                        <img src="<?php echo $image_src;?>" alt="<?php echo $this->escape($this->item->product_name).' '.$key;?>" class="img-fluid rounded-1 j2commerce-item-additionalimage-preview swiper-thumb-img">
                                    </div>
                                </div>
                            <?php elseif(!empty($image)):?>
                                <?php echo J2CommerceHelper::product()->displayImage($this->item,array('type'=>'ViewAdditional','params' => $this->params,'key'=>$key,'image' => $image, 'alt' =>(isset($additional_images_alt[$key]) && !empty($additional_images_alt[$key])) ? $this->escape($additional_images_alt[$key]) : $this->escape($this->item->product_name))); ?>
                            <?php endif;?>
                        <?php endforeach;?>
                    </div>
                </div>
            </div>
        <?php endif;?>
    <?php endif;?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductDetailImage', [$this->item])->getArgument('html'); ?>
</div>


