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
$productId = (int) $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php if (!empty($option['parent_id'])) continue; ?>
        <?php $defaultOptionValueId = $this->product->default_option_selections[$option['productoption_id']] ?? ''; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                        class="form-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                        onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')">
                    <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <option value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"<?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? ' selected' : ''; ?>
                            <?php echo $esc($optionValue['product_optionvalue_attribs'] ?? ''); ?>>
                            <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $esc($optionValue['product_optionvalue_prefix']); ?>
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
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="radioOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-radio-options d-flex flex-wrap gap-2" data-binded-label="#radioOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input type="radio"
                               name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                               value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                               id="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                               class="btn-check"
                               onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                               <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                               autocomplete="off"
                               <?php echo $esc($optionValue['product_optionvalue_attribs'] ?? ''); ?> />

                        <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                            <label class="btn btn-image p-0 form-check-label border-2" for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                <span class="visually-hidden"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                            </label>
                        <?php else : ?>
                            <label class="btn btn-sm btn-outline-secondary form-check-label border-2" for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>">
                                <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $esc($optionValue['product_optionvalue_prefix']); ?>
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
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#colorOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <input type="radio"
                               name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                               value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                               id="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                               class="btn-check"
                               autocomplete="off"
                               onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                               <?php echo ($defaultOptionValueId == $optionValue['product_optionvalue_id']) ? 'checked' : ''; ?>
                               <?php echo $esc($optionValue['product_optionvalue_attribs'] ?? ''); ?> />
                        <label for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>" class="btn btn-color fs-xl" title="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>" style="color:<?php echo $esc($optionValue['optionvalue_image']); ?>;">
                            <span class="visually-hidden"><?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <div class="form-check">
                            <input type="checkbox"
                                   name="product_option[<?php echo (int) $option['productoption_id']; ?>][]"
                                   value="<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                                   id="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>"
                                   class="form-check-input"
                                   onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo (int) $option['productoption_id']; ?>')"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo (int) $option['productoption_id']; ?>" />
                            <?php if ($showOptionImages && !empty($optionValue['optionvalue_image'])) : ?>
                                <img class="optionvalue-image me-1"
                                     src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>"
                                     alt="<?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>"
                                     width="32" />
                            <?php endif; ?>
                            <label class="form-check-label" for="option-value-<?php echo (int) $optionValue['product_optionvalue_id']; ?>">
                                <?php echo $esc(Text::_($optionValue['optionvalue_name'])); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    (
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $esc($optionValue['product_optionvalue_prefix']); ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                                    )
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="option-input-<?php echo (int) $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="option-input-<?php echo (int) $option['productoption_id']; ?>"
                       class="form-control"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="option-textarea-<?php echo (int) $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <textarea id="option-textarea-<?php echo (int) $option['productoption_id']; ?>"
                          class="form-control"
                          name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                          cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'file') : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <div>
                    <button type="button"
                            id="product-option-<?php echo (int) $option['productoption_id']; ?>"
                            data-loading-text="<?php echo $esc(Text::_('COM_J2COMMERCE_LOADING')); ?>"
                            class="btn btn-outline-secondary btn-sm">
                        <span class="fa fa-upload" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                    </button>
                    <input type="hidden"
                           name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                           value="" id="input-option<?php echo (int) $option['productoption_id']; ?>" />
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . (int) $option['productoption_id']; ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="<?php echo $element_date; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="<?php echo $element_date; ?>"
                       class="form-control <?php echo $element_date; ?>"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDatePicker($element_date, $option['option_params']); ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . (int) $option['productoption_id']; ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="<?php echo $element_datetime; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="<?php echo $element_datetime; ?>"
                       class="form-control <?php echo $element_datetime; ?>"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDateTimePicker($element_datetime, $option['option_params']); ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'time') : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2" for="j2commerce_time_<?php echo (int) $option['productoption_id']; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       id="j2commerce_time_<?php echo (int) $option['productoption_id']; ?>"
                       class="form-control j2commerce_time"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>" />
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option])->getArgument('html', ''); ?>

        <div id="ChildOptions<?php echo (int) $option['productoption_id']; ?>"></div>

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
                            errorSpan.className = 'j2file-upload-response text-danger';
                            errorSpan.textContent = json.error;
                            inputField.insertAdjacentElement('afterend', errorSpan);
                        }
                        if (json.success && inputField) {
                            const successSpan = document.createElement('span');
                            successSpan.className = 'j2file-upload-response text-success';
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