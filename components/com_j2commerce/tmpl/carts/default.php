<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \J2Commerce\Component\J2commerce\Site\View\Carts\HtmlView $this */

$app = Factory::getApplication();

// Load Bootstrap 5 collapse for accordion functionality
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->useScript('bootstrap.collapse');

// Load cart AJAX script using registerAndUseScript
$wa->registerAndUseScript('com_j2commerce.cart-ajax', 'media/com_j2commerce/js/site/cart-ajax.js', [], ['defer' => true], ['core']);

// Pass configuration to JavaScript
$document->addScriptOptions('j2commerce.cart', [
    'csrfToken' => Session::getFormToken(),
    'baseUrl'   => Route::_('index.php', false),
    'strings'   => [
        'errorUpdating'       => Text::_('COM_J2COMMERCE_ERROR_UPDATING_CART'),
        'errorRemoving'       => Text::_('COM_J2COMMERCE_ERROR_REMOVING_ITEM'),
        'emptyCart'            => Text::_('COM_J2COMMERCE_CART_NO_ITEMS'),
        'confirmClearCart'     => Text::_('COM_J2COMMERCE_CONFIRM_CLEAR_CART'),
    ],
]);


// Get cart URL for form action
$cartUrl = J2CommerceHelper::platform()->getCartUrl();
$clearCartUrl = J2CommerceHelper::platform()->getCartUrl(['task' => 'clearCart']);

?>
<div class="j2commerce">
    <?php if ($this->params->get('show_page_heading')) : ?>
    <div class="page-header">
        <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    </div>
    <?php endif; ?>

    <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-cart-top'); ?>

    <div class="j2commerce-cart">
        <?php if (\count($this->items)): ?>
            <div class="row">
                <div class="col-12"><?php echo $this->before_display_cart; ?></div>
            </div>

            <div class="row">
                <div class="col-12">
                    <form action="<?php echo Route::_($cartUrl); ?>" method="post" name="j2commerce-cart-form" id="j2commerce-cart-form" enctype="multipart/form-data">

                        <input type="hidden" name="option" value="com_j2commerce" />
                        <input type="hidden" name="view" value="carts" />
                        <input type="hidden" id="j2commerce-cart-task" name="task" value="update" />

                        <?php echo $this->loadTemplate('items'); ?>

                        <div class="j2commerce-cart-buttons d-flex justify-content-between flex-wrap gap-2 my-3">
                            <div class="buttons-left d-flex gap-2">
                                <span class="cart-continue-shopping-button">
                                    <?php if ($this->continue_shopping_url->type !== 'previous'): ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="window.location='<?php echo $this->continue_shopping_url->url; ?>';">
                                            <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span>
                                            <?php echo Text::_('COM_J2COMMERCE_CART_CONTINUE_SHOPPING'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="window.history.back();">
                                            <span class="fa-solid fa-chevron-left me-1" aria-hidden="true"></span>
                                            <?php echo Text::_('COM_J2COMMERCE_CART_CONTINUE_SHOPPING'); ?>
                                        </button>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="buttons-right">
                                <?php if ($this->params->get('show_clear_cart_button', 0)): ?>
                                    <span class="cart-clear-button">
                                        <button type="button" class="btn btn-sm btn-outline-danger j2commerce-clear-cart-ajax">
                                            <?php echo Text::_('COM_J2COMMERCE_EMPTY_CART'); ?>
                                        </button>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>

                    <?php echo $this->after_display_cart; ?>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-lg-5">
                    <div class="cart-estimator-discount-block">
                        <?php
                        // Get coupon/voucher state for layout rendering
                        $enableCoupon = (int) $this->params->get('enable_coupon', 0);
                        $enableVoucher = (int) $this->params->get('enable_voucher', 0);
                        $couponCode = '';
                        $voucherCode = '';

                        if ($enableCoupon) {
                            $couponModel = Factory::getApplication()->bootComponent('com_j2commerce')
                                ->getMVCFactory()->createModel('Coupon', 'Administrator', ['ignore_request' => true]);
                            $couponCode = $couponModel ? $couponModel->getCoupon() : '';
                        }
                        if ($enableVoucher) {
                            $voucherModel = Factory::getApplication()->bootComponent('com_j2commerce')
                                ->getMVCFactory()->createModel('Voucher', 'Administrator', ['ignore_request' => true]);
                            $voucherCode = $voucherModel ? $voucherModel->getVoucherCode() : '';
                        }
                        ?>
                        <div class="accordion" id="cartToolsAccordion">
                            <?php if ($enableCoupon) : ?>
                                <?php echo LayoutHelper::render('form.coupon', [
                                    'couponCode'   => $couponCode,
                                    'formId'       => 'cart-coupon',
                                    'variant'      => 'accordion',
                                    'accordionId'  => 'cartToolsAccordion',
                                    'expanded'     => !empty($couponCode),
                                    'showDiscount' => true,
                                ], JPATH_COMPONENT . '/layouts'); ?>
                            <?php endif; ?>
                            <?php if ($enableVoucher) : ?>
                                <?php echo LayoutHelper::render('form.voucher', [
                                    'voucherCode'  => $voucherCode,
                                    'formId'       => 'cart-voucher',
                                    'variant'      => 'accordion',
                                    'accordionId'  => 'cartToolsAccordion',
                                    'expanded'     => !empty($voucherCode),
                                    'showDiscount' => true,
                                ], JPATH_COMPONENT . '/layouts'); ?>
                            <?php endif; ?>
                            <?php echo $this->loadTemplate('calculator'); ?>
                        </div>
                        <div id="j2commerce-cart-shipping-wrapper" class="mt-3">
                            <?php echo $this->loadTemplate('shipping'); ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 ms-lg-auto">
                    <?php echo $this->loadTemplate('totals'); ?>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info">
                <span class="cart-no-items">
                    <?php echo Text::_('COM_J2COMMERCE_CART_NO_ITEMS'); ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php echo J2CommerceHelper::modules()->loadposition('j2commerce-cart-bottom'); ?>
</div>

<script>
// Cart page: refresh totals via AJAX on coupon/voucher apply/remove
(function() {
    var cartOpts = Joomla.getOptions('j2commerce.cart') || {};
    var cvOpts   = Joomla.getOptions('j2commerce.couponVoucher') || {};
    var csrfToken = cartOpts.csrfToken || cvOpts.csrfToken || '';
    var ajaxUrl   = cartOpts.baseUrl || cvOpts.baseUrl || 'index.php';

    function refreshCartTotals() {
        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'carts.getTotalsAjax');
        if (csrfToken) formData.append(csrfToken, '1');

        fetch(ajaxUrl, { method: 'POST', body: formData, headers: { 'Cache-Control': 'no-cache' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.html) {
                    var totals = document.querySelector('.cart-totals-block');
                    if (totals) totals.outerHTML = data.html;
                }
                if (data.shipping_html !== undefined) {
                    var shipping = document.getElementById('j2commerce-cart-shipping-wrapper');
                    if (shipping) shipping.innerHTML = data.shipping_html;
                }
            })
            .catch(function() { window.location.reload(); });

        // Also update the mini-cart module
        document.dispatchEvent(new CustomEvent('j2commerce:cart:updated', { bubbles: true }));
    }

    document.addEventListener('j2commerce:coupon:applied', refreshCartTotals);
    document.addEventListener('j2commerce:coupon:removed', refreshCartTotals);
    document.addEventListener('j2commerce:voucher:applied', refreshCartTotals);
    document.addEventListener('j2commerce:voucher:removed', refreshCartTotals);
})();
</script>
