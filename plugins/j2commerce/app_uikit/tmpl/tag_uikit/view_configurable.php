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
<div class="product-<?php echo (int) $this->product->j2commerce_product_id; ?> <?php echo $this->escape($this->product->product_type); ?>-product">
    <div class="uk-grid uk-grid-large uk-margin-large-bottom" uk-grid>
        <div class="uk-width-1-2@l">
            <?php
            $images = $this->loadTemplate('images');
            J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.view.uikit']);
            echo $images;
            ?>
        </div>

        <div class="uk-width-1-2@l">
            <?php echo $this->loadTemplate('title'); ?>
            <?php if (isset($this->product->source->event->afterDisplayTitle)) : ?>
                <?php echo $this->product->source->event->afterDisplayTitle; ?>
            <?php endif; ?>

            <div class="sku-price-container uk-grid uk-margin-small-bottom" uk-grid>
                <?php if (J2CommerceHelper::product()->canShowprice($this->params)) : ?>
                    <div class="uk-width-1-2@l">
                        <?php echo $this->loadTemplate('price'); ?>
                    </div>
                <?php endif; ?>
                <?php if (J2CommerceHelper::product()->canShowSku($this->params)) : ?>
                    <div class="uk-width-1-2@l">
                        <?php echo $this->loadTemplate('sku'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stock-brand-container uk-grid uk-flex-middle uk-margin-bottom" uk-grid>
                <?php if ($this->params->get('item_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->product->variant)) : ?>
                    <div class="uk-width-1-2@l">
                        <?php echo $this->loadTemplate('stock'); ?>
                    </div>
                <?php endif; ?>
                <div class="uk-width-1-2@l uk-text-right@l">
                    <?php echo $this->loadTemplate('brand'); ?>
                </div>
            </div>

            <?php if ($this->params->get('item_show_sdesc')) : ?>
                <?php echo $this->loadTemplate('sdesc'); ?>
            <?php endif; ?>

            <?php if (isset($this->product->source->event->beforeDisplayContent)) : ?>
                <?php echo $this->product->source->event->beforeDisplayContent; ?>
            <?php endif; ?>

            <?php if (J2CommerceHelper::product()->canShowCart($this->params)) : ?>
                <form action="<?php echo $this->product->cart_form_action; ?>"
                      method="post" class="j2commerce-addtocart-form"
                      id="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      name="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_id="<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_type="<?php echo $this->product->product_type; ?>"
                      enctype="multipart/form-data">

                    <?php echo $this->loadTemplate('configurableoptions'); ?>
                    <?php echo $this->loadTemplate('cart'); ?>

                </form>
            <?php endif; ?>
            <?php if ($this->params->get('item_use_tabs', 1) == 2) : ?>
                <?php echo $this->loadTemplate('accordions'); ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($this->params->get('item_use_tabs', 1) == 1) : ?>
        <?php echo $this->loadTemplate('tabs'); ?>
    <?php endif; ?>

    <?php if ($this->params->get('item_use_tabs', 1) == 0) : ?>
        <?php echo $this->loadTemplate('notabs'); ?>
    <?php endif; ?>

    <?php if (isset($this->product->source->event->afterDisplayContent)) : ?>
        <?php echo $this->product->source->event->afterDisplayContent; ?>
    <?php endif; ?>
</div>

<?php if ($this->params->get('item_show_product_upsells', 0) && !empty($this->product->up_sells)) : ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif; ?>

<?php if ($this->params->get('item_show_product_cross_sells', 0) && !empty($this->product->cross_sells)) : ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif; ?>
