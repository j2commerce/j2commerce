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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>
<div class="j2cm-config">

    <?php if (empty($this->configAnalysis)) : ?>
        <div class="border rounded-3 p-5 text-center text-muted">
            <span class="fa-solid fa-sliders fa-2x mb-3 d-block" aria-hidden="true"></span>
            <p class="mb-2"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_EMPTY'); ?></p>
        </div>
    <?php else : ?>

        <form id="j2cm-config-form"
              method="post"
              action="<?php echo Route::_('index.php?option=com_j2commercemigrator&task=config.migrate'); ?>">

            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_TABLE_HEADING'); ?></h2>
                    <span class="badge text-bg-secondary"><?php echo count($this->configAnalysis); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_HEADING_KEY'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_HEADING_SOURCE_VALUE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_HEADING_TARGET_DEFAULT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_HEADING_ACTION'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->configAnalysis as $row) : ?>
                                <?php
                                $actionBadge = match ($row['action'] ?? 'skip') {
                                    'migrate' => 'text-bg-success',
                                    'manual'  => 'text-bg-warning',
                                    default   => 'text-bg-secondary',
                                };
                                ?>
                                <tr>
                                    <td class="font-monospace small"><?php echo $this->escape($row['key'] ?? ''); ?></td>
                                    <td class="small"><?php echo $this->escape((string) ($row['sourceValue'] ?? '')); ?></td>
                                    <td class="small text-muted"><?php echo $this->escape((string) ($row['targetDefault'] ?? '')); ?></td>
                                    <td>
                                        <select class="form-select form-select-sm"
                                                name="actions[<?php echo $this->escape($row['key'] ?? ''); ?>]"
                                                style="min-width:8rem">
                                            <option value="migrate" <?php echo ($row['action'] ?? '') === 'migrate' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_ACTION_MIGRATE'); ?>
                                            </option>
                                            <option value="skip" <?php echo ($row['action'] ?? '') === 'skip' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_ACTION_SKIP'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-purple">
                    <span class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_CONFIG_BTN_APPLY'); ?>
                </button>
                <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary">
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
                </a>
            </div>

        </form>

    <?php endif; ?>
</div>
