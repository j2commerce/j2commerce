<?php

/**
 * @package     J2Commerce
 * @subpackage  mod_j2commerce_cart
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

$moduleId       = (int) $module->id;
$productCount   = (int) ($productCount ?? 0);
$cartUrl        = (string) ($cartUrl ?? '');
$ajaxUrl        = (string) ($ajaxUrl ?? '');
$isAjax         = !empty($isAjax ?? false);
$iconClass      = htmlspecialchars($params->get('minicart_cart_icon_class', 'bi bi-cart3'), ENT_QUOTES, 'UTF-8');
$moduleClassSfx = htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');

// The endpoint reports both measures, so the badge names the one it rendered and the
// hydrator applies the matching field. Without this a cached replay would leave the
// priming visitor's number on screen in whichever mode the endpoint could not restate.
$countMeasure = ((int) $params->get('quantity_count', 1) === 1) ? 'qty' : 'lines';
$countAttr    = ' data-j2c-cart-count="' . $countMeasure . '"';
$badgeAttr    = ' data-j2c-cart-badge';

// check_empty bakes a hide/show decision into cacheable HTML, so the wrapper is rendered
// hidden rather than omitted — a shopper with items replaying a cache primed by an empty
// cart still gets a cart icon once hydration runs.
$emptyHidden = ((int) $params->get('check_empty', 0) === 1 && $productCount < 1);
$wrapAttr    = ' data-j2c-cart-wrapper';
$wrapHidden  = $emptyHidden ? ' hidden style="display:none"' : '';

$customCss = strip_tags((string) $params->get('custom_css', ''));
if (!empty($customCss)) {
    $doc = \Joomla\CMS\Factory::getApplication()->getDocument();
    $doc->getWebAssetManager()->addInlineStyle($customCss);
}
?>
<?php if (!$isAjax) : ?>
<div class="j2commerce-cart-module j2commerce-cart-module-<?php echo $moduleId; ?> <?php echo $moduleClassSfx; ?>">
<?php endif; ?>

    <div class="j2commerce-minicart"<?php echo $wrapAttr; ?><?php echo $wrapHidden; ?>>
        <?php if (!empty($cartUrl)) : ?>
            <a class="j2commerce-minicart-link uk-position-relative uk-display-inline-block"
               href="<?php echo htmlspecialchars($cartUrl, ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="<?php echo htmlspecialchars(\Joomla\CMS\Language\Text::_('MOD_J2COMMERCE_CART_VIEW_CART'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                <span class="uk-badge j2commerce-cart-badge"
                      style="position:absolute;top:-8px;right:-12px;font-size:0.65rem;<?php echo $productCount > 0 ? '' : 'display:none;'; ?>"
                      <?php echo $badgeAttr; ?><?php echo $productCount > 0 ? '' : ' hidden'; ?>>
                    <span<?php echo $countAttr; ?>><?php echo $productCount; ?></span>
                    <span class="uk-hidden"><?php echo \Joomla\CMS\Language\Text::_('MOD_J2COMMERCE_CART_VIEW_CART'); ?></span>
                </span>
            </a>
        <?php else : ?>
            <span class="j2commerce-minicart-link uk-position-relative uk-display-inline-block">
                <span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
                <span class="uk-badge j2commerce-cart-badge"
                      style="position:absolute;top:-8px;right:-12px;font-size:0.65rem;<?php echo $productCount > 0 ? '' : 'display:none;'; ?>"
                      <?php echo $badgeAttr; ?><?php echo $productCount > 0 ? '' : ' hidden'; ?>>
                    <span<?php echo $countAttr; ?>><?php echo $productCount; ?></span>
                </span>
            </span>
        <?php endif; ?>
    </div>

<?php if (!$isAjax) : ?>
</div>
<?php else : ?>
    <?php \Joomla\CMS\Factory::getApplication()->setUserState('mod_j2commerce_mini_cart.isAjax', 0); ?>
<?php endif; ?>

<?php if (!$isAjax) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
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
