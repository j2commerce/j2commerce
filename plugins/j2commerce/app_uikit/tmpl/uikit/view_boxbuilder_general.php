<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
?>

<div class="boxbuilder-general">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeProductTitle', [$this->product, J2CommerceHelper::utilities()->getContext('title')]); ?>
    <?php if ($this->params->get('item_show_title', 1)): ?>
        <h<?php echo $this->params->get('item_title_headertag', '2'); ?> class="product-title uk-text-capitalize uk-margin-bottom">
            <?php echo $this->escape($this->product->product_name); ?>
        </h<?php echo $this->params->get('item_title_headertag', '2'); ?>>
    <?php endif; ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductTitle', [$this->product, J2CommerceHelper::utilities()->getContext('title')]); ?>
    <?php if (isset($this->product->source->event->afterDisplayTitle)): ?>
        <?php echo $this->product->source->event->afterDisplayTitle; ?>
    <?php endif; ?>
    <?php if (J2CommerceHelper::product()->canShowSku($this->params)): ?>
        <?php echo $this->loadTemplate('sku'); ?>
    <?php endif; ?>
    <?php echo $this->loadTemplate('sdesc'); ?>

    <div class="uk-flex uk-flex-wrap uk-flex-middle uk-margin-bottom">
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

    <div class="price-sku-brand-container uk-grid" uk-grid>
        <div class="uk-width-1-2@s">
            <?php echo $this->loadTemplate('brand'); ?>
        </div>
    </div>
</div>
