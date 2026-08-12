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
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');

        var label = document.createElement('p');
        label.className = 'mt-2 mb-0';
        label.textContent = (opts.strings && opts.strings.loading) || 'Loading...';

        var loading = document.createElement('div');
        loading.className = 'text-center py-5';
        loading.append(spinner, label);

        modalBody.replaceChildren(loading);
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
            .then(function (resp) { return resp.ok ? resp.json() : null; })
            .then(function (data) {
                if (!data) {
                    return;
                }

                var options = [new Option(data.placeholder, '')];

                (data.zones || []).forEach(function (zone) {
                    var option = new Option(zone.name, zone.id);
                    option.selected = String(zone.id) === String(data.selected);
                    options.push(option);
                });

                zoneSelect.replaceChildren.apply(zoneSelect, options);
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
            regionLine += address.zone_name + ', ';
        }
        if (address.country_name) {
            regionLine += address.country_name;
        }

        var typeLabel    = (opts.strings && opts.strings.typeLabel) || 'Address Type';
        var actionsLabel = ((opts.strings && opts.strings.cardActions) || 'Actions for {name}').replace('{name}', displayName);
        var editLabel    = ((opts.strings && opts.strings.editAria)    || 'Edit address for {name}').replace('{name}', displayName);
        var deleteLabel  = ((opts.strings && opts.strings.deleteAria)  || 'Delete address for {name}').replace('{name}', displayName);

        var typeSr = document.createElement('span');
        typeSr.className = 'visually-hidden';
        typeSr.textContent = typeLabel + ': ';

        var badge = document.createElement('span');
        badge.className = 'badge text-bg-info text-uppercase';
        badge.append(typeSr, type);

        var actions = document.createElement('div');
        actions.className = 'btn-group btn-group-sm';
        actions.setAttribute('role', 'group');
        actions.setAttribute('aria-label', actionsLabel);
        actions.append(
            addressActionButton('btn btn-outline-secondary j2commerce-address-edit', 'icon-edit', editLabel, cardId),
            addressActionButton('btn btn-outline-danger j2commerce-address-delete', 'icon-trash', deleteLabel, cardId)
        );

        var header = document.createElement('header');
        header.className = 'card-header d-flex justify-content-between align-items-center';
        header.append(badge, actions);

        var heading = document.createElement('h3');
        heading.className = 'card-title h6 mb-2';
        heading.id = headingId;
        heading.textContent = displayName;

        var lines = document.createElement('address');
        lines.className = 'mb-0';

        if (address.company) {
            lines.append(address.company, document.createElement('br'));
        }
        lines.append(address.address_1 || '', document.createElement('br'));
        if (address.address_2) {
            lines.append(address.address_2, document.createElement('br'));
        }
        lines.append(cityLine, document.createElement('br'), regionLine);

        if (address.phone_1) {
            lines.append(document.createElement('br'), contactLink('icon-phone', 'tel:', address.phone_1));
        }
        if (address.email) {
            lines.append(document.createElement('br'), contactLink('icon-envelope', 'mailto:', address.email));
        }

        var cardBody = document.createElement('div');
        cardBody.className = 'card-body';
        cardBody.append(heading, lines);

        var card = document.createElement('article');
        card.className = 'card h-100 rounded-1 shadow-sm border';
        card.setAttribute('aria-labelledby', headingId);
        card.append(header, cardBody);

        wrapper.replaceChildren(card);

        return wrapper;
    }

    function addressActionButton(className, iconClass, ariaLabel, addressId) {
        var icon = document.createElement('span');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');

        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.dataset.addressId = addressId;
        button.setAttribute('aria-label', ariaLabel);
        button.appendChild(icon);

        return button;
    }

    // Returns the icon and the link as a fragment so both land inside <address> in one append.
    function contactLink(iconClass, scheme, value) {
        var icon = document.createElement('span');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');

        var link = document.createElement('a');
        link.href = scheme + value;
        link.textContent = value;

        var fragment = document.createDocumentFragment();
        fragment.append(icon, ' ', link);

        return fragment;
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
