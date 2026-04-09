<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

$productParams = $this->product->params;
if (!$productParams instanceof Registry) {
    $productParams = new Registry($productParams);
}

$boxSize            = (int) $productParams->get('box_size', 4);
$boxbuilderProducts = $productParams->get('boxbuilderproduct', []);
$productOrder       = $productParams->get('product_order', 'added');
$productDisplay     = $productParams->get('product_display', 'grid');

if (!empty($boxbuilderProducts)) {
    $this->availableProducts = [];
    $this->boxSize           = $boxSize;
    $this->boxPrice          = $this->product->pricing->price ?? 0;
    $this->productDisplay    = $productDisplay;

    $productHelper = J2CommerceHelper::product();
    $addedIndex    = 0;

    foreach ($boxbuilderProducts as $bProduct) {
        $bProduct = (object) $bProduct;
        $productId = (int) ($bProduct->product_id ?? 0);
        if (!$productId) {
            continue;
        }

        $fullProduct = ProductHelper::getFullProduct($productId, true, true);
        if (!$fullProduct) {
            continue;
        }

        // Get product image
        $productImage = '';
        if (ImageHelper::isValidImagePath($fullProduct->thumb_image ?? '')) {
            $productImage = $fullProduct->thumb_image;
        } elseif (ImageHelper::isValidImagePath($fullProduct->main_image ?? '')) {
            $productImage = $fullProduct->main_image;
        }

        // Check stock status
        $isOutOfStock = false;
        if ($productHelper->managing_stock($fullProduct->variant)) {
            $isOutOfStock = !$productHelper->check_stock_status($fullProduct->variant, 1);
        }

        // Get article ordering from #__content for article_order sorting
        $articleOrdering = 0;
        if (!empty($fullProduct->product_source_id)) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('ordering'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . (int) $fullProduct->product_source_id);
            $db->setQuery($query);
            $articleOrdering = (int) $db->loadResult();
        }

        $this->availableProducts[] = (object) [
            'product_id'      => $productId,
            'product_name'    => $fullProduct->product_name ?? '',
            'product_sku'     => $fullProduct->variant->sku ?? '',
            'product_image'   => $productImage,
            'is_out_of_stock' => $isOutOfStock,
            'article_ordering'=> $articleOrdering,
            'added_index'     => $addedIndex,
        ];
        $addedIndex++;
    }

    // Apply product ordering
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
        default: // 'added'
            usort($this->availableProducts, fn($a, $b) => $a->added_index <=> $b->added_index);
            break;
    }

    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
    $wa->registerAndUseStyle(
        'plg_j2commerce_app_boxbuilderproduct.interactive',
        'media/plg_j2commerce_app_boxbuilderproduct/css/boxbuilder-interactive.css',
        ['version' => 'auto']
    );
    $wa->registerAndUseScript(
        'plg_j2commerce_app_boxbuilderproduct.interactive',
        'media/plg_j2commerce_app_boxbuilderproduct/js/boxbuilder-interactive.js',
        ['version' => 'auto'],
        ['defer' => true]
    );

    Text::script('COM_J2COMMERCE_ADD_TO_CART');
    Text::script('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_ADD_X_MORE');
}
?>
<section class="product-<?php echo (int) $this->product->j2commerce_product_id; ?> <?php echo $this->escape($this->product->product_type); ?>-product">
    <div class="container">
        <div class="row">
            <div class="col-12">
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

<?php if ($this->params->get('item_use_tabs', 1) && $this->params->get('display_item_details', 0) && count($this->up_sells ?? [])): ?>
    <?php echo $this->loadTemplate('upsells'); ?>
<?php endif; ?>

<?php if ($this->params->get('item_use_tabs', 1) && $this->params->get('item_show_product_cross_sells', 0) && count($this->cross_sells ?? [])): ?>
    <?php echo $this->loadTemplate('crosssells'); ?>
<?php endif; ?>

<?php if (isset($this->product->source->event->afterDisplayContent)): ?>
    <?php echo $this->product->source->event->afterDisplayContent; ?>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('afterDisplayProductPage', [$this->product, $this->context]); ?>
