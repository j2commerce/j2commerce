/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('orderExportForm');
    const countEl = document.getElementById('orderExportCount');
    const offcanvas = document.getElementById('orderExportOffcanvas');

    if (!form || !countEl) {
        return;
    }

    const token = Joomla.getOptions('csrf.token', '') || '';
    let debounceTimer = null;

    const refreshCount = async () => {
        countEl.textContent = Joomla.Text._('COM_J2COMMERCE_EXPORT_CALCULATING');

        try {
            const formData = new FormData(form);

            const response = await fetch('index.php?option=com_j2commerce&task=orders.exportCount&format=json', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': token },
            });

            if (!response.ok) {
                throw new Error('Network error');
            }

            const result = await response.json();

            if (result.success) {
                countEl.textContent = result.count === 1
                    ? Joomla.Text._('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED_1')
                    : Joomla.Text._('COM_J2COMMERCE_N_ORDERS_WILL_BE_EXPORTED').replaceAll('%d', String(result.count));
            } else {
                countEl.textContent = result.message || '';
            }
        } catch (err) {
            countEl.textContent = countEl.dataset.default || '';
        }
    };

    const scheduleRefresh = () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(refreshCount, 400);
    };

    form.addEventListener('input', scheduleRefresh);
    form.addEventListener('change', scheduleRefresh);

    // The modal user picker adds/removes its hidden inputs without firing form
    // events, so watch the form subtree for those DOM changes as well.
    // Mutations inside the count element itself are ignored — refreshCount()
    // writes to it, and reacting to our own update would loop forever.
    const observer = new MutationObserver((records) => {
        if (records.some((record) => !countEl.contains(record.target))) {
            scheduleRefresh();
        }
    });
    observer.observe(form, { childList: true, subtree: true });

    offcanvas?.addEventListener('shown.bs.offcanvas', refreshCount);
});
