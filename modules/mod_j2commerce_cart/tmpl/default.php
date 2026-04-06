<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_cart
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Registry\Registry;

/**
 * Variables extracted from Dispatcher::getLayoutData() by Joomla's AbstractModuleDispatcher.
 *
 * IMPORTANT: Joomla's dispatch() calls extract($displayData) then unset($displayData).
 * All data keys become direct PHP variables. Do NOT reference $displayData[...] — it does not exist.
 *
 * @var \stdClass              $module         The module object
 * @var \Joomla\Registry\Registry $params      Module parameters
 * @var int                    $productCount   Number of items in cart
 * @var string                 $formattedTotal Formatted cart total
 * @var string                 $cartUrl        Cart page URL
 * @var string                 $checkoutUrl    Checkout page URL
 * @var string                 $ajaxUrl        AJAX refresh URL
 * @var bool                   $isAjax         Whether this is an AJAX render
 * @var array                  $items          Cart line items
 * @var object|null            $order          Order object with totals
 * @var float                  $cartTotal      Raw cart total
 */

$moduleId       = (int) $module->id;
$productCount   = (int) ($productCount ?? 0);
$formattedTotal = (string) ($formattedTotal ?? '');
$cartUrl        = (string) ($cartUrl ?? '');
$checkoutUrl    = (string) ($checkoutUrl ?? '');
$ajaxUrl        = (string) ($ajaxUrl ?? '');
$isAjax         = !empty($isAjax ?? false);
$items          = $items ?? [];
$order          = $order ?? null;
$linkType       = $params->get('link_type', 'link');
$title          = $params->get('cart_module_title', '');
$moduleClassSfx = htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');

$showThumb    = (int) $params->get('show_thumbimage', 0);
$showQty      = (int) $params->get('show_product_qty', 0);
$showRemove   = (int) $params->get('show_cart_remove', 0);
$showCheckout = (int) $params->get('enable_checkout', 0);
$showViewCart = (int) $params->get('enable_view_cart', 0);

// Price display mode — sourced from component config (same as cart page)
$checkoutPriceDisplay = 0;
try {
    $checkoutPriceDisplay = (int) J2CommerceHelper::config()->get('checkout_price_display_options', 0);
} catch (\Throwable $e) {
    // Fallback to default if component config unavailable
}

// Hide module when empty if configured
$hide = ((int) $params->get('check_empty', 0) === 1 && $productCount < 1);

// Custom CSS
$customCss = strip_tags((string) $params->get('custom_css', ''));

if (!empty($customCss)) {
    /** @var \Joomla\CMS\Document\HtmlDocument $doc */
    $doc = \Joomla\CMS\Factory::getApplication()->getDocument();
    $doc->getWebAssetManager()->addInlineStyle($customCss);
}

$platform = null;
try {
    $platform = J2CommerceHelper::platform();
} catch (\Throwable $e) {
    // Platform helper unavailable — thumbnails will not display
}
?>
<?php if (!$isAjax) : ?>
<div class="j2commerce-cart-module j2commerce-cart-module-<?php echo $moduleId; ?> <?php echo $moduleClassSfx; ?>">
<?php endif; ?>

<?php if (!$hide) : ?>

    <?php if (!empty($title)) : ?>
        <h3 class="cart-module-title"><?php echo htmlspecialchars(Text::_($title), ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>

    <?php if ($productCount > 0 && !empty($items)) : ?>

        <!-- Cart summary -->
        <div class="j2commerce-cart-summary mb-2">
            <span class="j2commerce-cart-text fw-bold">
                <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <!-- Item list (same patterns as com_j2commerce cart page) -->
        <ul class="list-group list-group-flush j2commerce-cart-item-list mb-2">
            <?php foreach ($items as $item) :
                $thumbImage = '';
                if ($showThumb) {
                    $itemParams = $platform
                        ? $platform->getRegistry($item->orderitem_params ?? '{}')
                        : new Registry($item->orderitem_params ?? '{}');
                    $rawThumbImage = (string) $itemParams->get('thumb_image', '');

                    if ($rawThumbImage !== '') {
                        $thumbSource = $platform ? $platform->getImagePath($rawThumbImage) : $rawThumbImage;
                        $thumbImage = HTMLHelper::_('cleanImageURL', $thumbSource)->url;
                    }
                }

                $cartitemId = $item->cartitem_id ?? $item->j2commerce_cartitem_id ?? 0;
                $itemQty    = (int) ($item->orderitem_quantity ?? 0);

                // Use order methods for price formatting (same as cart page)
                $unitPrice = 0.0;
                $lineTotal = 0.0;
                if ($order && method_exists($order, 'get_formatted_lineitem_price')) {
                    $unitPrice = $order->get_formatted_lineitem_price($item, $checkoutPriceDisplay);
                    $lineTotal = $order->get_formatted_lineitem_total($item, $checkoutPriceDisplay);
                } else {
                    $unitPrice = (float) ($item->orderitem_price ?? 0);
                    $lineTotal = (float) ($item->orderitem_final_price ?? $unitPrice * $itemQty);
                }
            ?>
            <li class="list-group-item px-0 j2commerce-minicart-item" data-cartitem-id="<?php echo (int) $cartitemId; ?>">
                <div class="d-flex align-items-start gap-2">
                    <?php if (!empty($thumbImage)) : ?>
                        <div class="j2commerce-cart-thumb flex-shrink-0" style="width:60px;">
                            <img src="<?php echo htmlspecialchars($thumbImage, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                 class="img-fluid rounded" loading="lazy" />
                        </div>
                    <?php endif; ?>

                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="cart-product-name fw-semibold">
                                <?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($showRemove) : ?>
                                <button type="button"
                                        class="btn btn-sm btn-link text-danger ms-2 flex-shrink-0 p-0 j2commerce-minicart-remove"
                                        data-cartitem-id="<?php echo (int) $cartitemId; ?>"
                                        title="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>"
                                        aria-label="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>">
                                    <span class="icon-times" aria-hidden="true"></span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item->orderitemattributes)) : ?>
                            <div class="cart-item-options mt-1">
                                <?php foreach ($item->orderitemattributes as $attribute) : ?>
                                    <small class="d-block text-muted">
                                        &ndash; <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>:
                                        <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_value ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-muted small">
                                <?php if ($showQty) : ?>
                                    <span class="j2commerce-cart-item-qty"><?php echo $itemQty; ?></span> &times;
                                    <?php echo CurrencyHelper::format($unitPrice); ?>
                                <?php else : ?>
                                    <?php echo CurrencyHelper::format($unitPrice); ?>
                                <?php endif; ?>
                            </span>
                            <span class="fw-bold">
                                <?php echo CurrencyHelper::format($lineTotal); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Order totals (same pattern as cart page default_totals.php) -->
        <!-- Note: label/value output is raw because get_formatted_order_totals() returns
             pre-built HTML (e.g. coupon remove links in label, currency-formatted value).
             This matches the cart page template pattern exactly. -->
        <?php if ($order && method_exists($order, 'get_formatted_order_totals')) : ?>
            <?php $totals = $order->get_formatted_order_totals(); ?>
            <?php if (!empty($totals)) : ?>
                <table class="table table-sm table-borderless mb-2">
                    <?php foreach ($totals as $total) : ?>
                        <tr>
                            <th scope="row" class="text-muted small">
                                <?php echo $total['label']; ?>
                                <?php if (isset($total['link'])) : ?>
                                    <?php echo $total['link']; ?>
                                <?php endif; ?>
                            </th>
                            <td class="text-end fw-bold"><?php echo $total['value']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>

    <?php else : ?>
        <span class="j2commerce-cart-empty"><?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?></span>
    <?php endif; ?>

    <!-- Footer buttons -->
    <?php if ($showCheckout || $showViewCart) : ?>
        <div class="j2commerce-minicart-button d-flex gap-2 mt-2">
            <?php if ($showCheckout && $productCount > 0) : ?>
                <a class="btn btn-success flex-fill"
                   href="<?php echo htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_CHECKOUT'); ?>
                </a>
            <?php endif; ?>
            <?php if ($showViewCart) : ?>
                <?php if ($linkType === 'link') : ?>
                    <a class="j2commerce-view-cart-link"
                       href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php else : ?>
                    <a class="btn btn-outline-secondary flex-fill"
                       href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php elseif ($productCount > 0 || !((int) $params->get('check_empty', 0) === 1)) : ?>
        <!-- Fallback: always show View Cart if no button params configured -->
        <div class="j2commerce-minicart-button mt-2">
            <?php if ($linkType === 'link') : ?>
                <a class="j2commerce-view-cart-link"
                   href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                </a>
            <?php else : ?>
                <a class="btn btn-primary j2commerce-view-cart-btn"
                   href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php if (!$isAjax) : ?>
</div>
<?php else : ?>
    <?php \Joomla\CMS\Factory::getApplication()->setUserState('mod_j2commerce_mini_cart.isAjax', 0); ?>
<?php endif; ?>

<?php if (!$isAjax) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ajaxUrl = <?php echo json_encode($ajaxUrl); ?>;
    var baseUrl = <?php echo json_encode(Route::_('index.php', false)); ?>;
    var csrfToken = <?php echo json_encode(Session::getFormToken()); ?>;

    // Refresh the entire module via ajaxmini endpoint
    function refreshMiniCart() {
        fetch(ajaxUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) { return response.json(); })
        .then(function (json) {
            if (json && json.response) {
                Object.keys(json.response).forEach(function (key) {
                    document.querySelectorAll('.j2commerce-cart-module-' + key).forEach(function (el) {
                        el.innerHTML = json.response[key];
                    });
                });
            }
        })
        .catch(function (error) {
            console.error('Cart module refresh error:', error);
        });
    }

    // Listen for cart updated events (from cart page or add-to-cart)
    document.addEventListener('j2commerce:cart:updated', refreshMiniCart);

    // AJAX remove item from cart module
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.j2commerce-minicart-remove');
        if (!btn) return;

        e.preventDefault();
        var cartitemId = btn.dataset.cartitemId;
        if (!cartitemId) return;

        btn.disabled = true;

        var row = btn.closest('.j2commerce-minicart-item');

        var formData = new FormData();
        formData.append('option', 'com_j2commerce');
        formData.append('task', 'carts.removeAjax');
        formData.append('cartitem_id', cartitemId);
        formData.append(csrfToken, '1');

        fetch(baseUrl, {
            method: 'POST',
            body: formData,
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                // Fade out the row then dispatch event to refresh module + cart page
                if (row) {
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                }
                setTimeout(function () {
                    // Dispatch event — module listener refreshes itself via ajaxmini,
                    // and if the cart page is open, its JS also picks this up
                    document.dispatchEvent(new CustomEvent('j2commerce:cart:updated'));
                }, 300);
            } else {
                btn.disabled = false;
                if (data.message && typeof Joomla !== 'undefined' && Joomla.renderMessages) {
                    Joomla.renderMessages({ error: [data.message] });
                }
            }
        })
        .catch(function (error) {
            console.error('Cart module remove error:', error);
            btn.disabled = false;
        });
    });
});
</script>
<?php endif; ?>
