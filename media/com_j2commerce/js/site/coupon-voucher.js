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
 * Required: Joomla.getOptions('j2commerce.couponVoucher') with
 *   {baseUrl, csrfToken, strings, framework, classes, accordion}
 */
(function () {

    const options = Joomla.getOptions('j2commerce.couponVoucher') || {};
    const baseUrl = options.baseUrl || 'index.php';
    const token   = options.csrfToken || '';
    const strings = options.strings || {};
    const fw      = options.framework || 'bootstrap5';
    const acc     = options.accordion || { itemSelector: '.accordion-item', headerSelector: '.accordion-button' };

    // Bootstrap 5 fallback defaults (used when options.classes is absent)
    const DEFAULTS = {
        appliedRow:     'd-flex align-items-center justify-content-between py-1',
        badge:          'badge bg-success',
        iconTag:        'icon-tag me-1',
        removeBtnBase:  'btn btn-sm btn-link text-danger p-0',
        input:          'form-control',
        inputWrap:      'input-group',
        inputInner:     'input-group_inner',
        applyBtnBase:   'btn btn-outline-secondary',
        fieldError:     'j2c-field-error text-danger small mt-1',
        accordionBadge: 'badge bg-success ms-2',
    };

    const classes = options.classes || {};

    /** Return the framework class for key, falling back to Bootstrap 5 default. */
    function cls(key) {
        return classes[key] || DEFAULTS[key] || '';
    }

    // --- Inline error display ---

    function showInputError(input, message) {
        clearInputError(input);
        const inner = input.closest('.input-group_inner, .uk-width-expand');
        if (!inner) return;

        input.style.borderColor = '#dc3545';

        const xBtn = document.createElement('span');
        xBtn.className = 'j2c-input-clear';
        xBtn.textContent = '×';
        inner.appendChild(xBtn);

        const group = inner.parentElement;
        const errDiv = document.createElement('div');
        errDiv.className = cls('fieldError');
        errDiv.textContent = message;
        (group || inner).parentElement.appendChild(errDiv);

        xBtn.addEventListener('click', function () {
            input.value = '';
            clearInputError(input);
            input.focus();
        });
    }

    function clearInputError(input) {
        const inner = input.closest('.input-group_inner, .uk-width-expand');
        if (!inner) return;
        const xBtn = inner.querySelector('.j2c-input-clear');
        if (xBtn) xBtn.remove();
        input.style.borderColor = '';
        const group = inner.parentElement;
        const errDiv = (group || inner).parentElement?.querySelector('.j2c-field-error');
        if (errDiv) errDiv.remove();
    }

    // --- Spinner ---

    function buildSpinner() {
        if (fw === 'uikit') {
            const s = document.createElement('span');
            s.setAttribute('uk-spinner', '');
            return s;
        }
        const s = document.createElement('span');
        s.className = 'spinner-border spinner-border-sm';
        s.setAttribute('role', 'status');
        const vh = document.createElement('span');
        vh.className = 'visually-hidden';
        vh.textContent = Joomla.Text._('COM_J2COMMERCE_LOADING');
        s.appendChild(vh);
        return s;
    }

    // --- Form state switching ---

    function showAppliedState(form, code, type) {
        const removeClass  = type === 'coupon' ? 'j2c-remove-coupon' : 'j2c-remove-voucher';
        const removeTitle  = type === 'coupon' ? (strings.removeCoupon || 'Remove Coupon') : (strings.removeVoucher || 'Remove Voucher');
        const removeText   = strings.remove || 'Remove';

        // Badge: <span class="badge ..."><span class="icon-tag ..." aria-hidden></span> CODE</span>
        const iconSpan = document.createElement('span');
        iconSpan.className = cls('iconTag');
        iconSpan.setAttribute('aria-hidden', 'true');

        const badge = document.createElement('span');
        badge.className = cls('badge');
        badge.appendChild(iconSpan);
        badge.appendChild(document.createTextNode(code));

        // Remove button: <button class="removeBtnBase removeClass" ...>
        const timesSpan = document.createElement('span');
        timesSpan.className = 'icon-times';
        timesSpan.setAttribute('aria-hidden', 'true');

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = cls('removeBtnBase') + ' ' + removeClass;
        removeBtn.title = removeTitle;
        removeBtn.appendChild(timesSpan);
        removeBtn.appendChild(document.createTextNode(' ' + removeText));

        // Row wrapper
        const row = document.createElement('div');
        row.className = cls('appliedRow');
        row.appendChild(badge);
        row.appendChild(removeBtn);

        form.replaceChildren(row);

        // Update accordion header badge
        const item = form.closest(acc.itemSelector);
        if (item) {
            const header = item.querySelector(acc.headerSelector);
            if (header) {
                let existingBadge = header.querySelector('.j2c-' + type + '-badge');
                if (!existingBadge) {
                    existingBadge = document.createElement('span');
                    existingBadge.className = cls('accordionBadge') + ' j2c-' + type + '-badge';
                    header.appendChild(existingBadge);
                }
                existingBadge.textContent = code;
            }
        }
    }

    function showInputState(form, type) {
        const inputName   = type === 'coupon' ? 'coupon' : 'voucher';
        const applyClass  = type === 'coupon' ? 'j2c-apply-coupon' : 'j2c-apply-voucher';
        const placeholder = type === 'coupon' ? (strings.enterCoupon || 'Enter coupon code') : (strings.enterVoucher || 'Enter voucher code');
        const ariaLabel   = type === 'coupon' ? (strings.couponCode || 'Coupon Code') : (strings.voucherCode || 'Voucher Code');
        const btnText     = type === 'coupon' ? (strings.applyCoupon || 'Apply Coupon') : (strings.applyVoucher || 'Apply Voucher');

        // Input element
        const input = document.createElement('input');
        input.type = 'text';
        input.name = inputName;
        input.className = cls('input');
        input.placeholder = placeholder;
        input.setAttribute('aria-label', ariaLabel);

        // Inner wrap
        const inner = document.createElement('div');
        inner.className = cls('inputInner');
        inner.appendChild(input);

        // Apply button
        const applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = cls('applyBtnBase') + ' ' + applyClass;
        applyBtn.textContent = btnText;

        // Outer wrap
        const wrap = document.createElement('div');
        wrap.className = cls('inputWrap');
        wrap.appendChild(inner);
        wrap.appendChild(applyBtn);

        // Container
        const container = document.createElement('div');
        container.className = 'j2c-' + type + '-input-wrap';
        container.appendChild(wrap);

        form.replaceChildren(container);

        // Remove accordion header badge
        const item = form.closest(acc.itemSelector);
        if (item) {
            const badge = item.querySelector('.j2c-' + type + '-badge');
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
        const orig = Array.from(btn.childNodes).map(function (n) { return n.cloneNode(true); });
        btn.disabled = true;
        btn.replaceChildren(buildSpinner());

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
                    btn.replaceChildren(...orig);
                    dispatchEvent(form, 'j2commerce:coupon:error', {
                        message: data.message || '', formId: form.id
                    });
                }
            })
            .catch(function () {
                showInputError(input, 'An error occurred.');
                btn.disabled = false;
                btn.replaceChildren(...orig);
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
        const orig = Array.from(btn.childNodes).map(function (n) { return n.cloneNode(true); });
        btn.disabled = true;
        btn.replaceChildren(buildSpinner());

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
                    btn.replaceChildren(...orig);
                    dispatchEvent(form, 'j2commerce:voucher:error', {
                        message: data.message || '', formId: form.id
                    });
                }
            })
            .catch(function () {
                showInputError(input, 'An error occurred.');
                btn.disabled = false;
                btn.replaceChildren(...orig);
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
