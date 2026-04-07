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
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

extract($displayData);

$productId = (int) $product->j2commerce_product_id;
$cssClass = htmlspecialchars($product->params->get('product_css_class', '') ?? '', ENT_QUOTES, 'UTF-8');
$productType = htmlspecialchars($product->product_type ?? '', ENT_QUOTES, 'UTF-8');

$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml(
    'BeforeProductListItemDisplay',
    [$product, $context, &$displayData]
)->getArgument('html', '');

$afterHtml = J2CommerceHelper::plugin()->eventWithHtml(
    'AfterProductListItemDisplay',
    [$product, $context, &$displayData]
)->getArgument('html', '');
?>
<div class="j2commerce-product-item j2commerce-product-<?php echo $productId; ?> j2commerce-type-<?php echo $productType;?> <?php echo $cssClass; ?> d-flex flex-column h-100"
     data-product-id="<?php echo $productId; ?>"
     data-product-type="<?php echo $productType;?>">

    <?php echo $beforeHtml; ?>

    <?php if ($showImage): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_images', $displayData); ?>
    <?php endif; ?>

    <?php if ($showTitle): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_title', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayTitle)): ?>
        <?php echo $product->event->afterDisplayTitle; ?>
    <?php endif; ?>

    <?php if (isset($product->event->beforeDisplayContent)): ?>
        <?php echo $product->event->beforeDisplayContent; ?>
    <?php endif; ?>

    <?php if ($showDescription): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_description', $displayData); ?>
    <?php endif; ?>

    <?php if ($showPrice): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_price', $displayData); ?>
    <?php endif; ?>

    <?php if ($showSku): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_sku', $displayData); ?>
    <?php endif; ?>

    <?php if ($showStock): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_stock', $displayData); ?>
    <?php endif; ?>

    <?php echo ProductLayoutService::renderLayout('list.tag.item_variableoptions', $displayData); ?>

    <?php if ($showQuickview): ?>
        <?php echo ProductLayoutService::renderLayout('list.tag.item_quickview', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayContent)): ?>
        <?php echo $product->event->afterDisplayContent; ?>
    <?php endif; ?>

    <?php echo $afterHtml; ?>
</div>
