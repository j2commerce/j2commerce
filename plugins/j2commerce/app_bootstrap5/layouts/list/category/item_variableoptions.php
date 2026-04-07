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

$options = isset($product->options) && !empty($product->options) ? $product->options : [];

if (empty($options)) {
    return;
}

$productId = (int) $product->j2commerce_product_id;
$productHelper = J2CommerceHelper::product();
$platform = J2CommerceHelper::platform();
$showOptionImages = (int) ($params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$optionsSummary = $productHelper::getOptionsSummary($options);
?>
<div class="j2commerce-variable-options py-2" id="variable-options-<?php echo $productId; ?>">
    <button class="btn btn-link btn-sm p-0 text-decoration-none j2commerce-configurable-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptions<?php echo $productId; ?>" aria-expanded="false" aria-controls="collapseOptions<?php echo $productId; ?>">
        <?php echo $esc($optionsSummary); ?><span class="ms-2 fa-solid fa-chevron-down fs-xs"></span>
    </button>
    <div class="collapse pt-2" id="collapseOptions<?php echo $productId; ?>">
        <?php foreach ($options as $option) : ?>
            <?php $optionId = (int) $option['productoption_id']; ?>
            <?php $defaultOptionValueId = $product->default_option_selections[$optionId] ?? ''; ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

            <?php if ($option['type'] === 'select') : ?>
                <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </label>
                    <select name="product_option[<?php echo $optionId; ?>]" class="form-select" onchange="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $optionId; ?>')" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $optionId; ?>">
                        <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                        <?php foreach ($option['optionvalue'] as $ov) : ?>
                            <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                            <option value="<?php echo $ovId; ?>"<?php echo ($defaultOptionValueId == $ovId) ? ' selected' : ''; ?>>
                                <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($option['type'] === 'radio') : ?>
                <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                        <span class="fw-normal fs-sm ms-1" id="radioOption<?php echo $optionId; ?>"></span>
                    </label>
                    <div class="j2commerce-radio-options d-flex flex-wrap gap-2" data-binded-label="#radioOption<?php echo $optionId; ?>">
                        <?php foreach ($option['optionvalue'] as $ov) : ?>
                            <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                            <input type="radio"
                                   name="product_option[<?php echo $optionId; ?>]"
                                   value="<?php echo $ovId; ?>"
                                   id="option-value-<?php echo $ovId; ?>"
                                   class="btn-check"
                                   onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $optionId; ?>')"
                                   <?php echo ($defaultOptionValueId == $ovId) ? 'checked' : ''; ?>
                                   autocomplete="off"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $optionId; ?>" />

                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) : ?>
                                <label class="btn btn-image p-0 form-check-label" for="option-value-<?php echo $ovId; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($ov['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" width="48" style="width:48px;" />
                                    <span class="visually-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                                </label>
                            <?php else : ?>
                                <label class="btn btn-sm btn-outline-secondary form-check-label fs-xs" for="option-value-<?php echo $ovId; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                                </label>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($option['type'] === 'color') : ?>
                <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                    <label class="form-label fw-semibold pb-1 mb-1">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                        <span class="fw-normal fs-sm ms-1" id="colorOption<?php echo $optionId; ?>"></span>
                    </label>
                    <div class="j2commerce-color-options d-flex flex-wrap gap-2" data-binded-label="#colorOption<?php echo $optionId; ?>">
                        <?php foreach ($option['optionvalue'] as $ov) : ?>
                            <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                            <input
                                type="radio"
                                name="product_option[<?php echo $optionId; ?>]"
                                value="<?php echo $ovId; ?>"
                                id="option-value-<?php echo $ovId; ?>"
                                class="btn-check"
                                autocomplete="off"
                                onclick="doAjaxPrice(<?php echo $productId; ?>, 'option-<?php echo $optionId; ?>')"
                                data-product-id="<?php echo $productId; ?>"
                                data-option-id="<?php echo $optionId; ?>"
                                <?php echo ($defaultOptionValueId == $ovId) ? 'checked' : ''; ?>
                            />
                            <label for="option-value-<?php echo $ovId; ?>" class="btn btn-color fs-xl" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" style="color:<?php echo $esc($ov['optionvalue_image']); ?>;">
                                <span class="visually-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$product, $option])->getArgument('html', ''); ?>
        <?php endforeach; ?>
    </div>
</div>
