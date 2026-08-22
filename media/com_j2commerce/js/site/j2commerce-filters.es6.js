// A filter group renders its values as checkboxes, colour swatches, radios or a <select>,
// whichever the group is set to. The value carrier is an <input> in the first three and an
// <option> in the last, and both answer :checked — so every selector in this file is shared
// and only the property differs. An <option> has no .checked at all; writing one silently
// creates an expando and selects nothing, which is what these two exist to prevent.
const pfilterPicked = el => (el.tagName === 'OPTION' ? el.selected : el.checked);
const pfilterPick = (el, on) => {
    if (el.tagName === 'OPTION') {
        el.selected = on;
    } else {
        el.checked = on;
    }
};

// The empty value is the "Any" control a single-value group offers as its reset. It is a real
// picked control, so it has to be kept out of every selection, count and chip.
const pfilterValued = el => el.value !== '';

class J2CommerceFilters {
    constructor(options = {}) {
        this.productContainer = document.querySelector(options.productContainer || '.j2commerce-product-list');
        this.filterForm = document.getElementById(options.filterFormId || 'productsideFilters');
        this.sortForm = document.getElementById(options.sortFormId || 'productFilters');
        this.paginationForm = document.getElementById(options.paginationFormId || 'j2commerce-pagination');
        this.loadingOverlay = document.getElementById('j2commerce-product-loading');
        this.endpoint = options.endpoint || 'index.php?option=com_j2commerce&task=products.filter&format=json';
        this.checkboxDebounce = options.checkboxDebounce || 300;
        this.searchDebounce = options.searchDebounce || 500;
        this.debounceTimer = null;
        this.enabled = true;

        this.init();
    }

    /**
     * A hidden Joomla form-token input reproduces exactly `<input type="hidden"
     * name="<32hex>" value="1">` — the shape core's page cache rewrites to the current
     * visitor's token on every cached-page replay, unlike the csrf.token script option.
     */
    readFormToken() {
        return (Array.from(document.querySelectorAll('input[type="hidden"][value="1"]'))
            .find((el) => /^[0-9a-f]{32}$/.test(el.name)) || {}).name || '';
    }

    /**
     * Resolve a CSRF token: DOM (freshest) -> endpoint -> the csrf.token script option. The
     * script option is baked into the markup, so on a cached-page replay it carries whichever
     * visitor primed the cache; putting it last means a stale value is used only when nothing
     * fresher exists.
     */
    async resolveToken() {
        const domToken = this.readFormToken();

        if (domToken) {
            return domToken;
        }

        if (window.J2CommerceToken && typeof window.J2CommerceToken.get === 'function') {
            try {
                const endpointToken = await window.J2CommerceToken.get();

                if (endpointToken) {
                    return endpointToken;
                }
            } catch (error) {
                // Fall through to the legacy source.
            }
        }

        return Joomla.getOptions('csrf.token') || '';
    }

    init() {
        if (!this.productContainer) return;

        this.bindCheckboxFilters();
        this.bindPriceFilter();
        this.bindSearchFilter();
        this.bindSortFilter();
        this.bindCategoryLinks();
        this.bindClearButtons();
        this.bindPagination();
        this.bindActiveFilterTiles();
        this.bindMobileFooter();
        this.buildActiveFilterTiles();
        this.bindHistory();
    }

    bindHistory() {
        // Seed the entry the page loaded on. Without this, stepping back to the landing
        // state arrives with a null state and the handler has nothing to restore from.
        window.history.replaceState(this.captureState(this.currentStart()), '', window.location.href);

        window.addEventListener('popstate', (event) => {
            if (!event.state || !event.state.j2commerce) return;

            this.restoreState(event.state);
            this.applyFilters(event.state.start || 0, true);
        });
    }

    currentStart() {
        const params = new URLSearchParams(window.location.search);

        return parseInt(params.get('start') || params.get('limitstart') || '0', 10) || 0;
    }

    /** Snapshot of the controls, stored on the history entry so Back restores without re-parsing the URL. */
    captureState(limitstart = 0) {
        const checkedValues = selector => [...new Set(
            Array.from(document.querySelectorAll(`${selector}:checked`)).filter(pfilterValued).map(cb => cb.value)
        )];

        return {
            j2commerce: true,
            brands: checkedValues('.j2commerce-brand-checkboxes'),
            vendors: checkedValues('.j2commerce-vendor-checkboxes'),
            pfilters: checkedValues('[class*="j2commerce-pfilter-checkboxes"]'),
            search: document.getElementById('j2commerce-search')?.value || '',
            sortby: document.getElementById('j2commerce-sortby')?.value || '',
            priceFrom: document.getElementById('min_price_input')?.value || '',
            priceTo: document.getElementById('max_price_input')?.value || '',
            rangeMin: document.getElementById('j2commerce-range-min')?.value || '',
            rangeMax: document.getElementById('j2commerce-range-max')?.value || '',
            start: limitstart
        };
    }

    restoreState(state) {
        const applyChecked = (selector, wanted) => {
            const values = new Set(wanted || []);
            // Every matching control, so the mirrored mobile and desktop copies land together.
            document.querySelectorAll(selector).forEach(cb => {
                pfilterPick(cb, values.has(cb.value));
            });
        };

        applyChecked('.j2commerce-brand-checkboxes', state.brands);
        applyChecked('.j2commerce-vendor-checkboxes', state.vendors);
        applyChecked('[class*="j2commerce-pfilter-checkboxes"]', state.pfilters);
        this.syncAnyControls();

        const searchInput = document.getElementById('j2commerce-search');
        if (searchInput) searchInput.value = state.search || '';

        const sortSelect = document.getElementById('j2commerce-sortby');
        if (sortSelect) sortSelect.value = state.sortby || sortSelect.options[0]?.value || '';

        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        if (rangeMin) rangeMin.value = state.rangeMin !== '' ? state.rangeMin : rangeMin.min;
        if (rangeMax) rangeMax.value = state.rangeMax !== '' ? state.rangeMax : rangeMax.max;

        // Same idiom as resetAllFilters(): non-bubbling so the sliderContainer delegation
        // listener does not fire another filter run.
        if (rangeMin) rangeMin.dispatchEvent(new Event('input', { bubbles: false }));

        // updateDisplays() above rewrites the hidden inputs from the slider positions, so the
        // recorded prices go back afterwards rather than before.
        const minPriceInput = document.getElementById('min_price_input');
        const maxPriceInput = document.getElementById('max_price_input');
        if (minPriceInput) minPriceInput.value = state.priceFrom || '0';
        if (maxPriceInput) maxPriceInput.value = state.priceTo || '0';
    }

    bindCheckboxFilters() {
        const checkboxSelectors = [
            '.j2commerce-brand-checkboxes',
            '.j2commerce-vendor-checkboxes',
            '[class*="j2commerce-pfilter-checkboxes"]'
        ];

        checkboxSelectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.syncMirroredCheckbox(checkbox);
                    this.debounce(() => this.applyFilters(), this.checkboxDebounce);
                });
            });
        });

        // An <option> never fires change — the <select> around it does.
        document.querySelectorAll('.j2commerce-pfilter-select').forEach(select => {
            select.addEventListener('change', () => {
                this.syncMirroredSelect(select);
                this.debounce(() => this.applyFilters(), this.checkboxDebounce);
            });
        });
    }

    /** Re-pick the "Any" control of every single-value group that ended up with nothing picked. */
    syncAnyControls() {
        document.querySelectorAll('.j2commerce-productfilter-list').forEach(list => {
            const controls = Array.from(list.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]'));
            const anyControls = controls.filter(el => !pfilterValued(el));

            if (anyControls.length === 0) {
                return;
            }

            const picked = controls.some(el => pfilterValued(el) && pfilterPicked(el));

            anyControls.forEach(el => pfilterPick(el, !picked));
        });
    }

    syncMirroredSelect(source) {
        // Every copy of one group carries the same per-group class, which is the only key that
        // holds where the tag listing omits the group alias and all selects share a name.
        const groupClass = Array.from(source.classList).find(name => name.startsWith('j2commerce-pfilter-select-'));

        if (!groupClass) {
            return;
        }

        const picked = new Set(Array.from(source.selectedOptions).map(option => option.value));

        document.querySelectorAll('.' + groupClass).forEach(select => {
            if (select === source) return;

            Array.from(select.options).forEach(option => {
                option.selected = picked.has(option.value);
            });
        });
    }

    syncMirroredCheckbox(source) {
        const value = source.value;
        const groupAlias = source.dataset.groupAlias;

        // Find all pfilter checkboxes with the same value + group; sync their checked state
        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]').forEach(cb => {
            if (cb === source) return;
            if (cb.value !== value) return;
            // If both have group alias, require it to match; otherwise fall back to value-only match
            if (groupAlias && cb.dataset.groupAlias && cb.dataset.groupAlias !== groupAlias) return;
            pfilterPick(cb, pfilterPicked(source));
        });

        this.syncAnyControls();

        // Also sync brand/vendor mirrors (no group alias needed — IDs are globally unique)
        if (source.classList.contains('j2commerce-brand-checkboxes')) {
            document.querySelectorAll('.j2commerce-brand-checkboxes').forEach(cb => {
                if (cb !== source && cb.value === value) cb.checked = source.checked;
            });
        } else if (source.classList.contains('j2commerce-vendor-checkboxes')) {
            document.querySelectorAll('.j2commerce-vendor-checkboxes').forEach(cb => {
                if (cb !== source && cb.value === value) cb.checked = source.checked;
            });
        }
    }

    bindPriceFilter() {
        const filterBtn = document.getElementById('filterProductsBtn');

        filterBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            this.applyFilters();
        });

        // Use event delegation for price range sliders because they are
        // dynamically created by template JavaScript after DOM ready.
        // Bind to the slider container which exists at page load.
        const sliderContainer = document.getElementById('j2commerce-slider-range');
        if (sliderContainer) {
            const isSlider = (e) => e.target.id === 'j2commerce-range-min' || e.target.id === 'j2commerce-range-max';

            // 'input' fires reliably during drag even with pointer-events:none
            // on the range element (only thumb has pointer-events:auto).
            // 'change' may NOT fire/bubble in that CSS scenario on some browsers.
            sliderContainer.addEventListener('input', (e) => {
                if (isSlider(e)) {
                    this.debounce(() => this.applyFilters(), this.checkboxDebounce);
                }
            });

            // Keep 'change' as a fallback for browsers where it does fire
            sliderContainer.addEventListener('change', (e) => {
                if (isSlider(e)) {
                    this.debounce(() => this.applyFilters(), this.checkboxDebounce);
                }
            });
        }

        // Also watch the hidden price inputs directly in case they're updated by other means
        const minInput = document.getElementById('min_price_input');
        const maxInput = document.getElementById('max_price_input');

        minInput?.addEventListener('change', () => {
            this.debounce(() => this.applyFilters(), this.checkboxDebounce);
        });

        maxInput?.addEventListener('change', () => {
            this.debounce(() => this.applyFilters(), this.checkboxDebounce);
        });
    }

    bindSearchFilter() {
        const searchInput = document.getElementById('j2commerce-search');

        searchInput?.addEventListener('input', () => {
            this.debounce(() => this.applyFilters(), this.searchDebounce);
        });

        searchInput?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(this.debounceTimer);
                this.applyFilters();
            }
        });

        // Prevent sort/search form submission - use AJAX instead
        // The form has Go button with type="submit" that would cause page reload
        if (this.sortForm) {
            this.sortForm.addEventListener('submit', (e) => {
                e.preventDefault();
                clearTimeout(this.debounceTimer);
                this.applyFilters();
            });
        }
    }

    bindSortFilter() {
        const sortSelect = document.getElementById('j2commerce-sortby');

        sortSelect?.addEventListener('change', () => {
            this.applyFilters();
        });
    }

    bindCategoryLinks() {
        // Modern router uses real href links — no interception needed
    }

    bindClearButtons() {
        document.querySelectorAll('.j2commerce-clear-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                const filterType = btn.dataset.filterType;
                let selector = '';

                if (filterType === 'brand') {
                    selector = '.j2commerce-brand-checkboxes';
                } else if (filterType === 'vendor') {
                    selector = '.j2commerce-vendor-checkboxes';
                }

                if (selector) {
                    document.querySelectorAll(selector).forEach(cb => cb.checked = false);
                    this.applyFilters();
                }
            });
        });

        document.querySelectorAll('.j2commerce-clear-pf-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                const filterClass = btn.dataset.filterClass;
                if (filterClass) {
                    document.querySelectorAll('.' + filterClass).forEach(cb => pfilterPick(cb, false));
                    this.syncAnyControls();
                    this.applyFilters();
                }
            });
        });

        const resetBtn = document.getElementById('j2commerce-filter-reset');
        resetBtn?.addEventListener('click', () => {
            this.resetAllFilters();
        });
    }

    bindPagination() {
        document.addEventListener('click', (e) => {
            // Joomla's pagination chrome doesn't always emit .page-link (UIkit/UI3 chromes
            // use bare anchors). Match any anchor with an href inside .j2commerce-pagination.
            const paginationLink = e.target.closest('.j2commerce-pagination a[href]');
            if (!paginationLink) return;

            e.preventDefault();
            const href = paginationLink.getAttribute('href');
            if (!href) return;

            const url = new URL(href, window.location.origin);
            const limitstart = url.searchParams.get('limitstart') || url.searchParams.get('start') || 0;

            this.applyFilters(parseInt(limitstart, 10));
        });
    }

    collectFilterData(limitstart = 0, token = '') {
        const data = new FormData();

        const manufacturerIds = Array.from(document.querySelectorAll('.j2commerce-brand-checkboxes:checked'))
            .map(cb => cb.value);
        manufacturerIds.forEach(id => data.append('manufacturer_ids[]', id));

        const vendorIds = Array.from(document.querySelectorAll('.j2commerce-vendor-checkboxes:checked'))
            .map(cb => cb.value);
        vendorIds.forEach(id => data.append('vendor_ids[]', id));

        const productfilterIds = [...new Set(
            Array.from(document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked'))
                .filter(pfilterValued)
                .map(cb => cb.value)
        )];
        productfilterIds.forEach(id => data.append('productfilter_ids[]', id));

        // #filter_catid lives in the sidebar filter form, which is not rendered when
        // list_show_filter is off — fall back to the sort form, then to the wrapper's
        // data attribute, which is always present.
        const catid = document.getElementById('filter_catid')?.value
            || document.getElementById('sort_filter_catid')?.value
            || this.productContainer?.dataset.filterCatid
            || '';
        if (catid) data.append('filter_catid', catid);

        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        // Compare slider POSITION against its attributes — not the hidden price input
        // values — to avoid a float precision mismatch (Math.ceil(maxPrice) vs rawPrice).
        const isCustomPrice = rangeMin && rangeMax && (
            parseFloat(rangeMin.value) > parseFloat(rangeMin.min) ||
            parseFloat(rangeMax.value) < parseFloat(rangeMax.max)
        );
        if (isCustomPrice) {
            const priceFrom = parseFloat(document.getElementById('min_price_input')?.value) || 0;
            const priceTo = parseFloat(document.getElementById('max_price_input')?.value) || 0;
            if (priceFrom > 0) data.append('pricefrom', priceFrom.toString());
            if (priceTo > 0) data.append('priceto', priceTo.toString());
        }

        const search = document.getElementById('j2commerce-search')?.value || '';
        if (search) data.append('search', search);

        const sortby = document.getElementById('j2commerce-sortby')?.value || '';
        if (sortby) data.append('sortby', sortby);

        data.append('limitstart', limitstart.toString());

        // Include Itemid for menu item context (needed for sub-template selection)
        const itemidInput = document.querySelector('input[name="Itemid"]');
        const itemid = itemidInput?.value || Joomla.getOptions('j2commerce.Itemid') || '';
        if (itemid) {
            data.append('Itemid', itemid);
        }

        if (token) {
            data.append(token, '1');
        }

        return data;
    }

    async applyFilters(limitstart = 0, fromHistory = false) {
        if (!this.enabled) {
            this.fallbackSubmit();
            return;
        }

        this.showLoading();

        const token = await this.resolveToken();
        const data = this.collectFilterData(limitstart, token);

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                body: data,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Filter request failed');
            }

            const payload = result.data || result;

            this.updateProducts(payload);
            this.updateFilterCounts(payload.filterCounts);
            this.updateUrl(data, limitstart, fromHistory);
            this.updateClearButtonVisibility();
            this.buildActiveFilterTiles();

        } catch (error) {
            console.error('J2Commerce filter error:', error);
            this.fallbackSubmit();
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Re-point each sidebar value at the listing that just came back.
     *
     * The server sends counts only. The value list is a superset that ignores the current
     * selection, so every checkbox the visitor could ever need is already in the page and
     * this only has to update numbers and availability — the accordion stays where it was
     * and focus is never torn out from under the keyboard.
     */
    updateFilterCounts(counts) {
        if (!counts || typeof counts !== 'object') {
            return;
        }

        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]').forEach(checkbox => {
            // The "Any" control of a single-value group carries no value to count.
            if (!pfilterValued(checkbox)) {
                return;
            }

            const count = Number(counts[checkbox.value] ?? 0);

            checkbox.dataset.count = String(count);

            // A picked value stays operable at zero, or there is no way left to undo the
            // selection that emptied the listing.
            checkbox.disabled = count === 0 && !pfilterPicked(checkbox);

            if (checkbox.tagName === 'OPTION') {
                // An option has no room for a separate badge, so the count rides its text.
                checkbox.textContent = `${checkbox.dataset.label ?? checkbox.textContent} (${count})`;

                return;
            }

            const list = checkbox.closest('.j2commerce-productfilter-list');
            const wrapper = list
                ? Array.from(list.children).find(child => child.contains(checkbox))
                : checkbox.parentElement;

            wrapper?.classList.toggle('j2commerce-filter-unavailable', checkbox.disabled);

            const badge = wrapper?.querySelector('.j2commerce-filter-count');

            if (badge) {
                badge.textContent = `(${count})`;
            }
        });

        // A group with nothing left to offer is noise — fold the whole accordion item away.
        document.querySelectorAll('.j2commerce-productfilter-list').forEach(list => {
            const boxes = Array.from(list.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]'))
                .filter(pfilterValued);
            const item = list.closest('.accordion-item, li');

            if (item && boxes.length) {
                item.hidden = boxes.every(cb => cb.disabled);
            }
        });
    }

    updateProducts(data) {
        // Target the inner content wrapper.  Prefer the explicit data attribute set by
        // default.php (unambiguous even in template overrides), then fall back to the
        // class name.  The old [class*="col-md-"] fallback is intentionally removed: it
        // matched the sidebar column (.col-md-3) before the content column (.col-md-9)
        // when filters are positioned on the left, causing products to be inserted into
        // the sidebar while existing rows were never removed.
        const contentArea = this.productContainer.querySelector('[data-j2commerce-content="products"]') ||
            this.productContainer.querySelector('.j2commerce-products-content');

        if (!contentArea) {
            // No content area found — cannot do an in-place DOM update.
            // Fall back to a full form submission so the user still gets filtered results.
            this.fallbackSubmit();
            return;
        }

        const existingRows = contentArea.querySelectorAll('.j2commerce-products-row');
        existingRows.forEach(row => row.remove());

        const existingPagination = contentArea.querySelector('.j2commerce-pagination');
        if (existingPagination) existingPagination.closest('form, nav')?.remove();

        // Remove "no products" alert — may be a bare .alert-info or wrapped in .row > .col-12
        const existingNoProducts = contentArea.querySelector('.alert-info');
        if (existingNoProducts) {
            const wrapper = existingNoProducts.closest('.row');
            if (wrapper && wrapper.parentNode === contentArea) {
                wrapper.remove();
            } else {
                existingNoProducts.remove();
            }
        }

        const sortFilter = contentArea.querySelector('.form-inline, #productFilters');
        const insertPoint = sortFilter || contentArea.firstChild;

        const tempDiv = document.createElement('div');
        tempDiv.append(J2CommerceDom.parse(data.products));

        while (tempDiv.firstChild) {
            if (insertPoint && insertPoint.parentNode === contentArea) {
                insertPoint.after(tempDiv.firstChild);
            } else {
                contentArea.appendChild(tempDiv.firstChild);
            }
        }

        if (data.pagination) {
            const paginationDiv = document.createElement('div');
            paginationDiv.append(J2CommerceDom.parse(data.pagination));
            contentArea.appendChild(paginationDiv.firstChild || paginationDiv);
        }

        this.productContainer.dispatchEvent(new CustomEvent('j2commerce:filters-applied', {
            bubbles: true,
            detail: { total: data.total, start: data.start, limit: data.limit }
        }));

        this.updateShowingCount(data.total);

        if (typeof J2Commerce !== 'undefined') {
            J2Commerce.equalizeHeights();
        }
    }

    updateShowingCount(total) {
        const el = document.getElementById('j2commerce-showing-count');
        if (!el) return;
        total = parseInt(total, 10) || 0;
        const key = total === 1 ? 'COM_J2COMMERCE_SHOWING_1_ITEM' : 'COM_J2COMMERCE_SHOWING_N_ITEMS';
        const str = Joomla.Text._(key) || `Showing <strong>${total}</strong> Items`;
        // Deliberate parse: this language string carries its own markup (the count is bolded),
        // and the only interpolated value is an integer from parseInt above. Rendering it as
        // text would drop the emphasis the translation is written around.
        el.replaceChildren(document.createRange().createContextualFragment(str.replace(/%d/, total)));
    }

    updateUrl(formData, limitstart, replace = false) {
        const params = new URLSearchParams();

        // Use comma-separated values for cleaner SEF-friendly URLs
        // e.g., ?brands=1,2,3 instead of ?manufacturer_ids[]=1&manufacturer_ids[]=2
        const manufacturerIds = formData.getAll('manufacturer_ids[]');
        if (manufacturerIds.length > 0) {
            params.set('brands', manufacturerIds.join(','));
        }

        const vendorIds = formData.getAll('vendor_ids[]');
        if (vendorIds.length > 0) {
            params.set('vendors', vendorIds.join(','));
        }

        // Use composite groupAlias:filterAlias tokens to disambiguate same-named filters across groups.
        // Falls back to bare alias (or numeric value) when group alias is absent.
        const productfilterTokens = [...new Set(
            Array.from(document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked'))
                .filter(pfilterValued)
                .map(cb => {
                    const alias = cb.dataset.alias || cb.value;
                    const groupAlias = cb.dataset.groupAlias;
                    return groupAlias ? `${groupAlias}:${alias}` : alias;
                })
                .filter(token => token)
        )];
        if (productfilterTokens.length > 0) {
            params.set('filters', productfilterTokens.join(','));
        }

        const search = formData.get('search');
        if (search) {
            params.set('search', search);
        }

        // Don't add sortby to URL if it's the default value (a.ordering)
        const sortby = formData.get('sortby');
        if (sortby && sortby !== 'a.ordering') {
            // Use SEF-friendly sort names
            const sortMap = {
                'a.title ASC': 'name-asc',
                'a.title DESC': 'name-desc',
                'v.price ASC': 'price-asc',
                'v.price DESC': 'price-desc',
                'a.created DESC': 'newest',
                'p.hits DESC': 'popular'
            };
            const sefSort = sortMap[sortby] || sortby;
            params.set('sort', sefSort);
        }

        const pricefrom = formData.get('pricefrom');
        const priceto = formData.get('priceto');
        if (pricefrom) params.set('pricefrom', pricefrom);
        if (priceto) params.set('priceto', priceto);

        if (limitstart > 0) {
            params.set('start', limitstart.toString());
        }

        // Build query string and decode commas for cleaner URLs
        // URLSearchParams encodes commas as %2C, but commas are safe in query strings
        // URLSearchParams encodes commas and colons; both are safe in query strings.
        // Keep the composite group:filter token readable as `?filters=caliber:9mm`.
        const queryString = params.toString().replace(/%2C/gi, ',').replace(/%3A/gi, ':');
        const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
        const state = this.captureState(limitstart);

        // Replaying history, or landing on the URL we are already on: overwrite the current
        // entry. Pushing an identical URL would cost the visitor a Back press that appears
        // to do nothing.
        if (replace || newUrl === window.location.pathname + window.location.search) {
            window.history.replaceState(state, '', newUrl);

            return;
        }

        window.history.pushState(state, '', newUrl);
    }

    resetAllFilters() {
        document.querySelectorAll('.j2commerce-brand-checkboxes').forEach(cb => cb.checked = false);
        document.querySelectorAll('.j2commerce-vendor-checkboxes').forEach(cb => cb.checked = false);
        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]').forEach(cb => pfilterPick(cb, false));
        this.syncAnyControls();

        const searchInput = document.getElementById('j2commerce-search');
        if (searchInput) searchInput.value = '';

        const sortSelect = document.getElementById('j2commerce-sortby');
        if (sortSelect) sortSelect.selectedIndex = 0;

        // Reset price range sliders to their full range (min to max)
        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        if (rangeMin) rangeMin.value = rangeMin.min;
        if (rangeMax) rangeMax.value = rangeMax.max;

        // Trigger the template's updateDisplays to refresh min_price_display / max_price_display.
        // Use non-bubbling event to avoid re-triggering the sliderContainer delegation listener.
        if (rangeMin) rangeMin.dispatchEvent(new Event('input', { bubbles: false }));

        // CRITICAL: Set hidden price inputs to 0 to DISABLE price filtering
        // collectFilterData() only sends price params if value > 0.
        // updateDisplays() above set them to slider min/max; override back to 0.
        const minPriceInput = document.getElementById('min_price_input');
        const maxPriceInput = document.getElementById('max_price_input');
        if (minPriceInput) minPriceInput.value = '0';
        if (maxPriceInput) maxPriceInput.value = '0';

        this.applyFilters();
    }

    showLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = 'block';
        }
        this.productContainer?.classList.add('j2commerce-loading');
    }

    hideLoading() {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = 'none';
        }
        this.productContainer?.classList.remove('j2commerce-loading');
    }

    debounce(callback, delay) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(callback, delay);
    }

    fallbackSubmit() {
        // Use sort form preferentially as it has the SEF action URL
        const form = this.sortForm || this.filterForm;
        if (form) {
            this.showLoading();

            // If using sortForm, sync filter values from filterForm
            if (form === this.sortForm && this.filterForm) {
                this.syncFilterValuesToSortForm();
            }

            form.submit();
        }
    }

    syncFilterValuesToSortForm() {
        // Sync hidden filter values from sidebar filter form to sort form for fallback submission
        const sortForm = this.sortForm;
        const filterForm = this.filterForm;
        if (!sortForm || !filterForm) return;

        // Sync checkbox filters by creating hidden inputs
        const existingHiddenInputs = sortForm.querySelectorAll('input[data-synced="true"]');
        existingHiddenInputs.forEach(input => input.remove());

        // Sync manufacturer IDs
        const manufacturerIds = Array.from(filterForm.querySelectorAll('.j2commerce-brand-checkboxes:checked'))
            .map(cb => cb.value);
        manufacturerIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'manufacturer_ids[]';
            input.value = id;
            input.dataset.synced = 'true';
            sortForm.appendChild(input);
        });

        // Sync vendor IDs
        const vendorIds = Array.from(filterForm.querySelectorAll('.j2commerce-vendor-checkboxes:checked'))
            .map(cb => cb.value);
        vendorIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'vendor_ids[]';
            input.value = id;
            input.dataset.synced = 'true';
            sortForm.appendChild(input);
        });

        // Sync product filter IDs
        const productfilterIds = Array.from(filterForm.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked'))
            .filter(pfilterValued)
            .map(cb => cb.value);
        productfilterIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'productfilter_ids[]';
            input.value = id;
            input.dataset.synced = 'true';
            sortForm.appendChild(input);
        });

        // Sync price filters
        const priceFrom = filterForm.querySelector('#min_price_input')?.value;
        const priceTo = filterForm.querySelector('#max_price_input')?.value;
        if (priceFrom && parseFloat(priceFrom) > 0) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'pricefrom';
            input.value = priceFrom;
            input.dataset.synced = 'true';
            sortForm.appendChild(input);
        }
        if (priceTo && parseFloat(priceTo) > 0) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'priceto';
            input.value = priceTo;
            input.dataset.synced = 'true';
            sortForm.appendChild(input);
        }

        // Sync catid from filter form if exists
        const filterCatid = filterForm.querySelector('#filter_catid')?.value;
        const sortCatid = sortForm.querySelector('#sort_filter_catid');
        if (filterCatid && sortCatid) {
            sortCatid.value = filterCatid;
        }
    }

    updateClearButtonVisibility() {
        // Brand/Manufacturer clear button
        const brandClearBtn = document.getElementById('j2commerce-clear-brand');
        if (brandClearBtn) {
            const hasCheckedBrands = document.querySelectorAll('.j2commerce-brand-checkboxes:checked').length > 0;
            brandClearBtn.style.display = hasCheckedBrands ? '' : 'none';
        }

        // Vendor clear button
        const vendorClearBtn = document.getElementById('j2commerce-clear-vendor');
        if (vendorClearBtn) {
            const hasCheckedVendors = document.querySelectorAll('.j2commerce-vendor-checkboxes:checked').length > 0;
            vendorClearBtn.style.display = hasCheckedVendors ? '' : 'none';
        }

        // Product filter group clear buttons
        document.querySelectorAll('.j2commerce-productfilter-list').forEach(container => {
            const containerId = container.id;
            if (!containerId) return;

            const filterScriptId = containerId.replace('j2commerce-pf-filter-', '');
            const clearBtn = document.getElementById('product-filter-group-clear-' + filterScriptId);

            if (clearBtn) {
                const checkboxClass = 'j2commerce-pfilter-checkboxes-' + filterScriptId;
                const hasCheckedFilters = Array.from(container.querySelectorAll('.' + checkboxClass + ':checked'))
                    .some(pfilterValued);
                clearBtn.style.display = hasCheckedFilters ? '' : 'none';

                // Expand the accordion/accordion panel if it has checked filters
                if (hasCheckedFilters) {
                    // Bootstrap 5
                    const collapseEl = container.closest('.accordion-collapse');
                    if (collapseEl && !collapseEl.classList.contains('show')) {
                        collapseEl.classList.add('show');
                        const btn = collapseEl.previousElementSibling?.querySelector('.accordion-button');
                        if (btn) btn.classList.remove('collapsed');
                    }
                    // UIKit
                    const ukItem = container.closest('li.uk-accordion-item') ?? container.closest('.uk-accordion > li');
                    if (ukItem && !ukItem.classList.contains('uk-open')) {
                        ukItem.classList.add('uk-open');
                        const content = ukItem.querySelector('.uk-accordion-content');
                        if (content) content.hidden = false;
                    }
                }
            }
        });
    }

    // A layout may render the filter form twice (UIkit ships a mobile offcanvas copy
    // alongside the desktop sidebar), so every chip target is addressed as a set.
    // The bare ids are kept in the set for template overrides that predate the classes.
    filterChipTargets() {
        const collect = (className, id) => {
            const nodes = new Set(document.querySelectorAll('.' + className));
            const legacy = document.getElementById(id);

            if (legacy) nodes.add(legacy);

            return [...nodes];
        };

        return {
            containers: collect('j2commerce-active-filter-tiles', 'j2commerce-active-filter-tiles'),
            clearAllButtons: collect('j2commerce-clear-all-filters', 'j2commerce-clear-all-filters'),
        };
    }

    buildActiveFilterTiles() {
        const { containers, clearAllButtons } = this.filterChipTargets();
        if (containers.length === 0) return;

        const tiles = [];

        const getCheckboxLabel = (cb) =>
            cb.dataset.label?.trim()
            ?? cb.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim()
            ?? cb.labels?.[0]?.textContent?.trim()
            ?? cb.closest('label')?.textContent?.trim();

        document.querySelectorAll('.j2commerce-brand-checkboxes:checked').forEach(cb => {
            const label = getCheckboxLabel(cb);
            if (label) tiles.push(this.createTile('brand', cb.value, label));
        });

        document.querySelectorAll('.j2commerce-vendor-checkboxes:checked').forEach(cb => {
            const label = getCheckboxLabel(cb);
            if (label) tiles.push(this.createTile('vendor', cb.value, label));
        });

        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked').forEach(cb => {
            if (!pfilterValued(cb)) return;

            const label = getCheckboxLabel(cb);
            if (label) tiles.push(this.createTile('productfilter', cb.value, label));
        });

        // Price range tile (only when customized from defaults)
        // Price range tile — compare slider POSITION against its min/max attributes,
        // not the hidden price inputs against the slider attributes.
        // The slider uses Math.floor/ceil so rangeMax.max may differ from the raw
        // catalog price stored in max_price_input, causing a false "custom price"
        // detection on every fresh page load.
        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        if (rangeMin && rangeMax) {
            const isCustomPrice = parseFloat(rangeMin.value) > parseFloat(rangeMin.min) ||
                                  parseFloat(rangeMax.value) < parseFloat(rangeMax.max);
            if (isCustomPrice) {
                const minDisplay = document.getElementById('min_price_display')?.textContent?.trim() || rangeMin.value;
                const maxDisplay = document.getElementById('max_price_display')?.textContent?.trim() || rangeMax.value;
                tiles.push(this.createTile('price', 'price', `${minDisplay} – ${maxDisplay}`));
            }
        }

        // Search tile
        const searchValue = document.getElementById('j2commerce-search')?.value?.trim();
        if (searchValue) {
            tiles.push(this.createTile('search', 'search', `"${searchValue}"`));
        }

        // Update only the tiles content and "Clear all" visibility — wrapper stays visible
        containers.forEach(container => {
            container.replaceChildren();
            if (tiles.length > 0) {
                // Clone per container: appending the same node twice would move it out of the first.
                container.append(...tiles.map(tile => tile.cloneNode(true)));
            }
        });
        clearAllButtons.forEach(btn => {
            btn.style.display = tiles.length > 0 ? '' : 'none';
        });
    }

    createTile(type, id, displayLabel) {
        const uikit = typeof UIkit !== 'undefined';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = uikit
            ? 'j2commerce-filter-chip-remove uk-close uk-margin-small-left'
            : 'j2commerce-filter-chip-remove btn-close text-danger ms-1';
        remove.setAttribute('aria-label', 'Remove');
        if (!uikit) {
            remove.style.fontSize = '.5rem';
        }

        const chip = document.createElement('span');
        chip.className = uikit
            ? 'filter-chip uk-label'
            : 'filter-chip badge bg-light text-dark border d-flex align-items-center gap-1 p-2';
        chip.dataset.type = type;
        chip.dataset.id = String(id);
        chip.append(displayLabel, remove);

        return chip;
    }

    bindActiveFilterTiles() {
        // Event delegation — survives innerHTML replacement after AJAX
        document.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.j2commerce-filter-chip-remove') ?? e.target.closest('.filter-chip .btn-close');
            if (!removeBtn) return;
            const chip = removeBtn.closest('.filter-chip');
            if (!chip) return;

            e.preventDefault();
            this.removeFilter(chip.dataset.type, chip.dataset.id);
        });

        // Delegated so every rendered copy of the "Clear all" control is covered.
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.j2commerce-clear-all-filters, #j2commerce-clear-all-filters')) return;

            e.preventDefault();
            this.resetAllFilters();
        });
    }

    bindMobileFooter() {
        document.getElementById('j2commerce-mobile-clear-all')?.addEventListener('click', () => {
            this.resetAllFilters();
            // Close offcanvas after clearing
            const offcanvasEl = document.getElementById('j2commerceFilterOffcanvas');
            if (offcanvasEl) {
                if (typeof UIkit !== 'undefined') {
                    try { UIkit.offcanvas(offcanvasEl).hide(); } catch (_) {}
                } else {
                    globalThis.bootstrap?.Offcanvas?.getInstance(offcanvasEl)?.hide();
                }
            }
        });
    }

    removeFilter(type, id) {
        // Document-wide, not scoped to this.filterForm: a layout may render the filter
        // form twice, and clearing only one copy leaves the other checked — collectFilterData()
        // reads every checkbox in the document, so the filter would survive its own removal.
        const uncheckByValue = (selector) =>
            document.querySelectorAll(selector).forEach(cb => {
                if (cb.value === id) pfilterPick(cb, false);
            });

        switch (type) {
            case 'brand': {
                uncheckByValue('.j2commerce-brand-checkboxes');
                break;
            }
            case 'vendor': {
                uncheckByValue('.j2commerce-vendor-checkboxes');
                break;
            }
            case 'productfilter': {
                uncheckByValue('[class*="j2commerce-pfilter-checkboxes"]');
                this.syncAnyControls();
                break;
            }
            case 'price': {
                const rangeMin = document.getElementById('j2commerce-range-min');
                const rangeMax = document.getElementById('j2commerce-range-max');
                if (rangeMin) rangeMin.value = rangeMin.min;
                if (rangeMax) rangeMax.value = rangeMax.max;
                if (rangeMin) rangeMin.dispatchEvent(new Event('input', { bubbles: false }));
                const minInput = document.getElementById('min_price_input');
                const maxInput = document.getElementById('max_price_input');
                if (minInput) minInput.value = '0';
                if (maxInput) maxInput.value = '0';
                break;
            }
            case 'search': {
                const searchInput = document.getElementById('j2commerce-search');
                if (searchInput) searchInput.value = '';
                break;
            }
        }
        this.applyFilters();
    }

    disable() {
        this.enabled = false;
    }

    enable() {
        this.enabled = true;
    }
}

window.J2CommerceFilters = J2CommerceFilters;
