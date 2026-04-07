<?php
/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.AppGuidedbuilder
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Plugin\J2Commerce\AppGuidedbuilder\Helper\GuidedbuilderHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

$product  = $this->product;
$params   = ($product->params instanceof Registry) ? $product->params : new Registry($product->params ?? '{}');
$appParams = ($product->app_params instanceof Registry) ? $product->app_params : new Registry($product->app_params ?? '{}');

// Register guided builder assets
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle(
    'plg_j2commerce_app_guidedbuilder.css',
    'media/plg_j2commerce_app_guidedbuilder/css/guidedbuilder.css'
);
$wa->registerAndUseScript(
    'plg_j2commerce_app_guidedbuilder.js',
    'media/plg_j2commerce_app_guidedbuilder/js/guidedbuilder.js',
    [],
    ['defer' => true]
);

// Pre-load JS-accessible language strings
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_STEP_OF');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REQUIRED_SELECTION');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW_TITLE');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_LOADING');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ERROR_LOADING');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_PER_UNIT');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ADDING_TO_CART');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ADDED_TO_CART');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ADD_TO_CART_ERROR');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_SHOW_PREVIEW');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_HIDE_PREVIEW');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BACK');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_NEXT_STEP');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_NEXT_REVIEW');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW_SUBTITLE');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW_EDIT');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_SUBTOTAL');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_SHIPPING');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TAX');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_CALCULATED_AT_CHECKOUT');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ESTIMATED_TOTAL');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_PRICE_BREAKDOWN');
Text::script('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BASE_PRICE');
Text::script('COM_J2COMMERCE_CART_LINE_ITEM_QUANTITY');
Text::script('JNEXT');
Text::script('JYES');
Text::script('JNO');
Text::script('COM_J2COMMERCE_ADD_TO_CART');

$previewMode     = $params->get('gb_preview_mode', $appParams->get('default_preview_mode', 'static_image'));
$previewPosition = $params->get('gb_preview_position', $appParams->get('default_preview_position', 'left'));
$previewBg       = $params->get('gb_preview_background', '#F8FAFC');
if ($previewBg && $previewBg[0] !== '#' && ctype_xdigit($previewBg)) {
    $previewBg = '#' . $previewBg;
}
$aspectRatio     = $params->get('gb_preview_aspect_ratio', '4:3');
if (!str_contains($aspectRatio, ':')) {
    $aspectRatio = '4:3';
}
$preloadImages   = (int) $params->get('gb_preload_images', 1);
$showStickyBar   = (int) $appParams->get('display_mobile_stickybar', 1);

// Load steps
$db     = Factory::getContainer()->get('DatabaseDriver');
$helper = new GuidedbuilderHelper($db);
$steps  = $helper->getStepsForProduct((int) $product->j2commerce_product_id);

$basePrice = (float) ($product->pricing->base_price ?? $product->variant->price ?? 0);
$basePriceFormatted = CurrencyHelper::format($basePrice);

// Build config JSON for JS
$stepsConfig = [];
foreach ($steps as $step) {
    $stepsConfig[] = [
        'step_number' => (int) $step->step_number,
        'step_label'  => $step->step_label ?: $step->option_name,
        'required'    => (int) $step->required,
    ];
}

// Product-level interactive SVG config — always include if base SVG exists
$previewConfig = [];
$baseSvg = $params->get('gb_base_svg', '');
if (!empty($baseSvg)) {
    $cssVariables = $params->get('gb_svg_css_variables', '{}');
    if (is_string($cssVariables)) {
        $cssVariables = json_decode($cssVariables, true) ?: [];
    }
    $previewConfig = [
        'baseSvg'      => $baseSvg,
        'cssVariables' => $cssVariables,
    ];
}

// Currency config for JS price formatting
$jsConfig = [
    'ajaxUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=guidedbuilder&group=j2commerce&format=raw',
    'token'   => Session::getFormToken(),
    'steps'   => $stepsConfig,
    'preview' => array_merge([
        'mode'     => $previewMode,
        'position' => $previewPosition,
    ], $previewConfig),
    'pricing' => [
        'basePrice'   => $basePrice,
        'symbol'      => CurrencyHelper::getSymbol(),
        'position'    => CurrencyHelper::getSymbolPosition(),
        'decimals'    => CurrencyHelper::getDecimalPlace(),
        'decimalSep'  => CurrencyHelper::getDecimalSeparator(),
        'thousandSep' => CurrencyHelper::getThousandsSeparator(),
    ],
];
?>

<div class="gb-configurator" data-product-id="<?php echo (int) $product->j2commerce_product_id; ?>">
    <script type="application/json"><?php echo json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>

    <!-- Progress bar (above configurator row) -->
    <?php if (\count($steps) > 1) : ?>
    <div class="gb-progress-wrapper" role="navigation" aria-label="<?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_YOUR_CONFIGURATION'); ?>">
        <ol class="gb-progress-steps">
            <?php foreach ($steps as $i => $step) : ?>
            <li class="gb-progress-step<?php echo $i === 0 ? ' is-active' : ''; ?>" aria-label="Step <?php echo $i + 1; ?>: <?php echo htmlspecialchars($step->step_label ?: $step->option_name, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="gb-progress-btn" type="button"<?php echo $i > 0 ? ' disabled' : ''; ?> aria-current="<?php echo $i === 0 ? 'step' : 'false'; ?>">
                    <span class="gb-step-number" aria-hidden="true">
                        <?php if ($step->step_icon) : ?>
                        <span class="<?php echo htmlspecialchars($step->step_icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <?php else : ?>
                        <?php echo $i + 1; ?>
                        <?php endif; ?>
                    </span>
                    <span class="gb-step-label"><?php echo htmlspecialchars($step->step_label ?: $step->option_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
            </li>
            <?php endforeach; ?>
            <li class="gb-progress-step" aria-label="<?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW'); ?>">
                <button class="gb-progress-btn" type="button" disabled aria-current="false">
                    <span class="gb-step-number" aria-hidden="true">
                        <span class="fa-solid fa-check-double" aria-hidden="true"></span>
                    </span>
                    <span class="gb-step-label"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_REVIEW'); ?></span>
                </button>
            </li>
        </ol>
    </div>
    <?php endif; ?>

    <div class="row gb-configurator-row">
        <?php if ($previewPosition === 'left') : ?>
        <div class="col-lg-7">
            <div class="gb-preview-panel sticky-lg-top" style="top: 1rem;">
                <div class="gb-preview-canvas" style="background:<?php echo htmlspecialchars($previewBg, ENT_QUOTES, 'UTF-8'); ?>; aspect-ratio:<?php echo str_replace(':', ' / ', $aspectRatio); ?>;">
                    <?php if (!empty($product->images->main_image)) : ?>
                    <img src="<?php echo htmlspecialchars($product->images->main_image, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:0;">
                    <?php endif; ?>
                </div>

                <div class="gb-price-card">
                    <?php if ($basePrice > 0): ?>
                    <div class="gb-price-line">
                        <span><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BASE_PRICE'); ?></span>
                        <span><?php echo $basePriceFormatted; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="gb-price-breakdown"></div>
                    <div class="gb-price-line gb-price-total-line">
                        <span><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TOTAL_PRICE'); ?></span>
                        <span class="gb-price-total"><?php echo $basePriceFormatted; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
        <?php else : ?>
        <div class="col-12">
            <?php if ($previewPosition === 'top') : ?>
            <div class="gb-preview-panel mb-4">
                <div class="gb-preview-canvas" style="background:<?php echo htmlspecialchars($previewBg, ENT_QUOTES, 'UTF-8'); ?>; aspect-ratio:<?php echo str_replace(':', ' / ', $aspectRatio); ?>;">
                    <?php if (!empty($product->images->main_image)) : ?>
                    <img src="<?php echo htmlspecialchars($product->images->main_image, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:0;">
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

            <div class="gb-step-container">
                <!-- Step content loaded via AJAX -->
            </div>

            <div class="gb-navigation d-flex gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary gb-btn-back" style="display:none;">
                    <span class="fa-solid fa-arrow-left" aria-hidden="true"></span> <?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BACK'); ?>
                </button>
                <button type="button" class="btn btn-primary gb-btn-next flex-grow-1">
                    <?php echo Text::_('JNEXT'); ?> <span class="fa-solid fa-arrow-right" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-success gb-btn-add-to-cart flex-grow-1" style="display:none;">
                    <span class="fa-solid fa-cart-plus" aria-hidden="true"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_TO_CART'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php if ($showStickyBar) : ?>
    <div class="gb-mobile-price-bar">
        <div>
            <span class="fw-semibold"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TOTAL_PRICE'); ?>:</span>
            <span class="gb-price-total fw-bold"><?php echo $basePriceFormatted; ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
