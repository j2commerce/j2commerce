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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Producttags\HtmlView $this */

$app = Factory::getApplication();
$session = $app->getSession();

$currencySymbol = CurrencyHelper::getSymbol();
$currencyPosition = CurrencyHelper::getSymbolPosition();
$currencyValue = CurrencyHelper::getValue();
$thousandSymbol = CurrencyHelper::getThousandsSeparator();
$decimalPlace = CurrencyHelper::getDecimalPlace();
$currencyCode = CurrencyHelper::getCode();

$sessionManufacturerIds = $session->get('manufacturer_ids', [], 'j2commerce');
$sessionVendorIds = $session->get('vendor_ids', [], 'j2commerce');
$sessionProductfilterIds = $session->get('productfilter_ids', [], 'j2commerce');

$filterCatid = $this->filter_catid ?? '';
$itemId = $app->getInput()->getUint('Itemid', 0);

$currentSefPath = Uri::getInstance()->getPath();

$csrfTokenName = Session::getFormToken();
$app->getDocument()->addScriptOptions('csrf.token', $csrfTokenName);

$hasFilterGroups = (!empty($this->filters['manufacturers']) && $this->params->get('list_show_manufacturer_filter', 1))
    || (!empty($this->filters['vendors']) && $this->params->get('list_show_vendor_filter', 1))
    || (!empty($this->filters['productfilters']) && $this->params->get('list_show_product_filter', 1));

$hasPriceFilter = $this->params->get('list_show_filter_price', 1) && isset($this->filters['pricefilters']) && count($this->filters['pricefilters']);
$filtersCollapsed = ((int) $this->params->get('list_filter_category_toggle', 1) === 2);
?>
<div id="j2commerce-product-loading" class="j2commerce-loading-overlay" style="display:none;"></div>

<style>
    .filter-accordion .uk-accordion-title { padding: .75rem 0; font-weight: 600; }
    .filter-accordion .uk-accordion-content { padding: .5rem 0 .75rem; }
    .filter-chip .uk-close { font-size: .5rem; }

    .j2commerce-dual-range { background: #E9ECEF; height: 8px; border-radius: 4px; position: relative; }
    .j2commerce-dual-range input[type="range"] { position: absolute; width: 100%; top: 50%; transform: translateY(-50%); pointer-events: none; -webkit-appearance: none; appearance: none; background: transparent; margin: 0; }
    .j2commerce-dual-range input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; appearance: none;
        width: 1.25rem; height: 1.25rem;
        background-color: #333;
        border: 2px solid #fff; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,.2);
        cursor: pointer; pointer-events: auto;
        transition: transform .15s ease;
    }
    .j2commerce-dual-range input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.1); }
    .j2commerce-dual-range input[type="range"]::-moz-range-thumb {
        width: 1.25rem; height: 1.25rem;
        background-color: #333;
        border: 2px solid #fff; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,.2);
        cursor: pointer; pointer-events: auto; transition: transform .15s ease;
    }
    .j2commerce-dual-range input[type="range"]::-moz-range-thumb:hover { transform: scale(1.1); }
    .j2commerce-dual-range input[type="range"]::-moz-range-track { background: transparent; border: none; }
</style>

<button class="uk-button uk-button-default uk-width-1-1 uk-hidden@m uk-margin-small-bottom" type="button" uk-toggle="target: #j2commerceFilterOffcanvas">
    <span uk-icon="icon: settings"></span> <?php echo Text::_('COM_J2COMMERCE_FILTER_AND_SORT'); ?>
</button>

<div id="j2commerceFilterOffcanvas" uk-offcanvas="mode: slide; flip: false;">
    <div class="uk-offcanvas-bar">
        <button class="uk-offcanvas-close" type="button" uk-close aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
        <h3 class="uk-margin-top"><?php echo Text::_('COM_J2COMMERCE_FILTER_ACTIVE_TITLE'); ?></h3>

        <form action="<?php echo htmlspecialchars($currentSefPath, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="uk-form-stacked uk-width-1-1" id="productsideFilters" name="productsideFilters" enctype="multipart/form-data">
            <input type="hidden" name="filter_catid" id="filter_catid" value="<?php echo $this->escape($filterCatid); ?>" />

            <?php if ($hasFilterGroups) : ?>
            <div id="j2commerce-active-filters" class="uk-margin-small-bottom">
                <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
                    <h3 class="uk-text-bold uk-margin-remove uk-text-small"><?php echo Text::_('COM_J2COMMERCE_FILTER_ACTIVE_TITLE'); ?></h3>
                    <a href="javascript:void(0);" class="uk-text-danger uk-text-small" id="j2commerce-clear-all-filters" style="display:none;">
                        <?php echo Text::_('COM_J2COMMERCE_FILTER_CLEAR_ALL'); ?>
                    </a>
                </div>
                <div id="j2commerce-active-filter-tiles" class="uk-flex uk-flex-wrap" style="gap:.5rem;"></div>
            </div>
            <?php endif; ?>

            <ul class="uk-accordion filter-accordion" uk-accordion>

                <?php if ($hasPriceFilter) : ?>
                    <?php
                    $minPrice = 0;
                    $maxPrice = (float) $this->filters['pricefilters']['max_price'];
                    $hasActivePrice = !empty($this->state->pricefrom) || !empty($this->state->priceto);
                    $priceFrom = $hasActivePrice && $this->state->pricefrom ? (float) $this->state->pricefrom : $minPrice;
                    $priceTo = $hasActivePrice && $this->state->priceto ? (float) $this->state->priceto : $maxPrice;
                    $dPriceFrom = CurrencyHelper::format($priceFrom, $currencyCode, $currencyValue, false);
                    $dPriceTo = CurrencyHelper::format($priceTo, $currencyCode, $currencyValue, false);
                    ?>
                    <li class="uk-open">
                        <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_PRICE_TITLE'); ?></a>
                        <div class="uk-accordion-content">
                            <div id="j2commerce-price-filter-container">
                                <div id="j2commerce-slider-range" class="uk-width-1-1"></div>
                                <div id="j2commerce-slider-range-box" class="uk-flex uk-flex-middle uk-margin-small-top" style="gap:.5rem;">
                                    <button type="submit" class="uk-button uk-button-secondary uk-button-small uk-hidden" id="filterProductsBtn"><?php echo Text::_('COM_J2COMMERCE_FILTER_GO'); ?></button>
                                    <div class="uk-text-center uk-text-small uk-text-muted uk-width-1-1">
                                        <span id="min_price" style="display: none"><?php echo $priceFrom; ?></span>
                                        <span id="max_price" style="display: none"><?php echo $priceTo; ?></span>
                                        <?php if ($currencyPosition === 'pre') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                        <span id="min_price_display" class="uk-text-bold"><?php echo $dPriceFrom; ?></span>
                                        <?php if ($currencyPosition === 'post') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                        <span class="uk-margin-small-left uk-margin-small-right"><?php echo Text::_('COM_J2COMMERCE_TO_PRICE'); ?></span>
                                        <?php if ($currencyPosition === 'pre') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                        <span id="max_price_display" class="uk-text-bold"><?php echo $dPriceTo; ?></span>
                                        <?php if ($currencyPosition === 'post') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                        <input type="hidden" name="pricefrom" id="min_price_input" value="<?php echo $priceFrom; ?>" />
                                        <input type="hidden" name="priceto" id="max_price_input" value="<?php echo $priceTo; ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($this->params->get('list_show_product_filter', 1) && !empty($this->filters['productfilters'])) : ?>
                    <?php foreach ($this->filters['productfilters'] as $pfKey => $filtergroup) : ?>
                        <?php
                        $filterScriptId = J2CommerceHelper::utilities()->generateId($filtergroup['group_name']) . '_' . $pfKey;
                        $pfShowExpanded = !$filtersCollapsed;
                        $groupFilterIds = array_map(fn($f) => $f->filter_id, $filtergroup['filters']);
                        $hasSelectedFilters = !empty($sessionProductfilterIds) && count(array_intersect($sessionProductfilterIds, $groupFilterIds)) > 0;
                        if ($hasSelectedFilters) {
                            $pfShowExpanded = true;
                        }
                        ?>
                        <li<?php echo $pfShowExpanded ? ' class="uk-open"' : ''; ?>>
                            <a class="uk-accordion-title" href="#"><?php echo $this->escape(Text::_($filtergroup['group_name'])); ?></a>
                            <div class="uk-accordion-content">
                                <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                                    <a href="javascript:void(0);" class="j2commerce-clear-pf-filter uk-text-small" data-filter-class="j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" id="product-filter-group-clear-<?php echo $filterScriptId; ?>"<?php echo $hasSelectedFilters ? '' : ' style="display:none;"'; ?>>
                                        <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                    </a>
                                </div>
                                <div id="j2commerce-pf-filter-<?php echo $filterScriptId; ?>" class="j2commerce-productfilter-list">
                                    <?php foreach ($filtergroup['filters'] as $filter) : ?>
                                        <?php
                                        $checked = (!empty($sessionProductfilterIds) && in_array($filter->filter_id, $sessionProductfilterIds));
                                        $filterAlias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe(Text::_($filter->filter_name));
                                        ?>
                                        <div class="uk-margin-small-bottom">
                                            <label class="uk-text-small">
                                                <input type="checkbox" class="uk-checkbox j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" name="productfilter_ids[]" id="j2commerce-pfilter-<?php echo $filterScriptId; ?>-<?php echo $filter->filter_id; ?>" value="<?php echo $filter->filter_id; ?>" data-alias="<?php echo $this->escape($filterAlias); ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                                <?php echo $this->escape(Text::_($filter->filter_name)); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($this->params->get('list_show_manufacturer_filter', 1) && !empty($this->filters['manufacturers']) && count($this->filters['manufacturers'])) : ?>
                    <li<?php echo !$filtersCollapsed ? ' class="uk-open"' : ''; ?>>
                        <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_BY_BRAND'); ?></a>
                        <div class="uk-accordion-content">
                            <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                                <a href="javascript:void(0);" class="j2commerce-clear-filter uk-text-small" data-filter-type="brand" id="j2commerce-clear-brand"<?php echo empty($sessionManufacturerIds) ? ' style="display:none;"' : ''; ?>>
                                    <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                </a>
                            </div>
                            <div id="j2commerce-brand-filter-container">
                                <?php foreach ($this->filters['manufacturers'] as $brand) : ?>
                                    <?php $checked = (!empty($sessionManufacturerIds) && in_array($brand->j2commerce_manufacturer_id, $sessionManufacturerIds)); ?>
                                    <div class="uk-margin-small-bottom">
                                        <label class="uk-text-small">
                                            <input type="checkbox" class="uk-checkbox j2commerce-brand-checkboxes" name="manufacturer_ids[]" id="brand-input-<?php echo $brand->j2commerce_manufacturer_id; ?>" value="<?php echo $brand->j2commerce_manufacturer_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                            <?php echo $this->escape($brand->company); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($this->params->get('list_show_vendor_filter', 1) && !empty($this->filters['vendors'])) : ?>
                    <li<?php echo !$filtersCollapsed ? ' class="uk-open"' : ''; ?>>
                        <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_BY_VENDOR'); ?></a>
                        <div class="uk-accordion-content">
                            <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                                <a href="javascript:void(0);" class="j2commerce-clear-filter uk-text-small" data-filter-type="vendor" id="j2commerce-clear-vendor"<?php echo empty($sessionVendorIds) ? ' style="display:none;"' : ''; ?>>
                                    <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                </a>
                            </div>
                            <div id="j2commerce-vendor-filter-container">
                                <?php foreach ($this->filters['vendors'] as $vendor) : ?>
                                    <?php $checked = (!empty($sessionVendorIds) && in_array($vendor->j2commerce_vendor_id, $sessionVendorIds)); ?>
                                    <div class="uk-margin-small-bottom">
                                        <label class="uk-text-small">
                                            <input type="checkbox" class="uk-checkbox j2commerce-vendor-checkboxes" name="vendor_ids[]" id="vendor-input-<?php echo $vendor->j2commerce_vendor_id; ?>" value="<?php echo $vendor->j2commerce_vendor_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                            <?php echo $this->escape($vendor->company); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>

            <input type="hidden" name="option" value="com_j2commerce" />
            <input type="hidden" name="view" value="producttags" />
            <input type="hidden" name="task" value="browse" />
            <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <div class="uk-margin-top uk-flex" style="gap:.5rem;">
            <button type="button" class="uk-button uk-button-default uk-flex-1" id="j2commerce-mobile-clear-all">
                <?php echo Text::_('COM_J2COMMERCE_FILTER_CLEAR_ALL'); ?>
            </button>
            <button type="button" class="uk-button uk-button-secondary uk-flex-1" uk-toggle="target: #j2commerceFilterOffcanvas">
                <?php echo Text::_('COM_J2COMMERCE_FILTER_APPLY'); ?>
            </button>
        </div>
    </div>
</div>

<div class="uk-visible@m">
    <form action="<?php echo htmlspecialchars($currentSefPath, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="uk-form-stacked uk-width-1-1" id="productsideFilters" name="productsideFilters" enctype="multipart/form-data">
        <input type="hidden" name="filter_catid" id="filter_catid" value="<?php echo $this->escape($filterCatid); ?>" />

        <?php if ($hasFilterGroups) : ?>
        <div id="j2commerce-active-filters" class="uk-margin-small-bottom">
            <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
                <h3 class="uk-text-bold uk-margin-remove uk-text-small"><?php echo Text::_('COM_J2COMMERCE_FILTER_ACTIVE_TITLE'); ?></h3>
                <a href="javascript:void(0);" class="uk-text-danger uk-text-small" id="j2commerce-clear-all-filters" style="display:none;">
                    <?php echo Text::_('COM_J2COMMERCE_FILTER_CLEAR_ALL'); ?>
                </a>
            </div>
            <div id="j2commerce-active-filter-tiles" class="uk-flex uk-flex-wrap" style="gap:.5rem;"></div>
        </div>
        <?php endif; ?>

        <ul class="uk-accordion filter-accordion" uk-accordion>

            <?php if ($hasPriceFilter) : ?>
                <li class="uk-open">
                    <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_PRICE_TITLE'); ?></a>
                    <div class="uk-accordion-content">
                        <div id="j2commerce-price-filter-container">
                            <div id="j2commerce-slider-range" class="uk-width-1-1"></div>
                            <div id="j2commerce-slider-range-box" class="uk-flex uk-flex-middle uk-margin-small-top" style="gap:.5rem;">
                                <button type="submit" class="uk-button uk-button-secondary uk-button-small uk-hidden" id="filterProductsBtn"><?php echo Text::_('COM_J2COMMERCE_FILTER_GO'); ?></button>
                                <div class="uk-text-center uk-text-small uk-text-muted uk-width-1-1">
                                    <span id="min_price" style="display: none"><?php echo $priceFrom; ?></span>
                                    <span id="max_price" style="display: none"><?php echo $priceTo; ?></span>
                                    <?php if ($currencyPosition === 'pre') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                    <span id="min_price_display" class="uk-text-bold"><?php echo $dPriceFrom; ?></span>
                                    <?php if ($currencyPosition === 'post') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                    <span class="uk-margin-small-left uk-margin-small-right"><?php echo Text::_('COM_J2COMMERCE_TO_PRICE'); ?></span>
                                    <?php if ($currencyPosition === 'pre') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                    <span id="max_price_display" class="uk-text-bold"><?php echo $dPriceTo; ?></span>
                                    <?php if ($currencyPosition === 'post') echo '<span class="uk-text-bold">' . $currencySymbol . '</span>'; ?>
                                    <input type="hidden" name="pricefrom" id="min_price_input" value="<?php echo $priceFrom; ?>" />
                                    <input type="hidden" name="priceto" id="max_price_input" value="<?php echo $priceTo; ?>" />
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($this->params->get('list_show_product_filter', 1) && !empty($this->filters['productfilters'])) : ?>
                <?php foreach ($this->filters['productfilters'] as $pfKey => $filtergroup) : ?>
                    <?php
                    $filterScriptId = J2CommerceHelper::utilities()->generateId($filtergroup['group_name']) . '_' . $pfKey;
                    $pfShowExpanded = !$filtersCollapsed;
                    $groupFilterIds = array_map(fn($f) => $f->filter_id, $filtergroup['filters']);
                    $hasSelectedFilters = !empty($sessionProductfilterIds) && count(array_intersect($sessionProductfilterIds, $groupFilterIds)) > 0;
                    if ($hasSelectedFilters) {
                        $pfShowExpanded = true;
                    }
                    ?>
                    <li<?php echo $pfShowExpanded ? ' class="uk-open"' : ''; ?>>
                        <a class="uk-accordion-title" href="#"><?php echo $this->escape(Text::_($filtergroup['group_name'])); ?></a>
                        <div class="uk-accordion-content">
                            <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                                <a href="javascript:void(0);" class="j2commerce-clear-pf-filter uk-text-small" data-filter-class="j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" id="product-filter-group-clear-<?php echo $filterScriptId; ?>"<?php echo $hasSelectedFilters ? '' : ' style="display:none;"'; ?>>
                                    <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                </a>
                            </div>
                            <div id="j2commerce-pf-filter-<?php echo $filterScriptId; ?>" class="j2commerce-productfilter-list">
                                <?php foreach ($filtergroup['filters'] as $filter) : ?>
                                    <?php
                                    $checked = (!empty($sessionProductfilterIds) && in_array($filter->filter_id, $sessionProductfilterIds));
                                    $filterAlias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe(Text::_($filter->filter_name));
                                    ?>
                                    <div class="uk-margin-small-bottom">
                                        <label class="uk-text-small">
                                            <input type="checkbox" class="uk-checkbox j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" name="productfilter_ids[]" id="j2commerce-pfilter-<?php echo $filterScriptId; ?>-<?php echo $filter->filter_id; ?>" value="<?php echo $filter->filter_id; ?>" data-alias="<?php echo $this->escape($filterAlias); ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                            <?php echo $this->escape(Text::_($filter->filter_name)); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($this->params->get('list_show_manufacturer_filter', 1) && !empty($this->filters['manufacturers']) && count($this->filters['manufacturers'])) : ?>
                <li<?php echo !$filtersCollapsed ? ' class="uk-open"' : ''; ?>>
                    <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_BY_BRAND'); ?></a>
                    <div class="uk-accordion-content">
                        <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                            <a href="javascript:void(0);" class="j2commerce-clear-filter uk-text-small" data-filter-type="brand" id="j2commerce-clear-brand"<?php echo empty($sessionManufacturerIds) ? ' style="display:none;"' : ''; ?>>
                                <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                            </a>
                        </div>
                        <div id="j2commerce-brand-filter-container">
                            <?php foreach ($this->filters['manufacturers'] as $brand) : ?>
                                <?php $checked = (!empty($sessionManufacturerIds) && in_array($brand->j2commerce_manufacturer_id, $sessionManufacturerIds)); ?>
                                <div class="uk-margin-small-bottom">
                                    <label class="uk-text-small">
                                        <input type="checkbox" class="uk-checkbox j2commerce-brand-checkboxes" name="manufacturer_ids[]" id="brand-input-<?php echo $brand->j2commerce_manufacturer_id; ?>" value="<?php echo $brand->j2commerce_manufacturer_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                        <?php echo $this->escape($brand->company); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>

            <?php if ($this->params->get('list_show_vendor_filter', 1) && !empty($this->filters['vendors'])) : ?>
                <li<?php echo !$filtersCollapsed ? ' class="uk-open"' : ''; ?>>
                    <a class="uk-accordion-title" href="#"><?php echo Text::_('COM_J2COMMERCE_FILTER_BY_VENDOR'); ?></a>
                    <div class="uk-accordion-content">
                        <div class="uk-text-right uk-margin-small-bottom uk-hidden">
                            <a href="javascript:void(0);" class="j2commerce-clear-filter uk-text-small" data-filter-type="vendor" id="j2commerce-clear-vendor"<?php echo empty($sessionVendorIds) ? ' style="display:none;"' : ''; ?>>
                                <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                            </a>
                        </div>
                        <div id="j2commerce-vendor-filter-container">
                            <?php foreach ($this->filters['vendors'] as $vendor) : ?>
                                <?php $checked = (!empty($sessionVendorIds) && in_array($vendor->j2commerce_vendor_id, $sessionVendorIds)); ?>
                                <div class="uk-margin-small-bottom">
                                    <label class="uk-text-small">
                                        <input type="checkbox" class="uk-checkbox j2commerce-vendor-checkboxes" name="vendor_ids[]" id="vendor-input-<?php echo $vendor->j2commerce_vendor_id; ?>" value="<?php echo $vendor->j2commerce_vendor_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                        <?php echo $this->escape($vendor->company); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </li>
            <?php endif; ?>
        </ul>

        <input type="hidden" name="option" value="com_j2commerce" />
        <input type="hidden" name="view" value="producttags" />
        <input type="hidden" name="task" value="browse" />
        <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ajaxEnabled = document.querySelector('.j2commerce-product-list')?.dataset.ajaxFilters === 'true';
    if (ajaxEnabled && typeof J2CommerceFilters !== 'undefined') {
        initClearButtonVisibility();
        bindMobileFooter();
        return;
    }

    const form = document.getElementById('productsideFilters');
    const loadingOverlay = document.getElementById('j2commerce-product-loading');

    const submitWithLoading = () => {
        if (loadingOverlay) loadingOverlay.style.display = 'block';
        form.submit();
    };

    document.querySelectorAll('.j2commerce-clear-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            const filterType = btn.dataset.filterType;
            let checkboxClass = '';
            if (filterType === 'brand') checkboxClass = '.j2commerce-brand-checkboxes';
            else if (filterType === 'vendor') checkboxClass = '.j2commerce-vendor-checkboxes';
            if (checkboxClass) {
                document.querySelectorAll(checkboxClass).forEach(cb => cb.checked = false);
                submitWithLoading();
            }
        });
    });

    document.querySelectorAll('.j2commerce-clear-pf-filter').forEach(btn => {
        btn.addEventListener('click', () => {
            const filterClass = btn.dataset.filterClass;
            if (filterClass) {
                document.querySelectorAll('.' + filterClass).forEach(cb => cb.checked = false);
                submitWithLoading();
            }
        });
    });

    initClearButtonVisibility();

    document.getElementById('filterProductsBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        submitWithLoading();
    });

    buildFallbackTiles();

    document.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.filter-chip .uk-close');
        if (!removeBtn) return;
        const chip = removeBtn.closest('.filter-chip');
        if (!chip) return;
        e.preventDefault();

        const type = chip.dataset.type;
        const id = chip.dataset.id;
        const findByValue = (sel) => Array.from(form?.querySelectorAll(sel) || []).find(cb => cb.value === id);

        if (type === 'brand') {
            const cb = findByValue('.j2commerce-brand-checkboxes');
            if (cb) cb.checked = false;
        } else if (type === 'vendor') {
            const cb = findByValue('.j2commerce-vendor-checkboxes');
            if (cb) cb.checked = false;
        } else if (type === 'productfilter') {
            const cb = findByValue('[class*="j2commerce-pfilter-checkboxes"]');
            if (cb) cb.checked = false;
        }
        submitWithLoading();
    });

    document.getElementById('j2commerce-clear-all-filters')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('.j2commerce-brand-checkboxes, .j2commerce-vendor-checkboxes, [class*="j2commerce-pfilter-checkboxes"]').forEach(cb => cb.checked = false);
        submitWithLoading();
    });

    bindMobileFooter();

    function buildFallbackTiles() {
        const container = document.getElementById('j2commerce-active-filter-tiles');
        const clearAllBtn = document.getElementById('j2commerce-clear-all-filters');
        if (!container) return;

        const tiles = [];
        document.querySelectorAll('.j2commerce-brand-checkboxes:checked, .j2commerce-vendor-checkboxes:checked, [class*="j2commerce-pfilter-checkboxes"]:checked').forEach(cb => {
            const label = cb.closest('label')?.textContent?.trim();
            if (!label) return;

            const type = cb.classList.contains('j2commerce-brand-checkboxes') ? 'brand'
                : cb.classList.contains('j2commerce-vendor-checkboxes') ? 'vendor' : 'productfilter';
            const escaped = document.createElement('span');
            escaped.textContent = label;
            tiles.push('<span class="filter-chip uk-label uk-label-default uk-flex uk-flex-middle" style="gap:.25rem;" data-type="' + type + '" data-id="' + cb.value + '">' + escaped.innerHTML + '<a uk-close class="uk-close" style="font-size:.5rem" aria-label="Remove"></a></span>');
        });

        container.innerHTML = tiles.length > 0 ? tiles.join('') : '';
        if (clearAllBtn) {
            clearAllBtn.style.display = tiles.length > 0 ? '' : 'none';
        }
    }

    function initClearButtonVisibility() {
        document.querySelectorAll('.j2commerce-productfilter-list').forEach(container => {
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            if (checkedCount > 0) {
                const filterId = container.id.replace('j2commerce-pf-filter-', '');
                const clearBtn = document.getElementById('product-filter-group-clear-' + filterId);
                if (clearBtn) clearBtn.style.display = 'inline';
            }
        });
    }

    function bindMobileFooter() {
        document.getElementById('j2commerce-mobile-clear-all')?.addEventListener('click', () => {
            document.querySelectorAll('.j2commerce-brand-checkboxes, .j2commerce-vendor-checkboxes, [class*="j2commerce-pfilter-checkboxes"]').forEach(cb => cb.checked = false);
            const searchInput = document.getElementById('j2commerce-search');
            if (searchInput) searchInput.value = '';
            UIkit.offcanvas('#j2commerceFilterOffcanvas').hide();
            const form = document.getElementById('productsideFilters');
            if (form) {
                const loadingOverlay = document.getElementById('j2commerce-product-loading');
                if (loadingOverlay) loadingOverlay.style.display = 'block';
                form.submit();
            }
        });
    }
});
</script>

<?php if ($hasPriceFilter) : ?>
<script>
(function() {
    const minPriceEl = document.getElementById('min_price');
    const maxPriceEl = document.getElementById('max_price');
    const minInputEl = document.getElementById('min_price_input');
    const maxInputEl = document.getElementById('max_price_input');
    const minDisplayEl = document.getElementById('min_price_display');
    const maxDisplayEl = document.getElementById('max_price_display');
    const sliderContainer = document.getElementById('j2commerce-slider-range');

    if (!sliderContainer) return;

    const formatValue = <?php echo (float) $currencyValue; ?>;
    const decimalPlace = <?php echo (int) $decimalPlace; ?>;
    const thousandSymbol = '<?php echo $this->escape($thousandSymbol); ?>';
    const minPrice = <?php echo (float) $minPrice; ?>;
    const maxPrice = <?php echo (float) $maxPrice; ?>;
    const currentMin = parseFloat(minPriceEl?.textContent || minPrice);
    const currentMax = parseFloat(maxPriceEl?.textContent || maxPrice);

    function formatCurrency(amount) {
        if (amount < 0) amount = Math.abs(amount);
        amount = parseFloat(amount || 0).toFixed(decimalPlace);
        return amount.replace(/(\d)(?=(\d{3})+\.)/g, '$1' + thousandSymbol);
    }

    function updateDisplays(min, max) {
        if (minPriceEl) minPriceEl.textContent = min;
        if (maxPriceEl) maxPriceEl.textContent = max;
        if (minInputEl) minInputEl.value = min;
        if (maxInputEl) maxInputEl.value = max;
        if (minDisplayEl) minDisplayEl.textContent = formatCurrency(min * formatValue);
        if (maxDisplayEl) maxDisplayEl.textContent = formatCurrency(max * formatValue);
    }

    const sliderMin = Math.floor(minPrice);
    const sliderMax = Math.ceil(maxPrice);
    const sliderCurrentMin = Math.max(sliderMin, Math.floor(currentMin));
    const sliderCurrentMax = Math.min(sliderMax, Math.ceil(currentMax));

    sliderContainer.innerHTML = `
        <div class="j2commerce-dual-range">
            <input type="range" id="j2commerce-range-min" min="${sliderMin}" max="${sliderMax}" value="${sliderCurrentMin}" step="1">
            <input type="range" id="j2commerce-range-max" min="${sliderMin}" max="${sliderMax}" value="${sliderCurrentMax}" step="1">
        </div>
    `;

    const rangeMin = document.getElementById('j2commerce-range-min');
    const rangeMax = document.getElementById('j2commerce-range-max');

    rangeMin?.addEventListener('input', () => {
        const min = Math.min(parseFloat(rangeMin.value), parseFloat(rangeMax.value) - 0.01);
        rangeMin.value = min;
        updateDisplays(min, parseFloat(rangeMax.value));
    });

    rangeMax?.addEventListener('input', () => {
        const max = Math.max(parseFloat(rangeMax.value), parseFloat(rangeMin.value) + 0.01);
        rangeMax.value = max;
        updateDisplays(parseFloat(rangeMin.value), max);
    });

    updateDisplays(currentMin, currentMax);
})();
</script>
<?php endif; ?>
