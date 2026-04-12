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


$wa  = Factory::getApplication()->getDocument()->getWebAssetManager();
$style = '.dimensions-input-group .form-control{min-width:100px;width:auto!important;max-width:130px;}';
$script = 'document.addEventListener("DOMContentLoaded", function () {
        var checkAllShipping = document.getElementById("checkAllShipping");
        if (checkAllShipping) {
            checkAllShipping.addEventListener("click", function () {
                this.value = 0;
                if (this.checked === true) {
                    this.value = 1;
                }
                var shippingInput = document.getElementById("shippingInput");
                if (shippingInput) {
                    shippingInput.disabled = this.checked;
                }
            });
        }
    });';

$wa->addInlineScript($script, [], []);
$wa->addInlineStyle($style, [], []);


// Lengths
$lengths = isset($item->lengths) ? $item->lengths : [];
$lengthsArray = (array) $lengths; // cast stdClass -> array
$lengthsList = array_map(
    function ($text, $value) {
        return (object) ['value' => $value, 'text' => $text];
    },
    $lengthsArray,
    array_keys($lengthsArray)
);

// Weights
$weights = isset($item->weights) ? $item->weights : [];
$weightsArray = (array) $weights;
$weightsList = array_map(
    function ($text, $value) {
        return (object) ['value' => $value, 'text' => $text];
    },
    $weightsArray,
    array_keys($weightsArray)
);

$defaultLengthClassId = empty($item->variant->length_class_id)
    ? J2CommerceHelper::config()->get('config_length_class_id', 1)
    : $item->variant->length_class_id;

$defaultWeightClassId = empty($item->variant->weight_class_id)
    ? J2CommerceHelper::config()->get('config_weight_class_id', 2)
    : $item->variant->weight_class_id;
?>
<div class="j2commerce-product-shipping">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_SHIPPING');?></legend>
        <div class="form-grid">

            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-shipping-radio-group-lbl" for="j2commerce-product-shipping-radio-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_ENABLE_SHIPPING');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', ['name'  => $formPrefix.'[shipping]','id'    => 'j2commerce-product-shipping-radio-group','value' => $item->variant->shipping ?? 0,'options' => [(object) ['value' => 0, 'text' => Text::_('JNO')],(object) ['value' => 1, 'text' => Text::_('JYES')]]] + $switcherDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-dimensions-lbl" for="j2commerce-product-dimensions"><?php echo Text::_('COM_J2COMMERCE_DIMENSIONS');?></label>
                </div>
                <div class="controls">
                    <div class="input-group dimensions-input-group">
                        <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_LENGTH');?></span>
                        <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[length]','id'    => 'j2commerce-product-length','value' => $item->variant->length ?? '','class' => 'form-control','hint' => Text::_('COM_J2COMMERCE_LENGTH'),] + $textFieldDefaults);?>
                        <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_WIDTH');?></span>
                        <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[width]','id'    => 'j2commerce-product-width','value' => $item->variant->width ?? '','class' => 'form-control','hint' => Text::_('COM_J2COMMERCE_WIDTH'),] + $textFieldDefaults);?>
                        <span class="input-group-text"><?php echo Text::_('COM_J2COMMERCE_HEIGHT');?></span>
                        <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[height]','id'    => 'j2commerce-product-height','value' => $item->variant->height ?? '','class' => 'form-control','hint' => Text::_('COM_J2COMMERCE_HEIGHT'),] + $textFieldDefaults);?>
                    </div>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-length_class_id-select-group-lbl" for="j2commerce-product-length_class_id-select-group"><?php echo Text::_('COM_J2COMMERCE_DIMENSIONS_UNIT');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[length_class_id]','id'    => 'j2commerce-product-length_class_id-select-group','value' => $defaultLengthClassId,'options' => $lengthsList] + $fancySelectDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-weight-lbl" for="j2commerce-product-weight"><?php echo Text::_('COM_J2COMMERCE_WEIGHT');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[weight]','id'    => 'j2commerce-product-weight','value' => $item->variant->weight ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                </div>
            </div>


            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-weight_class_id-select-group-lbl" for="j2commerce-product-weight_class_id-select-group"><?php echo Text::_('COM_J2COMMERCE_WEIGHT_UNIT');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[weight_class_id]','id'    => 'j2commerce-product-weight_class_id-select-group','value' => $defaultWeightClassId,'options' => $weightsList] + $fancySelectDefaults);?>
                </div>
            </div>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductShippingEdit', array($this, $item, $formPrefix))->getArgument('html', ''); ?>
        </div>
    </fieldset>
</div>

