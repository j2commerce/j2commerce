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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Customer\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $this->item->j2commerce_address_id); ?>" method="post" name="adminForm" id="customer-form" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-customer" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_CUSTOMER_DETAILS'); ?></legend>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('first_name'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('last_name'); ?>
                        </div>
                    </div>
                    <?php echo $this->form->renderField('email'); ?>
                    <?php echo $this->form->renderField('company'); ?>
                    <?php echo $this->form->renderField('phone_1'); ?>
                    <?php echo $this->form->renderField('phone_2'); ?>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <fieldset id="fieldset-user" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELD_USER'); ?></legend>
                    <?php echo $this->form->renderField('user_id'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'address', Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-address" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS'); ?></legend>
                    <?php echo $this->form->renderField('address_1'); ?>
                    <?php echo $this->form->renderField('address_2'); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('city'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('zip'); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('country_id'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo $this->form->renderField('zone_id'); ?>
                        </div>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <fieldset id="fieldset-type" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ADDRESS_TYPE'); ?></legend>
                    <?php echo $this->form->renderField('type'); ?>
                </fieldset>
                <fieldset id="fieldset-tax" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_TAX_INFO'); ?></legend>
                    <?php echo $this->form->renderField('tax_number'); ?>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_address_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
