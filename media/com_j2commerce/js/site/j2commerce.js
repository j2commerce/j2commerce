/**
 * J2Commerce Site JavaScript
 *
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 */

'use strict';

const J2Commerce = {
    baseUrl: '',

    /**
     * Initialize all J2Commerce functionality
     */
    init() {
        this.baseUrl = this.getBaseUrl();
        //this.initImageGallery();
        this.bindEvents();
        this.initColorOptionLabels();
        this.initRadioOptionLabels();
        this.initShippingSameAsBilling();
        this.initConfigurableDefaults();
        this.equalizeHeights();
        let _eqTimer;
        window.addEventListener('resize', () => {
            clearTimeout(_eqTimer);
            _eqTimer = setTimeout(() => this.equalizeHeights(), 150);
        });
    },

    /**
     * Get base URL for AJAX requests
     * @returns {string}
     */
    getBaseUrl() {
        if (window.j2commerceURL) return window.j2commerceURL;
        if (typeof Joomla !== 'undefined' && Joomla.getOptions) {
            const paths = Joomla.getOptions('system.paths');
            if (paths?.rootFull) return paths.rootFull.replace(/\/?$/, '/');
            if (paths?.root !== undefined) return window.location.origin + paths.root + '/';
        }
        const base = document.querySelector('base[href]');
        if (base) return base.href.replace(/\/?$/, '/');
        return '';
    },

    /**
     * Get CSRF token from Joomla
     * @returns {string}
     */
    getCsrfToken() {
        const tokenInput = document.querySelector('input[name^="csrf.token"], input[name][value="1"][name*="token"]');
        if (tokenInput) return tokenInput.name;
        if (typeof Joomla !== 'undefined' && Joomla.getOptions) {
            const token = Joomla.getOptions('csrf.token');
            if (token) return token;
        }
        return '';
    },

    // =========================================================================
    // CART FUNCTIONALITY
    // =========================================================================

    /**
     * Add item to cart via button click (simple products)
     * @param {HTMLElement} button - The add to cart button
     */
    async addToCartButton(button) {
        const productId = button.dataset.product_id || button.dataset.productId;
        if (!productId) return true;

        button.classList.remove('added');
        button.classList.add('loading');

        const data = new FormData();
        data.append('option', 'com_j2commerce');
        data.append('view', 'carts');
        data.append('task', 'addItem');
        data.append('ajax', '1');

        // Copy all data attributes to form data
        Object.entries(button.dataset).forEach(([key, value]) => {
            data.append(key, value);
        });

        this.dispatchEvent('addingToCart', { button, data });

        const href = button.getAttribute('href') || 'index.php';

        try {
            const response = await fetch(href, {
                method: 'POST',
                body: data,
                headers: { 'Cache-Control': 'no-cache' }
            });
            const json = await response.json();

            if (!json) return;

            if (json.error) {
                window.location = json.product_url;
                return;
            }

            if (json.success) {
                button.classList.remove('loading');
                button.classList.add('added');
                const complete = button.parentElement?.querySelector('.cart-action-complete');
                if (complete) complete.style.display = 'block';
                this.dispatchEvent('afterAddingToCart', { button, response: json, type: 'link' });
                this.dispatchEvent('cart:updated', { type: 'add' });
            }

            if (json.redirect) {
                window.location.href = json.redirect;
                return;
            }
        } catch (error) {
            console.error('Cart error:', error);
            button.classList.remove('loading');
        }
    },

    /**
     * Add item to cart via form submission
     * @param {HTMLFormElement} form - The add to cart form
     * @param {Event} e - The submit event
     */
    async addToCartForm(form, e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        const actionAlways = submitBtn?.dataset.cartActionAlways;
        const actionDone = submitBtn?.dataset.cartActionDone;
        const actionTimeout = parseInt(submitBtn?.dataset.cartActionTimeout || '0', 10);

        if (submitBtn) {
            if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = actionAlways;
            else submitBtn.value = actionAlways;
            submitBtn.disabled = true;
        }

        const formData = new FormData(form);
        formData.set('ajax', '1');
        const href = form.getAttribute('action') || 'index.php';

        this.dispatchEvent('addingToCart', { form, data: formData });

        try {
            const response = await fetch(href, {
                method: 'POST',
                body: formData,
                headers: { 'Cache-Control': 'no-cache' }
            });
            const json = await response.json();

            if (submitBtn) submitBtn.disabled = false;

            // Remove previous notifications
            form.querySelectorAll('.j2success, .j2warning, .j2attention, .j2information, .j2error').forEach(el => el.remove());
            document.querySelectorAll('.j2commerce-notification').forEach(el => el.style.display = 'none');

            if (json.error) {
                if (submitBtn) { if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = actionDone; else submitBtn.value = actionDone; }

                if (json.error.option) {
                    Object.entries(json.error.option).forEach(([key, msg]) => {
                        const optEl = form.querySelector(`#option-${key}`);
                        if (optEl) {
                            const span = document.createElement('span');
                            span.className = 'j2error';
                            span.textContent = msg;
                            optEl.insertAdjacentElement('afterend', span);
                        }
                    });
                }

                const notifications = form.querySelector('.j2commerce-notifications');
                if (json.error.stock && notifications) {
                    notifications.textContent = '';
                    const stockErr = document.createElement('span');
                    stockErr.className = 'j2error';
                    stockErr.textContent = json.error.stock;
                    notifications.appendChild(stockErr);
                }
                if (json.error.general && notifications) {
                    notifications.textContent = '';
                    const generalErr = document.createElement('span');
                    generalErr.className = 'j2error';
                    generalErr.textContent = json.error.general;
                    notifications.appendChild(generalErr);
                }
                if (json.error.product && notifications) {
                    const span = document.createElement('span');
                    span.className = 'j2error';
                    span.textContent = json.error.product;
                    notifications.insertAdjacentElement('afterend', span);
                }
                return;
            }

            if (json.success) {
                setTimeout(() => {
                    if (submitBtn) { if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = actionDone; else submitBtn.value = actionDone; }
                    const complete = form.querySelector('.cart-action-complete');
                    if (complete) {
                        complete.style.opacity = '0';
                        complete.style.display = 'block';
                        complete.style.transition = 'opacity 0.5s';
                        requestAnimationFrame(() => complete.style.opacity = '1');
                    }
                }, actionTimeout);

                this.dispatchEvent('afterAddingToCart', { form, response: json, type: 'normal' });
                this.dispatchEvent('cart:updated', { type: 'add' });
            }

            if (json.redirect) {
                window.location.href = json.redirect;
                return;
            }
        } catch (error) {
            console.error('Cart form error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                if (submitBtn.tagName === 'BUTTON') submitBtn.textContent = actionDone;
                else submitBtn.value = actionDone;
            }
        }
    },

    // =========================================================================
    // AJAX TASK EXECUTION
    // =========================================================================

    /**
     * Execute a generic AJAX task
     * @param {string} url - The URL to call
     * @param {string} containerId - Container element ID for response
     * @param {HTMLFormElement|null} form - Optional form element
     * @param {string} msg - Message to display
     * @param {Object} formdata - Additional form data
     */
    async doTask(url, containerId, form, msg, formdata) {
        const container = document.getElementById(containerId);
        if (!url) return;

        const loaderHtml = `<span class="wait"><img src="${this.baseUrl}media/com_j2commerce/images/loader.gif" alt="" /></span>`;

        if (container) {
            container.insertAdjacentHTML('beforebegin', loaderHtml);
        }

        const params = new URLSearchParams(formdata || {});

        try {
            const response = await fetch(`${url}&${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Content-Type': 'application/json; charset=utf-8'
                }
            });
            const json = await response.json();

            document.querySelectorAll('.wait').forEach(el => el.remove());

            if (container && json.msg) {
                container.textContent = json.msg;
            }
        } catch (error) {
            console.error('Task error:', error);
            document.querySelectorAll('.wait').forEach(el => el.remove());
        }
    },

    // =========================================================================
    // SHIPPING FUNCTIONALITY
    // =========================================================================

    /**
     * Set shipping rate values in hidden form fields
     * @param {string} name - Shipping method name
     * @param {number} price - Shipping price
     * @param {number} tax - Shipping tax
     * @param {string} extra - Extra shipping data
     * @param {string} code - Shipping code
     * @param {string} combined - Combined value
     * @param {string} shipElement - Shipping element
     * @param {string} cssId - CSS class ID for display
     */
    setShippingRate(name, price, tax, extra, code, combined, shipElement, cssId) {
        const fields = {
            'shipping_name': name,
            'shipping_code': code,
            'shipping_price': price,
            'shipping_tax': tax,
            'shipping_extra': extra
        };

        Object.entries(fields).forEach(([fieldName, value]) => {
            const input = document.querySelector(`input[type="hidden"][name="${fieldName}"]`);
            if (input) input.value = value;
        });

        // Hide all shipping elements and show selected one
        const wrapper = document.getElementById('onCheckoutShipping_wrapper');
        if (wrapper) {
            wrapper.querySelectorAll('.shipping_element').forEach(el => el.style.display = 'none');
            const selected = wrapper.querySelector(`.${cssId}_select_text`);
            if (selected) selected.style.display = 'block';
        }
    },

    /**
     * Initialize shipping same as billing checkbox
     */
    initShippingSameAsBilling() {
        const checkbox = document.getElementById('j2commerce_shipping_make_same');
        if (!checkbox) return;

        const section = document.getElementById('j2commerce_shipping_section');
        if (!section) return;

        const toggleSection = () => {
            if (checkbox.checked) {
                section.style.display = 'none';
                section.querySelectorAll('.input-label, .input-text').forEach(el => {
                    el.classList.remove('required');
                });
            } else {
                section.style.display = 'block';
            }
        };

        checkbox.addEventListener('change', toggleSection);
        toggleSection();
    },

    // =========================================================================
    // PRODUCT FILTER/VARIANT FUNCTIONALITY
    // =========================================================================

    /**
     * Get matching variant ID from selected options
     * @param {Object} variants - Map of variant combinations to IDs
     * @param {string} selected - Comma-separated selected option values
     * @returns {string|undefined}
     */
    getMatchingVariant(variants, selected) {
        for (const [id, combination] of Object.entries(variants)) {
            if (combination === selected) return id;
        }
        return undefined;
    },

    /**
     * Handle AJAX filter for product options
     * @param {string} povId - Product option value ID
     * @param {number} productId - Product ID
     * @param {string} poId - Product option ID
     * @param {string} elementId - Triggering element ID
     */
    async doAjaxFilter(povId, productId, poId, elementId) {
        // Strip leading # — templates pass CSS selectors but getElementById expects bare IDs
        const cleanId = elementId.startsWith('#') ? elementId.slice(1) : elementId;
        const element = document.getElementById(cleanId) || document.querySelector(`[id="${cleanId}"]`);
        if (!element) return;

        const form = element.closest('form');
        if (!form || (form.dataset.product_id ?? form.dataset.productId) != productId) return;

        // Clear child options if present and restore any hidden standalone options
        const childContainer = document.getElementById(`ChildOptions${poId}`)
            || document.getElementById(`child-ChildOptions${poId}`);
        if (povId === '' || childContainer) {
            if (childContainer) childContainer.innerHTML = '';
        }
        const optionsContainer = form?.querySelector('[id^="configurable-options-"]')
            || element.closest('.options') || element.closest('.j2commerce-product-options');
        if (optionsContainer) {
            optionsContainer.querySelectorAll(`[data-hidden-by-parent="${poId}"]`).forEach(el => {
                el.classList.remove('d-none');
                el.classList.remove('uk-hidden');
                el.removeAttribute('data-hidden-by-parent');
            });
        }

        const formData = new FormData(form);
        const values = {};

        // Convert FormData to object, excluding task and view
        for (const [key, value] of formData.entries()) {
            if (key !== 'task' && key !== 'view') {
                values[key] = value;
            }
        }
        values.product_id = productId;

        // Handle advanced variable products
        if (form.dataset.product_type === 'advancedvariable') {
            const csv = [];
            form.querySelectorAll('input[type="radio"]:checked, select').forEach(el => {
                if (el.value && el.dataset.isVariant) {
                    csv.push(el.value);
                }
            });

            const sortedCsv = csv.sort((a, b) => a - b);
            const selectedVariant = sortedCsv.join(',');
            const variants = form.dataset.product_variants ? JSON.parse(form.dataset.product_variants) : {};
            const variantId = this.getMatchingVariant(variants, selectedVariant);

            const variantInput = form.querySelector('input[name="variant_id"]');
            if (variantInput) variantInput.value = variantId || '';
            values.variant_id = variantId;
        }

        this.dispatchEvent('beforeAjaxFilter', { form, values });

        if (!this.baseUrl) this.baseUrl = this.getBaseUrl();

        if (element) {
            element.insertAdjacentHTML('beforeend',
                `<span class="wait">&nbsp;<img src="${this.baseUrl}media/com_j2commerce/images/loader.gif" alt="" /></span>`);
        }

        const params = new URLSearchParams(values);
        const url = `${this.baseUrl}index.php?option=com_j2commerce&view=product&task=product.update&po_id=${poId}&pov_id=${povId}&product_id=${productId}`;

        try {
            const response = await fetch(`${url}&${params.toString()}`, {
                method: 'GET',
                headers: { 'Cache-Control': 'no-cache' }
            });
            const json = await response.json();

            document.querySelectorAll('.wait').forEach(el => el.remove());

            this.updateProductDisplay(productId, json, form);

            // Update child options if present
            if (json.optionhtml) {
                const childOpts = document.getElementById(`ChildOptions${poId}`)
                    || document.getElementById(`child-ChildOptions${poId}`);
                if (childOpts) {
                    childOpts.innerHTML = json.optionhtml;
                    this.initConfigCheckboxes(childOpts);
                    this.initColorOptionLabels();
                    this.triggerChildDefaults(childOpts, productId);
                }
            }

            // Hide standalone options that are now shown as injected children
            const container = form?.querySelector('[id^="configurable-options-"]')
                || element.closest('.options') || element.closest('.j2commerce-product-options');
            if (container && json.child_option_ids?.length) {
                for (const childPoId of json.child_option_ids) {
                    const standalone = container.querySelector(`#option-${childPoId}`);
                    if (standalone && !standalone.closest('[id^="ChildOptions"]')) {
                        standalone.classList.add('d-none');
                        standalone.setAttribute('data-hidden-by-parent', poId);
                    }
                    const childPlaceholder = container.querySelector(`#ChildOptions${childPoId}`);
                    if (childPlaceholder && !childPlaceholder.closest('[id^="ChildOptions"]')) {
                        childPlaceholder.classList.add('d-none');
                        childPlaceholder.setAttribute('data-hidden-by-parent', poId);
                    }
                }
            }

            this.dispatchEvent('afterAjaxFilterResponse', { productId, response: json });
        } catch (error) {
            console.error('AJAX filter error:', error);
            document.querySelectorAll('.wait').forEach(el => el.remove());
        }
    },

    // Attach change listeners to configurable checkbox options injected via innerHTML
    initConfigCheckboxes(container) {
        container.querySelectorAll('[data-config-checkbox="1"]').forEach(div => {
            const productId = div.dataset.productId;
            const poId = div.dataset.poId;
            div.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', () => {
                    const checked = div.querySelector('input[type="checkbox"]:checked');
                    doAjaxFilter(checked ? checked.value : '', productId, poId, '#' + div.id);
                });
            });
        });
    },

    // Auto-fire doAjaxFilter for any pre-selected defaults inside a child options container
    triggerChildDefaults(container, productId) {
        container.querySelectorAll('.option').forEach(optDiv => {
            const poId = optDiv.id?.replace(/^(child-)?option-/, '');
            if (!poId) return;

            const select = optDiv.querySelector('select');
            if (select?.value) {
                this.doAjaxFilter(select.value, productId, poId, '#' + optDiv.id);
                return;
            }

            const radio = optDiv.querySelector('input[type="radio"]:checked');
            if (radio?.value) {
                this.doAjaxFilter(radio.value, productId, poId, '#' + optDiv.id);
            }
        });
    },

    // On page load, fire doAjaxFilter for configurable parent options with a pre-selected default
    initConfigurableDefaults() {
        document.querySelectorAll('[id^="configurable-options-"]').forEach(container => {
            const productId = container.id.replace('configurable-options-', '');

            container.querySelectorAll('.option').forEach(optDiv => {
                const poId = optDiv.id?.replace(/^(child-)?option-/, '');
                if (!poId) return;

                const select = optDiv.querySelector('select');
                if (select?.value) {
                    this.doAjaxFilter(select.value, productId, poId, '#' + optDiv.id);
                    return;
                }

                const radio = optDiv.querySelector('input[type="radio"]:checked');
                if (radio?.value) {
                    this.doAjaxFilter(radio.value, productId, poId, '#' + optDiv.id);
                }
            });
        });
    },

    /**
     * Handle AJAX price update
     * @param {number} productId - Product ID
     * @param {string} elementId - Triggering element ID
     */
    async doAjaxPrice(productId, elementId) {
        const element = document.getElementById(elementId) || document.querySelector(`[id="${elementId}"]`);
        if (!element) return;

        const form = element.closest('form');
        if (!form || (form.dataset.product_id ?? form.dataset.productId) != productId) return;

        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        const formData = new FormData(form);
        const values = {};

        for (const [key, value] of formData.entries()) {
            if (key !== 'task' && key !== 'view') {
                values[key] = value;
            }
        }
        values.product_id = productId;

        // Handle variable product types
        const productType = form.dataset.product_type;
        if (['variable', 'advancedvariable', 'variablesubscriptionproduct'].includes(productType)) {
            const csv = [];

            if (productType === 'advancedvariable') {
                form.querySelectorAll('input[type="radio"]:checked, select').forEach(el => {
                    if (el.value && el.dataset.isVariant) {
                        csv.push(el.value);
                    }
                });
            } else {
                form.querySelectorAll('input[type="radio"]:checked, select').forEach(el => {
                    if (el.value) csv.push(el.value);
                });
            }

            const sortedCsv = csv.sort((a, b) => a - b);
            const selectedVariant = sortedCsv.join(',');
            const variants = form.dataset.product_variants ? JSON.parse(form.dataset.product_variants) : {};
            const variantId = this.getMatchingVariant(variants, selectedVariant);

            const variantInput = form.querySelector('input[name="variant_id"]');
            if (variantInput) variantInput.value = variantId || '';
            values.variant_id = variantId;
        }

        this.dispatchEvent('beforeAjaxPrice', { form, values });

        // Clear previous errors
        const notifications = document.querySelector('.j2commerce-notifications .j2error');
        if (notifications) notifications.innerHTML = '';

        const params = new URLSearchParams(values);
        if (!this.baseUrl) this.baseUrl = this.getBaseUrl();
        const url = `${this.baseUrl}index.php?option=com_j2commerce&view=product&task=product.update`;

        try {
            const response = await fetch(`${url}&${params.toString()}`, {
                method: 'GET',
                headers: { 'Cache-Control': 'no-cache' }
            });
            const json = await response.json();

            if (submitBtn) submitBtn.disabled = false;

            this.updateProductDisplay(productId, json, form);

            this.dispatchEvent('afterAjaxFilter', { productId, response: json });
            this.dispatchEvent('afterAjaxPrice', { productId, response: json });
        } catch (error) {
            console.error('AJAX price error:', error);
            if (submitBtn) submitBtn.disabled = false;
        }
    },

    /**
     * Update product display with AJAX response data
     * @param {number} productId - Product ID
     * @param {Object} response - AJAX response data
     * @param {HTMLFormElement} form - The product form
     */
    updateProductDisplay(productId, response, form) {
        const product = document.querySelector(`.j2commerce-product-${productId}`) || document.querySelector(`.product-${productId}`);
        if (!product || response.error) return;

        // SKU — detail uses .sku, list uses .sku-value
        if (response.sku) {
            const sku = product.querySelector('.sku-value') || product.querySelector('.sku');
            if (sku) sku.textContent = response.sku;
        }

        // Pricing — handle both standard (.base-price/.sale-price) and flexiprice (.j2commerce-flexiprice) layouts
        if (response.pricing?.price) {
            const basePrice = product.querySelector('.base-price');
            const salePrice = product.querySelector('.sale-price');
            const flexiPrice = product.querySelector('.j2commerce-flexiprice');

            if (basePrice || salePrice) {
                // Standard price layout (detail view, simple products)
                if (basePrice && response.pricing.base_price) {
                    basePrice.innerHTML = response.pricing.base_price;
                    if (response.pricing.class) {
                        basePrice.style.display = response.pricing.class === 'show' ? 'block' : 'none';
                    }
                }
                if (salePrice) salePrice.innerHTML = response.pricing.price;
            } else if (flexiPrice) {
                // Flexiprice layout (list views) — replace range/from with specific variant price
                let html = '';
                if (response.pricing.base_price && response.pricing.class === 'show') {
                    html += `<del class="base-price text-body-tertiary">${response.pricing.base_price}</del> `;
                }
                html += `<span class="sale-price">${response.pricing.price}</span>`;
                flexiPrice.innerHTML = html;
            }
        }

        // After display price
        if (response.afterDisplayPrice) {
            const adp = product.querySelector('.afterDisplayPrice');
            if (adp) adp.innerHTML = response.afterDisplayPrice;
        }

        // Quantity
        if (response.quantity) {
            const qtyInput = product.querySelector('input[name="product_qty"]');
            if (qtyInput) {
                qtyInput.value = response.quantity;
                const productType = form?.dataset.product_type;
                if (['variable', 'advancedvariable', 'variablesubscriptionproduct'].includes(productType)) {
                    qtyInput.setAttribute('value', response.quantity);
                }
                this.updateCountInputStates(qtyInput);
            }
        }

        // Dimensions
        if (response.dimensions) {
            const dims = product.querySelector('.product-dimensions');
            if (dims) dims.innerHTML = response.dimensions;
        }

        // Weight
        if (response.weight) {
            const weight = product.querySelector('.product-weight');
            if (weight) weight.innerHTML = response.weight;
        }

        // Main image — skip legacy swap if variant gallery handled by Swiper
        if (response.main_image && !response.variant_gallery?.length) {
            const mainImages = [
                document.querySelector(`.j2commerce-product-main-image-${productId}`),
                product.querySelector('.j2commerce-mainimage .j2commerce-img-responsive'),
                product.querySelector('.j2commerce-product-additional-images .additional-mainimage')
            ];
            mainImages.forEach(img => {
                if (img) img.src = response.main_image;
            });
        }

        // Variant gallery swap (Swiper)
        if (typeof this.swapGalleryImages === 'function') {
            this.swapGalleryImages(productId, response.variant_gallery);
        }

        // Discount text
        const discountEl = product.querySelector('.discount-percentage');
        if (discountEl) {
            if (response.pricing?.discount_text) {
                discountEl.innerHTML = response.pricing.discount_text;
                discountEl.classList.remove('no-discount');
            } else {
                discountEl.classList.add('no-discount');
            }
        }

        // Stock status
        if (typeof response.stock_status !== 'undefined') {
            const stockContainer = product.querySelector('.product-stock-container');
            if (stockContainer) {
                const statusClass = response.availability === 1 ? 'instock' : 'outofstock';
                stockContainer.innerHTML = `<span class="${statusClass}">${response.stock_status}</span>`;
            }
        }

        // Subscription duration text (for variablesubscriptionproduct variant switches)
        if (typeof response.subscription_duration !== 'undefined') {
            const existing = product.querySelector('.subscriptionproducts');
            if (existing) {
                existing.outerHTML = response.subscription_duration || '';
            } else if (response.subscription_duration) {
                const priceContainer = product.querySelector('.j2commerce-product-price-container');
                if (priceContainer) {
                    priceContainer.insertAdjacentHTML('afterend', response.subscription_duration);
                }
            }
        }

        // Subscription sign-up fee (for variablesubscriptionproduct variant switches)
        if (typeof response.subscription_signup_fee_html !== 'undefined') {
            const existing = product.querySelector('.subscription-signup-fee');
            if (existing) {
                existing.outerHTML = response.subscription_signup_fee_html || '';
            } else if (response.subscription_signup_fee_html) {
                const priceContainer = product.querySelector('.j2commerce-product-price-container');
                if (priceContainer) {
                    priceContainer.insertAdjacentHTML('afterend', response.subscription_signup_fee_html);
                }
            }
        }

        // Variant ID — always update from server response as authoritative fallback
        if (response.variant_id) {
            const vi = form?.querySelector('input[name="variant_id"]');
            if (vi) vi.value = response.variant_id;
        }
    },



    // =========================================================================
    // VARIANT GALLERY SWAP (Swiper)
    // =========================================================================

    _escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    swapGalleryImages(productId, variantGallery) {
        const galleryEl = document.getElementById('product-gallery-' + productId);
        if (!galleryEl) return;

        const mainEl = galleryEl.querySelector('.product-gallery-main');
        const thumbsEl = galleryEl.querySelector('.product-gallery-thumbs');
        if (!mainEl) return;

        const enableZoom = mainEl.dataset.enableZoom === '1';

        // Case C: No variant images — restore original gallery
        if (!variantGallery || variantGallery.length === 0) {
            this.restoreOriginalGallery(mainEl, thumbsEl, enableZoom);
            return;
        }

        // Case A: Single variant image — replace only first slide
        if (variantGallery.length === 1) {
            this.replaceFirstSlide(mainEl, thumbsEl, variantGallery[0], enableZoom);
            return;
        }

        // Case B: Multiple variant images — replace entire gallery
        this.replaceAllSlides(mainEl, thumbsEl, variantGallery, enableZoom);
    },

    replaceFirstSlide(mainEl, thumbsEl, image, enableZoom) {
        const firstMainImg = mainEl.querySelector('.swiper-slide img');
        if (firstMainImg) {
            firstMainImg.src = image.src;
            if (image.alt) firstMainImg.alt = image.alt;
            if (enableZoom && typeof Zoom !== 'undefined') new Zoom(firstMainImg);
        }

        if (thumbsEl) {
            const firstThumb = thumbsEl.querySelector('.swiper-slide img');
            if (firstThumb) {
                firstThumb.src = image.thumb_src;
                if (image.alt) firstThumb.alt = image.alt;
            }
        }

        mainEl._swiper?.slideTo(0);
        thumbsEl?._swiper?.slideTo(0);
    },

    replaceAllSlides(mainEl, thumbsEl, images, enableZoom) {
        const mainSwiper = mainEl._swiper;
        const thumbSwiper = thumbsEl?._swiper;

        if (mainSwiper) mainSwiper.destroy(true, true);
        if (thumbSwiper) thumbSwiper.destroy(true, true);

        const zoomIcon = enableZoom
            ? '<span class="product-zoom-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>'
            : '';

        mainEl.querySelector('.swiper-wrapper').innerHTML = images.map(img =>
            `<div class="swiper-slide"><img src="${this._escAttr(img.src)}" alt="${this._escAttr(img.alt || '')}" class="j2commerce-product-main-image" ${enableZoom ? 'data-action="zoom"' : ''} loading="lazy" />${zoomIcon}</div>`
        ).join('');

        if (thumbsEl) {
            thumbsEl.querySelector('.swiper-wrapper').innerHTML = images.map(img =>
                `<div class="swiper-slide"><img src="${this._escAttr(img.thumb_src)}" alt="${this._escAttr(img.alt || '')}" class="product-thumb" width="100" height="100" loading="lazy" /></div>`
            ).join('');
        }

        const hasMultiple = images.length > 1;
        const prevBtn = mainEl.querySelector('.swiper-button-prev');
        const nextBtn = mainEl.querySelector('.swiper-button-next');
        if (prevBtn) prevBtn.style.display = hasMultiple ? '' : 'none';
        if (nextBtn) nextBtn.style.display = hasMultiple ? '' : 'none';
        if (thumbsEl) thumbsEl.style.display = hasMultiple ? '' : 'none';

        this.reinitSwipers(mainEl, thumbsEl, enableZoom);
    },

    restoreOriginalGallery(mainEl, thumbsEl, enableZoom) {
        const originalMain = mainEl.dataset.originalSlides;
        if (!originalMain) return;

        const mainSwiper = mainEl._swiper;
        const thumbSwiper = thumbsEl?._swiper;

        if (mainSwiper) mainSwiper.destroy(true, true);
        if (thumbSwiper) thumbSwiper.destroy(true, true);

        mainEl.querySelector('.swiper-wrapper').innerHTML = originalMain;
        if (thumbsEl?.dataset.originalSlides) {
            thumbsEl.querySelector('.swiper-wrapper').innerHTML = thumbsEl.dataset.originalSlides;
        }

        const slideCount = mainEl.querySelectorAll('.swiper-slide').length;
        const prevBtn = mainEl.querySelector('.swiper-button-prev');
        const nextBtn = mainEl.querySelector('.swiper-button-next');
        if (prevBtn) prevBtn.style.display = slideCount > 1 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = slideCount > 1 ? '' : 'none';
        if (thumbsEl) thumbsEl.style.display = slideCount > 1 ? '' : 'none';

        this.reinitSwipers(mainEl, thumbsEl, enableZoom);
    },

    reinitSwipers(mainEl, thumbsEl, enableZoom) {
        if (typeof Swiper === 'undefined') return;

        const galleryId = mainEl.closest('.product-gallery')?.id;

        let newThumbSwiper = null;
        if (thumbsEl && thumbsEl.querySelectorAll('.swiper-slide').length > 1) {
            newThumbSwiper = new Swiper('#' + thumbsEl.id, {
                spaceBetween: 12,
                slidesPerView: 5,
                freeMode: true,
                watchSlidesProgress: true,
                breakpoints: {
                    0: { slidesPerView: 4, spaceBetween: 8 },
                    768: { slidesPerView: 5, spaceBetween: 12 }
                }
            });
            thumbsEl._swiper = newThumbSwiper;
        }

        const newMainSwiper = new Swiper('#' + mainEl.id, {
            spaceBetween: 0,
            navigation: galleryId ? {
                nextEl: '#' + galleryId + ' .swiper-button-next',
                prevEl: '#' + galleryId + ' .swiper-button-prev'
            } : undefined,
            thumbs: newThumbSwiper ? { swiper: newThumbSwiper } : undefined
        });
        mainEl._swiper = newMainSwiper;

        if (enableZoom && typeof Zoom !== 'undefined') {
            mainEl.querySelectorAll('[data-action="zoom"]').forEach(img => new Zoom(img));
        }
    },

    // =========================================================================
    // FILTER FUNCTIONALITY
    // =========================================================================

    /**
     * Submit product filters form
     */
    submitFilters() {
        const loading = document.getElementById('j2commerce-product-loading');
        if (loading) loading.style.display = 'block';

        const form = document.getElementById('productsideFilters');
        if (form) form.submit();
    },

    /**
     * Reset brand filter
     * @param {string} inputId - Optional specific input ID to reset
     */
    resetBrandFilter(inputId) {
        const form = document.getElementById('productsideFilters');
        if (inputId && form) {
            const input = form.querySelector(`#${inputId}`);
            if (input) input.checked = false;
        } else {
            document.querySelectorAll('.j2commerce-brand-checkboxes').forEach(cb => {
                cb.checked = false;
            });
        }
    },

    /**
     * Reset vendor filter
     * @param {string} inputId - Optional specific input ID to reset
     */
    resetVendorFilter(inputId) {
        const form = document.getElementById('productsideFilters');
        if (inputId && form) {
            const input = form.querySelector(`#${inputId}`);
            if (input) input.checked = false;
        } else {
            document.querySelectorAll('.j2commerce-vendor-checkboxes').forEach(cb => {
                cb.checked = false;
            });
        }
    },

    /**
     * Reset product filter by class or input ID
     * @param {string} filterClass - Class name for filter checkboxes
     * @param {string} inputId - Optional specific input ID
     */
    resetProductFilter(filterClass, inputId) {
        if (filterClass) {
            document.querySelectorAll(`.${filterClass}`).forEach(cb => {
                cb.checked = false;
            });
        } else if (inputId) {
            const form = document.getElementById('productsideFilters');
            if (form) {
                const input = form.querySelector(`#${inputId}`);
                if (input) input.checked = false;
            }
        }
    },

    /**
     * Toggle price filter visibility
     */
    togglePriceFilter() {
        this.toggleElements([
            '#price-filter-icon-plus',
            '#price-filter-icon-minus',
            '#j2commerce-slider-range',
            '#j2commerce-slider-range-box'
        ]);
    },

    /**
     * Toggle category filter visibility
     */
    toggleCategoryFilter() {
        this.toggleElements([
            '#cat-filter-icon-plus',
            '#cat-filter-icon-minus',
            '#j2commerce_category'
        ]);
    },

    /**
     * Toggle brand filter visibility
     */
    toggleBrandFilter() {
        this.toggleElements([
            '#brand-filter-icon-plus',
            '#brand-filter-icon-minus',
            '#j2commerce-brand-filter-container'
        ]);
    },

    /**
     * Toggle vendor filter visibility
     */
    toggleVendorFilter() {
        this.toggleElements([
            '#vendor-filter-icon-plus',
            '#vendor-filter-icon-minus',
            '#j2commerce-vendor-filter-container'
        ]);
    },

    /**
     * Toggle product filter visibility by ID
     * @param {string} id - Filter ID suffix
     */
    toggleProductFilterById(id) {
        this.toggleElements([
            `#pf-filter-icon-plus-${id}`,
            `#pf-filter-icon-minus-${id}`,
            `#j2commerce-pf-filter-${id}`
        ]);
    },

    /**
     * Toggle visibility of multiple elements
     * @param {string[]} selectors - Array of CSS selectors
     */
    toggleElements(selectors) {
        selectors.forEach(selector => {
            const el = document.querySelector(selector);
            if (el) {
                el.style.display = el.style.display === 'none' ? 'block' : 'none';
            }
        });
    },

    // =========================================================================
    // EVENT HANDLING
    // =========================================================================

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Add to cart button clicks
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.j2commerce_add_to_cart_button');
            if (btn) {
                e.preventDefault();
                this.addToCartButton(btn);
            }
        });

        // Add to cart form submissions (event delegation for AJAX-replaced content)
        document.addEventListener('submit', (e) => {
            const form = e.target.closest('.j2commerce-addtocart-form');
            if (form) {
                this.addToCartForm(form, e);
            }
        });

        // Additional image clicks/hovers
        document.addEventListener('click', (e) => {
            const thumbnail = e.target.closest('.j2commerce-item-additionalimage-preview');
            if (!thumbnail) return;

            e.preventDefault();
            const productId = thumbnail.dataset.productId;
            const enableZoom = thumbnail.dataset.enableZoom === '1';
            this.setMainPreview(thumbnail.id, productId, enableZoom);
        });

        document.addEventListener('mouseover', (e) => {
            const thumbnail = e.target.closest('.j2commerce-item-additionalimage-preview');
            if (!thumbnail) return;

            const productId = thumbnail.dataset.productId;
            const enableZoom = thumbnail.dataset.enableZoom === '1';
            this.setMainPreview(thumbnail.id, productId, enableZoom);
        });

        // Count input: increment button
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.count-input:not(.j2commerce-qty-controls) [data-increment]');
            if (!btn) return;
            const input = btn.parentNode.querySelector('input[type="number"], input[type="text"]');
            if (!input) return;
            const maxValue = parseInt(input.getAttribute('max')) || Infinity;
            if (parseInt(input.value) < maxValue) {
                input.value = parseInt(input.value) + 1;
                this.updateCountInputStates(input);
            }
        });

        // Count input: decrement button
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.count-input:not(.j2commerce-qty-controls) [data-decrement]');
            if (!btn) return;
            const input = btn.parentNode.querySelector('input[type="number"], input[type="text"]');
            if (!input) return;
            const minValue = parseInt(input.getAttribute('min')) || 1;
            if (parseInt(input.value) > minValue) {
                input.value = parseInt(input.value) - 1;
                this.updateCountInputStates(input);
            }
        });

        // Color option label update
        document.addEventListener('change', (e) => {
            const radio = e.target.closest('.j2commerce-color-options input[type="radio"]');
            if (!radio) return;
            const container = radio.closest('.j2commerce-color-options');
            const label = radio.nextElementSibling;
            const target = document.querySelector(container.dataset.bindedLabel);
            if (label && target) target.textContent = label.dataset.label || '';
        });

        // Radio option label update
        document.addEventListener('change', (e) => {
            const radio = e.target.closest('.j2commerce-radio-options input[type="radio"]');
            if (!radio) return;
            const container = radio.closest('.j2commerce-radio-options');
            const label = radio.nextElementSibling;
            const target = document.querySelector(container.dataset.bindedLabel);
            if (label && target) target.textContent = label.dataset.label || '';
        });
    },

    initColorOptionLabels() {
        document.querySelectorAll('.j2commerce-color-options').forEach(container => {
            const checked = container.querySelector('input[type="radio"]:checked');
            if (!checked) return;
            const label = checked.nextElementSibling;
            const target = document.querySelector(container.dataset.bindedLabel);
            if (label && target) target.textContent = label.dataset.label || '';
        });
    },

    initRadioOptionLabels() {
        document.querySelectorAll('.j2commerce-radio-options').forEach(container => {
            const checked = container.querySelector('input[type="radio"]:checked');
            if (!checked) return;
            const label = checked.nextElementSibling;
            const target = document.querySelector(container.dataset.bindedLabel);
            if (label && target) target.textContent = label.dataset.label || '';
        });
    },

    updateCountInputStates(input) {
        const parent = input.closest('.count-input');
        if (!parent) return;
        const decrementBtn = parent.querySelector('[data-decrement]');
        const incrementBtn = parent.querySelector('[data-increment]');
        const minValue = parseInt(input.getAttribute('min')) || 1;
        const maxValue = parseInt(input.getAttribute('max')) || Infinity;
        if (decrementBtn) decrementBtn.disabled = parseInt(input.value) <= minValue;
        if (incrementBtn) incrementBtn.disabled = parseInt(input.value) >= maxValue;
    },

    /**
     * Dispatch a custom event
     * @param {string} eventName - Event name
     * @param {Object} detail - Event detail data
     */
    dispatchEvent(eventName, detail = {}) {
        const event = new CustomEvent(`j2commerce:${eventName}`, {
            bubbles: true,
            detail
        });
        document.dispatchEvent(event);

        // Also dispatch with legacy naming for backwards compatibility
        const legacyName = eventName.replace(/([A-Z])/g, '_$1').toLowerCase();
        const legacyEvent = new CustomEvent(legacyName, { bubbles: true, detail });
        document.body.dispatchEvent(legacyEvent);
    },

    equalizeHeights() {
        const groups = {};
        document.querySelectorAll('[data-equal-height]').forEach(el => {
            el.style.minHeight = '';
            const key = el.dataset.equalHeight;
            (groups[key] = groups[key] || []).push(el);
        });
        Object.values(groups).forEach(els => {
            const max = Math.max(...els.map(el => el.offsetHeight));
            els.forEach(el => el.style.minHeight = max + 'px');
        });
    }
};

// =========================================================================
// GLOBAL FUNCTIONS (Backwards Compatibility)
// =========================================================================


function getCategoryFilterToggle() {
    J2Commerce.toggleCategoryFilter();
}

function getBrandFilterToggle() {
    J2Commerce.toggleBrandFilter();
}

function getVendorFilterToggle() {
    J2Commerce.toggleVendorFilter();
}

function getPFFilterToggle(id) {
    J2Commerce.toggleProductFilterById(id);
}

function get_matching_variant(variants, selected) {
    return J2Commerce.getMatchingVariant(variants, selected);
}

// =========================================================================
// INITIALIZATION
// =========================================================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => J2Commerce.init());
} else {
    J2Commerce.init();
}

if (typeof window !== 'undefined') {
    window.J2Commerce = J2Commerce;
}
