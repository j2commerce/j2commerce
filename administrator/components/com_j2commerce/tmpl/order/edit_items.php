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

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$item = $this->item;
$orderItems = $item->orderitems ?? [];
$currency = $item->currency_code ?? 'USD';

?>
<div class="row">
    <div class="col-12">
        <?php if (!empty($orderItems)) : ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                    <th scope="col"><?php echo Text::_('COM_J2COMMERCE_HEADING_PRODUCT'); ?></th>
                    <th scope="col" class="w-10 text-center"><?php echo Text::_('COM_J2COMMERCE_HEADING_QTY'); ?></th>
                    <th scope="col" class="w-15 text-center"><?php echo Text::_('COM_J2COMMERCE_INVENTORY'); ?></th>
                    <th scope="col" class="w-10 text-end"><?php echo Text::_('COM_J2COMMERCE_HEADING_TOTAL'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $i => $orderItem) : ?>
                <tr>
                    <td class="text-center">
                        <?php echo HTMLHelper::_('grid.id', $i, $orderItem->j2commerce_orderitem_id); ?>
                    </td>
                    <td>
                        <strong><?php echo $this->escape($orderItem->orderitem_name); ?></strong>
                        <?php if (!empty($orderItem->orderitemattributes)) : ?>
                            <?php echo LayoutHelper::render('orderitem.attributes', [
                                'attributes' => $orderItem->orderitemattributes,
                                'item'       => $orderItem,
                                'context'    => 'admin_edit',
                                'variant'    => 'compact',
                            ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                        <?php endif; ?>
                        <div class="small text-muted">
                            <?php echo $this->escape($currency); ?> <?php echo number_format((float) $orderItem->orderitem_price, 2); ?> / unit
                        </div>
                    </td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center" style="width: 80px; margin: 0 auto;"
                               name="orderitem_qty[<?php echo (int) $orderItem->j2commerce_orderitem_id; ?>]"
                               value="<?php echo (int) $orderItem->orderitem_quantity; ?>" min="1">
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-success j2c-stock-btn"
                                    data-item-id="<?php echo (int) $orderItem->j2commerce_orderitem_id; ?>" data-direction="increase"
                                    title="<?php echo Text::_('COM_J2COMMERCE_INCREASE_STOCK'); ?>">
                                <span class="icon-arrow-up" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="btn btn-outline-danger j2c-stock-btn"
                                    data-item-id="<?php echo (int) $orderItem->j2commerce_orderitem_id; ?>" data-direction="reduce"
                                    title="<?php echo Text::_('COM_J2COMMERCE_REDUCE_STOCK'); ?>">
                                <span class="icon-arrow-down" aria-hidden="true"></span>
                            </button>
                        </div>
                    </td>
                    <td class="text-end">
                        <strong><?php echo $this->escape($currency); ?> <?php echo number_format((float) $orderItem->orderitem_finalprice, 2); ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mb-4">
            <button type="button" class="btn btn-primary me-2" id="updateItemsBtn">
                <span class="icon-loop" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_UPDATE_ITEMS'); ?>
            </button>
            <button type="button" class="btn btn-danger" id="removeItemsBtn">
                <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_REMOVE_ITEMS'); ?>
            </button>
        </div>
        <?php else : ?>
            <div class="alert alert-info"><?php echo Text::_('COM_J2COMMERCE_NO_ORDER_ITEMS'); ?></div>
        <?php endif; ?>

        <hr>

        <h2 class="fs-5"><?php echo Text::_('COM_J2COMMERCE_ADD_PRODUCTS'); ?></h2>
        <div class="row">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" id="skuSearchInput"
                           placeholder="<?php echo Text::_('COM_J2COMMERCE_SEARCH_BY_SKU'); ?>">
                    <button type="button" class="btn btn-outline-secondary" id="skuSearchBtn">
                        <span class="icon-search" aria-hidden="true"></span>
                    </button>
                </div>
                <div id="skuSearchResults" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>
