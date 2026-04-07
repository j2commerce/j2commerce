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
$productHelper = J2CommerceHelper::product();
$platform = J2CommerceHelper::platform();
$showOptionImages = (int) ($params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$optionsSummary = $productHelper::getOptionsSummary($options);
?>
<div class="j2commerce-flexivariable-options uk-padding-small-top" id="variable-options-<?php echo $productId; ?>">
    <button class="uk-button uk-button-text uk-padding-remove j2commerce-configurable-button" type="button" uk-toggle="target: #collapseOptions<?php echo $productId; ?>" aria-expanded="false" aria-controls="collapseOptions<?php echo $productId; ?>">
        <?php echo $esc($optionsSummary); ?><span class="uk-margin-small-left fa-solid fa-chevron-down"></span>
    </button>
    <div class="uk-hidden uk-padding-small-top" id="collapseOptions<?php echo $productId; ?>">
        <?php foreach ($options as $option) : ?>
            <?php $defaultOptionValueId = $product->default_option_selections[$option['productoption_id']] ?? ''; ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

            <?php if ($option['type'] === 'select') : ?>
                <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                    <label class="uk-form-label">
                        <?php echo $esc(Text::_($option['option_name'])); ?>
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                    </label>
                    <select name="product_option[<?php echo $option['productoption_id']; ?>]" class="uk-select" onchange="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')">
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
                    <label class="uk-form-label">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                        <span id="radioOption<?php echo $option['productoption_id']; ?>"></span>
                    </label>
                    <div class="j2commerce-radio-options uk-flex uk-flex-wrap" style="gap: .5rem;" data-binded-label="#radioOption<?php echo $option['productoption_id']; ?>">
                        <?php foreach ($option['optionvalue'] as $ov) : ?>
                            <input type="radio"
                                   name="product_option[<?php echo $option['productoption_id']; ?>]"
                                   value="<?php echo $ov['product_optionvalue_id']; ?>"
                                   id="option-value-<?php echo $ov['product_optionvalue_id']; ?>"
                                   class="uk-hidden"
                                   onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')"
                                <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                                   autocomplete="off"
                                   data-product-id="<?php echo $productId; ?>"
                                   data-option-id="<?php echo $option['productoption_id']; ?>"
                                <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? ' checked' : ''; ?> />

                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) { ?>
                                <label class="btn-image uk-padding-remove" for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <img class="optionvalue-image uk-margin-small-right" src="<?php echo Uri::root(true) . '/' . $esc($ov['optionvalue_image']); ?>" alt="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" width="48" style="width:48px;" />
                                    <span class="uk-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                                </label>
                            <?php } else { ?>
                                <label class="uk-button uk-button-default uk-button-small" for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>">
                                    <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                                </label>
                            <?php } ?>

                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($option['type'] === 'color') : ?>
                <div id="option-<?php echo $option['productoption_id']; ?>" class="option uk-margin-small-bottom">
                    <label class="uk-form-label">
                        <?php echo $esc(Text::_($option['option_name'])); ?>:
                        <?php if ($option['required']) : ?>
                            <span class="uk-text-danger">*</span>
                        <?php endif; ?>
                        <span id="colorOption<?php echo $option['productoption_id']; ?>"></span>
                    </label>
                    <div class="j2commerce-color-options uk-flex uk-flex-wrap" style="gap: .5rem;" data-binded-label="#colorOption<?php echo $option['productoption_id']; ?>">
                        <?php foreach ($option['optionvalue'] as $ov) : ?>
                            <input
                                type="radio"
                                name="product_option[<?php echo $option['productoption_id']; ?>]"
                                value="<?php echo $ov['product_optionvalue_id']; ?>"
                                id="option-value-<?php echo $ov['product_optionvalue_id']; ?>"
                                class="uk-hidden"
                                autocomplete="off"
                                onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $option['productoption_id']; ?>')"
                                <?php echo ($defaultOptionValueId == $ov['product_optionvalue_id']) ? 'checked' : ''; ?>
                            />
                            <label for="option-value-<?php echo $ov['product_optionvalue_id']; ?>" class="btn-color" data-label="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" style="color:<?php echo $ov['optionvalue_image'];?>;">
                                <span class="uk-hidden"><?php echo $esc(Text::_($ov['optionvalue_name'])); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$product, $option])->getArgument('html', ''); ?>
        <?php endforeach; ?>
    </div>
</div>
