<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_sku.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

extract($displayData);

$sku = $product->variant->sku ?? $product->sku ?? 'SAMPLE-001';
?>
<j2c-conditional data-condition="$showSku">
    <div class="j2commerce-product-sku small">
        <span class="sku-label"><?php echo Text::_('COM_J2COMMERCE_SKU'); ?></span>
        <span class="sku-value fw-bold">
            <j2c-token data-token="PRODUCT_SKU"><?php echo htmlspecialchars($sku, ENT_QUOTES, 'UTF-8'); ?></j2c-token>
        </span>
    </div>
</j2c-conditional>
