<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<?php if($this->params->get('item_show_product_sku', 1) && !empty($this->item->variant->sku)): ?>
    <div class="d-flex align-items-center product-sku fs-sm">
        <span class="sku-text text-dark fw-bold me-2"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SKU')?>:</span>
        <span class="text-dark fw-normal text-end sku"><?php echo $this->escape($this->item->variant->sku); ?></span>
    </div>
<?php endif; ?>
