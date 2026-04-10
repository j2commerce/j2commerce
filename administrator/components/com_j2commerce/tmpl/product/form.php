<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
use J2Commerce\Component\J2commerce\Administrator\View\Product\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var HtmlView $this */

$platform = J2CommerceHelper::platform();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_j2commerce.admin', 'media/com_j2commerce/css/administrator/j2commerce_admin.css');
HTMLHelper::_('bootstrap.modal');


$app = Factory::getApplication();
$option = $app->input->getString('option');

// Ensure language files are loaded (may already be loaded by plugin, but safe to call again)
$language = $app->getLanguage();
$language->load('com_j2commerce', JPATH_ADMINISTRATOR);

$item = $displayData['product'];
$html = $displayData['html'] ?? '';

$row_class = 'row';
$col_class = 'col-md-';
$product_type_class = 'badge bg-primary';
$alert_html = '<joomla-alert type="danger" close-text="Close" dismiss="true" role="alert" style="animation-name: joomla-alert-fade-in;"><div class="alert-heading"><span class="error"></span><span class="visually-hidden">Error</span></div><div class="alert-wrapper"><div class="alert-message" >'.htmlspecialchars(Text::_('COM_J2COMMERCE_INVALID_INPUT_FIELD')).'</div></div></joomla-alert>' ;

// Use form_prefix from displayData if available (from plugin), otherwise use standalone component prefix
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$switcherDefaults = ['onchange' => '', 'label' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'class' => ''];

$productTypeField = new ProductTypeField();
$productTypeField->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
$element = new SimpleXMLElement('<field />');
$productTypeField->setup($element, '');
$productTypes = $productTypeField->getOptions();

$loadSubTemplate = $displayData['loadSubTemplate'];

// Extract additional display data from plugin
$variantStats = $displayData['variantStats'] ?? [
    'total' => 0,
    'manage_inventory_yes' => 0,
    'manage_inventory_no' => 0,
    'shipping_enabled' => 0,
    'shipping_disabled' => 0,
    'in_stock_percent' => 100,
    'out_of_stock_percent' => 0,
];
$imageCount = $displayData['imageCount'] ?? 0;
$filterCount = $displayData['filterCount'] ?? 0;
$hasRelations = $displayData['hasRelations'] ?? false;

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('core')->useScript('form.validate');
$wa->registerAndUseStyle('com_j2commerce.editview', 'media/com_j2commerce/css/administrator/editview.css');

Factory::getApplication()->getDocument()->addScriptOptions('com_j2commerce.productForm', [
    'productId' => (int) ($item->j2commerce_product_id ?? 0),
    'productType' => $item->product_type ?? '',
    'enabled' => (bool) ($item->enabled ?? 0),
    'csrfToken' => Session::getFormToken(),
]);

$submitButtonJs = <<<JS
(function() {
    'use strict';
    var J2CommerceSubmitbuttonOverride = Joomla.submitbutton || function(){};
    Joomla.submitbutton = function(pressbutton) {
        var form = document.adminForm;
        if (pressbutton === 'article.cancel') {
            document.adminForm.task.value = pressbutton;
            J2CommerceSubmitbuttonOverride(pressbutton);
        } else if (pressbutton === 'article.apply') {
            if (document.formvalidator.isValid(form)) {
                document.adminForm.task.value = pressbutton;
                var submitBtn = document.getElementById('submit_button');
                if (submitBtn !== null) {
                    submitBtn.onclick = function() {
                        this.disabled = true;
                    };
                }
                J2CommerceSubmitbuttonOverride(pressbutton);
            }
        } else {
            if (document.formvalidator.isValid(form)) {
                document.adminForm.task.value = pressbutton;
                J2CommerceSubmitbuttonOverride(pressbutton);
            }
        }
    };
})();
JS;

$wa->addInlineScript($submitButtonJs);

?>
<div class="j2commerce">
    <div class="j2commerce-product-edit-form">
        <?php if (!empty($item->j2commerce_product_id)): ?>
        <div class="j2commerce-existing-product-display">
            <div class="product-card-v3 mb-5">
                <div class="card-header-v3">
                    <div class="header-content">
                        <div class="header-left">
                            <?php if ($item->enabled): ?>
                            <div class="d-flex align-items-center me-2 mb-4">
                                <label id="j2commerce-product-enabled-radio-group-lbl" for="j2commerce-product-enabled-radio-group" class="me-3"><?php echo Text::_('COM_J2COMMERCE_TREAT_AS_PRODUCT');?></label>
                                <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', [
                                    'name'  => $formPrefix.'[enabled]',
                                    'id'    => 'j2commerce-product-enabled-radio-group-header',
                                    'value' => $item->enabled ?? 0,
                                    'options' => [
                                        (object) ['value' => 0, 'text' => Text::_('JNO')],
                                        (object) ['value' => 1, 'text' => Text::_('JYES')]
                                    ],
                                    'onchange' => 'this.form.submit();',
                                    'label' => Text::_('COM_J2COMMERCE_TREAT_AS_PRODUCT'),
                                    'disabled' => false,
                                    'readonly' => false,
                                    'class' => 'form-check-input',
                                    'dataAttribute' => 'data-bs-toggle="tooltip" title="Toggle Status"'
                                ] + $switcherDefaults);?>
                            </div>
                            <?php endif; ?>
                            <div class="product-name-v3 d-flex align-items-center">
                                <span><?php echo htmlspecialchars($item->product_name, ENT_QUOTES, 'UTF-8');?></span>
                                <button type="button" class="btn btn-soft-primary btn-sm ms-3 fw-medium"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#productDetailsCollapse"
                                        aria-expanded="false"
                                        aria-controls="productDetailsCollapse"
                                        id="viewDetailsBtn">
                                    <?php echo Text::_('COM_J2COMMERCE_VIEW_DETAILS'); ?>
                                    <span class="fa-solid fa-chevron-down ms-1 view-details-chevron" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        <?php if ($variantStats['manage_inventory_yes'] > 0): ?>
                        <div class="header-right">
                            <?php if ($variantStats['in_stock_percent'] > 0): ?>
                            <div class="stock-indicator in-stock">
                                <span class="stock-dot"></span>
                                <span class="stock-text"><?php echo $variantStats['in_stock_percent']; ?>% <?php echo Text::_('COM_J2COMMERCE_IN_STOCK'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($variantStats['out_of_stock_percent'] > 0): ?>
                            <div class="stock-indicator out-of-stock">
                                <span class="stock-dot"></span>
                                <span class="stock-text"><?php echo $variantStats['out_of_stock_percent']; ?>% <?php echo Text::_('COM_J2COMMERCE_OUT_OF_STOCK'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body-v3 collapse" id="productDetailsCollapse">
                    <div class="main-content">
                        <div class="image-section">
                            <?php if ($imageCount > 0): ?>
                                <div class="image-frame">
                                    <?php echo ImageHelper::getInstance()->getProductImage($item->main_image,height: 160,width: 160,class: 'object-fit-cover img-fluid',alt: htmlspecialchars($item->product_name, ENT_QUOTES, 'UTF-8'));?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="data-section">
                            <div class="data-list">
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE'); ?></span>
                                    <span class="data-val d-flex align-items-center justify-content-between">
                                        <span><?php echo htmlspecialchars(ProductTypeField::getProductTypes()[$item->product_type] ?? $item->product_type, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($item->j2commerce_product_id && $item->enabled && $item->product_type): ?>
                                            <button type="button" class="btn btn-soft-primary btn-sm ms-2 fw-medium" data-bs-toggle="modal" data-bs-target="#changeProductTypeModal"><?php echo Text::_('COM_J2COMMERCE_CHANGE_PRODUCT_TYPE'); ?></button>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_MANAGE_INVENTORY'); ?></span>
                                    <span class="data-val">
                                        <?php if($variantStats['manage_inventory_yes'] > 0){
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-success').'">'.Text::_('JYES').'</span>';
                                        } else {
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-danger').'">'.Text::_('JNO').'</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_IMAGES'); ?></span>
                                    <span class="data-val"><?php echo Text::sprintf($imageCount === 1 ? 'COM_J2COMMERCE_COUNT_IMAGE' : 'COM_J2COMMERCE_COUNT_IMAGES', $imageCount); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_SHIPPING'); ?></span>
                                    <span class="data-val">
                                        <?php if($variantStats['shipping_enabled'] > 0){
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-success').'">'.Text::_('JYES').'</span>';
                                        } else {
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-danger').'">'.Text::_('JNO').'</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_FILTERS'); ?></span>
                                    <span class="data-val"><?php echo Text::sprintf($filterCount === 1 ? 'COM_J2COMMERCE_COUNT_FILTER' : 'COM_J2COMMERCE_COUNT_FILTERS', $filterCount); ?></span>
                                </div>
                                <div class="data-item">
                                    <span class="data-key"><?php echo Text::_('COM_J2COMMERCE_RELATIONS'); ?></span>
                                    <span class="data-val">
                                        <?php if($hasRelations){
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-success').'">'.Text::_('JYES').'</span>';
                                        } else {
                                            echo '<span class="'.J2htmlHelper::badgeClass('badge text-bg-danger').'">'.Text::_('JNO').'</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="description-area">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6><?php echo Text::_('COM_J2COMMERCE_PLUGIN_SHORTCODE');?></h6>
                                <button type="button" class="btn btn-sm btn-soft-dark fw-medium" id="j2commerceToggleShortcodes" data-bs-toggle="collapse" data-bs-target="#collapseShortcodes" aria-expanded="false" aria-controls="collapseShortcodes">
                                    <?php echo Text::_('COM_J2COMMERCE_VIEW_ADDITIONAL_SHORTCODES'); ?>
                                </button>
                            </div>
                            <div class="mb-3 shortcode-item">
                                <code>{j2commerce}<?php echo $item->j2commerce_product_id; ?>|cart{/j2commerce}</code>
                                <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_PLUGIN_SHORTCODE_HELP_TEXT');?></div>
                            </div>
                            <div class="collapse" id="collapseShortcodes">
                                <div class="shortcode-item mb-3">
                                    <code>{j2commerce}<?php echo $item->j2commerce_product_id; ?>|upsells|crosssells{/j2commerce}</code>
                                    <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_PLUGIN_SHORTCODE_HELP_TEXT_ADDITIONAL');?></div>
                                </div>
                                <div class="shortcode-item">
                                    <code>price|thumbnail|mainimage|mainadditional|upsells|crosssells</code>
                                    <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_PLUGIN_SHORTCODE_HELP_TEXT_ADDITIONAL2');?></div>
                                </div>
                            </div>
                        </div>
                        <div class="notice-bar">
                            <span class="icon-info-circle"></span>
                            <?php
                            $db = Factory::getContainer()->get('DatabaseDriver');
                            $query = $db->getQuery(true)
                                ->select($db->quoteName('extension_id'))
                                ->from($db->quoteName('#__extensions'))
                                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
                                ->where($db->quoteName('element') . ' = ' . $db->quote('j2commerce'));
                            $pluginId = (int) $db->setQuery($query)->loadResult();
                            $pluginLink = '<a href="' . Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId) . '">'
                                . Text::_('COM_J2COMMERCE_CONTENT_PLUGIN_LINK_TEXT') . '</a>';
                            ?>
                            <span><?php echo Text::sprintf('COM_J2COMMERCE_PLUGIN_SHORTCODE_FOOTER_WARNING', $pluginLink);?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!($item->enabled ?? 0)): ?>
        <div class="row">
            <div class="col-12 mb-3">
                <fieldset id="fieldset-j2commerce-product-type" class="options-form">
                    <legend><?php echo Text::_('PLG_CONTENT_J2COMMERCE_PRODUCT_TYPE'); ?></legend>
                    <div class="form-grid">
                        <div class="control-group">
                            <div class="control-label">
                                <label id="j2commerce-product-enabled-radio-group-lbl" for="j2commerce-product-enabled-radio-group"><?php echo Text::_('COM_J2COMMERCE_TREAT_AS_PRODUCT');?></label>
                            </div>
                            <div class="controls">
                                <?php echo LayoutHelper::render('joomla.form.field.radio.switcher', [
                                    'name'  => $formPrefix.'[enabled]',
                                    'id'    => 'j2commerce-product-enabled-radio-group',
                                    'value' => 0,
                                    'options' => [
                                        (object) ['value' => 0, 'text' => Text::_('JNO')],
                                        (object) ['value' => 1, 'text' => Text::_('JYES')]
                                    ],
                                    'class' => 'form-check-input',
                                    'onchange' => '',
                                    'label' => Text::_('COM_J2COMMERCE_TREAT_AS_PRODUCT'),
                                    'disabled' => false,
                                    'readonly' => false,
                                    'dataAttribute' => 'data-bs-toggle="tooltip" title="Toggle Status"'
                                ] + $switcherDefaults);?>
                            </div>
                        </div>
                        <div id="j2commerce-product-type-wrapper" class="d-none">
                        <div class="control-group">
                            <div class="control-label">
                                <label for="product_type"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE');?></label>
                            </div>
                            <div class="controls">
                                <?php if(!empty($item->product_type)): ?>
                                    <span class="<?php echo $product_type_class;?>"><?php echo htmlspecialchars(ProductTypeField::getProductTypes()[$item->product_type] ?? $item->product_type, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <input type="hidden" name="<?php echo $formPrefix.'[product_type]'?>" value="<?php echo $item->product_type; ?>" />
                                <?php else: ?>
                                    <select name="<?php echo $formPrefix;?>[product_type]" id="product_type" class="form-select">
                                        <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_PRODUCT_TYPE'); ?></option>
                                        <?php foreach ($productTypes as $option) : ?>
                                            <option value="<?php echo $option->value; ?>"
                                                <?php echo (($item->product_type ?? '') == $option->value) ? 'selected' : ''; ?>>
                                                <?php echo Text::_($option->text); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="control-group form-group">
                            <input type="button" id="submit_button" onclick="Joomla.submitbutton('article.apply')" class="btn btn-primary" value="<?php echo Text::_('COM_J2COMMERCE_SAVE_AND_CONTINUE'); ?>" />
                        </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php endif; ?>

        <?php if(($item->j2commerce_product_id ?? 0) && ($item->enabled ?? 0) && ($item->product_type ?? '')): ?>
        <div class="row">
            <div class="col-12">
                <div id="fieldset-j2commerce-product-settings">
                    <!--<legend><?php /*echo Text::_('COM_J2COMMERCE_PRODUCT_SETTINGS'); */?></legend>-->
                    <input type="hidden" name="<?php echo $formPrefix.'[j2commerce_product_id]'?>" value="<?php echo $item->j2commerce_product_id; ?>" />

                    <?php echo J2CommerceHelper::loadSubTemplate($item->product_type, ['product' => $item, 'form_prefix' => $formPrefix],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'); ?>

                    <input type="hidden" name="<?php echo $formPrefix.'[product_type]'?>" value="<?php echo $item->product_type; ?>" />

                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($item->j2commerce_product_id ?? 0) && ($item->enabled ?? 0) && ($item->product_type ?? '')): ?>
        <!-- Modal: Change Product Type -->
        <div id="changeProductTypeModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="changeProductTypeModalLabel">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fs-3 fw-bold" id="changeProductTypeModalLabel"><?php echo Text::_('COM_J2COMMERCE_CHANGE_PRODUCT_TYPE'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-body_inner p-3">
                            <p><?php echo Text::_('COM_J2COMMERCE_SELECT_NEW_PRODUCT_TYPE'); ?></p>
                            <div class="mb-3">
                                <select id="j2commerceNewProductType" class="form-select">
                                    <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_PRODUCT_TYPE'); ?></option>
                                    <?php foreach ($productTypes as $option) : ?>
                                        <?php if ($option->value && $option->value !== $item->product_type) : ?>
                                            <option value="<?php echo htmlspecialchars($option->value, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo Text::_($option->text); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="j2commerceProductTypeWarning" class="alert alert-danger d-none">
                                <span class="icon-warning" aria-hidden="true"></span>
                                <strong><?php echo Text::_('COM_J2COMMERCE_WARNING'); ?></strong>
                                <p class="mb-0 mt-2"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TYPE_CHANGE_WARNING'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-danger box-shadow-none" data-bs-dismiss="modal">
                            <?php echo Text::_('JCANCEL'); ?>
                        </button>
                        <button type="button" id="changeTypeConfirmBtn" class="btn btn-primary box-shadow-none" disabled>
                            <?php echo Text::_('COM_J2COMMERCE_CONFIRM_CHANGE_TYPE'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$productFormJs = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var options = Joomla.getOptions('com_j2commerce.productForm') || {};
    var productId = options.productId || 0;
    var productType = options.productType || '';
    var enabled = options.enabled || false;
    var csrfToken = options.csrfToken || '';

    var initNestedTabs = function(forceClick) {
        var j2commerceTabContainer = document.querySelector('.j2commerce');
        if (!j2commerceTabContainer) return;

        var nestedJoomlaTab = j2commerceTabContainer.querySelector('joomla-tab');
        if (!nestedJoomlaTab) return;

        var tabButtonContainer = nestedJoomlaTab.querySelector('[role="tablist"]');
        if (!tabButtonContainer) return;

        var firstButton = tabButtonContainer.querySelector('button[role="tab"]');
        if (!firstButton) return;

        var tabId = firstButton.getAttribute('aria-controls');
        var tabElement = tabId ? document.getElementById(tabId) : null;

        if (forceClick || !tabElement || !tabElement.hasAttribute('active')) {
            firstButton.click();
        }
    };

    document.addEventListener('joomla.tab.shown', function(event) {
        var joomlaTabComponent = event.target.closest('joomla-tab');
        if (!joomlaTabComponent) return;

        if (joomlaTabComponent.closest('.j2commerce')) return;

        var shownTabId = event.target.getAttribute('aria-controls');
        if (!shownTabId) return;

        var shownTabElement = document.getElementById(shownTabId);
        if (shownTabElement && shownTabElement.querySelector('.j2commerce')) {
            setTimeout(function() { initNestedTabs(true); }, 100);
        }
    });

    if (typeof customElements !== 'undefined' && customElements.whenDefined) {
        customElements.whenDefined('joomla-tab').then(function() {
            setTimeout(function() {
                initNestedTabs(false);

                // Deep-link to a specific sub-tab via sessionStorage
                var targetTab = sessionStorage.getItem('j2ctab');
                if (targetTab) {
                    sessionStorage.removeItem('j2ctab');
                    var tabEl = document.getElementById(targetTab);
                    if (tabEl) {
                        var joomlaTab = tabEl.closest('joomla-tab');
                        if (joomlaTab && typeof joomlaTab.activateTab === 'function') {
                            joomlaTab.activateTab(tabEl);
                        }
                    }
                }
            }, 50);
        });
    }

    if (productId && enabled && productType) {
        var modalEl = document.getElementById('changeProductTypeModal');
        var selectEl = document.getElementById('j2commerceNewProductType');
        var warningEl = document.getElementById('j2commerceProductTypeWarning');
        var confirmBtn = document.getElementById('changeTypeConfirmBtn');

        if (selectEl && warningEl && confirmBtn) {
            // Reset modal state when opened
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function() {
                    selectEl.value = '';
                    warningEl.classList.add('d-none');
                    confirmBtn.disabled = true;
                });
            }

            // Show warning and enable confirm when a type is selected
            selectEl.addEventListener('change', function() {
                if (this.value) {
                    warningEl.classList.remove('d-none');
                    confirmBtn.disabled = false;
                } else {
                    warningEl.classList.add('d-none');
                    confirmBtn.disabled = true;
                }
            });

            // Submit the product type change
            confirmBtn.addEventListener('click', function() {
                var newType = selectEl.value;
                if (!newType) return;

                var formData = new FormData();
                formData.append('product_id', productId);
                formData.append('new_product_type', newType);
                formData.append(csrfToken, 1);

                confirmBtn.disabled = true;
                confirmBtn.insertAdjacentHTML('beforeend', ' <span class="spinner-border spinner-border-sm ms-2" role="status"><span class="visually-hidden">' + Joomla.Text._("COM_J2COMMERCE_LOADING") + '</span></span>');

                fetch('index.php?option=com_j2commerce&task=product.changeProductType', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(json) {
                    if (json.success) {
                        location.reload();
                    } else {
                        Joomla.renderMessages({error: [json.message || 'Failed to change product type']});
                        confirmBtn.disabled = false;
                        var spinner = confirmBtn.querySelector('.spinner-border');
                        if (spinner) spinner.remove();
                        var bsModal = bootstrap.Modal.getInstance(modalEl);
                        if (bsModal) bsModal.hide();
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    confirmBtn.disabled = false;
                    var spinner = confirmBtn.querySelector('.spinner-border');
                    if (spinner) spinner.remove();
                });
            });
        }
    }

    var tabMenuLinks = document.querySelectorAll('div.j2commerce-tab-menu > div.list-group > a');
    var tabContents = document.querySelectorAll('div.j2commerce-tab > div.j2commerce-tab-content');

    tabMenuLinks.forEach(function(link, index) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            tabMenuLinks.forEach(function(sibling) { sibling.classList.remove('active'); });
            link.classList.add('active');
            tabContents.forEach(function(content) { content.classList.remove('active'); });
            if (tabContents[index]) {
                tabContents[index].classList.add('active');
            }
        });
    });

    var enableRadioGroup = document.getElementById('j2commerce-product-enabled-radio-group');
    var typeWrapper = document.getElementById('j2commerce-product-type-wrapper');

    if (enableRadioGroup && typeWrapper) {
        var productTypeSelect = typeWrapper.querySelector('select[name$="[product_type]"]');

        var toggleProductType = function() {
            var checked = enableRadioGroup.querySelector('input[type="radio"]:checked');
            var isEnabled = checked && checked.value === '1';

            typeWrapper.classList.toggle('d-none', !isEnabled);

            if (productTypeSelect) {
                if (isEnabled) {
                    productTypeSelect.setAttribute('required', '');
                } else {
                    productTypeSelect.removeAttribute('required');
                    productTypeSelect.value = '';
                }
            }
        };

        enableRadioGroup.addEventListener('change', toggleProductType);
        toggleProductType();
    }

    // View Details collapse state management
    var viewDetailsBtn = document.getElementById('viewDetailsBtn');
    var productDetailsCollapse = document.getElementById('productDetailsCollapse');

    if (viewDetailsBtn && productDetailsCollapse) {
        var storageKey = 'j2commerce_product_details_expanded_' + productId;
        var chevron = viewDetailsBtn.querySelector('.view-details-chevron');

        // Initialize state from localStorage (collapsed by default)
        var isExpanded = localStorage.getItem(storageKey) === 'true';

        // Set initial state
        if (isExpanded) {
            productDetailsCollapse.classList.add('show');
            viewDetailsBtn.setAttribute('aria-expanded', 'true');
            if (chevron) {
                chevron.style.transform = 'rotate(180deg)';
            }
        }

        // Listen for collapse events to update localStorage and chevron
        productDetailsCollapse.addEventListener('show.bs.collapse', function() {
            localStorage.setItem(storageKey, 'true');
            viewDetailsBtn.setAttribute('aria-expanded', 'true');
            if (chevron) {
                chevron.style.transform = 'rotate(180deg)';
            }
        });

        productDetailsCollapse.addEventListener('hide.bs.collapse', function() {
            localStorage.setItem(storageKey, 'false');
            viewDetailsBtn.setAttribute('aria-expanded', 'false');
            if (chevron) {
                chevron.style.transform = 'rotate(0deg)';
            }
        });
    }
});
JS;

$wa->addInlineScript($productFormJs);
?>
