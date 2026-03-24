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
            <button type="button" class="btn btn-outline-secondary active" data-device="desktop" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_DESKTOP'); ?>">
                <span class="fa-solid fa-desktop" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-device="tablet" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_TABLET'); ?>">
                <span class="fa-solid fa-tablet-screen-button" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-device="mobile" title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_DEVICE_MOBILE'); ?>">
                <span class="fa-solid fa-mobile-screen-button" aria-hidden="true"></span>
            </button>
        </div>

        <!-- Undo/Redo -->
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary" id="builder-undo" disabled title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_UNDO'); ?>">
                <span class="fa-solid fa-rotate-left" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="builder-redo" disabled title="<?php echo Text::_('COM_J2COMMERCE_BUILDER_REDO'); ?>">
                <span class="fa-solid fa-rotate-right" aria-hidden="true"></span>
            </button>
        </div>

        <!-- Save Button -->
        <button type="button" class="btn btn-primary btn-sm" id="builder-save" disabled>
            <span class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_BUILDER_SAVE'); ?>
        </button>
    </div>

    <!-- Builder Content Area -->
    <div class="row">
        <!-- Sidebar: Block Palette + Properties -->
        <div class="col-lg-3">
            <div class="card-builder card mb-3 box-shadow-none">
                <div class="card-header p-3">
                    <h3 class="mb-0 fs-4"><span class="fa-solid fa-cubes me-2 text-warning" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_BUILDER_BLOCKS'); ?></h3>
                </div>
                <div class="card-body bg-white" id="builder-blocks-panel">
                    <div class="small text-center py-3">
                        <?php echo Text::_('COM_J2COMMERCE_BUILDER_SELECT_FILE'); ?>
                    </div>
                </div>
            </div>
            <div class="card-builder card mb-3 box-shadow-none">
                <div class="card-header p-3">
                    <h3 class="mb-0 fs-4"><span class="fa-solid fa-sliders me-2 text-warning" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_BUILDER_PROPERTIES'); ?></h3>
                </div>
                <div class="card-body bg-white" id="builder-properties-panel">
                    <!-- Properties panel populated by JS -->
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="col-lg-9">
            <div class="card box-shadow-none rounded-1">
                <div class="card-body p-0">
                    <div id="builder-canvas" class="j2commerce-builder-canvas" style="min-height: 500px;">
                        <div class="d-flex align-items-center justify-content-center h-100 align-self-stretch" id="builder-placeholder">
                            <div class="text-center">
                                <span class="fa-solid fa-wand-magic-sparkles text-warning fa-3x mb-3" aria-hidden="true"></span>
                                <p><?php echo Text::_('COM_J2COMMERCE_BUILDER_SELECT_FILE'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
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
]);
?>
