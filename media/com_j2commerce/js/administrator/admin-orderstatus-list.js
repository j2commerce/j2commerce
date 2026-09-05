/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const token = Joomla.getOptions('csrf.token', '') || '';

    const iconSpan = (className) => {
        const span = document.createElement('span');
        span.className = className;
        span.setAttribute('aria-hidden', 'true');

        return span;
    };

    document.getElementById('orderstatusesList')?.addEventListener('click', async (e) => {
        const saveBtn = e.target.closest('.orderstatus-type-save');
        if (!saveBtn) return;

        e.preventDefault();

        const orderstatusId = parseInt(saveBtn.dataset.orderstatusId, 10);
        const row = saveBtn.closest('tr');
        if (!row || !orderstatusId) return;

        const select = row.querySelector('.orderstatus-type-select');
        if (!select) return;

        const type = select.value;

        saveBtn.disabled = true;
        const origNodes = [...saveBtn.childNodes];
        saveBtn.replaceChildren(iconSpan('icon-spinner icon-spin'));

        try {
            const formData = new FormData();
            formData.append('orderstatus_id', orderstatusId.toString());
            formData.append('orderstatus_type', type);
            if (token) {
                formData.append(token, '1');
            }

            const response = await fetch('index.php?option=com_j2commerce&task=orderstatuses.ajaxSaveType&format=json', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': token },
            });

            if (!response.ok) {
                Joomla.renderMessages({ error: [Joomla.Text._('JERROR_AN_ERROR_HAS_OCCURRED')] });
                saveBtn.replaceChildren(...origNodes);
                saveBtn.disabled = false;
                return;
            }

            const result = await response.json();

            if (result.success) {
                const statusEl = document.getElementById('orderstatus-type-status');
                if (statusEl) {
                    statusEl.textContent = result.message || '';
                }

                saveBtn.replaceChildren(iconSpan('icon-check'));
                saveBtn.classList.remove('btn-primary');
                saveBtn.classList.add('btn-success');
                // Stays disabled until the check icon clears, so a second click cannot capture it as the label.
                setTimeout(() => {
                    saveBtn.replaceChildren(...origNodes);
                    saveBtn.classList.remove('btn-success');
                    saveBtn.classList.add('btn-primary');
                    saveBtn.disabled = false;
                }, 2000);
            } else {
                Joomla.renderMessages({ error: [result.message || Joomla.Text._('JERROR_AN_ERROR_HAS_OCCURRED')] });
                saveBtn.replaceChildren(...origNodes);
                saveBtn.disabled = false;
            }
        } catch {
            Joomla.renderMessages({ error: [Joomla.Text._('JERROR_AN_ERROR_HAS_OCCURRED')] });
            saveBtn.replaceChildren(...origNodes);
            saveBtn.disabled = false;
        }
    });
});
