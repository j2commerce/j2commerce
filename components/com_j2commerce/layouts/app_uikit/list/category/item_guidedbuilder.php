<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppGuidedbuilder
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Language\Text;

extract($displayData);

$productId = $product->j2commerce_product_id;
$cssClass  = $product->params->get('product_css_class', '') ?? '';
$productType = htmlspecialchars($product->product_type ?? '', ENT_QUOTES, 'UTF-8');
$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeProductListItemDisplay', [$product, $context, &$displayData])->getArgument('html', '');
$afterHtml  = J2CommerceHelper::plugin()->eventWithHtml('AfterProductListItemDisplay', [$product, $context, &$displayData])->getArgument('html', '');
$cartText   = !empty($product->addtocart_text) ? $product->addtocart_text : Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_CONFIGURE');
?>

<div class="j2commerce-product-item j2commerce-product-<?php echo $productId; ?> j2commerce-type-<?php echo $productType;?> <?php echo $cssClass; ?> uk-flex uk-flex-column uk-height-1-1"
     data-product-id="<?php echo $productId; ?>"
     data-product-type="<?php echo $productType;?>">

    <?php echo $beforeHtml; ?>

    <?php if ($showImage): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_images', $displayData); ?>
    <?php endif; ?>

    <?php if ($showTitle): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_title', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayTitle)): ?>
        <?php echo $product->event->afterDisplayTitle; ?>
    <?php endif; ?>

    <?php if (isset($product->event->beforeDisplayContent)): ?>
        <?php echo $product->event->beforeDisplayContent; ?>
    <?php endif; ?>

    <?php if ($showDescription): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_description', $displayData); ?>
    <?php endif; ?>

    <div class="j2commerce-price-sku-container uk-flex uk-flex-wrap uk-flex-middle uk-flex-between" style="gap: .25rem">
        <?php if ($showPrice && !empty($product->pricing->is_from_price)): ?>
            <?php
            $productHelper = J2CommerceHelper::product();
            if ($productHelper->canShowprice($params)):
            ?>
            <div class="j2commerce-product-price-container uk-flex uk-flex-middle" style="gap: .25rem">
                <span class="uk-text-muted uk-text-small"><?php echo Text::_('COM_J2COMMERCE_FROM'); ?></span>
                <div class="sale-price uk-text-lead uk-text-bold">
                    <?php echo $productHelper->displayPrice((float) ($product->pricing->price ?? 0), $product, $params); ?>
                </div>
            </div>
            <?php endif; ?>
        <?php elseif ($showPrice): ?>
            <?php echo ProductLayoutService::renderLayout('list.category.item_price', $displayData); ?>
        <?php endif; ?>
        <?php if ($showSku): ?>
            <?php echo ProductLayoutService::renderLayout('list.category.item_sku', $displayData); ?>
        <?php endif; ?>
    </div>

    <?php if ($showCart): ?>
        <a href="<?php echo htmlspecialchars($productLink ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="uk-button uk-button-default uk-margin-auto-top">
            <span class="fa-solid fa-wand-magic-sparkles uk-margin-small-right" aria-hidden="true"></span>
            <?php echo htmlspecialchars($cartText ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endif; ?>

    <?php if ($showStock): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_stock', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayContent)): ?>
        <?php echo $product->event->afterDisplayContent; ?>
    <?php endif; ?>

    <?php echo $afterHtml; ?>
</div>
