'use strict';

(function() {
    var initialized = new WeakSet();

    function initAll() {
        document.querySelectorAll('.j2c-telephone-field').forEach(function(el) {
            if (!initialized.has(el)) {
                initialized.add(el);
                initTelephoneField(el);
            }
        });
    }

    // Run on initial page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Watch for AJAX-inserted telephone fields
    var observer = new MutationObserver(function(mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var added = mutations[i].addedNodes;
            for (var j = 0; j < added.length; j++) {
                var node = added[j];
                if (node.nodeType !== 1) continue;
                if (node.classList && node.classList.contains('j2c-telephone-field')) {
                    if (!initialized.has(node)) {
                        initialized.add(node);
                        initTelephoneField(node);
                    }
                }
                if (node.querySelectorAll) {
                    node.querySelectorAll('.j2c-telephone-field').forEach(function(el) {
                        if (!initialized.has(el)) {
                            initialized.add(el);
                            initTelephoneField(el);
                        }
                    });
                }
            }
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    function initTelephoneField(container) {
        // "none" mode renders a plain <input type="tel" data-mode="none"> without the
        // .j2c-telephone-field wrapper, so this function is never called for none-mode
        // fields. The guard below handles any edge case where it might be.
        if (container.dataset.mode === 'none') return;

        var countries;
        try {
            countries = JSON.parse(container.dataset.countries || '[]');
        } catch (e) {
            countries = [];
        }
        if (!countries.length) return;

        var hiddenInput   = container.querySelector('input[type="hidden"]');
        var nationalInput = container.querySelector('.j2c-phone-national');

        if (!hiddenInput || !nationalInput) return;

        var isSingle = container.dataset.singleCountry === '1' || countries.length === 1;

        if (isSingle) {
            initSingleCountry(container, hiddenInput, nationalInput, countries[0]);
            return;
        }

        var flagEl     = container.querySelector('.j2c-phone-flag');
        var codeSpan   = container.querySelector('.j2c-phone-code');
        var dropdown   = container.querySelector('.j2c-phone-country-dropdown');
        var searchInput = dropdown ? dropdown.querySelector('.j2c-phone-search') : null;
        var countryBtn = container.querySelector('.j2c-phone-country-btn');

        if (!flagEl || !codeSpan || !dropdown || !countryBtn) return;

        var selectedIso        = container.dataset.defaultIso || 'US';
        var userChangedCountry = false;

        populateDropdown(dropdown, countries, selectedIso);
        applyMaxLength();

        dropdown.addEventListener('click', function(e) {
            var item = e.target.closest('[data-iso]');
            if (!item) return;
            selectCountry(item.dataset.iso);
            userChangedCountry = true;
            var dd = bootstrap.Dropdown.getInstance(countryBtn);
            if (dd) dd.hide();
        });

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterDropdown(dropdown, searchInput.value.toLowerCase(), countries);
            });
        }

        countryBtn.addEventListener('shown.bs.dropdown', function() {
            if (searchInput) {
                searchInput.value = '';
                filterDropdown(dropdown, '', countries);
                searchInput.focus();
            }
        });

        nationalInput.addEventListener('input', function() {
            nationalInput.value = nationalInput.value.replace(/\D/g, '');
            var country = findCountry(selectedIso);
            if (country && nationalInput.value.length > country.max) {
                nationalInput.value = nationalInput.value.slice(0, country.max);
            }
            updateHiddenValue();
            validateLength();
        });

        // Sync phone country when billing country_id changes
        document.addEventListener('change', function(e) {
            if (userChangedCountry) return;
            var sel = e.target.closest('[name="country_id"], #country_id');
            if (!sel) return;
            var countryMap = (typeof Joomla !== 'undefined' && Joomla.getOptions)
                ? Joomla.getOptions('com_j2commerce.phoneCountryMap') || {}
                : {};
            var iso = countryMap[sel.value];
            if (iso) selectCountry(iso);
        });

        function findCountry(iso) {
            for (var i = 0; i < countries.length; i++) {
                if (countries[i].iso2 === iso) return countries[i];
            }
            return null;
        }

        function selectCountry(iso) {
            var country = findCountry(iso);
            if (!country) return;
            selectedIso = iso;

            var currentFlag = countryBtn.querySelector('.j2c-phone-flag');
            if (country.flagUrl) {
                if (currentFlag && currentFlag.tagName === 'IMG') {
                    currentFlag.src = country.flagUrl;
                    currentFlag.alt = iso;
                } else {
                    var img = document.createElement('img');
                    img.src = country.flagUrl;
                    img.alt = iso;
                    img.className = 'j2c-phone-flag';
                    if (currentFlag) currentFlag.replaceWith(img);
                }
            } else if (currentFlag) {
                if (currentFlag.tagName === 'IMG') {
                    var span = document.createElement('span');
                    span.className = 'j2c-phone-flag';
                    span.textContent = iso;
                    currentFlag.replaceWith(span);
                } else {
                    currentFlag.textContent = iso;
                }
            }

            codeSpan.textContent = '+' + country.code;
            applyMaxLength();
            updateHiddenValue();
            validateLength();
        }

        function applyMaxLength() {
            var country = findCountry(selectedIso);
            if (country) {
                nationalInput.maxLength = country.max;
                if (nationalInput.value.length > country.max) {
                    nationalInput.value = nationalInput.value.slice(0, country.max);
                }
            }
        }

        function updateHiddenValue() {
            var country  = findCountry(selectedIso);
            var national = nationalInput.value.replace(/\D/g, '');
            hiddenInput.value = (country && national) ? '+' + country.code + national : '';
        }

        function validateLength() {
            var country = findCountry(selectedIso);
            if (!country) return;
            var len = nationalInput.value.length;
            var invalid = len > 0 && (len < country.min || len > country.max);
            nationalInput.classList.toggle('is-invalid', invalid);
        }
    }

    function initSingleCountry(container, hiddenInput, nationalInput, country) {
        var dialCode = nationalInput.dataset.dialCode || country.code;

        nationalInput.addEventListener('input', function() {
            nationalInput.value = nationalInput.value.replace(/\D/g, '');
            if (nationalInput.value.length > country.max) {
                nationalInput.value = nationalInput.value.slice(0, country.max);
            }
            var national = nationalInput.value;
            hiddenInput.value = national ? '+' + dialCode + national : '';
            var len = national.length;
            var invalid = len > 0 && (len < country.min || len > country.max);
            nationalInput.classList.toggle('is-invalid', invalid);
        });

        // Set initial hidden value if national already populated
        if (nationalInput.value) {
            hiddenInput.value = '+' + dialCode + nationalInput.value;
        }
    }

    function populateDropdown(dropdown, countries, selectedIso) {
        var html = '';
        for (var i = 0; i < countries.length; i++) {
            var c = countries[i];
            var active = c.iso2 === selectedIso ? ' active' : '';
            var flagHtml = c.flagUrl
                ? '<img src="' + c.flagUrl + '" alt="' + c.iso2 + '" class="j2c-phone-flag">'
                : '<span class="j2c-phone-flag">' + c.iso2 + '</span>';
            html += '<li><button type="button" class="dropdown-item' + active
                + '" data-iso="' + c.iso2 + '">'
                + flagHtml + ' ' + c.name
                + ' <span class="text-muted">+' + c.code + '</span>'
                + '</button></li>';
        }

        // Keep the search input (first child), remove everything else
        while (dropdown.children.length > 1) {
            dropdown.removeChild(dropdown.lastChild);
        }
        dropdown.insertAdjacentHTML('beforeend', html);
    }

    function filterDropdown(dropdown, query, countries) {
        var items = dropdown.querySelectorAll('[data-iso]');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var iso = item.dataset.iso;
            var country = null;
            for (var j = 0; j < countries.length; j++) {
                if (countries[j].iso2 === iso) { country = countries[j]; break; }
            }
            if (!country) continue;
            var match = !query
                || country.name.toLowerCase().indexOf(query) !== -1
                || country.code.indexOf(query) !== -1
                || country.iso2.toLowerCase().indexOf(query) !== -1;
            item.closest('li').style.display = match ? '' : 'none';
        }
    }
})();
