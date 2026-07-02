/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Live Users Widget - auto-refresh polling script
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const widget = document.getElementById('j2commerce-liveusers');
    if (!widget) return;

    const REFRESH_INTERVAL = 60000; // 60 seconds
    const AJAX_URL = 'index.php?option=com_j2commerce&task=dashboard.getLiveUsers&format=json';

    async function refreshLiveUsers() {
        const formData = new FormData();
        // Joomla 6 stores csrf.token as a plain string
        const csrfToken = Joomla.getOptions('csrf.token') || '';
        const tokenName = typeof csrfToken === 'string' ? csrfToken : Object.keys(csrfToken)[0];

        if (tokenName) {
            formData.append(tokenName, 1);
        }

        try {
            const response = await fetch(AJAX_URL, { method: 'POST', body: formData });
            const json = await response.json();

            if (json.success && json.data) {
                updateWidget(json.data);
            }
        } catch (e) {
            console.warn('Live users refresh failed:', e);
        }
    }

    function updateWidget(data) {
        const totalEl = widget.querySelector('.j2commerce-liveusers-total');
        const regEl = widget.querySelector('.j2commerce-liveusers-registered');
        const guestEl = widget.querySelector('.j2commerce-liveusers-guests');
        const badgeEl = widget.querySelector('.j2commerce-liveusers-pulse');

        if (totalEl) totalEl.textContent = data.total;
        if (regEl) regEl.textContent = data.registered;
        if (guestEl) guestEl.textContent = data.guests;
        if (badgeEl) badgeEl.textContent = data.total;

        const listEl = widget.querySelector('.j2commerce-liveusers-list');

        if (listEl && data.users) {
            const html = data.users.map(user => {
                const mins = Math.max(0, Math.floor((Date.now() / 1000 - user.time) / 60));
                const timeText = mins < 1
                    ? Joomla.Text._('COM_J2COMMERCE_LIVE_USERS_JUST_NOW')
                    : Joomla.Text.sprintf('COM_J2COMMERCE_LIVE_USERS_MINUTES_AGO', mins);
                const safeName = user.username.replace(/[&<>"']/g, c =>
                    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

                return `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><span class="icon-user" aria-hidden="true"></span> ${safeName}</span>
                    <small class="text-body-secondary">${timeText}</small>
                </li>`;
            }).join('');
            listEl.replaceChildren(document.createRange().createContextualFragment(html));
        }
    }

    setInterval(refreshLiveUsers, REFRESH_INTERVAL);
});
