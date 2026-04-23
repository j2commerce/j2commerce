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
<header class="mb-4 text-center">
    <div class="mb-3">
        <span class="fa-stack fa-3x text-success" aria-hidden="true">
            <span class="fa-solid fa-circle fa-stack-2x opacity-25"></span>
            <span class="fa-solid fa-check fa-stack-1x"></span>
        </span>
    </div>
    <h2 id="j2cm-step-finalize-title" class="h3 fw-semibold mb-1"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_STEP_FINALIZE_TITLE'); ?></h2>
    <p class="text-muted mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_STEP_FINALIZE_DESC'); ?></p>
</header>

<div id="j2cm-finalize-summary" class="mb-4">
    <div class="row g-3 mb-4" id="j2cm-finalize-stats">
        <div class="col-sm-4">
            <div class="card text-center border-0 bg-body-secondary h-100">
                <div class="card-body">
                    <div class="h2 fw-bold text-success mb-1" id="j2cm-final-migrated">—</div>
                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_ROWS_MIGRATED'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center border-0 bg-body-secondary h-100">
                <div class="card-body">
                    <div class="h2 fw-bold text-warning mb-1" id="j2cm-final-skipped">—</div>
                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_ROWS_SKIPPED'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card text-center border-0 bg-body-secondary h-100">
                <div class="card-body">
                    <div class="h2 fw-bold text-danger mb-1" id="j2cm-final-errors">—</div>
                    <div class="text-muted small"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_ROWS_ERRORED'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="j2cm-finalize-next-steps" class="mb-4">
    <h3 class="h5 fw-semibold mb-3"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_NEXT_STEPS'); ?></h3>
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <span class="fa-solid fa-check text-success me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_STEP_REVIEW_ERRORS'); ?>
        </li>
        <li class="list-group-item">
            <span class="fa-solid fa-check text-success me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_STEP_DISABLE_J2STORE'); ?>
        </li>
        <li class="list-group-item">
            <span class="fa-solid fa-check text-success me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_STEP_TEST_STORE'); ?>
        </li>
        <li class="list-group-item">
            <span class="fa-solid fa-check text-success me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_FINALIZE_STEP_CLEAR_CACHES'); ?>
        </li>
    </ul>
</div>

<div class="d-flex gap-2 flex-wrap">
    <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary">
        <span class="fa-solid fa-house me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator&view=runs'); ?>" class="btn btn-outline-primary">
        <span class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_VIEW_RUNS'); ?>
    </a>
    <a href="<?php echo Route::_('index.php?option=com_j2commerce'); ?>" class="btn btn-success ms-auto">
        <span class="fa-solid fa-store me-1" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_OPEN_J2COMMERCE'); ?>
    </a>
</div>
