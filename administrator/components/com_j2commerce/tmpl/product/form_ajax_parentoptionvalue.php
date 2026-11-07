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

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;

/**
 * Layout for the "Set Parent Value(s)" modal - the non-list-type sibling of
 * form_ajax_optionvalues.php. Rendered into a JSON string, so nothing registered here reaches
 * the page; the fancy-select webcomponent/preset below is registered by the view that fetches
 * this fragment (see that file's note on the same point).
 *
 * @var array $displayData
 */
$productId       = $displayData['product_id'];
$productOptionId = $displayData['productoption_id'];
$parentValues    = $displayData['parent_values'];
$selectedValues  = $displayData['selected_values'];

$parentValueChoices = [];
foreach ($parentValues as $value) {
    $parentValueChoices[] = (object) [
        'value' => (int) $value['id'],
        'text'  => J2htmlHelper::translateKey($value['label']),
    ];
}
?>
<div class="j2commerce-ajax-parentoptionvalue" data-product-id="<?php echo $productId; ?>" data-productoption-id="<?php echo $productOptionId; ?>">
    <?php if (empty($parentValues)) : ?>
        <div class="alert alert-warning" role="alert">
            <?php echo Text::_('COM_J2COMMERCE_PARENT_OPTIONVALUE_SELECT_PARENT_FIRST'); ?>
        </div>
    <?php else : ?>
        <!-- Same card shell as form_ajax_optionvalues.php, so both AJAX modals look alike -->
        <div class="card box-shadow-none">
            <div class="card-header">
                <h3 class="mb-0 fs-6"><?php echo Text::_('COM_J2COMMERCE_PARENT_OPTIONVALUE'); ?></h3>
            </div>
            <div class="card-body">
                <?php echo LayoutHelper::render('joomla.form.field.list-fancy-select', [
                    'name'      => 'parent_optionvalue[]',
                    'id'        => 'j2commerce-parentoptionvalue-select',
                    'multiple'  => true,
                    'value'     => array_map('strval', $selectedValues),
                    'options'   => $parentValueChoices,
                    'autofocus' => false,
                    'onchange'  => '',
                    'dataAttribute' => '',
                    'readonly'  => false,
                    'disabled'  => false,
                    'hint'      => '',
                    'required'  => false,
                ]); ?>
                <button type="button" class="btn btn-success mt-3" id="j2commerce-save-parentoptionvalue-btn">
                    <?php echo Text::_('COM_J2COMMERCE_SAVE_CHANGES'); ?>
                </button>

                <div id="j2commerce-optionvalues-messages" class="mt-3"></div>
            </div>
        </div>
    <?php endif; ?>
</div>
