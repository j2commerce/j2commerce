/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function (window, document) {
    'use strict';

    const TBODY_ID = 'j2commerce-optionvalues-tbody';
    const ROW = 'tr[data-pov-id]';

    let dragged = null;

    function tbody() {
        return document.getElementById(TBODY_ID);
    }

    function rows() {
        const body = tbody();

        return body ? Array.from(body.querySelectorAll(ROW)) : [];
    }

    /** Drag position is the only source of truth: rewrite every hidden ordering to its row index. */
    function renumber() {
        rows().forEach((row, index) => {
            const input = row.querySelector('input[type="hidden"][name$="[ordering]"]');

            if (input) {
                input.value = index;
            }
        });
    }

    /** Row the pointer is over, and whether it sits past that row's midpoint. */
    function rowUnder(y) {
        return rows().find(row => {
            const box = row.getBoundingClientRect();

            return y < box.bottom;
        }) || null;
    }

    /**
     * A row carrying draggable="true" permanently would swallow text selection in its own
     * inputs, so the attribute is armed only while the pointer is held on the handle.
     */
    function arm(event) {
        const handle = event.target.closest('.j2commerce-ov-drag-handle');
        const row    = handle && handle.closest(ROW);

        if (row && tbody() && tbody().contains(row)) {
            row.draggable = true;
        }
    }

    function disarm() {
        if (dragged) {
            dragged.classList.remove('j2commerce-ov-dragging');
        }

        rows().forEach(row => {
            row.draggable = false;
        });

        dragged = null;
    }

    document.addEventListener('mousedown', arm);
    document.addEventListener('touchstart', arm, { passive: true });
    document.addEventListener('mouseup', disarm);

    document.addEventListener('dragstart', event => {
        const row = event.target.closest && event.target.closest(ROW);

        if (!row || !row.draggable || !tbody() || !tbody().contains(row)) {
            return;
        }

        dragged = row;
        row.classList.add('j2commerce-ov-dragging');

        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            // Firefox starts no drag at all unless something is written to the transfer.
            event.dataTransfer.setData('text/plain', row.dataset.povId || '');
        }
    });

    document.addEventListener('dragover', event => {
        if (!dragged) {
            return;
        }

        const body = tbody();

        if (!body || !body.contains(event.target)) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'move';
        }

        const target = rowUnder(event.clientY);

        if (target !== dragged) {
            body.insertBefore(dragged, target);
        }
    });

    document.addEventListener('drop', event => {
        if (dragged) {
            event.preventDefault();
            renumber();
            disarm();
        }
    });

    document.addEventListener('dragend', disarm);

    window.J2CommerceOptionValuesSortable = { renumber, count: () => rows().length };
})(window, document);
