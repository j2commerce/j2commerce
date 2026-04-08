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
$productType = htmlspecialchars($product->product_type ?? 'simple', ENT_QUOTES, 'UTF-8');

$show = $productHelper->validateVariableProduct($product);

$beforeCart = J2CommerceHelper::plugin()->eventWithHtml(
    'BeforeAddToCartButton',
    [$product, $context]
)->getArgument('html', '');

$afterCart = J2CommerceHelper::plugin()->eventWithHtml(
    'AfterAddToCartButton',
    [$product, $context]
)->getArgument('html', '');
?>
<div class="j2commerce-product-cart">
    <?php echo $beforeCart; ?>

    <?php if ($hasOptions || $cartType == 2 || $cartType == 3): ?>
        <a href="<?php echo $productLink; ?>" class="<?php echo $chooseBtnClass; ?>">
            <?php echo Text::_('COM_J2COMMERCE_CART_CHOOSE_OPTIONS'); ?>
        </a>
    <?php elseif ($show): ?>
        <form action="<?php echo htmlspecialchars($product->cart_form_action ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              method="post"
              class="j2commerce-addtocart-form uk-margin-auto-top"
              id="j2commerce-addtocart-form-<?php echo $productId; ?>"
              data-product_id="<?php echo $productId; ?>"
              data-product_type="<?php echo $productType; ?>">

            <div class="cart-action-complete" style="display:none;">
                <p class="uk-text-success">
                    <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
                    <a href="<?php echo htmlspecialchars($product->checkout_link ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="j2commerce-checkout-link">
                        <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
                    </a>
                </p>
            </div>

            <div id="add-to-cart-<?php echo $productId; ?>" class="j2commerce-add-to-cart">
                <?php echo $productHelper->displayQuantity('com_j2commerce.productlist', $product, $params, ['class' => 'input-mini uk-input']); ?>

                <input type="hidden" name="product_id" value="<?php echo $productId; ?>" />

                <button type="submit"
                        class="j2commerce-cart-button <?php echo $btnClass; ?>"
                        data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>"
                        data-cart-action-done="<?php echo htmlspecialchars($cartText ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-cart-action-timeout="1000">
                    <?php echo htmlspecialchars($cartText ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>

            <input type="hidden" name="option" value="com_j2commerce" />
            <input type="hidden" name="view" value="carts" />
            <input type="hidden" name="task" value="carts.addItem" />
            <input type="hidden" name="ajax" value="0" />
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="return" value="<?php echo base64_encode(Uri::getInstance()->toString()); ?>" />

            <div class="j2commerce-notifications"></div>
        </form>
    <?php else: ?>
        <button type="button" class="j2commerce_button_no_stock uk-button uk-button-default" disabled>
            <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?>
        </button>
    <?php endif; ?>

    <?php echo $afterCart; ?>
</div>
