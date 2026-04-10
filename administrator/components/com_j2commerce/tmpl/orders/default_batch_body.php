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

use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Orders\HtmlView $this */
?>

<div class="p-3">
    <!-- Change Status Section -->
    <div class="mb-4">
        <h2 class="fw-bold fs-5"><?php echo Text::_('COM_J2COMMERCE_CHANGE_ORDER_STATUS'); ?></h2>
        <div class="mb-2">
            <select name="order_state_id" id="batch_order_state_id" class="form-select">
                <option value=""><?php echo Text::_('COM_J2COMMERCE_SELECT_ORDER_STATUS'); ?></option>
                <?php foreach ($this->orderStatuses as $status) : ?>
                    <option value="<?php echo (int) $status->j2commerce_orderstatus_id; ?>">
                        <?php echo Text::_($status->orderstatus_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="batch_notify_customer" name="notify_customer" value="1">
            <label class="form-check-label" for="batch_notify_customer"><?php echo Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER'); ?></label>
        </div>
        <div class="mb-2">
            <textarea name="status_comment" id="batch_status_comment" class="form-control" rows="2"
                      placeholder="<?php echo Text::_('COM_J2COMMERCE_FIELD_STATUS_COMMENT'); ?>"></textarea>
        </div>
        <joomla-toolbar-button task="orders.updatestatus">
            <button type="button" class="btn btn-primary btn-sm">
                <span class="icon-checkmark" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_CHANGE_ORDER_STATUS'); ?>
            </button>
        </joomla-toolbar-button>
    </div>

    <?php if ($this->hasPackingSlipTemplate) : ?>
    <!-- Print Packing Slips Section -->
    <div class="mb-4">
        <h2 class="fw-bold fs-5"><?php echo Text::_('COM_J2COMMERCE_PRINT_PACKING_SLIPS'); ?></h2>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.j2cPrintPackingSlips();">
            <span class="icon-print" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_PRINT_PACKING_SLIPS'); ?>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($this->canDelete) : ?>
    <!-- Delete Section -->
    <div class="mb-2">
        <h2 class="fw-bold text-danger fs-5"><?php echo Text::_('COM_J2COMMERCE_DELETE_SELECTED_ORDERS'); ?></h2>
        <p class="text-muted small"><?php echo Text::_('COM_J2COMMERCE_DELETE_ORDERS_WARNING'); ?></p>
        <joomla-toolbar-button task="orders.delete">
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="if(!confirm('<?php echo $this->escape(Text::_('COM_J2COMMERCE_CONFIRM_DELETE_ORDERS')); ?>')) { event.stopImmediatePropagation(); return false; }">
                <span class="icon-times" aria-hidden="true"></span>
                <?php echo Text::_('COM_J2COMMERCE_DELETE_SELECTED_ORDERS'); ?>
            </button>
        </joomla-toolbar-button>
    </div>
    <?php endif; ?>
</div>
