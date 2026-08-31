/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
'use strict';

(function (window, document) {
    const ENDPOINT_TASK = 'index.php?option=com_ajax&group=system&plugin=j2commerce&format=json&j2c_task=customerState';

    const HYDRATION_HOOKS = '[data-j2c-cart-count], [data-j2c-cart-badge], [data-j2c-cart-wrapper], [data-j2c-hydrate]';

    let statePromise = null;
    let liveRegion = null;
    let lastAnnouncedCount = null;

    /** Returns '' rather than the key when Joomla.Text is unavailable — a raw key is what a screen reader would read aloud. */
    function translate(key) {
        if (typeof Joomla === 'undefined' || !Joomla.Text) {
            return '';
        }

        const text = Joomla.Text._(key);

        return text === key ? '' : text;
    }

    /**
     * Built from system.paths.root, which Joomla sets on every page. window.j2commerceURL is only
     * a fallback: it is published through an inline script that is not guaranteed to be present,
     * and without a base the relative URL resolves against the current SEF path
     * (/shop-categories/others/index.php?...) and 404s.
     */
    function getEndpointUrl() {
        let base = '';

        if (typeof Joomla !== 'undefined' && typeof Joomla.getOptions === 'function') {
            const paths = Joomla.getOptions('system.paths') || {};

            if (typeof paths.root === 'string') {
                base = paths.root;
            }
        }

        if (base === '' && typeof window.j2commerceURL === 'string') {
            base = window.j2commerceURL;
        }

        return base.replace(/\/+$/, '') + '/' + ENDPOINT_TASK;
    }

    /**
     * Fetches and caches the customerState payload. Shared by the DOMContentLoaded
     * hydrator and the lazy window.J2CommerceToken accessor so the endpoint is
     * requested at most once per page load, success or failure.
     */
    function fetchState() {
        if (statePromise) {
            return statePromise;
        }

        statePromise = fetch(getEndpointUrl(), { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('customerState request failed: ' + response.status);
                }

                return response.json();
            })
            .then(function (json) {
                if (!json || json.success !== true || !json.data) {
                    throw new Error('customerState response malformed');
                }

                return json.data;
            });

        return statePromise;
    }

    // Escape hatch for consumers whose markup arrives after load (an AJAX fragment cannot be
    // caught by the DOMContentLoaded gate). Safe to call repeatedly — fetchState() is cached,
    // so the endpoint is still requested at most once per page.
    window.J2CommerceState = {
        request: function () {
            hydrate();
        }
    };

    window.J2CommerceToken = {
        get: function () {
            return fetchState()
                .then(function (data) {
                    return data.token || '';
                })
                .catch(function () {
                    return '';
                });
        }
    };

    /**
     * Built from JS, never addCustomTag() — a <div> injected into <head> breaks head parsing.
     * Must be in the DOM and empty BEFORE any message lands in it: ARIA22 requires the role to
     * be present before the status occurs, and a region created together with its text is
     * routinely missed by assistive technology.
     * Styling is inlined rather than using a `visually-hidden` class: that class is a Bootstrap
     * utility, this script is registered sitewide, and a UIkit-family store would otherwise
     * render the announcement as visible page text.
     */
    function getLiveRegion() {
        if (liveRegion) {
            return liveRegion;
        }

        liveRegion = document.getElementById('j2c-state-live');

        if (liveRegion) {
            return liveRegion;
        }

        liveRegion = document.createElement('div');
        liveRegion.id = 'j2c-state-live';
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;'
            + 'overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
        document.body.appendChild(liveRegion);

        return liveRegion;
    }

    function announceCartCount(count) {
        let message;

        if (count === 0) {
            message = translate('COM_J2COMMERCE_CART_NO_ITEMS');
        } else if (count === 1) {
            message = translate('COM_J2COMMERCE_N_CART_ITEMS_1');
        } else {
            message = translate('COM_J2COMMERCE_N_CART_ITEMS_MORE')
                .replace('%d', String(count))
                .replace('%s', String(count));
        }

        if (message === '') {
            return;
        }

        getLiveRegion().replaceChildren(document.createTextNode(message));
    }

    function hydrateCart(cart) {
        if (!cart || typeof cart.count !== 'number') {
            return;
        }

        const count = cart.count;
        let changed = false;

        // A badge declares which measure it rendered: the quantity total (default, and what
        // pre-6.6 markup carries as a valueless attribute) or the number of distinct lines.
        // An endpoint that predates lineCount leaves that badge alone rather than rewriting
        // it with a different measure.
        document.querySelectorAll('[data-j2c-cart-count]').forEach(function (el) {
            const value = el.getAttribute('data-j2c-cart-count') === 'lines' ? cart.lineCount : count;

            if (typeof value !== 'number') {
                return;
            }

            const nextText = String(value);

            if (el.textContent.trim() !== nextText) {
                changed = true;
            }

            el.replaceChildren(document.createTextNode(nextText));
        });

        // Bootstrap's reboot marks [hidden] display:none !important, but UIkit's does not and loses
        // to .uk-badge{display:inline-block}. Setting the inline style too keeps both families
        // behaving identically without depending on a framework stylesheet.
        document.querySelectorAll('[data-j2c-cart-badge]').forEach(function (el) {
            el.hidden = count === 0;
            el.style.display = count === 0 ? 'none' : '';
        });

        // Modules set to hide when empty render the wrapper hidden rather than omitting it, so a
        // cache primed by an empty cart still has something to reveal here.
        document.querySelectorAll('[data-j2c-cart-wrapper]').forEach(function (el) {
            el.hidden = count === 0;
            el.style.display = count === 0 ? 'none' : '';
        });

        if (changed && lastAnnouncedCount !== count) {
            announceCartCount(count);
        }

        lastAnnouncedCount = count;
    }

    /** Copies every slice except the live form token, for the two plugin-facing broadcasts. */
    function buildPublicState(data) {
        const publicState = {};

        Object.keys(data).forEach(function (key) {
            if (key !== 'token') {
                publicState[key] = data[key];
            }
        });

        return publicState;
    }

    function hydrate() {
        getLiveRegion();

        fetchState()
            .then(function (data) {
                hydrateCart(data.cart);

                const publicState = buildPublicState(data);

                window.J2CommerceCustomerState = publicState;
                document.dispatchEvent(new CustomEvent('j2c:customer-state', { detail: publicState }));
            })
            .catch(function () {
                // Network/parse failure: leave server-rendered values in place. Never blank.
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        // The script is registered sitewide, but only pages carrying a hydratable placeholder need
        // the request — this endpoint boots the full Joomla stack, so an ungated fetch would put a
        // bootstrap on every page of the site, including ones with no J2Commerce content at all.
        // Carriers that need a token on a placeholder-less page still reach it lazily through
        // window.J2CommerceToken.get().
        // data-j2c-hydrate is the generic opt-in for NON-CORE consumers. Core cannot know a
        // plugin's selectors — that separation is the whole point of the collector — so a
        // plugin that wants the j2c:customer-state broadcast marks any element with it.
        // Without this the gate would serve only the cart, and a page carrying wishlist or
        // garage markup but no minicart would never hydrate at all.
        if (document.querySelector(HYDRATION_HOOKS) === null) {
            return;
        }

        hydrate();
    });
})(window, document);
