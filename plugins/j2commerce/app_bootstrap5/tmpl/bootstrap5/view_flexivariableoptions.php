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

/** @var \J2Commerce\Component\J2commerce\Site\View\Product\HtmlView $this */

$options = isset($this->product->options) && !empty($this->product->options) ? $this->product->options : [];

if (empty($options)) {
    return;
}

$productId = (int) $this->product->j2commerce_product_id;
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php $optionId = (int) $option['productoption_id']; ?>
        <?php $defaultOptionValueId = $this->product->default_option_selections[$option['productoption_id']] ?? ''; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select') : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo $optionId; ?>]" class="form-select j2commerce-flexi-option-select" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $optionId; ?>" onchange="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $optionId; ?>')">
                    <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                    <?php foreach ($option['optionvalue'] as $ov) : ?>
                        <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                        <option value="<?php echo $ovId; ?>"<?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? ' selected' : ''; ?>>
                            <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio') : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
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
                               onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $optionId; ?>')"
                            <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                               autocomplete="off"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo $optionId; ?>" />

                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) { ?>
                                <label class="btn btn-image p-0 form-check-label border-2" for="option-value-<?php echo $ovId; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image me-1" src="<?php echo Uri::root(true) . '/' . $esc($ov['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                    <span class="visually-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                                </label>
                            <?php } else { ?>
                                <label class="btn btn-sm btn-outline-secondary form-check-label border-2" for="option-value-<?php echo $ovId; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                                </label>
                            <?php } ?>

                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color') : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-semibold pb-1 mb-2">
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
                            onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $optionId; ?>')"
                            <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                        />
                        <label for="option-value-<?php echo $ovId; ?>" class="btn btn-color fs-xl" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" style="color:<?php echo $esc($ov['optionvalue_image']); ?>;">
                            <span class="visually-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>
    <?php endforeach; ?>
</div>
