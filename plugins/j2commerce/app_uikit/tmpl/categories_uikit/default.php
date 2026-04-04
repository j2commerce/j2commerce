<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppUikit
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Categories\HtmlView $this */

$params = $this->params;
$activeMenu = Factory::getApplication()->getMenu()->getActive();
$displayMode = $this->displayMode;
$itemId = $activeMenu ? (int) $activeMenu->id : 0;
$htag = $params->get('show_category_root_title', 1) ? 'h2' : 'h1';
$category_columns = (int) $params->get('category_columns', 4);
$showCategoryImage = (bool) $params->get('show_category_image', 1);

// UIkit child-width mapping (matches module grid pattern)
$childWidth = match ($category_columns) {
    2       => 'uk-child-width-1-2@s',
    3       => 'uk-child-width-1-2@s uk-child-width-1-3@m',
    6       => 'uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-6@l',
    default => 'uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l',
};
?>
<div class="j2commerce-categories uikit <?php echo $this->escape($params->get('pageclass_sfx', '')); ?>">
    <?php if ($params->get('show_category_root_title', 1)) : ?>
        <div class="uk-margin-medium-bottom">
            <h1><?php echo $this->escape($params->get('page_heading', '')); ?></h1>
        </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-categories-top'); ?>

    <?php if ($params->get('show_category_description', 1) && $this->parent && !empty($this->parent->description)) : ?>
        <div class="category-desc uk-margin-medium-bottom">
            <?php echo $this->parent->description; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($this->items) && empty($this->products)) : ?>
        <div class="uk-alert uk-alert-primary" uk-alert>
            <?php echo Text::_('COM_J2COMMERCE_NO_CATEGORIES_FOUND'); ?>
        </div>
    <?php else : ?>

        <?php if ($displayMode === 'products') : ?>
            <?php // Products mode: show category cards + product grid for root category ?>
            <?php if (!empty($this->items)) : ?>
                <div class="j2commerce-category-grid <?php echo $childWidth; ?> uk-margin-medium-bottom" uk-grid>
                    <?php foreach ($this->items as $category) :
                        $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                        $hasImage = $showCategoryImage && !empty($category->image);
                    ?>
                        <div>
                            <div class="uk-card uk-card-default uk-card-small uk-card-hover">
                                <?php if ($hasImage) : ?>
                                <div class="uk-card-media-top">
                                    <a href="<?php echo $categoryUrl; ?>">
                                        <img src="<?php echo $this->escape($category->image); ?>"
                                             alt="<?php echo $this->escape($category->image_alt ?: $category->title); ?>"
                                             loading="lazy">
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="uk-card-body">
                                    <h3 class="uk-card-title uk-text-truncate">
                                        <a href="<?php echo $categoryUrl; ?>" class="uk-link-reset"><?php echo $this->escape($category->title); ?></a>
                                    </h3>
                                    <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="uk-badge">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($category->children)) : ?>
                                    <ul class="uk-nav uk-nav-default uk-margin-small-top">
                                        <?php foreach ($category->children as $child) :
                                            $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                        ?>
                                        <li>
                                            <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                            <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                <span class="uk-text-muted">(<?php echo $child->product_count; ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($this->products)) : ?>
                <hr class="uk-divider-icon">
                <<?php echo $htag; ?>><?php echo Text::_('COM_J2COMMERCE_PRODUCTS'); ?></<?php echo $htag; ?>>
                <div class="uk-grid uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
                    <?php foreach ($this->products as $product) : ?>
                        <div>
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
                <div class="j2commerce-category-grid <?php echo $childWidth; ?>" uk-grid>
                    <?php foreach ($this->items as $category) :
                        $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                        $hasImage = $showCategoryImage && !empty($category->image);
                    ?>
                        <div>
                            <div class="uk-card uk-card-default uk-card-small uk-card-hover">
                                <?php if ($hasImage) : ?>
                                <div class="uk-card-media-top">
                                    <a href="<?php echo $categoryUrl; ?>">
                                        <img src="<?php echo $this->escape($category->image); ?>"
                                             alt="<?php echo $this->escape($category->image_alt ?: $category->title); ?>"
                                             loading="lazy">
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="uk-card-body">
                                    <h3 class="uk-card-title uk-text-truncate">
                                        <a href="<?php echo $categoryUrl; ?>" class="uk-link-reset"><?php echo $this->escape($category->title); ?></a>
                                    </h3>
                                    <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="uk-badge">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($category->children)) : ?>
                                    <ul class="uk-nav uk-nav-default uk-margin-small-top">
                                        <?php foreach ($category->children as $child) :
                                            $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                        ?>
                                        <li>
                                            <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                            <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                <span class="uk-text-muted">(<?php echo $child->product_count; ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($displayMode === 'categories_popular') : ?>
            <?php // Category cards + combined trending products ?>
            <?php if (!empty($this->items)) : ?>
                <div class="j2commerce-category-grid <?php echo $childWidth; ?> uk-margin-medium-bottom" uk-grid>
                    <?php foreach ($this->items as $category) :
                        $categoryUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $category->id, $activeMenu));
                        $hasImage = $showCategoryImage && !empty($category->image);
                    ?>
                        <div>
                            <div class="uk-card uk-card-default uk-card-small uk-card-hover">
                                <?php if ($hasImage) : ?>
                                <div class="uk-card-media-top">
                                    <a href="<?php echo $categoryUrl; ?>">
                                        <img src="<?php echo $this->escape($category->image); ?>"
                                             alt="<?php echo $this->escape($category->image_alt ?: $category->title); ?>"
                                             loading="lazy">
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="uk-card-body">
                                    <h3 class="uk-card-title uk-text-truncate">
                                        <a href="<?php echo $categoryUrl; ?>" class="uk-link-reset"><?php echo $this->escape($category->title); ?></a>
                                    </h3>
                                    <?php if ($params->get('show_product_count', 1)) : ?>
                                    <span class="uk-badge">
                                        <?php echo Text::plural('COM_J2COMMERCE_N_PRODUCTS', $category->product_count); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($category->children)) : ?>
                                    <ul class="uk-nav uk-nav-default uk-margin-small-top">
                                        <?php foreach ($category->children as $child) :
                                            $childUrl = Route::_(RouteHelper::getCategoryRouteInContext((int) $child->id, $activeMenu));
                                        ?>
                                        <li>
                                            <a href="<?php echo $childUrl; ?>"><?php echo $this->escape($child->title); ?></a>
                                            <?php if ($params->get('show_product_count', 1) && $child->product_count > 0) : ?>
                                                <span class="uk-text-muted">(<?php echo $child->product_count; ?>)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($this->trendingProducts)) : ?>
                <?php $popularDisplayType = $params->get('popular_display_type', 'grid'); ?>
                <hr class="uk-divider-icon">
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
                    <div class="uk-grid uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l" uk-grid>
                        <?php foreach ($this->trendingProducts as $product) : ?>
                            <div>
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

        <?php endif; ?>

    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadPosition('j2commerce-categories-bottom'); ?>
</div>
