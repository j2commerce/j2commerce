/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, Uppy) => {
    'use strict';

    if (!Uppy) {
        console.warn('J2CommerceImagePicker: Uppy library not loaded');
        return;
    }

    class J2CommerceImagePicker {
        constructor(element) {
            this.element = element;
            this.options = JSON.parse(element.dataset.options || '{}');
            this.multiple = element.dataset.multiple === 'true';
            this.max = parseInt(element.dataset.max, 10) || 0;

            this.selectedPaths = this.loadExistingPaths();
            this.browserSelections = new Set();
            this.uploadedInSession = [];
            this.sessionKey = 'j2img_lastdir_' + (element.id || 'default');
            this.currentFolder = this.loadLastDirectory() || this.options.directory || 'images';
            this.siteRoot = element.dataset.siteRoot || '';
            this.uppy = null;
            this.modalEl = null;
            this.bsModal = null;
            this.csrfToken = this.findCsrfToken();

            this.init();
        }

        loadExistingPaths() {
            const input = this.element.querySelector('input[type="hidden"]');
            if (!input?.value) return [];

            if (this.element.dataset.multiple === 'true') {
                try {
                    const arr = JSON.parse(input.value);
                    return Array.isArray(arr) ? arr.filter(Boolean) : [];
                } catch {
                    return input.value ? [input.value] : [];
                }
            }
            return input.value ? [input.value] : [];
        }

        findCsrfToken() {
            let token = Joomla.getOptions('csrf.token');
            if (!token) {
                const hiddenInputs = document.querySelectorAll('input[type="hidden"][value="1"]');
                for (const input of hiddenInputs) {
                    if (input.name && /^[a-f0-9]{32}$/i.test(input.name)) {
                        token = input.name;
                        break;
                    }
                }
            }
            return token;
        }

        loadLastDirectory() {
            try { return sessionStorage.getItem(this.sessionKey) || ''; }
            catch { return ''; }
        }

        saveLastDirectory(path) {
            try { sessionStorage.setItem(this.sessionKey, path); }
            catch { /* ignore */ }
        }

        init() {
            this.modalEl = this.element.querySelector('.uppymedia-modal');
            if (!this.modalEl) return;

            // Open modal via JS instead of data-bs-target to avoid duplicate-ID
            // issues when this field is inside a Joomla subform repeatable row.
            const chooseBtn = this.element.querySelector('.j2commerce-image-choose-btn');
            if (chooseBtn) {
                chooseBtn.addEventListener('click', () => {
                    if (!this.bsModal) {
                        this.bsModal = new bootstrap.Modal(this.modalEl);
                    }
                    this.bsModal.show();
                });
            }

            this.setupModalHandlers();
            this.setupRemoveHandlers();
            this.updateChooseButton();
        }

        setupModalHandlers() {
            this.modalEl.addEventListener('shown.bs.modal', () => {
                this.initUppy();
                this.loadFolders().then(() => {
                    const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
                    if (folderSelect) {
                        let option = folderSelect.querySelector(`option[value="${CSS.escape(this.currentFolder)}"]`);
                        if (!option && this.currentFolder) {
                            option = document.createElement('option');
                            option.value = this.currentFolder;
                            option.textContent = this.currentFolder;
                            folderSelect.appendChild(option);
                        }
                        if (option) folderSelect.value = this.currentFolder;
                    }
                });
                this.loadFolderContents(this.currentFolder);
            });

            this.modalEl.addEventListener('hidden.bs.modal', () => {
                this.browserSelections.clear();
                this.uploadedInSession = [];
                // Reset Uppy so it reinitializes fresh on next modal open
                if (this.uppy) {
                    this.uppy.clear();
                    this.uppy.destroy();
                    this.uppy = null;
                }
            });

            const doneBtn = this.modalEl.querySelector('.uppymedia-done-btn');
            if (doneBtn) {
                doneBtn.addEventListener('click', () => this.confirmSelection());
            }

            const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
            if (folderSelect) {
                folderSelect.addEventListener('change', (e) => {
                    this.currentFolder = e.target.value;
                    this.loadFolderContents(this.currentFolder);
                });
            }

            const createFolderBtn = this.modalEl.querySelector('.uppymedia-create-folder');
            if (createFolderBtn) {
                createFolderBtn.addEventListener('click', () => this.createFolder());
            }

            const filterInput = this.modalEl.querySelector('.uppymedia-filter-input');
            if (filterInput) {
                filterInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase().trim();
                    const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
                    if (!grid) return;
                    grid.querySelectorAll('.uppymedia-browser-image').forEach(img => {
                        img.style.display = (img.dataset.name || '').toLowerCase().includes(term) || !term ? '' : 'none';
                    });
                    grid.querySelectorAll('.uppymedia-browser-folder').forEach(folder => {
                        const name = (folder.querySelector('.uppymedia-folder-name')?.textContent || '').toLowerCase();
                        folder.style.display = name.includes(term) || !term ? '' : 'none';
                    });
                });
            }
        }

        initUppy() {
            if (this.uppy) return;

            const dashboardTarget = this.modalEl.querySelector('.uppymedia-dashboard');
            if (!dashboardTarget) return;

            this.uppy = new Uppy.Uppy({
                id: this.element.id + '-uppy',
                autoProceed: false,
                restrictions: {
                    maxFileSize: this.options.maxFileSize || 10 * 1024 * 1024,
                    allowedFileTypes: this.options.allowedFileTypes || ['image/*'],
                },
                meta: { path: this.currentFolder },
            });

            this.uppy.use(Uppy.Dashboard, {
                target: dashboardTarget,
                inline: true,
                width: '100%',
                height: 180,
                showProgressDetails: true,
                showRemoveButtonAfterComplete: true,
                proudlyDisplayPoweredByUppy: false,
                locale: {
                    strings: {
                        dropHereOr: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DROP_HERE_OR'),
                        browse: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_BROWSE'),
                        uploadComplete: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_COMPLETE'),
                        uploadPaused: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_PAUSED'),
                    }
                },
            });

            // Replace default Uppy AddFiles UI with custom button matching product edit modal
            const addFilesEl = dashboardTarget.querySelector('.uppy-Dashboard-AddFiles');
            if (addFilesEl) {
                const inner = addFilesEl.querySelector('.uppy-Dashboard-AddFiles-title');
                if (inner) {
                    inner.innerHTML = `<span class="uppymedia-uppy-btn"><span class="fa-solid fa-images" aria-hidden="true"></span> ${this.escapeHtml(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_IMAGES'))}</span>`
                        + `<p class="uppymedia-uppy-hint">${this.escapeHtml(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DRAG_DROP_NOTE'))}</p>`;
                }
                // Wire browse button to Uppy's hidden file input
                const browseSpan = addFilesEl.querySelector('.uppymedia-uppy-btn');
                if (browseSpan) {
                    browseSpan.style.cursor = 'pointer';
                    browseSpan.addEventListener('click', () => {
                        const fileInput = dashboardTarget.querySelector('.uppy-Dashboard-input');
                        if (fileInput) fileInput.click();
                    });
                }
            }

            this.uppy.use(Uppy.ThumbnailGenerator, {
                thumbnailWidth: 200,
                thumbnailHeight: 200,
                thumbnailType: 'image/webp',
                waitForThumbnailsBeforeUpload: false,
            });

            if (this.options.enableCompression && Uppy.Compressor) {
                this.uppy.use(Uppy.Compressor, { quality: 0.8, mimeType: 'image/webp' });
            }

            let endpoint = this.options.endpoint || 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json';
            const allowedMeta = ['path', 'autoThumbnail'];

            if (this.csrfToken) {
                endpoint += (endpoint.includes('?') ? '&' : '?') + encodeURIComponent(this.csrfToken) + '=1';
                allowedMeta.push(this.csrfToken);
            }

            this.uppy.use(Uppy.XHRUpload, {
                endpoint,
                formData: true,
                fieldName: 'file',
                allowedMetaFields: allowedMeta,
                headers: { 'Accept': 'application/json' },
                getResponseData: (xhr) => {
                    try {
                        const json = JSON.parse(xhr.responseText);
                        return json.success ? (json.data || {}) : {};
                    } catch {
                        return {};
                    }
                },
            });

            if (this.csrfToken) {
                this.uppy.setMeta({ [this.csrfToken]: '1' });
            }

            this.uppy.on('file-added', (file) => {
                this.uppy.setFileMeta(file.id, {
                    path: this.currentFolder,
                    autoThumbnail: this.options.autoThumbnail ? '1' : '0',
                });
            });

            this.uppy.on('upload-success', (file, response) => {
                const fileData = response.body?.data || response.body || {};
                const uploadedPath = fileData.path || '';
                if (uploadedPath) {
                    this.uploadedInSession.push(uploadedPath);
                    this.appendToBrowserGrid({
                        name: fileData.name || file.name,
                        path: uploadedPath,
                        url: fileData.url || '',
                        thumb_url: fileData.thumb_url || fileData.url || '',
                    });
                }
            });

            this.uppy.on('upload-error', (file, error) => {
                Joomla.renderMessages({ error: [error.message || this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_ERROR')] });
            });

            this.uppy.on('complete', (result) => {
                if (result.successful.length > 0) {
                    Joomla.renderMessages({ success: [this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_SUCCESS')] });
                }
            });
        }

        setupRemoveHandlers() {
            this.element.querySelector('.j2commerce-image-thumbs')?.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.j2commerce-image-remove');
                if (!removeBtn) return;

                const thumb = removeBtn.closest('.j2commerce-image-thumb');
                if (!thumb) return;

                const thumbs = [...this.element.querySelectorAll('.j2commerce-image-thumb')];
                const index = thumbs.indexOf(thumb);
                if (index >= 0 && index < this.selectedPaths.length) {
                    this.selectedPaths.splice(index, 1);
                    this.updateHiddenInput();
                    this.renderThumbnails();
                    this.updateChooseButton();
                }
            });
        }

        confirmSelection() {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (grid) {
                grid.querySelectorAll('.uppymedia-browser-image.selected').forEach(el => {
                    const path = el.dataset.path;
                    if (!path || this.selectedPaths.includes(path)) return;

                    if (!this.multiple) {
                        // Single mode: replace
                        this.selectedPaths = [path];
                    } else if (!this.max || this.selectedPaths.length < this.max) {
                        this.selectedPaths.push(path);
                    }
                });
            }

            this.updateHiddenInput();
            this.renderThumbnails();
            this.updateChooseButton();

            this.browserSelections.clear();
            this.uploadedInSession = [];
            if (this.uppy) this.uppy.clear();

            if (!this.bsModal) {
                this.bsModal = bootstrap.Modal.getInstance(this.modalEl);
            }
            this.bsModal?.hide();
        }

        updateHiddenInput() {
            const input = this.element.querySelector('input[type="hidden"]');
            if (!input) return;

            if (this.multiple) {
                input.value = JSON.stringify(this.selectedPaths);
            } else {
                input.value = this.selectedPaths[0] || '';
            }
        }

        renderThumbnails() {
            const container = this.element.querySelector('.j2commerce-image-thumbs');
            if (!container) return;

            const previewStyle = this.options.previewStyle || 'square';
            const isEditable = !this.element.hasAttribute('disabled') && !this.element.hasAttribute('readonly');

            container.innerHTML = this.selectedPaths.map(path => `
                <div class="j2commerce-image-thumb ${previewStyle === 'contain' ? 'preview-contain' : ''}" data-path="${this.escapeHtml(path)}">
                    <img src="${this.escapeHtml(this.resolveImageUrl(path))}" alt="" loading="lazy">
                    ${isEditable ? `<button type="button" class="j2commerce-image-remove" aria-label="${this.getText('JACTION_DELETE')}">
                        <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                    </button>` : ''}
                </div>
            `).join('');
        }

        updateChooseButton() {
            const btn = this.element.querySelector('.j2commerce-image-choose-btn');
            if (!btn) return;

            if (this.multiple && this.max && this.selectedPaths.length >= this.max) {
                btn.style.display = 'none';
            } else {
                btn.style.display = '';
            }
        }

        // --- Folder/browser methods (reuse UppyMediaField patterns) ---

        async loadFolders() {
            const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
            if (!folderSelect) return;

            try {
                const data = await this.fetchApi('folders');
                if (data.success && Array.isArray(data.data)) {
                    folderSelect.innerHTML = '';
                    data.data.forEach(path => {
                        const option = document.createElement('option');
                        option.value = path;
                        option.textContent = path;
                        if (path === this.currentFolder) option.selected = true;
                        folderSelect.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('J2CommerceImagePicker: Failed to load folders:', e);
            }
        }

        async createFolder() {
            const name = prompt(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER_PROMPT'));
            if (!name?.trim()) return;

            const safeName = name.trim().replace(/[^a-zA-Z0-9_\-]/g, '');
            if (!safeName) {
                Joomla.renderMessages({ error: [this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_INVALID_FOLDER_NAME')] });
                return;
            }

            try {
                const result = await this.fetchApi('create-folder', { path: this.currentFolder, name: safeName });
                if (result.success) {
                    this.currentFolder = result.data.path;
                    await this.loadFolders();
                    const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
                    if (folderSelect) {
                        let option = folderSelect.querySelector(`option[value="${CSS.escape(this.currentFolder)}"]`);
                        if (!option) {
                            option = document.createElement('option');
                            option.value = this.currentFolder;
                            option.textContent = this.currentFolder;
                            folderSelect.appendChild(option);
                        }
                        folderSelect.value = this.currentFolder;
                    }
                    this.loadFolderContents(this.currentFolder);
                } else {
                    Joomla.renderMessages({ error: [result.message || 'Failed to create folder'] });
                }
            } catch {
                Joomla.renderMessages({ error: ['Failed to create folder'] });
            }
        }

        async loadFolderContents(path) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            const loading = this.modalEl.querySelector('.uppymedia-browser-loading');
            if (!grid) return;

            if (loading) loading.classList.remove('d-none');
            grid.innerHTML = '';

            try {
                const data = await this.fetchApi('list', { path });
                if (loading) loading.classList.add('d-none');

                if (data.success) {
                    this.currentFolder = data.data.current_path || path;
                    this.saveLastDirectory(this.currentFolder);
                    this.renderBrowserGrid(data.data.folders || [], data.data.files || []);
                    if (this.uppy) this.uppy.setMeta({ path: this.currentFolder });
                } else {
                    grid.innerHTML = `<div class="text-center text-muted p-3" style="grid-column:1/-1">${this.escapeHtml(data.message || 'Error loading folder')}</div>`;
                }
            } catch {
                if (loading) loading.classList.add('d-none');
                grid.innerHTML = '<div class="text-center text-danger p-3" style="grid-column:1/-1">Failed to load images</div>';
            }
        }

        renderBrowserGrid(folders, files) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (!grid) return;
            grid.innerHTML = '';

            folders.forEach(folderName => {
                if (folderName === 'thumbs' || folderName === 'tiny') return;

                const folderEl = document.createElement('div');
                folderEl.className = 'uppymedia-browser-folder';
                folderEl.innerHTML = `
                    <span class="fa-solid fa-folder-open" aria-hidden="true"></span>
                    <span class="uppymedia-folder-name">${this.escapeHtml(folderName)}</span>
                `;

                folderEl.addEventListener('click', () => {
                    const newPath = this.currentFolder + '/' + folderName;
                    this.currentFolder = newPath;
                    this.loadFolderContents(newPath);
                    const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
                    if (folderSelect) {
                        if (!folderSelect.querySelector(`option[value="${CSS.escape(newPath)}"]`)) {
                            const option = document.createElement('option');
                            option.value = newPath;
                            option.textContent = newPath;
                            folderSelect.appendChild(option);
                        }
                        folderSelect.value = newPath;
                    }
                });

                grid.appendChild(folderEl);
            });

            files.forEach(file => {
                const imageEl = document.createElement('div');
                imageEl.className = 'uppymedia-browser-image';
                imageEl.dataset.path = file.path;
                imageEl.dataset.url = file.url;
                imageEl.dataset.thumbUrl = file.thumb_url || file.url;
                imageEl.dataset.name = file.name;

                if (this.browserSelections.has(file.path)) {
                    imageEl.classList.add('selected');
                }

                imageEl.innerHTML = `
                    <img src="${this.escapeHtml(file.thumb_url || file.url)}" alt="${this.escapeHtml(file.name)}" loading="lazy">
                    <div class="uppymedia-check"></div>
                    <div class="uppymedia-browser-name">${this.escapeHtml(file.name)}</div>
                `;

                imageEl.addEventListener('click', () => {
                    if (!this.multiple) {
                        // Single mode: deselect all others, select this one
                        grid.querySelectorAll('.uppymedia-browser-image.selected').forEach(el => {
                            el.classList.remove('selected');
                            this.browserSelections.delete(el.dataset.path);
                        });
                        this.browserSelections.add(file.path);
                        imageEl.classList.add('selected');
                    } else {
                        if (this.browserSelections.has(file.path)) {
                            this.browserSelections.delete(file.path);
                            imageEl.classList.remove('selected');
                        } else {
                            // Enforce max cap (account for already-selected paths)
                            if (this.max && (this.selectedPaths.length + this.browserSelections.size) >= this.max) {
                                return;
                            }
                            this.browserSelections.add(file.path);
                            imageEl.classList.add('selected');
                        }
                    }
                });

                grid.appendChild(imageEl);
            });

            if (folders.length === 0 && files.length === 0) {
                grid.innerHTML = '<div class="text-center text-muted p-3" style="grid-column:1/-1">No images in this folder</div>';
            }
        }

        appendToBrowserGrid(file) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (!grid) return;

            const emptyMsg = grid.querySelector('.text-muted, .text-danger');
            if (emptyMsg?.style.gridColumn) emptyMsg.remove();

            const imageEl = document.createElement('div');
            imageEl.className = 'uppymedia-browser-image selected';
            imageEl.dataset.path = file.path;
            imageEl.dataset.url = file.url;
            imageEl.dataset.thumbUrl = file.thumb_url || file.url;
            imageEl.dataset.name = file.name;

            imageEl.innerHTML = `
                <img src="${this.escapeHtml(file.thumb_url || file.url)}" alt="${this.escapeHtml(file.name)}" loading="lazy">
                <div class="uppymedia-check"></div>
                <div class="uppymedia-browser-name">${this.escapeHtml(file.name)}</div>
            `;

            this.browserSelections.add(file.path);

            imageEl.addEventListener('click', () => {
                if (this.browserSelections.has(file.path)) {
                    this.browserSelections.delete(file.path);
                    imageEl.classList.remove('selected');
                } else {
                    if (!this.multiple) {
                        grid.querySelectorAll('.uppymedia-browser-image.selected').forEach(el => {
                            el.classList.remove('selected');
                            this.browserSelections.delete(el.dataset.path);
                        });
                    }
                    this.browserSelections.add(file.path);
                    imageEl.classList.add('selected');
                }
            });

            grid.appendChild(imageEl);
        }

        async fetchApi(action, params = {}) {
            const taskMap = {
                'list': 'listFiles',
                'folders': 'folders',
                'create-folder': 'createFolder',
            };
            const task = taskMap[action] || action;
            const isDestructive = ['create-folder'].includes(action);

            let url = `index.php?option=com_j2commerce&task=multiimageuploader.${task}&format=json`;
            const searchParams = new URLSearchParams();
            if (this.csrfToken) searchParams.set(this.csrfToken, '1');
            Object.entries(params).forEach(([key, value]) => searchParams.set(key, value));

            const fetchOptions = { headers: { 'Accept': 'application/json' } };

            if (isDestructive) {
                fetchOptions.method = 'POST';
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetchOptions.body = searchParams.toString();
            } else {
                fetchOptions.method = 'GET';
                url += '&' + searchParams.toString();
            }

            const response = await fetch(url, fetchOptions);
            return JSON.parse(await response.text());
        }

        resolveImageUrl(path) {
            if (!path) return '';
            // Already absolute URL
            if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('//')) {
                return path;
            }
            // Prepend site root for relative paths (needed in admin context)
            return this.siteRoot + path.replace(/^\/+/, '');
        }

        escapeHtml(text) {
            return String(text || '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;',
                '"': '&quot;', "'": '&#039;'
            })[c]);
        }

        getText(key) {
            return Joomla.Text?._(key) || key;
        }
    }

    const initFields = () => {
        document.querySelectorAll('.j2commerce-image-field:not([data-initialized])').forEach(el => {
            try {
                el._j2imgPicker = new J2CommerceImagePicker(el);
                el.setAttribute('data-initialized', 'true');
            } catch (e) {
                console.error('J2CommerceImagePicker init error:', e);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFields);
    } else {
        initFields();
    }

    // Reinit when subform rows are added
    document.addEventListener('joomla:updated', initFields);

    window.J2CommerceImagePicker = J2CommerceImagePicker;

})(Joomla, globalThis.Uppy || window.Uppy);
