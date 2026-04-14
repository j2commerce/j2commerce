<?php
/**
 * @package     J2Commerce Library
 * @subpackage  Layout
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

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

// Prepare options for each Modal
$modalSelect = [
    'popupType'  => 'iframe',
    'src'        => empty($urls['select']) ? '' : Route::_($urls['select'], false),
    'textHeader' => Text::_($modalTitles['select'] ?? 'JSELECT'),
];

// Decide when the select button always will be visible
$isSelectAlways = true; //!empty($canDo['select']) && empty($canDo['clear']);

?>
<?php if ($modalSelect['src'] && $canDo['select'] ?? true) : ?>
<button type="button" class="btn btn-primary" <?php echo $value && !$isSelectAlways ? 'hidden' : ''; ?>
        data-button-action="select" <?php echo !$isSelectAlways ? 'data-show-when-value=""' : ''; ?>
        data-modal-config="<?php echo $this->escape(json_encode($modalSelect, JSON_UNESCAPED_SLASHES)); ?>">
    <span class="me-1 <?php echo !empty($buttonIcons['select']) ? $buttonIcons['select'] : 'icon-file'; ?>" aria-hidden="true"></span> <?php echo Text::_($modalTitles['select'] ?? 'JSELECT'); ?>
</button>
<?php endif; ?>
