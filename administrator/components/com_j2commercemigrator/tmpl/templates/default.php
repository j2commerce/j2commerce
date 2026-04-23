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
<div class="j2cm-templates">

    <?php if (empty($this->templates) && empty($this->templateOverrides)) : ?>
        <div class="border rounded-3 p-5 text-center text-muted">
            <span class="fa-solid fa-file-code fa-2x mb-3 d-block" aria-hidden="true"></span>
            <p class="mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_EMPTY'); ?></p>
        </div>
    <?php else : ?>

        <form id="j2cm-templates-form"
              method="post"
              action="<?php echo Route::_('index.php?option=com_j2commercemigrator&task=templates.migrate'); ?>">

            <?php echo HTMLHelper::_('form.token'); ?>

            <?php if (!empty($this->templates)) : ?>
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_SUBTEMPLATES_HEADING'); ?></h2>
                        <span class="badge text-bg-secondary"><?php echo count($this->templates); ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-1"></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_HEADING_NAME'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_HEADING_SOURCE_PATH'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_HEADING_TARGET_PATH'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ACTION'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->templates as $tpl) : ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="templateKeys[]"
                                                   value="<?php echo $this->escape($tpl['key'] ?? ''); ?>"
                                                   aria-label="<?php echo $this->escape($tpl['name'] ?? ''); ?>">
                                        </td>
                                        <td class="fw-semibold small"><?php echo $this->escape($tpl['name'] ?? ''); ?></td>
                                        <td class="font-monospace small text-muted"><?php echo $this->escape($tpl['sourcePath'] ?? ''); ?></td>
                                        <td class="font-monospace small"><?php echo $this->escape($tpl['targetPath'] ?? ''); ?></td>
                                        <td>
                                            <select class="form-select form-select-sm"
                                                    name="templateActions[<?php echo $this->escape($tpl['key'] ?? ''); ?>]"
                                                    style="min-width:7rem">
                                                <option value="migrate"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_ACTION_MIGRATE'); ?></option>
                                                <option value="skip"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_ACTION_SKIP'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($this->templateOverrides)) : ?>
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h2 class="h5 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_OVERRIDES_HEADING'); ?></h2>
                        <span class="badge text-bg-secondary"><?php echo count($this->templateOverrides); ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-1"></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_HEADING_TEMPLATE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_HEADING_FILE'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_HEADING_ACTION'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->templateOverrides as $override) : ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="overrideKeys[]"
                                                   value="<?php echo $this->escape($override['key'] ?? ''); ?>"
                                                   aria-label="<?php echo $this->escape($override['file'] ?? ''); ?>">
                                        </td>
                                        <td class="small"><?php echo $this->escape($override['template'] ?? ''); ?></td>
                                        <td class="font-monospace small"><?php echo $this->escape($override['file'] ?? ''); ?></td>
                                        <td>
                                            <select class="form-select form-select-sm"
                                                    name="overrideActions[<?php echo $this->escape($override['key'] ?? ''); ?>]"
                                                    style="min-width:7rem">
                                                <option value="migrate"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_ACTION_MIGRATE'); ?></option>
                                                <option value="skip"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_ACTION_SKIP'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h6 mb-0"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_MANUAL_HEADING'); ?></h3>
                </div>
                <div class="card-body">
                    <label for="j2cm-manual-replacements" class="form-label small text-muted">
                        <?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_MANUAL_DESC'); ?>
                    </label>
                    <textarea class="form-control font-monospace small"
                              id="j2cm-manual-replacements"
                              name="manualReplacements"
                              rows="6"
                              placeholder="old-subtemplate-key=new-subtemplate-key"></textarea>
                    <div class="form-text"><?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_MANUAL_HELP'); ?></div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-purple">
                    <span class="fa-solid fa-file-import me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_TEMPLATES_BTN_MIGRATE'); ?>
                </button>
                <a href="<?php echo Route::_('index.php?option=com_j2commercemigrator'); ?>" class="btn btn-outline-secondary">
                    <?php echo Text::_('COM_J2COMMERCEMIGRATOR_BTN_DASHBOARD'); ?>
                </a>
            </div>

        </form>

    <?php endif; ?>

    <div id="j2cm-templates-result" class="mt-3" role="status" aria-live="polite"></div>
</div>
