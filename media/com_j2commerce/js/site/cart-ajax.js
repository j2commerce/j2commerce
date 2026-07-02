/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

/**
 * J2Commerce Cart AJAX functionality
 *
 * Provides AJAX-based quantity updates and item removal for the cart page.
 * Configuration is passed via Joomla.getOptions('j2commerce.cart').
 */
(function() {

    // Parse server-rendered HTML into an inert fragment (no innerHTML sink, no script
    // execution) so adopted nodes never re-run injected <script> tags.
    function parseFragment(html) {
        return document.createRange().createContextualFragment(html || '');
    }

    /**
     * Initialize cart AJAX functionality
     */
    function initCartAjax() {
        // Get configuration from Joomla options
        const options = Joomla.getOptions('j2commerce.cart') || {};

        const csrfToken = options.csrfToken || '';
        const baseUrl = options.baseUrl || 'index.php';
        const strings = options.strings || {};

        // Check if we have the required configuration
        if (!csrfToken) {
            console.warn('J2Commerce Cart: Missing CSRF token configuration');
            return;
        }

        let updateDebounceTimer = null;

        /**
         * Refresh the cart totals section via AJAX
         * Fetches updated totals HTML and replaces the totals container
         */
        async function refreshCartTotals() {
            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'carts.getTotalsAjax');
            formData.append(csrfToken, '1');

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                const data = await response.json();

                if (data.success && data.html) {
                    // Find and replace the totals container
                    const totalsContainer = document.querySelector('.cart-totals-block');
                    if (totalsContainer) {
                        totalsContainer.replaceWith(parseFragment(data.html));
                    }

                    // Also update shipping methods display
                    if (data.shipping_html !== undefined) {
                        const shippingWrapper = document.getElementById('j2commerce-cart-shipping-wrapper');
                        if (shippingWrapper) {
                            shippingWrapper.replaceChildren(parseFragment(data.shipping_html));
                        }
                    }
                }
            } catch (error) {
                console.error('Error refreshing totals:', error);
                // Fallback: reload the page if AJAX totals refresh fails
                window.location.reload();
            }
        }

        /**
         * Show empty cart message when all items are removed
         */
        function showEmptyCartMessage() {
            const cartContainer = document.querySelector('.j2commerce-cart');
            if (cartContainer) {
                // Create empty cart message
                const emptyMessage = document.createElement('div');
                emptyMessage.className = 'alert alert-info';
                const noItems = document.createElement('span');
                noItems.className = 'cart-no-items';
                noItems.textContent = strings.emptyCart || 'Your cart is empty.';
                emptyMessage.append(noItems);

                // Clear the cart container and show empty message
                cartContainer.replaceChildren();
                cartContainer.appendChild(emptyMessage);
            }
        }

        /**
         * Show loading state on quantity controls
         * @param {HTMLElement} container - The quantity controls container
         * @param {boolean} loading - Whether to show or hide loading state
         */
        function setLoadingState(container, loading) {
            const buttons = container.querySelectorAll('button');
            const input = container.querySelector('.j2commerce-qty-input');

            if (loading) {
                container.classList.add('j2commerce-loading');
                buttons.forEach(btn => btn.disabled = true);
                if (input) input.disabled = true;
            } else {
                container.classList.remove('j2commerce-loading');
                // Re-enable buttons based on constraints
                updateButtonStates(container);
                if (input) input.disabled = false;
            }
        }

        /**
         * Update button enabled/disabled states based on quantity constraints
         * @param {HTMLElement} container - The quantity controls container
         */
        function updateButtonStates(container) {
            const input = container.querySelector('.j2commerce-qty-input');
            const minusBtn = container.querySelector('.j2commerce-qty-minus');
            const plusBtn = container.querySelector('.j2commerce-qty-plus');
            const removeBtn = container.querySelector('.j2commerce-remove-ajax');

            if (!input) return;

            const currentQty = parseInt(input.value, 10) || 1;
            const minQty = parseInt(container.dataset.minQty, 10) || 1;
            const maxQty = parseInt(container.dataset.maxQty, 10) || 0;

            if (minusBtn) {
                minusBtn.disabled = currentQty <= minQty;
            }

            if (plusBtn) {
                plusBtn.disabled = maxQty > 0 && currentQty >= maxQty;
            }

            // Always re-enable the remove button
            if (removeBtn) {
                removeBtn.disabled = false;
            }
        }

        /**
         * Update quantity via AJAX
         * @param {number} cartitemId - The cart item ID
         * @param {number} qty - New quantity
         * @param {HTMLElement} container - The quantity controls container
         */
        async function updateQuantity(cartitemId, qty, container) {
            setLoadingState(container, true);

            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'carts.updateQuantityAjax');
            formData.append('cartitem_id', cartitemId);
            formData.append('qty', qty);
            formData.append(csrfToken, '1');

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Update the input value in case server adjusted it
                    const input = container.querySelector('.j2commerce-qty-input');
                    if (input && data.qty !== undefined) {
                        input.value = data.qty;
                    }

                    // Update line total for this item
                    const row = container.closest('.j2commerce-cart-item');
                    if (row && data.line_total) {
                        // Target the specific span for the line total value
                        const lineTotalValue = row.querySelector('.cart-line-subtotal .line-total-value');
                        if (lineTotalValue) {
                            lineTotalValue.textContent = data.line_total;
                        }
                    }

                    // Refresh totals section via AJAX if available
                    if (data.redirect) {
                        refreshCartTotals();
                    }
                } else {
                    // Show error message
                    if (data.message) {
                        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                            Joomla.renderMessages({ error: [data.message] });
                        } else {
                            alert(data.message);
                        }
                    }

                    // Revert to previous value if provided
                    if (data.original_qty !== undefined) {
                        const input = container.querySelector('.j2commerce-qty-input');
                        if (input) {
                            input.value = data.original_qty;
                        }
                    }
                }
            } catch (error) {
                console.error('Error updating quantity:', error);
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ error: [strings.errorUpdating || 'Error updating cart'] });
                }
            } finally {
                setLoadingState(container, false);
            }
        }

        /**
         * Remove item via AJAX
         * @param {number} cartitemId - The cart item ID
         * @param {HTMLElement} button - The remove button
         */
        async function removeItem(cartitemId, button) {
            const row = button.closest('.j2commerce-cart-item');
            if (!row) return;

            // Disable button during operation
            button.disabled = true;

            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'carts.removeAjax');
            formData.append('cartitem_id', cartitemId);
            formData.append(csrfToken, '1');

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Fade out and remove the row
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';

                    setTimeout(function() {
                        row.remove();

                        // Check if cart is now empty
                        const remainingItems = document.querySelectorAll('.j2commerce-cart-item');
                        if (remainingItems.length === 0) {
                            // Cart is empty - show empty cart message
                            showEmptyCartMessage();
                        } else if (data.redirect) {
                            // Refresh totals section
                            refreshCartTotals();
                        }
                    }, 300);

                    // Show success message
                    if (data.message && typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                        Joomla.renderMessages({ success: [data.message] });
                    }

                    document.dispatchEvent(new CustomEvent('j2commerce:cart:updated'));
                } else {
                    // Show error
                    button.disabled = false;
                    if (data.message) {
                        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                            Joomla.renderMessages({ error: [data.message] });
                        } else {
                            alert(data.message);
                        }
                    }
                }
            } catch (error) {
                console.error('Error removing item:', error);
                button.disabled = false;
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ error: [strings.errorRemoving || 'Error removing item'] });
                }
            }
        }

        // Event delegation for quantity minus buttons
        document.addEventListener('click', function(e) {
            const minusBtn = e.target.closest('.j2commerce-qty-minus');
            if (!minusBtn) return;
            e.preventDefault();
            const container = minusBtn.closest('.j2commerce-qty-controls');
            const input = container.querySelector('.j2commerce-qty-input');

            if (!input || minusBtn.disabled) return;

            const currentQty = parseInt(input.value, 10) || 1;
            const minQty = parseInt(container.dataset.minQty, 10) || 1;
            const newQty = Math.max(minQty, currentQty - 1);

            if (newQty !== currentQty) {
                input.value = newQty;
                const cartitemId = container.dataset.cartitemId;
                updateQuantity(cartitemId, newQty, container);
            }
        });

        // Event delegation for quantity plus buttons
        document.addEventListener('click', function(e) {
            const plusBtn = e.target.closest('.j2commerce-qty-plus');
            if (!plusBtn) return;

            e.preventDefault();
            const container = plusBtn.closest('.j2commerce-qty-controls');
            const input = container.querySelector('.j2commerce-qty-input');

            if (!input || plusBtn.disabled) return;

            const currentQty = parseInt(input.value, 10) || 1;
            const maxQty = parseInt(container.dataset.maxQty, 10) || 0;
            const newQty = maxQty > 0 ? Math.min(maxQty, currentQty + 1) : currentQty + 1;

            if (newQty !== currentQty) {
                input.value = newQty;
                const cartitemId = container.dataset.cartitemId;
                updateQuantity(cartitemId, newQty, container);
            }
        });

        // Event delegation for direct input changes (with debounce)
        document.addEventListener('change', function(e) {
            const input = e.target.closest('.j2commerce-qty-input');
            if (!input) return;

            const container = input.closest('.j2commerce-qty-controls');
            if (!container) return;

            let newQty = parseInt(input.value, 10) || 1;
            const minQty = parseInt(container.dataset.minQty, 10) || 1;
            const maxQty = parseInt(container.dataset.maxQty, 10) || 0;

            // Enforce min/max
            if (newQty < minQty) {
                newQty = minQty;
                input.value = newQty;
            }
            if (maxQty > 0 && newQty > maxQty) {
                newQty = maxQty;
                input.value = newQty;
            }

            const cartitemId = container.dataset.cartitemId;

            // Debounce the update
            clearTimeout(updateDebounceTimer);
            updateDebounceTimer = setTimeout(function() {
                updateQuantity(cartitemId, newQty, container);
            }, 300);
        });

        // Event delegation for remove buttons
        document.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.j2commerce-remove-ajax');
            if (!removeBtn) return;

            e.preventDefault();
            const cartitemId = removeBtn.dataset.cartitemId;

            if (cartitemId) {
                removeItem(cartitemId, removeBtn);
            }
        });

        // Coupon/voucher handlers moved to coupon-voucher.js (reusable layout system)

        // When a new item is added to cart from outside (e.g. related products module),
        // reload the page so the cart items list reflects the new item.
        document.addEventListener('j2commerce:afterAddingToCart', function() {
            window.location.reload();
        });

        // Event delegation for shipping method radio selection
        document.addEventListener('change', function(e) {
            const radio = e.target.closest('.shipping-method-radio');
            if (!radio) return;

            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'carts.shippingUpdate');
            formData.append('shipping_name', radio.dataset.name || '');
            formData.append('shipping_price', radio.dataset.price || '0');
            formData.append('shipping_tax', radio.dataset.tax || '0');
            formData.append('shipping_extra', radio.dataset.extra || '0');
            formData.append('shipping_code', radio.dataset.code || '');
            formData.append('shipping_plugin', radio.dataset.element || '');
            formData.append(csrfToken, '1');

            fetch(baseUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.json())
            .then(data => {
                refreshCartTotals();
            })
            .catch(error => {
                console.error('Error updating shipping method:', error);
            });
        });

        // Event delegation for clear cart button
        document.addEventListener('click', function(e) {
            const clearBtn = e.target.closest('.j2commerce-clear-cart-ajax');
            if (!clearBtn) return;

            e.preventDefault();

            // Confirm before clearing
            if (!confirm(strings.confirmClearCart || 'Are you sure you want to empty your cart?')) {
                return;
            }

            clearBtn.disabled = true;
            clearBtn.classList.add('disabled');

            const formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'carts.clearCartAjax');
            formData.append(csrfToken, '1');

            fetch(baseUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show empty cart message
                    showEmptyCartMessage();

                    // Show success message
                    if (data.message && typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                        Joomla.renderMessages({ success: [data.message] });
                    }

                    document.dispatchEvent(new CustomEvent('j2commerce:cart:updated'));
                } else {
                    clearBtn.disabled = false;
                    clearBtn.classList.remove('disabled');
                    if (data.message) {
                        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                            Joomla.renderMessages({ error: [data.message] });
                        } else {
                            alert(data.message);
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error clearing cart:', error);
                clearBtn.disabled = false;
                clearBtn.classList.remove('disabled');
            });
        });
    }

    // Initialize when DOM is ready or immediately if already loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCartAjax);
    } else {
        // DOM already loaded (script is deferred)
        initCartAjax();
    }
})();
