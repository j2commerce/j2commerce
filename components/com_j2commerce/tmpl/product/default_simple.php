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
use Joomla\CMS\Language\Text;
?>
<div class="row">
    <div class="col-12 mb-3 d-lg-none">
        <?php echo $this->loadTemplate('title'); ?>
    </div>
    <div class="col-lg-5">
        <?php
        $images = $this->loadTemplate('images');
        J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.view.bootstrap']);
        echo $images;
        ?>
    </div>
    <div id="productDetails" class="col-lg-6 ms-lg-auto">
        <div class="d-none d-lg-block">
            <?php echo $this->loadTemplate('title'); ?>
        </div>

        <?php if(isset($this->item->source->event->afterDisplayTitle)) : ?>
            <?php echo $this->item->source->event->afterDisplayTitle; ?>
        <?php endif;?>

        <?php if(J2CommerceHelper::product()->canShowSku($this->params)): ?>
            <?php echo $this->loadTemplate('sku'); ?>
        <?php endif; ?>

        <?php echo $this->loadTemplate('sdesc'); ?>

        <div class="d-flex flex-wrap align-items-center mb-3">
            <?php if(J2CommerceHelper::product()->canShowprice($this->params)): ?>
                <?php echo $this->loadTemplate('price'); ?>
            <?php endif; ?>
        </div>

        <?php if(isset($this->item->source->event->beforeDisplayContent)) : ?>
            <?php echo $this->item->source->event->beforeDisplayContent; ?>
        <?php endif;?>


        <div class="price-sku-brand-container row mb-4">
            <?php if($this->params->get('item_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->item->variant)) : ?>
                <div class="col-md-6 col-lg-8 mb-3 mb-md-0">
                    <?php echo $this->loadTemplate('stock'); ?>
                    <?php if($this->item->variant->allow_backorder == 2 && !$this->item->variant->availability): ?>
                        <span class="backorder-notification">
                            <?php echo Text::_('COM_J2COMMERCE_BACKORDER_NOTIFICATION'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="col-md-6 col-lg-4">
                <?php echo $this->loadTemplate('brand'); ?>
            </div>
        </div>

        <?php if(J2CommerceHelper::product()->canShowCart($this->params)): ?>
            <form action="<?php echo $this->item->cart_form_action; ?>"
                  method="post" class="j2commerce-addtocart-form w-100 d-block mt-3"
                  id="j2commerce-addtocart-form-<?php echo $this->item->j2commerce_product_id; ?>"
                  name="j2commerce-addtocart-form-<?php echo $this->item->j2commerce_product_id; ?>"
                  data-product_id="<?php echo $this->item->j2commerce_product_id; ?>"
                  data-product_type="<?php echo $this->item->product_type; ?>"
                  enctype="multipart/form-data">

                <?php echo $this->loadTemplate('options'); ?>
                <?php echo $this->loadTemplate('cart'); ?>

            </form>
        <?php endif; ?>

        <?php if($this->params->get('item_use_tabs', 1)): ?>
            <?php echo $this->loadTemplate('accordiontabs'); ?>
        <?php endif; ?>
    </div>
</div>

<?php if(!$this->params->get('item_use_tabs', 1)): ?>
    <?php echo $this->loadTemplate('notabs'); ?>
<?php endif; ?>


<?php if($this->params->get('item_use_tabs', 1)): ?>
    <?php if($this->params->get('item_show_product_upsells', 0) && isset($this->up_sells)): ?>
        <?php echo $this->loadTemplate('upsells'); ?>
    <?php endif;?>
<?php endif; ?>

<?php if($this->params->get('item_use_tabs', 1)): ?>
    <?php if($this->params->get('item_show_product_cross_sells', 0) && isset($this->item->cross_sells)): ?>
        <?php echo $this->loadTemplate('crosssells'); ?>
    <?php endif;?>
<?php endif; ?>

<?php if (isset($this->item->source->event->afterDisplayContent)) : ?>
    <?php echo $this->item->source->event->afterDisplayContent; ?><!-- TODO -->
<?php endif;?>





