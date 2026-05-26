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

use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
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

// Hide the field for guests when allow_guest_uploads=0 — the upload would
// be rejected server-side by MediaHelper's user-group gate anyway, so the
// dropzone is misleading. Logged-in users always see it.
$isGuest = Factory::getApplication()->getIdentity()?->guest ?? true;
if ($isGuest && (int) ComponentHelper::getParams('com_j2commerce')->get('allow_guest_uploads', 0) !== 1) {
    return;
}

$optionId    = (int) ($displayData['productOptionId'] ?? 0);
$maxSizeMB   = (float) ($displayData['maxSizeMB'] ?? 0);
$allowedExts = (string) ($displayData['allowedExts'] ?? '');
$framework   = ($displayData['framework'] ?? 'bs5') === 'uikit' ? 'uikit' : 'bootstrap5';

$hintParts = [];
if ($allowedExts !== '') {
    $hintParts[] = strtoupper(str_replace(',', ', ', $allowedExts));
}
if ($maxSizeMB > 0) {
    $hintParts[] = Text::sprintf('COM_J2COMMERCE_UPLOAD_HINT_MAX_SIZE', rtrim(rtrim(number_format($maxSizeMB, 2, '.', ''), '0'), '.'));
}
$hintText = implode(' · ', $hintParts);

$displayData['inputId']  = 'j2c-upload-input-' . $optionId;
$displayData['hiddenId'] = 'input-option' . $optionId;
$displayData['hintText'] = $hintText;

ProductLayoutService::setSubtemplateOverride($framework);
try {
    echo ProductLayoutService::renderLayout('productoption.upload_file', $displayData);
} finally {
    ProductLayoutService::clearSubtemplateOverride();
}
