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


$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

$style = '.autocomplete-list{background: var(--form-control-bg);max-height: 200px;overflow-y: auto;width: 100%;}.autocomplete-list.autocomplete-active{border: var(--form-control-border);}.autocomplete-item{padding: 8px;cursor: pointer;font-size: .8rem;}.autocomplete-item:hover {background-color: var(--template-bg-dark-5, #f0f0f0);}';
$wa->addInlineStyle($style, [], []);

// Adopts the option-values markup this view fetches. Deferred, so it has run before any fetch callback.
$wa->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true]);

// Drag-ordering for the fetched option-values table. Registered here because the fragment is
// adopted as markup only - its own asset registrations and scripts never reach the page.
$wa->registerAndUseScript('com_j2commerce.optionvalues-sortable', 'media/com_j2commerce/js/administrator/optionvalues-sortable.js', [], ['defer' => true]);
$wa->registerAndUseStyle('com_j2commerce.optionvalues-sortable', 'media/com_j2commerce/css/administrator/optionvalues-sortable.css');

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

// Options already on the product are offered as disabled entries rather than omitted,
// so the reason they cannot be picked again is visible in the list itself.
$assignedOptionIds = array_flip(array_map(
    static fn($poption): int => (int) $poption->option_id,
    (array) ($item->product_options ?? [])
));

// Initialize key counter for options
$key = 0;

// Pass CSRF token to JavaScript
$csrfToken = \Joomla\CMS\Session\Session::getFormToken();

?>

<div class="j2commerce-product-options">
    <fieldset id="j2commerce-product-options" class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_OPTIONS'); ?></legend>
        <?php if (empty($productOptionList)) : ?>
            <p class="alert alert-warning">
                <span class="me-3"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_NO_OPTION_MESSAGE')?></span>
            </p>
            <div>
                <a href="index.php?option=com_j2commerce&view=options" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_CREATE')?></a>
            </div>

        <?php else : ?>
            <div class="table-responsive">
                <table id="attribute_options_table" class="table itemList align-middle j2commerce">
                    <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_NAME');?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_REQUIRED');?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING');?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_OPTION_REMOVE');?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if(isset($item->product_options) && !empty($item->product_options)):?>
                        <?php foreach($item->product_options as $poption):?>
                            <tr id="pao_current_option_<?php echo $poption->j2commerce_productoption_id;?>">
                                <td>
                                    <?php echo $this->escape($poption->option_name);?>
                                    <input type="hidden" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id .'][j2commerce_productoption_id]';?>" value="<?php echo $poption->j2commerce_productoption_id;?>">
                                    <input type="hidden" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id .'][option_id]';?>" value="<?php echo $poption->option_id;?>">

                                    <small>(<?php echo $this->escape($poption->option_unique_name);?>)</small>
                                    <small><?php echo Text::_('COM_J2COMMERCE_OPTION_TYPE');?> <?php echo $this->escape(J2CommerceHelper::getOptionTypeLabel($poption->type));?></small>
                                    <?php if(isset($poption->type) && ($poption->type =='select' || $poption->type =='radio' || $poption->type =='checkbox' || $poption->type =='color')):?>
                                        <button type="button" class="small ms-2 btn btn-outline-primary btn-sm j2commerce-option-values-link"
                                           data-product-id="<?php echo $item->j2commerce_product_id; ?>"
                                           data-option-id="<?php echo $poption->j2commerce_productoption_id; ?>"
                                           data-option-name="<?php echo $this->escape($poption->option_name); ?>">
                                            <span class="icon-cog"></span> <?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES');?>
                                        </button>
                                    <?php endif;?>
                                </td>
                                <td>
                                    <select class="form-select" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id .'][required]';?>">
                                        <option value="0"<?php echo ($poption->required == 0) ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                        <option value="1"<?php echo ($poption->required == 1) ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="<?php echo $formPrefix.'[item_options]['.$poption->j2commerce_productoption_id .'][ordering]';?>" id="ordering<?php echo $poption->j2commerce_productoption_id;?>" value="<?php echo $poption->ordering;?>">
                                </td>
                                <td class="text-end">
                                    <span class="optionRemove btn btn-danger btn-sm"
                                          data-option-id="<?php echo $poption->j2commerce_productoption_id;?>"
                                          data-product-type="<?php echo $item->product_type;?>"
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
                                    <label id="option_select_id-lbl" for="option_select_id"><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_ADD_VARIANT_OPTION');?></label>
                                </div>
                                <div class="controls">
                                    <div class="input-group">
                                        <select name="option_select_id" id="option_select_id" class="form-select">
                                            <?php foreach ($productOptionList as $option_list):
                                                $optionLabel = $option_list->option_name . ' (' . $option_list->option_unique_name . ')';
                                                $optionAssigned = isset($assignedOptionIds[(int) $option_list->j2commerce_option_id]);
                                                ?>
                                                <option value="<?php echo $option_list->j2commerce_option_id?>" data-option-label="<?php echo $this->escape($optionLabel);?>"<?php echo $optionAssigned ? ' disabled' : '';?>><?php echo $this->escape($optionAssigned ? Text::sprintf('COM_J2COMMERCE_OPTION_LABEL_ALREADY_ADDED', $optionLabel) : $optionLabel);?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="j2commerce-add-option-btn" class="btn btn-success">
                                            <?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD')?>
                                        </button>
                                    </div>
                                    <div id="j2commerce-option-notice" class="alert alert-warning mt-2 d-none" role="alert"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif;?>

        <!-- Hidden field to track deleted option IDs for persistence on save -->
        <input type="hidden" name="<?php echo $formPrefix; ?>[deleted_options]" id="j2commerce-deleted-options" value="">

    </fieldset>
</div>

<!-- AJAX Modal for Option Values (no iframe) -->
<div class="modal fade" id="j2commerceOptionValuesModal" tabindex="-1" aria-labelledby="optionValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="optionValuesModalLabel"><?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceOptionValuesModalBody" style="max-height: 70vh; overflow-y: auto;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                    </div>
                    <p class="mt-2 text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></p>
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
    const optionSelect = document.getElementById('option_select_id');
    const optionNotice = document.getElementById('j2commerce-option-notice');
    const addOptionBtn = document.getElementById('j2commerce-add-option-btn');
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

    function selectedOptionEl() {
        return optionSelect && optionSelect.selectedIndex >= 0
            ? optionSelect.options[optionSelect.selectedIndex]
            : null;
    }

    // The table is the single source of truth for what is on the product, so the dropdown
    // is derived from it after every change rather than toggled entry by entry.
    function syncOptionSelect() {
        if (!optionSelect) return;

        const usedIds = new Set(
            Array.from(document.querySelectorAll('#attribute_options_table input[name$="[option_id]"]'))
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

    syncOptionSelect();

    // Button content swaps. Each builds the same DOM the markup strings used to,
    // so a button keeps its icon, spacing and assistive-text behaviour.
    function setIconLabel(el, iconClass, label) {
        const icon = document.createElement('span');
        icon.className = iconClass;
        el.replaceChildren(icon);

        if (label) {
            el.append(' ' + label);
        }
    }

    // Decorative spinner with the label visible beside it.
    function setSpinnerLabel(el, label, spinnerClass = 'spinner-border spinner-border-sm') {
        const spinner = document.createElement('span');
        spinner.className = spinnerClass;
        spinner.setAttribute('aria-hidden', 'true');
        el.replaceChildren(spinner);
        el.append(' ' + label);
    }

    // Spinner carrying the label for assistive tech only.
    function setSpinnerOnly(el, label) {
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');

        const srLabel = document.createElement('span');
        srLabel.className = 'visually-hidden';
        srLabel.textContent = label;
        spinner.append(srLabel);
        el.replaceChildren(spinner);
    }

    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', () => {
            if (!optionSelect) return;

            const optionValue = optionSelect.value;
            const selectedOption = selectedOptionEl();
            const optionName = selectedOption ? (selectedOption.dataset.optionLabel || selectedOption.textContent) : '';

            // Rows are keyed by record id when rendered by the server and by a counter when
            // added here, so the option is matched on the value it carries rather than the key.
            const alreadyAdded = Array.from(
                document.querySelectorAll('#attribute_options_table input[name$="[option_id]"]')
            ).some(input => input.value === optionValue);
            if (alreadyAdded) {
                showOptionNotice(<?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_ALREADY_ADDED')); ?>);
                syncOptionSelect();
                return;
            }

            clearOptionNotice();

            // Create new table row
            const newRow = document.createElement('tr');
            newRow.id = 'j2commerce-op-tr-' + optionKey;
            // The field names carry this row into the product save, so they are built from
            // one place rather than repeated per input. The key is prefixed because the
            // server-rendered rows key this same array by record id, and a bare counter can
            // land on one of those ids — whichever row posts last then wins and the other is
            // dropped. The save reads each row's j2commerce_productoption_id, never the key.
            const fieldName = (suffix) => formPrefix + '[item_options][new_' + optionKey + '][' + suffix + ']';

            const nameCell = document.createElement('td');
            nameCell.className = 'addedOption';
            nameCell.textContent = optionName;

            const requiredCell = document.createElement('td');
            const requiredSelect = document.createElement('select');
            requiredSelect.className = 'form-select';
            requiredSelect.name = fieldName('required');
            requiredSelect.append(
                new Option(<?php echo json_encode(Text::_('JNO')); ?>, '0'),
                new Option(<?php echo json_encode(Text::_('JYES')); ?>, '1')
            );
            requiredCell.append(requiredSelect);

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

            newRow.append(nameCell, requiredCell, orderingCell, actionsCell);

            // Insert before the add options row
            const insertBeforeRow = document.querySelector('.j2commerce_a_options');
            if (insertBeforeRow) {
                insertBeforeRow.parentNode.insertBefore(newRow, insertBeforeRow);
            }

            optionKey++;
            syncOptionSelect();
        });
    }

    // Remove option handler (event delegation for both existing and dynamically added rows).
    // Scoped to this fieldset like the sibling editors. Only one editor renders per page, so
    // this is parity rather than a fix: the narrowest listener that can serve every row.
    document.getElementById('j2commerce-product-options')?.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.optionRemove');
        if (!removeBtn) return;

        e.preventDefault();
        const row = removeBtn.closest('tr');
        if (row) {
            // Track deleted option ID for persistence on save
            const optionId = removeBtn.getAttribute('data-option-id');
            if (optionId) {
                const deletedField = document.getElementById('j2commerce-deleted-options');
                if (deletedField) {
                    const currentValue = deletedField.value;
                    const deletedIds = currentValue ? currentValue.split(',') : [];
                    // Only add if this is an existing option (numeric ID), not a new one
                    if (!isNaN(parseInt(optionId)) && parseInt(optionId) > 0 && !deletedIds.includes(optionId)) {
                        deletedIds.push(optionId);
                        deletedField.value = deletedIds.join(',');
                    }
                }
            }

            // Fade out effect
            row.style.transition = 'opacity 0.3s';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                // Re-offer the option only once the row is really gone, so the dropdown and
                // the duplicate guard never disagree during the fade.
                clearOptionNotice();
                syncOptionSelect();
            }, 300);
        }
    });

    // Option Values Modal - AJAX loading (no iframe)
    const optionValuesModal = document.getElementById('j2commerceOptionValuesModal');
    const optionValuesModalBody = document.getElementById('j2commerceOptionValuesModalBody');
    const modalLabel = document.getElementById('optionValuesModalLabel');
    let modalInstance = null;
    let currentProductId = null;
    let currentProductOptionId = null;

    // Show loading state in modal
    function showModalLoading() {
        const wrapper = document.createElement('div');
        wrapper.className = 'text-center py-5';

        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-primary';
        spinner.setAttribute('role', 'status');

        const spinnerLabel = document.createElement('span');
        spinnerLabel.className = 'visually-hidden';
        spinnerLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING')); ?>;
        spinner.append(spinnerLabel);

        const caption = document.createElement('p');
        caption.className = 'mt-2 text-body-secondary';
        caption.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING')); ?>;

        wrapper.append(spinner, caption);
        optionValuesModalBody.replaceChildren(wrapper);
    }

    // Show error in modal
    function showModalError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';

        const icon = document.createElement('span');
        icon.className = 'icon-warning';
        alert.append(icon, ' ' + message);

        optionValuesModalBody.replaceChildren(alert);
    }

    // Show success message in modal
    function showModalMessage(message, type = 'success') {
        const messagesContainer = document.getElementById('j2commerce-optionvalues-messages');
        if (messagesContainer) {
            // Mirrors the sibling editors: only known contextual classes are accepted.
            const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info';

            const alertBox = document.createElement('div');
            alertBox.className = 'alert alert-' + safeType + ' alert-dismissible fade show';
            alertBox.setAttribute('role', 'alert');
            alertBox.append(message);

            const dismissButton = document.createElement('button');
            dismissButton.type = 'button';
            dismissButton.className = 'btn-close';
            dismissButton.setAttribute('data-bs-dismiss', 'alert');
            dismissButton.setAttribute('aria-label', <?php echo json_encode(Text::_('JCLOSE')); ?>);
            alertBox.append(dismissButton);

            messagesContainer.replaceChildren(alertBox);
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                const alert = messagesContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 3000);
        }
    }

    // Load option values content via AJAX
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
                J2CommerceDom.adopt(optionValuesModalBody, data.html);
                modalLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR')); ?> + ': ' + data.optionName;

                // Initialize event handlers for the injected content
                initOptionValuesHandlers();
            } else {
                showModalError(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT')); ?>);
            }
        } catch (error) {
            console.error('Error loading option values:', error);
            showModalError(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT')); ?>);
        }
    }

    // Initialize event handlers for AJAX-loaded content
    function initOptionValuesHandlers() {
        const container = document.querySelector('.j2commerce-ajax-optionvalues');
        if (!container) return;

        const productId = container.dataset.productId;
        const productOptionId = container.dataset.productoptionId;

        // The tbody is replaced on every load, so the hidden ordering inputs are restamped here.
        window.J2CommerceOptionValuesSortable?.renumber();

        // Create new option value
        const createBtn = document.getElementById('j2commerce-create-optionvalue-btn');
        if (createBtn) {
            createBtn.addEventListener('click', async () => {
                createBtn.disabled = true;
                setSpinnerLabel(createBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVING')); ?>);

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
                // Drag position owns ordering, so a new value lands after the ones already listed.
                formData.append('ordering', window.J2CommerceOptionValuesSortable?.count() ?? 0);
                formData.append('product_optionvalue_attribs', document.getElementById('j2commerce_new_attribs')?.value || '');
                formData.append(csrfToken, 1);

                // Handle parent option values (multi-select)
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
                        // Reload the content to show the new value
                        await loadOptionValuesContent(productId, productOptionId);
                        showModalMessage(data.message, 'success');
                    } else {
                        showModalMessage(data.message, 'danger');
                        createBtn.disabled = false;
                        setIconLabel(createBtn, 'icon-plus', <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION')); ?>);
                    }
                } catch (error) {
                    console.error('Error creating option value:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_SAVING')); ?>, 'danger');
                    createBtn.disabled = false;
                    setIconLabel(createBtn, 'icon-plus', <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION')); ?>);
                }
            });
        }

        // Add all option values
        const addAllBtn = document.getElementById('j2commerce-add-all-optionvalues-btn');
        if (addAllBtn) {
            addAllBtn.addEventListener('click', async () => {
                addAllBtn.disabled = true;
                setSpinnerLabel(addAllBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING')); ?>);

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
                        // Reload the content
                        await loadOptionValuesContent(productId, productOptionId);
                        showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ALL_OPTION_VALUES_ADDED')); ?>, 'success');
                    } else {
                        showModalMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED')); ?>, 'danger');
                        addAllBtn.disabled = false;
                        setIconLabel(addAllBtn, 'icon-list', <?php echo json_encode(Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE')); ?>);
                    }
                } catch (error) {
                    console.error('Error adding all option values:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED')); ?>, 'danger');
                    addAllBtn.disabled = false;
                    setIconLabel(addAllBtn, 'icon-list', <?php echo json_encode(Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE')); ?>);
                }
            });
        }

        // Save option values
        const saveBtn = document.getElementById('j2commerce-save-optionvalues-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVING')); ?>;

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', 'products.saveProductOptionValueAjax');
                formData.append(csrfToken, 1);

                // Collect all form fields from the table
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
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_SAVING')); ?>, 'danger');
                }

                saveBtn.disabled = false;
                saveBtn.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVE_CHANGES')); ?>;
            });
        }

        // Delete option value
        document.querySelectorAll('.j2commerce-delete-optionvalue-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_CONFIRM_DELETE')); ?>)) {
                    return;
                }

                const povId = btn.dataset.povId;
                btn.disabled = true;
                setSpinnerOnly(btn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING')); ?>);

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
                        // Remove the row with animation
                        const row = btn.closest('tr');
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            // Check if table is empty
                            const tbody = document.getElementById('j2commerce-optionvalues-tbody');
                            if (tbody && tbody.querySelectorAll('tr[data-pov-id]').length === 0) {
                                const emptyRow = document.createElement('tr');
                                emptyRow.className = 'j2commerce-no-values-row';

                                const emptyCell = document.createElement('td');
                                emptyCell.colSpan = 10;
                                emptyCell.className = 'text-center text-body-secondary py-4';
                                emptyCell.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_NO_OPTION_VALUES_ASSIGNED')); ?>;

                                emptyRow.append(emptyCell);
                                tbody.replaceChildren(emptyRow);
                            }
                        }, 300);
                        showModalMessage(data.message, 'success');
                    } else {
                        showModalMessage(data.message, 'danger');
                        btn.disabled = false;
                        setIconLabel(btn, 'icon-trash');
                    }
                } catch (error) {
                    console.error('Error deleting option value:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED')); ?>, 'danger');
                    btn.disabled = false;
                    setIconLabel(btn, 'icon-trash');
                }
            });
        });

        // Paint one star from the state the server just confirmed.
        const applyDefaultState = (button, isDefault) => {
            button.dataset.isDefault = isDefault ? '1' : '0';
            button.classList.toggle('text-warning', isDefault);
            button.classList.toggle('text-body-secondary', !isDefault);
            button.title = isDefault ? <?php echo json_encode(Text::_('COM_J2COMMERCE_UNSET_DEFAULT')); ?> : <?php echo json_encode(Text::_('COM_J2COMMERCE_SET_AS_DEFAULT')); ?>;
            const icon = button.querySelector('span');
            if (icon) icon.className = isDefault ? 'icon-star' : 'icon-star-empty';
        };

        document.querySelectorAll('.j2commerce-set-default-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const povId = btn.dataset.povId;
                const wasDefault = btn.dataset.isDefault === '1';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('option', 'com_j2commerce');
                formData.append('task', wasDefault ? 'products.unsetDefault' : 'products.setDefault');
                formData.append('product_id', productId);
                formData.append('productoption_id', productOptionId);
                formData.append('cid[]', povId);
                formData.append(csrfToken, 1);

                try {
                    const response = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        // A single-choice option carries one default, so the star this
                        // click claimed was released from whichever value held it.
                        if (data.is_default && data.exclusive) {
                            document.querySelectorAll('.j2commerce-set-default-btn').forEach(b => applyDefaultState(b, false));
                        }
                        applyDefaultState(btn, !!data.is_default);
                        if (data.is_default) {
                            showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_DEFAULT_SET_SUCCESSFULLY')); ?>, 'success');
                        }
                    } else {
                        showModalMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED')); ?>, 'danger');
                    }
                } catch (error) {
                    console.error('Error changing default:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED')); ?>, 'danger');
                }

                btn.disabled = false;
            });
        });
    }

    // Event delegation for option values links
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.j2commerce-option-values-link');
        if (!link) return;

        e.preventDefault();
        const productId = link.getAttribute('data-product-id');
        const productOptionId = link.getAttribute('data-option-id');
        const optionName = link.getAttribute('data-option-name');

        currentProductId = productId;
        currentProductOptionId = productOptionId;

        // Update modal title immediately
        modalLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR')); ?> + ': ' + optionName;

        // Show modal
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(optionValuesModal);
        }
        modalInstance.show();

        // Load content via AJAX
        loadOptionValuesContent(productId, productOptionId);
    });

    // Clear modal content when hidden
    optionValuesModal.addEventListener('hidden.bs.modal', () => {
        // Don't reload the page - just clear the modal back to its loading state
        showModalLoading();
        currentProductId = null;
        currentProductOptionId = null;
    });
});
</script>
