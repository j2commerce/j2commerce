<?php
/**
 * @package     J2Commerce
 * @subpackage  plg_j2commerce_app_boxbuilderproduct
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

$plugin    = PluginHelper::getPlugin('j2commerce', 'app_boxbuilderproduct');
$appParams = new Registry($plugin->params ?? '');

$display_mobile_stickybar = (int) $appParams->get('display_mobile_stickybar', 1);

$productParams = $this->product->params instanceof Registry
    ? $this->product->params
    : new Registry($this->product->params ?? '{}');

$show_qty_btn = (int) $productParams->get('show_quantity_field', 0);
$box_size     = (int) $productParams->get('box_size', 4);
$productId    = (int) ($this->product->j2commerce_product_id ?? 0);

if ($box_size > 0) {
    $cart_text = Text::sprintf('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_ADD_X_MORE', $box_size);
} else {
    $cart_text = Text::_('COM_J2COMMERCE_ADD_TO_CART');
}

$manageStock  = J2CommerceHelper::product()->managing_stock($this->product->variant);
$is_available = $this->product->variant->availability ?? 0;
$is_out_of_stock = $manageStock && ($is_available == 0);
$disabled = $is_out_of_stock ? ' disabled' : '';
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$this->product, $this->context]); ?>

<?php if (J2CommerceHelper::product()->validateVariableProduct($this->product)): ?>
    <div class="cart-action-complete d-none" style="display:none;">
        <p class="text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
            <a href="<?php echo $this->escape($this->product->checkout_link ?? ''); ?>" class="j2commerce-checkout-link">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
            </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $productId; ?>" class="j2commerce-add-to-cart pt-3">
        <div class="product-add-to-cart-group">
            <?php if ($show_qty_btn): ?>
                <?php echo J2CommerceHelper::product()->displayQuantity('com_j2commerce.product.bootstrap5', $this->product, $this->params, ['class' => 'j2commerce-qty-input form-control border-0']); ?>
            <?php else: ?>
                <input type="hidden" name="product_qty" value="1" />
            <?php endif; ?>

            <input type="hidden" id="j2commerce_product_id" name="product_id" value="<?php echo $productId; ?>" />

            <button data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>"
                    data-cart-action-done="<?php echo $cart_text; ?>"
                    data-cart-action-timeout="1000"
                    value="<?php echo $cart_text; ?>"
                    type="submit"
                    class="j2commerce-cart-button rounded-1 btn btn-lg w-100 animate-slide-end order-sm-2 order-md-4 order-lg-2 d-flex align-items-center justify-content-center ms-0 <?php echo $this->params->get('addtocart_button_class', 'btn-primary'); ?> boxbuilder-action-btn"
                    id="boxbuilder-action-btn-<?php echo $productId; ?>"
                    <?php echo $disabled; ?>
                    disabled>
                <span class="fa-solid fa-shopping-cart fs-4 animate-target ms-n1 me-2" aria-hidden="true"></span>
                <span class="fs-6 text-capitalize boxbuilder-btn-text"><?php echo $cart_text; ?></span>
            </button>
        </div>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, $this->context]); ?>
    </div>
<?php else: ?>
    <button type="button" class="j2commerce_button_no_stock btn btn-warning"><?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?></button>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, $this->context])->getArgument('html', ''); ?>

<?php if ($display_mobile_stickybar): ?>
    <div class="boxbuilder-floating-bar d-lg-none" id="boxbuilder-floating-bar-<?php echo $productId; ?>">
        <div class="boxbuilder-floating-bar-inner">
            <div class="boxbuilder-progress-text">
                <span class="boxbuilder-count">0</span> / <span class="boxbuilder-total"><?php echo $box_size; ?></span>
                <?php echo Text::_('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_PRODUCTS_SELECTED_SHORT'); ?>
            </div>
            <button type="submit"
                    class="btn btn-primary boxbuilder-floating-action boxbuilder-mobile-cart-btn j2commerce-cart-button"
                    id="boxbuilder-floating-btn-<?php echo $productId; ?>"
                    disabled>
                <span class="boxbuilder-btn-text text-capitalize"><?php echo $cart_text; ?></span>
            </button>
        </div>
    </div>
<?php endif; ?>

<input type="hidden" name="option" value="com_j2commerce" />
<input type="hidden" name="view" value="carts" />
<input type="hidden" name="task" value="carts.addItem" />
<input type="hidden" name="ajax" value="0" />
<?php echo HTMLHelper::_('form.token'); ?>
<input type="hidden" name="return" value="<?php echo base64_encode(Uri::getInstance()->toString()); ?>" />
<div class="j2commerce-notifications"></div>


