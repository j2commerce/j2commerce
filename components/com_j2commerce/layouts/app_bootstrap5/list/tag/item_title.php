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

extract($displayData);

if (!$showTitle) {
    return;
}

$productName = htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8');
?>
<h3 class="j2commerce-product-title">
    <?php if ($linkTitle): ?>
        <a href="<?php echo htmlspecialchars($productLink ?? '', ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $productName; ?>">
    <?php endif; ?>

    <?php echo $productName; ?>

    <?php if ($linkTitle): ?>
        </a>
    <?php endif; ?>
</h3>
