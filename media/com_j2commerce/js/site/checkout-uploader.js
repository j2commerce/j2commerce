/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Checkout file uploader — lightweight Uppy wrapper for multiuploader custom fields.
 *
 * @since  6.2.0
 */

const J2CheckoutUploader = (() => {
    'use strict';

    const instances = new Map();

    /**
     * Format file size to human-readable string.
     */
    const formatSize = (bytes) => {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
    };

    /**
     * Get file type icon class based on extension.
     */
    const getFileIcon = (name) => {
        const ext = (name.split('.').pop() || '').toLowerCase();
        const iconMap = {
            pdf: 'fa-solid fa-file-pdf',
            doc: 'fa-solid fa-file-word',
            docx: 'fa-solid fa-file-word',
            xls: 'fa-solid fa-file-excel',
            xlsx: 'fa-solid fa-file-excel',
            jpg: 'fa-solid fa-file-image',
            jpeg: 'fa-solid fa-file-image',
            png: 'fa-solid fa-file-image',
            gif: 'fa-solid fa-file-image',
            webp: 'fa-solid fa-file-image',
            zip: 'fa-solid fa-file-zipper',
            rar: 'fa-solid fa-file-zipper',
            txt: 'fa-solid fa-file-lines',
            csv: 'fa-solid fa-file-csv',
        };
        return iconMap[ext] || 'fa-solid fa-file';
    };

    /**
     * Get translated string via Joomla.Text.
     */
    const t = (key) => {
        if (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._) {
            return Joomla.Text._(key, key);
        }
        return key;
    };

    /**
     * Render the file list from the hidden input's JSON value.
     */
    const renderFileList = (container) => {
        const hiddenInput = container.querySelector('input[type="hidden"]');
        const listEl = container.querySelector('.j2c-upload-file-list');

        if (!hiddenInput || !listEl) return;

        let files = [];
        try {
            files = JSON.parse(hiddenInput.value || '[]');
        } catch (_e) {
            files = [];
        }

        if (!Array.isArray(files) || files.length === 0) {
            listEl.replaceChildren();
            return;
        }

        listEl.replaceChildren();
        const ul = document.createElement('ul');
        ul.className = 'j2c-file-list list-unstyled mb-0';

        files.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'j2c-file-item d-flex align-items-center gap-2 py-1';

            const icon = document.createElement('i');
            icon.className = getFileIcon(file.name);

            const nameSpan = document.createElement('span');
            nameSpan.className = 'j2c-file-name flex-grow-1 text-truncate';
            nameSpan.textContent = file.name;

            const sizeSpan = document.createElement('span');
            sizeSpan.className = 'j2c-file-size text-muted small';
            sizeSpan.textContent = formatSize(file.size || 0);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger j2c-file-remove';
            removeBtn.textContent = t('COM_J2COMMERCE_CHECKOUT_UPLOAD_REMOVE');
            removeBtn.dataset.index = index;

            li.append(icon, nameSpan, sizeSpan, removeBtn);
            ul.appendChild(li);
        });

        listEl.appendChild(ul);
    };

    /**
     * Initialize a single uploader container.
     */
    const initUploader = (container) => {
        const fieldId = container.dataset.fieldId;

        if (instances.has(fieldId)) return;

        const hiddenInput = container.querySelector('input[type="hidden"]');
        const dashboardEl = container.querySelector('.j2c-uppy-dashboard');

        if (!hiddenInput || !dashboardEl || typeof Uppy === 'undefined') return;

        const maxFiles = parseInt(container.dataset.maxFiles, 10) || 5;
        const maxFileSize = parseInt(container.dataset.maxFileSize, 10) || 10485760;
        const allowedTypesStr = container.dataset.allowedTypes || '';
        const uploadUrl = container.dataset.uploadUrl || '';
        const directory = container.dataset.directory || 'images/checkout-uploads';
        const isRequired = container.dataset.required === '1';

        // Convert comma-separated extensions to Uppy-compatible format
        let restrictions = {
            maxNumberOfFiles: maxFiles,
            maxFileSize: maxFileSize,
        };

        if (allowedTypesStr) {
            restrictions.allowedFileTypes = allowedTypesStr.split(',').map(ext => '.' + ext.trim());
        }

        // Build the note line showing max size and accepted types
        const maxSizeMB = Math.round(maxFileSize / (1024 * 1024));
        let noteText = '';
        if (allowedTypesStr) {
            noteText = t('COM_J2COMMERCE_CHECKOUT_UPLOAD_NOTE')
                .replace('%s', maxSizeMB)
                .replace('%s', allowedTypesStr.toUpperCase().split(',').map(s => s.trim()).join(', '));
        } else {
            noteText = 'Max ' + maxSizeMB + ' MB per file';
        }

        const uppy = new Uppy.Uppy({
            id: 'checkout-uploader-' + fieldId,
            restrictions: restrictions,
            autoProceed: true,
            locale: {
                strings: {
                    dropPasteFiles: t('COM_J2COMMERCE_CHECKOUT_UPLOAD_DROP_OR_BROWSE'),
                    browseFiles: t('COM_J2COMMERCE_CHECKOUT_UPLOAD_BROWSE'),
                    uploading: t('COM_J2COMMERCE_CHECKOUT_UPLOAD_UPLOADING'),
                    complete: t('COM_J2COMMERCE_CHECKOUT_UPLOAD_COMPLETE'),
                    uploadFailed: t('COM_J2COMMERCE_CHECKOUT_UPLOAD_ERROR'),
                },
            },
        });

        uppy.use(Uppy.Dashboard, {
            target: dashboardEl,
            inline: true,
            width: '100%',
            height: 180,
            hideUploadButton: true,
            showProgressDetails: true,
            proudlyDisplayPoweredByUppy: false,
            note: noteText,
        });

        uppy.use(Uppy.XHRUpload, {
            endpoint: uploadUrl,
            fieldName: 'file',
            formData: true,
            headers: {},
            getResponseData: (responseText) => {
                try {
                    const parsed = JSON.parse(responseText);
                    if (parsed.success && parsed.data) {
                        return parsed.data;
                    }
                } catch (_e) {
                    // ignore
                }
                return {};
            },
        });

        // On successful upload, append file to the hidden input JSON
        uppy.on('upload-success', (file, response) => {
            // Try response.body first (Uppy 4 getResponseData result),
            // then fall back to parsing response.body as wrapper
            let data = response.body || {};
            if (data.success && data.data) {
                data = data.data;
            }
            // If still no name, use the original file name from Uppy
            if (!data.name && file && file.name) {
                data.name = file.name;
            }
            if (!data.size && file && file.size) {
                data.size = file.size;
            }
            let files = [];
            try {
                files = JSON.parse(hiddenInput.value || '[]');
            } catch (_e) {
                files = [];
            }

            if (!Array.isArray(files)) files = [];

            files.push({
                name: data.name || '',
                path: data.path || '',
                url: data.url || '',
                size: data.size || 0,
            });

            hiddenInput.value = JSON.stringify(files);
            renderFileList(container);
        });

        // Remove file from list on button click (event delegation)
        container.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.j2c-file-remove');
            if (!removeBtn) return;

            const index = parseInt(removeBtn.dataset.index, 10);
            let files = [];
            try {
                files = JSON.parse(hiddenInput.value || '[]');
            } catch (_e) {
                files = [];
            }

            if (Array.isArray(files) && index >= 0 && index < files.length) {
                files.splice(index, 1);
                hiddenInput.value = JSON.stringify(files);
                renderFileList(container);
            }
        });

        // Required validation on form submit
        if (isRequired) {
            const form = container.closest('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    let files = [];
                    try {
                        files = JSON.parse(hiddenInput.value || '[]');
                    } catch (_e) {
                        files = [];
                    }

                    if (!Array.isArray(files) || files.length === 0) {
                        e.preventDefault();
                        e.stopPropagation();

                        // Show validation message
                        let msg = container.querySelector('.j2c-upload-validation-msg');
                        if (!msg) {
                            msg = document.createElement('div');
                            msg.className = 'j2c-upload-validation-msg invalid-feedback d-block';
                            container.appendChild(msg);
                        }
                        msg.textContent = t('COM_J2COMMERCE_CHECKOUT_UPLOAD_REQUIRED');
                    }
                });
            }
        }

        // Render any existing files (e.g., when navigating back to this checkout step)
        renderFileList(container);

        instances.set(fieldId, uppy);
    };

    /**
     * Initialize all uploader containers within a parent element.
     * Called on DOMContentLoaded and after AJAX checkout step loads.
     */
    const init = (parent) => {
        const root = parent || document;
        const containers = root.querySelectorAll('.j2c-checkout-uploader');
        containers.forEach(initUploader);
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init());
    } else {
        init();
    }

    // Re-init after AJAX checkout step loads
    document.addEventListener('j2commerce:checkout:stepLoaded', (e) => {
        init(e.detail?.container || document);
    });

    return { init };
})();
