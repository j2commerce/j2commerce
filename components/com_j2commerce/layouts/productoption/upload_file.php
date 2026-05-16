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

/**
 * Drag & Drop file upload widget for product option type=file.
 *
 * @var array $displayData
 * @var int     $displayData['productOptionId']
 * @var int     $displayData['productId']
 * @var bool    $displayData['required']
 * @var string  $displayData['optionName']
 * @var string  $displayData['ajaxUrl']         POST endpoint that returns JSON {success, code, error}
 * @var float   $displayData['maxSizeMB']       from com_media upload_maxsize
 * @var string  $displayData['allowedExts']     comma list, lowercase, no dots (e.g. "pdf,doc,zip")
 * @var string  $displayData['framework']       'bs5' (default) | 'uikit'
 */

$optionId    = (int) ($displayData['productOptionId'] ?? 0);
$productId   = (int) ($displayData['productId'] ?? 0);
$required    = (bool) ($displayData['required'] ?? false);
$optionName  = (string) ($displayData['optionName'] ?? '');
$ajaxUrl     = (string) ($displayData['ajaxUrl'] ?? '');
$maxSizeMB   = (float) ($displayData['maxSizeMB'] ?? 0);
$allowedExts = (string) ($displayData['allowedExts'] ?? '');
$framework   = ($displayData['framework'] ?? 'bs5') === 'uikit' ? 'uikit' : 'bs5';

$inputId  = 'j2c-upload-input-' . $optionId;
$hiddenId = 'input-option' . $optionId;
$esc      = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$hintParts = [];
if ($allowedExts !== '') {
    $hintParts[] = strtoupper(str_replace(',', ', ', $allowedExts));
}
if ($maxSizeMB > 0) {
    $hintParts[] = Text::sprintf('COM_J2COMMERCE_UPLOAD_HINT_MAX_SIZE', rtrim(rtrim(number_format($maxSizeMB, 2, '.', ''), '0'), '.'));
}
$hintText = implode(' · ', $hintParts);

$dropzoneClass = $framework === 'uikit' ? 'uk-upl-dropzone' : 'j2c-dropzone';
$labelClass    = $framework === 'uikit' ? 'uk-form-label uk-text-bold d-block mb-2' : 'form-label fw-bold d-block mb-2';
$requiredClass = $framework === 'uikit' ? 'uk-text-danger' : 'text-danger';
$wrapperClass  = $framework === 'uikit' ? 'option uk-margin-small-bottom' : 'option mb-3';
?>
<div id="option-<?php echo $optionId; ?>" class="<?php echo $wrapperClass; ?>">
    <span class="<?php echo $labelClass; ?>">
        <?php echo $esc(Text::_($optionName)); ?><?php if ($required) : ?>
            <span class="<?php echo $requiredClass; ?>" aria-hidden="true">*</span>
            <span class="visually-hidden"><?php echo Text::_('JFIELD_FIELD_REQUIRED_LABEL'); ?></span>
        <?php endif; ?>
    </span>
    <label
        class="<?php echo $dropzoneClass; ?>"
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
