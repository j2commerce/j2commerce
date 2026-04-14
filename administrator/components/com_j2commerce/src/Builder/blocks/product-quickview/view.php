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

$settings  = $settings ?? [];
$cssClass  = $settings['css_class'] ?? 'j2commerce-quickview';
$productId = $product->j2commerce_product_id ?? 0;

if (!($showQuickview ?? false)) {
    return;
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="btn btn-sm btn-light j2commerce-quickview-btn" data-product-id="<?php echo $productId; ?>">
        <span class="fa-solid fa-eye me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCE_QUICK_VIEW'); ?>
    </button>
</div>
