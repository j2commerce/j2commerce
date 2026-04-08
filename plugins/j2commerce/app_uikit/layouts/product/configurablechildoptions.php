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
// Injected into #ChildOptions{poId} when a parent option is selected.

$product = $displayData['product'];
$params  = $displayData['params'];
$options = $displayData['options'] ?? [];

$product_helper = J2CommerceHelper::product();
$platform = J2CommerceHelper::platform();
$product_id = $product->j2commerce_product_id;
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<?php if (!empty($options)) : ?>
    <?php foreach ($options as $option) : ?>
        <?php $optionId = (int) $option['productoption_id']; ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
        <?php $selectInputId = 'child-product-option-' . (int) $product_id . '-' . $optionId; ?>
        <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <label class="uk-form-label" for="<?php echo $selectInputId; ?>"><?php echo $esc(Text::_($option['option_name'])); ?>:</label>
            <select id="<?php echo $selectInputId; ?>" name="product_option[<?php echo $optionId; ?>]"
                class="uk-select"
                onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo (int) $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_ADDTOCART_SELECT'); ?></option>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'selected="selected"' : ''; ?>
                    <option <?php echo $checked; ?> value="<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                        <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo $esc($option_value['product_optionvalue_prefix']); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <?php $childOptionValueInputId = 'child-option-value-' . (int) $product_id . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                <input <?php echo $checked; ?> type="radio"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="<?php echo $childOptionValueInputId; ?>"
                    class="uk-radio"
                    onchange="doAjaxFilter(this.value, <?php echo (int) $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                         alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                <?php endif; ?>
                <label for="<?php echo $childOptionValueInputId; ?>">
                    <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo $esc($option_value['product_optionvalue_prefix']); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                    <?php endif; ?>
                </label>
                <br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom" data-config-checkbox="1" data-product-id="<?php echo (int) $product_id; ?>" data-po-id="<?php echo $optionId; ?>">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <?php $childOptionValueInputId = 'child-option-value-' . (int) $product_id . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                <input type="checkbox"
                    class="uk-checkbox"
                    name="product_option[<?php echo $optionId; ?>][]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="<?php echo $childOptionValueInputId; ?>" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                         alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                <?php endif; ?>
                <label for="<?php echo $childOptionValueInputId; ?>">
                    <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo $esc($option_value['product_optionvalue_prefix']); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                    <?php endif; ?>
                </label>
                <br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color' && !empty($option['optionvalue'])) : ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <div class="uk-form-label uk-text-bold">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-muted uk-text-small" id="child-colorOption<?php echo $optionId; ?>"></span>
                </div>
                <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap: 0.5rem;" data-binded-label="#child-colorOption<?php echo $optionId; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $childOptionValueInputId = 'child-option-value-' . (int) $product_id . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                        <input <?php echo $checked; ?> type="radio"
                            name="product_option[<?php echo $optionId; ?>]"
                            value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                            id="<?php echo $childOptionValueInputId; ?>"
                            class="uk-hidden"
                            onchange="doAjaxFilter(this.value, <?php echo (int) $product_id; ?>, <?php echo $optionId; ?>, '#child-option-<?php echo $optionId; ?>');" />

                        <label for="<?php echo $childOptionValueInputId; ?>" class="btn-color" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                            <span class="uk-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <?php $textInputId = 'child-product-option-text-' . (int) $product_id . '-' . $optionId; ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <label class="uk-form-label" for="<?php echo $textInputId; ?>"><?php echo $esc(Text::_($option['option_name'])); ?>:</label>
                <input id="<?php echo $textInputId; ?>" type="text" class="uk-input"
                    name="product_option[<?php echo $optionId; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <?php $textareaInputId = 'child-product-option-textarea-' . (int) $product_id . '-' . $optionId; ?>
            <div id="child-option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <label class="uk-form-label" for="<?php echo $textareaInputId; ?>"><?php echo $esc(Text::_($option['option_name'])); ?>:</label>
                <textarea id="<?php echo $textareaInputId; ?>" class="uk-textarea"
                    name="product_option[<?php echo $optionId; ?>]"
                    cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <div id="child-ChildOptions<?php echo $optionId; ?>"></div>

    <?php endforeach; ?>
<?php endif; ?>
