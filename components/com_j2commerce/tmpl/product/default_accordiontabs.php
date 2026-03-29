<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('bootstrap.collapse', '[data-bs-toggle="collapse"]');

$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->item->product_short_desc ?? '')));
$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->item->product_long_desc ?? '')));
$hasDescription = $hasShortDesc || $hasLongDesc;
$set_specification_active = !$hasDescription;
?>

<div class="accordion mt-5" id="j2CommerceAccordion">
    <?php if($hasDescription):?>
        <div class="accordion-item border-top">
            <div class="accordion-header fs-4" id="headingDescription">
                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#description" aria-expanded="true" aria-controls="description">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse show" id="description" aria-labelledby="headingDescription" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-3">
                    <?php echo $this->loadTemplate('ldesc'); ?>
                </div>
            </div>
        </div>
    <?php endif;?>
    <?php if($this->params->get('item_show_product_specification')):?>
        <div class="accordion-item">
            <div class="accordion-header fs-4" id="headingSpecs">
                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#specs" aria-expanded="false" aria-controls="specs">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse <?php echo isset($set_specification_active) && $set_specification_active ? 'show' : '';?>" id="specs" aria-labelledby="headingSpecs" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-3">
                    <?php echo $this->loadTemplate('specs'); ?>
                </div>
            </div>
        </div>
    <?php endif;?>
    <?php $productfilters = $this->item->productfilters ?? []; ?>
    <?php if($this->params->get('item_show_product_filters') && !empty($productfilters)):?>
        <div class="accordion-item">
            <div class="accordion-header fs-4" id="headingFilters">
                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILTERS')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse" id="filters" aria-labelledby="headingFilters" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-3">
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
            </div>
        </div>
    <?php endif;?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTab', [$this->item])->getArgument('html'); ?>
</div>


