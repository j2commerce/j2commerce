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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

J2CommerceHelper::plugin()->importCatalogPlugins();

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$style = '.autocomplete-list{background: var(--form-control-bg);max-height: 200px;overflow-y: auto;width: 100%;}.autocomplete-list.autocomplete-active{border: var(--form-control-border);}.autocomplete-item{padding: 8px;cursor: pointer;font-size: .8rem;}.autocomplete-item:hover {background-color: #f0f0f0;}';
$wa->addInlineStyle($style, [], []);

$item = $displayData['product'];
$formPrefix = $displayData['form_prefix'] ?? 'jform[attribs][j2commerce]';

// Defaults for Joomla core layout fields to prevent PHP 8.4 undefined variable warnings
$textFieldDefaults = ['value' => '', 'onchange' => '', 'disabled' => false, 'readonly' => false, 'dataAttribute' => '', 'hint' => '', 'required' => false, 'autofocus' => false, 'spellcheck' => false, 'addonBefore' => '', 'addonAfter' => '', 'dirname' => '', 'charcounter' => false, 'options' => []];
?>
<div class="j2commerce-product-relations">
    <div class="alert alert-info alert-block">
        <strong><?php echo Text::_('COM_J2COMMERCE_NOTE'); ?></strong> <?php echo Text::_('COM_J2COMMERCE_FEATURE_AVAILABLE_IN_COM_J2COMMERCE_PRODUCT_LAYOUTS'); ?>
    </div>
    <fieldset class="options-form product-upsells mb-4">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_UP_SELLS');?></legend>
        <div class="table-responsive">
            <table class="table itemList">
                <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_NAME');?></th>
                    <th scope="col" class="text-center w-1"><?php echo Text::_('COM_J2COMMERCE_REMOVE');?></th>
                </tr>
                </thead>
                <tbody id="addedProductUpsell">
                <?php
                if(isset($item->up_sells) && !empty($item->up_sells)):
                    $upsells = J2CommerceHelper::product()->getRelatedProducts($item->up_sells);
                    ?>
                    <?php foreach($upsells as $key=>$related_product):?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterGetProduct', array($related_product))->getArgument('html', ''); ?>
                        <?php if(isset($related_product->product_source_id)):?>
                            <tr id="upSell-<?php echo $related_product->j2commerce_product_id;?>">
                                <td class="addedProductUpsell">
                                    <?php if(Factory::getApplication()->isClient('site')):?>
                                        <?php echo isset($related_product->sku) && !empty($related_product->sku) ? $this->escape($related_product->product_name)." (".$this->escape($related_product->sku).")" : $related_product->product_name;?>
                                    <?php else: ?>
                                        <a href="<?php echo $related_product->product_edit_url; ?>" target="_blank">
                                            <?php echo isset($related_product->sku) && !empty($related_product->sku) ? $this->escape($related_product->product_name)." (".$this->escape($related_product->sku).")" : $related_product->product_name;?>
                                        </a>
                                    <?php endif; ?>
                                    <input type="hidden" value="<?php echo $related_product->j2commerce_product_id;?>"  name="<?php echo $formPrefix.'[up_sells]' ;?>[<?php echo $related_product->j2commerce_product_id;?>]" />
                                </td>
                                <td class="text-center">
                                    <a href="javascript:void(0);" onclick="removeThisRelatedRow('upSell',<?php echo $related_product->j2commerce_product_id;?>)">
                                        <span class="icon icon-trash text-danger"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endif;?>
                    <?php endforeach;?>
                <?php endif;?>
                </tbody>
                <tbody>
                <tr>
                    <td colspan="2">
                        <small><strong><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_RELATED_PRODUCTS');?></strong></small>
                        <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => 'J2CommerceupsellSelector','id'    => 'J2CommerceupsellSelector','value' => '','class' => 'form-control ms-2',] + $textFieldDefaults);?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </fieldset>
    <fieldset class="options-form product-crosssells mb-4">
        <legend><?php echo Text::_('COM_J2COMMERCE_PRODUCT_CROSS_SELLS');?></legend>
        <div class="table-responsive">
            <table class="table itemList">
                <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_NAME');?></th>
                    <th scope="col" class="text-center w-1"><?php echo Text::_('COM_J2COMMERCE_REMOVE');?></th>
                </tr>
                </thead>
                <tbody id="addedProductCrosssell">
                <?php if(isset($item->cross_sells) && !empty($item->cross_sells)):
                    $crosssells = J2CommerceHelper::product()->getRelatedProducts($item->cross_sells);

                    ?>
                    <?php foreach($crosssells as $key=>$related_product): ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterGetProduct', array($related_product))->getArgument('html', ''); ?>
                        <?php if(isset($related_product->product_source_id)):?>
                        <tr id="crossSell-<?php echo $related_product->j2commerce_product_id;?>">
                            <td class="addedProductCrosssell">
                                <?php if(Factory::getApplication()->isClient('site')):?>
                                    <?php echo isset($related_product->sku) && !empty($related_product->sku) ? $this->escape($related_product->product_name).' ('.$this->escape($related_product->sku).')' : $this->escape($related_product->product_name);?>
                                <?php else: ?>
                                    <a href="index.php?option=com_content&task=article.edit&id=<?php echo $related_product->product_source_id;?>" target="_blank">
                                        <?php echo isset($related_product->sku) && !empty($related_product->sku) ? $this->escape($related_product->product_name).' ('.$this->escape($related_product->sku).')' : $this->escape($related_product->product_name);?>
                                    </a>
                                <?php endif;?>
                                <input type="hidden" value="<?php echo $related_product->j2commerce_product_id;?>" name="<?php echo $formPrefix.'[cross_sells]' ;?>[<?php echo $related_product->j2commerce_product_id;?>]" />
                            </td>
                            <td class="text-center">
                                <a href="javascript:void(0);" onclick="removeThisRelatedRow('crossSell',<?php echo $related_product->j2commerce_product_id;?>)">
                                    <span class="icon icon-trash text-danger"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endif;?>
                <?php endforeach;?>
                <?php endif;?>
                </tbody>
                <tbody>
                <tr>
                    <td colspan="2">
                        <small><strong><?php echo Text::_('COM_J2COMMERCE_SEARCH_AND_RELATED_PRODUCTS');?></strong></small>
                        <?php echo LayoutHelper::render('joomla.form.field.text', ['name'  => 'J2CommercecrossSellSelector','id'    => 'J2CommercecrossSellSelector','value' => '','class' => 'form-control ms-2',] + $textFieldDefaults);?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </fieldset>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var productId = <?php echo (int) $item->j2commerce_product_id; ?>;
    var formPrefix = '<?php echo $formPrefix; ?>';

    /**
     * Setup autocomplete for related products (upsells/cross-sells)
     * @param {string} inputId - The input element ID
     * @param {string} tbodyId - The tbody element ID to append rows
     * @param {string} rowPrefix - Prefix for row IDs (upSell or crossSell)
     * @param {string} fieldName - Form field name (up_sells or cross_sells)
     */
    function setupRelatedProductAutocomplete(inputId, tbodyId, rowPrefix, fieldName) {
        var input = document.getElementById(inputId);
        if (!input) return;

        var autocompleteList;

        // Create autocomplete container
        function createAutocompleteContainer() {
            autocompleteList = document.createElement('div');
            autocompleteList.className = 'autocomplete-list';
            autocompleteList.style.position = 'absolute';
            autocompleteList.style.width = '350px';
            autocompleteList.style.zIndex = '1000';
            input.parentNode.style.position = 'relative';
            input.parentNode.appendChild(autocompleteList);
        }

        function updateAutocompleteListState() {
            if (autocompleteList.children.length > 0) {
                autocompleteList.classList.add('autocomplete-active');
            } else {
                autocompleteList.classList.remove('autocomplete-active');
            }
        }

        createAutocompleteContainer();

        // Handle input for autocomplete
        input.addEventListener('input', function() {
            var term = this.value;
            if (term.length < 2) {
                autocompleteList.innerHTML = '';
                updateAutocompleteListState();
                return;
            }

            var searchData = {
                option: 'com_j2commerce',
                task: 'products.getRelatedProducts',
                format: 'raw',
                product_id: productId,
                q: term
            };

            var formData = new URLSearchParams(searchData).toString();

            input.classList.add('optionsLoading');

            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                input.classList.remove('optionsLoading');
                autocompleteList.innerHTML = '';

                if (data.products && data.products.length > 0) {
                    data.products.forEach(function(item) {
                        var option = document.createElement('div');
                        option.className = 'autocomplete-item';
                        option.textContent = item.product_name;
                        option.dataset.value = item.j2commerce_product_id;
                        option.dataset.label = item.product_name;

                        // Handle item selection
                        option.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();

                            var value = this.dataset.value;
                            var label = this.dataset.label;

                            // Check if already added
                            if (document.getElementById(rowPrefix + '-' + value)) {
                                input.value = '';
                                autocompleteList.innerHTML = '';
                                updateAutocompleteListState();
                                return;
                            }

                            // Escape label for safe HTML insertion
                            var escapedLabel = label.replace(/&/g, '&amp;')
                                                    .replace(/</g, '&lt;')
                                                    .replace(/>/g, '&gt;')
                                                    .replace(/"/g, '&quot;')
                                                    .replace(/'/g, '&#039;');

                            // Create new row
                            var newRow = document.createElement('tr');
                            newRow.id = rowPrefix + '-' + value;
                            newRow.innerHTML = '<td class="addedProduct' + rowPrefix.charAt(0).toUpperCase() + rowPrefix.slice(1) + '">' +
                                escapedLabel +
                                '<input type="hidden" value="' + value + '" name="' + formPrefix + '[' + fieldName + '][' + value + ']" />' +
                                '</td>' +
                                '<td class="text-center">' +
                                '<a href="javascript:void(0);" onclick="removeThisRelatedRow(\'' + rowPrefix + '\',' + value + ')">' +
                                '<span class="icon icon-trash text-danger"></span>' +
                                '</a>' +
                                '</td>';

                            document.getElementById(tbodyId).appendChild(newRow);
                            input.value = '';
                            autocompleteList.innerHTML = '';
                            updateAutocompleteListState();
                        });

                        autocompleteList.appendChild(option);
                    });
                }

                updateAutocompleteListState();
            })
            .catch(function(error) {
                console.error('Error fetching related products:', error);
                input.classList.remove('optionsLoading');
            });
        });

        // Close autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!input.contains(e.target) && !autocompleteList.contains(e.target)) {
                autocompleteList.innerHTML = '';
                updateAutocompleteListState();
            }
        });
    }

    // Setup autocomplete for upsells
    setupRelatedProductAutocomplete('J2CommerceupsellSelector', 'addedProductUpsell', 'upSell', 'up_sells');

    // Setup autocomplete for cross-sells
    setupRelatedProductAutocomplete('J2CommercecrossSellSelector', 'addedProductCrosssell', 'crossSell', 'cross_sells');
});

/**
 * Remove a related product row
 * @param {string} type - Row type prefix (upSell or crossSell)
 * @param {number} p_id - Product ID
 */
function removeThisRelatedRow(type, p_id) {
    var row = document.getElementById(type + '-' + p_id);
    if (row) {
        row.remove();
    }
}
</script>
