<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$settings     = $settings ?? [];
$showQuantity = $settings['show_quantity'] ?? true;
$btnClass     = $settings['btn_class'] ?? 'btn btn-primary';
$cssClass     = $settings['css_class'] ?? 'j2commerce-add-to-cart mt-auto';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-cart">
    <div class="j2commerce-cart-buttons d-flex align-items-center">
        <div class="input-group">
            <?php if ($showQuantity): ?>
                <input type="number" value="1" min="1" class="form-control qty-input" style="max-width:70px;" disabled />
            <?php endif; ?>
            <button type="button" class="j2commerce-cart-button flex-fill <?php echo htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                <j2c-token data-j2c-token="ADD_TO_CART"><?php echo Text::_('COM_J2COMMERCE_ADD_TO_CART'); ?></j2c-token>
            </button>
        </div>
    </div>
</div>
