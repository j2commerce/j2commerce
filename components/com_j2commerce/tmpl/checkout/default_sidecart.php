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
<div class="bg-light rounded p-3 p-lg-4">

            <?php if (!empty($this->items)): ?>
            <div class="checkout-sidebar-items list-group list-group-flush mb-3">
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
                    <div class="checkout-sidebar-item list-group-item px-0 d-flex align-items-center gap-3">
                        <?php if (!empty($thumbImage)): ?>
                        <div class="position-relative flex-shrink-0" style="width:64px;height:64px;">
                            <img class="rounded border w-100 h-100 object-fit-cover" src="<?php echo $this->escape($thumbImage); ?>" alt="<?php echo $this->escape($item->orderitem_name); ?>">
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary"><?php echo $qty; ?></span>
                        </div>
                        <?php else: ?>
                        <div class="position-relative flex-shrink-0 d-flex align-items-center justify-content-center rounded border bg-light" style="width:64px;height:64px;">
                            <span class="text-muted small"><?php echo $qty; ?>x</span>
                        </div>
                        <?php endif; ?>

                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-medium text-truncate"><?php echo $this->escape($item->orderitem_name); ?></div>
                            <?php if ($this->params->get('show_sku', 1) && !empty($item->orderitem_sku)): ?>
                                <div class="cart-product-sku small">
                                    <span class="cart-item-title text-muted"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>:</span>
                                    <span class="cart-item-value"><?php echo $this->escape($item->orderitem_sku); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item->orderitemattributes)): ?>
                                <?php echo LayoutHelper::render('orderitem.attributes', [
                                    'attributes' => $item->orderitemattributes,
                                    'item'       => $item,
                                    'context'    => 'checkout',
                                    'variant'    => 'compact',
                                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                            <?php endif; ?>
                            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $this->order, &$this->params]); ?>
                        </div>

                        <div class="fw-medium flex-shrink-0">
                            <?php echo $this->currency->format($lineTotal); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($enableCoupon): ?>
            <div class="mb-2 checkout-coupon-form">
                <?php echo LayoutHelper::render('form.coupon', [
                    'couponCode'   => $coupon,
                    'formId'       => 'sidecart-coupon',
                    'variant'      => 'inline',
                    'showDiscount' => true,
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
            <?php endif; ?>

            <?php if ($enableVoucher): ?>
            <div class="checkout-voucher-form mb-3">
                <?php echo LayoutHelper::render('form.voucher', [
                    'voucherCode'  => $voucher,
                    'formId'       => 'sidecart-voucher',
                    'variant'      => 'inline',
                    'showDiscount' => true,
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($totals)): ?>
            <div class="border-top pt-3">
                <?php foreach ($totals as $key => $total): ?>
                    <?php if ($key === 'grandtotal'): ?>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-2">
                            <span class="fs-5 fw-bold"><?php echo $total['label']; ?></span>
                            <span class="fs-5 fw-bold j2commerce-sidecart-grandtotal"><?php echo $total['value']; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">
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
