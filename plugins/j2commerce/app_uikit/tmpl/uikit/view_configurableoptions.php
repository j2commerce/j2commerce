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
$productId = $this->product->j2commerce_product_id;
$product_helper = J2CommerceHelper::product();
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<?php if ($options) : ?>

<div class="options" id="configurable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php if (!empty($option['parent_id'])) continue; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] == 'select' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select class="uk-select" name="product_option[<?php echo $option['productoption_id']; ?>]" onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo $productId; ?>, <?php echo $option['productoption_id']; ?>, '#option-<?php echo $option['productoption_id']; ?>');">
                    <option value=""><?php echo Text::_('COM_J2COMMERCE_CHOOSE'); ?></option>
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                        <option <?php echo $checked; ?> value="<?php echo $option_value['product_optionvalue_id']; ?>">
                            <?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>
                            <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $option_value['product_optionvalue_prefix']; ?>
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
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal" id="radioOption<?php echo $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#radioOption<?php echo $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                        <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo $option['productoption_id']; ?>]" value="<?php echo $option_value['product_optionvalue_id']; ?>" id="option-value-<?php echo $option_value['product_optionvalue_id']; ?>" class="uk-radio uk-hidden" onchange="doAjaxFilter(this.value, <?php echo $productId; ?>, <?php echo $option['productoption_id']; ?>, '#option-<?php echo $option['productoption_id']; ?>');" autocomplete="off" />

                        <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) { ?>
                            <label class="btn-image" for="option-value-<?php echo $option_value['product_optionvalue_id']; ?>" data-label="<?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>">
                                <img class="optionvalue-image" src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" alt="<?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>" width="56" style="width:56px;" />
                                <span class="uk-invisible"><?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?></span>
                            </label>
                        <?php } else { ?>
                            <label class="uk-button uk-button-small uk-button-default" for="option-value-<?php echo $option_value['product_optionvalue_id']; ?>" data-label="<?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>">
                                <?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>
                                <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $option_value['product_optionvalue_prefix']; ?>
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
        <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <label class="uk-form-label uk-text-bold uk-display-block">
                <?php echo $esc(Text::_($option['option_name'])); ?>:
                <?php if ($option['required']) : ?>
                    <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <span class="uk-text-normal" id="colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
            </label>
            <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#colorOption<?php echo (int) $option['productoption_id']; ?>">
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                    <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo (int) $option['productoption_id']; ?>]" value="<?php echo (int) $option_value['product_optionvalue_id']; ?>" id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" class="uk-radio uk-hidden" onchange="doAjaxFilter(this.value, <?php echo (int) $productId; ?>, <?php echo (int) $option['productoption_id']; ?>, '#option-<?php echo (int) $option['productoption_id']; ?>');" />
                    <label for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" class="btn-color" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                        <span class="uk-invisible"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

        <?php if ($option['type'] == 'checkbox' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
        <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="uk-text-danger">*</span>
            <?php endif; ?>
            <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <label class="uk-flex uk-flex-middle" style="gap:.5rem;">
                    <input type="checkbox"
                        class="uk-checkbox"
                        name="product_option[<?php echo $option['productoption_id']; ?>][]"
                        value="<?php echo $option_value['product_optionvalue_id']; ?>"
                        id="option-value-<?php echo $option_value['product_optionvalue_id']; ?>" />
                    <?php if ($this->params->get('image_for_product_options', 0) && isset($option_value['optionvalue_image']) && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo $option_value['product_optionvalue_id']; ?>"
                             src="<?php echo Uri::root(true) . '/' . $option_value['optionvalue_image']; ?>" />
                    <?php endif; ?>
                    <?php echo stripslashes($this->escape(Text::_($option_value['optionvalue_name']))); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                        (
                        <?php if ($this->params->get('product_option_price_prefix', 1)) : ?>
                            <?php echo $option_value['product_optionvalue_prefix']; ?>
                        <?php endif; ?>
                        <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $this->product, $this->params, 'products.view.option'); ?>
                        )
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <script type="text/javascript">
            (function() {
                const poId = '<?php echo $option['productoption_id']; ?>';
                const productId = '<?php echo $productId; ?>';
                const checkboxes = document.querySelectorAll('#option-' + poId + ' input[type="checkbox"]');
                checkboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('click', function() {
                        const checkedCheckbox = document.querySelector('#option-' + poId + ' input[type="checkbox"]:checked');
                        const checkboxValue = checkedCheckbox ? checkedCheckbox.value : '';
                        doAjaxFilter(checkboxValue, productId, poId, '#option-' + poId + ' input:checkbox');
                    });
                });
            })();
        </script>
        <?php endif; ?>

        <?php if ($option['type'] == 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params']); ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    class="uk-input"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>"
                    placeholder="<?php echo $text_option_params->get('place_holder', ''); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'textarea') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <textarea class="uk-textarea" name="product_option[<?php echo $option['productoption_id']; ?>]"
                    cols="20" rows="5"><?php echo $option['optionvalue']; ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'file') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <button type="button"
                    id="product-option-<?php echo $option['productoption_id']; ?>"
                    data-loading-text="<?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>"
                    class="uk-button uk-button-default">
                    <span uk-icon="icon: upload"></span> <?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                </button>
                <input type="hidden"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="" id="input-option<?php echo $option['productoption_id']; ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] == 'date') : ?>
            <?php $element_date = 'j2commerce_date_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    class="uk-input <?php echo $element_date; ?>"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDatePicker($element_date, $option['option_params'], '', ['uk-icon' => 'calendar']); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'datetime') : ?>
            <?php $element_datetime = 'j2commerce_datetime_' . $option['productoption_id']; ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    class="uk-input <?php echo $element_datetime; ?>"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>" />
            </div>
            <?php J2CommerceHelper::strapper()->addDateTimePicker($element_datetime, $option['option_params'], '', ['uk-icon' => 'calendar']); ?>
        <?php endif; ?>

        <?php if ($option['type'] == 'time') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $this->escape(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text"
                    class="uk-input j2commerce_time"
                    name="product_option[<?php echo $option['productoption_id']; ?>]"
                    value="<?php echo $option['optionvalue']; ?>" />
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>

        <div id="ChildOptions<?php echo $option['productoption_id']; ?>"></div>

    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isset($options) && !empty($options)) : ?>
    <?php foreach ($options as $option) : ?>
        <?php if ($option['type'] == 'file') : ?>
            <script type="text/javascript">
                (function() {
                    const productOptionBtn = document.getElementById('product-option-<?php echo $option['productoption_id']; ?>');

                    if (!productOptionBtn) return;

                    productOptionBtn.addEventListener('click', function() {
                        const node = this;

                        const existingForm = document.getElementById('form-upload');
                        if (existingForm) {
                            existingForm.remove();
                        }

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

                                fetch('index.php?option=com_j2commerce&view=carts&task=upload&product_id=<?php echo $this->product->j2commerce_product_id; ?>', {
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
                                    alert(error.message + '\r\n' + error);
                                    node.disabled = false;
                                    node.innerHTML = originalText;
                                });
                            }
                        }, 500);
                    });
                })();
            </script>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
