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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

$item = $this->item;
$orderShipping = $item->ordershipping ?? null;

// Resolve payment plugin display name and image
$paymentType    = $item->orderpayment_type ?? '';
$paymentLangKey = 'PLG_J2COMMERCE_' . strtoupper($paymentType);
$paymentName    = ($paymentType !== '' && Text::_($paymentLangKey) !== $paymentLangKey)
    ? Text::_($paymentLangKey)
    : ucwords(str_replace('_', ' ', $paymentType));

$paymentImage = '';
if ($paymentType !== '') {
    $paymentPlugin = PluginHelper::getPlugin('j2commerce', $paymentType);
    if ($paymentPlugin) {
        $paymentParams = new Registry($paymentPlugin->params ?? '{}');
        $paymentImage  = $paymentParams->get('display_image', '');
    }

    if (empty($paymentImage)) {
        $paymentImage = ImageHelper::getPluginImage($paymentType);
    }
}
// Resolve shipping plugin image
$shippingImage = '';
if ($orderShipping && !empty($orderShipping->ordershipping_type)) {
    $shippingPlugin = PluginHelper::getPlugin('j2commerce', $orderShipping->ordershipping_type);
    if ($shippingPlugin) {
        $shippingParams = new Registry($shippingPlugin->params ?? '{}');
        $shippingImage  = $shippingParams->get('display_image', '');
    }

    if (empty($shippingImage)) {
        $shippingImage = ImageHelper::getPluginImage($orderShipping->ordershipping_type);
    }
}

// Parse transaction details for modal display
$transactionDetails = $item->transaction_details ?? '';
$hasDetails         = !empty($transactionDetails) && $transactionDetails !== '{}';
$detailsParsed      = null;

if ($hasDetails) {
    $decoded = json_decode($transactionDetails, true);
    if (\is_array($decoded) && !empty($decoded)) {
        $detailsParsed = $decoded;
    }
}

?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo Text::_('COM_J2COMMERCE_ORDER_DETAILS'); ?></h5>
    </div>
    <div class="card-body">
        <div class="j2c-detail-card-payment j2c-detail-card d-flex justify-content-between align-items-center p-3 mb-3">
            <div class="d-flex align-items-center">
                <?php if (!empty($paymentImage)) : ?>
                    <div class="flex-shrink-0 me-3">
                        <img src="<?php echo $this->escape(ImageHelper::getImageUrl($paymentImage)); ?>" class="j2commerce-payment-image" alt="<?php echo $this->escape($paymentName); ?>">
                    </div>
                <?php endif; ?>

                <div class="flex-grow-1">
                    <div class="fw-bold fs-6"><?php echo $this->escape($paymentName); ?></div>
                    <?php if (!empty($item->transaction_id)) : ?>
                        <div class="text-muted small">
                            <?php echo Text::_('COM_J2COMMERCE_FIELD_TRANSACTION_ID'); ?>:
                            <strong class="text-body"><?php echo $this->escape($item->transaction_id); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="j2c-detail-card-payment-buttons d-flex align-items-center gap-2">
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderPaymentButton', array($item))->getArgument('html', ''); ?>
                <?php if ($hasDetails) : ?>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-1" data-bs-toggle="modal" data-bs-target="#transactionDetailsModal">
                        <?php echo Text::_('COM_J2COMMERCE_VIEW_DETAILS'); ?>
                    </button>
                <?php endif; ?>
                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderPaymentButton', array($item))->getArgument('html', ''); ?>
            </div>
        </div>

        <?php if ($hasDetails) : ?>
            <div class="modal fade" id="transactionDetailsModal" tabindex="-1"
                 aria-labelledby="transactionDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="transactionDetailsModalLabel">
                                <?php echo Text::_('COM_J2COMMERCE_TRANSACTION_DETAILS'); ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="<?php echo Text::_('JCLOSE'); ?>"></button>
                        </div>
                        <div class="modal-body p-0 j2c-transaction-modal">
                            <?php if (!empty($item->transaction_id) || !empty($item->transaction_status)) : ?>
                                <div class="j2c-txn-grid">
                                    <?php if (!empty($item->transaction_id)) : ?>
                                        <div class="j2c-txn-item">
                                            <span class="j2c-txn-key"><?php echo Text::_('COM_J2COMMERCE_FIELD_TRANSACTION_ID'); ?></span>
                                            <span class="j2c-txn-val"><?php echo $this->escape($item->transaction_id); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item->transaction_status)) : ?>
                                        <div class="j2c-txn-item">
                                            <span class="j2c-txn-key"><?php echo Text::_('COM_J2COMMERCE_FIELD_TRANSACTION_STATUS'); ?></span>
                                            <?php
                                            $statusClass = match (strtoupper($item->transaction_status ?? '')) {
                                                'COMPLETED', 'SUCCESS', 'CAPTURED' => 'text-bg-success',
                                                'PENDING'                          => 'text-bg-warning',
                                                'FAILED', 'DENIED', 'EXPIRED'     => 'text-bg-danger',
                                                'REFUNDED', 'REVERSED'            => 'text-bg-info',
                                                default                            => 'text-bg-dark',
                                            };
                                            ?>
                                            <span class="d-inline-block <?php echo J2htmlHelper::badgeClass('badge ' . $statusClass); ?>"><?php echo $this->escape($item->transaction_status); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($detailsParsed !== null) : ?>
                                <div class="j2c-txn-grid">
                                    <?php foreach ($detailsParsed as $key => $value) : ?>
                                        <?php if (\is_array($value) || \is_object($value)) : ?>
                                            <div class="j2c-txn-item j2c-txn-item-full">
                                                <span class="j2c-txn-key"><?php echo $this->escape((string) $key); ?></span>
                                                <pre class="j2c-txn-pre"><?php echo $this->escape(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                            </div>
                                        <?php else : ?>
                                            <div class="j2c-txn-item">
                                                <span class="j2c-txn-key"><?php echo $this->escape((string) $key); ?></span>
                                                <span class="j2c-txn-val"><?php echo $this->escape((string) $value); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="p-4">
                                    <pre class="j2c-txn-pre"><?php echo $this->escape($transactionDetails); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderPayment', array($item))->getArgument('html', ''); ?>
        <?php // === Shipping Information Card === ?>
        <?php if ($orderShipping && $item->is_shippable) : ?>
            <div class="j2c-detail-card-shipping j2c-detail-card d-flex justify-content-between align-items-center p-3 mb-3">
                <div class="j2c-detail-card-shipping-details d-flex align-items-center">
                    <?php if (!empty($shippingImage)) : ?>
                        <div class="flex-shrink-0 me-3">
                            <img src="<?php echo $this->escape(ImageHelper::getImageUrl($shippingImage)); ?>" class="j2commerce-shipping-image" alt="<?php echo $this->escape($orderShipping->ordershipping_name); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="flex-grow-1">
                        <div class="fw-bold fs-6"><?php echo $this->escape($orderShipping->ordershipping_name); ?></div>
                        <?php if (!empty($item->transaction_id)) : ?>
                            <div class="text-muted small">
                                <?php echo Text::_('COM_J2COMMERCE_FIELD_TRACKING_NUMBER'); ?>:
                                <strong class="text-body"><?php echo $this->escape($orderShipping->ordershipping_tracking_id ?: '-'); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="j2c-detail-card-shipping-buttons d-flex align-items-center gap-2">
                    <?php if ($hasDetails) : ?>
                        <span id="trackingEdit" class="d-none">
                        <span class="input-group input-group-sm d-inline-flex w-auto">
                            <input type="text" class="form-control form-control-sm" id="trackingInput" style="max-width: 200px;" value="<?php echo $this->escape($orderShipping->ordershipping_tracking_id ?? ''); ?>">
                            <button type="button" class="btn btn-success btn-sm" id="saveTrackingBtn">
                                <span class="icon-save" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="cancelTrackingBtn">
                                <span class="icon-cancel" aria-hidden="true"></span>
                            </button>
                        </span>
                    </span>
                        <span id="trackingDisplay">
                        <button type="button" class="btn btn-sm btn-outline-success rounded-1 j2c-copy-btn" id="editTrackingBtn" title="<?php echo Text::_('JACTION_EDIT'); ?>">
                            <span class="icon-pencil-alt" aria-hidden="true"></span>
                            <span class="ms-1"><?php echo Text::_('JACTION_EDIT'); ?></span>
                        </button>
                    </span>
                    <?php endif; ?>
                    <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderShippingButton', array($item))->getArgument('html', ''); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderShipping', array($item))->getArgument('html', ''); ?>
    </div>
</div>
