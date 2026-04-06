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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$uikit_plugin = PluginHelper::getPlugin('j2commerce', 'app_uikit');
$pluginParams = new Registry();
$pluginParams->loadString($uikit_plugin->params ?? '{}');
$showRequiredStar = $pluginParams->get('show_required_star');

$app = Factory::getApplication();
$user = $app->getIdentity();

$options = $this->product->options ?? [];
$productId = $this->product->j2commerce_product_id;
$productHelper = J2CommerceHelper::product();

$parentOptionIds = [];
?>
<?php if ($options) : ?>
<div class="options uk-margin" id="variable-options-<?php echo $productId; ?>" data-product__id="<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php
        $productOptionvalues = $productHelper->getProductOptionValues($option['productoption_id']);

        $parent = null;
        $isChild = false;
        foreach ($productOptionvalues as $ov) {
            $parentOptionIds[$ov->j2commerce_product_optionvalue_id] = [
                'productoption_id' => $ov->productoption_id,
                'selected' => $ov->product_optionvalue_default
            ];
            $parents = explode(",", $ov->parent_optionvalue ?? '');
            foreach ($parents as $p) {
                if (!$isChild && array_key_exists((int) $p, $parentOptionIds)) {
                    $isChild = true;
                }
            }
        }

        if ($isChild) {
            continue;
        }

        $optionCount = is_array($option['optionvalue']) ? count($option['optionvalue']) : 0;

        $useRadioButtons = false;
        if ($optionCount < 7 && (($option['type'] === 'select') || ($option['type'] === 'radio'))) {
            $useRadioButtons = true;
        }

        $disabled = '';
        $class = '';
        $singleOption = false;
        if ($optionCount === 1) {
            $disabled = ' disabled="disabled" disabled';
            $class = ' newline--disabled';
            $singleOption = true;
        }
        ?>

        <?php if ($option['type'] === 'select') : ?>
            <?php
            $optionCountSelect = count($option['optionvalue']);
            $disabledSelect = ($optionCountSelect === 1) ? ' disabled="disabled" disabled' : '';
            ?>
            <!-- select -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <select name="product_option[<?php echo $option['productoption_id']; ?>]"
                        data-is-variant="<?php echo $option['is_variant']; ?>"
                        class="uk-select j2c-option-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>">
                    <?php if ($option['is_variant'] == 0) : ?>
                        <option value=""><?php echo Text::_('COM_J2COMMERCE_ADDTOCART_SELECT'); ?></option>
                    <?php endif; ?>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <?php $checked = $optionValue['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                        <option <?php echo $checked; ?> value="<?php echo $optionValue['product_optionvalue_id']; ?>">
                            <?php echo stripslashes($this->escape(Text::_($optionValue['optionvalue_name']))); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (<?php if ($this->params->get('product_option_price_prefix', 1)) : ?><?php echo $optionValue['product_optionvalue_prefix']; ?><?php endif; ?>
                                <?php echo $productHelper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio') : ?>
            <?php
            $radioOptionCount = count($option['optionvalue']);
            $radioDisabled = '';
            $radioClass = '';
            $radioSingleOption = false;
            if ($radioOptionCount === 1) {
                $radioDisabled = ' disabled="disabled" disabled';
                $radioClass = ' newline--disabled';
                $radioSingleOption = true;
            }
            $checkedOptionId = null;
            ?>
            <!-- radio -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <?php
                        $checked = '';
                        if ($optionValue['product_optionvalue_default']) {
                            $checked = ' checked="checked"';
                            $checkedOptionId = $optionValue['product_optionvalue_id'];
                        }
                        ?>
                        <div class="radio--btn option_li--<?php echo $optionValue['product_optionvalue_id']; ?>">
                            <input<?php echo $checked; ?> type="radio"
                                   name="product_option[<?php echo $option['productoption_id']; ?>]"
                                   data-is-variant="<?php echo $option['is_variant']; ?>"
                                   value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                                   class="uk-radio j2c-option-radio<?php echo $radioDisabled; ?>"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $option['productoption_id']; ?>" />
                            <label for="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>">
                                <?php if ($this->params->get('image_for_product_options', 0) && isset($optionValue['optionvalue_image']) && !empty($optionValue['optionvalue_image'])) : ?>
                                    <img class="optionvalue-image-<?php echo $option['productoption_id']; ?>-<?php echo $optionValue['product_optionvalue_id']; ?>" src="<?php echo Uri::root(true) . '/' . $optionValue['optionvalue_image']; ?>" alt="" />
                                <?php endif; ?>
                                <?php echo stripslashes($this->escape(Text::_($optionValue['optionvalue_name']))); ?>
                                <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                    (<?php if ($this->params->get('product_option_price_prefix', 1)) : ?><?php echo $optionValue['product_optionvalue_prefix']; ?><?php endif; ?>
                                    <?php echo $productHelper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params); ?>)
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($checkedOptionId) : ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof doAjaxFilter === 'function') {
                        doAjaxFilter(
                            <?php echo $checkedOptionId; ?>,
                            <?php echo $productId; ?>,
                            <?php echo $option['productoption_id']; ?>,
                            '#option-<?php echo $option['productoption_id']; ?>'
                        );
                    }
                });
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox') : ?>
            <!-- checkbox -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                    <label class="uk-display-inline-block uk-margin-small-right">
                        <input type="checkbox"
                               name="product_option[<?php echo $option['productoption_id']; ?>][]"
                               value="<?php echo $optionValue['product_optionvalue_id']; ?>"
                               id="option-value-<?php echo $optionValue['product_optionvalue_id']; ?>"
                               class="uk-checkbox j2c-option-checkbox"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo $option['productoption_id']; ?>" />
                        <?php echo stripslashes($this->escape(Text::_($optionValue['optionvalue_name']))); ?>
                        <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                            (<?php if ($this->params->get('product_option_price_prefix', 1)) : ?><?php echo $optionValue['product_optionvalue_prefix']; ?><?php endif; ?>
                            <?php echo $productHelper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params); ?>)
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $textOptionParams = new Registry($option['option_params'] ?? ''); ?>
            <!-- text -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       placeholder="<?php echo $textOptionParams->get('place_holder', ''); ?>"
                       value="<?php echo $this->escape($option['optionvalue'] ?? ''); ?>"
                       class="uk-input" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <!-- textarea -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <textarea name="product_option[<?php echo $option['productoption_id']; ?>]"
                          cols="40" rows="3"
                          class="uk-textarea"><?php echo $this->escape($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'file') : ?>
            <!-- file -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <button type="button"
                        id="product-option-<?php echo $option['productoption_id']; ?>"
                        class="uk-button uk-button-default j2c-file-upload-btn"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $option['productoption_id']; ?>">
                    <span uk-icon="icon: upload" class="uk-margin-small-right"></span><?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                </button>
                <input type="hidden"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value=""
                       id="input-option<?php echo $option['productoption_id']; ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'date') : ?>
            <!-- date -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $this->escape($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_date uk-input" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'datetime') : ?>
            <!-- datetime -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $this->escape($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_datetime uk-input" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'time') : ?>
            <!-- time -->
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <div class="uk-margin-small-bottom">
                    <label class="uk-form-label uk-text-bold uk-flex">
                        <span><?php echo $this->escape(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required uk-text-danger uk-text-small uk-margin-small-left">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $option['productoption_id']; ?>]"
                       value="<?php echo $this->escape($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_time uk-input" />
            </div>
        <?php endif; ?>

        <div id="ChildOptions<?php echo $option['productoption_id']; ?>" class="child-options"></div>
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

                            fetch('index.php?option=com_j2commerce&view=carts&task=upload&product_id=' + node.dataset.productId, {
                                method: 'POST',
                                body: formData,
                            })
                            .then(response => response.json())
                            .then(json => {
                                node.disabled = false;

                                node.parentElement.querySelectorAll('.uk-text-danger, .uk-text-success').forEach(el => el.remove());

                                const inputField = node.parentElement.querySelector('input[type="hidden"]');

                                if (json.error && inputField) {
                                    const errorSpan = document.createElement('span');
                                    errorSpan.className = 'uk-text-danger';
                                    errorSpan.textContent = json.error;
                                    inputField.insertAdjacentElement('afterend', errorSpan);
                                }

                                if (json.success && inputField) {
                                    const successSpan = document.createElement('span');
                                    successSpan.className = 'uk-text-success';
                                    successSpan.textContent = json.success + ' ';
                                    inputField.insertAdjacentElement('afterend', successSpan);
                                    inputField.value = json.code;
                                }
                            })
                            .catch(error => {
                                console.error('Upload error:', error);
                                node.disabled = false;
                            });
                        }
                    }, 500);
                });
            })();
        </script>
    <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.body.addEventListener('after_doAjaxFilter_response', (e) => {
            const { product, response } = e.detail;

            if (response.pricing?.base_price) {
                const basePriceEl = product.querySelector('.base-price');
                if (basePriceEl) {
                    basePriceEl.innerHTML = response.pricing.base_price;

                    if (response.pricing.class) {
                        basePriceEl.style.display = response.pricing.class === 'show' ? '' : 'none';
                    }
                }
            }

            const discountEl = product.querySelector('.discount-percentage');
            if (discountEl && response.pricing?.discount_text) {
                discountEl.innerHTML = response.pricing.discount_text;
            }
        });
    });
</script>
