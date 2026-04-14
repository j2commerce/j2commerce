<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

if (!empty($this->item->addtocart_text)) {
	$cart_text = Text::_($this->item->addtocart_text);
} else {
	$cart_text = Text::_('COM_J2COMMERCE_ADD_TO_CART');
}
$show = J2CommerceHelper::product()->validateVariableProduct($this->item);

$manageStock = J2CommerceHelper::product()->managing_stock($this->item->variant);
$is_available = $this->item->variant->availability;
$is_out_of_stock = false;
if($manageStock === true && $is_available === 0)
    $is_out_of_stock = true;

if($is_out_of_stock){
    $disabled = ' disabled';
} else {
    $disabled = '';
}
?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>

<?php if($show): ?>
    <div class="cart-action-complete" style="display:none;">
        <p class="text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART');?>
            <?php if ($this->params->get('list_enable_quickview', 0) && Factory::getApplication()->getInput()->getString('tmpl') === 'component') : ?>
                <a href="<?php echo $this->item->checkout_link; ?>" class="j2commerce-checkout-link" target="_top">
            <?php else:?>
                <a href="<?php echo $this->item->checkout_link; ?>" class="j2commerce-checkout-link">
            <?php endif;?>
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
            </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $this->item->j2commerce_product_id; ?>" class="j2commerce-add-to-cart d-flex flex-wrap flex-sm-nowrap flex-md-wrap flex-lg-nowrap gap-3 gap-lg-2 gap-xl-3 mb-4 mt-4">

        <?php echo J2CommerceHelper::product()->displayQuantity('com_j2commerce.product', $this->item, $this->params, ['class' => 'form-control form-control-sm']); ?>

        <input type="hidden" id="j2commerce_product_id" name="product_id" value="<?php echo $this->item->j2commerce_product_id;?>" />

        <button data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>" data-cart-action-done="<?php echo $cart_text; ?>" data-cart-action-timeout="1000" value="<?php echo $cart_text; ?>" type="submit" class="j2commerce-cart-button j2commerce-cart-button rounded-1 btn btn-lg w-100 animate-slide-end order-sm-2 order-md-4 order-lg-2 d-flex align-items-center justify-content-center <?php echo $this->params->get('addtocart_button_class', 'btn-primary');?>"<?php echo $disabled;?>>
            <span class="si-shopping-cart fs-4 animate-target ms-n1 me-2" aria-hidden="true"></span><span class="fs-6 text-capitalize"><?php echo $cart_text; ?></span>
        </button>

        <div class="order-5 AfterAddToCartButton">
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>
        </div>
    </div>
<?php else: ?>
    <button class="j2commerce-cart-button j2commerce_button_no_stock rounded-1 btn btn-lg w-100 order-sm-2 order-md-4 order-lg-2 d-flex align-items-center justify-content-center btn btn-primary mb-4 mt-4" disabled>
        <span class="si-shopping-cart fs-4 ms-n1 me-2" aria-hidden="true"></span><span class="fs-6 text-capitalize"><?php echo Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK'); ?></span>
    </button>
    <div class="order-5 AfterAddToCartButton">
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>
    </div>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>

<input type="hidden" name="option" value="com_j2commerce" />
<input type="hidden" name="view" value="carts" />
<input type="hidden" name="task" value="carts.addItem" />
<input type="hidden" name="ajax" value="0" />
<?php echo HTMLHelper::_( 'form.token' ); ?>
<input type="hidden" name="return" value="<?php echo base64_encode( Uri::getInstance()->toString() ); ?>" />
<div class="j2commerce-notifications"></div>

<div id="AfterProductCartGrid">
    <div class="d-flex flex-wrap gap-3 gap-xl-4">
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductCartGrid', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>
    </div>
</div>

<div id="AfterProductCart" class="py-3">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductCart', [$this->item, J2CommerceHelper::utilities()->getContext('view_cart')])->getArgument('html');?>
</div>
