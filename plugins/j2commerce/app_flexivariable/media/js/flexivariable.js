/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppFlexivariable
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

const J2CommerceFlexivariable = {
    /**
     * Base URL for AJAX requests
     */
    baseUrl: '',

    /**
     * Initialize the module
     */
    init: function() {
        this.baseUrl = Joomla.getOptions('system.paths')?.root || '';
        this.bindEvents();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-flexivariable-action]');
            if (!target) return;

            const action = target.dataset.flexivariableAction;
            const variantId = target.dataset.variantId;
            const productId = target.dataset.productId;

            switch (action) {
                case 'delete':
                    this.deleteVariant(parseInt(variantId, 10));
                    break;
                case 'setDefault':
                    this.setDefault(parseInt(variantId, 10), 'setDefault', parseInt(productId, 10));
                    break;
                case 'unsetDefault':
                    this.setDefault(parseInt(variantId, 10), 'unsetDefault', parseInt(productId, 10));
                    break;
            }
        });
    },

    /**
     * Perform AJAX price update for flexivariable products
     *
     * @param {number} productId - The product ID
     * @param {string|Element} elementOrSelector - Element or selector for the triggering element
     */
    doAjaxPrice: async function(productId, elementOrSelector) {
        const element = typeof elementOrSelector === 'string'
            ? document.querySelector(elementOrSelector)
            : elementOrSelector;

        if (!element) return;

        const form = element.closest('form');
        if (!form) return;

        // Sanity check - ensure we're on the right product form
        if (form.dataset.product_id && form.dataset.product_id != productId) return;

        const formData = new FormData(form);

        // Remove unnecessary fields
        formData.delete('task');
        formData.delete('view');

        // Add product ID
        formData.append('product_id', productId);

        // Clear previous errors
        document.querySelectorAll('.j2error').forEach(el => el.remove());

        // Trigger before event
        document.body.dispatchEvent(new CustomEvent('j2commerce:before_doAjaxPrice', {
            bubbles: true,
            detail: { form, formData }
        }));

        try {
            const response = await fetch(
                this.baseUrl + '/index.php?option=com_j2commerce&view=product&task=product.update&format=json',
                {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            const productContainer = document.querySelector('.j2commerce-product-' + productId) || document.querySelector('.product-' + productId);

            if (productContainer && !data.error) {
                this.updateProductDisplay(productContainer, data, productId);

                // Trigger after events
                document.body.dispatchEvent(new CustomEvent('j2commerce:after_doAjaxFilter', {
                    bubbles: true,
                    detail: { productContainer, response: data }
                }));

                document.body.dispatchEvent(new CustomEvent('j2commerce:after_doAjaxPrice', {
                    bubbles: true,
                    detail: { productContainer, response: data }
                }));
            } else if (data.error) {
                const optionsContainer = document.getElementById('variable-options-' + productId);
                if (optionsContainer) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'j2error alert alert-danger';
                    errorDiv.textContent = data.error;
                    optionsContainer.insertAdjacentElement('afterend', errorDiv);
                }
            }
        } catch (error) {
            console.error('Flexivariable AJAX error:', error);
        }
    },

    /**
     * Update the product display with response data
     *
     * @param {Element} productContainer - The product container element
     * @param {Object} response - The AJAX response data
     * @param {number} productId - The product ID
     */
    updateProductDisplay: function(productContainer, response, productId) {
        // Update SKU
        if (response.sku) {
            const skuEl = productContainer.querySelector('.sku-value');
            if (skuEl) skuEl.innerHTML = response.sku;
        }

        // Update pricing — handle both standard (.base-price/.sale-price) and flexiprice (.j2commerce-flexiprice) layouts
        if (response.pricing) {
            const basePriceEl = productContainer.querySelector('.base-price');
            const salePriceEl = productContainer.querySelector('.sale-price');
            const flexiPriceEl = productContainer.querySelector('.j2commerce-flexiprice');

            if (basePriceEl || salePriceEl) {
                // Standard price layout (detail view, simple products)
                if (basePriceEl && response.pricing.base_price) {
                    basePriceEl.innerHTML = response.pricing.base_price;
                    if (response.pricing.class === 'show') {
                        basePriceEl.style.display = '';
                        basePriceEl.classList.add('strike');
                    } else {
                        basePriceEl.style.display = 'none';
                    }
                }
                if (salePriceEl && response.pricing.price) {
                    salePriceEl.innerHTML = response.pricing.price;
                }
            } else if (flexiPriceEl && response.pricing.price) {
                // Flexiprice layout (list views) — replace range/from with specific variant price
                let html = '';
                if (response.pricing.base_price && response.pricing.class === 'show') {
                    html += `<del class="base-price text-body-tertiary">${response.pricing.base_price}</del> `;
                }
                html += `<span class="sale-price">${response.pricing.price}</span>`;
                flexiPriceEl.innerHTML = html;
            }

            // Discount text — show/hide based on whether the variant has a discount
            const discountEl = productContainer.querySelector('.discount-percentage');
            if (discountEl) {
                if (response.pricing.discount_text) {
                    discountEl.innerHTML = response.pricing.discount_text;
                    discountEl.style.display = '';
                } else {
                    discountEl.innerHTML = '';
                    discountEl.style.display = 'none';
                }
            }
        }

        // After display price
        if (response.afterDisplayPrice) {
            const afterPriceEl = productContainer.querySelector('.afterDisplayPrice');
            if (afterPriceEl) afterPriceEl.innerHTML = response.afterDisplayPrice;
        }

        // Quantity
        if (response.quantity) {
            const qtyInput = productContainer.querySelector('input[name="product_qty"]');
            if (qtyInput) {
                qtyInput.value = response.quantity;
                const productType = qtyInput.closest('form')?.dataset.product_type;
                if (['variable', 'advancedvariable', 'variablesubscriptionproduct', 'flexivariable'].includes(productType)) {
                    qtyInput.setAttribute('value', response.quantity);
                }
            }
        }

        // Main image — skip legacy swap if variant gallery handled by Swiper
        if (response.main_image && !response.variant_gallery?.length) {
            if (response.thumb_image) {
                const thumbImages = document.querySelectorAll('.j2commerce-product-thumb-image-' + productId);
                thumbImages.forEach(img => img.src = response.thumb_image);
            }

            const mainImages = document.querySelectorAll('.j2commerce-product-main-image-' + productId);
            mainImages.forEach(img => img.src = response.main_image);

            const mainImageContainer = productContainer.querySelector('.j2commerce-mainimage .j2commerce-img-responsive');
            if (mainImageContainer) mainImageContainer.src = response.main_image;

            const additionalMainImage = productContainer.querySelector('.j2commerce-product-additional-images .additional-mainimage');
            if (additionalMainImage) additionalMainImage.src = response.main_image;
        }

        // Variant gallery swap (Swiper)
        if (typeof J2Commerce !== 'undefined' && J2Commerce.swapGalleryImages) {
            J2Commerce.swapGalleryImages(productId, response.variant_gallery);
        }

        // Stock status
        if (typeof response.stock_status !== 'undefined') {
            const stockContainer = productContainer.querySelector('.product-stock-container');
            if (stockContainer) {
                const statusClass = response.availability == 1 ? 'in-stock' : 'out-of-stock';
                stockContainer.innerHTML = `<span class="${statusClass}">${response.stock_status}</span>`;
            }
        }

        // Dimensions
        if (response.dimensions) {
            const dimensionsEl = productContainer.querySelector('.product-dimensions');
            if (dimensionsEl) dimensionsEl.innerHTML = response.dimensions;
        }

        // Weight
        if (response.weight) {
            const weightEl = productContainer.querySelector('.product-weight');
            if (weightEl) weightEl.innerHTML = response.weight;
        }

        // Variant ID — update hidden field from server response
        if (response.variant_id) {
            const form = productContainer.querySelector('form.j2commerce-addtocart-form');
            const vi = form?.querySelector('input[name="variant_id"]');
            if (vi) vi.value = response.variant_id;
        }
    },

    /**
     * Delete a variant
     *
     * @param {number} variantId - The variant ID to delete
     */
    deleteVariant: async function(variantId) {
        if (!confirm(Joomla.Text._('PLG_J2COMMERCE_APP_FLEXIVARIABLE_CONFIRM_DELETE') || 'Are you sure you want to delete this variant?')) {
            return;
        }

        const deleteButton = document.querySelector(`[data-variant-id="${variantId}"][data-flexivariable-action="delete"]`);
        if (deleteButton) {
            deleteButton.disabled = true;
            deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + Joomla.Text._('PLG_J2COMMERCE_APP_FLEXIVARIABLE_DELETING');
        }

        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'flexivariable.deleteVariant');
        formData.append('variant_id', variantId);
        formData.append(Joomla.getOptions('csrf.token'), '1');

        try {
            const response = await fetch(this.baseUrl + '/index.php?format=json', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Remove the variant panel from DOM with animation
                const variantPanel = document.querySelector(`[data-variant-id="${variantId}"].accordion-item`);
                if (variantPanel) {
                    variantPanel.style.transition = 'opacity 0.3s ease';
                    variantPanel.style.opacity = '0';
                    setTimeout(() => variantPanel.remove(), 300);
                } else {
                    window.location.reload();
                }
            } else {
                Joomla.renderMessages({ error: [data.message || 'Failed to delete variant'] });
                if (deleteButton) {
                    deleteButton.disabled = false;
                    deleteButton.innerHTML = '<span class="icon-trash" aria-hidden="true"></span> ' + (Joomla.Text._('JACTION_DELETE') || 'Delete');
                }
            }
        } catch (error) {
            console.error('Delete variant error:', error);
            Joomla.renderMessages({ error: ['An error occurred while deleting the variant'] });
            if (deleteButton) {
                deleteButton.disabled = false;
                deleteButton.innerHTML = '<span class="icon-trash" aria-hidden="true"></span> ' + (Joomla.Text._('JACTION_DELETE') || 'Delete');
            }
        }
    },

    /**
     * Delete all variants for a product
     *
     * @param {number} productId - The product ID
     * @param {number} extensionId - The extension ID
     */
    deleteAllVariants: async function(productId, extensionId) {
        if (!confirm(Joomla.Text._('PLG_J2COMMERCE_APP_FLEXIVARIABLE_CONFIRM_DELETE_ALL') || 'Are you sure you want to delete all variants?')) {
            return;
        }

        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('view', 'apps');
        formData.append('task', 'view');
        formData.append('id', extensionId);
        formData.append('appTask', 'deleteAllVariant');
        formData.append('product_id', productId);
        formData.append(Joomla.getOptions('csrf.token'), '1');

        try {
            const response = await fetch(this.baseUrl + '/index.php?format=json', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                window.location.reload();
            } else {
                Joomla.renderMessages({ error: [data.message || 'Failed to delete variants'] });
            }
        } catch (error) {
            console.error('Delete all variants error:', error);
            Joomla.renderMessages({ error: ['An error occurred while deleting variants'] });
        }
    },

    /**
     * Set or unset default variant
     *
     * @param {number} variantId - The variant ID
     * @param {string} action - 'setDefault' or 'unsetDefault'
     * @param {number} productId - The product ID
     */
    setDefault: async function(variantId, action, productId) {
        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'product.setDefaultVariant');
        formData.append('variant_id', variantId);
        formData.append('product_id', productId);
        formData.append('action', action);
        formData.append(Joomla.getOptions('csrf.token'), '1');

        try {
            const response = await fetch(this.baseUrl + '/index.php?format=json', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                window.location.reload();
            } else {
                Joomla.renderMessages({ error: [data.message || 'Failed to update default variant'] });
            }
        } catch (error) {
            console.error('Set default variant error:', error);
            Joomla.renderMessages({ error: ['An error occurred while updating the default variant'] });
        }
    },

    /**
     * Add a new flexi variant
     *
     * @param {number} productId - The product ID
     * @param {Object} variantCombin - Object with productoption_id => optionvalue_id mappings
     * @param {number} extensionId - The extension ID
     * @param {string} formPrefix - The form field prefix
     */
    addFlexiVariant: async function(productId, variantCombin, extensionId, formPrefix) {
        const formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('view', 'apps');
        formData.append('task', 'view');
        formData.append('id', extensionId);
        formData.append('appTask', 'addFlexiVariant');
        formData.append('flexi_product_id', productId);
        formData.append('form_prefix', formPrefix);
        formData.append(Joomla.getOptions('csrf.token'), '1');

        // Add variant combinations
        for (const [key, value] of Object.entries(variantCombin)) {
            formData.append('variant_combin[' + key + ']', value);
        }

        try {
            const response = await fetch(this.baseUrl + '/index.php?format=json', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Reload the variant list or the entire page
                window.location.reload();
            } else {
                Joomla.renderMessages({ error: [data.message || 'Failed to add variant'] });
            }
        } catch (error) {
            console.error('Add flexi variant error:', error);
            Joomla.renderMessages({ error: ['An error occurred while adding the variant'] });
        }
    }
};

// Global function for backward compatibility
function doFlexiAjaxPrice(productId, elementOrSelector) {
    J2CommerceFlexivariable.doAjaxPrice(productId, elementOrSelector);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    J2CommerceFlexivariable.init();
});
