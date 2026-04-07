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

use Joomla\CMS\Language\Text;

$boxSize           = $this->boxSize ?? 4;
$availableProducts = $this->availableProducts ?? [];
$boxPrice          = $this->boxPrice ?? 0;
$boxPriceFormatted = $this->boxPriceFormatted ?? '';
$addToCartButtonText = $this->addToCartButtonText ?? Text::_('COM_J2COMMERCE_ADD_TO_CART');
$productId         = $this->product->j2commerce_product_id ?? 0;
$productDisplay    = $this->productDisplay ?? 'grid';

$slotGridClass = 'boxbuilder-slots-grid-' . $boxSize;
?>

<div class="boxbuilder-interactive-container" id="boxbuilder-container-<?php echo $productId; ?>" data-product-id="<?php echo $productId; ?>" data-box-size="<?php echo $boxSize; ?>" data-box-price="<?php echo $boxPrice; ?>">
    <div class="uk-grid-medium" uk-grid>
        <div class="uk-width-2-5@l uk-flex-last uk-flex-first@l boxbuilder-products-display">
            <div class="boxbuilder-products-section">
                <h4 class="boxbuilder-section-title uk-hidden@s uk-margin-bottom">
                    <?php echo Text::_('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_AVAILABLE_PRODUCTS'); ?>
                </h4>
                <h4 class="boxbuilder-section-title uk-visible@l uk-margin-bottom">
                    <?php echo Text::_('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_AVAILABLE_PRODUCTS'); ?>
                </h4>

                <?php if ($productDisplay === 'list'): ?>
                    <div class="boxbuilder-products-list">
                        <?php foreach ($availableProducts as $product): ?>
                            <?php
                            $this->availableProduct = $product;
                            echo $this->loadTemplate('boxbuilder_product_list_item');
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="boxbuilder-products-grid uk-grid-small uk-child-width-1-2 uk-child-width-1-3@l" uk-grid>
                        <?php foreach ($availableProducts as $product): ?>
                            <?php
                            $this->availableProduct = $product;
                            echo $this->loadTemplate('boxbuilder_product_card');
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="uk-width-3-5@l boxbuilder-general">
            <?php echo $this->loadTemplate('boxbuilder_general'); ?>

            <div uk-sticky="offset: 16; bottom: true">
                <?php echo $this->loadTemplate('boxbuilder_sidebar'); ?>

                <form action="<?php echo $this->product->cart_form_action; ?>"
                      method="post"
                      class="j2commerce-addtocart-form uk-width-1-1 uk-display-block uk-margin-auto-top"
                      id="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      name="j2commerce-addtocart-form-<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_id="<?php echo $this->product->j2commerce_product_id; ?>"
                      data-product_type="<?php echo $this->product->product_type; ?>"
                      enctype="multipart/form-data">

                    <input type="hidden" name="boxbuilder_selections" id="boxbuilder-selections-<?php echo $productId; ?>" value="[]">
                    <input type="hidden" name="boxbuilder_complete" id="boxbuilder-complete-<?php echo $productId; ?>" value="0">

                    <?php echo $this->loadTemplate('boxbuilder_cart'); ?>

                </form>
            </div>
        </div>
    </div>
</div>
