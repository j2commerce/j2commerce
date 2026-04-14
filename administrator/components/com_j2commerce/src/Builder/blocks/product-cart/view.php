<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;

extract($displayData);

$settings      = $settings ?? [];
$showQuantity  = $settings['show_quantity'] ?? true;
$btnClass      = $settings['btn_class'] ?? 'btn btn-primary';
$cssClass      = $settings['css_class'] ?? 'j2commerce-add-to-cart mt-auto';
$productHelper = J2CommerceHelper::product();

if (!($showCart ?? true) || !$productHelper->canShowCart($params)) {
    return;
}

$cartText  = $cartText ?? Text::_('COM_J2COMMERCE_ADD_TO_CART');
$productId = $product->j2commerce_product_id ?? 0;
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="j2commerce-cart-buttons d-flex align-items-center">
        <div class="input-group">
            <?php if ($showQuantity): ?>
                <input type="number" name="quantity" value="1" min="1" class="form-control qty-input" style="max-width:70px;" />
            <?php endif; ?>
            <button type="button" class="j2commerce-cart-button flex-fill <?php echo htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($cartText, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>
    </div>
</div>
