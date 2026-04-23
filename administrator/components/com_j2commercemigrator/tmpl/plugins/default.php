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
<div class="j2cm-plugins">

    <?php if (empty($this->plugins)) : ?>
        <div class="border rounded-3 p-5 text-center text-muted">
            <span class="fa-solid fa-puzzle-piece fa-2x mb-3 d-block" aria-hidden="true"></span>
            <p class="mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_PLUGINS_EMPTY'); ?></p>
        </div>
    <?php else : ?>
        <div class="j2cm-card-grid mb-4">
            <?php foreach ($this->plugins as $plugin) : ?>
                <?php
                $statusPill = match ($plugin['status'] ?? 'disabled') {
                    'enabled'      => ['text-bg-success',  Text::_('COM_J2COMMERCEMIGRATOR_STATUS_ENABLED')],
                    'needs_config' => ['text-bg-warning',  Text::_('COM_J2COMMERCEMIGRATOR_STATUS_NEEDS_CONFIG')],
                    'running'      => ['text-bg-info',     Text::_('COM_J2COMMERCEMIGRATOR_STATUS_RUNNING')],
                    default        => ['text-bg-secondary', Text::_('COM_J2COMMERCEMIGRATOR_STATUS_DISABLED')],
                };
                $isEnabled = ($plugin['enabled'] ?? false);
                ?>
                <article class="card j2cm-adapter-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <span class="fa-stack fa-lg text-purple" aria-hidden="true">
                                <i class="fa-solid fa-square fa-stack-2x opacity-25"></i>
                                <i class="<?php echo $this->escape($plugin['icon'] ?? 'fa-solid fa-plug'); ?> fa-stack-1x"></i>
                            </span>
                            <div class="flex-grow-1">
                                <h3 class="h5 mb-0"><?php echo $this->escape($plugin['title'] ?? ''); ?></h3>
                                <p class="text-muted small mb-0"><?php echo $this->escape($plugin['author'] ?? ''); ?></p>
                            </div>
                            <span class="badge <?php echo $statusPill[0]; ?> flex-shrink-0"><?php echo $statusPill[1]; ?></span>
                        </div>

                        <p class="small text-muted mb-3"><?php echo $this->escape($plugin['description'] ?? ''); ?></p>

                        <?php if (!empty($plugin['lastRunStatus'])) : ?>
                            <?php
                            $runBadge = match ($plugin['lastRunStatus']) {
                                'completed' => ['text-bg-success', Text::_('COM_J2COMMERCEMIGRATOR_PLUGINS_LAST_RUN_OK')],
                                'failed'    => ['text-bg-danger',  Text::_('COM_J2COMMERCEMIGRATOR_PLUGINS_LAST_RUN_FAILED')],
                                default     => ['text-bg-secondary', $plugin['lastRunStatus']],
                            };
                            ?>
                            <p class="small mb-3">
                                <span class="badge <?php echo $runBadge[0]; ?>"><?php echo $runBadge[1]; ?></span>
                            </p>
                        <?php endif; ?>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($isEnabled) : ?>
                                <a class="btn btn-purple btn-sm"
                                   href="<?php echo Route::_('index.php?option=com_j2commercemigrator&view=migrate&adapter=' . $this->escape($plugin['key'] ?? '')); ?>">
                                    <span class="fa-solid fa-play me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_DASHBOARD_START_MIGRATION'); ?>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        data-task="plugin.unpublish"
                                        data-extension-id="<?php echo (int) ($plugin['extensionId'] ?? 0); ?>"
                                        aria-label="<?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DISABLE'); ?>">
                                    <span class="fa-solid fa-pause" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DISABLE'); ?></span>
                                </button>
                            <?php else : ?>
                                <button type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        data-task="plugin.publish"
                                        data-extension-id="<?php echo (int) ($plugin['extensionId'] ?? 0); ?>"
                                        aria-label="<?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_ENABLE'); ?>">
                                    <span class="fa-solid fa-play-pause me-1" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_ENABLE'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($plugin['prerequisiteErrors'])) : ?>
                        <div class="card-footer text-muted small">
                            <span class="fa-solid fa-triangle-exclamation text-warning me-1" aria-hidden="true"></span>
                            <?php foreach ($plugin['prerequisiteErrors'] as $err) : ?>
                                <div><?php echo $this->escape($err); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="mt-2">
        <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary btn-sm">
            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
        </a>
    </div>
</div>
