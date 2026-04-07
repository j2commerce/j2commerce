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

use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Overrides\HtmlView $this */

$plugin = $this->source->pluginElement ?? '';
$file = $this->source->fileId ?? '';
?>

<div class="row mt-2">
    <div id="treeholder" class="col-md-3 tree-holder">
        <?php if (empty($this->overrideFiles)) : ?>
            <div class="p-3 text-body-secondary text-center small">
                <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_NO_FILES'); ?>
            </div>
        <?php else : ?>
            <div class="mt-2 mb-2">
                <ul class="directory-tree treeselect">
                    <li class="folder-select">
                        <a class="folder-url" data-id="" href="">
                            <span class="icon-folder icon-fw" aria-hidden="true"></span>
                            <?php echo $this->escape($this->activeTemplate); ?>/html/layouts/com_j2commerce
                        </a>
                        <?php echo $this->loadTemplate('tree'); ?>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-9">
        <?php if ($this->source) : ?>
            <?php
                $isLayoutFile = ($this->source->fileType ?? '') === 'layouts';
                $builderFileValue = $this->source->pluginElement . '::' . ($this->source->builderFileId ?? $this->source->filename);

                // Classify: only show builder button for block-layout files, not dispatchers
                $sourcePath = OverrideRegistry::getSourcePath(
                    $this->source->pluginElement,
                    $this->source->builderFileId ?? $this->source->filename
                );
                $fileClassification = OverrideRegistry::classifyLayoutFile($sourcePath);
                $isBuilderEditable = $isLayoutFile && $fileClassification !== OverrideRegistry::FILE_TYPE_DISPATCHER;
            ?>
            <div class="d-flex align-items-center justify-content-between mb-2">
                <p class="lead mb-0"><?php echo Text::sprintf('COM_J2COMMERCE_OVERRIDE_FILENAME', $this->escape($this->source->filename)); ?></p>
                <?php if ($isBuilderEditable) : ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-open-visual-builder"
                            data-builder-file="<?php echo $this->escape($builderFileValue); ?>">
                        <span class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_OPEN_VISUAL_BUILDER'); ?>
                    </button>
                <?php elseif ($isLayoutFile && $fileClassification === OverrideRegistry::FILE_TYPE_DISPATCHER) : ?>
                    <span class="badge bg-secondary">
                        <span class="fa-solid fa-code-branch me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_DISPATCHER_FILE_HINT'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=overrides&tab=editor&plugin=' . urlencode($plugin) . '&file=' . urlencode($file)); ?>"
                  method="post" name="adminForm" id="adminForm">
                <div class="editor-border">
                    <?php echo $this->editorForm->getInput('source'); ?>
                </div>
                <input type="hidden" name="task" value="">
                <input type="hidden" name="plugin" value="<?php echo $this->escape($plugin); ?>">
                <input type="hidden" name="file" value="<?php echo $this->escape($file); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php else : ?>
            <form action="<?php echo Route::_('index.php?option=com_j2commerce&view=overrides&tab=editor'); ?>"
                  method="post" name="adminForm" id="adminForm">
                <div class="d-flex align-items-center justify-content-center" style="min-height: 400px;">
                    <div class="text-center text-body-secondary">
                        <span class="icon-file-alt d-block mb-3" style="font-size: 3rem;" aria-hidden="true"></span>
                        <p class="lead"><?php echo Text::_('COM_J2COMMERCE_OVERRIDE_SELECT_FILE'); ?></p>
                        <p class="small"><?php echo Text::_('COM_J2COMMERCE_OVERRIDE_SELECT_FILE_DESC'); ?></p>
                    </div>
                </div>
                <input type="hidden" name="task" value="">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
    </div>
</div>
