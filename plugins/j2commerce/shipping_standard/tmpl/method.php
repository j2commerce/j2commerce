<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * Template variables provided by ShippingStandard::onShippingPluginView():
 *
 * @var  \Joomla\CMS\Form\Form  $form      The edit form
 * @var  object                 $item      The shipping method record
 * @var  array                  $geozones  Geozones [id => name]
 * @var  bool                   $isNew     Whether this is a new method
 * @var  int                    $methodId  The method ID (0 if new)
 * @var  int                    $methodType The current method type
 */

$wa = \Joomla\CMS\Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$methodId   = (int) ($item->j2commerce_shippingmethod_id ?? 0);
$isNew      = ($methodId === 0);

?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $methodId); ?>"
      method="post" name="adminForm" id="shippingstandard-form" class="form-validate">

    <input type="hidden" name="plugin" value="shipping_standard">

    <!-- Method name at top -->
    <div class="row title-alias form-vertical mb-3">
        <div class="col-12">
            <?php echo $form->renderField('shipping_method_name'); ?>
        </div>
    </div>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_BASIC')); ?>
        <div class="row">
            <div class="col-lg-8">
                <fieldset id="fieldset-basic" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_BASIC'); ?></legend>
                    <div class="form-grid">
                        <?php echo $form->renderField('shipping_method_type'); ?>
                        <?php echo $form->renderField('tax_class_id'); ?>
                        <?php echo $form->renderField('address_override'); ?>
                        <?php echo $form->renderField('subtotal_minimum'); ?>
                        <?php echo $form->renderField('subtotal_maximum'); ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-4">
                <fieldset id="fieldset-status" class="options-form">
                    <legend><?php echo Text::_('JSTATUS'); ?></legend>
                    <div class="form-grid">
                        <?php echo $form->renderField('published'); ?>

                        <?php if (!$isNew) : ?>
                            <div class="control-group">
                                <div class="control-label">
                                    <label><?php echo Text::_('COM_J2COMMERCE_SHIPPING_RATES'); ?></label>
                                </div>
                                <div class="controls">
                                    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=setrates&id=' . $methodId); ?>" class="btn btn-primary w-100">
                                        <span class="icon-list me-1" aria-hidden="true"></span>
                                        <?php echo Text::_('COM_J2COMMERCE_SHIPPING_SET_RATES'); ?>
                                    </a>
                                    <small class="form-text"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_RATES_MANAGE_DESC'); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'params', Text::_('COM_J2COMMERCE_FIELDSET_PARAMS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-params" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_PARAMS'); ?></legend>
                    <div class="form-grid">
                        <?php echo $form->renderField('shipping_select_text'); ?>
                        <?php echo $form->renderField('shipping_image'); ?>
                        <?php echo $form->renderField('shipping_desc'); ?>
                        <?php echo $form->renderField('shipping_price_based_on'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $form->renderField('j2commerce_shippingmethod_id'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

