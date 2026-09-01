'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('j2commerce-database-health');
    if (!el) return;

    new DatabaseHealthCard(el).init();
});

class DatabaseHealthCard {
    constructor(el) {
        this.el = el;
        this.token = Joomla.getOptions('csrf.token', '') || '';
        this.baseUrl = 'index.php?option=com_j2commerce';
        this.loading = el.querySelector('.database-health-loading');
        this.list = el.querySelector('.database-health-list');
        this.productsReviewModalId = el.dataset.productsReviewModal || '';

        // Resolved server-side by J2htmlHelper::badgeClass() against the badge_style param —
        // never re-derive the text-bg- -> bg- transform here.
        this.badgeClasses = {
            ok: el.dataset.badgeSuccess || 'badge bg-success me-2',
            warning: el.dataset.badgeWarning || 'badge bg-warning me-2',
            info: el.dataset.badgeInfo || 'badge bg-info me-2',
        };
    }

    init() {
        this.el.addEventListener('click', (e) => {
            const fixBtn = e.target.closest('.database-health-fix');
            if (fixBtn) {
                e.preventDefault();
                this.handleFix(fixBtn);
                return;
            }

            const guideBtn = e.target.closest('.database-health-setup-guide');
            if (guideBtn) {
                e.preventDefault();
                this.openSetupGuide();
                return;
            }

            const reviewBtn = e.target.closest('.database-health-review');
            if (reviewBtn) {
                e.preventDefault();
                this.openProductsReviewModal();
            }
        });

        // The Review modal's own list (view=databasehealthproducts) deletes products inside
        // its iframe — this card's count for that row is stale until the modal closes.
        this.productsModal = this.productsReviewModalId
            ? document.getElementById(this.productsReviewModalId)
            : null;
        this.productsIframe = this.productsModal
            ? this.productsModal.querySelector('.database-health-products-iframe')
            : null;

        if (this.productsModal) {
            this.productsModal.addEventListener('close', () => this.load());

            this.productsModal.addEventListener('click', (e) => {
                if (e.target.closest('.database-health-products-dialog-close')) {
                    this.productsModal.close();
                    return;
                }

                // Click on the dialog's own backdrop area (not its content) closes it —
                // <dialog> has no built-in click-outside-to-close behaviour.
                if (e.target === this.productsModal) {
                    this.productsModal.close();
                }
            });
        }

        this.load();
    }

    openSetupGuide() {
        const guide = document.getElementById('j2commerce-setup-guide');
        if (guide && window.bootstrap) {
            bootstrap.Offcanvas.getOrCreateInstance(guide).show();
        }
    }

    // Native <dialog>.showModal() — no Bootstrap JS dependency, so the button being created
    // after page load (inside render()) is not a concern. Loads the iframe on first open only.
    openProductsReviewModal() {
        if (!this.productsModal || this.productsModal.open) {
            return;
        }

        if (this.productsIframe && !this.productsIframe.src) {
            this.productsIframe.src = this.productsIframe.dataset.src;
        }

        this.productsModal.showModal();
    }

    async load() {
        this.showLoading(true);

        try {
            const resp = await fetch(`${this.baseUrl}&task=databasehealth.getStatus&format=json`);
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Error');

            this.render(json.data.checks || []);
        } catch (err) {
            // A failed check run is itself something the admin needs to see, so the card
            // comes out of hiding to carry the error.
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.textContent = err.message;
            this.list.replaceChildren(alert);
            this.list.classList.remove('d-none');
            this.el.classList.remove('d-none');
        } finally {
            this.showLoading(false);
        }
    }

    render(checks) {
        // The card is a to-do list, not an inventory: a check at zero is done, and a
        // report-only check with no Fix and no Review is something the admin cannot act on
        // here. Neither gets a row, and with no rows left the whole card stays hidden —
        // the tmpl ships it with d-none so a healthy store never sees it appear at all.
        const actionable = checks.filter((check) => check.count > 0 && (check.repairable || check.reviewUrl));

        this.list.replaceChildren(...actionable.map((check) => this.rowNode(check)));
        this.list.classList.toggle('d-none', actionable.length === 0);
        this.el.classList.toggle('d-none', actionable.length === 0);
    }

    rowNode(check) {
        const ok = check.count === 0;
        // Three states, never conflated: clear, repairable-and-actionable (Fix button), or
        // report-only-and-informational (never an alarm — migrator_residue in particular is
        // non-zero forever on a migrated store and that is correct, expected state).
        const state = ok ? 'ok' : (check.repairable ? 'warning' : 'info');

        const iconClasses = {
            ok: 'fa-regular fa-circle-check text-success me-2',
            warning: 'fa-solid fa-triangle-exclamation text-warning me-2',
            info: 'fa-solid fa-circle-info text-body-secondary me-2',
        };

        const icon = document.createElement('span');
        icon.className = iconClasses[state];
        icon.setAttribute('aria-hidden', 'true');

        const label = document.createElement('div');
        label.className = 'fw-bold';
        label.textContent = check.label;

        const desc = document.createElement('div');
        desc.className = 'small text-body-secondary';
        desc.textContent = check.description;

        const textWrap = document.createElement('div');
        textWrap.append(label, desc);

        const left = document.createElement('div');
        left.className = 'd-flex align-items-start';
        left.append(icon, textWrap);

        const count = document.createElement('span');
        count.className = this.badgeClasses[state];
        count.textContent = String(check.count);

        const right = document.createElement('div');
        right.className = 'd-flex align-items-center flex-shrink-0';
        right.appendChild(count);

        if (state === 'warning') {
            const fixBtn = document.createElement('button');
            fixBtn.type = 'button';
            fixBtn.className = 'btn btn-sm btn-primary database-health-fix';
            fixBtn.dataset.checkId = check.id;
            fixBtn.dataset.destructive = check.destructive ? '1' : '0';
            fixBtn.dataset.destructiveWarning = check.destructiveWarning || '';
            fixBtn.textContent = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_FIX');
            right.appendChild(fixBtn);
        }

        if (state === 'warning' && check.setupGuideLink) {
            const guideBtn = document.createElement('button');
            guideBtn.type = 'button';
            guideBtn.className = 'btn btn-sm btn-outline-secondary ms-1 database-health-setup-guide';
            guideBtn.textContent = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_VIEW_SETUP_GUIDE');
            right.appendChild(guideBtn);
        }

        // Report-only rows can never be fixed here, so a "Review" affordance is the only
        // action offered — a real admin list in a modal (view=databasehealthproducts), never
        // a synthetic screen.
        if (state === 'info' && check.reviewUrl && this.productsReviewModalId) {
            const reviewBtn = document.createElement('button');
            reviewBtn.type = 'button';
            reviewBtn.className = 'btn btn-sm btn-outline-secondary ms-1 database-health-review';
            reviewBtn.textContent = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_REVIEW');
            right.appendChild(reviewBtn);
        }

        const row = document.createElement('li');
        row.className = 'list-group-item d-flex align-items-center justify-content-between flex-wrap gap-2';
        row.append(left, right);

        return row;
    }

    async handleFix(btn) {
        const checkId = btn.dataset.checkId;
        const destructive = btn.dataset.destructive === '1';
        const warning = btn.dataset.destructiveWarning || Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_DESTRUCTIVE_WARNING');

        if (destructive && !window.confirm(warning)) {
            return;
        }

        btn.disabled = true;
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        btn.replaceChildren(spinner);

        try {
            const body = new URLSearchParams();
            body.append(this.token, '1');
            body.append('checkId', checkId);

            const resp = await fetch(`${this.baseUrl}&task=databasehealth.runAction&format=json`, {
                method: 'POST',
                body,
            });
            const json = await resp.json();

            if (!json.success) throw new Error(json.message || 'Fix failed');

            Joomla.renderMessages({ message: [json.message || Joomla.Text._('JLIB_APPLICATION_SAVE_SUCCESS')] });
            await this.load();
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            btn.disabled = false;
            btn.textContent = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_FIX');
        }
    }

    showLoading(show) {
        this.loading.classList.toggle('d-none', !show);
        if (show) this.list.classList.add('d-none');
    }
}
