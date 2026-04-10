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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Overrides\HtmlView $this */

$token = Session::getFormToken();
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$fileGroups = OverrideRegistry::getFileGroups();

$style = <<<CSS
#subtemplatesAccordion .j2commerce-app-image {
    width: 140px;
}
.accordion-button:not(.collapsed) {
    background-color: transparent;
    box-shadow: none;
}
.card.layout-card {
    border-color: #e0e5eb;
    box-shadow: none;
}
.override-group-fieldset {
    margin-bottom: 1.5rem;
}
.override-group-fieldset:last-child {
    margin-bottom: 0;
}
CSS;
$wa->addInlineStyle($style);
?>

<div class="p-3">
    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
        <span class="icon-info-circle me-2 fs-4" aria-hidden="true"></span>
        <div>
            <?php echo Text::sprintf('COM_J2COMMERCE_OVERRIDE_TEMPLATE_INFO', '<strong>' . $this->escape($this->activeTemplate) . '</strong>'); ?>
        </div>
    </div>

    <?php if (empty($this->subtemplates)) : ?>
        <div class="alert alert-warning">
            <span class="icon-exclamation-triangle me-2" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_NO_SUBTEMPLATES'); ?>
        </div>
    <?php else : ?>
        <div class="accordion" id="subtemplatesAccordion">
            <?php foreach ($this->subtemplates as $index => $subtemplate) : ?>
                <?php
                $collapseId = 'collapse-' . $subtemplate['element'];
                $headerId = 'heading-' . $subtemplate['element'];
                $layoutCount = $subtemplate['layoutCount'] ?? 0;
                $activeCount = $subtemplate['activeOverrideCount'] ?? 0;
                $groupedFiles = $subtemplate['groupedFiles'] ?? [];
                ?>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="<?php echo $headerId; ?>">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>"
                                aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                            <div class="d-flex align-items-center w-100">
                                <img src="<?php echo $this->escape($subtemplate['imagePath']); ?>"
                                     alt="<?php echo $this->escape(Text::_($subtemplate['name'])); ?>"
                                     class="img-fluid j2commerce-app-image me-3"
                                     />
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="fw-semibold"><?php echo Text::_($subtemplate['name']); ?></span>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-secondary'); ?>">
                                            <?php echo Text::plural('COM_J2COMMERCE_OVERRIDE_FILES_COUNT', $layoutCount); ?>
                                        </span>
                                    </div>
                                    <div class="small text-body-secondary">
                                        <?php echo Text::_($subtemplate['description']); ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 me-3">
                                    <?php if ($activeCount > 0) : ?>
                                        <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?>">
                                            <span class="icon-check me-1" aria-hidden="true"></span>
                                            <?php echo Text::plural('COM_J2COMMERCE_OVERRIDE_N_ACTIVE', $activeCount); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" aria-labelledby="<?php echo $headerId; ?>" data-bs-parent="#subtemplatesAccordion">
                        <div class="accordion-body">
                            <?php if (empty($groupedFiles)) : ?>
                                <div class="text-body-secondary text-center py-3">
                                    <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_NO_FILES'); ?>
                                </div>
                            <?php else : ?>
                                <?php foreach ($groupedFiles as $groupKey => $files) : ?>
                                    <?php if (empty($files)) continue; ?>
                                    <?php $groupInfo = $fileGroups[$groupKey] ?? ['label' => $groupKey, 'description' => '']; ?>
                                    <fieldset class="options-form override-group-fieldset">
                                        <legend><?php echo Text::_($groupInfo['label']); ?></legend>
                                        <?php if (!empty($groupInfo['description'])) : ?>
                                            <p class="small text-body-secondary mb-3"><?php echo Text::_($groupInfo['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                                            <?php foreach ($files as $file) : ?>
                                                <div class="col">
                                                    <div class="card layout-card rounded-1 h-100 border-2 <?php echo ($file['hasOverride'] ?? false) ? 'border-success' : ''; ?>">
                                                        <div class="card-body d-flex flex-column">
                                                            <div class="d-flex align-items-start justify-content-between mb-2">
                                                                <h5 class="card-title mb-0">
                                                                    <?php echo $this->escape($file['displayName'] ?? $file['filename']); ?>
                                                                    <?php if (($file['context'] ?? '') === 'tag') : ?>
                                                                        <span class="ms-1 <?php echo J2htmlHelper::badgeClass('badge text-bg-purple'); ?>">Tag</span>
                                                                    <?php endif; ?>
                                                                </h5>
                                                                <?php if ($file['hasOverride'] ?? false) : ?>
                                                                    <span class="<?php echo J2htmlHelper::badgeClass('badge text-bg-success'); ?> ms-2 flex-shrink-0">
                                                                        <span class="icon-check" aria-hidden="true"></span>
                                                                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_ACTIVE'); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <p class="card-text mb-4">
                                                                <code class="small"><?php echo $this->escape($file['relativePath'] ?? $file['filename']); ?></code>
                                                            </p>
                                                            <div class="mt-auto d-flex align-items-center justify-content-between">
                                                                <?php if ($file['hasOverride'] ?? false) : ?>
                                                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=overrides&plugin=' . $subtemplate['element'] . '&file=' . urlencode($file['id']) . '&tab=editor'); ?>"
                                                                       class="btn btn-sm btn-outline-primary rounded-1 border-2">
                                                                        <span class="icon-edit me-1" aria-hidden="true"></span>
                                                                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_EDIT'); ?>
                                                                    </a>
                                                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=overrides.revertOverride&plugin=' . $subtemplate['element'] . '&file=' . urlencode($file['id']) . '&' . $token . '=1'); ?>"
                                                                       class="btn btn-sm btn-outline-danger rounded-1 border-2"
                                                                       onclick="return confirm('<?php echo Text::_('COM_J2COMMERCE_OVERRIDE_CONFIRM_REVERT', true); ?>');">
                                                                        <span class="icon-undo me-1" aria-hidden="true"></span>
                                                                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_REVERT'); ?>
                                                                    </a>
                                                                <?php else : ?>
                                                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=overrides.createOverride&plugin=' . $subtemplate['element'] . '&file=' . urlencode($file['id']) . '&' . $token . '=1'); ?>"
                                                                       class="btn btn-sm btn-primary rounded-1">
                                                                        <span class="icon-copy me-1" aria-hidden="true"></span>
                                                                        <?php echo Text::_('COM_J2COMMERCE_OVERRIDE_CREATE'); ?>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
