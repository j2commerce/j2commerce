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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Emailtemplate\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$app = Factory::getApplication();
$input = $app->input;

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$bodySource = $this->item->body_source ?? 'visual';
$isVisual = ($bodySource === 'visual');
$isEditor = ($bodySource === 'editor');
$hideShortcodes = ($isVisual || $isEditor);

// Inline JS for shortcode insertion, preview, send test, template loading
$wa->addInlineScript('
document.addEventListener("DOMContentLoaded", function() {
    // Insert shortcode into Joomla editor (TinyMCE / CodeMirror)
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

    // Subject field validation
    const subjectField = document.querySelector("input[name=\"jform[subject]\"]");
    if (subjectField) {
        subjectField.addEventListener("blur", function() { this.value = this.value.trim(); });
        subjectField.addEventListener("input", function() {
            if (this.value.length > 255) this.value = this.value.substring(0, 255);
        });
    }

    function getEditorContent() {
        // For visual mode, sync GrapesJS content to the hidden body field first
        if (window._j2cGrapesEditor) {
            const bodySourceField = document.querySelector("select[name=\"jform[body_source]\"]");
            if (bodySourceField && bodySourceField.value === "visual") {
                // Trigger the form sync so jform_body has postprocessed HTML
                const syncBtn = document.querySelector("button[data-j2c-sync]");
                if (typeof window.syncGrapesDataToForm === "function") {
                    window.syncGrapesDataToForm(window._j2cGrapesEditor);
                } else {
                    // Fallback: run the export commands directly
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
            const val = Joomla.editors.instances["jform_body"].getValue();
            if (val) return val;
        }
        const bodyField = document.querySelector("#jform_body");
        return bodyField ? bodyField.value : "";
    }

    // Preview
    const previewBtn = document.getElementById("btn-refresh-preview");
    if (previewBtn) {
        previewBtn.addEventListener("click", async function() {
            const iframe = document.getElementById("email-preview-iframe");
            const body = getEditorContent();
            const subject = document.querySelector("input[name=\"jform[subject]\"]")?.value || "";
            const customCss = document.querySelector("textarea[name=\"jform[custom_css]\"]")?.value || "";
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            previewBtn.disabled = true;
            previewBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm\" aria-hidden=\"true\"></span> " + Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_LOADING");

            try {
                const formData = new FormData();
                formData.append("body", body);
                formData.append("subject", subject);
                formData.append("custom_css", customCss);
                formData.append(token, "1");

                const response = await fetch("index.php?option=com_j2commerce&task=emailtemplate.preview&format=raw", {
                    method: "POST",
                    body: formData
                });

                if (response.ok) {
                    const html = await response.text();
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(html);
                    doc.close();
                } else {
                    Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_PREVIEW_FAILED")]});
                }
            } catch (err) {
                Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_REQUEST_FAILED").replace("%s", err.message)]});
            }

            previewBtn.disabled = false;
            previewBtn.innerHTML = "<span class=\"icon-loop\"></span> ' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_REFRESH_PREVIEW', true) . '";
        });
    }

    // Send test email
    const confirmSendBtn = document.getElementById("btn-confirm-send-test");
    if (confirmSendBtn) {
        confirmSendBtn.addEventListener("click", async function() {
            const email = document.getElementById("test-email-address")?.value || "";
            if (!email) {
                Joomla.renderMessages({warning: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_ENTER_EMAIL")]});
                return;
            }

            const body = getEditorContent();
            const subject = document.querySelector("input[name=\"jform[subject]\"]")?.value || "";
            const customCss = document.querySelector("textarea[name=\"jform[custom_css]\"]")?.value || "";
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            confirmSendBtn.disabled = true;
            confirmSendBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm\" aria-hidden=\"true\"></span> " + Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_SENDING");

            try {
                const formData = new FormData();
                formData.append("body", body);
                formData.append("subject", subject);
                formData.append("custom_css", customCss);
                formData.append("recipient", email);
                formData.append(token, "1");

                const response = await fetch("index.php?option=com_j2commerce&task=emailtemplate.sendTest&format=json", {
                    method: "POST",
                    body: formData
                });

                const json = await response.json();
                if (json.success) {
                    Joomla.renderMessages({message: [json.message || Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_TEST_SENT")]});
                    document.getElementById("sendTestModal")?.querySelector("[data-bs-dismiss=modal]")?.click();
                } else {
                    Joomla.renderMessages({error: [json.message || Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_SEND_FAILED")]});
                }
            } catch (err) {
                Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_REQUEST_FAILED").replace("%s", err.message)]});
            }

            confirmSendBtn.disabled = false;
            confirmSendBtn.innerHTML = "<span class=\"icon-envelope\"></span> ' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEND_TEST', true) . '";
        });
    }

    // Shortcode search filter (sidebar)
    function applyShortcodeSearch(container, term) {
        container.querySelectorAll(".shortcode-group").forEach(function(group) {
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
    }

    const sidebarSearch = document.getElementById("shortcode-search-sidebar");
    if (sidebarSearch) {
        sidebarSearch.addEventListener("input", function() {
            applyShortcodeSearch(document.getElementById("shortcodes-sidebar-col"), this.value.toLowerCase());
        });
    }

    // Re-bind shortcode buttons after DOM replacement
    function bindShortcodeButtons(container) {
        container.querySelectorAll(".shortcode-btn").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                insertShortcode(this.getAttribute("data-shortcode"));
            });
        });
    }

    // Email type change — filter template cards and reload shortcodes
    const emailTypeField = document.getElementById("jform_email_type");
    if (emailTypeField) {
        emailTypeField.addEventListener("change", async function() {
            const emailType = this.value;

            // Filter template cards in the modal
            document.querySelectorAll(".template-card").forEach(function(card) {
                const col = card.closest(".col-md-4");
                if (!col) return;
                const cardType = card.getAttribute("data-email-type");
                col.style.display = (!emailType || cardType === emailType) ? "" : "none";
            });

            if (!emailType) return;

            // Load type-specific shortcodes via AJAX
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";
            const editorOptions = Joomla.getOptions("com_j2commerce.emaileditor") || {};
            const shortcodesUrl = editorOptions.shortcodesUrl || "index.php?option=com_j2commerce&task=emailtemplate.getShortcodes&format=json";

            try {
                const response = await fetch(shortcodesUrl + "&" + token + "=1&email_type=" + encodeURIComponent(emailType));
                const json = await response.json();

                if (json.success) {
                    // Update inline sidebar
                    const sidebarContent = document.querySelector("#shortcodes-sidebar-col .options-form > div[style]");
                    if (sidebarContent) {
                        sidebarContent.innerHTML = json.html;
                        bindShortcodeButtons(sidebarContent);
                    }

                    // Update GrapesJS shortcode blocks if editor is active
                    if (typeof window.updateJ2CShortcodeBlocks === "function" && json.shortcodes) {
                        window.updateJ2CShortcodeBlocks(json.shortcodes);
                    }
                }
            } catch (err) {
                // Silently fail — shortcodes are a convenience feature
                console.warn("Failed to load type-specific shortcodes:", err.message);
            }
        });

        // On initial load, filter template cards if email type is already set
        if (emailTypeField.value && emailTypeField.value !== "transactional") {
            document.querySelectorAll(".template-card").forEach(function(card) {
                const col = card.closest(".col-md-4");
                if (!col) return;
                const cardType = card.getAttribute("data-email-type");
                col.style.display = (cardType === emailTypeField.value) ? "" : "none";
            });
        }
    }

    // Template card click handler — updates body field, body_json, and GrapesJS canvas
    document.querySelectorAll(".template-card").forEach(function(card) {
        card.addEventListener("click", async function(e) {
            e.stopImmediatePropagation();
            const type = this.getAttribute("data-template-type");
            const design = this.getAttribute("data-template-design");
            const token = Joomla.getOptions("csrf.token") || document.querySelector("input[type=hidden][name][value=\"1\"]")?.name || "";

            if (!confirm("' . Text::_('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE_CONFIRM', true) . '")) return;

            try {
                const response = await fetch("index.php?option=com_j2commerce&task=emailtemplate.loadTemplate&format=json&" + token + "=1&type=" + type + "&design=" + design);
                const json = await response.json();

                if (json.success && json.body) {
                    // Update the Joomla editor body field
                    if (typeof Joomla !== "undefined" && Joomla.editors && Joomla.editors.instances && Joomla.editors.instances["jform_body"]) {
                        Joomla.editors.instances["jform_body"].setValue(json.body);
                    } else {
                        const bodyField = document.querySelector("#jform_body");
                        if (bodyField) bodyField.value = json.body;
                    }

                    // Clear body_json so GrapesJS reimports from HTML
                    const bodyJsonField = document.getElementById("jform_body_json");
                    if (bodyJsonField) bodyJsonField.value = "";

                    // If GrapesJS is active, load into visual canvas
                    if (window._j2cGrapesEditor && typeof window.preprocessHtmlForImport === "function") {
                        window._j2cGrapesEditor.setComponents(window.preprocessHtmlForImport(json.body));
                    }

                    Joomla.renderMessages({message: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_LOAD_SUCCESS")]});
                    document.getElementById("loadTemplateModal")?.querySelector("[data-bs-dismiss=modal]")?.click();
                } else {
                    Joomla.renderMessages({error: [json.message || Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_LOAD_FAILED")]});
                }
            } catch (err) {
                Joomla.renderMessages({error: [Joomla.Text._("COM_J2COMMERCE_EMAILTEMPLATE_REQUEST_FAILED").replace("%s", err.message)]});
            }
        });
    });
});
');

// Inline styles — shortcode buttons, template cards, offcanvas Atum integration
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
    border:2px solid var(--gjs-border, #dee2e6);
    cursor: pointer;
    transition: border-color 0.15s;
}
.template-card:hover {
    border-color: var(--gjs-accent, #0d6efd);
    box-shadow: none;
}
');

$layout = 'edit';
$tmpl = $input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=' . $layout . $tmpl . '&j2commerce_emailtemplate_id=' . (int) $this->item->j2commerce_emailtemplate_id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'general', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'general', Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_GENERAL')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_GENERAL'); ?></legend>
                    <?php echo $this->form->renderField('email_type'); ?>
                    <?php echo $this->form->renderField('subject'); ?>
                    <?php echo $this->form->renderField('receiver_type'); ?>
                    <?php echo $this->form->renderField('language'); ?>
                    <?php echo $this->form->renderField('orderstatus_id'); ?>
                    <?php echo $this->form->renderField('group_id'); ?>
                    <?php echo $this->form->renderField('paymentmethod'); ?>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_PUBLISHING'); ?></legend>
                    <?php echo $this->form->renderField('enabled'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'content', Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_CONTENT')); ?>
        <div class="row">
            <div id="content-editor-col" class="<?php echo $hideShortcodes ? 'col-12' : 'col-lg-9'; ?>">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_CONTENT'); ?></legend>

                    <?php echo $this->form->renderField('body_source'); ?>

                    <!-- GrapesJS Visual Editor Container -->
                    <div id="gjs-container" style="<?php echo !$isVisual ? 'display:none;' : ''; ?>">
                        <div id="gjs" style="height:700px; overflow:hidden;"></div>
                    </div>

                    <input type="hidden" name="jform[body_json]" id="jform_body_json" value="<?php echo $this->escape($this->item->body_json ?? ''); ?>">

                    <?php echo $this->form->renderField('show_custom_css'); ?>
                    <?php echo $this->form->renderField('custom_css'); ?>
                    <?php echo $this->form->renderField('body'); ?>
                    <?php echo $this->form->renderField('body_source_file'); ?>
                    <?php echo $this->form->renderField('email_template_description'); ?>
                </fieldset>
            </div>

            <!-- Shortcodes sidebar column — hidden in visual mode, visible in editor/file mode -->
            <div class="col-lg-3" id="shortcodes-sidebar-col" style="<?php echo $hideShortcodes ? 'display:none;' : ''; ?>">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODES'); ?></legend>
                    <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" id="shortcode-search-sidebar" placeholder="<?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEARCH_SHORTCODES'); ?>">
                    </div>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php echo $this->loadTemplate('shortcodes_list'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'preview', Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAB_PREVIEW')); ?>
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-muted mb-0"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_PREVIEW_DESC'); ?></p>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn-refresh-preview">
                            <span class="icon-loop" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_REFRESH_PREVIEW'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm ms-2" id="btn-send-test" data-bs-toggle="modal" data-bs-target="#sendTestModal">
                            <span class="icon-envelope" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEND_TEST'); ?>
                        </button>
                    </div>
                </div>
                <div id="email-preview-container" style="border: 1px solid var(--gjs-border, #dee2e6); border-radius: 0.375rem; background: var(--gjs-bg-primary, #f8f9fa); min-height: 400px;">
                    <iframe id="email-preview-iframe" style="width: 100%; min-height: 600px; border: none; background: var(--gjs-bg-canvas, #fff);" sandbox="allow-same-origin allow-scripts"></iframe>
                </div>
            </div>
        </div>

        <!-- Send Test Email Modal -->
        <div class="modal fade" id="sendTestModal" tabindex="-1" aria-labelledby="sendTestModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="sendTestModalLabel"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEND_TEST'); ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="mb-3">
                            <label for="test-email-address" class="form-label"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEND_TO'); ?></label>
                            <input type="email" class="form-control" id="test-email-address" value="<?php echo $this->escape(Factory::getApplication()->getIdentity()->email); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                        <button type="button" class="btn btn-success" id="btn-confirm-send-test">
                            <span class="icon-envelope" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_SEND_TEST'); ?>
                        </button>
                    </div>
                </div>
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
                    <h2 class="modal-title fs-5" id="loadTemplateModalLabel"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE'); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted"><?php echo Text::_('COM_J2COMMERCE_EMAILTEMPLATE_LOAD_TEMPLATE_DESC'); ?></p>
                    <div class="row" id="template-grid">
                        <?php
                        $templateBase = JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts/templates/email';
                        $templateDirs = \Joomla\Filesystem\Folder::folders($templateBase);
                        sort($templateDirs);

                        // Core transactional directories — everything else maps to a registered email type
                        $coreTransactionalDirs = ['confirmed', 'shipped', 'cancelled'];

                        // Build directory→emailType map from registered email types
                        $dirEmailTypeMap = [];
                        try {
                            $emailTypes = \J2Commerce\Component\J2commerce\Administrator\Helper\EmailHelper::getEmailTypes();
                            foreach ($emailTypes as $typeId => $typeConfig) {
                                // Map plural directory name to singular type ID (e.g., giftcertificates → giftcertificate)
                                $dirEmailTypeMap[$typeId] = $typeId;
                                $dirEmailTypeMap[$typeId . 's'] = $typeId;
                            }
                        } catch (\Exception $e) {
                            // Fallback — EmailHelper unavailable
                        }

                        foreach ($templateDirs as $dir) :
                            $files = \Joomla\Filesystem\Folder::files($templateBase . '/' . $dir, '\.html$');
                            sort($files);
                            $dirEmailType = in_array($dir, $coreTransactionalDirs, true)
                                ? 'transactional'
                                : ($dirEmailTypeMap[$dir] ?? rtrim($dir, 's'));
                            foreach ($files as $file) :
                                $design = pathinfo($file, PATHINFO_FILENAME);
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 template-card" role="button" data-template-type="<?php echo $this->escape($dir); ?>" data-template-design="<?php echo $this->escape($design); ?>" data-email-type="<?php echo $this->escape($dirEmailType); ?>">
                                <div class="card-body text-center p-3">
                                    <span class="icon-envelope d-block mb-2" style="font-size: 2rem; color: var(--gjs-text-muted, #6c757d);" aria-hidden="true"></span>
                                    <h3 class="card-title mb-1 fs-6"><?php echo $this->escape(ucfirst($design)); ?></h3>
                                    <small class="text-muted"><?php echo $this->escape(ucfirst($dir)); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endforeach; ?>
                        <?php foreach ($this->pluginTemplateCards as $card): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 template-card" role="button"
                                data-template-type="<?php echo $this->escape($card['type'] ?? ''); ?>"
                                data-template-design="<?php echo $this->escape($card['design'] ?? ''); ?>"
                                data-email-type="<?php echo $this->escape($card['email_type'] ?? $card['type'] ?? ''); ?>">
                                <div class="card-body text-center p-3">
                                    <span class="icon-envelope d-block mb-2" style="font-size: 2rem; color: var(--gjs-text-muted, #6c757d);" aria-hidden="true"></span>
                                    <h3 class="card-title mb-1 fs-6"><?php echo $this->escape($card['label'] ?? ''); ?></h3>
                                    <small class="text-muted"><?php echo $this->escape($card['category'] ?? ''); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                </div>
            </div>
        </div>
    </div>


    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
