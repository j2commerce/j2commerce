/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

'use strict';

// SVG placeholder for shortcode images — avoids 404s when GrapesJS renders block content in canvas
const J2C_IMG_PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Crect fill='%23e5e7eb' width='100' height='100'/%3E%3Ctext x='50' y='54' text-anchor='middle' font-family='sans-serif' font-size='11' fill='%236b7280'%3E%5BIMAGE%5D%3C/text%3E%3C/svg%3E";

// A [LANG:KEY] key as resolveLangTokens() defines it, and a CSS property name. Both are
// interpolated into markup the export writes, so both are held to their own shape.
const J2C_LANG_KEY = /^[A-Z][A-Z0-9_]*$/;
const J2C_CSS_PROP = /^-{0,2}[a-zA-Z][a-zA-Z0-9-]*$/;

// Shortcode options for the trait dropdown — populated before editor init
let j2cShortcodeOptions = [];

// key => wording that key resolves to in the admin's own backend language, and whether this user
// may rewrite it. Both are server-supplied and only ever drive what the canvas DISPLAYS; the
// exported value of a token is always the token.
let j2cLangStrings = {};
let j2cCanOverrideLang = false;

/**
 * Drops the tags the target document type deletes at render, so no authoring route can offer a
 * tag that vanishes on print. Takes and returns the nested {category: {tag: desc}} shape.
 */
window.j2cFilterShortcodes = function(shortcodes, strippedTags) {
    if (!shortcodes || !Array.isArray(strippedTags) || !strippedTags.length) {
        return shortcodes;
    }

    const bare = tag => String(tag).replace(/[[\]{}]/g, '').toUpperCase();
    const drop = new Set(strippedTags.map(bare));
    const filtered = {};

    for (const [key, val] of Object.entries(shortcodes)) {
        if (val && typeof val === 'object' && !Array.isArray(val)) {
            const group = {};
            for (const [tag, desc] of Object.entries(val)) {
                if (!drop.has(bare(tag))) {
                    group[tag] = desc;
                }
            }
            filtered[key] = group;
        } else if (!drop.has(bare(key))) {
            filtered[key] = val;
        }
    }

    return filtered;
};

// GrapesJS renders both BlockManager labels and select-trait option labels as markup,
// so registry values are escaped on the way in
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[ch]);
}

document.addEventListener('DOMContentLoaded', () => {
    const options = Joomla.getOptions('com_j2commerce.emaileditor');
    if (!options) return;

    j2cLangStrings = options.langStrings || {};
    j2cCanOverrideLang = !!options.canOverrideLang;

    if (options.bodySource === 'visual') {
        initGrapesJSEditor(options);
    } else {
        setupModeSwitching(null, options);
    }
});

function initGrapesJSEditor(options) {
    const container = document.getElementById('gjs-container');
    const gjsEl = document.getElementById('gjs');
    if (!container || !gjsEl) return;

    const shortcodes = window.j2cFilterShortcodes(options.shortcodes, options.strippedTags);

    container.style.display = '';
    gjsEl.style.height = '700px';
    gjsEl.style.overflow = 'hidden';

    const editorConfig = {
        container: '#gjs',
        fromElement: false,
        height: '700px',
        width: 'auto',
        storageManager: false,
        plugins: ['grapesjs-preset-newsletter', j2commercePlugin],
        pluginsOpts: {
            'grapesjs-preset-newsletter': {
                modalTitleImport: 'Import HTML',
                importPlaceholder: '<table>...</table>',
                cellStyle: {
                    'font-size': '14px',
                    'font-family': 'Arial, Helvetica, sans-serif',
                    'color': '#333333',
                },
            },
        },
        canvas: {
            styles: [],
            scripts: [],
        },
        deviceManager: {
            devices: [
                { name: 'Desktop', width: '' },
                { name: 'Tablet', width: '768px', widthMedia: '992px' },
                { name: 'Mobile landscape', width: '480px', widthMedia: '768px' },
                { name: 'Mobile portrait', width: '320px', widthMedia: '480px' },
            ],
        },
    };

    // Build shortcode options for the trait dropdown before editor init
    // shortcodes may be nested {billing: {tag: desc}, ...} or flat {tag: desc}
    if (shortcodes && typeof shortcodes === 'object' && !Array.isArray(shortcodes)) {
        j2cShortcodeOptions = [];
        for (const [key, val] of Object.entries(shortcodes)) {
            if (val && typeof val === 'object' && !Array.isArray(val)) {
                // Nested: key is category name, val is {tag: desc}
                for (const [tag, desc] of Object.entries(val)) {
                    j2cShortcodeOptions.push({ id: tag, name: `${escapeHtml(tag)} — ${escapeHtml(desc)}` });
                }
            } else {
                // Flat: key is tag, val is description string
                j2cShortcodeOptions.push({ id: key, name: `${escapeHtml(key)} — ${escapeHtml(val)}` });
            }
        }
    }

    // Project data is restored by stored type and never consults isComponent(), so a body_json
    // written before [LANG:KEY] tokens were typed holds them as plain text that would never gain
    // the type exporting them as tokens. Re-import from the stored HTML in that one case — it is
    // the authoritative copy, carries the same inlined styles, and the next save writes a
    // body_json that no longer takes this branch.
    const langTokensUntyped = options.bodyJson
        && (options.bodyHtml || '').includes('[LANG:')
        && !String(options.bodyJson).includes('data-j2c-lang');

    if (options.bodyJson && !langTokensUntyped) {
        try {
            const projectData = JSON.parse(options.bodyJson);
            editorConfig.projectData = projectData;
        } catch (e) {
            if (options.bodyHtml) {
                editorConfig.components = preprocessHtmlForImport(options.bodyHtml);
            }
        }
    } else if (options.bodyHtml) {
        editorConfig.components = preprocessHtmlForImport(options.bodyHtml);
    }

    const editor = grapesjs.init(editorConfig);

    window._j2cGrapesEditor = editor;

    // After load, inject responsive styles, swap shortcode src placeholders, and
    // enable inline editing for <th> and <td> cells.
    editor.on('load', () => {
        const frame = editor.Canvas.getFrameEl();
        if (!frame) return;
        const doc = frame.contentDocument;
        if (!doc) return;

        // Responsive override so email tables/images scale in device preview
        const style = doc.createElement('style');
        style.setAttribute('data-j2c-responsive', '1');
        style.textContent = 'body{overflow-x:hidden}'
            + 'table{max-width:100%!important}'
            + 'img{max-width:100%!important;height:auto!important}'
            + 'td,th{word-break:break-word}';
        doc.head.appendChild(style);

        doc.querySelectorAll('img').forEach(img => {
            const src = img.getAttribute('src') || '';
            if (/^\[[A-Z_]+\]$/.test(src)) {
                img.setAttribute('data-j2c-src', src);
                img.setAttribute('src', J2C_IMG_PLACEHOLDER);
            }
        });

        // GrapesJS's built-in 'cell' type covers both <td> and <th> and provides no
        // text-editing view. Templates loaded from body_json restore <th> as 'cell' type
        // directly, bypassing isComponent detection, so a custom j2c-th type is useless.
        // Instead we use capture-phase event delegation on the canvas document so dblclick
        // on any <th> — regardless of how the template was loaded — becomes editable.
        // Block-level tags whose presence inside a cell means it's a layout container,
        // not a simple text cell — skip those so we don't clobber nested structures.
        const BLOCK_TAGS = new Set(['TABLE','DIV','P','TR','TD','TH','THEAD','TBODY','TFOOT','UL','OL','LI','BLOCKQUOTE','PRE']);

        doc.addEventListener('dblclick', (e) => {
            const el = e.target;
            if (!el || (el.tagName !== 'TD' && el.tagName !== 'TH')) return;
            // Skip layout cells — those whose direct children include block-level elements.
            if (!el.textContent.trim()) return;
            for (const child of el.children) {
                if (BLOCK_TAGS.has(child.tagName)) return;
            }

            e.stopPropagation();

            // Snapshot the selected component before we steal focus from the canvas.
            // GrapesJS selects on mousedown, so by dblclick the component is already set.
            const component = editor.getSelected();

            el.contentEditable = 'true';
            el.focus();

            // Pre-select all text so the user can overtype immediately.
            const range = doc.createRange();
            range.selectNodeContents(el);
            const sel = doc.defaultView?.getSelection();
            if (sel) { sel.removeAllRanges(); sel.addRange(range); }

            // Enter key commits the edit without inserting a newline.
            const onKeyDown = (ke) => { if (ke.key === 'Enter') { ke.preventDefault(); el.blur(); } };
            el.addEventListener('keydown', onKeyDown);

            el.addEventListener('blur', () => {
                el.removeEventListener('keydown', onKeyDown);
                el.contentEditable = 'false';

                // Sync the new text back to the GrapesJS model so Save picks it up.
                if (component) {
                    const newText = el.textContent.trim();
                    component.components().reset([{ type: 'textnode', content: newText }]);
                }
            }, { once: true });

        }, true); // capture phase — fires before GrapesJS's own bubble-phase handlers
    });

    setupFormSyncHandlers(editor);
    setupShortcodeBlocks(editor, shortcodes);
    setupPreviewIntegration(editor, options);
    setupTemplateLoading(editor, options);
    setupModeSwitching(editor, options);
}

function j2commercePlugin(editor) {
    const blockManager = editor.BlockManager;

    blockManager.add('j2c-order-items', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_ITEMS'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-table" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Product</th>
                        <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                        <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Price</th>
                        <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                    </tr>
                </thead>
                <tbody data-j2c-loop="ITEMS">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            [IF:ITEM_IMAGE]<img src="${J2C_IMG_PLACEHOLDER}" data-j2c-src="[ITEM_IMAGE]" width="50" height="50" style="border-radius: 4px; vertical-align: middle; margin-right: 8px;" />[/IF:ITEM_IMAGE]
                            [ITEM_NAME]
                            [IF:ITEM_OPTIONS]<br><small style="color: #666;">[ITEM_OPTIONS]</small>[/IF:ITEM_OPTIONS]
                        </td>
                        <td style="padding: 10px; text-align: center; border-bottom: 1px solid #eee;">[ITEM_QTY]</td>
                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #eee;">[ITEM_PRICE]</td>
                        <td style="padding: 10px; text-align: right; border-bottom: 1px solid #eee;">[ITEM_TOTAL]</td>
                    </tr>
                </tbody>
            </table>`,
    });

    blockManager.add('j2c-billing-address', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_BILLING_ADDRESS'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-address-card" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 15px; background-color: #f9fafb; border-radius: 8px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;">Billing Address</h3>
                        <p style="margin: 0; font-size: 14px; color: #555; line-height: 1.6;">
                            [BILLING_FIRSTNAME] [BILLING_LASTNAME]<br>
                            [IF:BILLING_COMPANY][BILLING_COMPANY]<br>[/IF:BILLING_COMPANY]
                            [BILLING_ADDRESS_1]<br>
                            [IF:BILLING_ADDRESS_2][BILLING_ADDRESS_2]<br>[/IF:BILLING_ADDRESS_2]
                            [BILLING_CITY], [BILLING_STATE] [BILLING_ZIP]<br>
                            [BILLING_COUNTRY]
                        </p>
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-shipping-address', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SHIPPING_ADDRESS'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-truck" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 15px; background-color: #f9fafb; border-radius: 8px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;">Shipping Address</h3>
                        <p style="margin: 0; font-size: 14px; color: #555; line-height: 1.6;">
                            [SHIPPING_FIRSTNAME] [SHIPPING_LASTNAME]<br>
                            [IF:SHIPPING_COMPANY][SHIPPING_COMPANY]<br>[/IF:SHIPPING_COMPANY]
                            [SHIPPING_ADDRESS_1]<br>
                            [IF:SHIPPING_ADDRESS_2][SHIPPING_ADDRESS_2]<br>[/IF:SHIPPING_ADDRESS_2]
                            [SHIPPING_CITY], [SHIPPING_STATE] [SHIPPING_ZIP]<br>
                            [SHIPPING_COUNTRY]
                        </p>
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-order-summary', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_SUMMARY'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-receipt" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 15px; background-color: #f9fafb; border-radius: 8px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 16px; color: #333;">Order Summary</h3>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding: 5px 0; font-size: 14px; color: #555;">Order Number:</td>
                                <td style="padding: 5px 0; font-size: 14px; color: #333; text-align: right; font-weight: bold;">[ORDERID]</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; font-size: 14px; color: #555;">Date:</td>
                                <td style="padding: 5px 0; font-size: 14px; color: #333; text-align: right;">[ORDERDATE]</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; font-size: 14px; color: #555;">Status:</td>
                                <td style="padding: 5px 0; font-size: 14px; color: #333; text-align: right;">[ORDERSTATUS]</td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0; font-size: 14px; color: #555;">Payment:</td>
                                <td style="padding: 5px 0; font-size: 14px; color: #333; text-align: right;">[PAYMENT_TYPE]</td>
                            </tr>
                            <tr style="border-top: 2px solid #ddd;">
                                <td style="padding: 10px 0 5px 0; font-size: 16px; font-weight: bold; color: #333;">Total:</td>
                                <td style="padding: 10px 0 5px 0; font-size: 16px; font-weight: bold; color: #333; text-align: right;">[ORDERAMOUNT]</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-cta-button', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CTA_BUTTON'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-hand-pointer" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="padding: 20px 0;">
                        <table cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="background-color: [ACCENT_COLOR]; border-radius: 6px; padding: 14px 32px;">
                                    <a href="[INVOICE_URL]" style="color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold;">View Your Order</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-store-info', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_STORE_INFO'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-store" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding: 15px 20px; font-size: 14px; line-height: 1.6; color: [TEXT_COLOR];">
                        <strong>[STORE_NAME]</strong><br>
                        [IF:STORE_ADDRESS_1][STORE_ADDRESS_1]<br>[/IF:STORE_ADDRESS_1]
                        [IF:STORE_ADDRESS_2][STORE_ADDRESS_2]<br>[/IF:STORE_ADDRESS_2]
                        [IF:STORE_CITY][STORE_CITY], [/IF:STORE_CITY][IF:STORE_STATE][STORE_STATE] [/IF:STORE_STATE][IF:STORE_ZIP][STORE_ZIP]<br>[/IF:STORE_ZIP]
                        [IF:STORE_COUNTRY][STORE_COUNTRY]<br>[/IF:STORE_COUNTRY]
                        [IF:STORE_PHONE]Tel: [STORE_PHONE]<br>[/IF:STORE_PHONE]
                        [IF:STORE_EMAIL]Email: [STORE_EMAIL][/IF:STORE_EMAIL]
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-store-logo', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_STORE_LOGO'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-image" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="padding: 20px; text-align: center;">
                        [IF:STORE_LOGO_URL]
                        <img src="${J2C_IMG_PLACEHOLDER}" data-j2c-src="[STORE_LOGO_URL]" alt="[SITENAME]" height="[LOGO_MAX_HEIGHT]" style="display: block; margin: 0 auto; border: 0; height: [LOGO_MAX_HEIGHT]px; width: auto;" />
                        [/IF:STORE_LOGO_URL]
                        [IFNOT:STORE_LOGO_URL]
                        <span style="font-size: 24px; font-weight: bold; color: [ACCENT_COLOR];">[SITENAME]</span>
                        [/IFNOT:STORE_LOGO_URL]
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-social-links', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SOCIAL_LINKS'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-share-nodes" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="padding: 15px 0;">
                        [IF:SOCIAL_FACEBOOK]
                        <a href="[SOCIAL_FACEBOOK]" style="display: inline-block; margin: 0 8px; text-decoration: none; color: #555; font-size: 14px;">Facebook</a>
                        [/IF:SOCIAL_FACEBOOK]
                        [IF:SOCIAL_INSTAGRAM]
                        <a href="[SOCIAL_INSTAGRAM]" style="display: inline-block; margin: 0 8px; text-decoration: none; color: #555; font-size: 14px;">Instagram</a>
                        [/IF:SOCIAL_INSTAGRAM]
                        [IF:SOCIAL_TWITTER]
                        <a href="[SOCIAL_TWITTER]" style="display: inline-block; margin: 0 8px; text-decoration: none; color: #555; font-size: 14px;">Twitter</a>
                        [/IF:SOCIAL_TWITTER]
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-conditional-section', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CONDITIONAL'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-code-branch" style="font-size:2.4em"></span>',
        content: {
            type: 'j2c-conditional',
            tagName: 'div',
            attributes: { 'data-j2c-condition': 'TAG' },
            style: {
                'border': '2px dashed #f59e0b',
                'padding': '10px',
                'margin': '5px 0',
            },
            components: [
                { tagName: 'p', content: 'Conditional content goes here. Edit the condition tag in traits.' },
            ],
        },
    });

    blockManager.add('j2c-hook-position', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_HOOK'),
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS'),
        media: '<span class="fa fa-plug" style="font-size:2.4em"></span>',
        content: `<tr data-j2c-hook="AFTER_HEADER"><td style="border:2px dashed #8b5cf6;padding:8px;text-align:center;color:#8b5cf6;font-size:12px;font-family:monospace;background:#f5f3ff;" colspan="1">[HOOK:AFTER_HEADER]</td></tr>`,
    });

    registerCustomComponentTypes(editor);
    registerLangOverride(editor);
}

/**
 * The override dialog, reached from a fifth icon on a [LANG:KEY] component's toolbar.
 *
 * The icon is appended to the toolbar GrapesJS has already built rather than declared in the
 * type's defaults: initToolbar() only assembles the stock select-parent/move/copy/delete set when
 * no toolbar is set, so declaring one replaces those four instead of joining them.
 */
function registerLangOverride(editor) {
    if (!j2cCanOverrideLang) return;

    editor.Commands.add('j2c:open-lang-override', {
        run(ed) {
            const component = ed.getSelected();
            const key = component?.getAttributes()['data-j2c-lang'];

            // The dialog lives in the subject-override module, which is an ES module and cannot
            // be imported from this classic script.
            const overrides = window.J2CommerceLangOverride;

            if (!key || !overrides) return;

            overrides.open({
                key,
                onSaved: (resolved, applied) => {
                    // Only the language this admin reads in changes what the canvas says.
                    if (!applied) return;

                    j2cLangStrings[key] = resolved;

                    if (component.view) {
                        component.view.el.textContent = resolved;
                    }

                    Joomla.renderMessages({
                        message: [Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_SUBJECT_OVERRIDE_SAVED')],
                    });
                },
            });
        },
    });

    editor.on('component:selected', (component) => {
        if (component.get('type') !== 'j2c-lang-text') return;

        const toolbar = component.get('toolbar') || [];

        if (toolbar.some(item => item.command === 'j2c:open-lang-override')) return;

        component.set('toolbar', [...toolbar, {
            attributes: {
                class: 'icon-language',
                title: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_LANG_OVERRIDE_EDIT'),
            },
            command: 'j2c:open-lang-override',
        }]);
    });
}

function registerCustomComponentTypes(editor) {
    const dc = editor.DomComponents;

    dc.addType('j2c-shortcode', {
        isComponent: (el) => {
            if (el.nodeType === Node.ELEMENT_NODE && el.getAttribute('data-j2c-tag')) {
                return { type: 'j2c-shortcode' };
            }
        },
        model: {
            defaults: {
                tagName: 'span',
                droppable: false,
                editable: false,
                attributes: { 'data-j2c-tag': '' },
                traits: [
                    {
                        type: 'select',
                        label: 'Shortcode',
                        name: 'data-j2c-tag',
                        options: j2cShortcodeOptions,
                    },
                ],
                style: {
                    'display': 'inline-block',
                    'background-color': '#dbeafe',
                    'color': '#1e40af',
                    'padding': '2px 8px',
                    'border-radius': '4px',
                    'font-size': '12px',
                    'font-family': 'monospace',
                    'border': '1px solid #93c5fd',
                    'cursor': 'default',
                    'user-select': 'none',
                },
            },
            toHTML() {
                const tag = this.getAttributes()['data-j2c-tag'] || '';
                return tag;
            },
        },
        view: {
            onRender() {
                const tag = this.model.getAttributes()['data-j2c-tag'] || '[TAG]';
                this.el.textContent = tag;
                this.el.contentEditable = 'false';
            },
        },
    });

    // A [LANG:KEY] token. The model exports the token and nothing else; the view shows the
    // wording. Non-editable for the same reason j2c-shortcode is: a stray keystroke in the canvas
    // would otherwise turn a token into plain text that resolveLangTokens() no longer recognises,
    // flattening that template's every other locale on the next save.
    dc.addType('j2c-lang-text', {
        isComponent: (el) => {
            if (el.nodeType === Node.ELEMENT_NODE && el.getAttribute('data-j2c-lang')) {
                return { type: 'j2c-lang-text' };
            }
        },
        model: {
            defaults: {
                tagName: 'span',
                droppable: false,
                editable: false,
                attributes: { 'data-j2c-lang': '' },
            },
            toHTML() {
                const key = this.getAttributes()['data-j2c-lang'] || '';
                if (!key) return '';

                // Exporting the bare token threw away any style set on it, so a colour or size
                // the merchant chose for this run lived on in body_json -- and in the canvas --
                // while the email kept the old look. Carry it out on a plain wrapper; the token
                // inside is still the only thing resolveLangTokens() has to find.
                if (!J2C_LANG_KEY.test(key)) return '';

                const style = this.getStyle() || {};
                const css = Object.entries(style)
                    .filter(([prop, value]) => J2C_CSS_PROP.test(prop) && value !== '' && value != null)
                    .map(([prop, value]) => `${prop}:${String(value).replace(/["<>]/g, '')}`)
                    .join(';');

                return css ? `<span style="${css}">[LANG:${key}]</span>` : `[LANG:${key}]`;
            },
        },
        view: {
            onRender() {
                const key = this.model.getAttributes()['data-j2c-lang'] || '';
                this.el.textContent = j2cLangStrings[key] ?? `[LANG:${key}]`;
                this.el.contentEditable = 'false';
            },
        },
    });

    dc.addType('j2c-conditional', {
        isComponent: (el) => {
            if (el.nodeType === Node.ELEMENT_NODE && el.getAttribute('data-j2c-condition')) {
                return { type: 'j2c-conditional' };
            }
        },
        model: {
            defaults: {
                tagName: 'div',
                droppable: true,
                attributes: { 'data-j2c-condition': 'TAG', 'data-j2c-negate': '0' },
                traits: [
                    {
                        type: 'text',
                        label: 'Condition Tag',
                        name: 'data-j2c-condition',
                    },
                    {
                        type: 'checkbox',
                        label: 'Negate (IFNOT)',
                        name: 'data-j2c-negate',
                        valueTrue: '1',
                        valueFalse: '0',
                    },
                ],
            },
            toHTML() {
                const tag = this.getAttributes()['data-j2c-condition'] || 'TAG';
                const negate = this.getAttributes()['data-j2c-negate'] === '1';
                const prefix = negate ? 'IFNOT' : 'IF';
                const inner = this.getInnerHTML();
                return `[${prefix}:${tag}]${inner}[/${prefix}:${tag}]`;
            },
        },
        view: {
            onRender() {
                const tag = this.model.getAttributes()['data-j2c-condition'] || 'TAG';
                const negate = this.model.getAttributes()['data-j2c-negate'] === '1';
                const prefix = negate ? 'IFNOT' : 'IF';
                // A <tbody>-based conditional cannot host a <div> badge — the canvas
                // parser foster-parents it straight back out of the table.
                if (this.el.tagName === 'TBODY') {
                    this.el.style.outline = '2px dashed #f59e0b';
                    this.el.title = `[${prefix}:${tag}]`;
                    return;
                }

                this.el.style.position = 'relative';
                let label = this.el.querySelector('.j2c-condition-label');
                if (!label) {
                    label = document.createElement('div');
                    label.className = 'j2c-condition-label';
                    this.el.prepend(label);
                }
                label.textContent = `[${prefix}:${tag}]`;
                label.style.cssText = 'position:absolute;top:-12px;left:8px;background:#f59e0b;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;font-family:monospace;z-index:1;';
            },
        },
    });

    dc.addType('j2c-hook', {
        isComponent: (el) => {
            if (el.nodeType === Node.ELEMENT_NODE && el.getAttribute('data-j2c-hook')) {
                return { type: 'j2c-hook' };
            }
        },
        model: {
            defaults: {
                tagName: 'tr',
                droppable: false,
                editable: false,
                attributes: { 'data-j2c-hook': 'AFTER_HEADER' },
                traits: [
                    {
                        type: 'select',
                        label: 'Hook Position',
                        name: 'data-j2c-hook',
                        options: [
                            { id: 'AFTER_HEADER', name: 'After Header' },
                            { id: 'BEFORE_ITEMS', name: 'Before Items' },
                            { id: 'AFTER_ITEMS', name: 'After Items' },
                            { id: 'BEFORE_SHIPPING', name: 'Before Shipping' },
                            { id: 'AFTER_PAYMENT', name: 'After Payment' },
                            { id: 'BEFORE_FOOTER', name: 'Before Footer' },
                        ],
                    },
                ],
            },
            toHTML() {
                const pos = this.getAttributes()['data-j2c-hook'] || 'AFTER_HEADER';
                return `[HOOK:${pos}]`;
            },
        },
        view: {
            onRender() {
                const pos = this.model.getAttributes()['data-j2c-hook'] || 'AFTER_HEADER';

                // Render as a styled table cell inside the <tr>
                const cell = document.createElement('td');
                cell.style.cssText = 'border:2px dashed #8b5cf6;padding:8px;text-align:center;color:#8b5cf6;font-size:12px;font-family:monospace;background:#f5f3ff;';
                cell.setAttribute('colspan', '1');
                cell.textContent = `[HOOK:${pos}]`;

                this.el.replaceChildren(cell);
                this.el.contentEditable = 'false';
            },
        },
    });
}

function setupShortcodeBlocks(editor, shortcodes) {
    if (!shortcodes) return;

    const bm = editor.BlockManager;

    // Flatten nested {category: {tag: desc}} into flat [{tag, desc}] pairs
    const flat = [];
    for (const [key, val] of Object.entries(shortcodes)) {
        if (val && typeof val === 'object' && !Array.isArray(val)) {
            for (const [tag, desc] of Object.entries(val)) {
                flat.push([tag, desc]);
            }
        } else {
            flat.push([key, val]);
        }
    }

    flat.forEach(([tag, desc]) => {
        const cleanId = tag.replace(/[\[\]{}]/g, '');
        bm.add(`j2c-tag-${cleanId}`, {
            label: `<span style="font-family:monospace;font-size:11px">${escapeHtml(tag)}</span><br><small>${escapeHtml(desc)}</small>`,
            category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_SHORTCODES'),
            media: '<span class="fa fa-tag" style="font-size:1.6em"></span>',
            content: { type: 'j2c-shortcode', attributes: { 'data-j2c-tag': tag } },
        });
    });
}

window.updateJ2CShortcodeBlocks = function(shortcodes) {
    const editor = window._j2cGrapesEditor;
    if (!editor || !shortcodes) return;

    const bm = editor.BlockManager;

    // Remove existing j2c-tag-* blocks
    bm.getAll().filter(b => b.id.startsWith('j2c-tag-')).forEach(b => bm.remove(b.id));

    // Re-add from new shortcode data
    setupShortcodeBlocks(editor, shortcodes);

    // Update the shortcode dropdown trait options
    j2cShortcodeOptions = [];
    for (const [key, val] of Object.entries(shortcodes)) {
        if (val && typeof val === 'object' && !Array.isArray(val)) {
            for (const [tag, desc] of Object.entries(val)) {
                j2cShortcodeOptions.push({ id: tag, name: `${escapeHtml(tag)} — ${escapeHtml(desc)}` });
            }
        } else {
            j2cShortcodeOptions.push({ id: key, name: `${escapeHtml(key)} — ${escapeHtml(val)}` });
        }
    }
};

function restoreShortcodeSrcInBody() {
    const bodyField = document.getElementById('jform_body');
    if (!bodyField) return;

    let html = bodyField.value;
    if (!html || !html.includes('data-j2c-src')) return;

    // Restore src="[SHORTCODE]" from data-j2c-src and remove the placeholder data URI
    html = html.replace(/(<img[^>]*)\ssrc="data:[^"]*"\s*data-j2c-src="(\[[A-Z_]+\])"/gi, '$1 src="$2"');
    bodyField.value = html;
}

function restoreShortcodeSrcInHtml(html) {
    if (!html || !html.includes('data-j2c-src')) return html;
    return html.replace(/(<img[^>]*)\ssrc="data:[^"]*"\s*data-j2c-src="(\[[A-Z_]+\])"/gi, '$1 src="$2"');
}

function setupFormSyncHandlers(editor) {
    const form = document.getElementById('adminForm');
    if (!form) return;

    const originalSubmitForm = Joomla.submitform;
    Joomla.submitform = function(task, submitForm, validate) {
        // Cancel keeps nothing, so there is nothing to sync. Core resolves the cancel task the
        // same way, from data-cancel-task or the <prefix>.cancel convention.
        const cancelTask = form.getAttribute('data-cancel-task')
            || `${String(task ?? '').split('.')[0]}.cancel`;

        if (task !== cancelTask) {
            try {
                const bodySourceField = document.querySelector('select[name="jform[body_source]"]');
                if (bodySourceField && bodySourceField.value === 'visual' && window._j2cGrapesEditor) {
                    syncGrapesDataToForm(window._j2cGrapesEditor);
                }
                // Restore shortcode src attributes from data-j2c-src placeholders (editor/file mode)
                restoreShortcodeSrcInBody();
            } catch (err) {
                // Submitting anyway would store a body the canvas never wrote. Refuse, and say so:
                // every toolbar button routes through here, and a throw here is indistinguishable
                // from a dead click.
                Joomla.renderMessages({
                    error: [`${Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_SYNC_FAILED')} ${err.message}`],
                });

                return false;
            }
        }

        return originalSubmitForm.call(this, task, submitForm, validate);
    };
}

window.syncGrapesDataToForm = function syncGrapesDataToForm(editor) {
    let html = editor.runCommand('gjs-get-inlined-html');
    html = postprocessHtmlForExport(html);
    const projectData = editor.getProjectData();
    const json = JSON.stringify(projectData);

    const bodyField = document.getElementById('jform_body');
    if (bodyField) bodyField.value = html;

    const bodyJsonField = document.getElementById('jform_body_json');
    if (bodyJsonField) bodyJsonField.value = json;
}

function setupPreviewIntegration(editor, options) {
    const previewBtn = document.getElementById('btn-refresh-preview');
    if (!previewBtn) return;

    previewBtn.addEventListener('click', async (e) => {
        e.stopImmediatePropagation();

        const bodySourceField = document.querySelector('select[name="jform[body_source]"]');
        let body = '';

        if (bodySourceField && bodySourceField.value === 'visual' && window._j2cGrapesEditor) {
            body = postprocessHtmlForExport(window._j2cGrapesEditor.runCommand('gjs-get-inlined-html'));
        } else {
            const bodyField = document.getElementById('jform_body');
            body = bodyField ? bodyField.value : '';
            body = restoreShortcodeSrcInHtml(body);
        }

        const subject = document.querySelector('input[name="jform[subject]"]')?.value || '';
        const customCss = document.querySelector('textarea[name="jform[custom_css]"]')?.value || '';
        const token = options.csrfToken;

        previewBtn.disabled = true;
        const origNodes = [...previewBtn.childNodes];

        const spinnerLabel = document.createElement('span');
        spinnerLabel.className = 'visually-hidden';
        spinnerLabel.textContent = Joomla.Text._('COM_J2COMMERCE_LOADING');

        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');
        spinner.append(spinnerLabel);

        previewBtn.replaceChildren(spinner);

        try {
            const formData = new FormData();
            formData.append('body', body);
            formData.append('subject', subject);
            formData.append('custom_css', customCss);
            formData.append('email_type', document.getElementById('jform_email_type')?.value || 'transactional');
            formData.append(token, '1');

            const response = await fetch(options.previewUrl, { method: 'POST', body: formData });
            if (response.ok) {
                const iframe = document.getElementById('email-preview-iframe');
                if (iframe) {
                    // srcdoc, not document.write: the frame is sandboxed, so its document
                    // is not reachable from here and script never runs in it.
                    iframe.srcdoc = await response.text();
                }
            }
        } catch (err) {
            Joomla.renderMessages({ error: ['Preview failed: ' + err.message] });
        }

        previewBtn.disabled = false;
        previewBtn.replaceChildren(...origNodes);
    }, true);
}

function setupTemplateLoading(editor, options) {
    document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', async () => {
            const type = card.getAttribute('data-template-type');
            const design = card.getAttribute('data-template-design');
            const token = options.csrfToken;

            if (!confirm(Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE_CONFIRM'))) {
                return;
            }

            try {
                const url = `${options.loadTemplateUrl}&${token}=1&type=${encodeURIComponent(type)}&design=${encodeURIComponent(design)}`;
                const response = await fetch(url);
                const json = await response.json();

                if (json.success && json.body) {
                    const bodySourceField = document.querySelector('select[name="jform[body_source]"]');

                    // The loaded template carries keys the page was never built around, and the
                    // server resolved them alongside the body. Merged before the import so the
                    // canvas shows wording rather than brackets on the very first render.
                    Object.assign(j2cLangStrings, json.langStrings || {});

                    if (bodySourceField && bodySourceField.value === 'visual' && window._j2cGrapesEditor) {
                        window._j2cGrapesEditor.setComponents(preprocessHtmlForImport(json.body));
                        const bodyJsonField = document.getElementById('jform_body_json');
                        if (bodyJsonField) bodyJsonField.value = '';
                    } else {
                        const bodyField = document.getElementById('jform_body');
                        if (bodyField) bodyField.value = json.body;
                    }

                    Joomla.renderMessages({ message: ['Template loaded successfully.'] });
                    document.getElementById('loadTemplateModal')?.querySelector('[data-bs-dismiss=modal]')?.click();
                } else {
                    Joomla.renderMessages({ error: [json.message || 'Failed to load template.'] });
                }
            } catch (err) {
                Joomla.renderMessages({ error: ['Load failed: ' + err.message] });
            }
        });
    });
}

function setupModeSwitching(editor, options) {
    const bodySourceField = document.querySelector('select[name="jform[body_source]"]');
    if (!bodySourceField) return;

    bodySourceField.addEventListener('change', () => {
        const newMode = bodySourceField.value;
        const container = document.getElementById('gjs-container');
        const bodyField = document.getElementById('jform_body');

        if (newMode === 'visual') {
            if (!window._j2cGrapesEditor && options) {
                initGrapesJSEditor(options);
            } else if (bodyField && bodyField.value && window._j2cGrapesEditor) {
                window._j2cGrapesEditor.setComponents(preprocessHtmlForImport(bodyField.value));
            }
            if (container) container.style.display = '';
        } else if (newMode === 'editor') {
            if (window._j2cGrapesEditor) {
                const html = postprocessHtmlForExport(window._j2cGrapesEditor.runCommand('gjs-get-inlined-html'));
                if (bodyField) bodyField.value = html;
            }
            if (container) container.style.display = 'none';

            Joomla.renderMessages({
                warning: [Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CODE_MODE_WARNING')]
            });
        } else {
            if (container) container.style.display = 'none';
        }
    });
}

window.preprocessHtmlForImport = function preprocessHtmlForImport(html) {
    if (!html) return html;

    // Replace shortcode src attributes with placeholders to avoid 404 errors in canvas
    html = html.replace(/(<img[^>]*)\ssrc="(\[[A-Z_]+\])"([^>]*>)/gi, (match, before, shortcode, after) => {
        return `${before} src="${J2C_IMG_PLACEHOLDER}" data-j2c-src="${shortcode}"${after}`;
    });

    // Strip <tr><td> wrappers around hooks (from TinyMCE-safe template format) before re-wrapping
    html = html.replace(/<tr>\s*<td[^>]*>\s*\[HOOK:(AFTER_HEADER|BEFORE_ITEMS|AFTER_ITEMS|BEFORE_SHIPPING|AFTER_PAYMENT|BEFORE_FOOTER)\]\s*<\/td>\s*<\/tr>/gi,
        '[HOOK:$1]');

    // Wrap [HOOK:POSITION] as <tr data-j2c-hook> for GrapesJS visual editing
    html = html.replace(/\[HOOK:(AFTER_HEADER|BEFORE_ITEMS|AFTER_ITEMS|BEFORE_SHIPPING|AFTER_PAYMENT|BEFORE_FOOTER)\]/g,
        '<tr data-j2c-hook="$1"><td style="border:2px dashed #8b5cf6;padding:8px;text-align:center;color:#8b5cf6;font-size:12px;font-family:monospace;background:#f5f3ff;" colspan="1">[HOOK:$1]</td></tr>');

    // Wrap [IF:TAG]...[/IF:TAG] and [IFNOT:TAG]...[/IFNOT:TAG] as elements.
    //
    // The wrapper tag is chosen by what the block contains. A <div> is not permitted
    // inside <table>, so the HTML parser foster-parents it out of the table and leaves
    // it EMPTY while the rows it guarded reattach to the table unconditionally — the
    // guard is silently lost on import and destroyed in the stored body on the next
    // save. A conditional that wraps table rows therefore becomes a <tbody>, which is
    // valid there and survives parsing (the same reason [ITEMS_LOOP] is a <tbody>
    // below). Cell-level conditionals stay <div> and keep the dashed authoring badge.
    html = html.replace(/\[(IF|IFNOT):([A-Z0-9_]+)\]([\s\S]*?)\[\/\1:\2\]/g, (match, prefix, tag, inner) => {
        const negate = prefix === 'IFNOT' ? '1' : '0';

        if (/^\s*<tr[\s>]/i.test(inner)) {
            return `<tbody data-j2c-condition="${tag}" data-j2c-negate="${negate}" style="outline:2px dashed #f59e0b;">${inner}</tbody>`;
        }

        return `<div data-j2c-condition="${tag}" data-j2c-negate="${negate}" style="border:2px dashed #f59e0b;padding:10px;margin:5px 0;position:relative;min-height:40px;">${inner}</div>`;
    });

    // Wrap [ITEMS_LOOP]...[/ITEMS_LOOP] as a <tbody> element (valid inside <table>, survives HTML parsing)
    html = html.replace(/\[ITEMS_LOOP\]([\s\S]*?)\[\/ITEMS_LOOP\]/g,
        '<tbody data-j2c-loop="ITEMS">$1</tbody>');

    // Wrap each [LANG:KEY] so the canvas can show the wording it resolves to. Alternating the
    // token against `<[^>]*>` matches whole tags FIRST, so a token that ever appears inside an
    // attribute is left exactly as it is rather than having markup spliced into the attribute.
    // The span only changes what is DISPLAYED — the j2c-lang-text type below always exports the
    // token, which is the thing every locale shares.
    html = html.replace(/<[^>]*>|\[LANG:([A-Z][A-Z0-9_]*)\]/g, (match, key) => {
        if (!key) return match;

        return `<span data-j2c-lang="${key}">${escapeHtml(j2cLangStrings[key] ?? match)}</span>`;
    });

    return html;
}

/** Index of the `<` opening the close tag that balances an element already open at `from`. */
function findMatchingClose(html, name, from) {
    const re = new RegExp('<(/?)' + name + '\\b', 'gi');
    re.lastIndex = from;

    for (let m = re.exec(html), depth = 1; m; m = re.exec(html)) {
        depth += m[1] ? -1 : 1;

        if (depth === 0) {
            return m.index;
        }
    }

    return -1;
}

/**
 * Restore [IF:TAG]/[IFNOT:TAG] brackets from their wrapper elements.
 *
 * Locates each wrapper's close tag by counting depth rather than matching
 * `([\s\S]*?)</div>`: that non-greedy form stops at the FIRST close tag, so a
 * conditional holding any nested element of the same name was truncated —
 * `<div data-j2c-condition="X"><div>inner</div>tail</div>` came back out as
 * `[IF:X]<div>inner[/IF:X]tail</div>`. Handles both wrapper tags emitted by
 * preprocessHtmlForImport() and recurses so nested conditionals survive.
 */
function unwrapConditionals(html) {
    const open = /<(div|tbody)\b([^>]*\bdata-j2c-condition="([^"]*)"[^>]*)>/i;
    let out  = '';
    let rest = html;

    for (let m = rest.match(open); m; m = rest.match(open)) {
        const [tagHtml, name, attrs, tag] = m;
        const start    = m.index + tagHtml.length;
        const close    = findMatchingClose(rest, name, start);
        const closeEnd = close < 0 ? -1 : rest.indexOf('>', close);

        // Unbalanced markup: step past the open tag and leave it as-is rather than
        // swallow the rest of the body into a conditional that never closes.
        if (close < 0 || closeEnd < 0) {
            out += rest.slice(0, start);
            rest = rest.slice(start);
            continue;
        }

        const prefix = /\bdata-j2c-negate="1"/i.test(attrs) ? 'IFNOT' : 'IF';

        out += rest.slice(0, m.index)
            + `[${prefix}:${tag}]`
            + unwrapConditionals(rest.slice(start, close))
            + `[/${prefix}:${tag}]`;

        rest = rest.slice(closeEnd + 1);
    }

    return out + rest;
}

/**
 * Replace every `<tag ... attr="value" ...>inner</tag>` marker with what `replace()` returns.
 *
 * Close tags are located by counting depth for the same reason unwrapConditionals() does: a
 * non-greedy `([\s\S]*?)</tag>` stops at the FIRST close tag, so a marker holding a nested
 * element of the same name came back out truncated. `tagPattern` may be an alternation, and the
 * tag that actually matched is the one whose depth is counted.
 */
function unwrapMarkers(html, tagPattern, attr, replace) {
    const open = new RegExp(`<(${tagPattern})\\b([^>]*\\b${attr}="([^"]*)"[^>]*)>`, 'i');
    let out  = '';
    let rest = html;

    for (let m = rest.match(open); m; m = rest.match(open)) {
        const [tagHtml, name, attrs, value] = m;
        const start    = m.index + tagHtml.length;
        const close    = findMatchingClose(rest, name, start);
        const closeEnd = close < 0 ? -1 : rest.indexOf('>', close);

        // Unbalanced markup: step past the open tag and leave it as-is rather than swallow the
        // rest of the body into a marker that never closes.
        if (close < 0 || closeEnd < 0) {
            out += rest.slice(0, start);
            rest = rest.slice(start);
            continue;
        }

        out += rest.slice(0, m.index) + replace(attrs, value, rest.slice(start, close));
        rest = rest.slice(closeEnd + 1);
    }

    return out + rest;
}

window.postprocessHtmlForExport = function postprocessHtmlForExport(html) {
    if (!html) return html;

    // The inliner hands back a whole-document shape: the fragment wrapped in <body>, with the
    // rules it cannot inline -- the responsive @media block -- appended AFTER the closing
    // </body>. Stored that way the send path nests one <body> inside another and that trailing
    // <style> ends up outside both, which is how those rules reached recipients as text at the
    // foot of the message. Keep the rules, move them to the front, and hand back a fragment.
    const lifted = [];
    html = html.replace(/<style\b[^>]*>[\s\S]*?<\/style\s*>/gi, (block) => {
        lifted.push(block);

        return '';
    });
    html = html.replace(/^\s*<body\b[^>]*>/i, '').replace(/<\/body\s*>\s*$/i, '');
    html = lifted.join('') + html;

    // Put every [LANG:KEY] token back before anything else looks at the markup. The type's
    // toHTML() already does this for a component the canvas typed, but a body restored from
    // body_json can come back with its spans as plain components, whose toHTML() would serialize
    // the wording on screen — one admin's language, saved over every other locale.
    // A style or class the editor put on the token's own span is a deliberate choice -- a
    // colour, a size -- and dropping the span with the wording dropped it with them, so the
    // change survived in body_json, showed in the canvas, and never reached the email. Keep a
    // wrapper carrying only those presentation attributes; the token inside still resolves at
    // send time, and the next import nests the re-typed span inside this one unchanged.
    html = unwrapMarkers(html, 'span', 'data-j2c-lang', (attrs, key) => {
        // The marker's own attribute value only has to exclude `"` to be well-formed, so hold it
        // to the shape resolveLangTokens() actually resolves rather than re-emitting whatever
        // the attribute happened to carry.
        if (!J2C_LANG_KEY.test(key)) return '';

        const keep = (attrs.match(/\s(?:style|class)="[^"]*"/gi) || []).join('');

        return keep ? `<span${keep}>[LANG:${key}]</span>` : `[LANG:${key}]`;
    });

    // Restore shortcode src attributes from data-j2c-src placeholders
    // GrapesJS may reorder attributes, so data-j2c-src may not be adjacent to src
    html = html.replace(/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/gi, (match, before, shortcode, after) => {
        let attrs = before + after;
        attrs = attrs.replace(/\ssrc="[^"]*"/gi, '');
        return `<img${attrs} src="${shortcode}">`;
    });

    // Convert <tr data-j2c-hook> wrappers back to TinyMCE-safe zero-height rows, and any
    // div-based ones (from block manager drag-drop) straight back to the bare marker.
    html = unwrapMarkers(html, 'tr', 'data-j2c-hook', (attrs, pos) =>
        `<tr><td style="padding:0;border:0;font-size:0;line-height:0;height:0;overflow:hidden;">[HOOK:${pos}]</td></tr>`);
    html = unwrapMarkers(html, 'div', 'data-j2c-hook', (attrs, pos) => `[HOOK:${pos}]`);

    // Convert conditional elements back to [IF:TAG]...[/IF:TAG] or [IFNOT:TAG]...[/IFNOT:TAG]
    html = unwrapConditionals(html);

    // Convert loop elements back to [ITEMS_LOOP]...[/ITEMS_LOOP] (tbody from block, div from legacy import)
    html = unwrapMarkers(html, 'div|tbody', 'data-j2c-loop', (attrs, name, inner) =>
        `[${name}_LOOP]${inner}[/${name}_LOOP]`);

    return html;
}
