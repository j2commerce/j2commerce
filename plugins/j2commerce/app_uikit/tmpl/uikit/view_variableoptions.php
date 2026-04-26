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

$options = $this->product->options ?? [];

if (empty($options)) {
    return;
}

$platform = J2CommerceHelper::platform();
$productId = $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php if (!empty($option['parent_id'])) continue; ?>
        <?php $defaultOptionValueId = $this->product->default_option_selections[$option['productoption_id']] ?? ''; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo $option['productoption_id']; ?>]"
                        class="uk-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>"
                        onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $option['productoption_id']; ?>')">
                    <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <option value="<?php echo $optionValue['product_optionvalue_id']; ?>"<?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? ' selected' : ''; ?>
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>>
                            <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                <?php endif; ?>
                                <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                )
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal uk-text-small uk-margin-small-left" id="radioOption<?php echo $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#radioOption<?php echo $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input
                            type="radio"
                            name="product_option[<?php echo $option['productoption_id']; ?>]"
                            value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                            id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                            class="uk-radio uk-hidden"
                            onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $option['productoption_id']; ?>')"
                            <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                            autocomplete="off"
                            data-product-id="<?php echo $productId; ?>"
                            data-option-id="<?php echo $option['productoption_id']; ?>"
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>
                        />

                        <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                            <label class="btn-image uk-display-inline-block" for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <img class="optionvalue-image" src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                <span class="uk-invisible"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                            </label>
                        <?php else : ?>
                            <label class="uk-button uk-button-small uk-button-default" for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal uk-text-small uk-margin-small-left" id="colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#colorOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input
                            type="radio"
                            name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                            value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                            id="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                            class="uk-radio uk-hidden"
                            autocomplete="off"
                            onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                            <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                            <?php echo $optionValue['product_optionvalue_attribs'] ?? ''; ?>
                        />
                        <label for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>" class="btn-color" title="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" style="color:<?php echo $esc($optionValue['optionvalue_image']); ?>;">
                            <span class="uk-invisible"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <div class="uk-flex uk-flex-wrap" style="gap:.5rem;">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <label class="uk-flex uk-flex-middle" style="gap:.25rem;">
                            <input type="checkbox"
                                   name="product_option[<?php echo $option['productoption_id']; ?>][]"
                                   value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   class="uk-checkbox"
                                   onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $option['productoption_id']; ?>')"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $option['productoption_id']; ?>" />
                            <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                                <img class="optionvalue-image"
                                     src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>"
                                     alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>"
                                     width="32" />
                            <?php endif; ?>
                            <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $optionValue['product_optionvalue_prefix']; ?>
                                <?php endif; ?>
                                <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                )
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params']); ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block" for="option-input-<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="option-input-<?php echo $option['productoption_id']; ?>"
                       class="uk-input"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block" for="option-textarea-<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <textarea id="option-textarea-<?php echo $option['productoption_id']; ?>"
                          class="uk-textarea"
                          name="product_option[<?php echo $option['productoption_id']; ?>]"
                          cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'file') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <div>
                    <button type="button"
                            id="product-option-<?php echo $option['productoption_id']; ?>"
                            data-loading-text="<?php echo $esc(Text::_('COM_J2COMMERCE_LOADING')); ?>"
                            class="uk-button uk-button-default uk-button-small">
                        <span uk-icon="icon: upload" class="uk-margin-small-right"></span><?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                    </button>
                    <input type="hidden"
                           name="product_option[<?php echo $option['productoption_id']; ?>]"
                           value="" id="input-option<?php echo $option['productoption_id']; ?>" />
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block" for="<?php echo $element_date; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="<?php echo $element_date; ?>"
                       class="uk-input <?php echo $element_date; ?>"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDatePicker($element_date, $option['option_params'], '', ['uk-icon' => 'calendar']); ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block" for="<?php echo $element_datetime; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="<?php echo $element_datetime; ?>"
                       class="uk-input <?php echo $element_datetime; ?>"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDateTimePicker($element_datetime, $option['option_params'], '', ['uk-icon' => 'calendar']); ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'time') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-margin-small-bottom uk-display-block" for="j2commerce_time_<?php echo $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="j2commerce_time_<?php echo $option['productoption_id']; ?>"
                       class="uk-input j2commerce_time"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>

        <div id="ChildOptions<?php echo $option['productoption_id']; ?>"></div>

    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productId = <?php echo $productId; ?>;
    const optionsContainer = document.getElementById('variable-options-' + productId);
    if (!optionsContainer) return;

    // File upload handlers
    optionsContainer.querySelectorAll('[id^="product-option-"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const node = this;
            const existingForm = document.getElementById('form-upload');
            if (existingForm) existingForm.remove();

            const form = document.createElement('form');
            form.enctype = 'multipart/form-data';
            form.id = 'form-upload';
            form.style.display = 'none';

            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'file';
            form.appendChild(fileInput);
            document.body.insertAdjacentElement('afterbegin', form);
            fileInput.click();

            const timer = setInterval(() => {
                if (fileInput.value !== '') {
                    clearInterval(timer);
                    const formData = new FormData(form);
                    node.disabled = true;
                    const originalText = node.innerHTML;
                    node.innerHTML = node.getAttribute('data-loading-text') || 'Loading...';

                    fetch('index.php?option=com_j2commerce&view=carts&task=upload&product_id=<?php echo $productId; ?>', {
                        method: 'POST',
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(json => {
                        node.disabled = false;
                        node.innerHTML = originalText;
                        document.querySelectorAll('.j2file-upload-response').forEach(el => el.remove());

                        const inputField = node.parentElement.querySelector('input[type="hidden"]');
                        if (json.error && inputField) {
                            const errorSpan = document.createElement('span');
                            errorSpan.className = 'j2file-upload-response uk-text-danger';
                            errorSpan.textContent = json.error;
                            inputField.insertAdjacentElement('afterend', errorSpan);
                        }
                        if (json.success && inputField) {
                            const successSpan = document.createElement('span');
                            successSpan.className = 'j2file-upload-response uk-text-success';
                            successSpan.textContent = json.success + ' ';
                            inputField.insertAdjacentElement('afterend', successSpan);
                            inputField.value = json.code;
                        }
                    })
                    .catch(error => {
                        console.error('File upload error:', error);
                        node.disabled = false;
                        node.innerHTML = originalText;
                    });
                }
            }, 500);
        });
    });
});
</script>
