/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const options = Joomla.getOptions('com_j2commerce.recordpayment');

    if (!options) {
        return;
    }

    const text = (key) => Joomla.Text._(key);
    const live = document.getElementById('j2cPaymentLive');
    const urls = { refund: options.refundUrl };
    let opener = null;

    const announce = (message) => {
        if (!live) {
            return;
        }

        // Clear, then set after a tick: assigning the same text again would not be announced by a live region.
        live.textContent = '';
        window.setTimeout(() => { live.textContent = message; }, 60);
    };

    const el = (tag, className, content) => {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (content !== undefined) {
            node.textContent = content;
        }

        return node;
    };

    const clearErrors = (form) => {
        const summary = form.querySelector('[data-j2c-record-error]');

        if (summary) {
            summary.hidden = true;
            summary.textContent = '';
        }

        form.querySelectorAll('.is-invalid').forEach((control) => {
            control.classList.remove('is-invalid');
            control.removeAttribute('aria-invalid');
            control.setAttribute('aria-describedby', (control.getAttribute('aria-describedby') || '')
                .split(' ').filter((id) => id && !id.endsWith('Error')).join(' '));

            if (!control.getAttribute('aria-describedby')) {
                control.removeAttribute('aria-describedby');
            }
        });

        form.querySelectorAll('[data-j2c-field-error]').forEach((node) => node.remove());
    };

    const showError = (form, message, fieldName) => {
        const summary = form.querySelector('[data-j2c-record-error]');
        const control = fieldName ? form.querySelector(`[name="${CSS.escape(fieldName)}"]`) : null;

        if (summary) {
            const icon = el('span', 'fa-solid fa-triangle-exclamation me-1');
            icon.setAttribute('aria-hidden', 'true');
            // text-body-emphasis: Atum's light-mode alert-danger text measures 3.96:1 on its own background; plain text-body would drop the dark pair below 4.5:1.
            summary.replaceChildren(icon, el('span', 'text-body-emphasis', control ? text('COM_J2COMMERCE_ERR_FIX_HIGHLIGHTED') : message));
            summary.hidden = false;
        }

        if (!control) {
            // No field to land on: the summary itself takes focus so the message is read and keyboard users start there.
            if (summary) {
                summary.setAttribute('tabindex', '-1');
                summary.focus();
            }

            return;
        }

        const feedback = el('div', 'invalid-feedback d-block');
        const icon = el('span', 'fa-solid fa-circle-exclamation me-1');
        icon.setAttribute('aria-hidden', 'true');
        feedback.id = `${control.id}Error`;
        feedback.dataset.j2cFieldError = '1';
        feedback.append(icon, document.createTextNode(message));
        control.closest('.control-group').append(feedback);
        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid', 'true');
        control.setAttribute('aria-describedby', `${control.getAttribute('aria-describedby') || ''} ${feedback.id}`.trim());
        control.focus();
    };

    const setBusy = (form, busy) => {
        const modal = form.closest('.modal');
        const submit = modal.querySelector('[data-j2c-record-submit]');
        const spinner = modal.querySelector('[data-j2c-record-spinner]');

        form.setAttribute('aria-busy', busy ? 'true' : 'false');
        submit.disabled = busy;

        if (spinner) {
            spinner.hidden = !busy;
        }
    };

    const buildRow = (row) => {
        const tr = document.createElement('tr');
        const method = el('td', '', row.method);

        if (row.kind === 'refund') {
            method.append(' ', el('span', row.badgeClass, text('COM_J2COMMERCE_REFUND')));
        }

        if (row.by) {
            method.append(el('div', 'text-body-secondary', text('COM_J2COMMERCE_RECORDED_BY').replace('%s', row.by)));
        }

        tr.append(el('td', '', row.date), method, el('td', '', row.reference), el('td', 'text-end', row.amount));

        return tr;
    };

    const applyFigures = (figures) => {
        const set = (name, value) => {
            const cell = document.querySelector(`[data-j2c-balance="${name}"]`);

            if (cell) {
                cell.textContent = value;
            }
        };

        set('total', figures.total);
        set('paid', figures.paid);
        set('refunded', figures.refunded);
        set('net', figures.net);
        set('due', figures.balance);

        const due = document.querySelector('[data-j2c-balance="due"]');

        if (due) {
            due.classList.toggle('text-danger', !figures.paidInFull);
            due.classList.toggle('text-success', figures.paidInFull);
        }

        const refundedRow = document.querySelector('[data-j2c-balance-row="refunded"]');

        if (refundedRow) {
            refundedRow.hidden = !figures.hasRefunded;
        }

        const rows = document.querySelector('[data-j2c-recorded-rows]');
        const wrapper = document.getElementById('j2cRecordedPayments');

        if (rows) {
            rows.replaceChildren(...figures.payments.map(buildRow));
        }

        if (wrapper) {
            wrapper.hidden = figures.payments.length === 0;
        }

        document.querySelectorAll('[data-j2c-record-open]').forEach((button) => {
            button.hidden = !(button.dataset.j2cRecordOpen === 'payment' ? figures.canRecordPayment : figures.canRecordRefund);
        });

        const refundAmount = document.getElementById('j2cRrAmount');
        const refundInfo = document.querySelector('[data-j2c-refund-info]');

        if (refundAmount) {
            refundAmount.value = figures.refundDefault;
        }

        if (refundInfo) {
            const icon = el('span', 'fa-solid fa-circle-info me-1');
            icon.setAttribute('aria-hidden', 'true');
            refundInfo.replaceChildren(icon, el('span', 'text-body-emphasis', figures.refundInfo));
        }
    };

    const applyState = (state, status) => {
        const wrapper = document.getElementById('j2cPaymentState');

        if (wrapper) {
            const paymentBadge = wrapper.querySelector('[data-j2c-payment-badge]');
            const icon = wrapper.querySelector('[data-j2c-payment-icon]');
            const label = wrapper.querySelector('[data-j2c-payment-label]');
            const secondary = wrapper.querySelector('[data-j2c-payment-secondary]');

            wrapper.dataset.j2cPaymentState = state.state;

            if (paymentBadge) {
                paymentBadge.className = state.class;
                paymentBadge.hidden = !state.show;
            }

            if (icon) {
                icon.className = `${state.icon} me-1`;
            }

            if (label) {
                label.textContent = state.label;
            }

            if (secondary) {
                secondary.textContent = state.secondary;
                secondary.hidden = !state.show || state.secondary === '';
            }
        }

        const notice = document.querySelector('[data-j2c-conflict-notice]');

        if (notice) {
            notice.hidden = !state.conflict;

            const conflictText = notice.querySelector('[data-j2c-conflict-text]');

            if (conflictText) {
                conflictText.textContent = state.conflictText;
            }
        }

        const badge = document.getElementById('orderStatusBadge');

        if (badge) {
            badge.className = status.cssclass;
            badge.textContent = status.name;
        }

        const select = document.getElementById('headerStatusSelect');

        if (select) {
            select.value = String(status.id);
            select.dataset.currentStatus = String(status.id);
        }
    };

    const submit = async (form) => {
        if (form.getAttribute('aria-busy') === 'true') {
            return;
        }

        const kind = form.dataset.j2cRecordKind;
        const modalEl = form.closest('.modal');
        const body = new FormData(form);

        body.append(options.token, '1');
        body.append('order_id', String(options.orderId));
        clearErrors(form);
        setBusy(form, true);

        try {
            const response = await fetch(urls[kind], {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': options.token },
                body,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {
                showError(form, result.message || text('COM_J2COMMERCE_ERR_GENERIC'), result.field || '');

                return;
            }

            applyState(result.state, result.status);
            applyFigures(result.figures);
            document.dispatchEvent(new CustomEvent('j2commerce:order-history-refresh'));

            form.querySelectorAll('[name="reference"], [name="note"]').forEach((field) => { field.value = ''; });

            const instance = bootstrap.Modal.getInstance(modalEl);
            const returnTo = opener;
            const announcement = `${result.message} ${text('COM_J2COMMERCE_PAYMENT_STATE_ANNOUNCE').replace('%s', result.state.label)}`;

            // The opener may now be hidden itself; fall back to the payment badge, then the status badge.
            // The message is announced once the dialog has closed, so it is not spoken over the dialog's own teardown.
            modalEl.addEventListener('hidden.bs.modal', () => {
                announce(announcement);

                const visible = (node) => node !== null && node.getClientRects().length > 0;
                const paymentState = document.getElementById('j2cPaymentState');
                const target = [
                    returnTo,
                    visible(paymentState?.querySelector('[data-j2c-payment-badge]') ?? null) ? paymentState : null,
                    document.getElementById('orderStatusBadge'),
                ].find((node) => node && visible(node));

                target?.focus();
            }, { once: true });

            instance?.hide();
        } catch (error) {
            showError(form, text('COM_J2COMMERCE_ERROR_NETWORK'), '');
        } finally {
            setBusy(form, false);
        }
    };

    document.querySelectorAll('.j2c-record-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submit(form);
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-j2c-record-open]');

        if (button) {
            opener = button;
        }
    });

    document.querySelectorAll('.modal').forEach((modalEl) => {
        const form = modalEl.querySelector('.j2c-record-form');

        if (!form) {
            return;
        }

        modalEl.addEventListener('shown.bs.modal', () => {
            modalEl.querySelector('select')?.focus();
        });

        modalEl.addEventListener('hidden.bs.modal', () => clearErrors(form));
    });
});
