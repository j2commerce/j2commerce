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

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;

$item = $this->item;
$orderItems = $item->orderitems ?? [];
$currencyCode = $item->currency_code ?? '';

?>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('BeforeAdminOrderItems', array($item))->getArgument('html', ''); ?>
<div class="order-items-card card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0 fs-4"><?php echo Text::_('COM_J2COMMERCE_ORDER_ITEMS'); ?></h2>
    </div>
    <div class="card-body">
        <?php if (!empty($orderItems)) : ?>
            <?php foreach ($orderItems as $orderItem) :
                $orderItemImage = '';
                if(!empty($orderItem->orderitem_params)){
                    $orderItemParams = json_decode($orderItem->orderitem_params);
                    $orderItemImage = $orderItemParams->thumb_image ?? '';
                }
                ?>
                <div class="border mb-4 rounded-3 px-4 py-3 text-subdued">
                    <div class="row align-items-lg-start">
                        <div class="col-lg-9">
                            <div class="row justify-content-lg-between align-items-lg-start">
                                <div class="col-lg-8">
                                    <div class="order-item-left">
                                        <div class="d-flex align-items-start">
                                            <?php if (!empty($orderItemImage)) : ?>
                                                <div class="cart-product-image me-2">
                                                    <img src="<?php echo Uri::root().$orderItemImage; ?>" alt="<?php echo $this->escape($orderItem->orderitem_name); ?>" class="img-fluid" style="height: 64px;">
                                                </div>
                                            <?php endif; ?>
                                            <div class="cart-product-info">
                                                <h3 class="cart-product-name mb-1 fs-5"><?php echo $this->escape($orderItem->orderitem_name); ?></h3>
                                                <?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterDisplayLineItemTitle', array($orderItem, $item, $this->params))->getArgument('html', ''); ?>
                                                <div class="small d-flex align-items-center">
                                                    <div class="item-option item-option-name"><?php echo Text::_('COM_J2COMMERCE_PRODUCT_SKU');?>:</div>
                                                    <div class="item-option item-option-value fw-bold ms-1"><?php echo $this->escape($orderItem->orderitem_sku); ?></div>
                                                </div>
                                                <?php if (!empty($orderItem->orderitemattributes)) : ?>
                                                    <?php echo LayoutHelper::render('orderitem.attributes', [
                                                        'attributes' => $orderItem->orderitemattributes,
                                                        'item'       => $orderItem,
                                                        'context'    => 'admin_order',
                                                        'variant'    => 'full',
                                                    ], JPATH_ROOT . '/components/com_j2commerce/layouts'); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="fw-medium fs-6 text-end"><?php echo (int) $orderItem->orderitem_quantity; ?><span class="fa-solid fa-times small text-muted mx-1" aria-hidden="true"></span><?php echo CurrencyHelper::format((float) $orderItem->orderitem_price, $currencyCode); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <div class="fw-medium fs-5 text-end"><strong><?php echo CurrencyHelper::format((float) $orderItem->orderitem_finalprice, $currencyCode); ?></strong></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="p-3">
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_J2COMMERCE_NO_ORDER_ITEMS'); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php echo J2CommerceHelper::plugin()->eventWithHtml('AfterAdminOrderItems', array($item))->getArgument('html', ''); ?>
