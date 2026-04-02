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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Filtergroup\HtmlView $this */

J2CommerceHelper::strapper()->addStyleSheets();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$layout  = 'edit';
$tmpl    = Factory::getApplication()->input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=filtergroup&layout=' . $layout . $tmpl . '&id=' . (int) ($this->item->id ?? $this->item->j2commerce_filtergroup_id ?? 0)); ?>" method="post" name="adminForm" id="filtergroup-form" aria-label="<?php echo Text::_('COM_J2COMMERCE_FILTERGROUP_FORM_' . ((int) ($this->item->id ?? $this->item->j2commerce_filtergroup_id ?? 0) === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <div class="main-card">
        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <fieldset class="options-form">
                            <legend><?php echo Text::_('COM_J2COMMERCE_FILTERGROUP_DETAILS'); ?></legend>
                            <?php echo $this->form->renderField('group_name'); ?>
                        </fieldset>

                        <?php if (!empty($this->item->id) || !empty($this->item->j2commerce_filtergroup_id)) : ?>
                        <fieldset class="options-form">
                            <legend><?php echo Text::sprintf('COM_J2COMMERCE_FILTERGROUP_FIELDSET_FILTERS', $this->item->group_name); ?> </legend>
                            <?php echo $this->form->renderField('filters'); ?>
                        </fieldset>
                        <?php else : ?>
                        <div class="alert alert-info">
                            <span class="icon-info-circle" aria-hidden="true"></span>
                            <?php echo Text::_('COM_J2COMMERCE_FILTERGROUP_SAVE_FIRST_TO_ADD_FILTERS'); ?>
                        </div>
                        <?php endif; ?>

                        <?php echo $this->form->renderField('id'); ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
