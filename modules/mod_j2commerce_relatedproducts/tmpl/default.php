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

/** @var \Joomla\Registry\Registry $params */
/** @var array $products */
/** @var int $itemId */
/** @var int $moduleId */
/** @var string $layoutType */
/** @var bool $showHeading */
/** @var string $headingText */
/** @var string $relationType */
/** @var bool $ajaxRefresh */

if (empty($products)) {
    return;
}

if ($layoutType === 'slider') {
    require __DIR__ . '/slider.php';
    return;
}

$defaultHeading = match ($relationType) {
    'up_sells' => Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_UPSELLS'),
    'both'     => Text::_('MOD_J2COMMERCE_RELATEDPRODUCTS_HEADING_RECOMMENDED'),
    default    => Text::_('COM_J2COMMERCE_RELATED_PRODUCTS_CROSS_SELLS'),
};
$heading = !empty($headingText) ? $headingText : $defaultHeading;

$columns = (int) $params->get('list_no_of_columns', 4);
$colClass = match ($columns) {
    2       => 'col-6 col-md-6',
    3       => 'col-6 col-md-4',
    6       => 'col-6 col-md-2',
    default => 'col-6 col-md-3',
};
?>
<div class="j2commerce-relatedproducts-module mod-j2commerce-relatedproducts-<?php echo $moduleId; ?> j2commerce"
     data-module-id="<?php echo $moduleId; ?>">

    <?php if ($showHeading) : ?>
        <h3 class="j2commerce-relatedproducts-heading mb-4"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($products as $product) : ?>
            <div class="<?php echo $colClass; ?>">
                <?php echo ProductLayoutService::renderProductItem(
                    $product,
                    $params,
                    ProductLayoutService::CONTEXT_MODULE,
                    $itemId
                ); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($ajaxRefresh) :
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
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
});"
);
endif; ?>
