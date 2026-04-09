<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

$productParams = $this->product->params;
if (!$productParams instanceof Registry) {
    $productParams = new Registry($productParams);
}

$boxSize           = (int) $productParams->get('box_size', 4);
$boxbuilderProducts = $productParams->get('boxbuilderproduct', []);
$productOrder      = $productParams->get('product_order', 'added');
$productDisplay    = $productParams->get('product_display', 'grid');

if (!empty($boxbuilderProducts)) {
    $this->availableProducts    = [];
    $this->boxSize              = $boxSize;
    $this->boxPrice             = $this->product->pricing->price ?? 0;
    $this->boxPriceFormatted    = J2CommerceHelper::currency()->format($this->boxPrice);
    $this->addToCartButtonText  = $productParams->get('add_to_cart_button_text', Text::_('COM_J2COMMERCE_ADD_TO_CART'));
    $this->productDisplay       = $productDisplay;

    $addedIndex = 0;
    foreach ($boxbuilderProducts as $bProduct) {
        $bProduct = (object) $bProduct;
        $productId = $bProduct->product_id ?? 0;
        if (!$productId) {
            continue;
        }

        $product = J2CommerceHelper::product()->setId($productId)->getProduct();
        if (!$product) {
            continue;
        }

        J2CommerceHelper::product()->runBehaviorFlag(true)->getProduct($product);

        $productImage = '';
        if (!empty($product->thumb_image)) {
            $productImage = $product->thumb_image;
        } elseif (!empty($product->main_image)) {
            $productImage = $product->main_image;
        }

        $isOutOfStock = false;
        if (J2CommerceHelper::product()->managing_stock($product->variant)) {
            $isOutOfStock = !J2CommerceHelper::product()->check_stock_status($product->variant, 1);
        }

        $articleOrdering = 0;
        if ($product->product_source_id) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('ordering'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $product->product_source_id);
            $db->setQuery($query);
            $articleOrdering = (int) $db->loadResult();
        }

        $this->availableProducts[] = (object) [
            'product_id'       => $productId,
            'product_name'     => $product->product_name ?? '',
            'product_sku'      => $product->variant->sku ?? '',
            'product_image'    => $productImage,
            'is_out_of_stock'  => $isOutOfStock,
            'article_ordering' => $articleOrdering,
            'added_index'      => $addedIndex,
        ];
        $addedIndex++;
    }

    switch ($productOrder) {
        case 'article_order_asc':
            usort($this->availableProducts, fn($a, $b) => $a->article_ordering <=> $b->article_ordering);
            break;
        case 'article_order_desc':
            usort($this->availableProducts, fn($a, $b) => $b->article_ordering <=> $a->article_ordering);
            break;
        case 'title_asc':
            usort($this->availableProducts, fn($a, $b) => strcasecmp($a->product_name, $b->product_name));
            break;
        case 'title_desc':
            usort($this->availableProducts, fn($a, $b) => strcasecmp($b->product_name, $a->product_name));
            break;
        case 'random':
            shuffle($this->availableProducts);
            break;
        case 'added':
        default:
            usort($this->availableProducts, fn($a, $b) => $a->added_index <=> $b->added_index);
            break;
    }

    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
    $wa->registerAndUseStyle(
        'plg_j2commerce_app_boxbuilderproduct.interactive',
        Uri::base() . 'media/plg_j2commerce_app_boxbuilderproduct/css/boxbuilder-interactive.css',
        ['version' => 'auto']
    );
    $wa->registerAndUseScript(
        'plg_j2commerce_app_boxbuilderproduct.interactive',
        Uri::base() . 'media/plg_j2commerce_app_boxbuilderproduct/js/boxbuilder-interactive.js',
        ['version' => 'auto'],
        ['defer' => true]
    );

    Text::script('COM_J2COMMERCE_ADD_TO_CART');
    Text::script('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_ADD_X_MORE');
}
?>
<section class="product-<?php echo $this->product->j2commerce_product_id; ?> <?php echo $this->product->product_type; ?>-product">
    <div class="uk-container">
        <div class="uk-grid" uk-grid>
            <div class="uk-width-1-1">
                <?php if (J2CommerceHelper::product()->canShowCart($this->params)): ?>
                    <?php if (!empty($this->availableProducts)): ?>
                        <?php echo $this->loadTemplate('boxbuilder_interactive'); ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($this->params->get('item_use_tabs', 1)): ?>
                    <?php echo $this->loadTemplate('tabs'); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$this->params->get('item_use_tabs')): ?>
        <?php echo $this->loadTemplate('notabs'); ?>
    <?php endif; ?>
</section>

<?php if ($this->params->get('item_use_tabs', 1)): ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('DisplayBoxBuilderDetails', [$this->product, $this->context]); ?>
<?php endif; ?>

<?php if ($this->params->get('item_use_tabs', 1)): ?>
    <?php if ($this->params->get('display_item_details', 0) && \count($this->up_sells)): ?>
        <?php echo $this->loadTemplate('upsells'); ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($this->params->get('item_use_tabs', 1)): ?>
    <?php if ($this->params->get('item_show_product_cross_sells', 0) && \count($this->cross_sells)): ?>
        <?php echo $this->loadTemplate('crosssells'); ?>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($this->product->source->event->afterDisplayContent)): ?>
    <?php echo $this->product->source->event->afterDisplayContent; ?>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('afterDisplayProductPage', [$this->product, $this->context]); ?>
