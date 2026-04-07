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

// Shortcode options for the trait dropdown — populated before editor init
let j2cShortcodeOptions = [];

document.addEventListener('DOMContentLoaded', () => {
    const options = Joomla.getOptions('com_j2commerce.emaileditor');
    if (!options) return;

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
    if (options.shortcodes && typeof options.shortcodes === 'object' && !Array.isArray(options.shortcodes)) {
        j2cShortcodeOptions = [];
        for (const [key, val] of Object.entries(options.shortcodes)) {
            if (val && typeof val === 'object' && !Array.isArray(val)) {
                // Nested: key is category name, val is {tag: desc}
                for (const [tag, desc] of Object.entries(val)) {
                    j2cShortcodeOptions.push({ id: tag, name: `${tag} — ${desc}` });
                }
            } else {
                // Flat: key is tag, val is description string
                j2cShortcodeOptions.push({ id: key, name: `${key} — ${val}` });
            }
        }
    }

    if (options.bodyJson) {
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

    // After load, inject responsive styles and swap shortcode src placeholders
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
    });

    setupFormSyncHandlers(editor);
    setupShortcodeBlocks(editor, options.shortcodes);
    setupPreviewIntegration(editor, options);
    setupTemplateLoading(editor, options);
    setupModeSwitching(editor, options);
}

function j2commercePlugin(editor) {
    const blockManager = editor.BlockManager;

    blockManager.add('j2c-order-items', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_ITEMS') || 'Order Items Table',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_BILLING_ADDRESS') || 'Billing Address',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SHIPPING_ADDRESS') || 'Shipping Address',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_ORDER_SUMMARY') || 'Order Summary',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CTA_BUTTON') || 'CTA Button',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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

    blockManager.add('j2c-store-logo', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_STORE_LOGO') || 'Store Logo',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
        media: '<span class="fa fa-image" style="font-size:2.4em"></span>',
        content: `
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" style="padding: 20px;">
                        [IF:STORE_LOGO_URL]
                        <img src="${J2C_IMG_PLACEHOLDER}" data-j2c-src="[STORE_LOGO_URL]" alt="[SITENAME]" style="max-width: 200px; max-height: [LOGO_MAX_HEIGHT]px;" />
                        [/IF:STORE_LOGO_URL]
                        [IFNOT:STORE_LOGO_URL]
                        <span style="font-size: 24px; font-weight: bold; color: [ACCENT_COLOR];">[SITENAME]</span>
                        [/IFNOT:STORE_LOGO_URL]
                    </td>
                </tr>
            </table>`,
    });

    blockManager.add('j2c-social-links', {
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_SOCIAL_LINKS') || 'Social Links',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_CONDITIONAL') || 'Conditional Section',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
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
        label: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_BLOCK_HOOK') || 'Hook Position',
        category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_J2_BLOCKS') || 'J2 Blocks',
        media: '<span class="fa fa-plug" style="font-size:2.4em"></span>',
        content: `<tr data-j2c-hook="AFTER_HEADER"><td style="border:2px dashed #8b5cf6;padding:8px;text-align:center;color:#8b5cf6;font-size:12px;font-family:monospace;background:#f5f3ff;" colspan="1">[HOOK:AFTER_HEADER]</td></tr>`,
    });

    registerCustomComponentTypes(editor);
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
                this.el.innerHTML = tag;
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
                this.el.innerHTML = `<td style="border:2px dashed #8b5cf6;padding:8px;text-align:center;color:#8b5cf6;font-size:12px;font-family:monospace;background:#f5f3ff;" colspan="1">[HOOK:${pos}]</td>`;
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
            label: `<span style="font-family:monospace;font-size:11px">${tag}</span><br><small>${desc}</small>`,
            category: Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CATEGORY_SHORTCODES') || 'Shortcodes',
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
                j2cShortcodeOptions.push({ id: tag, name: `${tag} — ${desc}` });
            }
        } else {
            j2cShortcodeOptions.push({ id: key, name: `${key} — ${val}` });
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
    Joomla.submitform = function(task, form, validate) {
        const bodySourceField = document.querySelector('select[name="jform[body_source]"]');
        if (bodySourceField && bodySourceField.value === 'visual' && window._j2cGrapesEditor) {
            syncGrapesDataToForm(window._j2cGrapesEditor);
        }
        // Restore shortcode src attributes from data-j2c-src placeholders (editor/file mode)
        restoreShortcodeSrcInBody();
        return originalSubmitForm.call(this, task, form, validate);
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
        const origHtml = previewBtn.innerHTML;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>';

        try {
            const formData = new FormData();
            formData.append('body', body);
            formData.append('subject', subject);
            formData.append('custom_css', customCss);
            formData.append(token, '1');

            const response = await fetch(options.previewUrl, { method: 'POST', body: formData });
            if (response.ok) {
                const html = await response.text();
                const iframe = document.getElementById('email-preview-iframe');
                if (iframe) {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(html);
                    doc.close();
                }
            }
        } catch (err) {
            Joomla.renderMessages({ error: ['Preview failed: ' + err.message] });
        }

        previewBtn.disabled = false;
        previewBtn.innerHTML = origHtml;
    }, true);
}

function setupTemplateLoading(editor, options) {
    document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', async () => {
            const type = card.getAttribute('data-template-type');
            const design = card.getAttribute('data-template-design');
            const token = options.csrfToken;

            if (!confirm(Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE_CONFIRM') || 'Load this template? Current content will be replaced.')) {
                return;
            }

            try {
                const url = `${options.loadTemplateUrl}&${token}=1&type=${encodeURIComponent(type)}&design=${encodeURIComponent(design)}`;
                const response = await fetch(url);
                const json = await response.json();

                if (json.success && json.body) {
                    const bodySourceField = document.querySelector('select[name="jform[body_source]"]');

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
                warning: [Joomla.Text._('COM_J2COMMERCE_EMAILTEMPLATE_CODE_MODE_WARNING')
                    || 'Warning: Manual HTML edits may break the visual editor layout if you switch back.']
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

    // Wrap [IF:TAG]...[/IF:TAG] and [IFNOT:TAG]...[/IFNOT:TAG] as elements
    html = html.replace(/\[(IF|IFNOT):([A-Z_]+)\]([\s\S]*?)\[\/\1:\2\]/g, (match, prefix, tag, inner) => {
        return `<div data-j2c-condition="${tag}" data-j2c-negate="${prefix === 'IFNOT' ? '1' : '0'}" style="border:2px dashed #f59e0b;padding:10px;margin:5px 0;position:relative;min-height:40px;">${inner}</div>`;
    });

    // Wrap [ITEMS_LOOP]...[/ITEMS_LOOP] as a <tbody> element (valid inside <table>, survives HTML parsing)
    html = html.replace(/\[ITEMS_LOOP\]([\s\S]*?)\[\/ITEMS_LOOP\]/g,
        '<tbody data-j2c-loop="ITEMS">$1</tbody>');

    return html;
}

window.postprocessHtmlForExport = function postprocessHtmlForExport(html) {
    if (!html) return html;

    // Restore shortcode src attributes from data-j2c-src placeholders
    // GrapesJS may reorder attributes, so data-j2c-src may not be adjacent to src
    html = html.replace(/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/gi, (match, before, shortcode, after) => {
        let attrs = before + after;
        attrs = attrs.replace(/\ssrc="[^"]*"/gi, '');
        return `<img${attrs} src="${shortcode}">`;
    });

    // Convert <tr data-j2c-hook> wrappers back to TinyMCE-safe zero-height rows
    html = html.replace(/<tr[^>]*data-j2c-hook="([^"]*)"[^>]*>[\s\S]*?<\/tr>/gi,
        '<tr><td style="padding:0;border:0;font-size:0;line-height:0;height:0;overflow:hidden;">[HOOK:$1]</td></tr>');
    // Fallback: any remaining div-based hook wrappers (from block manager drag-drop)
    html = html.replace(/<div[^>]*data-j2c-hook="([^"]*)"[^>]*>[\s\S]*?<\/div>/gi, '[HOOK:$1]');

    // Convert conditional elements back to [IF:TAG]...[/IF:TAG] or [IFNOT:TAG]...[/IFNOT:TAG]
    html = html.replace(/<div[^>]*data-j2c-condition="([^"]*)"[^>]*data-j2c-negate="1"[^>]*>([\s\S]*?)<\/div>/g, '[IFNOT:$1]$2[/IFNOT:$1]');
    html = html.replace(/<div[^>]*data-j2c-negate="1"[^>]*data-j2c-condition="([^"]*)"[^>]*>([\s\S]*?)<\/div>/g, '[IFNOT:$1]$2[/IFNOT:$1]');
    html = html.replace(/<div[^>]*data-j2c-condition="([^"]*)"[^>]*>([\s\S]*?)<\/div>/g, '[IF:$1]$2[/IF:$1]');

    // Convert loop elements back to [ITEMS_LOOP]...[/ITEMS_LOOP] (tbody from block, div from legacy import)
    html = html.replace(/<tbody[^>]*data-j2c-loop="ITEMS"[^>]*>([\s\S]*?)<\/tbody>/g, '[ITEMS_LOOP]$1[/ITEMS_LOOP]');
    html = html.replace(/<div[^>]*data-j2c-loop="ITEMS"[^>]*>([\s\S]*?)<\/div>/g, '[ITEMS_LOOP]$1[/ITEMS_LOOP]');

    return html;
}
