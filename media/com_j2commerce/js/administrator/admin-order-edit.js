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

    let orderId = parseInt(form.dataset.orderId, 10);
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
            if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) return;
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

    // Non-Basic tabs are disabled until the order exists, so a store owner
    // can't click ahead and trigger AJAX calls against order_id=0.
    function setTabsLocked(locked) {
        getTabButtons().forEach((btn, index) => {
            if (index === 0) return;
            btn.disabled = locked;
            btn.classList.toggle('disabled', locked);
        });
    }

    if (orderId < 1) {
        setTabsLocked(true);
    }

    document.addEventListener('click', async (e) => {
        const navBtn = e.target.closest('[data-j2c-nav]');
        if (!navBtn) return;

        e.preventDefault();

        const direction = navBtn.dataset.j2cNav === 'next' ? 1 : -1;
        const buttons = getTabButtons();
        const current = activeTabIndex(buttons);
        const currentKey = navBtn.closest('joomla-tab-element')?.id || buttons[current]?.getAttribute('aria-controls');

        // "Shipping same as billing" skips the Shipping step in both directions.
        const skipShipping = !!document.getElementById('j2c-same-as-shipping')?.checked;
        let targetIndex = current + direction;
        if (skipShipping && buttons[targetIndex]?.getAttribute('aria-controls') === 'shipping') {
            targetIndex += direction;
        }
        const target = buttons[targetIndex];

        // On the first Next (order not yet created) lock the whole footer, not just
        // the clicked button, so a second click can't fire a duplicate create.
        const navButtons = orderId < 1
            ? Array.from(form.querySelectorAll('[data-j2c-nav]'))
            : [navBtn];
        navButtons.forEach((b) => { b.disabled = true; });

        try {
            const result = await postEditData('ajaxSaveOrderEdit');

            if (!result.success) {
                showMessage('error', result.message || translate('COM_J2COMMERCE_ERROR_INVALID_REQUEST', 'Invalid request'));
                return;
            }

            if (result.created) {
                orderId = result.order_id;
                form.dataset.orderId = String(orderId);

                const idField = form.querySelector('input[name="id"]');
                if (idField) idField.value = String(orderId);

                // Reveal the Take Payment button (rendered hidden on the blank form).
                const payWrap = document.getElementById('j2c-take-payment-wrap');
                if (payWrap && result.take_payment_url) {
                    payWrap.querySelector('.j2c-take-payment')?.setAttribute('href', result.take_payment_url);
                    payWrap.classList.remove('d-none');
                }

                const orderIdField = document.getElementById('order_id');
                if (orderIdField && result.order_ref) orderIdField.value = result.order_ref;

                const customerNoteField = document.getElementById('customer_note');
                if (customerNoteField) customerNoteField.readOnly = true;

                if (result.redirect) {
                    window.history.replaceState(null, '', result.redirect);
                }

                setTabsLocked(false);
            }

            updateSummary(result.totals);
            showMessage('message', result.message);

            // Sync shipping = billing when leaving the Billing step with the box checked.
            if (skipShipping && direction === 1 && currentKey === 'billing' && orderId > 0) {
                try {
                    await postAjax('ajaxCopyBillingToShipping');
                } catch (err) {
                    // Non-fatal: the shipping copy is a convenience, not a hard requirement.
                }
            }

            if (target) {
                target.click();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            navButtons.forEach((b) => { b.disabled = false; });
        }
    });

    // ===================== Step 4: Order items (catalog + cart) =====================
    const cartLines      = document.getElementById('j2c-cart-lines');
    const cartEmpty      = document.getElementById('j2c-cart-empty');
    const unitsPill      = document.getElementById('j2c-units-pill');
    const catalogGrid    = document.getElementById('j2c-catalog-grid');
    const catalogEmpty   = document.getElementById('j2c-catalog-empty');
    const catalogPager   = document.getElementById('j2c-catalog-pager');
    const pagerInfo      = document.getElementById('j2c-pager-info');
    const skuInput       = document.getElementById('skuSearchInput');
    const skuBtn         = document.getElementById('skuSearchBtn');
    const currencySymbol = cartLines?.dataset.currencySymbol || currency;

    const searchState = { term: '', page: 1, totalPages: 1 };

    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    };
    const faIcon = (faClass) => {
        const s = document.createElement('span');
        s.className = `fa-solid ${faClass}`;
        s.setAttribute('aria-hidden', 'true');
        return s;
    };

    function refreshUnits() {
        if (!unitsPill) return;
        let total = 0;
        (cartLines?.querySelectorAll('.j2c-qty-input') || []).forEach((i) => { total += parseInt(i.value, 10) || 0; });
        unitsPill.classList.toggle('d-none', total <= 0);
        const key = total === 1 ? 'COM_J2COMMERCE_N_ITEMS_1' : 'COM_J2COMMERCE_N_ITEMS_MORE';
        unitsPill.textContent = translate(key, total === 1 ? '%d Item' : '%d Items').replace('%d', total);
    }

    // Colored live-stock badge (green > 0, red <= 0, neutral dash for non-managed variants).
    function applyStockBadge(badge, stock, manages) {
        badge.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-secondary');
        const label = translate('COM_J2COMMERCE_INVENTORY', 'Inventory');
        if (!manages) {
            badge.classList.add('text-bg-secondary');
            badge.textContent = `${label}: —`;
            return;
        }
        const n = parseInt(stock, 10) || 0;
        badge.classList.add(n > 0 ? 'text-bg-success' : 'text-bg-danger');
        badge.textContent = `${label}: ${n}`;
    }

    function buildStockBadge(id, stock, manages) {
        const badge = el('span', 'badge j2c-line-stock');
        badge.dataset.itemId = String(id);
        applyStockBadge(badge, stock, manages);
        return badge;
    }

    function toggleCartEmpty() {
        cartEmpty?.classList.toggle('d-none', !!cartLines?.querySelector('.j2c-line-row'));
    }

    // Build a cart line row matching the server-rendered structure so delegated handlers fire on it.
    function buildLineRow(line) {
        const row = el('div', 'j2c-line-row d-flex align-items-center gap-3 px-3 py-2 border-bottom');
        row.dataset.itemId = String(line.id);

        // Thumbnail: real product image, else icon-tile fallback.
        if (line.image_url) {
            const img = el('img', 'j2c-line-icon j2c-line-img');
            img.setAttribute('src', line.image_url);
            img.setAttribute('alt', line.name);
            img.setAttribute('loading', 'lazy');
            row.appendChild(img);
        } else {
            const iconTile = el('span', 'j2c-line-icon j2c-icon-tile bg-body-secondary text-body-secondary');
            iconTile.appendChild(faIcon('fa-box-open'));
            row.appendChild(iconTile);
        }

        const info = el('div', 'j2c-line-info flex-grow-1');
        info.style.minWidth = '0';

        // Name + inline stock badge.
        const nameRow = el('div', 'd-flex align-items-center gap-2');
        nameRow.appendChild(el('span', 'j2c-line-name fw-semibold text-truncate', line.name));
        const badge = buildStockBadge(line.id, line.stock, line.manages_stock);
        badge.classList.add('flex-shrink-0');
        nameRow.appendChild(badge);
        info.appendChild(nameRow);

        // Meta: clickable price toggle + "each · SKU".
        const meta = el('div', 'j2c-line-meta text-body-secondary small');
        const priceLink = el('a', 'j2c-price-toggle', line.price_formatted);
        priceLink.href = '#';
        priceLink.dataset.itemId = String(line.id);
        meta.appendChild(priceLink);
        meta.appendChild(document.createTextNode(
            ` ${translate('COM_J2COMMERCE_EACH', 'each')} · ${translate('COM_J2COMMERCE_EMAIL_SKU', 'SKU')} ${line.sku || ''}`
        ));
        info.appendChild(meta);

        // Attribute list (vertical) below the meta.
        if (Array.isArray(line.attributes) && line.attributes.length) {
            const ul = el('ul', 'j2c-line-attributes list-unstyled small text-body-secondary');
            line.attributes.forEach((a) => ul.appendChild(el('li', null, `${a.label}: ${a.value}`)));
            info.appendChild(ul);
        }

        // Hidden unit-price editor (revealed by the price toggle).
        const admin = el('div', 'j2c-line-admin d-flex align-items-center gap-2 mt-1 flex-wrap');
        const priceGrp = el('div', 'input-group input-group-sm j2c-line-price d-none');
        priceGrp.style.maxWidth = '150px';
        priceGrp.appendChild(el('span', 'input-group-text', currencySymbol));
        const priceInput = el('input', 'form-control j2c-price-input');
        priceInput.type = 'number';
        priceInput.step = '0.01';
        priceInput.min = '0';
        priceInput.name = `orderitem_price_edit[${line.id}]`;
        priceInput.value = line.price;
        priceInput.setAttribute('aria-label', translate('COM_J2COMMERCE_FIELD_UNIT_PRICE', 'Unit Price'));
        priceGrp.appendChild(priceInput);
        admin.appendChild(priceGrp);
        info.appendChild(admin);
        row.appendChild(info);

        const stepper = el('div', 'input-group input-group-sm j2c-qty-stepper');
        stepper.style.width = 'auto';
        stepper.style.flex = '0 0 auto';
        const dec = el('button', 'btn btn-light border j2c-qty-dec');
        dec.type = 'button';
        dec.dataset.itemId = String(line.id);
        dec.setAttribute('aria-label', '-');
        dec.appendChild(faIcon('fa-minus'));
        const val = el('span', 'input-group-text bg-white justify-content-center fw-semibold j2c-qty-value', String(line.quantity));
        val.style.minWidth = '42px';
        const inc = el('button', 'btn btn-light border j2c-qty-inc');
        inc.type = 'button';
        inc.dataset.itemId = String(line.id);
        inc.setAttribute('aria-label', '+');
        inc.appendChild(faIcon('fa-plus'));
        const hidden = el('input', 'j2c-qty-input');
        hidden.type = 'hidden';
        hidden.name = `orderitem_qty[${line.id}]`;
        hidden.value = String(line.quantity);
        stepper.appendChild(dec);
        stepper.appendChild(val);
        stepper.appendChild(inc);
        stepper.appendChild(hidden);
        row.appendChild(stepper);

        const total = el('div', 'j2c-line-total fw-bold text-end j2c-tabnum', line.finalprice_formatted);
        total.style.width = '90px';
        total.style.flex = '0 0 auto';
        row.appendChild(total);

        const remove = el('button', 'btn btn-sm j2c-line-remove');
        remove.type = 'button';
        remove.dataset.itemId = String(line.id);
        remove.title = translate('JACTION_DELETE', 'Delete');
        remove.setAttribute('aria-label', remove.title);
        const trash = faIcon('fa-trash');
        trash.classList.add('text-danger');
        remove.appendChild(trash);
        row.appendChild(remove);

        return row;
    }

    function renderCatalog(data) {
        if (!catalogGrid) return;
        catalogGrid.replaceChildren();
        const results = data.results || [];

        if (!results.length) {
            catalogGrid.classList.add('d-none');
            catalogPager?.classList.add('d-none');
            if (catalogEmpty) {
                catalogEmpty.classList.remove('d-none');
                catalogEmpty.replaceChildren(el('div', null, translate('JGLOBAL_NO_MATCHING_RESULTS', 'No matching results')));
            }
            return;
        }

        catalogEmpty?.classList.add('d-none');
        catalogGrid.classList.remove('d-none');

        results.forEach((product) => {
            const col = el('div', 'col');
            const tile = el('button', 'card h-100 w-100 border p-2 text-start j2c-catalog-tile');
            tile.type = 'button';
            tile.dataset.variantId = String(product.variant_id);


            const thumb = el('div', 'j2c-tile-thumb d-flex align-items-center justify-content-center rounded-1 bg-white text-body-secondary mb-2');
            if (product.image) {
                const img = el('img', 'j2c-tile-img img-fluid');
                img.setAttribute('src', product.image);
                img.setAttribute('alt', product.name || '');
                img.setAttribute('loading', 'lazy');
                thumb.appendChild(img);
            } else {
                const ti = faIcon('fa-box-open');
                ti.classList.add('fs-4');
                thumb.appendChild(ti);
            }
            tile.appendChild(thumb);

            const name = el('div', 'j2c-tile-name fw-semibold mb-2', product.name);
            name.style.cssText = 'font-size:15px;line-height:1.3;min-height:34px;';
            tile.appendChild(name);

            const foot = el('div', 'd-flex align-items-center justify-content-between mt-auto');
            const price = el('span', 'j2c-tile-price fw-bold', product.price_formatted);
            price.style.fontSize = '14px';
            foot.appendChild(price);
            foot.appendChild(el('span', 'badge bg-primary-subtle text-primary j2c-tile-add', `+ ${translate('COM_J2COMMERCE_ADD', 'Add')}`));
            tile.appendChild(foot);
            col.appendChild(tile);
            catalogGrid.appendChild(col);
        });

        searchState.page = data.page || 1;
        searchState.totalPages = data.totalPages || 1;

        if (catalogPager) {
            catalogPager.classList.toggle('d-none', searchState.totalPages <= 1);
            catalogPager.classList.toggle('d-flex', searchState.totalPages > 1);
            if (pagerInfo) pagerInfo.textContent = `${searchState.page} / ${searchState.totalPages}`;
            const prev = catalogPager.querySelector('.j2c-pager-prev');
            const next = catalogPager.querySelector('.j2c-pager-next');
            if (prev) prev.disabled = searchState.page <= 1;
            if (next) next.disabled = searchState.page >= searchState.totalPages;
        }
    }

    async function runProductSearch(page = 1) {
        const term = (skuInput?.value || '').trim();
        if (!term) return;
        searchState.term = term;

        try {
            const result = await postAjax('ajaxSearchProducts', { term, page });
            if (!result.success) { showMessage('error', result.message); return; }
            renderCatalog(result);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    }

    skuBtn?.addEventListener('click', () => runProductSearch(1));
    skuInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); runProductSearch(1); }
    });

    catalogPager?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-j2c-page]');
        if (!btn || btn.disabled) return;
        const next = btn.dataset.j2cPage === 'next' ? searchState.page + 1 : searchState.page - 1;
        if (next >= 1 && next <= searchState.totalPages) runProductSearch(next);
    });

    // Add product: click a catalog tile → AJAX add, append the line, keep the search results.
    catalogGrid?.addEventListener('click', async (e) => {
        const tile = e.target.closest('.j2c-catalog-tile');
        if (!tile) return;
        tile.disabled = true;

        try {
            const result = await postAjax('ajaxAddOrderItem', { variant_id: tile.dataset.variantId, quantity: 1 });
            if (!result.success) { showMessage('error', result.message); return; }

            if (result.line && cartLines) {
                cartLines.appendChild(buildLineRow(result.line));
                toggleCartEmpty();
                refreshUnits();
            }
            updateSummary(result.totals);
            showMessage('message', result.message);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            tile.disabled = false;
        }
    });

    // Re-persist qty/price for every line and refresh line totals + summary.
    async function applyItemChanges() {
        try {
            const result = await postEditData('ajaxUpdateItems');
            if (!result.success) { showMessage('error', result.message); return; }

            if (result.lines) {
                for (const [itemId, line] of Object.entries(result.lines)) {
                    const rowEl = cartLines?.querySelector(`.j2c-line-row[data-item-id="${itemId}"]`);
                    const totalCell = rowEl?.querySelector('.j2c-line-total');
                    if (totalCell) totalCell.textContent = line.finalprice_formatted ?? formatMoney(line.finalprice);
                    const badge = rowEl?.querySelector('.j2c-line-stock');
                    if (badge) applyStockBadge(badge, line.stock, line.manages_stock);
                }
            }
            updateSummary(result.totals);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        }
    }

    // Cart interactions (delegated so appended rows work): qty stepper + per-line remove.
    cartLines?.addEventListener('click', async (e) => {
        // Click the price under the title to reveal the hidden unit-price editor.
        const priceToggle = e.target.closest('.j2c-price-toggle');
        if (priceToggle) {
            e.preventDefault();
            const grp = priceToggle.closest('.j2c-line-row')?.querySelector('.j2c-line-price');
            if (grp) {
                grp.classList.toggle('d-none');
                if (!grp.classList.contains('d-none')) grp.querySelector('.j2c-price-input')?.focus();
            }
            return;
        }

        const stepBtn = e.target.closest('.j2c-qty-dec, .j2c-qty-inc');
        if (stepBtn) {
            const row = stepBtn.closest('.j2c-line-row');
            const hidden = row?.querySelector('.j2c-qty-input');
            const valSpan = row?.querySelector('.j2c-qty-value');
            if (!hidden) return;
            let q = parseInt(hidden.value, 10) || 1;
            q = stepBtn.classList.contains('j2c-qty-dec') ? Math.max(1, q - 1) : q + 1;
            hidden.value = String(q);
            if (valSpan) valSpan.textContent = String(q);
            refreshUnits();
            await applyItemChanges();
            return;
        }

        const rm = e.target.closest('.j2c-line-remove');
        if (rm) {
            const row = rm.closest('.j2c-line-row');
            if (!rm.dataset.itemId) return;
            rm.disabled = true;
            try {
                const result = await postAjax('ajaxRemoveItems', { cid: [rm.dataset.itemId] });
                if (!result.success) { showMessage('error', result.message); rm.disabled = false; return; }
                row?.remove();
                toggleCartEmpty();
                refreshUnits();
                updateSummary(result.totals);
                showMessage('message', result.message);
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
                rm.disabled = false;
            }
        }
    });

    // Per-line unit-price edit → apply on change.
    cartLines?.addEventListener('change', (e) => {
        if (e.target.closest('.j2c-price-input')) applyItemChanges();
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

    // === Save Order (summary tab) ===
    document.getElementById('saveOrderBtn')?.addEventListener('click', () => {
        if (typeof Joomla !== 'undefined' && Joomla.submitbutton) {
            Joomla.submitbutton('order.save');
        }
    });

    // === New Customer modal (Basic tab) ===
    document.getElementById('newCustomerSaveBtn')?.addEventListener('click', async () => {
        const saveBtn = document.getElementById('newCustomerSaveBtn');
        const nameField = document.getElementById('newCustomerName');
        const emailField = document.getElementById('newCustomerEmail');
        const usernameField = document.getElementById('newCustomerUsername');
        const sendEmailField = document.getElementById('newCustomerSendEmail');

        const name = (nameField?.value || '').trim();
        const email = (emailField?.value || '').trim();
        const username = (usernameField?.value || '').trim() || email;

        if (!name || !email) {
            showMessage('warning', translate('COM_J2COMMERCE_ERROR_CUSTOMER_REQUIRED', 'Please select or create a customer.'));
            return;
        }

        saveBtn.disabled = true;

        try {
            const result = await postAjax('ajaxCreateCustomer', {
                name,
                email,
                username,
                send_email: sendEmailField?.checked ? 1 : 0,
            });

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            const hiddenUserId = document.getElementById('jform_user_id_id');
            const visibleUserName = document.getElementById('jform_user_id');
            if (hiddenUserId) hiddenUserId.value = String(result.id);
            if (visibleUserName) visibleUserName.value = result.name;

            const registeredRadio = form.querySelector('input[name="jform[customer_type]"][value="registered"]');
            if (registeredRadio) {
                registeredRadio.checked = true;
                registeredRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const modalEl = document.getElementById('newCustomerModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            if (nameField) nameField.value = '';
            if (emailField) emailField.value = '';
            if (usernameField) usernameField.value = '';
            if (sendEmailField) sendEmailField.checked = false;

            showMessage('message', result.message);
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            saveBtn.disabled = false;
        }
    });

    // === Refund / charge-balance modals (Summary totals rail) ===
    // Both are money actions against server-validated ledger amounts; a full page
    // reload afterwards re-renders the server-side balance panel honestly.
    async function submitMoneyModal(btnId, inputId, modalId, task) {
        const btn = document.getElementById(btnId);
        const amount = parseFloat(document.getElementById(inputId)?.value || '0');

        if (!(amount > 0)) {
            showMessage('warning', translate('COM_J2COMMERCE_ERROR_INVALID_REQUEST', 'Invalid request'));
            return;
        }

        btn.disabled = true;

        try {
            const result = await postAjax(task, { amount: String(amount) });

            if (!result.success) {
                showMessage('error', result.message);
                return;
            }

            const modalEl = document.getElementById(modalId);
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            showMessage('message', result.message);
            window.location.reload();
        } catch (err) {
            showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('refundPaymentConfirmBtn')?.addEventListener('click', () => {
        submitMoneyModal('refundPaymentConfirmBtn', 'refundAmount', 'refundPaymentModal', 'ajaxRefundOrderPayment');
    });

    document.getElementById('chargeBalanceConfirmBtn')?.addEventListener('click', () => {
        submitMoneyModal('chargeBalanceConfirmBtn', 'chargeAmount', 'chargeBalanceModal', 'ajaxChargeOrderBalance');
    });

    // === Payment & Shipping step ===
    document.addEventListener('change', (e) => {
        const sel = e.target.closest('#ordershipping_method_select');
        if (sel) {
            const custom = document.getElementById('j2c-custom-shipping');
            if (sel.value === '__custom__') {
                custom?.classList.remove('d-none');
            } else {
                const opt = sel.options[sel.selectedIndex];
                const nameF = document.getElementById('ordershipping_name');
                const priceF = document.getElementById('ordershipping_price');
                const taxF = document.getElementById('ordershipping_tax');
                if (nameF) nameF.value = sel.value;
                if (priceF && opt && opt.dataset.price !== undefined) priceF.value = opt.dataset.price;
                if (taxF && opt && opt.dataset.tax !== undefined) taxF.value = opt.dataset.tax;
                custom?.classList.add('d-none');
            }
            return;
        }

        const payRadio = e.target.closest('.j2c-payment-radio');
        if (payRadio) {
            document.querySelectorAll('.j2c-payment-option').forEach((o) => o.classList.remove('selected'));
            payRadio.closest('.j2c-payment-option')?.classList.add('selected');

            // "No Payment Method Needed" suppresses the pseudo-checkout handoff;
            // a real method re-shows Take Payment only when a signed URL exists.
            const payWrap = document.getElementById('j2c-take-payment-wrap');
            if (payWrap) {
                const hasUrl = (payWrap.querySelector('.j2c-take-payment')?.getAttribute('href') || '') !== '';
                payWrap.classList.toggle('d-none', payRadio.value === 'none' || !hasUrl);
            }
        }
    });

    // Take Payment: persist the current step first (payment-method radio, shipping
    // edits) so the pseudo-checkout preselects the chosen method, then open it.
    // The tab is opened synchronously (inside the click gesture) and navigated
    // after the save — window.open after an await gets popup-blocked.
    document.addEventListener('click', async (e) => {
        const payLink = e.target.closest('.j2c-take-payment');
        if (!payLink) return;

        e.preventDefault();
        payLink.classList.add('disabled');

        const payWindow = window.open('', '_blank');

        try {
            await postEditData('ajaxSaveOrderEdit');
        } catch (err) {
            // Non-fatal: the payment page still works with the last-saved method.
        } finally {
            payLink.classList.remove('disabled');
            if (payWindow) {
                payWindow.location = payLink.href;
            } else {
                window.location = payLink.href;
            }
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

    // === Add-new-address modal (billing/shipping steps) ===
    // Reuses the Customer view's form/save endpoints; the fetched form fragment is
    // trusted admin-component HTML (same pattern as customer-addresses.js). On save,
    // the new address is auto-applied to the current step and the page reloads.
    const addrOpts = (typeof Joomla !== 'undefined' && Joomla.getOptions)
        ? (Joomla.getOptions('com_j2commerce.order_addresses') || {})
        : {};
    const addrModalEl = document.getElementById('j2commerce-address-modal');

    if (addrModalEl && addrOpts.formUrl && addrOpts.saveUrl) {
        const addrBody = addrModalEl.querySelector('.modal-body');
        const addrSave = addrModalEl.querySelector('.j2commerce-address-save');
        let addrType = 'billing';
        let addrModal = null;

        const getAddrModal = () => {
            if (!addrModal && typeof bootstrap !== 'undefined') {
                addrModal = bootstrap.Modal.getOrCreateInstance(addrModalEl);
            }
            return addrModal;
        };

        const bindAddrZones = () => {
            const country = addrBody.querySelector('#jform_country_id');
            const zone = addrBody.querySelector('#jform_zone_id');
            if (!country || !zone || !addrOpts.zonesUrl) return;

            country.addEventListener('change', async () => {
                if (!country.value || country.value === '0') return;
                try {
                    const resp = await fetch(
                        `${addrOpts.zonesUrl}&country_id=${encodeURIComponent(country.value)}&zone_id=0`,
                        { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                    );
                    const html = resp.ok ? await resp.text() : '';
                    if (html) zone.replaceChildren(document.createRange().createContextualFragment(html));
                } catch (err) {
                    // Keep the existing zone options on failure.
                }
            });
        };

        document.addEventListener('click', async (e) => {
            const btn = e.target.closest('.j2c-address-new');
            if (!btn) return;

            addrType = btn.dataset.addressType || 'billing';
            addrBody.replaceChildren();
            getAddrModal()?.show();

            try {
                const resp = await fetch(
                    `${addrOpts.formUrl}&id=0&user_id=${encodeURIComponent(btn.dataset.userId || '0')}&${encodeURIComponent(addrOpts.token)}=1`,
                    { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }
                );
                if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                addrBody.replaceChildren(document.createRange().createContextualFragment(await resp.text()));
                bindAddrZones();
            } catch (err) {
                getAddrModal()?.hide();
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            }
        });

        addrSave?.addEventListener('click', async () => {
            const formEl = addrBody.querySelector('form');
            if (!formEl) return;

            const fd = new FormData(formEl);
            fd.append(addrOpts.token, '1');
            addrSave.disabled = true;

            try {
                const resp = await fetch(addrOpts.saveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });

                if (!resp.ok) {
                    throw new Error(`HTTP ${resp.status}`);
                }

                const data = await resp.json();

                if (!data || !data.success) {
                    showMessage('error', (data && data.message) || translate('COM_J2COMMERCE_ERROR_INVALID_REQUEST', 'Invalid request'));
                    return;
                }

                getAddrModal()?.hide();

                const newId = parseInt(data.address?.j2commerce_address_id ?? '0', 10);

                if (newId > 0) {
                    const applied = await postAjax('ajaxApplySavedAddress', {
                        address_type: addrType,
                        address_id: newId,
                    });

                    if (applied.success) {
                        window.location.reload();
                        return;
                    }
                }

                // Fallback: address saved but not auto-applied — it is available
                // in the Choose Alternate Address list.
                showMessage('message', data.message || '');
            } catch (err) {
                showMessage('error', translate('COM_J2COMMERCE_ERROR_NETWORK', 'Network error. Please try again.'));
            } finally {
                addrSave.disabled = false;
            }
        });
    }
});
