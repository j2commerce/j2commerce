/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentPaypal
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

(function () {
    let sdkLoaded = false;
    let sdkLoading = false;
    let buttonsRendered = false;
    let debug = false;
    let observer = null;

    const debugLog = (...args) => {
        if (debug) {
            console.log('[PayPal Debug]', ...args);
        }
    };

    const loadPayPalSDK = (sdkUrl) => {
        debugLog('Loading PayPal SDK from:', sdkUrl);
        return new Promise((resolve, reject) => {
            if (typeof paypal !== 'undefined') {
                sdkLoaded = true;
                debugLog('PayPal SDK already loaded');
                resolve();
                return;
            }

            if (sdkLoading) {
                debugLog('PayPal SDK loading in progress, waiting...');
                const checkInterval = setInterval(() => {
                    if (typeof paypal !== 'undefined') {
                        clearInterval(checkInterval);
                        debugLog('PayPal SDK loaded (while waiting)');
                        resolve();
                    }
                }, 100);
                return;
            }

            sdkLoading = true;

            const script = document.createElement('script');
            script.src = sdkUrl;

            script.onload = () => {
                sdkLoaded = true;
                sdkLoading = false;
                debugLog('PayPal SDK loaded successfully');
                resolve();
            };

            script.onerror = (e) => {
                sdkLoading = false;
                console.error('[PayPal] Failed to load SDK:', e);
                reject(new Error('Failed to load PayPal SDK'));
            };

            document.head.appendChild(script);
        });
    };

    const initializePayPalButtons = (container) => {
        if (!container || container.dataset.paypalInitialized === 'true' || buttonsRendered) {
            debugLog('Skipping init — already initialized');
            return;
        }

        debug = container.dataset.debug === 'true';
        debugLog('Initializing PayPal buttons');

        // Stop observing once we found the container
        if (observer) {
            observer.disconnect();
            observer = null;
        }

        const orderId = container.dataset.orderId;
        const createOrderUrl = container.dataset.createOrderUrl;
        const captureOrderUrl = container.dataset.captureOrderUrl;
        const csrfToken = container.dataset.csrfToken;
        const currency = container.dataset.currency || 'USD';
        const amount = container.dataset.amount;
        const sandbox = container.dataset.sandbox === 'true';
        const clientId = container.dataset.clientId;
        const isSubscription = container.dataset.isSubscription === 'true';
        const subscriptionMode = container.dataset.subscriptionMode || 'rest';
        const isNvpMode = isSubscription && subscriptionMode === 'nvp';

        debugLog('Configuration:', { orderId, currency, amount, sandbox, isSubscription, subscriptionMode });

        const errorContainer = document.getElementById('paypal-error-message');
        const processingContainer = document.getElementById('paypal-processing-message');

        if (!clientId) {
            console.error('[PayPal] No client ID found in data attributes');
            return;
        }

        const showError = (message) => {
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.classList.remove('d-none');
            }
            if (processingContainer) {
                processingContainer.classList.add('d-none');
            }
        };

        const showProcessing = () => {
            if (processingContainer) {
                processingContainer.classList.remove('d-none');
            }
            if (errorContainer) {
                errorContainer.classList.add('d-none');
            }
        };

        const hideMessages = () => {
            if (errorContainer) {
                errorContainer.classList.add('d-none');
            }
            if (processingContainer) {
                processingContainer.classList.add('d-none');
            }
        };

        // Legacy NVP Express Checkout flow: no Smart Buttons SDK. Render a single
        // "Subscribe via PayPal" button that POSTs to createOrderUrl, gets back a
        // redirect_url, sends the customer to classic PayPal Express Checkout.
        if (isNvpMode) {
            container.dataset.paypalInitialized = 'true';
            buttonsRendered = true;

            const nvpButton = document.createElement('button');
            nvpButton.type = 'button';
            nvpButton.className = 'btn btn-primary btn-lg w-100';
            nvpButton.textContent = 'Subscribe via PayPal';
            nvpButton.style.minHeight = '45px';

            nvpButton.addEventListener('click', async () => {
                nvpButton.disabled = true;
                hideMessages();
                showProcessing();
                debugLog('NVP express checkout: requesting redirect URL');

                try {
                    const response = await fetch(createOrderUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_id: orderId,
                            currency: currency,
                            amount: amount,
                            mode: 'nvp',
                            [csrfToken]: '1'
                        })
                    });
                    const data = await response.json();
                    debugLog('NVP express checkout response:', { status: response.status, data });

                    if (!response.ok || !data.success || !data.redirect_url) {
                        throw new Error(data.error || 'Failed to start PayPal Express Checkout');
                    }

                    window.location.href = data.redirect_url;
                } catch (err) {
                    console.error('[PayPal NVP] start error:', err);
                    showError(err.message || 'Failed to start PayPal Express Checkout. Please try again.');
                    nvpButton.disabled = false;
                }
            });

            container.appendChild(nvpButton);
            debugLog('NVP express checkout button rendered');
            return;
        }

        const baseUrl = sandbox ? 'https://www.sandbox.paypal.com/sdk/js' : 'https://www.paypal.com/sdk/js';
        // Subscriptions require vault=true + intent=subscription on the SDK URL.
        // One-off Orders v2 carts use vault=false + intent=capture.
        const sdkUrl = isSubscription
            ? `${baseUrl}?client-id=${encodeURIComponent(clientId)}&vault=true&intent=subscription&components=buttons`
            : `${baseUrl}?client-id=${encodeURIComponent(clientId)}&currency=${encodeURIComponent(currency)}&intent=capture&components=buttons`;

        container.dataset.paypalInitialized = 'true';
        buttonsRendered = true;

        loadPayPalSDK(sdkUrl)
            .then(() => {
                if (typeof paypal === 'undefined') {
                    throw new Error('PayPal SDK loaded but paypal object not available');
                }

                debugLog('Rendering PayPal buttons (' + (isSubscription ? 'subscription' : 'one-off') + ')');

                const buttonConfig = {
                    style: {
                        layout: 'vertical',
                        color: 'gold',
                        shape: 'rect',
                        label: isSubscription ? 'subscribe' : 'paypal',
                        height: 45
                    },

                    onCancel: () => {
                        debugLog('onCancel: Payment cancelled by user');
                        showError('Payment was cancelled. You can try again or choose a different payment method.');
                    },

                    onError: (err) => {
                        console.error('[PayPal] Button error:', err);
                        const msg = err?.message || (typeof err === 'string' ? err : 'Unknown error');
                        showError('PayPal error: ' + msg);
                    }
                };

                if (isSubscription) {
                    buttonConfig.createSubscription = async () => {
                        try {
                            debugLog('createSubscription: Starting subscription creation');
                            hideMessages();

                            const requestBody = {
                                order_id: orderId,
                                currency: currency,
                                amount: amount,
                                [csrfToken]: '1'
                            };

                            const response = await fetch(createOrderUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(requestBody)
                            });

                            const data = await response.json();

                            debugLog('createSubscription: Response:', { status: response.status, data });

                            if (!response.ok || !data.success || !data.paypal_subscription_id) {
                                throw new Error(data.error || 'Failed to create PayPal subscription');
                            }

                            debugLog('createSubscription: PayPal subscription ID:', data.paypal_subscription_id);
                            return data.paypal_subscription_id;
                        } catch (error) {
                            console.error('[PayPal] createSubscription error:', error);
                            showError(error.message || 'Failed to initialize subscription. Please try again.');
                            throw error;
                        }
                    };

                    buttonConfig.onApprove = async (data) => {
                        try {
                            debugLog('onApprove (subscription): Approving subscription:', data.subscriptionID);
                            showProcessing();

                            const requestBody = {
                                paypal_subscription_id: data.subscriptionID,
                                order_id: orderId,
                                [csrfToken]: '1'
                            };

                            const response = await fetch(captureOrderUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(requestBody)
                            });

                            const result = await response.json();

                            debugLog('onApprove (subscription): Finalize response:', { status: response.status, result });

                            if (!response.ok || !result.success) {
                                throw new Error(result.error || 'Subscription approval failed');
                            }

                            if (result.redirect) {
                                window.location.href = result.redirect;
                            } else {
                                showError('Subscription approved but redirect URL is missing.');
                            }
                        } catch (error) {
                            console.error('[PayPal] onApprove (subscription) error:', error);
                            showError(error.message || 'Subscription approval failed. Please contact support.');
                        }
                    };
                } else {
                    buttonConfig.createOrder = async () => {
                        try {
                            debugLog('createOrder: Starting order creation');
                            hideMessages();


                            const requestBody = {
                                order_id: orderId,
                                currency: currency,
                                amount: amount,
                                [csrfToken]: '1'
                            };

                            const response = await fetch(createOrderUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(requestBody)
                            });

                            const data = await response.json();

                            debugLog('createOrder: Response:', { status: response.status, data });

                            if (!response.ok || !data.success) {
                                throw new Error(data.error || 'Failed to create PayPal order');
                            }

                            debugLog('createOrder: PayPal order ID:', data.paypal_order_id);
                            return data.paypal_order_id;
                        } catch (error) {
                            console.error('[PayPal] createOrder error:', error);
                            showError(error.message || 'Failed to initialize payment. Please try again.');
                            throw error;
                        }
                    };

                    buttonConfig.onApprove = async (data) => {
                        try {
                            debugLog('onApprove: Capturing payment for order:', data.orderID);
                            showProcessing();

                            const requestBody = {
                                paypal_order_id: data.orderID,
                                order_id: orderId,
                                [csrfToken]: '1'
                            };

                            const response = await fetch(captureOrderUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(requestBody)
                            });

                            const result = await response.json();

                            debugLog('onApprove: Capture response:', { status: response.status, result });

                            if (!response.ok || !result.success) {
                                if (result.error && result.error.includes('INSTRUMENT_DECLINED')) {
                                    showError('Your payment method was declined. Please try a different payment method.');
                                    return data.restart();
                                }
                                throw new Error(result.error || 'Payment capture failed');
                            }

                            if (result.redirect) {
                                debugLog('onApprove: Redirecting to:', result.redirect);
                                window.location.href = result.redirect;
                            } else {
                                showError('Payment completed but redirect URL is missing.');
                            }
                        } catch (error) {
                            console.error('[PayPal] onApprove error:', error);
                            showError(error.message || 'Payment processing failed. Please contact support.');
                        }
                    };
                }

                return paypal.Buttons(buttonConfig).render('#paypal-button-container');
            })
            .catch((err) => {
                console.error('[PayPal] Initialization failed:', err);
                showError('Failed to load PayPal payment button. Please refresh the page.');
                container.dataset.paypalInitialized = 'false';
                buttonsRendered = false;
            });
    };

    const observeForPayPalContainer = () => {
        observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType !== 1) continue;

                    if (node.id === 'paypal-button-container') {
                        initializePayPalButtons(node);
                        return;
                    }

                    if (node.querySelector) {
                        const container = node.querySelector('#paypal-button-container');
                        if (container) {
                            initializePayPalButtons(container);
                            return;
                        }
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('paypal-button-container');
        if (container) {
            initializePayPalButtons(container);
        }

        observeForPayPalContainer();
    });
})();
