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

?>
<?php if (isset($this->filters) && count($this->filters)) : ?>
    <div class="j2commerce-product-specifications">
        <?php foreach ($this->filters as $group_id => $rows) : ?>
            <h4 class="filter-group-name"><?php echo $this->escape(J2htmlHelper::translateKey($rows['group_name'])); ?></h4>
            <table class="uk-table uk-table-striped">
                <?php foreach ($rows['filters'] as $filter) : ?>
                    <tr>
                        <td>
                            <?php echo $this->escape(J2htmlHelper::translateKey($filter->filter_name)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<table class="uk-table uk-table-striped">
    <tr>
        <td><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DIMENSIONS'); ?></td>
        <td>
            <span class="product-dimensions">
                <?php if (isset($this->product->variant) && !empty($this->product->variant)) : ?>
                    <?php if ($this->product->variant->length && $this->product->variant->height && $this->product->variant->width) : ?>
                        <?php echo LengthHelper::formatDimensions(
                            $this->product->variant->length,
                            $this->product->variant->width,
                            $this->product->variant->height,
                            $this->product->variant->length_title,
                            (int) ($this->product->variant->length_class_id ?? 0)
                        ); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <?php echo Text::_('COM_J2COMMERCE_EMPTY_DASHES'); ?>
                <?php endif; ?>
            </span>
        </td>
    </tr>
    <tr>
        <td>
            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_WEIGHT'); ?>
        </td>
        <td>
            <span class="product-weight">
                <?php if (isset($this->product->variant) && !empty($this->product->variant)) : ?>
                    <?php if ($this->product->variant->weight) : ?>
                        <?php echo WeightHelper::formatValue(
                            $this->product->variant->weight,
                            $this->product->variant->weight_title,
                            (int) ($this->product->variant->weight_class_id ?? 0)
                        ); ?>
                    <?php endif; ?>
                <?php else : ?>
                    <?php echo Text::_('COM_J2COMMERCE_EMPTY_DASHES'); ?>
                <?php endif; ?>
            </span>
        </td>
    </tr>
</table>
