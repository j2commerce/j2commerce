<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use J2Commerce\Plugin\System\J2Commerce\Helper\LeafletMapHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Confirmation\HtmlView $this */

$order     = $this->order;
$paction   = $this->paction;
$info      = $this->orderInfo;
$items     = $this->orderItems;
$shippings = $this->orderShippings;
$taxes     = $this->orderTaxes;
$fees      = $this->orderFees;
$discounts = $this->orderDiscounts;
$platform  = J2CommerceHelper::platform();

$currencyCode  = $order->currency_code ?? '';
$currencyValue = (float) ($order->currency_value ?? 1);

$fmt = static function (float $amount) use ($currencyCode, $currencyValue): string {
    return CurrencyHelper::format($amount, $currencyCode, $currencyValue);
};

$isCancelled = ($paction === 'cancel');

// Status tier drives the confirmation banner. The order state determines whether
// the customer actually completed payment — never assume success.
//   success : Confirmed(1), Shipped(7), Delivered(8), Scheduled(9)
//   pending : Processed(2), Pending(4) — received, awaiting payment confirmation
//   failed  : Failed(3), New(5), unknown — payment not completed
$orderStateId = (int) ($order->order_state_id ?? 0);
$statusTier   = $isCancelled ? 'cancelled' : match ($orderStateId) {
    1, 7, 8, 9 => 'success',
    2, 4       => 'pending',
    default    => 'failed',
};

$tierAlertClass = match ($statusTier) {
    'success' => 'alert-success',
    'pending' => 'alert-info',
    default   => 'alert-danger',
};
$tierCircleClass = match ($statusTier) {
    'success' => 'j2c-check-circle',
    'pending' => 'j2c-info-circle',
    default   => 'j2c-cancel-circle',
};
$tierIcon = match ($statusTier) {
    'success' => 'fa-solid fa-circle-check',
    'pending' => 'fa-solid fa-circle-info',
    'failed'  => 'fa-solid fa-triangle-exclamation',
    default   => 'fa-solid fa-xmark',
};
$tierMessage = match ($statusTier) {
    'success'   => 'COM_J2COMMERCE_ORDER_CONFIRMED_MESSAGE',
    'pending'   => 'COM_J2COMMERCE_ORDER_RECEIVED_MESSAGE',
    'cancelled' => 'COM_J2COMMERCE_ORDER_CANCELLED_MESSAGE',
    default     => 'COM_J2COMMERCE_ORDER_PAYMENT_INCOMPLETE_MESSAGE',
};
$tierMessageClass = match ($statusTier) {
    'pending' => 'text-info',
    'failed'  => 'text-danger',
    default   => 'text-body-secondary',
};

$firstName = '';
if ($info) {
    $firstName = trim((string) ($info->billing_first_name ?? ''));
}

// Fire AfterPostPayment plugin event and collect HTML
$afterPostHtml = J2CommerceHelper::plugin()->eventWithHtml('AfterPostPayment', [$this])->getArgument('html', '');

// Order date formatting
$orderDate = '';
if (!empty($order->created_on) && $order->created_on !== '0000-00-00 00:00:00') {
    $orderDate = HTMLHelper::_('date', $order->created_on, Text::_('DATE_FORMAT_LC2'));
}

// Format payment method display name — reads plugin's display_name param so language overrides are respected
$paymentDisplay = $this->escape(J2CommerceHelper::getPaymentDisplayName($order->orderpayment_type ?? ''));

$statusBadgeHtml = J2htmlHelper::getOrderStatusHtml((int) ($order->order_state_id ?? 0));

// Build shipping address for map (fall back to billing if not shippable)
$mapAddress = '';
$mapLoaded  = false;

if ($info) {
    $useShipping = (int) $order->is_shippable && !empty($info->shipping_address_1);

    $mapStreet  = $useShipping ? ($info->shipping_address_1 ?? '') : ($info->billing_address_1 ?? '');
    $mapCity    = $useShipping ? ($info->shipping_city ?? '') : ($info->billing_city ?? '');
    $mapState   = $useShipping ? ($info->shipping_zone_name ?? '') : ($info->billing_zone_name ?? '');
    $mapZip     = $useShipping ? ($info->shipping_zip ?? '') : ($info->billing_zip ?? '');
    $mapCountry = $useShipping ? ($info->shipping_country_name ?? '') : ($info->billing_country_name ?? '');
    $mapAddress = implode(', ', array_filter([$mapStreet, $mapCity, $mapState, $mapZip, $mapCountry]));

    if ($mapCity !== '') {
        $mapLoaded = LeafletMapHelper::loadMapStructured($mapStreet, $mapCity, $mapState, $mapZip, $mapCountry, 'j2c-confirmation-map');
    }
}
?>

<div class="j2commerce">
    <div class="page-header">
        <h1><?php echo Text::_('COM_J2COMMERCE_ORDER_CONFIRMATION'); ?></h1>
    </div>

    <div class="j2commerce-confirmation">
        <?php if ($this->showingRecent) : ?>
        <div class="j2c-block-showing-recent alert alert-info d-flex justify-content-between align-items-center mb-3">
            <span><span class="fa-solid fa-circle-info me-2" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_SHOWING_RECENT'); ?></span>
            <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="btn btn-sm btn-outline-primary">
                <?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_VIEW_ALL_ORDERS'); ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="j2c-block-postpayment-top"><jdoc:include type="modules" name="j2commerce-postpayment-top" style="none" /></div>

        <div class="row g-0">
            <?php // Left column ?>
            <div class="<?php echo $isCancelled ? 'col-12' : 'col-lg-7'; ?> j2c-confirmation-main <?php echo $isCancelled ? '' : 'pe-lg-4'; ?>">

                <div class="j2c-block-status-banner alert <?php echo $tierAlertClass; ?> mb-4 border-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="<?php echo $tierCircleClass; ?> rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                            <span class="<?php echo $tierIcon; ?> text-white" style="font-size: 1.5rem;" aria-hidden="true"></span>
                        </div>
                        <div>
                            <?php if ($statusTier === 'cancelled') : ?>
                                <h2 class="h4 mb-1 alert-heading"><?php echo Text::_('COM_J2COMMERCE_ORDER_CANCELLED'); ?></h2>
                            <?php elseif ($statusTier === 'success') : ?>
                                <?php if ($firstName !== '') : ?>
                                    <h2 class="h4 mb-1 alert-heading"><?php echo Text::sprintf('COM_J2COMMERCE_THANK_YOU_NAME', $this->escape($firstName)); ?></h2>
                                <?php else : ?>
                                    <h2 class="h4 mb-1 alert-heading"><?php echo Text::_('COM_J2COMMERCE_ORDER_CONFIRMATION'); ?></h2>
                                <?php endif; ?>
                            <?php elseif ($statusTier === 'pending') : ?>
                                <h2 class="h4 mb-1 alert-heading"><?php echo Text::_('COM_J2COMMERCE_ORDER_RECEIVED'); ?></h2>
                            <?php else : ?>
                                <h2 class="h4 mb-1 alert-heading"><?php echo Text::_('COM_J2COMMERCE_ORDER_PAYMENT_INCOMPLETE'); ?></h2>
                            <?php endif; ?>
                            <?php if ($statusTier !== 'cancelled') : ?>
                                <p class="mb-1">
                                    <?php echo Text::_('COM_J2COMMERCE_ORDER_ID'); ?>: <?php echo $this->escape($order->order_id); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeConfirmationOrderStatus', array($this, $order, 'onJ2Commerce'))->getArgument('html', ''); ?>

                <div class="j2c-block-order-status card mb-4">
                    <div class="card-body">
                        <div>
                            <h3 class="h6 mb-1">
                                <?php echo Text::_('COM_J2COMMERCE_ORDERSTATUS'); ?>
                                <span class="ms-2"><?php echo $statusBadgeHtml; ?></span>
                            </h3>
                            <p class="<?php echo $tierMessageClass; ?> mb-0 small">
                                <?php echo Text::_($tierMessage); ?>
                            </p>
                            <?php if ($mapLoaded) : ?>
                                <div id="j2c-confirmation-map" class="leaflet-map-container mt-3" role="img" aria-label="<?php echo $this->escape($mapAddress); ?>"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>


                <?php if (!empty($this->plugin_html)) : ?>
                    <div class="j2c-block-plugin-html card mb-4">
                        <div class="card-body">
                            <?php echo $this->plugin_html; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isCancelled) : ?>
                    <?php // Email updates card ?>
                    <?php if (!empty($order->user_email)) : ?>
                        <div class="j2c-block-email-updates card mb-4">
                            <div class="card-body d-flex align-items-center gap-3">
                                <span class="fa-solid fa-envelope text-body-secondary" style="font-size: 1.25rem;" aria-hidden="true"></span>
                                <div>
                                    <h3 class="h6 mb-1"><?php echo Text::_('COM_J2COMMERCE_ORDER_UPDATES'); ?></h3>
                                    <p class="text-body-secondary mb-0 small">
                                        <?php echo Text::_('COM_J2COMMERCE_ORDER_UPDATES_DESC'); ?>
                                        <br><strong><?php echo $this->escape($order->user_email); ?></strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Article linked to the order's payment method ?>
                    <?php if ($this->articleHtml !== '') : ?>
                        <div class="j2c-block-article-content card mb-4">
                            <div class="card-body">
                                <?php echo $this->articleHtml; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php echo LayoutHelper::render('download.list', [
                        'downloads'  => $this->downloads,
                        'framework'  => 'bootstrap5',
                        'dateFormat' => $this->params->get('date_format', 'Y-m-d'),
                    ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>

                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeConfirmationOrderDetails', array($this, $order, 'onJ2Commerce'))->getArgument('html', ''); ?>

                    <?php // Customer information grid ?>
                    <?php if ($info) : ?>
                        <div class="j2c-block-order-details card mb-4">
                            <div class="card-body">
                                <h3 class="h6 mb-3"><?php echo Text::_('COM_J2COMMERCE_ORDER_DETAILS'); ?></h3>

                                <div class="row g-4">
                                    <?php // Contact information ?>
                                    <div class="col-sm-6">
                                        <h4 class="j2c-info-label text-uppercase text-body-dark fw-semibold mb-2">
                                            <?php echo Text::_('COM_J2COMMERCE_CONTACT_INFORMATION'); ?>
                                        </h4>
                                        <p class="mb-0 small">
                                            <?php echo $this->escape($order->user_email); ?>
                                            <?php if (!empty($info->billing_phone_1)) : ?>
                                                <br><?php echo $this->escape($info->billing_phone_1); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <?php // Payment method ?>
                                    <div class="col-sm-6">
                                        <h4 class="j2c-info-label text-uppercase text-body-dark fw-semibold mb-2">
                                            <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?>
                                        </h4>
                                        <p class="mb-0 small">
                                            <?php echo $paymentDisplay; ?>
                                            <br><?php echo $fmt((float) $order->order_total); ?>
                                        </p>
                                    </div>

                                    <?php // Shipping address ?>
                                    <?php if ((int) $order->is_shippable) : ?>
                                        <div class="col-sm-6">
                                            <h4 class="j2c-info-label text-uppercase text-body-dark fw-semibold mb-2">
                                                <?php echo Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'); ?>
                                            </h4>
                                            <address class="mb-0 small">
                                                <?php echo $this->escape(trim(($info->shipping_first_name ?? '') . ' ' . ($info->shipping_last_name ?? ''))); ?>
                                                <?php if (!empty($info->shipping_company)) : ?>
                                                    <br><?php echo $this->escape($info->shipping_company); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($info->shipping_address_1)) : ?>
                                                    <br><?php echo $this->escape($info->shipping_address_1); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($info->shipping_address_2)) : ?>
                                                    <br><?php echo $this->escape($info->shipping_address_2); ?>
                                                <?php endif; ?>
                                                <?php $shippingLocality = implode(', ', array_filter([$info->shipping_city ?? '', trim(($info->shipping_zone_name ?? '') . ' ' . ($info->shipping_zip ?? ''))], 'strlen')); ?>
                                                <?php if ($shippingLocality !== '') : ?>
                                                    <br><?php echo $this->escape($shippingLocality); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($info->shipping_country_name)) : ?>
                                                    <br><?php echo $this->escape($info->shipping_country_name); ?>
                                                <?php endif; ?>
                                            </address>
                                        </div>
                                    <?php endif; ?>

                                    <?php // Billing address ?>
                                    <div class="col-sm-6">
                                        <h4 class="j2c-info-label text-uppercase text-body-dark fw-semibold mb-2">
                                            <?php echo Text::_('COM_J2COMMERCE_BILLING_ADDRESS'); ?>
                                        </h4>
                                        <address class="mb-0 small">
                                            <?php echo $this->escape(trim(($info->billing_first_name ?? '') . ' ' . ($info->billing_last_name ?? ''))); ?>
                                            <?php if (!empty($info->billing_company)) : ?>
                                                <br><?php echo $this->escape($info->billing_company); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($info->billing_address_1)) : ?>
                                                <br><?php echo $this->escape($info->billing_address_1); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($info->billing_address_2)) : ?>
                                                <br><?php echo $this->escape($info->billing_address_2); ?>
                                            <?php endif; ?>
                                            <?php $billingLocality = implode(', ', array_filter([$info->billing_city ?? '', trim(($info->billing_zone_name ?? '') . ' ' . ($info->billing_zip ?? ''))], 'strlen')); ?>
                                            <?php if ($billingLocality !== '') : ?>
                                                <br><?php echo $this->escape($billingLocality); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($info->billing_country_name)) : ?>
                                                <br><?php echo $this->escape($info->billing_country_name); ?>
                                            <?php endif; ?>
                                        </address>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Customer note card ?>
                    <?php if ((int) J2CommerceHelper::config()->get('show_customer_note', 1) === 1 && trim((string) ($order->customer_note ?? '')) !== '') : ?>
                        <div class="j2c-block-customer-note card mb-4">
                            <div class="card-body">
                                <h3 class="h6 mb-3"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CUSTOMER_NOTE'); ?></h3>
                                <p class="mb-0 small"><?php echo nl2br($this->escape($order->customer_note)); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Shipping method card ?>
                    <?php if ((int) $order->is_shippable && !empty($shippings)) : ?>
                        <div class="j2c-block-shipping-method card mb-4">
                            <div class="card-body">
                                <h4 class="j2c-info-label text-uppercase text-body-dark fw-semibold mb-2">
                                    <?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD'); ?>
                                </h4>
                                <?php foreach ($shippings as $shipping) : ?>
                                    <p class="mb-0 small">
                                        <?php echo $this->escape($shipping->ordershipping_name); ?>
                                        &middot;
                                        <?php echo $fmt((float) ($shipping->ordershipping_price ?? 0)); ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Continue shopping + need help ?>
                    <div class="j2c-block-actions d-flex flex-wrap align-items-center justify-content-center gap-3 mb-4">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="btn btn-primary">
                            <?php echo Text::_('COM_J2COMMERCE_CONTINUE_SHOPPING'); ?>
                        </a>
                        <?php if (!empty($this->order_link)) : ?>
                            <a href="<?php echo $this->order_link; ?>" class="btn btn-outline-primary">
                                <?php echo Text::_('COM_J2COMMERCE_VIEW_ORDER_HISTORY'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <p class="j2c-block-need-help text-body-secondary small mb-4">
                        <?php echo Text::_('COM_J2COMMERCE_NEED_HELP'); ?>
                        <a href="<?php echo Route::_('index.php?option=com_contact'); ?>">
                            <?php echo Text::_('COM_J2COMMERCE_CONTACT_US'); ?>
                        </a>
                    </p>

                <?php else : ?>
                    <?php // Cancelled order actions ?>
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="btn btn-dark">
                            <?php echo Text::_('COM_J2COMMERCE_CONTINUE_SHOPPING'); ?>
                        </a>
                    </div>
                <?php endif; ?>

            </div>

            <?php // Right column - Order summary sidebar (only for non-cancelled orders) ?>
            <?php if (!$isCancelled) : ?>
                <div class="j2c-block-order-summary col-lg-5 j2c-sidebar-bg p-4">
                    <h3 class="h5 mb-4"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h3>

                    <?php
                    // Partial-payment orders scale each orderitem_finalprice down to the deposit
                    // charged today (required by itemising gateways). The plugin preserves the full
                    // per-item price in order_params so the line items reconcile with the full
                    // Subtotal/Total; the plugin's Paid Today / Balance Due rows explain the split.
                    $ppFullPrices = [];
                    $ppParamsRaw  = (string) ($order->order_params ?? '');

                    if ($ppParamsRaw !== '') {
                        $ppDecoded = json_decode($ppParamsRaw, true);

                        if (is_array($ppDecoded) && is_array($ppDecoded['partialpayment'] ?? null)) {
                            $ppFullPrices = (array) ($ppDecoded['partialpayment']['item_full_prices'] ?? []);
                        }
                    }
                    ?>

                    <?php // Order items list ?>
                    <?php if (!empty($items)) : ?>
                        <div class="j2c-block-order-items mb-4">
                            <?php foreach ($items as $item) : ?>
                                <?php
                                $params   = json_decode($item->orderitem_params ?? '{}', true) ?: [];
                                $rawThumb = (string) ($params['thumb_image'] ?? '');
                                $thumb    = $rawThumb !== ''
                                    ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumb))->url
                                    : '';
                                $qty      = (int) $item->orderitem_quantity;
                                $cartitemId = (int) ($item->cartitem_id ?? 0);
                                $lineTotal  = isset($ppFullPrices[$cartitemId])
                                    ? (float) $ppFullPrices[$cartitemId]
                                    : (float) $item->orderitem_finalprice;
                                ?>
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <?php // Product image with quantity badge ?>
                                    <div class="position-relative flex-shrink-0">
                                        <?php if (!empty($thumb)) : ?>
                                            <img src="<?php echo $this->escape($thumb); ?>"
                                                 alt="<?php echo $this->escape($item->orderitem_name); ?>"
                                                 class="j2c-order-item-img rounded border" loading="lazy">
                                        <?php else : ?>
                                            <div class="j2c-item-placeholder rounded border d-flex align-items-center justify-content-center">
                                                <span class="fa-solid fa-image text-body-tertiary" aria-hidden="true"></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($qty > 1) : ?>
                                            <span class="j2c-order-item-qty position-absolute badge rounded-pill bg-secondary d-flex align-items-center justify-content-center">
                                                <?php echo $qty; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php // Product name + options ?>
                                    <div class="flex-grow-1 small">
                                        <span class="fw-semibold"><?php echo $this->escape($item->orderitem_name); ?></span>
                                        <?php if (!empty($item->orderitemattributes)) : ?>
                                            <br><?php echo LayoutHelper::render('orderitem.attributes', [
                                                'attributes' => $item->orderitemattributes,
                                                'item'       => $item,
                                                'context'    => 'confirmation',
                                                'variant'    => 'compact',
                                            ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item->orderitem_sku)) : ?>
                                            <br><span class="text-body-tertiary"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>: <?php echo $this->escape($item->orderitem_sku); ?></span>
                                        <?php endif; ?>
                                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $order, &$this->params]); ?>
                                    </div>
                                    <?php // Item price ?>
                                    <div class="text-end small fw-semibold text-nowrap">
                                        <?php echo $fmt($lineTotal); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php // Discount code badges ?>
                    <?php if (!empty($discounts)) : ?>
                        <div class="j2c-block-discount-codes mb-3">
                            <?php foreach ($discounts as $disc) : ?>
                                <?php if (!empty($disc->discount_code)) : ?>
                                    <span class="badge bg-dark text-white me-1 mb-1">
                                        <span class="fa-solid fa-tag me-1" aria-hidden="true"></span>
                                        <?php echo $this->escape($disc->discount_title ?: $disc->discount_code); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <?php
                    // One builder answers for the checkout summary and for this page, so the rows, their
                    // labels and the tax split are the ones the customer was quoted, and every figure
                    // printed below foots to the total the order carries.
                    $summaryRows = array_filter(
                        OrderHelper::getFormattedOrderTotals($order, [
                            'shippings' => $shippings,
                            'taxes'     => $taxes,
                            'fees'      => $fees,
                            'discounts' => $discounts,
                        ]),
                        static fn (array $summaryRow): bool => $summaryRow['type'] !== 'grandtotal'
                    );
                    ?>

                    <?php // Summary lines ?>
                    <div class="j2c-block-summary-lines small">
                        <?php // Plugin-contributed extra summary lines ?>
                        <?php foreach (J2CommerceHelper::plugin()->eventWithArray('GetOrderSummaryExtraRows', [$order]) as $extraRow) : ?>
                            <?php if (\is_array($extraRow) && isset($extraRow['label'], $extraRow['value'])) : ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?php echo $this->escape($extraRow['label']); ?></span>
                                    <span><?php echo $this->escape($extraRow['value']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php foreach ($summaryRows as $summaryRow) : ?>
                            <div class="d-flex justify-content-between mb-2<?php echo $summaryRow['type'] === 'discount' ? ' text-success' : ''; ?>">
                                <span><?php echo $this->escape($summaryRow['label']); ?></span>
                                <span><?php echo $summaryRow['type'] === 'discount' ? '-' : ''; ?><?php echo $fmt($summaryRow['amount']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>

                    <?php // Grand total ?>
                    <div class="j2c-block-grand-total d-flex justify-content-between align-items-center j2c-summary-total fw-bold">
                        <span><?php echo Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'); ?></span>
                        <span>
                            <?php echo $fmt((float) $order->order_total); ?>
                            <?php if (!empty($currencyCode)) : ?>
                                <span class="j2c-summary-currency badge bg-secondary-subtle text-body-emphasis fw-normal ms-1"><?php echo $this->escape($currencyCode); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php // Order date ?>
                    <?php if (!empty($orderDate)) : ?>
                        <p class="j2c-block-order-date text-body-tertiary small mt-3 mb-0">
                            <?php echo Text::_('COM_J2COMMERCE_ORDER_DATE'); ?>: <?php echo $orderDate; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="j2c-block-postpayment-bottom"><jdoc:include type="modules" name="j2commerce-postpayment-bottom" style="none" /></div>

        <?php if (\is_object($order) && isset($order->orderpayment_type) && $order->orderpayment_type === 'free') : ?>
            <div class="j2c-block-postpayment-bottom-free"><jdoc:include type="modules" name="j2commerce-postpayment-bottom-free" style="none" /></div>
        <?php endif; ?>

        <?php echo $afterPostHtml; ?>
    </div>
</div>
