/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_cart
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// One listener per page: a single ajaxmini response carries every cart module, whatever its layout.
(() => {
    'use strict';

    const options = (window.Joomla && Joomla.getOptions) ? Joomla.getOptions('com_j2commerce.minicart', {}) : {};
    const refreshUrl = options.refreshUrl || '';

    if (!refreshUrl || window.j2commerceMiniCartRefresh === true) {
        return;
    }
    window.j2commerceMiniCartRefresh = true;

    // SVG element names are matched case-sensitively, hence the camelCase entries.
    const blockedElements = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'base', 'meta', 'link', 'template',
        'animate', 'set', 'animateTransform', 'animateMotion', 'use', 'foreignObject',
    ].join(',');
    const toggleSelector = '[data-j2commerce-cart-toggle]';

    // Browsers drop control characters and spaces inside a URL scheme, and SVG lists values with ; or ,.
    const isScriptUrl = (value) => value.split(/[;,]/)
        .some((part) => /^(javascript|vbscript):/i.test(part.replace(/[\u0000- ]/g, '')));

    // Parsed scripts never run, but handler attributes, srcdoc and script URLs would once the
    // nodes join the page.
    const replaceWithMarkup = (el, html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');

        doc.body.querySelectorAll(blockedElements).forEach((node) => node.remove());
        doc.body.querySelectorAll('*').forEach((node) => {
            [...node.attributes].forEach((attr) => {
                const name = attr.name.toLowerCase();

                if (name.startsWith('on') || name === 'srcdoc' || isScriptUrl(attr.value)) {
                    node.removeAttributeNode(attr);
                }
            });
        });

        el.replaceChildren(...doc.body.childNodes);
    };

    // Replacing the children destroys a focused control; hand focus to the module's cart link or
    // panel toggle, or to the module itself when the new markup shows neither (emptied and hidden).
    const restoreFocus = (el) => {
        const target = el.querySelector('a.j2commerce-minicart-link, ' + toggleSelector);

        if (target) {
            target.focus();
        }

        if (target && document.activeElement === target) {
            return;
        }

        el.setAttribute('tabindex', '-1');
        el.focus();

        if (document.activeElement === el) {
            el.addEventListener('blur', () => el.removeAttribute('tabindex'), { once: true });
        } else {
            el.removeAttribute('tabindex');
        }
    };

    document.addEventListener('j2commerce:cart:updated', () => {
        fetch(refreshUrl, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                return response.json();
            })
            .then((json) => {
                if (!json || !json.response || typeof json.response !== 'object') {
                    return;
                }

                // Read every module's state before replacing any of them, so one module's update
                // cannot change what another one restores.
                const modules = [];

                Object.keys(json.response).forEach((key) => {
                    if (!/^\d+$/.test(key)) {
                        return;
                    }

                    document.querySelectorAll('.j2commerce-cart-module-' + key).forEach((el) => {
                        modules.push({
                            el,
                            html: json.response[key],
                            hadFocus: el.contains(document.activeElement),
                            wasOpen: !!el.querySelector(toggleSelector + '[aria-expanded="true"]'),
                        });
                    });
                });

                modules.forEach(({ el, html, hadFocus, wasOpen }) => {
                    replaceWithMarkup(el, html);

                    // The server renders the panel closed; reopen one the shopper was using.
                    const toggle = wasOpen && el.querySelector(toggleSelector);
                    const panel = toggle && document.getElementById(toggle.getAttribute('data-j2commerce-cart-toggle'));

                    if (panel) {
                        panel.style.display = 'block';
                        toggle.setAttribute('aria-expanded', 'true');
                    }

                    if (hadFocus) {
                        restoreFocus(el);
                    }
                });
            })
            .catch((error) => {
                console.error('Cart module refresh error:', error);
            });
    });
})();
