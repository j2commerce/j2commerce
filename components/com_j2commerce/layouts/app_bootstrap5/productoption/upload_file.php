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
        class="j2c-dropzone"
        for="<?php echo $inputId; ?>"
        data-j2c-dropzone
        data-ajax-url="<?php echo $esc($ajaxUrl); ?>"
        data-hidden-id="<?php echo $esc($hiddenId); ?>"
        data-maxsize-mb="<?php echo $esc((string) $maxSizeMB); ?>"
        data-allowed-exts="<?php echo $esc($allowedExts); ?>">
        <span class="dz-icon">
            <span class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></span>
        </span>
        <span class="dz-title">
            <?php echo Text::_('COM_J2COMMERCE_UPLOAD_DROP_FILE_OR'); ?>
            <span class="browse"><?php echo Text::_('COM_J2COMMERCE_UPLOAD_BROWSE'); ?></span>
        </span>
        <?php if ($hintText !== '') : ?>
            <span class="dz-hint"><?php echo $esc($hintText); ?></span>
        <?php endif; ?>
        <input
            id="<?php echo $inputId; ?>"
            type="file"
            class="j2c-upload-native"
            <?php if ($allowedExts !== '') : ?>accept=".<?php echo $esc(str_replace(',', ',.', $allowedExts)); ?>"<?php endif; ?>
            <?php if ($required) : ?>aria-required="true"<?php endif; ?> />
    </label>
    <input
        type="hidden"
        name="product_option[<?php echo $optionId; ?>]"
        value=""
        id="<?php echo $esc($hiddenId); ?>" />
</div>
