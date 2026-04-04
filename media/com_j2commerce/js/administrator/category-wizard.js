'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('j2commerceCategoryWizardModal');
    if (!modalEl) return;

    const wizard = new CategoryWizard(modalEl);
    wizard.init();
});

class CategoryWizard {
    constructor(el) {
        this.el         = el;
        this.modal      = null;
        this.baseUrl    = 'index.php?option=com_j2commerce';
        this.tokenInput = document.getElementById('j2c-wizard-token');

        // UI refs
        this.errorEl      = el.querySelector('#j2c-wizard-error');
        this.stepIndicator = el.querySelector('#j2c-wizard-step-indicator');
        this.btnBack      = el.querySelector('#j2c-wizard-back');
        this.btnNext      = el.querySelector('#j2c-wizard-next');
        this.btnCreate    = el.querySelector('#j2c-wizard-create');
        this.btnDone      = el.querySelector('#j2c-wizard-done');

        // Wizard state
        this.flow           = null;    // 'single' | 'multi'
        this.currentStepKey = 'step1';
        this.stepHistory    = [];

        // Data collected across steps
        this.data = {
            productCount:    null,
            productName:     '',
            productType:     null,
            optionTitles:    [],
            optionValues:    {},
            categoryCount:   null,
            singleCategory:  true,
            menuType:        'categories',
            categoryNames:   [],
            rootCategoryName: 'Shop',
            subtemplate:     'bootstrap5',
        };

        // Template detection result
        this.templateInfo = {
            yoothemeInstalled:     false,
            availableSubtemplates: ['bootstrap5'],
            defaultSubtemplate:    'bootstrap5',
        };

        // Created resource data from server
        this.createdData = null;

        // Step sequence maps (dynamic)
        this.SINGLE_FLOW = ['step1', '2a', '3a', 'confirm', 'success'];
        this.SINGLE_WITH_OPTIONS_FLOW = ['step1', '2a', '3a', '3b', '3c', 'confirm', 'success'];
        this.MULTI_FLOW_SINGLE = ['step1', '2b', 'category-naming', 'confirm', 'success'];
        this.MULTI_FLOW_MULTI  = ['step1', '2b', '3b-multi', 'category-naming', 'confirm', 'success'];
    }

    init() {
        this.modal = bootstrap.Modal.getOrCreateInstance(this.el);

        // Detect template on modal open (once)
        let templateDetected = false;
        this.el.addEventListener('show.bs.modal', () => {
            if (!templateDetected) {
                templateDetected = true;
                this.detectTemplate();
            }
            this.reset();
        });

        // Refresh setup guide when wizard closes after successful creation
        this.el.addEventListener('hidden.bs.modal', () => {
            if (this.createdData) {
                document.dispatchEvent(new CustomEvent('j2commerce:wizard:complete'));
            }
        });

        // Back / Next / Create / Done buttons
        this.btnBack.addEventListener('click', () => this.goBack());
        this.btnNext.addEventListener('click', () => this.goNext());
        this.btnCreate.addEventListener('click', () => this.executeCreation());

        // Add option title
        const addOptionBtn = this.el.querySelector('#j2c-add-option-title');
        if (addOptionBtn) {
            addOptionBtn.addEventListener('click', () => this.addOptionTitleRow());
        }

        // Add category
        const addCategoryBtn = this.el.querySelector('#j2c-add-category');
        if (addCategoryBtn) {
            addCategoryBtn.addEventListener('click', () => this.addCategoryNameRow());
        }

        // Event delegation for remove buttons and card selection
        this.el.addEventListener('click', (e) => {
            const removeOptionBtn = e.target.closest('[data-remove-option-title]');
            if (removeOptionBtn) {
                this.removeOptionTitleRow(removeOptionBtn.closest('.j2c-option-title-row'));
                return;
            }

            const removeValueBtn = e.target.closest('[data-remove-option-value]');
            if (removeValueBtn) {
                this.removeOptionValueRow(removeValueBtn.closest('.j2c-option-value-row'));
                return;
            }

            const addValueBtn = e.target.closest('[data-add-option-value]');
            if (addValueBtn) {
                const groupKey = addValueBtn.dataset.addOptionValue;
                this.addOptionValueRow(groupKey);
                return;
            }

            const removeCatBtn = e.target.closest('[data-remove-category]');
            if (removeCatBtn) {
                this.removeCategoryNameRow(removeCatBtn.closest('.j2c-category-name-row'));
                return;
            }
        });

        // Card radio selection — apply/remove 'selected' class
        this.el.addEventListener('change', (e) => {
            const radio = e.target;
            if (radio.type !== 'radio') return;

            const cards = this.el.querySelectorAll(`input[name="${radio.name}"]`);
            cards.forEach((r) => {
                r.closest('.j2c-wizard-card')?.classList.remove('selected');
            });
            radio.closest('.j2c-wizard-card')?.classList.add('selected');

            // When product_type changes, update flow decision
            if (radio.name === 'product_type') {
                this.data.productType = radio.value;
            }

            this.updateNextButton();
        });

        // Enable/disable Next based on product name input
        const productNameInput = this.el.querySelector('#j2c-product-name');
        if (productNameInput) {
            productNameInput.addEventListener('input', () => {
                this.updateNextButton();
            });
        }
    }

    // =========================================================================
    // Navigation
    // =========================================================================

    reset() {
        this.flow           = null;
        this.currentStepKey = 'step1';
        this.stepHistory    = [];
        this.data           = {
            productCount:    null,
            productName:     '',
            productType:     null,
            optionTitles:    [],
            optionValues:    {},
            categoryCount:   null,
            singleCategory:  true,
            menuType:        'categories',
            categoryNames:   [],
            rootCategoryName: 'Shop',
            subtemplate:     'bootstrap5',
        };
        this.createdData = null;

        this.hideError();
        this.showStep('step1');
        this.renderOptionTitles(true);
    }

    showStep(stepKey) {
        this.el.querySelectorAll('.j2c-wizard-step').forEach((el) => {
            el.classList.add('d-none');
        });

        const stepEl = this.el.querySelector(`[data-step="${stepKey}"]`);
        if (stepEl) {
            stepEl.classList.remove('d-none');
        }

        this.currentStepKey = stepKey;
        this.updateNavButtons();
        this.updateStepIndicator();
        this.hideError();
    }

    goNext() {
        if (!this.validateCurrentStep()) return;

        this.collectStepData();

        const nextKey = this.getNextStepKey();
        if (!nextKey) return;

        this.stepHistory.push(this.currentStepKey);
        this.prepareStep(nextKey);
        this.showStep(nextKey);
    }

    goBack() {
        if (this.stepHistory.length === 0) return;
        const prevKey = this.stepHistory.pop();
        this.showStep(prevKey);
    }

    getNextStepKey() {
        const flow = this.getFlowSequence();
        const idx  = flow.indexOf(this.currentStepKey);
        if (idx < 0 || idx >= flow.length - 1) return null;
        return flow[idx + 1];
    }

    getFlowSequence() {
        if (this.flow === 'single') {
            const needsOptions = this.data.productType === 'variable' ||
                (this.data.productType === 'simple' && this.data.productType !== 'downloadable');

            // We decide at step 3a whether to include option steps
            // The flow is determined once productType is set
            const includeOptionSteps = this.data.productType === 'variable' || this.data.productType === 'simple';
            const hasTemplate = this.templateInfo.yoothemeInstalled &&
                this.templateInfo.availableSubtemplates.includes('uikit');

            let seq = ['step1', '2a', '3a'];

            if (includeOptionSteps) {
                seq.push('3b', '3c');
            }

            if (hasTemplate) {
                seq.push('template');
            }

            seq.push('confirm', 'success');
            return seq;
        }

        if (this.flow === 'multi') {
            const hasTemplate = this.templateInfo.yoothemeInstalled &&
                this.templateInfo.availableSubtemplates.includes('uikit');

            let seq = ['step1', '2b'];

            if (!this.data.singleCategory) {
                seq.push('3b-multi');
            }

            seq.push('category-naming');

            if (hasTemplate) {
                seq.push('template');
            }

            seq.push('confirm', 'success');
            return seq;
        }

        // Before flow is decided, just step1
        return ['step1'];
    }

    // =========================================================================
    // Step preparation (populate dynamic content before showing)
    // =========================================================================

    prepareStep(stepKey) {
        switch (stepKey) {
            case '2b':
                // Clear category count selection
                break;

            case '3c':
                this.renderOptionValuesStep();
                break;

            case 'category-naming':
                this.renderCategoryNamingStep();
                break;

            case 'confirm':
                this.renderConfirmStep();
                break;
        }
    }

    // =========================================================================
    // Data collection
    // =========================================================================

    collectStepData() {
        switch (this.currentStepKey) {
            case 'step1': {
                const checked = this.el.querySelector('input[name="product_count"]:checked');
                this.data.productCount = checked ? checked.value : null;
                this.flow = this.data.productCount === '1' ? 'single' : 'multi';
                break;
            }

            case '2a':
                this.data.productName = this.el.querySelector('#j2c-product-name')?.value.trim() || '';
                break;

            case '3a': {
                const checked = this.el.querySelector('input[name="product_type"]:checked');
                this.data.productType = checked ? checked.value : 'simple';
                break;
            }

            case '3b':
                this.data.optionTitles = this.collectOptionTitles();
                break;

            case '3c':
                this.data.optionValues = this.collectOptionValues();
                break;

            case '2b': {
                const checked = this.el.querySelector('input[name="category_count"]:checked');
                this.data.categoryCount = checked ? checked.value : '1';
                this.data.singleCategory = this.data.categoryCount === '1';
                break;
            }

            case '3b-multi': {
                const checked = this.el.querySelector('input[name="menu_type"]:checked');
                this.data.menuType = checked ? checked.value : 'categories';
                break;
            }

            case 'category-naming':
                this.data.categoryNames    = this.collectCategoryNames();
                this.data.rootCategoryName = 'Shop';
                break;

            case 'template': {
                const checked = this.el.querySelector('input[name="subtemplate"]:checked');
                this.data.subtemplate = checked ? checked.value : 'bootstrap5';
                break;
            }
        }
    }

    // =========================================================================
    // Validation
    // =========================================================================

    validateCurrentStep() {
        this.hideError();

        switch (this.currentStepKey) {
            case 'step1': {
                const checked = this.el.querySelector('input[name="product_count"]:checked');
                if (!checked) {
                    // Button should be disabled, but double-check
                    return false;
                }
                break;
            }

            case '2a': {
                const name = this.el.querySelector('#j2c-product-name')?.value.trim() || '';
                if (name === '') {
                    this.showError(Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_PRODUCT_NAME_REQUIRED'));
                    return false;
                }
                break;
            }

            case '3a': {
                const checked = this.el.querySelector('input[name="product_type"]:checked');
                if (!checked) return false;
                break;
            }

            case '3b': {
                const titles = this.collectOptionTitles();
                if (titles.some((t) => t === '')) {
                    this.showError(Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_OPTION_TITLE_REQUIRED'));
                    return false;
                }
                if (titles.length === 0) {
                    this.showError(Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_OPTION_TITLE_REQUIRED'));
                    return false;
                }
                break;
            }

            case '3c': {
                const values = this.collectOptionValues();
                for (const title of this.data.optionTitles) {
                    if (!values[title] || values[title].length === 0) {
                        this.showError(Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_OPTION_VALUES_REQUIRED'));
                        return false;
                    }
                }
                break;
            }

            case '2b': {
                const checked = this.el.querySelector('input[name="category_count"]:checked');
                if (!checked) return false;
                break;
            }

            case 'category-naming': {
                const names = this.collectCategoryNames();
                const nonEmpty = names.filter((n) => n !== '');
                if (nonEmpty.length === 0) {
                    this.showError(Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CATEGORY_NAMES_REQUIRED'));
                    return false;
                }
                break;
            }
        }

        return true;
    }

    updateNextButton() {
        const stepEl = this.el.querySelector(`[data-step="${this.currentStepKey}"]`);
        if (!stepEl) return;

        let enabled = true;

        if (this.currentStepKey === 'step1') {
            enabled = !!this.el.querySelector('input[name="product_count"]:checked');
        } else if (this.currentStepKey === '2a') {
            enabled = (this.el.querySelector('#j2c-product-name')?.value.trim() || '') !== '';
        }

        this.btnNext.disabled = !enabled;
    }

    // =========================================================================
    // Dynamic UI rendering
    // =========================================================================

    renderOptionTitles(reset = false) {
        const container = this.el.querySelector('#j2c-option-titles-list');
        if (!container) return;

        if (reset) {
            container.replaceChildren();
            this.addOptionTitleRow();
        }
    }

    addOptionTitleRow() {
        const container = this.el.querySelector('#j2c-option-titles-list');
        if (!container) return;

        const rows      = container.querySelectorAll('.j2c-option-title-row');
        const rowNumber = rows.length + 1;

        const row  = document.createElement('div');
        row.className = 'j2c-option-title-row mb-3 d-flex gap-2 align-items-center';

        const input = document.createElement('input');
        input.type        = 'text';
        input.className   = 'form-control j2c-option-title-input';
        input.placeholder = Joomla.Text._('COM_J2COMMERCE_WIZARD_OPTION_TITLE_PLACEHOLDER') || 'e.g., Size, Color';
        input.setAttribute('aria-label', `Option ${rowNumber} Title`);

        const removeBtn = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'btn btn-outline-danger btn-sm flex-shrink-0';
        removeBtn.setAttribute('data-remove-option-title', '');
        removeBtn.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_OPTION') || 'Remove');
        removeBtn.title = Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_OPTION') || 'Remove';

        const icon = document.createElement('span');
        icon.className = 'fa-solid fa-times';
        removeBtn.appendChild(icon);

        row.appendChild(input);
        row.appendChild(removeBtn);
        container.appendChild(row);

        this.updateRemoveOptionButtons();
        input.focus();
    }

    removeOptionTitleRow(row) {
        if (!row) return;
        row.remove();
        this.updateRemoveOptionButtons();
    }

    updateRemoveOptionButtons() {
        const container = this.el.querySelector('#j2c-option-titles-list');
        if (!container) return;

        const rows = container.querySelectorAll('.j2c-option-title-row');
        rows.forEach((row) => {
            const btn = row.querySelector('[data-remove-option-title]');
            if (btn) {
                btn.classList.toggle('d-none', rows.length <= 1);
            }
        });
    }

    collectOptionTitles() {
        const inputs = this.el.querySelectorAll('.j2c-option-title-input');
        return Array.from(inputs).map((i) => i.value.trim());
    }

    renderOptionValuesStep() {
        const container = this.el.querySelector('#j2c-option-values-container');
        if (!container) return;

        container.replaceChildren();

        const titles = this.collectOptionTitles().filter((t) => t !== '');
        this.data.optionTitles = titles;

        titles.forEach((title) => {
            const group = document.createElement('div');
            group.className = 'mb-4 j2c-option-value-group';
            group.dataset.optionTitle = title;

            const heading = document.createElement('h6');
            heading.className   = 'text-secondary border-bottom pb-2 mb-3';
            heading.textContent = title;
            group.appendChild(heading);

            const valuesList = document.createElement('div');
            valuesList.className = 'j2c-option-values-list';

            // Start with one empty input
            const firstRow = this.buildOptionValueRow(title);
            valuesList.appendChild(firstRow);
            group.appendChild(valuesList);

            const addBtn = document.createElement('button');
            addBtn.type      = 'button';
            addBtn.className = 'btn btn-outline-secondary btn-sm mt-2';
            addBtn.setAttribute('data-add-option-value', title);

            const plusIcon = document.createElement('span');
            plusIcon.className = 'fa-solid fa-plus me-1';
            addBtn.appendChild(plusIcon);
            addBtn.appendChild(document.createTextNode(Joomla.Text._('COM_J2COMMERCE_WIZARD_ADD_VALUE') || 'Add value'));

            group.appendChild(addBtn);
            container.appendChild(group);
        });
    }

    buildOptionValueRow(groupKey) {
        const row = document.createElement('div');
        row.className = 'j2c-option-value-row mb-2 d-flex gap-2 align-items-center';

        const input = document.createElement('input');
        input.type        = 'text';
        input.className   = 'form-control j2c-option-value-input';
        input.placeholder = Joomla.Text._('COM_J2COMMERCE_WIZARD_OPTION_VALUE_PLACEHOLDER') || 'e.g., Small';

        const removeBtn = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'btn btn-outline-danger btn-sm flex-shrink-0';
        removeBtn.setAttribute('data-remove-option-value', '');
        removeBtn.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_VALUE') || 'Remove');
        removeBtn.title = Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_VALUE') || 'Remove';

        const icon = document.createElement('span');
        icon.className = 'fa-solid fa-times';
        removeBtn.appendChild(icon);

        row.appendChild(input);
        row.appendChild(removeBtn);

        this.updateRemoveValueButtons(row.closest('.j2c-option-values-list'));

        return row;
    }

    addOptionValueRow(groupKey) {
        const group = this.el.querySelector(`.j2c-option-value-group[data-option-title="${CSS.escape(groupKey)}"]`);
        if (!group) return;

        const list = group.querySelector('.j2c-option-values-list');
        if (!list) return;

        const row = this.buildOptionValueRow(groupKey);
        list.appendChild(row);
        this.updateRemoveValueButtons(list);
        row.querySelector('input')?.focus();
    }

    removeOptionValueRow(row) {
        if (!row) return;
        const list = row.closest('.j2c-option-values-list');
        row.remove();
        if (list) this.updateRemoveValueButtons(list);
    }

    updateRemoveValueButtons(list) {
        if (!list) return;
        const rows = list.querySelectorAll('.j2c-option-value-row');
        rows.forEach((row) => {
            const btn = row.querySelector('[data-remove-option-value]');
            if (btn) {
                btn.classList.toggle('d-none', rows.length <= 1);
            }
        });
    }

    collectOptionValues() {
        const result  = {};
        const groups  = this.el.querySelectorAll('.j2c-option-value-group');

        groups.forEach((group) => {
            const title  = group.dataset.optionTitle;
            const inputs = group.querySelectorAll('.j2c-option-value-input');
            result[title] = Array.from(inputs)
                .map((i) => i.value.trim())
                .filter((v) => v !== '');
        });

        return result;
    }

    renderCategoryNamingStep() {
        const container = this.el.querySelector('#j2c-category-names-container');
        if (!container) return;
        container.replaceChildren();

        const addBtn  = this.el.querySelector('#j2c-add-category');
        const titleEl = this.el.querySelector('.j2c-cat-naming-title');

        const count = this.data.categoryCount;

        if (count === '1') {
            // Single category name input
            if (titleEl) {
                titleEl.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_CATEGORY_NAME') || 'What would you like to call your shop category?';
            }
            if (addBtn) addBtn.classList.add('d-none');

            const row = this.buildCategoryNameRow(1, 'Shop', false);
            container.appendChild(row);
        } else if (count === '10+') {
            // Info note + single root category name
            if (titleEl) {
                titleEl.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_CATEGORY_NAME') || 'Shop Category Name';
            }
            if (addBtn) addBtn.classList.add('d-none');

            const note = document.createElement('p');
            note.className   = 'text-muted';
            note.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_CATEGORY_10_PLUS_NOTE') || "We'll create a root 'Shop' category. You can add subcategories later.";
            container.appendChild(note);

            const row = this.buildCategoryNameRow(1, 'Shop', false);
            container.appendChild(row);
        } else {
            // Multiple category inputs (2-5 or 6-10)
            if (titleEl) {
                titleEl.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_CATEGORY_NAMES') || 'Name your categories:';
            }
            if (addBtn) addBtn.classList.remove('d-none');

            const defaultCount = count === '2-5' ? 2 : 3;
            for (let i = 1; i <= defaultCount; i++) {
                const row = this.buildCategoryNameRow(i, '', true);
                container.appendChild(row);
            }
        }
    }

    buildCategoryNameRow(num, defaultVal, removable) {
        const row = document.createElement('div');
        row.className = 'j2c-category-name-row mb-2 d-flex gap-2 align-items-center';

        const input = document.createElement('input');
        input.type        = 'text';
        input.className   = 'form-control j2c-category-name-input';
        input.placeholder = Joomla.Text._('COM_J2COMMERCE_WIZARD_CATEGORY_NAME_PLACEHOLDER') || 'e.g., Electronics';
        input.value       = defaultVal;

        row.appendChild(input);

        if (removable) {
            const removeBtn = document.createElement('button');
            removeBtn.type      = 'button';
            removeBtn.className = 'btn btn-outline-danger btn-sm flex-shrink-0';
            removeBtn.setAttribute('data-remove-category', '');
            removeBtn.setAttribute('aria-label', Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_OPTION') || 'Remove');
            removeBtn.title = Joomla.Text._('COM_J2COMMERCE_WIZARD_REMOVE_OPTION') || 'Remove';

            const icon = document.createElement('span');
            icon.className = 'fa-solid fa-times';
            removeBtn.appendChild(icon);
            row.appendChild(removeBtn);
        }

        return row;
    }

    addCategoryNameRow() {
        const container = this.el.querySelector('#j2c-category-names-container');
        if (!container) return;

        const rows = container.querySelectorAll('.j2c-category-name-row');
        const row  = this.buildCategoryNameRow(rows.length + 1, '', true);
        container.appendChild(row);
        row.querySelector('input')?.focus();
    }

    removeCategoryNameRow(row) {
        if (!row) return;
        const container = this.el.querySelector('#j2c-category-names-container');
        const rows      = container?.querySelectorAll('.j2c-category-name-row');
        if (rows && rows.length > 1) {
            row.remove();
        }
    }

    collectCategoryNames() {
        const inputs = this.el.querySelectorAll('.j2c-category-name-input');
        return Array.from(inputs).map((i) => i.value.trim()).filter((v) => v !== '');
    }

    renderConfirmStep() {
        const listEl = this.el.querySelector('#j2c-wizard-summary');
        if (!listEl) return;
        listEl.replaceChildren();

        const addItem = (iconClass, text) => {
            const li = document.createElement('li');
            li.className = 'mb-2';

            const icon = document.createElement('span');
            icon.className = iconClass + ' me-2';

            const span = document.createElement('span');
            span.textContent = text;

            li.appendChild(icon);
            li.appendChild(span);
            listEl.appendChild(li);
        };

        if (this.flow === 'single') {
            addItem('fa-solid fa-folder text-muted',
                Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_SHOP_CATEGORY') || '1 "Shop" category');
            addItem('fa-solid fa-file-alt text-muted',
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_ARTICLE') || '1 article: "%s"')
                    .replace('%s', this.data.productName));
            addItem('fa-solid fa-box text-muted',
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_PRODUCT') || '1 %s product')
                    .replace('%s', this.data.productType || 'simple'));

            if (this.data.optionTitles.length > 0) {
                const totalValues = Object.values(this.data.optionValues)
                    .reduce((sum, vals) => sum + vals.length, 0);
                addItem('fa-solid fa-list text-muted',
                    (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_OPTIONS') || '%d option(s) with %d value(s)')
                        .replace('%d', String(this.data.optionTitles.length))
                        .replace('%d', String(totalValues)));
            }

            addItem('fa-solid fa-link text-muted',
                Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_MENU_SINGLE') || '1 menu item: "Shop" (Single Product)');
        } else {
            const catNames = this.data.categoryNames.filter((n) => n !== '');
            const catCount = catNames.length;

            addItem('fa-solid fa-folder text-muted',
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_ROOT_CATEGORY') || '1 root category: "%s"')
                    .replace('%s', 'Shop'));

            if (catCount > 0) {
                addItem('fa-solid fa-folder-open text-muted',
                    (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_SUBCATEGORIES') || '%d subcategory(ies)')
                        .replace('%d', String(catCount)));
            }

            const menuItemCount = this.data.menuType === 'products' && catCount > 0 ? catCount : 1;
            const menuLabel     = this.data.menuType === 'categories' ? 'Product Categories' : 'Product Category';
            addItem('fa-solid fa-link text-muted',
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_CONFIRM_SUMMARY_MENU_MULTI') || '%d menu item(s): %s')
                    .replace('%d', String(menuItemCount))
                    .replace('%s', menuLabel));
        }
    }

    renderUrlExample() {
        const codeEl = this.el.querySelector('#j2c-wizard-url-code');
        const linkEl = this.el.querySelector('#j2c-wizard-url-example');
        if (!codeEl || !linkEl) return;

        const siteUrl    = document.getElementById('j2c-wizard-site-url')?.value || '';
        const sef        = document.getElementById('j2c-wizard-sef')?.value === '1';
        const sefRewrite = document.getElementById('j2c-wizard-sef-rewrite')?.value === '1';

        let exampleUrl;

        if (!sef) {
            exampleUrl = siteUrl + '/index.php?option=com_j2commerce&view=products';
        } else if (!sefRewrite) {
            exampleUrl = siteUrl + '/index.php/shop';
        } else {
            exampleUrl = siteUrl + '/shop';
        }

        codeEl.textContent = exampleUrl;
        linkEl.href        = exampleUrl;
    }

    // =========================================================================
    // Navigation buttons state
    // =========================================================================

    updateNavButtons() {
        const isSuccess = this.currentStepKey === 'success';
        const isConfirm = this.currentStepKey === 'confirm';
        const isFirst   = this.stepHistory.length === 0;

        // Back button
        this.btnBack.classList.toggle('d-none', isFirst || isSuccess);

        // Next button
        this.btnNext.classList.toggle('d-none', isConfirm || isSuccess);

        // Create button
        this.btnCreate.classList.toggle('d-none', !isConfirm);

        // Done button
        this.btnDone.classList.toggle('d-none', !isSuccess);

        this.updateNextButton();
    }

    updateStepIndicator() {
        const flow = this.getFlowSequence();
        const total = flow.length;
        const current = flow.indexOf(this.currentStepKey) + 1;

        if (this.currentStepKey === 'success' || total <= 1) {
            this.stepIndicator.textContent = '';
            return;
        }

        const template = Joomla.Text._('COM_J2COMMERCE_WIZARD_STEP_OF') || 'Step {current} of {total}';
        this.stepIndicator.textContent = template
            .replace('{current}', String(current))
            .replace('{total}', String(total - 1)); // don't count 'success' step
    }

    // =========================================================================
    // Template detection
    // =========================================================================

    async detectTemplate() {
        try {
            const resp = await fetch(`${this.baseUrl}&task=categorywizard.detectTemplate&format=json`);
            if (!resp.ok) return;
            const json = await resp.json();
            if (json.success && json.data) {
                this.templateInfo = json.data;
                // Default subtemplate
                this.data.subtemplate = json.data.defaultSubtemplate || 'bootstrap5';

                // Pre-check the default subtemplate radio
                const subtemplateInput = this.el.querySelector(`input[name="subtemplate"][value="${this.data.subtemplate}"]`);
                if (subtemplateInput) {
                    subtemplateInput.checked = true;
                    subtemplateInput.closest('.j2c-wizard-card')?.classList.add('selected');
                }
            }
        } catch {
            // Non-fatal: fall back to bootstrap5
        }
    }

    // =========================================================================
    // AJAX execution
    // =========================================================================

    async executeCreation() {
        this.hideError();
        this.setCreating(true);

        try {
            if (this.flow === 'single') {
                await this.executeSingleProduct();
            } else {
                await this.executeMultiProduct();
            }
        } catch (err) {
            this.showError(err.message || Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED') || 'An error occurred.');
            this.setCreating(false);
        }
    }

    async executeSingleProduct() {
        const token = this.tokenInput?.value || '';
        const fd    = new FormData();

        fd.append(token, '1');
        fd.append('product_name', this.data.productName);
        fd.append('product_type', this.data.productType || 'simple');
        fd.append('subtemplate', this.data.subtemplate);

        // Append options
        const titles = this.data.optionTitles.filter((t) => t !== '');
        titles.forEach((title, i) => {
            fd.append(`options[${i}][title]`, title);
            const values = this.data.optionValues[title] || [];
            values.forEach((val) => {
                fd.append(`options[${i}][values][]`, val);
            });
        });

        const resp = await fetch(
            `${this.baseUrl}&task=categorywizard.createSingleProduct&format=json`,
            { method: 'POST', body: fd }
        );

        if (!resp.ok) {
            const json = await resp.json().catch(() => ({}));
            throw new Error(json.message || Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'));
        }

        const json = await resp.json();

        if (!json.success) {
            throw new Error(json.message || Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'));
        }

        this.createdData = json.data;
        this.setCreating(false);
        this.renderSuccessStep();
        this.stepHistory.push(this.currentStepKey);
        this.showStep('success');
    }

    async executeMultiProduct() {
        const token = this.tokenInput?.value || '';
        const fd    = new FormData();

        const names = this.data.categoryNames.filter((n) => n !== '');

        fd.append(token, '1');
        fd.append('root_category_name', 'Shop');
        fd.append('menu_type', this.data.menuType);
        fd.append('subtemplate', this.data.subtemplate);

        names.forEach((name) => {
            fd.append('categories[]', name);
        });

        const resp = await fetch(
            `${this.baseUrl}&task=categorywizard.createMultiProduct&format=json`,
            { method: 'POST', body: fd }
        );

        if (!resp.ok) {
            const json = await resp.json().catch(() => ({}));
            throw new Error(json.message || Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'));
        }

        const json = await resp.json();

        if (!json.success) {
            throw new Error(json.message || Joomla.Text._('COM_J2COMMERCE_WIZARD_ERR_CREATION_FAILED'));
        }

        this.createdData = json.data;
        this.setCreating(false);
        this.renderSuccessStep();
        this.stepHistory.push(this.currentStepKey);
        this.showStep('success');
    }

    setCreating(loading) {
        this.btnCreate.disabled = loading;
        this.btnBack.disabled   = loading;

        const spinner = this.btnCreate.querySelector('.spinner-border');

        if (loading) {
            if (!spinner) {
                const s = document.createElement('span');
                s.className = 'spinner-border spinner-border-sm me-1';
                s.setAttribute('role', 'status');
                this.btnCreate.prepend(s);
            }
        } else {
            spinner?.remove();
        }
    }

    // =========================================================================
    // Success step rendering
    // =========================================================================

    renderSuccessStep() {
        this.renderUrlExample();

        const container = this.el.querySelector('#j2c-wizard-success-details');
        if (!container || !this.createdData) return;
        container.replaceChildren();

        if (this.flow === 'single') {
            this.renderSingleSuccess(container);
        } else {
            this.renderMultiSuccess(container);
        }
    }

    renderSingleSuccess(container) {
        const d = this.createdData;

        // Created list
        const ul = document.createElement('ul');
        ul.className = 'list-unstyled mb-4';

        const items = [
            [`fa-solid fa-folder-check text-success`,
                Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_SINGLE_CATEGORY') || '"Shop" category created'],
            [`fa-solid fa-file-check text-success`,
                `"${this.data.productName}" ` + (Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_PRODUCT_CREATED') || 'product created')],
            [`fa-solid fa-link text-success`,
                Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_MENU_ITEM') || '"Shop" menu item created'],
        ];

        if (d.optionValueCount > 0) {
            items.push([`fa-solid fa-list-check text-success`,
                `${d.optionIds.length} option(s), ${d.optionValueCount} value(s)`]);
        }

        items.forEach(([iconClass, text]) => {
            const li = document.createElement('li');
            li.className = 'mb-1';

            const icon = document.createElement('span');
            icon.className = iconClass + ' me-2';

            const span = document.createElement('span');
            span.textContent = text.replace('&amp;', '&');

            li.appendChild(icon);
            li.appendChild(span);
            ul.appendChild(li);
        });

        container.appendChild(ul);

        // Variable product note
        if (d.productType === 'variable' && d.optionIds && d.optionIds.length > 0) {
            const note = document.createElement('div');
            note.className = 'alert alert-info mb-3';

            const noteText = document.createTextNode(
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_VARIANTS_NOTE') || 'Your product has been created with %d option(s). Click below to set pricing, inventory, and images for each variant.')
                    .replace('%d', String(d.optionIds.length))
            );
            note.appendChild(noteText);
            container.appendChild(note);
        }

        // Next steps
        const heading = document.createElement('h6');
        heading.className   = 'mb-3';
        heading.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_NEXT_STEPS') || 'Next steps:';
        container.appendChild(heading);

        const links = document.createElement('div');
        links.className = 'd-flex flex-column gap-2';

        if (d.productType === 'variable') {
            links.appendChild(this.buildLink(
                d.editUrl,
                Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_EDIT_VARIANTS') || 'Edit Product & Configure Variants',
                'fa-solid fa-arrows-rotate',
                'btn-primary'
            ));
        } else {
            links.appendChild(this.buildLink(
                d.editUrl,
                Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_EDIT_PRODUCT') || 'Edit your product',
                'fa-solid fa-edit',
                'btn-outline-primary'
            ));
        }

        links.appendChild(this.buildLink(
            'index.php?option=com_j2commerce&view=products',
            Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_ADD_PRODUCTS') || 'Add more products',
            'fa-solid fa-plus',
            'btn-outline-secondary'
        ));

        container.appendChild(links);
    }

    renderMultiSuccess(container) {
        const d = this.createdData;

        const ul = document.createElement('ul');
        ul.className = 'list-unstyled mb-4';

        const addItem = (iconClass, text) => {
            const li = document.createElement('li');
            li.className = 'mb-1';

            const icon = document.createElement('span');
            icon.className = iconClass + ' me-2';

            const span = document.createElement('span');
            span.textContent = text;

            li.appendChild(icon);
            li.appendChild(span);
            ul.appendChild(li);
        };

        addItem('fa-solid fa-folder-check text-success',
            Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_ROOT_CATEGORY') || 'Root category created');

        if (d.subcategoryIds && d.subcategoryIds.length > 0) {
            addItem('fa-solid fa-folder-check text-success',
                (Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_SUBCATEGORIES') || '%d subcategory(ies) created')
                    .replace('%d', String(d.subcategoryIds.length)));
        }

        addItem('fa-solid fa-link text-success',
            (Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_MENU_ITEMS') || '%d menu item(s) created')
                .replace('%d', String(d.menuItemIds.length)));

        container.appendChild(ul);

        const heading = document.createElement('h6');
        heading.className   = 'mb-3';
        heading.textContent = Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_NEXT_STEPS') || 'Next steps:';
        container.appendChild(heading);

        const links = document.createElement('div');
        links.className = 'd-flex flex-column gap-2';

        links.appendChild(this.buildLink(
            d.adminProductsUrl,
            Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_ADD_PRODUCTS') || 'Add products',
            'fa-solid fa-plus',
            'btn-primary'
        ));

        links.appendChild(this.buildLink(
            d.adminCategoryUrl,
            Joomla.Text._('COM_J2COMMERCE_WIZARD_SUCCESS_MANAGE_CATEGORIES') || 'Manage categories',
            'fa-solid fa-folder',
            'btn-outline-secondary'
        ));

        container.appendChild(links);
    }

    buildLink(href, text, iconClass, btnClass) {
        const a = document.createElement('a');
        a.href      = href;
        a.className = `btn ${btnClass}`;

        const icon = document.createElement('span');
        icon.className = iconClass + ' me-1';

        a.appendChild(icon);
        a.appendChild(document.createTextNode(text));

        return a;
    }

    // =========================================================================
    // Error display
    // =========================================================================

    showError(message) {
        if (!this.errorEl) return;
        this.errorEl.textContent = message;
        this.errorEl.classList.remove('d-none');
    }

    hideError() {
        if (!this.errorEl) return;
        this.errorEl.classList.add('d-none');
        this.errorEl.textContent = '';
    }
}
