/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Inventory list view (administrator). Extracted from inline script (#792).
 *
 * Depends on:
 *   - <input type="hidden" id="inventory-csrf-token" value="<TOKEN>"> on the page,
 *     OR Joomla.getOptions('com_j2commerce.inventory').csrfToken
 *   - Joomla.Text._() with COM_J2COMMERCE_SAVING / _SAVED / _ERROR /
 *     _INVENTORY_AJAX_ERROR / _SHOW_VARIANTS / _HIDE_VARIANTS preloaded via Text::script()
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const options   = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('com_j2commerce.inventory', {}) : {};
        const csrfToken = options.csrfToken
            || (document.getElementById('inventory-csrf-token') && document.getElementById('inventory-csrf-token').value)
            || '';

        function t(key, fallback) {
            return (window.Joomla && Joomla.Text && Joomla.Text._) ? Joomla.Text._(key, fallback || key) : (fallback || key);
        }

        function getFieldValue(row, selector) {
            const el = row.querySelector(selector);
            return el ? el.value : '';
        }

        function getManageStockValue(row) {
            const checked = row.querySelector('.manage-stock-toggle:checked, input[name*=manage_stock]:checked');
            if (checked) {
                return checked.value;
            }
            const cell = row.querySelector('.j2commerce-inventory-manage-stock, [class*=manage]');
            if (cell) {
                const radio = cell.querySelector('input[type=radio]:checked');
                if (radio) {
                    return radio.value;
                }
            }
            return '0';
        }

        function showSystemMessage(message, type) {
            const container = document.getElementById('system-message-container');
            if (!container) {
                return;
            }

            const alert = document.createElement('div');
            alert.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-danger') + ' alert-dismissible fade show';
            alert.setAttribute('role', 'alert');
            alert.appendChild(document.createTextNode(message));

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('data-bs-dismiss', 'alert');
            close.setAttribute('aria-label', 'Close');
            alert.appendChild(close);

            container.replaceChildren(alert);

            setTimeout(function () {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 500);
            }, 5000);
        }

        function setBtnState(btn, state, originalText) {
            btn.classList.remove('btn-primary', 'btn-success', 'btn-danger');
            if (state === 'saving') {
                btn.classList.add('btn-primary');
                btn.disabled = true;
                btn.textContent = t('COM_J2COMMERCE_SAVING') + '...';
            } else if (state === 'success') {
                btn.classList.add('btn-success');
                btn.textContent = t('COM_J2COMMERCE_SAVED');
                setTimeout(function () {
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-primary');
                    btn.textContent = originalText;
                }, 2000);
            } else if (state === 'error') {
                btn.classList.add('btn-danger');
                btn.textContent = t('COM_J2COMMERCE_ERROR');
                setTimeout(function () {
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-primary');
                    btn.textContent = originalText;
                }, 3000);
            }
        }

        function postSave(row, saveBtn, productId, variantId) {
            const quantity     = getFieldValue(row, '.quantity-input, input[name*=quantity]');
            const manageStock  = getManageStockValue(row);
            const availability = getFieldValue(row, '.stock-select, select[name*=availability]');
            const originalText = saveBtn.textContent;

            setBtnState(saveBtn, 'saving', originalText);

            const body = new URLSearchParams();
            body.set('product_id', productId);
            body.set('variant_id', variantId);
            body.set('quantity', quantity);
            body.set('manage_stock', manageStock);
            body.set('availability', availability);
            if (csrfToken) {
                body.set(csrfToken, '1');
            }

            fetch('index.php?option=com_j2commerce&task=inventory.saveItem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        setBtnState(saveBtn, 'success', originalText);
                        if (response.message) {
                            showSystemMessage(response.message, 'success');
                        }
                    } else {
                        setBtnState(saveBtn, 'error', originalText);
                        if (response.message) {
                            showSystemMessage(response.message, 'error');
                        }
                    }
                })
                .catch(function () {
                    setBtnState(saveBtn, 'error', originalText);
                    showSystemMessage(t('COM_J2COMMERCE_INVENTORY_AJAX_ERROR'), 'error');
                })
                .finally(function () { saveBtn.disabled = false; });
        }

        function saveInventoryItem(saveBtn, productId, variantId) {
            const row = document.getElementById('inventory-row-' + productId);
            if (!row) { return; }
            postSave(row, saveBtn, productId, variantId);
        }

        function saveVariantItem(saveBtn, variantId, productId) {
            const row = document.getElementById('variant-row-' + variantId);
            if (!row) { return; }
            postSave(row, saveBtn, productId, variantId);
        }

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.save-btn[data-j2c-action]');
            if (!btn) {
                return;
            }
            event.preventDefault();
            const productId = parseInt(btn.dataset.productId || '0', 10);
            const variantId = parseInt(btn.dataset.variantId || '0', 10);
            if (btn.dataset.j2cAction === 'save-variant') {
                saveVariantItem(btn, variantId, productId);
            } else {
                saveInventoryItem(btn, productId, variantId);
            }
        });

        // Batch Update: gather checked products + the chosen modal fields, POST via AJAX, reload on success.
        document.addEventListener('click', function (event) {
            const applyBtn = event.target.closest('#j2c-batch-apply');
            if (!applyBtn) {
                return;
            }
            event.preventDefault();

            const checked = Array.from(document.querySelectorAll('#inventoryList input[name="cid[]"]:checked'));
            if (!checked.length) {
                showSystemMessage(t('COM_J2COMMERCE_INVENTORY_BATCH_NO_SELECTION'), 'error');
                return;
            }

            const applyQty = document.getElementById('batch_apply_quantity');
            const applyMs  = document.getElementById('batch_apply_manage_stock');
            const applyAv  = document.getElementById('batch_apply_availability');

            if (!(applyQty && applyQty.checked) && !(applyMs && applyMs.checked) && !(applyAv && applyAv.checked)) {
                showSystemMessage(t('COM_J2COMMERCE_INVENTORY_BATCH_NO_FIELDS'), 'error');
                return;
            }

            const body = new URLSearchParams();
            checked.forEach(function (cb) { body.append('cid[]', cb.value); });

            if (applyQty && applyQty.checked) {
                const q = document.getElementById('batch_quantity');
                body.set('apply_quantity', '1');
                body.set('batch_quantity', q ? q.value : '0');
            }
            if (applyMs && applyMs.checked) {
                const r = document.querySelector('input[name="batch_manage_stock"]:checked');
                body.set('apply_manage_stock', '1');
                body.set('batch_manage_stock', r ? r.value : '0');
            }
            if (applyAv && applyAv.checked) {
                const s = document.getElementById('batch_availability');
                body.set('apply_availability', '1');
                body.set('batch_availability', s ? s.value : '1');
            }
            if (csrfToken) {
                body.set(csrfToken, '1');
            }

            applyBtn.disabled = true;

            fetch('index.php?option=com_j2commerce&task=inventory.batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        showSystemMessage(response.message || t('COM_J2COMMERCE_INVENTORY_AJAX_ERROR'), 'error');
                        applyBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showSystemMessage(t('COM_J2COMMERCE_INVENTORY_AJAX_ERROR'), 'error');
                    applyBtn.disabled = false;
                });
        });

        // Manual toggle fallback when Bootstrap data-bs-toggle="collapse" cannot be used.
        window.toggleVariants = function (productId) {
            const variantsRow = document.getElementById('variants-' + productId);
            const toggleBtn   = document.getElementById('toggle-variants-' + productId);
            if (!variantsRow) {
                return;
            }
            const isVisible = variantsRow.style.display !== 'none' && variantsRow.classList.contains('show');
            if (isVisible) {
                variantsRow.classList.remove('show');
                if (toggleBtn) { toggleBtn.textContent = t('COM_J2COMMERCE_SHOW_VARIANTS'); }
            } else {
                variantsRow.classList.add('show');
                if (toggleBtn) { toggleBtn.textContent = t('COM_J2COMMERCE_HIDE_VARIANTS'); }
            }
        };
    });
})();
