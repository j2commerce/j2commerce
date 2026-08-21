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
     * Ancestry a fragment has to be parsed inside to survive.
     *
     * A document parse puts the fragment in body context, and there the parser discards a
     * table-section start tag outright — a bare <tr> response parses to nothing at all.
     * Parsing it under the elements the tag requires keeps the rows. Nothing else needs this:
     * <option> and <li> are inserted in body context, so they come through a plain parse.
     */
    const PARSE_CONTEXT = {
        caption: ['table'],
        col: ['table', 'colgroup'],
        colgroup: ['table'],
        tbody: ['table'],
        td: ['table', 'tbody', 'tr'],
        tfoot: ['table'],
        th: ['table', 'tbody', 'tr'],
        thead: ['table'],
        tr: ['table', 'tbody'],
    };

    function contextFor(html) {
        const first = /^\s*<([a-zA-Z]+)/.exec(html);

        return first ? PARSE_CONTEXT[first[1].toLowerCase()] || null : null;
    }

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
        const context = contextFor(html || '');
        const source  = context
            ? context.map(tag => '<' + tag + '>').join('') + html
            : (html || '');

        const doc = new DOMParser().parseFromString(source, 'text/html');

        doc.querySelectorAll('script').forEach(script => script.remove());
        // querySelectorAll does not descend into template.content — sweep those separately.
        doc.querySelectorAll('template').forEach(tpl => {
            tpl.content.querySelectorAll('script').forEach(script => script.remove());
        });

        const fragment = document.createDocumentFragment();

        if (context) {
            // Unwrap back to the level the caller passed, leaving the scaffolding behind.
            const parent = doc.querySelector(context[context.length - 1]);
            fragment.append(...(parent ? parent.childNodes : []));
        } else {
            fragment.append(...doc.head.childNodes, ...doc.body.childNodes);
        }

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

    /**
     * Point a modal's iframe at its source the first time that modal opens.
     *
     * Variant rows are fetched over ajax, so a modal inside one is never registered
     * with Joomla's modal script and nothing injects its frame. The frame is emitted
     * with the address in data-src instead, and set here on first open — delegated
     * from the document so rows added later are covered, and once per frame so
     * reopening does not reload it.
     */
    document.addEventListener('show.bs.modal', event => {
        const frame = event.target.querySelector ? event.target.querySelector('iframe[data-src]') : null;

        if (frame) {
            frame.src = frame.dataset.src;
            frame.removeAttribute('data-src');
        }
    });

    window.J2CommerceDom = { parse, adopt, el };
})(window, document);
