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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

HTMLHelper::_('bootstrap.tab', '#j2commerce-product-detail-tab', []);

$productfilters = $this->product->productfilters ?? [];
$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->product->product_short_desc ?? '')));
$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->product->product_long_desc ?? '')));
$hasDescription = $hasShortDesc || $hasLongDesc;
$set_specification_active = !$hasDescription;
?>

<div class="j2commerce-product-tabs">
    <ul class="nav nav-tabs d-flex justify-content-center border-0 rounded-0 bg-transparent" id="j2commerce-product-detail-tab" role="tablist">
        <?php if ($hasDescription) : ?>
            <li class="nav-item">
                <a class="nav-link active rounded-0" href="#description" data-bs-toggle="tab"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION'); ?></a>
            </li>
        <?php endif; ?>

        <?php if ($this->params->get('item_show_product_specification')) : ?>
            <li class="nav-item">
                <a href="#specs" class="nav-link rounded-0 <?php echo $set_specification_active ? 'active' : ''; ?>" data-bs-toggle="tab"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS'); ?></a>
            </li>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabLink', [$this->product, $this->context])->getArgument('html', ''); ?>
    </ul>

    <div class="tab-content border-0 rounded-0 box-shadow-none px-1">
        <?php if ($this->params->get('item_show_ldesc')) : ?>
            <div class="tab-pane fade show active" id="description">
                <?php echo $this->loadTemplate('ldesc'); ?>
            </div>
        <?php endif; ?>

        <?php if ($this->params->get('item_show_product_specification')) : ?>
            <div class="tab-pane fade show <?php echo $set_specification_active ? 'active' : ''; ?>" id="specs">
                <?php echo $this->loadTemplate('specs'); ?>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabContent', [$this->product, $this->context])->getArgument('html', ''); ?>
    </div>
</div>

