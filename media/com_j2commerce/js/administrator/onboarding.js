/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC
 * @license     GNU General Public License version 2 or later
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('j2commerceOnboardingModal');
    if (!modal) return;

    const options         = Joomla.getOptions('com_j2commerce.onboarding') || {};
    const token           = document.getElementById('ob-token')?.value || '';
    const currencyMeta    = options.currencyMeta || {};
    const countryDefaults = options.countryDefaults || {};
    const zoneAjaxUrl     = options.zoneAjaxUrl || '';

    let currentStep = parseInt(document.getElementById('ob-resume-step')?.value || '1', 10) || 1;

    const modalInstance = bootstrap.Modal.getOrCreateInstance(modal);
    modalInstance.show();

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    function showError(msg) {
        const area = modal.querySelector('#ob-alert-area');
        if (!area) return;
        area.replaceChildren();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mb-3';
        alert.setAttribute('role', 'alert');
        alert.textContent = msg;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-close';
        btn.setAttribute('data-bs-dismiss', 'alert');
        btn.setAttribute('aria-label', 'Close');
        alert.appendChild(btn);
        area.appendChild(alert);
        area.classList.remove('d-none');
    }

    function clearError() {
        const area = modal.querySelector('#ob-alert-area');
        if (!area) return;
        area.replaceChildren();
        area.classList.add('d-none');
    }

    function toggleElement(selector, show) {
        const el = modal.querySelector(selector);
        if (el) el.classList.toggle('d-none', !show);
    }

    function setNextButtonSpinner(loading) {
        const btn = modal.querySelector('#ob-btn-next');
        if (!btn) return;
        const spinner = btn.querySelector('.spinner-border');
        const label   = btn.querySelector('.btn-label');
        if (loading) {
            btn.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (label) label.textContent = '';
        } else {
            btn.disabled = false;
            if (spinner) spinner.classList.add('d-none');
        }
    }

    // -------------------------------------------------------------------------
    // Zone loading
    // -------------------------------------------------------------------------

    async function loadZones(countryId, savedZoneId) {
        if (!zoneAjaxUrl || !countryId) return;
        try {
            const url      = `${zoneAjaxUrl}&country_id=${encodeURIComponent(countryId)}&zone_id=${encodeURIComponent(savedZoneId || 0)}`;
            const response = await fetch(url);
            if (!response.ok) return;
            const html = await response.text();
            const zoneEl = modal.querySelector('#ob-zone');
            if (zoneEl) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(`<select>${html}</select>`, 'text/html');
                const newOptions = doc.querySelectorAll('option');
                zoneEl.replaceChildren(...newOptions);

                // Explicitly set the zone value after replacing options
                if (savedZoneId) {
                    zoneEl.value = String(savedZoneId);
                } else {
                    zoneEl.selectedIndex = 0;
                }
            }
        } catch {
            // Zone loading failure is non-fatal
        }
    }

    // -------------------------------------------------------------------------
    // Defaults preview
    // -------------------------------------------------------------------------

    function updateDefaultsPreview(countryId) {
        const preview = modal.querySelector('#ob-defaults-preview');
        const currencyPreview = modal.querySelector('#ob-currency-defaults-preview');

        const defaults = countryId ? countryDefaults[countryId] : null;

        if (preview) {
            if (defaults) {
                const tmpl = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_DEFAULTS_PREVIEW') || '';
                preview.textContent = tmpl
                    .replace('%s', defaults.weight_name || '')
                    .replace('%s', defaults.length_name || '');
                preview.classList.remove('d-none');
            } else {
                preview.textContent = '';
                preview.classList.add('d-none');
            }
        }

        if (currencyPreview) {
            if (defaults && defaults.currency) {
                const tmpl2 = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_DEFAULTS_PREVIEW_CURRENCY') || '';
                currencyPreview.textContent = tmpl2.replace('%s', defaults.currency);
                currencyPreview.classList.remove('d-none');
            } else {
                currencyPreview.textContent = '';
                currencyPreview.classList.add('d-none');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Step navigation
    // -------------------------------------------------------------------------

    function goToStep(targetStep, direction) {
        const oldStep  = currentStep;
        const oldEl    = modal.querySelector(`[data-step="${oldStep}"]`);
        const newEl    = modal.querySelector(`[data-step="${targetStep}"]`);
        if (!oldEl || !newEl) return;

        const leavingClass  = direction === 'forward' ? 'leaving-left' : 'leaving-right';
        const enteringClass = direction === 'forward' ? 'entering-right' : 'entering-left';

        oldEl.classList.add(leavingClass);

        setTimeout(() => {
            oldEl.setAttribute('hidden', '');
            oldEl.classList.remove(leavingClass);

            newEl.removeAttribute('hidden');
            newEl.classList.add(enteringClass);

            // Force reflow so CSS transition triggers
            void newEl.offsetWidth;
            newEl.classList.remove(enteringClass);

            currentStep = targetStep;
            updateStepper(targetStep);
            updateButtons(targetStep);
            initStep(targetStep);
        }, 250);
    }

    function initStep(stepNum) {
        if (stepNum === 5) {
            // Initialize PayPal section visibility based on pre-checked state
            updatePaypalSection();
            updateDefaultPaymentDropdown();
        }
    }

    function updateStepper(activeStep) {
        const indicators = modal.querySelectorAll('[data-step-indicator]');
        const connectors = modal.querySelectorAll('[data-connector]');

        indicators.forEach((el, index) => {
            const step = index + 1;
            const innerIndicator = el.querySelector('.j2c-step-indicator');
            if (!innerIndicator) return;
            innerIndicator.classList.remove('active', 'completed', 'upcoming');
            innerIndicator.removeAttribute('aria-current');

            if (step < activeStep) {
                innerIndicator.classList.add('completed');
                const checkSpan = document.createElement('span');
                checkSpan.className = 'fa-solid fa-check';
                checkSpan.setAttribute('aria-hidden', 'true');
                innerIndicator.textContent = '';
                innerIndicator.appendChild(checkSpan);
            } else if (step === activeStep) {
                innerIndicator.classList.add('active');
                innerIndicator.setAttribute('aria-current', 'step');
            } else {
                innerIndicator.classList.add('upcoming');
            }
        });

        connectors.forEach((el, index) => {
            const segmentStep = index + 1;
            if (segmentStep < activeStep) {
                el.classList.add('completed');
            } else {
                el.classList.remove('completed');
            }
        });

        const bar = modal.querySelector('#ob-progress-bar');
        if (bar) {
            const pct = Math.round(activeStep * 100 / 6);
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', pct);
        }
    }

    function updateButtons(activeStep) {
        const btnBack   = modal.querySelector('#ob-btn-back');
        const btnSkip   = modal.querySelector('#ob-btn-skip');
        const btnNext   = modal.querySelector('#ob-btn-next');
        const footer    = modal.querySelector('#ob-footer');
        const labelEl   = btnNext ? btnNext.querySelector('.btn-label') : null;

        if (btnBack) {
            if (activeStep === 1) {
                btnBack.setAttribute('hidden', '');
            } else {
                btnBack.removeAttribute('hidden');
            }
        }

        if (btnSkip) {
            if (activeStep === 1 || activeStep === 6) {
                btnSkip.setAttribute('hidden', '');
            } else {
                btnSkip.removeAttribute('hidden');
            }
        }

        if (btnNext) {
            if (activeStep === 6) {
                btnNext.setAttribute('hidden', '');
            } else {
                btnNext.removeAttribute('hidden');
                if (labelEl) {
                    if (activeStep === 5) {
                        labelEl.textContent = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_BTN_FINISH');
                    } else {
                        labelEl.textContent = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_BTN_CONTINUE');
                    }
                }
            }
        }

        if (footer) {
            if (activeStep === 6) {
                footer.setAttribute('hidden', '');
            } else {
                footer.removeAttribute('hidden');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    function validateStep(stepNum) {
        if (stepNum !== 1) return true;

        let valid = true;

        const storeName = modal.querySelector('#ob-store-name');
        if (storeName) {
            if (!storeName.value.trim()) {
                storeName.classList.add('is-invalid');
                valid = false;
            } else {
                storeName.classList.remove('is-invalid');
            }
        }

        const country = modal.querySelector('#ob-country');
        if (country) {
            if (!country.value) {
                country.classList.add('is-invalid');
                valid = false;
            } else {
                country.classList.remove('is-invalid');
            }
        }

        const zone = modal.querySelector('#ob-zone');
        if (zone) {
            if (!zone.value) {
                zone.classList.add('is-invalid');
                valid = false;
            } else {
                zone.classList.remove('is-invalid');
            }
        }

        return valid;
    }

    // -------------------------------------------------------------------------
    // AJAX save
    // -------------------------------------------------------------------------

    async function saveStep(stepNum) {
        clearError();
        setNextButtonSpinner(true);

        const stepEl   = modal.querySelector(`[data-step="${stepNum}"]`);
        const formData = new FormData();

        if (stepEl) {
            const inputs = stepEl.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (!input.name) return;
                if (input.type === 'checkbox' || input.type === 'radio') {
                    if (input.checked) formData.append(input.name, input.value);
                } else {
                    formData.append(input.name, input.value);
                }
            });
        }

        // Step 4: collect selected product type cards + shipping rates
        if (stepNum === 4) {
            const selectedCards = modal.querySelectorAll('.j2c-product-type-card.selected');
            const types = [];
            selectedCards.forEach(card => {
                const t = card.dataset.productType;
                if (t) types.push(t);
            });
            formData.append('product_types', types.join(','));
            formData.append('shipping_rates', collectRates());
        }

        // Step 5: collect selected payment plugins
        if (stepNum === 5) {
            const checked = modal.querySelectorAll('#ob-payment-list input:checked');
            const plugins = [];
            checked.forEach(cb => plugins.push(cb.value));
            formData.append('selected_payment_plugins', plugins.join(','));
        }

        formData.append(token, '1');
        formData.append('step', stepNum);

        try {
            const response = await fetch('index.php?option=com_j2commerce&task=onboarding.saveStep&format=json', {
                method: 'POST',
                body:   formData,
            });

            if (!response.ok) {
                throw new Error(Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
            }

            const json = await response.json();

            if (!json.success) {
                throw new Error(json.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
            }

            await handlePostSave(stepNum, json.data || {});

        } catch (err) {
            showError(err.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
        } finally {
            setNextButtonSpinner(false);
        }
    }

    // -------------------------------------------------------------------------
    // Per-step post-save logic
    // -------------------------------------------------------------------------

    function applyDefaults(defaults) {
        if (!defaults) return;

        // Only apply country-based defaults on a fresh onboarding (step 1).
        // If the user already completed step 2+ previously, the saved config
        // values are already rendered in the dropdowns — don't overwrite them.
        const resumeStep = parseInt(document.getElementById('ob-resume-step')?.value || '1', 10);

        if (resumeStep > 1) return;

        const currencyEl = modal.querySelector('#ob-currency');
        const weightEl   = modal.querySelector('#ob-weight');
        const lengthEl   = modal.querySelector('#ob-length');
        if (currencyEl && defaults.currency) currencyEl.value = defaults.currency;
        if (weightEl   && defaults.weight_id) weightEl.value = defaults.weight_id;
        if (lengthEl   && defaults.length_id) lengthEl.value = defaults.length_id;
    }

    function populateGeozoneDropdowns(geozone) {
        if (!geozone || !geozone.geozone_id) return;
        modal.querySelectorAll('.ob-rate-geozone').forEach(select => {
            // Clear existing options and add the new geozone
            select.replaceChildren();
            const opt = document.createElement('option');
            opt.value = geozone.geozone_id;
            opt.textContent = geozone.geozone_name;
            select.appendChild(opt);
        });
    }

    async function handlePostSave(stepNum, data) {
        switch (stepNum) {
            case 1: {
                populateGeozoneDropdowns(data.geozone);
                if (data.languagePrompt === true) {
                    const prompt = modal.querySelector('#ob-lang-prompt');
                    if (prompt) prompt.classList.remove('d-none');
                    // Do NOT advance yet — wait for install-lang or skip-lang click
                } else {
                    applyDefaults(data.defaults);
                    goToStep(2, 'forward');
                }
                break;
            }
            case 2: {
                const infoArea = modal.querySelector('#ob-step2-info');
                if (infoArea && (data.weightTitle || data.lengthTitle)) {
                    const parts = [];
                    if (data.weightTitle) parts.push(data.weightTitle);
                    if (data.lengthTitle)  parts.push(data.lengthTitle);
                    infoArea.textContent = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_MEASUREMENTS_SYNCED') + ' ' + parts.join(', ');
                    infoArea.classList.remove('d-none');
                }
                goToStep(3, 'forward');
                break;
            }
            case 3: {
                goToStep(4, 'forward');
                break;
            }
            case 4: {
                goToStep(5, 'forward');
                break;
            }
            case 5: {
                goToStep(6, 'forward');
                // Auto-save step 6 to mark complete
                await saveStep(6);
                break;
            }
            case 6: {
                buildSummary(data);
                const sdPrompt = modal.querySelector('#ob-sampledata-prompt');
                if (sdPrompt) sdPrompt.classList.remove('d-none');
                break;
            }
        }
    }

    function buildSummary(data) {
        const summaryEl = modal.querySelector('#ob-summary');
        if (!summaryEl) return;

        const rows = [];
        if (data.storeName)     rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_STORE'),        data.storeName]);
        if (data.currency)      rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_CURRENCY'),     data.currency]);
        if (data.measurements)  rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_MEASUREMENTS'), data.measurements]);
        if (data.tax)           rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_TAX'),          data.tax]);
        if (data.productTypes)  rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_PRODUCTS'),     data.productTypes]);
        if (data.shipping)      rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_SHIPPING'),     data.shipping]);
        if (data.payment)       rows.push([Joomla.Text._('COM_J2COMMERCE_ONBOARDING_READY_SUMMARY_PAYMENT'),      data.payment]);

        const table = document.createElement('table');
        table.className = 'table table-sm table-bordered';
        const tbody = document.createElement('tbody');

        rows.forEach(([label, value]) => {
            const tr = document.createElement('tr');
            const th = document.createElement('th');
            th.className = 'w-40';
            th.textContent = label;
            const td = document.createElement('td');
            td.textContent = value;
            tr.append(th, td);
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        summaryEl.replaceChildren(table);
    }

    // -------------------------------------------------------------------------
    // Event delegation
    // -------------------------------------------------------------------------

    modal.addEventListener('click', async (e) => {
        const action = e.target.closest('[data-action]')?.dataset?.action;

        // Product type card selection (no data-action, handled by class)
        const card = e.target.closest('.j2c-product-type-card');
        if (card) {
            card.classList.toggle('selected');
            const isSelected = card.classList.contains('selected');
            card.setAttribute('aria-checked', isSelected ? 'true' : 'false');

            const productType = card.dataset.productType;
            if (productType === 'subscription') {
                const note = modal.querySelector('#ob-note-subscription');
                if (note) note.classList.toggle('d-none', !isSelected);
            }
            if (productType === 'digital') {
                const note = modal.querySelector('#ob-note-digital');
                if (note) note.classList.toggle('d-none', !isSelected);
            }
            if (productType === 'physical') {
                const note = modal.querySelector('#ob-shipping-question');
                if (note) note.classList.toggle('d-none', !isSelected);

                // Since "Yes" is pre-selected, show shipping sub-sections immediately
                const shippingYes = modal.querySelector('#ob-shipping-yes');
                const requireShipping = isSelected && shippingYes && shippingYes.checked;
                toggleElement('#ob-free-shipping-question', requireShipping);
                toggleElement('#ob-shipping-type-question', requireShipping);

                // Hide everything when Physical deselected
                if (!isSelected) {
                    toggleElement('#ob-free-shipping-config', false);
                    toggleElement('#ob-fixed-shipping-config', false);
                    toggleElement('#ob-calculated-shipping-info', false);
                }
            }
            return;
        }

        if (!action) return;

        switch (action) {
            case 'next': {
                if (!validateStep(currentStep)) return;
                await saveStep(currentStep);
                break;
            }
            case 'back': {
                if (currentStep > 1) {
                    goToStep(currentStep - 1, 'backward');
                }
                break;
            }
            case 'skip': {
                if (currentStep > 1 && currentStep < 6) {
                    goToStep(currentStep + 1, 'forward');
                }
                break;
            }
            case 'close-onboarding': {
                e.preventDefault();
                modalInstance.hide();
                break;
            }
            case 'dismiss-onboarding': {
                const confirmed = window.confirm(Joomla.Text._('COM_J2COMMERCE_ONBOARDING_DISMISS_CONFIRM'));
                if (!confirmed) return;

                const fd = new FormData();
                fd.append(token, '1');

                try {
                    const res = await fetch('index.php?option=com_j2commerce&task=onboarding.dismiss&format=json', {
                        method: 'POST',
                        body:   fd,
                    });
                    if (!res.ok) throw new Error();
                    const json = await res.json();
                    if (json.success) {
                        modalInstance.hide();
                    } else {
                        showError(json.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
                    }
                } catch {
                    showError(Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
                }
                break;
            }
            case 'install-lang': {
                const btn = e.target.closest('[data-action="install-lang"]');
                const originalText = btn?.textContent?.trim() || '';
                if (btn) {
                    btn.disabled = true;
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm me-1';
                    spinner.setAttribute('role', 'status');
                    btn.prepend(spinner);
                }

                const fd = new FormData();
                fd.append(token, '1');
                fd.append('lang', 'en-US');

                try {
                    const res = await fetch('index.php?option=com_j2commerce&task=onboarding.installLanguage&format=json', {
                        method: 'POST',
                        body:   fd,
                    });
                    if (!res.ok) throw new Error();
                    const json = await res.json();
                    const prompt = modal.querySelector('#ob-lang-prompt');
                    if (json.success) {
                        if (prompt) {
                            const successAlert = document.createElement('div');
                            successAlert.className = 'alert alert-success';
                            successAlert.textContent = json.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_LANG_SUCCESS');
                            prompt.replaceChildren(successAlert);
                        }
                        applyDefaults(json.data?.defaults);
                        goToStep(2, 'forward');
                    } else {
                        throw new Error(json.message);
                    }
                } catch (err) {
                    if (btn) {
                        btn.disabled = false;
                        const spinner = btn.querySelector('.spinner-border');
                        if (spinner) spinner.remove();
                        btn.textContent = originalText;
                    }
                    showError(err.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
                }
                break;
            }
            case 'skip-lang': {
                const prompt = modal.querySelector('#ob-lang-prompt');
                if (prompt) prompt.classList.add('d-none');
                const defaults = options.defaults || {};
                applyDefaults(defaults);
                goToStep(2, 'forward');
                break;
            }
            case 'load-sampledata': {
                const btn = e.target.closest('[data-action="load-sampledata"]');
                if (!btn) break;
                btn.disabled = true;
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm me-1';
                spinner.setAttribute('role', 'status');
                btn.prepend(spinner);

                const fd = new FormData();
                fd.append(token, '1');

                try {
                    const res  = await fetch('index.php?option=com_j2commerce&task=dashboard.loadSampleData&format=json', { method: 'POST', body: fd });
                    const json = await res.json();
                    const sdPrompt = modal.querySelector('#ob-sampledata-prompt');
                    if (json.success) {
                        if (sdPrompt) {
                            const successAlert = document.createElement('div');
                            successAlert.className = 'alert alert-success mt-3';
                            successAlert.textContent = Joomla.Text._('COM_J2COMMERCE_DASHBOARD_SAMPLEDATA_LOADED');
                            sdPrompt.replaceChildren(successAlert);
                        }
                    } else {
                        btn.disabled = false;
                        spinner.remove();
                        showError(json.message || Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
                    }
                } catch {
                    btn.disabled = false;
                    spinner.remove();
                    showError(Joomla.Text._('COM_J2COMMERCE_ONBOARDING_ERR_SAVE'));
                }
                break;
            }
            case 'skip-sampledata': {
                const sdPrompt = modal.querySelector('#ob-sampledata-prompt');
                if (sdPrompt) sdPrompt.classList.add('d-none');
                break;
            }
        }
    });

    // -------------------------------------------------------------------------
    // Shipping sub-flow handlers
    // -------------------------------------------------------------------------

    modal.addEventListener('change', (e) => {
        // Show/hide shipping sub-sections when require_shipping changes
        if (e.target.name === 'require_shipping') {
            const show = e.target.value === '1';
            toggleElement('#ob-free-shipping-question', show);
            toggleElement('#ob-shipping-type-question', show);
            if (!show) {
                toggleElement('#ob-free-shipping-config', false);
                toggleElement('#ob-fixed-shipping-config', false);
                toggleElement('#ob-calculated-shipping-info', false);
            }
        }

        // Show/hide min subtotal when free shipping answer changes
        if (e.target.name === 'offer_free_shipping') {
            toggleElement('#ob-free-shipping-config', e.target.value === '1');
        }

        // Show/hide fixed vs calculated sections
        if (e.target.name === 'shipping_rate_type') {
            const val = e.target.value;
            toggleElement('#ob-fixed-shipping-config', val === 'fixed' || val === 'both');
            toggleElement('#ob-calculated-shipping-info', val === 'calculated' || val === 'both');
        }

        // Show/hide range columns based on shipping method type
        if (e.target.name === 'shipping_method_type') {
            const rangeTypes = [1, 2, 4, 5, 6];
            const showRange = rangeTypes.includes(parseInt(e.target.value, 10));
            modal.querySelectorAll('.ob-rate-range').forEach(el => {
                el.classList.toggle('d-none', !showRange);
            });
        }

        // Payment checkbox changes
        if (e.target.closest('#ob-payment-list')) {
            updateDefaultPaymentDropdown();
            updatePaypalSection();

            // Show warning if all unchecked
            const anyChecked = modal.querySelectorAll('#ob-payment-list input:checked').length > 0;
            toggleElement('#ob-payment-none-warning', !anyChecked);
        }

        // PayPal keys status radio
        if (e.target.name === 'paypal_keys_status') {
            toggleElement('#ob-paypal-keys', e.target.value === 'have_keys');
            toggleElement('#ob-paypal-help-info', e.target.value === 'need_help');
        }
    });

    // -------------------------------------------------------------------------
    // Payment helpers
    // -------------------------------------------------------------------------

    function updateDefaultPaymentDropdown() {
        const checked = modal.querySelectorAll('#ob-payment-list input:checked');
        const select  = modal.querySelector('#ob-default-payment');
        if (!select) return;

        const currentVal = select.value;
        // Keep the placeholder option
        const placeholder = select.querySelector('option[value=""]');
        select.replaceChildren();
        if (placeholder) select.appendChild(placeholder);

        checked.forEach(cb => {
            const opt = document.createElement('option');
            opt.value = cb.value;
            opt.textContent = cb.dataset.label;
            select.appendChild(opt);
        });

        // Restore selection if still valid
        if ([...select.options].some(o => o.value === currentVal)) {
            select.value = currentVal;
        }

        // Auto-select if only one real option
        if (checked.length === 1) {
            select.value = checked[0].value;
        }

        toggleElement('#ob-default-payment-section', checked.length > 0);
    }

    function updatePaypalSection() {
        const paypalCb = modal.querySelector('#ob-payment-list input[value="payment_paypal"]');
        const paypalChecked = paypalCb ? paypalCb.checked : false;
        toggleElement('#ob-paypal-config', paypalChecked);
        if (!paypalChecked) {
            toggleElement('#ob-paypal-keys', false);
            toggleElement('#ob-paypal-help-info', false);
            // Reset radio selection
            const radios = modal.querySelectorAll('input[name="paypal_keys_status"]');
            radios.forEach(r => { r.checked = false; });
        }
    }

    // -------------------------------------------------------------------------
    // Rate row management
    // -------------------------------------------------------------------------

    let rateRowCount = 1;
    const MAX_RATE_ROWS = 10;

    // Shipping rate + payment enable (delegated click handler)
    modal.addEventListener('click', (e) => {
        // "Enable Payment Methods" button — show the unpublished plugins list
        if (e.target.closest('[data-action="enable-payment-plugins"]')) {
            e.preventDefault();
            toggleElement('#ob-payment-enable-list', true);
            return;
        }

        // "Enable Selected" — move checked plugins to the main payment list
        if (e.target.closest('[data-action="confirm-enable-payment"]')) {
            e.preventDefault();
            const enableList = modal.querySelector('#ob-payment-enable-list');
            const mainList   = modal.querySelector('#ob-payment-list');
            if (!enableList || !mainList) return;

            const checked = enableList.querySelectorAll('input:checked');
            if (checked.length === 0) return;

            checked.forEach(cb => {
                const div = document.createElement('div');
                div.className = 'form-check';

                const input = document.createElement('input');
                input.className = 'form-check-input';
                input.type = 'checkbox';
                input.name = 'payment_plugins[]';
                input.value = cb.value;
                input.id = 'ob-pay-' + cb.value;
                input.dataset.label = cb.dataset.label;
                input.checked = true;

                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = input.id;
                label.textContent = cb.dataset.label;

                div.appendChild(input);
                div.appendChild(label);
                mainList.appendChild(div);

                // Remove from the enable list
                cb.closest('.form-check').remove();
            });

            // Show the main list, hide warning
            mainList.classList.remove('d-none');
            toggleElement('#ob-payment-none-warning', false);

            // Update default dropdown and PayPal section
            updateDefaultPaymentDropdown();
            updatePaypalSection();
            return;
        }

        if (e.target.closest('[data-action="add-rate"]')) {
            e.preventDefault();
            addRateRow();
            return;
        }
        if (e.target.closest('[data-action="remove-rate"]')) {
            e.preventDefault();
            const row = e.target.closest('tr');
            if (row) {
                row.remove();
                rateRowCount--;
            }
            return;
        }
    });

    function addRateRow() {
        if (rateRowCount >= MAX_RATE_ROWS) return;
        rateRowCount++;

        const tbody = modal.querySelector('#ob-rates-body');
        if (!tbody) return;

        const firstRow = tbody.querySelector('tr');
        if (!firstRow) return;

        const row = firstRow.cloneNode(true);
        row.dataset.rateRow = rateRowCount;
        row.querySelectorAll('input[type="number"]').forEach(input => { input.value = '0'; });

        // Add remove button in the last cell
        const lastCell = row.querySelector('td:last-child');
        if (lastCell) {
            lastCell.innerHTML = '';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-link text-danger shadow-none';
            btn.dataset.action = 'remove-rate';
            btn.innerHTML = '<span class="fa-solid fa-trash-can" aria-hidden="true"></span>';
            lastCell.appendChild(btn);
        }

        tbody.appendChild(row);
    }

    function collectRates() {
        const rows = modal.querySelectorAll('#ob-rates-body tr[data-rate-row]');
        const rates = [];

        rows.forEach(row => {
            const geozone    = row.querySelector('.ob-rate-geozone');
            const price      = row.querySelector('.ob-rate-price');
            const handling   = row.querySelector('.ob-rate-handling');
            const weightStart = row.querySelector('.ob-rate-weight-start');
            const weightEnd   = row.querySelector('.ob-rate-weight-end');

            rates.push({
                geozone_id:   parseInt(geozone?.value || '0', 10),
                price:        parseFloat(price?.value || '0'),
                handling:     parseFloat(handling?.value || '0'),
                weight_start: parseFloat(weightStart?.value || '0'),
                weight_end:   parseFloat(weightEnd?.value || '0'),
            });
        });

        return JSON.stringify(rates);
    }

    // -------------------------------------------------------------------------
    // Keyboard support for product type cards
    // -------------------------------------------------------------------------

    modal.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('.j2c-product-type-card')) {
            e.preventDefault();
            e.target.closest('.j2c-product-type-card').click();
        }
    });

    // -------------------------------------------------------------------------
    // Country change handler
    // -------------------------------------------------------------------------

    const countryEl = modal.querySelector('#ob-country');
    if (countryEl) {
        countryEl.addEventListener('change', () => {
            const countryId = countryEl.value;
            loadZones(countryId, 0);
            updateDefaultsPreview(countryId);

            const zoneEl = modal.querySelector('#ob-zone');
            if (zoneEl) zoneEl.classList.remove('is-invalid');
        });
    }

    // -------------------------------------------------------------------------
    // Currency mode toggle
    // -------------------------------------------------------------------------

    modal.addEventListener('change', (e) => {
        if (e.target.name === 'currency_mode') {
            const multiNote = modal.querySelector('#ob-currency-multi-note');
            const singleNote = modal.querySelector('#ob-currency-single-note');
            if (multiNote) {
                multiNote.classList.toggle('d-none', e.target.value !== 'multi');
            }
            if (singleNote) {
                singleNote.classList.toggle('d-none', e.target.value !== 'single');
            }
        }
    });

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    const initialCountry = countryEl?.value || '';
    const savedZoneId    = options.savedZoneId || 0;

    if (initialCountry) {
        loadZones(initialCountry, savedZoneId);
        updateDefaultsPreview(initialCountry);
    }

    updateStepper(currentStep);
    updateButtons(currentStep);

    // Show the correct step on resume
    modal.querySelectorAll('[data-step]').forEach(el => {
        const step = parseInt(el.dataset.step, 10);
        if (step === currentStep) {
            el.removeAttribute('hidden');
        } else {
            el.setAttribute('hidden', '');
        }
    });
});
