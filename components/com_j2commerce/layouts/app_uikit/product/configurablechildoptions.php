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

// Layout for rendering child configurable options via AJAX.
// Injected into #child-ChildOptions{poId} when a parent option is selected.
// Markup mirrors tmpl/uikit/view_configurableoptions.php so children look
// identical to their parents. Injected nodes keep inline onchange + the
// initConfigCheckboxes data-* hooks: innerHTML never executes the parent's
// per-option <script> blocks, so checkbox binding relies on initConfigCheckboxes
// in j2commerce.js, and select/radio/color filtering relies on inline onchange.

$product        = $displayData['product'];
$params         = $displayData['params'];
$options        = $displayData['options'] ?? [];
$product_helper = J2CommerceHelper::product();
$platform       = J2CommerceHelper::platform();
$product_id     = (int) $product->j2commerce_product_id;
$esc            = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<?php if (!empty($options)) : ?>
    <?php foreach ($options as $option) : ?>
        <?php $optionId = (int) $option['productoption_id']; ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select class="uk-select" name="product_option[<?php echo $optionId; ?>]" onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');">
                    <option value=""><?php echo Text::_('COM_J2COMMERCE_CHOOSE'); ?></option>
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'selected="selected"' : ''; ?>
                        <option <?php echo $checked; ?> value="<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                            <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                            <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                                (
                                <?php if ($params->get('product_option_price_prefix', 1)) : ?>
                                    <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                                <?php endif; ?>
                                <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>
                                )
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio' && !empty($option['optionvalue'])) : ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal" id="child-radioOption<?php echo $optionId; ?>"></span>
                </label>
                <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#child-radioOption<?php echo $optionId; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                        <?php $optionValueId = (int) $option_value['product_optionvalue_id']; ?>
                        <?php $childOptionValueInputId = 'child-option-value-' . $product_id . '-' . $optionId . '-' . $optionValueId; ?>
                        <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo $optionId; ?>]" value="<?php echo $optionValueId; ?>" id="<?php echo $childOptionValueInputId; ?>" class="uk-radio uk-hidden" onchange="doAjaxFilter(this.value, <?php echo $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');" autocomplete="off" />

                        <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                            <label class="btn-image" for="<?php echo $childOptionValueInputId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                <img class="optionvalue-image" src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                <span class="uk-invisible"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                            </label>
                        <?php else : ?>
                            <label class="uk-button uk-button-small uk-button-default" for="<?php echo $childOptionValueInputId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                                <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                                    <?php if ($params->get('product_option_price_prefix', 1)) : ?>
                                        <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                                    <?php endif; ?>
                                    <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color' && !empty($option['optionvalue'])) : ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal" id="child-colorOption<?php echo $optionId; ?>"></span>
                </label>
                <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#child-colorOption<?php echo $optionId; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                        <?php $colorValueId = (int) $option_value['product_optionvalue_id']; ?>
                        <?php $childOptionValueInputId = 'child-option-value-' . $product_id . '-' . $optionId . '-' . $colorValueId; ?>
                        <input <?php echo $checked; ?> type="radio" name="product_option[<?php echo $optionId; ?>]" value="<?php echo $colorValueId; ?>" id="<?php echo $childOptionValueInputId; ?>" class="uk-radio uk-hidden" onchange="doAjaxFilter(this.value, <?php echo $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');" />
                        <label for="<?php echo $childOptionValueInputId; ?>" class="btn-color" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                            <span class="uk-invisible"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom" data-config-checkbox="1" data-product-id="<?php echo $product_id; ?>" data-po-id="<?php echo $optionId; ?>">
                <?php if ($option['required']) : ?>
                    <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checkboxValueId = (int) $option_value['product_optionvalue_id']; ?>
                    <?php $childOptionValueInputId = 'child-option-value-' . $product_id . '-' . $optionId . '-' . $checkboxValueId; ?>
                    <label class="uk-flex uk-flex-middle" style="gap:.5rem;">
                        <input type="checkbox"
                            class="uk-checkbox"
                            name="product_option[<?php echo $optionId; ?>][]"
                            value="<?php echo $checkboxValueId; ?>"
                            id="<?php echo $childOptionValueInputId; ?>" />
                        <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                            <img class="optionvalue-image-<?php echo $checkboxValueId; ?>"
                                 src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" />
                        <?php endif; ?>
                        <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                            (
                            <?php if ($params->get('product_option_price_prefix', 1)) : ?>
                                <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                            <?php endif; ?>
                            <?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>
                            )
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <?php $textInputId = 'child-product-option-text-' . $product_id . '-' . $optionId; ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block" for="<?php echo $textInputId; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input id="<?php echo $textInputId; ?>" type="text" class="uk-input"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <?php $textareaInputId = 'child-product-option-textarea-' . $product_id . '-' . $optionId; ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block" for="<?php echo $textareaInputId; ?>">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <textarea id="<?php echo $textareaInputId; ?>" class="uk-textarea"
                    name="product_option[<?php echo $optionId; ?>]"
                    cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <div id="child-ChildOptions<?php echo $optionId; ?>"></div>

    <?php endforeach; ?>
<?php endif; ?>
