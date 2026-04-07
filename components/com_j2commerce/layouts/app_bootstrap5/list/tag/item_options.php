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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;

extract($displayData);

HTMLHelper::_('bootstrap.collapse');

$options = $product->options ?? [];

if (empty($options)) {
    return;
}

$productId = (int) $product->j2commerce_product_id;
$showOptionImages = (int) ($params->get('image_for_product_options', 0) ?? 0);
$productHelper = J2CommerceHelper::product();
$platform = J2CommerceHelper::platform();
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$optionsSummary = $productHelper::getOptionsSummary($options);

?>
<div class="j2commerce-product-options py-2" id="product-options-<?php echo $productId; ?>">
    <button class="btn btn-link btn-sm p-0 text-decoration-none j2commerce-options-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptions<?php echo $productId; ?>" aria-expanded="false" aria-controls="collapseOptions<?php echo $productId; ?>">
        <?php echo $esc($optionsSummary); ?><span class="ms-2 fa-solid fa-chevron-down fs-xs"></span>
    </button>
    <div class="collapse pt-2" id="collapseOptions<?php echo $productId; ?>">
        <?php foreach ($options as $option) : ?>
            <?php if (!empty($option['parent_id'])) continue; ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

            <?php if ($option['type'] == 'select' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
                <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </label>

                    <select name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                        class="form-select"
                        data-product-id="<?php echo $productId; ?>"
                        data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                        onchange="doAjaxFilter(this.options[this.selectedIndex].value, <?php echo $productId; ?>, <?php echo (int) $option['productoption_id']; ?>, '#option-<?php echo (int) $option['productoption_id']; ?>');">
                        <option value=""><?php echo Text::_('COM_J2COMMERCE_CHOOSE'); ?></option>
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $checked = $option_value['product_optionvalue_default'] ? 'selected="selected"' : ''; ?>
                            <option <?php echo $checked; ?> value="<?php echo (int) $option_value['product_optionvalue_id']; ?>">
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
                <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                        <span class="fw-normal fs-sm ms-1" id="radioOption<?php echo (int) $option['productoption_id']; ?>"></span>
                    </label>
                    <div class="j2commerce-radio-options d-flex flex-wrap gap-2" data-binded-label="#radioOption<?php echo (int) $option['productoption_id']; ?>">
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $checked = $option_value['product_optionvalue_default'] ? 'checked="checked"' : ''; ?>
                            <input <?php echo $checked; ?> type="radio"
                                name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                                value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                class="btn-check"
                                data-product-id="<?php echo $productId; ?>"
                                data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                                autocomplete="off"
                                onchange="doAjaxFilter(this.value, <?php echo $productId; ?>, <?php echo (int) $option['productoption_id']; ?>, '#option-<?php echo (int) $option['productoption_id']; ?>')" />

                            <?php if ($showOptionImages && !empty($option_value['optionvalue_image'])) : ?>
                                <label class="btn btn-image p-0 form-check-label fs-xs" for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                    <span class="visually-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                                </label>
                            <?php else : ?>
                                <label class="btn btn-sm btn-outline-secondary form-check-label fs-xs" for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>">
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
                <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                        <span class="fw-normal fs-sm ms-1" id="colorOption<?php echo (int) $option['productoption_id']; ?>"></span>
                    </label>
                    <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#colorOption<?php echo (int) $option['productoption_id']; ?>">
                        <?php foreach ($option['optionvalue'] as $option_value) : ?>
                            <?php $checked = !empty($option_value['product_optionvalue_default']) ? 'checked="checked"' : ''; ?>
                            <input <?php echo $checked; ?> type="radio"
                                name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                                value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                                class="btn-check"
                                data-product-id="<?php echo $productId; ?>"
                                data-option-id="<?php echo (int) $option['productoption_id']; ?>"
                                onchange="doAjaxFilter(this.value, <?php echo $productId; ?>, <?php echo (int) $option['productoption_id']; ?>, '#option-<?php echo (int) $option['productoption_id']; ?>');" />
                            <label for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" class="btn btn-color fs-xl" title="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" data-label="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" style="color:<?php echo $esc($option_value['optionvalue_image']); ?>;">
                                <span class="visually-hidden"><?php echo $esc(Text::_($option_value['optionvalue_name'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php if ($option['type'] == 'checkbox' && isset($option['optionvalue']) && !empty($option['optionvalue'])) : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3" data-config-checkbox="1" data-product-id="<?php echo $productId; ?>" data-po-id="<?php echo (int) $option['productoption_id']; ?>">
                <?php if ($option['required']) : ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <?php foreach ($option['optionvalue'] as $option_value) : ?>
                    <input type="checkbox"
                           name="product_option[<?php echo (int) $option['productoption_id']; ?>][]"
                           value="<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                           id="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>" />
                    <?php if ($showOptionImages && !empty($option_value['optionvalue_image'])) : ?>
                        <img class="optionvalue-image-<?php echo (int) $option_value['product_optionvalue_id']; ?>"
                             src="<?php echo Uri::root(true) . '/' . $esc($option_value['optionvalue_image']); ?>"
                             alt="<?php echo $esc(Text::_($option_value['optionvalue_name'])); ?>" />
                    <?php endif; ?>
                    <label for="option-value-<?php echo (int) $option_value['product_optionvalue_id']; ?>">
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
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <input type="text" class="form-control"
                       name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                       value="<?php echo $esc($option['optionvalue'] ?? ''); ?>"
                       placeholder="<?php echo $esc($text_option_params->get('place_holder', '')); ?>" />
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'textarea') : ?>
            <div id="option-<?php echo (int) $option['productoption_id']; ?>" class="option mb-3">
                <?php if ($option['required']) : ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
                <b><?php echo $esc(Text::_($option['option_name'])); ?>:</b><br>
                <textarea class="form-control"
                          name="product_option[<?php echo (int) $option['productoption_id']; ?>]"
                          cols="20" rows="5"><?php echo $esc($option['optionvalue'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$product, $option])->getArgument('html', ''); ?>

            <div id="ChildOptions<?php echo (int) $option['productoption_id']; ?>"></div>

        <?php endforeach; ?>
    </div>
</div>
