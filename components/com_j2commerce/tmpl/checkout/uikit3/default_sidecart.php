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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$platform = J2CommerceHelper::platform();
$checkoutPriceDisplay = (int) $this->params->get('checkout_price_display_options', 0);
$showThumbCart = (int) $this->params->get('show_thumb_cart', 1);
$enableCoupon = (int) $this->params->get('enable_coupon', 0);
$enableVoucher = (int) $this->params->get('enable_voucher', 0);

// Coupon state
$coupon = '';
if ($enableCoupon) {
    $couponModel = Factory::getApplication()
        ->bootComponent('com_j2commerce')
        ->getMVCFactory()
        ->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
    $coupon = $couponModel ? $couponModel->getCoupon() : '';
}
$hasCoupon = !empty($coupon);

// Voucher state
$voucher = '';
if ($enableVoucher) {
    $voucherModel = Factory::getApplication()
        ->bootComponent('com_j2commerce')
        ->getMVCFactory()
        ->createModel('Voucher', 'Administrator', ['ignore_request' => true]);
    $voucher = $voucherModel ? $voucherModel->getVoucherCode() : '';
}
$hasVoucher = !empty($voucher);

// Order totals
$totals = ($this->order && method_exists($this->order, 'get_formatted_order_totals'))
    ? $this->order->get_formatted_order_totals()
    : [];

$grandTotalValue = $totals['grandtotal']['value'] ?? '';

?>
<div class="uk-background-muted uk-border-rounded uk-padding">

            <?php if (!empty($this->items)): ?>
            <div class="checkout-sidebar-items uk-margin-bottom">
                <?php foreach ($this->items as $item): ?>
                    <?php
                    $itemParams = $platform->getRegistry($item->orderitem_params ?? '{}');

                    $rawThumb = (string) $itemParams->get('thumb_image', '');
                    $thumbImage = '';
                    if ($showThumbCart && $rawThumb !== '') {
                        $thumbImage = HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumb))->url;
                    }

                    $qty = (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1);
                    $lineTotal = ($this->order && method_exists($this->order, 'get_formatted_lineitem_total'))
                        ? $this->order->get_formatted_lineitem_total($item, $checkoutPriceDisplay)
                        : 0;
                    ?>
                    <div class="checkout-sidebar-item uk-flex uk-flex-middle" style="gap: 12px;">
                        <?php if (!empty($thumbImage)): ?>
                        <div class="uk-position-relative uk-flex-none j2commerce-sidecart-thumb">
                            <img class="uk-border-rounded uk-border uk-width-1-1 uk-height-1-1" style="object-fit:cover;" src="<?php echo $this->escape($thumbImage); ?>" alt="<?php echo $this->escape($item->orderitem_name); ?>">
                            <span class="uk-badge j2commerce-sidecart-qty-badge"><?php echo $qty; ?></span>
                        </div>
                        <?php else: ?>
                        <div class="uk-flex-none uk-flex uk-flex-middle uk-flex-center uk-border-rounded uk-border uk-background-default" style="width:64px;height:64px;">
                            <span class="uk-text-meta uk-text-small"><?php echo $qty; ?>x</span>
                        </div>
                        <?php endif; ?>

                        <div class="uk-flex-1" style="min-width:0;">
                            <div class="uk-text-bold uk-text-truncate"><?php echo $this->escape($item->orderitem_name); ?></div>
                            <?php if ($this->params->get('show_sku', 1) && !empty($item->orderitem_sku)): ?>
                                <div class="cart-product-sku uk-text-small">
                                    <span class="cart-item-title uk-text-meta"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>:</span>
                                    <span class="cart-item-value"><?php echo $this->escape($item->orderitem_sku); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item->orderitemattributes)): ?>
                                <?php echo LayoutHelper::render('orderitem.attributes', [
                                    'attributes' => $item->orderitemattributes,
                                    'item'       => $item,
                                    'context'    => 'checkout',
                                    'variant'    => 'compact',
                                    'framework'  => 'uikit3',
                                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                            <?php endif; ?>
                            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $this->order, &$this->params]); ?>
                        </div>

                        <div class="uk-text-bold uk-flex-none">
                            <?php echo $this->currency->format($lineTotal); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($enableCoupon): ?>
            <div class="uk-margin-small-bottom checkout-coupon-form">
                <?php echo LayoutHelper::render('form.coupon', [
                    'couponCode'   => $coupon,
                    'formId'       => 'sidecart-coupon',
                    'variant'      => 'inline',
                    'showDiscount' => true,
                    'framework'    => 'uikit3',
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
            <?php endif; ?>

            <?php if ($enableVoucher): ?>
            <div class="checkout-voucher-form uk-margin-bottom">
                <?php echo LayoutHelper::render('form.voucher', [
                    'voucherCode'  => $voucher,
                    'formId'       => 'sidecart-voucher',
                    'variant'      => 'inline',
                    'showDiscount' => true,
                    'framework'    => 'uikit3',
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($totals)): ?>
            <div class="j2commerce-sidecart-totals">
                <?php foreach ($totals as $key => $total): ?>
                    <?php if ($key === 'grandtotal'): ?>
                        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-top j2commerce-sidecart-grandtotal-row">
                            <span class="uk-text-large uk-text-bold"><?php echo $total['label']; ?></span>
                            <span class="uk-text-large uk-text-bold j2commerce-sidecart-grandtotal"><?php echo $total['value']; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                            <span class="uk-text-meta">
                                <?php echo $total['label']; ?>
                                <?php if (!empty($total['link'])): ?>
                                    <?php echo $total['link']; ?>
                                <?php endif; ?>
                            </span>
                            <span><?php echo $total['value']; ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

</div>
