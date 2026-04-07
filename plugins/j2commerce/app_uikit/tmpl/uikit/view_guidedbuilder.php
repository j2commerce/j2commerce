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
Text::script('JPREVIOUS');
Text::script('JNEXT');
Text::script('JYES');
Text::script('JNO');
Text::script('COM_J2COMMERCE_ADD_TO_CART');

$previewMode     = $params->get('gb_preview_mode', $appParams->get('default_preview_mode', 'static_image'));
$previewPosition = $params->get('gb_preview_position', $appParams->get('default_preview_position', 'left'));
$previewBg       = $params->get('gb_preview_background', '#F8FAFC');
$aspectRatio     = $params->get('gb_preview_aspect_ratio', '4:3');
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

// Currency config for JS price formatting
$jsConfig = [
    'ajaxUrl' => Uri::base() . 'index.php?option=com_ajax&plugin=guidedbuilder&group=j2commerce&format=raw',
    'token'   => Session::getFormToken(),
    'steps'   => $stepsConfig,
    'preview' => [
        'mode'     => $previewMode,
        'position' => $previewPosition,
    ],
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

    <div class="uk-grid" uk-grid>
        <?php if ($previewPosition === 'left') : ?>
        <div class="uk-width-2-5@l">
            <div class="gb-preview-panel" uk-sticky="offset: 16; media: @l">
                <div class="gb-preview-canvas" style="background:<?php echo htmlspecialchars($previewBg, ENT_QUOTES, 'UTF-8'); ?>; aspect-ratio:<?php echo str_replace(':', ' / ', $aspectRatio); ?>;">
                    <?php if (!empty($product->images->main_image)) : ?>
                    <img src="<?php echo htmlspecialchars($product->images->main_image, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                         style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;z-index:0;">
                    <?php endif; ?>
                </div>

                <div class="gb-price-card">
                    <div class="gb-price-line">
                        <span><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_BASE_PRICE'); ?></span>
                        <span><?php echo $basePriceFormatted; ?></span>
                    </div>
                    <div class="gb-price-breakdown"></div>
                    <div class="gb-price-line gb-price-total-line">
                        <span><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TOTAL_PRICE'); ?></span>
                        <span class="gb-price-total"><?php echo $basePriceFormatted; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="uk-width-3-5@l">
        <?php else : ?>
        <div class="uk-width-1-1">
            <?php if ($previewPosition === 'top') : ?>
            <div class="gb-preview-panel uk-margin-bottom">
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

            <!-- Progress bar -->
            <?php if (\count($steps) > 1) : ?>
            <div class="gb-progress" role="navigation" aria-label="<?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_YOUR_CONFIGURATION'); ?>">
                <?php foreach ($steps as $i => $step) : ?>
                <div class="gb-progress-step<?php echo $i === 0 ? ' is-active' : ''; ?>">
                    <div class="gb-step-bubble">
                        <?php if ($step->step_icon) : ?>
                        <span class="<?php echo htmlspecialchars($step->step_icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <?php else : ?>
                        <?php echo $i + 1; ?>
                        <?php endif; ?>
                    </div>
                    <div class="gb-step-label"><?php echo htmlspecialchars($step->step_label ?: $step->option_name, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="gb-step-container">
                <!-- Step content loaded via AJAX -->
            </div>

            <div class="gb-navigation uk-flex uk-flex-between uk-margin-top">
                <button type="button" class="uk-button uk-button-default gb-btn-back" style="display:none;">
                    <span uk-icon="icon: arrow-left"></span> <?php echo Text::_('JPREVIOUS'); ?>
                </button>
                <button type="button" class="uk-button uk-button-primary gb-btn-next uk-margin-auto-left">
                    <?php echo Text::_('JNEXT'); ?> <span uk-icon="icon: arrow-right"></span>
                </button>
                <button type="button" class="uk-button uk-button-primary gb-btn-add-to-cart uk-margin-auto-left" style="display:none;">
                    <span uk-icon="icon: cart"></span> <?php echo Text::_('COM_J2COMMERCE_ADD_TO_CART'); ?>
                </button>
            </div>
        </div>
    </div>

    <?php if ($showStickyBar) : ?>
    <div class="gb-mobile-price-bar">
        <div>
            <span class="uk-text-bold"><?php echo Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_TOTAL_PRICE'); ?>:</span>
            <span class="gb-price-total uk-text-bold"><?php echo $basePriceFormatted; ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
