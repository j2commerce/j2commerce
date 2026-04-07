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

$productId = (int) $product->j2commerce_product_id;
$showOptionImages = (int) ($params->get('image_for_product_options', 0) ?? 0);
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="j2commerce-flexivariable-options" id="variable-options-<?php echo $productId; ?>">
    <?php foreach ($options as $option) : ?>
        <?php $optionId = (int) $option['productoption_id']; ?>
        <?php $defaultOptionValueId = $product->default_option_selections[$optionId] ?? ''; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeDisplaySingleProductOption', [$product, &$option])->getArgument('html', ''); ?>

        <?php if ($option['type'] === 'select') : ?>
            <div id="option-<?php echo $optionId; ?>" class="option mb-3">
                <label class="form-label fw-bold">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <select name="product_option[<?php echo $optionId; ?>]"
                        class="form-select"
                        onchange="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $optionId; ?>')">
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
                <div class="fw-bold mb-1">
                    <?php echo $esc(Text::_($option['option_name'])); ?>
                    <?php if ($option['required']) : ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </div>
                <?php foreach ($option['optionvalue'] as $ov) : ?>
                    <?php $ovId = (int) $ov['product_optionvalue_id']; ?>
                    <div class="form-check">
                        <input type="radio"
                               name="product_option[<?php echo $optionId; ?>]"
                               value="<?php echo $ovId; ?>"
                               id="option-value-<?php echo $ovId; ?>"
                               class="form-check-input"
                               autocomplete="off"
                               onclick="doFlexiAjaxPrice(<?php echo $productId; ?>, '#option-<?php echo $optionId; ?>')"
                               <?php echo ($defaultOptionValueId == $ovId) ? ' checked' : ''; ?> />
                        <label class="form-check-label" for="option-value-<?php echo $ovId; ?>">
                            <?php if ($showOptionImages && !empty($ov['optionvalue_image'])) : ?>
                                <img class="optionvalue-image me-1"
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
