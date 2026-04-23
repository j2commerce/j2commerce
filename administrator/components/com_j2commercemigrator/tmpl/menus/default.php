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
<div class="j2cm-menus">

    <?php if (empty($this->menuItems)) : ?>
        <div class="border rounded-3 p-5 text-center text-muted">
            <span class="fa-solid fa-bars fa-2x mb-3 d-block" aria-hidden="true"></span>
            <p class="mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_EMPTY'); ?></p>
        </div>
    <?php else : ?>

        <form id="j2cm-menus-form"
              method="post"
              action="<?php echo Route::_('index.php?option=com_j2commercemigrator&task=menus.migrate'); ?>">

            <?php echo HTMLHelper::_('form.token'); ?>

            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_TABLE_HEADING'); ?></h2>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-secondary"><?php echo count($this->menuItems); ?></span>
                        <div class="form-check mb-0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="j2cm-menus-check-all"
                                   data-j2cm-check-all="menuIds">
                            <label class="form-check-label small" for="j2cm-menus-check-all">
                                <?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_CHECK_ALL'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="w-1"></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_TITLE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_HEADING_LINK'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_HEADING_MENU'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_STATUS'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->menuItems as $item) : ?>
                                <tr>
                                    <td>
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="menuIds[]"
                                               value="<?php echo (int) ($item['id'] ?? 0); ?>"
                                               aria-label="<?php echo $this->escape($item['title'] ?? ''); ?>">
                                    </td>
                                    <td><?php echo $this->escape($item['title'] ?? ''); ?></td>
                                    <td class="small text-muted font-monospace"><?php echo $this->escape($item['link'] ?? ''); ?></td>
                                    <td class="small"><?php echo $this->escape($item['menutype'] ?? ''); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = match ((int) ($item['published'] ?? 1)) {
                                            1  => 'text-bg-success',
                                            0  => 'text-bg-secondary',
                                            default => 'text-bg-warning',
                                        };
                                        $statusLabel = match ((int) ($item['published'] ?? 1)) {
                                            1  => Text::_('JPUBLISHED'),
                                            0  => Text::_('JUNPUBLISHED'),
                                            default => Text::_('JTRASHED'),
                                        };
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit"
                        name="task"
                        value="menus.migrate"
                        class="btn btn-purple">
                    <span class="fa-solid fa-right-left me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_BTN_MIGRATE'); ?>
                </button>
                <button type="submit"
                        name="task"
                        value="menus.createCanonical"
                        class="btn btn-outline-primary">
                    <span class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_BTN_CREATE_CANONICAL'); ?>
                </button>
                <button type="submit"
                        name="task"
                        value="menus.rollback"
                        class="btn btn-outline-danger">
                    <span class="fa-solid fa-rotate-left me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_MENUS_BTN_ROLLBACK'); ?>
                </button>
                <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary">
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
                </a>
            </div>

        </form>

    <?php endif; ?>

    <div id="j2cm-menus-result" class="mt-3" role="status" aria-live="polite"></div>
</div>
