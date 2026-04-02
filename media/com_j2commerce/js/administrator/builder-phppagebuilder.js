/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
'use strict';

// ===== Debounce Utility =====
function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

(function() {
    const config = Joomla.getOptions('com_j2commerce.builder') || {};
    const token = config.token || '';
    const ajaxUrl = config.ajaxUrl || 'index.php?option=com_j2commerce&task=builder.';
    const messages = config.messages || {};

    // DOM references
    const fileSelect = document.getElementById('builder-file-select');
    const productSelect = document.getElementById('builder-product-select');
    const saveBtn = document.getElementById('builder-save');
    const saveLabelEl = document.getElementById('builder-save-label');
    const resetBtn = document.getElementById('builder-reset');
    const statusBar = document.getElementById('builder-status-bar');
    const undoBtn = document.getElementById('builder-undo');
    const redoBtn = document.getElementById('builder-redo');
    const canvas = document.getElementById('builder-canvas');
    const placeholder = document.getElementById('builder-placeholder');
    const blocksPanel = document.getElementById('builder-blocks-list');
    const propertiesPanel = document.getElementById('builder-properties-panel');
    const templatesBtn = document.getElementById('builder-templates-btn');
    const presetsGrid = document.getElementById('builder-presets-grid');
    let availableBlocks = {};

    let editor = null;
    let isDirty = false;
    let isLoading = false;
    let currentBlockOrder = [];
    let currentFileHasOverride = false;

    // Phase 3: Sub-layout editing mode
    let currentEditorMode = 'composition'; // 'composition' | 'sublayout'
    let currentSubLayoutId = null;

    // Sub-layout to block slug mapping for "Edit HTML" drill-down
    const subLayoutFileMap = {
        'product-image':       'list/category/item_images.php',
        'product-title':       'list/category/item_title.php',
        'product-price':       'list/category/item_price.php',
        'product-sku':         'list/category/item_sku.php',
        'product-stock':       'list/category/item_stock.php',
        'cart-form':           'list/category/item_cart.php',
        'product-description': 'list/category/item_description.php',
        'product-quickview':   'list/category/item_quickview.php',
    };

    // ===== AJAX Helper =====
    async function fetchJson(task, params = {}, method = 'GET') {
        const url = new URL(ajaxUrl + task);
        url.searchParams.set(token, '1');

        const options = { method, headers: {} };

        if (method === 'GET') {
            Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(params);
        }

        const response = await fetch(url.toString(), options);

        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }

        const json = await response.json();

        if (!json.success) {
            throw new Error(json.message || 'Request failed');
        }

        return json.data;
    }

    // ===== Status Bar =====
    function updateStatusBar(hasOverride) {
        if (!statusBar) return;
        statusBar.classList.remove('d-none', 'builder-status-bar--default', 'builder-status-bar--customized');
        if (hasOverride) {
            statusBar.classList.add('builder-status-bar--customized');
            statusBar.textContent = messages.statusCustomized || 'Editing customized layout override.';
        } else {
            statusBar.classList.add('builder-status-bar--default');
            statusBar.textContent = messages.statusDefault || 'Viewing default layout. Edit and save to create a template override.';
        }
    }

    function updateSaveButton(hasOverride) {
        if (saveLabelEl) {
            saveLabelEl.textContent = hasOverride
                ? (messages.saveUpdate || 'Save Layout')
                : (messages.saveCreate || 'Create Override & Save');
        }
    }

    function updateResetButton(hasOverride) {
        if (!resetBtn) return;
        if (hasOverride) {
            resetBtn.classList.remove('d-none');
        } else {
            resetBtn.classList.add('d-none');
        }
    }

    function updateFileOptionBadge(value, hasOverride) {
        if (!fileSelect) return;
        const opt = fileSelect.querySelector('option[value="' + CSS.escape(value) + '"]');
        if (!opt) return;
        const badge = hasOverride
            ? (messages.badgeCustomized || 'CUSTOMIZED')
            : (messages.badgeDefault || 'DEFAULT');
        // Strip existing badge suffix and replace
        opt.textContent = opt.textContent.replace(/\s*\[(?:DEFAULT|CUSTOMIZED)\]\s*$/, '') + ' [' + badge + ']';
    }

    // ===== Canvas Loading State =====
    function setCanvasLoading(loading) {
        if (!canvas) return;
        canvas.style.opacity = loading ? '0.4' : '';
        canvas.style.pointerEvents = loading ? 'none' : '';
    }

    // ===== Load Project =====
    async function loadProject(pluginElement, fileId) {
        setCanvasLoading(true);
        try {
            const data = await fetchJson('loadProject', {
                plugin_element: pluginElement,
                file_id: fileId
            });

            // Guard: if the server classified this as a dispatcher, show info and bail
            if (data.file_type === 'dispatcher') {
                if (canvas) {
                    const alertEl = document.createElement('div');
                    alertEl.className = 'alert alert-info m-3';
                    const icon = document.createElement('i');
                    icon.className = 'fa-solid fa-code-branch me-2';
                    alertEl.appendChild(icon);
                    alertEl.appendChild(document.createTextNode(data.message || 'This file is a dispatcher and cannot be edited visually.'));
                    canvas.textContent = '';
                    canvas.appendChild(alertEl);
                }
                saveBtn.disabled = true;
                return;
            }

            // Phase 3: Branch based on file classification
            if (data.file_type === 'sublayout') {
                await loadSubLayoutMode(pluginElement, fileId);
                return;
            }

            currentEditorMode = 'composition';
            currentSubLayoutId = null;

            currentBlockOrder = data.block_order || [];
            availableBlocks = data.available_blocks || {};
            populateBlockPalette(availableBlocks);

            // Load the override file content into the canvas using file block order
            await renderFreshCanvas(pluginElement, fileId, data.available_blocks || {});

            saveBtn.disabled = false;
            if (placeholder) placeholder.style.display = 'none';

            updateModeIndicator('composition');
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        } finally {
            setCanvasLoading(false);
        }
    }

    // ===== Sub-Layout Mode (Phase 3) =====
    async function loadSubLayoutMode(pluginElement, fileId) {
        if (!fileSelect || !fileSelect.value) return;

        currentEditorMode = 'sublayout';
        const productId = productSelect ? parseInt(productSelect.value, 10) : 0;

        if (canvas) {
            canvas.textContent = '';
            const loadingEl = document.createElement('div');
            loadingEl.className = 'alert alert-info m-3';
            loadingEl.textContent = messages.sublayoutLoading || 'Loading sub-layout editor...';
            canvas.appendChild(loadingEl);
        }

        try {
            const data = await fetchJson('renderSubLayout', {
                plugin_element: pluginElement,
                file_id: fileId,
                product_id: productId
            });

            currentSubLayoutId = data.sub_layout_id || null;

            // In sub-layout mode: load tokenized HTML into GrapeJS
            // Replace block palette with element insertion palette
            populateElementPalette();

            initGrapesJS(null, data.html, true);

            saveBtn.disabled = false;
            if (placeholder) placeholder.style.display = 'none';

            updateModeIndicator('sublayout');
        } catch (err) {
            if (canvas) {
                canvas.textContent = '';
                const alertEl = document.createElement('div');
                alertEl.className = 'alert alert-danger m-3';
                alertEl.textContent = err.message;
                canvas.appendChild(alertEl);
            }
        }
    }

    function updateModeIndicator(mode) {
        const modeEl = document.getElementById('builder-mode-indicator');
        if (!modeEl) return;

        modeEl.textContent = mode === 'sublayout'
            ? (messages.modeSublayout || 'HTML Editing Mode')
            : (messages.modeComposition || 'Composition Mode');
        modeEl.className = mode === 'sublayout'
            ? 'badge bg-warning text-dark ms-2'
            : 'badge bg-secondary ms-2';
    }

    function populateElementPalette() {
        if (!blocksPanel) return;
        blocksPanel.textContent = '';

        const heading = document.createElement('div');
        heading.className = 'fw-semibold small text-muted mb-2 border-bottom pb-1';
        heading.textContent = messages.insertElement || 'Insert Element';
        blocksPanel.appendChild(heading);

        const backBtn = document.createElement('button');
        backBtn.type = 'button';
        backBtn.className = 'btn btn-sm btn-outline-secondary w-100 mb-3';
        const backIcon = document.createElement('i');
        backIcon.className = 'fa-solid fa-arrow-left me-1';
        backBtn.appendChild(backIcon);
        backBtn.appendChild(document.createTextNode(messages.backToComposition || 'Back to Composition'));
        backBtn.addEventListener('click', () => {
            if (fileSelect && fileSelect.value) {
                const [pluginElement, fileId] = fileSelect.value.split('::');
                currentEditorMode = 'composition';
                currentSubLayoutId = null;
                populateBlockPalette(availableBlocks);
                loadProject(pluginElement, fileId);
            }
        });
        blocksPanel.appendChild(backBtn);

        const elements = [
            { tag: 'div',    label: 'Div',       icon: 'fa-solid fa-square-dashed' },
            { tag: 'p',      label: 'Paragraph',  icon: 'fa-solid fa-paragraph' },
            { tag: 'span',   label: 'Span',       icon: 'fa-solid fa-font' },
            { tag: 'h2',     label: 'Heading 2',  icon: 'fa-solid fa-heading' },
            { tag: 'h3',     label: 'Heading 3',  icon: 'fa-solid fa-heading' },
            { tag: 'h4',     label: 'Heading 4',  icon: 'fa-solid fa-heading' },
            { tag: 'a',      label: 'Link',       icon: 'fa-solid fa-link' },
            { tag: 'small',  label: 'Small Text', icon: 'fa-solid fa-text-height' },
            { tag: 'strong', label: 'Bold',       icon: 'fa-solid fa-bold' },
        ];

        elements.forEach(el => {
            const item = document.createElement('div');
            item.className = 'builder-block-item p-2 border rounded mb-2 cursor-pointer';
            item.draggable = true;
            item.dataset.tag = el.tag;
            const icon = document.createElement('i');
            icon.className = el.icon + ' me-2';
            item.appendChild(icon);
            item.appendChild(document.createTextNode(el.label));

            item.addEventListener('click', () => {
                if (!editor) return;
                editor.addComponents('<' + el.tag + '>New ' + el.label + '</' + el.tag + '>');
            });

            blocksPanel.appendChild(item);
        });
    }

    // ===== Render Fresh Canvas =====
    async function renderFreshCanvas(pluginElement, fileId, availableBlocks) {
        const productId = productSelect ? parseInt(productSelect.value, 10) : 0;

        // Use the file's block order if available, otherwise fall back to all blocks
        const slugOrder = currentBlockOrder.length > 0
            ? currentBlockOrder
            : Object.values(availableBlocks).map(b => b.slug);

        const blocks = slugOrder
            .filter(slug => availableBlocks[slug])
            .map(slug => ({
                slug: slug,
                settings: Object.fromEntries(
                    Object.entries(availableBlocks[slug].settings || {}).map(([k, v]) => [k, v.default])
                )
            }));

        try {
            const data = await fetchJson('renderAllBlocks', {
                product_id: productId,
                edit_mode: true,
                blocks: blocks
            }, 'POST');

            const blockHtml = Object.values(data.blocks || {}).join('\n');
            const html = '<div class="j2commerce-product-item j2commerce-type-simple d-flex flex-column" style="max-width:400px;">' + blockHtml + '</div>';
            initGrapesJS(null, html);
        } catch (err) {
            if (canvas) {
                const alertEl = document.createElement('div');
                alertEl.className = 'alert alert-danger m-3';
                alertEl.textContent = err.message;
                canvas.textContent = '';
                canvas.appendChild(alertEl);
            }
        }
    }

    // ===== Initialize GrapesJS =====
    function initGrapesJS(projectData, initialHtml, subLayoutMode = false) {
        if (editor) {
            editor.destroy();
        }

        if (canvas) canvas.innerHTML = '';

        editor = grapesjs.init({
            container: '#builder-canvas',
            fromElement: false,
            height: 'max(600px, calc(100vh - 280px))',
            width: 'auto',
            storageManager: false,
            panels: { defaults: [] },
            blockManager: { blocks: [] },
            richTextEditor: {
                actions: ['bold', 'italic', 'underline', 'strikethrough', 'link']
            },
            deviceManager: {
                devices: [
                    { name: 'Desktop', width: '' },
                    { name: 'Tablet', width: '768px', widthMedia: '992px' },
                    { name: 'Mobile', width: '375px', widthMedia: '480px' },
                ]
            },
            styleManager: {
                appendTo: '#builder-styles-panel',
                sectors: [
                    {
                        name: 'Spacing',
                        open: false,
                        properties: [
                            { extend: 'margin', type: 'composite', properties: [
                                { name: 'Top', property: 'margin-top', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Right', property: 'margin-right', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Bottom', property: 'margin-bottom', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Left', property: 'margin-left', type: 'integer', units: ['px','em','rem','%'] },
                            ]},
                            { extend: 'padding', type: 'composite', properties: [
                                { name: 'Top', property: 'padding-top', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Right', property: 'padding-right', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Bottom', property: 'padding-bottom', type: 'integer', units: ['px','em','rem','%'] },
                                { name: 'Left', property: 'padding-left', type: 'integer', units: ['px','em','rem','%'] },
                            ]},
                        ]
                    },
                    {
                        name: 'Typography',
                        open: false,
                        properties: [
                            { property: 'font-family', type: 'select', options: [
                                { id: '', name: 'Default' },
                                { id: 'Arial, sans-serif', name: 'Arial' },
                                { id: 'Georgia, serif', name: 'Georgia' },
                                { id: 'Verdana, sans-serif', name: 'Verdana' },
                                { id: "'Courier New', monospace", name: 'Courier New' },
                                { id: 'system-ui, sans-serif', name: 'System UI' },
                            ]},
                            { property: 'font-size', type: 'integer', units: ['px','em','rem'] },
                            { property: 'font-weight', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: '300', name: 'Light' },
                                { id: '400', name: 'Normal' }, { id: '500', name: 'Medium' },
                                { id: '600', name: 'Semi Bold' }, { id: '700', name: 'Bold' },
                            ]},
                            { property: 'line-height', type: 'integer', units: ['px','em','rem'] },
                            { property: 'letter-spacing', type: 'integer', units: ['px','em'] },
                            { property: 'color', type: 'color' },
                            { property: 'text-align', type: 'radio', options: [
                                { id: 'left', label: 'Left' }, { id: 'center', label: 'Center' },
                                { id: 'right', label: 'Right' }, { id: 'justify', label: 'Justify' },
                            ]},
                            { property: 'text-transform', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'uppercase', name: 'Uppercase' },
                                { id: 'lowercase', name: 'Lowercase' }, { id: 'capitalize', name: 'Capitalize' },
                            ]},
                            { property: 'text-decoration', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'none', name: 'None' },
                                { id: 'underline', name: 'Underline' }, { id: 'line-through', name: 'Strikethrough' },
                            ]},
                        ]
                    },
                    {
                        name: 'Background',
                        open: false,
                        properties: [
                            { property: 'background-color', type: 'color' },
                            { property: 'background-repeat', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'no-repeat', name: 'No Repeat' },
                                { id: 'repeat', name: 'Repeat' }, { id: 'repeat-x', name: 'Repeat X' },
                                { id: 'repeat-y', name: 'Repeat Y' },
                            ]},
                            { property: 'background-size', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'cover', name: 'Cover' },
                                { id: 'contain', name: 'Contain' }, { id: 'auto', name: 'Auto' },
                            ]},
                        ]
                    },
                    {
                        name: 'Borders',
                        open: false,
                        properties: [
                            { property: 'border-radius', type: 'integer', units: ['px','em','rem','%'] },
                            { property: 'border-width', type: 'integer', units: ['px'] },
                            { property: 'border-style', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'none', name: 'None' },
                                { id: 'solid', name: 'Solid' }, { id: 'dashed', name: 'Dashed' },
                                { id: 'dotted', name: 'Dotted' },
                            ]},
                            { property: 'border-color', type: 'color' },
                        ]
                    },
                    {
                        name: 'Layout',
                        open: false,
                        properties: [
                            { property: 'display', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'block', name: 'Block' },
                                { id: 'flex', name: 'Flex' }, { id: 'grid', name: 'Grid' },
                                { id: 'inline-block', name: 'Inline Block' }, { id: 'none', name: 'None' },
                            ]},
                            { property: 'flex-direction', type: 'select', options: [
                                { id: 'row', name: 'Row' }, { id: 'column', name: 'Column' },
                                { id: 'row-reverse', name: 'Row Reverse' }, { id: 'column-reverse', name: 'Column Reverse' },
                            ]},
                            { property: 'justify-content', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'flex-start', name: 'Start' },
                                { id: 'center', name: 'Center' }, { id: 'flex-end', name: 'End' },
                                { id: 'space-between', name: 'Space Between' }, { id: 'space-around', name: 'Space Around' },
                            ]},
                            { property: 'align-items', type: 'select', options: [
                                { id: '', name: 'Default' }, { id: 'flex-start', name: 'Start' },
                                { id: 'center', name: 'Center' }, { id: 'flex-end', name: 'End' },
                                { id: 'stretch', name: 'Stretch' },
                            ]},
                            { property: 'gap', type: 'integer', units: ['px','em','rem'] },
                            { property: 'width', type: 'integer', units: ['px','%','em','rem','vw'] },
                            { property: 'height', type: 'integer', units: ['px','%','em','rem','vh'] },
                        ]
                    },
                ]
            },
            canvas: {
                styles: (config.canvasStyles || []).length
                    ? config.canvasStyles
                    : [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                        Joomla.getOptions('system.paths').root + '/media/com_j2commerce/css/site/j2commerce.css'
                    ]
            },
            plugins: [],
            pluginsOpts: {}
        });

        isLoading = true;
        if (projectData) {
            editor.loadProjectData(projectData);
        } else if (initialHtml) {
            editor.setComponents(initialHtml);
        }
        // Allow GrapesJS to settle, then clear undo history and enable dirty tracking
        requestAnimationFrame(() => {
            editor.UndoManager.clear();
            isDirty = false;
            isLoading = false;
        });

        // Make j2c-token elements non-editable but draggable
        editor.on('component:add', component => {
            const tagName = component.get('tagName');

            if (tagName === 'j2c-token') {
                component.set({
                    editable: false,
                    draggable: true,
                    removable: false,
                    copyable: false,
                    badgable: true,
                    highlightable: true
                });
                const tokenAttr = component.getAttributes()['data-token']
                    || component.getAttributes()['data-j2c-token']
                    || 'TOKEN';
                component.set('custom-name', tokenAttr);
            }

            // j2c-conditional: draggable as a unit, children rearrangeable, condition not editable
            if (tagName === 'j2c-conditional') {
                component.set({
                    draggable: true,
                    droppable: true,
                    editable: false,
                    removable: false,
                    copyable: false,
                    badgable: true,
                    highlightable: true
                });
                const condition = component.getAttributes()['data-condition'] || '';
                component.set('custom-name', 'if: ' + condition);
            }

            // j2c-locked: invisible infrastructure elements — completely non-interactive
            if (tagName === 'span' && component.getAttributes()['data-j2c-locked'] !== undefined) {
                component.set({
                    selectable: false,
                    draggable: false,
                    droppable: false,
                    editable: false,
                    removable: false,
                    hoverable: false,
                    copyable: false,
                    badgable: false,
                    highlightable: false
                });
            }

            // cart-form block: draggable as a unit, nothing can be dropped inside
            const attrs = component.getAttributes();
            if (attrs['data-j2c-block'] === 'cart-form') {
                component.set({
                    droppable: false,
                    editable: false,
                    copyable: false,
                    removable: true,
                    draggable: true,
                    badgable: true,
                    highlightable: true
                });
                component.set('custom-name', 'Options & Cart');
                // Lock all children so nothing inside can be individually dragged out
                component.components().forEach(function lockChildren(child) {
                    child.set({ draggable: false, droppable: false, removable: false, copyable: false });
                    child.components().forEach(lockChildren);
                });
            }
        });

        editor.on('change:changesCount', () => {
            if (isLoading) return;
            isDirty = true;
            if (undoBtn) undoBtn.disabled = !editor.UndoManager.hasUndo();
            if (redoBtn) redoBtn.disabled = !editor.UndoManager.hasRedo();
        });

        editor.on('component:selected', component => {
            showProperties(component);
        });

        editor.on('component:deselected', () => {
            if (propertiesPanel) {
                propertiesPanel.textContent = '';
                const placeholder = document.createElement('div');
                placeholder.className = 'text-muted small text-center py-3';
                placeholder.textContent = messages.selectElement || 'Select an element';
                propertiesPanel.appendChild(placeholder);
            }
        });
    }

    // ===== Block Palette =====
    // Blocks replaced by the combined cart-form block
    const hiddenBlocks = ['product-options', 'product-cart'];

    function populateBlockPalette(blocks) {
        if (!blocksPanel) return;
        blocksPanel.innerHTML = '';

        Object.values(blocks).filter(block => !hiddenBlocks.includes(block.slug)).forEach(block => {
            const el = document.createElement('div');
            el.className = 'builder-block-item p-2 border rounded mb-2 cursor-pointer';
            el.draggable = true;
            el.dataset.slug = block.slug;
            const icon = document.createElement('i');
            icon.className = (block.icon || 'fa-solid fa-cube') + ' me-2';
            el.appendChild(icon);
            el.appendChild(document.createTextNode(block.label || block.slug));

            el.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', block.slug);
            });

            el.addEventListener('click', async () => {
                if (!editor) return;
                const productId = productSelect ? parseInt(productSelect.value, 10) : 0;
                const defaults = Object.fromEntries(
                    Object.entries(block.settings || {}).map(([k, v]) => [k, v.default])
                );

                try {
                    const data = await fetchJson('renderBlock', {
                        slug: block.slug,
                        product_id: productId,
                        edit_mode: true,
                        settings: JSON.stringify(defaults)
                    });
                    if (data.html) {
                        editor.addComponents(data.html);
                    }
                } catch (err) {
                    Joomla.renderMessages({ error: [err.message] });
                }
            });

            blocksPanel.appendChild(el);
        });
    }

    // ===== Properties Panel =====
    function createControlGroup(id, labelText, controlEl, descText) {
        const group = document.createElement('div');
        group.className = 'control-group mb-2';

        const labelDiv = document.createElement('div');
        labelDiv.className = 'control-label';
        const lbl = document.createElement('label');
        lbl.setAttribute('for', id);
        lbl.textContent = labelText;
        labelDiv.appendChild(lbl);

        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'controls';
        controlsDiv.appendChild(controlEl);

        if (descText) {
            const descEl = document.createElement('small');
            descEl.className = 'form-text text-muted';
            descEl.textContent = descText;
            controlsDiv.appendChild(descEl);
        }

        group.appendChild(labelDiv);
        group.appendChild(controlsDiv);
        return group;
    }

    function buildSettingControl(id, settingKey, settingDef, currentValue, onChange) {
        const type = settingDef.type || 'text';
        let el;

        if (type === 'select') {
            el = document.createElement('select');
            el.id = id;
            el.className = 'form-select form-select-sm';
            (settingDef.options || []).forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                if (opt === currentValue) option.selected = true;
                el.appendChild(option);
            });
        } else if (type === 'checkbox') {
            el = document.createElement('input');
            el.type = 'checkbox';
            el.id = id;
            el.className = 'form-check-input';
            el.checked = currentValue === true || currentValue === '1' || currentValue === 'true';
            el.addEventListener('change', () => onChange(el.checked));
            return el;
        } else if (type === 'color') {
            el = document.createElement('input');
            el.type = 'color';
            el.id = id;
            el.className = 'form-control form-control-sm form-control-color';
            el.value = currentValue || '#000000';
        } else {
            el = document.createElement('input');
            el.type = 'text';
            el.id = id;
            el.className = 'form-control form-control-sm';
            el.value = currentValue !== undefined ? String(currentValue) : '';
        }

        el.addEventListener('change', () => onChange(el.value));
        return el;
    }

    function showProperties(component) {
        if (!propertiesPanel) return;
        propertiesPanel.textContent = '';

        // --- Block-specific settings from config ---
        const attrs = component.getAttributes();
        const blockSlug = attrs['data-j2c-block'];
        const blockConfig = blockSlug ? availableBlocks[blockSlug] : null;

        // --- Edit HTML drill-down button (composition mode only) ---
        if (blockSlug && currentEditorMode === 'composition' && subLayoutFileMap[blockSlug]) {
            const subLayoutFile = subLayoutFileMap[blockSlug];
            const editHtmlBtn = document.createElement('button');
            editHtmlBtn.type = 'button';
            editHtmlBtn.className = 'btn btn-sm btn-outline-primary w-100 mb-3';
            const editIcon = document.createElement('i');
            editIcon.className = 'fa-solid fa-code me-1';
            editHtmlBtn.appendChild(editIcon);
            editHtmlBtn.appendChild(document.createTextNode(messages.editHtml || 'Edit HTML'));
            editHtmlBtn.title = messages.editHtmlDesc || 'Edit the internal HTML structure of this block';
            editHtmlBtn.addEventListener('click', () => {
                if (!fileSelect || !fileSelect.value) return;
                const [pluginElement] = fileSelect.value.split('::');
                const newValue = pluginElement + '::' + subLayoutFile;
                // Update file selector if the option exists
                const optEl = fileSelect.querySelector('option[value="' + CSS.escape(newValue) + '"]');
                if (optEl) {
                    fileSelect.value = newValue;
                    fileSelect.dispatchEvent(new Event('change'));
                } else {
                    // File not in selector — load directly
                    currentEditorMode = 'sublayout';
                    loadSubLayoutMode(pluginElement, subLayoutFile);
                    updateModeIndicator('sublayout');
                }
            });
            propertiesPanel.appendChild(editHtmlBtn);
        }

        if (blockConfig && blockConfig.settings && Object.keys(blockConfig.settings).length > 0) {
            const heading = document.createElement('div');
            heading.className = 'fw-semibold small text-muted mb-2 border-bottom pb-1';
            heading.textContent = blockConfig.label || blockSlug;
            propertiesPanel.appendChild(heading);

            Object.entries(blockConfig.settings).forEach(([key, def]) => {
                const id = 'block-setting-' + key;
                const currentVal = def.default;
                const controlEl = buildSettingControl(id, key, def, currentVal, (newVal) => {
                    // Debounced re-render block with new settings via AJAX
                    getDebouncedRender(blockSlug)(component, key, newVal);
                });
                const group = createControlGroup(id, def.label || key, controlEl, null);
                propertiesPanel.appendChild(group);
            });

            const sep = document.createElement('hr');
            sep.className = 'my-2';
            propertiesPanel.appendChild(sep);
        }

        // --- Generic HTML Tag ---
        const currentTag = component.get('tagName') || 'div';
        const tagEl = document.createElement('select');
        tagEl.id = 'prop-tag';
        tagEl.className = 'form-select form-select-sm';
        ['div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'section', 'article'].forEach(t => {
            const opt = document.createElement('option');
            opt.value = t;
            opt.textContent = t;
            if (t === currentTag) opt.selected = true;
            tagEl.appendChild(opt);
        });
        tagEl.addEventListener('change', () => component.set('tagName', tagEl.value));
        propertiesPanel.appendChild(createControlGroup('prop-tag', 'HTML Tag', tagEl, 'The HTML element type for this component.'));

        // --- CSS Classes ---
        const classEl = document.createElement('input');
        classEl.type = 'text';
        classEl.id = 'prop-classes';
        classEl.className = 'form-control form-control-sm';
        classEl.value = component.getClasses().join(' ');
        classEl.addEventListener('change', () => {
            component.setClass(classEl.value.split(' ').filter(Boolean));
        });
        propertiesPanel.appendChild(createControlGroup('prop-classes', 'CSS Classes', classEl, 'Space-separated CSS class names.'));

        // --- Visibility ---
        const isVisible = component.getStyle().display !== 'none';
        const visFieldset = document.createElement('fieldset');
        visFieldset.id = 'prop-visible';
        visFieldset.className = 'radio switcher';
        ['0', '1'].forEach(val => {
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.id = 'prop-visible-' + val;
            radio.name = 'prop-visible';
            radio.value = val;
            radio.checked = val === '1' ? isVisible : !isVisible;
            radio.addEventListener('change', () => {
                component.setStyle({ display: radio.value === '1' ? '' : 'none' });
            });
            const lbl = document.createElement('label');
            lbl.setAttribute('for', 'prop-visible-' + val);
            lbl.textContent = val === '1' ? 'Yes' : 'No';
            visFieldset.appendChild(radio);
            visFieldset.appendChild(lbl);
        });
        propertiesPanel.appendChild(createControlGroup('prop-visible', 'Visible', visFieldset, 'Toggle element visibility.'));
    }

    // Store block settings per slug for re-render
    const blockSettingsCache = {};

    // Debounced wrapper map: one debounced fn per block slug
    const debouncedRenderMap = {};

    function getDebouncedRender(slug) {
        if (!debouncedRenderMap[slug]) {
            debouncedRenderMap[slug] = debounce((component, changedKey, newVal) => {
                reRenderBlockWithSettings(component, slug, changedKey, newVal);
            }, 300);
        }
        return debouncedRenderMap[slug];
    }

    async function reRenderBlockWithSettings(component, slug, changedKey, newVal) {
        if (!editor || !fileSelect || !fileSelect.value) return;

        if (!blockSettingsCache[slug]) {
            const def = availableBlocks[slug] || {};
            blockSettingsCache[slug] = Object.fromEntries(
                Object.entries(def.settings || {}).map(([k, v]) => [k, v.default])
            );
        }
        blockSettingsCache[slug][changedKey] = newVal;

        const productId = productSelect ? parseInt(productSelect.value, 10) : 0;

        try {
            const data = await fetchJson('renderBlock', {
                slug,
                product_id: productId,
                edit_mode: true,
                settings: JSON.stringify(blockSettingsCache[slug])
            });
            if (data.html) {
                component.replaceWith(data.html);
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        }
    }

    // ===== Extract Block Order from GrapesJS Canvas =====
    function extractBlockOrder() {
        if (!editor) return [];

        const order = [];
        const wrapper = editor.getWrapper();

        function walk(component) {
            const attrs = component.getAttributes();
            const blockSlug = attrs['data-j2c-block'];
            if (blockSlug && !order.includes(blockSlug)) {
                order.push(blockSlug);
            }
            component.components().forEach(child => walk(child));
        }

        walk(wrapper);
        return order;
    }

    // ===== Save =====
    async function saveLayout() {
        if (!editor || !fileSelect || !fileSelect.value) return;

        const [pluginElement, fileId] = fileSelect.value.split('::');

        saveBtn.disabled = true;
        const savingLabel = messages.savingLabel || 'Saving...';
        if (saveLabelEl) saveLabelEl.textContent = savingLabel;

        try {
            let saveResult;

            if (currentEditorMode === 'sublayout') {
                // Sub-layout mode: extract HTML from GrapeJS and regenerate PHP
                const html = editor.getHtml();
                saveResult = await fetchJson('saveSubLayoutHtml', {
                    plugin_element: pluginElement,
                    file_id: fileId,
                    html: html
                }, 'POST');
            } else {
                // Composition mode: extract block order and regenerate PHP
                const blockOrder = extractBlockOrder();

                if (blockOrder.length === 0) {
                    Joomla.renderMessages({ warning: ['No blocks found on canvas. Nothing to save.'] });
                    saveBtn.disabled = false;
                    updateSaveButton(currentFileHasOverride);
                    return;
                }

                saveResult = await fetchJson('saveSubLayout', {
                    plugin_element: pluginElement,
                    file_id: fileId,
                    block_order: blockOrder
                }, 'POST');
            }

            isDirty = false;

            // Update badge and UI to reflect that override now exists
            if (saveResult && saveResult.hasOverride) {
                currentFileHasOverride = true;
                updateStatusBar(true);
                updateSaveButton(true);
                updateResetButton(true);
                updateFileOptionBadge(fileSelect.value, true);
                // Mark selected option as customized for future selections
                const selectedOpt = fileSelect.options[fileSelect.selectedIndex];
                if (selectedOpt) selectedOpt.dataset.hasOverride = '1';
            }

            saveBtn.disabled = false;
            updateSaveButton(currentFileHasOverride);
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
            saveBtn.disabled = false;
            updateSaveButton(currentFileHasOverride);
        }
    }

    // ===== Device Switching =====
    const deviceNameMap = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };
    document.querySelectorAll('[data-device]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!editor) return;

            document.querySelectorAll('[data-device]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const deviceName = deviceNameMap[btn.dataset.device] || 'Desktop';
            editor.setDevice(deviceName);
        });
    });

    // ===== Event Listeners =====
    if (fileSelect) {
        fileSelect.addEventListener('change', () => {
            if (!fileSelect.value) return;

            if (isDirty) {
                const confirmMsg = messages.unsavedChanges || 'You have unsaved changes. Are you sure you want to leave?';
                if (!window.confirm(confirmMsg)) {
                    // Revert selector to previous value — find option matching current editor state
                    // We can't easily revert, so just warn and proceed if confirmed is false, abort
                    return;
                }
            }

            const [pluginElement, fileId] = fileSelect.value.split('::');
            const selectedOpt = fileSelect.options[fileSelect.selectedIndex];
            currentFileHasOverride = selectedOpt && selectedOpt.dataset.hasOverride === '1';
            updateStatusBar(currentFileHasOverride);
            updateSaveButton(currentFileHasOverride);
            updateResetButton(currentFileHasOverride);
            loadProject(pluginElement, fileId);
        });
    }

    if (productSelect) {
        productSelect.addEventListener('change', async () => {
            if (!editor || !fileSelect || !fileSelect.value) return;
            const [pluginElement, fileId] = fileSelect.value.split('::');
            await loadProject(pluginElement, fileId);
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', saveLayout);
    }

    if (undoBtn) {
        undoBtn.addEventListener('click', () => editor && editor.UndoManager.undo());
    }

    if (redoBtn) {
        redoBtn.addEventListener('click', () => editor && editor.UndoManager.redo());
    }

    window.addEventListener('beforeunload', (e) => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ===== Keyboard Shortcuts =====
    document.addEventListener('keydown', (e) => {
        // Only fire when the builder tab is visible
        const builderContainer = document.getElementById('j2commerce-builder-container');
        if (!builderContainer || !editor) return;

        // Do not intercept when focus is inside a form input
        const tag = document.activeElement && document.activeElement.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

        const isCtrlOrCmd = e.ctrlKey || e.metaKey;

        if (isCtrlOrCmd && e.key === 's') {
            e.preventDefault();
            saveLayout();
            return;
        }

        if (isCtrlOrCmd && e.shiftKey && (e.key === 'z' || e.key === 'Z')) {
            e.preventDefault();
            editor.UndoManager.redo();
            return;
        }

        if (isCtrlOrCmd && (e.key === 'z' || e.key === 'Z')) {
            e.preventDefault();
            editor.UndoManager.undo();
            return;
        }

        if (e.key === 'Escape') {
            editor.select(null);
            return;
        }

        if (e.key === 'Delete' || e.key === 'Backspace') {
            const selected = editor.getSelected();
            if (selected && selected.get('removable') !== false) {
                selected.remove();
            }
        }
    });

    // ===== Template Presets =====
    async function loadPresets() {
        try {
            const data = await fetchJson('listPresets', {});
            renderPresetCards(data.presets || []);
        } catch (err) {
            if (presetsGrid) {
                const alert = document.createElement('div');
                alert.className = 'col-12';
                const alertInner = document.createElement('div');
                alertInner.className = 'alert alert-warning';
                alertInner.textContent = err.message;
                alert.appendChild(alertInner);
                presetsGrid.textContent = '';
                presetsGrid.appendChild(alert);
            }
        }
    }

    function renderPresetCards(presets) {
        if (!presetsGrid) return;
        presetsGrid.textContent = '';

        if (presets.length === 0) {
            const col = document.createElement('div');
            col.className = 'col-12';
            const p = document.createElement('p');
            p.className = 'text-muted text-center';
            p.textContent = messages.noPresets || 'No presets available.';
            col.appendChild(p);
            presetsGrid.appendChild(col);
            return;
        }

        const iconMap = {
            'minimal-card': 'fa-solid fa-square',
            'detailed-card': 'fa-solid fa-list',
            'horizontal-layout': 'fa-solid fa-table-columns',
            'price-focused': 'fa-solid fa-tag',
        };

        presets.forEach(preset => {
            const col = document.createElement('div');
            col.className = 'col-md-6';

            const card = document.createElement('div');
            card.className = 'card h-100 cursor-pointer border-2';
            card.style.cursor = 'pointer';

            const body = document.createElement('div');
            body.className = 'card-body text-center';

            const icon = document.createElement('i');
            icon.className = (iconMap[preset.id] || 'fa-solid fa-layer-group') + ' fa-2x text-warning mb-2';

            const title = document.createElement('h6');
            title.className = 'card-title';
            title.textContent = preset.name;

            const desc = document.createElement('p');
            desc.className = 'card-text small text-muted';
            desc.textContent = preset.description || '';

            body.appendChild(icon);
            body.appendChild(title);
            body.appendChild(desc);
            card.appendChild(body);
            col.appendChild(card);
            presetsGrid.appendChild(col);

            card.addEventListener('click', () => applyPreset(preset.id));
            card.addEventListener('mouseenter', () => card.classList.add('border-warning'));
            card.addEventListener('mouseleave', () => card.classList.remove('border-warning'));
        });
    }

    async function applyPreset(presetId) {
        if (!editor || !fileSelect || !fileSelect.value) return;

        try {
            const data = await fetchJson('loadPreset', { preset: presetId });
            const blockOrder = data.blockOrder || [];

            if (blockOrder.length === 0) return;

            // Close modal
            const modalEl = document.getElementById('builder-templates-modal');
            if (modalEl) {
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            }

            // Re-render canvas with preset block order
            const [pluginElement, fileId] = fileSelect.value.split('::');
            currentBlockOrder = blockOrder;

            const productId = productSelect ? parseInt(productSelect.value, 10) : 0;
            const blocks = blockOrder
                .filter(slug => availableBlocks[slug])
                .map(slug => ({
                    slug,
                    settings: Object.fromEntries(
                        Object.entries(availableBlocks[slug].settings || {}).map(([k, v]) => [k, v.default])
                    )
                }));

            const renderData = await fetchJson('renderAllBlocks', {
                product_id: productId,
                edit_mode: true,
                blocks
            }, 'POST');

            const blockHtml = Object.values(renderData.blocks || {}).join('\n');
            const html = '<div class="j2commerce-product-item j2commerce-type-simple d-flex flex-column" style="max-width:400px;">' + blockHtml + '</div>';
            initGrapesJS(null, html);

            isDirty = true;

            if (messages.presetApplied) {
                Joomla.renderMessages({ message: [messages.presetApplied] });
            }
        } catch (err) {
            Joomla.renderMessages({ error: [err.message] });
        }
    }

    if (templatesBtn) {
        templatesBtn.addEventListener('click', () => {
            loadPresets();
            const modalEl = document.getElementById('builder-templates-modal');
            if (modalEl) {
                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            }
        });
    }

    // ===== Populate File Selector =====
    function populateFileSelector() {
        if (!fileSelect) return;

        const subLayouts = config.subLayoutFiles || [];
        const badgeDefault = messages.badgeDefault || 'DEFAULT';
        const badgeCustomized = messages.badgeCustomized || 'CUSTOMIZED';

        subLayouts.forEach(sl => {
            const opt = document.createElement('option');
            opt.value = sl.value;
            const badge = sl.hasOverride ? badgeCustomized : badgeDefault;
            opt.textContent = sl.label + ' [' + badge + ']';
            opt.dataset.hasOverride = sl.hasOverride ? '1' : '0';
            fileSelect.appendChild(opt);
        });
    }

    populateFileSelector();

    // ===== Reset to Default =====
    if (resetBtn) {
        resetBtn.addEventListener('click', async () => {
            if (!fileSelect || !fileSelect.value) return;

            const confirmMsg = messages.resetConfirm || 'This will delete your customizations and revert to the default layout. Continue?';
            if (!window.confirm(confirmMsg)) return;

            const [pluginElement, fileId] = fileSelect.value.split('::');
            resetBtn.disabled = true;

            try {
                await fetchJson('resetToDefault', {
                    plugin_element: pluginElement,
                    file_id: fileId
                }, 'POST');

                currentFileHasOverride = false;
                updateStatusBar(false);
                updateSaveButton(false);
                updateResetButton(false);
                updateFileOptionBadge(fileSelect.value, false);

                // Reload canvas from source
                await loadProject(pluginElement, fileId);
            } catch (err) {
                Joomla.renderMessages({ error: [err.message] });
            } finally {
                resetBtn.disabled = false;
            }
        });
    }
})();
