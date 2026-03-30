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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

// Extract display data - MUST set $item BEFORE using it
$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

// Now we can safely use $item->product_type
$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

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
                                            <?php foreach ($productOptionList as $option_list):?>
                                                <option value="<?php echo $option_list->j2commerce_option_id?>"><?php echo $this->escape($option_list->option_name) .' ('.$this->escape($option_list->option_unique_name).')';?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="j2commerce_flexivar_add_option_btn" class="btn btn-success"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD')?></button>
                                    </div>
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
        <span class="fas fa-solid fa-exclamation-circle me-3"></span>
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
        <span class="fas fa-solid fa-cogs me-1"></span><?php echo Text::_('COM_J2COMMERCE_CREATE_VARIANTS'); ?>
    </button>
</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const formPrefix = '<?php echo $formPrefix; ?>';
    let optionKey = <?php echo $key; ?>;
    const createVariantsBtn = document.getElementById('j2commerce-create-variants-btn');
    const productId = <?php echo (int) ($item->j2commerce_product_id ?? 0); ?>;

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

    // Add option button handler
    const addOptionBtn = document.getElementById('j2commerce_flexivar_add_option_btn');
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', function() {
            const selectEl = document.getElementById('j2commerce_flexivar_option_select');
            if (!selectEl) return;

            const optionValue = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const optionName = selectedOption ? selectedOption.textContent : '';

            // Check if option already added (prevent duplicates)
            const existingInput = document.querySelector('input[name="' + formPrefix + '[item_options][' + optionValue + '][option_id]"]');
            if (existingInput) {
                Joomla.renderMessages({warning: ['<?php echo Text::_('COM_J2COMMERCE_OPTION_ALREADY_ADDED', true); ?>']});
                return;
            }

            // Create new table row
            const newRow = document.createElement('tr');
            newRow.id = 'j2commerce-flexivar-op-tr-' + optionKey;
            newRow.innerHTML = `
                <td class="addedOption">${optionName}</td>
                <td>
                    <input class="form-control" name="${formPrefix}[item_options][${optionKey}][ordering]" value="0">
                </td>
                <td class="text-end">
                    <span class="optionRemove btn btn-danger btn-sm" role="button" title="<?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?>">
                        <span class="icon icon-trash"></span>
                    </span>
                    <input type="hidden" value="${optionValue}" name="${formPrefix}[item_options][${optionKey}][option_id]">
                    <input type="hidden" value="" name="${formPrefix}[item_options][${optionKey}][j2commerce_productoption_id]">
                </td>
            `;

            // Insert before the add options row
            const insertBeforeRow = document.querySelector('#flexivariable_options_table .j2commerce_a_options');
            if (insertBeforeRow) {
                insertBeforeRow.parentNode.insertBefore(newRow, insertBeforeRow);
            }

            optionKey++;
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
            const origText = createVariantsBtn.innerHTML;
            createVariantsBtn.disabled = true;
            createVariantsBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>';

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
                                addRow.insertAdjacentHTML('beforebegin', result.options_table_html);
                            }
                        }
                    }

                    // Update the variant_add_block with the returned HTML
                    const variantAddBlock = document.getElementById('variant_add_block');
                    if (variantAddBlock && result.variant_add_block_html) {
                        variantAddBlock.innerHTML = result.variant_add_block_html;
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
                createVariantsBtn.innerHTML = origText;
            }
        });
    }

    // Initial visibility check
    updateCreateVariantsBtnVisibility();
});
</script>
