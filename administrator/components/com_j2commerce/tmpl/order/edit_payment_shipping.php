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

$item = $this->item;
$orderShipping = $item->ordershipping ?? null;

?>
<div class="row">
    <div class="col-lg-6">
        <h2 class="fs-4"><?php echo Text::_('COM_J2COMMERCE_ENTER_SHIPPING_DETAILS'); ?></h2>
        <div class="mb-3">
            <label for="ordershipping_name" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_METHOD'); ?></label>
            <input type="text" class="form-control" name="jform[ordershipping_name]" id="ordershipping_name"
                   value="<?php echo $this->escape($orderShipping->ordershipping_name ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label for="ordershipping_price" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_PRICE'); ?></label>
            <input type="number" class="form-control" name="jform[ordershipping_price]" id="ordershipping_price"
                   value="<?php echo number_format((float) ($orderShipping->ordershipping_price ?? 0), 5, '.', ''); ?>" step="0.01" min="0">
        </div>
        <div class="mb-3">
            <label for="ordershipping_tax" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_SHIPPING_TAX'); ?></label>
            <input type="number" class="form-control" name="jform[ordershipping_tax]" id="ordershipping_tax"
                   value="<?php echo number_format((float) ($orderShipping->ordershipping_tax ?? 0), 5, '.', ''); ?>" step="0.01" min="0">
        </div>
        <div class="mb-3">
            <label for="ordershipping_tracking_id" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_TRACKING_NUMBER'); ?></label>
            <textarea class="form-control" name="jform[ordershipping_tracking_id]" id="ordershipping_tracking_id" rows="3"><?php echo $this->escape($orderShipping->ordershipping_tracking_id ?? ''); ?></textarea>
        </div>
    </div>
    <div class="col-lg-6">
        <h2 class="fs-4"><?php echo Text::_('COM_J2COMMERCE_SELECT_PAYMENT_METHOD'); ?></h2>
        <div class="mb-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-2"><?php echo Text::_('COM_J2COMMERCE_FIELD_PAYMENT_METHOD'); ?>:</p>
                    <p><strong><?php echo $this->escape($item->orderpayment_type); ?></strong></p>
                    <input type="hidden" name="jform[orderpayment_type]" value="<?php echo $this->escape($item->orderpayment_type); ?>">
                </div>
            </div>
        </div>
    </div>
</div>
