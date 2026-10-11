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
        discountLabel:  'text-body-tertiary ms-1',
    };

    const classes = options.classes || {};

    /** Return the framework class for key, falling back to Bootstrap 5 default. */
    function cls(key) {
        return classes[key] || DEFAULTS[key] || '';
    }

    // --- Inline error display ---

    let errorSeq = 0;

    function showInputError(input, message) {
        clearInputError(input);
        const inner = input.closest('.input-group_inner, .uk-width-expand');
        if (!inner) return;

        input.style.borderColor = '#dc3545';

        const xBtn = document.createElement('button');
        xBtn.type = 'button';
        xBtn.className = 'j2c-input-clear';
        xBtn.textContent = '×';
        xBtn.setAttribute('aria-label', strings.clear || '');
        inner.appendChild(xBtn);

        const group = inner.parentElement;
        const errDiv = document.createElement('div');
        errDiv.className = cls('fieldError');
        errDiv.id = 'j2c-field-error-' + (++errorSeq);
        errDiv.setAttribute('role', 'alert');
        (group || inner).parentElement.appendChild(errDiv);
        // The live region exists empty first, then gets its text, so assistive tech announces the change.
        setTimeout(function () { errDiv.textContent = message; }, 50);

        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean).concat(errDiv.id).join(' '));

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
        input.removeAttribute('aria-invalid');
        const group = inner.parentElement;
        const errDiv = (group || inner).parentElement?.querySelector('.j2c-field-error');
        if (errDiv) {
            const ids = (input.getAttribute('aria-describedby') || '').split(' ').filter((id) => id && id !== errDiv.id);
            if (ids.length) {
                input.setAttribute('aria-describedby', ids.join(' '));
            } else {
                input.removeAttribute('aria-describedby');
            }
            errDiv.remove();
        }
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

    /** Build the "10% Discount" / "$25 Balance" note the server renders beside the badge. */
    function buildDiscountLabel(text, type) {
        const label = document.createElement('small');
        label.className = cls('discountLabel') + ' j2c-' + type + '-discount';
        label.textContent = text;
        return label;
    }

    function showAppliedState(form, code, type, discountLabel) {
        const removeClass  = type === 'coupon' ? 'j2c-remove-coupon' : 'j2c-remove-voucher';
        const removeTitle  = type === 'coupon' ? (strings.removeCoupon || '') : (strings.removeVoucher || '');
        const removeText   = strings.remove || '';

        // The label is a server string, and the form states whether this instance shows one.
        const label = form.dataset.showDiscount === '0' ? '' : (discountLabel || '');

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

        // Badge and label share a wrapper so the row still spaces them against the Remove button
        const badgeWrap = document.createElement('span');
        badgeWrap.appendChild(badge);

        if (label) {
            badgeWrap.appendChild(buildDiscountLabel(label, type));
        }

        // Row wrapper
        const row = document.createElement('div');
        row.className = cls('appliedRow');
        row.appendChild(badgeWrap);
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

                let existingLabel = header.querySelector('.j2c-' + type + '-discount');
                if (label) {
                    if (!existingLabel) {
                        existingLabel = buildDiscountLabel(label, type);
                        existingBadge.insertAdjacentElement('afterend', existingLabel);
                    }
                    existingLabel.textContent = label;
                } else if (existingLabel) {
                    existingLabel.remove();
                }
            }
        }
    }

    function showInputState(form, type) {
        const inputName   = type === 'coupon' ? 'coupon' : 'voucher';
        const applyClass  = type === 'coupon' ? 'j2c-apply-coupon' : 'j2c-apply-voucher';
        const placeholder = type === 'coupon' ? (strings.enterCoupon || '') : (strings.enterVoucher || '');
        const ariaLabel   = type === 'coupon' ? (strings.couponCode || '') : (strings.voucherCode || '');
        const btnText     = type === 'coupon' ? (strings.applyCoupon || '') : (strings.applyVoucher || '');

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

        // Remove accordion header badge and its discount note
        const item = form.closest(acc.itemSelector);
        if (item) {
            const badge = item.querySelector('.j2c-' + type + '-badge');
            if (badge) badge.remove();
            const label = item.querySelector('.j2c-' + type + '-discount');
            if (label) label.remove();
        }
    }

    // --- AJAX helper ---

    /**
     * A hidden Joomla form-token input reproduces exactly `<input type="hidden"
     * name="<32hex>" value="1">` — the shape core's page cache rewrites to the current
     * visitor's token on every cached-page replay, unlike the csrfToken script option.
     */
    function readFormToken() {
        return (Array.from(document.querySelectorAll('input[type="hidden"][value="1"]'))
            .find((el) => /^[0-9a-f]{32}$/.test(el.name)) || {}).name || '';
    }

    /**
     * Resolve a CSRF token: DOM (freshest) -> endpoint -> the script-option fallback. The
     * script option is baked into the markup, so on a cached-page replay it carries whichever
     * visitor primed the cache; putting it last means a stale value is used only when nothing
     * fresher exists.
     */
    async function resolveToken() {
        const domToken = readFormToken();

        if (domToken) {
            return domToken;
        }

        if (window.J2CommerceToken && typeof window.J2CommerceToken.get === 'function') {
            try {
                const endpointToken = await window.J2CommerceToken.get();

                if (endpointToken) {
                    return endpointToken;
                }
            } catch (error) {
                // Fall through to the legacy source.
            }
        }

        return options.csrfToken || '';
    }

    async function postAction(task, extraData) {
        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', task);

        const tok = await resolveToken();
        if (tok) {
            formData.append(tok, '1');
        }
        if (extraData) {
            Object.entries(extraData).forEach(function (entry) {
                formData.append(entry[0], entry[1]);
            });
        }

        const response = await fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    function dispatchEvent(element, name, detail) {
        element.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail }));
    }

    /**
     * Announce the cart money change on the signal every cart surface listens to.
     *
     * The coupon/voucher events above are consumed by the pages that host this
     * form (cart, checkout). Surfaces that host it without owning the page — the
     * mini-cart module, the cart drawer, third-party totals displays — refresh on
     * j2commerce:cart:updated, the same signal a quantity change or an item
     * removal sends. A coupon moves the same money, so it sends it too.
     */
    function notifyCartUpdated() {
        document.dispatchEvent(new CustomEvent('j2commerce:cart:updated', { bubbles: true }));
    }

    // The event carries the same translated text the shopper sees, so a listener never
    // receives an empty or untranslated message — including when the request itself fails.
    function showError(form, input, btn, orig, type, serverMessage) {
        const message = serverMessage || strings.error || '';
        showInputError(input, message);
        btn.disabled = false;
        btn.replaceChildren(...orig);
        input.focus();
        dispatchEvent(form, 'j2commerce:' + type + ':error', { message: message, formId: form.id });
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
                    showAppliedState(form, code, 'coupon', data.discountLabel);
                    dispatchEvent(form, 'j2commerce:coupon:applied', {
                        code: code, message: data.message || '', formId: form.id
                    });
                    notifyCartUpdated();
                } else {
                    showError(form, input, btn, orig, 'coupon', data.message);
                }
            })
            .catch(function () {
                showError(form, input, btn, orig, 'coupon');
            });
    });

    // --- Remove Coupon ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-remove-coupon, .j2commerce-remove-coupon');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;

        // The order-totals row renders its own remove link outside the form. The coupon
        // is gone from the cart either way, so every form the page hosts goes back to
        // its input state — a page can carry more than one (cart page plus drawer).
        const owner = btn.closest('.j2c-coupon-form');
        const forms = owner ? [owner] : Array.from(document.querySelectorAll('.j2c-coupon-form'));

        postAction('carts.removeCouponAjax')
            .then(function (data) {
                forms.forEach(function (f) {
                    showInputState(f, 'coupon');
                });
                dispatchEvent(forms[0] || document, 'j2commerce:coupon:removed', {
                    message: data.message || '', formId: forms[0] ? forms[0].id : ''
                });
                notifyCartUpdated();
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
                    showAppliedState(form, code, 'voucher', data.discountLabel);
                    dispatchEvent(form, 'j2commerce:voucher:applied', {
                        code: code, message: data.message || '', formId: form.id
                    });
                    notifyCartUpdated();
                } else {
                    showError(form, input, btn, orig, 'voucher', data.message);
                }
            })
            .catch(function () {
                showError(form, input, btn, orig, 'voucher');
            });
    });

    // --- Remove Voucher ---

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.j2c-remove-voucher, .j2commerce-remove-voucher');
        if (!btn) return;
        e.preventDefault();
        btn.disabled = true;

        const owner = btn.closest('.j2c-voucher-form');
        const forms = owner ? [owner] : Array.from(document.querySelectorAll('.j2c-voucher-form'));

        postAction('carts.removeVoucherAjax')
            .then(function (data) {
                forms.forEach(function (f) {
                    showInputState(f, 'voucher');
                });
                dispatchEvent(forms[0] || document, 'j2commerce:voucher:removed', {
                    message: data.message || '', formId: forms[0] ? forms[0].id : ''
                });
                notifyCartUpdated();
            })
            .catch(function () {
                btn.disabled = false;
            });
    });

})();
