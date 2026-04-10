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
use J2Commerce\Component\J2commerce\Administrator\Helper\SelectHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$style = '.autocomplete-list{background: var(--form-control-bg);max-height: 200px;overflow-y: auto;width: 100%;}.autocomplete-list.autocomplete-active{border: var(--form-control-border);}.autocomplete-item{padding: 8px;cursor: pointer;font-size: .8rem;}.autocomplete-item:hover {background-color: #f0f0f0;}';
$wa->addInlineStyle($style, [], []);

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

$key = 0;

$csrfToken = \Joomla\CMS\Session\Session::getFormToken();

?>

<div class="j2commerce-product-options">
    <fieldset id="j2commerce-product-configoptions" class="options-form">
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
                <table id="configurable_options_table" class="table itemList align-middle j2commerce">
                    <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_NAME'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PARENT_OPTION'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_REQUIRED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (isset($item->product_options) && !empty($item->product_options)) : ?>
                        <?php foreach ($item->product_options as $poption) : ?>
                            <?php
                                $parentOptions = SelectHelper::getParentOption(
                                    (int) $item->j2commerce_product_id,
                                    [],
                                    (int) $poption->option_id
                                );
                            ?>
                            <tr id="pao_current_option_<?php echo $poption->j2commerce_productoption_id; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <strong><?php echo $this->escape($poption->option_name); ?></strong>
                                        <input type="hidden" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][j2commerce_productoption_id]'; ?>" value="<?php echo $poption->j2commerce_productoption_id; ?>">
                                        <input type="hidden" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][option_id]'; ?>" value="<?php echo $poption->option_id; ?>">
                                        <small class="ms-1">(<?php echo $this->escape($poption->option_unique_name); ?>)</small>
                                        <?php if (isset($poption->type) && ($poption->type === 'select' || $poption->type === 'radio' || $poption->type === 'checkbox' || $poption->type === 'color')) : ?>
                                            <button type="button" class="small ms-3 btn btn-outline-primary btn-sm j2commerce-option-values-link"
                                                    data-product-id="<?php echo $item->j2commerce_product_id; ?>"
                                                    data-option-id="<?php echo $poption->j2commerce_productoption_id; ?>"
                                                    data-option-name="<?php echo $this->escape($poption->option_name); ?>">
                                                <span class="icon-cog me-1"></span> <?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <small><?php echo Text::_('COM_J2COMMERCE_OPTION_TYPE'); ?> <?php echo Text::_('COM_J2COMMERCE_' . strtoupper($poption->type)); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <select class="form-select" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][parent_id]'; ?>">
                                        <?php foreach ($parentOptions as $parentValue => $parentLabel) : ?>
                                            <option value="<?php echo (int) $parentValue; ?>"<?php echo ((int) $poption->parent_id === (int) $parentValue) ? ' selected' : ''; ?>><?php echo $this->escape($parentLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][required]'; ?>">
                                        <option value="0"<?php echo ($poption->required == 0) ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                        <option value="1"<?php echo ($poption->required == 1) ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="<?php echo $formPrefix . '[item_options][' . $poption->j2commerce_productoption_id . '][ordering]'; ?>" id="ordering<?php echo $poption->j2commerce_productoption_id; ?>" value="<?php echo $poption->ordering; ?>">
                                </td>
                                <td class="text-end">
                                    <span class="optionRemove btn btn-danger btn-sm"
                                          data-option-id="<?php echo $poption->j2commerce_productoption_id; ?>"
                                          data-product-type="<?php echo $item->product_type; ?>"
                                          role="button" title="<?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE'); ?>">
                                        <span class="icon icon-trash"></span>
                                    </span>
                                </td>
                            </tr>
                            <?php $key++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="j2commerce_a_options">
                        <td colspan="5">
                            <div class="control-group align-items-center mt-4">
                                <div class="control-label">
                                    <label id="config_option_select_id-lbl" for="config_option_select_id"><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_ADD_VARIANT_OPTION'); ?></label>
                                </div>
                                <div class="controls">
                                    <div class="input-group">
                                        <select name="config_option_select_id" id="config_option_select_id" class="form-select">
                                            <?php foreach ($productOptionList as $option_list) : ?>
                                                <option value="<?php echo $option_list->j2commerce_option_id; ?>"><?php echo $this->escape($option_list->option_name) . ' (' . $this->escape($option_list->option_unique_name) . ')'; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="j2commerce-add-configoption-btn" class="btn btn-success">
                                            <?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <input type="hidden" name="<?php echo $formPrefix; ?>[deleted_options]" id="j2commerce-deleted-configoptions" value="">

    </fieldset>
</div>

<!-- AJAX Modal for Option Values (no iframe) -->
<div class="modal fade" id="j2commerceConfigOptionValuesModal" tabindex="-1" aria-labelledby="configOptionValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="configOptionValuesModalLabel"><?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceConfigOptionValuesModalBody" style="max-height: 70vh; overflow-y: auto;">
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
    let optionKey = <?php echo $key; ?>;
    const csrfToken = '<?php echo $csrfToken; ?>';

    function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Add option button handler
    const addOptionBtn = document.getElementById('j2commerce-add-configoption-btn');
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', () => {
            const selectEl = document.getElementById('config_option_select_id');
            if (!selectEl) return;

            const optionValue = selectEl.value;
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const optionName = selectedOption ? selectedOption.textContent : '';

            const newRow = document.createElement('tr');
            newRow.id = 'j2commerce-configop-tr-' + optionKey;
            newRow.innerHTML = `
                <td class="addedOption">${escHtml(optionName)}</td>
                <td>
                    <span class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_PARENT_OPTION_SAVE_FIRST'); ?></span>
                    <input type="hidden" value="0" name="${formPrefix}[item_options][${optionKey}][parent_id]">
                </td>
                <td>
                    <select class="form-select" name="${formPrefix}[item_options][${optionKey}][required]">
                        <option value="0"><?php echo Text::_('JNO'); ?></option>
                        <option value="1"><?php echo Text::_('JYES'); ?></option>
                    </select>
                </td>
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

            const insertBeforeRow = document.querySelector('#configurable_options_table .j2commerce_a_options');
            if (insertBeforeRow) {
                insertBeforeRow.parentNode.insertBefore(newRow, insertBeforeRow);
            }

            optionKey++;
        });
    }

    // Remove option handler (event delegation for both existing and dynamically added rows)
    document.getElementById('j2commerce-product-configoptions')?.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.optionRemove');
        if (!removeBtn) return;

        e.preventDefault();
        const row = removeBtn.closest('tr');
        if (row) {
            const optionId = removeBtn.getAttribute('data-option-id');
            if (optionId) {
                const deletedField = document.getElementById('j2commerce-deleted-configoptions');
                if (deletedField) {
                    const currentValue = deletedField.value;
                    const deletedIds = currentValue ? currentValue.split(',') : [];
                    if (!isNaN(parseInt(optionId)) && parseInt(optionId) > 0 && !deletedIds.includes(optionId)) {
                        deletedIds.push(optionId);
                        deletedField.value = deletedIds.join(',');
                    }
                }
            }

            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        }
    });

    // Option Values Modal - AJAX loading (no iframe)
    const optionValuesModal = document.getElementById('j2commerceConfigOptionValuesModal');
    const optionValuesModalBody = document.getElementById('j2commerceConfigOptionValuesModalBody');
    const modalLabel = document.getElementById('configOptionValuesModalLabel');
    let modalInstance = null;
    let currentProductId = null;
    let currentProductOptionId = null;

    function showModalLoading() {
        optionValuesModalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                </div>
                <p class="mt-2 text-muted"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
            </div>
        `;
    }

    function showModalError(message) {
        optionValuesModalBody.innerHTML = `
            <div class="alert alert-danger">
                <span class="icon-warning"></span> ${message}
            </div>
        `;
    }

    function showModalMessage(message, type = 'success') {
        const messagesContainer = document.getElementById('j2commerce-optionvalues-messages');
        if (messagesContainer) {
            messagesContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            setTimeout(() => {
                const alert = messagesContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 3000);
        }
    }

    async function loadOptionValuesContent(productId, productOptionId) {
        showModalLoading();

        try {
            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.getProductOptionValuesAjax');
            formData.append('product_id', productId);
            formData.append('productoption_id', productOptionId);
            formData.append(csrfToken, 1);

            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });

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

        const productId = container.dataset.productId;
        const productOptionId = container.dataset.productoptionId;

        const createBtn = document.getElementById('j2commerce-create-optionvalue-btn');
        if (createBtn) {
            createBtn.addEventListener('click', async () => {
                createBtn.disabled = true;
                createBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SAVING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.createProductOptionValueAjax');
                formData.append('product_id', productId);
                formData.append('productoption_id', productOptionId);
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
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        await loadOptionValuesContent(productId, productOptionId);
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

        const addAllBtn = document.getElementById('j2commerce-add-all-optionvalues-btn');
        if (addAllBtn) {
            addAllBtn.addEventListener('click', async () => {
                addAllBtn.disabled = true;
                addAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.addAllOptionValue');
                formData.append('product_id', productId);
                formData.append('productoption_id', productOptionId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        await loadOptionValuesContent(productId, productOptionId);
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

        const saveBtn = document.getElementById('j2commerce-save-optionvalues-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<?php echo Text::_('COM_J2COMMERCE_SAVING'); ?>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.saveProductOptionValueAjax');
                formData.append(csrfToken, 1);

                const rows = document.querySelectorAll('#j2commerce-optionvalues-tbody tr[data-pov-id]');
                rows.forEach(row => {
                    const inputs = row.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        if (input.name) {
                            if (input.tagName === 'SELECT' && input.multiple) {
                                Array.from(input.selectedOptions).forEach(opt => {
                                    formData.append(input.name, opt.value);
                                });
                            } else {
                                formData.append(input.name, input.value);
                            }
                        }
                    });
                });

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showModalMessage(data.message, 'success');
                    } else {
                        showModalMessage(data.message, 'danger');
                    }
                } catch (error) {
                    console.error('Error saving option values:', error);
                    showModalMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_SAVING'); ?>', 'danger');
                }

                saveBtn.disabled = false;
                saveBtn.innerHTML = '<?php echo Text::_('COM_J2COMMERCE_SAVE_CHANGES'); ?>';
            });
        }

        document.querySelectorAll('.j2commerce-delete-optionvalue-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('<?php echo Text::_('COM_J2COMMERCE_CONFIRM_DELETE'); ?>')) {
                    return;
                }

                const povId = btn.dataset.povId;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.deleteProductOptionValueAjax');
                formData.append('pov_id', povId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        const row = btn.closest('tr');
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            const tbody = document.getElementById('j2commerce-optionvalues-tbody');
                            if (tbody && tbody.querySelectorAll('tr[data-pov-id]').length === 0) {
                                tbody.innerHTML = `
                                    <tr class="j2commerce-no-values-row">
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <?php echo Text::_('COM_J2COMMERCE_NO_OPTION_VALUES_ASSIGNED'); ?>
                                        </td>
                                    </tr>
                                `;
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

        document.querySelectorAll('.j2commerce-set-default-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const povId = btn.dataset.povId;
                btn.disabled = true;

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.setDefault');
                formData.append('product_id', productId);
                formData.append('productoption_id', productOptionId);
                formData.append('cid[]', povId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        document.querySelectorAll('.j2commerce-set-default-btn').forEach(b => {
                            b.classList.remove('text-warning');
                            b.classList.add('text-muted');
                            const icon = b.querySelector('span');
                            if (icon) {
                                icon.className = 'icon-star-empty';
                            }
                        });
                        btn.classList.remove('text-muted');
                        btn.classList.add('text-warning');
                        const selectedIcon = btn.querySelector('span');
                        if (selectedIcon) {
                            selectedIcon.className = 'icon-star';
                        }
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

    // Event delegation for option values links
    document.getElementById('j2commerce-product-configoptions')?.addEventListener('click', (e) => {
        const link = e.target.closest('.j2commerce-option-values-link');
        if (!link) return;

        e.preventDefault();
        const productId = link.getAttribute('data-product-id');
        const productOptionId = link.getAttribute('data-option-id');
        const optionName = link.getAttribute('data-option-name');

        currentProductId = productId;
        currentProductOptionId = productOptionId;

        modalLabel.textContent = '<?php echo Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'); ?>: ' + optionName;

        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(optionValuesModal);
        }
        modalInstance.show();

        loadOptionValuesContent(productId, productOptionId);
    });

    // Clear modal content when hidden
    optionValuesModal.addEventListener('hidden.bs.modal', () => {
        optionValuesModalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                </div>
                <p class="mt-2 text-muted"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
            </div>
        `;
        currentProductId = null;
        currentProductOptionId = null;
    });
});
</script>
