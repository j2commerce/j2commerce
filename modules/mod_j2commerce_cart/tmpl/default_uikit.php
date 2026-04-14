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
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

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

$checkoutPriceDisplay = 0;
try {
    $checkoutPriceDisplay = (int) J2CommerceHelper::config()->get('checkout_price_display_options', 0);
} catch (\Throwable $e) {
}

$hide = ((int) $params->get('check_empty', 0) === 1 && $productCount < 1);

$customCss = strip_tags((string) $params->get('custom_css', ''));
if (!empty($customCss)) {
    $doc = \Joomla\CMS\Factory::getApplication()->getDocument();
    $doc->getWebAssetManager()->addInlineStyle($customCss);
}

$platform = null;
try {
    $platform = J2CommerceHelper::platform();
} catch (\Throwable $e) {
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

        <div class="j2commerce-cart-summary uk-margin-small-bottom">
            <span class="j2commerce-cart-text uk-text-bold">
                <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <ul class="uk-list uk-list-divider j2commerce-cart-item-list uk-margin-small-bottom">
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
            <li class="j2commerce-minicart-item uk-padding-remove-horizontal" data-cartitem-id="<?php echo (int) $cartitemId; ?>">
                <div class="uk-flex uk-flex-top uk-grid-small" uk-grid>
                    <?php if (!empty($thumbImage)) : ?>
                        <div class="j2commerce-cart-thumb uk-width-auto" style="width:60px;">
                            <img src="<?php echo htmlspecialchars($thumbImage, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                 uk-border-rounded loading="lazy" style="max-width:100%;height:auto;" />
                        </div>
                    <?php endif; ?>

                    <div class="uk-width-expand">
                        <div class="uk-flex uk-flex-between uk-flex-top">
                            <span class="cart-product-name uk-text-bold">
                                <?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($showRemove) : ?>
                                <button type="button"
                                        class="uk-text-danger uk-margin-small-left j2commerce-minicart-remove"
                                        style="background:none;border:none;cursor:pointer;padding:0;"
                                        data-cartitem-id="<?php echo (int) $cartitemId; ?>"
                                        title="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>"
                                        aria-label="<?php echo Text::_('COM_J2COMMERCE_REMOVE'); ?>">
                                    <span uk-icon="icon: close; ratio: 0.8" aria-hidden="true"></span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item->orderitemattributes)) : ?>
                            <div class="cart-item-options uk-margin-small-top">
                                <?php foreach ($item->orderitemattributes as $attribute) : ?>
                                    <span class="uk-display-block uk-text-small uk-text-muted">
                                        &ndash; <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>:
                                        <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_value ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-top">
                            <span class="uk-text-muted uk-text-small">
                                <?php if ($showQty) : ?>
                                    <span class="j2commerce-cart-item-qty"><?php echo $itemQty; ?></span> &times;
                                    <?php echo CurrencyHelper::format($unitPrice); ?>
                                <?php else : ?>
                                    <?php echo CurrencyHelper::format($unitPrice); ?>
                                <?php endif; ?>
                            </span>
                            <span class="uk-text-bold">
                                <?php echo CurrencyHelper::format($lineTotal); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($order && method_exists($order, 'get_formatted_order_totals')) : ?>
            <?php $totals = $order->get_formatted_order_totals(); ?>
            <?php if (!empty($totals)) : ?>
                <table class="uk-table uk-table-small uk-table-justify uk-margin-small-bottom">
                    <?php foreach ($totals as $total) : ?>
                        <tr>
                            <th scope="row" class="uk-text-muted uk-text-small">
                                <?php echo $total['label']; ?>
                                <?php if (isset($total['link'])) : ?>
                                    <?php echo $total['link']; ?>
                                <?php endif; ?>
                            </th>
                            <td class="uk-text-right uk-text-bold"><?php echo $total['value']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>

    <?php else : ?>
        <span class="j2commerce-cart-empty"><?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?></span>
    <?php endif; ?>

    <?php
        $showCheckoutBtn = $showCheckout && !empty($checkoutUrl);
        $showViewCartBtn = $showViewCart && !empty($cartUrl);
    ?>
    <?php if ($productCount > 0 && ($showCheckoutBtn || $showViewCartBtn)) : ?>
        <div class="j2commerce-minicart-button uk-margin-small-top">
            <?php if ($showCheckoutBtn) : ?>
                <a class="uk-button uk-button-primary uk-width-1-1"
                   href="<?php echo htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_CHECKOUT'); ?>
                </a>
            <?php endif; ?>
            <?php if ($showViewCartBtn) : ?>
                <?php if ($linkType === 'link') : ?>
                    <a class="j2commerce-view-cart-link uk-display-block uk-text-center uk-margin-small-top"
                       href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php else : ?>
                    <a class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top"
                       href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php elseif ($productCount > 0 && !empty($cartUrl)) : ?>
        <div class="j2commerce-minicart-button uk-margin-small-top">
            <?php if ($linkType === 'link') : ?>
                <a class="j2commerce-view-cart-link"
                   href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                </a>
            <?php else : ?>
                <a class="uk-button uk-button-primary j2commerce-view-cart-btn"
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
    var baseUrl = <?php echo json_encode(rtrim(Uri::base(true), '/') . '/index.php'); ?>;
    var csrfToken = <?php echo json_encode(Session::getFormToken()); ?>;

    function replaceWithFragment(el, html) {
        var frag = document.createRange().createContextualFragment(html);
        el.replaceChildren(frag);
    }

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
                        replaceWithFragment(el, json.response[key]);
                    });
                });
            }
        })
        .catch(function (error) {
            console.error('Cart module refresh error:', error);
        });
    }

    document.addEventListener('j2commerce:cart:updated', refreshMiniCart);

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
                if (row) {
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                }
                setTimeout(function () {
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
