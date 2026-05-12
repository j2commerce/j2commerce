/**
 * J2Commerce Site Accessibility JavaScript
 *
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 */

/**
    Applies accessibility attributes to known live-region elements and any future element that opts in via selectors or data attributes.

    By default, it adds aria-live="assertive" to .j2commerce-notifications so screen readers announce cart error messages (e.g. "already in cart") immediately.
    If we were to apply it to flexi prices, we would add aria-live="polite" to the flexi-price element so screen readers announce the price change immediately.

    usage:
       1. To apply attributes to existing elements, add rules to the Joomla options:
        From javascript:
            Joomla.loadOptions('com_j2commerce.a11yLiveRegions', [
                {
                    selector: '.my-custom-live-region',
                    attributes: {
                        'role': 'alert',
                        'aria-live': 'assertive',
                        'aria-atomic': 'true'
                    }
                }
            ]);
        Or from PHP:
            $options = [
                [
                    'selector' => '.my-custom-live-region',
                    'attributes' => [
                        'role' => 'status',
                        'aria-live' => 'polite',
                        'aria-atomic' => 'true',
                    ],
                ],
            ];
           $document->addScriptOptions('com_j2commerce.a11yLiveRegions', $options);

        2. To apply attributes to specific elements, add data attributes:
            <div data-j2-a11y-live="assertive" data-j2-a11y-atomic="true">...</div>

            Supports markup-driven opt-in on any element using:
            data-j2-a11y-live
            data-j2-a11y-role
            data-j2-a11y-atomic
            data-j2-a11y-relevant
 */
document.addEventListener('DOMContentLoaded', function () {
    const defaultRules = [
        {
            selector: '.j2commerce-notifications',
            attributes: {
                'role': 'alert',
                'aria-live': 'assertive',
                'aria-atomic': 'true'
            }
        }
    ];

    const configuredRules = window.Joomla?.getOptions?.('com_j2commerce.a11yLiveRegions', []);
    const a11yRules = [...defaultRules, ...configuredRules].filter(function (rule) {
        return rule && typeof rule.selector === 'string' && rule.selector.trim() !== '' && typeof rule.attributes === 'object';
    });

    const dataAttributeSelector = '[data-j2-a11y-live], [data-j2-a11y-role], [data-j2-a11y-atomic], [data-j2-a11y-relevant]';

    function applyAttributes(el, attributes) {
        if (!el || !attributes) {
            return;
        }

        Object.entries(attributes).forEach(function ([name, value]) {
            if (value === null || typeof value === 'undefined' || value === false) {
                el.removeAttribute(name);
                return;
            }

            el.setAttribute(name, String(value));
        });
    }

    function applyDataAttributes(el) {
        if (!el?.dataset) {
            return;
        }

        const attributes = {};

        if (el.dataset.j2A11yRole) {
            attributes.role = el.dataset.j2A11yRole;
        }

        if (el.dataset.j2A11yLive) {
            attributes['aria-live'] = el.dataset.j2A11yLive;
        }

        if (el.dataset.j2A11yAtomic) {
            attributes['aria-atomic'] = el.dataset.j2A11yAtomic;
        }

        if (el.dataset.j2A11yRelevant) {
            attributes['aria-relevant'] = el.dataset.j2A11yRelevant;
        }

        applyAttributes(el, attributes);
    }

    function applyRules(root) {
        a11yRules.forEach(function (rule) {
            root.querySelectorAll(rule.selector).forEach(function (el) {
                applyAttributes(el, rule.attributes);
            });
        });

        root.querySelectorAll(dataAttributeSelector).forEach(applyDataAttributes);
    }

    function applyToNode(node) {
        if (!node || node.nodeType !== 1) {
            return;
        }

        if (typeof node.matches === 'function') {
            a11yRules.forEach(function (rule) {
                if (node.matches(rule.selector)) {
                    applyAttributes(node, rule.attributes);
                }
            });

            if (node.matches(dataAttributeSelector)) {
                applyDataAttributes(node);
            }
        }

        if (typeof node.querySelectorAll === 'function') {
            applyRules(node);
        }
    }

    applyRules(document);

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(applyToNode);
        });
    }).observe(document.body, { childList: true, subtree: true });
});
