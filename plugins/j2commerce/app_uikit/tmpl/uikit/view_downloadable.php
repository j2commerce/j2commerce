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

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

?>
<div class="product-<?php echo $this->product->j2commerce_product_id; ?> <?php echo $this->product->product_type; ?>-product">
    <div class="uk-grid" uk-grid>
        <div class="uk-width-1-2@s">
            <?php
            $images = $this->loadTemplate('images');
            J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.view.uikit']);
            echo $images;
            ?>
        </div>

        <div class="uk-width-1-2@s">
            <?php echo $this->loadTemplate('title'); ?>
            <?php if (isset($this->product->source->event->afterDisplayTitle)) : ?>
                <?php echo $this->product->source->event->afterDisplayTitle; ?>
            <?php endif; ?>

            <div class="price-sku-brand-container uk-grid" uk-grid>
                <?php if (J2CommerceHelper::product()->canShowprice($this->params)) : ?>
                    <div class="uk-width-1-2@s">
                        <?php echo $this->loadTemplate('price'); ?>
                    </div>
                <?php endif; ?>

                <div class="uk-width-1-2@s">
                    <?php if (isset($this->product->source->event->beforeDisplayContent)) : ?>
                        <?php echo $this->product->source->event->beforeDisplayContent; ?>
                    <?php endif; ?>

                    <?php if (J2CommerceHelper::product()->canShowSku($this->params)) : ?>
                        <?php echo $this->loadTemplate('sku'); ?>
                    <?php endif; ?>

                    <?php echo $this->loadTemplate('brand'); ?>
                    <?php if ($this->params->get('item_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->product->variant)) : ?>
                        <?php echo $this->loadTemplate('stock'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (J2CommerceHelper::product()->canShowCart($this->params)) : ?>
                <form action="<?php echo $this->product->cart_form_action; ?>"
                      method="post" class="j2commerce-addtocart-form"
                      id="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      name="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_id="<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_type="<?php echo $this->product->product_type; ?>"
                      enctype="multipart/form-data">

                    <?php echo $this->loadTemplate('cart'); ?>

                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($this->params->get('item_use_tabs', 1)) : ?>
        <?php echo $this->loadTemplate('tabs'); ?>
    <?php else : ?>
        <?php echo $this->loadTemplate('notabs'); ?>
    <?php endif; ?>

    <?php if (isset($this->product->source->event->afterDisplayContent)) : ?>
        <?php echo $this->product->source->event->afterDisplayContent; ?>
    <?php endif; ?>
</div>

<?php if ($this->params->get('item_show_product_upsells', 0) && !empty($this->up_sells) && count($this->up_sells)) : ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif; ?>

<?php if ($this->params->get('item_show_product_cross_sells', 0) && !empty($this->product->cross_sells)) : ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif; ?>
