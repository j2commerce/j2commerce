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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;


$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

/**
 * Layout for AJAX-loaded product option values content.
 *
 * @var array $displayData
 */
$product = $displayData['product'];
$productOption = $displayData['product_option'];
$productId = $displayData['product_id'];
$productOptionId = $displayData['productoption_id'];
$optionValues = $displayData['option_values'];
$productOptionValues = $displayData['product_optionvalues'];
$parentOptionValues = $displayData['parent_optionvalues'];
$prefix = $displayData['prefix'];

// Build option values dropdown
$options = [];
foreach ($optionValues as $opvalue) {
    // Option value names are data, not language keys - don't use Text::_()
    $options[$opvalue->j2commerce_optionvalue_id] = $opvalue->optionvalue_name;
}

// Build parent option values dropdown
$parentOptionArray = [];
foreach ($parentOptionValues as $parentOpvalue) {
    $parentOptionArray[$parentOpvalue->j2commerce_product_optionvalue_id] = $parentOpvalue->optionvalue_name ?? '';
}

$conSpan = 0;


$style = '.j2commerce-ajax-optionvalues{};';
$wa->addInlineStyle($style, [], []);
?>
<div class="j2commerce-ajax-optionvalues" data-product-id="<?php echo $productId; ?>" data-productoption-id="<?php echo $productOptionId; ?>">
    <!-- Add New Option Value Section -->
    <div class="card box-shadow-none mb-3">
        <div class="card-header">
            <h3 class="mb-0 fs-6"><?php echo Text::_('COM_J2COMMERCE_PAO_ADD_NEW_OPTION'); ?></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table itemList">
                    <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_NAME'); $conSpan += 1; ?></th>
                        <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_FIELDATTRIBS'); $conSpan += 1; ?></th>
                        <?php endif; ?>
                        <?php if (($productOption->is_variant ?? 0) != 1): ?>
                            <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                                <?php if (!empty($parentOptionValues)): ?>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PARENT_OPTION_NAME'); $conSpan += 1; ?></th>
                                <?php endif; ?>

                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PREFIX'); $conSpan += 1; ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PRICE'); $conSpan += 1; ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_WEIGHT_PREFIX'); $conSpan += 1; ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_WEIGHT'); $conSpan += 1; ?></th>
                            <?php endif; ?>
                        <?php endif; ?>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING'); $conSpan += 1; ?></th>
                        <th scope="col" class="text-end"><?php $conSpan += 1; ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>
                            <select name="optionvalue_id" id="j2commerce_new_optionvalue_id" class="form-select form-select-sm">
                                <?php foreach ($options as $ovId => $ovName): ?>
                                    <option value="<?php echo $ovId; ?>"><?php echo htmlspecialchars($ovName, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                            <td>
                                <textarea name="product_optionvalue_attribs" id="j2commerce_new_attribs" class="form-control form-control-sm w-100 d-block" placeholder="<?php echo Text::_('COM_J2COMMERCE_PAO_FIELD_ATTRIBS_STYLE_HELP'); ?>" rows="1"></textarea>
                            </td>
                        <?php endif; ?>
                        <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                            <?php if (!empty($parentOptionValues)): ?>
                                <td>
                                    <select name="parent_optionvalue[]" id="j2commerce_new_parent" class="form-select form-select-sm" multiple>
                                        <?php foreach ($parentOptionArray as $povId => $povName): ?>
                                            <option value="<?php echo $povId; ?>"><?php echo htmlspecialchars($povName, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endif; ?>

                            <?php if (($productOption->is_variant ?? 0) != 1): ?>
                                <td>
                                    <select name="product_optionvalue_prefix" id="j2commerce_new_price_prefix" class="form-select form-select-sm" style="width: 80px;">
                                        <option value="+" selected>+</option>
                                        <option value="-">-</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="product_optionvalue_price" id="j2commerce_new_price" class="form-control form-control-sm" value="" style="width: 80px;">
                                </td>
                                <td>
                                    <select name="product_optionvalue_weight_prefix" id="j2commerce_new_weight_prefix" class="form-select form-select-sm" style="width: 80px;">
                                        <option value="+" selected>+</option>
                                        <option value="-">-</option>
                                    </select>
                                </td>
                            <?php endif; ?>
                            <td>
                                <input type="text" name="product_optionvalue_weight" id="j2commerce_new_weight" class="form-control form-control-sm" value="" style="width: 80px;">
                            </td>
                        <?php endif; ?>

                        <td>
                            <input type="text" name="ordering" id="j2commerce_new_ordering" class="form-control form-control-sm" value="0" style="width: 60px;">
                        </td>

                        <td class="text-end">
                            <button class="btn btn-primary btn-sm" type="button" id="j2commerce-create-optionvalue-btn">
                                <span class="icon-plus"></span> <?php echo Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'); ?>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="<?php echo $conSpan + 1; ?>">
                            <button class="btn btn-outline-primary btn-sm" type="button" id="j2commerce-add-all-optionvalues-btn">
                                <span class="icon-list"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'); ?>
                            </button>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Current Option Values Section -->
    <div class="card box-shadow-none">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0 fs-6"><?php echo Text::_('COM_J2COMMERCE_PAO_CURRENT_OPTIONS'); ?></h3>
            <button class="btn btn-success btn-sm" type="button" id="j2commerce-save-optionvalues-btn">
                <?php echo Text::_('COM_J2COMMERCE_SAVE_CHANGES'); ?>
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table itemList" id="j2commerce-optionvalues-table">
                    <thead>
                    <tr>
                        <th scope="col" style="width: 40px;"><span class="visually-hidden"><?php echo Text::_('JGRID_HEADING_ORDERING'); ?></span></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_NAME'); ?></th>
                        <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                            <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_FIELDATTRIBS'); ?></th>
                        <?php endif; ?>
                        <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                            <?php if (!empty($parentOptionValues)): ?>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PARENT_OPTION_NAME'); ?></th>
                            <?php endif; ?>

                            <?php if (($productOption->is_variant ?? 0) != 1): ?>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PREFIX'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_PRICE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_WEIGHT_PREFIX'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_PAO_WEIGHT'); ?></th>
                                <?php if (in_array($product->product_type, ['simple', 'advancedvariable', 'booking', 'configurable'])): ?>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_DEFAULT'); ?></th>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING'); ?></th>
                        <th scope="col" style="width: 60px;"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></span></th>
                    </tr>
                    </thead>
                    <tbody id="j2commerce-optionvalues-tbody">
                    <?php $i = 0; $k = 0; ?>
                    <?php if (!empty($productOptionValues)): ?>
                        <?php foreach ($productOptionValues as $key => $poptionvalue): ?>
                            <tr class="row<?php echo $k; ?>" data-pov-id="<?php echo $poptionvalue->j2commerce_product_optionvalue_id; ?>">
                                <td>
                                    <span class="icon-menu text-muted" title="<?php echo Text::_('JGRID_HEADING_ORDERING'); ?>"></span>
                                    <input type="hidden" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][productoption_id]'; ?>" value="<?php echo $productOptionId; ?>">
                                    <input type="hidden" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][j2commerce_product_optionvalue_id]'; ?>" value="<?php echo $poptionvalue->j2commerce_product_optionvalue_id; ?>">
                                </td>

                                <td>
                                    <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][optionvalue_id]'; ?>" class="form-select form-select-sm">
                                        <?php foreach ($options as $ovId => $ovName): ?>
                                            <option value="<?php echo $ovId; ?>"<?php echo ($poptionvalue->optionvalue_id == $ovId) ? ' selected' : ''; ?>><?php echo htmlspecialchars($ovName, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                                    <td>
                                        <textarea name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_attribs]'; ?>" class="form-control form-control-sm w-100 d-block" placeholder="<?php echo Text::_('COM_J2COMMERCE_PAO_FIELD_ATTRIBS_STYLE_HELP'); ?>" rows="1"><?php echo htmlspecialchars($poptionvalue->product_optionvalue_attribs ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </td>
                                <?php endif; ?>
                                <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                                    <?php if (!empty($parentOptionValues)): ?>
                                        <td>
                                            <?php
                                            $currentParentValues = !empty($poptionvalue->parent_optionvalue) ? explode(',', $poptionvalue->parent_optionvalue) : [];
                                            ?>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][parent_optionvalue][]'; ?>" class="form-select form-select-sm" multiple>
                                                <?php foreach ($parentOptionArray as $povId => $povName): ?>
                                                    <option value="<?php echo $povId; ?>"<?php echo in_array($povId, $currentParentValues) ? ' selected' : ''; ?>><?php echo htmlspecialchars($povName, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endif; ?>

                                    <?php if (($productOption->is_variant ?? 0) != 1): ?>
                                        <td>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_prefix]'; ?>" class="form-select form-select-sm" style="width: 80px;">
                                                <option value="+"<?php echo ($poptionvalue->product_optionvalue_prefix === '+') ? ' selected' : ''; ?>>+</option>
                                                <option value="-"<?php echo ($poptionvalue->product_optionvalue_prefix === '-') ? ' selected' : ''; ?>>-</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_price]'; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($poptionvalue->product_optionvalue_price ?? '0', ENT_QUOTES, 'UTF-8'); ?>" style="width: 80px;">
                                        </td>
                                        <td>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_weight_prefix]'; ?>" class="form-select form-select-sm" style="width: 80px;">
                                                <option value="+"<?php echo ($poptionvalue->product_optionvalue_weight_prefix === '+') ? ' selected' : ''; ?>>+</option>
                                                <option value="-"<?php echo ($poptionvalue->product_optionvalue_weight_prefix === '-') ? ' selected' : ''; ?>>-</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_weight]'; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($poptionvalue->product_optionvalue_weight ?? '0', ENT_QUOTES, 'UTF-8'); ?>" style="width: 80px;">
                                        </td>
                                        <?php if (in_array($product->product_type, ['simple', 'advancedvariable', 'booking', 'configurable'])): ?>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm j2commerce-set-default-btn btn-link <?php echo ($poptionvalue->product_optionvalue_default ?? 0) ? 'text-warning' : 'text-muted'; ?>" data-pov-id="<?php echo $poptionvalue->j2commerce_product_optionvalue_id; ?>" title="<?php echo Text::_('COM_J2COMMERCE_SET_AS_DEFAULT'); ?>">
                                                    <span class="icon-star<?php echo ($poptionvalue->product_optionvalue_default ?? 0) ? '' : '-empty'; ?>"></span>
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <td>
                                    <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][ordering]'; ?>" class="form-control form-control-sm" value="<?php echo htmlspecialchars($poptionvalue->ordering ?? '0', ENT_QUOTES, 'UTF-8'); ?>" style="width: 60px;">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-link btn-sm j2commerce-delete-optionvalue-btn text-danger" data-pov-id="<?php echo $poptionvalue->j2commerce_product_optionvalue_id; ?>" title="<?php echo Text::_('JACTION_DELETE'); ?>">
                                        <span class="fa-solid fa-trash" aria-hidden="true"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php $i++; $k = 1 - $k; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="j2commerce-no-values-row">
                            <td colspan="10" class="text-center text-muted py-4">
                                <?php echo Text::_('COM_J2COMMERCE_NO_OPTION_VALUES_ASSIGNED'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Status message area -->
    <div id="j2commerce-optionvalues-messages" class="mt-3"></div>
</div>
