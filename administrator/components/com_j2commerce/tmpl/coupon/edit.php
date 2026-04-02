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

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Coupon\HtmlView $this */

J2CommerceHelper::strapper()->addCSS();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$layout  = 'edit';
$tmpl    = Factory::getApplication()->input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=coupon&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->j2commerce_coupon_id); ?>" method="post" name="adminForm" id="coupon-form" aria-label="<?php echo Text::_('COM_J2COMMERCE_COUPON_FORM_' . ((int) $this->item->j2commerce_coupon_id === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_BASIC_SETTINGS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_BASIC_SETTINGS'); ?></legend>
                    <?php echo $this->form->renderField('j2commerce_coupon_id'); ?>
                    <?php echo $this->form->renderField('coupon_name'); ?>
                    <?php echo $this->form->renderField('coupon_code'); ?>
                    <?php echo $this->form->renderField('value_type'); ?>
                    <?php echo $this->form->renderField('value'); ?>
                    <?php echo $this->form->renderField('free_shipping'); ?>
                    <?php echo $this->form->renderField('valid_from'); ?>
                    <?php echo $this->form->renderField('valid_to'); ?>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'validity', Text::_('COM_J2COMMERCE_ADVANCED_SETTINGS')); ?>
        <div class="row">
            <div class="col-12">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_ADVANCED_SETTINGS'); ?></legend>

                    <?php echo $this->form->renderField('product_category'); ?>
                    <?php echo $this->form->renderField('products'); ?>
                    <?php echo $this->form->renderField('products_alert'); ?>
                    <?php echo $this->form->renderField('brand_ids'); ?>
                    <?php echo $this->form->renderField('logged'); ?>
                    <?php echo $this->form->renderField('user_group'); ?>
                    <?php echo $this->form->renderField('users'); ?>

                    <?php echo $this->form->renderField('min_subtotal'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'restrictions', Text::_('COM_J2COMMERCE_COUPON_TAB_USAGE')); ?>
        <div class="row">
            <div class="col-12">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_COUPON_TAB_USAGE'); ?></legend>
                    <?php echo $this->form->renderField('max_uses'); ?>
                    <?php echo $this->form->renderField('max_quantity'); ?>
                    <?php echo $this->form->renderField('max_customer_uses'); ?>
                    <?php echo $this->form->renderField('max_value'); ?>

                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
