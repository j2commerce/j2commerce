<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;
$currencyCode = $item->currency_code ?? '';

$fmt = static fn(float $amount): string => CurrencyHelper::format($amount, $currencyCode);

$checkoutPriceDisplay = (int) ComponentHelper::getParams('com_j2commerce')->get('checkout_price_display_options', 1);

$taxes     = $item->ordertaxes ?? [];
$discounts = $item->orderdiscounts ?? [];
$shipping  = $item->ordershipping ?? null;
$fees      = $item->orderfees ?? [];

?>
<div class="card mb-4 order-card order-summary-card">
    <div class="card-header">
        <h4 class="card-title mb-0"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h4>
    </div>
    <div class="card-body">
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderSummery', array(&$order,&$items))->getArgument('html', ''); ?>
        <div class="table-responsive">
            <table class="table order-summary-table mb-0 bg-transparent">
                <thead class="visually-hidden">
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_ITEM'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_J2COMMERCE_AMOUNT'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo Text::_('COM_J2COMMERCE_SUBTOTAL'); ?></td>
                        <td class="text-end"><?php echo $fmt((float) $item->order_subtotal); ?></td>
                    </tr>

                    <?php // Shipping - show actual method name ?>
                    <?php if ((float) $item->order_shipping > 0) : ?>
                        <?php
                        $shippingLabel = Text::_('COM_J2COMMERCE_SHIPPING');
                        if ($shipping && !empty($shipping->ordershipping_name)) {
                            $shippingLabel = Text::_(stripslashes($shipping->ordershipping_name));
                        }
                        ?>
                        <tr>
                            <td><?php echo $this->escape($shippingLabel); ?></td>
                            <td class="text-end"><?php echo $fmt((float) $item->order_shipping); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php // Fees/Surcharges — show each with its actual name ?>
                    <?php if (!empty($fees)) : ?>
                        <?php foreach ($fees as $fee) : ?>
                            <?php $feeAmount = (float) ($fee->amount ?? 0); ?>
                            <?php if ($feeAmount > 0) : ?>
                                <tr>
                                    <td><?php echo $this->escape($fee->name ?: Text::_('COM_J2COMMERCE_SURCHARGE')); ?></td>
                                    <td class="text-end"><?php echo $fmt($feeAmount); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ((float) ($item->order_surcharge ?? 0) > 0) : ?>
                        <tr>
                            <td><?php echo Text::_('COM_J2COMMERCE_SURCHARGE'); ?></td>
                            <td class="text-end"><?php echo $fmt((float) $item->order_surcharge); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php // Discounts - each on its own line ?>
                    <?php if (!empty($discounts)) : ?>
                        <?php foreach ($discounts as $disc) : ?>
                            <?php $discountAmount = (float) ($disc->discount_amount ?? 0); ?>
                            <?php if ($discountAmount > 0) : ?>
                                <?php
                                if ($disc->discount_type === 'coupon') {
                                    $discountLabel = Text::sprintf('COM_J2COMMERCE_COUPON_TITLE', $disc->discount_title ?: $disc->discount_code);
                                } elseif ($disc->discount_type === 'voucher') {
                                    $discountLabel = Text::sprintf('COM_J2COMMERCE_VOUCHER_TITLE', $disc->discount_title ?: $disc->discount_code);
                                } else {
                                    $discountLabel = Text::sprintf('COM_J2COMMERCE_DISCOUNT_TITLE', $disc->discount_title ?: $disc->discount_code ?: Text::_('COM_J2COMMERCE_CART_DISCOUNT'));
                                }
                                ?>
                                <tr>
                                    <td><?php echo $this->escape($discountLabel); ?></td>
                                    <td class="text-end text-danger">-<?php echo $fmt($discountAmount); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ((float) $item->order_discount > 0) : ?>
                        <tr>
                            <td><?php echo Text::_('COM_J2COMMERCE_DISCOUNT'); ?></td>
                            <td class="text-end text-danger">-<?php echo $fmt((float) $item->order_discount); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php // Tax lines - each tax profile on its own line ?>
                    <?php if (!empty($taxes)) : ?>
                        <?php foreach ($taxes as $tax) : ?>
                            <?php
                            $taxTitle   = $tax->ordertax_title ?? Text::_('COM_J2COMMERCE_CART_TAX');
                            $taxPercent = (float) ($tax->ordertax_percent ?? 0);

                            if ($taxPercent > 0) {
                                $taxLabel = $checkoutPriceDisplay
                                    ? Text::sprintf('COM_J2COMMERCE_CART_TAX_INCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%')
                                    : Text::sprintf('COM_J2COMMERCE_CART_TAX_EXCLUDED_TITLE', Text::_($taxTitle), $taxPercent . '%');
                            } else {
                                $taxLabel = Text::_($taxTitle);
                            }
                            ?>
                            <tr>
                                <td><?php echo $this->escape($taxLabel); ?></td>
                                <td class="text-end"><?php echo $fmt((float) $tax->ordertax_amount); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ((float) $item->order_tax > 0) : ?>
                        <tr>
                            <td><?php echo Text::_('COM_J2COMMERCE_TAX'); ?></td>
                            <td class="text-end"><?php echo $fmt((float) $item->order_tax); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td><strong><?php echo Text::_('COM_J2COMMERCE_TOTAL'); ?></strong></td>
                        <td class="text-end"><strong><?php echo $fmt((float) $item->order_total); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderSummery', array($item))->getArgument('html', ''); ?>
    </div>
</div>
