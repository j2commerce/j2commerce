/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

window.j2cPrintPackingSlips = function () {
    const form = document.getElementById('adminForm');
    if (!form) return;

    const checked = form.querySelectorAll('input[name="cid[]"]:checked');
    if (checked.length === 0) {
        Joomla.renderMessages({ warning: ['Please select at least one order.'] });
        return;
    }

    const ids = [];
    checked.forEach(cb => ids.push('cid[]=' + cb.value));
    const token = Joomla.getOptions('csrf.token', '') || '';
    window.open('index.php?option=com_j2commerce&task=order.printPackingSlips&' + ids.join('&') + '&' + token + '=1', '_blank');
};

// Payment transaction count for the current selection, so the second prompt can name it.
// Advisory only - the model re-counts and refuses without the flag, whatever this returns.
const j2cCountTransactions = async (task) => {
    const form = document.getElementById('adminForm');
    if (!form) return 0;

    const body = new FormData();
    form.querySelectorAll('input[name="cid[]"]:checked').forEach(cb => body.append('cid[]', cb.value));
    body.append(Joomla.getOptions('csrf.token', ''), '1');

    const response = await fetch('index.php?option=com_j2commerce&task=' + task, { method: 'POST', body });

    if (!response.ok) return 0;

    const json = await response.json();

    return json && json.success ? { orders: json.orders || 0, transactions: json.transactions || 0 } : 0;
};

const j2cReplayClick = (btn, withTransactions) => {
    const form = document.getElementById('adminForm');

    // The flag belongs to the submit it was created for. If the submit does not happen - a
    // validation stop, a listener cancelling it - it must not sit on the form pre-confirming
    // whatever is deleted next, so it is removed either way before being added back.
    form?.querySelector('input[name="confirm_transactions"]')?.remove();

    if (withTransactions && form) {
        const flag = document.createElement('input');
        flag.type = 'hidden';
        flag.name = 'confirm_transactions';
        flag.value = '1';
        form.appendChild(flag);
        setTimeout(() => flag.remove(), 0);
    }

    btn.dataset.j2cConfirmed = '1';
    btn.click();
};

// Capture phase: joomla-toolbar-button wraps the button and submits on its own click listener,
// so a bubble-phase handler would run after the form had already gone.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-j2c-confirm]');
    if (!btn) return;

    // The replayed click from j2cReplayClick(): let it through to the toolbar button once.
    if (btn.dataset.j2cConfirmed === '1') {
        delete btn.dataset.j2cConfirmed;
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    if (!window.confirm(Joomla.Text._(btn.dataset.j2cConfirm))) return;

    const countTask = btn.dataset.j2cCountTask;

    if (!countTask) {
        j2cReplayClick(btn, false);
        return;
    }

    j2cCountTransactions(countTask).then((counts) => {
        if (!counts || counts.transactions === 0) {
            j2cReplayClick(btn, false);
            return;
        }

        const message = Joomla.Text._('COM_J2COMMERCE_CONFIRM_DELETE_ORDERS_WITH_TRANSACTIONS')
            .replace('%1$s', counts.orders)
            .replace('%2$s', counts.transactions);

        if (window.confirm(message)) {
            j2cReplayClick(btn, true);
        }
    }).catch(() => {
        // The count is advisory; on failure fall through to the server, which refuses without
        // the flag and says why.
        j2cReplayClick(btn, false);
    });
}, true);

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-j2c-action="print-packing-slips"]')) {
        e.preventDefault();
        window.j2cPrintPackingSlips();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const token = Joomla.getOptions('csrf.token', '') || '';

    const iconSpan = (className) => {
        const span = document.createElement('span');
        span.className = className;
        span.setAttribute('aria-hidden', 'true');

        return span;
    };

    document.getElementById('ordersList')?.addEventListener('click', async (e) => {
        const saveBtn = e.target.closest('.order-status-save');
        if (!saveBtn) return;

        e.preventDefault();

        const orderId = parseInt(saveBtn.dataset.orderId, 10);
        const row = saveBtn.closest('tr');
        if (!row || !orderId) return;

        const select = row.querySelector('.order-status-select');
        const notifyCheck = row.querySelector('.order-notify-check');
        const newStatus = parseInt(select?.value, 10);
        const notify = notifyCheck?.checked ? 1 : 0;

        if (!newStatus) return;

        // Disable save button and show spinner
        saveBtn.disabled = true;
        const origNodes = [...saveBtn.childNodes];
        saveBtn.replaceChildren(iconSpan('icon-spinner icon-spin'));

        try {
            const formData = new FormData();
            formData.append('order_id', orderId.toString());
            formData.append('new_status', newStatus.toString());
            formData.append('notify', notify.toString());
            if (token) {
                formData.append(token, '1');
            }

            const response = await fetch('index.php?option=com_j2commerce&task=orders.ajaxUpdateStatus&format=json', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': token },
            });

            const result = await response.json();

            if (result.success) {
                // Update the status badge in the Status column
                const badge = row.querySelector('.order-status-badge');
                if (badge && result.data) {
                    badge.className = 'order-status-badge ' + (result.data.cssclass || 'badge text-bg-secondary');
                    badge.textContent = result.data.statusName || '';
                }

                // Show success feedback on button
                saveBtn.replaceChildren(iconSpan('icon-check'));
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-outline-success');
                setTimeout(() => {
                    saveBtn.replaceChildren(...origNodes);
                    saveBtn.classList.remove('btn-outline-success');
                    saveBtn.classList.add('btn-success');
                }, 2000);
            } else {
                Joomla.renderMessages({ error: [result.message || 'Update failed'] });
                saveBtn.replaceChildren(...origNodes);
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message || 'Network error'] });
            saveBtn.replaceChildren(...origNodes);
        } finally {
            saveBtn.disabled = false;
        }
    });
});
