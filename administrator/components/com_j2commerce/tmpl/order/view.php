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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Administrator\View\Order\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('keepalive');

$item = $this->item;
$orderInfo = $item->orderinfo ?? null;
$orderItems = $item->orderitems ?? [];
$orderHistory = $item->orderhistory ?? [];
$orderShipping = $item->ordershipping ?? null;
$orderDiscounts = $item->orderdiscounts ?? [];
$dateFormat = $this->dateFormat;

$customerName = '';
if ($orderInfo) {
    $customerName = trim(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? ''));
}
if (empty($customerName)) {
    $customerName = $item->user_email ?? '';
}

?>
<div id="j2c-order-view" data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>" data-token="<?php echo Session::getFormToken(); ?>">
    <?php // === HEADER BAR === ?>
    <div class="card j2c-order-header mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=orders'); ?>" class="text-decoration-none me-2" title="<?php echo Text::_('JTOOLBAR_BACK'); ?>">
                    <span class="icon-arrow-left-4" aria-hidden="true"></span>
                </a>
                <div>
                    <div class="j2-title-top d-flex align-items-center mb-1">
                        <h2 class="h3 mb-0 order-id">#<?php echo $this->escape($item->invoice); ?></h2>
                        <div class="ms-2 fw-normal fs-5">(<b><?php echo $this->escape($item->order_id); ?></b>)</div>
                        <div class="<?php echo $this->escape(J2htmlHelper::badgeClass($item->orderstatus_cssclass ?? 'badge text-bg-secondary')); ?> ms-2" id="orderStatusBadge">
                            <?php echo Text::_($item->orderstatus_name ?? 'Unknown'); ?>
                        </div>
                    </div>
                    <div class="j2-title-bottom d-flex align-items-center">
                        <small><?php echo HTMLHelper::_('date', $item->created_on, $dateFormat); ?></small>
                        <?php if (!empty($item->customer_language)) : ?>
                            <small class="mx-2">|</small>
                            <?php $flagPath = ImageHelper::getLanguageFlag($item->customer_language); ?>
                            <?php if ($flagPath !== '') : ?>
                                <img src="<?php echo htmlspecialchars(ImageHelper::getImageUrl($flagPath), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo $this->escape($item->customer_language); ?>"
                                     height="14" class="me-1">
                            <?php endif; ?>
                            <small><?php echo $this->escape($item->customer_language); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2 mt-lg-0">
                <div class="form-check form-switch pt-0">
                    <input type="checkbox" class="form-check-input" id="notifyCustomer" name="notify_customer">
                    <label class="form-check-label small" for="notifyCustomer"><?php echo Text::_('COM_J2COMMERCE_NOTIFY_CUSTOMER'); ?></label>
                </div>
                <div class="form-check form-switch pt-0">
                    <input type="checkbox" class="form-check-input" id="reduceStock" name="reduce_stock">
                    <label class="form-check-label small" for="reduceStock"><?php echo Text::_('COM_J2COMMERCE_REDUCE_STOCK'); ?></label>
                </div>
                <div class="form-check form-switch pt-0">
                    <input type="checkbox" class="form-check-input" id="increaseStock" name="increase_stock">
                    <label class="form-check-label small" for="increaseStock"><?php echo Text::_('COM_J2COMMERCE_INCREASE_STOCK'); ?></label>
                </div>
                <label for="headerStatusSelect" class="visually-hidden"><?php echo Text::_('COM_J2COMMERCE_ORDER_STATUSES'); ?></label>
                <select name="order_state_id" id="headerStatusSelect" class="form-select form-select-sm w-auto">
                    <?php foreach ($this->orderStatuses as $status) : ?>
                        <option value="<?php echo (int) $status->value; ?>"
                            <?php echo ((int) $status->value === (int) $item->order_state_id) ? 'selected' : ''; ?>>
                            <?php echo Text::_($status->text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-sm btn-primary" id="headerSaveStatus">
                    <?php echo Text::_('JAPPLY'); ?>
                </button>
            </div>
        </div>
    </div>
    <?php // === TWO-COLUMN LAYOUT === ?>
    <div class="row">
        <?php // === MAIN CONTENT (left) === ?>
        <div class="col-lg-8">
            <?php echo $this->loadTemplate('items'); ?>

            <?php echo $this->loadTemplate('summary'); ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderSummary', array($item))->getArgument('html', ''); ?>
            <?php echo $this->loadTemplate('details'); ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderDetails', array($item))->getArgument('html', ''); ?>
            <?php echo $this->loadTemplate('history'); ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderHistory', array($item))->getArgument('html', ''); ?>
        </div>

        <?php // === SIDEBAR (right) === ?>
        <div class="col-lg-4 j2c-sidebar">
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderSidebar', array($item))->getArgument('html', ''); ?>
            <?php echo $this->loadTemplate('sidebar'); ?>
            <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderSidebar', array($item))->getArgument('html', ''); ?>
        </div>
    </div>
</div>

<?php // Hidden form for toolbar actions (Close button) ?>
<form action="<?php echo Route::_('index.php?option=com_j2commerce&view=order&layout=view&id=' . (int) $item->j2commerce_order_id); ?>"
      method="post" name="adminForm" id="adminForm">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="id" value="<?php echo (int) $item->j2commerce_order_id; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
