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

use J2Commerce\Component\J2commerce\Administrator\Helper\CustomFieldHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2htmlHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$item = $this->item;
$orderInfo = $item->orderinfo ?? null;
$orderShipping = $item->ordershipping ?? null;

$customerName = '';
if ($orderInfo) {
    $customerName = trim(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? ''));
}

$billingAll  = $orderInfo ? CustomFieldHelper::decodeOrderSnapshot($orderInfo->all_billing ?? null) : [];
$shippingAll = $orderInfo ? CustomFieldHelper::decodeOrderSnapshot($orderInfo->all_shipping ?? null) : [];

$billingCustomRows = $orderInfo ? array_values(array_filter(
    CustomFieldHelper::describeOrderFields($orderInfo->all_billing ?? null, ...CustomFieldHelper::ORDER_AREAS['billing']),
    static fn (array $row): bool => !$row['core']
)) : [];

$shippingCustomRows = $orderInfo ? array_values(array_filter(
    CustomFieldHelper::describeOrderFields($orderInfo->all_shipping ?? null, ...CustomFieldHelper::ORDER_AREAS['shipping']),
    static fn (array $row): bool => !$row['core']
)) : [];

$paymentRows = $orderInfo
    ? CustomFieldHelper::describeOrderFields($orderInfo->all_payment ?? null, ...CustomFieldHelper::ORDER_AREAS['payment'])
    : [];

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderButtons', array($item))->getArgument('html', ''); ?>
<div class="j2c-action-buttons d-flex flex-wrap gap-2 mb-3">
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
        <h2 class="card-title mb-0 fs-5"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NOTE'); ?></h2>
    </div>
    <div class="card-body">
        <textarea class="form-control" id="customerNote" rows="2" readonly aria-label="<?php echo Text::_('COM_J2COMMERCE_CUSTOMER_NOTE'); ?>"><?php echo $this->escape($item->customer_note ?? ''); ?></textarea>
    </div>
</div>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderNote', array($item))->getArgument('html', ''); ?>

<?php if (!empty($item->user_id) || !empty($item->user_email)) : ?>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderCustomerInformation', array($item))->getArgument('html', ''); ?>
    <div class="customer-information-card card mb-3">
        <div class="card-header">
            <h2 class="card-title mb-0 fs-5"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_INFORMATION'); ?></h2>
        </div>
        <div class="card-body">
            <div class="row customer-information-row">
                <div class="col-9">
                    <div class="customer-information-item mb-2">
                        <span class="icon-user me-1 fa-fw" aria-hidden="true"></span>
                        <?php if (!empty($item->user_email)) : ?>
                            <a href="<?php echo Route::_('index.php?option=com_j2commerce&view=customers&filter[search]=' . urlencode($item->user_email)); ?>">
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
                        <div class="report-stat-box p-2 <?php echo J2htmlHelper::badgeClass('text-bg-success'); ?> mb-2 text-center">
                            <div class="fs-2 fw-bold j2commerce-customer-age"><?php echo $this->customerDays;?></div>
                            <div class="report-stat-title small"><?php echo Text::_('COM_J2COMMERCE_CUSTOMER_AGE');?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($this->totalSales > 0) : ?>
                        <div class="report-stat-box p-2 <?php echo J2htmlHelper::badgeClass('text-bg-info'); ?> mb-2 text-center">
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
                    <div class="text-body-secondary small"><?php echo $this->escape(($orderInfo->billing_first_name ?? '') . ' ' . ($orderInfo->billing_last_name ?? '')); ?></div>
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
                <?php if (!empty($orderInfo->billing_tax_number)) : ?>
                    <br><span class="text-body-secondary"><?php echo Text::_('J2COMMERCE_ADDRESS_TAX_NUMBER'); ?>:</span> <?php echo $this->escape($orderInfo->billing_tax_number); ?>
                <?php endif; ?>
                <?php if (!empty($orderInfo->billing_phone_1)) : ?>
                    <br><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_ADDRESS_PHONE'); ?>: </span><?php echo $this->escape($orderInfo->billing_phone_1); ?>
                <?php endif; ?>
                <?php if (!empty($orderInfo->billing_phone_2)) : ?>
                    <br><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_ADDRESS_MOBILE'); ?>: </span><?php echo $this->escape($orderInfo->billing_phone_2); ?>
                <?php endif; ?>
                <?php if (!empty($billingAll['email'])) : ?>
                    <br><span class="icon-envelope me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_EMAIL'); ?>: </span><?php echo $this->escape((string) $billingAll['email']); ?>
                <?php endif; ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
            </address>
            <?php echo LayoutHelper::render('order.customfieldrows', [
                'rows'      => $billingCustomRows,
                'title_tag' => 'h3',
            ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
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
                        <div class="text-body-secondary small"><?php echo $this->escape(($orderInfo->shipping_first_name ?? '') . ' ' . ($orderInfo->shipping_last_name ?? '')); ?></div>
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
                    <?php $shippingLocality = implode(', ', array_filter([$orderInfo->shipping_city ?? '', trim(($orderInfo->shipping_zone_name ?? '') . ' ' . ($orderInfo->shipping_zip ?? ''))], 'strlen')); ?>
                    <?php if ($shippingLocality !== '') : ?>
                        <?php echo $this->escape($shippingLocality); ?><br>
                    <?php endif; ?>
                    <?php echo $this->escape($orderInfo->shipping_country_name ?? ''); ?>
                    <?php if (!empty($orderInfo->shipping_tax_number)) : ?>
                        <br><span class="text-body-secondary"><?php echo Text::_('J2COMMERCE_ADDRESS_TAX_NUMBER'); ?>:</span> <?php echo $this->escape($orderInfo->shipping_tax_number); ?>
                    <?php endif; ?>
                    <?php if (!empty($orderInfo->shipping_phone_1)) : ?>
                        <br><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_ADDRESS_PHONE'); ?>: </span><?php echo $this->escape($orderInfo->shipping_phone_1); ?>
                    <?php endif; ?>
                    <?php if (!empty($orderInfo->shipping_phone_2)) : ?>
                        <br><span class="icon-phone me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_ADDRESS_MOBILE'); ?>: </span><?php echo $this->escape($orderInfo->shipping_phone_2); ?>
                    <?php endif; ?>
                    <?php if (!empty($shippingAll['email'])) : ?>
                        <br><span class="icon-envelope me-1 fa-fw" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('J2COMMERCE_EMAIL'); ?>: </span><?php echo $this->escape((string) $shippingAll['email']); ?>
                    <?php endif; ?>
                </address>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderShippingAddress', array($item))->getArgument('html', ''); ?>
                <?php echo LayoutHelper::render('order.customfieldrows', [
                    'rows'      => $shippingCustomRows,
                    'title_tag' => 'h3',
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
            <?php else : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_J2COMMERCE_NO_SHIPPING_ADDRESS'); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderBillingAddress', array($item))->getArgument('html', ''); ?>
<?php endif; ?>
<?php // === Payment Fields === ?>
<?php if ($orderInfo && $paymentRows !== []) : ?>
    <div class="payment-fields-card card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="fa-solid fa-credit-card j2c-address-icon text-primary me-2" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo Text::_('COM_J2COMMERCE_ORDER_PAYMENT_FIELDS'); ?></strong>
                    </div>
                </div>
                <button class="btn btn-sm btn-primary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#paymentFieldsCollapse"
                        aria-expanded="false" aria-controls="paymentFieldsCollapse">
                    <?php echo Text::_('COM_J2COMMERCE_VIEW_MORE'); ?>
                </button>
            </div>
            <div class="collapse mt-2" id="paymentFieldsCollapse">
                <?php echo LayoutHelper::render('order.customfieldrows', [
                    'rows'      => $paymentRows,
                    'title'     => '',
                    'title_tag' => 'h3',
                ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AdminOrderAfterGeneralInformation', array($item))->getArgument('html', ''); ?>
