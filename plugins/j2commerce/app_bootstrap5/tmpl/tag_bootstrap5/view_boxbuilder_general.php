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

    <?php if ($this->params->get('item_show_title', 1)): ?>
        <h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title font-j2commerce text-capitalize mb-3">
            <?php echo $this->escape($this->product->product_name); ?>
        </h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
    <?php endif; ?>

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductTitle', [$this->product, $this->context]); ?>

    <?php if (isset($this->product->source->event->afterDisplayTitle)): ?>
        <?php echo $this->product->source->event->afterDisplayTitle; ?>
    <?php endif; ?>

    <?php if (J2CommerceHelper::product()->canShowSku($this->params)): ?>
        <?php echo $this->loadTemplate('sku'); ?>
    <?php endif; ?>

    <?php echo $this->loadTemplate('sdesc'); ?>

    <div class="d-flex flex-wrap align-items-center mb-4">
        <?php if (J2CommerceHelper::product()->canShowprice($this->params)): ?>
            <?php echo $this->loadTemplate('price'); ?>
        <?php endif; ?>

        <?php if ($this->params->get('item_show_product_stock', 1) && J2CommerceHelper::product()->managing_stock($this->product->variant)): ?>
            <?php echo $this->loadTemplate('stock'); ?>
        <?php endif; ?>
    </div>

    <?php if (isset($this->product->source->event->beforeDisplayContent)): ?>
        <?php echo $this->product->source->event->beforeDisplayContent; ?>
    <?php endif; ?>

    <div class="price-sku-brand-container row">
        <div class="col-sm-6">
            <?php echo $this->loadTemplate('brand'); ?>
        </div>
    </div>
</div>
