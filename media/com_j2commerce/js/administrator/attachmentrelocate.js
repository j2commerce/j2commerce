'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.j2c-attachment-relocate');

    if (!wrapper) {
        return;
    }

    new AttachmentRelocate(wrapper).init();
});

class AttachmentRelocate {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.endpoint = wrapper.dataset.endpoint || '';
        this.csrfToken = wrapper.dataset.csrfToken || '';
        this.batchLimit = 10;
        this.noteCap = 20;
        this.running = false;
        this.triggerButton = null;
        this.abortController = null;
        this.modalEl = null;
        this.modal = null;
        this.lastAnnouncedMilestone = -1;
    }

    init() {
        this.wrapper.addEventListener('click', (event) => {
            const button = event.target.closest('[data-j2c-relocate]');

            if (!button) {
                return;
            }

            this.triggerButton = button;
            this.start();
        });
    }

    buildModal() {
        if (this.modalEl) {
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'j2cRelocateModalTitle');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');

        const dialog = document.createElement('div');
        dialog.className = 'modal-dialog';

        const content = document.createElement('div');
        content.className = 'modal-content';

        const header = document.createElement('div');
        header.className = 'modal-header';

        const title = document.createElement('h5');
        title.className = 'modal-title';
        title.id = 'j2cRelocateModalTitle';
        title.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_MODAL_TITLE');

        const headerCloseBtn = document.createElement('button');
        headerCloseBtn.type = 'button';
        headerCloseBtn.className = 'btn-close';
        headerCloseBtn.setAttribute('data-bs-dismiss', 'modal');
        headerCloseBtn.setAttribute('aria-label', Joomla.Text._('JCLOSE'));

        header.append(title, headerCloseBtn);

        const body = document.createElement('div');
        body.className = 'modal-body px-3 py-2';

        // role=status + aria-live: the move reports progress without taking focus, which is
        // what 4.1.3 Status Messages asks for.
        const statusText = document.createElement('p');
        statusText.className = 'j2c-relocate-status mb-2';
        statusText.setAttribute('role', 'status');
        statusText.setAttribute('aria-live', 'polite');
        statusText.setAttribute('aria-atomic', 'true');

        const progressWrap = document.createElement('div');
        progressWrap.className = 'progress mb-2';
        progressWrap.setAttribute('role', 'progressbar');
        progressWrap.setAttribute('aria-valuemin', '0');
        progressWrap.setAttribute('aria-valuemax', '100');
        progressWrap.setAttribute('aria-valuenow', '0');
        progressWrap.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_PROGRESS_LABEL'));

        const progressFill = document.createElement('div');
        progressFill.className = 'progress-bar';
        progressFill.style.width = '0%';

        progressWrap.append(progressFill);

        const progressText = document.createElement('p');
        progressText.className = 'j2c-relocate-progress-text text-body-secondary small mb-1';

        const summaryText = document.createElement('p');
        summaryText.className = 'j2c-relocate-summary-text text-body-secondary small mb-2';

        const noteList = document.createElement('ul');
        noteList.className = 'j2c-relocate-notes small text-body-secondary mb-0';

        body.append(statusText, progressWrap, progressText, summaryText, noteList);

        const footer = document.createElement('div');
        footer.className = 'modal-footer';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-outline-secondary';
        cancelBtn.textContent = Joomla.Text._('JCANCEL');
        cancelBtn.addEventListener('click', () => this.cancel());

        const footerCloseBtn = document.createElement('button');
        footerCloseBtn.type = 'button';
        footerCloseBtn.className = 'btn btn-primary';
        footerCloseBtn.setAttribute('data-bs-dismiss', 'modal');
        footerCloseBtn.textContent = Joomla.Text._('JCLOSE');

        footer.append(cancelBtn, footerCloseBtn);

        content.append(header, body, footer);
        dialog.append(content);
        modal.append(dialog);
        document.body.append(modal);

        // Focus returns to the control that opened the dialog — the step the ARIA APG modal
        // dialog pattern names as the one usually missed.
        modal.addEventListener('hidden.bs.modal', () => {
            this.abort();

            if (this.triggerButton && document.body.contains(this.triggerButton)) {
                this.triggerButton.focus();
            }
        });

        this.modalEl = modal;
        this.modal = bootstrap.Modal.getOrCreateInstance(modal);
        this.statusText = statusText;
        this.progressWrap = progressWrap;
        this.progressFill = progressFill;
        this.progressText = progressText;
        this.summaryText = summaryText;
        this.noteList = noteList;
        this.cancelBtn = cancelBtn;
    }

    resetModal() {
        this.lastAnnouncedMilestone = -1;
        this.progressFill.style.width = '0%';
        this.progressWrap.setAttribute('aria-valuenow', '0');
        this.progressText.textContent = '';
        this.summaryText.textContent = '';
        this.statusText.textContent = '';
        this.noteList.replaceChildren();
        this.cancelBtn.disabled = false;
    }

    setButtonsDisabled(disabled) {
        this.wrapper.querySelectorAll('[data-j2c-relocate]').forEach((button) => {
            button.disabled = disabled;
        });
    }

    async start() {
        if (this.running) {
            return;
        }

        this.running = true;
        this.buildModal();
        this.resetModal();
        this.setButtonsDisabled(true);
        this.modal.show();
        this.abortController = new AbortController();

        this.announce(Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_SCANNING'));

        try {
            const scanResponse = await this.postJson('scan', {});

            if (!scanResponse || !scanResponse.success) {
                this.fail(scanResponse ? scanResponse.message : '');
                return;
            }

            const total = scanResponse.data.total || 0;

            if (total === 0) {
                this.updateProgress(0, 0);
                this.announce(Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOTHING'));
                this.cancelBtn.disabled = true;
                return;
            }

            let moved = 0;
            let skipped = 0;
            let failed = 0;
            let finalized = false;
            const allNotes = [];
            let done = false;

            while (!done) {
                const runResponse = await this.postJson('run', { limit: this.batchLimit });

                if (!runResponse || !runResponse.success) {
                    this.fail(runResponse ? runResponse.message : '');
                    return;
                }

                const data = runResponse.data;
                moved += data.moved;
                skipped += data.skipped;
                failed += data.failed;
                allNotes.push(...(data.notes || []));
                finalized = data.finalized;
                done = data.done;

                this.updateProgress(total - data.remaining, total);
                this.updateSummary(moved, skipped, failed);
                this.renderNotes(allNotes);
            }

            this.complete(moved, skipped, failed, finalized, allNotes);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                this.announceCancelled();
            } else {
                this.fail(Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_ERROR'));
            }
        } finally {
            this.running = false;
            this.setButtonsDisabled(false);
        }
    }

    cancel() {
        if (this.running && this.abortController) {
            this.abortController.abort();
        }
    }

    abort() {
        if (this.abortController) {
            this.abortController.abort();
        }
    }

    async postJson(task, params) {
        const body = new URLSearchParams();

        if (this.csrfToken) {
            body.set(this.csrfToken, '1');
        }

        Object.keys(params).forEach((key) => {
            body.set(key, String(params[key]));
        });

        const response = await fetch(this.endpoint + task + '&format=json', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
            signal: this.abortController.signal,
        });

        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }

        try {
            return await response.json();
        } catch (parseError) {
            throw new Error('Invalid JSON response');
        }
    }

    updateProgress(handled, total) {
        const percent = total > 0 ? Math.min(100, Math.max(0, Math.round((handled / total) * 100))) : 100;

        this.progressFill.style.width = percent + '%';
        this.progressWrap.setAttribute('aria-valuenow', String(percent));
        this.progressText.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_PROGRESS')
            .replace('%1$s', String(Math.max(0, handled)))
            .replace('%2$s', String(total))
            .replace('%3$s', String(percent));

        const milestone = Math.floor(percent / 25) * 25;

        if (milestone > this.lastAnnouncedMilestone) {
            this.lastAnnouncedMilestone = milestone;
            this.announce(this.progressText.textContent);
        }
    }

    updateSummary(moved, skipped, failed) {
        this.summaryText.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_SUMMARY')
            .replace('%1$s', String(moved))
            .replace('%2$s', String(skipped))
            .replace('%3$s', String(failed));
    }

    renderNotes(notes) {
        this.noteList.replaceChildren();

        if (notes.length === 0) {
            return;
        }

        const heading = document.createElement('li');
        heading.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOTES');
        this.noteList.append(heading);

        notes.slice(0, this.noteCap).forEach((note) => {
            const item = document.createElement('li');
            item.textContent = note;
            this.noteList.append(item);
        });
    }

    announce(message) {
        this.statusText.textContent = message;
    }

    complete(moved, skipped, failed, finalized, notes) {
        this.updateSummary(moved, skipped, failed);
        this.renderNotes(notes);

        const summary = Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_COMPLETE')
            .replace('%1$s', String(moved))
            .replace('%2$s', String(skipped))
            .replace('%3$s', String(failed));

        const tail = finalized
            ? Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_FINALIZED')
            : Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_NOT_FINALIZED');

        this.announce(summary + ' ' + tail);
        this.cancelBtn.disabled = true;
    }

    fail(message) {
        this.announce(message || Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_ERROR'));
        this.cancelBtn.disabled = true;
    }

    announceCancelled() {
        this.announce(Joomla.Text._('COM_J2COMMERCE_CONFIG_ATTACHMENT_RELOCATE_CANCELLED'));
        this.cancelBtn.disabled = true;
    }
}
