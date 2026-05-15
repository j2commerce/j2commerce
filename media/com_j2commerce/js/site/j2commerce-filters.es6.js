class J2CommerceFilters {
    constructor(options = {}) {
        this.productContainer = document.querySelector(options.productContainer || '.j2commerce-product-list');
        this.filterForm = document.getElementById(options.filterFormId || 'productsideFilters');
        this.sortForm = document.getElementById(options.sortFormId || 'productFilters');
        this.paginationForm = document.getElementById(options.paginationFormId || 'j2commerce-pagination');
        this.loadingOverlay = document.getElementById('j2commerce-product-loading');
        this.endpoint = options.endpoint || 'index.php?option=com_j2commerce&task=products.filter&format=json';
        this.csrfToken = Joomla.getOptions('csrf.token') || '';
        this.checkboxDebounce = options.checkboxDebounce || 300;
        this.searchDebounce = options.searchDebounce || 500;
        this.debounceTimer = null;
        this.enabled = true;

        this.init();
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
            cb.checked = source.checked;
        });

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
                    document.querySelectorAll('.' + filterClass).forEach(cb => cb.checked = false);
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

    collectFilterData(limitstart = 0) {
        const data = new FormData();

        const manufacturerIds = Array.from(document.querySelectorAll('.j2commerce-brand-checkboxes:checked'))
            .map(cb => cb.value);
        manufacturerIds.forEach(id => data.append('manufacturer_ids[]', id));

        const vendorIds = Array.from(document.querySelectorAll('.j2commerce-vendor-checkboxes:checked'))
            .map(cb => cb.value);
        vendorIds.forEach(id => data.append('vendor_ids[]', id));

        const productfilterIds = [...new Set(
            Array.from(document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked'))
                .map(cb => cb.value)
        )];
        productfilterIds.forEach(id => data.append('productfilter_ids[]', id));

        const catid = document.getElementById('filter_catid')?.value || '';
        if (catid) data.append('filter_catid', catid);

        const priceFrom = parseFloat(document.getElementById('min_price_input')?.value) || 0;
        const priceTo = parseFloat(document.getElementById('max_price_input')?.value) || 0;
        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        const isCustomPrice = rangeMin && rangeMax && (priceFrom > parseFloat(rangeMin.min) || priceTo < parseFloat(rangeMax.max));
        if (isCustomPrice) {
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

        if (this.csrfToken) {
            data.append(this.csrfToken, '1');
        }

        return data;
    }

    async applyFilters(limitstart = 0) {
        if (!this.enabled) {
            this.fallbackSubmit();
            return;
        }

        this.showLoading();

        const data = this.collectFilterData(limitstart);

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

            this.updateProducts(result.data || result);
            this.updateUrl(data, limitstart);
            this.updateClearButtonVisibility();
            this.buildActiveFilterTiles();

        } catch (error) {
            console.error('J2Commerce filter error:', error);
            this.fallbackSubmit();
        } finally {
            this.hideLoading();
        }
    }

    updateProducts(data) {
        // Target the inner content wrapper (.j2commerce-products-content) which sits
        // inside .col-md-9.  This matches the nesting in default.php so AJAX-replaced
        // HTML is structurally identical to the initial server render.
        const contentArea = this.productContainer.querySelector('.j2commerce-products-content') ||
            this.productContainer.querySelector('.col-md-9') ||
            this.productContainer.querySelector('[class*="col-md-"]');

        if (!contentArea) return;

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
        tempDiv.innerHTML = data.products;

        while (tempDiv.firstChild) {
            if (insertPoint && insertPoint.parentNode === contentArea) {
                insertPoint.after(tempDiv.firstChild);
            } else {
                contentArea.appendChild(tempDiv.firstChild);
            }
        }

        if (data.pagination) {
            const paginationDiv = document.createElement('div');
            paginationDiv.innerHTML = data.pagination;
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
        el.innerHTML = str.replace(/%d/, total);
    }

    updateUrl(formData, limitstart) {
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
                'a.hits DESC': 'popular'
            };
            const sefSort = sortMap[sortby] || sortby;
            params.set('sort', sefSort);
        }

        if (limitstart > 0) {
            params.set('start', limitstart.toString());
        }

        // Build query string and decode commas for cleaner URLs
        // URLSearchParams encodes commas as %2C, but commas are safe in query strings
        // URLSearchParams encodes commas and colons; both are safe in query strings.
        // Keep the composite group:filter token readable as `?filters=caliber:9mm`.
        const queryString = params.toString().replace(/%2C/gi, ',').replace(/%3A/gi, ':');
        const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
        window.history.replaceState({ j2commerce: true }, '', newUrl);
    }

    resetAllFilters() {
        document.querySelectorAll('.j2commerce-brand-checkboxes').forEach(cb => cb.checked = false);
        document.querySelectorAll('.j2commerce-vendor-checkboxes').forEach(cb => cb.checked = false);
        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]').forEach(cb => cb.checked = false);

        const searchInput = document.getElementById('j2commerce-search');
        if (searchInput) searchInput.value = '';

        const sortSelect = document.getElementById('j2commerce-sortby');
        if (sortSelect) sortSelect.selectedIndex = 0;

        // Reset price range sliders to their full range (min to max)
        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        if (rangeMin) rangeMin.value = rangeMin.min;
        if (rangeMax) rangeMax.value = rangeMax.max;

        // CRITICAL: Set hidden price inputs to 0 to DISABLE price filtering
        // collectFilterData() only sends price params if value > 0
        // Setting to slider min/max would still filter; setting to 0 disables filtering
        const minPriceInput = document.getElementById('min_price_input');
        const maxPriceInput = document.getElementById('max_price_input');
        if (minPriceInput) minPriceInput.value = '0';
        if (maxPriceInput) maxPriceInput.value = '0';

        // Update display elements to show the full range
        const minPriceSpan = document.getElementById('min_price');
        const maxPriceSpan = document.getElementById('max_price');
        if (minPriceSpan && rangeMin) minPriceSpan.textContent = rangeMin.min;
        if (maxPriceSpan && rangeMax) maxPriceSpan.textContent = rangeMax.max;

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
                const hasCheckedFilters = container.querySelectorAll('.' + checkboxClass + ':checked').length > 0;
                clearBtn.style.display = hasCheckedFilters ? '' : 'none';

                // Expand the accordion panel if it has checked filters
                if (hasCheckedFilters) {
                    const collapseEl = container.closest('.accordion-collapse');
                    if (collapseEl && !collapseEl.classList.contains('show')) {
                        collapseEl.classList.add('show');
                        const btn = collapseEl.previousElementSibling?.querySelector('.accordion-button');
                        if (btn) btn.classList.remove('collapsed');
                    }
                }
            }
        });
    }

    buildActiveFilterTiles() {
        const container = document.getElementById('j2commerce-active-filter-tiles');
        const clearAllBtn = document.getElementById('j2commerce-clear-all-filters');
        if (!container) return;

        const tiles = [];

        document.querySelectorAll('.j2commerce-brand-checkboxes:checked').forEach(cb => {
            const label = cb.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim();
            if (label) tiles.push(this.createTileHtml('brand', cb.value, label));
        });

        document.querySelectorAll('.j2commerce-vendor-checkboxes:checked').forEach(cb => {
            const label = cb.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim();
            if (label) tiles.push(this.createTileHtml('vendor', cb.value, label));
        });

        document.querySelectorAll('[class*="j2commerce-pfilter-checkboxes"]:checked').forEach(cb => {
            const label = cb.closest('.form-check')?.querySelector('.form-check-label')?.textContent?.trim();
            if (label) tiles.push(this.createTileHtml('productfilter', cb.value, label));
        });

        // Price range tile (only when customized from defaults)
        const priceFrom = parseFloat(document.getElementById('min_price_input')?.value) || 0;
        const priceTo = parseFloat(document.getElementById('max_price_input')?.value) || 0;
        const rangeMin = document.getElementById('j2commerce-range-min');
        const rangeMax = document.getElementById('j2commerce-range-max');
        if (rangeMin && rangeMax && priceFrom > 0 && priceTo > 0) {
            const isCustomPrice = (priceFrom > parseFloat(rangeMin.min) || priceTo < parseFloat(rangeMax.max));
            if (isCustomPrice) {
                const minDisplay = document.getElementById('min_price_display')?.textContent?.trim() || priceFrom;
                const maxDisplay = document.getElementById('max_price_display')?.textContent?.trim() || priceTo;
                tiles.push(this.createTileHtml('price', 'price', `${minDisplay} – ${maxDisplay}`));
            }
        }

        // Search tile
        const searchValue = document.getElementById('j2commerce-search')?.value?.trim();
        if (searchValue) {
            tiles.push(this.createTileHtml('search', 'search', `"${searchValue}"`));
        }

        // Update only the tiles content and "Clear all" visibility — wrapper stays visible
        container.innerHTML = tiles.length > 0 ? tiles.join('') : '';
        if (clearAllBtn) {
            clearAllBtn.style.display = tiles.length > 0 ? '' : 'none';
        }
    }

    createTileHtml(type, id, displayLabel) {
        const escaped = this.escapeHtml(displayLabel);
        return `<span class="filter-chip badge bg-light text-dark border d-flex align-items-center gap-1 p-2" data-type="${type}" data-id="${this.escapeHtml(String(id))}">${escaped}<button type="button" class="btn-close text-danger ms-1" style="font-size:.5rem" aria-label="Remove"></button></span>`;
    }

    bindActiveFilterTiles() {
        // Event delegation — survives innerHTML replacement after AJAX
        document.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.filter-chip .btn-close');
            if (!removeBtn) return;
            const chip = removeBtn.closest('.filter-chip');
            if (!chip) return;

            e.preventDefault();
            this.removeFilter(chip.dataset.type, chip.dataset.id);
        });

        document.getElementById('j2commerce-clear-all-filters')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.resetAllFilters();
        });
    }

    bindMobileFooter() {
        document.getElementById('j2commerce-mobile-clear-all')?.addEventListener('click', () => {
            this.resetAllFilters();
            // Close offcanvas after clearing
            const offcanvasEl = document.getElementById('j2commerceFilterOffcanvas');
            const offcanvas = globalThis.bootstrap?.Offcanvas?.getInstance(offcanvasEl);
            offcanvas?.hide();
        });
    }

    removeFilter(type, id) {
        const findByValue = (selector) =>
            Array.from(this.filterForm?.querySelectorAll(selector) || []).find(cb => cb.value === id);

        switch (type) {
            case 'brand': {
                const cb = findByValue('.j2commerce-brand-checkboxes');
                if (cb) cb.checked = false;
                break;
            }
            case 'vendor': {
                const cb = findByValue('.j2commerce-vendor-checkboxes');
                if (cb) cb.checked = false;
                break;
            }
            case 'productfilter': {
                const cb = findByValue('[class*="j2commerce-pfilter-checkboxes"]');
                if (cb) cb.checked = false;
                break;
            }
            case 'price': {
                const rangeMin = document.getElementById('j2commerce-range-min');
                const rangeMax = document.getElementById('j2commerce-range-max');
                if (rangeMin) rangeMin.value = rangeMin.min;
                if (rangeMax) rangeMax.value = rangeMax.max;
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

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    disable() {
        this.enabled = false;
    }

    enable() {
        this.enabled = true;
    }
}

window.J2CommerceFilters = J2CommerceFilters;
