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

use J2Commerce\Component\J2commerce\Administrator\Field\PriceField;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductModel;
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
use Joomla\CMS\Uri\Uri;

//HTMLHelper::_('bootstrap.modal');

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$fancySelectDefaults = ['multiple' => false, 'autofocus' => false, 'onchange' => '', 'dataAttribute' => '', 'readonly' => false, 'disabled' => false, 'hint' => '', 'required' => false];

$variant = $item->variants ?? null;

$priceField = new PriceField();
$priceField->setDatabase(Factory::getContainer()->get('DatabaseDriver'));
$element = new SimpleXMLElement('<field />');
$priceField->setup($element, '');

$pricing_calculator = J2CommerceHelper::product()->getPricingCalculators();

$base_path = rtrim(Uri::root(),'/').'/administrator/';

$priceValues = [
    'name' => $formPrefix . '[price]',
    'id' => 'j2commerce-product-price-field',
    'value' => $item->variant->price ?? '',
    'class' => 'form-control'
];
//$link = $base_path . '/index.php?option=com_j2commerce&view=products&task=setproductprice&variant_id=' . $item->variant->j2commerce_variant_id . '&layout=productpricing&tmpl=component';
$link = $base_path . '/index.php?option=com_j2commerce&view=productprice&layout=productpricing&variant_id='. $item->variant->j2commerce_variant_id. '&tmpl=component';
?>

<div class="j2commerce-product-pricing">
    <fieldset class="options-form mb-5">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_TAB_PRICE');?></legend>
        <div class="form-grid">
            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-price-field-lbl" for="j2commerce-product-price-field"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_REGULAR_PRICE');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('field.price', $priceValues, JPATH_ADMINISTRATOR . '/components/com_j2commerce/layouts');?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-price-field-lbl" for="j2commerce-product-price-field"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SET_ADVANCED_PRICING');?></label>
                </div>
                <div class="controls">
                    <a href="<?php echo $link; ?>" class="btn btn-primary" rel="noopener noreferrer" data-bs-toggle="modal" data-bs-target="#priceModal">
                        <?php echo Text::_('COM_J2COMMERCE_PRODUCT_ADDITIONAL_PRICING');?>
                    </a>
                    <?php echo HTMLHelper::_(
                        'bootstrap.renderModal',
                        'priceModal',
                        [
                            'url'    => $link,
                            'title'  => Text::_('COM_J2COMMERCE_PRODUCT_ADDITIONAL_PRICING'),
                            'height' => '100%',
                            'width'  => '100%',
                            'modalWidth'  => '95%',
                            'bodyHeight'  => '95%',
                            'footer' => '<button type="button" class="btn btn-primary" data-bs-dismiss="modal" aria-hidden="true">' . Text::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</button>'
                        ]
                    );
                    ?>
                </div>
            </div>

            <div class="control-group align-items-center">
                <div class="control-label">
                    <label id="j2commerce-product-pricing_calculator-select-group-lbl" for="j2commerce-product-pricing_calculator-select-group"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICING_CALCULATOR');?></label>
                </div>
                <div class="controls">
                    <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', ['name'  => $formPrefix.'[pricing_calculator]','id'    => 'j2commerce-product-pricing_calculator-select-group','value' => $item->variant->pricing_calculator,'options' => $pricing_calculator] + $fancySelectDefaults);?>
                </div>
            </div>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterProductPricingEdit', array($this, $item, $formPrefix))->getArgument('html', ''); ?>

        </div>
    </fieldset>


    <div class="alert alert-info">
        <h4><?php echo Text::_('COM_J2COMMERCE_QUICK_HELP'); ?></h4>
        <?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICE_HELP_TEXT'); ?>
    </div>

</div>


