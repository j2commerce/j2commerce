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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

$platform = J2CommerceHelper::platform();
$showQtyField = $this->params->get('show_qty_field', 1);
$showItemTax = $this->params->get('show_item_tax', 0);
$showThumbCart = $this->params->get('show_thumb_cart', 1);
$showPriceField = $this->params->get('show_price_field', 1);
$showSku = $this->params->get('show_sku', 1);
$checkoutPriceDisplay = $this->params->get('checkout_price_display_options', 0);

?>
<div class="table-responsive">
    <table class="j2commerce-cart-table table table-borderless">
        <thead>
            <tr>
                <th><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM'); ?></th>
                <?php if ($showQtyField): ?>
                    <th class="text-start" style="width: 160px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></th>
                <?php endif; ?>
                <?php if (isset($this->taxes) && \count($this->taxes) && $showItemTax): ?>
                    <th class="start"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TAX'); ?></th>
                <?php endif; ?>
                <th class="text-end" style="width: 120px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TOTAL'); ?></th>
                <td class="text-end"></td>
            </tr>
        </thead>
        <tbody>
            <?php $i = 0; ?>
            <?php foreach ($this->items as $item): ?>
                <?php
                $itemParams = $platform->getRegistry($item->orderitem_params ?? '{}');
                $rawThumbImage = (string) $itemParams->get('thumb_image', '');
                $thumbImage = $rawThumbImage !== ''
                    ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumbImage))->url
                    : '';
                $backOrderText = $itemParams->get('back_order_item', '');
                $removeUrl = J2CommerceHelper::platform()->getCartUrl(['task' => 'remove','cartitem_id' => $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0
                ]);
                ?>
                <?php $cartitemIdRow = $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0; ?>
                <tr class="j2commerce-cart-item" data-cartitem-id="<?php echo $cartitemIdRow; ?>">
                    <td class="py-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="d-flex j2commerce-cart-item-details">
                                <?php if ($showThumbCart && !empty($thumbImage)): ?>
                                    <div class="flex-shrink-0 me-3">
                                        <span class="cart-thumb-image flex-shrink-0">
                                            <img src="<?php echo $thumbImage; ?>" alt="<?php echo $this->escape($item->orderitem_name); ?>" class="img-thumbnail img-fluid object-fit-cover" style="max-width: 80px;width:80px;height:80px;" width="80" height="80">
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="cart-product-details flex-grow-1">
                                    <div class="cart-product-name fw-bold mb-1">
                                        <?php echo $this->escape($item->orderitem_name); ?>
                                    </div>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $this->order, &$this->params]); ?>
                                    <?php if ($showSku && !empty($item->orderitem_sku)): ?>
                                        <div class="cart-product-sku fs-xs lh-1">
                                            <span class="cart-item-option-title text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>:</span>
                                            <span class="cart-item-option-value text-dark-emphasis fw-medium"><?php echo $this->escape($item->orderitem_sku); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item->orderitemattributes)): ?>
                                        <?php echo LayoutHelper::render('orderitem.attributes', [
                                            'attributes' => $item->orderitemattributes,
                                            'item'       => $item,
                                            'context'    => 'cart',
                                            'variant'    => 'full',
                                        ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                    <?php endif; ?>

                                    <?php if ($showPriceField): ?>
                                        <div class="cart-product-unit-price mt-1 fs-xs lh-1">
                                            <span class="cart-item-option-title text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE'); ?>:</span>
                                            <span class="cart-item-option-value text-dark-emphasis fw-medium"><?php echo $this->currency->format($this->order->get_formatted_lineitem_price($item, $checkoutPriceDisplay)); ?></span>
                                        </div>
                                    <?php endif; ?>



                                    <?php if ($backOrderText): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-secondary"><?php echo Text::_($backOrderText); ?></span>
                                        </div>
                                    <?php endif; ?>


                                    <?php if (isset($this->onDisplayCartItem[$i])): ?>
                                        <div class="mt-2">
                                            <?php echo $this->onDisplayCartItem[$i]; ?>
                                        </div>
                                    <?php endif; ?>



                                    <?php if (!$showQtyField): ?>
                                        <?php
                                        $cartitemId = $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0;
                                        $currentQty = (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1);
                                        ?>
                                        <input type="hidden" name="qty[<?php echo $cartitemId; ?>]" value="<?php echo $currentQty; ?>" />
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <?php if($showQtyField): ?>
                        <?php
                        $cartitemId = $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0;
                        $currentQty = (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1);
                        $minQty = (int) ($item->min_sale_qty ?? 1);
                        $maxQty = (int) ($item->max_sale_qty ?? 0);
                        if ($minQty < 1) {
                            $minQty = 1;
                        }
                        ?>
                        <td class="text-center align-middle py-3">
                            <div class="j2commerce-qty-controls d-flex align-items-center justify-content-start" data-cartitem-id="<?php echo $cartitemId; ?>" data-min-qty="<?php echo $minQty; ?>" data-max-qty="<?php echo $maxQty; ?>">
                                <div class="input-group border w-auto">
                                    <button type="button" class="btn btn-sm btn-link j2commerce-qty-minus border-0 border-end-1" aria-label="<?php echo Text::_('COM_J2COMMERCE_DECREASE_QUANTITY'); ?>" <?php if ($currentQty <= $minQty): ?>disabled<?php endif; ?>>
                                        <span class="icon-minus" aria-hidden="true"></span>
                                    </button>
                                    <input type="number" name="qty[<?php echo $cartitemId; ?>]" value="<?php echo $currentQty; ?>" min="<?php echo $minQty; ?>" <?php if ($maxQty > 0): ?>max="<?php echo $maxQty; ?>"<?php endif; ?> step="1" class="form-control form-control-sm text-center j2commerce-qty-input border-0" style="width: 50px;max-width:50px;" aria-label="<?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?>" />
                                    <button type="button" class="btn btn-sm btn-link j2commerce-qty-plus border-0" aria-label="<?php echo Text::_('COM_J2COMMERCE_INCREASE_QUANTITY'); ?>" <?php if ($maxQty > 0 && $currentQty >= $maxQty): ?>disabled<?php endif; ?>>
                                        <span class="icon-plus" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    <?php else: ?>
                        <?php
                        $cartitemId = $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0;
                        $currentQty = (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1);
                        ?>
                        <input type="hidden" name="qty[<?php echo $cartitemId; ?>]" value="<?php echo $currentQty; ?>" />
                    <?php endif; ?>

                    <?php if (isset($this->taxes) && \count($this->taxes) && $showItemTax): ?>
                        <td class="text-start align-middle py-3">
                            <?php echo $this->currency->format($item->orderitem_tax / max($currentQty, 1)); ?>
                        </td>
                    <?php endif; ?>

                    <td class="cart-line-subtotal text-end align-middle fw-bold py-3">
                        <?php
                        $discountInfo = $this->order->get_lineitem_discount_info($item, $checkoutPriceDisplay);
                        $lineTotal = $this->order->get_formatted_lineitem_total($item, $checkoutPriceDisplay);
                        if ($discountInfo && !empty($discountInfo->original_price) && $discountInfo->original_price > $discountInfo->final_price):
                        ?>
                            <span class="line-total-original text-decoration-line-through text-muted"><?php echo $this->currency->format($discountInfo->original_price); ?></span>
                            <span class="line-total-value ms-2"><?php echo $this->currency->format($discountInfo->final_price); ?></span>
                        <?php else: ?>
                            <span class="line-total-value"><?php echo $this->currency->format($lineTotal); ?></span>
                        <?php endif; ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTotal', [$item, $this->order, $this->params]); ?>
                    </td>
                    <td class="cart-line-remove align-middle text-end pe-0 py-3" style="width:36px;">
                        <button type="button" class="btn btn-sm btn-link text-danger j2commerce-remove-ajax" data-cartitem-id="<?php echo $cartitemId; ?>" title="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>">
                            <span class="icon-trash" aria-hidden="true"></span>
                        </button>
                    </td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.j2commerce-qty-controls.j2commerce-loading {
    opacity: 0.6;
    pointer-events: none;
}

.j2commerce-qty-controls .btn {
    padding: 0.25rem 0.5rem;
    line-height: 1;
}

.j2commerce-qty-controls .j2commerce-qty-input {
    -moz-appearance: textfield;
}

.j2commerce-qty-controls .j2commerce-qty-input::-webkit-outer-spin-button,
.j2commerce-qty-controls .j2commerce-qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
</style>
