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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Hero Banner image upload widget for product option type=image.
 *
 * @var array $displayData
 * @var int     $displayData['productOptionId']
 * @var int     $displayData['productId']
 * @var bool    $displayData['required']
 * @var string  $displayData['optionName']
 * @var string  $displayData['ajaxUrl']
 * @var float   $displayData['maxSizeMB']
 * @var string  $displayData['allowedExts']     comma list (e.g. "jpg,png,webp")
 * @var string  $displayData['framework']       'bs5' (default) | 'uikit'
 */

// Hide the field for guests when allow_guest_uploads=0 — the upload would
// be rejected server-side by MediaHelper's user-group gate anyway, so the
// dropzone is misleading. Logged-in users always see it.
$isGuest = Factory::getApplication()->getIdentity()?->guest ?? true;
if ($isGuest && (int) ComponentHelper::getParams('com_j2commerce')->get('allow_guest_uploads', 0) !== 1) {
    return;
}

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
$hintText = $hintParts !== []
    ? Text::sprintf('COM_J2COMMERCE_UPLOAD_HINT_IMAGE', implode(' · ', $hintParts))
    : Text::_('COM_J2COMMERCE_UPLOAD_HINT_IMAGE_GENERIC');

$heroClass     = $framework === 'uikit' ? 'uk-img-hero' : 'j2c-img-hero';
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
        class="<?php echo $heroClass; ?>"
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
        <span class="ih-cta" data-icon-replace="fa-solid fa-arrows-rotate">
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
