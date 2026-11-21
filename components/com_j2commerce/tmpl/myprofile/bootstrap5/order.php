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
use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \J2Commerce\Component\J2commerce\Site\View\Myprofile\HtmlView $this */

$order      = $this->order;
$items      = $this->orderItems;
$info       = $this->orderInfo;
$history    = $this->orderHistory;
$shippings  = $this->orderShippings;
$taxes      = $this->orderTaxes;
$fees       = $this->orderFees;
$platform   = J2CommerceHelper::platform();
$params     = $this->params;
$dateFormat = $params->get('date_format', 'Y-m-d');
$isPrint    = Factory::getApplication()->getInput()->getCmd('tmpl') === 'component';

if (!$order) {
    echo '<div class="alert alert-danger">' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
    return;
}

$currencyCode  = $order->currency_code ?? '';
$currencyValue = (float) ($order->currency_value ?? 1);

$fmt = static function (float $amount) use ($currencyCode, $currencyValue): string {
    return CurrencyHelper::format($amount, $currencyCode, $currencyValue);
};

$cssClass = !empty($order->orderstatus_cssclass) ? $order->orderstatus_cssclass : 'bg-secondary';
$statusName = Text::_($order->orderstatus_name ?? '');
?>

<div class="j2commerce j2commerce-order-detail">

    <?php if (!$isPrint): ?>
    <div class="mb-3 d-flex justify-content-between">
        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="text-decoration-none">
            &larr; <?php echo Text::_('COM_J2COMMERCE_BACK_TO_PROFILE'); ?>
        </a>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm j2commerce-order-print ms-2" data-url="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($order->order_id) . '&tmpl=component'); ?>">
                <span class="fa-solid fa-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
            </button>
            <?php if ($params->get('show_packingslip', 0)): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm j2commerce-packingslip-print ms-2" data-url="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=packingslip&order_id=' . urlencode($order->order_id) . '&tmpl=component'); ?>">
                    <span class="fa-solid fa-box" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT_PACKING_SLIP'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order header: ID / Date / Status -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered mb-0">
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER_ID'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER_DATE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ORDER_STATUS'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $this->escape($order->order_id); ?></td>
                    <td><?php echo HTMLHelper::_('date', $order->created_on, $dateFormat); ?></td>
                    <td><span class="badge <?php echo $this->escape($cssClass); ?>"><?php echo $this->escape($statusName); ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Addresses: Shipping + Billing side by side -->
    <?php if ($info): ?>
    <div class="row g-3 mb-4">
        <?php if (!empty($info->shipping_first_name)): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'); ?></h5>
                    <?php if (!empty($info->shipping_company)): ?><?php echo $this->escape($info->shipping_company); ?><br><?php endif; ?>
                    <?php echo $this->escape($info->shipping_first_name ?? '') . ' ' . $this->escape($info->shipping_last_name ?? ''); ?><br>
                    <?php echo $this->escape($info->shipping_address_1 ?? ''); ?><br>
                    <?php if (!empty($info->shipping_address_2)): ?><?php echo $this->escape($info->shipping_address_2); ?><br><?php endif; ?>
                    <?php echo $this->escape($info->shipping_city ?? ''); ?>
                    <?php if (!empty($info->shipping_zone_name)): ?>, <?php echo $this->escape($info->shipping_zone_name); ?><?php endif; ?>
                    <?php if (!empty($info->shipping_zip)): ?> <?php echo $this->escape($info->shipping_zip); ?><?php endif; ?><br>
                    <?php echo $this->escape($info->shipping_country_name ?? ''); ?>
                    <?php if (!empty($info->shipping_phone_1)): ?><br><?php echo $this->escape($info->shipping_phone_1); ?><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_BILLING_ADDRESS'); ?></h5>
                    <?php if (!empty($info->billing_company)): ?><?php echo $this->escape($info->billing_company); ?><br><?php endif; ?>
                    <?php echo $this->escape($info->billing_first_name ?? '') . ' ' . $this->escape($info->billing_last_name ?? ''); ?><br>
                    <?php echo $this->escape($info->billing_address_1 ?? ''); ?><br>
                    <?php if (!empty($info->billing_address_2)): ?><?php echo $this->escape($info->billing_address_2); ?><br><?php endif; ?>
                    <?php echo $this->escape($info->billing_city ?? ''); ?>
                    <?php if (!empty($info->billing_zone_name)): ?>, <?php echo $this->escape($info->billing_zone_name); ?><?php endif; ?>
                    <?php if (!empty($info->billing_zip)): ?> <?php echo $this->escape($info->billing_zip); ?><?php endif; ?><br>
                    <?php echo $this->escape($info->billing_country_name ?? ''); ?>
                    <?php if (!empty($info->billing_phone_1)): ?><br><?php echo $this->escape($info->billing_phone_1); ?><?php endif; ?>
                    <?php if (!empty($info->billing_email)): ?><br><?php echo $this->escape($info->billing_email); ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Checkout fields: merchant-defined values switched on for the customer order view -->
    <?php $checkoutFields = $info ? CustomFieldHelper::describeOrderCustomFields($info, 'site') : []; ?>
    <?php if ($checkoutFields): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_ORDER_CHECKOUT_FIELDS'); ?></h5>
            <dl class="row mb-0">
                <?php foreach ($checkoutFields as $checkoutField): ?>
                <dt class="col-sm-4"><?php echo $this->escape($checkoutField['label']); ?></dt>
                <dd class="col-sm-8"><?php echo $checkoutField['multiline'] ? nl2br($this->escape($checkoutField['value'])) : $this->escape($checkoutField['value']); ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>
    <?php endif; ?>

    <!-- Customer note -->
    <?php if ((int) J2CommerceHelper::config()->get('show_customer_note', 1) === 1 && trim((string) ($order->customer_note ?? '')) !== ''): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_CHECKOUT_CUSTOMER_NOTE'); ?></h5>
            <p class="mb-0"><?php echo nl2br($this->escape($order->customer_note)); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment instructions: merchant-authored, so shown regardless of the customer-note option -->
    <?php $paymentInstructions = J2CommerceHelper::getPaymentInstructions($order); ?>
    <?php if ($paymentInstructions !== ''): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title fw-bold"><?php echo Text::_('COM_J2COMMERCE_PAYMENT_INSTRUCTIONS'); ?></h5>
            <div class="mb-0"><?php echo $paymentInstructions; ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Summary: items table -->
    <?php if (!empty($items)): ?>
    <h4 class="mb-3"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h4>
    <div class="table-responsive mb-3">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($params->get('show_thumb_cart', 0)): ?>
                    <th scope="col" style="width:60px"><span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_IMAGE'); ?></span></th>
                    <?php endif; ?>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM'); ?></th>
                    <?php if ($params->get('show_sku', 0)): ?>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?></th>
                    <?php endif; ?>
                    <?php if ($params->get('show_price_field', 1)): ?>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="text-center"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></th>
                    <?php if ($params->get('show_item_tax', 0)): ?>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TAX'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $lineItem): ?>
                <?php
                $thumb = '';

                if ($params->get('show_thumb_cart', 0)) {
                    $itemParams = !empty($lineItem->orderitem_params) ? json_decode($lineItem->orderitem_params, true) : [];
                    $rawThumb   = ImageHelper::getLineItemImage((string) ($itemParams['thumb_image'] ?? ''), (int) ($lineItem->product_id ?? 0));

                    $thumb = $rawThumb !== ''
                        ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumb))->url
                        : '';
                }
                ?>
                <tr>
                    <?php if ($params->get('show_thumb_cart', 0)): ?>
                    <td>
                        <?php if ($thumb): ?>
                        <img src="<?php echo $this->escape($thumb); ?>" alt="<?php echo $this->escape($lineItem->orderitem_name); ?>" class="img-fluid" style="max-width:50px;max-height:50px;">
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php echo $this->escape($lineItem->orderitem_name); ?>
                        <?php if (!empty($lineItem->orderitem_sku)): ?>
                        <br><small class="text-body-secondary"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>: <?php echo $this->escape($lineItem->orderitem_sku); ?></small>
                        <?php endif; ?>
                        <?php if (!empty($lineItem->orderitemattributes)): ?>
                        <br><?php echo LayoutHelper::render('orderitem.attributes', [
                            'attributes' => $lineItem->orderitemattributes,
                            'item'       => $lineItem,
                            'context'    => 'myprofile',
                            'variant'    => 'compact',
                        ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                        <?php endif; ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$lineItem, $order, &$params]); ?>
                    </td>
                    <?php if ($params->get('show_sku', 0)): ?>
                    <td><?php echo $this->escape($lineItem->orderitem_sku); ?></td>
                    <?php endif; ?>
                    <?php if ($params->get('show_price_field', 1)): ?>
                    <td class="text-end"><?php echo $fmt((float) $lineItem->orderitem_price); ?></td>
                    <?php endif; ?>
                    <td class="text-center"><?php echo (int) $lineItem->orderitem_quantity; ?></td>
                    <?php if ($params->get('show_item_tax', 0)): ?>
                    <td class="text-end"><?php echo $fmt((float) ($lineItem->orderitem_tax ?? 0)); ?></td>
                    <?php endif; ?>
                    <td class="text-end"><?php echo $fmt((float) $lineItem->orderitem_finalprice); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="row">
        <div class="col-md-6 offset-md-6">
            <table class="table table-sm">
                <tbody>
                    <?php
                    // One builder answers for checkout, the confirmation page and this page, so every
                    // row printed below foots to order_total.
                    $totalRows = array_filter(
                        OrderHelper::getFormattedOrderTotals($order, [
                            'shippings' => $shippings,
                            'taxes'     => $taxes,
                            'fees'      => $fees,
                            'discounts' => [],
                        ]),
                        static fn (array $totalRow): bool => $totalRow['type'] !== 'grandtotal'
                    );
                    ?>
                    <?php // Plugin-contributed extra summary rows ?>
                    <?php foreach (J2CommerceHelper::plugin()->eventWithArray('GetOrderSummaryExtraRows', [$order]) as $extraRow): ?>
                        <?php if (\is_array($extraRow) && isset($extraRow['label'], $extraRow['value'])): ?>
                        <tr>
                            <td class="text-end"><?php echo $this->escape($extraRow['label']); ?></td>
                            <td class="text-end fw-bold"><?php echo $this->escape($extraRow['value']); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($totalRows as $totalRow): ?>
                    <tr>
                        <td class="text-end">
                            <?php echo $this->escape($totalRow['label']); ?>
                            <?php if (!empty($totalRow['tracking'])): ?>
                                <span class="d-block small text-body-secondary"><?php echo Text::sprintf('COM_J2COMMERCE_ORDER_TRACKING_NUMBER', nl2br($this->escape($totalRow['tracking']))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end<?php echo $totalRow['type'] === 'subtotal' ? ' fw-bold' : ($totalRow['type'] === 'discount' ? ' text-danger' : ''); ?>"><?php echo $totalRow['type'] === 'discount' ? '-' : ''; ?><?php echo CurrencyHelper::format($totalRow['display'], $currencyCode, 1.0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="border-top">
                        <td class="text-end fs-5"><?php echo Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'); ?></td>
                        <td class="text-end fs-5 fw-bold">
                            <?php if (!empty($currencyCode)): ?>
                            <span class="badge bg-secondary me-1"><?php echo $this->escape($currencyCode); ?></span>
                            <?php endif; ?>
                            <?php echo $fmt((float) $order->order_total); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$isPrint): ?>
        <?php echo LayoutHelper::render('download.list', [
            'downloads'  => $this->downloads,
            'framework'  => 'bootstrap5',
            'dateFormat' => $dateFormat,
        ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
    <?php endif; ?>
</div>

<?php if (!$isPrint): ?>
<!-- Order Print Modal (shared with myprofile dashboard) -->
<div class="modal fade" id="j2commerceOrderModal" tabindex="-1" aria-labelledby="j2commerceOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="j2commerceOrderModalLabel"><?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body" id="j2commerceOrderModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_LOADING'); ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCLOSE'); ?></button>
                <button type="button" class="btn btn-primary" id="j2commerceOrderPrintBtn">
                    <span class="fa-solid fa-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isPrint): ?>
<script>window.print();</script>
<?php endif; ?>
