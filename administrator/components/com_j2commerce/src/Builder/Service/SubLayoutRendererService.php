<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Builder\Service;

\defined('_JEXEC') or die;

final class SubLayoutRendererService
{
    private TokenRegistry $tokenRegistry;

    // No block groups — all blocks are independent or handled as combined slugs (e.g. cart-form)

    public function __construct()
    {
        $this->tokenRegistry = new TokenRegistry();
    }

    public function regenerateFromBlockOrder(array $blockOrder): array
    {
        if (empty($blockOrder)) {
            return ['success' => false, 'error' => 'No blocks specified', 'php' => ''];
        }

        $php = $this->buildCompositionPhp($blockOrder);

        $syntaxCheck = $this->validatePhpSyntax($php);

        if (!$syntaxCheck['valid']) {
            return ['success' => false, 'error' => 'PHP syntax error: ' . $syntaxCheck['error'], 'php' => $php];
        }

        return ['success' => true, 'error' => '', 'php' => $php];
    }

    public function regeneratePhp(string $modifiedHtml, array $settings = [], string $sourceFile = ''): array
    {
        $validation = $this->validateInput($modifiedHtml);

        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'php' => ''];
        }

        $php = $this->tokenRegistry->replaceTokensWithPhp($modifiedHtml);
        $php = $this->stripBuilderAttributes($php);
        $php = $this->wrapWithHeader($php, $sourceFile);

        $syntaxCheck = $this->validatePhpSyntax($php);

        if (!$syntaxCheck['valid']) {
            return ['success' => false, 'error' => 'PHP syntax error: ' . $syntaxCheck['error'], 'php' => $php];
        }

        return ['success' => true, 'error' => '', 'php' => $php];
    }

    private function buildCompositionPhp(array $blockOrder): string
    {
        $sections = [];

        foreach ($blockOrder as $slug) {
            $section = $this->getBlockSection($slug);

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        return $this->getCompositionHeader()
            . $this->getContainerOpen()
            . implode("\n", $sections)
            . $this->getContainerClose();
    }

    private function getBlockSection(string $slug): ?string
    {
        return match ($slug) {
            'product-image' => <<<'PHP'
    <?php if ($showImage): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_images', $displayData); ?>
    <?php endif; ?>
PHP,
            'product-title' => <<<'PHP'
    <?php if ($showTitle): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_title', $displayData); ?>
    <?php endif; ?>

    <?php if (isset($product->event->afterDisplayTitle)): ?>
        <?php echo $product->event->afterDisplayTitle; ?>
    <?php endif; ?>

    <?php if (isset($product->event->beforeDisplayContent)): ?>
        <?php echo $product->event->beforeDisplayContent; ?>
    <?php endif; ?>
PHP,
            'product-description' => <<<'PHP'
    <?php if ($showDescription): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_description', $displayData); ?>
    <?php endif; ?>
PHP,
            'product-price' => <<<'PHP'
    <?php if ($showPrice): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_price', $displayData); ?>
    <?php endif; ?>
PHP,
            'product-sku' => <<<'PHP'
    <?php if ($showSku): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_sku', $displayData); ?>
    <?php endif; ?>
PHP,
            'product-stock' => <<<'PHP'
    <?php if ($showStock): ?>
        <?php echo ProductLayoutService::renderLayout('list.category.item_stock', $displayData); ?>
    <?php endif; ?>
PHP,
            'cart-form' => <<<'PHP'
    <?php if ($showCart): ?>
        <form action="<?php echo htmlspecialchars($product->cart_form_action ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              method="post"
              class="j2commerce-addtocart-form mt-auto"
              id="j2commerce-addtocart-form-<?php echo $productId; ?>"
              data-product_id="<?php echo $productId; ?>"
              data-product_type="<?php echo $product->product_type; ?>"
              enctype="multipart/form-data"
              >

            <?php if ($cartType == 1) : ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_options', $displayData); ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_cart', $displayData); ?>
            <?php elseif (($cartType == 2 && !empty($product->options)) || $cartType == 3) : ?>
                <a href="<?php echo $productLink; ?>" class="btn btn-outline-primary">
                    <?php echo Text::_('COM_J2COMMERCE_VIEW_PRODUCT_DETAILS'); ?>
                </a>
            <?php else : ?>
                <?php echo ProductLayoutService::renderLayout('list.category.item_cart', $displayData); ?>
            <?php endif; ?>
        </form>
    <?php endif; ?>
PHP,
            'custom-html' => <<<'PHP'
    <?php
    $j2cCustomHtmlClass = '';
    $j2cCustomHtmlContent = '';
    ?>
    <div<?php if ($j2cCustomHtmlClass): ?> class="<?php echo htmlspecialchars($j2cCustomHtmlClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo $j2cCustomHtmlContent; ?></div>
PHP,
            'custom-text' => <<<'PHP'
    <?php
    $j2cCustomTextClass  = '';
    $j2cCustomTextAlign  = 'left';
    $j2cCustomTextContent = '';
    $j2cCustomTextStyle  = $j2cCustomTextAlign !== 'left' ? ' style="text-align:' . htmlspecialchars($j2cCustomTextAlign, ENT_QUOTES, 'UTF-8') . ';"' : '';
    ?>
    <p<?php if ($j2cCustomTextClass): ?> class="<?php echo htmlspecialchars($j2cCustomTextClass, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?><?php echo $j2cCustomTextStyle; ?>><?php echo htmlspecialchars($j2cCustomTextContent, ENT_QUOTES, 'UTF-8'); ?></p>
PHP,
            'custom-divider' => <<<'PHP'
    <?php
    $j2cDividerStyle     = 'solid';
    $j2cDividerThickness = '1px';
    $j2cDividerColor     = '#dee2e6';
    ?>
    <hr style="border-style:<?php echo htmlspecialchars($j2cDividerStyle, ENT_QUOTES, 'UTF-8'); ?>; border-width:<?php echo htmlspecialchars($j2cDividerThickness, ENT_QUOTES, 'UTF-8'); ?>; border-color:<?php echo htmlspecialchars($j2cDividerColor, ENT_QUOTES, 'UTF-8'); ?>; border-bottom:none;" />
PHP,
            'custom-banner' => <<<'PHP'
    <?php
    $j2cBannerBg      = '#f8f9fa';
    $j2cBannerColor   = '#212529';
    $j2cBannerPadding = '1rem';
    $j2cBannerClass   = '';
    $j2cBannerContent = '';
    $j2cBannerCssClass = 'j2c-banner' . ($j2cBannerClass ? ' ' . htmlspecialchars($j2cBannerClass, ENT_QUOTES, 'UTF-8') : '');
    ?>
    <div class="<?php echo $j2cBannerCssClass; ?>" style="background:<?php echo htmlspecialchars($j2cBannerBg, ENT_QUOTES, 'UTF-8'); ?>; color:<?php echo htmlspecialchars($j2cBannerColor, ENT_QUOTES, 'UTF-8'); ?>; padding:<?php echo htmlspecialchars($j2cBannerPadding, ENT_QUOTES, 'UTF-8'); ?>;"><?php echo $j2cBannerContent; ?></div>
PHP,
            default => null,
        };
    }

    private function getCompositionHeader(): string
    {
        return <<<'PHP'
<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Generated by J2Commerce Visual Builder
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService;

extract($displayData);

$productId = $product->j2commerce_product_id;
$cssClass = $product->params->get('product_css_class', '') ?? '';
$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeProductListItemDisplay',[$product, $context, &$displayData])->getArgument('html', '');
$afterHtml = J2CommerceHelper::plugin()->eventWithHtml('AfterProductListItemDisplay',[$product, $context, &$displayData])->getArgument('html', '');
$cartType = (int) $params->get('list_show_cart', 1);
?>

PHP;
    }

    private function getContainerOpen(): string
    {
        return <<<'PHP'
<div class="j2commerce-product-item j2commerce-product-<?php echo $productId; ?> j2commerce-type-<?php echo $product->product_type; ?> <?php echo $cssClass; ?> d-flex flex-column" data-product-id="<?php echo $productId; ?>" data-product-type="<?php echo $product->product_type;?>" data-equal-height="itemContainer">

    <?php echo $beforeHtml; ?>

PHP;
    }

    private function getContainerClose(): string
    {
        return <<<'PHP'

    <?php if (isset($product->event->afterDisplayContent)): ?>
        <?php echo $product->event->afterDisplayContent; ?>
    <?php endif; ?>

    <?php echo $afterHtml; ?>
</div>

PHP;
    }

    public function regenerateSubLayoutPhp(string $html, string $subLayoutId): array
    {
        $validation = $this->validateInput($html);

        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error'], 'php' => ''];
        }

        // Restore locked sections first (event hooks, structural PHP)
        $php = $this->restoreLockedSections($html);

        // Restore conditional blocks
        $php = $this->restoreConditionals($php);

        // Restore token placeholders back to PHP echo statements
        $php = $this->tokenRegistry->replaceSubLayoutTokensWithPhp($php);

        // Strip builder-internal data-* attributes from the output
        $php = $this->stripBuilderAttributes($php);

        // Prepend the correct PHP header for this sub-layout
        $header = $this->getSubLayoutHeader($subLayoutId);
        $php    = $header . $php . "\n";

        $syntaxCheck = $this->validatePhpSyntax($php);

        if (!$syntaxCheck['valid']) {
            return ['success' => false, 'error' => 'PHP syntax error: ' . $syntaxCheck['error'], 'php' => $php];
        }

        return ['success' => true, 'error' => '', 'php' => $php];
    }

    public function getSubLayoutHeader(string $subLayoutId): string
    {
        $copyright = <<<'PHP'
<?php
/**
 * @package     J2Commerce
 * @subpackage  Layout Override
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC
 * @license     GNU General Public License version 2 or later
 *
 * Generated by J2Commerce Visual Builder
 */

declare(strict_types=1);

defined('_JEXEC') or die;

PHP;

        // Closing PHP tag is split to prevent PHP parser from treating it as a tag inside strings
        $close = '?' . '>';

        return $copyright . match ($subLayoutId) {
            'item-title' => implode("\n", [
                '',
                'extract($displayData);',
                '',
                'if (!$showTitle) {',
                '    return;',
                '}',
                '',
                "\$productName = htmlspecialchars(\$product->product_name ?? '', ENT_QUOTES, 'UTF-8');",
                $close,
            ]),
            'item-images' => implode("\n", [
                '',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\ImageHelper;',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper;',
                'use J2Commerce\\Component\\J2commerce\\Site\\Service\\ProductLayoutService;',
                'use Joomla\\CMS\\HTML\\HTMLHelper;',
                'use Joomla\\CMS\\Language\\Text;',
                '',
                'extract($displayData);',
                '',
                'if (!$showImage) {',
                '    return;',
                '}',
                '',
                "\$platform   = J2CommerceHelper::platform();",
                "\$imageType  = \$params->get('list_image_type', 'thumbnail');",
                "\$imageWidth = (int) \$params->get('list_image_thumbnail_width', 350);",
                "\$image      = '';",
                "\$imageAlt   = '';",
                '',
                "if (\$imageType === 'thumbimage' || \$imageType === 'thumbnail') {",
                "    \$image    = \$platform->getImagePath(\$product->thumb_image ?? '');",
                "    \$imageAlt = \$product->thumb_image_alt ?? \$product->product_name ?? '';",
                '} else {',
                "    \$image    = \$platform->getImagePath(\$product->main_image ?? '');",
                "    \$imageAlt = \$product->main_image_alt ?? \$product->product_name ?? '';",
                '}',
                '',
                "\$image = HTMLHelper::_('cleanImageURL', \$image)->url;",
                '',
                'if (empty($image)) {',
                '    return;',
                '}',
                '',
                "\$productName = htmlspecialchars(\$product->product_name ?? '', ENT_QUOTES, 'UTF-8');",
                "\$imageAlt    = htmlspecialchars(\$imageAlt ?? '', ENT_QUOTES, 'UTF-8');",
                "\$basePrice   = \$product->pricing->base_price ?? 0;",
                "\$salePrice   = \$product->pricing->price ?? 0;",
                $close,
            ]),
            'item-price' => implode("\n", [
                '',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper;',
                '',
                'extract($displayData);',
                '',
                "\$productHelper = J2CommerceHelper::product();",
                '',
                'if (!$showPrice || !$productHelper->canShowprice($params)) {',
                '    return;',
                '}',
                '',
                "\$pricing = \$product->pricing ?? null;",
                'if (!$pricing) {',
                '    return;',
                '}',
                '',
                "\$showBasePrice    = (bool) \$params->get('list_show_product_base_price', 1);",
                "\$showSpecialPrice = (bool) \$params->get('list_show_product_special_price', 1);",
                "\$showTaxInfo      = (bool) \$params->get('display_price_with_tax_info', 0);",
                '',
                'if (!$showBasePrice && !$showSpecialPrice) {',
                '    return;',
                '}',
                '',
                "\$beforeHtml = J2CommerceHelper::plugin()->eventWithHtml('BeforeRenderingProductPrice', [\$product])->getArgument('html', '');",
                "\$afterHtml  = J2CommerceHelper::plugin()->eventWithHtml('AfterRenderingProductPrice', [\$product])->getArgument('html', '');",
                "\$basePrice  = \$pricing->base_price ?? 0;",
                "\$salePrice  = \$pricing->price ?? 0;",
                $close,
            ]),
            'item-cart' => implode("\n", [
                '',
                'use Joomla\\CMS\\HTML\\HTMLHelper;',
                'use Joomla\\CMS\\Language\\Text;',
                'use Joomla\\CMS\\Uri\\Uri;',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper;',
                '',
                'extract($displayData);',
                '',
                "\$productHelper = J2CommerceHelper::product();",
                '',
                'if (!$showCart || !$productHelper->canShowCart($params)) {',
                '    return;',
                '}',
                '',
                "\$cartType       = (int) \$params->get('list_show_cart', 1);",
                "\$btnClass       = \$params->get('addtocart_button_class', 'btn btn-primary') ?? 'btn btn-primary';",
                "\$productId      = (int) \$product->j2commerce_product_id;",
                "\$productType    = htmlspecialchars(\$product->product_type ?? '', ENT_QUOTES, 'UTF-8');",
                "\$esc            = static fn(string \$value): string => htmlspecialchars(\$value, ENT_QUOTES, 'UTF-8');",
                "\$show           = \$productHelper->validateVariableProduct(\$product);",
                "\$beforeCart     = J2CommerceHelper::plugin()->eventWithHtml('BeforeAddToCartButton', [\$product, \$context])->getArgument('html', '');",
                "\$afterCart      = J2CommerceHelper::plugin()->eventWithHtml('AfterAddToCartButton', [\$product, \$context])->getArgument('html', '');",
                $close,
            ]),
            'item-description' => implode("\n", [
                '',
                'extract($displayData);',
                '',
                'if (!$showDescription) {',
                '    return;',
                '}',
                '',
                "\$shortDesc = \$product->short_description ?? '';",
                'if (empty($shortDesc)) {',
                '    return;',
                '}',
                '',
                "\$maxLength = (int) \$params->get('list_description_length', 150);",
                'if ($maxLength > 0 && strlen($shortDesc) > $maxLength) {',
                "    \$shortDesc = substr(\$shortDesc, 0, \$maxLength) . '...';",
                '}',
                $close,
            ]),
            'item-sku' => implode("\n", [
                '',
                'use Joomla\\CMS\\Language\\Text;',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper;',
                '',
                'extract($displayData);',
                '',
                "\$productHelper = J2CommerceHelper::product();",
                '',
                'if (!$showSku || !$productHelper->canShowSku($params)) {',
                '    return;',
                '}',
                '',
                "\$sku = \$product->variant->sku ?? \$product->sku ?? '';",
                'if (empty($sku)) {',
                '    return;',
                '}',
                $close,
            ]),
            'item-stock' => implode("\n", [
                '',
                'use Joomla\\CMS\\Component\\ComponentHelper;',
                'use Joomla\\CMS\\Language\\Text;',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\J2CommerceHelper;',
                'use J2Commerce\\Component\\J2commerce\\Administrator\\Helper\\ProductHelper;',
                '',
                'extract($displayData);',
                '',
                "\$productHelper = J2CommerceHelper::product();",
                '',
                'if (!$showStock) {',
                '    return;',
                '}',
                '',
                "\$variant = \$product->variant ?? null;",
                'if (!$variant || !is_object($variant)) {',
                '    return;',
                '}',
                '',
                "\$manageStock = \$productHelper->managing_stock(\$variant);",
                "\$inStock     = !\$manageStock || !empty(\$variant->availability);",
                "\$stockClass  = \$inStock ? 'in-stock' : 'out-of-stock';",
                '',
                'if (!$inStock) {',
                "    \$stockText = Text::_('COM_J2COMMERCE_OUT_OF_STOCK');",
                '} elseif ($manageStock) {',
                "    \$componentParams = ComponentHelper::getParams('com_j2commerce');",
                '    $stockText = ProductHelper::displayStock($variant, $componentParams);',
                '    if (empty($stockText)) {',
                '        return;',
                '    }',
                '} else {',
                "    \$stockText = Text::_('COM_J2COMMERCE_IN_STOCK');",
                '}',
                $close,
            ]),
            'item-quickview' => implode("\n", [
                '',
                'use Joomla\\CMS\\Language\\Text;',
                'use Joomla\\CMS\\Router\\Route;',
                'use J2Commerce\\Component\\J2commerce\\Site\\Helper\\RouteHelper;',
                '',
                'extract($displayData);',
                '',
                'if (!$showQuickview) {',
                '    return;',
                '}',
                '',
                "\$quickviewUrl = Route::_(RouteHelper::getProductRoute((int) \$product->j2commerce_product_id) . '&tmpl=component');",
                $close,
            ]),
            default => implode("\n", [
                '',
                'extract($displayData);',
                $close,
            ]),
        };
    }

    private function restoreLockedSections(string $html): string
    {
        // Splits the PHP close tag to avoid PHP parser interpreting it as a tag inside string literals.
        $open  = '<' . '?php';
        $close = '?' . '>';

        return preg_replace_callback(
            '/<span[^>]+data-j2c-locked="([a-z-]+)"[^>]*>\s*<\/span>/i',
            static function (array $matches) use ($open, $close): string {
                $lockName = $matches[1];
                $varName  = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $lockName))));

                return $open . ' echo $' . $varName . '; ' . $close;
            },
            $html
        ) ?? $html;
    }

    private function restoreConditionals(string $html): string
    {
        // Splits PHP open/close tags to avoid PHP parser interpreting them as tags inside string literals.
        $open  = '<' . '?php';
        $close = '?' . '>';

        return preg_replace_callback(
            '/<j2c-conditional\s+data-condition="([^"]+)"[^>]*>(.*?)<\/j2c-conditional>/si',
            static function (array $matches) use ($open, $close): string {
                $condition = $matches[1];
                $content   = $matches[2];

                return $open . ' if (' . $condition . '): ' . $close
                    . $content
                    . $open . ' endif; ' . $close;
            },
            $html
        ) ?? $html;
    }

    private function validateInput(string $html): array
    {
        if (preg_match('/<\?(?!xml)/i', $html)) {
            return ['valid' => false, 'error' => 'Raw PHP tags are not allowed in builder output'];
        }

        if (preg_match('/<script\b/i', $html)) {
            return ['valid' => false, 'error' => 'Script tags are not allowed in builder output'];
        }

        if (preg_match('/\bon\w+\s*=/i', $html)) {
            return ['valid' => false, 'error' => 'Inline event handlers are not allowed in builder output'];
        }

        if (preg_match('/<iframe\b/i', $html)) {
            return ['valid' => false, 'error' => 'Iframe elements are not allowed in builder output'];
        }

        if (preg_match('/<object\b/i', $html)) {
            return ['valid' => false, 'error' => 'Object elements are not allowed in builder output'];
        }

        if (preg_match('/<embed\b/i', $html)) {
            return ['valid' => false, 'error' => 'Embed elements are not allowed in builder output'];
        }

        if (preg_match('/<base\b/i', $html)) {
            return ['valid' => false, 'error' => 'Base elements are not allowed in builder output'];
        }

        return ['valid' => true, 'error' => ''];
    }

    private function stripBuilderAttributes(string $html): string
    {
        return preg_replace('/\s*data-j2c-[a-z-]+="[^"]*"/', '', $html) ?? $html;
    }

    private function wrapWithHeader(string $body, string $sourceFile): string
    {
        $header  = "<?php\n";
        $header .= "/**\n";
        $header .= " * @package     J2Commerce\n";
        $header .= " * @subpackage  Layout Override\n";
        $header .= " *\n";
        $header .= " * @copyright   (C)2024-2026 J2Commerce, LLC\n";
        $header .= " * @license     GNU General Public License version 2 or later\n";
        $header .= " *\n";
        $header .= " * Generated by J2Commerce Visual Builder\n";

        if ($sourceFile) {
            $header .= ' * Source: ' . basename($sourceFile) . "\n";
        }

        $header .= " */\n\n";
        $header .= "\\defined('_JEXEC') or die;\n\n";
        $header .= "extract(\$displayData);\n";
        $header .= "?>\n";

        return $header . $body . "\n";
    }

    private function validatePhpSyntax(string $php): array
    {
        try {
            @token_get_all($php, TOKEN_PARSE);

            return ['valid' => true, 'error' => ''];
        } catch (\ParseError $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
