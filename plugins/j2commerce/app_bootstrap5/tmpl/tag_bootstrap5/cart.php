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
$action = 'index.php?option=com_j2commerce&view=carts&task=carts.addItem&product_id=' . (int) $product->j2commerce_product_id;
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', array($product, J2Store::utilities()->getContext('cart')))->getArgument('html', ''); ?>
<div class="cart-action-complete" style="display:none;">
		<p class="text-success">
			<?php echo JText::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART');?>
			<a href="<?php echo htmlspecialchars($product->checkout_link ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="j2commerce-checkout-link">
				<?php echo JText::_('COM_J2COMMERCE_CHECKOUT'); ?>
			</a>
		</p>
</div>
<a class="<?php echo $params->get('addtocart_button_class', 'btn btn-primary');?> j2commerce_add_to_cart_button"
href="<?php echo JRoute::_($action); ?>" data-quantity="1" data-product_id="<?php echo (int) $product->j2commerce_product_id;?>"
rel="nofollow">
<?php echo $this->singleton_cartext; ?>
</a>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', array($product, J2Store::utilities()->getContext('cart')))->getArgument('html', ''); ?>
