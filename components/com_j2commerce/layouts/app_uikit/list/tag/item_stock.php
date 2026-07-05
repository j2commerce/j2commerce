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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();

if (!$showStock) {
    return;
}

$variant = $product->variant ?? null;
if (!$variant || !is_object($variant)) {
    return;
}

$manageStock = $productHelper->managing_stock($variant);
$inStock = !$manageStock || !empty($variant->availability);
$stockClass = $inStock ? 'in-stock' : 'out-of-stock';

if (!$inStock) {
    $stockText = Text::_('COM_J2COMMERCE_OUT_OF_STOCK');
} elseif ($manageStock) {
    $componentParams = ComponentHelper::getParams('com_j2commerce');
    $stockText = ProductHelper::displayStock($variant, $componentParams);

    if (empty($stockText)) {
        return;
    }
} else {
    $stockText = Text::_('COM_J2COMMERCE_IN_STOCK');
}
?>
<div class="j2commerce-product-stock uk-text-small uk-padding-small uk-text-center <?php echo $stockClass; ?>">
    <?php echo $stockText; ?>
</div>
