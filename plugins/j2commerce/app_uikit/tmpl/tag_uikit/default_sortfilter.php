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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Producttags\HtmlView $this */

$app = Factory::getApplication();
$filterCatid = $this->filter_catid ?? '';
$search = htmlspecialchars($this->state->search ?? '', ENT_QUOTES, 'UTF-8');

$csrfTokenName = Session::getFormToken();
$app->getDocument()->addScriptOptions('csrf.token', $csrfTokenName);
Text::script('COM_J2COMMERCE_SHOWING_N_ITEMS');
Text::script('COM_J2COMMERCE_SHOWING_1_ITEM');

$currentSefPath = Uri::getInstance()->getPath();

?>

<form id="productFilters" name="productfilters" action="<?php echo htmlspecialchars($currentSefPath, ENT_QUOTES, 'UTF-8'); ?>" data-sef-path="<?php echo htmlspecialchars($currentSefPath, ENT_QUOTES, 'UTF-8'); ?>" method="get">
    <input type="hidden" name="filter_catid" id="sort_filter_catid" value="<?php echo $this->escape($filterCatid); ?>" />

    <div class="j2commerce-sortbar-filter uk-flex uk-flex-between uk-flex-middle uk-margin uk-padding-small uk-border-top" style="flex-wrap:wrap; gap:.5rem;">
        <div class="uk-flex uk-flex-middle j2commerce-sortbar-filter-left" style="gap:.5rem;">
            <?php
            $totalItems = (int) ($this->pagination->total ?? 0);
            $showingText = ($totalItems === 1)
                ? Text::_('COM_J2COMMERCE_SHOWING_1_ITEM')
                : Text::sprintf('COM_J2COMMERCE_SHOWING_N_ITEMS', $totalItems);
            ?>
            <p class="uk-text-muted uk-margin-remove" id="j2commerce-showing-count"><?php echo $showingText; ?></p>
        </div>

        <div class="uk-flex uk-flex-middle j2commerce-sortbar-filter-right" style="gap:.5rem;">
            <?php if ($this->params->get('list_show_filter_search', 1)) : ?>
                <div class="uk-inline">
                    <input type="text" name="search" id="j2commerce-search" class="uk-input j2commerce-product-search-input" value="<?php echo $search; ?>" placeholder="<?php echo Text::_('COM_J2COMMERCE_FILTER_SEARCH'); ?>" />
                    <button type="submit" class="uk-button uk-button-primary" title="<?php echo Text::_('COM_J2COMMERCE_FILTER_GO'); ?>">
                        <span uk-icon="icon: search"></span>
                    </button>
                    <button type="button" class="uk-button uk-button-default" id="j2commerce-filter-reset" title="<?php echo Text::_('COM_J2COMMERCE_FILTER_RESET'); ?>">
                        <span uk-icon="icon: close"></span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($this->params->get('list_show_filter_sorting', 1)) : ?>
                <?php
                $sortOptions = $this->filters['sorting'] ?? [];
                $currentSort = $this->state->sortby ?? '';
                ?>
                <select name="sortby" id="j2commerce-sortby" class="uk-select" aria-label="<?php echo Text::_('COM_J2COMMERCE_FILTER_SORT_BY'); ?>">
                    <?php foreach ($sortOptions as $value => $label) : ?>
                        <option value="<?php echo $this->escape($value); ?>"<?php echo ($currentSort === $value) ? ' selected' : ''; ?>>
                            <?php echo $this->escape($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <input type="hidden" name="option" value="com_j2commerce" />
    <input type="hidden" name="view" value="producttags" />
    <input type="hidden" name="task" value="browse" />
    <input type="hidden" name="Itemid" value="<?php echo $app->getInput()->getUint('Itemid', 0); ?>" />
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('productFilters');
    const resetBtn = document.getElementById('j2commerce-filter-reset');
    const sortSelect = document.getElementById('j2commerce-sortby');

    const ajaxEnabled = document.querySelector('.j2commerce-product-list')?.dataset.ajaxFilters === 'true';
    if (ajaxEnabled && typeof J2CommerceFilters !== 'undefined') {
        return;
    }

    resetBtn?.addEventListener('click', () => {
        const searchInput = form.querySelector('.j2commerce-product-search-input');
        if (searchInput) searchInput.value = '';
        if (sortSelect) sortSelect.selectedIndex = 0;
        window.location.href = window.location.pathname;
    });

    sortSelect?.addEventListener('change', () => {
        form.submit();
    });
});
</script>
