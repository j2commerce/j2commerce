<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$optionId    = (int) ($displayData['productOptionId'] ?? 0);
$required    = (bool) ($displayData['required'] ?? false);
$optionName  = (string) ($displayData['optionName'] ?? '');
$ajaxUrl     = (string) ($displayData['ajaxUrl'] ?? '');
$maxSizeMB   = (float) ($displayData['maxSizeMB'] ?? 0);
$allowedExts = (string) ($displayData['allowedExts'] ?? '');
$inputId     = (string) ($displayData['inputId'] ?? 'j2c-upload-input-' . $optionId);
$hiddenId    = (string) ($displayData['hiddenId'] ?? 'input-option' . $optionId);
$hintText    = (string) ($displayData['hintText'] ?? '');
$esc         = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div id="option-<?php echo $optionId; ?>" class="option mb-3">
    <span class="form-label fw-bold d-block mb-2">
        <?php echo $esc(Text::_($optionName)); ?><?php if ($required) : ?>
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="visually-hidden"><?php echo Text::_('JFIELD_FIELD_REQUIRED_LABEL'); ?></span>
        <?php endif; ?>
    </span>
    <label
        class="j2c-img-hero"
        for="<?php echo $inputId; ?>"
        data-j2c-image-hero
        data-ajax-url="<?php echo $esc($ajaxUrl); ?>"
        data-hidden-id="<?php echo $esc($hiddenId); ?>"
        data-maxsize-mb="<?php echo $esc((string) $maxSizeMB); ?>"
        data-allowed-exts="<?php echo $esc($allowedExts); ?>">
        <span class="ih-thumb">
            <span class="j2c-thumb-icon fa-solid fa-image" aria-hidden="true"></span>
        </span>
        <span class="ih-body">
            <span class="ih-title"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_ADD_PHOTO'); ?></span>
            <span class="ih-hint"><?php echo $esc($hintText); ?></span>
        </span>
        <span class="ih-cta" data-icon-replace="fa-solid fa-arrows-rotate me-1">
            <span class="fa-solid fa-upload me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_PRODUCT_OPTION_CHOOSE_IMAGE'); ?>
        </span>
        <input
            id="<?php echo $inputId; ?>"
            type="file"
            class="j2c-upload-native"
            accept="image/*"
            <?php if ($required) : ?>aria-required="true"<?php endif; ?> />
    </label>
    <input
        type="hidden"
        name="product_option[<?php echo $optionId; ?>]"
        value=""
        id="<?php echo $esc($hiddenId); ?>" />
</div>
