/**
 * Dashboard/Analytics "Add Module" button handler.
 *
 * Opens Joomla's module-type picker in an iframe modal and, once the user
 * picks a type and the module edit form has loaded inside the iframe,
 * pre-fills the Position field with the tab's custom position so the user
 * doesn't have to copy/paste the position code by hand.
 *
 * Triggered by any `[data-j2c-add-module-position]` button.
 */
'use strict';

(function () {
    var modalEl = document.getElementById('j2cAddModuleModal');
    var iframe  = document.getElementById('j2cAddModuleIframe');
    if (!modalEl || !iframe) return;

    var opts = (typeof Joomla !== 'undefined' && Joomla.getOptions)
        ? Joomla.getOptions('com_j2commerce.addModuleModal') || {}
        : {};
    var selectUrl = opts.selectUrl || '';
    if (!selectUrl) return;

    // Position the user is adding a module to — set when a button is clicked,
    // read when the iframe loads the module edit form.
    var targetPosition = '';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-j2c-add-module-position]');
        if (!btn) return;
        e.preventDefault();
        targetPosition = btn.dataset.j2cAddModulePosition || '';
        iframe.src = selectUrl;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    });

    // Reset iframe when modal closes so the next open starts fresh
    modalEl.addEventListener('hidden.bs.modal', function () {
        iframe.src = 'about:blank';
        targetPosition = '';
        // If a module was just created we might want the dashboard to refresh
        // to show it. Safest is a full page reload.
        if (modalEl.dataset.j2cModuleSaved === '1') {
            delete modalEl.dataset.j2cModuleSaved;
            window.location.reload();
        }
    });

    // Watch for iframe navigation to the module edit form and inject position
    iframe.addEventListener('load', function () {
        if (!targetPosition) return;

        var iframeDoc;
        try {
            iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        } catch (err) {
            return; // Cross-origin — shouldn't happen, same site
        }
        if (!iframeDoc) return;

        // Detect the module edit form inside the iframe
        var positionSelect = iframeDoc.querySelector('select[name="jform[position]"]');
        if (!positionSelect) return;

        // If the target position isn't already an option, add it
        var existing = Array.prototype.find.call(positionSelect.options, function (o) {
            return o.value === targetPosition;
        });
        if (!existing) {
            var opt = iframeDoc.createElement('option');
            opt.value = targetPosition;
            opt.text  = targetPosition;
            opt.selected = true;
            positionSelect.appendChild(opt);
        } else {
            existing.selected = true;
        }
        positionSelect.value = targetPosition;

        // Notify the fancy-select web component (Choices.js wrapper) that
        // the underlying select changed. The component listens for 'change'
        // on its select, so we fire one and also trigger a custom reinit
        // if the component exposes one.
        positionSelect.dispatchEvent(new Event('change', { bubbles: true }));

        // Some joomla-field-fancy-select implementations ignore later
        // programmatic option adds; as a fallback, replace the visible
        // Choices.js text with the target position and flag the item
        // selected. This is a best-effort visual sync.
        var fancyHost = positionSelect.closest('joomla-field-fancy-select');
        if (fancyHost) {
            var choicesText = fancyHost.querySelector('.choices__list--single .choices__item');
            if (choicesText) {
                choicesText.textContent = targetPosition;
                choicesText.setAttribute('data-value', targetPosition);
            }
        }

        // Flag so we refresh the dashboard after the user saves & closes
        modalEl.dataset.j2cModuleSaved = '1';
    });
})();
