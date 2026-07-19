/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adminForm');
    if (!form) return;

    const orderId = parseInt(form.dataset.orderId, 10);
    const token = form.dataset.token;
    const currency = form.dataset.currency || '';

    const translate = (key, fallback) => (typeof Joomla !== 'undefined' && Joomla.Text ? Joomla.Text._(key, fallback) : fallback);

    const formatMoney = (value) => `${currency} ${Number(value).toFixed(2)}`;

    function showMessage(type, text) {
        const container = document.getElementById('system-message-container');
        if (container) {
            container.replaceChildren();
        }
        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            Joomla.renderMessages({ [type]: [text] });
        }
    }

    async function postAjax(task, body = {}) {
        const formData = new FormData();
        formData.append(token, '1');
        formData.append('order_id', orderId.toString());

        for (const [key, value] of Object.entries(body)) {
            if (Array.isArray(value)) {
                value.forEach((entry) => formData.append(`${key}[]`, String(entry)));
            } else {
                formData.append(key, String(value));
            }
        }

        const response = await fetch(`index.php?option=com_j2commerce&task=order.${task}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token },
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    // Serialize the editable tab fields (jform + item qty/price inputs)
    function collectEditData() {
        const data = {};

        form.querySelectorAll('input[name^="jform["], select[name^="jform["], textarea[name^="jform["]').forEach((field) => {
            data[field.name] = field.value;
        });

        form.querySelectorAll('input[name^="orderitem_qty["], input[name^="orderitem_price_edit["]').forEach((field) => {
            data[field.name] = field.value;
        });

        return data;
    }

    async function postEditData(task, extra = {}) {
        const formData = new FormData();
        formData.append(token, '1');
        formData.append('order_id', orderId.toString());

        for (const [name, value] of Object.entries(collectEditData())) {
            formData.append(name, value);
        }

        for (const [key, value] of Object.entries(extra)) {
            if (Array.isArray(value)) {
                value.forEach((entry) => formData.append(`${key}[]`, String(entry)));
            } else {
                formData.append(key, String(value));
            }
        }

        const response = await fetch(`index.php?option=com_j2commerce&task=order.${task}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token },
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    // === Summary totals refresh ===
    function updateSummary(totals) {
        if (!totals) return;

        const fmt = (name) => totals.formatted?.[name] ?? formatMoney(totals[name]);

        const subtotalCell = document.getElementById('summarySubtotal');
        if (subtotalCell) {
            subtotalCell.textContent = fmt('subtotal');
        }

        const rows = [
            ['summaryShippingRow', 'summaryShipping', 'shipping', ''],
            ['summarySurchargeRow', 'summarySurcharge', 'surcharge', ''],
            ['summaryDiscountRow', 'summaryDiscount', 'discount', '-'],
            ['summaryTaxRow', 'summaryTax', 'tax', ''],
            ['summaryFeesRow', 'summaryFees', 'fees', ''],
        ];

        for (const [rowId, cellId, name, prefix] of rows) {
            const row = document.getElementById(rowId);
            const cell = document.getElementById(cellId);
            if (!cell) continue;
            cell.textContent = `${prefix}${fmt(name)}`;
            row?.classList.toggle('d-none', Number(totals[name]) <= 0);
        }

        const totalCell = document.querySelector('#summaryTotal strong') || document.getElementById('summaryTotal');
        if (totalCell) {
            totalCell.textContent = fmt('total');
        }
    }

    // === Tab navigation (uitab renders role="tab" buttons inside the form) ===
    function getTabButtons() {
        return Array.from(form.querySelectorAll('[role="tab"]'));
    }

    function activeTabIndex(buttons) {
        return buttons.findIndex(
            (btn) => btn.getAttribute('aria-selected') === 'true'
                || btn.getAttribute('aria-expanded') === 'true'
                || btn.hasAttribute('active')
                || btn.classList.contains('active')
        );
    }

    document.addEventListener('click', async (e) => {
        const navBtn = e.target.closest('[data-j2c-nav]');
        if (!navBtn) return;

        e.preventDefault();

        const direction = navBtn.dataset.j2cNav === 'next' ? 1 : -1;
        const buttons = getTabButtons();
        const current = activeTabIndex(buttons);
        const target = buttons[current + direction];

        navBtn.disabled = true;

        try {
            const result = await postEditData('ajaxSaveOrderEdit');

            if (!result.success) {
                showMessage('error', result.message || translate('COM_J2COMMERCE_ERROR_INVALID_REQUEST', 'Invalid request'));
                return;
            }

            updateSummary(result.totals);
            showMessage('message', result.message);

            if (target) {
                target.click();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            navBtn.disabled = false;
        }
    });

    // === Update items (quantity / unit price) ===
    const updateItemsBtn = document.getElementById('updateItemsBtn');
    if (updateItemsBtn) {
        updateItemsBtn.addEventListener('click', async () => {
            updateItemsBtn.disabled = true;

            try {
                const result = await postEditData('ajaxUpdateItems');

                if (!result.success) {
                    showMessage('error', result.message);
                    return;
                }

                if (result.lines) {
                    for (const [itemId, line] of Object.entries(result.lines)) {
                        const row = form.querySelector(`tr[data-item-id="${itemId}"]`);
                        if (!row) continue;

                        const totalCell = row.querySelector('.j2c-line-total');
                        if (totalCell) {
                            totalCell.textContent = line.finalprice_formatted ?? formatMoney(line.finalprice);
                        }
                    }
                }

                updateSummary(result.totals);
                showMessage('message', result.message);
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            } finally {
                updateItemsBtn.disabled = false;
            }
        });
    }

    // === Remove selected items ===
    const removeItemsBtn = document.getElementById('removeItemsBtn');
    if (removeItemsBtn) {
        removeItemsBtn.addEventListener('click', async () => {
            const checked = Array.from(form.querySelectorAll('input[name="cid[]"]:checked')).map((cb) => cb.value);

            if (!checked.length) {
                showMessage('warning', translate('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST', 'Please first make a selection from the list.'));
                return;
            }

            if (!window.confirm(translate('COM_J2COMMERCE_CONFIRM_REMOVE_ITEMS', 'Remove the selected items from this order?'))) {
                return;
            }

            removeItemsBtn.disabled = true;

            try {
                const result = await postAjax('ajaxRemoveItems', { cid: checked });

                if (!result.success) {
                    showMessage('error', result.message);
                    return;
                }

                window.location.reload();
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            } finally {
                removeItemsBtn.disabled = false;
            }
        });
    }

    // === SKU / product search + add to order ===
    const skuInput = document.getElementById('skuSearchInput');
    const skuBtn = document.getElementById('skuSearchBtn');
    const skuResults = document.getElementById('skuSearchResults');

    function renderSearchResults(results) {
        if (!skuResults) return;

        skuResults.replaceChildren();

        if (!results.length) {
            const empty = document.createElement('div');
            empty.className = 'alert alert-info mb-0';
            empty.textContent = translate('JGLOBAL_NO_MATCHING_RESULTS', 'No matching results');
            skuResults.appendChild(empty);
            return;
        }

        const list = document.createElement('div');
        list.className = 'list-group';

        results.forEach((product) => {
            const row = document.createElement('div');
            row.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';

            const info = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = product.name;
            const meta = document.createElement('div');
            meta.className = 'small text-body-secondary';
            meta.textContent = `${product.sku} — ${formatMoney(product.price)}`;
            info.appendChild(name);
            info.appendChild(meta);

            const controls = document.createElement('div');
            controls.className = 'd-flex align-items-center gap-2';

            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.className = 'form-control form-control-sm';
            qtyInput.style.width = '70px';
            qtyInput.min = '1';
            qtyInput.value = '1';
            qtyInput.setAttribute('aria-label', translate('COM_J2COMMERCE_HEADING_QTY', 'Qty'));

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn btn-sm btn-primary';
            addBtn.dataset.variantId = String(product.variant_id);
            addBtn.textContent = translate('COM_J2COMMERCE_ADD_TO_ORDER', 'Add to Order');

            controls.appendChild(qtyInput);
            controls.appendChild(addBtn);
            row.appendChild(info);
            row.appendChild(controls);
            list.appendChild(row);
        });

        skuResults.appendChild(list);
    }

    async function runSkuSearch() {
        const term = (skuInput?.value || '').trim();
        if (!term) return;

        try {
            const result = await postAjax('ajaxSearchProducts', { term });

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            renderSearchResults(result.results || []);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    }

    skuBtn?.addEventListener('click', runSkuSearch);
    skuInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            runSkuSearch();
        }
    });

    skuResults?.addEventListener('click', async (e) => {
        const addBtn = e.target.closest('button[data-variant-id]');
        if (!addBtn) return;

        addBtn.disabled = true;

        const qtyField = addBtn.closest('.list-group-item')?.querySelector('input[type="number"]');
        const quantity = Math.max(1, parseInt(qtyField?.value || '1', 10) || 1);

        try {
            const result = await postAjax('ajaxAddOrderItem', {
                variant_id: addBtn.dataset.variantId,
                quantity,
            });

            if (!result.success) {
                showMessage('error', result.message);
                addBtn.disabled = false;
                return;
            }

            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            addBtn.disabled = false;
        }
    });

    // === Recalculate totals ===
    const recalculateBtn = document.getElementById('recalculateBtn');
    if (recalculateBtn) {
        recalculateBtn.addEventListener('click', async () => {
            recalculateBtn.disabled = true;

            try {
                const result = await postAjax('ajaxRecalculate');

                if (!result.success) {
                    showMessage('error', result.message);
                    return;
                }

                updateSummary(result.totals);
                showMessage('message', result.message);
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            } finally {
                recalculateBtn.disabled = false;
            }
        });
    }

    // === Address editing ===
    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-j2c-address-edit]');
        if (editBtn) {
            const type = editBtn.dataset.j2cAddressEdit;
            document.getElementById(`${type}AddressForm`)?.classList.toggle('d-none');
            document.getElementById(`${type}SavedAddresses`)?.classList.add('d-none');
            return;
        }

        const cancelBtn = e.target.closest('[data-j2c-address-cancel]');
        if (cancelBtn) {
            document.getElementById(`${cancelBtn.dataset.j2cAddressCancel}AddressForm`)?.classList.add('d-none');
        }
    });

    // Country → zone cascade inside the address forms
    document.addEventListener('change', async (e) => {
        const select = e.target.closest('select[data-address-field="country_id"]');
        if (!select) return;

        const formCard = select.closest('[data-address-type]');
        const zoneSelect = formCard?.querySelector('select[data-address-field="zone_id"]');
        if (!zoneSelect) return;

        try {
            const result = await postAjax('ajaxGetZones', { country_id: select.value });
            zoneSelect.replaceChildren();

            const placeholder = document.createElement('option');
            placeholder.value = '0';
            placeholder.textContent = translate('JGLOBAL_SELECT_AN_OPTION', '- Select -');
            zoneSelect.appendChild(placeholder);

            (result.zones || []).forEach((zone) => {
                const option = document.createElement('option');
                option.value = String(zone.j2commerce_zone_id);
                option.textContent = zone.zone_name;
                zoneSelect.appendChild(option);
            });
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    });

    // Save address form
    document.addEventListener('click', async (e) => {
        const saveBtn = e.target.closest('[data-j2c-address-save]');
        if (!saveBtn) return;

        const type = saveBtn.dataset.j2cAddressSave;
        const formCard = document.getElementById(`${type}AddressForm`);
        if (!formCard) return;

        const body = { address_type: type };
        formCard.querySelectorAll('[data-address-field]').forEach((field) => {
            body[`address[${field.dataset.addressField}]`] = field.value;
        });

        saveBtn.disabled = true;

        try {
            const result = await postAjax('ajaxSaveAddress', body);

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            saveBtn.disabled = false;
        }
    });

    // Choose from the customer's saved addresses
    document.addEventListener('click', async (e) => {
        const chooseBtn = e.target.closest('[data-j2c-address-choose]');
        const applyBtn = e.target.closest('[data-j2c-address-apply]');

        if (chooseBtn) {
            const type = chooseBtn.dataset.j2cAddressChoose;
            const container = document.getElementById(`${type}SavedAddresses`);
            if (!container) return;

            if (!container.classList.contains('d-none')) {
                container.classList.add('d-none');
                return;
            }

            document.getElementById(`${type}AddressForm`)?.classList.add('d-none');

            try {
                const result = await postAjax('ajaxGetSavedAddresses', {});

                if (!result.success) {
                    showMessage('error', result.message);
                    return;
                }

                container.replaceChildren();

                if (!(result.addresses || []).length) {
                    const empty = document.createElement('div');
                    empty.className = 'alert alert-info mb-0';
                    empty.textContent = translate('COM_J2COMMERCE_NO_SAVED_ADDRESSES', 'This customer has no saved addresses.');
                    container.appendChild(empty);
                } else {
                    const list = document.createElement('div');
                    list.className = 'list-group';

                    result.addresses.forEach((address) => {
                        const row = document.createElement('div');
                        row.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';

                        const info = document.createElement('div');
                        const name = document.createElement('strong');
                        name.textContent = `${address.first_name} ${address.last_name}`.trim();
                        const detail = document.createElement('div');
                        detail.className = 'small text-body-secondary';
                        detail.textContent = [address.address_1, address.city, address.zone_name, address.zip, address.country_name]
                            .filter(Boolean).join(', ');
                        info.appendChild(name);
                        info.appendChild(detail);

                        const useBtn = document.createElement('button');
                        useBtn.type = 'button';
                        useBtn.className = 'btn btn-sm btn-primary';
                        useBtn.dataset.j2cAddressApply = String(address.j2commerce_address_id);
                        useBtn.dataset.addressType = type;
                        useBtn.textContent = translate('COM_J2COMMERCE_USE_THIS_ADDRESS', 'Use this address');

                        row.appendChild(info);
                        row.appendChild(useBtn);
                        list.appendChild(row);
                    });

                    container.appendChild(list);
                }

                container.classList.remove('d-none');
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            }

            return;
        }

        if (applyBtn) {
            applyBtn.disabled = true;

            try {
                const result = await postAjax('ajaxApplySavedAddress', {
                    address_type: applyBtn.dataset.addressType,
                    address_id: applyBtn.dataset.j2cAddressApply,
                });

                if (!result.success) {
                    showMessage('error', result.message);
                    applyBtn.disabled = false;
                    return;
                }

                window.location.reload();
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
                applyBtn.disabled = false;
            }
        }
    });

    // === Coupon / voucher apply ===
    async function applyDiscountCode(task, field, value) {
        try {
            const result = await postAjax(task, { [field]: value });

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    }

    document.getElementById('applyCouponBtn')?.addEventListener('click', () => {
        const code = (document.getElementById('couponCode')?.value || '').trim();
        if (code) applyDiscountCode('ajaxApplyCoupon', 'coupon_code', code);
    });

    document.getElementById('applyVoucherBtn')?.addEventListener('click', () => {
        const code = (document.getElementById('voucherCode')?.value || '').trim();
        if (code) applyDiscountCode('ajaxApplyVoucher', 'voucher_code', code);
    });

    // === Remove discount / fee (delegated on summary lists) ===
    document.addEventListener('click', async (e) => {
        const removeDiscountBtn = e.target.closest('[data-j2c-remove-discount]');
        const removeFeeBtn = e.target.closest('[data-j2c-remove-fee]');
        if (!removeDiscountBtn && !removeFeeBtn) return;

        const isDiscount = Boolean(removeDiscountBtn);
        const confirmKey = isDiscount ? 'COM_J2COMMERCE_CONFIRM_REMOVE_DISCOUNT' : 'COM_J2COMMERCE_CONFIRM_REMOVE_FEE';

        if (!window.confirm(translate(confirmKey, 'Remove this entry from the order?'))) {
            return;
        }

        const btn = removeDiscountBtn || removeFeeBtn;
        btn.disabled = true;

        try {
            const result = isDiscount
                ? await postAjax('ajaxRemoveDiscount', { discount_id: btn.dataset.j2cRemoveDiscount })
                : await postAjax('ajaxRemoveFee', { fee_id: btn.dataset.j2cRemoveFee });

            if (!result.success) {
                showMessage('error', result.message);
                btn.disabled = false;
                return;
            }

            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            btn.disabled = false;
        }
    });

    // === Add fee ===
    document.getElementById('addFeeBtn')?.addEventListener('click', async () => {
        const name = (document.getElementById('feeName')?.value || '').trim();
        const amount = document.getElementById('feeAmount')?.value || '';

        if (!name || !amount) {
            showMessage('warning', translate('COM_J2COMMERCE_ERROR_INVALID_REQUEST', 'Invalid request'));
            return;
        }

        try {
            const result = await postAjax('ajaxAddFee', { fee_name: name, fee_amount: amount });

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    });

    // === Inventory adjust buttons (Items tab) ===
    document.addEventListener('click', async (e) => {
        const stockBtn = e.target.closest('.j2c-stock-btn');
        if (!stockBtn) return;

        stockBtn.disabled = true;

        try {
            const result = await postAjax('ajaxAdjustStock', {
                item_id: stockBtn.dataset.itemId,
                direction: stockBtn.dataset.direction,
            });

            showMessage(result.success ? 'message' : 'error', result.message);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            stockBtn.disabled = false;
        }
    });

    // === Save Order (summary tab) ===
    document.getElementById('saveOrderBtn')?.addEventListener('click', () => {
        if (typeof Joomla !== 'undefined' && Joomla.submitbutton) {
            Joomla.submitbutton('order.save');
        }
    });

    // === Validation helpers (kept from the original implementation) ===
    form.addEventListener('invalid', (e) => {
        const field = e.target;
        if (!field) return;

        field.classList.add('is-invalid');

        const tabPane = field.closest('[role="tabpanel"], .tab-pane, joomla-tab-element');
        if (tabPane && tabPane.id) {
            const trigger = document.querySelector(`[role="tab"][aria-controls="${tabPane.id}"]`);
            trigger?.click();
        }
    }, true);

    form.addEventListener('input', (e) => {
        e.target.classList.remove('is-invalid');
    });
});
