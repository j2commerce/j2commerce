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
use Joomla\CMS\Session\Session;

// Adopts the option-values markup this view fetches. Deferred, so it has run before any fetch callback.
// Drag-ordering is registered here too: the fragment is adopted as markup only, so its own asset
// registrations and scripts never reach the page.
Factory::getApplication()->getDocument()->getWebAssetManager()
    ->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true])
    ->registerAndUseScript('com_j2commerce.optionvalues-sortable', 'media/com_j2commerce/js/administrator/optionvalues-sortable.js', [], ['defer' => true])
    ->registerAndUseStyle('com_j2commerce.optionvalues-sortable', 'media/com_j2commerce/css/administrator/optionvalues-sortable.css');

$item        = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];

$productOptionList = J2CommerceHelper::product()->getProductOptionList($item->product_type);

// Options already on the product are offered as disabled entries rather than omitted,
// so the reason they cannot be picked again is visible in the list itself.
$assignedOptionIds = array_flip(array_map(
    static fn($poption): int => (int) $poption->option_id,
    (array) ($item->product_options ?? [])
));

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
                                                    <?php foreach ($productOptionList as $option_list) :
                                                        $optionLabel = $option_list->option_name . ' (' . $option_list->option_unique_name . ')';
                                                        $optionAssigned = isset($assignedOptionIds[(int) $option_list->j2commerce_option_id]);
                                                        ?>
                                                        <option value="<?php echo $option_list->j2commerce_option_id; ?>" data-option-label="<?php echo $this->escape($optionLabel); ?>"<?php echo $optionAssigned ? ' disabled' : ''; ?>><?php echo $this->escape($optionAssigned ? Text::sprintf('COM_J2COMMERCE_OPTION_LABEL_ALREADY_ADDED', $optionLabel) : $optionLabel); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" id="j2commerce_variable_add_option_btn" class="btn btn-primary"><?php echo Text::_('COM_J2COMMERCE_OPTIONS_ADD'); ?></button>
                                            </div>
                                            <div id="j2commerce-variable-option-notice" class="alert alert-warning mt-2 d-none" role="alert"></div>
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
        <span class="fas fa-solid fa-exclamation-circle me-3" aria-hidden="true"></span>
        <div><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIABLE_GENERATION_HELP_TEXT'); ?></div>
    </div>
</div>

<!-- AJAX Modal for Option Values -->
<div class="modal fade" id="j2commerceVariableOptionValuesModal" tabindex="-1" aria-labelledby="variableOptionValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="variableOptionValuesModalLabel"><?php echo Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceVariableOptionValuesModalBody" style="max-height: 70vh; overflow-y: auto;">
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

    const formPrefix = '<?php echo $formPrefix; ?>';
    const productId = <?php echo (int) ($item->j2commerce_product_id ?? 0); ?>;
    const csrfToken = '<?php echo $csrfToken; ?>';
    const variantTypes = ['select', 'radio', 'checkbox', 'color'];
    const optionSelect = document.getElementById('j2commerce_variable_option_select');
    const optionNotice = document.getElementById('j2commerce-variable-option-notice');
    const addOptionBtn = document.getElementById('j2commerce_variable_add_option_btn');
    const addedLabelFormat = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_LABEL_ALREADY_ADDED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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
            Array.from(document.querySelectorAll('#variable_options_table input[name$="[option_id]"]'))
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


    // Returns the row element itself, so the caller inserts it directly.
    function buildOptionRow(poId, optionId, optionName, uniqueName, optionType, ordering) {
        const fieldName = (suffix) => formPrefix + '[item_options][' + poId + '][' + suffix + ']';

        const hidden = (suffix, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = fieldName(suffix);
            input.value = value;

            return input;
        };

        const row = document.createElement('tr');
        row.id = 'pao_variable_option_' + poId;

        const nameCell = document.createElement('td');

        // Mirrors the server-rendered row above: name in a flex line, type on its own line.
        // A row added here has to survive a reload without changing shape.
        const nameLine = document.createElement('div');
        nameLine.className = 'd-flex align-items-center';

        const nameLabel = document.createElement('strong');
        nameLabel.textContent = optionName;

        const uniqueLabel = document.createElement('small');
        uniqueLabel.className = 'ms-1';
        uniqueLabel.textContent = '(' + uniqueName + ')';

        nameLine.append(
            nameLabel,
            hidden('j2commerce_productoption_id', poId),
            hidden('option_id', optionId),
            uniqueLabel
        );

        if (variantTypes.includes(optionType)) {
            const setValuesButton = document.createElement('button');
            setValuesButton.type = 'button';
            setValuesButton.className = 'small ms-2 ms-lg-3 btn btn-soft-dark btn-sm j2commerce-variable-option-values-link';
            setValuesButton.dataset.productId = productId;
            setValuesButton.dataset.optionId = poId;
            setValuesButton.dataset.optionName = optionName;

            const cogIcon = document.createElement('span');
            cogIcon.className = 'icon-cog me-1';
            setValuesButton.append(cogIcon, <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_SET_VALUES'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

            nameLine.append(setValuesButton);
        }

        const typeLine = document.createElement('div');
        const typeLabel = document.createElement('small');
        typeLabel.className = 'text-capitalize';
        typeLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_TYPE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + optionType;
        typeLine.append(typeLabel);

        nameCell.append(nameLine, typeLine);

        const orderingCell = document.createElement('td');
        const orderingInput = document.createElement('input');
        orderingInput.type = 'text';
        orderingInput.className = 'form-control';
        orderingInput.name = fieldName('ordering');
        orderingInput.value = ordering;
        orderingCell.append(orderingInput);

        const actionsCell = document.createElement('td');
        actionsCell.className = 'text-end';

        const removeButton = document.createElement('span');
        removeButton.className = 'optionRemove btn btn-soft-danger btn-sm';
        removeButton.dataset.optionId = poId;
        removeButton.setAttribute('role', 'button');
        removeButton.title = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTION_REMOVE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        const removeIcon = document.createElement('span');
        removeIcon.className = 'icon icon-trash';
        removeButton.append(removeIcon);
        actionsCell.append(removeButton);

        row.append(nameCell, orderingCell, actionsCell);

        return row;
    }

    // AJAX Add option button handler
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', async () => {
            if (!optionSelect) return;

            const optionId = optionSelect.value;
            if (!optionId) return;

            clearOptionNotice();
            addOptionBtn.disabled = true;
            setSpinnerOnly(addOptionBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

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
                        const newRow = buildOptionRow(
                            data.productoption_id,
                            optionId,
                            data.option_name,
                            data.option_unique_name,
                            data.option_type,
                            data.ordering
                        );
                        insertBeforeRow.parentNode.insertBefore(newRow, insertBeforeRow);
                    }
                } else {
                    // The server rejects a duplicate on its own; surface that beside the control.
                    showOptionNotice(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                }
            } catch (error) {
                console.error('Error adding option:', error);
                showOptionNotice(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }

            addOptionBtn.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_OPTIONS_ADD'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            addOptionBtn.disabled = false;
            syncOptionSelect();
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
            clearOptionNotice();
            syncOptionSelect();
            return;
        }

        setSpinnerOnly(removeBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

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
                setTimeout(() => {
                    row.remove();
                    // Re-offer the option only once the row is really gone, so the dropdown
                    // and the server's own duplicate check never disagree during the fade.
                    clearOptionNotice();
                    syncOptionSelect();
                }, 300);
            } else {
                showOptionNotice(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                setIconLabel(removeBtn, 'icon icon-trash');
            }
        } catch (error) {
            console.error('Error removing option:', error);
            setIconLabel(removeBtn, 'icon icon-trash');
        }
    });

    // Option Values Modal
    const optionValuesModal = document.getElementById('j2commerceVariableOptionValuesModal');
    const optionValuesModalBody = document.getElementById('j2commerceVariableOptionValuesModalBody');
    const modalLabel = document.getElementById('variableOptionValuesModalLabel');
    let modalInstance = null;

    function buildLoadingIndicator() {
        const wrapper = document.createElement('div');
        wrapper.className = 'text-center py-5';

        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-primary';
        spinner.setAttribute('role', 'status');

        const spinnerLabel = document.createElement('span');
        spinnerLabel.className = 'visually-hidden';
        spinnerLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        spinner.append(spinnerLabel);

        const caption = document.createElement('p');
        caption.className = 'mt-2 text-body-secondary';
        caption.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        wrapper.append(spinner, caption);

        return wrapper;
    }

    function showModalLoading() {
        optionValuesModalBody.replaceChildren(buildLoadingIndicator());
    }

    function showModalError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';

        const icon = document.createElement('span');
        icon.className = 'icon-warning';
        alert.append(icon, ' ' + message);

        optionValuesModalBody.replaceChildren(alert);
    }

    function showModalMessage(message, type = 'success') {
        const messagesContainer = document.getElementById('j2commerce-optionvalues-messages');
        if (messagesContainer) {
            const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info';
            const alertBox = document.createElement('div');
            alertBox.className = 'alert alert-' + safeType + ' alert-dismissible fade show';
            alertBox.setAttribute('role', 'alert');
            alertBox.append(message);

            const dismissButton = document.createElement('button');
            dismissButton.type = 'button';
            dismissButton.className = 'btn-close';
            dismissButton.setAttribute('data-bs-dismiss', 'alert');
            dismissButton.setAttribute('aria-label', <?php echo json_encode(Text::_('JCLOSE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            alertBox.append(dismissButton);

            messagesContainer.replaceChildren(alertBox);
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
                J2CommerceDom.adopt(optionValuesModalBody, data.html);
                modalLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + data.optionName;
                initOptionValuesHandlers();
            } else {
                showModalError(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
            }
        } catch (error) {
            console.error('Error loading option values:', error);
            showModalError(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_LOADING_CONTENT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    }

    function initOptionValuesHandlers() {
        const container = document.querySelector('.j2commerce-ajax-optionvalues');
        if (!container) return;

        const containerProductId = container.dataset.productId;
        const containerProductOptionId = container.dataset.productoptionId;

        // The tbody is replaced on every load, so the hidden ordering inputs are restamped here.
        window.J2CommerceOptionValuesSortable?.renumber();

        // Create option value
        const createBtn = document.getElementById('j2commerce-create-optionvalue-btn');
        if (createBtn) {
            createBtn.addEventListener('click', async () => {
                createBtn.disabled = true;
                setSpinnerLabel(createBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

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
                // Drag position owns ordering, so a new value lands after the ones already listed.
                formData.append('ordering', window.J2CommerceOptionValuesSortable?.count() ?? 0);
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
                        setIconLabel(createBtn, 'icon-plus', <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                    }
                } catch (error) {
                    console.error('Error creating option value:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_SAVING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
                    createBtn.disabled = false;
                    setIconLabel(createBtn, 'icon-plus', <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                }
            });
        }

        // Add all option values
        const addAllBtn = document.getElementById('j2commerce-add-all-optionvalues-btn');
        if (addAllBtn) {
            addAllBtn.addEventListener('click', async () => {
                addAllBtn.disabled = true;
                setSpinnerLabel(addAllBtn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

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
                        showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ALL_OPTION_VALUES_ADDED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
                    } else {
                        showModalMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
                        addAllBtn.disabled = false;
                        setIconLabel(addAllBtn, 'icon-list', <?php echo json_encode(Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                    }
                } catch (error) {
                    console.error('Error adding all option values:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
                    addAllBtn.disabled = false;
                    setIconLabel(addAllBtn, 'icon-list', <?php echo json_encode(Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                }
            });
        }

        // Save option values
        const saveBtn = document.getElementById('j2commerce-save-optionvalues-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_SAVING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
                }

                saveBtn.disabled = false;
                saveBtn.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_SAVE_CHANGES'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            });
        }

        // Delete individual option values
        document.querySelectorAll('.j2commerce-delete-optionvalue-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_CONFIRM_DELETE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)) return;

                const povId = btn.dataset.povId;
                btn.disabled = true;
                setSpinnerOnly(btn, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

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
                                const emptyRow = document.createElement('tr');
                                emptyRow.className = 'j2commerce-no-values-row';

                                const emptyCell = document.createElement('td');
                                emptyCell.colSpan = 10;
                                emptyCell.className = 'text-center text-body-secondary py-4';
                                emptyCell.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_NO_OPTION_VALUES_ASSIGNED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
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
            button.title = isDefault ? <?php echo json_encode(Text::_('COM_J2COMMERCE_UNSET_DEFAULT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> : <?php echo json_encode(Text::_('COM_J2COMMERCE_SET_AS_DEFAULT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
                formData.append('product_id', containerProductId);
                formData.append('productoption_id', containerProductOptionId);
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
                            showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_DEFAULT_SET_SUCCESSFULLY'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
                        }
                    } else {
                        showModalMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
                    }
                } catch (error) {
                    console.error('Error changing default:', error);
                    showModalMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_OCCURRED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'danger');
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

        modalLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ': ' + optionName;

        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(optionValuesModal);
        }
        modalInstance.show();

        loadOptionValuesContent(linkProductId, productOptionId);
    });

    // Reset modal content on hide
    optionValuesModal?.addEventListener('hidden.bs.modal', () => {
        optionValuesModalBody.replaceChildren(buildLoadingIndicator());
    });
});
</script>
