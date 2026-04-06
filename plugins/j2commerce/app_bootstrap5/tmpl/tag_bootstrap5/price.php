<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

$product = $this->singleton_product;
$params = $this->singleton_params;

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', array($product))->getArgument('html', ''); ?>

<?php if($params->get('item_show_product_base_price', 1) || $params->get('item_show_product_special_price', 1)): ?>
<div class="product-price-container">
		<?php if($params->get('item_show_product_base_price', 1) && isset($product->pricing->base_price) && isset($product->pricing->price) && $product->pricing->base_price != $product->pricing->price): ?>
			<?php $class='';?>
			<?php if(isset($product->pricing->is_discount_pricing_available)) $class='strike'; ?>
			<div class="base-price <?php echo $class?>">
					<span class="product-element-value">
						<?php echo J2CommerceHelper::product()->displayPrice($product->pricing->base_price, $product, $params);?>
					</span>
			</div>
		<?php endif; ?>

		<?php if($params->get('item_show_product_special_price', 1) && isset($product->pricing->price) ): ?>
		<div class="sale-price">
			<span class="product-element-value">
				<?php echo J2CommerceHelper::product()->displayPrice($product->pricing->price, $product, $params);?>
				</span>
		</div>
	<?php endif; ?>

	<?php if($params->get('display_price_with_tax_info', 0) ): ?>
		<div class="tax-text">
			<?php echo J2CommerceHelper::product()->get_tax_text(); ?>
		</div>
	<?php endif; ?>

</div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', array($product))->getArgument('html', ''); ?>

<?php if($params->get('item_show_discount_percentage', 1)): ?>
    <div class="discount-percentage">
        <?php if( isset($product->pricing->is_discount_pricing_available) && isset($product->pricing->base_price) && !empty($product->pricing->base_price)): ?>
            <?php $discount =(1 - ($product->pricing->price / $product->pricing->base_price) ) * 100; ?>
            <?php if($discount > 0): ?>
                <?php  echo JText::sprintf('J2STORE_PRODUCT_OFFER',round($discount).'%');?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
