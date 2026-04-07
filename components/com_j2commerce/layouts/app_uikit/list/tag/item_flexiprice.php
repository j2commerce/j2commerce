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

if (!$productHelper->canShowprice($params)) {
    return;
}

$minPrice = isset($product->min_price) ? (float) $product->min_price : null;
$maxPrice = isset($product->max_price) ? (float) $product->max_price : null;
$currency = J2CommerceHelper::currency();

$pricing = $product->pricing ?? null;
$defaultPrice = (float) ($pricing->price ?? 0);
$hasRange = $minPrice !== null && $maxPrice !== null && $minPrice != $maxPrice;
$showRange = !empty($product->show_price_range) && $hasRange;
?>
<div class="j2commerce-product-price j2commerce-flexiprice">
    <?php if ($showRange): ?>
        <span class="price-range uk-text-muted uk-text-small">
            <?php echo $currency->format($minPrice); ?> - <?php echo $currency->format($maxPrice); ?>
        </span>
    <?php endif; ?>
    <?php if ($defaultPrice > 0): ?>
        <span class="price-current">
            <?php echo $productHelper->displayPrice($defaultPrice, $product, $params); ?>
        </span>
    <?php elseif ($minPrice !== null && !$hasRange): ?>
        <span class="price-from">
            <?php echo Text::_('COM_J2COMMERCE_PRICE_FROM'); ?>
            <?php echo $currency->format($minPrice); ?>
        </span>
    <?php endif; ?>
</div>
