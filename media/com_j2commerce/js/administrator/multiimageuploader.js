/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

((Joomla, Uppy) => {
    'use strict';

    if (!Uppy) {
        console.warn('Uppy library not loaded');
        return;
    }

    class UppyMediaField {
        constructor(element, options = {}) {
            this.element = element;
            this.options = {
                maxFileSize: 10 * 1024 * 1024,
                allowedFileTypes: ['image/*'],
                enableCompression: true,
                enableImageEditor: true,
                autoThumbnail: true,
                endpoint: 'index.php?option=com_j2commerce&task=multiimageuploader.upload&format=json',
                multiple: true,
                directory: 'images',
                ...options
            };

            this.selectedFiles = this.loadExistingFiles();
            this.browserSelections = new Set();
            this.uploadedInSession = [];
            this.sessionKey = 'uppymedia_lastdir_' + (element.id || 'default');
            this.currentFolder = this.loadLastDirectory() || this.options.directory;
            this.uppy = null;
            this.modalEl = null;
            this.bsModal = null;
            this.csrfToken = this.findCsrfToken();

            this.init();
        }

        loadExistingFiles() {
            const input = this.element.querySelector('input[type="hidden"]');
            if (input && input.value) {
                try {
                    return JSON.parse(input.value) || [];
                } catch (e) {
                    return [];
                }
            }
            return [];
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
            try {
                return sessionStorage.getItem(this.sessionKey) || '';
            } catch (e) {
                return '';
            }
        }

        saveLastDirectory(path) {
            try {
                sessionStorage.setItem(this.sessionKey, path);
            } catch (e) {
                // sessionStorage unavailable — silently ignore
            }
        }

        init() {
            this.modalEl = this.element.querySelector('.uppymedia-modal');
            if (!this.modalEl) {
                console.error('MultiImageUploader: Modal element not found');
                return;
            }

            // Set initial empty/non-empty state
            this.element.classList.toggle('has-images', this.selectedFiles.length > 0);

            this.setupModalHandlers();
            this.setupPreviewHandlers();
            this.renderSelectedImages();

            // Sync existing images to form inputs on load so saving
            // without changes still preserves the current images
            this.syncToFormInputs();
        }

        setupModalHandlers() {
            // Lazy-init Uppy when modal opens
            this.modalEl.addEventListener('shown.bs.modal', () => {
                this.initUppy();
                this.loadFolders().then(() => {
                    // Ensure the folder select reflects the remembered directory
                    const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
                    if (folderSelect) {
                        const remembered = this.currentFolder;
                        let option = folderSelect.querySelector(`option[value="${CSS.escape(remembered)}"]`);
                        if (!option && remembered) {
                            option = document.createElement('option');
                            option.value = remembered;
                            option.textContent = remembered;
                            folderSelect.appendChild(option);
                        }
                        if (option) {
                            folderSelect.value = remembered;
                        }
                    }
                });
                this.loadFolderContents(this.currentFolder);
            });

            // Clean up Uppy selections when modal closes without confirming
            this.modalEl.addEventListener('hidden.bs.modal', () => {
                this.browserSelections.clear();
                this.uploadedInSession = [];
                // Reset Uppy so it reinitializes fresh on next modal open
                if (this.dashboardObserver) {
                    this.dashboardObserver.disconnect();
                    this.dashboardObserver = null;
                }
                if (this.uppy) {
                    this.uppy.clear();
                    this.uppy.destroy();
                    this.uppy = null;
                }
            });

            // Done button
            const doneBtn = this.modalEl.querySelector('.uppymedia-done-btn');
            if (doneBtn) {
                doneBtn.addEventListener('click', () => this.confirmSelection());
            }

            // Folder selector change
            const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
            if (folderSelect) {
                folderSelect.addEventListener('change', (e) => {
                    this.currentFolder = e.target.value;
                    this.loadFolderContents(this.currentFolder);
                });
            }

            // Create folder button
            const createFolderBtn = this.modalEl.querySelector('.uppymedia-create-folder');
            if (createFolderBtn) {
                createFolderBtn.addEventListener('click', () => this.createFolder());
            }

            // Image filename filter
            const filterInput = this.modalEl.querySelector('.uppymedia-filter-input');
            if (filterInput) {
                filterInput.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase().trim();
                    const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
                    if (!grid) return;
                    grid.querySelectorAll('.uppymedia-browser-image').forEach(img => {
                        const name = (img.dataset.name || '').toLowerCase();
                        img.style.display = name.includes(term) || !term ? '' : 'none';
                    });
                    // Also show/hide folders based on name
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
            if (!dashboardTarget) {
                console.error('MultiImageUploader: Dashboard target not found in modal');
                return;
            }

            this.uppy = new Uppy.Uppy({
                id: this.element.id + '-uppy',
                autoProceed: false,
                restrictions: {
                    maxFileSize: this.options.maxFileSize,
                    allowedFileTypes: this.options.allowedFileTypes,
                },
                meta: {
                    path: this.currentFolder,
                },
            });

            this.uppy.use(Uppy.Dashboard, {
                target: dashboardTarget,
                inline: true,
                width: '100%',
                height: 180,
                showProgressDetails: true,
                showRemoveButtonAfterComplete: true,
                proudlyDisplayPoweredByUppy: false,
                locale: this.getLocale(),
            });

            // Replace Uppy's default AddFiles UI with custom button + hint.
            // Uses MutationObserver because Uppy re-renders AddFiles when "+ Add more" is clicked.
            const customizeAddFiles = () => {
                const addFilesEl = dashboardTarget.querySelector('.uppy-Dashboard-AddFiles');
                if (!addFilesEl || addFilesEl.dataset.j2customized) return;
                addFilesEl.dataset.j2customized = '1';
                const inner = addFilesEl.querySelector('.uppy-Dashboard-AddFiles-title');
                if (inner) {
                    inner.innerHTML = `<span class="uppymedia-uppy-btn"><i class="fa-solid fa-images" aria-hidden="true"></i> ${this.escapeHtml(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_IMAGES'))}</span>`
                        + `<p class="uppymedia-uppy-hint">${this.escapeHtml(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DRAG_DROP_NOTE'))}</p>`;
                }
                const browseSpan = addFilesEl.querySelector('.uppymedia-uppy-btn');
                if (browseSpan) {
                    browseSpan.style.cursor = 'pointer';
                    browseSpan.addEventListener('click', () => {
                        const fileInput = dashboardTarget.querySelector('.uppy-Dashboard-input');
                        if (fileInput) fileInput.click();
                    });
                }
            };
            customizeAddFiles();
            this.dashboardObserver = new MutationObserver(() => customizeAddFiles());
            this.dashboardObserver.observe(dashboardTarget, { childList: true, subtree: true });

            if (!this.options.fileMode) {
                this.uppy.use(Uppy.ThumbnailGenerator, {
                    thumbnailWidth: 200,
                    thumbnailHeight: 200,
                    thumbnailType: 'image/webp',
                    waitForThumbnailsBeforeUpload: false,
                });

                if (this.options.enableImageEditor && Uppy.ImageEditor) {
                    this.uppy.use(Uppy.ImageEditor, {
                        quality: 0.8,
                        cropperOptions: {
                            aspectRatio: NaN,
                            viewMode: 1,
                            autoCropArea: 1,
                        },
                    });
                }

                if (this.options.enableCompression && Uppy.Compressor) {
                    this.uppy.use(Uppy.Compressor, {
                        quality: 0.8,
                        mimeType: 'image/webp',
                    });
                }
            }

            // Build endpoint URL with CSRF token
            let endpoint = this.options.endpoint;
            if (this.csrfToken) {
                const separator = endpoint.includes('?') ? '&' : '?';
                endpoint = endpoint + separator + encodeURIComponent(this.csrfToken) + '=1';
            }

            const allowedMeta = ['path', 'altText', 'autoThumbnail'];
            if (this.csrfToken) {
                allowedMeta.push(this.csrfToken);
            }

            this.uppy.use(Uppy.XHRUpload, {
                endpoint: endpoint,
                formData: true,
                fieldName: 'file',
                allowedMetaFields: allowedMeta,
                headers: { 'Accept': 'application/json' },
                getResponseData: (xhr) => {
                    try {
                        const text = xhr.responseText;
                        const json = JSON.parse(text);

                        if (!json.success) {
                            console.error('[MultiImageUploader] Server error:', json.message);
                            return {};
                        }

                        const inner = json.data;
                        if (!inner) {
                            return {};
                        }

                        // inner is { name, path, url, ... }
                        return inner;
                    } catch (e) {
                        console.error('[MultiImageUploader] getResponseData error:', e);
                        return {};
                    }
                },
            });

            if (this.csrfToken) {
                this.uppy.setMeta({ [this.csrfToken]: '1' });
            }

            this.setupUppyEvents();
        }

        getAutoThumbnail() {
            const checkbox = this.element.querySelector('.uppymedia-auto-thumbnail');
            return checkbox ? checkbox.checked : this.options.autoThumbnail;
        }

        setupUppyEvents() {
            this.uppy.on('file-added', (file) => {
                this.uppy.setFileMeta(file.id, {
                    altText: '',
                    path: this.currentFolder,
                    autoThumbnail: this.getAutoThumbnail() ? '1' : '0',
                });
            });

            this.uppy.on('upload-success', (file, response) => {
                const fileData = response.body?.data || response.body || {};

                const uploadedFile = {
                    name: fileData.name || file.name,
                    path: fileData.path || '',
                    url: fileData.url || '',
                    thumb_url: fileData.thumb_url || fileData.url || '',
                    alt_text: '',
                    width: fileData.width || 0,
                    height: fileData.height || 0,
                    thumb_path: fileData.thumb_path || '',
                    thumb_width: fileData.thumb_width || 0,
                    thumb_height: fileData.thumb_height || 0,
                    tiny_path: fileData.tiny_path || '',
                    tiny_url: fileData.tiny_url || '',
                    tiny_width: fileData.tiny_width || 0,
                    tiny_height: fileData.tiny_height || 0,
                };

                this.uploadedInSession.push(uploadedFile);

                // Add to browser grid immediately
                this.appendToBrowserGrid(uploadedFile);
            });

            this.uppy.on('upload-error', (file, error) => {
                console.error('Upload error:', error);
                const message = error.message || this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_ERROR');
                Joomla.renderMessages({ error: [message] });
            });

            this.uppy.on('complete', (result) => {
                if (result.successful.length > 0) {
                    Joomla.renderMessages({
                        success: [this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_SUCCESS')]
                    });
                }
            });
        }

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
                        if (path === this.currentFolder) {
                            option.selected = true;
                        }
                        folderSelect.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Failed to load folders:', e);
            }
        }

        async createFolder() {
            const name = prompt(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER_PROMPT'));
            if (!name || !name.trim()) return;

            // Client-side validation: alphanumeric, hyphens, underscores
            const safeName = name.trim().replace(/[^a-zA-Z0-9_\-]/g, '');
            if (!safeName) {
                Joomla.renderMessages({ error: [this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_INVALID_FOLDER_NAME')] });
                return;
            }

            try {
                const result = await this.fetchApi('create-folder', {
                    path: this.currentFolder,
                    name: safeName
                });

                if (result.success) {
                    // Navigate into the new folder
                    this.currentFolder = result.data.path;
                    await this.loadFolders();

                    // Select the new folder in the dropdown
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
            } catch (e) {
                console.error('Failed to create folder:', e);
                Joomla.renderMessages({ error: ['Failed to create folder'] });
            }
        }

        async loadFolderContents(path) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            const loading = this.modalEl.querySelector('.uppymedia-browser-loading');
            if (!grid) return;

            // Show loading
            if (loading) loading.classList.remove('d-none');
            grid.innerHTML = '';

            try {
                const listParams = { path: path };
                if (this.options.fileMode) listParams.fileMode = '1';
                const data = await this.fetchApi('list', listParams);
                if (loading) loading.classList.add('d-none');

                if (data.success) {
                    this.currentFolder = data.data.current_path || path;
                    this.saveLastDirectory(this.currentFolder);
                    this.renderBrowserGrid(data.data.folders || [], data.data.files || []);

                    // Update Uppy meta with current folder
                    if (this.uppy) {
                        this.uppy.setMeta({ path: this.currentFolder });
                    }
                } else {
                    grid.innerHTML = `<div class="text-center text-muted p-3" style="grid-column:1/-1">${this.escapeHtml(data.message || 'Error loading folder')}</div>`;
                }
            } catch (e) {
                if (loading) loading.classList.add('d-none');
                console.error('Failed to load folder contents:', e);
                grid.innerHTML = `<div class="text-center text-danger p-3" style="grid-column:1/-1">Failed to load ${this.options.fileMode ? 'files' : 'images'}</div>`;
            }
        }

        renderBrowserGrid(folders, files) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (!grid) return;

            grid.innerHTML = '';

            // Render subfolders
            folders.forEach(folderName => {
                // Skip thumbs/tiny helper subdirectories
                if (folderName === 'thumbs' || folderName === 'tiny') return;

                const folderEl = document.createElement('div');
                folderEl.className = 'uppymedia-browser-folder';
                folderEl.innerHTML = `
                    <button type="button" class="uppymedia-folder-delete" title="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FOLDER')}">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    </button>
                    <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                    <span class="uppymedia-folder-name">${this.escapeHtml(folderName)}</span>
                `;

                // Click folder to navigate into it (but not if clicking delete)
                folderEl.addEventListener('click', (e) => {
                    if (e.target.closest('.uppymedia-folder-delete')) return;

                    const newPath = this.currentFolder + '/' + folderName;
                    this.currentFolder = newPath;
                    this.loadFolderContents(newPath);

                    // Update folder select if the path exists
                    const folderSelect = this.modalEl.querySelector('.uppymedia-folder-select');
                    if (folderSelect) {
                        const existingOption = folderSelect.querySelector(`option[value="${CSS.escape(newPath)}"]`);
                        if (!existingOption) {
                            const option = document.createElement('option');
                            option.value = newPath;
                            option.textContent = newPath;
                            folderSelect.appendChild(option);
                        }
                        folderSelect.value = newPath;
                    }
                });

                // Delete button on the folder card
                const deleteBtn = folderEl.querySelector('.uppymedia-folder-delete');
                deleteBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const folderPath = this.currentFolder + '/' + folderName;

                    if (!confirm(this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_FOLDER'))) return;

                    try {
                        const result = await this.fetchApi('delete-folder', { path: folderPath });
                        if (result.success) {
                            folderEl.remove();
                        } else {
                            Joomla.renderMessages({ error: [result.message || 'Failed to delete folder'] });
                        }
                    } catch (err) {
                        console.error('Failed to delete folder:', err);
                        Joomla.renderMessages({ error: ['Failed to delete folder'] });
                    }
                });

                grid.appendChild(folderEl);
            });

            // Render images
            files.forEach(file => {
                grid.appendChild(this.createBrowserImageEl(file));
            });

            if (folders.length === 0 && files.length === 0) {
                const emptyText = this.options.fileMode ? 'No files in this folder' : 'No images in this folder';
                grid.innerHTML = `<div class="text-center text-muted p-3" style="grid-column:1/-1">${emptyText}</div>`;
            }
        }

        createBrowserImageEl(file) {
            const imageEl = document.createElement('div');
            imageEl.className = 'uppymedia-browser-image';
            imageEl.dataset.path = file.path;
            imageEl.dataset.url = file.url;
            imageEl.dataset.thumbUrl = file.thumb_url || file.url;
            imageEl.dataset.name = file.name;
            imageEl.dataset.width = file.width || 0;
            imageEl.dataset.height = file.height || 0;

            // Check if already selected
            if (this.browserSelections.has(file.path)) {
                imageEl.classList.add('selected');
            }

            const isImage = this.isImageFile(file.name);
            const mediaHtml = isImage
                ? `<img src="${this.escapeHtml(file.thumb_url || file.url)}" alt="${this.escapeHtml(file.name)}" loading="lazy">`
                : `<div class="uppymedia-file-icon"><i class="fa-solid ${this.getFileIcon(file.name)}"></i></div>`;

            imageEl.innerHTML = `
                ${mediaHtml}
                <div class="uppymedia-check"></div>
                <button type="button" class="uppymedia-browser-delete" title="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FROM_SERVER')}">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                </button>
                <div class="uppymedia-browser-name">${this.escapeHtml(file.name)}</div>
            `;

            // Click image area to toggle selection (but not if clicking delete btn)
            imageEl.addEventListener('click', (e) => {
                if (e.target.closest('.uppymedia-browser-delete')) return;

                const path = imageEl.dataset.path;
                if (this.browserSelections.has(path)) {
                    this.browserSelections.delete(path);
                    imageEl.classList.remove('selected');
                } else {
                    this.browserSelections.add(path);
                    imageEl.classList.add('selected');
                }
            });

            // Delete button — permanently remove from server
            const deleteBtn = imageEl.querySelector('.uppymedia-browser-delete');
            deleteBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const path = imageEl.dataset.path;
                const deleted = await this.deleteImageFromServer(path);
                if (deleted) {
                    // Remove from selections if selected
                    this.browserSelections.delete(path);
                    // Also remove from selectedFiles if already confirmed
                    const idx = this.selectedFiles.findIndex(f => f.path === path);
                    if (idx !== -1) {
                        this.selectedFiles.splice(idx, 1);
                        this.updateHiddenInput();
                        this.renderSelectedImages();
                    }
                    // Remove from DOM
                    imageEl.remove();
                }
            });

            return imageEl;
        }

        appendToBrowserGrid(file) {
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (!grid) return;

            // Remove "no images" message if present
            const emptyMsg = grid.querySelector('.text-muted, .text-danger');
            if (emptyMsg && emptyMsg.style.gridColumn) {
                emptyMsg.remove();
            }

            const imageEl = this.createBrowserImageEl(file);
            // Auto-select uploaded files
            imageEl.classList.add('selected');
            this.browserSelections.add(file.path);

            grid.appendChild(imageEl);
        }

        async confirmSelection() {
            // Collect all selected images from browser grid
            const newFiles = [];
            const grid = this.modalEl.querySelector('.uppymedia-browser-grid');
            if (grid) {
                const selectedEls = grid.querySelectorAll('.uppymedia-browser-image.selected');
                selectedEls.forEach(el => {
                    const path = el.dataset.path;
                    // Don't duplicate if already in selectedFiles
                    const alreadyExists = this.selectedFiles.some(f => f.path === path);
                    if (!alreadyExists) {
                        const newFile = {
                            name: el.dataset.name || '',
                            path: path,
                            url: el.dataset.url || '',
                            thumb_url: el.dataset.thumbUrl || el.dataset.url || '',
                            alt_text: '',
                            width: parseInt(el.dataset.width, 10) || 0,
                            height: parseInt(el.dataset.height, 10) || 0,
                            thumb_path: '',
                            thumb_width: 0,
                            thumb_height: 0,
                            tiny_path: '',
                            tiny_url: '',
                            tiny_width: 0,
                            tiny_height: 0,
                        };

                        // Check if this was uploaded in this session (has thumb/tiny data)
                        const sessionFile = this.uploadedInSession.find(f => f.path === path);
                        if (sessionFile) {
                            newFile.thumb_path = sessionFile.thumb_path || '';
                            newFile.thumb_width = sessionFile.thumb_width || 0;
                            newFile.thumb_height = sessionFile.thumb_height || 0;
                            newFile.tiny_path = sessionFile.tiny_path || '';
                            newFile.tiny_url = sessionFile.tiny_url || '';
                            newFile.tiny_width = sessionFile.tiny_width || 0;
                            newFile.tiny_height = sessionFile.tiny_height || 0;
                        }

                        this.selectedFiles.push(newFile);
                        if (!newFile.thumb_path) {
                            newFiles.push(newFile);
                        }
                    }
                });
            }

            // Generate thumbnails for browser-selected images that lack them (skip in fileMode)
            if (!this.options.fileMode) {
                for (const file of newFiles) {
                    try {
                        const result = await this.fetchApi('thumbnail', { path: file.path });
                        if (result && result.success && result.data) {
                            const d = result.data;
                            file.thumb_path   = d.thumb_path || '';
                            file.thumb_url    = d.thumb_url || '';
                            file.thumb_width  = d.thumb_width || 0;
                            file.thumb_height = d.thumb_height || 0;
                            file.tiny_path    = d.tiny_path || '';
                            file.tiny_url     = d.tiny_url || '';
                            file.tiny_width   = d.tiny_width || 0;
                            file.tiny_height  = d.tiny_height || 0;
                        }
                    } catch (e) {
                        console.warn('Failed to generate thumbnails for', file.path, e);
                    }
                }
            }

            // Update hidden input and preview
            this.updateHiddenInput();
            this.renderSelectedImages();

            // Clear session state
            this.browserSelections.clear();
            this.uploadedInSession = [];

            // Clear Uppy's files for next session
            if (this.uppy) {
                this.uppy.clear();
            }

            // Close modal
            if (!this.bsModal) {
                this.bsModal = bootstrap.Modal.getInstance(this.modalEl);
            }
            if (this.bsModal) {
                this.bsModal.hide();
            }
        }

        setupPreviewHandlers() {
            const preview = this.element.querySelector('.uppymedia-preview');
            if (!preview) return;

            // Checkbox toggle — update checked state and bulk bar visibility
            preview.addEventListener('change', (e) => {
                const checkbox = e.target.closest('.uppymedia-image-checkbox');
                if (checkbox) {
                    const imageEl = checkbox.closest('.uppymedia-image');
                    if (imageEl) {
                        imageEl.classList.toggle('checked', checkbox.checked);
                    }
                    this.updateBulkBar();
                }
            });

            // Alt text — use 'input' event for real-time sync (fires on every keystroke,
            // unlike 'change' which only fires on blur and could lose data if user saves immediately)
            preview.addEventListener('input', (e) => {
                const altInput = e.target.closest('[data-action="alt-text"]');
                if (altInput) {
                    const index = parseInt(altInput.dataset.index, 10);
                    if (this.selectedFiles[index]) {
                        this.selectedFiles[index].alt_text = altInput.value;
                        this.updateHiddenInput();
                    }
                }
            });

            // Bulk remove button
            const bulkRemoveBtn = this.element.querySelector('.uppymedia-bulk-remove');
            if (bulkRemoveBtn) {
                bulkRemoveBtn.addEventListener('click', () => {
                    this.removeCheckedImages();
                });
            }

            // Move arrows — delegated click handler for left/right reorder buttons
            preview.addEventListener('click', (e) => {
                const moveBtn = e.target.closest('.uppymedia-move-btn');
                if (!moveBtn) return;

                e.preventDefault();
                const index = parseInt(moveBtn.dataset.index, 10);
                const direction = moveBtn.dataset.action === 'move-left' ? 'left' : 'right';
                if (!isNaN(index)) {
                    this.moveImage(index, direction);
                }
            });
        }

        /**
         * Update the bulk action bar visibility and count based on checked checkboxes.
         */
        updateBulkBar() {
            const bulkBar = this.element.querySelector('.uppymedia-bulk-bar');
            if (!bulkBar) return;

            const checked = this.element.querySelectorAll('.uppymedia-image-checkbox:checked');
            const count = checked.length;

            if (count > 0) {
                bulkBar.classList.add('visible');
                const countEl = bulkBar.querySelector('.uppymedia-bulk-count');
                if (countEl) {
                    countEl.textContent = count + ' selected';
                }
            } else {
                bulkBar.classList.remove('visible');
            }
        }

        /**
         * Remove all checked images from the preview (dissociate, not server delete).
         */
        removeCheckedImages() {
            const checked = this.element.querySelectorAll('.uppymedia-image-checkbox:checked');
            if (!checked.length) return;

            // Collect indices in descending order so splice doesn't shift later indices
            const indices = Array.from(checked)
                .map(cb => parseInt(cb.dataset.index, 10))
                .filter(i => !isNaN(i))
                .sort((a, b) => b - a);

            for (const index of indices) {
                this.selectedFiles.splice(index, 1);
            }

            this.updateHiddenInput();
            this.renderSelectedImages();
        }

        /**
         * Move an image one position left or right in the array, then re-render.
         */
        moveImage(index, direction) {
            const targetIndex = direction === 'left' ? index - 1 : index + 1;
            if (targetIndex < 0 || targetIndex >= this.selectedFiles.length) return;

            // Swap in the data array
            const temp = this.selectedFiles[index];
            this.selectedFiles[index] = this.selectedFiles[targetIndex];
            this.selectedFiles[targetIndex] = temp;

            this.updateHiddenInput();
            this.renderSelectedImages();
        }

        updateHiddenInput() {
            const input = this.element.querySelector('input[type="hidden"]');
            if (input) {
                input.value = JSON.stringify(this.selectedFiles);
            }
            this.syncToFormInputs();
        }

        /**
         * Build a joomlaImage format value from a path and dimensions.
         * Format: images/path/file.webp#joomlaImage://local-images/path/file.webp?width=700&height=700
         */
        buildJoomlaImageValue(file, pathOverride, widthOverride, heightOverride) {
            const path = pathOverride || file.path || '';
            if (!path) return '';

            // Use explicit overrides when provided (even if 0),
            // only fall back to file dimensions when no override is given
            const w = arguments.length > 2 ? widthOverride : file.width;
            const h = arguments.length > 3 ? heightOverride : file.height;

            let value = path;

            if (w && h) {
                const localPath = path.replace(/^images\//, 'local-images/');
                value += '#joomlaImage://' + localPath + '?width=' + w + '&height=' + h;
            }

            return value;
        }

        /**
         * Derive a variant path (thumbs/tiny) from an original image path.
         * images/products/photo.webp → images/products/{variant}/photo.webp
         */
        _deriveVariantPath(originalPath, variant) {
            if (!originalPath) return '';
            const lastSlash = originalPath.lastIndexOf('/');
            if (lastSlash < 0) return '';
            const dir = originalPath.substring(0, lastSlash);
            const filename = originalPath.substring(lastSlash + 1);
            const dotIndex = filename.lastIndexOf('.');
            const nameNoExt = dotIndex > 0 ? filename.substring(0, dotIndex) : filename;
            return dir + '/' + variant + '/' + nameNoExt + '.webp';
        }

        deriveThumbPath(originalPath) {
            return this._deriveVariantPath(originalPath, 'thumbs');
        }

        deriveTinyPath(originalPath) {
            return this._deriveVariantPath(originalPath, 'tiny');
        }

        /**
         * Sync selectedFiles to hidden form inputs that match the
         * j2commerce productimages save flow (12 fields total):
         *
         * First image (index 0):
         *   main_image, main_image_alt, thumb_image, thumb_image_alt,
         *   tiny_image, tiny_image_alt
         *
         * Remaining images (index 1+):
         *   additional_images[N], additional_images_alt[N],
         *   additional_thumb_images[N], additional_thumb_images_alt[N],
         *   additional_tiny_images[N], additional_tiny_images_alt[N]
         */
        syncToFormInputs() {
            if (this.options.fileMode) return;
            const formPrefix = this.options.formPrefix;
            if (!formPrefix) return;

            const form = this.element.closest('form');
            if (!form) return;

            // Remove all previously created sync inputs
            form.querySelectorAll('.uppymedia-sync-input').forEach(el => el.remove());

            if (this.selectedFiles.length > 0) {
                const mainFile = this.selectedFiles[0];
                const mainValue = this.buildJoomlaImageValue(mainFile);
                const altText = mainFile.alt_text || '';

                // Main image
                this.createSyncInput(form, formPrefix + '[main_image]', mainValue);
                this.createSyncInput(form, formPrefix + '[main_image_alt]', altText);

                // Thumb image — use thumb_path if available, otherwise derive from main
                const thumbPath = mainFile.thumb_path || this.deriveThumbPath(mainFile.path);
                const thumbValue = this.buildJoomlaImageValue(mainFile, thumbPath, mainFile.thumb_width, mainFile.thumb_height);
                this.createSyncInput(form, formPrefix + '[thumb_image]', thumbValue);
                this.createSyncInput(form, formPrefix + '[thumb_image_alt]', altText);

                // Tiny image — use tiny_path if available, otherwise derive from main
                const tinyPath = mainFile.tiny_path || this.deriveTinyPath(mainFile.path);
                const tinyValue = this.buildJoomlaImageValue(mainFile, tinyPath, mainFile.tiny_width, mainFile.tiny_height);
                this.createSyncInput(form, formPrefix + '[tiny_image]', tinyValue);
                this.createSyncInput(form, formPrefix + '[tiny_image_alt]', altText);
            } else {
                this.createSyncInput(form, formPrefix + '[main_image]', '');
                this.createSyncInput(form, formPrefix + '[main_image_alt]', '');
                this.createSyncInput(form, formPrefix + '[thumb_image]', '');
                this.createSyncInput(form, formPrefix + '[thumb_image_alt]', '');
                this.createSyncInput(form, formPrefix + '[tiny_image]', '');
                this.createSyncInput(form, formPrefix + '[tiny_image_alt]', '');
            }

            // Remaining images → JSON-encoded objects with string keys
            // Matches J2Store format: {"0":"images/...","1":"images/...","2":"images/..."}
            const additionalImages = {};
            const additionalImagesAlt = {};
            const additionalThumbImages = {};
            const additionalThumbImagesAlt = {};
            const additionalTinyImages = {};
            const additionalTinyImagesAlt = {};

            for (let i = 1; i < this.selectedFiles.length; i++) {
                const file = this.selectedFiles[i];
                const idx = String(i - 1);
                const altText = file.alt_text || '';

                // Original
                additionalImages[idx] = this.buildJoomlaImageValue(file);
                additionalImagesAlt[idx] = altText;

                // Thumb
                const thumbPath = file.thumb_path || this.deriveThumbPath(file.path);
                const thumbValue = this.buildJoomlaImageValue(file, thumbPath, file.thumb_width, file.thumb_height);
                additionalThumbImages[idx] = thumbValue;
                additionalThumbImagesAlt[idx] = altText;

                // Tiny
                const tinyPath = file.tiny_path || this.deriveTinyPath(file.path);
                const tinyValue = this.buildJoomlaImageValue(file, tinyPath, file.tiny_width, file.tiny_height);
                additionalTinyImages[idx] = tinyValue;
                additionalTinyImagesAlt[idx] = altText;
            }

            // Submit as JSON-encoded object strings in single inputs
            this.createSyncInput(form, formPrefix + '[additional_images]', JSON.stringify(additionalImages));
            this.createSyncInput(form, formPrefix + '[additional_images_alt]', JSON.stringify(additionalImagesAlt));
            this.createSyncInput(form, formPrefix + '[additional_thumb_images]', JSON.stringify(additionalThumbImages));
            this.createSyncInput(form, formPrefix + '[additional_thumb_images_alt]', JSON.stringify(additionalThumbImagesAlt));
            this.createSyncInput(form, formPrefix + '[additional_tiny_images]', JSON.stringify(additionalTinyImages));
            this.createSyncInput(form, formPrefix + '[additional_tiny_images_alt]', JSON.stringify(additionalTinyImagesAlt));
        }

        /**
         * Create a hidden input marked with the sync class so we can clean up later.
         */
        createSyncInput(form, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.className = 'uppymedia-sync-input';
            form.appendChild(input);
        }

        renderSelectedImages() {
            const preview = this.element.querySelector('.uppymedia-preview');
            if (!preview) return;

            const hasImages = this.selectedFiles.length > 0;
            const total = this.selectedFiles.length;
            this.element.classList.toggle('has-images', hasImages);

            const isFileMode = this.options.fileMode;
            let html = this.selectedFiles.map((file, index) => {
                const isImage = this.isImageFile(file.name);
                let mediaHtml;
                if (isFileMode) {
                    mediaHtml = `<div class="uppymedia-file-icon"><i class="fa-solid ${this.getFileIcon(file.name)}"></i></div>`;
                } else if (isImage) {
                    mediaHtml = `<img src="${this.escapeHtml(file.thumb_url || file.url)}" alt="${this.escapeHtml(file.alt_text || '')}">`;
                } else {
                    mediaHtml = `<div class="uppymedia-file-icon"><i class="fa-solid ${this.getFileIcon(file.name)}"></i></div>`;
                }
                const altHtml = isFileMode ? '' : `
                    <input type="text" class="form-control form-control-sm mt-0"
                           placeholder="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT_PLACEHOLDER')}"
                           value="${this.escapeHtml(file.alt_text || '')}"
                           data-action="alt-text" data-index="${index}"
                           aria-label="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT')}">`;
                return `
                <div class="uppymedia-image ${index === 0 && !isFileMode ? 'main-image' : ''}" data-index="${index}">
                    ${mediaHtml}
                    <label class="uppymedia-select-check" title="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_IMAGE')}">
                        <input type="checkbox" class="uppymedia-image-checkbox" data-index="${index}">
                        <span class="uppymedia-checkmark"></span>
                    </label>
                    ${total > 1 ? `<div class="uppymedia-move-arrows">
                        ${index > 0 ? `<button type="button" class="uppymedia-move-btn" data-action="move-left" data-index="${index}" title="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_LEFT')}"><i class="fa-solid fa-chevron-left"></i></button>` : ''}
                        ${index < total - 1 ? `<button type="button" class="uppymedia-move-btn" data-action="move-right" data-index="${index}" title="${this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_RIGHT')}"><i class="fa-solid fa-chevron-right"></i></button>` : ''}
                    </div>` : ''}
                    ${isFileMode ? `<div class="uppymedia-file-name">${this.escapeHtml(file.name)}</div>` : ''}
                    ${altHtml}
                </div>`;
            }).join('');

            // Append "+" add-more card when images exist and field is editable
            if (hasImages && !this.element.hasAttribute('disabled') && !this.element.hasAttribute('readonly')) {
                html += `
                    <div class="uppymedia-add-more" data-bs-toggle="modal" data-bs-target="#${this.modalEl.id}">
                        <span class="uppymedia-add-more-icon">+</span>
                    </div>
                `;
            }

            preview.innerHTML = html;

            // Reset bulk action bar since checkboxes are cleared on re-render
            this.updateBulkBar();
        }

        /**
         * Dissociate image from the product (preview area).
         * Does NOT delete the file from the server.
         */
        dissociateImage(index) {
            this.selectedFiles.splice(index, 1);
            this.updateHiddenInput();
            this.renderSelectedImages();
        }

        /**
         * Delete image from the server (original + thumb + tiny).
         * Used by the modal browser grid's trash icon.
         */
        async deleteImageFromServer(path) {
            if (!path) return false;

            // Check cross-product usage before confirming
            let message = this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE');
            try {
                const usage = await this.fetchApi('check-usage', { path });
                const count = usage?.data?.count || 0;
                if (count > 0) {
                    message = this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_CONFIRM_DELETE_SHARED')
                        .replace('%d', count);
                }
            } catch (e) {
                // If usage check fails, proceed with standard confirm
                console.warn('Usage check failed, proceeding with standard confirm:', e);
            }

            if (!confirm(message)) {
                return false;
            }

            try {
                const result = await this.fetchApi('delete', { path });
                if (result && !result.success) {
                    Joomla.renderMessages({ warning: [result.message || 'Failed to delete file'] });
                    return false;
                }
                return true;
            } catch (e) {
                console.warn('Failed to delete files from server:', e);
                Joomla.renderMessages({ warning: [this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DELETE_FAILED')] });
                return false;
            }
        }

        async fetchApi(action, params = {}) {
            // Map action names to controller task methods
            const taskMap = {
                'list': 'listFiles',
                'folders': 'folders',
                'delete': 'delete',
                'thumbnail': 'thumbnail',
                'check-usage': 'checkUsage',
                'create-folder': 'createFolder',
                'delete-folder': 'deleteFolder',
            };
            const task = taskMap[action] || action;
            const isDestructive = ['delete', 'create-folder', 'delete-folder'].includes(action);

            let url = `index.php?option=com_j2commerce&task=multiimageuploader.${task}&format=json`;
            const searchParams = new URLSearchParams();
            if (this.csrfToken) {
                searchParams.set(this.csrfToken, '1');
            }
            Object.entries(params).forEach(([key, value]) => {
                searchParams.set(key, value);
            });

            const fetchOptions = {
                headers: { 'Accept': 'application/json' },
            };

            if (isDestructive) {
                fetchOptions.method = 'POST';
                fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                fetchOptions.body = searchParams.toString();
            } else {
                fetchOptions.method = 'GET';
                url += '&' + searchParams.toString();
            }

            const response = await fetch(url, fetchOptions);
            const text = await response.text();

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('MultiImageUploader: Response was not JSON:', text.substring(0, 200));
                throw new Error('Invalid JSON response');
            }
        }

        isImageFile(name) {
            const ext = (name || '').split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'].includes(ext);
        }

        getFileIcon(name) {
            const ext = (name || '').split('.').pop().toLowerCase();
            const iconMap = {
                pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word',
                xls: 'fa-file-excel', xlsx: 'fa-file-excel', csv: 'fa-file-excel',
                ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint',
                zip: 'fa-file-zipper', rar: 'fa-file-zipper', '7z': 'fa-file-zipper', gz: 'fa-file-zipper',
                mp3: 'fa-file-audio', wav: 'fa-file-audio', ogg: 'fa-file-audio', flac: 'fa-file-audio', aac: 'fa-file-audio',
                mp4: 'fa-file-video', avi: 'fa-file-video', mkv: 'fa-file-video', mov: 'fa-file-video',
                txt: 'fa-file-lines', log: 'fa-file-lines', md: 'fa-file-lines',
                js: 'fa-file-code', css: 'fa-file-code', html: 'fa-file-code', php: 'fa-file-code', json: 'fa-file-code',
            };
            return iconMap[ext] || 'fa-file';
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

        getLocale() {
            return {
                strings: {
                    dropHereOr: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DROP_HERE_OR'),
                    browse: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_BROWSE'),
                    uploadComplete: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_COMPLETE'),
                    uploadPaused: this.getText('COM_J2COMMERCE_MULTIIMAGEUPLOADER_UPLOAD_PAUSED'),
                }
            };
        }

        destroy() {
            if (this.uppy) {
                this.uppy.close();
            }
        }
    }

    const initFields = () => {
        document.querySelectorAll('.uppymedia-field:not([data-initialized])').forEach(element => {
            try {
                const options = JSON.parse(element.dataset.options || '{}');
                element.uppyMedia = new UppyMediaField(element, options);
                element.setAttribute('data-initialized', 'true');
            } catch (e) {
                console.error('Failed to initialize UppyMediaField:', e);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFields);
    } else {
        initFields();
    }

    document.addEventListener('joomla:updated', initFields);

    window.UppyMediaField = UppyMediaField;

})(Joomla, globalThis.Uppy || window.Uppy);
