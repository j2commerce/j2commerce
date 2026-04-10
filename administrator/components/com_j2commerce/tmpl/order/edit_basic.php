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

$item = $this->item;

?>
<div class="row">
    <div class="col-lg-8">
        <div class="alert alert-info">
            <span class="icon-info-circle" aria-hidden="true"></span>
            <?php echo Text::_('COM_J2COMMERCE_STATUS_CHANGE_NOTE'); ?>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="order_id" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_ID'); ?></label>
                <input type="text" class="form-control" id="order_id" value="<?php echo $this->escape($item->order_id); ?>" readonly>
            </div>
            <div class="col-md-6">
                <label for="created_on" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_CREATED_ON'); ?></label>
                <?php echo HTMLHelper::_('calendar', $item->created_on ?? '', 'jform[created_on]', 'created_on', '%Y-%m-%d %H:%M:%S', ['class' => 'form-control', 'showTime' => true]); ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="user_email" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_EMAIL'); ?></label>
                <input type="email" class="form-control" name="jform[user_email]" id="user_email"
                       value="<?php echo $this->escape($item->user_email); ?>">
            </div>
            <div class="col-md-6">
                <label for="customer_language" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_CUSTOMER_LANGUAGE'); ?></label>
                <select name="jform[customer_language]" id="customer_language" class="form-select">
                    <option value=""><?php echo Text::_('JALL'); ?></option>
                    <option value="en-GB" <?php echo ($item->customer_language ?? '') === 'en-GB' ? 'selected' : ''; ?>>English (United Kingdom)</option>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_STATUS'); ?></label>
            <div>
                <span class="<?php echo $this->escape($item->orderstatus_cssclass ?? 'badge text-bg-secondary'); ?>">
                    <?php echo Text::_($item->orderstatus_name ?? 'Unknown'); ?>
                </span>
            </div>
        </div>

        <div class="mb-3">
            <label for="customer_note" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NOTE'); ?></label>
            <textarea class="form-control" name="jform[customer_note]" id="customer_note" rows="4" readonly><?php echo $this->escape($item->customer_note ?? ''); ?></textarea>
        </div>
    </div>
</div>
