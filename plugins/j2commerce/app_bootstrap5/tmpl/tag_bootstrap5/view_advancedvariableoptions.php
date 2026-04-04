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

$bs5plusPlugin = PluginHelper::getPlugin('j2commerce', 'app_bootstrap5plus');
$pluginParams = new Registry();
$pluginParams->loadString($bs5plusPlugin->params ?? '');
$showRequiredStar = $pluginParams->get('show_required_star');

$app = Factory::getApplication();
$user = $app->getIdentity();

$options = $this->product->options ?? [];
$productId = (int) $this->product->j2commerce_product_id;
$productHelper = J2CommerceHelper::product();
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$parentOptionIds = [];
?>
<?php if ($options) : ?>
<div class="options" id="variable-options-<?php echo $productId; ?>" data-product__id="<?php echo $productId; ?>">
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
            <?php $optionId = (int) $option['productoption_id']; ?>
            <!-- select -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <select name="product_option[<?php echo $optionId; ?>]"
                        data-is-variant="<?php echo (int) $option['is_variant']; ?>"
                        class="form-select form-select-lg j2c-option-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $optionId; ?>">
                    <?php if ($option['is_variant'] == 0) : ?>
                        <option value=""><?php echo Text::_('J2STORE_ADDTOCART_SELECT'); ?></option>
                    <?php endif; ?>
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <?php $checked = $optionValue['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                        <?php $ovId = (int) $optionValue['product_optionvalue_id']; ?>
                        <option <?php echo $checked; ?> value="<?php echo $ovId; ?>">
                            <?php echo stripslashes($esc(Text::_($optionValue['optionvalue_name']))); ?>
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
            <div id="option-<?php echo $optionId; ?>" class="option pb-3 mb-2 mb-lg-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                        <?php
                        $checked = '';
                        if ($optionValue['product_optionvalue_default']) {
                            $checked = ' checked="checked"';
                            $checkedOptionId = $optionValue['product_optionvalue_id'];
                        }
                        ?>
                        <div class="radio--btn option_li--<?php echo $ovId; ?>">
                            <input<?php echo $checked; ?> type="radio"
                                   name="product_option[<?php echo $optionId; ?>]"
                                   data-is-variant="<?php echo (int) $option['is_variant']; ?>"
                                   value="<?php echo $ovId; ?>"
                                   id="option-value-<?php echo $ovId; ?>"
                                   class="btn-check j2c-option-radio<?php echo $radioDisabled; ?>"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $optionId; ?>" />
                            <label for="option-value-<?php echo $ovId; ?>" class="btn btn-sm btn-outline-secondary">
                                <?php if ($this->params->get('image_for_product_options', 0) && isset($optionValue['optionvalue_image']) && !empty($optionValue['optionvalue_image'])) : ?>
                                    <img class="optionvalue-image-<?php echo $optionId; ?>-<?php echo $ovId; ?>" src="<?php echo Uri::root(true) . '/' . $esc($optionValue['optionvalue_image']); ?>" alt="" />
                                <?php endif; ?>
                                <?php echo stripslashes($esc(Text::_($optionValue['optionvalue_name']))); ?>
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
                <?php $checkedOptionIdInt = (int) $checkedOptionId; ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (typeof doAjaxFilter === 'function') {
                        doAjaxFilter(
                            <?php echo $checkedOptionIdInt; ?>,
                            <?php echo $productId; ?>,
                            <?php echo $optionId; ?>,
                            '#option-<?php echo $optionId; ?>'
                        );
                    }
                });
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox') : ?>
            <!-- checkbox -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <?php foreach ($option['optionvalue'] as $optionValue) : ?>
                    <?php $cbValueId = (int) $optionValue['product_optionvalue_id']; ?>
                    <div class="form-check form-check-inline">
                        <input type="checkbox"
                               name="product_option[<?php echo $optionId; ?>][]"
                               value="<?php echo $cbValueId; ?>"
                               id="option-value-<?php echo $cbValueId; ?>"
                               class="form-check-input j2c-option-checkbox"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo $optionId; ?>" />
                        <label for="option-value-<?php echo $cbValueId; ?>" class="form-check-label">
                            <?php echo stripslashes($esc(Text::_($optionValue['optionvalue_name']))); ?>
                            <?php if ($optionValue['product_optionvalue_price'] > 0 && $this->params->get('product_option_price', 1)) : ?>
                                (<?php if ($this->params->get('product_option_price_prefix', 1)) : ?><?php echo $optionValue['product_optionvalue_prefix']; ?><?php endif; ?>
                                <?php echo $productHelper->displayPrice($optionValue['product_optionvalue_price'], $this->product, $this->params); ?>)
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $textOptionParams = new Registry($option['option_params'] ?? ''); ?>
            <!-- text -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $optionId; ?>]"
                       placeholder="<?php echo $esc($textOptionParams->get('place_holder', '')); ?>"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       class="form-control" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <!-- textarea -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <textarea name="product_option[<?php echo $optionId; ?>]"
                          cols="40" rows="3"
                          class="form-control"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'file') : ?>
            <!-- file -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <button type="button"
                        id="product-option-<?php echo $optionId; ?>"
                        class="btn btn-default j2c-file-upload-btn"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo $optionId; ?>">
                    <span class="fas fa-solid fa-upload me-1" aria-hidden="true"></span><?php echo Text::_('J2STORE_PRODUCT_OPTION_CHOOSE_FILE'); ?>
                </button>
                <input type="hidden"
                       name="product_option[<?php echo $optionId; ?>]"
                       value=""
                       id="input-option<?php echo $optionId; ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'date') : ?>
            <!-- date -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $optionId; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_date form-control" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'datetime') : ?>
            <!-- datetime -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $optionId; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_datetime form-control" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'time') : ?>
            <!-- time -->
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <div class="mb-2">
                    <label class="form-label fw-bold d-flex">
                        <span><?php echo $esc(Text::_($option['option_name'])); ?></span>
                        <?php if ($option['required'] && $showRequiredStar) : ?>
                            <span class="required fs-xs ms-1 align-self-start">*</span>
                        <?php endif; ?>
                    </label>
                </div>
                <input type="text"
                       name="product_option[<?php echo $optionId; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       class="j2commerce_time form-control" />
            </div>
        <?php endif; ?>

        <div id="ChildOptions<?php echo $optionId; ?>" class="child-options"></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productId = <?php echo $productId; ?>;

    // Handle select option changes
    document.querySelectorAll('.j2c-option-select').forEach(select => {
        select.addEventListener('change', function() {
            const optionId = this.dataset.optionId;
            if (typeof doAjaxPrice === 'function') {
                doAjaxPrice(productId, '#option-' + optionId);
            }
        });
    });

    // Handle radio option changes
    document.querySelectorAll('.j2c-option-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            const optionId = this.dataset.optionId;
            if (typeof doAjaxFilter === 'function') {
                doAjaxFilter(this.value, productId, optionId, '#option-' + optionId);
            }
        });
    });

    // Handle checkbox option changes
    document.querySelectorAll('.j2c-option-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            const optionId = this.dataset.optionId;
            if (typeof doAjaxFilter === 'function') {
                doAjaxFilter(this.value, productId, optionId, '#option-' + optionId + ' input:checkbox');
            }
        });
    });

    // Handle file upload buttons
    document.querySelectorAll('.j2c-file-upload-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const optionId = this.dataset.optionId;
            const fileProductId = this.dataset.productId;
            const node = this;

            // Remove existing form if it exists
            const existingForm = document.getElementById('form-upload');
            if (existingForm) {
                existingForm.remove();
            }

            // Create a new form element
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

                    fetch('index.php?option=com_j2commerce&view=carts&task=upload&product_id=' + fileProductId, {
                        method: 'POST',
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(json => {
                        node.disabled = false;

                        // Remove any existing error or success messages
                        node.parentElement.querySelectorAll('.text-danger, .text-success').forEach(el => el.remove());

                        const inputField = node.parentElement.querySelector('input[type="hidden"]');

                        if (json.error && inputField) {
                            const errorSpan = document.createElement('span');
                            errorSpan.className = 'text-danger';
                            errorSpan.textContent = json.error;
                            inputField.insertAdjacentElement('afterend', errorSpan);
                        }

                        if (json.success && inputField) {
                            const successSpan = document.createElement('span');
                            successSpan.className = 'text-success';
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
    });

    // Handle after_doAjaxFilter_response event for price updates
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
