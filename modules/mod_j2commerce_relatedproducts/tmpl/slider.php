<?php
/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_relatedproducts
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

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
$loopEnabled   = $loop && count($products) > $slidesPerView;

$defaultHeading = match ($relationType) {
    'up_sells' => Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_UPSELLS'),
    'both'     => Text::_('MOD_J2COMMERCE_RELATEDPRODUCTS_HEADING_RECOMMENDED'),
    default    => Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_CROSS_SELLS'),
};
$heading = !empty($headingText) ? $headingText : $defaultHeading;
?>
<div class="j2commerce-relatedproducts-module j2commerce-relatedproducts-slider mod-j2commerce-relatedproducts-<?php echo $moduleId; ?> com_j2commerce"
     data-module-id="<?php echo $moduleId; ?>">

    <?php if ($showHeading) : ?>
        <h3 class="j2commerce-relatedproducts-heading mb-4"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>

    <div class="swiper" id="j2commerce-related-swiper-<?php echo $moduleId; ?>">
        <div class="swiper-wrapper">
            <?php foreach ($products as $product) : ?>
                <div class="swiper-slide">
                    <?php echo ProductLayoutService::renderProductItem(
                        $product,
                        $params,
                        ProductLayoutService::CONTEXT_MODULE,
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
        'nextEl' => '.mod-j2commerce-relatedproducts-' . $moduleId . ' .swiper-button-next',
        'prevEl' => '.mod-j2commerce-relatedproducts-' . $moduleId . ' .swiper-button-prev',
    ] : false,
    'pagination'    => $pagination ? [
        'el'        => '.mod-j2commerce-relatedproducts-' . $moduleId . ' .swiper-pagination',
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
    "document.addEventListener('DOMContentLoaded',function(){
    var el=document.getElementById('j2commerce-related-swiper-{$moduleId}');
    if(el&&typeof Swiper!=='undefined'){
        new Swiper(el,{$swiperConfig});
        if(typeof J2Commerce!=='undefined'){J2Commerce.equalizeHeights();}
    }
});",
    [],
    [],
    ['com_j2commerce.vendor.swiper']
);

if ($ajaxRefresh) :
$wa->addInlineScript(
    "document.addEventListener('DOMContentLoaded',function(){
    var moduleId={$moduleId};
    var container=document.querySelector('[data-module-id=\"'+moduleId+'\"]');
    if(!container)return;
    var refreshTimeout=null;
    function refreshRelatedProducts(){
        clearTimeout(refreshTimeout);
        refreshTimeout=setTimeout(function(){
            fetch('index.php?option=com_ajax&module=j2commerce_relatedproducts&method=getRelatedHtml&format=raw&module_id='+moduleId,{
                method:'GET',headers:{'Cache-Control':'no-cache'}
            })
            .then(function(r){return r.text();})
            .then(function(html){
                if(html.trim()){
                    container.outerHTML=html;
                    var nc=document.querySelector('[data-module-id=\"'+moduleId+'\"]');
                    if(nc){
                        nc.querySelectorAll('script').forEach(function(os){
                            var ns=document.createElement('script');
                            if(os.src){ns.src=os.src;}else{ns.textContent=os.textContent;}
                            os.parentNode.replaceChild(ns,os);
                        });
                        container=nc;
                        if(typeof J2Commerce!=='undefined'){J2Commerce.equalizeHeights();}
                    }
                }else{
                    container.style.display='none';
                }
            })
            .catch(function(e){console.error('Related products refresh error:',e);});
        },500);
    }
    document.addEventListener('j2commerce:afterAddingToCart',refreshRelatedProducts);
    document.addEventListener('j2commerce:cart:updated',refreshRelatedProducts);
    var cc=document.querySelector('.j2commerce-cart');
    if(cc){
        new MutationObserver(function(m){
            if(m.some(function(x){return x.type==='childList'&&x.removedNodes.length>0;}))refreshRelatedProducts();
        }).observe(cc,{childList:true,subtree:true});
    }
});",
    [],
    [],
    ['com_j2commerce.vendor.swiper']
);
endif;
