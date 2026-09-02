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

$tint = static fn(string $token): string => sprintf(
    'background-color: rgba(var(--%1$s-rgb), .06); background-color: color-mix(in srgb, var(--%1$s) 6%%, transparent);',
    $token
);


$iconTile = 'd-flex align-items-center justify-content-center flex-shrink-0 rounded-3';
?>
<div class="p-3 d-grid gap-3">
    <div class="card border border-primary-subtle" style="<?php echo $tint('primary'); ?>">
        <div class="card-body d-grid gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="<?php echo $iconTile; ?> bg-primary text-white" style="width: 2.5rem; height: 2.5rem;">
                    <span class="fa-solid fa-right-left" aria-hidden="true"></span>
                </div>
                <div class="flex-grow-1">
                    <h2 class="mb-0 fw-bold fs-6"><?php echo Text::_('COM_J2COMMERCE_CHANGE_ORDER_STATUS'); ?></h2>
                    <small class="text-secondary"><?php echo Text::_('COM_J2COMMERCE_BATCH_STATUS_DESC'); ?></small>
                </div>
            </div>
            <?php echo $this->batchForm->renderField('order_state_id', 'batch'); ?>
            <?php echo $this->batchForm->renderField('notify_customer', 'batch'); ?>
            <?php echo $this->batchForm->renderField('status_comment', 'batch'); ?>
            <div class="text-end">
                <joomla-toolbar-button task="orders.updatestatus">
                    <button type="button" class="btn btn-primary btn-sm">
                        <span class="fa-solid fa-check me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_J2COMMERCE_BATCH_APPLY_STATUS'); ?>
                    </button>
                </joomla-toolbar-button>
            </div>
        </div>
    </div>

    <?php if ($this->hasPackingSlipTemplate) : ?>
        <div class="card border">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="<?php echo $iconTile; ?> bg-primary-subtle text-primary" style="width: 2.5rem; height: 2.5rem;">
                    <span class="fa-solid fa-print" aria-hidden="true"></span>
                </div>
                <div class="flex-grow-1">
                    <h2 class="mb-0 fs-6"><?php echo Text::_('COM_J2COMMERCE_PRINT_PACKING_SLIPS'); ?></h2>
                    <small class="text-secondary"><?php echo Text::_('COM_J2COMMERCE_BATCH_PACKING_SLIPS_DESC'); ?></small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-j2c-action="print-packing-slips">
                    <?php echo Text::_('COM_J2COMMERCE_PRINT'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($this->canDelete) : ?>
        <div class="card border border-danger-subtle" style="<?php echo $tint('danger'); ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="<?php echo $iconTile; ?> bg-danger-subtle text-danger" style="width: 2.5rem; height: 2.5rem;">
                    <span class="fa-solid fa-triangle-exclamation" aria-hidden="true"></span>
                </div>
                <div class="flex-grow-1">
                    <h2 class="mb-1 fw-bold fs-6 text-danger-emphasis"><?php echo Text::_('COM_J2COMMERCE_DELETE_SELECTED_ORDERS'); ?></h2>
                    <small class="text-danger-emphasis opacity-75" style="line-height:1.4;display:block;"><?php echo Text::_('COM_J2COMMERCE_DELETE_ORDERS_WARNING'); ?></small>
                </div>
                <joomla-toolbar-button task="orders.delete">
                    <button type="button" class="btn btn-danger btn-sm" data-j2c-confirm="COM_J2COMMERCE_CONFIRM_DELETE_ORDERS" data-j2c-count-task="orders.counttransactions">
                        <?php echo Text::_('JTOOLBAR_DELETE'); ?>
                    </button>
                </joomla-toolbar-button>
            </div>
        </div>
    <?php endif; ?>
</div>
