/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Payment Methods Tab - Unified AJAX handlers for all payment providers
 *
 * Handles delete and set-default actions for saved payment methods
 * from multiple payment providers via com_ajax endpoints.
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initPaymentMethodsHandlers();
    });

    function iconSpan(className) {
        const span = document.createElement('span');
        span.className = className;
        span.setAttribute('aria-hidden', 'true');

        return span;
    }

    function loadingSpinner(className) {
        const spinner = document.createElement('span');
        spinner.className = className;
        spinner.setAttribute('role', 'status');

        const label = document.createElement('span');
        label.className = 'visually-hidden';
        label.textContent = Joomla.Text._('COM_J2COMMERCE_LOADING');
        spinner.appendChild(label);

        return spinner;
    }

    /**
     * Initialize event handlers for payment method actions
     */
    function initPaymentMethodsHandlers() {
        const container = document.querySelector('.j2commerce-payment-methods');

        if (!container) {
            return;
        }

        // Event delegation for delete buttons
        container.addEventListener('click', function(e) {
            const deleteBtn = e.target.closest('.j2commerce-delete-card-btn');

            if (deleteBtn) {
                e.preventDefault();
                handleDeleteCard(deleteBtn);
                return;
            }

            const setDefaultBtn = e.target.closest('.j2commerce-set-default-btn');

            if (setDefaultBtn) {
                e.preventDefault();
                handleSetDefault(setDefaultBtn);
            }
        });
    }

    /**
     * Handle delete card action
     *
     * @param {HTMLElement} button The delete button element
     */
    async function handleDeleteCard(button) {
        const confirmed = Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_CONFIRM_DELETE');

        if (!confirm(confirmed)) {
            return;
        }

        const provider = button.dataset.provider;
        const methodId = button.dataset.methodId;
        const card = button.closest('.j2commerce-payment-card');

        const csrfToken = await resolveCsrfToken();

        if (!csrfToken) {
            showErrorMessage(Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            return;
        }

        try {
            button.disabled = true;
            button.replaceChildren(iconSpan('spinner-border spinner-border-sm me-1'), ' ' + Joomla.Text._('COM_J2COMMERCE_LOADING'));

            // task= is what plugin onAjax handlers dispatch on; provider-specific id param
            // names are mirrored so every provider's handler finds the one it reads.
            const data = await requestCardAction('deleteCard', provider, methodId, csrfToken);

            if (data.success) {
                // Remove card from UI with fade effect
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';

                setTimeout(function() {
                    const row = card.closest('.j2commerce-payment-method') || card.closest('.col-12');
                    if (row) {
                        row.remove();
                        checkEmptyProvider(card);
                    }
                }, 300);

                Joomla.renderMessages({
                    'success': [Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_DELETED')]
                });
            } else {
                throw new Error(data.message || data.error || Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            }
        } catch (error) {
            console.error('Delete card error:', error);
            showErrorMessage(error.message || Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            button.disabled = false;
            button.replaceChildren(iconSpan('fa-solid fa-trash me-1'), Joomla.Text._('JACTION_DELETE'));
        }
    }

    /**
     * Handle set default card action
     *
     * @param {HTMLElement} button The set default button element
     */
    async function handleSetDefault(button) {
        const provider = button.dataset.provider;
        const methodId = button.dataset.methodId;
        const card = button.closest('.j2commerce-payment-card');

        const csrfToken = await resolveCsrfToken();

        if (!csrfToken) {
            showErrorMessage(Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            return;
        }

        try {
            button.disabled = true;
            button.replaceChildren(loadingSpinner('spinner-border spinner-border-sm me-1'));

            const data = await requestCardAction('setDefaultCard', provider, methodId, csrfToken);

            if (data.success) {
                // Update UI - remove default badge from all cards in this provider group
                const providerGroup = card.closest('.j2commerce-payment-provider');
                providerGroup.querySelectorAll('.badge.text-bg-info').forEach(function(badge) {
                    badge.remove();
                });

                // Remove set-default buttons from other cards
                providerGroup.querySelectorAll('.j2commerce-set-default-btn').forEach(function(btn) {
                    btn.remove();
                });

                // Add default badge to this card
                const cardDetails = card.querySelector('.j2commerce-payment-method-details > div');
                if (cardDetails) {
                    const defaultBadge = document.createElement('span');
                    defaultBadge.className = 'badge text-bg-info ms-2';
                    defaultBadge.textContent = Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT');
                    cardDetails.appendChild(defaultBadge);
                }

                // Remove set-default button from this card
                button.remove();

                Joomla.renderMessages({
                    'success': [Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT_SET')]
                });
            } else {
                throw new Error(data.message || data.error || Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            }
        } catch (error) {
            console.error('Set default card error:', error);
            showErrorMessage(error.message || Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR'));
            button.disabled = false;
            button.replaceChildren(iconSpan('fa-solid fa-star me-1'), Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_SET_DEFAULT'));
        }
    }

    /**
     * Normalize a com_ajax plugin response to a single result object.
     *
     * com_ajax wraps plugin event results as {success, data: [...]}; providers return
     * their result as an object, an array of objects, or a JSON-encoded string.
     *
     * @param {*} raw The parsed JSON response
     * @returns {Object} The provider result ({success, message?, error?})
     */
    function unwrapAjaxResult(raw) {
        let result = raw?.data ?? raw;

        if (Array.isArray(result)) {
            result = result[0];
        }

        if (typeof result === 'string') {
            try {
                result = JSON.parse(result);
            } catch (e) {
                result = {};
            }
        }

        return (result && typeof result === 'object') ? result : {};
    }

    /**
     * A hidden Joomla form-token input reproduces exactly `<input type="hidden"
     * name="<32hex>" value="1">` — the shape core's page cache rewrites to the current
     * visitor's token on every cached-page replay, unlike a data attribute or script option.
     */
    function readFormToken() {
        return (Array.from(document.querySelectorAll('input[type="hidden"][value="1"]'))
            .find((el) => /^[0-9a-f]{32}$/.test(el.name)) || {}).name || '';
    }

    /**
     * Resolve a CSRF token: DOM (freshest) -> endpoint -> the container dataset -> the
     * myprofile script option. The last two are baked into markup at render time, so on a
     * cached-page replay they can carry whichever visitor primed the cache; putting them
     * last means a stale value is used only when nothing fresher exists.
     */
    async function resolveCsrfToken() {
        const domToken = readFormToken();

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
                // Fall through to the legacy sources.
            }
        }

        const container = document.querySelector('.j2commerce-payment-methods');

        if (container && container.dataset.csrfToken) {
            return container.dataset.csrfToken;
        }

        return Joomla.getOptions('com_j2commerce.myprofile', {}).csrfToken || '';
    }

    /**
     * POST a card action.
     */
    async function requestCardAction(taskName, provider, methodId, token) {
        const url = 'index.php?option=com_ajax&plugin=' + provider + '&group=j2commerce&task=' + taskName + '&format=json';

        // task= is what plugin onAjax handlers dispatch on; provider-specific id param
        // names are mirrored so every provider's handler finds the one it reads.
        const body = new URLSearchParams({
            [token]: '1',
            method_id: methodId,
            payment_method_id: methodId,
            consent_id: methodId
        });

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': token
            },
            body
        });

        if (!response.ok) {
            throw new Error(Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_NETWORK_ERROR'));
        }

        return unwrapAjaxResult(await response.json());
    }

    /**
     * Show error message using Joomla message queue
     *
     * @param {string} message The error message
     */
    function showErrorMessage(message) {
        Joomla.renderMessages({
            'error': [message]
        });
    }

    /**
     * Check if provider section is empty and remove it
     *
     * @param {HTMLElement} removedCard The card that was removed
     */
    function checkEmptyProvider(removedCard) {
        const container = document.querySelector('.j2commerce-payment-methods');
        const providerGroups = container.querySelectorAll('.j2commerce-payment-provider');

        providerGroups.forEach(function(group) {
            const cards = group.querySelectorAll('.j2commerce-payment-card');

            if (cards.length === 0) {
                group.remove();
            }
        });

        // Check if all providers are empty and show no methods message
        const remainingCards = container.querySelectorAll('.j2commerce-payment-card');

        if (remainingCards.length === 0) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-info';
            alert.setAttribute('role', 'alert');
            alert.append(iconSpan('fa-solid fa-info-circle me-2'), Joomla.Text._('COM_J2COMMERCE_PAYMENT_METHODS_NO_SAVED'));
            container.replaceChildren(alert);
        }
    }
})();
