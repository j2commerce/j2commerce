<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
?>
<div class="row">
    <div class="col-sm-12">
        <ul class="nav nav-tabs" id="j2commerce-product-detail-tab" role="tablist">
			<?php
			$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->item->product_short_desc ?? '')));
			$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->item->product_long_desc ?? '')));
			$hasDescription = $hasShortDesc || $hasLongDesc;
			$set_specification_active = !$hasDescription;
            ?>
			<?php if ($hasDescription) : ?>
                <li class="nav-item">
                    <a class="nav-link active" href="#description" data-bs-toggle="tab"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION')?></a>
                </li>
			<?php endif;?>

			<?php if ($this->params->get('item_show_product_specification')) : ?>
                <li class="nav-item" >
                    <a href="#specs" class="nav-link<?php echo isset($set_specification_active) && $set_specification_active ? ' active' : ''; ?>" data-bs-toggle="tab"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS') ?></a>
                </li>
			<?php endif;?>

			<?php if ($this->params->get('item_show_product_filters') && !empty($this->item->productfilters)) : ?>
                <li class="nav-item">
                    <a href="#filters" class="nav-link" data-bs-toggle="tab"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILTERS'); ?></a>
                </li>
			<?php endif;?>

			<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabLink', [$this->item])->getArgument('html'); ?>
        </ul>

        <div class="tab-content">
			<?php if ($hasDescription) : ?>
                <div class="tab-pane fade show active" id="description">
					<?php echo $this->loadTemplate('sdesc'); ?>
					<?php echo $this->loadTemplate('ldesc'); ?>
                </div>
			<?php endif;?>

			<?php if ($this->params->get('item_show_product_specification')) : ?>
                <div class="tab-pane fade show<?php echo isset($set_specification_active) && $set_specification_active ? ' active' : '';?>" id="specs">
					<?php echo $this->loadTemplate('specs'); ?>
                </div>
			<?php endif;?>

			<?php $productfilters = $this->item->productfilters ?? []; ?>
			<?php if ($this->params->get('item_show_product_filters') && !empty($productfilters)) : ?>
                <div class="tab-pane fade" id="filters">
                    <?php foreach ($productfilters as $group) : ?>
                        <div class="mb-3">
                            <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($group['group_name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($group['filters'] as $filter) : ?>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($filter->filter_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
			<?php endif;?>

			<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTabContent', [$this->item])->getArgument('html'); ?>
        </div>
    </div>
</div>
