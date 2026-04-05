<?php


/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_products
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Factory;

// Variables inherited from default.php scope: $products, $params, $itemId, $moduleId

if (empty($products)) {
    return;
}

$slidesPerView = (int) $params->get('slides_per_view', 4);
$spaceBetween  = (int) $params->get('space_between', 20);
$autoplay      = (bool) $params->get('autoplay', false);
$autoplayDelay = (int) $params->get('autoplay_delay', 4);
$loop          = (bool) $params->get('loop', false);
$navigation    = (bool) $params->get('navigation', true);
$pagination    = (bool) $params->get('pagination', false);

// Loop requires more slides than slidesPerView to function correctly
$loopEnabled = $loop && count($products) > $slidesPerView;

echo J2CommerceHelper::plugin()->eventWithHtml('BeforeViewProductListDisplay', [$products])->getArgument('html', '');
?>
<div class="j2commerce-products-module j2commerce-products-slider mod-j2commerce-products-<?php echo $moduleId; ?> j2commerce">
    <div class="swiper" id="j2commerce-swiper-<?php echo $moduleId; ?>">
        <div class="swiper-wrapper">
            <?php foreach ($products as $product) : ?>
                <div class="swiper-slide">
                    <?php echo ProductLayoutService::renderProductItem($product,$params,ProductLayoutService::CONTEXT_MODULE,
                        $itemId);?>
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
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterViewProductListDisplay', [$products])->getArgument('html', '');

$swiperConfig = json_encode([
    'slidesPerView' => 1,
    'spaceBetween'  => $spaceBetween,
    'loop'          => $loopEnabled,
    'navigation'    => $navigation ? [
        'nextEl' => '.mod-j2commerce-products-' . $moduleId . ' .swiper-button-next',
        'prevEl' => '.mod-j2commerce-products-' . $moduleId . ' .swiper-button-prev',
    ] : false,
    'pagination'    => $pagination ? [
        'el'        => '.mod-j2commerce-products-' . $moduleId . ' .swiper-pagination',
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
$wa->addInlineScript(
    "document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('j2commerce-swiper-{$moduleId}');
    if (el && typeof Swiper !== 'undefined') {
        new Swiper(el, {$swiperConfig});
        if (typeof J2Commerce !== 'undefined') { J2Commerce.equalizeHeights(); }
    }
});",
    [],
    [],
    ['com_j2commerce.vendor.swiper']
);
