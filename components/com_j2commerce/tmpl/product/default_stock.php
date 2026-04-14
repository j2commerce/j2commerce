<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;


$displayStock = J2CommerceHelper::product()->displayStock($this->item->variant, $this->params);
$manageStock = J2CommerceHelper::product()->managing_stock($this->item->variant);



?>

<div class="product-stock-container d-flex align-items-center fs-sm ms-auto">
    <?php if(isset($this->item->variant) && $manageStock === true):?>
        <?php if($this->item->variant->availability): ?>
            <span class="instock availability d-flex align-items-center text-success"><span class="si-check-circle me-2"></span><span class="fs-sm"><?= $displayStock ?: Text::_('COM_J2COMMERCE_AVAILABLE') ?></span></span>
        <?php else: ?>
            <span class="outofstock availability d-flex align-items-center text-warning"><span class="si-banned me-2"></span><span class="fs-sm"><?= Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK') ?></span></span>
        <?php endif; ?>
    <?php endif; ?>
</div>
