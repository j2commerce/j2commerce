<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppFlexivariable
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/** @var \stdClass $displayData */

$vars = $displayData;
$product = $vars->product ?? null;
$variantList = $product->variants ?? [];
$weights = $product->weights ?? [];
$lengths = $product->lengths ?? [];
$formPrefix = $vars->form_prefix ?? '';
$extensionId = $vars->extension_id ?? 0;
$reinitialize = $vars->reinitialize ?? false;

HTMLHelper::_('bootstrap.collapse');
?>

<?php if ($reinitialize): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reinitialize any modal functionality if needed
});
</script>
<?php endif; ?>

<div class="j2commerce-advancedvariants-settings">
    <div class="accordion" id="flexivariable-accordion">
        <?php if (!empty($variantList)): ?>
            <?php $i = 0; ?>
            <?php foreach ($variantList as $variant): ?>
                <?php
                if ($variant->is_master == 1) {
                    continue;
                }

                $prefix = $formPrefix . '[variable][' . $variant->j2commerce_variant_id . ']';
                $params = new Registry($variant->params ?? '{}');
                $variantMainImage = $params->get('variant_main_image', '');
                $isMainAsThumb = $params->get('is_main_as_thum', 0);
                $variantId = $variant->j2commerce_variant_id;
                ?>

                <div class="accordion-item" data-variant-id="<?php echo (int) $variantId; ?>">
                    <h2 class="accordion-header" id="heading-<?php echo (int) $variantId; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo (int) $variantId; ?>" aria-expanded="false" aria-controls="collapse-<?php echo (int) $variantId; ?>">
                            <span class="me-2">#<?php echo (int) $variantId; ?></span>
                            <span><?php echo htmlspecialchars($variant->variant_name ?? $variant->sku ?? 'Variant', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ms-auto me-3">
                                <?php if (!empty($variant->isdefault_variant)): ?>
                                    <span class="badge bg-success"><?php echo Text::_('COM_J2COMMERCE_DEFAULT'); ?></span>
                                <?php endif; ?>
                            </span>
                        </button>
                    </h2>

                    <div id="collapse-<?php echo (int) $variantId; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo (int) $variantId; ?>" data-bs-parent="#flexivariable-accordion">
                        <div class="accordion-body">
                            <div class="row">
                                <!-- General Settings -->
                                <div class="col-md-4">
                                    <h3 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_GENERAL'); ?></h3>

                                    <input type="hidden" name="<?php echo $prefix; ?>[j2commerce_variant_id]" value="<?php echo (int) $variantId; ?>">

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SKU'); ?></label>
                                        <input type="text" class="form-control" name="<?php echo $prefix; ?>[sku]" value="<?php echo htmlspecialchars($variant->sku ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_UPC'); ?></label>
                                        <input type="text" class="form-control" name="<?php echo $prefix; ?>[upc]" value="<?php echo htmlspecialchars($variant->upc ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_REGULAR_PRICE'); ?></label>
                                        <input type="number" step="0.01" class="form-control" name="<?php echo $prefix; ?>[price]" value="<?php echo (float) ($variant->price ?? 0); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_PRICING_CALCULATOR'); ?></label>
                                        <select class="form-select" name="<?php echo $prefix; ?>[pricing_calculator]">
                                            <option value="standard" <?php echo ($variant->pricing_calculator ?? 'standard') === 'standard' ? 'selected' : ''; ?>>
                                                <?php echo Text::_('COM_J2COMMERCE_PRICING_CALCULATOR_STANDARD'); ?>
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Shipping Settings -->
                                <div class="col-md-4">
                                    <h3 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_SHIPPING'); ?></h3>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_ENABLE_SHIPPING'); ?></label>
                                        <select class="form-select" name="<?php echo $prefix; ?>[shipping]">
                                            <option value="1" <?php echo ($variant->shipping ?? 0) == 1 ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                            <option value="0" <?php echo ($variant->shipping ?? 0) == 0 ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_DIMENSIONS'); ?></label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" name="<?php echo $prefix; ?>[length]" value="<?php echo (float) ($variant->length ?? 0); ?>" placeholder="L">
                                            <input type="number" step="0.01" class="form-control" name="<?php echo $prefix; ?>[width]" value="<?php echo (float) ($variant->width ?? 0); ?>" placeholder="W">
                                            <input type="number" step="0.01" class="form-control" name="<?php echo $prefix; ?>[height]" value="<?php echo (float) ($variant->height ?? 0); ?>" placeholder="H">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_WEIGHT'); ?></label>
                                        <input type="number" step="0.01" class="form-control" name="<?php echo $prefix; ?>[weight]" value="<?php echo (float) ($variant->weight ?? 0); ?>">
                                    </div>
                                </div>

                                <!-- Inventory Settings -->
                                <div class="col-md-4">
                                    <h3 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_INVENTORY'); ?></h3>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_MANAGE_STOCK'); ?></label>
                                        <select class="form-select" name="<?php echo $prefix; ?>[manage_stock]">
                                            <option value="1" <?php echo ($variant->manage_stock ?? 0) == 1 ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                            <option value="0" <?php echo ($variant->manage_stock ?? 0) == 0 ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_QUANTITY'); ?></label>
                                        <input type="hidden" name="<?php echo $prefix; ?>[quantity][j2commerce_productquantity_id]" value="<?php echo (int) ($variant->j2commerce_productquantity_id ?? 0); ?>">
                                        <input type="number" class="form-control" name="<?php echo $prefix; ?>[quantity][quantity]" value="<?php echo (int) ($variant->quantity ?? 0); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_ALLOW_BACK_ORDERS'); ?></label>
                                        <select class="form-select" name="<?php echo $prefix; ?>[allow_backorder]">
                                            <option value="0" <?php echo ($variant->allow_backorder ?? 0) == 0 ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_DO_NOT_ALLOW_BACKORDER'); ?></option>
                                            <option value="1" <?php echo ($variant->allow_backorder ?? 0) == 1 ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_DO_ALLOW_BACKORDER'); ?></option>
                                            <option value="2" <?php echo ($variant->allow_backorder ?? 0) == 2 ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_ALLOW_BUT_NOTIFY_CUSTOMER'); ?></option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_STOCK_STATUS'); ?></label>
                                        <select class="form-select" name="<?php echo $prefix; ?>[availability]">
                                            <option value="1" <?php echo ($variant->availability ?? 1) == 1 ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_PRODUCT_IN_STOCK'); ?></option>
                                            <option value="0" <?php echo ($variant->availability ?? 1) == 0 ? 'selected' : ''; ?>><?php echo Text::_('COM_J2COMMERCE_PRODUCT_OUT_OF_STOCK'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="btn-group" role="group">
                                        <?php if (!empty($variant->isdefault_variant)): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="J2CommerceFlexivariable.setDefault(<?php echo (int) $variantId; ?>, 'unsetDefault', <?php echo (int) $variant->product_id; ?>)">
                                                <span class="icon-star" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_UNSET_DEFAULT'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="J2CommerceFlexivariable.setDefault(<?php echo (int) $variantId; ?>, 'setDefault', <?php echo (int) $variant->product_id; ?>)">
                                                <span class="icon-star-empty" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_SET_DEFAULT'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-danger btn-sm" onclick="J2CommerceFlexivariable.deleteVariant(<?php echo (int) $variantId; ?>)">
                                            <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('JACTION_DELETE'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $i++; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_J2COMMERCE_NO_RESULTS_FOUND'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
