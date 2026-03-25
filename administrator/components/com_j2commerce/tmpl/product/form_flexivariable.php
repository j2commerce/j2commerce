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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Field\ProductTypeField;
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
use Joomla\Database\DatabaseAwareTrait;

use Joomla\Registry\Registry;

$is_Pro = J2CommerceHelper::isPro();
$row_class = 'row';
$col_class = 'col-lg-';

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';



?>

<div class="alert alert-block alert-info">
    <?php echo Text::_('COM_J2COMMERCE_FLEXIVARIANT_PRODUCT_HELP_TEXT'); ?>
</div>
<?php echo HTMLHelper::_('uitab.startTabSet', 'j2commercetab', ['active' => 'generalTab', 'recall' => true, 'breakpoint' => 768]); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'generalTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_GENERAL')); ?>
    <input type="hidden" name="<?php echo $formPrefix.'[j2commerce_variant_id]'; ?>" value="<?php echo isset($item->variant->j2commerce_variant_id) && !empty($item->variant->j2commerce_variant_id) ? $item->variant->j2commerce_variant_id: 0; ?>" />
    <?php $layout_file = 'form_flexivariable_general';?>
    <?php $layout = new FileLayout($layout_file, JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
    <?php echo $layout->render(['product' => $item]);?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'imagesTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_IMAGES')); ?>
    <?php $layout_file = 'form_images';?>
    <?php $layout = new FileLayout($layout_file, JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
    <?php echo $layout->render(['product' => $item]);?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'variantsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_VARIANTS')); ?>
    <?php echo (new FileLayout('form_flexivariable_options', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'))->render(['product' => $item, 'form_prefix' => $formPrefix]);?>
    <?php echo (new FileLayout('form_flexivariablevariants', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'))->render(['product' => $item, 'form_prefix' => $formPrefix]);?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'filterTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_FILTER')); ?>
    <?php $layout_file = 'form_filters';?>
    <?php $layout = new FileLayout($layout_file, JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product');?>
    <?php echo $layout->render(['product' => $item]);?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'relationsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_RELATIONS')); ?>
    <?php echo (new FileLayout('form_relations', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'))->render(['product' => $item]);?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'j2commercetab', 'appsTab', Text::_('COM_J2COMMERCE_PRODUCT_TAB_APPS')); ?>
    <?php echo J2CommerceHelper::loadSubTemplate('apps', ['product' => $item, 'form_prefix' => $formPrefix], 'form', JPATH_ADMINISTRATOR . '/components/com_j2commerce/tmpl/product'); ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
