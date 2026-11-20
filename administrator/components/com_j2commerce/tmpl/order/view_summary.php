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
use J2Commerce\Component\J2commerce\Administrator\Helper\OrderHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;
$currencyCode = $item->currency_code ?? '';

$fmt = static fn(float $amount): string => CurrencyHelper::format($amount, $currencyCode);

$taxes     = $item->ordertaxes ?? [];
$discounts = $item->orderdiscounts ?? [];
$shipping  = $item->ordershipping ?? null;
$fees      = $item->orderfees ?? [];

// The same rows the confirmation page prints, so every figure foots to order_total.
$totalRows = array_filter(
    OrderHelper::getFormattedOrderTotals($item, [
        'shippings' => $shipping ? [$shipping] : [],
        'taxes'     => $taxes,
        'fees'      => $fees,
        'discounts' => $discounts,
    ]),
    static fn (array $totalRow): bool => $totalRow['type'] !== 'grandtotal'
);

foreach ($totalRows as &$totalRow) {
    // COM_J2COMMERCE_ORDER_SHIPPING_TAX lives in the site language file only.
    if ($totalRow['key'] === 'shipping_tax') {
        $totalRow['label'] = Text::_('COM_J2COMMERCE_FIELD_SHIPPING_TAX');
    }

    if (str_starts_with($totalRow['key'], 'discount_') && isset($discounts[(int) substr($totalRow['key'], 9)])) {
        $disc  = $discounts[(int) substr($totalRow['key'], 9)];
        $title = $disc->discount_title ?: $disc->discount_code;

        $totalRow['label'] = match ($disc->discount_type) {
            'coupon'  => Text::sprintf('COM_J2COMMERCE_COUPON_TITLE', $title),
            'voucher' => Text::sprintf('COM_J2COMMERCE_VOUCHER_TITLE', $title),
            default   => Text::sprintf('COM_J2COMMERCE_DISCOUNT_TITLE', $title ?: Text::_('COM_J2COMMERCE_CART_DISCOUNT')),
        };
    }
}

unset($totalRow);

?>
<div class="card mb-4 order-card order-summary-card">
    <div class="card-header">
        <h2 class="card-title mb-0 fs-4"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h2>
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
                    <?php // Plugin-contributed extra summary rows ?>
                    <?php foreach (J2CommerceHelper::plugin()->eventWithArray('GetOrderSummaryExtraRows', [$item]) as $extraRow) : ?>
                        <?php if (\is_array($extraRow) && isset($extraRow['label'], $extraRow['value'])) : ?>
                            <tr>
                                <th scope="row"><?php echo $this->escape($extraRow['label']); ?></th>
                                <td class="text-end"><?php echo $this->escape($extraRow['value']); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($totalRows as $totalRow) : ?>
                        <tr>
                            <th scope="row"><?php echo $this->escape($totalRow['label']); ?></th>
                            <td class="text-end<?php echo $totalRow['type'] === 'discount' ? ' text-danger' : ''; ?>"><?php echo $totalRow['type'] === 'discount' ? '-' : ''; ?><?php echo $fmt($totalRow['amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <th scope="row"><strong><?php echo Text::_('COM_J2COMMERCE_TOTAL'); ?></strong></th>
                        <td class="text-end"><strong><?php echo $fmt((float) $item->order_total); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderSummery', array($item))->getArgument('html', ''); ?>
    </div>
</div>
