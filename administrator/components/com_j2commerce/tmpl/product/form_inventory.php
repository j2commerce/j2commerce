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
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\User;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;



$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';
$product_params = json_decode($item->params);

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$switcherDefaults = ['onchange' => '', 'label' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'class' => ''];
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];
$fancySelectDefaults = ['multiple' => false, 'autofocus' => false, 'onchange' => '', 'dataAttribute' => '', 'readonly' => false, 'disabled' => false, 'hint' => '', 'required' => false];
$checkboxDefaults = ['disabled' => false, 'required' => false, 'autofocus' => false, 'onclick' => '', 'onchange' => '', 'dataAttribute' => ''];

?>
<div class="j2commerce-product-inventory">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_INVENTORY');?></legend>
        <div class="form-grid">
            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-manage_stock-radio-group-lbl" for="j2commerce-product-manage_stock-radio-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_MANAGE_STOCK');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', ['name'  => $formPrefix.'[manage_stock]','id'    => 'j2commerce-product-manage_stock-radio-group','value' => $item->variant->manage_stock ?? 0,'options' => [(object) ['value' => 0, 'text' => Text::_('JNO')],(object) ['value' => 1, 'text' => Text::_('JYES')]]] + $switcherDefaults);?>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-quantity_text-group-lbl" for="j2commerce-product-quantity_text-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUANTITY');?></label>
                </div>
                <div class="controls">
                    <input type="hidden" name="<?php echo $formPrefix;?>[quantity][j2commerce_productquantity_id]" class="input" value="<?php echo $item->variant->j2commerce_productquantity_id ?? 0;?>">
                    <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[quantity][quantity]','id'    => 'j2commerce-product-quantity_text-group','value' => $item->variant->quantity ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-allow_backorder-select-group-lbl" for="j2commerce-product-allow_backorder-select-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_ALLOW_BACKORDERS');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[allow_backorder]','id'    => 'j2commerce-product-allow_backorder-select-group','value' => $item->variant->allow_backorder ?? 0,'options' => [(object) ['value' => 0, 'text' => Text::_('COM_J2COMMERCE_NO_ALLOW')],(object) ['value' => 1, 'text' => Text::_('COM_J2COMMERCE_ALLOW')],(object) ['value' => 2, 'text' => Text::_('COM_J2COMMERCE_ALLOW_BUT_NOTIFY')]]] + $fancySelectDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-stock-status-select-group-lbl" for="j2commerce-product-stock-status-select-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_STOCK_STATUS');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[availability]','id'    => 'j2commerce-product-stock-status-select-group','value' => $item->variant->availability ?? 1,'options' => [(object) ['value' => 0, 'text' => Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK')],(object) ['value' => 1, 'text' => Text::_('COM_J2COMMERCE_STOCK_IN_STOCK')]]] + $fancySelectDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-notify_qty_text-group-lbl" for="j2commerce-product-notify_qty_text-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_NOTIFY_QUANTITY');?></label>
                </div>
                <div class="controls">
                    <div class="row row-cols-lg-auto g-3 align-items-center">
                        <div class="col-4">
                            <?php $attribs = (isset($item->variant->use_store_config_notify_qty) && $item->variant->use_store_config_notify_qty) ? array('id'=>'notify_qty' ,'disabled'=>'disabled','field_type'=>'integer') :array('id'=>'notify_qty','field_type'=>'integer');?>
                            <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[notify_qty]','id'    => 'j2commerce-product-notify_qty_text-group','value' => $item->variant->notify_qty ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                        </div>
                        <div class="col-12">
                            <div class="qty_restriction">
                                <?php echo LayoutHelper::render('joomla.form.field.checkbox', ['name'  => $formPrefix.'[use_store_config_notify_qty]','id'    => 'use_store_config_notify_qty','value' => $item->variant->use_store_config_notify_qty ?? 0,'class' => 'storeconfig','checked' => (isset($item->variant->use_store_config_notify_qty) && $item->variant->use_store_config_notify_qty) ? 'checked' : ''] + $checkboxDefaults);?>
                                <label for="use_store_config_notify_qty" class="lh-1 position-relative" style="top:-2px;"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_USE_STORE_CONFIGURATION'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-quantity_restriction-radio-group-lbl" for="j2commerce-product-quantity_restriction-radio-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUANTITY_RESTRICTION');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', ['name'  => $formPrefix.'[quantity_restriction]','id'    => 'j2commerce-product-quantity_restriction-radio-group','value' => $item->variant->quantity_restriction ?? 0,'options' => [(object) ['value' => 0, 'text' => Text::_('JNO')],(object) ['value' => 1, 'text' => Text::_('JYES')]]] + $switcherDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-max_sale_qty_text-group-lbl" for="j2commerce-product-max_sale_qty_text-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_MAX_SALE_QUANTITY');?></label>
                </div>
                <div class="controls">
                    <div class="row row-cols-lg-auto g-3 align-items-center">
                        <div class="col-4">
                            <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[max_sale_qty]','id'    => 'j2commerce-product-max_sale_qty_text-group','value' => $item->variant->max_sale_qty ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                        </div>
                        <div class="col-12">
                            <div class="qty_restriction">
                                <?php echo LayoutHelper::render('joomla.form.field.checkbox', ['name'  => $formPrefix.'[use_store_config_max_sale_qty]','id'    => 'use_store_config_max_sale_qty','value' => $item->variant->use_store_config_max_sale_qty ?? 0,'class' => 'storeconfig','checked' => (isset($item->variant->use_store_config_max_sale_qty) && $item->variant->use_store_config_max_sale_qty) ? 'checked' : ''] + $checkboxDefaults);?>
                                <label for="use_store_config_max_sale_qty" class="lh-1 position-relative" style="top:-2px;"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_USE_STORE_CONFIGURATION'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-min_sale_qty_text-group-lbl" for="j2commerce-product-min_sale_qty_text-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_MIN_SALE_QUANTITY');?></label>
                </div>
                <div class="controls">
                    <div class="row row-cols-lg-auto g-3 align-items-center">
                        <div class="col-4">
                            <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[min_sale_qty]','id'    => 'j2commerce-product-min_sale_qty_text-group','value' => $item->variant->min_sale_qty ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                        </div>
                        <div class="col-12">
                            <div class="qty_restriction">
                                <?php echo LayoutHelper::render('joomla.form.field.checkbox', ['name'  => $formPrefix.'[use_store_config_min_sale_qty]','id'    => 'use_store_config_min_sale_qty','value' => $item->variant->use_store_config_min_sale_qty ?? 0,'class' => 'storeconfig','checked' => (isset($item->variant->use_store_config_min_sale_qty) && $item->variant->use_store_config_min_sale_qty) ? 'checked' : ''] + $checkboxDefaults);?>
                                <label for="use_store_config_min_sale_qty" class="lh-1 position-relative" style="top:-2px;"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_USE_STORE_CONFIGURATION'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductInventoryEdit', array($this, $item, $formPrefix))->getArgument('html', ''); ?>
        </div>
    </fieldset>

</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Helper function to set up store config checkbox behavior
    function setupStoreConfigCheckbox(checkboxId, inputId) {
        const checkbox = document.getElementById(checkboxId);
        const input = document.getElementById(inputId);

        if (checkbox && input) {
            // Set initial state
            input.disabled = checkbox.checked;

            // Handle click events
            checkbox.addEventListener('click', function() {
                this.value = this.checked ? 1 : 0;
                input.disabled = this.checked;
            });
        }
    }

    // Set up each store config checkbox with its corresponding input
    setupStoreConfigCheckbox('use_store_config_notify_qty', 'j2commerce-product-notify_qty_text-group');
    setupStoreConfigCheckbox('use_store_config_max_sale_qty', 'j2commerce-product-max_sale_qty_text-group');
    setupStoreConfigCheckbox('use_store_config_min_sale_qty', 'j2commerce-product-min_sale_qty_text-group');
});
</script>
