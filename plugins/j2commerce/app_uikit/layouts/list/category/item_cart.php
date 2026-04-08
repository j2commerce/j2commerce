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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();

if (!$showCart || !$productHelper->canShowCart($params)) {
    return;
}

$hasOptions = !empty($product->options) ||
    in_array($product->product_type ?? 'simple', ['variable', 'flexivariable', 'configurable', 'advancedvariable']);

$cartType = (int) $params->get('list_show_cart', 1);
$btnClass = $params->get('addtocart_button_class', 'uk-button uk-button-primary');
$chooseBtnClass = $params->get('choosebtn_class', 'uk-button uk-button-primary');
$productId = $product->j2commerce_product_id;
$productType = htmlspecialchars($product->product_type ?? '', ENT_QUOTES, 'UTF-8');

$show = $productHelper->validateVariableProduct($product);

$beforeCart = J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton',[$product, $context])->getArgument('html', '');

$afterCart = J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton',[$product, $context])->getArgument('html', '');
?>
<?php echo $beforeCart; ?>

<?php if($show): ?>
    <div class="cart-action-complete" style="display:none;">
        <p class="uk-text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
            <a href="<?php echo htmlspecialchars($product->checkout_link ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="j2commerce-checkout-link">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
            </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $productId; ?>" class="j2commerce-add-to-cart">
        <input type="hidden" name="product_id" value="<?php echo $productId; ?>" />
        <div class="j2commerce-cart-buttons uk-flex uk-flex-middle">
            <?php echo $productHelper->displayQuantity('com_j2commerce.productlist', $product, $params, ['class' => 'uk-input qty-input','show_buttons' => false]); ?>
            <button type="submit" class="j2commerce-cart-button uk-width-expand <?php echo $btnClass; ?>" data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>" data-cart-action-done="<?php echo $cartText; ?>" data-cart-action-timeout="1000">
                <?php echo $cartText; ?>
            </button>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButtonIcon',[$product, $context])->getArgument('html', ''); ?>
        </div>
    </div>
<?php else: ?>
    <button type="button" class="j2commerce_button_no_stock uk-button uk-button-default" disabled>
        <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?>
    </button>
<?php endif; ?>

<?php echo $afterCart; ?>

<input type="hidden" name="option" value="com_j2commerce" />
<input type="hidden" name="view" value="carts" />
<input type="hidden" name="task" value="carts.addItem" />
<input type="hidden" name="ajax" value="0" />
<?php echo HTMLHelper::_('form.token'); ?>
<input type="hidden" name="return" value="<?php echo base64_encode(Uri::getInstance()->toString()); ?>" />

<div class="j2commerce-notifications"></div>


