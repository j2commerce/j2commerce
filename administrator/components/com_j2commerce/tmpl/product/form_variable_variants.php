<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.tooltip', '[data-bs-toggle="tooltip"]', ['placement' => 'top']);

$global_config = Factory::getApplication()->getConfig();
$limit         = $global_config->get('list_limit', 20);

$wa    = Factory::getApplication()->getDocument()->getWebAssetManager();
$style = '.com_j2commerce .fa-stack.small{width:1.25rem;height:1.25rem;line-height:1.25rem;}'
    . '.com_j2commerce .fa-stack.small .fa-stack-2x{font-size:1rem;}'
    . '.com_j2commerce .fa-stack.small .fa-stack-1x{font-size:0.5rem;top:50%;left:50%;transform:translate(-50%,-50%);}';
$wa->addInlineStyle($style, [], []);

// Adopts the variant list this view fetches. Deferred, so it has run before any fetch callback.
$wa->registerAndUseScript('com_j2commerce.dom', 'media/com_j2commerce/js/site/j2commerce-dom.js', [], ['defer' => true]);

$item        = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

$hasOptions  = isset($item->product_options) && !empty($item->product_options);
$hasVariants = isset($item->variants) && count($item->variants);
$csrfToken   = Session::getFormToken();
$ajaxBase    = json_encode(\Joomla\CMS\Uri\Uri::base() . 'index.php');
?>
<div class="j2commerce-product-variants">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIANTS'); ?></legend>

        <?php if ($hasOptions) : ?>

            <div class="<?php echo $hasVariants ? 'd-flex' : 'd-none'; ?> justify-content-start align-items-center mb-3" id="j2commerce-variant-toolbar">
                <div class="form-check pt-0 me-2">
                    <input class="form-check-input" type="checkbox" value="" id="j2commerce_toggleAllCheckboxes">
                </div>
                <button type="button" class="btn btn-soft-danger btn-sm me-2" id="j2commerce_deleteCheckedVariants"
                        data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIANTS_DELETE_CHECKED'); ?>"
                        disabled>
                    <span class="fas fa-solid fa-trash" aria-hidden="true"></span>
                </button>
                <button type="button" id="j2commerce-regenerate-variants"
                        class="btn btn-sm btn-soft-info me-2"
                        data-product-id="<?php echo (int) $item->j2commerce_product_id; ?>">
                    <span class="fas fa-solid fa-recycle me-2" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_REGENERATE_VARIANTS'); ?>
                </button>
                <button type="button" id="j2commerce-delete-all-variants"
                        class="btn btn-sm btn-soft-danger"
                        data-product-id="<?php echo (int) $item->j2commerce_product_id; ?>">
                    <span class="fas fa-solid fa-trash me-2" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_DELETE_ALL_VARIANTS'); ?>
                </button>
                <button type="button" id="j2commerce_openAll-panel" class="btn btn-soft-dark btn-sm ms-auto"
                        onclick="setExpandAll();"
                        data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_OPEN_ALL'); ?>">
                    <span class="fas fa-solid fa-chevron-down" aria-hidden="true"></span>
                </button>
                <button type="button" id="j2commerce_closeAll-panel" class="btn btn-soft-dark btn-sm ms-2"
                        onclick="setCloseAll();"
                        data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_CLOSE_ALL'); ?>">
                    <span class="fas fa-solid fa-chevron-up" aria-hidden="true"></span>
                </button>
            </div>
            <button type="button" id="j2commerce-generate-variants"
                    class="btn btn-soft-success mb-5"
                    data-product-id="<?php echo (int) $item->j2commerce_product_id; ?>"
                    style="<?php echo $hasVariants ? 'display:none' : ''; ?>">
                <span class="fas fa-solid fa-magic me-2" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_GENERATE_VARIANTS'); ?>
            </button>

        <?php endif; ?>

        <?php
        // Rendering a page of variants inline costs seconds and megabytes of markup, and the
        // browser cannot paint anything until it finishes. The count is known here, so the panel
        // opens on a skeleton of the right length and fetches page 1 through the same path
        // pagination already uses. A product with no variants says so straight away.
        $variantTotal = (int) ($item->variant_pagination->total ?? 0);
        ?>
        <div class="j2commerce-advancedvariants-settings">
            <div class="accordion" id="j2commerce_variants_accordion"
                 data-variant-total="<?php echo $variantTotal; ?>"
                 data-variant-deferred="<?php echo $variantTotal > 0 ? '1' : '0'; ?>">
                <?php if ($variantTotal > 0) : ?>
                    <?php echo LayoutHelper::render('product.variant_skeleton', ['rows' => min($variantTotal, (int) $limit)], JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts'); ?>
                <?php else : ?>
                    <div class="alert alert-info"><?php echo Text::_('COM_J2COMMERCE_NO_VARIANTS'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </fieldset>
</div>

<script type="text/javascript">
(function () {
    'use strict';

    // Decorative spinner with the label visible beside it.
    function setSpinnerLabel(el, label, spinnerClass = 'spinner-border spinner-border-sm') {
        const spinner = document.createElement('span');
        spinner.className = spinnerClass;
        spinner.setAttribute('aria-hidden', 'true');
        el.replaceChildren(spinner);
        el.append(' ' + label);
    }
    // Spinner carrying the label for assistive tech only.
    function setSpinnerOnly(el, label) {
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('role', 'status');

        const srLabel = document.createElement('span');
        srLabel.className = 'visually-hidden';
        srLabel.textContent = label;
        spinner.append(srLabel);
        el.replaceChildren(spinner);
    }

    // A single decorative icon, the shape the star toggles use.
    function setDecorativeIcon(el, iconClass) {
        const icon = document.createElement('span');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        el.replaceChildren(icon);
    }

    var J2COMMERCE_AJAX_BASE = <?php echo $ajaxBase; ?>;
    var csrfToken = '<?php echo $csrfToken; ?>';
    var starIconEmpty = 'far fa-regular fa-star';
    var starIconFilled = 'icon-featured';
    var txtSetDefault = <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_SET_DEFAULT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtUnsetDefault = <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_UNSET_DEFAULT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtError = <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtConfirmDelete = <?php echo json_encode(Text::_('COM_J2COMMERCE_CONFIRM_DELETE_VARIANT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtVariantDeleted = <?php echo json_encode(Text::_('COM_J2COMMERCE_VARIANT_DELETED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtErrorDeleting = <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANT'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var txtNoResults = <?php echo json_encode(Text::_('COM_J2COMMERCE_NO_RESULTS_FOUND'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    window.listVariableItemTask = async function(variantId, task, productId) {
        var button = document.getElementById('default-variant-' + variantId);
        if (!button) {
            console.error('Default variant button not found for variant:', variantId);
            return false;
        }

        var originalContent = [...button.childNodes];
        button.classList.add('disabled');
        setSpinnerOnly(button, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

        var controllerTask = task === 'setDefault' ? 'products.setDefaultVariantAjax' : 'products.unsetDefaultVariantAjax';

        try {
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', controllerTask);
            formData.append('format', 'json');
            formData.append('variant_id', variantId.toString());
            formData.append('product_id', productId.toString());
            formData.append(csrfToken, '1');

            var response = await fetch(J2COMMERCE_AJAX_BASE, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            var result = await response.json();

            if (result.success) {
                if (task === 'setDefault') {
                    document.querySelectorAll('[id^="default-variant-"]').forEach(function(btn) {
                        var btnVariantId = btn.id.replace('default-variant-', '');
                        if (btnVariantId !== variantId.toString()) {
                            btn.setAttribute('title', txtSetDefault);
                            btn.setAttribute('onclick', 'return listVariableItemTask(' + btnVariantId + ', \'setDefault\', ' + productId + ')');
                            setDecorativeIcon(btn, starIconEmpty);
                            var hiddenInput = document.getElementById('isdefault_' + btnVariantId);
                            if (hiddenInput) { hiddenInput.value = '0'; }
                        }
                    });

                    button.setAttribute('title', txtUnsetDefault);
                    button.setAttribute('onclick', 'return listVariableItemTask(' + variantId + ', \'unsetDefault\', ' + productId + ')');
                    setDecorativeIcon(button, starIconFilled);
                    button.classList.remove('disabled');

                    var hiddenInput = document.getElementById('isdefault_' + variantId);
                    if (hiddenInput) { hiddenInput.value = '1'; }
                } else {
                    button.setAttribute('title', txtSetDefault);
                    button.setAttribute('onclick', 'return listVariableItemTask(' + variantId + ', \'setDefault\', ' + productId + ')');
                    setDecorativeIcon(button, starIconEmpty);
                    button.classList.remove('disabled');

                    var hiddenInput2 = document.getElementById('isdefault_' + variantId);
                    if (hiddenInput2) { hiddenInput2.value = '0'; }
                }

                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ 'success': [result.message] });
                }
            } else {
                throw new Error(result.message || txtError);
            }
        } catch (error) {
            console.error('Error setting default variant:', error);
            button.classList.remove('disabled');
            button.replaceChildren(...originalContent);

            if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                Joomla.renderMessages({ 'error': [error.message || txtError] });
            }
        }

        return false;
    };

    function initConfigToggles() {
        document.querySelectorAll('.j2commerce-config-toggle').forEach(function(toggle) {
            var name = toggle.getAttribute('name');
            if (!name) return;

            var targetName = name.replace('use_store_config_', '');
            var targetInput = document.querySelector('[name="' + targetName + '"]');

            function updateState() {
                if (targetInput) { targetInput.disabled = toggle.checked; }
            }

            updateState();
            toggle.addEventListener('change', updateState);
        });
    }

    function initVariantItemHandlers() {
        initConfigToggles();
    }

    document.addEventListener('click', function(event) {
        var deleteBtn = event.target.closest('.j2commerce-delete-variant');
        if (!deleteBtn) return;

        event.preventDefault();

        var variantId = parseInt(deleteBtn.dataset.variantId, 10);
        if (!variantId) {
            console.error('Invalid variant ID');
            return;
        }

        var variantItem = deleteBtn.closest('.variant-item');
        var productIdInput = variantItem ? variantItem.querySelector('input[name*="[product_id]"]') : null;
        var productId = productIdInput ? parseInt(productIdInput.value, 10) : J2CommerceVariableVariants.config.productId;

        if (!confirm(txtConfirmDelete)) return;

        deleteVariantAjax(variantId, deleteBtn, productId);
    });

    async function deleteVariantAjax(variantId, button, productId) {
        var originalContent = [...button.childNodes];
        button.disabled = true;
        setSpinnerOnly(button, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

        try {
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteVariantAjax');
            formData.append('format', 'json');
            formData.append('variant_id', variantId.toString());
            formData.append('product_id', productId.toString());
            formData.append(csrfToken, '1');

            var response = await fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData });

            if (!response.ok) throw new Error('Network response was not ok');

            var result = await response.json();

            if (result && result.success) {
                var variantItem = document.querySelector('.variant-item[data-variant-id="' + variantId + '"]');
                if (variantItem) {
                    variantItem.style.transition = 'opacity 0.3s ease-out';
                    variantItem.style.opacity = '0';
                    setTimeout(function() {
                        if (typeof J2CommerceVariableVariants !== 'undefined') {
                            J2CommerceVariableVariants.cleanupVariantSyncInputs(variantId);
                        }
                        variantItem.remove();

                        if (typeof result.total !== 'undefined' && typeof J2CommerceVariableVariants !== 'undefined') {
                            J2CommerceVariableVariants.updateVariantCount(result.total);
                        }

                        if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                            Joomla.renderMessages({ 'success': [result.message || txtVariantDeleted] });
                        }

                        if (document.querySelectorAll('.variant-item').length === 0) {
                            var accordion = document.getElementById('j2commerce_variants_accordion');
                            if (accordion) {
                                var emptyNotice = document.createElement('div');
                                emptyNotice.className = 'alert alert-info';
                                emptyNotice.textContent = txtNoResults;
                                accordion.replaceChildren(emptyNotice);
                            }
                        }
                    }, 300);
                }
            } else {
                throw new Error((result && result.message) || txtErrorDeleting);
            }
        } catch (error) {
            console.error('Error deleting variant:', error);
            button.disabled = false;
            button.replaceChildren(...originalContent);

            if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                Joomla.renderMessages({ 'error': [error.message || txtErrorDeleting] });
            }
        }
    }

    var J2CommerceVariableVariants = {
        config: {
            totalVariants: <?php echo $item->variant_pagination->total ?? 0; ?>,
            currentPage: 1,
            limit: <?php echo (int) $limit; ?>,
            productId: <?php echo (int) $item->j2commerce_product_id; ?>,
            formPrefix: '<?php echo $formPrefix; ?>',
            csrfToken: '<?php echo $csrfToken; ?>'
        },

        setButtonLoading: function (button, loading) {
            if (!button) return;
            if (loading) {
                button._j2cOriginalContent = [...button.childNodes];
                button.disabled = true;
                setSpinnerLabel(button, <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'spinner-border spinner-border-sm me-1');
            } else {
                button.disabled = false;
                if (button._j2cOriginalContent) {
                    button.replaceChildren(...button._j2cOriginalContent);
                    delete button._j2cOriginalContent;
                }
            }
        },

        showMessage: function (message, type) {
            type = type || 'success';
            if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                var messages = {};
                messages[type] = [message];
                Joomla.renderMessages(messages);
            } else {
                alert(message);
            }
        },

        updateVariantCount: function (total) {
            this.config.totalVariants = total;
            var numPages = Math.max(1, Math.ceil(total / this.config.limit));
            if (this.config.currentPage > numPages) {
                this.config.currentPage = numPages;
            }
            var countDisplay = document.querySelector('.j2commerce-variant-pagination .text-end');
            if (countDisplay) {
                countDisplay.textContent = total + ' ' + <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_TAB_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            }
            this.rebuildPagination();
            this.updateToolbarVisibility(total);
        },

        updateToolbarVisibility: function (total) {
            var toolbarWrapper = document.getElementById('j2commerce-variant-toolbar');
            if (toolbarWrapper) {
                if (total > 0) {
                    toolbarWrapper.classList.remove('d-none');
                    toolbarWrapper.classList.add('d-flex');
                } else {
                    toolbarWrapper.classList.remove('d-flex');
                    toolbarWrapper.classList.add('d-none');
                }
            }
            var genBtn = document.getElementById('j2commerce-generate-variants');
            if (genBtn) {
                genBtn.style.display = total === 0 ? '' : 'none';
            }
        },

        setupPagination: function () {
            var accordion = document.getElementById('j2commerce_variants_accordion');
            if (!accordion) return;

            var paginationWrapper = accordion.parentNode.querySelector('.j2commerce-variant-pagination');
            if (!paginationWrapper) {
                paginationWrapper = document.createElement('nav');
                paginationWrapper.className = 'pagination__wrapper j2commerce-variant-pagination';
                paginationWrapper.setAttribute('aria-label', <?php echo json_encode(Text::_('JLIB_HTML_PAGINATION'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                var countRow = document.createElement('div');
                countRow.className = 'text-end';
                countRow.textContent = this.config.totalVariants + ' ' + <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_TAB_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

                var navRow = document.createElement('div');
                navRow.className = 'j2commerce-variant-nav text-center mt-0 mx-0';

                var navList = document.createElement('ul');
                navList.className = 'pagination pagination-toolbar pagination-list text-center mt-0 mx-0';
                navRow.append(navList);

                paginationWrapper.replaceChildren(countRow, navRow);
                accordion.parentNode.insertBefore(paginationWrapper, accordion.nextSibling);
            }
            this.rebuildPagination();
        },

        rebuildPagination: function () {
            var paginationList = document.querySelector('.j2commerce-variant-pagination .pagination-list');
            if (!paginationList) return;

            paginationList.replaceChildren();
            var numPages = Math.ceil(this.config.totalVariants / this.config.limit);
            if (numPages <= 1) return;

            var self = this;
            for (var i = 0; i < numPages; i++) {
                var pageNum   = i + 1;
                var limitstart = i * this.config.limit;
                var listItem  = document.createElement('li');
                listItem.className = 'page-item' + (pageNum === this.config.currentPage ? ' active' : '');

                var link = document.createElement('a');
                link.className = 'page-link';
                link.href = 'javascript:void(0);';
                link.setAttribute('data-limitstart', limitstart);
                link.setAttribute('data-page', i);
                link.textContent = pageNum;
                link.onclick = (function (ls) {
                    return function (e) {
                        e.preventDefault();
                        self.loadVariantList(ls);
                    };
                })(limitstart);

                listItem.appendChild(link);
                paginationList.appendChild(listItem);
            }
        },

        // A skeleton of the expected length, so the wait never reads as "there are none".
        showSkeleton: function (accordion, rows) {
            var count = Math.max(1, Math.min(rows || 1, 20));
            var frag  = document.createDocumentFragment();

            var wrapper = document.createElement('div');
            wrapper.className = 'j2commerce-variant-skeleton';
            wrapper.setAttribute('role', 'status');
            wrapper.setAttribute('aria-live', 'polite');

            var srLabel = document.createElement('span');
            srLabel.className = 'visually-hidden';
            srLabel.textContent = <?php echo json_encode(Text::_('COM_J2COMMERCE_LOADING'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            wrapper.append(srLabel);

            for (var i = 0; i < count; i++) {
                var row = document.createElement('div');
                row.className = 'variant-item border mb-3 rounded-3 px-3 py-2 placeholder-glow';
                row.setAttribute('aria-hidden', 'true');

                var inner = document.createElement('div');
                inner.className = 'd-flex align-items-center';
                ['placeholder rounded me-2', 'placeholder rounded me-2', 'placeholder col-4 rounded', 'placeholder col-2 rounded ms-auto']
                    .forEach(function (cls, index) {
                        var bar = document.createElement('span');
                        bar.className = cls;
                        if (index < 2) {
                            bar.style.width  = index === 0 ? '1rem' : '1.5rem';
                            bar.style.height = index === 0 ? '1rem' : '1.5rem';
                        }
                        inner.append(bar);
                    });

                row.append(inner);
                wrapper.append(row);
            }

            frag.append(wrapper);
            accordion.replaceChildren(frag);
        },

        // Shown only once a load has finished and genuinely produced nothing.
        showEmptyNotice: function (accordion, message) {
            var notice = document.createElement('div');
            notice.className = 'alert alert-info';
            notice.textContent = message || <?php echo json_encode(Text::_('COM_J2COMMERCE_NO_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            accordion.replaceChildren(notice);
        },

        loadVariantList: function (limitstart) {
            limitstart = limitstart || 0;
            var accordion = document.getElementById('j2commerce_variants_accordion');
            if (!accordion) return;

            this.config.currentPage = Math.floor(limitstart / this.config.limit) + 1;

            var self = this;
            var expected = this.config.totalVariants - limitstart;
            this.showSkeleton(accordion, expected > 0 ? Math.min(expected, this.config.limit) : this.config.limit);

            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.getVariantListAjax');
            formData.append('product_id', this.config.productId);
            formData.append('limitstart', limitstart);
            formData.append('limit', this.config.limit);
            formData.append('form_prefix', this.config.formPrefix);
            formData.append('variant_layout', 'form_ajax_avoptions');
            formData.append(csrfToken, '1');

            fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(function (data) {
                    var total = typeof data.total !== 'undefined' ? parseInt(data.total, 10) || 0 : 0;

                    if (total === 0) {
                        self.showEmptyNotice(accordion);
                    } else if (data.html) {
                        J2CommerceDom.adopt(accordion, data.html);
                        self.setupCheckboxHandlers();
                        initVariantItemHandlers();
                        // Dispatched on the container, not document: core showon.js reads event.target.classList.
                        accordion.dispatchEvent(new CustomEvent('joomla:updated', { bubbles: true }));
                    } else {
                        // The product has variants, this page of them does not.
                        self.showEmptyNotice(accordion, txtNoResults);
                    }

                    self.updateVariantCount(total);
                })
                .catch(function (error) {
                    console.error('Error loading variant list:', error);
                    self.showEmptyNotice(accordion, txtNoResults);
                    self.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_LOADING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                });
        },

        generateVariants: function () {
            if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_REGENERATE_HELP'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)) {
                return;
            }

            var btn = document.getElementById('j2commerce-generate-variants');
            this.setButtonLoading(btn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.generateVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');

            fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_VARIANTS_GENERATED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                        self.updateVariantCount(data.total || 0);
                        self.loadVariantList(0);
                    } else {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_GENERATING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                    }
                })
                .catch(function (error) {
                    console.error('Error generating variants:', error);
                    self.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_GENERATING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                })
                .finally(function () {
                    self.setButtonLoading(btn, false);
                });
        },

        regenerateVariants: function () {
            if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_REGENERATE_HELP'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)) {
                return;
            }

            var btn = document.getElementById('j2commerce-regenerate-variants');
            this.setButtonLoading(btn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.regenerateVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');

            fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_VARIANTS_REGENERATED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                        self.updateVariantCount(data.total || 0);
                        self.loadVariantList(0);
                    } else {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_GENERATING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                    }
                })
                .catch(function (error) {
                    console.error('Error regenerating variants:', error);
                    self.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_GENERATING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                })
                .finally(function () {
                    self.setButtonLoading(btn, false);
                });
        },

        deleteAllVariants: function () {
            if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_CONFIRM_DELETE_ALL_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)) {
                return;
            }

            var btn = document.getElementById('j2commerce-delete-all-variants');
            this.setButtonLoading(btn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteAllVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');

            fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        self.cleanupAllVariantSyncInputs();
                        var accordion = document.getElementById('j2commerce_variants_accordion');
                        if (accordion) {
                            self.showEmptyNotice(accordion);
                        }
                        self.updateVariantCount(0);
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ALL_VARIANTS_DELETED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                    } else {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                    }
                })
                .catch(function (error) {
                    console.error('Error deleting all variants:', error);
                    self.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                })
                .finally(function () {
                    self.setButtonLoading(btn, false);
                });
        },

        deleteSelectedVariants: function () {
            var checkedVariants = document.querySelectorAll('input[name="vid[]"]:checked');
            if (checkedVariants.length === 0) {
                this.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'warning');
                return;
            }

            if (!confirm(<?php echo json_encode(Text::_('COM_J2COMMERCE_CONFIRM_DELETE_SELECTED_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)) {
                return;
            }

            var deleteBtn = document.getElementById('j2commerce_deleteCheckedVariants');
            this.setButtonLoading(deleteBtn, true);

            var variantIds = Array.from(checkedVariants).map(function (cb) { return cb.value; });

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteSelectedVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');
            variantIds.forEach(function (id) { formData.append('variant_ids[]', id); });

            fetch(J2COMMERCE_AJAX_BASE, { method: 'POST', body: formData })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        variantIds.forEach(function (id) {
                            var item = document.querySelector('.variant-item[data-variant-id="' + id + '"]');
                            if (item) {
                                item.style.transition = 'opacity 0.3s';
                                item.style.opacity = '0';
                                setTimeout(function () { item.remove(); }, 300);
                            }
                        });
                        setTimeout(function () {
                            self.updateVariantCount(data.total);
                            self.updateCheckboxState();
                        }, 350);
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_VARIANTS_DELETED'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
                    } else {
                        self.showMessage(data.message || <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                    }
                })
                .catch(function (error) {
                    console.error('Error deleting selected variants:', error);
                    self.showMessage(<?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
                })
                .finally(function () {
                    self.setButtonLoading(deleteBtn, false);
                });
        },

        setupCheckboxHandlers: function () {
            var toggleAll = document.getElementById('j2commerce_toggleAllCheckboxes');
            if (!toggleAll) return;

            var self = this;
            toggleAll.onchange = function () {
                var checkboxes = document.querySelectorAll('input[name="vid[]"]');
                checkboxes.forEach(function (cb) { cb.checked = toggleAll.checked; });
                self.updateCheckboxState();
            };

            document.querySelectorAll('input[name="vid[]"]').forEach(function (cb) {
                cb.onchange = function () { self.updateCheckboxState(); };
            });
        },

        updateCheckboxState: function () {
            var checkboxes = document.querySelectorAll('input[name="vid[]"]');
            var toggleAll  = document.getElementById('j2commerce_toggleAllCheckboxes');
            var deleteBtn  = document.getElementById('j2commerce_deleteCheckedVariants');

            var anyChecked = Array.from(checkboxes).some(function (cb) { return cb.checked; });
            var allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(function (cb) { return cb.checked; });

            if (toggleAll) {
                toggleAll.checked = allChecked;
                toggleAll.indeterminate = anyChecked && !allChecked;
            }

            if (deleteBtn) {
                deleteBtn.disabled = !anyChecked;
            }
        },

        cleanupVariantSyncInputs: function(variantId) {
            var form = document.querySelector('form[name="adminForm"]');
            if (!form) return;
            var prefix = this.config.formPrefix + '[variable][' + variantId + ']';
            form.querySelectorAll('.uppymedia-sync-input').forEach(function(input) {
                if (input.name && input.name.indexOf(prefix) === 0) {
                    input.remove();
                }
            });
        },

        cleanupAllVariantSyncInputs: function() {
            var form = document.querySelector('form[name="adminForm"]');
            if (!form) return;
            var prefix = this.config.formPrefix + '[variable]';
            form.querySelectorAll('.uppymedia-sync-input').forEach(function(input) {
                if (input.name && input.name.indexOf(prefix) === 0) {
                    input.remove();
                }
            });
        },

        init: function () {
            this.setupPagination();
            this.setupCheckboxHandlers();

            var self = this;

            var deleteBtn = document.getElementById('j2commerce_deleteCheckedVariants');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.deleteSelectedVariants();
                });
            }

            var genBtn = document.getElementById('j2commerce-generate-variants');
            if (genBtn) {
                genBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.generateVariants();
                });
            }

            var regenBtn = document.getElementById('j2commerce-regenerate-variants');
            if (regenBtn) {
                regenBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.regenerateVariants();
                });
            }

            var deleteAllBtn = document.getElementById('j2commerce-delete-all-variants');
            if (deleteAllBtn) {
                deleteAllBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.deleteAllVariants();
                });
            }

            // The panel ships a skeleton, not the rows; fetch page 1 through the path
            // pagination already uses.
            var accordion = document.getElementById('j2commerce_variants_accordion');
            if (accordion && accordion.dataset.variantDeferred === '1') {
                this.loadVariantList(0);
            }
        }
    };

    window.J2CommerceVariableVariants = J2CommerceVariableVariants;

    window.setExpandAll = function () {
        document.querySelectorAll('.accordion .collapse').forEach(function (panel) {
            if (!panel.classList.contains('show')) {
                new bootstrap.Collapse(panel, { toggle: false }).show();
            }
        });
    };

    window.setCloseAll = function () {
        document.querySelectorAll('.accordion .collapse.show').forEach(function (panel) {
            new bootstrap.Collapse(panel, { toggle: false }).hide();
        });
    };

    window.getVariantList = function (element) {
        var limitstart = parseInt(element.getAttribute('data-get_limitstart'), 10) || 0;
        J2CommerceVariableVariants.loadVariantList(limitstart);
    };

    document.addEventListener('DOMContentLoaded', function () {
        J2CommerceVariableVariants.init();
        initVariantItemHandlers();
    });
})();
</script>
