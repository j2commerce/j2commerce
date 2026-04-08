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
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="j2commerce-flexivariable-options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php $optionId = (int) $option['productoption_id']; ?>
        <?php $defaultOptionValueId = $product->default_option_selections[$option['productoption_id']] ?? ''; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select') : ?>
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
                        onchange="doFlexiAjaxPrice(<?php echo (int) $productId; ?>, '#option-<?php echo $optionId; ?>')">
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
            <div id="option-<?php echo $optionId; ?>" class="option uk-margin-small-bottom">
                <div class="uk-form-label uk-text-bold uk-margin-small-bottom">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="uk-text-danger">*</span>
                    <?php endif; ?>
                </div>
                <?php foreach ($option['optionvalue'] as $ov) : ?>
                    <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                    <?php $optionValueInputId = 'option-value-' . (int) $productId . '-' . $optionId . '-' . $ovId; ?>
                    <div>
                        <input type="radio"
                               name="product_option[<?php echo $optionId; ?>]"
                               value="<?php echo $ovId; ?>"
                               id="<?php echo $optionValueInputId; ?>"
                               class="uk-radio"
                               autocomplete="off"
                               onclick="doFlexiAjaxPrice(<?php echo (int) $productId; ?>, '#option-<?php echo $optionId; ?>')"
                               <?php echo ($defaultOptionValueId == $ovId) ? ' checked' : ''; ?> />
                        <label for="<?php echo $optionValueInputId; ?>">
                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) : ?>
                                <img class="optionvalue-image uk-margin-small-right"
                                     src="<?php echo Uri::root(true) . '/' . $esc($ov['optionvalue_image']); ?>"
                                     alt="<?php echo $esc(Text::_($ov['optionvalue_name'])); ?>" />
                            <?php endif; ?>
                            <?php echo $esc(Text::_($ov['optionvalue_name'])); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplaySingleProductOption', [$product, $option])->getArgument('html', ''); ?>
    <?php endforeach; ?>
</div>
