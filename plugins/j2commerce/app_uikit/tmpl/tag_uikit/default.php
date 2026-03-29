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
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Producttags\HtmlView $this */

$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_j2commerce.site', 'media/com_j2commerce/css/site/j2commerce.css');
$wa->registerAndUseScript('com_j2commerce.site', 'media/com_j2commerce/js/site/j2commerce.js', [], ['defer' => true], []);
$wa->registerAndUseScript('com_j2commerce.filters', 'media/com_j2commerce/js/site/j2commerce-filters.es6.js', [], ['defer' => true], []);

$itemId = isset($this->active_menu->id) ? (int) $this->active_menu->id : 0;
$activeLink = Route::_(RouteHelper::getProductsRoute(null));
$filterPosition = $this->params->get('list_filter_position', 'right');

$app->getDocument()->addScriptOptions('j2commerce.Itemid', $itemId);
$columns = (int) $this->params->get('list_no_of_columns', 3);

$enableAjaxFilters = $this->params->get('list_enable_ajax_filters', 1);

$platform = J2CommerceHelper::platform();
if ($this->params->get('list_show_filter', 1) && $filterPosition === 'left'){
    $paddingClass = 'inner_class uk-padding-left';
} elseif($this->params->get('list_show_filter', 1) && $filterPosition === 'right') {
    $paddingClass = 'inner_class uk-padding-right';
} else {
    $paddingClass = 'inner_class';
}
?>
<div class="j2commerce-product-list uikit" data-link="<?php echo $activeLink; ?>" data-ajax-filters="<?php echo $enableAjaxFilters ? 'true' : 'false'; ?>">

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeViewProductListDisplay', [$this->products])->getArgument('html', ''); ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-top'); ?>

    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
        </div>
    <?php endif; ?>

    <div class="uk-grid uk-margin-top product-list-row" uk-grid>
        <?php if ($this->params->get('list_show_filter', 1) && $filterPosition === 'left') : ?>
            <div class="j2commerce-sidebar-filters-container uk-width-1-4@m">
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-left-top'); ?>
                <?php echo $this->loadTemplate('filters'); ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-left-bottom'); ?>
            </div>
        <?php endif; ?>

        <div class="<?php echo $this->params->get('list_show_filter', 1) ? 'uk-width-3-4@m' : 'uk-width-1-1'; ?>">
            <div class="j2commerce-products-content <?php echo $paddingClass;?>">
                <?php if ($this->params->get('list_show_top_filter', 1)) : ?>
                    <?php echo $this->loadTemplate('sortfilter'); ?>
                <?php endif; ?>

                <?php if (!empty($this->products)) : ?>
                    <div class="j2commerce-products-row uk-grid uk-grid-medium uk-child-width-1-<?php echo $columns; ?>@s uk-margin-bottom" uk-grid>
                        <?php foreach ($this->products as $product) : ?>
                            <?php if (!($product->params instanceof Registry)) {
                                $product->params = new Registry($product->params ?? '{}');
                            }
                            ?>
                            <div>
                                <?php echo ProductLayoutService::renderProductItem($product, $this->params, ProductLayoutService::CONTEXT_LIST, $itemId); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form id="j2commerce-pagination" name="j2commercepagination" action="<?php echo $activeLink; ?>" method="post">
                        <input type="hidden" name="option" value="com_j2commerce" />
                        <input type="hidden" name="view" value="producttags" />
                        <input type="hidden" name="task" id="task" value="browse" />
                        <input type="hidden" name="boxchecked" value="0" />
                        <input type="hidden" name="filter_order" value="" />
                        <input type="hidden" name="filter_order_Dir" value="" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <nav class="j2commerce-pagination uk-margin-top" aria-label="<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </nav>
                    </form>

                <?php else : ?>
                    <div class="uk-grid" uk-grid>
                        <div class="uk-width-1-1">
                            <div class="uk-alert uk-alert-primary" uk-alert>
                                <?php echo Text::_('COM_J2COMMERCE_NO_PRODUCTS_FOUND'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <?php if ($this->params->get('list_show_filter', 1) && $filterPosition === 'right') : ?>
            <div class="j2commerce-sidebar-filters-container uk-width-1-4@m">
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-right-top'); ?>
                <?php echo $this->loadTemplate('filters'); ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-right-bottom'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-bottom'); ?>
</div>

<?php if ($this->params->get('list_enable_quickview', 0)) : ?>
<?php
    $wa->registerAndUseScript('com_j2commerce.fancybox', 'media/com_j2commerce/js/site/fancybox.umd.js', [], ['defer' => true]);
    $wa->registerAndUseStyle('com_j2commerce.fancybox.css', 'media/com_j2commerce/css/site/fancybox.css');
    $scriptName = 'com_j2commerce.fancybox.init';
    if (!$wa->assetExists('script', $scriptName)) {
        $wa->registerScript($scriptName, '', [], ['defer' => true], ['com_j2commerce.fancybox']);
        $inlineScript = "document.addEventListener('DOMContentLoaded', () => {
                if (typeof Fancybox !== 'undefined') {
                    Fancybox.bind('[data-fancybox]', {
                        animated: true,
                        showClass: 'f-zoomInUp',
                        hideClass: 'f-zoomOutDown',
                        mainClass: 'j2commerce-quickview-fancybox',
                        iframe: {css: {width: '960px',height: '80vh',},},
                        Toolbar: {display: {left: [],middle: [],right: ['close'],},},
                    });
                }
            });";
        $wa->addInlineScript($inlineScript, [], [], [$scriptName]);
    }
?>
<?php endif; ?>

<?php if ($enableAjaxFilters) : ?>
<?php
    // Build endpoint with tag_ids so AJAX requests filter by the same tags as the initial page load
    $tagIdsParam = '';
    if (!empty($this->tag_ids)) {
        $tagParts = [];
        foreach ($this->tag_ids as $tid) {
            $tagParts[] = 'tag_ids[]=' . (int) $tid;
        }
        $tagIdsParam = '&' . implode('&', $tagParts) . '&tag_match=' . urlencode($this->tag_match);
    }

    $wa->addInlineScript("
document.addEventListener('DOMContentLoaded', () => {
    if (typeof J2CommerceFilters !== 'undefined') {
        window.j2commerceFilters = new J2CommerceFilters({
            productContainer: '.j2commerce-product-list',
            filterFormId: 'productsideFilters',
            sortFormId: 'productFilters',
            paginationFormId: 'j2commerce-pagination',
            endpoint: '" . Uri::base() . "index.php?option=com_j2commerce&task=products.filter&format=json" . $tagIdsParam . "',
            checkboxDebounce: 300,
            searchDebounce: 500
        });
    }
});
", [], ['defer' => true], ['com_j2commerce.filters']);
?>
<?php endif; ?>
