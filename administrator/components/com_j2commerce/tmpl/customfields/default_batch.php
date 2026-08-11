<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * Batch modal for bulk-updating custom field display settings.
 * Core areas (billing, shipping, etc.) update DB columns directly.
 * Plugin areas (via GetCustomFieldDisplayAreas) update field_display JSON.
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customfields\HtmlView $this */

// The form is built per request, so the two groups are told apart by field name rather than by
// two lists this template would have to keep in step with the helper.
$coreFields   = [];
$pluginFields = [];

foreach ($this->batchForm->getGroup('batch') as $field) {
    str_starts_with($field->fieldname, 'plugin_')
        ? $pluginFields[] = $field->fieldname
        : $coreFields[]   = $field->fieldname;
}
?>

<div class="modal fade" id="collapseModal" tabindex="-1" aria-labelledby="collapseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="collapseModalLabel"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_TITLE'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body px-4 pt-4 pb-2">
                <p class="text-body-secondary small mb-3"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_DESC'); ?></p>

                <h3 class="fw-bold mb-2 fs-6"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_CORE_HEADING'); ?></h3>
                <div class="row">
                    <?php foreach ($coreFields as $name) : ?>
                        <div class="form-group col-md-6 col-lg-4 mb-3">
                            <?php echo $this->batchForm->renderField($name, 'batch'); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($pluginFields)) : ?>
                    <hr class="my-3">
                    <h3 class="fw-bold mb-2 fs-6"><?php echo Text::_('COM_J2COMMERCE_BATCH_DISPLAY_PLUGIN_HEADING'); ?></h3>
                    <div class="row">
                        <?php foreach ($pluginFields as $name) : ?>
                            <div class="form-group col-md-6 col-lg-4 mb-3">
                                <?php echo $this->batchForm->renderField($name, 'batch'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <joomla-toolbar-button task="customfields.batch">
                    <button type="button" class="btn btn-primary"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
                </joomla-toolbar-button>
            </div>
        </div>
    </div>
</div>
