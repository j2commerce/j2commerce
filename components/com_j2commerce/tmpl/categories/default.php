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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Categories\HtmlView $this */

$params = $this->params;
$activeMenu = Factory::getApplication()->getMenu()->getActive();
$displayMode = $this->displayMode;
$itemId = $activeMenu ? (int) $activeMenu->id : 0;
$htag = $this->params->get('show_page_heading') ? 'h2' : 'h1';
$category_columns = (int) $params->get('category_columns', 4);

?>
<div class="j2commerce j2commerce-categories <?php echo $this->escape($params->get('pageclass_sfx', '')); ?>">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCategoriesView', array($this))->getArgument('html', ''); ?>
    <div class="container">
        <?php if ($this->params->get('show_page_heading')) : ?>
            <div class="page-header mb-3">
                <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-categories-top'); ?>

        <?php if ($params->get('show_category_description', 1) && $this->parent && !empty($this->parent->description)) : ?>
            <div class="category-desc mb-4">
                <?php echo $this->parent->description; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($this->items) && empty($this->products)) : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_J2COMMERCE_NO_CATEGORIES_FOUND'); ?>
            </div>
        <?php else : ?>

            <?php if ($displayMode === 'products') : ?>
                <?php // Products mode: show category cards + product grid for root category ?>
                <?php if (!empty($this->items)) : ?>
                    <div class="j2commerce-category-grid row row-cols-<?php echo $category_columns - 2;?> row-cols-md-<?php echo $category_columns - 1;?> row-cols-lg-<?php echo $category_columns;?> g-<?php echo $category_columns - 1;?> g-lg-<?php echo $category_columns;?> mb-4">
                        <?php foreach ($this->items as $category) :
                            $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                        ?>
                            <div class="col j2commerce-category-col">
                                <?php if(!empty($category->image)):?>
                                    <div class="j2commerce-category-image-container">
                                        <a href="<?php echo $categoryUrl; ?>" class="j2commerce-category-link d-block" title="<?php echo $this->escape($category->title); ?>">
                                            <?php echo ImageHelper::getProductImage($this->escape($category->image), 300, 'html', 300, 'img-fluid', $this->escape($category->image_alt ?: $category->title)); ?>
                                        </a>
                                    </div>
                                <?php endif;?>
                                <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="text-white-50 small j2commerce-category-product-count">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($category->children)) : ?>
                                    <div class="j2commerce-category-children">
                                        <ul class="list-unstyled mt-2 small">
                                            <?php foreach ($category->children as $child) :
                                                $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                                ?>
                                                <li>
                                                    <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                                    <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                        <span class="text-muted">(<?php echo $child->product_count; ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>


                <?php if (!empty($this->products)) : ?>
                    <<?php echo $htag; ?>><?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?></<?php echo $htag; ?>>
                    <div class="row g-3">
                        <?php foreach ($this->products as $product) : ?>
                            <div class="<?php echo $this->getProductColumnClass(); ?>">
                                <?php echo ProductLayoutService::renderProductItem(
                                    $product,
                                    $params,
                                    ProductLayoutService::CONTEXT_LIST,
                                    $itemId
                                ); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($displayMode === 'categories') : ?>
                <?php // Categories mode: show category cards only, no product grid ?>
                <?php if (!empty($this->items)) : ?>
                    <div class="j2commerce-category-grid row row-cols-<?php echo $category_columns - 2;?> row-cols-md-<?php echo $category_columns - 1;?> row-cols-lg-<?php echo $category_columns;?> g-<?php echo $category_columns - 1;?> g-lg-<?php echo $category_columns;?> mb-4">
                        <?php foreach ($this->items as $category) :
                            $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                            ?>
                            <div class="col j2commerce-category-col">
                                <?php if(!empty($category->image)):?>
                                    <div class="j2commerce-category-image-container">
                                        <a href="<?php echo $categoryUrl; ?>" class="j2commerce-category-link d-block" title="<?php echo $this->escape($category->title); ?>">
                                            <?php echo ImageHelper::getProductImage($this->escape($category->image), 300, 'html', 300, 'img-fluid', $this->escape($category->image_alt ?: $category->title)); ?>
                                        </a>
                                    </div>
                                <?php endif;?>
                                <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="text-white-50 small j2commerce-category-product-count">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($category->children)) : ?>
                                    <div class="j2commerce-category-children">
                                        <ul class="list-unstyled mt-2 small">
                                            <?php foreach ($category->children as $child) :
                                                $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                                ?>
                                                <li>
                                                    <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                                    <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                        <span class="text-muted">(<?php echo $child->product_count; ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($displayMode === 'categories_popular') : ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCategoriesTrendingProducts', array($this))->getArgument('html', ''); ?>
                <?php // Category cards + combined trending products ?>
                <?php if (!empty($this->items)) : ?>
                    <div class="j2commerce-category-grid row row-cols-<?php echo $category_columns - 2;?> row-cols-md-<?php echo $category_columns - 1;?> row-cols-lg-<?php echo $category_columns;?> g-<?php echo $category_columns - 1;?> g-lg-<?php echo $category_columns;?> mb-4">
                        <?php foreach ($this->items as $category) :
                            $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                            ?>
                            <div class="col j2commerce-category-col">
                                <?php if(!empty($category->image)):?>
                                    <div class="j2commerce-category-image-container">
                                        <a href="<?php echo $categoryUrl; ?>" class="j2commerce-category-link d-block" title="<?php echo $this->escape($category->title); ?>">
                                            <?php echo ImageHelper::getProductImage($this->escape($category->image), 300, 'html', 300, 'img-fluid', $this->escape($category->image_alt ?: $category->title), true); ?>
                                        </a>
                                    </div>
                                <?php endif;?>
                                <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="text-white-50 small j2commerce-category-product-count">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($category->children)) : ?>
                                    <div class="j2commerce-category-children">
                                        <ul class="list-unstyled mt-2 small">
                                            <?php foreach ($category->children as $child) :
                                                $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                                ?>
                                                <li>
                                                    <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                                    <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                        <span class="text-muted">(<?php echo $child->product_count; ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-categories-middle'); ?>
                <?php if (!empty($this->trendingProducts)) : ?>
                    <?php $popularDisplayType = $params->get('popular_display_type', 'grid'); ?>
                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeCategoriesTrendingProducts', array($this))->getArgument('html', ''); ?>
                    <<?php echo $htag; ?>><?php echo Text::_('COM_J2COMMERCE_TRENDING_PRODUCTS'); ?></<?php echo $htag; ?>>

                    <?php if ($popularDisplayType === 'scroller') : ?>
                        <?php
                        $slidesPerView = (int) $params->get('popular_slides_per_view', 4);
                        $spaceBetween  = (int) $params->get('popular_space_between', 20);
                        $autoplay      = (bool) $params->get('popular_autoplay', false);
                        $autoplayDelay = (int) $params->get('popular_autoplay_delay', 4);
                        $loop          = (bool) $params->get('popular_loop', false);
                        $navigation    = (bool) $params->get('popular_navigation', true);
                        $pagination    = (bool) $params->get('popular_pagination', false);
                        $loopEnabled   = $loop && count($this->trendingProducts) > $slidesPerView;
                        $swiperId      = 'j2commerce-trending-swiper';
                        ?>
                        <div class="j2commerce-popular-slider com_j2commerce">
                            <div class="swiper" id="<?php echo $swiperId; ?>">
                                <div class="swiper-wrapper">
                                    <?php foreach ($this->trendingProducts as $product) : ?>
                                        <div class="swiper-slide">
                                            <?php echo ProductLayoutService::renderProductItem(
                                                $product,
                                                $params,
                                                ProductLayoutService::CONTEXT_LIST,
                                                $itemId
                                            ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($navigation) : ?>
                                    <div class="swiper-button-next"></div>
                                    <div class="swiper-button-prev"></div>
                                <?php endif; ?>
                                <?php if ($pagination) : ?>
                                    <div class="swiper-pagination"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                        $swiperConfig = json_encode([
                            'slidesPerView' => 1,
                            'spaceBetween'  => $spaceBetween,
                            'loop'          => $loopEnabled,
                            'navigation'    => $navigation ? [
                                'nextEl' => '#' . $swiperId . ' .swiper-button-next',
                                'prevEl' => '#' . $swiperId . ' .swiper-button-prev',
                            ] : false,
                            'pagination'    => $pagination ? [
                                'el'        => '#' . $swiperId . ' .swiper-pagination',
                                'clickable' => true,
                            ] : false,
                            'autoplay'      => $autoplay
                                ? ['delay' => $autoplayDelay * 1000, 'disableOnInteraction' => false]
                                : false,
                            'breakpoints'   => [
                                576 => ['slidesPerView' => max(1, (int) ceil($slidesPerView / 3))],
                                768 => ['slidesPerView' => max(1, (int) ceil($slidesPerView / 2))],
                                992 => ['slidesPerView' => $slidesPerView],
                            ],
                        ], JSON_THROW_ON_ERROR);

                        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
                        $wa->registerAndUseScript('com_j2commerce.vendor.swiper', 'media/com_j2commerce/vendor/swiper/js/swiper-bundle.min.js', [], ['defer' => true]);
                        $wa->registerAndUseStyle('com_j2commerce.vendor.swiper.css', 'media/com_j2commerce/vendor/swiper/css/swiper-bundle.min.css');
                        $wa->addInlineScript(
                            "document.addEventListener('DOMContentLoaded', function() {
                                var el = document.getElementById('{$swiperId}');
                                if (el && typeof Swiper !== 'undefined') {
                                    new Swiper(el, {$swiperConfig});
                                    if (typeof J2Commerce !== 'undefined') { J2Commerce.equalizeHeights(); }
                                }
                            });",
                            [],
                            [],
                            ['com_j2commerce.vendor.swiper']
                        );
                        ?>
                    <?php else : ?>
                        <div class="row g-3">
                            <?php foreach ($this->trendingProducts as $product) : ?>
                                <div class="<?php echo $this->getPopularColumnClass(); ?>">
                                    <?php echo ProductLayoutService::renderProductItem(
                                        $product,
                                        $params,
                                        ProductLayoutService::CONTEXT_LIST,
                                        $itemId
                                    ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterCategoriesTrendingProducts', array($this))->getArgument('html', ''); ?>
            <?php endif; ?>

        <?php endif; ?>

        <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-categories-bottom'); ?>
    </div>
</div>
