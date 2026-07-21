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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;

?>
<div class="p-4">
    <div class="row g-4">
        <div class="col-md-6">
            <label for="order_id" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_ID'); ?></label>
            <input type="text" class="form-control bg-body-secondary j2c-tabnum" id="order_id" value="<?php echo $this->escape($item->order_id); ?>" readonly>
        </div>
        <div class="col-md-6">
            <label for="created_on" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_CREATED_ON'); ?></label>
            <?php echo HTMLHelper::_('calendar', $item->created_on ?? '', 'jform[created_on]', 'created_on', '%Y-%m-%d %H:%M:%S', ['class' => 'form-control', 'showTime' => true]); ?>
        </div>

        <div class="col-12">
            <?php echo $this->form->renderField('customer_type'); ?>
        </div>

        <div class="col-md-6" data-showon='[{"field":"jform[customer_type]","values":["registered"],"sign":"=","op":""}]'>
            <label for="jform_user_id" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_CUSTOMER'); ?></label>
            <?php echo $this->form->getInput('user_id'); ?>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="newCustomerBtn"
                    data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <span class="fa-solid fa-plus me-1" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NEW'); ?>
            </button>
        </div>
        <div class="col-md-6" data-showon='[{"field":"jform[customer_type]","values":["guest"],"sign":"=","op":""}]'>
            <label for="jform_user_email" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_GUEST_EMAIL'); ?></label>
            <?php echo $this->form->getInput('user_email'); ?>
        </div>

        <div class="col-md-6">
            <label for="customer_language" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_CUSTOMER_LANGUAGE'); ?></label>
            <select name="jform[customer_language]" id="customer_language" class="form-select">
                <option value=""><?php echo Text::_('JALL'); ?></option>
                <?php foreach (\Joomla\CMS\Language\LanguageHelper::getContentLanguages([0, 1]) as $language) : ?>
                    <option value="<?php echo $this->escape($language->lang_code); ?>" <?php echo ($item->customer_language ?? '') === $language->lang_code ? 'selected' : ''; ?>>
                        <?php echo $this->escape($language->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-semibold d-block j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_ORDER_STATUS'); ?></label>
            <span class="<?php echo $this->escape(J2htmlHelper::badgeClass($item->orderstatus_cssclass ?? 'badge text-bg-secondary')); ?>">
                <?php echo Text::_($item->orderstatus_name ?? 'Unknown'); ?>
            </span>
            <div class="form-text"><?php echo Text::_('COM_J2COMMERCE_STATUS_CHANGE_NOTE'); ?></div>
        </div>

        <div class="col-12">
            <label for="customer_note" class="form-label fw-semibold j2c-field-label"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NOTE'); ?></label>
            <textarea class="form-control" name="jform[customer_note]" id="customer_note" rows="4"<?php echo $this->isNew ? '' : ' readonly'; ?>><?php echo $this->escape($item->customer_note ?? ''); ?></textarea>
        </div>
    </div>
</div>

<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="newCustomerModalLabel"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NEW'); ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newCustomerName" class="form-label"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NAME'); ?></label>
                    <input type="text" class="form-control" id="newCustomerName" required>
                </div>
                <div class="mb-3">
                    <label for="newCustomerEmail" class="form-label"><?php echo Text::_('COM_J2COMMERCE_FIELD_EMAIL'); ?></label>
                    <input type="email" class="form-control" id="newCustomerEmail" required>
                </div>
                <div class="mb-3">
                    <label for="newCustomerUsername" class="form-label"><?php echo Text::_('JGLOBAL_USERNAME'); ?></label>
                    <input type="text" class="form-control" id="newCustomerUsername">
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="newCustomerSendEmail">
                    <label class="form-check-label" for="newCustomerSendEmail"><?php echo Text::_('COM_J2COMMERCE_SEND_WELCOME_EMAIL'); ?></label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Text::_('JCANCEL'); ?></button>
                <button type="button" class="btn btn-primary" id="newCustomerSaveBtn"><?php echo Text::_('JSAVE'); ?></button>
            </div>
        </div>
    </div>
</div>
