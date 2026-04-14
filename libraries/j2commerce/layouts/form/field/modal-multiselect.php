<?php
/**
 * @package     J2Commerce Library
 * @subpackage  Layout
 *
 * @copyright   (C)2024-2025 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   string   $autocomplete    Autocomplete attribute for the field.
 * @var   boolean  $autofocus       Is autofocus enabled?
 * @var   string   $class           Classes for the input.
 * @var   string   $description     Description of the field.
 * @var   boolean  $disabled        Is this field disabled?
 * @var   string   $group           Group the field belongs to. <fields> section in form XML.
 * @var   boolean  $hidden          Is this field hidden in the form?
 * @var   string   $hint            Placeholder for the field.
 * @var   string   $id              DOM id of the field.
 * @var   string   $label           Label of the field.
 * @var   string   $labelclass      Classes to apply to the label.
 * @var   boolean  $multiple        Does this field support multiple values?
 * @var   string   $name            Name of the input field.
 * @var   string   $onchange        Onchange attribute for the field.
 * @var   string   $onclick         Onclick attribute for the field.
 * @var   string   $pattern         Pattern (Reg Ex) of value of the form field.
 * @var   boolean  $readonly        Is this field read only?
 * @var   boolean  $repeat          Allows extensions to duplicate elements.
 * @var   boolean  $required        Is this field required?
 * @var   integer  $size            Size attribute of the input.
 * @var   boolean  $spellcheck      Spellcheck state for the form field.
 * @var   string   $validate        Validation rules to apply.
 * @var   string   $value           Value attribute of the field.
 * @var   string   $dataAttribute   Miscellaneous data attributes preprocessed for HTML output
 * @var   array    $dataAttributes  Miscellaneous data attribute for eg, data-*
 * @var   string   $valueTitle
 * @var   array    $canDo
 * @var   string[] $urls
 * @var   string[] $modalTitles
 * @var   string[] $buttonIcons
 */

// Add the field script
if (!$readonly && !$disabled) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
    $wa->registerAndUseScript(
        'lib_j2commerce.modal-multiselect-layout',
        'lib_j2commerce/modal-multiselect-layout.min.js',
        [],
        ['type' => 'module'],
        ['core', 'joomla.dialog']
    );
}
?>
<div data-item-multi-field="<?php echo $id; ?>" class="js-modal-content-multiselect-field <?php echo $class; ?>" <?php echo $dataAttribute; ?>>
	<input type="hidden" value="<?php echo is_array($value) ? implode(',', $value) : ''; ?>" id="<?php echo $id; ?>_main" name="<?php echo $name; ?>" />
    <?php if (!$readonly && !$disabled) :
        echo $this->sublayout('buttons', $displayData);
        // The "extra-buttons" layout allows to add extra control buttons to the field, example "propagate association" by com_content
        echo $this->sublayout('extra-buttons', $displayData);
    endif; ?>
</div>
