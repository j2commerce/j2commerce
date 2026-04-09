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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$cart_text = !empty($this->product->addtocart_text)
    ? Text::_($this->product->addtocart_text)
    : Text::_('COM_J2COMMERCE_ADD_TO_CART');

$show = J2CommerceHelper::product()->validateVariableProduct($this->product);
$productId = $this->product->j2commerce_product_id;
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$this->product, $this->context])->getArgument('html', ''); ?>

<?php if ($show) : ?>
    <div class="cart-action-complete" style="display:none;">
        <p class="uk-text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
            <?php if ($this->params->get('list_enable_quickview', 0) && Factory::getApplication()->getInput()->getString('tmpl') == 'component') : ?>
                <a href="<?php echo $this->product->checkout_link; ?>" class="j2commerce-checkout-link" target="_top">
            <?php else : ?>
                <a href="<?php echo $this->product->checkout_link; ?>" class="j2commerce-checkout-link">
            <?php endif; ?>
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
            </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $productId; ?>" class="j2commerce-add-to-cart uk-margin-top">
        <input type="hidden" id="j2commerce_product_id" name="product_id" value="<?php echo $productId; ?>" />

        <div class="product-add-to-cart-group">
            <?php echo J2CommerceHelper::product()->displayQuantity('com_j2commerce.product.uikit', $this->product, $this->params, ['class' => 'j2commerce-qty-input uk-input']); ?>

            <button
                data-cart-action-always="<?php echo Text::_('COM_J2COMMERCE_ADDING_TO_CART'); ?>"
                data-cart-action-done="<?php echo $cart_text; ?>"
                data-cart-action-timeout="1000"
                type="submit"
                class="j2commerce-cart-button uk-width-1-1 <?php echo $this->params->get('addtocart_button_class', 'uk-button uk-button-primary'); ?>"
            >
                <?php echo $cart_text; ?>
            </button>
        </div>
    </div>
<?php else : ?>
    <button type="button" class="j2commerce_button_no_stock uk-button uk-button-default"><?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?></button>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$this->product, $this->context])->getArgument('html', ''); ?>

<input type="hidden" name="option" value="com_j2commerce" />
<input type="hidden" name="view" value="carts" />
<input type="hidden" name="task" value="carts.addItem" />
<input type="hidden" name="ajax" value="0" />
<?php echo HTMLHelper::_('form.token'); ?>
<input type="hidden" name="return" value="<?php echo base64_encode(Uri::getInstance()->toString()); ?>" />
<div class="j2commerce-notifications"></div>
