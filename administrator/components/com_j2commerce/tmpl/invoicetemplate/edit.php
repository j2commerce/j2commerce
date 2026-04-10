<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Invoicetemplate\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$app   = Factory::getApplication();
$input = $app->input;

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$bodySource    = $this->item->body_source ?? 'editor';
$isVisual      = ($bodySource === 'visual');
$isEditor      = ($bodySource === 'editor');
$hideShortcodes = $isVisual;

$wa->addInlineScript('
document.addEventListener("DOMContentLoaded", function() {
    function insertShortcode(shortcode) {
        if (typeof Joomla !== "undefined" && Joomla.editors && Joomla.editors.instances && Joomla.editors.instances["jform_body"]) {
            Joomla.editors.instances["jform_body"].replaceSelection(shortcode);
        } else {
            const bodyField = document.querySelector("#jform_body");
            if (bodyField) {
                const pos = bodyField.selectionStart;
                const before = bodyField.value.substring(0, pos);
                const after = bodyField.value.substring(pos);
                bodyField.value = before + shortcode + after;
                bodyField.selectionStart = bodyField.selectionEnd = pos + shortcode.length;
                bodyField.focus();
            }
        }
    }

    document.querySelectorAll(".shortcode-btn").forEach(function(btn) {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            insertShortcode(this.getAttribute("data-shortcode"));
        });
    });

    // Body source switcher — toggle visual editor + shortcodes column
    const bodySourceField = document.querySelector("select[name=\"jform[body_source]\"]");
    const contentCol = document.getElementById("content-editor-col");
    const shortcodesCol = document.getElementById("shortcodes-sidebar-col");

    function updateLayout() {
        if (!bodySourceField || !contentCol) return;
        const mode = bodySourceField.value;
        const showSidebar = (mode !== "visual");
        if (showSidebar) {
            contentCol.className = "col-lg-9";
            if (shortcodesCol) shortcodesCol.style.display = "";
        } else {
            contentCol.className = "col-12";
            if (shortcodesCol) shortcodesCol.style.display = "none";
        }
    }

    if (bodySourceField) {
        bodySourceField.addEventListener("change", updateLayout);
    }

    function getEditorContent() {
        let content = "";
        if (window._j2cGrapesEditor) {
            const bodySourceField = document.querySelector("select[name=\"jform[body_source]\"]");
            if (bodySourceField && bodySourceField.value === "visual") {
                if (typeof window.syncGrapesDataToForm === "function") {
                    window.syncGrapesDataToForm(window._j2cGrapesEditor);
                } else {
                    let html = window._j2cGrapesEditor.runCommand("gjs-get-inlined-html");
                    if (typeof window.postprocessHtmlForExport === "function") {
                        html = window.postprocessHtmlForExport(html);
                    }
                    const bodyField = document.querySelector("#jform_body");
                    if (bodyField) bodyField.value = html;
                }
            }
        }
        // Try Joomla editor API first (TinyMCE/JCE sync content on getValue)
        if (Joomla.editors && Joomla.editors.instances && Joomla.editors.instances["jform_body"]) {
            content = Joomla.editors.instances["jform_body"].getValue() || "";
        }
        if (!content) {
            const bodyField = document.querySelector("#jform_body");
            content = bodyField ? bodyField.value : "";
        }
        // Restore data-j2c-src placeholders back to src for server-side processing
        if (typeof window.postprocessHtmlForExport === "function") {
            content = window.postprocessHtmlForExport(content);
        } else if (content.includes("data-j2c-src")) {
            content = content.replace(/<img([^>]*?)data-j2c-src="(\[[A-Z_]+\])"([^>]*?)>/gi, (m, before, shortcode, after) => {
                let attrs = (before + after).replace(/\ssrc="[^"]*"/gi, \'\');
                return \'<img\' + attrs + \' src="\' + shortcode + \'">\';
            });
        }
        return content;
    }

    // Print Test (toolbar button)
    const printBtn = document.getElementById("btn-print-test");
    if (printBtn) {
        printBtn.addEventListener("click", async function() {
            const body = getEditorContent();
            const customCss = document.querySelector("textarea[name=\"jform[custom_css]\"]")?.value || "";
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            printBtn.disabled = true;
            printBtn.innerHTML = "<div class=\"spinner-border text-light\" aria-hidden=\"true\"></div> " + Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_LOADING");

            try {
                const formData = new FormData();
                formData.append("body", body);
                formData.append("custom_css", customCss);
                formData.append(token, "1");

                const response = await fetch("index.php?option=com_j2commerce&task=invoicetemplate.preview&format=raw", {
                    method: "POST",
                    body: formData
                });

                if (response.ok) {
                    const html = await response.text();
                    const win = window.open("", "_blank");
                    win.document.write(html);
                    win.document.close();
                    win.focus();

                    // Wait for all images to load before printing
                    const imgs = win.document.querySelectorAll("img");
                    if (imgs.length > 0) {
                        await Promise.all(Array.from(imgs).map(img => {
                            if (img.complete) return Promise.resolve();
                            return new Promise(resolve => {
                                img.addEventListener("load", resolve, {once: true});
                                img.addEventListener("error", resolve, {once: true});
                            });
                        }));
                    }
                    win.print();
                } else {
                    Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_PREVIEW_FAILED")]});
                }
            } catch (err) {
                Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_REQUEST_FAILED").replace("%s", err.message)]});
            }

            printBtn.disabled = false;
            printBtn.innerHTML = "<span class=\"icon-print\"></span> " + Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_PRINT_TEST");
        });
    }

    // Shortcode search filter (sidebar)
    const sidebarSearch = document.getElementById("shortcode-search-sidebar");
    if (sidebarSearch) {
        sidebarSearch.addEventListener("input", function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll("#shortcodes-sidebar-col .shortcode-group").forEach(function(group) {
                const btns = group.querySelectorAll(".shortcode-btn");
                let hasVisible = false;
                btns.forEach(function(btn) {
                    const text = (btn.textContent + " " + (btn.getAttribute("data-shortcode") || "")).toLowerCase();
                    const visible = !term || text.includes(term);
                    btn.style.display = visible ? "" : "none";
                    if (visible) hasVisible = true;
                });
                group.style.display = hasVisible ? "" : "none";
            });
        });
    }

    // Template card click handler (named function for reuse after AJAX render)
    function bindTemplateCardClick(card) {
        card.addEventListener("click", async function(e) {
            e.stopImmediatePropagation();
            const type = this.getAttribute("data-template-type");
            const design = this.getAttribute("data-template-design");
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            if (!confirm("' . Text::_('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_TEMPLATE_CONFIRM', true) . '")) return;

            try {
                const loadUrl = Joomla.getOptions("com_j2commerce.emaileditor")?.loadTemplateUrl || "";
                const response = await fetch(loadUrl + "&" + token + "=1&type=" + type + "&design=" + design);
                const json = await response.json();

                if (json.success && json.body) {
                    if (typeof Joomla !== "undefined" && Joomla.editors && Joomla.editors.instances && Joomla.editors.instances["jform_body"]) {
                        Joomla.editors.instances["jform_body"].setValue(json.body);
                    } else {
                        const bodyField = document.querySelector("#jform_body");
                        if (bodyField) bodyField.value = json.body;
                    }

                    const bodyJsonField = document.getElementById("jform_body_json");
                    if (bodyJsonField) bodyJsonField.value = "";

                    if (window._j2cGrapesEditor && typeof window.preprocessHtmlForImport === "function") {
                        window._j2cGrapesEditor.setComponents(window.preprocessHtmlForImport(json.body));
                    }

                    Joomla.renderMessages({message: [Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_LOAD_SUCCESS")]});
                    document.getElementById("loadTemplateModal")?.querySelector("[data-bs-dismiss=modal]")?.click();
                } else {
                    Joomla.renderMessages({error: [json.message || Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_LOAD_FAILED")]});
                }
            } catch (err) {
                Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_REQUEST_FAILED").replace("%s", err.message)]});
            }
        });
    }

    // Bind existing static cards (if any)
    document.querySelectorAll(".template-card").forEach(bindTemplateCardClick);

    // AJAX-load presets when Load Template modal opens
    const loadTemplateModal = document.getElementById("loadTemplateModal");
    if (loadTemplateModal) {
        loadTemplateModal.addEventListener("show.bs.modal", async function() {
            const typeField = document.querySelector("select[name=\"jform[invoice_type]\"]");
            const type = typeField ? typeField.value : "invoice";
            const grid = document.getElementById("template-grid");
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            grid.innerHTML = \'<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">\' + Joomla.Text._("COM_J2COMMERCE_LOADING") + \'</span></div></div>\';

            try {
                const presetsUrl = Joomla.getOptions("com_j2commerce.emaileditor")?.getPresetsUrl || "";
                const response = await fetch(presetsUrl + "&" + token + "=1&type=" + type);
                const json = await response.json();

                if (json.success && json.presets.length) {
                    let html = \'<div class="row">\';
                    json.presets.forEach(function(preset) {
                        html += \'<div class="col-md-4 mb-3">\'
                            + \'<div class="card h-100 template-card" role="button" data-template-type="\' + preset.type + \'" data-template-design="\' + preset.design + \'">\'
                            + \'<div class="card-body text-center p-3">\'
                            + \'<span class="icon-print d-block mb-2" style="font-size:2rem;color:var(--gjs-text-muted,#6c757d);" aria-hidden="true"></span>\'
                            + \'<h3 class="card-title mb-1 fs-6">\' + preset.label + \'</h3>\'
                            + \'<small class="text-muted">\' + type.charAt(0).toUpperCase() + type.slice(1).replace("_", " ") + \'</small>\'
                            + \'</div></div></div>\';
                    });
                    html += \'</div>\';
                    grid.innerHTML = html;
                    grid.querySelectorAll(".template-card").forEach(bindTemplateCardClick);
                } else {
                    grid.innerHTML = \'<div class="alert alert-info">\' + Joomla.Text._("COM_J2COMMERCE_INVOICETEMPLATE_NO_PRESETS") + \'</div>\';
                }
            } catch (err) {
                grid.innerHTML = \'<div class="alert alert-danger">\' + err.message + \'</div>\';
            }
        });
    }
});
');

$wa->addInlineStyle('
.shortcode-panel {
    border: 1px solid var(--gjs-border, #dee2e6);
    border-radius: 0.375rem;
    padding: 0.75rem;
    background-color: var(--gjs-bg-primary, #f8f9fa);
}
.shortcode-btn {
    margin: 0.125rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border: 1px solid var(--gjs-border, #6c757d);
    background-color: var(--gjs-input-bg, #fff);
    color: var(--gjs-text-primary, #495057);
    border-radius: 0.25rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    transition: background-color .15s, color .15s;
}
.shortcode-btn:hover {
    background-color: var(--gjs-accent, #2a69b8);
    color: #fff;
    text-decoration: none;
    border-color: var(--gjs-accent, #2a69b8);
}
.shortcode-category {
    font-weight: 600;
    color: var(--gjs-text-primary, #495057);
    margin-top: 0.75rem;
    margin-bottom: 0.375rem;
    border-bottom: 1px solid var(--gjs-border-light, #dee2e6);
    padding-bottom: 0.25rem;
    font-size: 0.8125rem;
}
.shortcode-category:first-child { margin-top: 0; }
.template-card {
    border: 2px solid var(--gjs-border, #dee2e6);
    cursor: pointer;
    transition: border-color 0.15s;
}
.template-card:hover {
    border-color: var(--gjs-accent, #0d6efd);
    box-shadow: none;
}
');

$layout = 'edit';
$tmpl   = $input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=' . $layout . $tmpl . '&j2commerce_invoicetemplate_id=' . (int) $this->item->j2commerce_invoicetemplate_id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TAB_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TAB_DETAILS'); ?></legend>
                    <?php echo $this->form->renderField('title'); ?>
                    <?php echo $this->form->renderField('language'); ?>
                    <?php echo $this->form->renderField('orderstatus_id'); ?>
                    <?php echo $this->form->renderField('group_id'); ?>
                    <?php echo $this->form->renderField('paymentmethod'); ?>
                    <?php echo $this->form->renderField('invoice_type'); ?>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TAB_CONFIGURATION'); ?></legend>
                    <?php echo $this->form->renderField('enabled'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'template', Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TAB_TEMPLATE')); ?>
        <div class="row">
            <div id="content-editor-col" class="<?php echo $hideShortcodes ? 'col-12' : 'col-lg-9'; ?>">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_TAB_TEMPLATE'); ?></legend>

                    <?php echo $this->form->renderField('body_source'); ?>

                    <!-- GrapesJS Visual Editor Container -->
                    <div id="gjs-container" style="<?php echo !$isVisual ? 'display:none;' : ''; ?>">
                        <div id="gjs" style="height:700px; overflow:hidden;"></div>
                    </div>

                    <input type="hidden" name="jform[body_json]" id="jform_body_json" value="<?php echo $this->escape($this->item->body_json ?? ''); ?>">

                    <?php echo $this->form->renderField('show_custom_css'); ?>
                    <?php echo $this->form->renderField('custom_css'); ?>
                    <?php echo $this->form->renderField('body'); ?>
                    <?php echo $this->form->renderField('invoice_template_description'); ?>
                </fieldset>
            </div>

            <!-- Shortcodes sidebar — hidden in visual mode, visible in editor mode -->
            <div class="col-lg-3" id="shortcodes-sidebar-col" style="<?php echo $hideShortcodes ? 'display:none;' : ''; ?>">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_SHORTCODES'); ?></legend>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" id="shortcode-search-sidebar" placeholder="<?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_SEARCH_SHORTCODES'); ?>">
                    </div>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php echo $this->loadTemplate('shortcodes_list'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <!-- Load Template Modal -->
    <div class="modal fade" id="loadTemplateModal" tabindex="-1" aria-labelledby="loadTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="loadTemplateModalLabel"><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_TEMPLATE'); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted"><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_LOAD_TEMPLATE_DESC'); ?></p>
                    <div id="template-grid">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_INVOICETEMPLATE_LOADING'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <input type="hidden" name="task" value="">
    <input type="hidden" name="j2commerce_invoicetemplate_id" value="<?php echo (int) $this->item->j2commerce_invoicetemplate_id; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
