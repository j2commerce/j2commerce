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

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

extract($displayData);

$productId = $product->j2commerce_product_id;
$cssClass = $product->params->get('product_css_class', '') ?? '';
$productType = htmlspecialchars($product->product_type ?? '', ENT_QUOTES, 'UTF-8');
$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeProductListItemDisplay',[$product, $context, &$displayData])->getArgument('html', '');
$afterHtml = J2CommerceHelper::plugin()->eventWithHtml('AfterProductListItemDisplay',[$product, $context, &$displayData])->getArgument('html', '');
$cartType = (int) $params->get('list_show_cart', 1);
?>

<div class="j2commerce-product-item j2commerce-product-<?php echo $productId; ?> j2commerce-type-<?php echo $productType;?> <?php echo $cssClass; ?> uk-flex uk-flex-column uk-height-1-1"
     data-product-id="<?php echo $productId; ?>"
     data-product-type="<?php echo $productType;?>"
     data-equal-height="itemContainer">

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

    <div class="j2commerce-price-sku-container uk-flex uk-flex-wrap uk-flex-middle uk-flex-between uk-margin-bottom">
        <?php if ($showPrice): ?>
            <?php echo ProductLayoutService::renderLayout('list.category.item_price', $displayData); ?>
        <?php endif; ?>
        <?php if ($showSku): ?>
            <?php echo ProductLayoutService::renderLayout('list.category.item_sku', $displayData); ?>
        <?php endif; ?>
    </div>

    <?php if ($showCart): ?>
        <form action="<?php echo htmlspecialchars($product->cart_form_action ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              method="post"
              class="j2commerce-addtocart-form uk-margin-auto-top"
              id="j2commerce-addtocart-form-<?php echo $productId; ?>"
              data-product_id="<?php echo $productId; ?>"
              data-product_type="<?php echo $product->product_type; ?>"
              data-product_variants="<?php echo htmlspecialchars($product->variant_json ?? '{}', ENT_QUOTES, 'UTF-8'); ?>"
              enctype="multipart/form-data"
              >

            <?php if ($cartType == 1) : ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_configurableoptions', $displayData); ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_cart', $displayData); ?>
            <?php elseif (($cartType == 2 && !empty($product->options)) || $cartType == 3) : ?>
                <a href="<?php echo $productLink; ?>" class="uk-button uk-button-default">
                    <?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT_DETAILS'); ?>
                </a>
            <?php else : ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_cart', $displayData); ?>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($showStock): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_stock', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayContent)): ?>
        <?php echo $product->event->afterDisplayContent; ?>
    <?php endif; ?>

    <?php echo $afterHtml; ?>
</div>
