/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('j2c-paymentupdate-form');

    if (!form) {
        return;
    }

    form.addEventListener('change', (e) => {
        const radio = e.target.closest('.j2c-paymentupdate-method');

        if (!radio) {
            return;
        }

        form.querySelectorAll('.j2c-paymentupdate-cardform').forEach((el) => {
            const isSelected = el.dataset.method === radio.value;
            el.classList.toggle('d-none', !isSelected);
            el.classList.toggle('uk-hidden', !isSelected);
        });
    });
});
