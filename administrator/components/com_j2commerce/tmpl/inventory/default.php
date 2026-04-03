<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

J2CommerceHelper::strapper()->addCSS();

$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$user = $app->getIdentity();

// Load necessary assets
$wa->useScript('core')
    ->useScript('multiselect')
    ->useScript('table.columns');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
// Inventory view does not support manual ordering, so disable draggable functionality
$saveOrder = false;

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_j2commerce&task=inventory.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}

// Add custom CSS and JavaScript for inventory management
$wa->addInlineStyle('
.inventory-row { border-bottom: 1px solid #ddd; }
.inventory-row:hover { background-color: #f9f9f9; }
.save-btn { margin: 2px; padding: 4px 8px; font-size: 12px; }
.quantity-input, .stock-select { width: 80px; margin: 2px; }
.manage-stock-toggle { margin: 2px; }
.product-link { font-weight: bold; }
.inventory-actions { text-align: center; min-width: 100px; }
.j2commerce-inventory-quantity > .control-group, .j2commerce-inventory-manage-stock > .control-group, .j2commerce-inventory-stock_status > .control-group {margin-bottom: 0;}
.variants-row { background-color: #f8f9fa; }
.variant-item {margin-bottom: 5px; }
.inventory-row .control-group .controls {min-width:80px;}

.variants-container { padding: 10px; background-color: #fff; border: 1px solid #dee2e6; border-radius: 3px; }
.has-variants .inventory-fields { display: none; }
');

$wa->addInlineScript('
document.addEventListener("DOMContentLoaded", function() {
    const csrfToken = "' . Session::getFormToken() . '";

    function getFieldValue(row, selector) {
        const el = row.querySelector(selector);
        return el ? el.value : "";
    }

    function getManageStockValue(row) {
        // Check for Joomla radio switcher (btn-check inputs)
        const checked = row.querySelector(".manage-stock-toggle:checked, input[name*=manage_stock]:checked");
        if (checked) return checked.value;
        // Fallback: look for any checked radio in the manage-stock cell
        const cell = row.querySelector(".j2commerce-inventory-manage-stock, [class*=manage]");
        if (cell) {
            const radio = cell.querySelector("input[type=radio]:checked");
            if (radio) return radio.value;
        }
        return "0";
    }

    function showSystemMessage(message, type) {
        const container = document.getElementById("system-message-container");
        if (!container) return;
        const alertClass = type === "success" ? "alert-success" : "alert-danger";
        container.innerHTML = "<div class=\"alert " + alertClass + " alert-dismissible fade show\" role=\"alert\">"
            + message
            + "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>"
            + "</div>";
        setTimeout(function() {
            const alert = container.querySelector(".alert");
            if (alert) { alert.style.transition = "opacity 0.5s"; alert.style.opacity = "0"; setTimeout(function() { alert.remove(); }, 500); }
        }, 5000);
    }

    function setBtnState(btn, state, originalText) {
        btn.classList.remove("btn-primary", "btn-success", "btn-danger");
        if (state === "saving") {
            btn.classList.add("btn-primary");
            btn.disabled = true;
            btn.textContent = "' . Text::_('COM_J2COMMERCE_SAVING') . '...";
        } else if (state === "success") {
            btn.classList.add("btn-success");
            btn.textContent = "' . Text::_('COM_J2COMMERCE_SAVED') . '";
            setTimeout(function() { btn.classList.remove("btn-success"); btn.classList.add("btn-primary"); btn.textContent = originalText; }, 2000);
        } else if (state === "error") {
            btn.classList.add("btn-danger");
            btn.textContent = "' . Text::_('COM_J2COMMERCE_ERROR') . '";
            setTimeout(function() { btn.classList.remove("btn-danger"); btn.classList.add("btn-primary"); btn.textContent = originalText; }, 3000);
        }
    }

    // AJAX save for individual inventory items
    window.saveInventoryItem = function(productId, variantId) {
        const row = document.getElementById("inventory-row-" + productId);
        if (!row) return;
        const quantity = getFieldValue(row, ".quantity-input, input[name*=quantity]");
        const manageStock = getManageStockValue(row);
        const availability = getFieldValue(row, ".stock-select, select[name*=availability]");
        const saveBtn = row.querySelector(".save-btn");
        if (!saveBtn) return;
        const originalText = saveBtn.textContent;
        setBtnState(saveBtn, "saving", originalText);

        const body = new URLSearchParams();
        body.set("product_id", productId);
        body.set("variant_id", variantId);
        body.set("quantity", quantity);
        body.set("manage_stock", manageStock);
        body.set("availability", availability);
        body.set(csrfToken, "1");

        fetch("index.php?option=com_j2commerce&task=inventory.saveItem", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json" },
            body: body.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                setBtnState(saveBtn, "success", originalText);
                if (response.message) showSystemMessage(response.message, "success");
            } else {
                setBtnState(saveBtn, "error", originalText);
                if (response.message) showSystemMessage(response.message, "error");
            }
        })
        .catch(function() {
            setBtnState(saveBtn, "error", originalText);
            showSystemMessage("' . Text::_('COM_J2COMMERCE_INVENTORY_AJAX_ERROR') . '", "error");
        })
        .finally(function() { saveBtn.disabled = false; });
    };

    // AJAX save for individual variant items
    window.saveVariantItem = function(variantId, productId) {
        const row = document.getElementById("variant-row-" + variantId);
        if (!row) return;
        const quantity = getFieldValue(row, ".quantity-input, input[name*=quantity]");
        const manageStock = getManageStockValue(row);
        const availability = getFieldValue(row, ".stock-select, select[name*=availability]");
        const saveBtn = row.querySelector(".save-btn");
        if (!saveBtn) return;
        const originalText = saveBtn.textContent;
        setBtnState(saveBtn, "saving", originalText);

        const body = new URLSearchParams();
        body.set("product_id", productId);
        body.set("variant_id", variantId);
        body.set("quantity", quantity);
        body.set("manage_stock", manageStock);
        body.set("availability", availability);
        body.set(csrfToken, "1");

        fetch("index.php?option=com_j2commerce&task=inventory.saveItem", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json" },
            body: body.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                setBtnState(saveBtn, "success", originalText);
                if (response.message) showSystemMessage(response.message, "success");
            } else {
                setBtnState(saveBtn, "error", originalText);
                if (response.message) showSystemMessage(response.message, "error");
            }
        })
        .catch(function() {
            setBtnState(saveBtn, "error", originalText);
            showSystemMessage("' . Text::_('COM_J2COMMERCE_INVENTORY_AJAX_ERROR') . '", "error");
        })
        .finally(function() { saveBtn.disabled = false; });
    };

    // Toggle variant visibility (fallback — Bootstrap collapse handles this via data-bs-toggle)
    window.toggleVariants = function(productId) {
        const variantsRow = document.getElementById("variants-" + productId);
        const toggleBtn = document.getElementById("toggle-variants-" + productId);
        if (!variantsRow) return;
        const isVisible = variantsRow.style.display !== "none" && variantsRow.classList.contains("show");
        if (isVisible) {
            variantsRow.classList.remove("show");
            if (toggleBtn) toggleBtn.textContent = "' . Text::_('COM_J2COMMERCE_SHOW_VARIANTS') . '";
        } else {
            variantsRow.classList.add("show");
            if (toggleBtn) toggleBtn.textContent = "' . Text::_('COM_J2COMMERCE_HIDE_VARIANTS') . '";
        }
    };
});
');




?>

<?php echo $this->navbar; ?>

<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=inventory'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_NO_ITEMS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table align-middle" id="inventoryList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_J2COMMERCE_INVENTORY_TABLE_CAPTION'); ?>
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?></span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_INVENTORY_PRODUCT_ID', 'p.j2commerce_product_id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-25">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_INVENTORY_PRODUCT_NAME', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_INVENTORY_SKU', 'v.sku', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 text-start">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_J2COMMERCE_INVENTORY_QUANTITY', 'pq.quantity', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 text-start">
                                    <?php echo Text::_('COM_J2COMMERCE_INVENTORY_MANAGE_STOCK'); ?>
                                </th>
                                <th scope="col" class="w-15 text-start">
                                    <?php echo Text::_('COM_J2COMMERCE_INVENTORY_STOCK_STATUS'); ?>
                                </th>
                                <th scope="col" class="w-10 text-center">
                                    <?php echo Text::_('COM_J2COMMERCE_INVENTORY_ACTIONS'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
                            <?php
                            $returnUrl = base64_encode('index.php?option=com_j2commerce&view=inventory');
                            foreach ($this->items as $i => $item) :
                                $canEdit = $user->authorise('core.edit', 'com_j2commerce');

                                // Get fresh form instance and bind data for this inventory item
                                $form = $this->getModel()->getForm();
                                if ($form) {
                                    $itemData = [
                                        'j2commerce_product_id' => $item->j2commerce_product_id,
                                        'j2commerce_variant_id' => $item->j2commerce_variant_id,
                                        'quantity' => $item->quantity,
                                        'manage_stock' => (string) $item->manage_stock,
                                        'availability' => (string) $item->availability
                                    ];
                                    $form->bind($itemData);
                                }
                            ?>
                            <tr class="row<?php echo $i % 2; ?> inventory-row align-items-center" id="inventory-row-<?php echo $item->j2commerce_product_id; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->j2commerce_product_id, false, 'cid', 'cb', $item->product_name); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->j2commerce_product_id); ?>
                                </td>
                                <th scope="row" class="j2commerce-inventory-product has-context">
                                    <div class="break-word">
                                        <?php if ($canEdit && $item->product_source_id) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_content&task=article.edit&id=' . $item->product_source_id . '&return=' . $returnUrl); ?>" class="product-link">
                                                <?php echo $this->escape($item->product_name); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="product-link"><?php echo $this->escape($item->product_name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <td class="text-start j2commerce-inventory-sku">
                                    <?php if ($item->has_options == 1) : ?>
                                        <button type="button" class="btn btn-sm btn-outline-info position-relative" data-bs-toggle="collapse" data-bs-target="#variants-<?php echo $item->j2commerce_product_id; ?>" aria-expanded="false">
                                            <?php echo Text::_('COM_J2COMMERCE_VARIANTS'); ?>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary"><?php echo count($item->variants ?? []); ?><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_VARIANT_COUNT');?></span></span>
                                        </button>
                                    <?php else : ?>
                                        <span class="font-monospace"><?php echo $this->escape($item->sku ?? ''); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($item->has_options == 1) : ?>
                                    <!-- Product has variants - empty cells for quantity, stock management, stock status, and actions -->
                                    <td class="text-center">
                                        <span class="text-muted">—</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted">—</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted">—</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-muted">—</span>
                                    </td>
                                <?php else : ?>
                                    <!-- Single variant product - show regular fields -->
                                    <td class="text-center j2commerce-inventory-quantity">
                                        <?php if ($form) : ?>
                                            <?php echo $form->renderField('quantity', null, $item->quantity, null, null, null, ['hiddenLabel' => true]); ?>
                                        <?php else : ?>
                                            <input type="number"
                                                   class="form-control quantity-input"
                                                   value="<?php echo (int) $item->quantity; ?>"
                                                   min="0"
                                                   step="1" />
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center j2commerce-inventory-manage-stock">
                                        <?php if ($form) : ?>
                                            <?php
                                            // Create unique field name for this row and ensure proper value binding
                                            $manageStockFieldName = 'manage_stock_' . $item->j2commerce_product_id;
                                            $manageStockValue = (string) $item->manage_stock;

                                            // Render the field with unique name and proper value
                                            $manageStockHtml = $form->renderField('manage_stock', null, $manageStockValue, null, null, null, ['hiddenLabel' => true]);

                                            // Replace name and id to be unique for this row
                                            $manageStockHtml = str_replace(
                                                'name="jform[manage_stock]"',
                                                'name="jform[' . $manageStockFieldName . ']"',
                                                $manageStockHtml
                                            );
                                            $manageStockHtml = str_replace(
                                                'id="jform_manage_stock',
                                                'id="jform_' . $manageStockFieldName,
                                                $manageStockHtml
                                            );

                                            echo $manageStockHtml;
                                            ?>
                                        <?php else : ?>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input manage-stock-toggle" type="checkbox" role="switch" <?php echo $item->manage_stock ? 'checked' : ''; ?> />
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center j2commerce-inventory-stock_status">
                                        <?php if ($form) : ?>
                                            <?php echo $form->renderField('availability', null, $item->availability, null, null, null, ['hiddenLabel' => true]); ?>
                                        <?php else : ?>
                                            <select class="form-select stock-select">
                                                <option value="1" <?php echo ($item->availability == 1) ? 'selected' : ''; ?>>
                                                    <?php echo Text::_('COM_J2COMMERCE_STOCK_IN_STOCK'); ?>
                                                </option>
                                                <option value="0" <?php echo ($item->availability == 0) ? 'selected' : ''; ?>>
                                                    <?php echo Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK'); ?>
                                                </option>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center inventory-actions">
                                        <button type="button" class="btn btn-sm btn-primary save-btn" onclick="saveInventoryItem(<?php echo $item->j2commerce_product_id; ?>, <?php echo $item->j2commerce_variant_id ?: 0; ?>)">
                                            <?php echo Text::_('COM_J2COMMERCE_SAVE'); ?>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($item->has_options == 1 && !empty($item->variants)) : ?>
                                <!-- Collapsible variants row -->
                                <tr id="variants-<?php echo $item->j2commerce_product_id; ?>" class="collapse variants-row">
                                    <td colspan="8">
                                        <div class="variants-container">

                                            <?php foreach ($item->variants as $vIndex => $variant) :
                                                // Get variant form for each variant
                                                $variantForm = $this->getModel()->getVariantForm();
                                                if ($variantForm) {
                                                    $variantData = [
                                                        'j2commerce_variant_id' => $variant->j2commerce_variant_id,
                                                        'product_id' => $variant->product_id,
                                                        'quantity' => $variant->quantity,
                                                        'manage_stock' => (string) $variant->manage_stock,
                                                        'availability' => (string) $variant->availability
                                                    ];
                                                    $variantForm->bind($variantData);
                                                }
                                            ?>
                                                <div class="row variant-item <?php echo $variant->is_master ? 'variant-master' : ''; ?>" id="variant-row-<?php echo $variant->j2commerce_variant_id; ?>">
                                                    <div class="col-md-3">
                                                        <strong>
                                                            <?php if ($variant->is_master) : ?>
                                                                <span class="badge bg-success me-1"><?php echo Text::_('COM_J2COMMERCE_MASTER'); ?></span>
                                                            <?php endif; ?>
                                                            <?php echo Text::_('COM_J2COMMERCE_VARIANT'); ?> #<?php echo $variant->j2commerce_variant_id; ?>
                                                        </strong>
                                                        <?php if (!empty($variant->sku)) : ?>
                                                            <div class="variant-sku">SKU: <?php echo $this->escape($variant->sku); ?></div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <label class="form-label small"><?php echo Text::_('COM_J2COMMERCE_VARIANTS_QUANTITY'); ?>:</label>
                                                        <?php if ($variantForm) : ?>
                                                            <?php echo $variantForm->renderField('quantity', null, $variant->quantity, null, null, null, ['hiddenLabel' => true]); ?>
                                                        <?php else : ?>
                                                            <input type="number"
                                                                   class="form-control form-control-sm quantity-input"
                                                                   value="<?php echo (int) $variant->quantity; ?>"
                                                                   min="0"
                                                                   step="1" />
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label class="form-label small"><?php echo Text::_('COM_J2COMMERCE_VARIANTS_MANAGE_STOCK'); ?>:</label>
                                                        <?php if ($variantForm) : ?>
                                                            <?php
                                                            $variantManageStockHtml = $variantForm->renderField('manage_stock', null, (string)$variant->manage_stock, null, null, null, ['hiddenLabel' => true]);
                                                            // Make field names unique for this variant
                                                            $variantManageStockHtml = str_replace(
                                                                'name="jform[manage_stock]"',
                                                                'name="jform[variant_manage_stock_' . $variant->j2commerce_variant_id . ']"',
                                                                $variantManageStockHtml
                                                            );
                                                            $variantManageStockHtml = str_replace(
                                                                'id="jform_manage_stock',
                                                                'id="jform_variant_manage_stock_' . $variant->j2commerce_variant_id,
                                                                $variantManageStockHtml
                                                            );
                                                            echo $variantManageStockHtml;
                                                            ?>
                                                        <?php else : ?>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input manage-stock-toggle" type="checkbox" role="switch" <?php echo $variant->manage_stock ? 'checked' : ''; ?> />
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-md-2">
                                                        <label class="form-label small"><?php echo Text::_('COM_J2COMMERCE_VARIANTS_STOCK_STATUS'); ?>:</label>
                                                        <?php if ($variantForm) : ?>
                                                            <?php echo $variantForm->renderField('availability', null, $variant->availability, null, null, null, ['hiddenLabel' => true]); ?>
                                                        <?php else : ?>
                                                            <select class="form-select form-select-sm stock-select">
                                                                <option value="1" <?php echo ($variant->availability == 1) ? 'selected' : ''; ?>>
                                                                    <?php echo Text::_('COM_J2COMMERCE_STOCK_IN_STOCK'); ?>
                                                                </option>
                                                                <option value="0" <?php echo ($variant->availability == 0) ? 'selected' : ''; ?>>
                                                                    <?php echo Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK'); ?>
                                                                </option>
                                                            </select>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-md-2 text-end">
                                                        <button type="button"
                                                                class="btn btn-sm btn-primary save-btn"
                                                                onclick="saveVariantItem(<?php echo $variant->j2commerce_variant_id; ?>, <?php echo (int) $variant->product_id; ?>)">
                                                            <?php echo Text::_('COM_J2COMMERCE_SAVE'); ?>
                                                        </button>
                                                    </div>
                                                </div>

                                                <?php if ($vIndex < count($item->variants) - 1) : ?>
                                                    <hr class="my-2">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php // load the pagination. ?>
                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="" />
                <input type="hidden" name="boxchecked" value="0" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<?php echo $this->footer ?? ''; ?>
