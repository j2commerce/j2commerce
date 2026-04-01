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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;


extract($displayData);
$options       = $options ?? [];
$disabled      = $disabled ?? false;
$readonly      = $readonly ?? false;
$dataAttribute = $dataAttribute ?? '';
$hint          = $hint ?? '';
$onchange      = $onchange ?? '';
$required      = $required ?? false;
$autofocus     = $autofocus ?? false;
$spellcheck    = $spellcheck ?? false;
$dirname       = $dirname ?? '';
$value         = $value ?? '';

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
 * @var   array    $checkedOptions  Options that will be set as checked.
 * @var   boolean  $hasValue        Has this field a value assigned?
 * @var   array    $options         Options available for this field.
 * @var   array    $inputType       Options available for this field.
 * @var   string   $accept          File types that are accepted.
 * @var   string   $dataAttribute   Miscellaneous data attributes preprocessed for HTML output
 * @var   array    $dataAttributes  Miscellaneous data attribute for eg, data-*.
 * @var   string   $dirname         The directory name
 * @var   string   $addonBefore     The text to use in a bootstrap input group prepend
 * @var   string   $addonAfter      The text to use in a bootstrap input group append

 */

$list = '';

if ($options) {
    $list = 'list="' . $id . '_datalist"';
}

$currencyHelper  = new J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper();
$currencySymbol  = $currencyHelper->getSymbol();
$currencyDecimals = CurrencyHelper::getDecimalPlace();
$value = number_format((float) ($value ?? 0), $currencyDecimals, '.', '');


$attributes = [
    !empty($class) ? 'class="form-control ' . $class . '"' : 'class="form-control"',
    !empty($size) ? 'size="' . $size . '"' : '',
    !empty($description) ? 'aria-describedby="' . ($id ?: $name) . '-desc"' : '',
    $disabled ? 'disabled' : '',
    $readonly ? 'readonly' : '',
    $dataAttribute,
    $list,
    strlen($hint) ? 'placeholder="' . htmlspecialchars($hint, ENT_COMPAT, 'UTF-8') . '"' : '',
    $onchange ? 'onchange="' . $onchange . '"' : '',
    !empty($maxLength) ? $maxLength : '',
    $required ? 'required' : '',
    !empty($autocomplete) ? 'autocomplete="' . $autocomplete . '"' : '',
    $autofocus ? 'autofocus' : '',
    $spellcheck ? '' : 'spellcheck="false"',
    !empty($inputmode) ? $inputmode : '',
    !empty($pattern) ? 'pattern="' . $pattern . '"' : '',


    !empty($validationtext) ? 'data-validation-text="' . $this->escape(Text::_($validationtext)) . '"' : '',
];

$addonBeforeHtml = '<span class="input-group-text">'.$currencySymbol.'</span>';

?>


<div class="input-group">
    <?php echo $addonBeforeHtml; ?>

    <input
        type="text"
        name="<?php echo $name; ?>"
        id="<?php echo $id; ?>"
        value="<?php echo htmlspecialchars($value, ENT_COMPAT, 'UTF-8'); ?>"
        <?php echo $dirname; ?>
        <?php echo implode(' ', $attributes); ?>>

</div>


