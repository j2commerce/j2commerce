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
use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;

/** @var array $displayData */
$id       = $displayData['id'];
$name     = $displayData['name'];
$value    = $displayData['value'];
$options  = $displayData['options'];
$multiple = $displayData['multiple'];
$max      = $displayData['max'];
$required = $displayData['required'];
$disabled = $displayData['disabled'];
$readonly = $displayData['readonly'];

$attributes = '';
if ($disabled) {
    $attributes .= ' disabled';
}
if ($readonly) {
    $attributes .= ' readonly';
}

$modalId         = $id . '-imgpicker-modal';
$thumbnailHeight = (int) ($options['thumbnailHeight'] ?? 80);
$previewStyle    = $options['previewStyle'] ?? 'square';

// For hidden input: single = string, multi = JSON array
if ($multiple) {
    $hiddenValue = !empty($value) ? json_encode($value) : '[]';
    $images      = (array) $value;
} else {
    $hiddenValue = (string) $value;
    $images      = !empty($value) ? [(string) $value] : [];
}
?>
<div id="<?php echo $id; ?>-wrapper"
     class="j2commerce-image-field"
     data-options="<?php echo htmlspecialchars(json_encode($options), ENT_QUOTES, 'UTF-8'); ?>"
     data-multiple="<?php echo $multiple ? 'true' : 'false'; ?>"
     data-max="<?php echo $max; ?>"
     data-site-root="<?php echo htmlspecialchars(Uri::root(), ENT_QUOTES, 'UTF-8'); ?>"
     style="--j2img-thumb-size: <?php echo $thumbnailHeight; ?>px;"
     <?php echo $attributes; ?>>

    <input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>" value="<?php echo htmlspecialchars($hiddenValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $required ? 'required' : ''; ?>>

    <div class="j2commerce-image-thumbs">
        <?php foreach ($images as $imagePath) :
            if (empty($imagePath)) {
                continue;
            }
            $thumbUrl = ImageHelper::getProductImage($imagePath, $thumbnailHeight, 'raw');
            ?>
            <div class="j2commerce-image-thumb <?php echo $previewStyle === 'contain' ? 'preview-contain' : ''; ?>" data-path="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?php echo htmlspecialchars($thumbUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" class="">
                <?php if (!$disabled && !$readonly) : ?>
                <button type="button" class="j2commerce-image-remove" aria-label="<?php echo Text::_('JACTION_DELETE'); ?>">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$disabled && !$readonly) : ?>
    <button type="button" class="btn btn-sm btn-outline-secondary j2commerce-image-choose-btn" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
        <i class="fa-solid fa-image" aria-hidden="true"></i>
        <?php echo Text::_($multiple ? 'COM_J2COMMERCE_IMAGE_CHOOSE_MULTIPLE' : 'COM_J2COMMERCE_IMAGE_CHOOSE'); ?>
    </button>

    <!-- Image Picker Modal -->
    <div class="modal fade uppymedia-modal" id="<?php echo $modalId; ?>" tabindex="-1"
         aria-labelledby="<?php echo $modalId; ?>-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?php echo $modalId; ?>-label">
                        <?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_FILE'); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-body_inner p-3">
                        <!-- Uppy upload zone -->
                        <div class="uppymedia-dashboard uppymedia-upload-zone mb-3"></div>

                        <hr>
                        <div class="uppymedia-folder-bar mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select uppymedia-folder-select" style="max-width:300px;">
                                        <option value="<?php echo htmlspecialchars($options['directory'] ?? 'images', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($options['directory'] ?? 'images', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary uppymedia-create-folder"
                                            title="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER'); ?>">
                                        <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="uppymedia-filter-bar">
                                    <input type="text" class="form-control uppymedia-filter-input"
                                           placeholder="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_IMAGE_FILTER_PLACEHOLDER'); ?>" />
                                </div>
                            </div>
                        </div>
                        <!-- Loading indicator -->
                        <div class="uppymedia-browser-loading text-center d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                            </div>
                        </div>
                        <!-- Image browser grid -->
                        <div class="uppymedia-browser-grid"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                        <?php echo Text::_('JCANCEL'); ?>
                    </button>
                    <button type="button" class="btn btn-success uppymedia-done-btn">
                        <?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DONE'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
