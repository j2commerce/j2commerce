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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

$plugin = PluginHelper::getPlugin('j2commerce', 'app_boxbuilderproduct');
$params = new Registry($plugin->params);

$display_mobile_stickybar = $params->get('display_mobile_stickybar', 1);

$productParamsRaw = $this->product->params;
$productParams    = ($productParamsRaw instanceof Registry) ? $productParamsRaw : new Registry($productParamsRaw);

$show_qty_btn = (int) $productParams->get('show_quantity_field', 0);
$box_size     = (int) $productParams->get('box_size', 0);

if (!empty($this->product->addtocart_text)) {
    $cart_text = Text::_($this->product->addtocart_text);
} else {
    if ($box_size > 0) {
        $cart_text = Text::sprintf('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_ADD_X_MORE', $box_size);
    } else {
        $cart_text = Text::_('COM_J2COMMERCE_ADD_TO_CART');
    }
}

$show          = J2CommerceHelper::product()->validateVariableProduct($this->product);
$manageStock   = J2CommerceHelper::product()->managing_stock($this->product->variant);
$is_available  = $this->product->variant->availability;
$is_out_of_stock = ($manageStock === true && $is_available === 0);
$disabled      = $is_out_of_stock ? ' disabled' : '';
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>
<?php if ($show): ?>
    <div class="cart-action-complete uk-hidden" style="display:none;">
        <p class="uk-text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
            <?php if ($this->params->get('list_enable_quickview', 0) && Factory::getApplication()->input->getString('tmpl') === 'component'): ?>
                <a href="<?php echo $this->product->checkout_link; ?>" class="j2commerce-checkout-link" target="_top">
            <?php else: ?>
                <a href="<?php echo $this->product->checkout_link; ?>" class="j2commerce-checkout-link">
            <?php endif; ?>
                    <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
                </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $this->product->j2commerce_product_id; ?>" class="j2commerce-add-to-cart uk-flex uk-flex-wrap uk-flex-between uk-margin-top uk-margin-bottom">
        <?php if ($show_qty_btn): ?>
            <?php echo J2CommerceHelper::product()->displayQuantity('com_j2commerce.product.uikit', $this->product, $this->params, ['class' => 'uk-input']); ?>
        <?php else: ?>
            <input type="hidden" name="product_qty" value="1" />
        <?php endif; ?>
        <input type="hidden" id="j2commerce_product_id" name="product_id" value="<?php echo $this->product->j2commerce_product_id; ?>" />

        <button data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>"
                data-cart-action-done="<?php echo $cart_text; ?>"
                data-cart-action-timeout="1000"
                value="<?php echo $cart_text; ?>"
                type="submit"
                class="j2commerce-cart-button uk-button uk-button-large uk-width-1-1 uk-flex uk-flex-middle uk-flex-center <?php echo $this->params->get('addtocart_button_class', 'uk-button-primary'); ?> boxbuilder-action-btn"
                id="boxbuilder-action-btn-<?php echo $this->product->j2commerce_product_id; ?>"
                disabled>
            <span uk-icon="icon: cart" class="uk-margin-small-right"></span>
            <span class="uk-text-capitalize boxbuilder-btn-text"><?php echo $cart_text; ?></span>
        </button>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>
    </div>
<?php else: ?>
    <div class="uk-margin AfterAddToCartButton">
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>
    </div>
<?php endif; ?>

<?php if ($display_mobile_stickybar): ?>
    <div class="boxbuilder-floating-bar uk-hidden@l" id="boxbuilder-floating-bar-<?php echo $this->product->j2commerce_product_id; ?>" style="position:fixed;bottom:0;left:0;right:0;z-index:1000;">
        <div class="boxbuilder-floating-bar-inner uk-flex uk-flex-between uk-flex-middle uk-padding-small" style="background:#fff;border-top:1px solid #e5e5e5;">
            <div class="boxbuilder-progress-text uk-text-small">
                <span class="boxbuilder-count">0</span> / <span class="boxbuilder-total"><?php echo $box_size; ?></span>
                <?php echo Text::_('PLG_J2COMMERCE_APP_BOXBUILDERPRODUCT_PRODUCTS_SELECTED_SHORT'); ?>
            </div>
            <button type="submit"
                    class="uk-button uk-button-primary boxbuilder-floating-action boxbuilder-mobile-cart-btn j2commerce-cart-button"
                    id="boxbuilder-floating-btn-<?php echo $this->product->j2commerce_product_id; ?>"
                    disabled>
                <span class="boxbuilder-btn-text uk-text-capitalize"><?php echo $cart_text; ?></span>
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

<div id="AfterProductCartGrid">
    <div class="uk-flex uk-flex-wrap" style="gap:1rem;">
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductCartGrid', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>
    </div>
</div>

<div id="AfterProductCart" class="uk-margin">
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductCart', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>
</div>
