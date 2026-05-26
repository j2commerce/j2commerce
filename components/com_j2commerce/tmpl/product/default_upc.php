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

use Joomla\CMS\Language\Text;
?>
<?php if ($this->params->get('item_show_product_upc', 0) && !empty($this->item->variant->upc)): ?>
    <div class="d-flex align-items-center product-upc fs-sm">
        <span class="upc-text text-dark fw-bold me-2"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_UPC'); ?>:</span>
        <span class="text-dark fw-normal text-end upc"><?php echo $this->escape($this->item->variant->upc); ?></span>
    </div>
<?php endif; ?>
