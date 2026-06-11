/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
    'use strict';

    const reveal = (wrapper) => {
        const trigger = wrapper.querySelector('.j2c-collapsible-trigger');
        const content = wrapper.querySelector('.j2c-collapsible-content');

        if (!content) {
            return;
        }

        content.hidden = false;

        if (trigger) {
            trigger.hidden = true;
        }

        const focusable = content.querySelector('input, select, textarea');

        if (focusable) {
            focusable.focus();
        }
    };

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.j2c-collapsible-trigger');

        if (!trigger) {
            return;
        }

        const wrapper = trigger.closest('[data-j2c-collapsible]');

        if (wrapper) {
            e.preventDefault();
            reveal(wrapper);
        }
    });
})();
