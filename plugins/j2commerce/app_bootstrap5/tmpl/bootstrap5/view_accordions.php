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
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$deps = [];

if ($wa->assetExists('script', 'bootstrap.esm')) {
    $deps[] = 'bootstrap.esm';
} elseif ($wa->assetExists('script', 'bootstrap.bundle')) {
    $deps[] = 'bootstrap.bundle';
}

$wa->registerAndUseScript('bootstrap.collapse',Uri::base().'media/com_j2commerce/js/site/vendor/bootstrap/collapse.min.js',[],['type' => 'module'],$deps);
HTMLHelper::_('bootstrap.collapse', '.accordion-button', []);
$hasShortDesc = $this->params->get('item_show_sdesc') && !empty(trim(strip_tags($this->product->product_short_desc ?? '')));
$hasLongDesc  = $this->params->get('item_show_ldesc') && !empty(trim(strip_tags($this->product->product_long_desc ?? '')));
$hasDescription = $hasShortDesc || $hasLongDesc;
$set_specification_active = !$hasDescription;
?>
<div class="accordion mt-5" id="j2CommerceAccordion">
    <?php if($hasDescription):?>
        <div class="accordion-item border-0 mb-2">
            <div class="accordion-header fs-4" id="headingDescription">
                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#description" aria-expanded="true" aria-controls="description">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DESCRIPTION')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse show" id="description" aria-labelledby="headingDescription" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-4 px-1 fs-sm">
                    <?php echo $this->loadTemplate('ldesc'); ?>
                </div>
            </div>
        </div>
    <?php endif;?>
    <?php if($this->params->get('item_show_product_specification')):?>
        <div class="accordion-item border-0 mb-2">
            <div class="accordion-header fs-4" id="headingSpecs">
                <button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#specs" aria-expanded="false" aria-controls="specs">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SPECIFICATIONS')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse <?php echo isset($set_specification_active) && $set_specification_active ? 'show' : '';?>" id="specs" aria-labelledby="headingSpecs" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-4 px-1 fs-sm">
                    <?php echo $this->loadTemplate('specs'); ?>
                </div>
            </div>
        </div>
    <?php endif;?>
    <?php if($this->params->get('item_show_product_filters') && !empty($productfilters)):?>
        <div class="accordion-item border-0">
            <div class="accordion-header fs-4" id="headingFilters">
                <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="false" aria-controls="filters">
                    <span class="underline-effect text-capitalize me-2 fs-6 fw-bold"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILTERS')?></span>
                </button>
            </div>
            <div class="accordion-collapse collapse" id="filters" aria-labelledby="headingFilters" data-bs-parent="#j2CommerceAccordion">
                <div class="accordion-body pt-4 px-1">
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
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingTab', [$this->product, $this->context]); ?>
</div>

