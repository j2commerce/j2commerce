/**
 * J2Commerce My Profile — Vanilla ES6+
 *
 * @copyright  (C)2024-2026 J2Commerce, LLC
 * @license    GNU General Public License version 2 or later
 */
document.addEventListener('DOMContentLoaded', () => {
    const opts = Joomla.getOptions('com_j2commerce.myprofile') || {};
    const baseUrl = opts.baseUrl || 'index.php?option=com_j2commerce';
    const csrf = opts.csrfToken || '';
    const sep = baseUrl.includes('?') ? '&' : '?';

    // Tab deep-linking via URL hash
    const hash = window.location.hash;
    if (hash) {
        const btn = document.querySelector(`[data-bs-target="${hash}"]`);
        if (btn) {
            new bootstrap.Tab(btn).show();
        }
    }
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(el => {
        el.addEventListener('shown.bs.tab', e => {
            history.replaceState(null, '', e.target.dataset.bsTarget);
        });
    });

    // =========================================================================
    // Orders: AJAX search + pagination
    // =========================================================================
    const searchInput = document.getElementById('j2c-order-search');
    const listLimit   = opts.listLimit || 20;
    let searchTimer   = null;
    let currentPage   = 0;

    function escapeHtml(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }

    // Capture the initial server-rendered HTML structure so AJAX rebuilds
    // preserve any template override customisations (classes, attributes, etc.)
    const wrap = document.getElementById('j2c-orders-table-wrap');
    let snapshotTheadHtml = '';
    let snapshotTableClass = 'table';
    let snapshotPagUlClass = 'pagination my-0';
    let snapshotPagDivClass = 'd-flex justify-content-end align-items-center';
    let snapshotCountClass = 'text-muted small ms-3 align-self-center';
    let snapshotNoOrdersId = 'j2c-no-orders';

    if (wrap) {
        const initTable = wrap.querySelector('#j2c-orders-table');
        if (initTable) {
            const thead = initTable.querySelector('thead');
            if (thead) snapshotTheadHtml = thead.outerHTML;
            snapshotTableClass = initTable.className || snapshotTableClass;
        }
        const initPagDiv = wrap.querySelector('#j2c-orders-pagination');
        if (initPagDiv) snapshotPagDivClass = initPagDiv.className || snapshotPagDivClass;
        const initPagUl = wrap.querySelector('#j2c-pagination-list');
        if (initPagUl) snapshotPagUlClass = initPagUl.className || snapshotPagUlClass;
        const initCount = wrap.querySelector('#j2c-orders-count');
        if (initCount) snapshotCountClass = initCount.className || snapshotCountClass;
    }

    async function loadOrders(page, search) {
        if (!wrap) return;

        const limitStart = page * listLimit;
        const url = `${baseUrl}${sep}task=myprofile.loadOrders&format=json`
            + `&limitstart=${limitStart}`
            + (search ? `&search=${encodeURIComponent(search)}` : '');

        wrap.style.opacity = '0.5';

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();

            if (!json.success) return;

            const { orders, total, limit } = json;

            if (!orders.length) {
                wrap.innerHTML = `<div class="alert alert-info" id="${snapshotNoOrdersId}">`
                    + (Joomla.Text._('COM_J2COMMERCE_NO_ORDERS') || 'No orders found.') + '</div>';
                wrap.style.opacity = '1';
                return;
            }

            // Build table rows
            let rows = '';
            for (const o of orders) {
                rows += '<tr>'
                    + `<td><a href="${escapeHtml(o.view_url)}">${escapeHtml(o.date)}</a></td>`
                    + `<td><a href="${escapeHtml(o.view_url)}">${escapeHtml(o.invoice)}</a></td>`
                    + `<td><span class="badge ${escapeHtml(o.status_css)}">${escapeHtml(o.status_name)}</span></td>`
                    + `<td class="text-end">${escapeHtml(o.amount)}</td>`
                    + '<td class="text-center text-nowrap">'
                    +   `<a href="${escapeHtml(o.view_url)}" class="btn btn-sm btn-outline-primary" title="${Joomla.Text._('COM_J2COMMERCE_ORDER_VIEW') || 'View'}"><span class="icon-eye" aria-hidden="true"></span></a> `
                    +   `<button type="button" class="btn btn-sm btn-outline-secondary j2commerce-order-print" data-url="${escapeHtml(o.print_url)}" title="${Joomla.Text._('COM_J2COMMERCE_ORDER_PRINT') || 'Print'}"><span class="icon-print" aria-hidden="true"></span></button>`
                    +   (o.after_display_html || '')
                    + '</td></tr>';
            }

            // Build pagination — reuse classes captured from the server-rendered template
            const pages = Math.ceil(total / limit);
            const start = limitStart + 1;
            const end   = Math.min(limitStart + limit, total);
            let pagHtml = '';

            if (pages > 1) {
                pagHtml += `<nav aria-label="${Joomla.Text._('JLIB_HTML_PAGINATION') || 'Pagination'}"><ul class="${snapshotPagUlClass}" id="j2c-pagination-list">`;
                for (let p = 0; p < pages; p++) {
                    const active = (p === page) ? ' active' : '';
                    pagHtml += `<li class="page-item${active}"><a class="page-link j2c-page-link" href="#" data-page="${p}">${p + 1}</a></li>`;
                }
                pagHtml += '</ul></nav>';
            }

            const countText = `${start} - ${end} / ${total} ` + (Joomla.Text._('COM_J2COMMERCE_ITEMS') || 'items');

            // Use the captured thead so template overrides are preserved on AJAX refresh
            const theadHtml = snapshotTheadHtml
                || '<thead><tr>'
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_ORDER_DATE') || 'Date'}</th>`
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_INVOICE_NO') || 'Order #'}</th>`
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_ORDER_STATUS') || 'Status'}</th>`
                + `<th scope="col" class="text-end">${Joomla.Text._('COM_J2COMMERCE_ORDER_AMOUNT') || 'Amount'}</th>`
                + `<th scope="col" class="text-center" style="width:1%"><span class="visually-hidden">${Joomla.Text._('COM_J2COMMERCE_ACTIONS') || 'Actions'}</span></th>`
                + '</tr></thead>';

            wrap.innerHTML = '<div class="table-responsive">'
                + `<table class="${escapeHtml(snapshotTableClass)}" id="j2c-orders-table">`
                + theadHtml
                + '<tbody id="j2c-orders-body">' + rows + '</tbody></table></div>'
                + `<div class="${escapeHtml(snapshotPagDivClass)}" id="j2c-orders-pagination">`
                + pagHtml
                + `<span class="${escapeHtml(snapshotCountClass)}" id="j2c-orders-count">${countText}</span>`
                + '</div>';

            currentPage = page;
        } catch (err) {
            console.error('Error loading orders:', err);
        } finally {
            wrap.style.opacity = '1';
        }
    }

    // Debounced search
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                currentPage = 0;
                loadOrders(0, searchInput.value.trim());
            }, 350);
        });
    }

    // Pagination clicks (delegated)
    document.addEventListener('click', e => {
        const link = e.target.closest('.j2c-page-link');
        if (!link) return;
        e.preventDefault();
        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) {
            const search = searchInput ? searchInput.value.trim() : '';
            loadOrders(page, search);
        }
    });

    // =========================================================================
    // Downloads: AJAX search + pagination
    // =========================================================================
    const dlSearchInput = document.getElementById('j2c-download-search');
    const dlWrap = document.getElementById('j2c-downloads-table-wrap');
    let dlSearchTimer = null;
    let dlCurrentPage = 0;

    // Capture the initial server-rendered HTML structure for downloads
    let dlSnapshotTheadHtml = '';
    let dlSnapshotTableClass = 'table';
    let dlSnapshotPagUlClass = 'pagination my-0';
    let dlSnapshotPagDivClass = 'd-flex justify-content-end align-items-center';
    let dlSnapshotCountClass = 'text-muted small ms-3 align-self-center';
    let dlSnapshotNoDownloadsId = 'j2c-no-downloads';

    if (dlWrap) {
        const initTable = dlWrap.querySelector('#j2c-downloads-table');
        if (initTable) {
            const thead = initTable.querySelector('thead');
            if (thead) dlSnapshotTheadHtml = thead.outerHTML;
            dlSnapshotTableClass = initTable.className || dlSnapshotTableClass;
        }
        const initPagDiv = dlWrap.querySelector('#j2c-downloads-pagination');
        if (initPagDiv) dlSnapshotPagDivClass = initPagDiv.className || dlSnapshotPagDivClass;
        const initPagUl = dlWrap.querySelector('#j2c-downloads-pagination-list');
        if (initPagUl) dlSnapshotPagUlClass = initPagUl.className || dlSnapshotPagUlClass;
        const initCount = dlWrap.querySelector('#j2c-downloads-count');
        if (initCount) dlSnapshotCountClass = initCount.className || dlSnapshotCountClass;
    }

    async function loadDownloads(page, search) {
        if (!dlWrap) return;

        const limitStart = page * listLimit;
        const url = `${baseUrl}${sep}task=myprofile.loadDownloads&format=json`
            + `&limitstart=${limitStart}`
            + (search ? `&search=${encodeURIComponent(search)}` : '');

        dlWrap.style.opacity = '0.5';

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();

            if (!json.success) return;

            const { downloads, total, limit } = json;

            if (!downloads.length) {
                dlWrap.innerHTML = `<div class="alert alert-info" id="${dlSnapshotNoDownloadsId}">`
                    + (Joomla.Text._('COM_J2COMMERCE_NO_DOWNLOADS') || 'No downloads found.') + '</div>';
                dlWrap.style.opacity = '1';
                return;
            }

            // Build table rows
            let rows = '';
            for (const d of downloads) {
                // File name cell
                let fileNameCell = '';
                if (d.file_name) {
                    fileNameCell = escapeHtml(d.file_name);
                } else {
                    fileNameCell = '<span class="text-muted fst-italic">' + (Joomla.Text._('COM_J2COMMERCE_FILE_UNAVAILABLE') || 'File unavailable') + '</span>';
                }

                // Expires cell
                let expiresCell = '';
                if (!d.access_granted) {
                    expiresCell = '<span class="badge bg-secondary">' + (Joomla.Text._('COM_J2COMMERCE_DOWNLOAD_PENDING') || 'Pending') + '</span>';
                } else if (d.never_expires) {
                    expiresCell = Joomla.Text._('COM_J2COMMERCE_NEVER_EXPIRES') || 'Never';
                } else {
                    expiresCell = escapeHtml(d.access_expires);
                }

                // Remaining cell
                const remainingCell = d.remaining >= 0 ? d.remaining : '&infin;';

                // Action cell
                let actionCell = '';
                if (d.can_download) {
                    actionCell = `<a href="${escapeHtml(d.download_url)}" class="btn btn-sm btn-primary" title="${Joomla.Text._('COM_J2COMMERCE_DOWNLOAD') || 'Download'}"><span class="icon-download" aria-hidden="true"></span></a>`;
                } else if (d.status === 'pending') {
                    actionCell = '<span class="badge text-bg-secondary">' + (Joomla.Text._('COM_J2COMMERCE_DOWNLOAD_PENDING') || 'Pending') + '</span>';
                } else if (d.status === 'expired') {
                    actionCell = '<span class="badge text-bg-danger">' + (Joomla.Text._('COM_J2COMMERCE_EXPIRED') || 'Expired') + '</span>';
                } else if (d.status === 'limit_reached') {
                    actionCell = '<span class="badge text-bg-warning">' + (Joomla.Text._('COM_J2COMMERCE_LIMIT_REACHED') || 'Limit reached') + '</span>';
                } else {
                    actionCell = '<span class="badge text-bg-secondary">' + (Joomla.Text._('COM_J2COMMERCE_FILE_UNAVAILABLE') || 'File unavailable') + '</span>';
                }

                rows += '<tr>'
                    + `<td>${escapeHtml(d.order_id)}</td>`
                    + `<td>${fileNameCell}</td>`
                    + `<td>${expiresCell}</td>`
                    + `<td class="text-center">${remainingCell}</td>`
                    + `<td class="text-center">${actionCell}</td>`
                    + '</tr>';
            }

            // Build pagination
            const pages = Math.ceil(total / limit);
            const start = limitStart + 1;
            const end   = Math.min(limitStart + limit, total);
            let pagHtml = '';

            if (pages > 1) {
                pagHtml += `<nav aria-label="${Joomla.Text._('JLIB_HTML_PAGINATION') || 'Pagination'}"><ul class="${dlSnapshotPagUlClass}" id="j2c-downloads-pagination-list">`;
                for (let p = 0; p < pages; p++) {
                    const active = (p === page) ? ' active' : '';
                    pagHtml += `<li class="page-item${active}"><a class="page-link j2c-download-page-link" href="#" data-page="${p}">${p + 1}</a></li>`;
                }
                pagHtml += '</ul></nav>';
            }

            const countText = `${start} - ${end} / ${total} ` + (Joomla.Text._('COM_J2COMMERCE_ITEMS') || 'items');

            // Use the captured thead so template overrides are preserved on AJAX refresh
            const theadHtml = dlSnapshotTheadHtml
                || '<thead><tr>'
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_ORDER') || 'Order'}</th>`
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_FILES') || 'Files'}</th>`
                + `<th scope="col">${Joomla.Text._('COM_J2COMMERCE_ACCESS_EXPIRES') || 'Expires'}</th>`
                + `<th scope="col" class="text-center">${Joomla.Text._('COM_J2COMMERCE_DOWNLOADS_REMAINING') || 'Remaining'}</th>`
                + `<th scope="col" class="text-center" style="width:1%"><span class="visually-hidden">${Joomla.Text._('COM_J2COMMERCE_ACTIONS') || 'Actions'}</span></th>`
                + '</tr></thead>';

            dlWrap.innerHTML = '<div class="table-responsive">'
                + `<table class="${escapeHtml(dlSnapshotTableClass)}" id="j2c-downloads-table">`
                + theadHtml
                + '<tbody id="j2c-downloads-body">' + rows + '</tbody></table></div>'
                + `<div class="${escapeHtml(dlSnapshotPagDivClass)}" id="j2c-downloads-pagination">`
                + pagHtml
                + `<span class="${escapeHtml(dlSnapshotCountClass)}" id="j2c-downloads-count">${countText}</span>`
                + '</div>';

            dlCurrentPage = page;
        } catch (err) {
            console.error('Error loading downloads:', err);
        } finally {
            dlWrap.style.opacity = '1';
        }
    }

    // Debounced search for downloads
    if (dlSearchInput) {
        dlSearchInput.addEventListener('input', () => {
            clearTimeout(dlSearchTimer);
            dlSearchTimer = setTimeout(() => {
                dlCurrentPage = 0;
                loadDownloads(0, dlSearchInput.value.trim());
            }, 350);
        });
    }

    // Downloads pagination clicks (delegated)
    document.addEventListener('click', e => {
        const link = e.target.closest('.j2c-download-page-link');
        if (!link) return;
        e.preventDefault();
        const page = parseInt(link.dataset.page, 10);
        if (!isNaN(page)) {
            const search = dlSearchInput ? dlSearchInput.value.trim() : '';
            loadDownloads(page, search);
        }
    });

    // Address delete (AJAX)
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.j2commerce-address-delete');
        if (!btn) return;
        e.preventDefault();

        const id = btn.dataset.addressId;
        if (!id || !confirm(Joomla.Text._('COM_J2COMMERCE_MYPROFILE_DELETE_CONFIRM') || 'Delete this address?')) return;

        const fd = new FormData();
        fd.append('address_id', id);
        fd.append(csrf, '1');

        try {
            const res = await fetch(`${baseUrl}${sep}task=myprofile.deleteAddress&format=json`, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
                const card = document.getElementById(`j2commerce-address-${id}`);
                if (card) {
                    card.style.transition = 'opacity .3s';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                }
                Joomla.renderMessages({ message: [json.message] });
            } else {
                Joomla.renderMessages({ error: [json.message || 'Error'] });
            }
        } catch (err) {
            Joomla.renderMessages({ error: ['An error occurred'] });
        }
    });

    // Address save (AJAX)
    const form = document.getElementById('j2commerce-address-form');
    if (form) {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            form.querySelectorAll('.j2commerce-field-error').forEach(el => el.remove());

            const fd = new FormData(form);
            fd.append(csrf, '1');

            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            try {
                const res = await fetch(`${baseUrl}${sep}task=myprofile.saveAddress&format=json`, { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    if (json.redirect) {
                        window.location.href = json.redirect;
                    } else {
                        Joomla.renderMessages({ message: [json.message] });
                        const idField = form.querySelector('[name="address_id"]');
                        if (idField && json.address_id) idField.value = json.address_id;
                    }
                } else if (json.errors) {
                    Object.entries(json.errors).forEach(([field, msg]) => {
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) {
                            const err = document.createElement('div');
                            err.className = 'j2commerce-field-error text-danger small mt-1';
                            err.textContent = msg;
                            input.closest('.col-md-6, .col-12')?.appendChild(err);
                        }
                    });
                    if (json.message) Joomla.renderMessages({ error: [json.message] });
                }
            } catch (err) {
                Joomla.renderMessages({ error: ['An error occurred'] });
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // Country → Zone cascading dropdowns (shared AJAX endpoints with checkout)
    function initCountryZoneFields(formEl) {
        if (!formEl) return;
        const countrySelect = formEl.querySelector('select[name="country_id"]');
        const zoneSelect = formEl.querySelector('select[name="zone_id"]');
        if (!countrySelect) return;

        const savedCountryId = formEl.dataset.countryId || '';
        const savedZoneId = formEl.dataset.zoneId || '';

        // Fetch and populate countries, restoring saved selection
        let countryUrl = baseUrl + sep + 'task=ajax.getCountries';
        if (savedCountryId && savedCountryId !== '0') {
            countryUrl += '&country_id=' + encodeURIComponent(savedCountryId);
        }

        fetch(countryUrl)
            .then(r => r.text())
            .then(html => {
                countrySelect.innerHTML = html;
                // If a country was pre-selected, cascade to load zones
                if (countrySelect.value && zoneSelect) {
                    loadZones(countrySelect.value, savedZoneId);
                }
            })
            .catch(err => console.error('Error loading countries:', err));

        if (!zoneSelect) return;

        function loadZones(countryId, selectedZoneId) {
            zoneSelect.innerHTML = '<option value="">...</option>';
            zoneSelect.disabled = true;

            if (!countryId || countryId === '0' || countryId === '') {
                zoneSelect.innerHTML = '<option value="">' + (Joomla.Text._('COM_J2COMMERCE_SELECT_ZONE') || 'Select Zone') + '</option>';
                zoneSelect.disabled = false;
                return;
            }

            let url = baseUrl + sep + 'task=ajax.getZones&country_id=' + encodeURIComponent(countryId);
            if (selectedZoneId && selectedZoneId !== '0') {
                url += '&zone_id=' + encodeURIComponent(selectedZoneId);
            }

            fetch(url)
                .then(r => r.text())
                .then(html => {
                    zoneSelect.innerHTML = html;
                    zoneSelect.disabled = false;
                })
                .catch(err => {
                    console.error('Error loading zones:', err);
                    zoneSelect.innerHTML = '<option value="">' + (Joomla.Text._('COM_J2COMMERCE_SELECT_ZONE') || 'Select Zone') + '</option>';
                    zoneSelect.disabled = false;
                });
        }

        // Country change → reload zones
        countrySelect.addEventListener('change', () => {
            loadZones(countrySelect.value, '');
        });
    }

    // Initialize country/zone fields if address form is present
    const addressForm = document.getElementById('j2commerce-address-form');
    if (addressForm) {
        initCountryZoneFields(addressForm);
    }

    // Address type change → reload page with correct custom fields for the area
    const typeSelect = document.getElementById('j2c-address-type');
    if (typeSelect && addressForm) {
        const initialType = typeSelect.value;
        typeSelect.addEventListener('change', () => {
            // Warn if form has been modified
            const formData = new FormData(addressForm);
            let isDirty = false;
            for (const [key, val] of formData.entries()) {
                if (key === 'type' || key === 'address_id' || key === 'j2commerce_address_id' || key === csrf) continue;
                if (val && typeof val === 'string' && val.trim() !== '') { isDirty = true; break; }
            }
            if (isDirty && !confirm(Joomla.Text._('COM_J2COMMERCE_MYPROFILE_DISCARD_CHANGES') || 'Changing the address type will reload the form. Discard unsaved changes?')) {
                typeSelect.value = initialType;
                return;
            }
            const newType = typeSelect.value;
            const addressId = addressForm.querySelector('[name="address_id"]')?.value || '0';
            const url = new URL(window.location.href);
            url.searchParams.set('layout', 'address');
            url.searchParams.set('type', newType);
            if (addressId && addressId !== '0') {
                url.searchParams.set('address_id', addressId);
            } else {
                url.searchParams.delete('address_id');
            }
            window.location.href = url.toString();
        });
    }

    // Print order button → open in Bootstrap modal
    const orderModalEl = document.getElementById('j2commerceOrderModal');
    const orderModalBody = document.getElementById('j2commerceOrderModalBody');
    const orderPrintBtn = document.getElementById('j2commerceOrderPrintBtn');
    let orderModal = null;

    if (orderModalEl) {
        orderModal = bootstrap.Modal.getOrCreateInstance(orderModalEl);
    }

    document.addEventListener('click', async e => {
        const btn = e.target.closest('.j2commerce-order-print');
        if (!btn) return;
        e.preventDefault();

        const url = btn.dataset.url || btn.getAttribute('href');
        if (!url || !orderModal || !orderModalBody) return;

        // Show modal with spinner
        orderModalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></div></div>';
        orderModal.show();

        try {
            const res = await fetch(url);
            const html = await res.text();
            // Extract body content from the response (tmpl=component returns minimal page)
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            // Remove any auto-print scripts from the parsed content
            doc.querySelectorAll('script').forEach(s => s.remove());
            const content = doc.querySelector('.j2commerce-order-detail') || doc.body;
            orderModalBody.innerHTML = content.innerHTML;
        } catch (err) {
            console.error('Error loading order:', err);
            orderModalBody.innerHTML = '<div class="alert alert-danger">Error loading order details.</div>';
        }
    });

    // Packing slip print — reuses the same modal and print flow
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.j2commerce-packingslip-print');
        if (!btn) return;
        e.preventDefault();

        const url = btn.dataset.url || btn.getAttribute('href');
        if (!url || !orderModal || !orderModalBody) return;

        orderModalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></div></div>';
        orderModal.show();

        try {
            const res = await fetch(url);
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            doc.querySelectorAll('script').forEach(s => s.remove());
            const content = doc.querySelector('.j2commerce-packingslip-detail') || doc.querySelector('.j2commerce-order-detail') || doc.body;
            orderModalBody.innerHTML = content.innerHTML;
        } catch (err) {
            console.error('Error loading packing slip:', err);
            orderModalBody.innerHTML = '<div class="alert alert-danger">Error loading packing slip.</div>';
        }
    });

    // Print button inside modal
    if (orderPrintBtn) {
        orderPrintBtn.addEventListener('click', () => {
            if (!orderModalBody) return;
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            if (printWindow) {
                printWindow.document.write(`<!DOCTYPE html><html><head><title>Order</title>
                    <link rel="stylesheet" href="${document.querySelector('link[href*="bootstrap"]')?.href || ''}">
                    <style>body{padding:20px;font-family:sans-serif}@media print{.no-print{display:none}}</style>
                    </head><body>${orderModalBody.innerHTML}
                    <script>window.onload=function(){window.print();window.close()}<\/script>
                    </body></html>`);
                printWindow.document.close();
            }
        });
    }
});
