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

/** @var \J2Commerce\Component\J2commerce\Site\View\Checkout\HtmlView $this */

$platform = J2CommerceHelper::platform();
$showItemTax = (int) $this->params->get('show_item_tax', 0);
$showThumbCart = (int) $this->params->get('show_thumb_cart', 1);
$showPriceField = (int) $this->params->get('show_price_field', 1);
$showSku = (int) $this->params->get('show_sku', 1);
$checkoutPriceDisplay = (int) $this->params->get('checkout_price_display_options', 0);

// Column count for footer colspan
$colspan = 2; // Product + Total always shown
if ($showSku) {
    $colspan++;
}
if ($showItemTax && isset($this->taxes) && \count($this->taxes)) {
    $colspan++;
}

?>
<div class="j2commerce-checkout-summary">
    <h5 class="mb-3"><?php echo Text::_('COM_J2COMMERCE_ORDER_SUMMARY'); ?></h5>

    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM'); ?></th>
                    <?php if ($showSku) : ?>
                        <th><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_SKU'); ?></th>
                    <?php endif; ?>
                    <th class="text-center" style="width: 80px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY'); ?></th>
                    <?php if ($showPriceField) : ?>
                        <th class="text-end"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_UNIT_PRICE'); ?></th>
                    <?php endif; ?>
                    <?php if ($showItemTax && isset($this->taxes) && \count($this->taxes)) : ?>
                        <th class="text-end"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TAX'); ?></th>
                    <?php endif; ?>
                    <th class="text-end" style="width: 120px;"><?php echo Text::_('COM_J2COMMERCE_CART_LINE_ITEM_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) : ?>
                    <?php
                    $itemParams = $platform->getRegistry($item->orderitem_params ?? '{}');
                    $rawThumbImage = (string) $itemParams->get('thumb_image', '');
                    $thumbImage = $rawThumbImage !== ''
                        ? HTMLHelper::_('cleanImageURL', $platform->getImagePath($rawThumbImage))->url
                        : '';
                    $qty = (int) ($item->orderitem_quantity ?? $item->product_qty ?? 1);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-start gap-2">
                                <?php if ($showThumbCart && !empty($thumbImage)) : ?>
                                    <img src="<?php echo $thumbImage; ?>"
                                         alt="<?php echo $this->escape($item->orderitem_name); ?>"
                                         class="rounded flex-shrink-0"
                                         width="50">
                                <?php endif; ?>
                                <div>
                                    <span class="fw-bold"><?php echo $this->escape($item->orderitem_name); ?></span>
                                    <?php if (!empty($item->orderitemattributes)) : ?>
                                        <div class="mt-1">
                                            <?php echo LayoutHelper::render('orderitem.attributes', [
                                                'attributes' => $item->orderitemattributes,
                                                'item'       => $item,
                                                'context'    => 'checkout_summary',
                                                'variant'    => 'compact',
                                            ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', [$item, $this->order, &$this->params]); ?>
                                </div>
                            </div>
                        </td>

                        <?php if ($showSku) : ?>
                            <td>
                                <small class="text-muted"><?php echo $this->escape($item->orderitem_sku ?? ''); ?></small>
                            </td>
                        <?php endif; ?>

                        <td class="text-center"><?php echo $qty; ?></td>

                        <?php if ($showPriceField) : ?>
                            <td class="text-end">
                                <?php echo $this->currency->format(
                                    $this->order->get_formatted_lineitem_price($item, $checkoutPriceDisplay)
                                ); ?>
                            </td>
                        <?php endif; ?>

                        <?php if ($showItemTax && isset($this->taxes) && \count($this->taxes)) : ?>
                            <td class="text-end">
                                <?php echo $this->currency->format($item->orderitem_tax ?? 0); ?>
                            </td>
                        <?php endif; ?>

                        <td class="text-end fw-bold">
                            <?php echo $this->currency->format(
                                $this->order->get_formatted_lineitem_total($item, $checkoutPriceDisplay)
                            ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <?php if ($this->order && ($totals = $this->order->get_formatted_order_totals())) : ?>
                    <?php foreach ($totals as $key => $total) : ?>
                        <tr<?php echo $key === 'grandtotal' ? ' class="fw-bold fs-5"' : ''; ?>>
                            <td colspan="<?php echo $colspan; ?>" class="text-end">
                                <?php echo $total['label']; ?>
                            </td>
                            <td class="text-end<?php echo $key === 'subtotal' ? ' fw-bold' : ''; ?>">
                                <?php echo $total['value']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>
</div>
