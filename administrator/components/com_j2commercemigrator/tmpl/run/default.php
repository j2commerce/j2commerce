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
<div class="j2cm-run">

    <?php if ($this->item === null) : ?>
        <div class="alert alert-danger" role="alert">
            <span class="fa-solid fa-circle-xmark me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_NOT_FOUND'); ?>
        </div>
    <?php else : ?>
        <?php
        $statusClass = match ($this->item->status) {
            'completed' => 'success',
            'failed'    => 'danger',
            'running'   => 'info',
            'cancelled' => 'warning',
            default     => 'secondary',
        };
        $counts   = is_string($this->item->counts ?? null) ? (json_decode($this->item->counts, true) ?? []) : [];
        $migrated = (int) (($counts['inserted'] ?? 0) + ($counts['overwritten'] ?? 0) + ($counts['merged'] ?? 0));
        $skipped  = (int) ($counts['skipped'] ?? 0);
        ?>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-3">
                <span class="badge text-bg-<?php echo $statusClass; ?> fs-6">
                    <?php echo $this->escape($this->item->status); ?>
                </span>
                <h2 class="h5 mb-0">
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_HEADING'); ?>
                    #<?php echo (int) $this->item->j2commerce_migrator_run_id; ?>
                </h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ADAPTER'); ?></div>
                        <div class="fw-semibold"><?php echo $this->escape($this->item->adapter); ?></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_CONFLICT_MODE'); ?></div>
                        <div class="fw-semibold"><?php echo $this->escape($this->item->conflict_mode ?? '—'); ?></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STARTED'); ?></div>
                        <div><?php echo $this->escape($this->item->started_on ?? '—'); ?></div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_FINISHED'); ?></div>
                        <div><?php echo $this->escape($this->item->finished_on ?? '—'); ?></div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-4 col-lg-2 text-center">
                        <div class="display-6 fw-bold text-success"><?php echo $migrated; ?></div>
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_MIGRATED'); ?></div>
                    </div>
                    <div class="col-4 col-lg-2 text-center">
                        <div class="display-6 fw-bold text-secondary"><?php echo $skipped; ?></div>
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_SKIPPED'); ?></div>
                    </div>
                    <div class="col-4 col-lg-2 text-center">
                        <div class="display-6 fw-bold text-danger"><?php echo (int) ($this->item->error_count ?? 0); ?></div>
                        <div class="small text-muted"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_ERRORED'); ?></div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator&task=run.export&id=' . (int) $this->item->j2commerce_migrator_run_id); ?>"
                       class="btn btn-outline-secondary btn-sm">
                        <span class="fa-solid fa-file-csv me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_BTN_EXPORT'); ?>
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($this->preflightResults)) : ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_PREFLIGHT_HEADING'); ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_HEADING_TIER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_DETAIL'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->preflightResults as $result) : ?>
                                <?php
                                $pfClass = match ($result['level'] ?? 'info') {
                                    'ok'      => 'text-bg-success',
                                    'warning' => 'text-bg-warning',
                                    'error'   => 'text-bg-danger',
                                    default   => 'text-bg-secondary',
                                };
                                ?>
                                <tr>
                                    <td class="small"><?php echo $this->escape((string) ($result['tier'] ?? '')); ?></td>
                                    <td class="font-monospace small"><?php echo $this->escape($result['table'] ?? ''); ?></td>
                                    <td><span class="badge <?php echo $pfClass; ?>"><?php echo $this->escape($result['level'] ?? ''); ?></span></td>
                                    <td class="small"><?php echo $this->escape($result['message'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($this->errors)) : ?>
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger-subtle d-flex align-items-center justify-content-between">
                    <h3 class="h6 mb-0 text-danger">
                        <span class="fa-solid fa-circle-xmark me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_ERRORS_HEADING'); ?>
                    </h3>
                    <span class="badge text-bg-danger"><?php echo count($this->errors); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_VERIFY_HEADING_TABLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_HEADING_ROW'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUN_HEADING_ERROR'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->errors as $error) : ?>
                                <tr>
                                    <td class="font-monospace small"><?php echo $this->escape($error['table'] ?? ''); ?></td>
                                    <td class="small"><?php echo $this->escape((string) ($error['rowId'] ?? '')); ?></td>
                                    <td class="small text-danger"><?php echo $this->escape($error['message'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="mt-3 d-flex gap-2">
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator&view=runs'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_VIEW_RUNS_TITLE'); ?>
        </a>
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-house me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
        </a>
    </div>
</div>
