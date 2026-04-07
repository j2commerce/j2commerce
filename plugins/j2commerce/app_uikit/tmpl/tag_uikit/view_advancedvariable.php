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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$uikit_plugin = PluginHelper::getPlugin('j2commerce', 'app_uikit');
$pluginParams = new Registry();
$pluginParams->loadString($uikit_plugin->params ?? '{}');
$enable_sticky_sidebar = $pluginParams->get('enable_sticky_sidebar');

$sticky_class = $enable_sticky_sidebar === '1' ? ' uk-position-sticky' : '';
$product_helper = J2CommerceHelper::product();
?>
<section class="product-<?php echo $this->product->j2commerce_product_id; ?> <?php echo $this->product->product_type; ?>-product uk-margin-bottom">
    <div class="uk-grid" uk-grid>
        <div class="uk-width-1-1 uk-margin-bottom uk-hidden@s">
            <?php if ($this->params->get('item_show_title', 1)) : ?>
                <h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title uk-margin-remove">
                    <?php echo $this->escape($this->product->product_name); ?>
                </h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
            <?php endif; ?>
        </div>
        <div class="uk-width-1-2@s<?php echo $sticky_class; ?>">
            <?php
            $images = $this->loadTemplate('images');
            J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.view.uikit']);
            echo $images;
            ?>
        </div>

        <div class="uk-width-1-2@s">
            <div class="uk-position-relative" id="ProductDetails">
                <?php echo $this->loadTemplate('title'); ?>
                <?php if (isset($this->product->source->event->afterDisplayTitle)) : ?>
                    <?php echo $this->product->source->event->afterDisplayTitle; ?>
                <?php endif; ?>
                <?php if ($product_helper->canShowSku($this->params)) : ?>
                    <?php echo $this->loadTemplate('sku'); ?>
                <?php endif; ?>
                <?php echo $this->loadTemplate('sdesc'); ?>

                <div class="uk-flex uk-flex-wrap uk-flex-middle uk-margin-bottom">
                    <?php if ($product_helper->canShowprice($this->params)) : ?>
                        <?php echo $this->loadTemplate('price'); ?>
                    <?php endif; ?>
                    <?php if ($this->params->get('item_show_product_stock', 1)) : ?>
                        <?php echo $this->loadTemplate('stock'); ?>

                        <?php if (isset($this->product->variant->allow_backorder) && $this->product->variant->allow_backorder == 2 && !$this->product->variant->availability) : ?>
                            <span class="backorder-notification">
                                <?php echo Text::_('COM_J2COMMERCE_BACKORDER_NOTIFICATION'); ?>
                            </span>
                        <?php else : ?>
                            <span class="backorder-notification"></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (isset($this->product->source->event->beforeDisplayContent)) : ?>
                    <?php echo $this->product->source->event->beforeDisplayContent; ?>
                <?php endif; ?>
            </div>
            <div class="price-sku-brand-container uk-grid" uk-grid>
                <div class="uk-width-1-2@s">
                    <?php echo $this->loadTemplate('brand'); ?>
                </div>
            </div>

            <?php if ($product_helper->canShowCart($this->params)) : ?>
                <form action="<?php echo $this->product->cart_form_action; ?>"
                      method="post" class="j2commerce-addtocart-form uk-width-1-1 uk-display-block"
                      id="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      name="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_id="<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_type="<?php echo $this->product->product_type; ?>"
                    <?php if (isset($this->product->variant_json)) : ?>
                        data-product_variants="<?php echo $this->escape($this->product->variant_json); ?>"
                    <?php endif; ?>
                      enctype="multipart/form-data">

                    <?php echo $this->loadTemplate('advancedvariableoptions'); ?>

                    <?php echo $this->loadTemplate('cart'); ?>
                    <input type="hidden" name="variant_id" value="<?php echo isset($this->product->variant->j2commerce_variant_id) ? $this->product->variant->j2commerce_variant_id : ''; ?>" />
                </form>
            <?php endif; ?>


            <?php if ($this->params->get('item_use_tabs', 1)) : ?>
                <?php echo $this->loadTemplate('tabs'); ?>
                <?php if (isset($this->product->source->event->afterDisplayContent)) : ?>
                    <?php echo $this->product->source->event->afterDisplayContent; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <?php if (!$this->params->get('item_use_tabs')) : ?>
        <?php echo $this->loadTemplate('notabs'); ?>
        <?php if (isset($this->product->source->event->afterDisplayContent)) : ?>
            <?php echo $this->product->source->event->afterDisplayContent; ?>
        <?php endif; ?>
    <?php endif; ?>

</section>

<?php if ($this->params->get('item_show_product_upsells', 0) && !empty($this->product->up_sells)) : ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif; ?>

<?php if ($this->params->get('item_show_product_cross_sells', 0) && !empty($this->product->cross_sells)) : ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif; ?>
