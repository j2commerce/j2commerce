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
    echo '<div class="uk-alert uk-alert-danger" uk-alert>' . Text::_('COM_J2COMMERCE_ORDER_MISMATCH') . '</div>';
    return;
}

$currencyCode  = $order->currency_code ?? '';
$currencyValue = (float) ($order->currency_value ?? 1);

$fmt = static function (float $amount) use ($currencyCode, $currencyValue): string {
    return CurrencyHelper::format($amount, $currencyCode, $currencyValue);
};

$cssClass = !empty($order->orderstatus_cssclass) ? $order->orderstatus_cssclass : 'uk-badge';
$statusName = !empty($order->orderstatus_name) ? Text::_($order->orderstatus_name) : $this->escape($order->order_state ?? '');
?>

<div class="j2commerce j2commerce-order-detail">

    <?php if (!$isPrint): ?>
    <div class="uk-margin-bottom uk-flex uk-flex-between">
        <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile'); ?>" class="uk-link-reset">
            &larr; <?php echo Text::_('COM_J2COMMERCE_BACK_TO_PROFILE'); ?>
        </a>
        <?php if (!$isPrint): ?>
            <button type="button" class="uk-button uk-button-small uk-button-default j2commerce-order-print" data-url="<?php echo Route::_('index.php?option=com_j2commerce&view=myprofile&layout=order&order_id=' . urlencode($order->order_id) . '&tmpl=component'); ?>">
                <span class="icon-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
            </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Order header: ID / Date / Status -->
    <div class="uk-overflow-auto uk-margin-bottom">
        <table class="uk-table uk-table-bordered uk-margin-remove">
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
                    <td><span class="uk-badge <?php echo $this->escape($cssClass); ?>"><?php echo $statusName; ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Addresses: Shipping + Billing side by side -->
    <?php if ($info): ?>
    <div class="uk-grid uk-grid-small uk-margin-bottom" uk-grid>
        <?php if (!empty($info->shipping_first_name)): ?>
        <div class="uk-width-1-2@m">
            <div class="uk-card uk-card-default uk-height-1-1">
                <div class="uk-card-body">
                    <h5 class="uk-card-title uk-text-bold"><?php echo Text::_('COM_J2COMMERCE_SHIPPING_ADDRESS'); ?></h5>
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
        <div class="uk-width-1-2@m">
            <div class="uk-card uk-card-default uk-height-1-1">
                <div class="uk-card-body">
                    <h5 class="uk-card-title uk-text-bold"><?php echo Text::_('COM_J2COMMERCE_BILLING_ADDRESS'); ?></h5>
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

    <!-- Order Summary: items table -->
    <?php if (!empty($items)): ?>
    <h4 class="uk-margin-bottom" style="color: #8b0000;"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h4>
    <div class="uk-overflow-auto uk-margin-bottom">
        <table class="uk-table uk-table-striped">
            <thead>
                <tr>
                    <?php if ($params->get('show_thumb_cart', 0)): ?>
                    <th scope="col" style="width:60px"><span class="uk-hidden-visually"><?php echo Text::_('COM_J2COMMERCE_IMAGE'); ?></span></th>
                    <?php endif; ?>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM'); ?></th>
                    <?php if ($params->get('show_sku', 0)): ?>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?></th>
                    <?php endif; ?>
                    <?php if ($params->get('show_price_field', 1)): ?>
                    <th scope="col" class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="uk-text-center"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></th>
                    <?php if ($params->get('show_item_tax', 0)): ?>
                    <th scope="col" class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TAX'); ?></th>
                    <?php endif; ?>
                    <th scope="col" class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $lineItem): ?>
                <?php
                $itemParams = !empty($lineItem->orderitem_params) ? json_decode($lineItem->orderitem_params, true) : [];
                $rawThumb = (string) ($itemParams['thumb_image'] ?? '');
                $thumb = $rawThumb !== ''
                    ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumb))->url
                    : '';
                ?>
                <tr>
                    <?php if ($params->get('show_thumb_cart', 0)): ?>
                    <td>
                        <?php if ($thumb): ?>
                        <img src="<?php echo $this->escape($thumb); ?>" alt="" style="max-width:50px;max-height:50px;">
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php echo $this->escape($lineItem->orderitem_name); ?>
                        <?php if (!empty($lineItem->orderitem_sku)): ?>
                        <br><small class="uk-text-meta"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?>: <?php echo $this->escape($lineItem->orderitem_sku); ?></small>
                        <?php endif; ?>
                        <?php if (!empty($lineItem->orderitemattributes)): ?>
                        <br><?php echo LayoutHelper::render('orderitem.attributes', [
                            'attributes' => $lineItem->orderitemattributes,
                            'item'       => $lineItem,
                            'context'    => 'myprofile',
                            'variant'    => 'compact',
                            'framework'  => 'uikit',
                        ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                        <?php endif; ?>
                        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$lineItem, $order, &$params]); ?>
                    </td>
                    <?php if ($params->get('show_sku', 0)): ?>
                    <td><?php echo $this->escape($lineItem->orderitem_sku); ?></td>
                    <?php endif; ?>
                    <?php if ($params->get('show_price_field', 1)): ?>
                    <td class="uk-text-right"><?php echo $fmt((float) $lineItem->orderitem_price); ?></td>
                    <?php endif; ?>
                    <td class="uk-text-center"><?php echo (int) $lineItem->orderitem_quantity; ?></td>
                    <?php if ($params->get('show_item_tax', 0)): ?>
                    <td class="uk-text-right"><?php echo $fmt((float) ($lineItem->orderitem_tax ?? 0)); ?></td>
                    <?php endif; ?>
                    <td class="uk-text-right"><?php echo $fmt((float) $lineItem->orderitem_finalprice); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Totals -->
    <div class="uk-grid" uk-grid>
        <div class="uk-width-1-2@m uk-push-1-2@m">
            <table class="uk-table uk-table-small">
                <tbody>
                    <?php // Plugin-contributed extra summary rows ?>
                    <?php foreach (J2CommerceHelper::plugin()->eventWithArray('GetOrderSummaryExtraRows', [$order]) as $extraRow): ?>
                        <?php if (\is_array($extraRow) && isset($extraRow['label'], $extraRow['value'])): ?>
                        <tr>
                            <td class="uk-text-right"><?php echo $this->escape($extraRow['label']); ?></td>
                            <td class="uk-text-right uk-text-bold"><?php echo $this->escape($extraRow['value']); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_SUBTOTAL'); ?></td>
                        <td class="uk-text-right uk-text-bold"><?php echo $fmt((float) ($order->order_subtotal ?? 0)); ?></td>
                    </tr>
                    <?php // Shipping ?>
                    <?php if (!empty($shippings)): ?>
                        <?php foreach ($shippings as $shipping): ?>
                            <?php if ((float) ($shipping->ordershipping_price ?? 0) > 0 || !empty($shipping->ordershipping_name)): ?>
                            <tr>
                                <td class="uk-text-right"><?php echo $this->escape($shipping->ordershipping_name ?: Text::_('COM_J2COMMERCE_CART_SHIPPING')); ?></td>
                                <td class="uk-text-right"><?php echo $fmt((float) ($shipping->ordershipping_price ?? 0)); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ((float) ($order->order_shipping ?? 0) > 0): ?>
                    <tr>
                        <td class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_SHIPPING'); ?></td>
                        <td class="uk-text-right"><?php echo $fmt((float) $order->order_shipping); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php // Fees / surcharges (with actual names) ?>
                    <?php if (!empty($fees)): ?>
                        <?php foreach ($fees as $fee): ?>
                            <?php if ((float) ($fee->amount ?? 0) > 0): ?>
                            <tr>
                                <td class="uk-text-right"><?php echo $this->escape($fee->name ?: Text::_('COM_J2COMMERCE_CART_SURCHARGE')); ?></td>
                                <td class="uk-text-right"><?php echo $fmt((float) $fee->amount); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ((float) ($order->order_surcharge ?? 0) > 0): ?>
                    <tr>
                        <td class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_SURCHARGE'); ?></td>
                        <td class="uk-text-right"><?php echo $fmt((float) $order->order_surcharge); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php // Discounts ?>
                    <?php if ((float) ($order->order_discount ?? 0) > 0): ?>
                    <tr>
                        <td class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_DISCOUNT'); ?></td>
                        <td class="uk-text-right uk-text-danger">-<?php echo $fmt((float) $order->order_discount); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php // Taxes ?>
                    <?php if (!empty($taxes)): ?>
                        <?php foreach ($taxes as $tax): ?>
                            <?php if ((float) ($tax->ordertax_amount ?? 0) > 0): ?>
                            <tr>
                                <td class="uk-text-right">
                                    <?php
                                    $taxLabel = $this->escape($tax->ordertax_title ?: Text::_('COM_J2COMMERCE_CART_TAX'));
                                    $taxPct   = (float) ($tax->ordertax_percent ?? 0);
                                    echo $taxPct > 0 ? $taxLabel . ' (' . number_format($taxPct, 2) . '%)' : $taxLabel;
                                    ?>
                                </td>
                                <td class="uk-text-right"><?php echo $fmt((float) $tax->ordertax_amount); ?></td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ((float) ($order->order_tax ?? 0) > 0): ?>
                    <tr>
                        <td class="uk-text-right"><?php echo Text::_('COM_J2COMMERCE_CART_TAX'); ?></td>
                        <td class="uk-text-right"><?php echo $fmt((float) $order->order_tax); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="uk-border-top">
                        <td class="uk-text-right uk-text-large"><?php echo Text::_('COM_J2COMMERCE_CART_GRANDTOTAL'); ?></td>
                        <td class="uk-text-right uk-text-large uk-text-bold">
                            <?php if (!empty($currencyCode)): ?>
                            <span class="uk-badge uk-margin-small-right"><?php echo $this->escape($currencyCode); ?></span>
                            <?php endif; ?>
                            <?php echo $fmt((float) $order->order_total); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$isPrint): ?>
<!-- Order Print Modal (shared with myprofile dashboard) -->
<div id="j2commerceOrderModal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <div class="uk-modal-header">
            <h5 class="uk-modal-title" id="j2commerceOrderModalLabel"><?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?></h5>
            <button type="button" class="uk-modal-close-default" uk-close aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
        </div>
        <div class="uk-modal-body" id="j2commerceOrderModalBody">
            <div class="uk-text-center uk-padding">
                <span uk-spinner="ratio: 2" role="status" aria-label="<?php echo Text::_('COM_J2COMMERCE_LOADING'); ?>"></span>
            </div>
        </div>
        <div class="uk-modal-footer uk-text-right">
            <button type="button" class="uk-button uk-button-default uk-modal-close"><?php echo Text::_('JCLOSE'); ?></button>
            <button type="button" class="uk-button uk-button-primary" id="j2commerceOrderPrintBtn">
                <span class="icon-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ORDER_PRINT'); ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isPrint): ?>
<script>window.print();</script>
<?php endif; ?>
