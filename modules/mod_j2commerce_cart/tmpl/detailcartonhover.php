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
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

$moduleId       = (int) $module->id;
$productCount   = (int) ($productCount ?? 0);
$formattedTotal = (string) ($formattedTotal ?? '');
$cartUrl        = (string) ($cartUrl ?? '');
$checkoutUrl    = (string) ($checkoutUrl ?? '');
$ajaxUrl        = (string) ($ajaxUrl ?? '');
$isAjax         = !empty($isAjax ?? false);
$items          = $items ?? [];
$title          = $params->get('cart_module_title', '');
$moduleClassSfx = htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');

$showThumb    = (int) $params->get('show_thumbimage', 0);
$showQty      = (int) $params->get('show_product_qty', 0);
$showRemove   = (int) $params->get('show_cart_remove', 0);
$showCheckout = (int) $params->get('enable_checkout', 0);
$showViewCart = (int) $params->get('enable_view_cart', 0);

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

$panelId = 'j2commerce-cart-detail-' . $moduleId;
?>
<?php if (!$isAjax) : ?>
<div class="j2commerce-cart-module j2commerce-cart-module-<?php echo $moduleId; ?> <?php echo $moduleClassSfx; ?>">
<?php endif; ?>

<?php if (!$hide) : ?>

    <?php if (!empty($title)) : ?>
        <h3 class="cart-module-title"><?php echo htmlspecialchars(Text::_($title), ENT_QUOTES, 'UTF-8'); ?></h3>
    <?php endif; ?>

    <div class="j2commerce-minicart-button position-relative">
        <div class="j2commerce-cart-info" role="button" tabindex="0"
             aria-expanded="false" aria-controls="<?php echo $panelId; ?>"
             aria-haspopup="true"
             aria-label="<?php echo htmlspecialchars(Text::_('MOD_J2COMMERCE_CART_TOGGLE_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
             data-j2commerce-cart-toggle="<?php echo $panelId; ?>">
            <span class="j2commerce-cart-icon bi bi-cart3 me-1" aria-hidden="true"></span>
            <?php if ($productCount > 0) : ?>
                <span class="j2commerce-cart-text">
                    <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <?php if ($showViewCart && !empty($cartUrl)) : ?>
                    <a class="j2commerce-view-cart-link ms-2" href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            <?php else : ?>
                <span class="j2commerce-cart-empty"><?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?></span>
            <?php endif; ?>
            <span class="j2commerce-cart-caret bi bi-chevron-down ms-1 small" aria-hidden="true"></span>
        </div>

        <!-- Detail dropdown panel -->
        <div class="j2commerce-cart-detail-panel card shadow" id="<?php echo $panelId; ?>"
             role="region"
             aria-label="<?php echo htmlspecialchars(Text::_('MOD_J2COMMERCE_CART_PANEL_LABEL'), ENT_QUOTES, 'UTF-8'); ?>"
             style="display:none;">
            <!-- Panel header -->
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="fw-bold">
                    <?php if ($productCount > 0) : ?>
                        <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
                    <?php else : ?>
                        <?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?>
                    <?php endif; ?>
                </div>
                <?php if (!$showViewCart && $productCount > 0 && !empty($cartUrl)) : ?>
                    <a class="text-decoration-none" href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($productCount > 0 && !empty($items)) : ?>
            <!-- Item list -->
            <ul class="list-group list-group-flush j2commerce-cart-item-list"
                aria-label="<?php echo htmlspecialchars(Text::_('MOD_J2COMMERCE_CART_ITEMS_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($items as $item) :
                    $thumbImage = '';

                    if ($showThumb) {
                        $itemParams = new Registry($item->orderitem_params ?? '{}');
                        $rawThumbImage = (string) $itemParams->get('thumb_image', '');

                        if ($rawThumbImage !== '') {
                            $thumbSource = $platform ? $platform->getImagePath($rawThumbImage) : $rawThumbImage;
                            $thumbImage = HTMLHelper::_('cleanImageURL', $thumbSource)->url;
                        }
                    }

                    $itemPrice  = (float) ($item->orderitem_final_price ?? $item->orderitem_price ?? 0);
                ?>
                <li class="list-group-item">
                    <div class="d-flex align-items-start gap-2">
                        <?php if (!empty($thumbImage)) : ?>
                            <div class="j2commerce-cart-thumb flex-shrink-0" style="width:60px;">
                                <img src="<?php echo htmlspecialchars($thumbImage, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                     class="img-fluid rounded" loading="lazy" />
                            </div>
                        <?php endif; ?>

                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <p class="mb-0 fw-semibold">
                                    <?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php if ($showRemove) : ?>
                                    <a class="text-danger ms-2"
                                       title="<?php echo Text::_('JACTION_DELETE'); ?>"
                                       aria-label="<?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_REMOVE_ITEM', $item->orderitem_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                       href="<?php echo htmlspecialchars(Route::_(RouteHelper::getRemoveFromCartRoute((int) ($item->cart_item_id ?? $item->cartitem_id ?? 0))), ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="bi bi-x-circle" aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="text-muted small">
                                <?php if ($showQty) : ?>
                                    <span class="j2commerce-cart-item-qty"><?php echo (int) ($item->orderitem_quantity ?? 0); ?></span> &times;
                                <?php endif; ?>
                                <?php echo CurrencyHelper::format($itemPrice); ?>
                            </div>

                            <?php if (!empty($item->orderitemattributes)) : ?>
                                <div class="j2commerce-cart-item-options small text-muted mt-1">
                                    <?php foreach ($item->orderitemattributes as $attribute) : ?>
                                        <div>
                                            &ndash; <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>:
                                            <?php echo htmlspecialchars(Text::_($attribute->orderitemattribute_value ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php
                $showCheckoutBtn = $showCheckout && !empty($checkoutUrl);
                $showViewCartBtn = $showViewCart && !empty($cartUrl);
            ?>
            <?php if ($showCheckoutBtn || $showViewCartBtn) : ?>
            <?php $viewCartClass = ($showCheckoutBtn && $showViewCartBtn) ? 'btn btn-link' : 'btn btn-success'; ?>
            <div class="card-footer d-grid gap-2">
                <?php if ($showCheckoutBtn) : ?>
                    <a class="btn btn-success"
                       href="<?php echo htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>"
                       role="button"
                       aria-label="<?php echo htmlspecialchars(Text::_('MOD_J2COMMERCE_CART_CHECKOUT'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_CHECKOUT'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($showViewCartBtn) : ?>
                    <a class="<?php echo $viewCartClass; ?>"
                       href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>"
                       role="button"
                       aria-label="<?php echo htmlspecialchars(Text::_('MOD_J2COMMERCE_CART_VIEW_CART'), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php if (!$isAjax) : ?>
</div>
<?php else : ?>
    <?php \Joomla\CMS\Factory::getApplication()->setUserState('mod_j2commerce_mini_cart.isAjax', 0); ?>
<?php endif; ?>

<?php if (!$isAjax) : ?>
<style>
.j2commerce-minicart-button {
    display: inline-block;
}
.j2commerce-cart-detail-panel {
    position: absolute;
    z-index: 1050;
    min-width: 350px;
    right: 0;
    top: 100%;
    max-height: 80vh;
    overflow-y: auto;
}
.j2commerce-cart-info {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
}
.j2commerce-cart-info:hover .j2commerce-cart-icon,
.j2commerce-cart-info:focus .j2commerce-cart-icon {
    transform: scale(1.05);
}
.j2commerce-cart-icon {
    transition: transform 0.15s ease-in-out;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function openPanel(trigger) {
        var panelId = trigger.getAttribute('data-j2commerce-cart-toggle');
        var panel = document.getElementById(panelId);
        if (!panel) return;
        panel.style.display = 'block';
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closePanel(trigger) {
        var panelId = trigger.getAttribute('data-j2commerce-cart-toggle');
        var panel = document.getElementById(panelId);
        if (!panel) return;
        panel.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
    }

    // Toggle panel on click
    document.querySelectorAll('[data-j2commerce-cart-toggle]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;

            var panelId = this.getAttribute('data-j2commerce-cart-toggle');
            var panel = document.getElementById(panelId);
            if (!panel) return;

            var isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
            this.setAttribute('aria-expanded', isVisible ? 'false' : 'true');
        });

        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Open/close panel on hover
    document.querySelectorAll('.j2commerce-minicart-button').forEach(function (wrapper) {
        var trigger = wrapper.querySelector('[data-j2commerce-cart-toggle]');
        if (!trigger) return;

        wrapper.addEventListener('mouseenter', function () {
            openPanel(trigger);
        });

        wrapper.addEventListener('mouseleave', function () {
            closePanel(trigger);
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.j2commerce-cart-detail-panel').forEach(function (panel) {
            if (panel.style.display === 'none') return;
            var parent = panel.closest('.j2commerce-minicart-button');
            if (parent && !parent.contains(e.target)) {
                panel.style.display = 'none';
                var trigger = parent.querySelector('[data-j2commerce-cart-toggle]');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    function replaceWithFragment(el, html) {
        var frag = document.createRange().createContextualFragment(html);
        el.replaceChildren(frag);
    }
    document.addEventListener('j2commerce:cart:updated', function () {
        fetch('<?php echo htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8'); ?>', {
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
    });
});
</script>
<?php endif; ?>
