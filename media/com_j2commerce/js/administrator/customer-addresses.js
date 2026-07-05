/**
 * J2Commerce admin — Customer Addresses card grid.
 *
 * Handles the Bootstrap 5 modal + AJAX CRUD for addresses linked to a customer
 * on the Customer edit view's "Address" tab.
 *
 * Endpoints and options come from Joomla.getOptions('com_j2commerce.customer_addresses').
 */
'use strict';

(function () {
    var opts = (typeof Joomla !== 'undefined' && Joomla.getOptions)
        ? Joomla.getOptions('com_j2commerce.customer_addresses') || {}
        : {};

    if (!opts.formUrl || !opts.saveUrl || !opts.deleteUrl) {
        return;
    }

    var modalEl = document.getElementById('j2commerce-address-modal');
    if (!modalEl) {
        return;
    }

    var modalBody  = modalEl.querySelector('.modal-body');
    var modalTitle = modalEl.querySelector('.modal-title');
    var saveBtn    = modalEl.querySelector('.j2commerce-address-save');
    var cardsGrid  = document.getElementById('j2commerce-address-cards');

    var bsModal = null;
    function getModal() {
        if (!bsModal && typeof bootstrap !== 'undefined') {
            bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return bsModal;
    }

    function showLoading() {
        modalBody.replaceChildren(document.createRange().createContextualFragment(
            '<div class="text-center py-5">' +
            '<span class="spinner-border" role="status" aria-hidden="true"></span>' +
            '<p class="mt-2 mb-0">' + (opts.strings && opts.strings.loading ? opts.strings.loading : 'Loading...') + '</p>' +
            '</div>'
        ));
    }

    function showError(message) {
        var alertEl = document.createElement('div');
        alertEl.className = 'alert alert-danger';
        alertEl.textContent = message || (opts.strings && opts.strings.genericError) || 'Error';
        modalBody.insertBefore(alertEl, modalBody.firstChild);
    }

    function renderToast(type, message) {
        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
            var bucket = {};
            bucket[type] = [message];
            Joomla.renderMessages(bucket);
            return;
        }

        window.alert(message);
    }

    /**
     * Fetch the modal form fragment and open the modal.
     */
    function openForm(addressId, userId, titleText) {
        showLoading();
        modalTitle.textContent = titleText || '';
        getModal().show();

        var url = opts.formUrl +
            '&id=' + encodeURIComponent(addressId) +
            '&user_id=' + encodeURIComponent(userId || 0) +
            '&' + encodeURIComponent(opts.token) + '=1';

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error('HTTP ' + resp.status);
                }
                return resp.text();
            })
            .then(function (html) {
                modalBody.replaceChildren(document.createRange().createContextualFragment(html));
                bindCountryZoneSync();
            })
            .catch(function (err) {
                modalBody.replaceChildren();
                showError(err && err.message ? err.message : '');
            });
    }

    /**
     * Country→Zone cascading select, re-bound every time the modal form is injected.
     */
    function bindCountryZoneSync() {
        if (!opts.zonesUrl) {
            return;
        }

        var countrySelect = modalBody.querySelector('#jform_country_id');
        var zoneSelect    = modalBody.querySelector('#jform_zone_id');

        if (!countrySelect || !zoneSelect) {
            return;
        }

        countrySelect.addEventListener('change', function () {
            loadZones(countrySelect.value, 0, zoneSelect);
        });
    }

    function loadZones(countryId, selectedZoneId, zoneSelect) {
        if (!countryId || countryId === '0' || countryId === '') {
            return;
        }

        var url = opts.zonesUrl + '&country_id=' + encodeURIComponent(countryId) + '&zone_id=' + encodeURIComponent(selectedZoneId || 0);

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (resp) { return resp.ok ? resp.text() : ''; })
            .then(function (html) {
                if (html) {
                    zoneSelect.replaceChildren(document.createRange().createContextualFragment(html));
                }
            });
    }

    /**
     * Render a single card element from the address payload returned by the server.
     * Mirrors the server-rendered markup in tmpl/customer/edit.php for a11y consistency.
     */
    function renderCard(address) {
        var wrapper = document.createElement('li');
        wrapper.className = 'col-md-6 col-xl-4 j2commerce-address-card-wrap';
        wrapper.setAttribute('data-address-id', String(address.j2commerce_address_id));

        var cardId    = parseInt(address.j2commerce_address_id, 10) || 0;
        var headingId = 'j2commerce-address-card-heading-' + cardId;
        var type      = address.type ? String(address.type) : 'billing';
        var fullName  = ((address.first_name || '') + ' ' + (address.last_name || '')).trim();
        var displayName = fullName || (opts.strings && opts.strings.unnamed) || '(unnamed)';

        var cityLine = address.city || '';
        if (address.zip) {
            cityLine += (cityLine ? ', ' : '') + address.zip;
        }

        var regionLine = '';
        if (address.zone_name) {
            regionLine += escapeHtml(address.zone_name) + ', ';
        }
        if (address.country_name) {
            regionLine += escapeHtml(address.country_name);
        }

        var phoneHtml = '';
        if (address.phone_1) {
            phoneHtml =
                '<br><span class="icon-phone" aria-hidden="true"></span> ' +
                '<a href="tel:' + escapeHtml(address.phone_1) + '">' + escapeHtml(address.phone_1) + '</a>';
        }

        var emailHtml = '';
        if (address.email) {
            emailHtml =
                '<br><span class="icon-envelope" aria-hidden="true"></span> ' +
                '<a href="mailto:' + escapeHtml(address.email) + '">' + escapeHtml(address.email) + '</a>';
        }

        var typeLabel    = (opts.strings && opts.strings.typeLabel) || 'Address Type';
        var actionsLabel = ((opts.strings && opts.strings.cardActions) || 'Actions for {name}').replace('{name}', displayName);
        var editLabel    = ((opts.strings && opts.strings.editAria)    || 'Edit address for {name}').replace('{name}', displayName);
        var deleteLabel  = ((opts.strings && opts.strings.deleteAria)  || 'Delete address for {name}').replace('{name}', displayName);

        wrapper.replaceChildren(document.createRange().createContextualFragment(
            '<article class="card h-100 rounded-1 shadow-sm border" aria-labelledby="' + headingId + '">' +
                '<header class="card-header d-flex justify-content-between align-items-center">' +
                    '<span class="badge text-bg-info text-uppercase">' +
                        '<span class="visually-hidden">' + escapeHtml(typeLabel) + ': </span>' +
                        escapeHtml(type) +
                    '</span>' +
                    '<div class="btn-group btn-group-sm" role="group" aria-label="' + escapeHtml(actionsLabel) + '">' +
                        '<button type="button" class="btn btn-outline-secondary j2commerce-address-edit" data-address-id="' + cardId + '" aria-label="' + escapeHtml(editLabel) + '">' +
                            '<span class="icon-edit" aria-hidden="true"></span>' +
                        '</button>' +
                        '<button type="button" class="btn btn-outline-danger j2commerce-address-delete" data-address-id="' + cardId + '" aria-label="' + escapeHtml(deleteLabel) + '">' +
                            '<span class="icon-trash" aria-hidden="true"></span>' +
                        '</button>' +
                    '</div>' +
                '</header>' +
                '<div class="card-body">' +
                    '<h3 class="card-title h6 mb-2" id="' + headingId + '">' + escapeHtml(displayName) + '</h3>' +
                    '<address class="mb-0">' +
                        (address.company ? escapeHtml(address.company) + '<br>' : '') +
                        escapeHtml(address.address_1 || '') + '<br>' +
                        (address.address_2 ? escapeHtml(address.address_2) + '<br>' : '') +
                        escapeHtml(cityLine) + '<br>' +
                        regionLine +
                        phoneHtml +
                        emailHtml +
                    '</address>' +
                '</div>' +
            '</article>'
        ));

        return wrapper;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }

        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // --- Event delegation ---

    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.j2commerce-address-edit');
        if (editBtn) {
            e.preventDefault();
            var editId = parseInt(editBtn.getAttribute('data-address-id'), 10) || 0;
            openForm(editId, opts.userId, 'Edit Address');
            return;
        }

        var addBtn = e.target.closest('.j2commerce-address-add');
        if (addBtn) {
            e.preventDefault();
            var targetUser = parseInt(addBtn.getAttribute('data-user-id'), 10) || opts.userId || 0;
            openForm(0, targetUser, 'Add Address');
            return;
        }

        var delBtn = e.target.closest('.j2commerce-address-delete');
        if (delBtn) {
            e.preventDefault();
            var delId = parseInt(delBtn.getAttribute('data-address-id'), 10) || 0;

            if (!delId) {
                return;
            }

            var confirmMsg = (opts.strings && opts.strings.confirmDelete) || 'Delete this address?';
            if (!window.confirm(confirmMsg)) {
                return;
            }

            var form = new FormData();
            form.append('id', String(delId));
            form.append(opts.token, '1');

            fetch(opts.deleteUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: form
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        var node = document.querySelector('.j2commerce-address-card-wrap[data-address-id="' + delId + '"]');
                        if (node) {
                            node.remove();
                        }
                        renderToast('message', data.message || '');
                    } else {
                        renderToast('error', (data && data.message) || 'Error');
                    }
                })
                .catch(function () {
                    renderToast('error', (opts.strings && opts.strings.genericError) || 'Error');
                });
        }
    });

    // --- User picker auto-relink (card mode only) ---
    //
    // In card mode the page-level Save buttons are removed, so changing the linked
    // Joomla user must propagate via AJAX. The Joomla user field renders a hidden
    // <input id="jform_user_id"> whose value changes when a user is picked from
    // the modal. We watch that input for changes, confirm with the operator, then
    // call ajaxRelinkUser.
    if (opts.cardMode && opts.relinkUrl) {
        var userInput = document.getElementById('jform_user_id');

        if (userInput) {
            var lastUserId = parseInt(userInput.value, 10) || 0;

            userInput.addEventListener('change', function () {
                var newUserId = parseInt(userInput.value, 10) || 0;

                if (newUserId === lastUserId) {
                    return;
                }

                var confirmMsg = (opts.strings && opts.strings.confirmRelink)
                    || 'Re-link every address from the previous user to the selected user?';

                if (!window.confirm(confirmMsg)) {
                    userInput.value = String(lastUserId);
                    // Notify the Joomla user field display that we reverted.
                    var revertEvt = new Event('change', { bubbles: true });
                    userInput.dispatchEvent(revertEvt);
                    return;
                }

                var fd = new FormData();
                fd.append('old_user_id', String(lastUserId));
                fd.append('new_user_id', String(newUserId));
                fd.append(opts.token, '1');

                fetch(opts.relinkUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                })
                    .then(function (resp) { return resp.json(); })
                    .then(function (data) {
                        if (data && data.success) {
                            lastUserId = newUserId;
                            renderToast('message', data.message || '');
                            // Reload so cards refresh against the new user.
                            window.location.reload();
                        } else {
                            renderToast('error', (data && data.message) || 'Error');
                            userInput.value = String(lastUserId);
                        }
                    })
                    .catch(function () {
                        renderToast('error', (opts.strings && opts.strings.genericError) || 'Error');
                        userInput.value = String(lastUserId);
                    });
            });
        }
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();

            var formEl = modalBody.querySelector('form');
            if (!formEl) {
                return;
            }

            var formData = new FormData(formEl);
            formData.append(opts.token, '1');

            saveBtn.disabled = true;

            fetch(opts.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    saveBtn.disabled = false;

                    if (!data || !data.success) {
                        showError((data && data.message) || '');
                        return;
                    }

                    // Replace existing card or prepend new one.
                    var addressPayload = data.address || {};
                    var existing = document.querySelector('.j2commerce-address-card-wrap[data-address-id="' + addressPayload.j2commerce_address_id + '"]');
                    var newCard  = renderCard(addressPayload);

                    if (existing) {
                        existing.parentNode.replaceChild(newCard, existing);
                    } else if (cardsGrid) {
                        cardsGrid.appendChild(newCard);
                    }

                    getModal().hide();
                    renderToast('message', data.message || '');
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    showError((opts.strings && opts.strings.genericError) || '');
                });
        });
    }
})();
