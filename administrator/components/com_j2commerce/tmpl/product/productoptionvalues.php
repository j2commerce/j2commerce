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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array $displayData */
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
    $options[$opvalue->j2commerce_optionvalue_id] = Text::_($opvalue->optionvalue_name);
}

// Build parent option values dropdown
$parentOptionArray = [];
foreach ($parentOptionValues as $parentOpvalue) {
    $parentOptionArray[$parentOpvalue->j2commerce_product_optionvalue_id] = $parentOpvalue->optionvalue_name ?? '';
}

$conSpan = 0;
?>
<div class="j2commerce px-lg-4">
    <form class="form-validate" id="adminForm" name="adminForm" method="post" action="index.php">
        <input type="hidden" name="option" value="com_j2commerce">
        <input type="hidden" name="view" value="products">
        <input type="hidden" name="tmpl" value="component">
        <input type="hidden" name="task" id="task" value="setDefault">
        <input type="hidden" name="optiontask" id="optiontask" value="">
        <input type="hidden" name="product_id" id="product_id" value="<?php echo $productId; ?>">
        <input type="hidden" name="productoption_id" id="productoption_id" value="<?php echo $productOptionId; ?>">
        <input type="hidden" name="boxchecked" value="">
        <?php echo HTMLHelper::_('form.token'); ?>

        <div class="note">
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_J2COMMERCE_PAO_SET_OPTIONS_FOR'); ?>: <?php echo htmlspecialchars($productOption->option_name ?? '', ENT_QUOTES, 'UTF-8'); ?></legend>
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <span class="fas fa-solid fa-exclamation-circle flex-shrink-0 me-2"></span>
                    <div><?php echo Text::_('COM_J2COMMERCE_PAO_ADD_NEW_OPTION'); ?></div>
                </div>
                <table class="adminlist table itemList">
                    <thead>
                    <tr>
                        <th scope="col"><?php $conSpan += 1; ?></th>
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
                        <th scope="col"><?php $conSpan += 1; ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td></td>
                        <td>
                            <select name="optionvalue_id" class="form-select">
                                <?php foreach ($options as $ovId => $ovName): ?>
                                    <option value="<?php echo $ovId; ?>"><?php echo htmlspecialchars($ovName, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                            <td>
                                <textarea name="product_optionvalue_attribs" class="form-control w-100 d-block" placeholder="<?php echo Text::_('COM_J2COMMERCE_PAO_FIELD_ATTRIBS_STYLE_HELP'); ?>" rows="1"></textarea>
                            </td>
                        <?php endif; ?>
                        <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                            <?php if (!empty($parentOptionValues)): ?>
                                <td>
                                    <select name="parent_optionvalue[]" class="form-select" multiple>
                                        <?php foreach ($parentOptionArray as $povId => $povName): ?>
                                            <option value="<?php echo $povId; ?>"><?php echo htmlspecialchars($povName, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php endif; ?>

                            <?php if (($productOption->is_variant ?? 0) != 1): ?>
                                <td>
                                    <select name="product_optionvalue_prefix" class="form-select">
                                        <option value="+">+</option>
                                        <option value="-">-</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="product_optionvalue_price" id="product_optionvalue_price" class="form-control" value="">
                                </td>
                                <td>
                                    <select name="product_optionvalue_weight_prefix" class="form-select">
                                        <option value="+">+</option>
                                        <option value="-">-</option>
                                    </select>
                                </td>
                            <?php endif; ?>
                            <td>
                                <input type="text" name="product_optionvalue_weight" id="product_optionvalue_weight" class="form-control" value="">
                            </td>
                        <?php endif; ?>

                        <td><input type="text" name="ordering" id="ordering" class="form-control" value="0"></td>

                        <td class="text-end">
                            <button class="btn btn-primary" type="button" onclick="document.getElementById('task').value='createproductoptionvalue'; document.adminForm.submit();">
                                <?php echo Text::_('COM_J2COMMERCE_PAO_CREATE_OPTION'); ?>
                            </button>
                        </td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="<?php echo $conSpan + 1; ?>">
                            <a class="btn btn-primary" id="add_all_option_value" href="#"><?php echo Text::_('COM_J2COMMERCE_ADD_ALL_OPTION_VALUE'); ?></a>
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </fieldset>
        </div>

        <div class="note_green">
            <fieldset class="options-form">
                <legend><?php echo Text::_('COM_J2COMMERCE_PAO_CURRENT_OPTIONS'); ?></legend>
                <div class="text-start">
                    <button class="btn btn-success btn-sm" type="button" onclick="document.getElementById('task').value='saveproductoptionvalue'; document.adminForm.submit();">
                        <?php echo Text::_('COM_J2COMMERCE_SAVE_CHANGES'); ?>
                    </button>
                </div>
                <table class="table itemList align-middle">
                    <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="checkall-toggle" name="checkall-toggle" value="" onclick="Joomla.checkAll(this);">
                        </th>
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
                                <?php if (in_array($product->product_type, ['simple', 'advancedvariable', 'booking'])): ?>
                                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_DEFAULT'); ?></th>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_OPTION_ORDERING'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $i = 0; $k = 0; ?>
                    <?php if (!empty($productOptionValues)): ?>
                        <?php foreach ($productOptionValues as $key => $poptionvalue): ?>
                            <tr class="row<?php echo $k; ?>">
                                <td>
                                    <?php echo HTMLHelper::_('grid.id', $i, $poptionvalue->j2commerce_product_optionvalue_id); ?>
                                    <input type="hidden" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][productoption_id]'; ?>" value="<?php echo $productOptionId; ?>">
                                    <input type="hidden" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][j2commerce_product_optionvalue_id]'; ?>" value="<?php echo $poptionvalue->j2commerce_product_optionvalue_id; ?>">
                                </td>

                                <td>
                                    <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][optionvalue_id]'; ?>" class="form-select">
                                        <?php foreach ($options as $ovId => $ovName): ?>
                                            <option value="<?php echo $ovId; ?>"<?php echo ($poptionvalue->optionvalue_id == $ovId) ? ' selected' : ''; ?>><?php echo htmlspecialchars($ovName, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <?php if ($product->product_type === 'variable' || $product->product_type === 'variablesubscriptionproduct'): ?>
                                    <td>
                                        <textarea name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_attribs]'; ?>" class="form-control w-100 d-block" placeholder="<?php echo Text::_('COM_J2COMMERCE_PAO_FIELD_ATTRIBS_STYLE_HELP'); ?>" rows="1"><?php echo htmlspecialchars($poptionvalue->product_optionvalue_attribs ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </td>
                                <?php endif; ?>
                                <?php if ($product->product_type !== 'variable' && $product->product_type !== 'variablesubscriptionproduct'): ?>
                                    <?php if (!empty($parentOptionValues)): ?>
                                        <td>
                                            <?php
                                            $currentParentValues = !empty($poptionvalue->parent_optionvalue) ? explode(',', $poptionvalue->parent_optionvalue) : [];
                                            ?>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][parent_optionvalue][]'; ?>" class="form-select" multiple>
                                                <?php foreach ($parentOptionArray as $povId => $povName): ?>
                                                    <option value="<?php echo $povId; ?>"<?php echo in_array($povId, $currentParentValues) ? ' selected' : ''; ?>><?php echo htmlspecialchars($povName, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endif; ?>

                                    <?php if (($productOption->is_variant ?? 0) != 1): ?>
                                        <td>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_prefix]'; ?>" class="form-select">
                                                <option value="+"<?php echo ($poptionvalue->product_optionvalue_prefix === '+') ? ' selected' : ''; ?>>+</option>
                                                <option value="-"<?php echo ($poptionvalue->product_optionvalue_prefix === '-') ? ' selected' : ''; ?>>-</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_price]'; ?>" id="product_optionvalue_price" class="form-control" value="<?php echo htmlspecialchars($poptionvalue->product_optionvalue_price ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <td>
                                            <select name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_weight_prefix]'; ?>" class="form-select">
                                                <option value="+"<?php echo ($poptionvalue->product_optionvalue_weight_prefix === '+') ? ' selected' : ''; ?>>+</option>
                                                <option value="-"<?php echo ($poptionvalue->product_optionvalue_weight_prefix === '-') ? ' selected' : ''; ?>>-</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][product_optionvalue_weight]'; ?>" id="product_optionvalue_weight" class="form-control" value="<?php echo htmlspecialchars($poptionvalue->product_optionvalue_weight ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                                        </td>
                                        <?php if (in_array($product->product_type, ['simple', 'advancedvariable', 'booking'])): ?>
                                            <td>
                                                <?php echo HTMLHelper::_('jgrid.isdefault', $poptionvalue->product_optionvalue_default ?? 0, $key, '', true, 'cb'); ?>
                                            </td>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <td>
                                    <input type="text" name="<?php echo $prefix . '[' . $poptionvalue->j2commerce_product_optionvalue_id . '][ordering]'; ?>" id="ordering" class="form-control" value="<?php echo htmlspecialchars($poptionvalue->ordering ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td>
                                    <?php $deleteUrl = Route::_('index.php?option=com_j2commerce&view=products&task=deleteProductOptionvalues&product_id=' . $productId . '&productoption_id=' . $poptionvalue->productoption_id . '&cid[]=' . $poptionvalue->j2commerce_product_optionvalue_id, false); ?>
                                    <a class="btn btn-danger" href="<?php echo $deleteUrl; ?>">
                                        <i class="icon icon-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php $i++; $k = 1 - $k; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </fieldset>
        </div>
    </form>
</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const addAllBtn = document.getElementById('add_all_option_value');
    if (addAllBtn) {
        addAllBtn.addEventListener('click', (e) => {
            e.preventDefault();

            const data = new FormData();
            data.append('option', 'com_j2commerce');
            data.append('view', 'products');
            data.append('task', 'addAllOptionValue');
            data.append('product_id', '<?php echo $productId; ?>');
            data.append('productoption_id', '<?php echo $productOptionId; ?>');
            data.append(Joomla.getOptions('csrf.token'), 1);

            addAllBtn.setAttribute('disabled', 'disabled');
            addAllBtn.insertAdjacentHTML('afterend', '<span class="wait spinner-border spinner-border-sm ms-2" role="status"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span></span>');

            fetch('index.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(json => {
                document.querySelector('.wait')?.remove();
                addAllBtn.removeAttribute('disabled');
                if (json.success) {
                    location.reload();
                }
            })
            .catch(() => {
                document.querySelector('.wait')?.remove();
                addAllBtn.removeAttribute('disabled');
            });
        });
    }

    // Custom listItemTask for default selection
    Joomla.listItemTask = function(id, task) {
        const f = document.adminForm;
        document.getElementById('optiontask').value = task;

        const cb = f[id];
        if (cb) {
            // Uncheck all checkboxes
            for (let i = 0; true; i++) {
                const cbx = f['cb' + i];
                if (!cbx) break;
                cbx.checked = false;
            }
            cb.checked = true;
            f.boxchecked.value = 1;

            const formData = new FormData(f);
            formData.append(Joomla.getOptions('csrf.token'), 1);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                }
            });
        }
        return false;
    };
});
</script>
