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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$product = $this->singleton_product;
$params = $this->singleton_params;
$action = 'index.php?option=com_j2commerce&view=carts&task=carts.addItem&product_id=' . $product->j2commerce_product_id;
$context = J2CommerceHelper::utilities()->getContext('cart');
?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [$product, $context])->getArgument('html', ''); ?>
<div class="cart-action-complete" style="display:none;">
    <p class="uk-text-success">
        <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
        <a href="<?php echo $product->checkout_link; ?>" class="j2commerce-checkout-link">
            <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
        </a>
    </p>
</div>
<a class="<?php echo $params->get('addtocart_button_class', 'uk-button uk-button-primary'); ?> j2commerce_add_to_cart_button"
   href="<?php echo Route::_($action); ?>"
   data-quantity="1"
   data-product_id="<?php echo $product->j2commerce_product_id; ?>"
   rel="nofollow">
    <?php echo $this->singleton_cartext; ?>
</a>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [$product, $context])->getArgument('html', ''); ?>
