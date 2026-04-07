<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Service;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

final class ProductLayoutService
{
    public const CONTEXT_LIST            = 'list';
    public const CONTEXT_CROSSSELL       = 'crosssell';
    public const CONTEXT_UPSELL          = 'upsell';
    public const CONTEXT_MODULE          = 'module';
    public const CONTEXT_SEARCH          = 'search';
    public const CONTEXT_AJAX            = 'ajax';
    public const CONTEXT_CUSTOMERBOUGHT  = 'customerbought';
    public const CONTEXT_ARTICLE         = 'article';

    public static function renderProductItem(
        object $product,
        Registry $params,
        string $context = self::CONTEXT_LIST,
        int $itemId = 0,
        array $overrides = []
    ): string {
        $productLink = $product->product_link ?? self::buildProductLink($product);

        $productHelper = J2CommerceHelper::product();
        $contextParts  = self::parseContext($context);

        $displayData = [
            'product'         => $product,
            'params'          => $params,
            'context'         => $context,
            'contextBase'     => $contextParts['base'],
            'contextSub'      => $contextParts['sub'],
            'contextChain'    => $contextParts['chain'],
            'itemId'          => $itemId,
            'columns'         => (int) $params->get('list_no_of_columns', 3),
            'imageWidth'      => self::getImageWidth($params, $context),
            'showImage'       => (bool) $params->get('list_show_image', 1),
            'showTitle'       => (bool) $params->get('list_show_title', 1),
            'showDescription' => (bool) $params->get('list_show_description', 0),
            'showPrice'       => $productHelper->canShowprice($params),
            'showCart'        => $productHelper->canShowCart($params),
            'showSku'         => (bool) $params->get('list_show_product_sku', 0),
            'showStock'       => (bool) $params->get('list_show_product_stock', 0)
                                 && isset($product->variant)
                                 && \is_object($product->variant),
            'showQuickview' => (bool) $params->get('list_enable_quickview', 0),
            'linkTitle'     => (bool) $params->get('list_link_title', 1),
            'linkImage'     => (bool) $params->get('list_image_link_to_product', 1),
            'productLink'   => $productLink,
            'cartText'      => !empty($product->addtocart_text)
                                 ? Text::_($product->addtocart_text)
                                 : Text::_('COM_J2COMMERCE_ADD_TO_CART'),
            'layoutBasePath' => self::getActivePluginLayoutPath(),
        ];

        $displayData = array_merge($displayData, $overrides);

        return self::renderLayout('list.category.item', $displayData);
    }

    public static function parseContext(string $context): array
    {
        $parts = explode('.', $context, 2);
        $base  = $parts[0] ?: self::CONTEXT_LIST;
        $sub   = $parts[1] ?? null;

        $chain = [];
        if ($sub !== null) {
            $chain[] = $context;
        }
        $chain[] = $base;
        if ($base !== self::CONTEXT_LIST) {
            $chain[] = self::CONTEXT_LIST;
        }

        return [
            'base'  => $base,
            'sub'   => $sub,
            'chain' => array_unique($chain),
        ];
    }

    public static function contextMatches(string $context, string|array $patterns): bool
    {
        $patterns = (array) $patterns;
        $parsed   = self::parseContext($context);

        foreach ($patterns as $pattern) {
            if ($pattern === $context) {
                return true;
            }

            if ($pattern === $parsed['base']) {
                return true;
            }

            if (str_ends_with($pattern, '.*')) {
                $patternBase = substr($pattern, 0, -2);
                if ($patternBase === $parsed['base'] && $parsed['sub'] !== null) {
                    return true;
                }
            }

            if (\in_array($pattern, $parsed['chain'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function contextExcluded(string $context, string|array $excludes): bool
    {
        return self::contextMatches($context, $excludes);
    }

    public static function renderLayout(string $layoutId, array $displayData): string
    {
        $pluginElement = self::getActivePluginElement();
        $layout        = new FileLayout($layoutId);

        $template = Factory::getApplication()->getTemplate();
        $paths    = [];

        if ($pluginElement) {
            // Template override for this subtemplate (highest priority)
            $tplPath = JPATH_ROOT . '/templates/' . $template . '/html/layouts/com_j2commerce/' . $pluginElement;
            if (is_dir($tplPath)) {
                $paths[] = $tplPath;
            }

            // Component source for this subtemplate
            $compPath = JPATH_ROOT . '/components/com_j2commerce/layouts/' . $pluginElement;
            if (is_dir($compPath)) {
                $paths[] = $compPath;
            }

            // Plugin source (lowest priority fallback)
            $pluginPath = $displayData['layoutBasePath'] ?? self::getActivePluginLayoutPath();
            if ($pluginPath) {
                $paths[] = $pluginPath;
            }
        }

        if (!empty($paths)) {
            $layout->setIncludePaths($paths);
        }


        return $layout->render($displayData);
    }

    private static function getActivePluginElement(): string
    {
        return self::resolvePluginFolder();
    }

    private static function getActivePluginLayoutPath(): string
    {
        $path = JPATH_PLUGINS . '/j2commerce/' . self::resolvePluginFolder() . '/layouts';

        return is_dir($path) ? $path : '';
    }

    private static ?string $subtemplateOverride = null;

    public static function setSubtemplateOverride(string $subtemplate): void
    {
        // Accept both element names (app_bootstrap5) and short names (bootstrap5)
        self::$subtemplateOverride = str_starts_with($subtemplate, 'app_')
            ? $subtemplate
            : 'app_' . $subtemplate;
    }

    public static function clearSubtemplateOverride(): void
    {
        self::$subtemplateOverride = null;
    }

    private static function resolvePluginFolder(): string
    {
        if (self::$subtemplateOverride !== null) {
            return self::$subtemplateOverride;
        }

        static $folder;

        if ($folder !== null) {
            return $folder;
        }

        try {
            $subtemplate = J2CommerceHelper::config()->get('subtemplate', 'bootstrap5');
        } catch (\Throwable) {
            $subtemplate = 'bootstrap5';
        }

        $folder = match ($subtemplate) {
            'tag_bootstrap5', 'bootstrap5' => 'app_bootstrap5',
            'uikit' => 'app_uikit',
            default => 'app_' . $subtemplate,
        };

        return $folder;
    }

    private static function getImageWidth(Registry $params, string $context): int
    {
        $base = self::parseContext($context)['base'];

        return match ($base) {
            'crosssell' => (int) $params->get('item_product_cross_image_width', 100),
            'upsell'    => (int) $params->get('item_product_upsell_image_width', 100),
            'module'    => (int) $params->get('module_image_thumbnail_width', 150),
            default     => (int) $params->get('list_image_thumbnail_width', 200),
        };
    }

    private static function buildProductLink(object $product): string
    {
        $productId = (int) ($product->j2commerce_product_id ?? 0);
        $alias     = $product->product_alias ?? $product->alias ?? null;
        $catid     = (int) ($product->catid ?? $product->product_catid ?? 0);

        $rawUrl = RouteHelper::getProductRoute($productId, $alias, $catid ?: null);

        return Route::_($rawUrl);
    }
}
