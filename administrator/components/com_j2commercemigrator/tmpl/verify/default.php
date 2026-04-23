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
<div class="j2cm-verify">

    <?php if (!$this->connected) : ?>
        <div class="alert alert-warning" role="alert">
            <span class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_NOT_CONNECTED'); ?>
            <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator&view=connection&adapter=' . $this->escape($this->adapterKey)); ?>"
               class="alert-link ms-1">
                <?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_CONFIGURE_CONNECTION'); ?>
            </a>
        </div>
    <?php else : ?>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 fw-semibold mb-2"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING'); ?></h2>
                <p class="text-muted mb-3"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_DESC'); ?></p>
                <button type="button"
                        class="btn btn-purple"
                        data-j2cm-action="runVerification"
                        data-adapter="<?php echo $this->escape($this->adapterKey); ?>">
                    <span class="fa-solid fa-circle-check me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_BTN_RUN'); ?>
                </button>
            </div>
        </div>

        <div id="j2cm-verify-loading" class="d-none mb-4" aria-live="polite">
            <div class="card">
                <div class="card-body placeholder-wave">
                    <span class="placeholder col-4 mb-2 d-block"></span>
                    <span class="placeholder col-8 mb-1 d-block"></span>
                    <span class="placeholder col-6 d-block"></span>
                </div>
            </div>
        </div>

        <div id="j2cm-verify-results" class="d-none" aria-live="polite">

            <div class="card mb-3" id="j2cm-verify-counts-panel">
                <div class="card-header">
                    <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_COUNTS_HEADING'); ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="j2cm-verify-counts-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_TABLE'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_SOURCE'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_TARGET'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3" id="j2cm-verify-integrity-panel">
                <div class="card-header">
                    <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_INTEGRITY_HEADING'); ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="j2cm-verify-integrity-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_CHECK'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_DETAIL'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3" id="j2cm-verify-financials-panel">
                <div class="card-header">
                    <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_FINANCIALS_HEADING'); ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="j2cm-verify-financials-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_METRIC'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_SOURCE'); ?></th>
                                <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_TARGET'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>

        <div id="j2cm-verify-error" class="alert alert-danger d-none" role="alert" aria-live="polite">
            <span class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></span>
            <strong><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_ERR_HEADING'); ?></strong>
            <span class="j2cm-error-message ms-1"></span>
        </div>

    <?php endif; ?>

    <div class="mt-3">
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
        </a>
    </div>
</div>
