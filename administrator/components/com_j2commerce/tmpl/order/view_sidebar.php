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

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$item = $this->item;
$orderInfo = $item->orderinfo ?? null;
$orderShipping = $item->ordershipping ?? null;

$customerName = '';
if ($orderInfo) {
    $customerName = trim(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? ''));
}

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderButtons', array($item))->getArgument('html', ''); ?>
<div class="j2c-action-buttons d-flex flex-wrap gap-2 mb-3">
    <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=order&layout=invoice&tmpl=component&id=' . (int) $item->j2commerce_order_id); ?>"
       class="btn btn-sm btn-primary" target="_blank">
        <span class="icon-print" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_PRINT_ORDER'); ?>
    </a>
    <?php if (!empty($this->hasPackingSlip)) : ?>
    <a href="<?php echo Route::_('index.php?option=com_j2commerce&task=order.packingSlip&id=' . (int) $item->j2commerce_order_id); ?>"
       class="btn btn-sm btn-primary" target="_blank">
        <span class="icon-list" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_PRINT_PACKING_SLIP'); ?>
    </a>
    <?php endif; ?>
    <button type="button" class="btn btn-sm btn-dark" id="resendEmailBtn"
            data-order-id="<?php echo (int) $item->j2commerce_order_id; ?>">
        <span class="icon-envelope" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_RESEND_EMAIL'); ?>
    </button>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderButton', array($item))->getArgument('html', ''); ?>
</div>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderButtons', array($item))->getArgument('html', ''); ?>
<?php // === Customer Note === ?>
<div class="customer-note-card card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NOTE'); ?></h5>
    </div>
    <div class="card-body">
        <textarea class="form-control" id="customerNote" rows="2" readonly><?php echo $this->escape($item->customer_note ?? ''); ?></textarea>
    </div>
</div>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderNote', array($item))->getArgument('html', ''); ?>

<?php if (!empty($item->user_id) || !empty($item->user_email)) : ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderCustomerInformation', array($item))->getArgument('html', ''); ?>
    <div class="customer-information-card card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_INFORMATION'); ?></h5>
        </div>
        <div class="card-body">
            <div class="row customer-information-row">
                <div class="col-9">
                    <div class="customer-information-item mb-2">
                        <span class="icon-user me-1 fa-fw" aria-hidden="true"></span>
                        <?php if ((int) $item->user_id > 0) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $item->user_id); ?>">
                                <?php echo $this->escape($customerName ?: $item->user_email); ?>
                            </a>
                        <?php else : ?>
                            <?php echo $this->escape($customerName ?: $item->user_email); ?>
                        <?php endif; ?>
                    </div>
                    <div class="customer-information-item mb-2">
                        <span class="icon-envelope me-1 fa-fw" aria-hidden="true"></span>
                        <?php echo $this->escape($item->user_email); ?>
                    </div>
                    <?php if (!empty($orderInfo->billing_phone_1)) : ?>
                        <div class="customer-information-item mb-2"><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span> <?php echo $this->escape($orderInfo->billing_phone_1); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($orderInfo->billing_phone_2)) : ?>
                        <div class="customer-information-item mb-2"><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span> <?php echo $this->escape($orderInfo->billing_phone_2); ?></div>
                    <?php endif; ?>
                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderCustomerInformation', array($item))->getArgument('html', ''); ?>
                </div>
                <div class="col-3 stats-sidebar">
                    <?php if ($this->customerDays > 0) : ?>
                        <div class="report-stat-box p-2 text-bg-success mb-2 text-center">
                            <div class="fs-2 fw-bold j2commerce-customer-age"><?php echo $this->customerDays;?></div>
                            <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_AGE');?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($this->totalSales > 0) : ?>
                        <div class="report-stat-box p-2 text-bg-info mb-2 text-center">
                            <div class="fs-2 fw-bold j2commerce-customer-orders"><?php echo $this->totalSales;?></div>
                            <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_ORDERS');?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderCustomerInformation', array($item))->getArgument('html', ''); ?>
<?php endif; ?>

<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
<div class="billing-address-card card mb-3">
    <div class="card-body">
        <?php if ($orderInfo) : ?>
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="icon-location j2c-address-icon text-primary me-2" aria-hidden="true"></span>
                <div>
                    <strong><?php echo Text::_('COM_J2COMMERCE_ORDER_BILLING'); ?></strong>
                    <div class="text-muted small"><?php echo $this->escape(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? '')); ?></div>
                </div>
            </div>
            <button class="btn btn-sm btn-primary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#billingAddressCollapse"
                    aria-expanded="false" aria-controls="billingAddressCollapse">
                <?php echo Text::_('COM_J2COMMERCE_VIEW_MORE'); ?>
            </button>
        </div>
        <div class="collapse mt-2" id="billingAddressCollapse">
            <address class="j2c-address-detail mb-0 ps-4 ms-2 border-start border-primary">
                <strong><?php echo $this->escape(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? '')); ?></strong><br>
                <?php if (!empty($orderInfo->billing_company)) : ?>
                    <?php echo $this->escape($orderInfo->billing_company); ?><br>
                <?php endif; ?>
                <?php echo $this->escape($orderInfo->billing_address_1 ?? ''); ?><br>
                <?php if (!empty($orderInfo->billing_address_2)) : ?>
                    <?php echo $this->escape($orderInfo->billing_address_2); ?><br>
                <?php endif; ?>
                <?php echo $this->escape($orderInfo->billing_city ?? ''); ?>, <?php echo $this->escape($orderInfo->billing_zone_name ?? ''); ?> <?php echo $this->escape($orderInfo->billing_zip ?? ''); ?><br>
                <?php echo $this->escape($orderInfo->billing_country_name ?? ''); ?>
                <?php if (!empty($orderInfo->billing_phone_1)) : ?>
                    <br><span class="icon-phone me-1 fa-fw" aria-hidden="true" title="<?php echo Text::_('COM_J2COMMERCE_PHONE'); ?>"></span><?php echo $this->escape($orderInfo->billing_phone_1); ?>
                <?php endif; ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
            </address>
        </div>
        <?php else : ?>
            <div class="alert alert-info mb-0"><?php echo Text::_('COM_J2COMMERCE_NO_BILLING_ADDRESS'); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
<?php // === Shipping Address (compact with View More collapse) === ?>
<?php if ($item->is_shippable) : ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderShippingAddress', array($item))->getArgument('html', ''); ?>
    <div class="shipping-address-card card mb-3">
        <div class="card-body">
            <?php if ($orderInfo) : ?>
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-location j2c-address-icon text-primary" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo Text::_('COM_J2COMMERCE_ORDER_SHIPPING'); ?></strong>
                        <div class="text-muted small"><?php echo $this->escape(($orderInfo->shipping_first_name ?? '') . ' ' . ($orderInfo->shipping_last_name ?? '')); ?></div>
                    </div>
                </div>
                <button class="btn btn-sm btn-primary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#shippingAddressCollapse"
                        aria-expanded="false" aria-controls="shippingAddressCollapse">
                    <?php echo Text::_('COM_J2COMMERCE_VIEW_MORE'); ?>
                </button>
            </div>
            <div class="collapse mt-2" id="shippingAddressCollapse">
                <address class="j2c-address-detail mb-0 ps-4 ms-2 border-start border-primary">
                    <strong><?php echo $this->escape(($orderInfo->shipping_first_name ?? '') . ' ' . ($orderInfo->shipping_last_name ?? '')); ?></strong><br>
                    <?php if (!empty($orderInfo->shipping_company)) : ?>
                        <?php echo $this->escape($orderInfo->shipping_company); ?><br>
                    <?php endif; ?>
                    <?php echo $this->escape($orderInfo->shipping_address_1 ?? ''); ?><br>
                    <?php if (!empty($orderInfo->shipping_address_2)) : ?>
                        <?php echo $this->escape($orderInfo->shipping_address_2); ?><br>
                    <?php endif; ?>
                    <?php echo $this->escape($orderInfo->shipping_city ?? ''); ?>, <?php echo $this->escape($orderInfo->shipping_zone_name ?? ''); ?> <?php echo $this->escape($orderInfo->shipping_zip ?? ''); ?><br>
                    <?php echo $this->escape($orderInfo->shipping_country_name ?? ''); ?>
                    <?php if (!empty($orderInfo->shipping_phone_1)) : ?>
                        <br><span class="icon-phone me-1 fa-fw" aria-hidden="true" title="<?php echo Text::_('COM_J2COMMERCE_PHONE'); ?>"></span><?php echo $this->escape($orderInfo->shipping_phone_1); ?>
                    <?php endif; ?>
                </address>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderShippingAddress', array($item))->getArgument('html', ''); ?>
            </div>
            <?php else : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_J2COMMERCE_NO_SHIPPING_ADDRESS'); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
<?php endif; ?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderAfterGeneralInformation', array($item))->getArgument('html', ''); ?>
