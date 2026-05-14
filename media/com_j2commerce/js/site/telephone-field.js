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

        // Strip separators from any legacy stored value (e.g. "555-555-0000"
        // saved by an older admin form) so we display clean digits. Do NOT
        // truncate to the country max-length here — that corrupts numbers
        // whose stored country differs from the rendered default.
        nationalInput.value = (nationalInput.value || '').replace(/\D/g, '');

        var framework = container.dataset.framework === 'uikit' ? 'uikit' : 'bootstrap5';

        populateDropdown(dropdown, countries, selectedIso, framework);
        applyMaxLength();

        // Initial sync: if an address country_id select already has a value
        // on page load (e.g. in an Edit Address form), align the phone
        // country flag to it. Otherwise the user would see a mismatched flag
        // until they re-select the country.
        syncFromCountryId();

        dropdown.addEventListener('click', function(e) {
            var item = e.target.closest('[data-iso]');
            if (!item) return;
            selectCountry(item.dataset.iso);
            userChangedCountry = true;
            // Truncate only on an explicit user-triggered country change so
            // the new country's max-length is respected.
            var c = findCountry(selectedIso);
            if (c && nationalInput.value.length > c.max) {
                nationalInput.value = nationalInput.value.slice(0, c.max);
                updateHiddenValue();
                validateLength();
            }
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
            if (country) {
                // Auto-strip trunk prefix 0 for non-NANP countries
                if (country.code !== '1' && nationalInput.value.charAt(0) === '0') {
                    nationalInput.value = nationalInput.value.substring(1);
                }
                if (nationalInput.value.length > country.max) {
                    nationalInput.value = nationalInput.value.slice(0, country.max);
                }
            }
            updateHiddenValue();
            validateLength();
        });

        // Sync phone country when billing/shipping country_id changes
        document.addEventListener('change', function(e) {
            if (userChangedCountry) return;
            var sel = e.target.closest('[name="country_id"], #country_id');
            if (!sel || !sel.value) return;
            var countryMap = (typeof Joomla !== 'undefined' && Joomla.getOptions)
                ? Joomla.getOptions('com_j2commerce.phoneCountryMap') || {}
                : {};
            var iso = countryMap[sel.value];
            if (!iso) return;
            // If the ISO isn't in this widget's allowed countries list, skip
            // silently — the widget may be restricted to a subset.
            if (!findCountry(iso)) return;
            selectCountry(iso);
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
            }
        }

        function syncFromCountryId() {
            var countryMap = (typeof Joomla !== 'undefined' && Joomla.getOptions)
                ? Joomla.getOptions('com_j2commerce.phoneCountryMap') || {}
                : {};
            // Only look inside the same form as the phone field to avoid
            // picking up an unrelated country_id on the page.
            var form = container.closest('form') || document;
            var sel  = form.querySelector('[name="country_id"], #country_id');
            if (!sel || !sel.value) return;
            var iso = countryMap[sel.value];
            if (iso && iso !== selectedIso) {
                selectCountry(iso);
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

        // Strip separators from any legacy stored value so the single-country
        // widget displays clean digits without truncation.
        nationalInput.value = (nationalInput.value || '').replace(/\D/g, '');

        nationalInput.addEventListener('input', function() {
            nationalInput.value = nationalInput.value.replace(/\D/g, '');
            // Auto-strip trunk prefix 0 for non-NANP countries
            if (country.code !== '1' && nationalInput.value.charAt(0) === '0') {
                nationalInput.value = nationalInput.value.substring(1);
            }
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

    function populateDropdown(dropdown, countries, selectedIso, framework) {
        var isUikit = framework === 'uikit';
        var itemClass = isUikit ? 'uk-nav-item j2c-phone-country-item' : 'dropdown-item';
        var dialClass = isUikit ? 'uk-text-muted' : 'text-body-secondary';

        while (dropdown.children.length > 1) {
            dropdown.removeChild(dropdown.lastChild);
        }

        var frag = document.createDocumentFragment();
        for (var i = 0; i < countries.length; i++) {
            var c = countries[i];
            var li = document.createElement('li');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = itemClass + (c.iso2 === selectedIso ? ' active' : '');
            btn.dataset.iso = c.iso2;

            var flagNode;
            if (c.flagUrl) {
                flagNode = document.createElement('img');
                flagNode.src = c.flagUrl;
                flagNode.alt = c.iso2;
                flagNode.className = 'j2c-phone-flag';
            } else {
                flagNode = document.createElement('span');
                flagNode.className = 'j2c-phone-flag';
                flagNode.textContent = c.iso2;
            }
            btn.appendChild(flagNode);
            btn.appendChild(document.createTextNode(' ' + c.name + ' '));

            var dial = document.createElement('span');
            dial.className = dialClass;
            dial.textContent = '+' + c.code;
            btn.appendChild(dial);

            li.appendChild(btn);
            frag.appendChild(li);
        }
        dropdown.appendChild(frag);
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
