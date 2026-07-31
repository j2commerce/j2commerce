/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function (window, document) {
    'use strict';

    /**
     * Parse server-rendered HTML into a fragment with every <script> removed.
     *
     * This is deliberate parity with the innerHTML assignments it replaces: plugin markup
     * renders, it does not execute. Inline handlers (onclick, onerror) still run once adopted,
     * exactly as they did under innerHTML — third-party checkout steps rely on that.
     * Range.createContextualFragment would NOT be equivalent: it leaves parsed scripts runnable.
     *
     * head nodes are carried over because this is a whole-document parse, so a leading <style>
     * or <link> lands in head where innerHTML's fragment parse would have kept it inline.
     */
    function parse(html) {
        const doc = new DOMParser().parseFromString(html || '', 'text/html');

        doc.querySelectorAll('script').forEach(script => script.remove());
        // querySelectorAll does not descend into template.content — sweep those separately.
        doc.querySelectorAll('template').forEach(tpl => {
            tpl.content.querySelectorAll('script').forEach(script => script.remove());
        });

        const fragment = document.createDocumentFragment();
        fragment.append(...doc.head.childNodes, ...doc.body.childNodes);

        return fragment;
    }

    /** Replace a container's children with parsed HTML. Returns the container. */
    function adopt(container, html) {
        if (container) {
            container.replaceChildren(parse(html));
        }

        return container;
    }

    /** Build an element in one call: J2CommerceDom.el('div', {class: 'x'}, 'text'). */
    function el(tag, attributes, text) {
        const node = document.createElement(tag);

        Object.entries(attributes || {}).forEach(([name, value]) => {
            if (value !== null && value !== undefined) {
                node.setAttribute(name, value);
            }
        });

        if (text !== null && text !== undefined && text !== '') {
            node.textContent = text;
        }

        return node;
    }

    window.J2CommerceDom = { parse, adopt, el };
})(window, document);
