/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('j2c-order-view');
    if (!root) return;

    const orderId = parseInt(root.dataset.orderId, 10);
    const token = root.dataset.token;

    // === Helper: AJAX POST with dual token delivery ===
    async function postAjax(task, body = {}) {
        const formData = new FormData();
        formData.append(token, '1');
        formData.append('order_id', orderId.toString());

        for (const [key, value] of Object.entries(body)) {
            formData.append(key, String(value));
        }

        const response = await fetch(`index.php?option=com_j2commerce&task=order.${task}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token },
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    // === Helper: Joomla message rendering ===
    function showMessage(type, text) {
        // Clear previous messages
        const container = document.getElementById('system-message-container');
        if (container) {
            container.innerHTML = '';
        }

        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            Joomla.renderMessages({ [type]: [text] });
        }
    }

    // === Helper: Brief success flash on an element ===
    function flashSuccess(el) {
        if (!el) return;
        el.classList.add('j2c-save-success');
        setTimeout(() => el.classList.remove('j2c-save-success'), 1000);
    }

    // === Save Status (header bar) ===
    const headerSaveBtn = document.getElementById('headerSaveStatus');
    if (headerSaveBtn) {
        headerSaveBtn.addEventListener('click', async () => {
            const statusSelect = document.getElementById('headerStatusSelect');
            const notifyCheck = document.getElementById('notifyCustomer');
            if (!statusSelect) return;

            headerSaveBtn.classList.add('j2c-saving');

            try {
                const result = await postAjax('ajaxUpdateStatus', {
                    order_state_id: statusSelect.value,
                    notify_customer: notifyCheck?.checked ? '1' : '0',
                });

                if (result.success) {
                    showMessage('message', result.message);

                    const badge = document.getElementById('orderStatusBadge');
                    if (badge && result.data) {
                        badge.className = result.data.cssclass || 'badge text-bg-secondary';
                        badge.textContent = result.data.statusName || '';
                    }

                    flashSuccess(headerSaveBtn);
                } else {
                    showMessage('error', result.message || 'Error updating status');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                headerSaveBtn.classList.remove('j2c-saving');
            }
        });
    }

    // === Tracking Number Inline Edit ===
    const editTrackingBtn = document.getElementById('editTrackingBtn');
    const saveTrackingBtn = document.getElementById('saveTrackingBtn');
    const cancelTrackingBtn = document.getElementById('cancelTrackingBtn');
    const trackingDisplay = document.getElementById('trackingDisplay');
    const trackingEdit = document.getElementById('trackingEdit');
    const trackingInput = document.getElementById('trackingInput');
    const trackingValue = document.getElementById('trackingValue');

    if (editTrackingBtn && trackingDisplay && trackingEdit) {
        editTrackingBtn.addEventListener('click', () => {
            trackingDisplay.classList.add('d-none');
            trackingEdit.classList.remove('d-none');
            trackingInput?.focus();
        });

        cancelTrackingBtn?.addEventListener('click', () => {
            trackingEdit.classList.add('d-none');
            trackingDisplay.classList.remove('d-none');
        });

        saveTrackingBtn?.addEventListener('click', async () => {
            if (!trackingInput) return;

            saveTrackingBtn.classList.add('j2c-saving');

            try {
                const result = await postAjax('ajaxSaveTracking', {
                    tracking_id: trackingInput.value,
                });

                if (result.success) {
                    showMessage('message', result.message);

                    if (trackingValue) {
                        trackingValue.textContent = trackingInput.value || '-';
                    }

                    trackingEdit.classList.add('d-none');
                    trackingDisplay.classList.remove('d-none');
                    flashSuccess(trackingDisplay);
                } else {
                    showMessage('error', result.message || 'Error saving tracking number');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                saveTrackingBtn.classList.remove('j2c-saving');
            }
        });

        // Keyboard shortcuts in tracking input
        trackingInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveTrackingBtn?.click();
            }
            if (e.key === 'Escape') {
                cancelTrackingBtn?.click();
            }
        });
    }

    // === Copy to Clipboard (event delegation) ===
    root.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('.j2c-copy-btn');
        if (!copyBtn) return;

        const text = copyBtn.dataset.copy;
        if (!text) return;

        const icon = copyBtn.querySelector('span');

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                if (icon) {
                    icon.className = 'icon-checkmark';
                    setTimeout(() => { icon.className = 'icon-copy'; }, 1500);
                }
            }).catch(() => {
                fallbackCopy(text, icon);
            });
        } else {
            fallbackCopy(text, icon);
        }
    });

    function fallbackCopy(text, icon) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            if (icon) {
                icon.className = 'icon-checkmark';
                setTimeout(() => { icon.className = 'icon-copy'; }, 1500);
            }
        } catch (err) {
            showMessage('error', 'Copy failed');
        } finally {
            document.body.removeChild(textarea);
        }
    }

    // === Resend Email ===
    const resendEmailBtn = document.getElementById('resendEmailBtn');
    if (resendEmailBtn) {
        resendEmailBtn.addEventListener('click', async () => {
            if (!confirm('Resend the order confirmation email to the customer?')) return;

            resendEmailBtn.classList.add('j2c-saving');

            try {
                const result = await postAjax('ajaxResendEmail', {});

                if (result.success) {
                    showMessage('message', result.message);
                    flashSuccess(resendEmailBtn);
                } else {
                    showMessage('error', result.message || 'Error sending email');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                resendEmailBtn.classList.remove('j2c-saving');
            }
        });
    }

    // === Add Admin Note (internal order note) ===
    const addAdminNoteBtn = document.getElementById('addAdminNoteBtn');
    if (addAdminNoteBtn) {
        addAdminNoteBtn.addEventListener('click', async () => {
            const noteField = document.getElementById('adminOrderNote');
            if (!noteField || !noteField.value.trim()) {
                showMessage('warning', 'Please enter a note.');
                noteField?.focus();
                return;
            }

            addAdminNoteBtn.classList.add('j2c-saving');

            try {
                const result = await postAjax('ajaxAddNote', {
                    admin_note: noteField.value.trim(),
                });

                if (result.success) {
                    showMessage('message', result.message);
                    noteField.value = '';
                    await reloadHistory();
                } else {
                    showMessage('error', result.message || 'Error adding note');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                addAdminNoteBtn.classList.remove('j2c-saving');
            }
        });

        // Ctrl+Enter to submit note
        const adminNoteField = document.getElementById('adminOrderNote');
        adminNoteField?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                addAdminNoteBtn.click();
            }
        });
    }

    // === Current user ID for admin note ownership ===
    const historyContainer = document.getElementById('j2c-history-container');
    const currentUserId = historyContainer ? parseInt(historyContainer.dataset.currentUser, 10) || 0 : 0;

    // === Delete Admin Note (event delegation) ===
    const historyCard = document.getElementById('j2c-order-history-card');
    if (historyCard) {
        historyCard.addEventListener('click', async (e) => {
            const deleteBtn = e.target.closest('.j2c-delete-note');
            if (!deleteBtn) return;

            const historyId = parseInt(deleteBtn.dataset.historyId, 10);
            if (!historyId) return;

            deleteBtn.classList.add('j2c-saving');

            try {
                const result = await postAjax('ajaxDeleteNote', { history_id: historyId });

                if (result.success) {
                    showMessage('message', result.message);
                    await reloadHistory();
                } else {
                    showMessage('error', result.message || 'Error deleting note');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                deleteBtn.classList.remove('j2c-saving');
            }
        });
    }

    // === Reload history helper (shared by add/delete note) ===
    async function reloadHistory() {
        if (!historyContainer) return;

        const itemsEl = document.getElementById('j2c-history-items');
        if (!itemsEl) return;

        const histResult = await postAjax('ajaxGetHistory', { page: 1 });
        if (!histResult.success) return;

        itemsEl.innerHTML = renderHistoryItems(histResult.items);
        historyContainer.dataset.currentPage = '1';
        historyContainer.dataset.totalPages = histResult.totalPages;

        const badge = document.querySelector('#j2c-order-history-card .badge.text-bg-secondary');
        if (badge) badge.textContent = histResult.total;

        const historyNav = historyContainer.querySelector('.j2c-history-pagination');
        if (historyNav) updatePagination(historyNav, 1, histResult.totalPages);
    }

    // === Order History AJAX Pagination ===
    if (historyContainer) {
        const historyNav = historyContainer.querySelector('.j2c-history-pagination');
        if (historyNav) {
            historyNav.addEventListener('click', async (e) => {
                e.preventDefault();
                const li = e.target.closest('li[data-page]');
                if (!li || li.classList.contains('disabled') || li.classList.contains('active')) return;

                const currentPage = parseInt(historyContainer.dataset.currentPage, 10);
                const totalPages = parseInt(historyContainer.dataset.totalPages, 10);
                let targetPage;

                const pageVal = li.dataset.page;
                if (pageVal === 'prev') {
                    targetPage = Math.max(1, currentPage - 1);
                } else if (pageVal === 'next') {
                    targetPage = Math.min(totalPages, currentPage + 1);
                } else {
                    targetPage = parseInt(pageVal, 10);
                }

                if (targetPage === currentPage) return;

                const itemsEl = document.getElementById('j2c-history-items');
                itemsEl.classList.add('j2c-loading');

                try {
                    const result = await postAjax('ajaxGetHistory', { page: targetPage });

                    if (result.success) {
                        itemsEl.innerHTML = renderHistoryItems(result.items);
                        historyContainer.dataset.currentPage = targetPage;
                        historyContainer.dataset.totalPages = result.totalPages;
                        updatePagination(historyNav, targetPage, result.totalPages);
                    } else {
                        showMessage('error', result.message || 'Error loading history');
                    }
                } catch (err) {
                    showMessage('error', 'Network error loading history');
                } finally {
                    itemsEl.classList.remove('j2c-loading');
                }
            });
        }

    }

    // === Shared history rendering functions (used by pagination and admin note) ===
    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderHistoryItems(items) {
        return items.map((item, idx) => {
            const color = escHtml(item.color);
            const col1 = item.isFirst ? '' : ' border-end';
            const col2 = item.isLast ? '' : ' border-end';

            let icon;
            if (item.isFirst) {
                icon = `fa-solid fa-cart-plus fa-fw text-${color}`;
            } else {
                icon = `fa-solid fa-circle fa-fw border border-2 rounded-circle border-white text-${color}`;
            }

            if (item.isAdminNote) {
                icon = `fa-solid fa-user fa-fw text-${color}`;
            } else if (item.isNotification) {
                icon = `fa-solid fa-envelope fa-fw text-${color}`;
            } else if (item.isItemRemoved) {
                icon = `fa-solid fa-trash fa-fw text-${color}`;
            }

            if (item.pluginIcon) {
                icon = item.pluginIcon;
            }

            const statusHtml = item.order_state_id
                ? `<h4 class="card-title small mb-1"><span class="badge rounded-2 px-2 text-bg-${color}">${escHtml(item.orderstatus_name)}</span></h4>`
                : '';

            const commentHtml = item.comment
                ? `<p class="card-text text-body-secondary small mb-0">${escHtml(item.comment)}</p>`
                : '';

            const deleteBtn = (item.isAdminNote && item.createdBy === currentUserId)
                ? `<button type="button" class="btn btn-sm btn-link text-danger p-0 mt-0 j2c-delete-note" data-history-id="${item.id}" title="${escHtml(Joomla.Text._('JACTION_DELETE'))}">
                            <span class="icon-trash small" aria-hidden="true"></span>
                            <span class="visually-hidden">${escHtml(Joomla.Text._('JACTION_DELETE'))}</span>
                        </button>`
                : '';

            return `<div class="row j2c-history-row">
                <div class="col-auto text-center flex-column d-none d-lg-flex">
                    <div class="row h-50 mb-n1">
                        <div class="col${col1}"></div>
                        <div class="col"></div>
                    </div>
                    <h5 class="m-2"><span class="${escHtml(icon)}"></span></h5>
                    <div class="row h-50">
                        <div class="col${col2}"></div>
                        <div class="col"></div>
                    </div>
                </div>
                <div class="col px-2 py-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="float-end text-end text-body-secondary small fw-bold">
                                <div>${escHtml(item.date)}</div>
                                <div class="fw-normal">${escHtml(item.time)}</div>
                                ${deleteBtn}
                            </div>
                            ${statusHtml}
                            ${commentHtml}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function updatePagination(nav, currentPage, totalPages) {
        const ul = nav.querySelector('ul');

        const prevLi = ul.querySelector('[data-page="prev"]');
        prevLi.classList.toggle('disabled', currentPage <= 1);

        const nextLi = ul.querySelector('[data-page="next"]');
        nextLi.classList.toggle('disabled', currentPage >= totalPages);

        // Remove old numeric pages
        ul.querySelectorAll('li:not([data-page="prev"]):not([data-page="next"])').forEach(li => li.remove());

        // Insert new numeric pages before next button
        for (let p = 1; p <= totalPages; p++) {
            const li = document.createElement('li');
            li.className = 'page-item' + (p === currentPage ? ' active' : '');
            li.dataset.page = p;
            li.innerHTML = `<a class="page-link" href="#">${p}</a>`;
            ul.insertBefore(li, nextLi);
        }
    }
});
