/**
 * J2Commerce My Profile — Vanilla ES6+
 *
 * @copyright  (C)2024-2026 J2Commerce, LLC
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
document.addEventListener('DOMContentLoaded', () => {
    const opts = Joomla.getOptions('com_j2commerce.myprofile') || {};
    const baseUrl = opts.baseUrl || 'index.php?option=com_j2commerce';
    const csrf = opts.csrfToken || '';
    const sep = baseUrl.includes('?') ? '&' : '?';
    // The list partials are picked per menu item, so every AJAX refresh has to name the
    // same menu item the first paint used.
    const itemIdParam = opts.itemId ? `&Itemid=${opts.itemId}` : '';

    // Tab deep-linking via URL hash
    const hash = window.location.hash;
    if (hash) {
        // Compare in JS rather than interpolating the hash into a selector: a URL ending in
        // #a"] makes querySelector throw, which would abort this whole handler and unbind
        // every listener in the file. Restricted to tab toggles for the same reason — show()
        // resolves siblings through the element's .nav / .list-group / [role=tablist] ancestor
        // and fails without one, which a navbar toggler or collapse button would not have.
        const btn = Array.from(document.querySelectorAll('[data-bs-toggle="tab"][data-bs-target]'))
            .find(el => el.dataset.bsTarget === hash);

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

    // 4.1.3 Status Messages: the replaced region cannot be the live region announcing its own
    // replacement, so mirror the fresh result summary into the sibling role="status" node.
    function announce(statusEl, container) {
        if (!statusEl) return;

        const summary = container.querySelector('[id$="-count"], .alert, .uk-alert');
        statusEl.textContent = summary ? summary.textContent.trim() : '';
    }

    // Parse server-rendered HTML into an inert fragment (no innerHTML sink) for adoption.
    // createContextualFragment unmarks parsed scripts as "already started", so unlike the
    // innerHTML assignment this replaced they WOULD run on insertion. Drop them to keep
    // the original semantics — plugin markup renders, it does not execute.
    function parseFragment(html) {
        const frag = document.createRange().createContextualFragment(html || '');
        frag.querySelectorAll('script').forEach(s => s.remove());

        return frag;
    }

    // The list markup is owned by the server template (default_orderslist.php) and re-rendered
    // there on every refresh, so a template override survives search and pagination. The only
    // contracts JS holds are this container and the .j2c-page-link[data-page] links inside it.
    const wrap   = document.getElementById('j2c-orders-table-wrap');
    const status = document.getElementById('j2c-orders-status');

    async function loadOrders(page, search) {
        if (!wrap) return;

        const limitStart = page * listLimit;
        const url = `${baseUrl}${sep}task=myprofile.loadOrders&format=json`
            + `&limitstart=${limitStart}`
            + itemIdParam
            + (search ? `&search=${encodeURIComponent(search)}` : '');

        wrap.style.opacity = '0.5';

        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json = await res.json();

            if (!json.success) return;

            wrap.replaceChildren(parseFragment(json.html));
            currentPage = page;
            announce(status, wrap);
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
    const dlWrap   = document.getElementById('j2c-downloads-table-wrap');
    const dlStatus = document.getElementById('j2c-downloads-status');
    let dlSearchTimer = null;
    let dlCurrentPage = 0;

    async function loadDownloads(page, search) {
        if (!dlWrap) return;

        const limitStart = page * listLimit;
        const url = `${baseUrl}${sep}task=myprofile.loadDownloads&format=json`
            + `&limitstart=${limitStart}`
            + itemIdParam
            + (search ? `&search=${encodeURIComponent(search)}` : '');

        dlWrap.style.opacity = '0.5';

        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json = await res.json();

            if (!json.success) return;

            dlWrap.replaceChildren(parseFragment(json.html));
            dlCurrentPage = page;
            announce(dlStatus, dlWrap);
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
        if (!id || !confirm(Joomla.Text._('COM_J2COMMERCE_MYPROFILE_DELETE_CONFIRM'))) return;

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

    // Country → Zone cascading dropdowns — shared with the registration form and checkout
    const addressForm = document.getElementById('j2commerce-address-form');
    if (addressForm) {
        J2CommerceCountryZone.init(addressForm, { baseUrl });
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
            if (isDirty && !confirm(Joomla.Text._('COM_J2COMMERCE_MYPROFILE_DISCARD_CHANGES'))) {
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

    // Print order button → open in the modal the active template family rendered
    const orderModalEl = document.getElementById('j2commerceOrderModal');
    const orderModalBody = document.getElementById('j2commerceOrderModalBody');
    const orderPrintBtn = document.getElementById('j2commerceOrderPrintBtn');
    let orderModal = null;

    // The uikit templates render `<div uk-modal>`, which the Bootstrap modal API cannot present.
    // UIkit's JS ships with the site template, not with J2Commerce, so fall back to Bootstrap
    // when the global is absent rather than leaving the button dead.
    const isUikitModal = !!orderModalEl
        && orderModalEl.hasAttribute('uk-modal')
        && typeof UIkit !== 'undefined';

    /** Spinner and alert markup differ per family; Bootstrap classes render as nothing in UIkit. */
    function frameworkHtml(kind, message) {
        if (kind === 'spinner') {
            return isUikitModal
                ? `<div class="uk-text-center uk-padding"><span uk-spinner="ratio: 2" role="status" aria-label="${message}"></span></div>`
                : `<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">${message}</span></div></div>`;
        }

        return isUikitModal
            ? `<div class="uk-alert-danger" uk-alert>${message}</div>`
            : `<div class="alert alert-danger">${message}</div>`;
    }

    if (orderModalEl) {
        orderModal = isUikitModal
            ? { show: () => UIkit.modal(orderModalEl).show() }
            : bootstrap.Modal.getOrCreateInstance(orderModalEl);

        // Do not leave the previous order or slip in the DOM after the modal closes.
        orderModalEl.addEventListener(isUikitModal ? 'hidden' : 'hidden.bs.modal', () => {
            if (orderModalBody) orderModalBody.replaceChildren();
        });
    }

    document.addEventListener('click', async e => {
        const btn = e.target.closest('.j2commerce-order-print');
        if (!btn) return;
        e.preventDefault();

        const url = btn.dataset.url || btn.getAttribute('href');
        if (!url || !orderModal || !orderModalBody) return;

        // Show modal with spinner
        orderModalBody.replaceChildren(parseFragment(frameworkHtml('spinner', Joomla.Text._('COM_J2COMMERCE_LOADING'))));
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
            orderModalBody.replaceChildren(...content.childNodes);
        } catch (err) {
            console.error('Error loading order:', err);
            orderModalBody.replaceChildren(parseFragment(frameworkHtml('error', 'Error loading order details.')));
        }
    });

    // Packing slip print — reuses the same modal and print flow
    document.addEventListener('click', async e => {
        const btn = e.target.closest('.j2commerce-packingslip-print');
        if (!btn) return;
        e.preventDefault();

        const url = btn.dataset.url || btn.getAttribute('href');
        if (!url || !orderModal || !orderModalBody) return;

        orderModalBody.replaceChildren(parseFragment(frameworkHtml('spinner', Joomla.Text._('COM_J2COMMERCE_LOADING'))));
        orderModal.show();

        try {
            const res = await fetch(url);
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            doc.querySelectorAll('script').forEach(s => s.remove());
            const content = doc.querySelector('.j2commerce-packingslip-detail') || doc.querySelector('.j2commerce-order-detail') || doc.body;
            orderModalBody.replaceChildren(...content.childNodes);
        } catch (err) {
            console.error('Error loading packing slip:', err);
            orderModalBody.replaceChildren(parseFragment(frameworkHtml('error', 'Error loading packing slip.')));
        }
    });

    // Print button inside modal
    if (orderPrintBtn) {
        orderPrintBtn.addEventListener('click', () => {
            if (!orderModalBody) return;

            const printWindow = window.open('about:blank', '_blank', 'width=800,height=600');
            if (!printWindow) return;

            const printDoc = printWindow.document;

            const title = printDoc.createElement('title');
            title.textContent = document.title;
            printDoc.head.appendChild(title);

            // Carry the storefront's framework stylesheet into the popup so the printed
            // markup keeps its layout — uikit storefronts ship no bootstrap link.
            const frameworkHref = document.querySelector('link[href*="bootstrap"], link[href*="uikit"]')?.href || '';
            let stylesheet = null;

            if (frameworkHref) {
                stylesheet = printDoc.createElement('link');
                stylesheet.rel = 'stylesheet';
                stylesheet.href = frameworkHref;
                printDoc.head.appendChild(stylesheet);
            }

            const style = printDoc.createElement('style');
            style.textContent = 'body{padding:20px;font-family:sans-serif}@media print{.no-print{display:none}}';
            printDoc.head.appendChild(style);

            const fragment = printDoc.createDocumentFragment();
            Array.from(orderModalBody.childNodes).forEach(node => {
                // Packing slip CSS travels inert so it cannot restyle the storefront; it only
                // becomes a stylesheet here, in the print document.
                if (node.nodeType === Node.ELEMENT_NODE && node.matches('template.j2commerce-packingslip-css')) {
                    printDoc.head.appendChild(printDoc.importNode(node.content, true));
                    return;
                }

                fragment.appendChild(printDoc.importNode(node, true));
            });
            printDoc.body.appendChild(fragment);

            let printed = false;
            const startPrint = () => {
                if (printed) return;
                printed = true;
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            };

            if (stylesheet) {
                stylesheet.addEventListener('load', startPrint);
                stylesheet.addEventListener('error', startPrint);
                // Fallback in case the stylesheet never resolves.
                window.setTimeout(startPrint, 1500);
            } else {
                window.setTimeout(startPrint, 0);
            }
        });
    }
});
