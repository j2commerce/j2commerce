/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
    'use strict';

    // Workaround for joomla/joomla-cms#47671 — Joomla 6 searchtools Clear button
    // blanks input.value but ignores data-alt-value on calendar fields, so list
    // views remain filtered by the stale ISO date. Mirrors upstream PR #47686.
    // Capture phase fires before Joomla's own clear handler; once #47686 lands
    // this becomes a no-op (data-alt-value already empty).
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.js-stools-btn-clear');
        if (!button) {
            return;
        }

        const form = button.closest('form');
        if (!form) {
            return;
        }

        form.querySelectorAll('input[data-alt-value]').forEach((input) => {
            if (input.getAttribute('data-alt-value') === '') {
                return;
            }
            input.setAttribute('data-alt-value', '');
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }, true);
})();
