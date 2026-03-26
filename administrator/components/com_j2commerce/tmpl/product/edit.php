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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Product\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&layout=edit&id=' . (int) $item->j2commerce_product_id); ?>" method="post" name="adminForm" id="product-form" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_J2COMMERCE_FIELDSET_BASIC')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-basic" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_BASIC'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('product_source_id'); ?>
                        <?php echo $this->form->renderField('product_type'); ?>
                        <?php echo $this->form->renderField('visibility'); ?>
                        <?php echo $this->form->renderField('has_options'); ?>
                        <?php echo $this->form->renderField('addtocart_text'); ?>
                    </div>
                </fieldset>
            </div>
            <div class="col-lg-3">
                <?php echo LayoutHelper::render('joomla.edit.global', $this); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'associations', Text::_('COM_J2COMMERCE_FIELDSET_ASSOCIATIONS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-associations" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_ASSOCIATIONS'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('taxprofile_id'); ?>
                        <?php echo $this->form->renderField('manufacturer_id'); ?>
                        <?php echo $this->form->renderField('vendor_id'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'relations', Text::_('COM_J2COMMERCE_FIELDSET_RELATIONS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-relations" class="options-form">
                    <legend><?php echo Text::_('COM_J2COMMERCE_FIELDSET_RELATIONS'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('up_sells'); ?>
                        <?php echo $this->form->renderField('cross_sells'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
        <div class="row">
            <div class="col-lg-9">
                <fieldset id="fieldset-publishing" class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                    <div class="form-grid">
                        <?php echo $this->form->renderField('created_on'); ?>
                        <?php echo $this->form->renderField('created_by'); ?>
                        <?php echo $this->form->renderField('modified_on'); ?>
                        <?php echo $this->form->renderField('modified_by'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo $this->form->renderField('j2commerce_product_id'); ?>
    <?php echo $this->form->renderField('product_source'); ?>
    <?php echo $this->form->renderField('plugins'); ?>
    <?php echo $this->form->renderField('params'); ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
