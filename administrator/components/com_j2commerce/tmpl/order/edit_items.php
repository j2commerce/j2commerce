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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$item       = $this->item;
$orderItems = $item->orderitems ?? [];
$currency   = $item->currency_code ?? 'USD';
$symbol     = CurrencyHelper::getSymbol($currency) ?: $currency;
$unitCount  = array_sum(array_map(static fn ($line): int => (int) $line->orderitem_quantity, $orderItems));

?>
<div class="row g-0 j2c-items-step">
    <?php // ── Left: catalog (card grid, populated via AJAX) ── ?>
    <div class="col-lg-5 d-flex flex-column border-end bg-light j2c-catalog-panel">
        <div class="p-3 border-bottom bg-white j2c-catalog-head">
            <h2 class="fs-6 fw-bold mb-2 j2c-catalog-title"><?php echo Text::_('COM_J2COMMERCE_ADD_PRODUCTS'); ?></h2>
            <div class="input-group j2c-catalog-search">
                <input type="text" class="form-control j2c-catalog-search-input" id="skuSearchInput"
                       placeholder="<?php echo $this->escape(Text::_('COM_J2COMMERCE_SEARCH_BY_NAME_OR_SKU')); ?>">
                <button type="button" class="btn btn-primary j2c-catalog-search-btn" id="skuSearchBtn"
                        aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_SEARCH_BY_NAME_OR_SKU')); ?>">
                    <span class="fa-solid fa-magnifying-glass" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <div class="p-3 overflow-auto flex-grow-1 j2c-catalog-body" style="max-height:560px;">
            <div class="row row-cols-2 g-2 j2c-catalog-grid d-none" id="j2c-catalog-grid"></div>
            <div class="j2c-catalog-empty text-center text-body-secondary py-5" id="j2c-catalog-empty">
                <div class="mb-2"><span class="fa-solid fa-magnifying-glass fa-2x" aria-hidden="true"></span></div>
                <?php echo Text::_('COM_J2COMMERCE_SEARCH_BY_NAME_OR_SKU'); ?>
            </div>
            <div class="j2c-catalog-pager d-none align-items-center justify-content-between mt-3" id="j2c-catalog-pager">
                <button type="button" class="btn btn-sm btn-outline-secondary j2c-pager-prev" data-j2c-page="prev">
                    <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span> <?php echo Text::_('JPREVIOUS'); ?>
                </button>
                <span class="j2c-pager-info small text-body-secondary" id="j2c-pager-info"></span>
                <button type="button" class="btn btn-sm btn-outline-secondary j2c-pager-next" data-j2c-page="next">
                    <?php echo Text::_('JNEXT'); ?> <span class="fa-solid fa-chevron-right ms-1" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>

    <?php // ── Right: cart (order items) ── ?>
    <div class="col-lg-7 d-flex flex-column j2c-cart-panel">
        <div class="d-flex align-items-center justify-content-between p-3 border-bottom j2c-cart-head">
            <div>
                <div class="fw-bold j2c-cart-title" style="font-size:15px;color:#1f2b38;"><?php echo Text::_('COM_J2COMMERCE_ORDER_ITEMS'); ?></div>
                <div class="text-body-secondary j2c-cart-sub" style="font-size:12px;"><?php echo !empty($item->orderstatus_name) ? $this->escape(Text::_($item->orderstatus_name)) : ''; ?></div>
            </div>
            <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 j2c-units-pill<?php echo $unitCount === 0 ? ' d-none' : ''; ?>" id="j2c-units-pill"><?php echo Text::plural('COM_J2COMMERCE_N_ITEMS', $unitCount); ?></span>
        </div>

        <div class="overflow-auto flex-grow-1 j2c-cart-body" style="max-height:560px;">
            <div class="j2c-cart-lines" id="j2c-cart-lines" data-currency-symbol="<?php echo $this->escape($symbol); ?>">
                <?php foreach ($orderItems as $orderItem) : ?>
                    <?php
                    $oid        = (int) $orderItem->j2commerce_orderitem_id;
                    $qty        = (int) $orderItem->orderitem_quantity;
                    $priceVal   = number_format((float) $orderItem->orderitem_price, 2, '.', '');
                    $priceFmt   = CurrencyHelper::format((float) $orderItem->orderitem_price, $currency);
                    $sku        = (string) ($orderItem->orderitem_sku ?? '');
                    $imageUrl   = (string) ($orderItem->image_url ?? '');
                    $manages    = !empty($orderItem->manages_stock);
                    $stockN     = (int) ($orderItem->stock_quantity ?? 0);
                    $stockClass = !$manages ? 'text-bg-secondary' : ($stockN > 0 ? 'text-bg-success' : 'text-bg-danger');
                    ?>
                    <div class="j2c-line-row d-flex align-items-center gap-3 px-3 py-2 border-bottom" data-item-id="<?php echo $oid; ?>">
                        <?php if ($imageUrl !== '') : ?>
                            <img class="j2c-line-icon j2c-line-img" src="<?php echo $this->escape($imageUrl); ?>" alt="<?php echo $this->escape($orderItem->orderitem_name); ?>" loading="lazy">
                        <?php else : ?>
                            <span class="j2c-line-icon j2c-icon-tile bg-body-secondary text-body-secondary"><span class="fa-solid fa-box-open" aria-hidden="true"></span></span>
                        <?php endif; ?>
                        <div class="j2c-line-info flex-grow-1" style="min-width:0;">
                            <div class="d-flex align-items-center gap-2">
                                <span class="j2c-line-name fw-semibold text-truncate"><?php echo $this->escape($orderItem->orderitem_name); ?></span>
                                <span class="badge j2c-line-stock <?php echo $stockClass; ?> flex-shrink-0" data-item-id="<?php echo $oid; ?>">
                                    <?php echo $this->escape(Text::_('COM_J2COMMERCE_INVENTORY')); ?>: <?php echo $manages ? $stockN : '&mdash;'; ?>
                                </span>
                            </div>
                            <div class="j2c-line-meta text-body-secondary small">
                                <a href="#" class="j2c-price-toggle" data-item-id="<?php echo $oid; ?>"><?php echo $this->escape($priceFmt); ?></a>
                                <?php echo $this->escape(' ' . Text::_('COM_J2COMMERCE_EACH') . ' · ' . Text::_('COM_J2COMMERCE_EMAIL_SKU') . ' ' . $sku); ?>
                            </div>
                            <?php if (!empty($orderItem->orderitemattributes)) : ?>
                                <div class="j2c-line-attributes">
                                    <?php echo LayoutHelper::render('orderitem.attributes', [
                                        'attributes' => $orderItem->orderitemattributes,
                                        'item'       => $orderItem,
                                        'context'    => 'admin_edit',
                                        'variant'    => 'compact',
                                    ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="j2c-line-admin d-flex align-items-center gap-2 mt-1 flex-wrap">
                                <div class="input-group input-group-sm j2c-line-price d-none" style="max-width:150px;">
                                    <span class="input-group-text"><?php echo $this->escape($symbol); ?></span>
                                    <input type="number" class="form-control j2c-price-input"
                                           name="orderitem_price_edit[<?php echo $oid; ?>]"
                                           value="<?php echo $priceVal; ?>" step="0.01" min="0"
                                           aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_FIELD_UNIT_PRICE')); ?>">
                                </div>
                            </div>
                            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', array($orderItem, $item, $this->params))->getArgument('html', ''); ?>
                        </div>
                        <div class="input-group input-group-sm j2c-qty-stepper" style="width:auto;flex:0 0 auto;">
                            <button type="button" class="btn btn-light border j2c-qty-dec" data-item-id="<?php echo $oid; ?>" aria-label="-"><span class="fa-solid fa-minus" aria-hidden="true"></span></button>
                            <span class="input-group-text bg-white justify-content-center fw-semibold j2c-qty-value" style="min-width:42px;"><?php echo $qty; ?></span>
                            <button type="button" class="btn btn-light border j2c-qty-inc" data-item-id="<?php echo $oid; ?>" aria-label="+"><span class="fa-solid fa-plus" aria-hidden="true"></span></button>
                            <input type="hidden" class="j2c-qty-input" name="orderitem_qty[<?php echo $oid; ?>]" value="<?php echo $qty; ?>">
                        </div>
                        <div class="j2c-line-total fw-bold text-end j2c-tabnum" style="width:90px;flex:0 0 auto;"><?php echo CurrencyHelper::format((float) $orderItem->orderitem_finalprice, $currency); ?></div>
                        <button type="button" class="btn btn-sm j2c-line-remove" data-item-id="<?php echo $oid; ?>"
                                title="<?php echo $this->escape(Text::_('JACTION_DELETE')); ?>" aria-label="<?php echo $this->escape(Text::_('JACTION_DELETE')); ?>">
                            <span class="fa-solid fa-trash text-danger" aria-hidden="true"></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="j2c-cart-empty text-center text-body-secondary py-5 <?php echo empty($orderItems) ? '' : 'd-none'; ?>" id="j2c-cart-empty">
                <div class="mb-2"><span class="fa-solid fa-cart-shopping fa-2x" aria-hidden="true"></span></div>
                <?php echo Text::_('COM_J2COMMERCE_NO_ORDER_ITEMS'); ?>
            </div>
        </div>

    </div>
</div>
