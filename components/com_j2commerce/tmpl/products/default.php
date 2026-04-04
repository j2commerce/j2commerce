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

/** @var \J2Commerce\Component\J2commerce\Site\View\Products\HtmlView $this */

$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_j2commerce.site,css', 'media/com_j2commerce/css/site/j2commerce.css');
$wa->registerAndUseScript('com_j2commerce.site', 'media/com_j2commerce/js/site/j2commerce.js', [], ['defer' => true], []);
$wa->registerAndUseScript('com_j2commerce.filters', 'media/com_j2commerce/js/site/j2commerce-filters.es6.js', [], ['defer' => true], []);

$itemId = isset($this->active_menu->id) ? (int) $this->active_menu->id : 0;
$activeLink = Route::_(RouteHelper::getProductsRoute($this->filter_catid ?? null));
$filterPosition = $this->params->get('list_filter_position', 'right');

$app->getDocument()->addScriptOptions('j2commerce.Itemid', $itemId);
$columns = (int) $this->params->get('list_no_of_columns', 3);
$colClass = 'col-md-' . (int) round(12 / $columns);

$enableAjaxFilters = $this->params->get('list_enable_ajax_filters', 1);

$platform = J2CommerceHelper::platform();
if ($this->params->get('list_show_filter', 1) && $filterPosition === 'left'){
    $paddingClass = 'inner_class ps-lg-5';
} elseif($this->params->get('list_show_filter', 1) && $filterPosition === 'right') {
    $paddingClass = 'inner_class pe-lg-5';
} else {
    $paddingClass = 'inner_class';
}
?>
<div class="j2commerce-product-list bs5" data-link="<?php echo $activeLink; ?>" data-ajax-filters="<?php echo $enableAjaxFilters ? 'true' : 'false'; ?>">

    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeViewProductListDisplay', [$this->products])->getArgument('html', ''); ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-top'); ?>

    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
        </div>
    <?php endif; ?>

    <?php if ($this->params->get('show_category_title', 1) && $this->parent && $this->parent->title) : ?>
        <?php $categoryTag = $this->params->get('show_page_heading') ? 'h2' : 'h1'; ?>
        <<?php echo $categoryTag; ?> class="j2commerce-category-title mt-3"><?php echo $this->escape($this->parent->title); ?></<?php echo $categoryTag; ?>>
    <?php endif; ?>

    <?php if ($this->params->get('show_category_description', 0) && $this->parent && $this->parent->description && $this->params->get('category_description_placement', 'above') === 'above') : ?>
        <div class="j2commerce-category-description mt-3"><?php echo $this->parent->description; ?></div>
    <?php endif; ?>

    <?php if ($this->params->get('list_show_filter_category', 1) && !empty($this->filters['filter_categories']) && count($this->filters['filter_categories'])) : ?>
        <?php echo $this->loadTemplate('categoryfilter'); ?>
    <?php endif; ?>

    <div class="row product-list-row mt-4">
        <?php if ($this->params->get('list_show_filter', 1) && $filterPosition === 'left') : ?>
            <div class="j2commerce-sidebar-filters-container col-md-3">
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-left-top'); ?>
                <?php echo $this->loadTemplate('filters'); ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-left-bottom'); ?>
            </div>
        <?php endif; ?>

        <div class="<?php echo $this->params->get('list_show_filter', 1) ? 'col-md-9' : 'col-12'; ?>">
            <div class="j2commerce-products-content <?php echo $paddingClass;?>">
                <?php if ($this->params->get('list_show_top_filter', 1)) : ?>
                    <?php echo $this->loadTemplate('sortfilter'); ?>
                <?php endif; ?>

                <?php if (!empty($this->products)) : ?>
                    <?php
                    $total = count($this->products);
                    $counter = 0;
                    ?>
                    <div class="j2commerce-products-row row g-4 mb-4">
                        <?php foreach ($this->products as $product) : ?>
                            <?php if (!($product->params instanceof Registry)) {
                                $product->params = new Registry($product->params ?? '{}');
                            }
                            ?>

                            <div class="<?php echo $colClass; ?>">
                                <?php echo ProductLayoutService::renderProductItem($product,$this->params,ProductLayoutService::CONTEXT_LIST,$itemId);?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form id="j2commerce-pagination" name="j2commercepagination" action="<?php echo Route::_(RouteHelper::getProductsRoute($this->filter_catid ?? null)); ?>" method="post">
                        <input type="hidden" name="option" value="com_j2commerce" />
                        <input type="hidden" name="view" value="products" />
                        <input type="hidden" name="task" id="task" value="browse" />
                        <input type="hidden" name="boxchecked" value="0" />
                        <input type="hidden" name="filter_order" value="" />
                        <input type="hidden" name="filter_order_Dir" value="" />
                        <input type="hidden" name="filter_catid" value="<?php echo $this->escape($this->filter_catid ?? ''); ?>" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <nav class="j2commerce-pagination mt-4" aria-label="<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>">
                            <?php echo $this->getPagination()->getPaginationLinks(); ?>
                            <?php //echo $this->pagination->getPagesLinks(); ?>
                        </nav>
                    </form>

                <?php else : ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <?php echo Text::_('COM_J2COMMERCE_NO_PRODUCTS_FOUND'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <?php if ($this->params->get('list_show_filter', 1) && $filterPosition === 'right') : ?>
            <div class="j2commerce-sidebar-filters-container col-md-3">
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-right-top'); ?>
                <?php echo $this->loadTemplate('filters'); ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-filter-right-bottom'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($this->params->get('show_category_description', 0) && $this->parent && $this->parent->description && $this->params->get('category_description_placement', 'above') === 'below') : ?>
        <div class="j2commerce-category-description mt-4"><?php echo $this->parent->description; ?></div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-product-list-bottom'); ?>
</div>

<?php if ($this->params->get('list_enable_quickview', 0)) : ?>
<?php
    $wa->registerAndUseScript('com_j2commerce.vendor.fancybox', 'media/com_j2commerce/vendor/fancybox/js/fancybox.umd.js', [], ['defer' => true]);
    $wa->registerAndUseStyle('com_j2commerce.vendor.fancybox.css', 'media/com_j2commerce/vendor/fancybox/css/fancybox.css');
    $scriptName = 'com_j2commerce.fancybox.init';
    if (!$wa->assetExists('script', $scriptName)) {
        $wa->registerScript($scriptName,'',[],['defer' => true],['com_j2commerce.vendor.fancybox']);
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
        $wa->addInlineScript($inlineScript,[],[],[$scriptName]);
    }
?>
<?php endif; ?>

<?php if ($enableAjaxFilters && class_exists('J2CommerceFilters', false) === false) : ?>
<?php
    $wa->addInlineScript("
document.addEventListener('DOMContentLoaded', () => {
    if (typeof J2CommerceFilters !== 'undefined') {
        window.j2commerceFilters = new J2CommerceFilters({
            productContainer: '.j2commerce-product-list',
            filterFormId: 'productsideFilters',
            sortFormId: 'productFilters',
            paginationFormId: 'j2commerce-pagination',
            endpoint: '" . Uri::base() . "index.php?option=com_j2commerce&task=products.filter&format=json',
            checkboxDebounce: 300,
            searchDebounce: 500
        });
    }
});
", [], ['defer' => true], ['com_j2commerce.filters']);
?>
<?php endif; ?>
