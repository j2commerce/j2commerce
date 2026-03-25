<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Field\ManufacturersField;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductfiltersModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\User;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

$productFilters = (new ProductfiltersModel)->getFiltersByProduct($item->j2commerce_product_id);



?>
<div class="alert alert-info alert-block">
    <strong><?php echo Text::_('COM_J2COMMERCE_NOTE'); ?></strong> <?php echo Text::_('COM_J2COMMERCE_FEATURE_AVAILABLE_IN_J2COMMERCE_PRODUCT_LAYOUTS'); ?>
</div>

<input type="hidden" name="<?php echo $formPrefix.'[productfilter_ids]';?>" value="" />

<div class="table-responsive">
    <table id="product_filters_table" class="table itemList j2commerce">
        <thead>
            <tr>
                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FILTER_VALUE');?></th>
                <th scope="col" class="w-1 text-center"><?php echo Text::_('COM_J2COMMERCE_REMOVE');?></th>
            </tr>
        </thead>
        <tbody>
        <?php if(isset($productFilters) && count($productFilters)): ?>
            <?php foreach($productFilters as $group_id=>$filters):?>
                <tr>
                    <td colspan="2"><h4 class="mb-0"><?php echo Text::_($this->escape($filters['group_name'])); ?></h4></td>
                </tr>
                <?php foreach($filters['filters'] as $filter):
                    ?>
                    <tr id="product_filter_current_option_<?php echo $filter->filter_id;?>">
                        <td class="addedFilter">
                            <?php echo $this->escape($filter->filter_name) ;?>
                        </td>
                        <td class="text-center">
                                <span class="filterRemove" onclick="removeFilter(<?php echo $filter->filter_id; ?>, <?php echo $item->j2commerce_product_id; ?>);">
                                    <span class="icon icon-trash text-danger"></span>
                                </span>
                            <input type="hidden" value="<?php echo $filter->filter_id;?>" name="<?php echo $formPrefix.'[productfilter_ids]' ;?>[]" />
                        </td>
                    </tr>
                <?php endforeach;?>
            <?php endforeach;?>
        <?php endif;?>
        <tr class="j2commerce_a_filter">
            <td colspan="2">
                <small><strong><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_PRODUCT_FILTERS');?></strong></small>
                <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => 'productfilter','id'    => 'J2CommerceproductFilter','value' => '','class' => 'form-control ms-2',] + $textFieldDefaults);?>
            </td>
        </tr>
        </tbody>
    </table>
</div>

