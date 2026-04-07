'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('j2commerce-setup-guide');
    if (!el) return;

    const guide = new SetupGuide(el);
    guide.init();
});

class SetupGuide {
    constructor(el) {
        this.el = el;
        this.offcanvas = null;
        this.token = Joomla.getOptions('csrf.token', '') || '';
        this.baseUrl = 'index.php?option=com_j2commerce';
        this.opts = Joomla.getOptions('com_j2commerce.setupGuide', {});

        this.groupsList = el.querySelector('.setup-groups-list');
        this.detailView = el.querySelector('.setup-detail-view');
        this.loading = el.querySelector('.setup-loading');
        this.backBtn = el.querySelector('[data-setup-back]');
        this.progressLabel = el.querySelector('.setup-progress-label');
        this.progressCount = el.querySelector('.setup-progress-count');
        this.progressBar = el.querySelector('.setup-progress-bar');
        this.progressFill = el.querySelector('.setup-progress-fill');

        this.inDetail = false;
    }

    init() {
        this.offcanvas = bootstrap.Offcanvas.getOrCreateInstance(this.el);

        document.addEventListener('click', (e) => {
            const slide = e.target.closest('[data-message-id="j2commerce_setup_guide"]');
            if (slide && e.target.closest('a.btn')) {
                e.preventDefault();
                this.offcanvas.show();
            }
        });

        this.el.addEventListener('shown.bs.offcanvas', () => {
            if (!this.inDetail) this.loadStatus();
        });

        // Refresh when category wizard completes successfully
        document.addEventListener('j2commerce:wizard:complete', () => {
            this.loadStatus();
        });

        this.el.addEventListener('click', (e) => {
            const actionBtn = e.target.closest('[data-setup-action]');
            if (actionBtn) {
                e.preventDefault();
                return this.handleAction(actionBtn);
            }

            const dismissBtn = e.target.closest('[data-setup-dismiss]');
            if (dismissBtn) {
                e.preventDefault();
                return this.handleDismiss(dismissBtn);
            }

            const saveParamBtn = e.target.closest('[data-setup-save-param]');
            if (saveParamBtn) {
                e.preventDefault();
                return this.handleSaveParam(saveParamBtn);
            }

            const clearParamBtn = e.target.closest('[data-setup-clear-param]');
            if (clearParamBtn) {
                e.preventDefault();
                return this.handleClearParam(clearParamBtn);
            }

            const checkItem = e.target.closest('[data-setup-check]');
            if (checkItem && !e.target.closest('button')) {
                return this.loadDetail(checkItem.dataset.setupCheck);
            }

            if (e.target.closest('[data-setup-back]')) {
                return this.showList();
            }

            // Joomla's guided tour handler checks event.target directly
            // (not .closest()), so clicks on child elements (icons, text)
            // inside the button are missed. We handle it here instead.
            const tourBtn = e.target.closest('.button-start-guidedtour');
            if (tourBtn) {
                e.preventDefault();
                e.stopPropagation();
                const uid = tourBtn.dataset.gtUid;
                if (uid) {
                    sessionStorage.setItem('tourToken', String(Joomla.getOptions('com_guidedtours.token')));
                    // Close the offcanvas before starting the tour
                    if (this.offcanvas) this.offcanvas.hide();
                    // Use a small delay to let the offcanvas close
                    setTimeout(() => {
                        const url = `${Joomla.getOptions('system.paths').rootFull}administrator/index.php?option=com_ajax&plugin=guidedtours&group=system&format=json&uid=${encodeURIComponent(uid)}`;
                        fetch(url)
                            .then(r => r.json())
                            .then(result => {
                                if (result.success && result.data) {
                                    // Store tour ID so it resumes after redirect
                                    sessionStorage.setItem('tourId', result.data.id);
                                    sessionStorage.setItem('stepCount', String(result.data.steps.length));
                                    // If current page doesn't match tour URL, redirect
                                    const rootUri = Joomla.getOptions('system.paths').rootFull;
                                    const tourUrl = result.data.steps[0]?.url || '';
                                    if (tourUrl && window.location.href !== rootUri + tourUrl) {
                                        window.location.href = rootUri + tourUrl;
                                    }
                                } else {
                                    Joomla.renderMessages({ error: [result.message || 'Could not load tour'] });
                                }
                            })
                            .catch(() => Joomla.renderMessages({ error: ['Could not load tour'] }));
                    }, 300);
                }
                return;
            }

            const groupHeader = e.target.closest('.setup-group-header');
            if (groupHeader) {
                return this.toggleGroup(groupHeader);
            }
        });
    }

    async loadStatus() {
        this.showLoading(true);

        try {
            const resp = await fetch(`${this.baseUrl}&task=setupguide.getStatus&format=json`);
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Error');

            this.renderProgress(json.data.progress);
            this.renderGroups(json.data.groups);
            this.showLoading(false);
            this.groupsList.classList.remove('d-none');
        } catch (err) {
            this.showLoading(false);
            this.groupsList.innerHTML = `<div class="alert alert-danger m-3">${err.message}</div>`;
            this.groupsList.classList.remove('d-none');
        }
    }

    async loadDetail(checkId) {
        this.detailView.replaceChildren();
        const spinner = document.createElement('div');
        spinner.className = 'text-center py-4';
      
        const icon = document.createElement('div');
        icon.className = 'spinner-border spinner-border-sm';
        icon.setAttribute('role', 'status');
      
        const hidden = document.createElement('span');
        hidden.className = 'visually-hidden';
        hidden.textContent = Joomla.Text._("COM_J2COMMERCE_LOADING");
      
        icon.appendChild(hidden);      
        spinner.appendChild(icon);
        this.detailView.appendChild(spinner);
        this.showDetail();

        try {
            const resp = await fetch(`${this.baseUrl}&task=setupguide.getDetail&checkId=${encodeURIComponent(checkId)}&format=json`);
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Error');

            const tpl = document.createElement('template');
            tpl.innerHTML = json.data.html;
            this.detailView.replaceChildren(tpl.content);
            this.initTimezoneClocks();
        } catch (err) {
            this.detailView.replaceChildren();
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = err.message;
            this.detailView.appendChild(alert);
        }
    }

    initTimezoneClocks() {
        const container = document.getElementById('j2c-tz-clocks');

        if (!container) return;

        const storeTz     = container.dataset.storeTz;
        const matchMsg    = container.dataset.matchMsg;
        const mismatchMsg = container.dataset.mismatchMsg;

        try {
            const localTz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            const tzNameEl = document.getElementById('j2c-local-tz-name');

            if (tzNameEl) tzNameEl.textContent = localTz || 'Unknown';

            const fmt = (tz) => new Date().toLocaleString('en-US', {
                timeZone: tz, hour: 'numeric', minute: '2-digit', hour12: true
            });

            const update = () => {
                const storeEl = document.getElementById('j2c-store-time');
                const localEl = document.getElementById('j2c-local-time');

                if (storeEl) storeEl.textContent = fmt(storeTz);
                if (localEl && localTz) localEl.textContent = fmt(localTz);
            };

            update();
            this._tzInterval = setInterval(update, 30000);

            const matchEl    = document.getElementById('j2c-tz-match');
            const mismatchEl = document.getElementById('j2c-tz-mismatch');

            if (localTz === storeTz && matchEl) {
                matchEl.classList.remove('d-none');
                matchEl.querySelector('.j2c-tz-msg').textContent = matchMsg;
            } else if (localTz && mismatchEl) {
                mismatchEl.classList.remove('d-none');
                mismatchEl.querySelector('.j2c-tz-msg').textContent = mismatchMsg;
            }
        } catch (e) {
            // Browser doesn't support Intl API — leave local time as --:--
        }
    }

    async handleAction(btn) {
        const checkId = btn.dataset.setupAction;
        const action = btn.dataset.action;
        const params = JSON.parse(btn.dataset.params || '{}');

        // Client-side actions — no AJAX needed
        if (action === 'open_category_wizard') {
            const wizardModal = document.getElementById('j2commerceCategoryWizardModal');
            if (wizardModal) {
                bootstrap.Modal.getOrCreateInstance(wizardModal).show();
            }
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        try {
            const body = new URLSearchParams();
            body.append(this.token, '1');
            body.append('checkId', checkId);
            body.append('action', action);
            body.append('params', JSON.stringify(params));

            const resp = await fetch(`${this.baseUrl}&task=setupguide.runAction&format=json`, {
                method: 'POST',
                body: body,
            });
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Action failed');

            Joomla.renderMessages({ message: [json.message] });
            await this.loadStatus();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled = false;
            btn.textContent = btn.dataset.label || 'Retry';
        }
    }

    async handleSaveParam(btn) {
        const paramName  = btn.dataset.paramName;
        const input      = btn.closest('.input-group')?.querySelector('input[name="param_value"]');
        const paramValue = input?.value?.trim() || '';

        if (!paramValue) {
            input?.focus();
            return;
        }

        const originalText = btn.textContent;
        btn.disabled       = true;
        btn.innerHTML      = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        try {
            const body = new URLSearchParams();
            body.append(this.token, '1');
            body.append('checkId', 'download_id');
            body.append('action', 'save_param');
            body.append('params', JSON.stringify({ param_name: paramName, param_value: paramValue }));

            const resp = await fetch(`${this.baseUrl}&task=setupguide.runAction&format=json`, {
                method: 'POST',
                body: body,
            });
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Save failed');

            Joomla.renderMessages({ message: [Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_PARAM_SAVED') || json.message] });
            this.showList();
            await this.loadStatus();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled   = false;
            btn.textContent = originalText;
        }
    }

    async handleClearParam(btn) {
        const paramName    = btn.dataset.paramName;
        const originalHtml = btn.outerHTML;

        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        try {
            const body = new URLSearchParams();
            body.append(this.token, '1');
            body.append('checkId', 'download_id');
            body.append('action', 'save_param');
            body.append('params', JSON.stringify({ param_name: paramName, param_value: '' }));

            const resp = await fetch(`${this.baseUrl}&task=setupguide.runAction&format=json`, {
                method: 'POST',
                body: body,
            });
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Clear failed');

            const container = btn.closest('[data-setup-param-form]');
            const placeholder = this.escHtml(Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PLACEHOLDER') || 'Enter your Download ID');
            const saveLabel   = this.escHtml(Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_SAVE') || 'Save Download ID');
            container.innerHTML = `<div class="input-group mb-3">
    <input type="text" class="form-control" name="param_value" placeholder="${placeholder}" />
    <button type="button" class="btn btn-primary" data-setup-save-param data-param-name="${this.escHtml(paramName)}">${saveLabel}</button>
</div>`;

            this.loadStatus();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled  = false;
            const spinner = btn.querySelector('.spinner-border');
            if (spinner) {
                spinner.remove();
            }
            if (!btn.innerHTML.trim()) {
                btn.outerHTML = originalHtml;
            }
        }
    }

    async handleDismiss(btn) {
        const checkId = btn.dataset.setupDismiss;
        btn.disabled = true;

        try {
            const body = new URLSearchParams();
            body.append(this.token, '1');
            body.append('checkId', checkId);

            const resp = await fetch(`${this.baseUrl}&task=setupguide.dismiss&format=json`, {
                method: 'POST',
                body: body,
            });
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Failed');

            await this.loadStatus();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled = false;
        }
    }

    renderProgress(progress) {
        this.progressLabel.textContent = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_PROGRESS') || 'Setup Progress';
        this.progressCount.textContent = `${progress.passed}/${progress.total}`;
        this.progressFill.style.width = `${progress.percent}%`;

        // Match dashboard message color logic: <33% danger, <=66% warning, >66% info, 100% success
        const pct = progress.percent;
        const type = progress.passed === progress.total ? 'success'
            : pct < 33 ? 'danger'
            : pct <= 66 ? 'warning'
            : 'info';
        this.progressBar.className = `setup-progress-bar rounded-0 text-bg-${type}`;
        this.progressFill.className = 'setup-progress-fill rounded-0';

        if (progress.passed === progress.total) {
            this.groupsList.innerHTML = `
                <div class="setup-all-complete text-center py-5 px-3">
                    <div class="setup-all-complete-icon mx-auto mb-3">
                        <span class="fa-solid fa-check" aria-hidden="true"></span>
                    </div>
                    <h5>${Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE') || 'All Done!'}</h5>
                    <p class="text-muted">${Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE_DESC') || 'Your store is fully configured.'}</p>
                </div>`;
            this.groupsList.classList.remove('d-none');
        }
    }

    renderGroups(groups) {
        let html = '';

        for (const group of groups) {
            const allPassed = group.passed === group.total;
            const collapsed = allPassed ? ' is-collapsed' : '';
            const hidden = allPassed ? ' d-none' : '';

            html += `<div class="setup-group" data-group="${group.id}">`;
            html += `<div class="setup-group-header${collapsed}">
                <span class="setup-group-chevron icon-chevron-down" aria-hidden="true"></span>
                <span class="setup-group-label flex-grow-1">${this.escHtml(group.label)}</span>
                <span class="badge ${allPassed ? 'bg-success' : 'bg-warning'}">${group.passed}/${group.total}</span>
            </div>`;
            html += `<div class="setup-group-checks${hidden}">`;

            for (const check of group.checks) {
                const statusClass = check.dismissed ? 'dismissed' : check.status;

                const iconMap = {
                    pass: 'fa-regular fa-circle-check text-success',
                    fail: 'fa-regular fa-circle-xmark text-danger',
                    warning: 'fa-regular fa-circle text-warning',
                    dismissed: 'fa-regular fa-circle-minus text-muted',
                };
                const iconClass = iconMap[statusClass] || iconMap.fail;

                html += `<div class="setup-check-item" data-setup-check="${this.escHtml(check.id)}">
                    <span class="${iconClass} setup-check-icon" aria-hidden="true"></span>
                    <span class="setup-check-label flex-grow-1">${this.escHtml(check.label)}</span>`;

                if (check.actions.length > 0 && check.status !== 'pass' && !check.dismissed) {
                    const act = check.actions[0];
                    const actLabel = Joomla.Text._(act.label) || act.label;
                    html += `<button type="button" class="btn btn-sm btn-primary setup-action-btn"
                        data-setup-action="${this.escHtml(check.id)}"
                        data-action="${this.escHtml(act.action)}"
                        data-params='${JSON.stringify(act.params || {})}'
                        data-label="${this.escHtml(actLabel)}">
                        ${this.escHtml(actLabel)}</button>`;
                }

                if (check.dismissible && check.status !== 'pass' && !check.dismissed) {
                    html += `<button type="button" class="btn btn-sm btn-link text-danger setup-dismiss-btn"
                        data-setup-dismiss="${this.escHtml(check.id)}"
                        title="${Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_DISMISS') || 'Dismiss'}">
                        <span class="icon-times" aria-hidden="true"></span></button>`;
                }

                if (check.guidedTourUid) {
                    html += `<button type="button" class="btn btn-sm btn-outline-info button-start-guidedtour ms-1"
                        data-gt-uid="${this.escHtml(check.guidedTourUid)}">
                        <span class="icon-map-signs" aria-hidden="true"></span></button>`;
                }

                html += `</div>`;
            }

            html += `</div></div>`;
        }

        this.groupsList.innerHTML = html;
    }

    showDetail() {
        this.inDetail = true;
        this.groupsList.classList.add('d-none');
        this.detailView.classList.remove('d-none');
        this.backBtn.classList.remove('d-none');
    }

    showList() {
        this.inDetail = false;
        this.detailView.classList.add('d-none');
        this.groupsList.classList.remove('d-none');
        this.backBtn.classList.add('d-none');
    }

    toggleGroup(header) {
        header.classList.toggle('is-collapsed');
        const checks = header.nextElementSibling;
        if (checks) checks.classList.toggle('d-none');
    }

    showLoading(show) {
        this.loading.classList.toggle('d-none', !show);
        if (show) this.groupsList.classList.add('d-none');
    }

    escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}
