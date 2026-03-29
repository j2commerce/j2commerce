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
use Joomla\CMS\Session\Session;

$item        = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

$key        = 0;
$csrfToken  = Session::getFormToken();
?>

<div class="j2commerce-product-variants">
    <fieldset id="j2commerce-variable-options" class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_OPTIONS'); ?></legend>
        <?php if (empty($productOptionList)) : ?>
            <p class="alert alert-warning">
                <span class="me-3"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_NO_OPTION_MESSAGE'); ?></span>
            </p>
            <div>
                <a href="index.php?option=com_j2commerce&view=options" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_CREATE'); ?></a>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table id="variable_options_table" class="table itemList align-middle j2commerce">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_NAME'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($item->product_options) && !empty($item->product_options)) : ?>
                            <?php foreach ($item->product_options as $poption) : ?>
                                <tr id="pao_variable_option_<?php echo $poption->j2commerce_productoption_id; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <strong><?php echo $this->escape($poption->option_name); ?></strong>
                                            <input type="hidden" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][j2commerce_productoption_id]'; ?>" value="<?php echo $poption->j2commerce_productoption_id; ?>">
                                            <input type="hidden" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][option_id]'; ?>" value="<?php echo $poption->option_id; ?>">
                                            <small class="ms-1">(<?php echo $this->escape($poption->option_unique_name); ?>)</small>
                                            <?php if (isset($poption->type) && in_array($poption->type, ['select', 'radio', 'checkbox', 'color'], true)) : ?>
                                                <button type="button" class="small ms-2 ms-lg-3 btn btn-soft-dark btn-sm j2commerce-variable-option-values-link"
                                                        data-product-id="<?php echo $item->j2commerce_product_id; ?>"
                                                        data-option-id="<?php echo $poption->j2commerce_productoption_id; ?>"
                                                        data-option-name="<?php echo $this->escape($poption->option_name); ?>">
                                                    <span class="icon-cog me-1"></span> <?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <small class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_OPTION_TYPE'); ?>: <?php echo $poption->type ?? ''; ?></small>
                                        </div>

                                    </td>
                                    <td>
                                        <?php echo LayoutHelper::render('joomla.form.field.text', [
                                            'name'  => $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][ordering]',
                                            'id'    => 'variable_ordering_' . $poption->j2commerce_productoption_id,
                                            'value' => $poption->ordering ?? '',
                                            'class' => 'form-control',
                                        ] + $textFieldDefaults); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="optionRemove btn btn-soft-danger btn-sm"
                                              data-option-id="<?php echo $poption->j2commerce_productoption_id; ?>"
                                              role="button"
                                              title="<?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?>">
                                            <span class="icon icon-trash"></span>
                                        </span>
                                    </td>
                                </tr>
                                <?php $key++; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="j2commerce_variable_a_options">
                            <td colspan="3">
                                <?php if (empty($item->j2commerce_product_id)) : ?>
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <?php echo Text::_('COM_J2COMMERCE_SAVE_PRODUCT_FIRST_TO_ADD_OPTIONS'); ?>
                                    </div>
                                <?php else : ?>
                                    <div class="control-group align-items-center mt-4">
                                        <div class="control-label">
                                            <label for="j2commerce_variable_option_select"><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_ADD_VARIANT_OPTION'); ?></label>
                                        </div>
                                        <div class="controls">
                                            <div class="input-group">
                                                <select name="variable_option_select_id" id="j2commerce_variable_option_select" class="form-select">
                                                    <?php foreach ($productOptionList as $option_list) : ?>
                                                        <option value="<?php echo $option_list->j2commerce_option_id; ?>"><?php echo $this->escape($option_list->option_name) . ' (' . $this->escape($option_list->option_unique_name) . ')'; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" id="j2commerce_variable_add_option_btn" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <input type="hidden" name="<?php echo $formPrefix; ?>[deleted_options]" id="j2commerce-variable-deleted-options" value="">

    </fieldset>
    <div class="alert alert-info d-flex align-items-center my-3" role="alert">
        <span class="fas fa-solid fa-exclamation-circle me-3"></span>
        <div><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIABLE_GENERATION_HELP_TEXT'); ?></div>
    </div>
</div>

<!-- AJAX Modal for Option Values -->
<div class="modal fade" id="j2commerceVariableOptionValuesModal" tabindex="-1" aria-labelledby="variableOptionValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variableOptionValuesModalLabel"><?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceVariableOptionValuesModalBody" style="max-height: 70vh; overflow-y: auto;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                    </div>
                    <p class="mt-2 text-muted"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const formPrefix = '<?php echo $formPrefix; ?>';
    const productId = <?php echo (int) ($item->j2commerce_product_id ?? 0); ?>;
    const csrfToken = '<?php echo $csrfToken; ?>';
    const variantTypes = ['select', 'radio', 'checkbox', 'color'];

    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function buildOptionRow(poId, optionId, optionName, uniqueName, optionType, ordering) {
        const showSetValues = variantTypes.includes(optionType);
        const setValuesBtn = showSetValues
            ? `<button type="button" class="small ms-2 btn btn-outline-primary btn-sm j2commerce-variable-option-values-link"
                    data-product-id="${productId}"
                    data-option-id="${poId}"
                    data-option-name="${escHtml(optionName)}">
                    <span class="icon-cog"></span> <?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?>
                </button>`
            : '';

        return `<tr id="pao_variable_option_${poId}">
            <td>
                ${escHtml(optionName)}
                <input type="hidden" name="${formPrefix}[item_options][${poId}][j2commerce_productoption_id]" value="${poId}">
                <input type="hidden" name="${formPrefix}[item_options][${poId}][option_id]" value="${optionId}">
                <small>(${escHtml(uniqueName)})</small>
                <small class="text-capitalize"><?php echo Text::_('COM_J2COMMERCE_OPTION_TYPE'); ?> ${escHtml(optionType)}</small>
                ${setValuesBtn}
            </td>
            <td>
                <input type="text" class="form-control" name="${formPrefix}[item_options][${poId}][ordering]" value="${ordering}">
            </td>
            <td class="text-end">
                <span class="optionRemove btn btn-danger btn-sm"
                      data-option-id="${poId}"
                      role="button"
                      title="<?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?>">
                    <span class="icon icon-trash"></span>
                </span>
            </td>
        </tr>`;
    }

    // AJAX Add option button handler
    const addOptionBtn = document.getElementById('j2commerce_variable_add_option_btn');
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', async () => {
            const selectEl = document.getElementById('j2commerce_variable_option_select');
            if (!selectEl) return;

            const optionId = selectEl.value;
            if (!optionId) return;

            addOptionBtn.disabled = true;
            addOptionBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';

            try {
                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.addProductOptionAjax');
                formData.append('product_id', productId);
                formData.append('option_id', optionId);
                formData.append(csrfToken, 1);

                const response = await fetch('index.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    const insertBeforeRow = document.querySelector('#variable_options_table .j2commerce_variable_a_options');
                    if (insertBeforeRow) {
                        const temp = document.createElement('tbody');
                        temp.innerHTML = buildOptionRow(
                            data.productoption_id,
                            optionId,
                            data.option_name,
                            data.option_unique_name,
                            data.option_type,
                            data.ordering
                        );
                        insertBeforeRow.parentNode.insertBefore(temp.firstElementChild, insertBeforeRow);
                    }
                } else {
                    Joomla.renderMessages({warning: [data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>']});
                }
            } catch (error) {
                console.error('Error adding option:', error);
                Joomla.renderMessages({error: ['<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>']});
            }

            addOptionBtn.disabled = false;
            addOptionBtn.textContent = '<?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD'); ?>';
        });
    }

    // AJAX Remove option handler (event delegation)
    document.getElementById('j2commerce-variable-options')?.addEventListener('click', async (e) => {
        const removeBtn = e.target.closest('.optionRemove');
        if (!removeBtn) return;

        e.preventDefault();
        const row = removeBtn.closest('tr');
        if (!row) return;

        const optionId = removeBtn.getAttribute('data-option-id');
        if (!optionId) {
            row.remove();
            return;
        }

        removeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';

        try {
            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.removeProductOptionAjax');
            formData.append('productoption_id', optionId);
            formData.append(csrfToken, 1);

            const response = await fetch('index.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            } else {
                Joomla.renderMessages({warning: [data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>']});
                removeBtn.innerHTML = '<span class="icon icon-trash"></span>';
            }
        } catch (error) {
            console.error('Error removing option:', error);
            removeBtn.innerHTML = '<span class="icon icon-trash"></span>';
        }
    });

    // Option Values Modal
    const optionValuesModal = document.getElementById('j2commerceVariableOptionValuesModal');
    const optionValuesModalBody = document.getElementById('j2commerceVariableOptionValuesModalBody');
    const modalLabel = document.getElementById('variableOptionValuesModalLabel');
    let modalInstance = null;

    const loadingHtml = `<div class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
        </div>
        <p class="mt-2 text-muted"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
    </div>`;

    function showModalLoading() {
        optionValuesModalBody.innerHTML = loadingHtml;
    }

    function showModalError(message) {
        optionValuesModalBody.innerHTML = `<div class="alert alert-danger"><span class="icon-warning"></span> ${escHtml(message)}</div>`;
    }

    function showModalMessage(message, type = 'success') {
        const messagesContainer = document.getElementById('j2commerce-optionvalues-messages');
        if (messagesContainer) {
            const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info';
            messagesContainer.innerHTML = `
                <div class="alert alert-${safeType} alert-dismissible fade show" role="alert">
                    ${escHtml(message)}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
            setTimeout(() => {
                const alert = messagesContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 3000);
        }
    }

    async function loadOptionValuesContent(prodId, productOptionId) {
        showModalLoading();
        try {
            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.getProductOptionValuesAjax');
            formData.append('product_id', prodId);
            formData.append('productoption_id', productOptionId);
            formData.append(csrfToken, 1);

            const response = await fetch('index.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                optionValuesModalBody.innerHTML = data.html;
                modalLabel.textContent = '<?php echo Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'); ?>: ' + data.optionName;
                initOptionValuesHandlers();
            } else {
                showModalError(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT'); ?>');
            }
        } catch (error) {
            console.error('Error loading option values:', error);
            showModalError('<?php echo Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT'); ?>');
        }
    }

    function initOptionValuesHandlers() {
        const container = document.querySelector('.j2commerce-ajax-optionvalues');
        if (!container) return;

        const containerProductId = container.dataset.productId;
        const containerProductOptionId = container.dataset.productoptionId;

        // Create option value
        const createBtn = document.getElementById('j2commerce-create-optionvalue-btn');
        if (createBtn) {
            createBtn.addEventListener('click', async () => {
                createBtn.disabled = true;
                createBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SAVING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.createProductOptionValueAjax');
                formData.append('product_id', containerProductId);
                formData.append('productoption_id', containerProductOptionId);
                formData.append('optionvalue_id', document.getElementById('j2commerce_new_optionvalue_id')?.value || '');
                formData.append('product_optionvalue_price', document.getElementById('j2commerce_new_price')?.value || '0');
                formData.append('product_optionvalue_prefix', document.getElementById('j2commerce_new_price_prefix')?.value || '+');
                formData.append('product_optionvalue_weight', document.getElementById('j2commerce_new_weight')?.value || '0');
                formData.append('product_optionvalue_weight_prefix', document.getElementById('j2commerce_new_weight_prefix')?.value || '+');
                formData.append('ordering', document.getElementById('j2commerce_new_ordering')?.value || '0');
                formData.append('product_optionvalue_attribs', document.getElementById('j2commerce_new_attribs')?.value || '');
                formData.append(csrfToken, 1);

                const parentSelect = document.getElementById('j2commerce_new_parent');
                if (parentSelect) {
                    Array.from(parentSelect.selectedOptions).forEach(opt => {
                        formData.append('parent_optionvalue[]', opt.value);
                    });
                }

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        await loadOptionValuesContent(containerProductId, containerProductOptionId);
                        showModalMessage(data.message, 'success');
                    } else {
                        showModalMessage(data.message, 'danger');
                        createBtn.disabled = false;
                        createBtn.innerHTML = '<span class="icon-plus"></span> <?php echo Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'); ?>';
                    }
                } catch (error) {
                    console.error('Error creating option value:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_SAVING'); ?>', 'danger');
                    createBtn.disabled = false;
                    createBtn.innerHTML = '<span class="icon-plus"></span> <?php echo Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'); ?>';
                }
            });
        }

        // Add all option values
        const addAllBtn = document.getElementById('j2commerce-add-all-optionvalues-btn');
        if (addAllBtn) {
            addAllBtn.addEventListener('click', async () => {
                addAllBtn.disabled = true;
                addAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.addAllOptionValue');
                formData.append('product_id', containerProductId);
                formData.append('productoption_id', containerProductOptionId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        await loadOptionValuesContent(containerProductId, containerProductOptionId);
                        showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ALL_OPTION_VALUES_ADDED'); ?>', 'success');
                    } else {
                        showModalMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>', 'danger');
                        addAllBtn.disabled = false;
                        addAllBtn.innerHTML = '<span class="icon-list"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'); ?>';
                    }
                } catch (error) {
                    console.error('Error adding all option values:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>', 'danger');
                    addAllBtn.disabled = false;
                    addAllBtn.innerHTML = '<span class="icon-list"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'); ?>';
                }
            });
        }

        // Save option values
        const saveBtn = document.getElementById('j2commerce-save-optionvalues-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<?php echo Text::_('COM_J2COMMERCE_SAVING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.saveProductOptionValueAjax');
                formData.append(csrfToken, 1);

                document.querySelectorAll('#j2commerce-optionvalues-tbody tr[data-pov-id]').forEach(row => {
                    row.querySelectorAll('input, select, textarea').forEach(input => {
                        if (input.name) {
                            if (input.tagName === 'SELECT' && input.multiple) {
                                Array.from(input.selectedOptions).forEach(opt => formData.append(input.name, opt.value));
                            } else {
                                formData.append(input.name, input.value);
                            }
                        }
                    });
                });

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    showModalMessage(data.message, data.success ? 'success' : 'danger');
                } catch (error) {
                    console.error('Error saving option values:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_SAVING'); ?>', 'danger');
                }

                saveBtn.disabled = false;
                saveBtn.innerHTML = '<?php echo Text::_('COM_J2COMMERCE_SAVE_CHANGES'); ?>';
            });
        }

        // Delete individual option values
        document.querySelectorAll('.j2commerce-delete-optionvalue-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('<?php echo Text::_('COM_J2COMMERCE_CONFIRM_DELETE'); ?>')) return;

                const povId = btn.dataset.povId;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.deleteProductOptionValueAjax');
                formData.append('pov_id', povId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        const row = btn.closest('tr');
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            const tbody = document.getElementById('j2commerce-optionvalues-tbody');
                            if (tbody && tbody.querySelectorAll('tr[data-pov-id]').length === 0) {
                                tbody.innerHTML = `<tr class="j2commerce-no-values-row">
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <?php echo Text::_('COM_J2COMMERCE_NO_OPTION_VALUES_ASSIGNED'); ?>
                                    </td>
                                </tr>`;
                            }
                        }, 300);
                        showModalMessage(data.message, 'success');
                    } else {
                        showModalMessage(data.message, 'danger');
                        btn.disabled = false;
                        btn.innerHTML = '<span class="icon-trash"></span>';
                    }
                } catch (error) {
                    console.error('Error deleting option value:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<span class="icon-trash"></span>';
                }
            });
        });

        // Set default option value
        document.querySelectorAll('.j2commerce-set-default-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const povId = btn.dataset.povId;
                btn.disabled = true;

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.setDefault');
                formData.append('product_id', containerProductId);
                formData.append('productoption_id', containerProductOptionId);
                formData.append('cid[]', povId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        document.querySelectorAll('.j2commerce-set-default-btn').forEach(b => {
                            b.classList.remove('text-warning');
                            b.classList.add('text-muted');
                            const icon = b.querySelector('span');
                            if (icon) icon.className = 'icon-star-empty';
                        });
                        btn.classList.remove('text-muted');
                        btn.classList.add('text-warning');
                        const selectedIcon = btn.querySelector('span');
                        if (selectedIcon) selectedIcon.className = 'icon-star';
                        showModalMessage('<?php echo Text::_('COM_J2COMMERCE_DEFAULT_SET_SUCCESSFULLY'); ?>', 'success');
                    } else {
                        showModalMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>', 'danger');
                    }
                } catch (error) {
                    console.error('Error setting default:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_OCCURRED'); ?>', 'danger');
                }

                btn.disabled = false;
            });
        });
    }

    // Event delegation for Set Values button clicks
    document.getElementById('j2commerce-variable-options')?.addEventListener('click', (e) => {
        const link = e.target.closest('.j2commerce-variable-option-values-link');
        if (!link) return;

        e.preventDefault();
        const linkProductId = link.getAttribute('data-product-id');
        const productOptionId = link.getAttribute('data-option-id');
        const optionName = link.getAttribute('data-option-name');

        modalLabel.textContent = '<?php echo Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'); ?>: ' + optionName;

        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(optionValuesModal);
        }
        modalInstance.show();

        loadOptionValuesContent(linkProductId, productOptionId);
    });

    // Reset modal content on hide
    optionValuesModal?.addEventListener('hidden.bs.modal', () => {
        optionValuesModalBody.innerHTML = loadingHtml;
    });
});
</script>
