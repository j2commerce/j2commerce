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
    <table class="j2commerce-cart-table uk-table">
        <thead>
            <tr>
                <th scope="col"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM'); ?></th>
                <?php if ($showQtyField): ?>
                    <th scope="col" class="uk-text-left" style="width: 160px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></th>
                <?php endif; ?>
                <?php if (isset($this->taxes) && \count($this->taxes) && $showItemTax): ?>
                    <th scope="col" class="start"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TAX'); ?></th>
                <?php endif; ?>
                <th scope="col" class="uk-text-right" style="width: 120px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TOTAL'); ?></th>
                <th scope="col"><span class="uk-hidden-visually"><?php echo Text::_('COM_J2COMMERCE_ACTIONS'); ?></span></th>
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
                    <td class="uk-padding-small">
                        <div class="uk-flex uk-flex-top">
                            <div class="uk-flex j2commerce-cart-item-details">
                                <?php if ($showThumbCart && !empty($thumbImage)): ?>
                                    <div class="uk-margin-small-right">
                                        <span class="cart-thumb-image">
                                            <img src="<?php echo $thumbImage; ?>" alt="<?php echo $this->escape($item->orderitem_name); ?>" class="img-thumbnail img-fluid object-fit-cover" style="max-width: 80px;width:80px;height:80px;" width="80" height="80">
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="cart-product-details">
                                    <div class="cart-product-name uk-text-bold uk-margin-small-bottom">
                                        <?php echo $this->escape($item->orderitem_name); ?>
                                    </div>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $this->order, &$this->params]); ?>
                                    <?php if ($showSku && !empty($item->orderitem_sku)): ?>
                                        <div class="cart-product-sku uk-text-small">
                                            <span class="cart-item-option-title uk-text-meta"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>:</span>
                                            <span class="cart-item-option-value uk-text-bold"><?php echo $this->escape($item->orderitem_sku); ?></span>
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
                                        <div class="cart-product-unit-price uk-margin-small-top uk-text-small">
                                            <span class="cart-item-option-title uk-text-meta"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE'); ?>:</span>
                                            <span class="cart-item-option-value uk-text-bold"><?php echo $this->currency->format($this->order->get_formatted_lineitem_price($item, $checkoutPriceDisplay)); ?></span>
                                        </div>
                                    <?php endif; ?>



                                    <?php if ($backOrderText): ?>
                                        <div class="uk-margin-small-top">
                                            <span class="uk-label"><?php echo Text::_($backOrderText); ?></span>
                                        </div>
                                    <?php endif; ?>


                                    <?php if (isset($this->onDisplayCartItem[$i])): ?>
                                        <div class="uk-margin-small-top">
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
                        <td class="uk-text-center uk-padding-small">
                            <div class="j2commerce-qty-controls uk-flex uk-flex-middle" data-cartitem-id="<?php echo $cartitemId; ?>" data-min-qty="<?php echo $minQty; ?>" data-max-qty="<?php echo $maxQty; ?>">
                                <div class="uk-inline">
                                    <button type="button" class="uk-button uk-button-small uk-button-link j2commerce-qty-minus" aria-label="<?php echo Text::_('COM_J2COMMERCE_DECREASE_QUANTITY'); ?>" <?php if ($currentQty <= $minQty): ?>disabled<?php endif; ?>>
                                        <span class="icon-minus" aria-hidden="true"></span>
                                    </button>
                                    <input type="text" name="qty[<?php echo $cartitemId; ?>]" value="<?php echo $currentQty; ?>" min="<?php echo $minQty; ?>" <?php if ($maxQty > 0): ?>max="<?php echo $maxQty; ?>"<?php endif; ?> step="1" pattern="[0-9]*" inputmode="numeric" class="uk-input uk-form-small uk-text-center j2commerce-qty-input" style="width: 50px; max-width: 50px;" aria-label="<?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?>" />
                                    <button type="button" class="uk-button uk-button-small uk-button-link j2commerce-qty-plus" aria-label="<?php echo Text::_('COM_J2COMMERCE_INCREASE_QUANTITY'); ?>" <?php if ($maxQty > 0 && $currentQty >= $maxQty): ?>disabled<?php endif; ?>>
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
                        <td class="uk-text-left uk-padding-small">
                            <?php echo $this->currency->format($item->orderitem_tax / max($currentQty, 1)); ?>
                        </td>
                    <?php endif; ?>

                    <td class="cart-line-subtotal uk-text-right uk-text-bold uk-padding-small">
                        <?php
                        $discountInfo = $this->order->get_lineitem_discount_info($item, $checkoutPriceDisplay);
                        $lineTotal = $this->order->get_formatted_lineitem_total($item, $checkoutPriceDisplay);
                        if ($discountInfo && !empty($discountInfo->original_price) && $discountInfo->original_price > $discountInfo->final_price):
                        ?>
                            <span class="line-total-original uk-text-decoration-line-through uk-text-meta"><?php echo $this->currency->format($discountInfo->original_price); ?></span>
                            <span class="line-total-value uk-margin-small-left"><?php echo $this->currency->format($discountInfo->final_price); ?></span>
                        <?php else: ?>
                            <span class="line-total-value"><?php echo $this->currency->format($lineTotal); ?></span>
                        <?php endif; ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTotal', [$item, $this->order, $this->params]); ?>
                    </td>
                    <td class="cart-line-remove uk-text-right uk-padding-small" style="width:36px;">
                        <button type="button" class="uk-button uk-button-small uk-button-link uk-text-danger j2commerce-remove-ajax" data-cartitem-id="<?php echo $cartitemId; ?>" title="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>" aria-label="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>">
                            <span class="icon-trash" aria-hidden="true"></span>
                        </button>
                    </td>
                </tr>
                <?php $i++; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
