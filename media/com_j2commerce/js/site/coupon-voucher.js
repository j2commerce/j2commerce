/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

/**
 * J2Commerce Coupon & Voucher AJAX handler
 *
 * Handles apply/remove for all coupon and voucher form instances on the page.
 * Uses event delegation so forms can be replaced by AJAX without losing handlers.
 * After AJAX success, switches the form DOM between input/applied states and
 * dispatches custom DOM events so consuming pages can react (refresh totals, etc.).
 *
 * Required: Joomla.getOptions('j2commerce.couponVoucher') with {baseUrl, csrfToken, strings}
 */
(function () {

    const options = Joomla.getOptions('j2commerce.couponVoucher') || {};
    const baseUrl = options.baseUrl || 'index.php';
    const token   = options.csrfToken || '';
    const strings = options.strings || {};

    // --- Inline error display ---

    function showInputError(input, message) {
        clearInputError(input);
        const inner = input.closest('.input-group_inner');
        if (!inner) return;

        input.style.borderColor = '#dc3545';

        const xBtn = document.createElement('span');
        xBtn.className = 'j2c-input-clear';
        xBtn.innerHTML = '&times;';
        inner.appendChild(xBtn);

        const group = inner.closest('.input-group');
        const errDiv = document.createElement('div');
        errDiv.className = 'j2c-field-error text-danger small mt-1';
        errDiv.textContent = message;
        (group || inner).parentElement.appendChild(errDiv);

        xBtn.addEventListener('click', function () {
            input.value = '';
            clearInputError(input);
            input.focus();
        });
    }

    function clearInputError(input) {
        const inner = input.closest('.input-group_inner');
        if (!inner) return;
        const xBtn = inner.querySelector('.j2c-input-clear');
        if (xBtn) xBtn.remove();
        input.style.borderColor = '';
        const group = inner.closest('.input-group');
        const errDiv = (group || inner).parentElement?.querySelector('.j2c-field-error');
        if (errDiv) errDiv.remove();
    }

    // --- Form state switching ---

    function showAppliedState(form, code, type) {
        var removeClass = type === 'coupon' ? 'j2c-remove-coupon' : 'j2c-remove-voucher';
        var removeTitle = type === 'coupon' ? (strings.removeCoupon || 'Remove Coupon') : (strings.removeVoucher || 'Remove Voucher');
        var removeText  = strings.remove || 'Remove';
        var escaped = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        form.innerHTML =
            '<div class="d-flex align-items-center justify-content-between py-1">' +
                '<span class="badge bg-success">' +
                    '<span class="icon-tag me-1" aria-hidden="true"></span>' + escaped +
                '</span>' +
                '<button type="button" class="btn btn-sm btn-link text-danger p-0 ' + removeClass + '"' +
                    ' title="' + removeTitle + '">' +
                    '<span class="icon-times" aria-hidden="true"></span> ' + removeText +
                '</button>' +
            '</div>';

        // Update accordion header badge if inside an accordion
        var accordionItem = form.closest('.accordion-item');
        if (accordionItem) {
            var header = accordionItem.querySelector('.accordion-button');
            if (header) {
                var existingBadge = header.querySelector('.j2c-' + type + '-badge');
                if (!existingBadge) {
                    var badge = document.createElement('span');
                    badge.className = 'badge bg-success ms-2 j2c-' + type + '-badge';
                    badge.textContent = code;
                    header.appendChild(badge);
                } else {
                    existingBadge.textContent = code;
                }
            }
        }
    }

    function showInputState(form, type) {
        var inputName   = type === 'coupon' ? 'coupon' : 'voucher';
        var applyClass  = type === 'coupon' ? 'j2c-apply-coupon' : 'j2c-apply-voucher';
        var placeholder = type === 'coupon' ? (strings.enterCoupon || 'Enter coupon code') : (strings.enterVoucher || 'Enter voucher code');
        var ariaLabel   = type === 'coupon' ? (strings.couponCode || 'Coupon Code') : (strings.voucherCode || 'Voucher Code');
        var btnText     = type === 'coupon' ? (strings.applyCoupon || 'Apply Coupon') : (strings.applyVoucher || 'Apply Voucher');

        form.innerHTML =
            '<div class="j2c-' + type + '-input-wrap">' +
                '<div class="input-group">' +
                    '<div class="input-group_inner">' +
                        '<input type="text" name="' + inputName + '" class="form-control"' +
                            ' placeholder="' + placeholder + '"' +
                            ' aria-label="' + ariaLabel + '">' +
                    '</div>' +
                    '<button type="button" class="btn btn-outline-secondary ' + applyClass + '">' +
                        btnText +
                    '</button>' +
                '</div>' +
            '</div>';

        // Remove accordion header badge if inside an accordion
        var accordionItem = form.closest('.accordion-item');
        if (accordionItem) {
            var badge = accordionItem.querySelector('.j2c-' + type + '-badge');
            if (badge) badge.remove();
        }
    }

    // --- AJAX helper ---

    function postAction(task, extraData) {
        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', task);
        if (token) {
            formData.append(token, '1');
        }
        if (extraData) {
            Object.entries(extraData).forEach(function (entry) {
                formData.append(entry[0], entry[1]);
            });
        }

        return fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json(); });
    }

    function dispatchEvent(element, name, detail) {
        element.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail }));
    }

    // --- Apply Coupon ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-apply-coupon');
        if (!btn) return;
        e.preventDefault();

        const form = btn.closest('.j2c-coupon-form');
        const input = form ? form.querySelector('input[name="coupon"]') : null;
        if (!input || !input.value.trim()) {
            if (input) input.focus();
            return;
        }

        clearInputError(input);
        const code = input.value.trim();
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        postAction('carts.applyCouponAjax', { coupon: code })
            .then(function (data) {
                if (data.success) {
                    showAppliedState(form, code, 'coupon');
                    dispatchEvent(form, 'j2commerce:coupon:applied', {
                        code: code, message: data.message || '', formId: form.id
                    });
                } else {
                    showInputError(input, data.message || 'Invalid coupon code.');
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                    dispatchEvent(form, 'j2commerce:coupon:error', {
                        message: data.message || '', formId: form.id
                    });
                }
            })
            .catch(function () {
                showInputError(input, 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = origHTML;
            });
    });

    // --- Remove Coupon ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-remove-coupon');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;

        const form = btn.closest('.j2c-coupon-form');

        postAction('carts.removeCouponAjax')
            .then(function (data) {
                showInputState(form, 'coupon');
                dispatchEvent(form, 'j2commerce:coupon:removed', {
                    message: data.message || '', formId: form.id
                });
            })
            .catch(function () {
                btn.disabled = false;
            });
    });

    // --- Apply Voucher ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-apply-voucher');
        if (!btn) return;
        e.preventDefault();

        const form = btn.closest('.j2c-voucher-form');
        const input = form ? form.querySelector('input[name="voucher"]') : null;
        if (!input || !input.value.trim()) {
            if (input) input.focus();
            return;
        }

        clearInputError(input);
        const code = input.value.trim();
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        postAction('carts.applyVoucherAjax', { voucher: code })
            .then(function (data) {
                if (data.success) {
                    showAppliedState(form, code, 'voucher');
                    dispatchEvent(form, 'j2commerce:voucher:applied', {
                        code: code, message: data.message || '', formId: form.id
                    });
                } else {
                    showInputError(input, data.message || 'Invalid voucher code.');
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                    dispatchEvent(form, 'j2commerce:voucher:error', {
                        message: data.message || '', formId: form.id
                    });
                }
            })
            .catch(function () {
                showInputError(input, 'An error occurred.');
                btn.disabled = false;
                btn.innerHTML = origHTML;
            });
    });

    // --- Remove Voucher ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-remove-voucher');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;

        const form = btn.closest('.j2c-voucher-form');

        postAction('carts.removeVoucherAjax')
            .then(function (data) {
                showInputState(form, 'voucher');
                dispatchEvent(form, 'j2commerce:voucher:removed', {
                    message: data.message || '', formId: form.id
                });
            })
            .catch(function () {
                btn.disabled = false;
            });
    });

})();
