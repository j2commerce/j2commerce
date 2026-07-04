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

if (!$showSku || !$productHelper->canShowSku($params)) {
    return;
}

$sku = $product->variant->sku ?? $product->sku ?? '';
if (empty($sku)) {
    return;
}
?>
<div class="j2commerce-product-sku small">
    <span class="sku-label"><?php echo Text::_('COM_J2COMMERCE_SKU'); ?></span>
    <span class="sku-value fw-bold"><?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
