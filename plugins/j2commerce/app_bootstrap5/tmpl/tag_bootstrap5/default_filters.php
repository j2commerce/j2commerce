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

$hasPriceFilter = $this->params->get('list_show_filter_price', 1) && !empty($this->filters['pricefilters']['max_price']);
$filtersCollapsed = ((int) $this->params->get('list_filter_category_toggle', 1) === 2);

// Choices.js only ships where a group actually asked for the multi-select control.
$hasFancySelect = false;

foreach (($this->filters['productfilters'] ?? []) as $pfGroup) {
    if (($pfGroup['filter_input_type'] ?? '') === 'multiselect') {
        $hasFancySelect = true;
        break;
    }
}

if ($hasFancySelect) {
    Text::script('JGLOBAL_SELECT_NO_RESULTS_MATCH');
    Text::script('JGLOBAL_SELECT_PRESS_TO_SELECT');

    $app->getDocument()->getWebAssetManager()
        ->usePreset('choicesjs')
        ->useScript('webcomponent.field-fancy-select');
}

HTMLHelper::_('bootstrap.offcanvas');
HTMLHelper::_('bootstrap.collapse');
?>
<div id="j2commerce-product-loading" class="j2commerce-loading-overlay" style="display:none;"></div>

<style>
    .filter-accordion label {cursor:pointer;}
    .filter-accordion .accordion-button {padding: .75rem 0; }
    .filter-accordion .accordion-button:not(.collapsed) { background: transparent; box-shadow: none; }
    .filter-accordion .accordion-button::after { content: '\2212'; background-image: none; font-size: .875rem; line-height: 1; width: 1.25rem; height: 1.25rem; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--bs-border-color, #dee2e6); border-radius: 50%; transition: none; transform: none; }
    .filter-accordion .accordion-button.collapsed::after { content: '+'; }
    .filter-accordion .accordion-button:focus-visible { outline: 2px solid var(--bs-primary, #0d6efd); outline-offset: 2px; box-shadow: none; }
    .filter-accordion .accordion-button:focus:not(:focus-visible) { box-shadow: none; }
    .filter-accordion .accordion-body { padding: .5rem 0 .75rem; }
    .filter-chip .btn-close { font-size: .5rem; }

    .j2commerce-dual-range { background: #E9ECEF; height: 8px; border-radius: 4px; position: relative; }
    .j2commerce-dual-range input[type="range"] { position: absolute; width: 100%; top: 50%; transform: translateY(-50%); pointer-events: none; -webkit-appearance: none; appearance: none; background: transparent; margin: 0; }
    .j2commerce-dual-range input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; appearance: none;
        width: 1.25rem; height: 1.25rem;
        background-color: var(--bs-dark, #212529);
        border: 2px solid #fff; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,.2);
        cursor: pointer; pointer-events: auto;
        transition: transform .15s ease;
    }
    .j2commerce-dual-range input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.1); }
    .j2commerce-dual-range input[type="range"]::-moz-range-thumb {
        width: 1.25rem; height: 1.25rem;
        background-color: var(--bs-dark, #212529);
        border: 2px solid #fff; border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,.2);
        cursor: pointer; pointer-events: auto; transition: transform .15s ease;
    }
    .j2commerce-dual-range input[type="range"]::-moz-range-thumb:hover { transform: scale(1.1); }
    .j2commerce-dual-range input[type="range"]::-moz-range-track { background: transparent; border: none; }
    .filter-chip .btn-close {
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='hsl(354%2C70%25%2C54%25)'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414'/%3e%3c/svg%3e") center / 1em auto no-repeat;
    }

    .j2commerce-swatch-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    /* Same swatch as .j2commerce-color-options on the product options, at a sidebar size. */
    .j2commerce-filter-swatches .btn-color { border: 2px solid transparent; border-radius: 50%; cursor: pointer; display: inline-block; flex-shrink: 0; height: 1.5rem; padding: .125rem; width: 1.5rem; }
    .j2commerce-filter-swatches .btn-color::before { background-color: currentcolor; border-radius: 50%; content: ""; display: flex; height: 100%; width: 100%; }
    .j2commerce-swatch-text { display: inline-block; padding: .15rem .6rem; border-radius: 2rem; border: 1px solid var(--bs-border-color, #dee2e6); cursor: pointer; font-size: .875rem; }
    .j2commerce-swatch-input:checked + .btn-color { border-color: var(--bs-dark, #212529); }
    .j2commerce-swatch-input:checked + .j2commerce-swatch-text { outline: 2px solid var(--bs-dark, #212529); outline-offset: 2px; }
    .j2commerce-swatch-input:focus-visible + .btn-color,
    .j2commerce-swatch-input:focus-visible + .j2commerce-swatch-text { outline: 2px solid var(--bs-primary, #0d6efd); outline-offset: 2px; }
    .j2commerce-filter-unavailable .btn-color,
    .j2commerce-filter-unavailable .j2commerce-swatch-text { opacity: .35; cursor: not-allowed; }
</style>

<button class="btn btn-outline-dark w-100 d-md-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#j2commerceFilterOffcanvas">
    <span class="icon-equalizer me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_FILTER_AND_SORT'); ?>
</button>

<div class="offcanvas-md offcanvas-start" tabindex="-1" id="j2commerceFilterOffcanvas">
    <div class="offcanvas-header border-bottom d-md-none">
        <h5 class="offcanvas-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_FILTER_ACTIVE_TITLE'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
    </div>
    <div class="offcanvas-body p-md-0">
        <form action="<?php echo htmlspecialchars($currentSefPath, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="form-horizontal w-100" id="productsideFilters" name="productsideFilters">
            <input type="hidden" name="filter_catid" id="filter_catid" value="<?php echo $this->escape($filterCatid); ?>" />

            <?php if ($hasFilterGroups) : ?>
            <div id="j2commerce-active-filters" class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="fw-bold mb-0 fs-5"><?php echo Text::_('COM_J2COMMERCE_FILTER_ACTIVE_TITLE'); ?></h3>
                    <a href="javascript:void(0);" class="text-danger small text-decoration-none" id="j2commerce-clear-all-filters" style="display:none;">
                        <?php echo Text::_('COM_J2COMMERCE_FILTER_CLEAR_ALL'); ?>
                    </a>
                </div>
                <div id="j2commerce-active-filter-tiles" class="d-flex flex-wrap gap-2"></div>
            </div>
            <?php endif; ?>

            <div class="accordion filter-accordion" id="j2commerceFiltersAccordion">

                <?php if ($hasPriceFilter) : ?>
                    <?php
                    $minPrice = 0;
                    $maxPrice = (float) $this->filters['pricefilters']['max_price'];
                    $hasActivePrice = $this->state->get('filter.price_from', 0) > 0 || $this->state->get('filter.price_to', 0) > 0;
                    $priceFrom = $hasActivePrice && $this->state->get('filter.price_from', 0) ? (float) $this->state->get('filter.price_from') : $minPrice;
                    $priceTo   = $hasActivePrice && $this->state->get('filter.price_to', 0)   ? (float) $this->state->get('filter.price_to')   : $maxPrice;
                    $dPriceFrom = CurrencyHelper::format($priceFrom, $currencyCode, $currencyValue, false);
                    $dPriceTo = CurrencyHelper::format($priceTo, $currencyCode, $currencyValue, false);
                    ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header">
                            <button class="accordion-button fw-semibold<?php echo $filtersCollapsed ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#filterPrice">
                                <?php echo Text::_('COM_J2COMMERCE_FILTER_PRICE_TITLE'); ?>
                            </button>
                        </h3>
                        <div id="filterPrice" class="accordion-collapse collapse<?php echo !$filtersCollapsed ? ' show' : ''; ?>">
                            <div class="accordion-body">
                                <div id="j2commerce-price-filter-container">
                                    <div id="j2commerce-slider-range" class="w-100"></div>
                                    <div id="j2commerce-slider-range-box" class="d-flex align-items-center gap-2 mt-3">
                                        <button type="submit" class="btn btn-dark btn-sm d-none" id="filterProductsBtn"><?php echo Text::_('COM_J2COMMERCE_FILTER_GO'); ?></button>
                                        <div class="text-center small text-body-secondary w-100">
                                            <span id="min_price" style="display: none"><?php echo $priceFrom; ?></span>
                                            <span id="max_price" style="display: none"><?php echo $priceTo; ?></span>
                                            <?php if ($currencyPosition === 'pre') echo '<span class="fw-semibold">' . $currencySymbol . '</span>'; ?>
                                            <span id="min_price_display" class="fw-semibold"><?php echo $dPriceFrom; ?></span>
                                            <?php if ($currencyPosition === 'post') echo '<span class="fw-semibold">' . $currencySymbol . '</span>'; ?>
                                            <span class="mx-1"><?php echo Text::_('COM_J2COMMERCE_TO_PRICE'); ?></span>
                                            <?php if ($currencyPosition === 'pre') echo '<span class="fw-semibold">' . $currencySymbol . '</span>'; ?>
                                            <span id="max_price_display" class="fw-semibold"><?php echo $dPriceTo; ?></span>
                                            <?php if ($currencyPosition === 'post') echo '<span class="fw-semibold">' . $currencySymbol . '</span>'; ?>
                                            <input type="hidden" name="pricefrom" id="min_price_input" value="<?php echo $priceFrom; ?>" />
                                            <input type="hidden" name="priceto" id="max_price_input" value="<?php echo $priceTo; ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                        $pfInputType = $filtergroup['filter_input_type'] ?? 'checkbox';
                        $pfIsList    = $pfInputType === 'select' || $pfInputType === 'multiselect';
                        // A radio set needs a name of its own, or every group on the page would
                        // form one exclusive set and picking a colour would clear the size.
                        $pfInputName = $pfInputType === 'radio'
                            ? 'productfilter_group[' . (int) $pfKey . ']'
                            : 'productfilter_ids[]';
                        ?>
                        <div class="accordion-item border-0 border-bottom">
                            <h3 class="accordion-header">
                                <button class="accordion-button fw-semibold<?php echo !$pfShowExpanded ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#filterPf-<?php echo $filterScriptId; ?>">
                                    <?php echo $this->escape(Text::_($filtergroup['group_name'])); ?>
                                </button>
                            </h3>
                            <div id="filterPf-<?php echo $filterScriptId; ?>" class="accordion-collapse collapse<?php echo $pfShowExpanded ? ' show' : ''; ?>">
                                <div class="accordion-body">
                                    <div class="text-end mb-2 d-none">
                                        <a href="javascript:void(0);" class="j2commerce-clear-pf-filter small text-decoration-none" data-filter-class="j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" id="product-filter-group-clear-<?php echo $filterScriptId; ?>"<?php echo $hasSelectedFilters ? '' : ' style="display:none;"'; ?>>
                                            <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                        </a>
                                    </div>
                                    <div id="j2commerce-pf-filter-<?php echo $filterScriptId; ?>" class="j2commerce-productfilter-list<?php echo $pfInputType === 'color' ? ' j2commerce-color-options j2commerce-filter-swatches d-flex flex-wrap gap-2' : ''; ?>">
                                        <?php if ($pfIsList) : ?>
                                            <?php if ($pfInputType === 'multiselect') : ?>
                                                <joomla-field-fancy-select placeholder="<?php echo $this->escape(Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS')); ?>">
                                            <?php endif; ?>
                                            <select class="form-select j2commerce-pfilter-select j2commerce-pfilter-select-<?php echo $filterScriptId; ?>" name="productfilter_ids[]" aria-label="<?php echo $this->escape(Text::_($filtergroup['group_name'])); ?>"<?php echo $pfInputType === 'multiselect' ? ' multiple' : ''; ?>>
                                                <?php if ($pfInputType === 'select') : ?>
                                                    <option class="j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" value=""><?php echo $this->escape(Text::_('COM_J2COMMERCE_FILTER_ANY')); ?></option>
                                                <?php endif; ?>
                                                <?php foreach ($filtergroup['filters'] as $filter) : ?>
                                                    <?php
                                                    $checked = (!empty($sessionProductfilterIds) && in_array($filter->filter_id, $sessionProductfilterIds));
                                                    $filterAlias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe(Text::_($filter->filter_name));
                                                    $filterCount = (int) ($filter->product_count ?? 0);
                                                    $filterLabel = Text::_($filter->filter_name);
                                                    ?>
                                                    <option class="j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" value="<?php echo $filter->filter_id; ?>" data-alias="<?php echo $this->escape($filterAlias); ?>" data-count="<?php echo $filterCount; ?>" data-label="<?php echo $this->escape($filterLabel); ?>"<?php echo $checked ? ' selected' : ''; ?><?php echo $filterCount === 0 && !$checked ? ' disabled' : ''; ?>><?php echo $this->escape($filterLabel); ?> (<?php echo $filterCount; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                                <?php if ($pfInputType === 'multiselect') : ?>
                                                    </joomla-field-fancy-select>
                                                <?php endif; ?>
                                        <?php else : ?>
                                            <?php if ($pfInputType === 'radio') : ?>
                                                <div class="form-check mb-2">
                                                    <input type="radio" class="form-check-input j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" name="<?php echo $pfInputName; ?>" id="j2commerce-pfilter-<?php echo $filterScriptId; ?>-any" value=""<?php echo $hasSelectedFilters ? '' : ' checked'; ?> />
                                                    <label class="form-check-label small" for="j2commerce-pfilter-<?php echo $filterScriptId; ?>-any">
                                                        <?php echo $this->escape(Text::_('COM_J2COMMERCE_FILTER_ANY')); ?>
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                            <?php foreach ($filtergroup['filters'] as $filter) : ?>
                                                <?php
                                                $checked = (!empty($sessionProductfilterIds) && in_array($filter->filter_id, $sessionProductfilterIds));
                                                $filterAlias = \Joomla\CMS\Filter\OutputFilter::stringURLSafe(Text::_($filter->filter_name));
                                                $filterCount = (int) ($filter->product_count ?? 0);
                                                $filterLabel = Text::_($filter->filter_name);
                                                // A ticked value stays operable at zero, or the selection that emptied
                                                // the listing could not be undone from here.
                                                $filterUnavailable = $filterCount === 0 && !$checked;
                                                $filterId = 'j2commerce-pfilter-' . $filterScriptId . '-' . $filter->filter_id;
                                                // Re-checked at render: only a hex literal ever reaches the style attribute.
                                                $swatchColor = preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', (string) ($filter->filter_color ?? ''))
                                                    ? $filter->filter_color
                                                    : '';
                                                ?>
                                                <?php if ($pfInputType === 'color') : ?>
                                                    <div<?php echo $filterUnavailable ? ' class="j2commerce-filter-unavailable"' : ''; ?>>
                                                        <input type="checkbox" class="j2commerce-swatch-input j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" name="<?php echo $pfInputName; ?>" id="<?php echo $filterId; ?>" value="<?php echo $filter->filter_id; ?>" data-alias="<?php echo $this->escape($filterAlias); ?>" data-count="<?php echo $filterCount; ?>" data-label="<?php echo $this->escape($filterLabel); ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $filterUnavailable ? ' disabled' : ''; ?> />
                                                        <?php if ($swatchColor !== '') : ?>
                                                            <label class="btn btn-color" for="<?php echo $filterId; ?>" style="color: <?php echo $this->escape($swatchColor); ?>" title="<?php echo $this->escape($filterLabel); ?>" data-label="<?php echo $this->escape($filterLabel); ?>">
                                                                <span class="visually-hidden"><?php echo $this->escape($filterLabel); ?></span>
                                                            </label>
                                                        <?php else : ?>
                                                            <label class="form-check-label j2commerce-swatch-text" for="<?php echo $filterId; ?>" title="<?php echo $this->escape($filterLabel); ?>">
                                                                <?php echo $this->escape($filterLabel); ?>
                                                            </label>
                                                        <?php endif; ?>
                                                        <span class="j2commerce-filter-count visually-hidden">(<?php echo $filterCount; ?>)</span>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="form-check mb-2<?php echo $filterUnavailable ? ' j2commerce-filter-unavailable' : ''; ?>">
                                                        <input type="<?php echo $pfInputType === 'radio' ? 'radio' : 'checkbox'; ?>" class="form-check-input j2commerce-pfilter-checkboxes-<?php echo $filterScriptId; ?>" name="<?php echo $pfInputName; ?>" id="<?php echo $filterId; ?>" value="<?php echo $filter->filter_id; ?>" data-alias="<?php echo $this->escape($filterAlias); ?>" data-count="<?php echo $filterCount; ?>" data-label="<?php echo $this->escape($filterLabel); ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $filterUnavailable ? ' disabled' : ''; ?> />
                                                        <label class="form-check-label small" for="<?php echo $filterId; ?>">
                                                            <?php echo $this->escape($filterLabel); ?>
                                                        </label>
                                                        <?php // Outside the label on purpose: the active-filter chips read the label textContent. ?>
                                                        <span class="j2commerce-filter-count text-body-secondary">(<?php echo $filterCount; ?>)</span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($this->params->get('list_show_manufacturer_filter', 1) && !empty($this->filters['manufacturers']) && count($this->filters['manufacturers'])) : ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header">
                            <button class="accordion-button fw-semibold<?php echo $filtersCollapsed ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#filterBrand">
                                <?php echo Text::_('COM_J2COMMERCE_FILTER_BY_BRAND'); ?>
                            </button>
                        </h3>
                        <div id="filterBrand" class="accordion-collapse collapse<?php echo !$filtersCollapsed ? ' show' : ''; ?>">
                            <div class="accordion-body">
                                <div class="text-end mb-2 d-none">
                                    <a href="javascript:void(0);" class="j2commerce-clear-filter small text-decoration-none" data-filter-type="brand" id="j2commerce-clear-brand"<?php echo empty($sessionManufacturerIds) ? ' style="display:none;"' : ''; ?>>
                                        <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                    </a>
                                </div>
                                <div id="j2commerce-brand-filter-container">
                                    <?php foreach ($this->filters['manufacturers'] as $brand) : ?>
                                        <?php $checked = (!empty($sessionManufacturerIds) && in_array($brand->j2commerce_manufacturer_id, $sessionManufacturerIds)); ?>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input j2commerce-brand-checkboxes" name="manufacturer_ids[]" id="brand-input-<?php echo $brand->j2commerce_manufacturer_id; ?>" value="<?php echo $brand->j2commerce_manufacturer_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                            <label class="form-check-label small" for="brand-input-<?php echo $brand->j2commerce_manufacturer_id; ?>">
                                                <?php echo $this->escape($brand->company); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($this->params->get('list_show_vendor_filter', 1) && !empty($this->filters['vendors'])) : ?>
                    <div class="accordion-item border-0 border-bottom">
                        <h3 class="accordion-header">
                            <button class="accordion-button fw-semibold<?php echo $filtersCollapsed ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#filterVendor">
                                <?php echo Text::_('COM_J2COMMERCE_FILTER_BY_VENDOR'); ?>
                            </button>
                        </h3>
                        <div id="filterVendor" class="accordion-collapse collapse<?php echo !$filtersCollapsed ? ' show' : ''; ?>">
                            <div class="accordion-body">
                                <div class="text-end mb-2 d-none">
                                    <a href="javascript:void(0);" class="j2commerce-clear-filter small text-decoration-none" data-filter-type="vendor" id="j2commerce-clear-vendor"<?php echo empty($sessionVendorIds) ? ' style="display:none;"' : ''; ?>>
                                        <?php echo Text::_('COM_J2COMMERCE_CLEAR'); ?>
                                    </a>
                                </div>
                                <div id="j2commerce-vendor-filter-container">
                                    <?php foreach ($this->filters['vendors'] as $vendor) : ?>
                                        <?php $checked = (!empty($sessionVendorIds) && in_array($vendor->j2commerce_vendor_id, $sessionVendorIds)); ?>
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input j2commerce-vendor-checkboxes" name="vendor_ids[]" id="vendor-input-<?php echo $vendor->j2commerce_vendor_id; ?>" value="<?php echo $vendor->j2commerce_vendor_id; ?>"<?php echo $checked ? ' checked' : ''; ?> />
                                            <label class="form-check-label small" for="vendor-input-<?php echo $vendor->j2commerce_vendor_id; ?>">
                                                <?php echo $this->escape($vendor->company); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <input type="hidden" name="option" value="com_j2commerce" />
            <input type="hidden" name="view" value="producttags" />
            <input type="hidden" name="task" value="browse" />
            <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>" />
        </form>

    </div>

    <div class="offcanvas-footer border-top p-3 d-md-none">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-dark flex-fill" id="j2commerce-mobile-clear-all">
                <?php echo Text::_('COM_J2COMMERCE_FILTER_CLEAR_ALL'); ?>
            </button>
            <button type="button" class="btn btn-dark flex-fill" data-bs-dismiss="offcanvas">
                <?php echo Text::_('COM_J2COMMERCE_FILTER_APPLY'); ?>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // A group may render as checkboxes, swatches, radios or a <select>; the value carrier is an
    // <option> in the last case and has .selected rather than .checked. The empty value is the
    // "Any" control of a single-value group and never counts as a selection.
    const pfPicked = el => (el.tagName === 'OPTION' ? el.selected : el.checked);
    const pfPick = (el, on) => { if (el.tagName === 'OPTION') { el.selected = on; } else { el.checked = on; } };
    const pfValued = el => el.value !== '';

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
                document.querySelectorAll('.' + filterClass).forEach(cb => pfPick(cb, !pfValued(cb)));
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
        const removeBtn = e.target.closest('.filter-chip .btn-close');
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
            if (cb) {
                pfPick(cb, false);
                const list = cb.closest('.j2commerce-productfilter-list');
                const any = list?.querySelector('[class*="j2commerce-pfilter-checkboxes"][value=""]');
                if (any) pfPick(any, true);
            }
        }
        submitWithLoading();
    });

    document.getElementById('j2commerce-clear-all-filters')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('.j2commerce-brand-checkboxes, .j2commerce-vendor-checkboxes, [class*="j2commerce-pfilter-checkboxes"]').forEach(cb => pfPick(cb, !pfValued(cb)));
        submitWithLoading();
    });

    bindMobileFooter();

    function buildFallbackTiles() {
        const container = document.getElementById('j2commerce-active-filter-tiles');
        const clearAllBtn = document.getElementById('j2commerce-clear-all-filters');
        if (!container) return;

        const tiles = [];
        document.querySelectorAll('.j2commerce-brand-checkboxes:checked, .j2commerce-vendor-checkboxes:checked, [class*="j2commerce-pfilter-checkboxes"]:checked').forEach(cb => {
            if (!pfValued(cb)) return;

            const label = cb.dataset.label?.trim() || cb.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim();
            if (!label) return;

            const type = cb.classList.contains('j2commerce-brand-checkboxes') ? 'brand'
                : cb.classList.contains('j2commerce-vendor-checkboxes') ? 'vendor' : 'productfilter';
            const escaped = document.createElement('span');
            escaped.textContent = label;
            tiles.push('<span class="filter-chip badge bg-light text-dark border d-flex align-items-center gap-1 p-2" data-type="' + type + '" data-id="' + cb.value + '">' + escaped.innerHTML + '<button type="button" class="btn-close text-danger ms-1" style="font-size:.5rem" aria-label="Remove"></button></span>');
        });

        container.innerHTML = tiles.length > 0 ? tiles.join('') : '';
        if (clearAllBtn) {
            clearAllBtn.style.display = tiles.length > 0 ? '' : 'none';
        }
    }

    function initClearButtonVisibility() {
        document.querySelectorAll('.j2commerce-productfilter-list').forEach(container => {
            const controls = container.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]');
            const checkedCount = Array.from(controls).filter(cb => pfValued(cb) && pfPicked(cb)).length;
            if (checkedCount > 0) {
                const filterId = container.id.replace('j2commerce-pf-filter-', '');
                const clearBtn = document.getElementById('product-filter-group-clear-' + filterId);
                if (clearBtn) clearBtn.style.display = 'inline';
                const collapseEl = container.closest('.accordion-collapse');
                if (collapseEl && !collapseEl.classList.contains('show')) {
                    collapseEl.classList.add('show');
                    const btn = collapseEl.previousElementSibling?.querySelector('.accordion-button');
                    if (btn) btn.classList.remove('collapsed');
                }
            }
        });
    }

    function bindMobileFooter() {
        document.getElementById('j2commerce-mobile-clear-all')?.addEventListener('click', () => {
            document.querySelectorAll('.j2commerce-brand-checkboxes, .j2commerce-vendor-checkboxes, [class*="j2commerce-pfilter-checkboxes"]').forEach(cb => pfPick(cb, !pfValued(cb)));
            const searchInput = document.getElementById('j2commerce-search');
            if (searchInput) searchInput.value = '';
            const offcanvasEl = document.getElementById('j2commerceFilterOffcanvas');
            const offcanvas = bootstrap?.Offcanvas?.getInstance(offcanvasEl);
            offcanvas?.hide();
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
            <input type="range" id="j2commerce-range-min" min="${sliderMin}" max="${sliderMax}" value="${sliderCurrentMin}" step="1" aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_FILTER_PRICE_MIN')); ?>">
            <input type="range" id="j2commerce-range-max" min="${sliderMin}" max="${sliderMax}" value="${sliderCurrentMax}" step="1" aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_FILTER_PRICE_MAX')); ?>">
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
