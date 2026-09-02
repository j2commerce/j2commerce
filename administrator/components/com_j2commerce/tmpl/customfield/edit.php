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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
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
                        <?php // Multiuploader options — inline, controlled by showon in XML ?>
                        <?php echo $this->form->renderField('upload_max_files'); ?>
                        <?php echo $this->form->renderField('upload_max_file_size'); ?>
                        <?php echo $this->form->renderField('upload_allowed_types'); ?>
                        <?php // Text sanitising options — inline, controlled by showon in XML ?>
                        <?php echo $this->form->renderField('field_strip_special_chars'); ?>
                        <?php echo $this->form->renderField('field_strip_chars'); ?>
                        <?php echo $this->form->renderField('field_max_length'); ?>
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
                        <p class="text-body-secondary small"><?php echo Text::_('COM_J2COMMERCE_FIELD_PREVIEW_DESC'); ?></p>
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
                        <?php echo $this->form->renderField('phone_country_mode'); ?>
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
                        <?php
                        // Render plugin-injected display area switchers dynamically
                        foreach ($this->form->getFieldset('display') as $field) {
                            if (strncmp($field->fieldname, 'plugin_area_', 12) === 0) {
                                echo $field->renderField();
                            }
                        }
                        ?>
                        <?php echo $this->form->renderField('field_collapse_toggle'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php
        // Render plugin-injected fieldsets as additional tabs
        $coreFieldsets = ['basic', 'telephone_countries', 'display'];
        foreach ($this->form->getFieldsets() as $fieldset) {
            if (\in_array($fieldset->name, $coreFieldsets, true)) {
                continue;
            }
            echo HTMLHelper::_('uitab.addTab', 'myTab', $fieldset->name, Text::_($fieldset->label ?? $fieldset->name));
            echo '<div class="row"><div class="col-lg-9">';
            echo '<fieldset class="options-form"><div class="form-grid">';
            foreach ($this->form->getFieldset($fieldset->name) as $field) {
                echo $field->renderField();
            }
            echo '</div></fieldset>';
            echo '</div></div>';
            echo HTMLHelper::_('uitab.endTab');
        }
        ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php if ($return = Factory::getApplication()->getInput()->get('return', '', 'base64')) : ?>
        <input type="hidden" name="return" value="<?php echo htmlspecialchars($return, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php echo $this->form->renderField('j2commerce_customfield_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const translations = <?php echo json_encode($jsTranslations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    const preview = document.getElementById('customfield-preview-content');
    const form = document.getElementById('customfield-form');

    function t(key) {
        if (!key) return '';
        return translations[key] ?? key;
    }

    function createEl(tag, attrs, children) {
        const node = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function(name) {
            const value = attrs[name];
            if (value === null || value === undefined || value === false) {
                return;
            }
            node.setAttribute(name, value === true ? '' : value);
        });

        (children || []).forEach(function(child) {
            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        });

        return node;
    }

    function labelNode(text, isRequired) {
        const node = createEl('label', { class: 'form-label' }, [text]);

        if (isRequired) {
            node.appendChild(document.createTextNode(' '));
            node.appendChild(createEl('span', { class: 'text-danger' }, ['*']));
        }

        return node;
    }

    function noteNode(text) {
        return createEl('p', { class: 'text-body-secondary small fst-italic mb-0' }, [text]);
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
                return sel.options[sel.selectedIndex].text;
            }
            return '';
        })();

        const label = t(fieldName) || fieldName;
        const placeholderText = t(placeholder) || placeholder;

        // CustomFieldHelper::getMaxLength() applies the ceiling to these three types only.
        const maxLength = ['text', 'textarea', 'email'].includes(fieldType)
            ? Math.max(0, parseInt(form.querySelector('#jform_field_max_length')?.value || '0', 10) || 0)
            : 0;

        const inputAttrs = {
            class: 'form-control',
            disabled: true,
            placeholder: placeholderText || null,
            autocomplete: autocomplete || null,
            maxlength: maxLength > 0 ? String(maxLength) : null
        };

        const nodes = [];

        switch (fieldType) {
            case 'text':
            case 'email':
                nodes.push(labelNode(label, isRequired));
                nodes.push(createEl('input', Object.assign({}, inputAttrs, { type: fieldType, value: fieldDefault })));
                break;

            case 'textarea':
                nodes.push(labelNode(label, isRequired));
                nodes.push(createEl('textarea', Object.assign({}, inputAttrs, { rows: '3' }), [fieldDefault]));
                break;

            case 'date':
            case 'time':
            case 'datetime': {
                nodes.push(labelNode(label, isRequired));
                nodes.push(createEl('input', {
                    type: fieldType === 'datetime' ? 'datetime-local' : fieldType,
                    class: 'form-control',
                    disabled: true,
                    value: fieldDefault,
                    placeholder: placeholderText || null
                }));
                break;
            }

            case 'singledropdown': {
                const select = createEl('select', { class: 'form-select', disabled: true }, [
                    createEl('option', { value: '' }, [<?php echo json_encode(Text::_('COM_J2COMMERCE_SELECT_OPTION'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>])
                ]);
                getSubformOptions().forEach(function(o) {
                    select.appendChild(createEl('option', { value: o.value }, [o.name]));
                });
                nodes.push(labelNode(label, isRequired));
                nodes.push(select);
                break;
            }

            case 'radio':
            case 'checkbox': {
                const opts = getSubformOptions();
                nodes.push(labelNode(label, isRequired));
                opts.forEach(function(o, i) {
                    const id = 'preview_' + fieldType + '_' + i;
                    nodes.push(createEl('div', { class: 'form-check' }, [
                        createEl('input', {
                            class: 'form-check-input',
                            type: fieldType,
                            disabled: true,
                            name: fieldType === 'radio' ? 'preview_radio' : null,
                            id: id,
                            value: o.value
                        }),
                        createEl('label', { class: 'form-check-label', for: id }, [o.name])
                    ]));
                });
                if (!opts.length) {
                    nodes.push(noteNode(<?php echo json_encode(Text::_('COM_J2COMMERCE_FIELD_PREVIEW_ADD_OPTIONS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>));
                }
                break;
            }

            case 'zone': {
                const zonePlaceholder = zoneType === 'zone'
                    ? <?php echo json_encode(Text::_('COM_J2COMMERCE_SELECT_ZONE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
                    : <?php echo json_encode(Text::_('COM_J2COMMERCE_SELECT_COUNTRY'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
                const select = createEl('select', { class: 'form-select pe-none', tabindex: '-1', 'aria-disabled': 'true' });

                if (zoneDefaultText) {
                    select.appendChild(createEl('option', { value: '', disabled: true }, [zonePlaceholder]));
                    select.appendChild(createEl('option', { selected: true }, [zoneDefaultText]));
                } else {
                    select.appendChild(createEl('option', { selected: true }, [zonePlaceholder]));
                }

                nodes.push(labelNode(label, isRequired));
                nodes.push(select);
                break;
            }

            case 'wysiwyg':
                nodes.push(labelNode(label, isRequired));
                nodes.push(createEl('textarea', {
                    class: 'form-control',
                    rows: '4',
                    disabled: true,
                    placeholder: placeholderText || null
                }, [fieldDefault]));
                nodes.push(noteNode(<?php echo json_encode(Text::_('COM_J2COMMERCE_FIELD_PREVIEW_WYSIWYG'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>));
                break;

            case 'customtext':
                nodes.push(createEl('div', { class: 'form-text' }, [label]));
                break;

            default:
                nodes.push(labelNode(label, isRequired));
                nodes.push(createEl('input', Object.assign({}, inputAttrs, { type: 'text', value: fieldDefault })));
        }

        if (fieldWidth) {
            nodes.push(createEl('div', { class: 'mt-2' }, [
                createEl('span', { class: <?php echo json_encode(J2htmlHelper::badgeClass('badge text-bg-secondary'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?> }, [fieldWidth])
            ]));
        }

        preview.replaceChildren(...nodes);
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
     'jform_field_default_country', 'jform_field_default_zone', 'jform_field_width',
     'jform_field_strip_special_chars', 'jform_field_strip_chars',
     'jform_field_max_length'].forEach(function(id) {
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

    // A required field cannot be collapsed behind an "Add" link: when "required"
    // is set to Yes, force the collapse toggle to No and disable it.
    function syncCollapseToggle() {
        const requiredYes = form.querySelector('#jform_field_required1');
        const isRequired = requiredYes ? requiredYes.checked : false;
        const collapseInputs = form.querySelectorAll('[id^="jform_field_collapse_toggle"]');

        collapseInputs.forEach(function(input) {
            input.disabled = isRequired;
            if (isRequired && input.id === 'jform_field_collapse_toggle1') {
                input.checked = false;
            }
            if (isRequired && input.id === 'jform_field_collapse_toggle0') {
                input.checked = true;
            }
        });
    }

    form.querySelectorAll('[id^="jform_field_required"]').forEach(function(el) {
        el.addEventListener('change', syncCollapseToggle);
    });
    syncCollapseToggle();

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
