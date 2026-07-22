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
use J2Commerce\Plugin\System\J2Commerce\Helper\LeafletMapHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Confirmation\HtmlView $this */

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_j2commerce.site', 'media/com_j2commerce/css/site/j2commerce.css');

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

$checkoutPriceDisplay = (int) (J2CommerceHelper::config()->get('checkout_price_display_options', 0));

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
    'success' => 'uk-alert-success',
    'pending' => 'uk-alert-primary',
    default   => 'uk-alert-danger',
};
$tierCircleClass = match ($statusTier) {
    'success' => 'j2c-check-circle',
    'pending' => 'j2c-info-circle',
    default   => 'j2c-cancel-circle',
};
$tierIcon = match ($statusTier) {
    'success' => 'icon-check',
    'pending' => 'icon-info-circle',
    'failed'  => 'icon-warning',
    default   => 'icon-times',
};
$tierMessage = match ($statusTier) {
    'success'   => 'COM_J2COMMERCE_ORDER_CONFIRMED_MESSAGE',
    'pending'   => 'COM_J2COMMERCE_ORDER_RECEIVED_MESSAGE',
    'cancelled' => 'COM_J2COMMERCE_ORDER_CANCELLED_MESSAGE',
    default     => 'COM_J2COMMERCE_ORDER_PAYMENT_INCOMPLETE_MESSAGE',
};
$tierMessageClass = match ($statusTier) {
    'pending' => 'uk-text-primary',
    'failed'  => 'uk-text-danger',
    default   => 'uk-text-meta',
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
        <div class="uk-alert uk-alert-primary uk-flex uk-flex-between uk-flex-middle uk-margin-bottom" uk-alert>
            <span><span class="uk-margin-small-right" uk-icon="icon: info" aria-hidden="true"></span><?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_SHOWING_RECENT'); ?></span>
            <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="uk-button uk-button-small uk-button-default">
                <?php echo Text::_('COM_J2COMMERCE_CONFIRMATION_VIEW_ALL_ORDERS'); ?>
            </a>
        </div>
        <?php endif; ?>

        <jdoc:include type="modules" name="j2commerce-postpayment-top" style="none" />

        <div class="uk-grid uk-grid-collapse" uk-grid>
            <?php // Left column ?>
            <div class="<?php echo $isCancelled ? 'uk-width-1-1' : 'uk-width-expand@l'; ?> j2c-confirmation-main<?php echo $isCancelled ? '' : ' uk-padding-right@l'; ?>">

                <div class="uk-alert <?php echo $tierAlertClass; ?> uk-margin-bottom" uk-alert>
                    <div class="uk-flex uk-flex-middle" style="gap: 12px;">
                        <div class="<?php echo $tierCircleClass; ?> uk-border-circle uk-flex uk-flex-middle uk-flex-center uk-flex-none">
                            <span class="<?php echo $tierIcon; ?>" style="color:#fff; font-size: 1.5rem;" aria-hidden="true"></span>
                        </div>
                        <div>
                            <?php if ($statusTier === 'cancelled') : ?>
                                <h2 class="uk-h4 uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ORDER_CANCELLED'); ?></h2>
                            <?php elseif ($statusTier === 'success') : ?>
                                <?php if ($firstName !== '') : ?>
                                    <h2 class="uk-h4 uk-margin-remove"><?php echo Text::sprintf('COM_J2COMMERCE_THANK_YOU_NAME', $this->escape($firstName)); ?></h2>
                                <?php else : ?>
                                    <h2 class="uk-h4 uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ORDER_CONFIRMATION'); ?></h2>
                                <?php endif; ?>
                            <?php elseif ($statusTier === 'pending') : ?>
                                <h2 class="uk-h4 uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ORDER_RECEIVED'); ?></h2>
                            <?php else : ?>
                                <h2 class="uk-h4 uk-margin-remove"><?php echo Text::_('COM_J2COMMERCE_ORDER_PAYMENT_INCOMPLETE'); ?></h2>
                            <?php endif; ?>
                            <?php if ($statusTier !== 'cancelled') : ?>
                                <p class="uk-margin-remove">
                                    <?php echo Text::_('COM_J2COMMERCE_ORDER_ID'); ?>: <?php echo $this->escape($order->order_id); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeConfirmationOrderStatus', array($this, $order, 'onJ2Commerce'))->getArgument('html', ''); ?>

                <div class="uk-card uk-card-default uk-margin-bottom">
                    <div class="uk-card-body">
                        <div>
                            <h3 class="uk-h6 uk-margin-small-bottom">
                                <?php echo Text::_('COM_J2COMMERCE_ORDERSTATUS'); ?>
                                <span class="uk-margin-small-left"><?php echo $statusBadgeHtml; ?></span>
                            </h3>
                            <p class="<?php echo $tierMessageClass; ?> uk-margin-remove uk-text-small">
                                <?php echo Text::_($tierMessage); ?>
                            </p>
                            <?php if ($mapLoaded) : ?>
                                <div id="j2c-confirmation-map" class="leaflet-map-container uk-margin-top" role="img" aria-label="<?php echo $this->escape($mapAddress); ?>"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>


                <?php if (!empty($this->plugin_html)) : ?>
                    <div class="uk-card uk-card-default uk-margin-bottom">
                        <div class="uk-card-body">
                            <?php echo $this->plugin_html; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isCancelled) : ?>
                    <?php // Email updates card ?>
                    <?php if (!empty($order->user_email)) : ?>
                        <div class="uk-card uk-card-default uk-margin-bottom">
                            <div class="uk-card-body uk-flex uk-flex-middle" style="gap: 12px;">
                                <span class="icon-envelope uk-text-meta" style="font-size: 1.25rem;" aria-hidden="true"></span>
                                <div>
                                    <h3 class="uk-h6 uk-margin-small-bottom"><?php echo Text::_('COM_J2COMMERCE_ORDER_UPDATES'); ?></h3>
                                    <p class="uk-text-meta uk-margin-remove uk-text-small">
                                        <?php echo Text::_('COM_J2COMMERCE_ORDER_UPDATES_DESC'); ?>
                                        <br><strong><?php echo $this->escape($order->user_email); ?></strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeConfirmationOrderDetails', array($this, $order, 'onJ2Commerce'))->getArgument('html', ''); ?>

                    <?php // Customer information grid ?>
                    <?php if ($info) : ?>
                        <div class="uk-card uk-card-default uk-margin-bottom">
                            <div class="uk-card-body">
                                <h3 class="uk-h6 uk-margin-bottom"><?php echo Text::_('COM_J2COMMERCE_ORDER_DETAILS'); ?></h3>

                                <div class="uk-grid uk-grid-medium" uk-grid>
                                    <?php // Contact information ?>
                                    <div class="uk-width-1-2@s">
                                        <h4 class="j2c-info-label uk-text-uppercase uk-text-bold uk-text-small uk-margin-small-bottom">
                                            <?php echo Text::_('COM_J2COMMERCE_CONTACT_INFORMATION'); ?>
                                        </h4>
                                        <p class="uk-margin-remove uk-text-small">
                                            <?php echo $this->escape($order->user_email); ?>
                                            <?php if (!empty($info->billing_phone_1)) : ?>
                                                <br><?php echo $this->escape($info->billing_phone_1); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <?php // Payment method ?>
                                    <div class="uk-width-1-2@s">
                                        <h4 class="j2c-info-label uk-text-uppercase uk-text-bold uk-text-small uk-margin-small-bottom">
                                            <?php echo Text::_('COM_J2COMMERCE_PAYMENT_METHOD'); ?>
                                        </h4>
                                        <p class="uk-margin-remove uk-text-small">
                                            <?php echo $paymentDisplay; ?>
                                            <br><?php echo $fmt((float) $order->order_total); ?>
                                        </p>
                                    </div>

                                    <?php // Shipping address ?>
                                    <?php if ((int) $order->is_shippable) : ?>
                                        <div class="uk-width-1-2@s">
                                            <h4 class="j2c-info-label uk-text-uppercase uk-text-bold uk-text-small uk-margin-small-bottom">
                                                <?php echo Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'); ?>
                                            </h4>
                                            <address class="uk-margin-remove uk-text-small">
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
                                                <br><?php echo $this->escape(trim(($info->shipping_city ?? '') . ', ' . ($info->shipping_zone_name ?? '') . ' ' . ($info->shipping_zip ?? ''))); ?>
                                                <?php if (!empty($info->shipping_country_name)) : ?>
                                                    <br><?php echo $this->escape($info->shipping_country_name); ?>
                                                <?php endif; ?>
                                            </address>
                                        </div>
                                    <?php endif; ?>

                                    <?php // Billing address ?>
                                    <div class="uk-width-1-2@s">
                                        <h4 class="j2c-info-label uk-text-uppercase uk-text-bold uk-text-small uk-margin-small-bottom">
                                            <?php echo Text::_('COM_J2COMMERCE_BILLING_ADDRESS'); ?>
                                        </h4>
                                        <address class="uk-margin-remove uk-text-small">
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
                                            <br><?php echo $this->escape(trim(($info->billing_city ?? '') . ', ' . ($info->billing_zone_name ?? '') . ' ' . ($info->billing_zip ?? ''))); ?>
                                            <?php if (!empty($info->billing_country_name)) : ?>
                                                <br><?php echo $this->escape($info->billing_country_name); ?>
                                            <?php endif; ?>
                                        </address>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Shipping method card ?>
                    <?php if ((int) $order->is_shippable && !empty($shippings)) : ?>
                        <div class="uk-card uk-card-default uk-margin-bottom">
                            <div class="uk-card-body">
                                <h4 class="j2c-info-label uk-text-uppercase uk-text-bold uk-text-small uk-margin-small-bottom">
                                    <?php echo Text::_('COM_J2COMMERCE_SHIPPING_METHOD'); ?>
                                </h4>
                                <?php foreach ($shippings as $shipping) : ?>
                                    <p class="uk-margin-remove uk-text-small">
                                        <?php echo $this->escape($shipping->ordershipping_name); ?>
                                        &middot;
                                        <?php echo $fmt((float) ($shipping->ordershipping_price ?? 0)); ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php // Continue shopping + need help ?>
                    <div class="uk-flex uk-flex-wrap uk-flex-middle uk-flex-center uk-margin-bottom" style="gap: 12px;">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="uk-button uk-button-primary">
                            <?php echo Text::_('COM_J2COMMERCE_CONTINUE_SHOPPING'); ?>
                        </a>
                        <?php if (!empty($this->order_link)) : ?>
                            <a href="<?php echo $this->order_link; ?>" class="uk-button uk-button-default">
                                <?php echo Text::_('COM_J2COMMERCE_VIEW_ORDER_HISTORY'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <p class="uk-text-meta uk-text-small uk-margin-bottom">
                        <?php echo Text::_('COM_J2COMMERCE_NEED_HELP'); ?>
                        <a href="<?php echo Route::_('index.php?option=com_contact'); ?>">
                            <?php echo Text::_('COM_J2COMMERCE_CONTACT_US'); ?>
                        </a>
                    </p>

                <?php else : ?>
                    <?php // Cancelled order actions ?>
                    <div class="uk-flex uk-flex-wrap uk-flex-middle uk-margin-bottom" style="gap: 12px;">
                        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=products'); ?>" class="uk-button uk-button-secondary">
                            <?php echo Text::_('COM_J2COMMERCE_CONTINUE_SHOPPING'); ?>
                        </a>
                    </div>
                <?php endif; ?>

            </div>

            <?php // Right column - Order summary sidebar (only for non-cancelled orders) ?>
            <?php if (!$isCancelled) : ?>
                <div class="uk-width-1-3@l j2c-sidebar-bg uk-padding">
                    <h3 class="uk-h5 uk-margin-bottom"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h3>

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
                        <div class="uk-margin-bottom">
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
                                <div class="uk-flex uk-flex-top uk-margin-small-bottom" style="gap: 12px;">
                                    <?php // Product image with quantity badge ?>
                                    <div class="uk-position-relative uk-flex-none">
                                        <?php if (!empty($thumb)) : ?>
                                            <img src="<?php echo $this->escape($thumb); ?>"
                                                 alt="<?php echo $this->escape($item->orderitem_name); ?>"
                                                 class="j2c-order-item-img uk-border-rounded uk-border" loading="lazy">
                                        <?php else : ?>
                                            <div class="j2c-item-placeholder uk-border-rounded uk-border uk-flex uk-flex-middle uk-flex-center">
                                                <span class="icon-image uk-text-muted" aria-hidden="true"></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($qty > 1) : ?>
                                            <span class="j2c-order-item-qty uk-position-top-right uk-badge">
                                                <?php echo $qty; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php // Product name + options ?>
                                    <div class="uk-flex-1 uk-text-small">
                                        <span class="uk-text-bold"><?php echo $this->escape($item->orderitem_name); ?></span>
                                        <?php if (!empty($item->orderitemattributes)) : ?>
                                            <br><?php echo LayoutHelper::render('orderitem.attributes', [
                                                'attributes' => $item->orderitemattributes,
                                                'item'       => $item,
                                                'context'    => 'confirmation',
                                                'variant'    => 'compact',
                                                'framework'  => 'uikit',
                                            ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item->orderitem_sku)) : ?>
                                            <br><span class="uk-text-muted"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>: <?php echo $this->escape($item->orderitem_sku); ?></span>
                                        <?php endif; ?>
                                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $order, &$this->params]); ?>
                                    </div>
                                    <?php // Item price ?>
                                    <div class="uk-text-right uk-text-small uk-text-bold uk-text-nowrap uk-flex-none">
                                        <?php echo $fmt($lineTotal); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php // Discount code badges ?>
                    <?php if (!empty($discounts)) : ?>
                        <div class="uk-margin-small-bottom">
                            <?php foreach ($discounts as $disc) : ?>
                                <?php if (!empty($disc->discount_code)) : ?>
                                    <span class="uk-badge uk-margin-small-right uk-margin-small-bottom">
                                        <span class="icon-tag uk-margin-small-right" aria-hidden="true"></span>
                                        <?php echo $this->escape($disc->discount_code); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <?php // Summary lines ?>
                    <div class="uk-text-small">
                        <?php // Plugin-contributed extra summary lines ?>
                        <?php foreach (J2CommerceHelper::plugin()->eventWithArray('GetOrderSummaryExtraRows', [$order]) as $extraRow) : ?>
                            <?php if (\is_array($extraRow) && isset($extraRow['label'], $extraRow['value'])) : ?>
                                <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                    <span><?php echo $this->escape($extraRow['label']); ?></span>
                                    <span><?php echo $this->escape($extraRow['value']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php // Subtotal ?>
                        <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                            <span><?php echo Text::_('COM_J2COMMERCE_CART_SUBTOTAL'); ?></span>
                            <span><?php echo $fmt((float) $order->order_subtotal); ?></span>
                        </div>

                        <?php // Shipping — show actual method name ?>
                        <?php if ((int) $order->is_shippable) : ?>
                            <?php
                            $shippingLabel = Text::_('COM_J2COMMERCE_CART_SHIPPING');
                            if (!empty($shippings)) {
                                $firstShipping = reset($shippings);
                                if (!empty($firstShipping->ordershipping_name)) {
                                    $shippingLabel = Text::_(stripslashes($firstShipping->ordershipping_name));
                                }
                            }
                            ?>
                            <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                <span><?php echo $this->escape($shippingLabel); ?></span>
                                <span><?php echo $fmt((float) $order->order_shipping); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php // Fees/Surcharges ?>
                        <?php if (!empty($fees)) : ?>
                            <?php foreach ($fees as $fee) : ?>
                                <?php if ((float) ($fee->amount ?? 0) > 0) : ?>
                                    <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                        <span><?php echo $this->escape($fee->name ?: Text::_('COM_J2COMMERCE_CART_SURCHARGE')); ?></span>
                                        <span><?php echo $fmt((float) $fee->amount); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php elseif ((float) ($order->order_surcharge ?? 0) > 0) : ?>
                            <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                <span><?php echo Text::_('COM_J2COMMERCE_CART_SURCHARGE'); ?></span>
                                <span><?php echo $fmt((float) $order->order_surcharge); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php // Discounts — show each with coupon/voucher name ?>
                        <?php if (!empty($discounts)) : ?>
                            <?php foreach ($discounts as $disc) : ?>
                                <?php $discountAmount = (float) ($disc->discount_amount ?? 0); ?>
                                <?php if ($discountAmount > 0) : ?>
                                    <?php
                                    $discountLabel = $disc->discount_title ?? $disc->discount_code ?? Text::_('COM_J2COMMERCE_CART_DISCOUNT');
                                    ?>
                                    <div class="uk-flex uk-flex-between uk-margin-small-bottom uk-text-success">
                                        <span><?php echo $this->escape($discountLabel); ?></span>
                                        <span>-<?php echo $fmt($discountAmount); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php elseif ((float) $order->order_discount > 0) : ?>
                            <div class="uk-flex uk-flex-between uk-margin-small-bottom uk-text-success">
                                <span><?php echo Text::_('COM_J2COMMERCE_CART_DISCOUNT'); ?></span>
                                <span>-<?php echo $fmt((float) $order->order_discount); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php // Tax lines — show tax profile name with percentage ?>
                        <?php if (!empty($taxes)) : ?>
                            <?php foreach ($taxes as $tax) : ?>
                                <?php
                                $taxTitle = $tax->ordertax_title ?? Text::_('COM_J2COMMERCE_CART_TAX');
                                $taxPercent = (float) ($tax->ordertax_percent ?? 0);

                                if ($taxPercent > 0) {
                                    $taxLabel = $checkoutPriceDisplay
                                        ? Text::sprintf('COM_J2COMMERCE_CART_TAX_INCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%')
                                        : Text::sprintf('COM_J2COMMERCE_CART_TAX_EXCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%');
                                } else {
                                    $taxLabel = Text::_($taxTitle);
                                }
                                ?>
                                <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                    <span><?php echo $this->escape($taxLabel); ?></span>
                                    <span><?php echo $fmt((float) $tax->ordertax_amount); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif ((float) $order->order_tax > 0) : ?>
                            <div class="uk-flex uk-flex-between uk-margin-small-bottom">
                                <span><?php echo Text::_('COM_J2COMMERCE_CART_TAX'); ?></span>
                                <span><?php echo $fmt((float) $order->order_tax); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <?php // Grand total ?>
                    <div class="uk-flex uk-flex-between uk-flex-middle j2c-summary-total uk-text-bold">
                        <span><?php echo Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'); ?></span>
                        <span>
                            <?php echo $fmt((float) $order->order_total); ?>
                            <?php if (!empty($currencyCode)) : ?>
                                <span class="j2c-summary-currency uk-badge uk-margin-small-left"><?php echo $this->escape($currencyCode); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php // Order date ?>
                    <?php if (!empty($orderDate)) : ?>
                        <p class="uk-text-muted uk-text-small uk-margin-top uk-margin-remove-bottom">
                            <?php echo Text::_('COM_J2COMMERCE_ORDER_DATE'); ?>: <?php echo $orderDate; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <jdoc:include type="modules" name="j2commerce-postpayment-bottom" style="none" />

        <?php echo $afterPostHtml; ?>

        <?php if (\is_object($order) && isset($order->orderpayment_type) && $order->orderpayment_type === 'free') : ?>
            <jdoc:include type="modules" name="j2commerce-postpayment-bottom-free" style="none" />
        <?php endif; ?>
    </div>
</div>
