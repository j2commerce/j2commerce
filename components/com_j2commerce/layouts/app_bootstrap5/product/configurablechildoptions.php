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
$product_id = (int) $product->j2commerce_product_id;
?>
<?php if (!empty($options)) : ?>
    <?php foreach ($options as $option) : ?>

        <?php if ($option['type'] === 'select' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:</b><br>
            <select name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                class="form-select"
                onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_ADDTOCART_SELECT'); ?></option>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'selected="selected"' : ''; ?>
                    <option <?php echo $checked; ?> value="<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                        <?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo htmlspecialchars($option_value['product_optionvalue_prefix'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                <input <?php echo $checked; ?> type="radio"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    onchange="doAjaxFilter(this.value, <?php echo $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . htmlspecialchars($option_value['optionvalue_image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                    <?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo htmlspecialchars($option_value['product_optionvalue_prefix'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                    <?php endif; ?>
                </label>
                <br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'checkbox' && !empty($option['optionvalue'])) : ?>
        <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3" data-config-checkbox="1" data-product-id="<?php echo $product_id; ?>" data-po-id="<?php echo (int) $option['productoption_id']; ?>">
            <?php if ($option['required']) : ?>
            <span class="required">*</span>
            <?php endif; ?>
            <b><?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:</b><br>
            <?php foreach ($option['optionvalue'] as $option_value) : ?>
                <input type="checkbox"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>][]"
                    value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                    id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" />
                <?php if ($params->get('image_for_product_options', 0) && !empty($option_value['optionvalue_image'])) : ?>
                    <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                         src="<?php echo Uri::root(true) . '/' . htmlspecialchars($option_value['optionvalue_image'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
                    <?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($option_value['product_optionvalue_price'] > 0 && $params->get('product_option_price', 1)) : ?>
                        (<?php if ($params->get('product_option_price_prefix', 1)) : ?><?php echo htmlspecialchars($option_value['product_optionvalue_prefix'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?><?php echo $product_helper->displayPrice($option_value['product_optionvalue_price'], $product, $params, 'products.view.option'); ?>)
                    <?php endif; ?>
                </label>
                <br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color' && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                    <span class="fw-normal fs-sm ms-1" id="child-colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#child-colorOption<?php echo (int) $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $option_value) : ?>
                        <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                        <input <?php echo $checked; ?> type="radio"
                            name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                            value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                            id="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                            class="btn-check"
                            onchange="doAjaxFilter(this.value, <?php echo $product_id; ?>, <?php echo (int) $option['productoption_id']; ?>, '#child-option-<?php echo (int) $option['productoption_id']; ?>');" />

                        <label for="child-option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" class="btn btn-color fs-xl" title="<?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?>" style="color:<?php echo htmlspecialchars($option_value['optionvalue_image'], ENT_QUOTES, 'UTF-8'); ?>;">
                            <span class="visually-hidden"><?php echo htmlspecialchars(Text::_($option_value['optionvalue_name']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($option['type'] === 'text') : ?>
            <?php $text_option_params = $platform->getRegistry($option['option_params'] ?? '{}'); ?>
            <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:</b><br>
                <input type="text" class="form-control"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    value="<?php echo htmlspecialchars($option['optionvalue'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="<?php echo htmlspecialchars($text_option_params->get('place_holder', ''), ENT_QUOTES, 'UTF-8'); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="child-option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                <span class="required">*</span>
                <?php endif; ?>
                <b><?php echo htmlspecialchars(Text::_($option['option_name']), ENT_QUOTES, 'UTF-8'); ?>:</b><br>
                <textarea class="form-control"
                    name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                    cols="20" rows="5"><?php echo htmlspecialchars($option['optionvalue'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        <?php endif; ?>

        <div id="child-ChildOptions<?php echo (int) $option['productoption_id']; ?>"></div>

    <?php endforeach; ?>
<?php endif; ?>
