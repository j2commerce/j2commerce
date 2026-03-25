<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Option\HtmlView $this */

J2CommerceHelper::strapper()->addStyleSheets();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('showon');

$layout  = 'edit';
$tmpl    = Factory::getApplication()->input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=option&layout=' . $layout . $tmpl . '&id=' . (int) ($this->item->id ?? $this->item->j2commerce_option_id ?? 0)); ?>" method="post" name="adminForm" id="option-form" aria-label="<?php echo Text::_('COM_J2COMMERCE_OPTION_FORM_' . ((int) ($this->item->id ?? $this->item->j2commerce_option_id ?? 0) === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_OPTION_DETAILS')); ?>
        <div class="row">
            <div class="col-12">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_OPTION_BASIC_INFO'); ?></legend>
                    <?php echo $this->form->renderField('option_name'); ?>
                    <?php echo $this->form->renderField('option_unique_name'); ?>
                    <?php echo $this->form->renderField('type'); ?>
                    <?php echo $this->form->renderField('published'); ?>

                    <div class="alert alert-info">
                        <h6><?php echo Text::_('COM_J2COMMERCE_OPTION_HELP_TITLE'); ?></h6>
                        <p class="small"><?php echo Text::_('COM_J2COMMERCE_OPTION_HELP_TEXT'); ?></p>
                        <hr>
                        <h6><?php echo Text::_('COM_J2COMMERCE_OPTION_UNIQUE_NAME_HELP'); ?></h6>
                        <p class="small"><?php echo Text::_('COM_J2COMMERCE_OPTION_UNIQUE_NAME_HELP_TEXT'); ?></p>
                    </div>
                </fieldset>

                <?php if ($this->form->getFieldset('option_params')): ?>
                    <fieldset class="options-form" id="optionparams-fieldset">
                        <legend><?php echo Text::_('COM_J2COMMERCE_OPTION_CONFIGURATION'); ?></legend>
                        <?php echo $this->form->renderField('option_params'); ?>
                        <div class="alert alert-info">
                            <h5><?php echo Text::_('COM_J2COMMERCE_OPTION_PARAMS_EXAMPLES'); ?></h5>
                            <code>{"placeholder": "Enter text here", "maxlength": 100, "required": true}</code>
                        </div>
                    </fieldset>
                <?php endif; ?>

                <?php if ($this->form->getFieldset('optionvalues')): ?>
                    <fieldset class="options-form" id="optionvalues-fieldset">
                        <legend><?php echo Text::_('COM_J2COMMERCE_OPTION_VALUES'); ?></legend>
                        <div class="alert alert-info">
                            <p><?php echo Text::_('COM_J2COMMERCE_OPTION_VALUES_HELP'); ?></p>
                        </div>
                        <?php echo $this->form->renderFieldset('optionvalues'); ?>
                    </fieldset>
                <?php endif; ?>

                <?php if ($this->form->getFieldset('optioncolorvalues')): ?>
                    <fieldset class="options-form" id="optioncolorvalues-fieldset">
                        <legend><?php echo Text::_('COM_J2COMMERCE_OPTION_COLOR_VALUES'); ?></legend>
                        <div class="alert alert-info">
                            <p><?php echo Text::_('COM_J2COMMERCE_OPTION_COLOR_VALUES_DESC'); ?></p>
                        </div>
                        <?php echo $this->form->renderFieldset('optioncolorvalues'); ?>
                    </fieldset>
                <?php endif; ?>

                <?php echo $this->form->renderField('id'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate unique name from option name if unique name is empty
    const nameField = document.getElementById('jform_option_name');
    const uniqueNameField = document.getElementById('jform_option_unique_name');

    if (nameField && uniqueNameField) {
        // Track the last auto-generated value so we keep updating
        // until the user manually edits the field
        let lastAutoValue = uniqueNameField.value.trim() === '' ? '' : null;

        function toSnakeCase(str) {
            return str
                .toLowerCase()
                .replace(/[^a-z0-9]/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
        }

        nameField.addEventListener('input', function() {
            const current = uniqueNameField.value;

            // Auto-generate if field is empty or still matches the last auto-generated value
            if (current === '' || current === lastAutoValue) {
                const generated = toSnakeCase(this.value);
                uniqueNameField.value = generated;
                lastAutoValue = generated;
            }
        });

        // If user manually edits the unique name, stop auto-generating
        uniqueNameField.addEventListener('input', function() {
            const generated = toSnakeCase(nameField.value);
            if (this.value !== generated) {
                lastAutoValue = null;
            }
        });
    }

    // Add syntax highlighting helper for JSON params field
    const paramsField = document.getElementById('jform_option_params');
    if (paramsField) {
        paramsField.addEventListener('blur', function() {
            // Basic JSON validation
            if (this.value.trim()) {
                try {
                    JSON.parse(this.value);
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } catch (e) {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
    }
});
</script>
