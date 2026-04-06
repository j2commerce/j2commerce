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

    <div class="j2commerce-minicart-button uk-position-relative">
        <!-- Cart summary trigger -->
        <div class="j2commerce-cart-info" role="button" tabindex="0"
             aria-expanded="false" aria-controls="<?php echo $panelId; ?>"
             data-j2commerce-cart-toggle="<?php echo $panelId; ?>">
            <?php if ($productCount > 0) : ?>
                <span class="j2commerce-cart-text">
                    <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <a class="j2commerce-view-cart-link uk-margin-small-left" href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                </a>
            <?php else : ?>
                <span class="j2commerce-cart-empty"><?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?></span>
            <?php endif; ?>
        </div>

        <!-- Detail dropdown panel -->
        <div class="j2commerce-cart-detail-panel uk-card uk-card-default uk-box-shadow-medium" id="<?php echo $panelId; ?>" style="display:none;">
            <!-- Panel header -->
            <div class="uk-card-header uk-flex uk-flex-between uk-flex-middle">
                <div class="uk-text-bold">
                    <?php if ($productCount > 0) : ?>
                        <?php echo htmlspecialchars(Text::sprintf('MOD_J2COMMERCE_CART_TOTAL', $productCount, $formattedTotal), ENT_QUOTES, 'UTF-8'); ?>
                    <?php else : ?>
                        <?php echo Text::_('MOD_J2COMMERCE_CART_EMPTY'); ?>
                    <?php endif; ?>
                </div>
                <a href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                </a>
            </div>

            <?php if ($productCount > 0 && !empty($items)) : ?>
            <!-- Item list -->
            <ul class="uk-list uk-list-divider j2commerce-cart-item-list">
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
                <li>
                    <div class="uk-flex uk-flex-top uk-grid-small" uk-grid>
                        <?php if (!empty($thumbImage)) : ?>
                            <div class="j2commerce-cart-thumb uk-width-auto" style="width:60px;">
                                <img src="<?php echo htmlspecialchars($thumbImage, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="<?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                     uk-border-rounded loading="lazy" style="max-width:100%;height:auto;" />
                            </div>
                        <?php endif; ?>

                        <div class="uk-width-expand">
                            <div class="uk-flex uk-flex-between">
                                <p class="uk-margin-remove-bottom uk-text-bold">
                                    <?php echo htmlspecialchars($item->orderitem_name ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php if ($showRemove) : ?>
                                    <a class="uk-text-danger uk-margin-small-left" title="<?php echo Text::_('JACTION_DELETE'); ?>"
                                       href="<?php echo htmlspecialchars(Route::_(RouteHelper::getRemoveFromCartRoute((int) ($item->cart_item_id ?? $item->cartitem_id ?? 0))), ENT_QUOTES, 'UTF-8'); ?>">
                                        <span uk-icon="icon: close; ratio: 0.8" aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="uk-text-muted uk-text-small">
                                <?php if ($showQty) : ?>
                                    <span class="j2commerce-cart-item-qty"><?php echo (int) ($item->orderitem_quantity ?? 0); ?></span> &times;
                                <?php endif; ?>
                                <?php echo CurrencyHelper::format($itemPrice); ?>
                            </div>

                            <?php if (!empty($item->orderitemattributes)) : ?>
                                <div class="j2commerce-cart-item-options uk-text-small uk-text-muted uk-margin-small-top">
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

            <?php if ($showCheckout || $showViewCart) : ?>
            <!-- Footer buttons -->
            <div class="uk-card-footer uk-flex uk-grid-small" uk-grid>
                <?php if ($showCheckout) : ?>
                    <a class="uk-button uk-button-primary uk-width-expand" href="<?php echo htmlspecialchars($checkoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_CHECKOUT'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($showViewCart) : ?>
                    <a class="uk-button uk-button-default uk-width-expand" href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Close button -->
            <div class="uk-card-footer uk-text-right">
                <button type="button" class="uk-button uk-button-default uk-button-small"
                        data-j2commerce-cart-close="<?php echo $panelId; ?>">
                    <?php echo Text::_('MOD_J2COMMERCE_CART_CLOSE'); ?>
                </button>
            </div>
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
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Toggle panel on click
    document.querySelectorAll('[data-j2commerce-cart-toggle]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            // Do not toggle if user clicked the View Cart link inside the trigger
            if (e.target.closest('a')) return;

            var panelId = this.getAttribute('data-j2commerce-cart-toggle');
            var panel = document.getElementById(panelId);
            if (!panel) return;

            var isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
            this.setAttribute('aria-expanded', isVisible ? 'false' : 'true');
        });

        // Keyboard accessibility
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Close button handler
    document.querySelectorAll('[data-j2commerce-cart-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panelId = this.getAttribute('data-j2commerce-cart-close');
            var panel = document.getElementById(panelId);
            if (panel) {
                panel.style.display = 'none';
                var trigger = document.querySelector('[data-j2commerce-cart-toggle="' + panelId + '"]');
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Close panel when clicking outside
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

    // AJAX cart refresh
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
                        el.innerHTML = json.response[key];
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
