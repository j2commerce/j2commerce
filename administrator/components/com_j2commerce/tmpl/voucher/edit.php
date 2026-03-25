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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Voucher\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$layout  = 'edit';
$tmpl    = Factory::getApplication()->input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
$voucherId = (int) ($this->item->j2commerce_voucher_id ?? 0);
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=' . $layout . $tmpl . '&id=' . $voucherId); ?>" method="post" name="adminForm" id="voucher-form" aria-label="<?php echo Text::_('COM_J2COMMERCE_VOUCHER_FORM_' . ($voucherId === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_BASIC_SETTINGS')); ?>
            <div class="row">
                <div class="col-lg-9">
                    <fieldset class="options-form">
                        <legend><?php echo Text::_('COM_J2COMMERCE_BASIC_SETTINGS'); ?></legend>
                        <?php echo $this->form->renderField('j2commerce_voucher_id'); ?>
                        <?php echo $this->form->renderField('order_id'); ?>
                        <?php echo $this->form->renderField('from_order_id'); ?>
                        <?php echo $this->form->renderField('voucher_type'); ?>
                        <?php echo $this->form->renderField('voucher_code'); ?>
                        <?php echo $this->form->renderField('voucher_value'); ?>
                        <?php echo $this->form->renderField('email_to'); ?>
                        <?php echo $this->form->renderField('valid_from'); ?>
                        <?php echo $this->form->renderField('valid_to'); ?>
                    </fieldset>
                </div>
                <div class="col-lg-3">
                    <?php echo $this->form->renderField('published'); ?>
                </div>
            </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'message', Text::_('COM_J2COMMERCE_VOUCHER_MESSAGE')); ?>
        <div class="row">
            <div class="col-12">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_VOUCHER_MESSAGE'); ?></legend>
                    <div class="row">
                        <div class="col-12">
                            <?php echo $this->form->renderField('subject'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <?php echo $this->form->renderField('email_body'); ?>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
