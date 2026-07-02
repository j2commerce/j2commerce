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

document.addEventListener('DOMContentLoaded', () => {
    const token = Joomla.getOptions('csrf.token', '') || '';

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
        saveBtn.replaceChildren(document.createRange().createContextualFragment('<span class="icon-spinner icon-spin" aria-hidden="true"></span>'));

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
                saveBtn.replaceChildren(document.createRange().createContextualFragment('<span class="icon-check" aria-hidden="true"></span>'));
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
