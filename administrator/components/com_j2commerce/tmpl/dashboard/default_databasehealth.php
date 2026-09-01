<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$user = Factory::getApplication()->getIdentity();

// Dashboard render is gated on core.manage; this card is gated on the stricter core.admin
// the setup-guide/health endpoints themselves require, so a manager without admin never
// sees a card whose Fix buttons it could not use.
if ($user === null || !$user->authorise('core.admin', 'com_j2commerce')) {
    return;
}

$doc = Factory::getApplication()->getDocument();
$wa  = $doc->getWebAssetManager();
$wa->registerAndUseScript(
    'com_j2commerce.database-health',
    'media/com_j2commerce/js/administrator/databasehealth.js',
    [],
    ['defer' => true]
);
$wa->registerAndUseStyle(
    'com_j2commerce.database-health.css',
    'media/com_j2commerce/css/administrator/databasehealth.css'
);

Text::script('COM_J2COMMERCE_DATABASE_HEALTH_FIX');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_REVIEW');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_VIEW_SETUP_GUIDE');
Text::script('COM_J2COMMERCE_DATABASE_HEALTH_DESTRUCTIVE_WARNING');
Text::script('JLIB_APPLICATION_SAVE_SUCCESS');

$productsReviewModalId = 'j2commerce-database-health-products-modal';

// Only source of truth for the badge_style transform (config.xml badge_style param) — the
// JS never re-implements the text-bg- -> bg- rule, it just reads these resolved classes.
$badgeSuccess = J2htmlHelper::badgeClass('badge text-bg-success me-2');
$badgeWarning = J2htmlHelper::badgeClass('badge text-bg-warning me-2');
$badgeInfo    = J2htmlHelper::badgeClass('badge text-bg-info me-2');
?>
<div
    class="card mb-4 d-none"
    id="j2commerce-database-health"
    data-badge-success="<?php echo $this->escape($badgeSuccess); ?>"
    data-badge-warning="<?php echo $this->escape($badgeWarning); ?>"
    data-badge-info="<?php echo $this->escape($badgeInfo); ?>"
    data-products-review-modal="<?php echo $this->escape($productsReviewModalId); ?>"
>
    <div class="card-header p-3">
        <h2 class="mb-0 fs-4">
            <span class="fa-solid fa-database me-2 text-info" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_TITLE'); ?>
        </h2>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small mb-3"><?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_DESC'); ?></p>
        <div class="database-health-loading text-center py-4">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
            </div>
        </div>
        <ul class="list-group database-health-list d-none" aria-live="polite" aria-atomic="false"></ul>
    </div>
</div>
<?php
// Lazy iframe modal for the products_without_master_variant "Review" affordance — real core
// list layout + pagination via view=databasehealthproducts, not hand-built HTML.
//
// A native <dialog> rather than HTMLHelper::_('bootstrap.renderModal', ...): Joomla 6 ships
// Bootstrap as per-component ES modules with no global `window.bootstrap` and no working
// `Joomla.Modal` open-by-id helper (nothing in this codebase calls Joomla.Modal.setCurrent()),
// so a JS-triggered open of a dynamically created button had no confirmed-working mechanism to
// call. <dialog>.showModal() is a native, dependency-free browser API — the exact same
// mechanism already used successfully for the delete-confirmation dialog on this screen.
?>
<dialog
    id="<?php echo $this->escape($productsReviewModalId); ?>"
    class="j2commerce-database-health-products-dialog shadow rounded-3"
    aria-labelledby="<?php echo $this->escape($productsReviewModalId); ?>-title"
>
    <div class="modal-content h-100">
        <div class="modal-header">
            <h2 id="<?php echo $this->escape($productsReviewModalId); ?>-title" class="modal-title fs-5">
                <?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_LABEL'); ?>
            </h2>
            <button
                type="button"
                class="btn-close database-health-products-dialog-close"
                aria-label="<?php echo Text::_('JCLOSE'); ?>"
            ></button>
        </div>
        <div class="modal-body p-0">
            <iframe
                class="database-health-products-iframe"
                data-src="index.php?option=com_j2commerce&view=databasehealthproducts&tmpl=component"
                title="<?php echo Text::_('COM_J2COMMERCE_DATABASE_HEALTH_CHECK_PRODUCTS_WITHOUT_MASTER_VARIANT_LABEL'); ?>"
            ></iframe>
        </div>
    </div>
</dialog>
