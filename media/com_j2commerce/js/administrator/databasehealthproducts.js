'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('database-health-products-list');
    const dialog = document.getElementById('database-health-product-delete-dialog');
    const tokenField = document.getElementById('database-health-products-token-field');

    if (!table || !dialog || !tokenField) return;

    new DatabaseHealthProducts(table, dialog, tokenField.name).init();
});

class DatabaseHealthProducts {
    constructor(table, dialog, token) {
        this.table = table;
        this.tbody = table.querySelector('tbody');
        this.dialog = dialog;
        this.dialogWarning = dialog.querySelector('.dhp-product-warning');
        this.dialogConfirm = dialog.querySelector('.dhp-confirm-delete');
        this.token = token;
        this.countEl = document.getElementById('database-health-products-count');
        this.pendingId = null;
        this.pendingRow = null;
    }

    init() {
        this.tbody.addEventListener('click', (e) => {
            const btn = e.target.closest('.database-health-product-delete');
            if (btn) this.requestDelete(btn);
        });

        this.dialogConfirm.addEventListener('click', () => this.confirmDelete());
        this.dialog.querySelectorAll('.dhp-cancel').forEach((btn) => {
            btn.addEventListener('click', () => this.dialog.close());
        });
    }

    requestDelete(btn) {
        if (!window.confirm(Joomla.Text._('JGLOBAL_CONFIRM_DELETE'))) {
            return;
        }

        this.pendingId = btn.dataset.productId;
        this.pendingRow = btn.closest('tr');

        const warning = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETE_WARNING')
            .replace('%s', btn.dataset.productName || ('#' + this.pendingId));
        this.dialogWarning.textContent = warning;

        this.dialog.showModal();
    }

    async confirmDelete() {
        this.dialog.close();

        const id = this.pendingId;
        const row = this.pendingRow;
        this.pendingId = null;
        this.pendingRow = null;

        if (!id || !row) return;

        const body = new URLSearchParams();
        body.append(this.token, '1');
        body.append('id', id);

        try {
            const resp = await fetch('index.php?option=com_j2commerce&task=databasehealth.deleteProduct&format=json', {
                method: 'POST',
                body,
            });

            if (!resp.ok) {
                throw new Error(resp.statusText);
            }

            const json = await resp.json();

            if (!json.success) {
                throw new Error(json.message || 'Delete failed');
            }

            row.remove();
            this.updateCount(json.data && typeof json.data.remaining === 'number' ? json.data.remaining : null);

            if (window.Joomla && typeof Joomla.renderMessages === 'function') {
                Joomla.renderMessages({ message: [json.message || Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCT_DELETED')] });
            }
        } catch (err) {
            if (window.Joomla && typeof Joomla.renderMessages === 'function') {
                Joomla.renderMessages({ error: [err.message] });
            }
        }
    }

    updateCount(remaining) {
        const count = remaining !== null ? remaining : this.tbody.querySelectorAll('tr').length;

        if (this.countEl) {
            this.countEl.textContent = Joomla.Text._('COM_J2COMMERCE_DATABASE_HEALTH_PRODUCTS_REMAINING').replace('%d', String(count));
        }

        if (count === 0 && this.tbody.querySelectorAll('tr').length === 0) {
            const emptyRow = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = this.table.querySelectorAll('thead th').length || 3;
            cell.className = 'text-center text-body-secondary py-4';
            cell.textContent = Joomla.Text._('JGLOBAL_NO_MATCHING_RESULTS');
            emptyRow.appendChild(cell);
            this.tbody.appendChild(emptyRow);
        }
    }
}
