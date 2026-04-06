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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Dashboard\HtmlView $this */

$app       = Factory::getApplication();
$siteUrl   = rtrim(Uri::root(), '/');
$sef       = (bool) $app->get('sef', true);
$sefRewrite = (bool) $app->get('sef_rewrite', false);
?>

<div class="modal fade" id="j2commerceCategoryWizardModal" tabindex="-1"
     aria-labelledby="categoryWizardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header border-0 p-3">
                <h5 class="modal-title" id="categoryWizardModalLabel">
                    <span class="fa-solid fa-wand-magic-sparkles me-2" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCE_WIZARD_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">

                <!-- Error alert (hidden by default) -->
                <div class="alert alert-danger d-none" id="j2c-wizard-error" role="alert"></div>

                <!-- Step 1: Product count -->
                <div class="j2c-wizard-step" data-step="step1">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_TITLE'); ?></h5>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_count" value="1" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_1'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_1_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_count" value="2-10" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_2_10'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_2_10_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_count" value="11-50" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_11_50'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_11_50_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_count" value="50+" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_50_PLUS'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_STEP1_OPT_50_PLUS_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 2a: Single product name -->
                <div class="j2c-wizard-step d-none" data-step="2a">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_NAME'); ?></h5>
                    <div class="mb-3">
                        <input type="text" class="form-control form-control-lg" id="j2c-product-name"
                               name="product_name"
                               placeholder="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_NAME_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
                               autocomplete="off">
                    </div>
                </div>

                <!-- Step 3a: Product type -->
                <div class="j2c-wizard-step d-none" data-step="3a">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE'); ?></h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_type" value="downloadable" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="d-flex align-items-center">
                                        <span class="fa-solid fa-download fa-lg me-3 text-primary" aria-hidden="true"></span>
                                        <div>
                                            <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_DOWNLOADABLE'); ?></div>
                                            <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_DOWNLOADABLE_DESC'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_type" value="simple" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="d-flex align-items-center">
                                        <span class="fa-solid fa-box fa-lg me-3 text-success" aria-hidden="true"></span>
                                        <div>
                                            <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_SIMPLE'); ?></div>
                                            <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_SIMPLE_DESC'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="product_type" value="variable" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="d-flex align-items-center">
                                        <span class="fa-solid fa-layer-group fa-lg me-3 text-warning" aria-hidden="true"></span>
                                        <div>
                                            <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_VARIABLE'); ?></div>
                                            <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_PRODUCT_TYPE_VARIABLE_DESC'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 3b: Define option titles -->
                <div class="j2c-wizard-step d-none" data-step="3b">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_OPTIONS_TITLE'); ?></h5>
                    <div id="j2c-option-titles-list">
                        <div class="j2c-option-title-row mb-3 d-flex gap-2 align-items-center">
                            <input type="text" class="form-control j2c-option-title-input"
                                   placeholder="<?php echo htmlspecialchars(Text::_('COM_J2COMMERCE_WIZARD_OPTION_TITLE_PLACEHOLDER'), ENT_QUOTES, 'UTF-8'); ?>"
                                   aria-label="<?php echo htmlspecialchars(Text::sprintf('COM_J2COMMERCE_WIZARD_OPTION_TITLE_LABEL', 1), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="j2c-add-option-title">
                        <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_ADD_OPTION'); ?>
                    </button>
                </div>

                <!-- Step 3c: Add option values -->
                <div class="j2c-wizard-step d-none" data-step="3c">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_OPTION_VALUES_TITLE'); ?></h5>
                    <div id="j2c-option-values-container">
                        <!-- Populated dynamically by JS based on option titles -->
                    </div>
                </div>

                <!-- Step 2b: Category count (multi) -->
                <div class="j2c-wizard-step d-none" data-step="2b">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_COUNT'); ?></h5>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="category_count" value="1" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_1'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_1_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="category_count" value="2-5" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_2_5'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="category_count" value="6-10" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_6_10'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="category_count" value="10+" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_10_PLUS'); ?></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 3b-multi: Display settings (>1 category) -->
                <div class="j2c-wizard-step d-none" data-step="3b-multi">
                    <h5 class="mb-4"><?php echo Text::_('COM_J2COMMERCE_WIZARD_DISPLAY_SETTINGS'); ?></h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="menu_type" value="categories" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_DISPLAY_SAME'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_DISPLAY_SAME_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="menu_type" value="products" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_DISPLAY_DIFFERENT'); ?></div>
                                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_WIZARD_DISPLAY_DIFFERENT_DESC'); ?></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step: Category naming -->
                <div class="j2c-wizard-step d-none" data-step="category-naming">
                    <h5 class="mb-4 j2c-cat-naming-title"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CATEGORY_NAME'); ?></h5>
                    <div id="j2c-category-names-container">
                        <!-- Populated by JS based on category count selection -->
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="j2c-add-category">
                        <span class="fa-solid fa-plus me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_ADD_CATEGORY'); ?>
                    </button>
                </div>

                <!-- Step: Template detection (shown only if YOOtheme + UIkit available) -->
                <div class="j2c-wizard-step d-none" data-step="template">
                    <div class="alert alert-info">
                        <span class="fa-solid fa-circle-info me-2" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_TEMPLATE_YOOTHEME'); ?>
                    </div>
                    <p><?php echo Text::_('COM_J2COMMERCE_WIZARD_TEMPLATE_UIKIT'); ?></p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="subtemplate" value="uikit" class="visually-hidden">
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_TEMPLATE_USE_UIKIT'); ?></div>
                                </div>
                            </label>
                        </div>
                        <div class="col-12">
                            <label class="j2c-wizard-card">
                                <input type="radio" name="subtemplate" value="bootstrap5" class="visually-hidden" checked>
                                <div class="j2c-wizard-card-body">
                                    <div class="fw-bold"><?php echo Text::_('COM_J2COMMERCE_WIZARD_TEMPLATE_USE_BS5'); ?></div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step: Confirmation -->
                <div class="j2c-wizard-step d-none" data-step="confirm">
                    <h6 class="mb-3"><?php echo Text::_('COM_J2COMMERCE_WIZARD_CONFIRM_TITLE'); ?></h6>
                    <ul class="list-unstyled" id="j2c-wizard-summary">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Step: Success -->
                <div class="j2c-wizard-step d-none" data-step="success">
                    <div class="text-center py-3">
                        <span class="fa-solid fa-circle-check fa-4x text-success mb-3 d-block" aria-hidden="true"></span>
                        <h4><?php echo Text::_('COM_J2COMMERCE_WIZARD_SUCCESS_TITLE'); ?></h4>
                    </div>
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">
                            <span class="fa-solid fa-circle-info me-2" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_WIZARD_URL_TITLE'); ?>
                        </h6>
                        <p class="mb-1"><?php echo Text::_('COM_J2COMMERCE_WIZARD_URL_DESC'); ?></p>
                        <a href="#" id="j2c-wizard-url-example" target="_blank" class="d-block"><code id="j2c-wizard-url-code"></code></a>
                    </div>
                    <div id="j2c-wizard-success-details">
                        <!-- Populated by JS -->
                    </div>
                </div>

            </div><!-- /.modal-body -->

            <!-- Footer -->
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="text-muted small" id="j2c-wizard-step-indicator"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary d-none" id="j2c-wizard-back">
                        <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_BTN_BACK'); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="j2c-wizard-next">
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_BTN_NEXT'); ?>
                        <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-sm btn-success d-none" id="j2c-wizard-create">
                        <span class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_BTN_CREATE'); ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-success d-none" id="j2c-wizard-done"
                            data-bs-dismiss="modal">
                        <span class="fa-solid fa-check me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_WIZARD_BTN_DONE'); ?>
                    </button>
                </div>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div>

<!-- Hidden CSRF token for wizard AJAX calls -->
<input type="hidden" id="j2c-wizard-token" value="<?php echo Session::getFormToken(); ?>">
<input type="hidden" id="j2c-wizard-site-url" value="<?php echo $this->escape($siteUrl); ?>">
<input type="hidden" id="j2c-wizard-sef" value="<?php echo $sef ? '1' : '0'; ?>">
<input type="hidden" id="j2c-wizard-sef-rewrite" value="<?php echo $sefRewrite ? '1' : '0'; ?>"
>
