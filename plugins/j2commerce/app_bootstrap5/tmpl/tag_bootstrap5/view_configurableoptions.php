<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$platform = J2CommerceHelper::platform();
$options = $this->product->options;
$productId = (int) $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<?php if ($options) : ?>

<div class="options" id="configurable-options-<?php echo $productId; ?>" data-product-id="<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php if (!empty($option['parent_id'])) continue; ?>
        <?php $optionId = (int) $option['productoption_id']; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] == 'select' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo $optionId; ?>]" class="j2commerce-option-filter" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $optionId; ?>">
                    <option value=""><?php echo Text::_('COM_J2COMMERCE_CHOOSE'); ?></option>
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                        <?php $optionValueId = (int) $option_value['product_optionvalue_id']; ?>
                        <option <?php echo $checked; ?> value="<?php echo $optionValueId; ?>">
                            <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                            <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                                <?php endif; ?>
                                <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                )
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'radio' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="radioOption<?php echo $optionId; ?>"></span>
                </label>
                <div class="j2commerce-radio-options d-flex flex-wrap gap-2" data-binded-label="#radioOption<?php echo $optionId; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                        <?php $optionValueId = (int) $option_value['product_optionvalue_id']; ?>
                        <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo $optionId; ?>]" value="<?php echo $optionValueId; ?>" id="option-value-<?php echo $optionValueId; ?>" class="btn-check j2commerce-option-filter" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $optionId; ?>" autocomplete="off" />

                        <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) { ?>
                            <label class="btn btn-image p-0 form-check-label border-2" for="option-value-<?php echo $optionValueId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                <span class="visually-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                            </label>
                        <?php } else { ?>
                            <label class="btn btn-sm btn-outline-secondary form-check-label border-2" for="option-value-<?php echo $optionValueId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                                <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                <?php endif; ?>
                            </label>
                        <?php } ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'color' && !empty($option['optionvalue'])) : ?>
        <div id="option-<?php echo $optionId; ?>" class="option mb-3">
            <label class="form-label fw-semibold pb-1 mb-2">
                <?php echo $esc(Text::_($option['option_name'])); ?>:
                <?php if ($option['required']) : ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
                <span class="fw-normal fs-sm ms-1" id="colorOption<?php echo $optionId; ?>"></span>
            </label>
            <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#colorOption<?php echo $optionId; ?>">
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                    <?php $colorOptionValueId = (int) $option_value['product_optionvalue_id']; ?>
                    <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo $optionId; ?>]" value="<?php echo $colorOptionValueId; ?>" id="option-value-<?php echo $colorOptionValueId; ?>" class="btn-check j2commerce-option-filter" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $optionId; ?>" />
                    <label for="option-value-<?php echo $colorOptionValueId; ?>" class="btn btn-color fs-xl" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                        <span class="visually-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

        <?php if ($option['type'] == 'checkbox' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
        <!-- checkbox-->
        <div id="option-<?php echo $optionId; ?>" class="option" data-option-id="<?php echo $optionId; ?>" data-product-id="<?php echo $productId; ?>">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <?php $checkboxValueId = (int) $option_value['product_optionvalue_id']; ?>
                <input type="checkbox"
                    name="product_option[<?php echo $optionId; ?>][]"
                    value="<?php echo $checkboxValueId; ?>"
                    id="option-value-<?php echo $checkboxValueId; ?>"
                    class="j2commerce-checkbox-filter" />
                <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo $checkboxValueId; ?>"
                         src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" />
                <?php endif; ?>
                <label for="option-value-<?php echo $checkboxValueId; ?>">
                    <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                        <?php endif; ?>
                        <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                        )
                    <?php endif; ?>
                </label>
                <br>
            <?php endforeach; ?>
        </div>
        <br>
        <?php endif; ?>

        <?php if ($option['type'] == 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params']); ?>
            <!-- text -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
            <br>
        <?php endif; ?>

        <?php if ($option['type'] == 'textarea') : ?>
            <!-- textarea -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <textarea name="product_option[<?php echo $optionId; ?>]"
                    cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
            <br>
        <?php endif; ?>

        <?php if ($option['type'] == 'file') : ?>
            <!-- File -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <button type="button"
                    id="product-option-<?php echo $optionId; ?>"
                    data-loading-text="<?php echo $esc(Text::_('COM_J2COMMERCE_LOADING')); ?>"
                    data-product-id="<?php echo $productId; ?>"
                    class="btn btn-default j2commerce-file-upload-btn">
                    <span class="fa fa-upload" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                </button>
                <input type="hidden"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="" id="input-option<?php echo $optionId; ?>" />
            </div>
            <br>
        <?php endif; ?>

        <?php if ($option['type'] == 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . $optionId; ?>
            <!-- date -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    class="<?php echo $element_date; ?>" />
            </div>
            <br>
            <?php J2CommerceHelper::strapper()->addDatePicker($element_date, $option['option_params'], 'fa-solid fa-calendar'); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . $optionId; ?>
            <!-- datetime -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    class="<?php echo $element_datetime; ?>" />
            </div>
            <br>
            <?php J2CommerceHelper::strapper()->addDateTimePicker($element_datetime, $option['option_params'], 'fa-solid fa-calendar'); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'time') : ?>
            <!-- time -->
            <div id="option-<?php echo $optionId; ?>" class="option">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    class="j2commerce_time" />
            </div>
            <br>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>

        <div id="ChildOptions<?php echo $optionId; ?>"></div>

    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Event delegation for select, radio, and color option filters
    document.querySelectorAll('.j2commerce-option-filter').forEach(function(element) {
        element.addEventListener('change', function() {
            var productId = parseInt(this.dataset.productId, 10);
            var optionId = parseInt(this.dataset.optionId, 10);
            var value = this.value;
            var targetSelector = '#option-' + optionId;
            if (typeof doAjaxFilter === 'function') {
                doAjaxFilter(value, productId, optionId, targetSelector);
            }
        });
    });

    // Event delegation for checkbox option filters
    document.querySelectorAll('.j2commerce-checkbox-filter').forEach(function(checkbox) {
        checkbox.addEventListener('click', function() {
            var optionContainer = this.closest('.option');
            if (!optionContainer) return;

            var productId = parseInt(optionContainer.dataset.productId, 10);
            var optionId = parseInt(optionContainer.dataset.optionId, 10);

            var checkedCheckbox = optionContainer.querySelector('input[type="checkbox"]:checked');
            var checkboxValue = checkedCheckbox ? checkedCheckbox.value : '';

            if (typeof doAjaxFilter === 'function') {
                doAjaxFilter(checkboxValue, productId, optionId, '#option-' + optionId + ' input:checkbox');
            }
        });
    });

    // File upload handlers
    document.querySelectorAll('.j2commerce-file-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var node = this;
            var productId = parseInt(this.dataset.productId, 10);

            // Remove existing form if it exists
            var existingForm = document.getElementById('form-upload');
            if (existingForm) {
                existingForm.remove();
            }

            // Create a new form element
            var form = document.createElement('form');
            form.enctype = 'multipart/form-data';
            form.id = 'form-upload';
            form.style.display = 'none';

            var fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'file';
            form.appendChild(fileInput);

            // Prepend form to body
            document.body.insertAdjacentElement('afterbegin', form);

            // Trigger file input click
            fileInput.click();

            // Polling until a file is selected
            var timer = setInterval(function() {
                if (fileInput.value !== '') {
                    clearInterval(timer);

                    // Prepare FormData
                    var formData = new FormData(form);

                    // Set loading state
                    node.disabled = true;
                    var originalText = node.innerHTML;
                    node.innerHTML = node.getAttribute('data-loading-text') || 'Loading...';

                    fetch('index.php?option=com_j2commerce&view=carts&task=upload&product_id=' + productId, {
                        method: 'POST',
                        body: formData,
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(json) {
                        // Reset button state
                        node.disabled = false;
                        node.innerHTML = originalText;

                        // Remove any existing response messages
                        document.querySelectorAll('.j2file-upload-response').forEach(function(el) { el.remove(); });

                        var inputField = node.parentElement.querySelector('input[type="hidden"]');

                        if (json.error && inputField) {
                            var errorSpan = document.createElement('span');
                            errorSpan.className = 'j2file-upload-response text-danger';
                            errorSpan.textContent = json.error;
                            inputField.insertAdjacentElement('afterend', errorSpan);
                        }

                        if (json.success && inputField) {
                            var successSpan = document.createElement('span');
                            successSpan.className = 'j2file-upload-response text-success';
                            successSpan.textContent = json.success + ' ';
                            inputField.insertAdjacentElement('afterend', successSpan);
                            inputField.value = json.code;
                        }
                    })
                    .catch(function(error) {
                        alert(error.message + '\r\n' + error);
                        // Reset button on error
                        node.disabled = false;
                        node.innerHTML = originalText;
                    });
                }
            }, 500);
        });
    });
});
</script>