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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

// Adopts the option rows and variant block this view fetches. Deferred, so it has run before any fetch callback.
Factory::getApplication()->getDocument()->getWebAssetManager()
    ->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true]);

// Extract display data - MUST set $item BEFORE using it
$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

// Now we can safely use $item->product_type
$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

// Options already on the product are offered as disabled entries rather than omitted,
// so the reason they cannot be picked again is visible in the list itself.
$assignedOptionIds = array_flip(array_map(
    static fn($poption): int => (int) $poption->option_id,
    (array) ($item->product_options ?? [])
));

// Initialize key counter for options
$key = 0;

?>

<div class="j2commerce-product-variants">
    <fieldset id="j2commerce-flexivariable-options" class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_OPTIONS');?></legend>
        <?php if (empty($productOptionList)) : ?>
            <p class="alert alert-warning">
                <span class="me-3"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_NO_OPTION_MESSAGE')?></span>
            </p>
            <div>
                <a href="index.php?option=com_j2commerce&view=options" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_CREATE')?></a>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table id="flexivariable_options_table" class="table itemList align-middle j2commerce">
                    <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_NAME');?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING');?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE');?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if(isset($item->product_options) && !empty($item->product_options)):?>
                        <?php foreach($item->product_options as $poption):?>
                            <tr id="pao_flexivar_option_<?php echo $poption->j2commerce_productoption_id;?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <strong><?php echo $this->escape($poption->option_name);?></strong>
                                        <input type="hidden" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id.'][j2commerce_productoption_id]';?>" value="<?php echo $poption->j2commerce_productoption_id;?>">
                                        <input type="hidden" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id.'][option_id]';?>" value="<?php echo $poption->option_id;?>">
                                        <small class="ms-1">(<?php echo $this->escape($poption->option_unique_name);?>)</small>
                                    </div>
                                    <div>
                                        <small class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_OPTION_TYPE');?>: <?php echo $poption->option_type ?? '';?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id.'][ordering]','id' => 'flexivar_ordering_'.$poption->j2commerce_productoption_id,'value' => $poption->ordering ?? '','class' => 'form-control',] + $textFieldDefaults);?>
                                </td>
                                <td class="text-end">
                                    <span class="optionRemove btn btn-danger btn-sm"
                                          data-option-id="<?php echo $poption->j2commerce_productoption_id;?>"
                                          role="button" title="<?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE');?>">
                                        <span class="icon icon-trash"></span>
                                    </span>
                                </td>
                            </tr>
                            <?php $key++;?>
                        <?php endforeach;?>
                    <?php endif;?>
                    <tr class="j2commerce_a_options">
                        <td colspan="4">
                            <div class="control-group align-items-center mt-4">
                                <div class="control-label">
                                    <label for="j2commerce_flexivar_option_select"><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_ADD_VARIANT_OPTION');?></label>
                                </div>
                                <div class="controls">
                                    <div class="input-group">
                                        <select name="option_select_id" id="j2commerce_flexivar_option_select" class="form-select">
                                            <?php foreach ($productOptionList as $option_list):
                                                $optionLabel = $option_list->option_name . ' (' . $option_list->option_unique_name . ')';
                                                $optionAssigned = isset($assignedOptionIds[(int) $option_list->j2commerce_option_id]);
                                                ?>
                                                <option value="<?php echo $option_list->j2commerce_option_id?>" data-option-label="<?php echo $this->escape($optionLabel);?>"<?php echo $optionAssigned ? ' disabled' : '';?>><?php echo $this->escape($optionAssigned ? Text::sprintf('COM_J2COMMERCE_OPTION_LABEL_ALREADY_ADDED', $optionLabel) : $optionLabel);?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="j2commerce_flexivar_add_option_btn" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD')?></button>
                                    </div>
                                    <div id="j2commerce-flexivar-option-notice" class="alert alert-warning mt-2 d-none" role="alert"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif;?>

        <!-- Hidden field to track deleted option IDs for persistence on save -->
        <input type="hidden" name="<?php echo $formPrefix; ?>[deleted_options]" id="j2commerce-flexivar-deleted-options" value="">

    </fieldset>
    <div class="alert alert-info d-flex align-items-center my-3" role="alert">
        <span class="fas fa-solid fa-exclamation-circle me-3" aria-hidden="true"></span>
        <div><?php echo Text::_('COM_J2COMMERCE_PRODUCT_FLEXIVARIANT_GENERATION_HELP_TEXT'); ?></div>
    </div>

    <?php
    // Show "Create Variants" button if options exist in the table but variant_add_block has no dropdowns yet
    $hasDbOptions = !empty($item->product_options);
    ?>
    <button type="button"
            id="j2commerce-create-variants-btn"
            class="btn btn-success mb-3<?php echo $hasDbOptions ? ' d-none' : ' d-none'; ?>"
            style="display: none;">
        <span class="fas fa-solid fa-cogs me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CREATE_VARIANTS'); ?>
    </button>
</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Decorative spinner with the label visible beside it.
    function setSpinnerLabel(el, label, spinnerClass = 'spinner-border spinner-border-sm') {
        const spinner = document.createElement('span');
        spinner.className = spinnerClass;
        spinner.setAttribute('aria-hidden', 'true');
        el.replaceChildren(spinner);
        el.append(' ' + label);
    }

    const formPrefix = '<?php echo $formPrefix; ?>';
    let optionKey = <?php echo $key; ?>;
    const createVariantsBtn = document.getElementById('j2commerce-create-variants-btn');
    const productId = <?php echo (int) ($item->j2commerce_product_id ?? 0); ?>;
    const optionSelect = document.getElementById('j2commerce_flexivar_option_select');
    const optionNotice = document.getElementById('j2commerce-flexivar-option-notice');
    const addOptionBtn = document.getElementById('j2commerce_flexivar_add_option_btn');
    const addedLabelFormat = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_LABEL_ALREADY_ADDED')); ?>;

    // Feedback belongs beside the control that produced it — the options block sits far
    // enough down the form that the page-top message container is off screen.
    function showOptionNotice(message) {
        if (!optionNotice) return;
        optionNotice.textContent = message;
        optionNotice.classList.remove('d-none');
    }

    function clearOptionNotice() {
        if (!optionNotice) return;
        optionNotice.textContent = '';
        optionNotice.classList.add('d-none');
    }

    // The table is the single source of truth for what is on the product, so the dropdown
    // is derived from it after every change rather than toggled entry by entry.
    function syncOptionSelect() {
        if (!optionSelect) return;

        const usedIds = new Set(
            Array.from(document.querySelectorAll('#flexivariable_options_table input[name$="[option_id]"]'))
                .map(input => input.value)
        );

        Array.from(optionSelect.options).forEach(option => {
            const label = option.dataset.optionLabel || option.textContent;
            const isUsed = usedIds.has(option.value);
            option.disabled = isUsed;
            // Function replacement so a '$' in an option name is not read as a substitution.
            option.textContent = isUsed ? addedLabelFormat.replace('%s', () => label) : label;
        });

        const firstAvailable = Array.from(optionSelect.options).find(option => !option.disabled);
        if (firstAvailable && (selectedOptionEl() === null || selectedOptionEl().disabled)) {
            optionSelect.value = firstAvailable.value;
        }
        if (addOptionBtn) {
            addOptionBtn.disabled = !firstAvailable;
        }
    }

    function selectedOptionEl() {
        return optionSelect && optionSelect.selectedIndex >= 0
            ? optionSelect.options[optionSelect.selectedIndex]
            : null;
    }

    function updateCreateVariantsBtnVisibility() {
        if (!createVariantsBtn) return;
        // Show button when there are option rows in the table (excluding the "add options" row)
        // AND the variant_add_block has no dropdowns (options not yet saved to DB)
        const optionRows = document.querySelectorAll('#flexivariable_options_table tbody tr:not(.j2commerce_a_options)');
        const variantAddBlock = document.getElementById('variant_add_block');
        const hasDropdowns = variantAddBlock && variantAddBlock.querySelector('select[name^="variant_combin"]');

        if (optionRows.length > 0 && !hasDropdowns) {
            createVariantsBtn.style.display = '';
            createVariantsBtn.classList.remove('d-none');
        } else {
            createVariantsBtn.style.display = 'none';
        }
    }

    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', function() {
            if (!optionSelect) return;

            const optionValue = optionSelect.value;
            const selectedOption = selectedOptionEl();
            const optionName = selectedOption ? (selectedOption.dataset.optionLabel || selectedOption.textContent) : '';

            // Rows are keyed by record id when rendered by the server and by a counter when
            // added here, so the option is matched on the value it carries rather than the key.
            const alreadyAdded = Array.from(
                document.querySelectorAll('#flexivariable_options_table input[name$="[option_id]"]')
            ).some(input => input.value === optionValue);
            if (alreadyAdded) {
                showOptionNotice(<?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_ALREADY_ADDED')); ?>);
                syncOptionSelect();
                return;
            }

            clearOptionNotice();

            // Create new table row
            const newRow = document.createElement('tr');
            newRow.id = 'j2commerce-flexivar-op-tr-' + optionKey;
            // The field names carry this row into the product save, so they are built from
            // one place rather than repeated per input. The key is prefixed because the
            // server-rendered rows key this same array by record id, and a bare counter can
            // land on one of those ids — whichever row posts last then wins and the other is
            // dropped. The save reads each row's j2commerce_productoption_id, never the key.
            const fieldName = (suffix) => formPrefix + '[item_options][new_' + optionKey + '][' + suffix + ']';

            const nameCell = document.createElement('td');
            nameCell.className = 'addedOption';
            nameCell.textContent = optionName;

            const orderingCell = document.createElement('td');
            const orderingInput = document.createElement('input');
            orderingInput.className = 'form-control';
            orderingInput.name = fieldName('ordering');
            orderingInput.value = '0';
            orderingCell.append(orderingInput);

            const actionsCell = document.createElement('td');
            actionsCell.className = 'text-end';

            const removeButton = document.createElement('span');
            removeButton.className = 'optionRemove btn btn-danger btn-sm';
            removeButton.setAttribute('role', 'button');
            removeButton.title = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_REMOVE')); ?>;

            const removeIcon = document.createElement('span');
            removeIcon.className = 'icon icon-trash';
            removeButton.append(removeIcon);

            const optionIdInput = document.createElement('input');
            optionIdInput.type = 'hidden';
            optionIdInput.name = fieldName('option_id');
            optionIdInput.value = optionValue;

            const productOptionIdInput = document.createElement('input');
            productOptionIdInput.type = 'hidden';
            productOptionIdInput.name = fieldName('j2commerce_productoption_id');
            productOptionIdInput.value = '';

            actionsCell.append(removeButton, optionIdInput, productOptionIdInput);

            newRow.append(nameCell, orderingCell, actionsCell);

            // Insert before the add options row
            const insertBeforeRow = document.querySelector('#flexivariable_options_table .j2commerce_a_options');
            if (insertBeforeRow) {
                insertBeforeRow.parentNode.insertBefore(newRow, insertBeforeRow);
            }

            optionKey++;
            syncOptionSelect();
            updateCreateVariantsBtnVisibility();
        });
    }

    // Remove option handler (event delegation for both existing and dynamically added rows)
    document.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('#j2commerce-flexivariable-options .optionRemove');
        if (!removeBtn) return;

        e.preventDefault();
        const row = removeBtn.closest('tr');
        if (row) {
            // Track deleted option ID for persistence on save
            const optionId = removeBtn.getAttribute('data-option-id');
            if (optionId) {
                const deletedField = document.getElementById('j2commerce-flexivar-deleted-options');
                if (deletedField) {
                    const currentValue = deletedField.value;
                    const deletedIds = currentValue ? currentValue.split(',') : [];
                    if (!isNaN(parseInt(optionId)) && parseInt(optionId) > 0 && !deletedIds.includes(optionId)) {
                        deletedIds.push(optionId);
                        deletedField.value = deletedIds.join(',');
                    }
                }
            }

            // Fade out effect
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(function() {
                row.remove();
                // Re-offer the option only once the row is really gone, so the dropdown and
                // the duplicate guard never disagree during the fade.
                clearOptionNotice();
                syncOptionSelect();
                updateCreateVariantsBtnVisibility();
            }, 300);
        }
    });

    // Create Variants button handler
    if (createVariantsBtn) {
        createVariantsBtn.addEventListener('click', async function() {
            if (productId <= 0) {
                Joomla.renderMessages({error: ['<?php echo Text::_('COM_J2COMMERCE_SAVE_ARTICLE_FIRST', true); ?>']});
                return;
            }

            // Collect option IDs from hidden inputs in the options table
            const optionInputs = document.querySelectorAll('#flexivariable_options_table input[name*="[option_id]"]');
            const options = [];
            optionInputs.forEach(function(input) {
                const optId = parseInt(input.value, 10);
                if (optId > 0) {
                    // Find corresponding ordering input
                    const row = input.closest('tr');
                    const orderingInput = row ? row.querySelector('input[name*="[ordering]"]') : null;
                    options.push({
                        option_id: optId,
                        ordering: orderingInput ? parseInt(orderingInput.value, 10) || 0 : 0
                    });
                }
            });

            if (options.length === 0) {
                Joomla.renderMessages({warning: ['<?php echo Text::_('COM_J2COMMERCE_INVALID_DATA', true); ?>']});
                return;
            }

            // Show loading state
            const origContent = [...createVariantsBtn.childNodes];
            createVariantsBtn.disabled = true;
            setSpinnerLabel(createVariantsBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING')); ?>, 'spinner-border spinner-border-sm me-1');

            try {
                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.saveProductOptionsAjax');
                formData.append('format', 'json');
                formData.append('product_id', productId.toString());
                formData.append('form_prefix', formPrefix);

                options.forEach(function(opt, i) {
                    formData.append('options[' + i + '][option_id]', opt.option_id.toString());
                    formData.append('options[' + i + '][ordering]', opt.ordering.toString());
                });

                const csrfToken = Joomla.getOptions('csrf.token');
                if (csrfToken) formData.append(csrfToken, '1');

                const response = await fetch('index.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error('Network response was not ok');

                const result = await response.json();

                if (result.success) {
                    // Replace option table rows with DB-backed rows (real PKs prevent duplicate inserts on save)
                    if (result.options_table_html) {
                        const tbody = document.querySelector('#flexivariable_options_table tbody');
                        if (tbody) {
                            // Remove all existing option rows (keep only the "add options" row)
                            const addRow = tbody.querySelector('.j2commerce_a_options');
                            tbody.querySelectorAll('tr:not(.j2commerce_a_options)').forEach(function(r) { r.remove(); });
                            // Insert new rows before the add row
                            if (addRow) {
                                addRow.before(J2CommerceDom.parse(result.options_table_html));
                            }
                            syncOptionSelect();
                        }
                    }

                    // Update the variant_add_block with the returned HTML
                    const variantAddBlock = document.getElementById('variant_add_block');
                    if (variantAddBlock && result.variant_add_block_html) {
                        J2CommerceDom.adopt(variantAddBlock, result.variant_add_block_html);
                    }

                    // Update J2CommerceVariants productId if needed
                    if (typeof window.J2CommerceVariants !== 'undefined') {
                        window.J2CommerceVariants.config.productId = productId;
                    }

                    // Hide the Create Variants button
                    createVariantsBtn.style.display = 'none';

                    Joomla.renderMessages({success: [result.message]});
                } else {
                    throw new Error(result.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR', true); ?>');
                }
            } catch (error) {
                console.error('Error saving product options:', error);
                Joomla.renderMessages({error: [error.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR', true); ?>']});
            } finally {
                createVariantsBtn.disabled = false;
                createVariantsBtn.replaceChildren(...origContent);
            }
        });
    }

    // Initial visibility check
    syncOptionSelect();
    updateCreateVariantsBtnVisibility();
});
</script>
