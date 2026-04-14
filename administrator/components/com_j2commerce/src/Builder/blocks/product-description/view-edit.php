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

$settings = $settings ?? [];
$cssClass = $settings['css_class'] ?? 'j2commerce-product-description mb-3';
$desc     = $product->product_short_desc ?? 'A sample product description for preview purposes.';
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>" data-j2c-block="product-description">
    <j2c-token data-j2c-token="PRODUCT_DESCRIPTION"><?php echo htmlspecialchars(strip_tags($desc), ENT_QUOTES, 'UTF-8'); ?></j2c-token>
</div>
