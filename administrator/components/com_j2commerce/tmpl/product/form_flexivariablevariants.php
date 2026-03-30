<?php
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
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tooltip', '[data-bs-toggle="tooltip"]', ['placement' => 'top']);

$global_config = Factory::getApplication()->getConfig();
$limit = $global_config->get('list_limit',20);

$wa  = Factory::getApplication()->getDocument()->getWebAssetManager();
$style = '.com_j2commerce .fa-stack.small {width: 1.25rem;height: 1.25rem;line-height: 1.25rem;}.com_j2commerce .fa-stack.small .fa-stack-2x {font-size:1rem;}.com_j2commerce .fa-stack.small .fa-stack-1x {font-size:0.5rem;top: 50%;left: 50%;transform: translate(-50%, -50%);}';
$wa->addInlineStyle($style, [], []);

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'];
?>
<div class="j2commerce-product-variants">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIANTS');?></legend>

        <div id="variant_add_block" class="mb-3">
            <input type="hidden" name="flexi_product_id" value="<?php echo $item->j2commerce_product_id;?>"/>
            <?php if(isset($item->product_options) && !empty($item->product_options)):?>
                <div class="input-group">
                    <?php foreach ($item->product_options as $product_option): ?>
                        <select name="variant_combin[<?php echo $product_option->j2commerce_productoption_id;?>]" class="form-select">
                            <option value="0"><?php echo substr(Text::_('COM_J2COMMERCE_ANY').' '.$this->escape($product_option->option_name),0,10).'...';?></option>
                            <?php foreach ($product_option->option_values as $option_value): ?>
                                <option value="<?php echo $option_value->j2commerce_optionvalue_id;?>"><?php echo $this->escape($option_value->optionvalue_name);?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endforeach; ?>
                    <button type="button" onclick="addFlexiVariant(event);" class="btn btn-primary" id="addVariantBtn">
                        <span class="fas fa-solid fa-plus me-1"></span><?php echo Text::_('COM_J2COMMERCE_ADD_VARIANT');?>
                    </button>
                </div>
            <?php endif;?>
        </div>
        <div id="variant_display_block">
            <?php if(isset($item->variants) && count($item->variants)):?>
                <div class="d-flex justify-content-start align-items-center mb-3">
                    <div class="form-check pt-0 me-2">
                        <input class="form-check-input" type="checkbox" value="" id="toggleAllCheckboxes">
                    </div>
                    <button type="button" class="btn btn-soft-danger btn-sm me-2" id="deleteCheckedVariants" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_PRODUCT_VARIANTS_DELETE_CHECKED');?>" disabled>
                        <span class="fas fa-solid fa-trash"></span>
                    </button>
                    <button type="button" onclick="removeFlexiAllVariant(event);" class="btn btn-sm btn-soft-danger" id="deleteAllVariantsBtn">
                        <span class="fas fa-solid fa-trash me-2"></span><?php echo Text::_('COM_J2COMMERCE_DELETE_ALL_VARIANTS');?>
                    </button>
                    <button type="button" id="openAll-panel" class="btn btn-soft-dark btn-sm ms-auto" onclick="setExpandAll();" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_OPEN_ALL');?>">
                        <span class="fas fa-solid fa-chevron-down"></span>
                    </button>
                    <button type="button" id="closeAll-panel" class="btn btn-soft-dark btn-sm ms-2" onclick="setCloseAll();" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_CLOSE_ALL');?>">
                        <span class="fas fa-solid fa-chevron-up"></span>
                    </button>
                </div>
            <?php endif;?>
            <div class="j2commerce-advancedvariants-settings j2commerce-advancedvariants-settings">
                <div class="accordion" id="accordion">
                    <?php
                    /* to get ajax advanced variable list need to
                     *  assign these variables
                     */
                    $variant_list = $item->variants;
                    $variant_pagination = $item->variant_pagination;
                    $weights = $item->weights;
                    $lengths = $item->lengths;
                    $layout = new FileLayout('form_ajax_flexivariableoptions', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');
                    ?>
                    <?php echo $layout->render(['product' => $item, 'variant_list' => $variant_list, 'variant_pagination' => $variant_pagination, 'weights' => $weights, 'lengths' => $lengths, 'form_prefix' => $formPrefix]);?>

                </div>
            </div>
        </div>
    </fieldset>
</div>

<script type="text/javascript">
(function() {
    'use strict';

    /**
     * J2Commerce Flexivariable Variants Manager
     * Joomla 6 MVC pattern with AJAX operations and DOM manipulation
     */
    var J2CommerceVariants = {
        // Configuration
        config: {
            currentPage: <?php echo $item->variant_pagination->pagesCurrent ?? 1; ?>,
            totalVariants: <?php echo $item->variant_pagination->total ?? 0; ?>,
            limit: <?php echo $limit ?? 20; ?>,
            productId: <?php echo $item->j2commerce_product_id ?? 0; ?>,
            formPrefix: '<?php echo $formPrefix; ?>',
            csrfToken: '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>'
        },

        /**
         * Show loading state on a button
         */
        setButtonLoading: function(button, loading) {
            if (!button) return;
            if (loading) {
                button.setAttribute('data-original-text', button.innerHTML);
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>';
            } else {
                button.disabled = false;
                var originalText = button.getAttribute('data-original-text');
                if (originalText) {
                    button.innerHTML = originalText;
                }
            }
        },

        /**
         * Display Joomla system message
         */
        showMessage: function(message, type) {
            type = type || 'success';
            if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                var messages = {};
                messages[type] = [message];
                Joomla.renderMessages(messages);
            } else {
                alert(message);
            }
        },

        /**
         * Update variant count display
         */
        updateVariantCount: function(total) {
            this.config.totalVariants = total;
            var countDisplay = document.querySelector('.pagination__wrapper .text-end');
            if (countDisplay) {
                countDisplay.textContent = total + ' <?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_VARIANTS'); ?>';
            }
            this.rebuildPagination();
            this.updateToolbarVisibility(total);
        },

        /**
         * Show/hide the variant toolbar based on variant count
         */
        updateToolbarVisibility: function(total) {
            var toolbar = document.querySelector('#variant_display_block > .d-flex');
            if (toolbar) {
                toolbar.style.display = total > 0 ? 'flex' : 'none';
            }
        },

        /**
         * Setup pagination
         */
        setupPagination: function() {
            var accordion = document.getElementById('accordion');
            if (!accordion) return;

            var paginationWrapper = document.querySelector('.pagination__wrapper');
            if (!paginationWrapper) {
                paginationWrapper = document.createElement('nav');
                paginationWrapper.className = 'pagination__wrapper';
                paginationWrapper.setAttribute('aria-label', '<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>');
                paginationWrapper.innerHTML = '<div class="text-end">' + this.config.totalVariants + ' <?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_VARIANTS'); ?></div><div id="nav" class="text-center mt-0 mx-0"><ul class="pagination pagination-toolbar pagination-list text-center mt-0 mx-0"></ul></div>';
                accordion.parentNode.insertBefore(paginationWrapper, accordion.nextSibling);
            }
            this.rebuildPagination();
        },

        /**
         * Rebuild pagination links
         */
        rebuildPagination: function() {
            var paginationList = document.querySelector('#nav .pagination-list');
            if (!paginationList) return;

            paginationList.innerHTML = '';
            var numPages = Math.ceil(this.config.totalVariants / this.config.limit);
            if (numPages <= 1) return;

            var self = this;
            for (var i = 0; i < numPages; i++) {
                var pageNum = i + 1;
                var limitstart = i * this.config.limit;

                var listItem = document.createElement('li');
                listItem.className = 'page-item' + (i === 0 ? ' active' : '');

                var link = document.createElement('a');
                link.className = 'page-link';
                link.href = 'javascript:void(0);';
                link.setAttribute('data-limitstart', limitstart);
                link.setAttribute('data-page', i);
                link.textContent = pageNum;
                link.onclick = (function(ls) {
                    return function(e) {
                        e.preventDefault();
                        self.loadVariantList(ls);
                        document.querySelectorAll('#nav .pagination-list li').forEach(function(li) {
                            li.classList.remove('active');
                        });
                        this.parentNode.classList.add('active');
                    };
                })(limitstart);

                listItem.appendChild(link);
                paginationList.appendChild(listItem);
            }
        },

        /**
         * Load variant list via AJAX
         */
        loadVariantList: function(limitstart) {
            limitstart = limitstart || 0;
            var accordion = document.getElementById('accordion');
            if (!accordion) return;

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.getVariantListAjax');
            formData.append('product_id', this.config.productId);
            formData.append('limitstart', limitstart);
            formData.append('limit', this.config.limit);
            formData.append('form_prefix', this.config.formPrefix);
            formData.append('variant_layout', 'form_ajax_flexivariableoptions');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.html) {
                    accordion.innerHTML = data.html;
                    self.setupCheckboxHandlers();
                    initConfigToggles();
                    document.dispatchEvent(new CustomEvent('joomla:updated'));
                }
                if (typeof data.total !== 'undefined') {
                    self.updateVariantCount(data.total);
                }
            })
            .catch(function(error) {
                console.error('Error loading variant list:', error);
                self.showMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_LOADING_VARIANTS'); ?>', 'error');
            });
        },

        /**
         * Add a new variant via AJAX
         */
        addVariant: function() {
            var addBlock = document.getElementById('variant_add_block');
            var addBtn = addBlock ? addBlock.querySelector('button') : null;
            if (!addBlock) return;

            this.setButtonLoading(addBtn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.addVariantAjax');
            formData.append('form_prefix', this.config.formPrefix);
            formData.append(this.config.csrfToken, '1');

            // Collect variant combination data
            var inputs = addBlock.querySelectorAll('select, input');
            inputs.forEach(function(input) {
                if (input.name) {
                    formData.append(input.name, input.value);
                }
            });

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_VARIANT_ADDED'); ?>');
                    self.updateVariantCount(data.total);
                    self.loadVariantList(0);
                    // Reset dropdowns
                    addBlock.querySelectorAll('select').forEach(function(select) {
                        select.selectedIndex = 0;
                    });
                } else {
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_ADDING_VARIANT'); ?>', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error adding variant:', error);
                self.showMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_ADDING_VARIANT'); ?>', 'error');
            })
            .finally(function() {
                self.setButtonLoading(addBtn, false);
            });
        },

        /**
         * Delete a single variant via AJAX
         */
        deleteVariant: function(variantId) {
            if (!confirm('<?php echo Text::_('COM_J2COMMERCE_CONFIRM_DELETE_VARIANT'); ?>')) {
                return;
            }

            var variantItem = document.querySelector('.variant-item[data-variant-id="' + variantId + '"]');
            var deleteBtn = variantItem ? variantItem.querySelector('.btn-danger') : null;

            this.setButtonLoading(deleteBtn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteVariantAjax');
            formData.append('variant_id', variantId);
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (variantItem) {
                        variantItem.style.transition = 'opacity 0.3s, transform 0.3s';
                        variantItem.style.opacity = '0';
                        variantItem.style.transform = 'translateX(-20px)';
                        setTimeout(function() {
                            variantItem.remove();
                            self.cleanupVariantSyncInputs(variantId);
                            self.updateVariantCount(data.total);
                            self.updateCheckboxState();
                        }, 300);
                    }
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_VARIANT_DELETED'); ?>');
                } else {
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANT'); ?>', 'error');
                    self.setButtonLoading(deleteBtn, false);
                }
            })
            .catch(function(error) {
                console.error('Error deleting variant:', error);
                self.showMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANT'); ?>', 'error');
                self.setButtonLoading(deleteBtn, false);
            });
        },

        /**
         * Delete all variants via AJAX
         */
        deleteAllVariants: function() {
            if (!confirm('<?php echo Text::_('COM_J2COMMERCE_CONFIRM_DELETE_ALL_VARIANTS'); ?>')) {
                return;
            }

            var deleteAllBtn = document.querySelector('[onclick*="removeFlexiAllVariant"]');
            this.setButtonLoading(deleteAllBtn, true);

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteAllVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var accordion = document.getElementById('accordion');
                    if (accordion) {
                        accordion.innerHTML = '<div class="alert alert-info"><?php echo Text::_('COM_J2COMMERCE_NO_VARIANTS'); ?></div>';
                    }
                    self.cleanupAllVariantSyncInputs();
                    self.updateVariantCount(0);
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ALL_VARIANTS_DELETED'); ?>');
                } else {
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'); ?>', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error deleting all variants:', error);
                self.showMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'); ?>', 'error');
            })
            .finally(function() {
                self.setButtonLoading(deleteAllBtn, false);
            });
        },

        /**
         * Delete selected variants via AJAX
         */
        deleteSelectedVariants: function() {
            var checkedVariants = document.querySelectorAll('input[name="vid[]"]:checked');
            if (checkedVariants.length === 0) {
                this.showMessage('<?php echo Text::_('COM_J2COMMERCE_NO_ITEM_SELECTED'); ?>', 'warning');
                return;
            }

            if (!confirm('<?php echo Text::_('COM_J2COMMERCE_CONFIRM_DELETE_SELECTED_VARIANTS'); ?>')) {
                return;
            }

            var deleteBtn = document.getElementById('deleteCheckedVariants');
            this.setButtonLoading(deleteBtn, true);

            var variantIds = Array.from(checkedVariants).map(function(cb) { return cb.value; });

            var self = this;
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', 'products.deleteSelectedVariantsAjax');
            formData.append('product_id', this.config.productId);
            formData.append(this.config.csrfToken, '1');
            variantIds.forEach(function(id) { formData.append('variant_ids[]', id); });

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    variantIds.forEach(function(id) {
                        var item = document.querySelector('.variant-item[data-variant-id="' + id + '"]');
                        if (item) {
                            item.style.transition = 'opacity 0.3s';
                            item.style.opacity = '0';
                            setTimeout(function() { item.remove(); }, 300);
                        }
                    });
                    setTimeout(function() {
                        self.updateVariantCount(data.total);
                        self.updateCheckboxState();
                    }, 350);
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_VARIANTS_DELETED'); ?>');
                } else {
                    self.showMessage(data.message || '<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'); ?>', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error deleting selected variants:', error);
                self.showMessage('<?php echo Text::_('COM_J2COMMERCE_ERROR_DELETING_VARIANTS'); ?>', 'error');
            })
            .finally(function() {
                self.setButtonLoading(deleteBtn, false);
            });
        },

        /**
         * Setup checkbox event handlers
         */
        setupCheckboxHandlers: function() {
            var toggleAll = document.getElementById('toggleAllCheckboxes');
            if (!toggleAll) return;

            var self = this;
            toggleAll.onchange = function() {
                var checkboxes = document.querySelectorAll('input[name="vid[]"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = toggleAll.checked;
                });
                self.updateCheckboxState();
            };

            document.querySelectorAll('input[name="vid[]"]').forEach(function(cb) {
                cb.onchange = function() { self.updateCheckboxState(); };
            });
        },

        /**
         * Update checkbox and button states
         */
        updateCheckboxState: function() {
            var checkboxes = document.querySelectorAll('input[name="vid[]"]');
            var toggleAll = document.getElementById('toggleAllCheckboxes');
            var deleteBtn = document.getElementById('deleteCheckedVariants');

            var anyChecked = Array.from(checkboxes).some(function(cb) { return cb.checked; });
            var allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(function(cb) { return cb.checked; });

            if (toggleAll) {
                toggleAll.checked = allChecked;
                toggleAll.indeterminate = anyChecked && !allChecked;
            }

            if (deleteBtn) {
                deleteBtn.disabled = !anyChecked;
            }
        },

        /**
         * Setup delete checked button handler
         */
        setupDeleteCheckedHandler: function() {
            var deleteBtn = document.getElementById('deleteCheckedVariants');
            if (!deleteBtn) return;

            var self = this;
            deleteBtn.onclick = function(e) {
                e.preventDefault();
                self.deleteSelectedVariants();
                return false;
            };
        },

        /**
         * Expand all accordion panels
         */
        expandAll: function() {
            document.querySelectorAll('.accordion .collapse').forEach(function(panel) {
                if (!panel.classList.contains('show')) {
                    var collapseInstance = new bootstrap.Collapse(panel, { toggle: false });
                    collapseInstance.show();
                }
            });
        },

        /**
         * Collapse all accordion panels
         */
        collapseAll: function() {
            document.querySelectorAll('.accordion .collapse.show').forEach(function(panel) {
                var collapseInstance = new bootstrap.Collapse(panel, { toggle: false });
                collapseInstance.hide();
            });
        },

        /**
         * Initialize
         */
        init: function() {
            this.setupPagination();
            this.setupCheckboxHandlers();
            this.setupDeleteCheckedHandler();
            this.setupDeleteVariantDelegation();
            initConfigToggles();
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

        setupDeleteVariantDelegation: function() {
            var self = this;
            document.addEventListener('click', function(e) {
                var deleteBtn = e.target.closest('.j2commerce-delete-variant');
                if (!deleteBtn) return;
                e.preventDefault();
                var variantId = parseInt(deleteBtn.dataset.variantId, 10);
                if (variantId) self.deleteVariant(variantId);
            });
        }
    };

    /**
     * Default variant star button handler — defined here (parent template)
     * so it survives innerHTML replacement from AJAX-loaded variant lists.
     */
    window.listVariableItemTask = async function(variantId, task, productId) {
        var button = document.getElementById('default-variant-' + variantId);
        if (!button) return false;

        var originalContent = button.innerHTML;
        button.classList.add('disabled');
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>';

        var controllerTask = task === 'setDefault' ? 'products.setDefaultVariantAjax' : 'products.unsetDefaultVariantAjax';
        var starIcon = 'far fa-regular fa-star';
        var setDefaultTitle = <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_SET_DEFAULT')); ?>;
        var unsetDefaultTitle = <?php echo json_encode(Text::_('COM_J2COMMERCE_PRODUCT_VARIANT_UNSET_DEFAULT')); ?>;
        var errorText = <?php echo json_encode(Text::_('COM_J2COMMERCE_ERROR')); ?>;

        try {
            var formData = new FormData();
            formData.append('option', 'com_j2commerce');
            formData.append('task', controllerTask);
            formData.append('format', 'json');
            formData.append('variant_id', variantId.toString());
            formData.append('product_id', productId.toString());

            var csrfToken = Joomla.getOptions('csrf.token');
            if (csrfToken) formData.append(csrfToken, '1');

            var response = await fetch('index.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network response was not ok');

            var result = await response.json();

            if (result.success) {
                if (task === 'setDefault') {
                    document.querySelectorAll('[id^="default-variant-"]').forEach(function(btn) {
                        var btnVid = btn.id.replace('default-variant-', '');
                        if (btnVid !== variantId.toString()) {
                            btn.setAttribute('title', setDefaultTitle);
                            btn.setAttribute('onclick', 'return listVariableItemTask(' + btnVid + ', \'setDefault\', ' + productId + ')');
                            btn.innerHTML = '<span class="' + starIcon + '" aria-hidden="true"></span>';
                            var hi = document.getElementById('isdefault_' + btnVid);
                            if (hi) hi.value = '0';
                        }
                    });
                    button.setAttribute('title', unsetDefaultTitle);
                    button.setAttribute('onclick', 'return listVariableItemTask(' + variantId + ', \'unsetDefault\', ' + productId + ')');
                    button.innerHTML = '<span class="icon-featured" aria-hidden="true"></span>';
                    button.classList.remove('disabled');
                    var hi = document.getElementById('isdefault_' + variantId);
                    if (hi) hi.value = '1';
                } else {
                    button.setAttribute('title', setDefaultTitle);
                    button.setAttribute('onclick', 'return listVariableItemTask(' + variantId + ', \'setDefault\', ' + productId + ')');
                    button.innerHTML = '<span class="' + starIcon + '" aria-hidden="true"></span>';
                    button.classList.remove('disabled');
                    var hi = document.getElementById('isdefault_' + variantId);
                    if (hi) hi.value = '0';
                }
                if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ 'success': [result.message] });
                }
            } else {
                throw new Error(result.message || errorText);
            }
        } catch (error) {
            console.error('Error setting default variant:', error);
            button.classList.remove('disabled');
            button.innerHTML = originalContent;
            if (typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                Joomla.renderMessages({ 'error': [error.message || errorText] });
            }
        }
        return false;
    };

    /**
     * Initialize inventory config toggles. Uses onchange assignment (not addEventListener)
     * to prevent duplicate listeners on AJAX reload.
     */
    function initConfigToggles() {
        document.querySelectorAll('.j2commerce-config-toggle').forEach(function(toggle) {
            var name = toggle.getAttribute('name');
            if (!name) return;

            var targetName = name.replace('use_store_config_', '');
            var targetInput = document.querySelector('[name="' + targetName + '"]');

            function updateState() {
                if (targetInput) targetInput.disabled = toggle.checked;
            }

            updateState();
            toggle.onchange = updateState;
        });
    }

    // Make available globally
    window.J2CommerceVariants = J2CommerceVariants;

    // Global functions for onclick handlers
    window.addFlexiVariant = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        J2CommerceVariants.addVariant();
        return false;
    };

    window.removeFlexiAllVariant = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        J2CommerceVariants.deleteAllVariants();
        return false;
    };

    window.deleteVariant = function(variantId, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        J2CommerceVariants.deleteVariant(variantId);
        return false;
    };

    window.setExpandAll = function() {
        J2CommerceVariants.expandAll();
    };

    window.setCloseAll = function() {
        J2CommerceVariants.collapseAll();
    };

    window.getVariantList = function(element) {
        var limitstart = parseInt(element.getAttribute('data-get_limitstart'), 10) || 0;
        J2CommerceVariants.loadVariantList(limitstart);
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        J2CommerceVariants.init();
    });
})();
</script>
