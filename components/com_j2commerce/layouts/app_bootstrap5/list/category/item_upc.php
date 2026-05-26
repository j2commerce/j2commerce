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
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$productHelper = J2CommerceHelper::product();

if (!$showUpc || !$productHelper->canShowUpc($params)) {
    return;
}

$upc = $product->variant->upc ?? $product->upc ?? '';
if (empty($upc)) {
    return;
}
?>
<div class="j2commerce-product-upc small">
    <span class="upc-label"><?php echo Text::_('COM_J2COMMERCE_UPC'); ?></span>
    <span class="upc-value fw-bold"><?php echo htmlspecialchars($upc, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
