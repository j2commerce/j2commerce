/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

import JoomlaDialog from 'joomla.dialog';

((Joomla, document) => {
    'use strict';

    const options = Joomla.getOptions('com_j2commerce.subjectoverride', {});

    const request = async (task, params, method) => {
        const url = `${options.baseUrl}&task=emailtemplate.${task}`;
        const body = new URLSearchParams({ ...params, [options.token]: 1 });

        const response = await fetch(method === 'POST' ? url : `${url}&${body}`, {
            method,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: method === 'POST' ? body : undefined,
        });

        // JsonResponse answers with a body on failure too, and that body carries the reason,
        // so the payload is read before response.ok is consulted.
        const payload = await response.json().catch(() => null);

        if (!payload || payload.success === false || !response.ok) {
            throw new Error(
                (payload && payload.message)
                || Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_SAVE_FAILED')
            );
        }

        return payload.data;
    };

    /**
     * Open the override dialog for one language key.
     *
     * Takes a descriptor rather than the button that was clicked: the same dialog is opened from
     * inside the GrapesJS canvas, where there is neither a button to read a dataset off nor a page
     * element to write the result into. `onSaved(resolved, applied)` is told whether the language
     * that was written is the one this admin reads in - only then does what is on screen change.
     */
    const open = ({ key, onSaved, returnFocus }) => {
        // Resolved lazily: the dialog renders its buttons before it renders its body, so nothing
        // here can hold an element reference taken at construction time.
        const field = (id) => dialog.getBody().querySelector(id);

        const announce = (message, isError) => {
            const status = field('#j2c-so-status');
            status.className = `j2c-so-status alert ${isError ? 'alert-danger' : 'alert-success'}`;
            status.textContent = message;
        };

        const load = async () => {
            const status = field('#j2c-so-status');
            status.className = 'j2c-so-status';
            status.textContent = '';

            try {
                const data = await request('loadOverride', { key, tag: field('#j2c-so-language').value }, 'GET');
                field('#j2c-so-original').value = data.original;
                field('#j2c-so-text').value = data.override || data.original;
            } catch (error) {
                announce(error.message, true);
            }
        };

        const save = async () => {
            const tag = field('#j2c-so-language').value;

            try {
                const data = await request('saveOverride', { key, tag, text: field('#j2c-so-text').value }, 'POST');

                onSaved(data.resolved, tag === options.adminTag);

                dialog.close();
            } catch (error) {
                // Failure keeps the dialog open, so the reason belongs where the eye already is.
                announce(error.message, true);
            }
        };

        const dialog = new JoomlaDialog({
            popupType: 'inline',
            textHeader: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_TITLE'),
            popupContent: document.getElementById('joomla-dialog-subjectoverride'),
            width: '640px',
            height: 'fit-content',
            popupButtons: [
                { label: options.saveLabel, onClick: save, className: 'btn btn-primary' },
                { label: options.closeLabel, onClick: () => dialog.close(), className: 'btn btn-secondary' },
            ],
        });

        dialog.addEventListener('joomla-dialog:load', () => {
            // The body has many keys where the subject has one, so the dialog is told which key it
            // is editing on every opening rather than being rendered around a single server value.
            field('#j2c-so-key').value = key;
            field('#j2c-so-language').addEventListener('change', load);
            load().then(() => field('#j2c-so-text').focus());
        });

        dialog.addEventListener('joomla-dialog:close', () => {
            dialog.destroy();
            returnFocus?.();
        });

        dialog.show();
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-j2c-subject-key]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const display = document.getElementById(button.dataset.j2cSubjectTarget);
        const pageStatus = document.getElementById(`${button.dataset.j2cSubjectTarget}-status`);

        if (pageStatus) {
            pageStatus.textContent = '';
        }

        open({
            key: button.dataset.j2cSubjectKey,
            onSaved: (resolved, applied) => {
                if (applied && display) {
                    display.value = resolved;
                }

                // Announced on the page, not in the dialog: the dialog is about to close, and a
                // live region removed in the same breath as it is written is never read out.
                if (pageStatus) {
                    pageStatus.textContent = Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_SAVED');
                }
            },
            returnFocus: () => button.focus(),
        });
    });

    // The GrapesJS wrapper is a classic script and cannot import this module, so the one entry
    // point it needs is published on the window.
    window.J2CommerceLangOverride = { open };
})(Joomla, document);
