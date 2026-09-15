'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.querySelector('.j2c-regenerate-images');

    if (!wrapper) {
        return;
    }

    new RegenerateImages(wrapper).init();
});

// Summary-card icon per state; colours for each state live in regenerateimages.css (.is-running, .is-complete, …).
const STATE_ICONS = {
    running: 'fa-arrows-rotate',
    complete: 'fa-circle-check',
    error: 'fa-triangle-exclamation',
    cancelled: 'fa-circle-minus',
};

class RegenerateImages {
    constructor(wrapper) {
        this.wrapper = wrapper;
        this.endpoint = wrapper.dataset.endpoint || '';
        this.csrfToken = wrapper.dataset.csrfToken || '';
        this.batchLimit = 10;
        this.running = false;
        this.scope = '';
        this.scopeLabel = '';
        this.scopeSelect = null;
        this.startButton = null;
        this.errors = [];
        this.triggerButton = null;
        this.abortController = null;
        this.pendingChoice = null;
        this.modalEl = null;
        this.modal = null;
        this.modalShown = false;
        this.noticeWrap = null;
        this.lastAnnouncedMilestone = -1;
        this.lastTotal = 0;
        // Several sites can share one origin (localhost/site-a, localhost/site-b), so the endpoint is part of the key.
        this.storagePrefix = 'j2c-regenerate-images:' + this.endpoint + ':';
        this.beforeUnloadHandler = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };
    }

    init() {
        this.scopeSelect = this.wrapper.querySelector('[data-j2c-regen-scope]');
        this.startButton = this.wrapper.querySelector('[data-j2c-regen-start]');

        if (!this.scopeSelect || !this.startButton) {
            return;
        }

        this.scopeSelect.addEventListener('change', () => {
            this.startButton.disabled = this.scopeSelect.value === '';
        });

        this.startButton.addEventListener('click', () => {
            const option = this.scopeSelect.selectedOptions[0];

            if (!option || option.value === '') {
                return;
            }

            this.triggerButton = this.startButton;
            this.scopeLabel = option.textContent.trim();
            this.start(option.value);
        });

        this.renderResumeNotices();
    }

    buildModal() {
        if (this.modalEl) {
            return;
        }

        const el = (tag, className, text) => {
            const node = document.createElement(tag);

            if (className) {
                node.className = className;
            }

            if (text !== undefined) {
                node.textContent = text;
            }

            return node;
        };
        const icon = (className) => {
            const node = el('i', 'fa-solid ' + className);

            node.setAttribute('aria-hidden', 'true');

            return node;
        };

        const modal = el('div', 'modal fade j2c-regen-modal');
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'j2cRegenModalTitle');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('data-bs-backdrop', 'static');

        const dialog = el('div', 'modal-dialog modal-dialog-centered modal-dialog-scrollable j2c-regen-dialog');
        const content = el('div', 'modal-content j2c-regen-content');

        const header = el('div', 'modal-header j2c-regen-header');
        const title = el('h5', 'modal-title j2c-regen-title', Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_MODAL_TITLE'));
        title.id = 'j2cRegenModalTitle';

        const headerCloseBtn = el('button', 'btn-close j2c-regen-close');
        headerCloseBtn.type = 'button';
        headerCloseBtn.setAttribute('data-bs-dismiss', 'modal');
        headerCloseBtn.setAttribute('aria-label', Joomla.Text._('JCLOSE'));
        header.append(title, headerCloseBtn);

        const body = el('div', 'modal-body d-grid j2c-regen-body');

        // Visible text updates on every batch; this live region announces only prompts and 25% milestones.
        const liveRegion = el('span', 'visually-hidden');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');

        const summaryCard = el('div', 'card j2c-regen-summary is-running');
        const summaryCardBody = el('div', 'card-body j2c-regen-summary-body');
        const summaryRow = el('div', 'd-flex align-items-center j2c-regen-summary-row');
        const percentEl = el('span', 'j2c-regen-percent', '0%');
        const countEl = el('span', 'flex-grow-1 j2c-regen-count');
        const statusIcon = icon(STATE_ICONS.running + ' j2c-regen-status-icon');
        summaryRow.append(percentEl, countEl, statusIcon);

        const progressWrap = el('div', 'progress j2c-regen-progress');
        progressWrap.setAttribute('role', 'progressbar');
        progressWrap.setAttribute('aria-valuemin', '0');
        progressWrap.setAttribute('aria-valuemax', '100');
        progressWrap.setAttribute('aria-valuenow', '0');
        progressWrap.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_LABEL'));

        const progressFill = el('div', 'progress-bar j2c-regen-progress-bar is-animated');
        progressFill.style.width = '0%';
        progressWrap.append(progressFill);

        const reportBtn = el('button', 'j2c-regen-report');
        reportBtn.type = 'button';
        reportBtn.hidden = true;
        reportBtn.append(icon('fa-download j2c-regen-report-icon'), document.createTextNode(Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_DOWNLOAD_REPORT')));
        reportBtn.addEventListener('click', () => this.downloadReport());

        summaryCardBody.append(summaryRow, progressWrap, reportBtn);
        summaryCard.append(summaryCardBody);

        const statsWrap = el('div', 'd-grid j2c-regen-stats');
        statsWrap.setAttribute('role', 'group');
        statsWrap.setAttribute('aria-labelledby', 'j2cRegenStatsHeading');
        statsWrap.hidden = true;

        const statsHeading = el('p', 'visually-hidden', Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_HEADING'));
        statsHeading.id = 'j2cRegenStatsHeading';
        statsWrap.append(statsHeading);

        const summaryCounts = {};
        let failedCard = null;

        // The label carries the meaning; colour only reinforces it (see the per-stat rules in regenerateimages.css).
        [
            ['generated', 'COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_GENERATED', 'fa-circle-check'],
            ['skipped', 'COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_SKIPPED', 'fa-circle-minus'],
            ['failed', 'COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_FAILED', 'fa-circle-xmark'],
        ].forEach(([key, labelKey, iconClass]) => {
            const card = el('div', 'card j2c-regen-stat j2c-regen-stat-' + key);
            const cardBody = el('div', 'card-body d-flex align-items-center j2c-regen-stat-body');
            const tile = el('span', 'd-flex align-items-center justify-content-center flex-shrink-0 j2c-regen-stat-tile');
            tile.append(icon(iconClass + ' j2c-regen-stat-icon'));

            const value = el('span', 'j2c-regen-stat-value', '0');

            cardBody.append(tile, el('span', 'flex-grow-1 j2c-regen-stat-label', Joomla.Text._(labelKey)), value);
            card.append(cardBody);
            statsWrap.append(card);
            summaryCounts[key] = value;

            if (key === 'failed') {
                failedCard = card;
            }
        });

        // The design's log box. Individual images are never listed on screen (they go to the report), so it stays empty.
        const logCard = el('div', 'card j2c-regen-log-card');
        logCard.hidden = true;
        logCard.append(el('ul', 'list-group list-group-flush j2c-regen-log'));

        const keepOpenAlert = el('div', 'alert alert-warning j2c-regen-notice');
        keepOpenAlert.hidden = true;
        keepOpenAlert.append(
            icon('fa-triangle-exclamation j2c-regen-notice-icon'),
            el('span', 'j2c-regen-notice-text', Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_KEEP_OPEN'))
        );

        body.append(liveRegion, summaryCard, statsWrap, logCard, keepOpenAlert);

        const footer = el('div', 'modal-footer j2c-regen-footer');

        const cancelBtn = el('button', 'btn btn-outline-danger btn-sm j2c-regen-btn-cancel', Joomla.Text._('JCANCEL'));
        cancelBtn.type = 'button';
        cancelBtn.addEventListener('click', () => this.cancel());

        const startOverBtn = el('button', 'btn btn-outline-primary btn-sm j2c-regen-btn-start-over', Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_START_OVER'));
        startOverBtn.type = 'button';
        startOverBtn.hidden = true;
        startOverBtn.addEventListener('click', () => this.choose('restart'));

        const startBtn = el('button', 'btn btn-primary btn-sm j2c-regen-btn-start');
        startBtn.type = 'button';
        startBtn.hidden = true;
        startBtn.addEventListener('click', () => this.choose(startBtn.dataset.choice || 'start'));

        const footerCloseBtn = el('button', 'btn btn-primary btn-sm j2c-regen-btn-close', Joomla.Text._('JCLOSE'));
        footerCloseBtn.type = 'button';
        footerCloseBtn.setAttribute('data-bs-dismiss', 'modal');

        footer.append(cancelBtn, startOverBtn, startBtn, footerCloseBtn);

        content.append(header, body, footer);
        dialog.append(content);
        modal.append(dialog);
        document.body.append(modal);

        modal.addEventListener('shown.bs.modal', () => {
            this.modalShown = true;
        });

        modal.addEventListener('hidden.bs.modal', () => {
            this.modalShown = false;
            this.abort();

            // Mid-run the controls are still disabled and cannot take focus; the run's finally block returns it.
            if (!this.running && this.triggerButton) {
                this.triggerButton.focus();
            }
        });

        this.modalEl = modal;
        // A static backdrop keeps a stray click outside the dialog from cancelling a run; Escape, Close and Cancel still work.
        this.modal = bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static' });
        this.liveRegion = liveRegion;
        this.summaryCard = summaryCard;
        this.percentEl = percentEl;
        this.countEl = countEl;
        this.statusIcon = statusIcon;
        this.progressWrap = progressWrap;
        this.progressFill = progressFill;
        this.reportBtn = reportBtn;
        this.statsWrap = statsWrap;
        this.summaryCounts = summaryCounts;
        this.failedCard = failedCard;
        this.logCard = logCard;
        this.keepOpenAlert = keepOpenAlert;
        this.cancelBtn = cancelBtn;
        this.startOverBtn = startOverBtn;
        this.startBtn = startBtn;
        this.closeBtn = footerCloseBtn;
    }

    /** running | complete | error | cancelled */
    setCardState(state) {
        this.summaryCard.className = 'card j2c-regen-summary is-' + state;
        this.statusIcon.className = 'fa-solid ' + STATE_ICONS[state] + ' j2c-regen-status-icon';
        this.progressFill.classList.toggle('is-animated', state === 'running');
    }

    resetModal() {
        this.lastAnnouncedMilestone = -1;
        this.lastTotal = 0;
        this.errors = [];
        this.setCardState('running');
        this.percentEl.textContent = '0%';
        this.progressFill.style.width = '0%';
        this.progressWrap.setAttribute('aria-valuenow', '0');
        this.updateSummary(0, 0, 0);
        this.statsWrap.hidden = true;
        this.logCard.hidden = true;
        this.countEl.textContent = '';
        this.liveRegion.textContent = '';
        this.reportBtn.hidden = true;
        this.setStage('scanning');
    }

    /** scanning | confirm | confirm-resume | running | finished */
    setStage(stage) {
        const confirming = stage === 'confirm' || stage === 'confirm-resume';

        // The confirm and resume questions use the page's info-alert look instead of the progress card's.
        this.summaryCard.classList.toggle('is-prompt', confirming);
        this.startBtn.hidden = !confirming;
        this.startOverBtn.hidden = stage !== 'confirm-resume';
        this.closeBtn.hidden = confirming;
        this.cancelBtn.disabled = stage === 'finished';
        this.percentEl.hidden = confirming;
        this.statusIcon.hidden = confirming;
        this.progressWrap.hidden = confirming;
        this.keepOpenAlert.hidden = stage !== 'running';
    }

    /** Focus an element inside the dialog once Bootstrap has finished showing it, so its own focus call cannot take it back. */
    focusInModal(element) {
        if (this.modalShown) {
            element.focus();

            return;
        }

        this.modalEl.addEventListener('shown.bs.modal', () => element.focus(), { once: true });
    }

    setButtonsDisabled(disabled) {
        this.scopeSelect.disabled = disabled;
        this.startButton.disabled = disabled || this.scopeSelect.value === '';
    }

    async start(scope) {
        if (this.running) {
            return;
        }

        this.running = true;
        this.scope = scope;
        this.buildModal();
        this.resetModal();
        this.setButtonsDisabled(true);
        this.abortController = new AbortController();
        this.modal.show();

        this.setStatus(Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SCANNING'));

        try {
            const scanResponse = await this.postJson('scan', { scope });

            if (!scanResponse || !scanResponse.success) {
                this.fail(scanResponse ? scanResponse.message : '');
                return;
            }

            const total = scanResponse.data.total || 0;

            if (total === 0) {
                this.clearProgress(scope);
                this.updateProgress(0, 0);
                this.complete(0, 0, 0);
                return;
            }

            const saved = this.loadProgress(scope);
            const resumable = saved && saved.offset < total ? saved : null;
            const choice = await this.chooseStart(total, resumable);

            if (choice === 'cancel') {
                this.announceCancelled();
                return;
            }

            if (choice === 'restart') {
                this.clearProgress(scope);
            }

            let progress = choice === 'resume'
                ? { offset: resumable.offset, total, generated: resumable.generated, skipped: resumable.skipped, failed: resumable.failed }
                : { offset: 0, total, generated: 0, skipped: 0, failed: 0 };
            let done = false;

            this.setStage('running');
            // The Start button just hid itself; keep focus inside the dialog on its container rather than on Cancel.
            this.focusInModal(this.modalEl);
            this.updateProgress(progress.offset, total);
            this.updateSummary(progress.generated, progress.skipped, progress.failed);
            window.addEventListener('beforeunload', this.beforeUnloadHandler);

            while (!done) {
                const runResponse = await this.postJson('run', { scope, offset: progress.offset, limit: this.batchLimit });

                if (!runResponse || !runResponse.success) {
                    this.fail(runResponse ? runResponse.message : '');
                    return;
                }

                const data = runResponse.data;

                progress = {
                    offset: data.nextOffset,
                    total: data.total,
                    generated: progress.generated + data.generated,
                    skipped: progress.skipped + data.skipped,
                    failed: progress.failed + data.failed,
                };
                this.errors.push(...data.errors);
                done = data.done;

                if (done) {
                    this.clearProgress(scope);
                } else {
                    this.saveProgress(scope, progress);
                }

                this.updateProgress(progress.offset, progress.total);
                this.updateSummary(progress.generated, progress.skipped, progress.failed);
                this.updateReportButton();
            }

            this.complete(progress.generated, progress.skipped, progress.failed);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                this.announceCancelled();
            } else {
                this.fail(Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERROR'));
            }
        } finally {
            window.removeEventListener('beforeunload', this.beforeUnloadHandler);
            this.pendingChoice = null;
            this.running = false;
            this.setButtonsDisabled(false);
            this.renderResumeNotices();

            if (!this.modalShown && this.triggerButton) {
                this.triggerButton.focus();
            }
        }
    }

    /** Resolves to 'start', 'resume', 'restart' or 'cancel' once the user answers the confirm step. */
    chooseStart(total, saved) {
        return new Promise((resolve) => {
            this.pendingChoice = resolve;

            if (saved) {
                this.startBtn.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME');
                this.startBtn.dataset.choice = 'resume';
                this.setStatus(
                    Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME_PROMPT')
                        .replace('%1$s', String(saved.offset))
                        .replace('%2$s', String(total))
                );
                this.setStage('confirm-resume');
            } else {
                this.startBtn.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_START');
                this.startBtn.dataset.choice = 'start';
                this.setStatus(Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_CONFIRM').replace('%s', String(total)));
                this.setStage('confirm');
            }

            this.focusInModal(this.startBtn);
        });
    }

    choose(choice) {
        if (!this.pendingChoice) {
            return;
        }

        const resolve = this.pendingChoice;

        this.pendingChoice = null;
        resolve(choice);
    }

    cancel() {
        if (this.pendingChoice) {
            this.choose('cancel');
            return;
        }

        if (this.running && this.abortController) {
            this.abortController.abort();
        }
    }

    abort() {
        this.choose('cancel');

        if (this.abortController) {
            this.abortController.abort();
        }
    }

    loadProgress(scope) {
        try {
            const raw = window.localStorage.getItem(this.storagePrefix + scope);
            const saved = raw ? JSON.parse(raw) : null;

            return saved
                && Number.isInteger(saved.offset)
                && Number.isInteger(saved.total)
                && saved.offset > 0
                && saved.offset < saved.total
                ? saved
                : null;
        } catch (error) {
            return null;
        }
    }

    saveProgress(scope, progress) {
        try {
            window.localStorage.setItem(this.storagePrefix + scope, JSON.stringify(progress));
        } catch (error) {
            // Storage blocked (private window, disabled site data): the run continues, it just cannot be resumed.
        }
    }

    clearProgress(scope) {
        try {
            window.localStorage.removeItem(this.storagePrefix + scope);
        } catch (error) {
            // Storage blocked: nothing was saved to clear.
        }
    }

    renderResumeNotices() {
        if (!this.noticeWrap) {
            this.noticeWrap = document.createElement('div');
            this.noticeWrap.className = 'j2c-regen-resume-notices';
            this.wrapper.append(this.noticeWrap);
        }

        const notices = [];

        Array.from(this.scopeSelect.options).forEach((option) => {
            const saved = option.value !== '' ? this.loadProgress(option.value) : null;

            if (!saved) {
                return;
            }

            const notice = document.createElement('div');
            notice.className = 'alert alert-info';
            notice.textContent = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_RESUME_NOTICE')
                .replace('%1$s', option.textContent.trim())
                .replace('%2$s', String(saved.offset))
                .replace('%3$s', String(saved.total));
            notices.push(notice);
        });

        this.noticeWrap.replaceChildren(...notices);
        this.noticeWrap.hidden = notices.length === 0;
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

    updateProgress(offset, total) {
        this.lastTotal = total;

        const percent = total > 0 ? Math.min(100, Math.round((offset / total) * 100)) : 100;
        // The language string escapes the literal percent sign as %% for PHP's sprintf. It is the fuller,
        // milestone-only announcement; the shorter PROGRESS_COUNT string is what stays visible in the card.
        const announceMessage = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS')
            .replace('%1$s', String(offset))
            .replace('%2$s', String(total))
            .replace('%3$s', String(percent))
            .replace('%%', '%');
        const countText = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_COUNT')
            .replace('%1$s', String(offset))
            .replace('%2$s', String(total));
        const milestone = Math.floor(percent / 25) * 25;
        const announce = milestone > this.lastAnnouncedMilestone;

        if (announce) {
            this.lastAnnouncedMilestone = milestone;
        }

        this.percentEl.textContent = percent + '%';
        this.progressFill.style.width = percent + '%';
        this.progressWrap.setAttribute('aria-valuenow', String(percent));
        this.setStatus(countText, announce, announceMessage);
    }

    updateSummary(generated, skipped, failed) {
        this.summaryCounts.generated.textContent = String(generated);
        this.summaryCounts.skipped.textContent = String(skipped);
        this.summaryCounts.failed.textContent = String(failed);
        this.statsWrap.hidden = false;
        this.logCard.hidden = false;
        this.failedCard.classList.toggle('has-failures', failed > 0);
    }

    summaryLines() {
        return [
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_HEADING'),
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_GENERATED') + ': ' + this.summaryCounts.generated.textContent,
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_SKIPPED') + ': ' + this.summaryCounts.skipped.textContent,
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_FAILED') + ': ' + this.summaryCounts.failed.textContent,
        ];
    }

    describeError(entry) {
        const status = entry.status === 'failed'
            ? Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_FAILED')
            : Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_STATUS_SKIPPED');
        const product = Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_REPORT_PRODUCT').replace('%s', String(entry.productId));
        const sizes = {
            thumbs: Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SIZE_THUMBS'),
            tiny: Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_SIZE_TINY'),
        };

        // A row-level entry (e.g. alt text left uncopied) belongs to no single size.
        return [status, product, sizes[entry.size] || '', entry.message].filter(Boolean);
    }

    /** Failed entries first, then skipped, so the report leads with the images that need attention. */
    orderedErrors() {
        return [
            ...this.errors.filter((entry) => entry.status === 'failed'),
            ...this.errors.filter((entry) => entry.status !== 'failed'),
        ];
    }

    /** Individual skipped and failed images are never listed on screen; they go only to the downloadable report. */
    updateReportButton() {
        this.reportBtn.hidden = this.errors.length === 0;
    }

    /** Every skipped and failed image with its reason, as a tab-separated text file. */
    downloadReport() {
        const lines = [
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_MODAL_TITLE') + ' - ' + (this.scopeLabel || this.scope),
            new Date().toISOString(),
            ...this.summaryLines(),
            '',
            ...this.orderedErrors().map((entry) => this.describeError(entry).join('\t')),
        ];
        const url = URL.createObjectURL(new Blob([lines.join('\r\n') + '\r\n'], { type: 'text/plain;charset=utf-8' }));
        const link = document.createElement('a');

        link.href = url;
        link.download = 'image-regeneration-' + this.scope + '-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.txt';
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    /**
     * Visible text always goes to the card's count line. announce=false keeps a per-batch refresh from
     * flooding screen readers; announceMessage lets a milestone speak a fuller sentence than what's shown.
     */
    setStatus(message, announce = true, announceMessage = message) {
        this.countEl.textContent = message;

        if (announce) {
            this.liveRegion.textContent = announceMessage;
        }
    }

    complete(generated, skipped, failed) {
        this.updateSummary(generated, skipped, failed);
        this.updateReportButton();
        this.setCardState('complete');
        this.setStatus(
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_PROGRESS_COUNT')
                .replace('%1$s', String(this.lastTotal))
                .replace('%2$s', String(this.lastTotal)),
            true,
            Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_COMPLETE')
                .replace('%1$s', String(generated))
                .replace('%2$s', String(skipped))
                .replace('%3$s', String(failed))
        );
        this.setStage('finished');
    }

    fail(message) {
        this.setCardState('error');
        this.setStatus(message || Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_ERROR'));
        this.setStage('finished');
    }

    announceCancelled() {
        this.setCardState('cancelled');
        this.setStatus(Joomla.Text._('COM_J2COMMERCE_CONFIG_IMAGE_REGENERATE_CANCELLED'));
        this.setStage('finished');
    }
}
