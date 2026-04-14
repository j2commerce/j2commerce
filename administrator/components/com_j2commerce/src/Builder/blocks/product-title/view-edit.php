<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

extract($displayData);

$settings    = $settings ?? [];
$tag         = $settings['tag'] ?? 'h3';
$fontSize    = $settings['font_size'] ?? 'fs-5';
$cssClass    = $settings['css_class'] ?? 'j2commerce-product-title';
$linkEnabled = $settings['link'] ?? true;

if ($fontSize && strpos($cssClass, $fontSize) === false) {
    $cssClass .= ' ' . $fontSize;
}
?>
<<?php echo $tag; ?> class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-title">
    <?php if ($linkEnabled): ?>
        <a href="#" class="text-decoration-none">
    <?php endif; ?>
    <j2c-token data-j2c-token="PRODUCT_NAME"><?php echo htmlspecialchars($product->product_name ?? 'Product Name', ENT_QUOTES, 'UTF-8'); ?></j2c-token>
    <?php if ($linkEnabled): ?>
        </a>
    <?php endif; ?>
</<?php echo $tag; ?>>
