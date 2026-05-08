/**
 * Coordinates Bootstrap modal handoffs so only one modal is active at a time.
 */
(function (window) {
    'use strict';

    const pendingHandoffs = new Set();

    function getModalElement(id) {
        return document.getElementById(id);
    }

    function isShown(modalEl) {
        return Boolean(modalEl && modalEl.classList.contains('show'));
    }

    function showExclusive(targetId, blockerId) {
        const targetModal = getModalElement(targetId);

        if (!targetModal) {
            return;
        }

        const showTarget = () => bootstrap.Modal.getOrCreateInstance(targetModal).show();

        if (!blockerId) {
            showTarget();
            return;
        }

        const blockerModal = getModalElement(blockerId);

        if (!isShown(blockerModal)) {
            showTarget();
            return;
        }

        const handoffKey = `${targetId}<-${blockerId}`;

        if (pendingHandoffs.has(handoffKey)) {
            return;
        }

        pendingHandoffs.add(handoffKey);

        blockerModal.addEventListener('hidden.bs.modal', () => {
            pendingHandoffs.delete(handoffKey);
            showTarget();
        }, { once: true });

        bootstrap.Modal.getOrCreateInstance(blockerModal).hide();
    }

    window.J2CommerceModalCoordinator = {
        showExclusive,
    };
})(window);

