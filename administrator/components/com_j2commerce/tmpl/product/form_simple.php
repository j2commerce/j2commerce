<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\RadioField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\User;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;

$item = $displayData['product'] ?? null;
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

if (!$item) {
    return;
}

$variant = $item->variants ?? null;

$row_class = 'row';
$col_class = 'col-md-';



?>

<?php echo HTMLHelper::_('uitab.startTabSet', 'j2commercetab', ['active' => 'generalTab', 'recall' => true, 'breakpoint' => 768]); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'generalTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_GENERAL')); ?>
    <input type="hidden" name="<?php echo $formPrefix.'[j2commerce_variant_id]'; ?>" value="<?php echo isset($item->variant->j2commerce_variant_id) && !empty($item->variant->j2commerce_variant_id) ? $item->variant->j2commerce_variant_id: 0; ?>" />
    <?php echo J2CommerceHelper::loadSubTemplate('general', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'pricingTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_PRICE')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('pricing', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'inventoryTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_INVENTORY')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('inventory', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'imagesTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_IMAGES')); ?>
<div class="row">
    <div class="col-lg-12">
        <?php echo J2CommerceHelper::loadSubTemplate('images', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
    </div>
</div>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'shippingTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_SHIPPING')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('shipping', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'optionsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_OPTIONS')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('options', ['product' => $item, 'form_prefix' => $formPrefix],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'filterTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_FILTER')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('filters', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'relationsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_RELATIONS')); ?>

    <?php echo J2CommerceHelper::loadSubTemplate('relations', ['product' => $item],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'appsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_APPS')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('apps', ['product' => $item,'form_prefix' =>$formPrefix],'form',JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

