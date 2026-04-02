<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_cart.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 * The cart form infrastructure (hidden inputs, CSRF token) is locked.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$productHelper = J2CommerceHelper::product();
$productId     = (int) ($product->j2commerce_product_id ?? 0);
$btnClass      = 'btn btn-primary';
$cartText      = 'Add to Cart';
?>
<j2c-conditional data-condition="$showCart && $productHelper->canShowCart($params)">
    <span data-j2c-locked="before-cart"></span>

    <div class="cart-action-complete" style="display:none;">
        <p class="text-success">
            <?php echo Text::_('COM_J2COMMERCE_ITEM_ADDED_TO_CART'); ?>
            <a href="#" class="j2commerce-checkout-link">
                <?php echo Text::_('COM_J2COMMERCE_CHECKOUT'); ?>
            </a>
        </p>
    </div>

    <div id="add-to-cart-<?php echo $productId; ?>" class="j2commerce-add-to-cart">
        <span data-j2c-locked="cart-quantity-input"></span>
        <div class="j2commerce-cart-buttons d-flex align-items-center">
            <div class="input-group">
                <button type="submit" class="j2commerce-cart-button flex-fill <?php echo htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($cartText, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
            <span data-j2c-locked="after-cart-button-icon"></span>
        </div>
    </div>

    <span data-j2c-locked="after-cart"></span>

    <span data-j2c-locked="cart-hidden-inputs"></span>
</j2c-conditional>
