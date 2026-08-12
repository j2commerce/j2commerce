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

$item      = $this->item;
$orderInfo = $item->orderinfo ?? null;

// Admin can always set a shipping address (or skip the step via the "same as
// billing" checkbox on the Billing tab) — the shipping fields are shown whenever
// the step is not skipped, never a "does not require shipping" message.
$hasShipping = $orderInfo
    && (!empty($orderInfo->shipping_address_1)
        || !empty($orderInfo->shipping_first_name)
        || !empty($orderInfo->shipping_last_name));

?>
<div class="row g-0">
    <div class="col-lg-7 p-4">
        <div class="text-body-secondary text-uppercase fw-bold mb-2" style="font-size:12px;letter-spacing:.5px;">
            <?php echo Text::_('COM_J2COMMERCE_TAB_SHIPPING_ADDRESS'); ?>
        </div>

        <?php if ($hasShipping) : ?>
        <div class="card j2c-inner-card border p-3">
            <div class="d-flex gap-3">
                <span class="j2c-icon-tile j2c-icon-tile-lg bg-primary-subtle text-primary"><span class="fa-solid fa-user fs-5" aria-hidden="true"></span></span>
                <address class="mb-0" style="font-style: normal; line-height: 1.8;">
                    <strong><?php echo $this->escape(($orderInfo->shipping_first_name ?? '') . ' ' . ($orderInfo->shipping_last_name ?? '')); ?></strong><br>
                    <?php if (!empty($orderInfo->shipping_company)) : ?>
                        <?php echo $this->escape($orderInfo->shipping_company); ?><br>
                    <?php endif; ?>
                    <?php echo $this->escape($orderInfo->shipping_address_1 ?? ''); ?><br>
                    <?php if (!empty($orderInfo->shipping_address_2)) : ?>
                        <?php echo $this->escape($orderInfo->shipping_address_2); ?><br>
                    <?php endif; ?>
                    <?php echo $this->escape($orderInfo->shipping_city ?? ''); ?>, <?php echo $this->escape($orderInfo->shipping_zone_name ?? ''); ?> <?php echo $this->escape($orderInfo->shipping_zip ?? ''); ?><br>
                    <?php echo $this->escape($orderInfo->shipping_country_name ?? ''); ?><br>
                    <?php if (!empty($orderInfo->shipping_phone_1)) : ?>
                        <?php echo Text::_('COM_J2COMMERCE_PHONE'); ?>: <?php echo $this->escape($orderInfo->shipping_phone_1); ?>
                    <?php endif; ?>
                </address>
            </div>
        </div>

        <?php // Checkout uploads are listed once, on the billing panel — they belong to the
              // order rather than to either address. ?>

        <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-outline-primary" id="editShippingAddressBtn" data-j2c-address-edit="shipping">
                <span class="fa-solid fa-pen me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_EDIT_ADDRESS'); ?>
            </button>
            <?php if ((int) ($item->user_id ?? 0) > 0) : ?>
            <button type="button" class="btn btn-outline-secondary j2c-address-new" data-address-type="shipping" data-user-id="<?php echo (int) $item->user_id; ?>">
                <span class="fa-solid fa-plus me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_ADD'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php else : ?>
            <div class="alert alert-info"><?php echo Text::_('COM_J2COMMERCE_NO_SHIPPING_ADDRESS'); ?></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" data-j2c-address-edit="shipping">
                    <span class="fa-solid fa-pen me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_EDIT_ADDRESS'); ?>
                </button>
                <?php if ((int) ($item->user_id ?? 0) > 0) : ?>
                <button type="button" class="btn btn-outline-secondary j2c-address-new" data-address-type="shipping" data-user-id="<?php echo (int) $item->user_id; ?>">
                    <span class="fa-solid fa-plus me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CUSTOMER_ADDRESSES_ADD'); ?>
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $this->addressFormType = 'shipping'; echo $this->loadTemplate('address_form'); ?>
    </div>

    <div class="col-lg-5 border-start bg-light p-4">
        <div class="text-body-secondary text-uppercase fw-bold mb-2" style="font-size:12px;letter-spacing:.5px;">
            <?php echo Text::_('COM_J2COMMERCE_CHOOSE_ALTERNATE_ADDRESS'); ?>
        </div>
        <button type="button" class="btn btn-outline-primary w-100" id="chooseShippingAddressBtn" data-j2c-address-choose="shipping">
            <span class="fa-solid fa-list me-2" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_CHOOSE_ALTERNATE_ADDRESS'); ?>
        </button>
        <div class="mt-3 d-none" id="shippingSavedAddresses" data-address-type="shipping"></div>
    </div>
</div>
