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

    document.getElementById('advancedPricingList')?.addEventListener('click', async (e) => {
        const saveBtn = e.target.closest('.price-save-btn');
        if (!saveBtn) return;

        e.preventDefault();

        const priceId = parseInt(saveBtn.dataset.id, 10);
        const row = saveBtn.closest('tr');
        if (!row || !priceId) return;

        const input = row.querySelector('.advancedpricing-price-input');
        const newPrice = parseFloat(input?.value);

        if (isNaN(newPrice) || newPrice < 0) {
            Joomla.renderMessages({ error: ['Invalid price value.'] });
            return;
        }

        // Disable button and show spinner
        saveBtn.disabled = true;
        const origHTML = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="icon-spinner icon-spin" aria-hidden="true"></span>';

        try {
            const formData = new FormData();
            formData.append('productprice_id', priceId.toString());
            formData.append('price', newPrice.toString());
            if (token) {
                formData.append(token, '1');
            }

            const response = await fetch('index.php?option=com_j2commerce&task=advancedpricing.ajaxSavePrice&format=json', {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': token },
            });

            const result = await response.json();

            if (result.success) {
                // Update original value
                const decimals = parseInt(input.dataset.decimals, 10) || 2;
                input.dataset.original = newPrice.toFixed(decimals);

                // Show success feedback
                saveBtn.innerHTML = '<span class="icon-check" aria-hidden="true"></span>';
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-outline-success');
                setTimeout(() => {
                    saveBtn.innerHTML = origHTML;
                    saveBtn.classList.remove('btn-outline-success');
                    saveBtn.classList.add('btn-success');
                }, 2000);
            } else {
                Joomla.renderMessages({ error: [result.message || 'Save failed'] });
                saveBtn.innerHTML = origHTML;
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message || 'Network error'] });
            saveBtn.innerHTML = origHTML;
        } finally {
            saveBtn.disabled = false;
        }
    });
});
