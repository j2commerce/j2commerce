/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function () {
    'use strict';

    const STR = window.Joomla && Joomla.Text ? Joomla.Text : null;

    function t(key, fallback) {
        if (!STR) return fallback;
        const value = STR._(key);
        if (!value || value === key) return fallback;
        return value;
    }

    function bytesToMB(bytes) {
        return (bytes / 1024 / 1024).toFixed(2);
    }

    function getMaxSizeBytes(node) {
        const mb = parseFloat(node.dataset.maxsizeMb || '0');
        return mb > 0 ? mb * 1024 * 1024 : 0;
    }

    function getAllowedExts(node) {
        const raw = (node.dataset.allowedExts || '').trim().toLowerCase();
        return raw === '' ? [] : raw.split(',').map(s => s.trim()).filter(Boolean);
    }

    function getExtension(name) {
        const idx = name.lastIndexOf('.');
        return idx === -1 ? '' : name.slice(idx + 1).toLowerCase();
    }

    function clientValidate(file, root) {
        const max = getMaxSizeBytes(root);
        if (max > 0 && file.size > max) {
            return t(
                'COM_J2COMMERCE_UPLOAD_ERR_TOO_LARGE',
                'File is too large. Max allowed: ' + (max / 1024 / 1024).toFixed(0) + ' MB.'
            );
        }
        const allowed = getAllowedExts(root);
        if (allowed.length > 0) {
            const ext = getExtension(file.name);
            if (!ext || !allowed.includes(ext)) {
                return t(
                    'COM_J2COMMERCE_UPLOAD_ERR_BAD_EXT',
                    'This file type is not allowed.'
                );
            }
        }
        return null;
    }

    function setStatus(node, message, kind) {
        let status = node.querySelector('.j2c-upload-status');
        if (!status) {
            status = document.createElement('span');
            status.className = 'j2c-upload-status';
            node.appendChild(status);
        }
        status.classList.remove('is-error', 'is-success');
        if (kind) status.classList.add('is-' + kind);
        status.textContent = message || '';
    }

    function uploadFile(file, root) {
        const ajaxUrl = root.dataset.ajaxUrl;
        const hidden  = document.getElementById(root.dataset.hiddenId);
        if (!ajaxUrl) return;

        const form = new FormData();
        form.append('file', file);

        setStatus(root, t('COM_J2COMMERCE_LOADING', 'Loading...'), null);

        fetch(ajaxUrl, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (json && json.error) {
                    setStatus(root, json.error, 'error');
                    if (hidden) hidden.value = '';
                    return false;
                }
                if (json && json.success) {
                    setStatus(root, json.success, 'success');
                    if (hidden) hidden.value = json.code || '';
                    return true;
                }
                setStatus(root, t('COM_J2COMMERCE_UPLOAD_ERR_GENERIC_ERROR', 'An error occurred.'), 'error');
                return false;
            })
            .catch(err => {
                setStatus(root, (err && err.message) || 'Network error', 'error');
            });
    }

    function initDropzone(zone) {
        const input = zone.querySelector('input.j2c-upload-native');
        const title = zone.querySelector('.dz-title');
        if (!input) return;

        // Drop the label-for activation; the page may sit inside a parent <form> whose
        // submit handler intercepts label clicks. We open the picker explicitly.
        zone.removeAttribute('for');

        const originalTitleNodes = title ? Array.from(title.childNodes).map(n => n.cloneNode(true)) : [];

        zone.addEventListener('click', e => {
            if (e.target === input) return;
            e.preventDefault();
            input.value = '';
            input.click();
        });

        function handleFile(file) {
            if (!file) return;
            const err = clientValidate(file, zone);
            if (err) {
                setStatus(zone, err, 'error');
                zone.classList.remove('has-file');
                if (title) title.replaceChildren(...originalTitleNodes.map(n => n.cloneNode(true)));
                return;
            }
            zone.classList.add('has-file');
            if (title) title.textContent = file.name;
            uploadFile(file, zone);
        }

        ['dragenter', 'dragover'].forEach(ev => {
            zone.addEventListener(ev, e => {
                e.preventDefault();
                zone.classList.add('is-drag');
            });
        });

        ['dragleave', 'drop'].forEach(ev => {
            zone.addEventListener(ev, e => {
                e.preventDefault();
                zone.classList.remove('is-drag');
            });
        });

        zone.addEventListener('drop', e => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length > 0) {
                try { input.files = files; } catch (_) {}
                handleFile(files[0]);
            }
        });

        input.addEventListener('change', () => {
            const f = input.files && input.files[0];
            if (f) handleFile(f);
        });
    }

    function initImageHero(hero) {
        const input = hero.querySelector('input.j2c-upload-native');
        const thumb = hero.querySelector('.ih-thumb');
        const title = hero.querySelector('.ih-title');
        const hint  = hero.querySelector('.ih-hint');
        const cta   = hero.querySelector('.ih-cta');
        if (!input) return;

        // Drop the label-for activation; the page may sit inside a parent <form> whose
        // submit handler intercepts label clicks. We open the picker explicitly.
        hero.removeAttribute('for');

        const originalTitle    = title ? title.textContent : '';
        const originalHint     = hint ? hint.textContent : '';
        const originalCtaNodes = cta ? Array.from(cta.childNodes).map(n => n.cloneNode(true)) : [];

        hero.addEventListener('click', e => {
            if (e.target === input) return;
            e.preventDefault();
            input.value = '';
            input.click();
        });

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) return;

            const err = clientValidate(file, hero);
            if (err) {
                setStatus(hero, err, 'error');
                if (title) title.textContent = originalTitle;
                if (hint)  hint.textContent  = originalHint;
                if (cta)   cta.replaceChildren(...originalCtaNodes.map(n => n.cloneNode(true)));
                return;
            }

            // Replace icon/img with the new preview
            if (thumb) {
                const oldImg = thumb.querySelector('img');
                if (oldImg) oldImg.remove();
                const icon = thumb.querySelector('span.j2c-thumb-icon');
                if (icon) icon.style.display = 'none';
                const img = new Image();
                img.alt = file.name;
                img.src = URL.createObjectURL(file);
                thumb.appendChild(img);
            }

            if (title) title.textContent = file.name;
            if (hint)  hint.textContent  = bytesToMB(file.size) + ' MB · ' + t('COM_J2COMMERCE_UPLOAD_READY', 'ready to upload');

            if (cta) {
                const i = document.createElement('span');
                if (cta.dataset.iconReplaceUk) {
                    i.setAttribute('uk-icon', 'icon: ' + cta.dataset.iconReplaceUk);
                    if (cta.dataset.iconReplaceClass) {
                        i.className = cta.dataset.iconReplaceClass;
                    }
                } else {
                    i.className = cta.dataset.iconReplace || 'fa-solid fa-arrows-rotate';
                }
                i.setAttribute('aria-hidden', 'true');
                cta.replaceChildren(i, document.createTextNode(' ' + t('COM_J2COMMERCE_PRODUCT_OPTION_CHANGE_IMAGE', 'Change Image')));
            }

            uploadFile(file, hero);
        });
    }

    function init() {
        document.querySelectorAll('[data-j2c-dropzone]').forEach(initDropzone);
        document.querySelectorAll('[data-j2c-image-hero]').forEach(initImageHero);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
