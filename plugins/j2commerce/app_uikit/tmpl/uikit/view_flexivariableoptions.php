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

$productId = $this->product->j2commerce_product_id;
$showOptionImages = (int) ($this->params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php $defaultOptionValueId = $this->product->default_option_selections[$option['productoption_id']] ?? ''; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$this->product, &$option, $this->context])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select class="uk-select j2commerce-flexi-option-select" name="product_option[<?php echo $option['productoption_id']; ?>]" data-product-id="<?php echo $productId; ?>" data-option-id="<?php echo $option['productoption_id']; ?>" onchange="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')">
                    <option value="*"><?php echo $esc(Text::_('COM_J2COMMERCE_CHOOSE')); ?></option>
                    <?php foreach ($option['optionvalue'] as $ov) : ?>
                        <option value="<?php echo $ov['product_optionvalue_id']; ?>"<?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? ' selected' : ''; ?>>
                            <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'radio') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal" id="radioOption<?php echo $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#radioOption<?php echo $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $ov) : ?>
                        <input type="radio"
                               name="product_option[<?php echo $option['productoption_id']; ?>]"
                               value="<?php echo $ov['product_optionvalue_id']; ?>"
                               id="option-value-<?php echo $ov['product_optionvalue_id']; ?>"
                               class="uk-radio uk-hidden"
                               onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')"
                            <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                               autocomplete="off"
                               data-product-id="<?php echo $productId; ?>"
                               data-option-id="<?php echo $option['productoption_id']; ?>" />

                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) { ?>
                                <label class="btn-image" for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image" src="<?php echo Uri::root(true) . '/' . $esc($ov['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" width="56" style="width:56px;" />
                                    <span class="uk-invisible"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                                </label>
                            <?php } else { ?>
                                <label class="uk-button uk-button-small uk-button-default" for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                                </label>
                            <?php } ?>

                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($option['type'] === 'color') : ?>
            <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                <label class="uk-form-label uk-text-bold uk-display-block">
                    <?php echo $esc(Text::_($option['option_name'])); ?>:
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                    <span class="uk-text-normal" id="colorOption<?php echo $option['productoption_id']; ?>"></span>
                </label>
                <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap:.5rem;" data-binded-label="#colorOption<?php echo $option['productoption_id']; ?>">
                    <?php foreach ($option['optionvalue'] as $ov) : ?>
                        <input
                            type="radio"
                            name="product_option[<?php echo $option['productoption_id']; ?>]"
                            value="<?php echo $ov['product_optionvalue_id']; ?>"
                            id="option-value-<?php echo $ov['product_optionvalue_id']; ?>"
                            class="uk-radio uk-hidden"
                            autocomplete="off"
                            onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')"
                            <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                        />
                        <label for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" class="btn-color" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" style="color:<?php echo $ov['optionvalue_image']; ?>;">
                            <span class="uk-invisible"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$this->product, $option, $this->context])->getArgument('html', ''); ?>
    <?php endforeach; ?>
</div>
