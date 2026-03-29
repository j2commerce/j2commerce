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

                // Explicitly set the saved zone value after replacing options
                if (savedZoneId) {
                    zoneEl.value = String(savedZoneId);
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
        if (!preview || !countryId) return;

        const defaults = countryDefaults[countryId];

        if (defaults) {
            const tmpl = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_DEFAULTS_PREVIEW') || '';
            preview.textContent = tmpl
                .replace('%s', defaults.currency || '')
                .replace('%s', defaults.weight_name || '')
                .replace('%s', defaults.length_name || '');
            preview.classList.remove('d-none');
        } else {
            preview.textContent = '';
            preview.classList.add('d-none');
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
        }, 250);
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
            bar.style.width = (activeStep * 20) + '%';
            bar.setAttribute('aria-valuenow', activeStep * 20);
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
            if (activeStep === 1 || activeStep === 5) {
                btnSkip.setAttribute('hidden', '');
            } else {
                btnSkip.removeAttribute('hidden');
            }
        }

        if (btnNext) {
            if (activeStep === 5) {
                btnNext.setAttribute('hidden', '');
            } else {
                btnNext.removeAttribute('hidden');
                if (labelEl) {
                    if (activeStep === 4) {
                        labelEl.textContent = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_BTN_FINISH');
                    } else {
                        labelEl.textContent = Joomla.Text._('COM_J2COMMERCE_ONBOARDING_BTN_CONTINUE');
                    }
                }
            }
        }

        if (footer) {
            if (activeStep === 5) {
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

        // Step 4: collect selected product type cards
        if (stepNum === 4) {
            const selectedCards = modal.querySelectorAll('.j2c-product-type-card.selected');
            const types = [];
            selectedCards.forEach(card => {
                const t = card.dataset.productType;
                if (t) types.push(t);
            });
            formData.append('product_types', types.join(','));
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

    async function handlePostSave(stepNum, data) {
        switch (stepNum) {
            case 1: {
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
                // Auto-save step 5 to mark complete
                await saveStep(5);
                break;
            }
            case 5: {
                buildSummary(data);
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
                if (currentStep > 1 && currentStep < 5) {
                    goToStep(currentStep + 1, 'forward');
                }
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
        }
    });

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
        });
    }

    // -------------------------------------------------------------------------
    // Currency mode toggle
    // -------------------------------------------------------------------------

    modal.addEventListener('change', (e) => {
        if (e.target.name === 'currency_mode') {
            const multiNote = modal.querySelector('#ob-currency-multi-note');
            if (multiNote) {
                multiNote.classList.toggle('d-none', e.target.value !== 'multi');
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
