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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

$options = isset($product->options) && !empty($product->options) ? $product->options : [];

if (empty($options)) {
    return;
}

$productId = $product->j2commerce_product_id;
$showOptionImages = (int) ($params->get('image_for_product_options', 0) ?? 0);
$productHelper = J2CommerceHelper::product();
$platform = J2CommerceHelper::platform();
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$optionsSummary = $productHelper::getOptionsSummary($options);

?>
<div class="j2commerce-configurable-options uk-padding-small-top" id="configurable-options-<?php echo $productId; ?>">
    <button class="uk-button uk-button-text uk-padding-remove j2commerce-configurable-button" type="button" uk-toggle="target: #collapseOptions<?php echo $productId; ?>" aria-expanded="false" aria-controls="collapseOptions<?php echo $productId; ?>">
        <?php echo $esc($optionsSummary); ?><span class="uk-margin-small-left fa-solid fa-chevron-down"></span>
    </button>
    <div class="uk-hidden uk-padding-small-top" id="collapseOptions<?php echo $productId; ?>">
        <?php foreach ($options as $option) : ?>
            <?php $optionId = (int) $option['productoption_id']; ?>
            <?php if (!empty($option['parent_id'])) continue; ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

            <?php if ($option['type'] == 'select' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
                <?php $selectInputId = 'product-option-' . (int) $productId . '-' . $optionId; ?>
                <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                    <label class="uk-form-label" for="<?php echo $selectInputId; ?>">
                        <?php echo $esc(Text::_($option['option_name'])); ?>
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                    </label>

                    <select id="<?php echo $selectInputId; ?>" name="product_option[<?php echo $optionId; ?>]"
                            class="uk-select"
                            onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo (int) $productId; ?>, <?php echo $optionId; ?>, '#option-<?php echo $optionId; ?>');">
                        <option value=""><?php echo Text::_('COM_J2COMMERCE_CHOOSE'); ?></option>
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                            <option <?php echo $checked; ?> value="<?php echo $option_value['product_optionvalue_id']; ?>">
                                <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                                <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                                    (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo $esc($option_value['product_optionvalue_prefix']); ?><?php endif; ?><?php echo $productHelper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($option['type'] == 'radio' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
                <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                    <div class="uk-form-label">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                        <span id="radioOption<?php echo $optionId; ?>"></span>
                    </div>
                    <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap: .5rem;" data-binded-label="#radioOption<?php echo $optionId; ?>">
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $optionValueInputId = 'option-value-' . (int) $productId . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                            <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                            <input <?php echo $checked; ?> type="radio"
                                name="product_option[<?php echo $optionId; ?>]"
                                value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                id="<?php echo $optionValueInputId; ?>"
                                class="uk-hidden"
                                onchange="doAjaxFilter(this.value, <?php echo (int) $productId; ?>, <?php echo $optionId; ?>, '#option-<?php echo $optionId; ?>');" autocomplete="off" />

                            <?php if ($showOptionImages && !empty($option_value['optionvalue_image'])) : ?>
                                <label class="btn-image uk-padding-remove" for="<?php echo $optionValueInputId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image uk-margin-small-right" src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                    <span class="uk-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                                </label>
                            <?php else : ?>
                                <label class="uk-button uk-button-default uk-button-small" for="<?php echo $optionValueInputId; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                    <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                                    <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                                        <?php if ($params->get('product_option_price_prefix', 1)) : ?>
                                            <?php echo $esc($option_value['product_optionvalue_prefix']); ?>
                                        <?php endif; ?>
                                        <?php echo $productHelper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>
                                    <?php endif; ?>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($option['type'] == 'color' && !empty($option['optionvalue'])) : ?>
                <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                    <div class="uk-form-label">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                        <span id="colorOption<?php echo $optionId; ?>"></span>
                    </div>
                    <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap: .5rem;" data-binded-label="#colorOption<?php echo $optionId; ?>">
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $optionValueInputId = 'option-value-' . (int) $productId . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                            <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                            <input <?php echo $checked; ?> type="radio"
                                name="product_option[<?php echo $optionId; ?>]"
                                value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                id="<?php echo $optionValueInputId; ?>"
                                class="uk-hidden"
                                onchange="doAjaxFilter(this.value, <?php echo (int) $productId; ?>, <?php echo $optionId; ?>, '#option-<?php echo $optionId; ?>');" />
                            <label for="<?php echo $optionValueInputId; ?>" class="btn-color" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                                <span class="uk-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php if ($option['type'] == 'checkbox' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom" data-config-checkbox="1" data-product-id="<?php echo (int) $productId; ?>" data-po-id="<?php echo $optionId; ?>">
                <?php if ($option['required']) : ?>
                    <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $optionValueInputId = 'option-value-' . (int) $productId . '-' . $optionId . '-' . (int) $option_value['product_optionvalue_id']; ?>
                    <input type="checkbox"
                           name="product_option[<?php echo $optionId; ?>][]"
                           value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                           id="<?php echo $optionValueInputId; ?>" />
                    <?php if ($showOptionImages && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                             src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                             alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                    <?php endif; ?>
                    <label for="<?php echo $optionValueInputId; ?>">
                        <?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                            (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo $esc($option_value['product_optionvalue_prefix']); ?><?php endif; ?><?php echo $productHelper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                        <?php endif; ?>
                    </label>
                    <br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <?php $textInputId = 'product-option-text-' . (int) $productId . '-' . $optionId; ?>
            <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                    <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label" for="<?php echo $textInputId; ?>"><?php echo $esc(Text::_($option['option_name'])); ?>:</label>
                <input id="<?php echo $textInputId; ?>" type="text" class="uk-input"
                       name="product_option[<?php echo $optionId; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <?php $textareaInputId = 'product-option-textarea-' . (int) $productId . '-' . $optionId; ?>
            <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                    <span class="uk-text-danger">*</span>
                <?php endif; ?>
                <label class="uk-form-label" for="<?php echo $textareaInputId; ?>"><?php echo $esc(Text::_($option['option_name'])); ?>:</label>
                <textarea id="<?php echo $textareaInputId; ?>" class="uk-textarea"
                          name="product_option[<?php echo $optionId; ?>]"
                          cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$product, $option])->getArgument('html', ''); ?>

            <div id="ChildOptions<?php echo $optionId; ?>"></div>

        <?php endforeach; ?>
    </div>
</div>
