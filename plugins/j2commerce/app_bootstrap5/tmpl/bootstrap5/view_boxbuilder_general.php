<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
?>
<div class="boxbuilder-general">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeProductTitle', [$this->product, $this->context]); ?>

    <?php echo $this->loadTemplate('title'); ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductTitle', [$this->product, $this->context]); ?>

    <?php if (isset($this->product->source->event->afterDisplayTitle)): ?>
        <?php echo $this->product->source->event->afterDisplayTitle; ?>
    <?php endif; ?>

    <div class="sku-price-container row mb-3">
        <?php if (J2CommerceHelper::product()->canShowprice($this->params)) : ?>
            <div class="col-lg-6">
                <?php echo $this->loadTemplate('price'); ?>
            </div>
        <?php endif; ?>
        <?php if (J2CommerceHelper::product()->canShowSku($this->params)) : ?>
            <div class="col-lg-6">
                <?php echo $this->loadTemplate('sku'); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="stock-brand-container align-items-center row mb-4">
        <?php if ($this->params->get('item_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->product->variant)) : ?>
            <div class="col-lg-6">
                <?php echo $this->loadTemplate('stock'); ?>
            </div>
        <?php endif; ?>
        <div class="col-lg-6 text-lg-end">
            <?php echo $this->loadTemplate('brand'); ?>
        </div>
    </div>


    <?php echo $this->loadTemplate('sdesc'); ?>

    <?php if (isset($this->product->source->event->beforeDisplayContent)): ?>
        <?php echo $this->product->source->event->beforeDisplayContent; ?>
    <?php endif; ?>

</div>
