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

use J2Commerce\Component\J2commerce\Administrator\Helper\LengthHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\WeightHelper;
use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$show_product_filters = $this->params->get('item_show_product_filters', 1);
$hide_empty_product_dimensions = $this->params->get('item_hide_empty_product_dimensions', 0);
$productfilters = $this->product->productfilters ?? [];

?>
<?php if (isset($productfilters)) : ?>
    <div class="j2commerce-product-specifications">
        <table class="table table-borderless mb-0">
            <?php foreach ($productfilters as $group_id => $rows) : ?>
                <tr class="filter-group-<?php echo $group_id;?>">
                    <td class="fw-bold border-bottom"><span class="filter-group-name"><?php echo $this->escape(J2htmlHelper::translateKey($rows['group_name'])); ?></span></td>
                    <td class="text-end border-bottom">
                        <?php
                        $items = [];
                        foreach ($rows['filters'] as $filter) {
                            $items[] = '<span class="classname">' . $this->escape(J2htmlHelper::translateKey($filter->filter_name)) . '</span>';
                        }
                        echo implode(', ', $items);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<table class="table table-borderless">
    <?php
    $variant = $this->product->variant ?? null;

    $length = (float) ($variant->length ?? 0);
    $width  = (float) ($variant->width ?? 0);
    $height = (float) ($variant->height ?? 0);
    $weight = (float) ($variant->weight ?? 0);

    $showDimensions = false;
    $showWeight     = false;

    if ($hide_empty_product_dimensions) {
        // Only show if ALL dimensions are > 0
        $showDimensions = ($length > 0 && $width > 0 && $height > 0);
        $showWeight     = ($weight > 0);
    } else {
        // Original behavior
        $showDimensions = true;
        $showWeight     = true;
    }
    ?>
    <?php if ($showDimensions) : ?>
        <tr>
            <td class="fw-bold border-bottom">
                <?php echo Text::_('COM_J2COMMERCE_PRODUCT_DIMENSIONS'); ?>
            </td>
            <td class="text-end border-bottom">
            <span class="product-dimensions">
                <?php echo LengthHelper::formatDimensions(
                    $length,
                    $width,
                    $height,
                    $variant->length_title,
                    (int) ($variant->length_class_id ?? 0)
                ); ?>
            </span>
            </td>
        </tr>
    <?php endif; ?>
    <?php if ($showWeight) : ?>
        <tr>
            <td class="fw-bold">
                <?php echo Text::_('COM_J2COMMERCE_PRODUCT_WEIGHT'); ?>
            </td>
            <td class="text-end">
            <span class="product-weight">
                <?php echo WeightHelper::formatValue(
                    $weight,
                    $variant->weight_title,
                    (int) ($variant->weight_class_id ?? 0)
                ); ?>
            </span>
            </td>
        </tr>
    <?php endif; ?>
</table>
