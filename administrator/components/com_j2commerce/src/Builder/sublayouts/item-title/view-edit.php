<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_title.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

extract($displayData);

$productName = htmlspecialchars($product->product_name ?? 'Product Name', ENT_QUOTES, 'UTF-8');
?>
<j2c-conditional data-condition="$showTitle">
    <h3 class="j2commerce-product-title fs-6">
        <j2c-conditional data-condition="$linkTitle">
            <a href="<j2c-token data-token="PRODUCT_LINK"><?php echo htmlspecialchars($productLink ?? '#', ENT_QUOTES, 'UTF-8'); ?></j2c-token>" class="text-decoration-none">
        </j2c-conditional>

        <j2c-token data-token="PRODUCT_NAME"><?php echo $productName; ?></j2c-token>

        <j2c-conditional data-condition="$linkTitle">
            </a>
        </j2c-conditional>
    </h3>
</j2c-conditional>
