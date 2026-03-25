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
use J2Commerce\Component\J2commerce\Administrator\Field\VendorsField;
use J2Commerce\Component\J2commerce\Administrator\Field\TaxProfileField;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\User;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;

// Extract display data - MUST set $item BEFORE using it
$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$manufacturersField = new ManufacturersField();
$manufacturersField->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
$element = new SimpleXMLElement('<field />');
$manufacturersField->setup($element, '');
$manufacturerTypes = $manufacturersField->getOptions();
array_unshift($manufacturerTypes, (object) ['value' => '', 'text' => Text::_('COM_J2COMMERCE_SELECT_MANUFACTURER')]);

$vendorsField = new VendorsField();
$vendorsField->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
$element = new SimpleXMLElement('<field />');
$vendorsField->setup($element, '');
$vendorTypes = $vendorsField->getOptions();
array_unshift($vendorTypes, (object) ['value' => '', 'text' => Text::_('COM_J2COMMERCE_SELECT_VENDOR')]);

$taxprofilesField = new TaxProfileField();
$taxprofilesField->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
$element = new SimpleXMLElement('<field />');
$taxprofilesField->setup($element, '');
$taxprofileTypes = $taxprofilesField->getOptions();
array_unshift($taxprofileTypes, (object) ['value' => '', 'text' => Text::_('COM_J2COMMERCE_SELECT_TAX_PROFILE')]);

$product_params = json_decode($item->params);

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$switcherDefaults = ['onchange' => '', 'label' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'class' => ''];
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];
$fancySelectDefaults = ['multiple' => false, 'autofocus' => false, 'onchange' => '', 'dataAttribute' => '', 'readonly' => false, 'disabled' => false, 'hint' => '', 'required' => false];
?>

<div class="j2commerce-product-general">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_GENERAL');?></legend>
        <div class="form-grid">
            <div class="control-group">
                <div class="control-label">
                    <label id="j2commerce-product-visibility-radio-group-lbl" for="j2commerce-product-visibility-radio-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VISIBLE_STOREFRONT');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', ['name'  => $formPrefix.'[visibility]','id'    => 'j2commerce-product-visibility-radio-group','value' => $item->visibility,'options' => [(object) ['value' => 0, 'text' => Text::_('JNO')],(object) ['value' => 1, 'text' => Text::_('JYES')]]] + $switcherDefaults);?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-manufacturer-select-group-lbl" for="j2commerce-product-manufacturer-select-group"><?php echo Text::_('COM_J2COMMERCE_COUPON_MANUFACTURER');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[manufacturer_id]','id'    => 'j2commerce-product-manufacturer-select-group','value' => $item->manufacturer_id,'options' => $manufacturerTypes] + $fancySelectDefaults);?>
                </div>
            </div>
            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-vendor-select-group-lbl" for="j2commerce-product-vendor-select-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VENDOR');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[vendor_id]','id'    => 'j2commerce-product-vendor-select-group','value' => $item->vendor_id,'options' => $vendorTypes] + $fancySelectDefaults);?>
                </div>
            </div>
            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-taxprofile_id-select-group-lbl" for="j2commerce-product-taxprofile_id-select-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAX_PROFILE');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[taxprofile_id]','id'    => 'j2commerce-product-taxprofile_id-select-group','value' => $item->taxprofile_id,'options' => $taxprofileTypes] + $fancySelectDefaults);?>
                </div>
            </div>
            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-addtocart_text-group-lbl" for="j2commerce-product-addtocart_text-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_CART_TEXT');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[addtocart_text]','id'    => 'j2commerce-product-addtocart_text-group','value' => Text::_($item->addtocart_text ?? ''),'class' => 'form-control',] + $textFieldDefaults);?>
                </div>
            </div>
            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-product_css_class-group-lbl" for="j2commerce-product-product_css_class-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_CUSTOM_CSS_CLASS');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[product_css_class]','id'    => 'j2commerce-product-product_css_class_text-group','value' => $product_params->product_css_class ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                </div>
            </div>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductGeneralEdit', array($this, $item, $formPrefix))->getArgument('html', ''); ?>
        </div>
    </fieldset>
</div>

