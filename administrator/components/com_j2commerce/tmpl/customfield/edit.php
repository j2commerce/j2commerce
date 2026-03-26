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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customfield\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

// Build a JS translation map for known language string keys used in field_name and field_placeholder
$lang = $this->getLanguage();
$lang->load('com_j2commerce', JPATH_SITE);
$translationKeys = [
    'J2COMMERCE_ADDRESS_FIRSTNAME', 'J2COMMERCE_ADDRESS_LASTNAME', 'J2COMMERCE_EMAIL',
    'J2COMMERCE_ADDRESS_LINE1', 'J2COMMERCE_ADDRESS_LINE2', 'J2COMMERCE_ADDRESS_CITY',
    'J2COMMERCE_ADDRESS_ZIP', 'J2COMMERCE_ADDRESS_PHONE', 'J2COMMERCE_ADDRESS_MOBILE',
    'J2COMMERCE_ADDRESS_COMPANY_NAME', 'J2COMMERCE_ADDRESS_TAX_NUMBER',
    'J2COMMERCE_ADDRESS_COUNTRY', 'J2COMMERCE_ADDRESS_ZONE',
    'J2COMMERCE_PLACEHOLDER_FIRSTNAME', 'J2COMMERCE_PLACEHOLDER_LASTNAME',
    'J2COMMERCE_PLACEHOLDER_EMAIL', 'J2COMMERCE_PLACEHOLDER_ADDRESS_1',
    'J2COMMERCE_PLACEHOLDER_ADDRESS_2', 'J2COMMERCE_PLACEHOLDER_CITY',
    'J2COMMERCE_PLACEHOLDER_ZIP', 'J2COMMERCE_PLACEHOLDER_PHONE',
    'J2COMMERCE_PLACEHOLDER_MOBILE', 'J2COMMERCE_PLACEHOLDER_COMPANY',
    'J2COMMERCE_PLACEHOLDER_TAX_NUMBER',
];
$jsTranslations = [];
foreach ($translationKeys as $key) {
    $jsTranslations[$key] = Text::_($key);
}

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_customfield_id); ?>" method="post" name="adminForm" id="customfield-form" class="form-validate">

    <div class="row title-alias form-vertical mb-3">
        <div class="col-12 col-lg-6">
            <?php echo $this->form->renderField('field_name'); ?>
        </div>
        <div class="col-12 col-lg-6">
            <?php echo $this->form->renderField('field_namekey'); ?>
        </div>
    </div>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_BASIC')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-basic" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_BASIC'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('field_table'); ?>
                        <?php echo $this->form->renderField('field_type'); ?>
                        <?php echo $this->form->renderField('field_core'); ?>
                        <?php echo $this->form->renderField('field_required'); ?>
                        <?php echo $this->form->renderField('field_placeholder'); ?>
                        <?php echo $this->form->renderField('field_autocomplete'); ?>
                        <?php echo $this->form->renderField('field_access'); ?>
                        <?php //echo $this->form->renderField('field_frontend'); ?>
                        <?php //echo $this->form->renderField('field_backend'); ?>
                        <?php echo $this->form->renderField('field_value'); ?>
                        <?php echo $this->form->renderField('field_zonetype'); ?>
                        <?php echo $this->form->renderField('field_default'); ?>
                        <?php echo $this->form->renderField('field_default_country'); ?>
                        <?php echo $this->form->renderField('field_default_zone'); ?>
                        <?php echo $this->form->renderField('field_width'); ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>

                <div class="card mt-3 border">
                    <div class="card-header">
                        <?php echo Text::_('COM_J2COMMERCE_FIELD_PREVIEW'); ?>
                    </div>
                    <div class="card-body pt-0" id="customfield-preview">
                        <p class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_FIELD_PREVIEW_DESC'); ?></p>
                        <div id="customfield-preview-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'telephone_countries', Text::_('COM_J2COMMERCE_FIELDSET_TELEPHONE_COUNTRIES')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-telephone-countries" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_TELEPHONE_COUNTRIES'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('phone_all_countries'); ?>
                        <div class="small text-muted mb-2" data-showon='[{"field":"jform[phone_all_countries]","values":["0"],"sign":"=","op":""}]'><?php echo Text::_('COM_J2COMMERCE_FIELD_PHONE_COUNTRIES_DESC'); ?></div>
                        <?php echo $this->form->renderField('phone_countries'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'display', Text::_('COM_J2COMMERCE_FIELDSET_DISPLAY_SETTINGS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-display" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_DISPLAY_SETTINGS'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('field_display_billing'); ?>
                        <?php echo $this->form->renderField('field_display_shipping'); ?>
                        <?php echo $this->form->renderField('field_display_payment'); ?>
                        <?php echo $this->form->renderField('field_display_register'); ?>
                        <?php echo $this->form->renderField('field_display_guest'); ?>
                        <?php echo $this->form->renderField('field_display_guest_shipping'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_customfield_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const translations = <?php echo json_encode($jsTranslations, JSON_UNESCAPED_UNICODE); ?>;
    const preview = document.getElementById('customfield-preview-content');
    const form = document.getElementById('customfield-form');

    function t(key) {
        if (!key) return '';
        return translations[key] ?? key;
    }

    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function getSubformOptions() {
        const rows = form.querySelectorAll('[data-group="field_value"] tr.subform-repeatable-group, [id*="field_value"] .subform-repeatable-group');
        const options = [];
        rows.forEach(function(row) {
            const nameInput = row.querySelector('input[id*="name"]');
            const valueInput = row.querySelector('input[id*="value"]');
            if (nameInput && nameInput.value.trim()) {
                options.push({ name: nameInput.value.trim(), value: valueInput ? valueInput.value.trim() : '' });
            }
        });
        return options;
    }

    function updatePreview() {
        const fieldName = form.querySelector('#jform_field_name')?.value || '';
        const fieldType = form.querySelector('#jform_field_type')?.value || 'text';
        const placeholder = form.querySelector('#jform_field_placeholder')?.value || '';
        const autocomplete = form.querySelector('#jform_field_autocomplete')?.value || '';
        const required = form.querySelector('#jform_field_required input[type="radio"]:checked, #jform_field_required0:checked, #jform_field_required1:checked');
        const isRequired = required ? required.value === '1' : false;
        const fieldDefault = form.querySelector('#jform_field_default')?.value || '';
        const fieldWidth = form.querySelector('#jform_field_width')?.value || 'col-md-6';
        const zoneType = form.querySelector('#jform_field_zonetype')?.value || 'country';

        // Read the selected country/zone default for zone field type preview
        const countrySelect = form.querySelector('#jform_field_default_country');
        const zoneSelect = form.querySelector('#jform_field_default_zone');
        const zoneDefaultText = (function() {
            const sel = zoneType === 'zone' ? zoneSelect : countrySelect;
            if (sel && sel.selectedIndex >= 0 && sel.value) {
                return esc(sel.options[sel.selectedIndex].text);
            }
            return '';
        })();

        const label = esc(t(fieldName) || fieldName);
        const placeholderText = esc(t(placeholder) || placeholder);
        const requiredMark = isRequired ? ' <span class="text-danger">*</span>' : '';
        const autocompleteAttr = autocomplete ? ' autocomplete="' + esc(autocomplete) + '"' : '';
        const placeholderAttr = placeholderText ? ' placeholder="' + placeholderText + '"' : '';
        const requiredAttr = isRequired ? ' required' : '';
        const defaultValue = esc(fieldDefault);

        let html = '';

        switch (fieldType) {
            case 'text':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="text" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + autocompleteAttr + '>';
                break;

            case 'email':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="email" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + autocompleteAttr + '>';
                break;

            case 'textarea':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<textarea class="form-control" rows="3" disabled'
                     + placeholderAttr + autocompleteAttr + '>' + defaultValue + '</textarea>';
                break;

            case 'date':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="date" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + '>';
                break;

            case 'time':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="time" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + '>';
                break;

            case 'datetime':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="datetime-local" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + '>';
                break;

            case 'singledropdown': {
                const opts = getSubformOptions();
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<select class="form-select" disabled>'
                     + '<option value=""><?php echo $this->escape(Text::_('COM_J2COMMERCE_SELECT_OPTION')); ?></option>';
                opts.forEach(function(o) {
                    html += '<option value="' + esc(o.value) + '">' + esc(o.name) + '</option>';
                });
                html += '</select>';
                break;
            }

            case 'radio': {
                const opts = getSubformOptions();
                html = '<label class="form-label">' + label + requiredMark + '</label>';
                opts.forEach(function(o, i) {
                    html += '<div class="form-check">'
                         + '<input class="form-check-input" type="radio" disabled name="preview_radio" id="preview_radio_' + i + '" value="' + esc(o.value) + '">'
                         + '<label class="form-check-label" for="preview_radio_' + i + '">' + esc(o.name) + '</label>'
                         + '</div>';
                });
                if (!opts.length) html += '<p class="text-muted small fst-italic mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_FIELD_PREVIEW_ADD_OPTIONS')); ?></p>';
                break;
            }

            case 'checkbox': {
                const opts = getSubformOptions();
                html = '<label class="form-label">' + label + requiredMark + '</label>';
                opts.forEach(function(o, i) {
                    html += '<div class="form-check">'
                         + '<input class="form-check-input" type="checkbox" disabled id="preview_check_' + i + '" value="' + esc(o.value) + '">'
                         + '<label class="form-check-label" for="preview_check_' + i + '">' + esc(o.name) + '</label>'
                         + '</div>';
                });
                if (!opts.length) html += '<p class="text-muted small fst-italic mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_FIELD_PREVIEW_ADD_OPTIONS')); ?></p>';
                break;
            }

            case 'zone': {
                const zonePlaceholder = zoneType === 'zone'
                    ? '<?php echo $this->escape(Text::_('COM_J2COMMERCE_SELECT_ZONE')); ?>'
                    : '<?php echo $this->escape(Text::_('COM_J2COMMERCE_SELECT_COUNTRY')); ?>';
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<select class="form-select pe-none" tabindex="-1" aria-disabled="true">';
                if (zoneDefaultText) {
                    html += '<option value="" disabled>' + zonePlaceholder + '</option>'
                         + '<option selected>' + zoneDefaultText + '</option>';
                } else {
                    html += '<option selected>' + zonePlaceholder + '</option>';
                }
                html += '</select>';
                break;
            }

            case 'wysiwyg':
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<textarea class="form-control" rows="4" disabled' + placeholderAttr + '>' + defaultValue + '</textarea>'
                     + '<p class="text-muted small fst-italic mb-0"><?php echo $this->escape(Text::_('COM_J2COMMERCE_FIELD_PREVIEW_WYSIWYG')); ?></p>';
                break;

            case 'customtext':
                html = '<div class="form-text">' + label + '</div>';
                break;

            default:
                html = '<label class="form-label">' + label + requiredMark + '</label>'
                     + '<input type="text" class="form-control" disabled value="' + defaultValue + '"'
                     + placeholderAttr + autocompleteAttr + '>';
        }

        if (fieldWidth) {
            html += '<div class="mt-2"><span class="badge text-bg-secondary">' + esc(fieldWidth) + '</span></div>';
        }
        preview.innerHTML = html;
    }

    // Show/hide the Countries tab based on field_type
    // Joomla 6 tab structure: <div role="tablist"><button aria-controls="telephone_countries" role="tab">...</button></div>
    function updateCountriesTab() {
        const fieldType = form.querySelector('#jform_field_type')?.value || '';
        const show = fieldType === 'telephone';
        const tabBtn = document.querySelector('div[role="tablist"] > button[aria-controls="telephone_countries"]');
        if (tabBtn) {
            tabBtn.style.display = show ? '' : 'none';
        }
        const tabPane = document.getElementById('telephone_countries');
        if (tabPane) {
            tabPane.style.display = show ? '' : 'none';
        }
    }

    // Listen to changes on all relevant form fields
    ['jform_field_name', 'jform_field_type', 'jform_field_placeholder',
     'jform_field_autocomplete', 'jform_field_default', 'jform_field_zonetype',
     'jform_field_default_country', 'jform_field_default_zone', 'jform_field_width'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreview);
        if (el) el.addEventListener('change', updatePreview);
    });

    const fieldTypeEl = document.getElementById('jform_field_type');
    if (fieldTypeEl) {
        fieldTypeEl.addEventListener('change', updateCountriesTab);
    }

    // Required field uses radio buttons
    form.querySelectorAll('[id^="jform_field_required"]').forEach(function(el) {
        el.addEventListener('change', updatePreview);
    });

    // Subform option changes (use event delegation for dynamic rows)
    form.addEventListener('input', function(e) {
        if (e.target.closest('[data-group="field_value"], [id*="field_value"]')) {
            updatePreview();
        }
    });

    // Also listen for subform row additions/removals
    const observer = new MutationObserver(function(mutations) {
        for (const m of mutations) {
            if (m.target.closest?.('[id*="field_value"]')) {
                updatePreview();
                return;
            }
        }
    });
    const subformContainer = form.querySelector('[id*="field_value"]');
    if (subformContainer) {
        observer.observe(subformContainer, { childList: true, subtree: true });
    }

    // Initial render
    updatePreview();
    updateCountriesTab();
});
</script>
