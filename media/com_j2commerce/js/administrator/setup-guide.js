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
        this.lastFocusedCheckId = null;
    }

    init() {
        this.offcanvas = bootstrap.Offcanvas.getOrCreateInstance(this.el);

        // Announce async status/detail updates to assistive tech.
        this.groupsList?.setAttribute('aria-live', 'polite');
        this.groupsList?.setAttribute('aria-atomic', 'false');
        this.detailView?.setAttribute('aria-live', 'polite');
        this.detailView?.setAttribute('aria-atomic', 'false');

        document.addEventListener('click', (e) => {
            const slide = e.target.closest('[data-message-id="j2commerce_setup_guide"]');
            if (slide && e.target.closest('a.btn')) {
                e.preventDefault();
                this.offcanvas.show();
            }
        });

        // Lock page scroll while the setup guide is open
        this.el.addEventListener('show.bs.offcanvas', () => {
            document.body.style.overflow = 'hidden';
        });

        this.el.addEventListener('hidden.bs.offcanvas', () => {
            document.body.style.removeProperty('overflow');
        });

        this.el.addEventListener('shown.bs.offcanvas', () => {
            if (!this.inDetail) this.loadStatus();
        });

        // Tab focus trap — prevent background page scroll when tabbing through the offcanvas
        this.el.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || !this.el.classList.contains('show')) return;
            event.preventDefault();
            this.moveFocusByTab(event.shiftKey);
        });

        // Recovery guard — if focus somehow escapes, pull it back
        document.addEventListener('focusin', (event) => {
            if (!this.el.classList.contains('show')) return;
            if (event.target instanceof Node && this.el.contains(event.target)) return;
            this.keepFocusInside();
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
            if (checkItem && !e.target.closest('button, a, input, select, textarea')) {
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

        this.el.addEventListener('keydown', (e) => {
            if ((e.key !== 'Enter' && e.key !== ' ') || !this.el.classList.contains('show')) {
                return;
            }

            const groupHeader = e.target.closest('.setup-group-header');

            if (groupHeader && !e.target.closest('button, a, input, select, textarea')) {
                e.preventDefault();
                this.toggleGroup(groupHeader);
                return;
            }

            const checkItem = e.target.closest('[data-setup-check]');

            if (!checkItem) {
                return;
            }

            // Let native controls inside the row keep their own keyboard behavior.
            if (e.target.closest('button, a, input, select, textarea')) {
                return;
            }

            e.preventDefault();
            this.loadDetail(checkItem.dataset.setupCheck);
        });
    }

    async loadStatus() {
        this.groupsList?.setAttribute('aria-busy', 'true');
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
            const error = document.createElement('div');
            error.className = 'alert alert-danger m-3';
            error.textContent = err.message;
            this.groupsList.replaceChildren(error);
            this.groupsList.classList.remove('d-none');
        } finally {
            this.groupsList?.setAttribute('aria-busy', 'false');
        }
    }

    async loadDetail(checkId) {
        this.lastFocusedCheckId = checkId;
        this.detailView?.setAttribute('aria-busy', 'true');
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

            J2CommerceDom.adopt(this.detailView, json.data.html);
            this.initTimezoneClocks();

            requestAnimationFrame(() => {
                const target = this.detailView.querySelector('h1, h2, h3, h4, h5, h6, button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');

                if (target instanceof HTMLElement) {
                    if (/^H[1-6]$/.test(target.tagName) && !target.hasAttribute('tabindex')) {
                        target.setAttribute('tabindex', '-1');
                    }
                    target.focus({ preventScroll: true });
                }
            });
        } catch (err) {
            this.detailView.replaceChildren();
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = err.message;
            this.detailView.appendChild(alert);
        } finally {
            this.detailView?.setAttribute('aria-busy', 'false');
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
            const openWizard = () => {
                if (window.J2CommerceModalCoordinator) {
                    window.J2CommerceModalCoordinator.showExclusive('j2commerceCategoryWizardModal', 'j2commerceOnboardingModal');
                    return;
                }

                const wizardModal = document.getElementById('j2commerceCategoryWizardModal');
                if (wizardModal) {
                    bootstrap.Modal.getOrCreateInstance(wizardModal).show();
                }
            };

            if (this.el.classList.contains('show') && this.offcanvas) {
                this.el.addEventListener('hidden.bs.offcanvas', () => {
                    openWizard();
                }, { once: true });
                this.offcanvas.hide();
            } else {
                openWizard();
            }

            return;
        }

        btn.disabled = true;
        btn.replaceChildren(this.loadingSpinner());

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
        btn.replaceChildren(this.loadingSpinner());

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
        const origBtn = btn.cloneNode(true);

        btn.disabled  = true;
        btn.replaceChildren(this.loadingSpinner());

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

            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control';
            input.name = 'param_value';
            input.placeholder = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_PLACEHOLDER');

            const save = document.createElement('button');
            save.type = 'button';
            save.className = 'btn btn-primary';
            save.dataset.setupSaveParam = '';
            save.dataset.paramName = paramName;
            save.textContent = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_CHECK_DOWNLOAD_ID_SAVE');

            const group = document.createElement('div');
            group.className = 'input-group mb-3';
            group.append(input, save);

            container.replaceChildren(group);

            this.loadStatus();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled  = false;
            const spinner = btn.querySelector('.spinner-border');
            if (spinner) {
                spinner.remove();
            }
            if (!btn.innerHTML.trim()) {
                btn.replaceWith(origBtn);
                btn = origBtn;
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
        this.progressLabel.textContent = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_PROGRESS');
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
            const tick = document.createElement('span');
            tick.className = 'fa-solid fa-check';
            tick.setAttribute('aria-hidden', 'true');

            const tickWrap = document.createElement('div');
            tickWrap.className = 'setup-all-complete-icon mx-auto mb-3';
            tickWrap.appendChild(tick);

            const heading = document.createElement('h5');
            heading.textContent = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE');

            const description = document.createElement('p');
            description.className = 'text-body-secondary';
            description.textContent = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_ALL_COMPLETE_DESC');

            const complete = document.createElement('div');
            complete.className = 'setup-all-complete text-center py-5 px-3';
            complete.append(tickWrap, heading, description);

            this.groupsList.replaceChildren(complete);
            this.groupsList.classList.remove('d-none');
        }
    }

    renderGroups(groups) {
        this.groupsList.replaceChildren(...groups.map(group => this.groupNode(group)));
    }

    groupNode(group) {
        const allPassed = group.passed === group.total;
        const checksId  = `setup-group-checks-${group.id}`;

        const chevron = document.createElement('span');
        chevron.className = 'setup-group-chevron icon-chevron-down';
        chevron.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.className = 'setup-group-label flex-grow-1';
        label.textContent = group.label;

        const badge = document.createElement('span');
        badge.className = `badge ${allPassed ? 'bg-success' : 'bg-warning'}`;
        badge.textContent = `${group.passed}/${group.total}`;
        badge.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_N_OF_M_PASSED')
            .replace('%d', group.passed)
            .replace('%d', group.total));

        const header = document.createElement('div');
        header.className = 'setup-group-header' + (allPassed ? ' is-collapsed' : '');
        header.setAttribute('role', 'button');
        header.tabIndex = 0;
        header.setAttribute('aria-controls', checksId);
        header.setAttribute('aria-expanded', allPassed ? 'false' : 'true');
        header.append(chevron, label, badge);

        const checks = document.createElement('div');
        checks.id = checksId;
        checks.className = 'setup-group-checks' + (allPassed ? ' d-none' : '');
        checks.append(...group.checks.map(check => this.checkNode(check)));

        const wrapper = document.createElement('div');
        wrapper.className = 'setup-group';
        wrapper.dataset.group = group.id;
        wrapper.append(header, checks);

        return wrapper;
    }

    checkNode(check) {
        const iconMap = {
            pass: 'fa-regular fa-circle-check text-success',
            fail: 'fa-regular fa-circle-xmark text-danger',
            warning: 'fa-regular fa-circle text-warning',
            dismissed: 'fa-regular fa-circle-minus text-body-secondary',
        };
        const statusTextMap = {
            pass: 'COM_J2COMMERCE_SETUP_GUIDE_STATUS_PASS',
            fail: 'COM_J2COMMERCE_SETUP_GUIDE_STATUS_FAIL',
            warning: 'COM_J2COMMERCE_SETUP_GUIDE_STATUS_WARNING',
            dismissed: 'COM_J2COMMERCE_SETUP_GUIDE_STATUS_DISMISSED',
        };
        const statusClass = check.dismissed ? 'dismissed' : check.status;

        const icon = document.createElement('span');
        icon.className = `${iconMap[statusClass] || iconMap.fail} setup-check-icon`;
        icon.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.className = 'setup-check-label flex-grow-1';
        label.textContent = check.label;

        const item = document.createElement('div');
        item.className = 'setup-check-item';
        item.dataset.setupCheck = check.id;
        item.setAttribute('role', 'button');
        item.tabIndex = 0;
        // The icon is the only visual carrier of the state, so the name carries it in text.
        const statusText = Joomla.Text._(statusTextMap[statusClass] || statusTextMap.fail);
        item.setAttribute('aria-label', `${check.label}, ${statusText}`);
        item.append(icon, label);

        const outstanding = check.status !== 'pass' && !check.dismissed;

        if (check.actions.length > 0 && outstanding) {
            const act      = check.actions[0];
            const actLabel = Joomla.Text._(act.label) || act.label;

            const action = document.createElement('button');
            action.type = 'button';
            action.className = 'btn btn-sm btn-primary setup-action-btn';
            action.dataset.setupAction = check.id;
            action.dataset.action = act.action;
            action.dataset.params = JSON.stringify(act.params || {});
            action.dataset.label = actLabel;
            action.textContent = actLabel;
            item.appendChild(action);
        }

        if (check.dismissible && outstanding) {
            const dismissLabel = Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_DISMISS');

            const dismiss = this.iconButton('btn btn-sm btn-link text-danger setup-dismiss-btn', 'icon-times', dismissLabel);
            dismiss.dataset.setupDismiss = check.id;
            dismiss.title = dismissLabel;
            item.appendChild(dismiss);
        }

        if (check.guidedTourUid) {
            const tour = this.iconButton(
                'btn btn-sm btn-outline-info button-start-guidedtour ms-1',
                'icon-map-signs',
                Joomla.Text._('COM_J2COMMERCE_SETUP_GUIDE_START_GUIDED_TOUR')
            );
            tour.dataset.gtUid = check.guidedTourUid;
            item.appendChild(tour);
        }

        return item;
    }

    iconButton(className, iconClass, ariaLabel) {
        const icon = document.createElement('span');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('aria-label', ariaLabel);
        button.appendChild(icon);

        return button;
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

        if (this.lastFocusedCheckId) {
            requestAnimationFrame(() => {
                const selector = `[data-setup-check="${CSS.escape(this.lastFocusedCheckId)}"]`;
                const row = this.el.querySelector(selector);

                if (row instanceof HTMLElement) {
                    row.focus({ preventScroll: true });
                }
            });
        }
    }

    toggleGroup(header) {
        header.classList.toggle('is-collapsed');
        const checks = header.nextElementSibling;
        if (checks) {
            checks.classList.toggle('d-none');
            header.setAttribute('aria-expanded', checks.classList.contains('d-none') ? 'false' : 'true');
        }
    }

    showLoading(show) {
        this.loading.classList.toggle('d-none', !show);
        if (show) this.groupsList.classList.add('d-none');
    }

    // =========================================================================
    // Focus trap helpers
    // =========================================================================

    getFocusableElements() {
        const selector = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(',');

        return Array.from(this.el.querySelectorAll(selector)).filter((el) => {
            if (!(el instanceof HTMLElement)) return false;
            if (el.hidden || el.getAttribute('aria-hidden') === 'true') return false;
            if (el.closest('[hidden], [aria-hidden="true"], [inert]')) return false;
            return el.offsetParent !== null || el === document.activeElement;
        });
    }

    keepFocusInside() {
        const focusable = this.getFocusableElements();
        if (focusable.length > 0) {
            focusable[0].focus({ preventScroll: true });
        } else {
            this.el.focus({ preventScroll: true });
        }
    }

    moveFocusByTab(backward) {
        const focusable = this.getFocusableElements();
        if (focusable.length === 0) {
            this.el.focus({ preventScroll: true });
            return;
        }

        const currentIndex = focusable.indexOf(document.activeElement);
        let targetIndex;

        if (backward) {
            targetIndex = currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1;
        } else {
            targetIndex = currentIndex < 0 || currentIndex === focusable.length - 1 ? 0 : currentIndex + 1;
        }

        focusable[targetIndex].focus({ preventScroll: true });
    }

    loadingSpinner() {
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');

        const label = document.createElement('span');
        label.className = 'visually-hidden';
        label.textContent = Joomla.Text._('COM_J2COMMERCE_LOADING');
        spinner.appendChild(label);

        return spinner;
    }
}
