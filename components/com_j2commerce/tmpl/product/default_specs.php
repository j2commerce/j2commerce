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

/**
 * Helper function to pluralize length unit titles
 *
 * @param   float   $value  The numeric value
 * @param   string  $title  The unit title (e.g., "Inch", "Centimeter")
 *
 * @return  string  The pluralized unit title
 */
$pluralizeLengthUnit = static function (float $value, string $title): string {
    if ($value <= 1) {
        return $title;
    }

    // Special case for "Inch" -> "Inches"
    if ($title === 'Inch') {
        return $title . 'es';
    }

    return $title . 's';
};

/**
 * Helper function to pluralize weight unit titles
 *
 * @param   float   $value  The numeric value
 * @param   string  $title  The unit title (e.g., "Pound", "Kilogram")
 *
 * @return  string  The pluralized unit title
 */
$pluralizeWeightUnit = static function (float $value, string $title): string {
    return $value > 1 ? $title . 's' : $title;
};

?>
<?php if (isset($this->filters) && count($this->filters)) : ?>
    <ul class="j2commerce-product-specifications list-unstyled d-flex flex-column gap-3 fs-xs pb-3 m-0 mb-2 mb-sm-3">
        <?php foreach ($this->filters as $group_id => $rows) : ?>
            <li class="d-flex align-items-center position-relative pe-0">
                <span class="filter-group-name fw-semibold text-dark"><?php echo $this->escape(J2htmlHelper::translateKey($rows['group_name'])); ?>:</span>
                <span class="d-block flex-grow-1 border-bottom border-dashed px-1 mt-2 mx-2"></span>
                <?php
                $filterNames = [];
                foreach ($rows['filters'] as $filter) {
                    $filterNames[] = $this->escape(J2htmlHelper::translateKey($filter->filter_name));
                }
                ?>
                <span class="text-dark-emphasis fw-normal fs-xs text-end"><?php echo implode(', ', $filterNames); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (isset($this->item->variant) && !empty($this->item->variant)) :
    $length = (float) $this->item->variant->length;
    $width = (float) $this->item->variant->width;
    $height = (float) $this->item->variant->height;
    $weight = (float) $this->item->variant->weight;
    $sumDimensions = $length + $width + $height + $weight;
    ?>
    <?php if ($sumDimensions > 0) : ?>
        <h3 class="font-j2commerce fs-6 text-capitalize fw-bold mb-3"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DIMENSIONS'); ?></h3>
        <ul class="j2commerce-product-specifications list-unstyled d-flex flex-column gap-3 fs-xs pb-3 m-0 mb-2 mb-sm-3">
            <?php if ($length > 0) : ?>
                <li class="d-flex align-items-center position-relative pe-0">
                    <span class="filter-group-name fw-semibold text-dark"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_LENGTH'); ?>:</span>
                    <span class="d-block flex-grow-1 border-bottom border-dashed px-1 mt-2 mx-2"></span>
                    <span class="text-dark-emphasis fw-normal fs-xs text-end product-length">
                        <?php echo LengthHelper::formatValue($length) . ' ' . $pluralizeLengthUnit($length, $this->item->variant->length_title); ?>
                    </span>
                </li>
            <?php endif; ?>
            <?php if ($width > 0) : ?>
                <li class="d-flex align-items-center position-relative pe-0">
                    <span class="filter-group-name fw-semibold text-dark"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_WIDTH'); ?>:</span>
                    <span class="d-block flex-grow-1 border-bottom border-dashed px-1 mt-2 mx-2"></span>
                    <span class="text-dark-emphasis fw-normal fs-xs text-end product-width">
                        <?php echo LengthHelper::formatValue($width) . ' ' . $pluralizeLengthUnit($width, $this->item->variant->length_title); ?>
                    </span>
                </li>
            <?php endif; ?>
            <?php if ($height > 0) : ?>
                <li class="d-flex align-items-center position-relative pe-0">
                    <span class="filter-group-name fw-semibold text-dark"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_HEIGHT'); ?>:</span>
                    <span class="d-block flex-grow-1 border-bottom border-dashed px-1 mt-2 mx-2"></span>
                    <span class="text-dark-emphasis fw-normal fs-xs text-end product-height">
                        <?php echo LengthHelper::formatValue($height) . ' ' . $pluralizeLengthUnit($height, $this->item->variant->length_title); ?>
                    </span>
                </li>
            <?php endif; ?>
            <?php if ($weight > 0) : ?>
                <li class="d-flex align-items-center position-relative pe-0">
                    <span class="filter-group-name fw-semibold text-dark"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_WEIGHT'); ?>:</span>
                    <span class="d-block flex-grow-1 border-bottom border-dashed px-1 mt-2 mx-2"></span>
                    <span class="text-dark-emphasis fw-normal fs-xs text-end product-weight">
                        <?php echo WeightHelper::formatValue($weight) . ' ' . $pluralizeWeightUnit($weight, $this->item->variant->weight_title); ?>
                    </span>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
