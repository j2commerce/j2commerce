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

$settings  = $settings ?? [];
$maxChars  = (int) ($settings['max_chars'] ?? 150);
$cssClass  = $settings['css_class'] ?? 'j2commerce-product-description mb-3';

if (!($showDescription ?? true)) {
    return;
}

$desc = $product->product_short_desc ?? '';
if (empty($desc)) {
    return;
}

if ($maxChars > 0 && mb_strlen(strip_tags($desc)) > $maxChars) {
    $desc = mb_substr(strip_tags($desc), 0, $maxChars) . '&hellip;';
}
?>
<div class="<?php echo htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo $desc; ?>
</div>
