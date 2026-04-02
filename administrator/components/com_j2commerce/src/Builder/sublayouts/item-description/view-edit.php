<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Edit-mode companion template for list/category/item_description.php
 * Outputs tokenized HTML for the Visual Builder sub-layout editor.
 */

declare(strict_types=1);

defined('_JEXEC') or die;

extract($displayData);

$shortDesc = $product->short_description ?? '';
$maxLength = (int) $params->get('list_description_length', 150);

if (empty($shortDesc)) {
    $shortDesc = 'Product short description text will appear here.';
} elseif ($maxLength > 0 && strlen($shortDesc) > $maxLength) {
    $shortDesc = substr($shortDesc, 0, $maxLength) . '...';
}
?>
<j2c-conditional data-condition="$showDescription">
    <div class="j2commerce-product-description">
        <?php echo htmlspecialchars($shortDesc, ENT_QUOTES, 'UTF-8'); ?>
    </div>
</j2c-conditional>
