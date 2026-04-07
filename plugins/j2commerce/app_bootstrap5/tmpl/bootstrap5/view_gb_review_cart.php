<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppGuidedbuilder
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

$plugin    = PluginHelper::getPlugin('j2commerce', 'app_guidedbuilder');
$appParams = new Registry($plugin->params ?? '');

$productParams = $this->product->params instanceof Registry
    ? $this->product->params
    : new Registry($this->product->params ?? '{}');

$productId    = (int) ($this->product->j2commerce_product_id ?? 0);
$showQtyBtn   = (int) $productParams->get('show_quantity_field', 0);
$buttonClass  = $this->params->get('addtocart_button_class', 'btn-primary');
$cartText     = Text::_('COM_J2COMMERCE_ADD_TO_CART');
$totalPrice   = CurrencyHelper::format((float) ($this->total ?? 0));

$manageStock    = J2CommerceHelper::product()->managing_stock($this->product->variant);
$isAvailable    = (int) ($this->product->variant->availability ?? 0);
$isOutOfStock   = $manageStock && ($isAvailable === 0);
$disabledAttr   = $isOutOfStock ? ' disabled' : '';

echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]);
?>
<?php if (J2CommerceHelper::product()->validateVariableProduct($this->product)): ?>
<div class="gb-review-cart-section">
    <?php if ($isOutOfStock): ?>
    <button type="button" class="btn btn-warning gb-btn-no-stock" disabled>
        <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?>
    </button>
    <?php else: ?>
    <div class="gb-cart-form">
        <?php if ($showQtyBtn): ?>
        <div class="gb-qty-section mb-3">
            <div class="gb-qty-label"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></div>
            <?php echo J2CommerceHelper::product()->displayQuantity('com_j2commerce.product.bootstrap5', $this->product, $this->params, ['class' => 'gb-qty-input']); ?>
        </div>
        <?php else: ?>
        <input type="hidden" name="product_qty" value="1" />
        <?php endif; ?>

        <input type="hidden" id="j2commerce_product_id" name="product_id" value="<?php echo $productId; ?>" />
        <input type="hidden" name="product_type" value="guidedbuilder" />
        <input type="hidden" name="gb_selections" value="<?php echo htmlspecialchars(json_encode($this->selections ?? []), ENT_QUOTES, 'UTF-8'); ?>" />

        <button type="submit"
                class="btn btn-lg w-100 <?php echo htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8'); ?> gb-review-add-to-cart"
                data-cart-action-always="<?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ADDING_TO_CART'); ?>"
                data-cart-action-done="<?php echo $cartText; ?>"
                data-cart-action-timeout="1000"<?php echo $disabledAttr; ?>>
            <span class="fa-solid fa-cart-plus" aria-hidden="true"></span>
            <span class="gb-review-cart-price ms-2"><?php echo $cartText; ?></span>
        </button>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<button type="button" class="btn btn-warning"><?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?></button>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, J2CommerceHelper::utilities()->getContext('view_cart')]); ?>

<input type="hidden" name="option" value="com_j2commerce" />
<input type="hidden" name="view" value="carts" />
<input type="hidden" name="task" value="carts.addItem" />
<input type="hidden" name="ajax" value="1" />
<?php echo HTMLHelper::_('form.token'); ?>
<input type="hidden" name="return" value="<?php echo base64_encode(Uri::getInstance()->toString()); ?>" />
<div class="j2commerce-notifications"></div>