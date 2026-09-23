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

    // WCAG 2.2 AA 4.1.2 Name, Role, Value — a fancy select announces the placeholder.
    // Choices.js names the widget it builds from its own config: the role="combobox"
    // wrapper gets a name only when labelId is passed (core never passes one), and the
    // cloned search input takes aria-label from the placeholder, which core's
    // list-fancy-select layout always supplies. The original <select> keeps the real
    // <label for>, but Choices.js hides it, so neither name reaches the user. The label's
    // required star is aria-hidden, so the accessible name stays clean. The stale
    // placeholder aria-label is removed rather than left to outrank: it loses to
    // aria-labelledby today, but would silently resurface if that reference ever broke.
    const nameFancySelect = (container) => {
        const select = container.querySelector('select[id]');
        if (!select) {
            return;
        }

        const labelId = `${select.id}-lbl`;
        if (!document.getElementById(labelId)) {
            return;
        }

        [container, container.querySelector('.choices__input--cloned')].forEach((target) => {
            if (target && target.getAttribute('aria-labelledby') !== labelId) {
                target.setAttribute('aria-labelledby', labelId);
                target.removeAttribute('aria-label');
            }
        });
    };

    const nameFancySelectsIn = (root) => {
        if (root.matches('.choices')) {
            nameFancySelect(root);
        }
        root.querySelectorAll('.choices').forEach(nameFancySelect);
    };

    // Choices.js upgrades on the custom element's connectedCallback, so the widget may not
    // exist yet at DOMContentLoaded, and modals and subforms add more later. Watching only
    // childList keeps the attribute writes above from re-triggering the observer. The root
    // stays documentElement because modals render outside the content container; the cost
    // is one selector match per added subtree, accepted so no fancy select is missed.
    new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    nameFancySelectsIn(node);
                }
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    nameFancySelectsIn(document.body);
})();
