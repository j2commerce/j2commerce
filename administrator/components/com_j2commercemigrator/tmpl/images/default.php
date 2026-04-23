<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="j2cm-images">

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_SOURCE_HEADING'); ?></h2>
        </div>
        <div class="card-body">

            <div class="mb-3">
                <label for="j2cm-image-source" class="form-label"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_FIELD_SOURCE'); ?></label>
                <select class="form-select" id="j2cm-image-source" name="imageSource" data-j2cm-image-source>
                    <option value=""><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_FIELD_SOURCE_PLACEHOLDER'); ?></option>
                    <?php foreach ($this->imageDirectories as $dir) : ?>
                        <option value="<?php echo $this->escape($dir['path']); ?>"
                            <?php if (!empty($this->savedFolder['path']) && $this->savedFolder['path'] === $dir['path']) : ?>selected<?php endif; ?>>
                            <?php echo $this->escape($dir['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_FIELD_SOURCE_HELP'); ?></div>
            </div>

            <?php if (!empty($this->imageSettings)) : ?>
                <details class="mb-3">
                    <summary class="fw-semibold small text-muted mb-2"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_SETTINGS_SUMMARY'); ?></summary>
                    <div class="mt-2">
                        <?php foreach ($this->imageSettings as $key => $value) : ?>
                            <div class="row mb-1">
                                <div class="col-5 small text-muted"><?php echo $this->escape((string) $key); ?></div>
                                <div class="col-7 small"><?php echo $this->escape((string) $value); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="button"
                        class="btn btn-purple"
                        data-j2cm-action="startImageRebuild">
                    <span class="fa-solid fa-rotate me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_BTN_START'); ?>
                </button>
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-j2cm-action="copyImages">
                    <span class="fa-solid fa-copy me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_BTN_COPY'); ?>
                </button>
            </div>
        </div>
    </div>

    <div id="j2cm-image-progress-panel" class="card mb-4 d-none" aria-live="polite">
        <div class="card-body">
            <h3 class="h6 fw-semibold mb-2"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_PROGRESS_HEADING'); ?></h3>
            <div class="progress mb-2" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-purple"
                     id="j2cm-image-progress-bar"
                     style="width:0%">
                </div>
            </div>
            <p class="small text-muted mb-0" id="j2cm-image-progress-label"></p>
        </div>
    </div>

    <div id="j2cm-image-categories" class="card d-none" aria-live="polite">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_IMAGES_CATEGORIES_HEADING'); ?></h3>
            <span class="badge text-bg-secondary" id="j2cm-image-category-count">0</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_CATEGORY'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_TOTAL'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_PROCESSED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                    </tr>
                </thead>
                <tbody id="j2cm-image-category-list">
                </tbody>
            </table>
        </div>
    </div>

    <div id="j2cm-image-result" class="mt-3" role="status" aria-live="polite"></div>

    <div class="mt-3">
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
        </a>
    </div>
</div>
