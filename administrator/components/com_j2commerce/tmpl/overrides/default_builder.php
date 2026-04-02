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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$previewProducts = $this->builderPreviewProducts ?? [];
$token = Session::getFormToken();
?>
<div id="j2commerce-builder-container" class="j2commerce-builder" data-token="<?php echo $token; ?>">
    <!-- Builder Toolbar -->
    <div class="j2commerce-builder-toolbar d-flex align-items-center gap-2 p-3 bg-light rounded-1 mb-3">
        <!-- File Selector -->
        <div class="builder-file-selector flex-grow-1">
            <select id="builder-file-select" class="form-select">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_BUILDER_SELECT_FILE'); ?></option>
            </select>
        </div>

        <!-- Product Selector -->
        <div class="builder-product-selector">
            <select id="builder-product-select" class="form-select" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_SELECT_PRODUCT_DESC'); ?>">
                <?php foreach ($previewProducts as $product): ?>
                    <option value="<?php echo (int) $product->id; ?>">
                        <?php echo htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Device Buttons -->
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-secondary active" data-device="desktop" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_DESKTOP'); ?>">
                <span class="fa-solid fa-desktop" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-device="tablet" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_TABLET'); ?>">
                <span class="fa-solid fa-tablet-screen-button" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-device="mobile" data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_MOBILE'); ?>">
                <span class="fa-solid fa-mobile-screen-button" aria-hidden="true"></span>
            </button>
        </div>

        <!-- Undo/Redo -->
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary" id="builder-undo" disabled data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_UNDO'); ?>">
                <span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="builder-redo" disabled data-bs-toggle="tooltip" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_REDO'); ?>">
                <span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
            </button>
        </div>

        <!-- Reset to Default Button (hidden until a customized file is selected) -->
        <button type="button" class="btn btn-outline-danger btn-sm d-none" id="builder-reset">
            <span class="fa-solid fa-rotate-left me-1"></span>
            <?php echo Text::_('COM_J2COMMERCE_BUILDER_RESET_DEFAULT'); ?>
        </button>

        <!-- Save Button -->
        <button type="button" class="btn btn-primary btn-sm" id="builder-save" disabled>
            <span class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></span>
            <span id="builder-save-label"><?php echo Text::_('COM_J2COMMERCE_BUILDER_SAVE'); ?></span>
        </button>
    </div>

    <!-- Status Bar -->
    <div id="builder-status-bar" class="builder-status-bar d-none" role="status" aria-live="polite"></div>

    <!-- Builder Content Area -->
    <div class="row">
        <!-- Sidebar: Blocks | Properties | Styles tabs -->
        <div class="col-lg-3">
            <div class="card-builder card box-shadow-none">
                <?php echo HTMLHelper::_('uitab.startTabSet', 'builderSidebarTabs', ['active' => 'builder-blocks', 'recall' => false, 'breakpoint' => 768]); ?>

                    <?php echo HTMLHelper::_('uitab.addTab', 'builderSidebarTabs', 'builder-blocks', '<span class="fa-solid fa-cubes me-1" aria-hidden="true"></span>' . Text::_('COM_J2COMMERCE_BUILDER_BLOCKS')); ?>
                        <!-- Templates button -->
                        <div class="mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="builder-templates-btn">
                                <span class="fa-solid fa-layer-group me-1" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_BUILDER_TEMPLATES'); ?>
                            </button>
                        </div>
                        <div id="builder-blocks-list">
                            <div class="small text-center py-3">
                                <?php echo Text::_('COM_J2COMMERCE_BUILDER_SELECT_FILE'); ?>
                            </div>
                        </div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>

                    <?php echo HTMLHelper::_('uitab.addTab', 'builderSidebarTabs', 'builder-properties', '<span class="fa-solid fa-sliders me-1" aria-hidden="true"></span>' . Text::_('COM_J2COMMERCE_BUILDER_PROPERTIES')); ?>
                        <div id="builder-properties-panel">
                            <!-- Properties panel populated by JS -->
                        </div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>

                    <?php echo HTMLHelper::_('uitab.addTab', 'builderSidebarTabs', 'builder-styles', '<span class="fa-solid fa-palette me-1" aria-hidden="true"></span>' . Text::_('COM_J2COMMERCE_BUILDER_TAB_STYLES')); ?>
                        <div id="builder-styles-panel">
                            <!-- GrapeJS Style Manager renders here via appendTo -->
                        </div>
                    <?php echo HTMLHelper::_('uitab.endTab'); ?>

                <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="col-lg-9">
            <div class="card box-shadow-none rounded-1">
                <div class="card-body p-0">
                    <div id="builder-canvas" class="j2commerce-builder-canvas">
                        <div class="d-flex align-items-center justify-content-center h-100 align-self-stretch" id="builder-placeholder">
                            <div class="text-center builder-placeholder-content">
                                <span class="fa-solid fa-wand-magic-sparkles text-warning fa-3x mb-3" aria-hidden="true"></span>
                                <h4 class="mt-3"><?php echo Text::_('COM_J2COMMERCE_BUILDER_PLACEHOLDER_TITLE'); ?></h4>
                                <p class="text-muted"><?php echo Text::_('COM_J2COMMERCE_BUILDER_PLACEHOLDER_TEXT'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Presets Modal -->
<div class="modal fade" id="builder-templates-modal" tabindex="-1" aria-labelledby="builderTemplatesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="builderTemplatesModalLabel">
                    <span class="fa-solid fa-layer-group me-2 text-warning"></span><?php echo Text::_('COM_J2COMMERCE_BUILDER_TEMPLATES_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted"><?php echo Text::_('COM_J2COMMERCE_BUILDER_TEMPLATES_DESC'); ?></p>
                <div id="builder-presets-grid" class="row g-3">
                    <!-- Preset cards populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php
$this->getDocument()->addScriptOptions('com_j2commerce.builder', [
    'token'           => $token,
    'baseUrl'         => Uri::base(),
    'ajaxUrl'         => Uri::base() . 'index.php?option=com_j2commerce&format=json&task=builder.',
    'previewProducts' => $previewProducts,
    'messages'        => [
        'statusDefault'     => Text::_('COM_J2COMMERCE_BUILDER_STATUS_DEFAULT'),
        'statusCustomized'  => Text::_('COM_J2COMMERCE_BUILDER_STATUS_CUSTOMIZED'),
        'saveCreate'        => Text::_('COM_J2COMMERCE_BUILDER_SAVE_CREATE'),
        'saveUpdate'        => Text::_('COM_J2COMMERCE_BUILDER_SAVE_UPDATE'),
        'resetConfirm'      => Text::_('COM_J2COMMERCE_BUILDER_RESET_CONFIRM'),
        'badgeDefault'      => Text::_('COM_J2COMMERCE_BUILDER_BADGE_DEFAULT'),
        'badgeCustomized'   => Text::_('COM_J2COMMERCE_BUILDER_BADGE_CUSTOMIZED'),
        'savingLabel'          => Text::_('COM_J2COMMERCE_BUILDER_SAVING'),
        'presetApplied'        => Text::_('COM_J2COMMERCE_BUILDER_PRESET_APPLIED'),
        'selectElement'        => Text::_('COM_J2COMMERCE_BUILDER_SELECT_ELEMENT'),
        'noPresets'            => Text::_('COM_J2COMMERCE_BUILDER_NO_PRESETS'),
        'modeComposition'      => Text::_('COM_J2COMMERCE_BUILDER_MODE_COMPOSITION'),
        'modeSublayout'        => Text::_('COM_J2COMMERCE_BUILDER_MODE_SUBLAYOUT'),
        'sublayoutLoading'     => Text::_('COM_J2COMMERCE_BUILDER_SUBLAYOUT_LOADING'),
        'sublayoutSaveError'   => Text::_('COM_J2COMMERCE_BUILDER_SUBLAYOUT_SAVE_ERROR'),
        'backToComposition'    => Text::_('COM_J2COMMERCE_BUILDER_BACK_TO_COMPOSITION'),
        'insertElement'        => Text::_('COM_J2COMMERCE_BUILDER_INSERT_ELEMENT'),
        'tokenLocked'          => Text::_('COM_J2COMMERCE_BUILDER_TOKEN_LOCKED'),
        'conditionalLocked'    => Text::_('COM_J2COMMERCE_BUILDER_CONDITIONAL_LOCKED'),
        'editHtml'             => Text::_('COM_J2COMMERCE_BUILDER_EDIT_HTML'),
        'editHtmlDesc'         => Text::_('COM_J2COMMERCE_BUILDER_EDIT_HTML_DESC'),
        'unsavedChanges'       => Text::_('COM_J2COMMERCE_BUILDER_UNSAVED_CHANGES'),
    ],
]);

HTMLHelper::_('bootstrap.tooltip', '.j2commerce-builder [data-bs-toggle="tooltip"]');
?>
