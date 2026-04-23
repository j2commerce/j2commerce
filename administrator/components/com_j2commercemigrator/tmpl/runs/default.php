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
<div class="j2cm-runs">
    <?php if (empty($this->runs)) : ?>
        <div class="border rounded-3 p-4 text-center text-muted">
            <span class="fa-solid fa-inbox fa-2x mb-2 d-block" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_RUNS_EMPTY'); ?>
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ID'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ADAPTER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_CONFLICT_MODE'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_MIGRATED'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_SKIPPED'); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ROWS_ERRORED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STARTED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_FINISHED'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->runs as $run) : ?>
                        <?php
                        $statusClass = match ($run->status) {
                            'completed' => 'success',
                            'failed'    => 'danger',
                            'running'   => 'info',
                            'cancelled' => 'warning',
                            default     => 'secondary',
                        };
                        $counts = is_string($run->counts ?? null)
                            ? (json_decode($run->counts, true) ?? [])
                            : [];
                        $migrated = (int) (($counts['inserted'] ?? 0) + ($counts['overwritten'] ?? 0) + ($counts['merged'] ?? 0));
                        $skipped  = (int) ($counts['skipped'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo (int) $run->j2commerce_migrator_run_id; ?></td>
                            <td><?php echo $this->escape($run->adapter); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo $statusClass; ?>">
                                    <?php echo $this->escape($run->status); ?>
                                </span>
                            </td>
                            <td><?php echo $this->escape($run->conflict_mode ?? '—'); ?></td>
                            <td class="text-end"><?php echo $migrated; ?></td>
                            <td class="text-end"><?php echo $skipped; ?></td>
                            <td class="text-end"><?php echo (int) ($run->error_count ?? 0); ?></td>
                            <td><?php echo $this->escape($run->started_on ?? '—'); ?></td>
                            <td><?php echo $this->escape($run->finished_on ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
        <div class="mt-3">
            <?php echo $this->pagination->getPagesLinks(); ?>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
        </a>
    </div>
</div>
