/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Checked-out handling for the product/article_edit_link layout.
 *
 * Delegated on document, so links that arrive later — AJAX fragments, plugin panels,
 * modals — are covered without re-binding. One state request per screen: the ids are
 * collected from whatever links exist, and the answer is cached until a check-in
 * changes it.
 */
(() => {
    'use strict';

    const SELECTOR = 'a.j2c-article-link[data-j2c-article-id]';

    const options = (typeof Joomla !== 'undefined' && Joomla.getOptions)
        ? (Joomla.getOptions('com_j2commerce.articleLink') || {})
        : {};

    const token = (typeof Joomla !== 'undefined' && Joomla.getOptions)
        ? (Joomla.getOptions('csrf.token', '') || '')
        : '';

    const text = (key, fallback) => (
        (typeof Joomla !== 'undefined' && Joomla.Text && Joomla.Text._)
            ? Joomla.Text._(key, fallback)
            : fallback
    );

    /** article id -> {editor, date, time, can_checkin}, for the held ones only. */
    let heldState = null;
    let statePromise = null;
    let modalEl = null;

    const collectIds = () => Array.from(document.querySelectorAll(SELECTOR))
        .map(link => parseInt(link.dataset.j2cArticleId, 10))
        .filter(id => id > 0);

    const loadState = () => {
        if (heldState !== null) {
            return Promise.resolve(heldState);
        }

        if (statePromise !== null) {
            return statePromise;
        }

        const ids = collectIds();

        if (!ids.length || !options.stateUrl) {
            heldState = new Map();
            return Promise.resolve(heldState);
        }

        const body = new FormData();
        body.append(token, '1');
        ids.forEach(id => body.append('article_ids[]', String(id)));

        statePromise = fetch(options.stateUrl, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json();
            })
            .then(json => {
                const map = new Map();

                if (json && json.success && json.data && Array.isArray(json.data.articles)) {
                    json.data.articles.forEach(article => map.set(article.id, article));
                }

                heldState = map;
                return heldState;
            })
            .catch(() => {
                // A failed probe must not strand the user on the page: fall back to
                // letting the click through, which is what happened before this ran.
                heldState = new Map();
                return heldState;
            })
            .finally(() => {
                statePromise = null;
            });

        return statePromise;
    };

    const closeModal = () => {
        if (!modalEl) {
            return;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const instance = bootstrap.Modal.getInstance(modalEl);

            if (instance) {
                instance.hide();
                return;
            }
        }

        modalEl.remove();
        modalEl = null;
    };

    const button = (label, classes) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = classes;
        el.textContent = label;
        return el;
    };

    const TITLE_ID = 'articleCheckedOutModalLabel';

    /** The sentence under the title: when it was locked, then what checking in does. */
    const describe = article => {
        const parts = [];

        if (article.date || article.time) {
            parts.push(
                text('COM_J2COMMERCE_ARTICLE_CHECKED_OUT_LOCKED_ON', 'It was locked on %1$s at %2$s.')
                    .replace('%1$s', article.date || '')
                    .replace('%2$s', article.time || '')
            );
        }

        // Only promised to someone who may actually take it over.
        if (article.can_checkin) {
            parts.push(text(
                'COM_J2COMMERCE_ARTICLE_CHECKED_OUT_TAKE_OVER',
                'Check it in to take over editing — any unsaved changes in the other session will be lost.'
            ));
        }

        return parts.join(' ');
    };

    const buildModal = (article, href) => {
        const icon = document.createElement('span');
        icon.className = 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle '
            + 'bg-danger-subtle border border-danger-subtle text-danger-emphasis fw-semibold';
        icon.style.width = '2.25rem';
        icon.style.height = '2.25rem';

        const glyph = document.createElement('span');
        glyph.className = 'fa-solid fa-exclamation';
        glyph.setAttribute('aria-hidden', 'true');
        icon.replaceChildren(glyph);

        const heading = document.createElement('h6');
        heading.className = 'modal-title fw-semibold mb-2 lh-base';
        heading.id = TITLE_ID;
        heading.textContent = text('COM_J2COMMERCE_ARTICLE_CHECKED_OUT_BY', 'This article is checked out by %s')
            .replace('%s', article.editor || '');

        const detail = document.createElement('p');
        detail.className = 'mb-0 small text-body-secondary lh-base';
        detail.textContent = describe(article);

        const copy = document.createElement('div');
        copy.className = 'me-auto';
        copy.replaceChildren(heading, detail);

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'btn-close flex-shrink-0';
        dismiss.setAttribute('aria-label', text('JCLOSE', 'Close'));
        dismiss.addEventListener('click', closeModal);

        const row = document.createElement('div');
        row.className = 'd-flex align-items-start gap-3';
        row.replaceChildren(icon, copy, dismiss);

        const body = document.createElement('div');
        body.className = 'modal-body p-4';
        body.replaceChildren(row);

        const content = document.createElement('div');
        content.className = 'modal-content rounded-1';

        // Dismissing is the X in the body, so a footer only earns its space when there is
        // an action to put in it — someone who may not release the article gets no footer.
        if (article.can_checkin) {
            const checkin = button(
                text('COM_J2COMMERCE_ARTICLE_CHECKIN_AND_CONTINUE', 'Check In and Continue'),
                'btn btn-primary btn-sm rounded-1 px-3'
            );
            checkin.addEventListener('click', () => releaseAndContinue(article.id, href, checkin, body));

            const footer = document.createElement('div');
            footer.className = 'modal-footer justify-content-end px-4 py-3';
            footer.replaceChildren(checkin);

            content.replaceChildren(body, footer);
        } else {
            content.replaceChildren(body);
        }

        const dialog = document.createElement('div');
        dialog.className = 'modal-dialog modal-dialog-centered';
        dialog.replaceChildren(content);

        const wrapper = document.createElement('div');
        wrapper.className = 'modal fade';
        wrapper.id = 'articleCheckedOutModal';
        wrapper.tabIndex = -1;
        wrapper.setAttribute('role', 'dialog');
        wrapper.setAttribute('aria-modal', 'true');
        wrapper.setAttribute('aria-labelledby', TITLE_ID);
        wrapper.replaceChildren(dialog);

        return wrapper;
    };

    const showError = (body, message) => {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger mt-3 mb-0';
        alert.textContent = message;
        body.appendChild(alert);
    };

    const releaseAndContinue = (articleId, href, trigger, body) => {
        if (!options.checkinUrl) {
            return;
        }

        trigger.disabled = true;

        const payload = new FormData();
        payload.append(token, '1');
        payload.append('article_id', String(articleId));

        fetch(options.checkinUrl, {
            method: 'POST',
            body: payload,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                return response.json();
            })
            .then(json => {
                if (!json || !json.success) {
                    throw new Error((json && json.message) || '');
                }

                if (heldState) {
                    heldState.delete(articleId);
                }

                window.location.assign(href);
            })
            .catch(error => {
                trigger.disabled = false;
                showError(body, error.message || text('JERROR_AN_ERROR_HAS_OCCURRED', 'An error has occurred.'));
            });
    };

    const openModal = (article, href) => {
        closeModal();

        modalEl = buildModal(article, href);
        document.body.appendChild(modalEl);

        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.remove();
            modalEl = null;
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }

        // No Bootstrap on this screen: still show the panel rather than navigating blind,
        // with the backdrop Bootstrap would otherwise have inserted.
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        modalEl.appendChild(backdrop);
        modalEl.classList.add('show', 'd-block');
    };

    document.addEventListener('click', event => {
        const link = event.target.closest(SELECTOR);

        if (!link) {
            return;
        }

        const articleId = parseInt(link.dataset.j2cArticleId, 10);

        if (!(articleId > 0)) {
            return;
        }

        // The answer is not in yet on the very first click: hold the navigation, ask,
        // then either open the panel or follow the link that was clicked.
        if (heldState === null) {
            event.preventDefault();

            loadState().then(state => {
                const article = state.get(articleId);

                if (article) {
                    openModal(article, link.href);
                    return;
                }

                window.location.assign(link.href);
            });

            return;
        }

        const article = heldState.get(articleId);

        if (article) {
            event.preventDefault();
            openModal(article, link.href);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadState();
    });

    if (document.readyState !== 'loading') {
        loadState();
    }
})();
