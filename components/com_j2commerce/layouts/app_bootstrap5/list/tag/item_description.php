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

use Joomla\CMS\HTML\HTMLHelper;

extract($displayData);

$maxLength = (int) $params->get('list_description_length', 150);

$shortDesc = '';
if ($showDescription) {
    $shortDesc = $product->product_short_desc ?? '';
    if ($maxLength > 0) {
        $shortDesc = HTMLHelper::_('string.truncate', $shortDesc, $maxLength, true, true);
    }
}

$longDesc = '';
if ($showLongDescription) {
    $longDesc = $product->product_long_desc ?? '';
    if ($maxLength > 0) {
        $longDesc = HTMLHelper::_('string.truncate', $longDesc, $maxLength, true, true);
    }
}

if (empty($shortDesc) && empty($longDesc)) {
    return;
}
?>
<div class="j2commerce-product-description">
    <?php if (!empty($shortDesc)) : ?>
        <div class="j2commerce-product-short-desc"><?php echo $shortDesc; ?></div>
    <?php endif; ?>
    <?php if (!empty($longDesc)) : ?>
        <div class="j2commerce-product-long-desc"><?php echo $longDesc; ?></div>
    <?php endif; ?>
</div>
