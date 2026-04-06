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

$bs5plus_plugin = PluginHelper::getPlugin('j2commerce', 'app_bootstrap5plus');
$pluginParams = new Registry();
$pluginParams->loadString($bs5plus_plugin->params);
$enable_sticky_sidebar = $pluginParams->get('enable_sticky_sidebar');

$sticky_class = $enable_sticky_sidebar === '1' ? ' sticky-lg-top max-content-height z-1' : '';
$product_helper = J2CommerceHelper::product();
?>
<section class="product-<?php echo (int) $this->product->j2commerce_product_id; ?> <?php echo $this->escape($this->product->product_type); ?>-product mb-5">
    <div class="row">
        <div class="col-12 mb-3 d-lg-none">
            <?php if ($this->params->get('item_show_title', 1)) : ?>
                <h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title h3 mb-0 font-j2commerce text-capitalize">
                    <?php echo $this->escape($this->product->product_name); ?>
                </h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
            <?php endif; ?>
        </div>
        <div class="col-lg-5<?php echo $sticky_class; ?>">
            <?php
            $images = $this->loadTemplate('images');
            J2CommerceHelper::plugin()->event('BeforeDisplayImages', [&$images, $this, 'com_j2commerce.products.view.bootstrap']);
            echo $images;
            ?>
        </div>

        <div class="col-lg-6 ms-lg-auto">
            <div class="position-relative" id="ProductDetails">
                <?php echo $this->loadTemplate('title'); ?>
                <?php if (isset($this->product->source->event->afterDisplayTitle)) : ?>
                    <?php echo $this->product->source->event->afterDisplayTitle; ?>
                <?php endif; ?>
                <?php if ($product_helper->canShowSku($this->params)) : ?>
                    <?php echo $this->loadTemplate('sku'); ?>
                <?php endif; ?>
                <?php echo $this->loadTemplate('sdesc'); ?>

                <div class="d-flex flex-wrap align-items-center mb-4">
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
            <div class="price-sku-brand-container row">
                <div class="col-sm-6">
                    <?php echo $this->loadTemplate('brand'); ?>
                </div>
            </div>

            <?php if ($product_helper->canShowCart($this->params)) : ?>
                <form action="<?php echo $this->escape($this->product->cart_form_action); ?>"
                      method="post" class="j2commerce-addtocart-form w-100 d-block mt-3"
                      id="j2commerce-addtocart-form-<?php echo (int) $this->product->j2commerce_product_id; ?>"
                      name="j2commerce-addtocart-form-<?php echo (int) $this->product->j2commerce_product_id; ?>"
                      data-product_id="<?php echo (int) $this->product->j2commerce_product_id; ?>"
                      data-product_type="<?php echo $this->escape($this->product->product_type); ?>"
                    <?php if (isset($this->product->variant_json)) : ?>
                        data-product_variants="<?php echo $this->escape($this->product->variant_json); ?>"
                    <?php endif; ?>
                      enctype="multipart/form-data">

                    <?php echo $this->loadTemplate('advancedvariableoptions'); ?>

                    <?php echo $this->loadTemplate('shippingtag'); ?>

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

<?php if ($this->params->get('item_show_product_upsells', 0) && !empty($this->up_sells) && count($this->up_sells)) : ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif; ?>

<?php if ($this->params->get('item_show_product_cross_sells', 0) && !empty($this->product->cross_sells)) : ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif; ?>
