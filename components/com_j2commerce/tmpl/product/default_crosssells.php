<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$original_product = $this->item;

$cross_sells = J2CommerceHelper::product()->getCrossSells($this->item);
$columns = $this->params->get('item_related_product_columns', 3);
//$total = count($this->item->cross_sells);
$counter = 0;
$cross_image_width = $this->params->get('item_product_cross_image_width', 100);


?>



<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingCrossSells', [$original_product])->getArgument('html'); ?>

<div class="product-crosssells">
    <div class="section__title--box text-start mb-5 mb-xl-7">
        <h2 class="text-uppercase ls-1 mb-4 fs-1"><span class="umarex-underline"><?php echo Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_CROSS_SELLS')?></span></h2>
    </div>

    <div class="position-relative mx-md-1">
        <button type="button" class="trending-prev btn btn-prev btn-icon btn-outline-secondary bg-body rounded-circle position-absolute top-50 start-0 z-2 translate-middle-y ms-n1 d-none d-sm-flex justify-content-center align-items-center" aria-label="Prev">
            <span class="si-chevron-left fs-4 text-center"></span>
        </button>
        <button type="button" class="trending-next btn btn-next btn-icon btn-outline-secondary bg-body rounded-circle position-absolute top-50 end-0 z-2 translate-middle-y me-n1 d-none d-sm-flex justify-content-center align-items-center" aria-label="Next">
            <span class="si-chevron-right fs-4 text-center"></span>
        </button>

        <div class="swiper py-4" data-swiper='{"slidesPerView": 1,"spaceBetween": 24,"loop": true,"navigation": {"prevEl": ".trending-prev","nextEl": ".trending-next"},"breakpoints": {"768": {"slidesPerView": 2}}}'>
            <div class="swiper-wrapper">
                <?php foreach($cross_sells as $cross_sell_product):


                    $cross_sell_product->product_link = $platform->getProductUrl(array('task' => 'view', 'id' => $cross_sell_product->j2commerce_product_id));
                    //$cross_sell_product->product_link = $model->getProductUrl(array('task' => 'view', 'id' => $cross_sell_product->j2commerce_product_id));
                    if(!empty($cross_sell_product->addtocart_text)) {
                        $cart_text = Text::_($cross_sell_product->addtocart_text);
                    } else {
                        $cart_text = Text::_('COM_J2COMMERCE_ADD_TO_CART');
                    }
                    $cross_product_name = $this->escape($cross_sell_product->product_name);
                    $thumb_image = '';

                    if (strpos($cross_sell_product->thumb_image, 'https:') !== false) {
                        $thumb_image = $cross_sell_product->thumb_image;
                    } else {
                        if(isset($cross_sell_product->thumb_image) && $cross_sell_product->thumb_image){
                            $thumb_image = $platform->getImagePath($cross_sell_product->thumb_image);
                        }
                    }


                    $this->singleton_product = $cross_sell_product;
                    $this->singleton_params = $this->params;
                    $this->singleton_cartext = $this->escape($cart_text);


                    ?>
                    <div class="swiper-slide j2commerce-single-product mb-3 multiple <?php echo $cross_sell_product->product_type;?>-product-type j2commerce-product-<?php echo $cross_sell_product->j2commerce_product_id;?> product-<?php echo $cross_sell_product->j2commerce_product_id;?>">
                        <div class="product-card animate-underline hover-effect-opacity bg-transparent border shadow-none rounded-1">
                            <div class="position-relative product-image">
                                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplayCategoryProduct', array($cross_sell_product, 'com_j2commerce.products.list.item'));?>
                                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductImage', array($cross_sell_product)); ?>
                                <div class="j2commerce-product-images">
                                    <?php //if($thumb_image): ?>
                                    <div class="j2commerce-thumbnail-image">
                                        <a class="d-block rounded-top overflow-hidden p-3 p-sm-4" href="<?php echo $cross_sell_product->product_link;?>">
                                            <div class="si-product-image">
                                                <img alt="<?php echo $cross_product_name;?>"  class="img-fluid j2c-product-thumb-image-<?php echo $cross_sell_product->j2commerce_product_id;?>" src="<?php echo $thumb_image;?>" width="<?php echo (int)$this->params->get('list_image_thumbnail_width', '200'); ?>">
                                            </div>
                                        </a>
                                    </div>
                                    <?php //endif;?>
                                </div>
                            </div>
                            <div class="w-100 min-w-0 px-3 pb-2 px-sm-3 pb-sm-3 product-content">
                                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCategoryProductTitle', array($cross_sell_product)); ?>

                                <?php if($this->params->get('list_show_product_sku', 1) && J2CommerceHelper::product()->canShowSku($this->params)) : ?>
                                    <?php if(!empty($cross_sell_product->variant->sku)) : ?>
                                        <div class="product-sku d-flex gap-1 fs-xs">
                                            <span class="sku-text"><?php echo Text::_('COM_J2COMMERCE_SKU')?>:</span>
                                            <span class="sku text-body-tertiary fs-xs"> <?php echo $cross_sell_product->variant->sku; ?> </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <h3 class="pb-1 mb-3 product-title font-j2commerce text-capitalize">
                                    <a class="d-block fs-5 fw-medium text-decoration-none text-dark" href="<?php echo $this->product->product_link; ?>" title="<?php echo $cross_product_name;?>" >
                                        <span class="underline-effect"><?php echo $cross_product_name;?></span>
                                    </a>
                                </h3>
                                <?php if(isset($cross_sell_product->event->afterDisplayTitle)) : ?>
                                    <?php echo $cross_sell_product->event->afterDisplayTitle; ?>
                                <?php endif;?>


                                <?php if(isset($cross_sell_product->event->beforeDisplayContent)) : ?>
                                    <?php echo $cross_sell_product->event->beforeDisplayContent; ?>
                                <?php endif;?>


                                <?php if( J2CommerceHelper::product()->canShowCart($this->params) ): ?>
                                    <form action="<?php echo $cross_sell_product->cart_form_action; ?>"
                                          method="post" class="j2commerce-addtocart-form mt-auto"
                                          id="j2commerce-addtocart-form-<?php echo $cross_sell_product->j2commerce_product_id; ?>"
                                          name="j2commerce-addtocart-form-<?php echo $cross_sell_product->j2commerce_product_id; ?>"
                                          data-product_id="<?php echo $cross_sell_product->j2commerce_product_id; ?>"
                                          data-product_type="<?php echo $cross_sell_product->product_type; ?>"
                                          enctype="multipart/form-data">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <?php if(J2CommerceHelper::product()->canShowprice($this->params)): ?>
                                                <?php echo $this->loadAnyTemplate('site:com_j2commerce/products/categoryprice');?>
                                            <?php endif; ?>

                                            <a href="<?php echo $cross_sell_product->product_link; ?>" class="product-card-button btn btn-icon rounded-1 btn-primary animate-slide-end ms-2 align-items-center btn btn-secondary" aria-label="<?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT_DETAILS'); ?>" data-bs-toggle="tooltip" data-bs-placement="left" title="<?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT_DETAILS'); ?>">
                                                <span class="si-shopping-cart fs-base animate-target align-self-center"></span>
                                            </a>
                                        </div>
                                    </form>
                                <?php endif; ?>
                                <div class="w-100 min-w-0 mt-2 product-footer d-flex align-items-center justify-content-center">
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayCategoryProduct', array($cross_sell_product, 'com_j2commerce.products.list.item'));?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach;?>
            </div>
        </div>

        <!-- External slider prev/next buttons visible on screens < 500px wide (sm breakpoint) -->
        <div class="d-flex justify-content-center gap-2 mt-n2 mb-3 pb-1 d-sm-none">
            <button type="button" class="trending-prev btn btn-prev btn-icon btn-outline-secondary bg-body rounded-circle me-1" aria-label="Prev">
                <span class="si-chevron-left fs-3"></span>
            </button>
            <button type="button" class="trending-next btn btn-next btn-icon btn-outline-secondary bg-body rounded-circle" aria-label="Next">
                <span class="si-chevron-right fs-3"></span>
            </button>
        </div>
    </div>
</div>
<?php $this->product = $original_product;?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingCrossSells', array($this->product)); ?>


<div class="row product-crosssells-container">
	<div class="col-sm-12">
		<h3><?php echo Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_CROSS_SELLS'); ?></h3>
        <?php foreach ($this->cross_sells as $cross_sell_product) : ?>
            <?php
                $cross_sell_product->product_link = J2CommerceHelper::platform()->getProductUrl(['task' => 'view', 'id' => $cross_sell_product->j2commerce_product_id]);
                if (!empty($cross_sell_product->addtocart_text)) {
                    $cart_text = Text::_($cross_sell_product->addtocart_text);
                } else {
                    $cart_text = Text::_('COM_J2COMMERCE_ADD_TO_CART');
                }
                $cross_product_name = $this->escape($cross_sell_product->product_name);
            ?>

            <?php $rowcount = ((int) $counter % (int) $columns) + 1; ?>
            <?php if ($rowcount == 1) : ?>
                <?php $row = $counter / $columns; ?>
                <div class="crosssell-product-row <?php echo 'row-'.$row; ?> row">
            <?php endif;?>

            <?php $cross_sell_css = $cross_sell_product->params->get('product_css_class',''); ?>
            <div class="col-sm-<?php echo round((12 / $columns));?> upsell-product product-<?php echo $cross_sell_product->j2commerce_product_id;?><?php echo isset($cross_sell_css) ? ' ' . $cross_sell_css : ''; ?>">
                <span class="cross-sell-product-image">
                    <?php
                        $thumb_image = '';
                        if(isset($cross_sell_product->thumb_image) && $cross_sell_product->thumb_image){
                            $thumb_image =$platform->getImagePath($cross_sell_product->thumb_image);
                        }
                    ?>
                    <?php if(isset($thumb_image) &&  !empty($thumb_image)):?>
                        <a href="<?php echo $cross_sell_product->product_link; ?>">
                            <img title="<?php echo $cross_product_name ;?>"
                                alt="<?php echo $cross_product_name ;?>"
                                class="j2commerce-product-thumb-image-<?php echo $cross_sell_product->j2commerce_product_id; ?>"
                                src="<?php echo $thumb_image;?>"
                                width="<?php echo intval($cross_image_width);?>"
                            />
                        </a>
                    <?php endif; ?>
                </span>
                <h3 class="cross-sell-product-title">
                    <a href="<?php echo $cross_sell_product->product_link; ?>">
                        <?php echo $cross_product_name; ?>
                    </a>
                </h3>

                <?php if (J2CommerceHelper::product()->canShowprice($this->params)) : ?>
                    <?php
                        $this->singleton_product = $cross_sell_product;
                        $this->singleton_params = $this->params;
                        echo $this->loadAnyTemplate('site:com_j2commerce/products/price'); // TODO
                    ?>
                <?php endif; ?>

                <?php if (J2CommerceHelper::product()->canShowCart($this->params)) : ?>
                    <?php $cross_sell_option = isset($cross_sell_product->options) && is_array($cross_sell_product->options) ? count($cross_sell_product->options) : 0; ?>
                    <?php if ($cross_sell_option || $cross_sell_product->product_type == 'variable') : ?>
                        <a class="<?php echo $this->params->get('choosebtn_class', 'btn btn-success'); ?>"
                            href="<?php echo $cross_sell_product->product_link; ?>">
                            <?php echo Text::_('COM_J2COMMERCE_CART_CHOOSE_OPTIONS'); ?>
                        </a>
                    <?php else: ?>
                        <?php
                            $this->singleton_product = $cross_sell_product;
                            $this->singleton_params = $this->params;
                            $this->singleton_cartext = $this->escape($cart_text);
                            echo $this->loadAnyTemplate('site:com_j2commerce/products/cart'); // TODO
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php $counter++; ?>
            <?php if (($rowcount == $columns) || ($counter == $total)) : ?>
                </div>
            <?php endif; ?>
        <?php endforeach;?>
	</div>
  </div>
