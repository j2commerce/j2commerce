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

if (!$showDescription) {
    return;
}

$shortDesc = $product->short_description ?? '';
if (empty($shortDesc)) {
    return;
}

$maxLength = (int) $params->get('list_description_length', 150);
if ($maxLength > 0) {
    $shortDesc = HTMLHelper::_('string.truncate', $shortDesc, $maxLength, true, true);
}
?>
<div class="j2commerce-product-description">
    <?php echo $shortDesc; ?>
</div>
