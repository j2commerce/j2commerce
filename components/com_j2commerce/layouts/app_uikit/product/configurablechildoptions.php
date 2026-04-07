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

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <select name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                class="uk-select"
                onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo (int) $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');">
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
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                <input <?php echo $checked; ?> type="radio"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    class="uk-radio"
                    onchange="doAjaxFilter(this.value, <?php echo (int) $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                         alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                <?php endif; ?>
                <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
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
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom" data-config-checkbox="1" data-product-id="<?php echo (int) $product_id; ?>" data-po-id="<?php echo (int) $option['productoption_id']; ?>">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <input type="checkbox"
                    class="uk-checkbox"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>][]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                         alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                <?php endif; ?>
                <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
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
            <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-muted uk-text-small" id="child-colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap: 0.5rem;" data-binded-label="#child-colorOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                        <input <?php echo $checked; ?> type="radio"
                            name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                            value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                            id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                            class="uk-hidden"
                            onchange="doAjaxFilter(this.value, <?php echo (int) $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');" />

                        <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" class="btn-color" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                            <span class="uk-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text" class="uk-input"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                    placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <textarea class="uk-textarea"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

        <div id="child-ChildOptions<?php echo (int) $option['productoption_id']; ?>"></div>

    <?php endforeach; ?>
<?php endif; ?>
