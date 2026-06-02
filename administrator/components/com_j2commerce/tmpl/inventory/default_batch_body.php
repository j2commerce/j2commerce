<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Inventory\HtmlView $this */
?>

<div class="p-3">
    <p class="text-body-secondary small">
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_HELP'); ?>
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_CASCADE_NOTE'); ?>
    </p>

    <!-- Quantity -->
    <div class="mb-4">
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="batch_apply_quantity" name="apply_quantity" value="1">
            <label class="form-check-label fw-bold" for="batch_apply_quantity">
                <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_QUANTITY'); ?>
            </label>
        </div>
        <input type="number" class="form-control" id="batch_quantity" name="batch_quantity" value="0" min="0" step="1">
    </div>

    <!-- Manage Stock -->
    <div class="mb-4">
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="batch_apply_manage_stock" name="apply_manage_stock" value="1">
            <label class="form-check-label fw-bold" for="batch_apply_manage_stock">
                <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_MANAGE_STOCK'); ?>
            </label>
        </div>
        <div class="btn-group" role="group" aria-label="<?php echo $this->escape(Text::_('COM_J2COMMERCE_INVENTORY_MANAGE_STOCK')); ?>">
            <input type="radio" class="btn-check" name="batch_manage_stock" id="batch_manage_stock_no" value="0" checked>
            <label class="btn btn-outline-primary" for="batch_manage_stock_no"><?php echo Text::_('JNO'); ?></label>
            <input type="radio" class="btn-check" name="batch_manage_stock" id="batch_manage_stock_yes" value="1">
            <label class="btn btn-outline-primary" for="batch_manage_stock_yes"><?php echo Text::_('JYES'); ?></label>
        </div>
    </div>

    <!-- Stock Status -->
    <div class="mb-4">
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="batch_apply_availability" name="apply_availability" value="1">
            <label class="form-check-label fw-bold" for="batch_apply_availability">
                <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_STOCK_STATUS'); ?>
            </label>
        </div>
        <select class="form-select" id="batch_availability" name="batch_availability">
            <option value="1"><?php echo Text::_('COM_J2COMMERCE_STOCK_IN_STOCK'); ?></option>
            <option value="0"><?php echo Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK'); ?></option>
        </select>
    </div>

    <button type="button" class="btn btn-primary" id="j2c-batch-apply">
        <span class="icon-checkmark" aria-hidden="true"></span>
        <?php echo Text::_('COM_J2COMMERCE_INVENTORY_BATCH_APPLY_BUTTON'); ?>
    </button>
</div>
