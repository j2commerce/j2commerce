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

use J2Commerce\Component\J2commerce\Administrator\Controller\MultiimageuploaderController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

/** @var array $displayData */
$id       = $displayData['id'];
$name     = $displayData['name'];
$value    = $displayData['value'];
$options  = $displayData['options'];
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

$modalId     = $id . '-modal';
$hasImages   = !empty($value);
$totalImages = $hasImages ? count($value) : 0;
?>
<div id="<?php echo $id; ?>-wrapper"
     class="uppymedia-field<?php echo $hasImages ? ' has-images' : ''; ?>"
     data-options="<?php echo htmlspecialchars(json_encode($options), ENT_QUOTES, 'UTF-8'); ?>"
     <?php echo $attributes; ?>>

    <input type="hidden"
           name="<?php echo $name; ?>"
           id="<?php echo $id; ?>"
           value="<?php echo htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8'); ?>"
           <?php echo $required ? 'required' : ''; ?>>

    <!-- Bulk action bar — shown when checkboxes are checked -->
    <div class="uppymedia-bulk-bar">
        <span class="uppymedia-bulk-count"></span>
        <button type="button" class="btn btn-danger btn-sm uppymedia-bulk-remove">
            <span class="fa-solid fa-trash-can" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_REMOVE_SELECTED'); ?>
        </button>
    </div>

    <!-- Inline preview of selected images -->
    <div class="uppymedia-preview">
        <?php if ($hasImages): ?>
            <?php foreach ($value as $index => $file): ?>
                <div class="uppymedia-image <?php echo $index === 0 ? 'main-image' : ''; ?>" data-index="<?php echo $index; ?>">
                    <img src="<?php echo htmlspecialchars($file['thumb_url'] ?? $file['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($file['alt_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="uppymedia-select-check" title="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_IMAGE'); ?>">
                        <input type="checkbox" class="uppymedia-image-checkbox" data-index="<?php echo $index; ?>">
                        <span class="uppymedia-checkmark"></span>
                    </label>
                    <?php if ($totalImages > 1): ?>
                    <div class="uppymedia-move-arrows">
                        <?php if ($index > 0): ?>
                        <button type="button" class="uppymedia-move-btn" data-action="move-left" data-index="<?php echo $index; ?>" title="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_LEFT'); ?>">
                            <span class="fa-solid fa-chevron-left" aria-hidden="true"></span>
                        </button>
                        <?php endif; ?>
                        <?php if ($index < $totalImages - 1): ?>
                        <button type="button" class="uppymedia-move-btn" data-action="move-right" data-index="<?php echo $index; ?>" title="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_MOVE_RIGHT'); ?>">
                            <span class="fa-solid fa-chevron-right" aria-hidden="true"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <input type="text"
                           class="form-control form-control-sm mt-0"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT_PLACEHOLDER'); ?>"
                           value="<?php echo htmlspecialchars($file['alt_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           data-action="alt-text"
                           data-index="<?php echo $index; ?>"
                           aria-label="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ALT_TEXT'); ?>" />
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!$disabled && !$readonly): ?>
    <?php
    $isFileMode = !empty($options['fileMode']);

    if ($isFileMode) {
        $fileExtensions  = MultiimageuploaderController::FILE_ALLOWLIST;
        $extList         = array_values(array_unique(array_map('strtoupper', $fileExtensions)));
        sort($extList);
        if (\in_array('JPG', $extList, true) && \in_array('JPEG', $extList, true)) {
            $extList = array_values(array_diff($extList, ['JPEG']));
        }
        $acceptedFormats = implode(', ', $extList);
        $hintFormat      = 'COM_J2COMMERCE_MULTIIMAGEUPLOADER_ACCEPTED_FILE_FORMATS';
        $addLabelKey     = 'COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_FILES';
        $iconClass       = 'fa-solid fa-file-arrow-down';
    } else {
        $mediaParams     = ComponentHelper::getParams('com_media');
        $imageExtensions = $mediaParams->get('image_extensions', 'bmp,gif,jpg,png,jpeg,webp,avif');
        $extList         = array_map('strtoupper', array_unique(array_map('trim', explode(',', $imageExtensions))));
        if (\in_array('JPG', $extList) && \in_array('JPEG', $extList)) {
            $extList = array_values(array_diff($extList, ['JPEG']));
        }
        $acceptedFormats = implode(', ', $extList);
        $hintFormat      = 'COM_J2COMMERCE_MULTIIMAGEUPLOADER_ACCEPTED_FORMATS';
        $addLabelKey     = 'COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_PRODUCT_IMAGES';
        $iconClass       = 'fa-solid fa-images';
    }
    ?>
    <div class="uppymedia-empty-state" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>" role="button">
        <div class="uppymedia-empty-inner">
            <span class="btn btn-primary">
                <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                <?php echo Text::_($addLabelKey); ?>
            </span>
        </div>
        <p class="uppymedia-empty-hint"><?php echo Text::sprintf($hintFormat, $acceptedFormats); ?></p>
    </div>

    <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-primary uppymedia-add-btn" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
            <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_ADD_MEDIA'); ?>
        </button>
        <div class="form-check d-none">
            <input type="checkbox"
                   class="form-check-input uppymedia-auto-thumbnail"
                   id="<?php echo $id; ?>-auto-thumbnail"
                   <?php echo !empty($options['autoThumbnail']) ? 'checked' : ''; ?>>
            <label class="form-check-label" for="<?php echo $id; ?>-auto-thumbnail">
                <?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_AUTO_THUMBNAIL_CHECKBOX'); ?>
            </label>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal -->
    <div class="modal fade uppymedia-modal" id="<?php echo $modalId; ?>" tabindex="-1" aria-labelledby="<?php echo $modalId; ?>-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?php echo $modalId; ?>-label"><?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_SELECT_FILE'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
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
                                        <option value="<?php echo htmlspecialchars($options['directory'] ?? 'images/products', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($options['directory'] ?? 'images/products', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary uppymedia-create-folder" title="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_NEW_FOLDER'); ?>">
                                        <span class="fa-solid fa-folder-plus" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div class="uppymedia-filter-bar">
                                    <input type="text" class="form-control uppymedia-filter-input" placeholder="<?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_IMAGE_FILTER_PLACEHOLDER'); ?>" />
                                </div>
                            </div>
                        </div>
                        <!-- Loading indicator -->
                        <div class="uppymedia-browser-loading text-center d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                            </div>
                        </div>
                        <!-- Existing images browser -->
                        <div class="uppymedia-browser-grid"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                    <button type="button" class="btn btn-success uppymedia-done-btn"><?php echo Text::_('COM_J2COMMERCE_MULTIIMAGEUPLOADER_DONE'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
