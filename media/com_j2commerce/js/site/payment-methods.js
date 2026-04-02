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
        const confirmed = Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_CONFIRM_DELETE', 'Are you sure you want to delete this payment method?');

        if (!confirm(confirmed)) {
            return;
        }

        const provider = button.dataset.provider;
        const methodId = button.dataset.methodId;
        const card = button.closest('.j2commerce-payment-card');

        const csrfToken = getCsrfToken();

        if (!csrfToken) {
            showErrorMessage(Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            return;
        }

        try {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ' + Joomla.JText._('COM_J2COMMERCE_LOADING');

            const response = await fetch('index.php?option=com_ajax&plugin=' + provider + '&group=j2commerce&method=deleteCard&format=json', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    [csrfToken]: '1',
                    method_id: methodId
                })
            });

            if (!response.ok) {
                throw new Error(Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_NETWORK_ERROR', 'Network error'));
            }

            const data = await response.json();

            if (data.success || data.data?.success) {
                // Remove card from UI with fade effect
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';

                setTimeout(function() {
                    const row = card.closest('.col-12');
                    if (row) {
                        row.remove();
                        checkEmptyProvider(card);
                    }
                }, 300);

                Joomla.renderMessages({
                    'success': [Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_DELETED', 'Payment method deleted')]
                });
            } else {
                throw new Error(data.message || data.data?.message || Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            }
        } catch (error) {
            console.error('Delete card error:', error);
            showErrorMessage(error.message || Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            button.disabled = false;
            button.innerHTML = '<span class="fa-solid fa-trash me-1"></span>' + Joomla.JText._('JACTION_DELETE', 'Delete');
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

        const csrfToken = getCsrfToken();

        if (!csrfToken) {
            showErrorMessage(Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            return;
        }

        try {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

            const response = await fetch('index.php?option=com_ajax&plugin=' + provider + '&group=j2commerce&method=setDefaultCard&format=json', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    [csrfToken]: '1',
                    method_id: methodId
                })
            });

            if (!response.ok) {
                throw new Error(Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_NETWORK_ERROR', 'Network error'));
            }

            const data = await response.json();

            if (data.success || data.data?.success) {
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
                    defaultBadge.textContent = Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT', 'Default');
                    cardDetails.appendChild(defaultBadge);
                }

                // Remove set-default button from this card
                button.remove();

                Joomla.renderMessages({
                    'success': [Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_DEFAULT_SET', 'Default payment method updated')]
                });
            } else {
                throw new Error(data.message || data.data?.message || Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            }
        } catch (error) {
            console.error('Set default card error:', error);
            showErrorMessage(error.message || Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_ERROR', 'An error occurred'));
            button.disabled = false;
            button.innerHTML = '<span class="fa-solid fa-star me-1"></span>' + Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_SET_DEFAULT', 'Set as Default');
        }
    }

    /**
     * Get CSRF token from container or Joomla options
     *
     * @returns {string|null} The CSRF token
     */
    function getCsrfToken() {
        const container = document.querySelector('.j2commerce-payment-methods');

        if (container && container.dataset.csrfToken) {
            return container.dataset.csrfToken;
        }

        // Fallback to Joomla script options
        const options = Joomla.getOptions('com_j2commerce.myprofile', {});

        return options.csrfToken || null;
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
            container.innerHTML = '<div class="alert alert-info" role="alert">' +
                '<span class="fa-solid fa-info-circle me-2" aria-hidden="true"></span>' +
                Joomla.JText._('COM_J2COMMERCE_PAYMENT_METHODS_NO_SAVED', 'No saved payment methods') +
                '</div>';
        }
    }
})();
