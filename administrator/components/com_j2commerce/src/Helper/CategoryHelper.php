<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Category Helper class for J2Commerce
 *
 * Provides category parameter retrieval with fallback to global configuration.
 *
 * @since  6.1.0
 */
class CategoryHelper
{
    protected static array $cache = [];

    /**
     * Get category params as Registry with caching
     */
    public static function getParams(int $categoryId): Registry
    {
        if (isset(self::$cache[$categoryId])) {
            return self::$cache[$categoryId];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $categoryId, ParameterType::INTEGER);

        $db->setQuery($query);
        $params = $db->loadResult() ?: '{}';

        self::$cache[$categoryId] = new Registry($params);

        return self::$cache[$categoryId];
    }

    /**
     * Get a display setting with fallback to global config
     *
     * Priority: Category param (if not using global) -> Global config -> Default
     */
    public static function getDisplaySetting(int $categoryId, string $key, mixed $default = null): mixed
    {
        $params = self::getParams($categoryId);

        // Build the use_global key for this setting group
        $useGlobalKey = self::getUseGlobalKey($key);
        $useGlobal    = $params->get($useGlobalKey, 1);

        if ($useGlobal) {
            return J2CommerceHelper::config()->get($key, $default);
        }

        return $params->get($key, $default);
    }

    /**
     * Get setting directly from category params without fallback
     */
    public static function getCategoryParam(int $categoryId, string $key, mixed $default = null): mixed
    {
        $params = self::getParams($categoryId);

        return $params->get($key, $default);
    }

    /**
     * Check if a setting group is using global configuration
     */
    public static function isUsingGlobal(int $categoryId, string $group): bool
    {
        $params       = self::getParams($categoryId);
        $useGlobalKey = $group . '_use_global';

        return (bool) $params->get($useGlobalKey, 1);
    }

    /**
     * Get all J2Commerce category settings as array
     */
    public static function getJ2CommerceSettings(int $categoryId): array
    {
        $params = self::getParams($categoryId);

        // Include known J2Commerce category setting keys
        $knownKeys = [
            'display_use_global', 'product_columns', 'products_per_page', 'display_style',
            'image_width', 'image_height',
            'elements_use_global', 'show_product_price', 'show_add_to_cart', 'show_product_sku',
            'show_product_rating', 'show_product_description', 'show_stock_status',
            'default_taxprofile_id', 'default_manufacturer_id', 'default_visibility', 'default_product_type',
            'filters_use_global', 'enabled_filters', 'default_sort_order', 'show_sorting_dropdown', 'show_product_count',
            'subcategories_use_global', 'show_subcategories', 'subcategory_columns',
            'subcategory_image_width', 'subcategory_image_height', 'show_empty_categories', 'show_product_count_badge',
        ];

        $j2commerceSettings = [];
        foreach ($knownKeys as $key) {
            if ($params->exists($key)) {
                $j2commerceSettings[$key] = $params->get($key);
            }
        }

        return $j2commerceSettings;
    }

    /**
     * Get core category form data for all accordion items
     *
     * @return array Array of app data arrays for core accordion items
     */
    public static function getCoreCategoryFormData(object $category, string $formPrefix): array
    {
        $params   = new Registry($category->params ?? '{}');
        $settings = self::extractJ2CommerceParams($params);

        return [
            [
                'element'     => 'category_display',
                'name'        => 'COM_J2COMMERCE_CATEGORY_DISPLAY_SETTINGS',
                'description' => 'COM_J2COMMERCE_CATEGORY_DISPLAY_SETTINGS_DESC',
                'image'       => J2CommerceHelper::getAppImagePath('category_display'),
                'form_xml'    => JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/category_display.xml',
                'data'        => $settings,
                'form_prefix' => $formPrefix,
                'ordering'    => 10,
            ],
            [
                'element'     => 'category_elements',
                'name'        => 'COM_J2COMMERCE_CATEGORY_PRODUCT_ELEMENTS',
                'description' => 'COM_J2COMMERCE_CATEGORY_PRODUCT_ELEMENTS_DESC',
                'image'       => J2CommerceHelper::getAppImagePath('category_elements'),
                'form_xml'    => JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/category_elements.xml',
                'data'        => $settings,
                'form_prefix' => $formPrefix,
                'ordering'    => 20,
            ],
            [
                'element'     => 'category_defaults',
                'name'        => 'COM_J2COMMERCE_CATEGORY_PRODUCT_DEFAULTS',
                'description' => 'COM_J2COMMERCE_CATEGORY_PRODUCT_DEFAULTS_DESC',
                'image'       => J2CommerceHelper::getAppImagePath('category_defaults'),
                'form_xml'    => JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/category_defaults.xml',
                'data'        => $settings,
                'form_prefix' => $formPrefix,
                'ordering'    => 30,
            ],
            [
                'element'     => 'category_filters',
                'name'        => 'COM_J2COMMERCE_CATEGORY_FILTER_CONFIG',
                'description' => 'COM_J2COMMERCE_CATEGORY_FILTER_CONFIG_DESC',
                'image'       => J2CommerceHelper::getAppImagePath('category_filters'),
                'form_xml'    => JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/category_filters.xml',
                'data'        => $settings,
                'form_prefix' => $formPrefix,
                'ordering'    => 40,
            ],
            [
                'element'     => 'category_subcategories',
                'name'        => 'COM_J2COMMERCE_CATEGORY_SUBCATEGORY_SETTINGS',
                'description' => 'COM_J2COMMERCE_CATEGORY_SUBCATEGORY_SETTINGS_DESC',
                'image'       => J2CommerceHelper::getAppImagePath('category_subcategories'),
                'form_xml'    => JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/category_subcategories.xml',
                'data'        => $settings,
                'form_prefix' => $formPrefix,
                'ordering'    => 50,
            ],
        ];
    }

    /**
     * Extract J2Commerce params from category params
     */
    protected static function extractJ2CommerceParams(Registry $params): array
    {
        $settings = [];
        foreach ($params->toArray() as $key => $value) {
            // Include known J2Commerce category setting keys
            if (\in_array($key, self::getKnownSettingKeys(), true)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Get known J2Commerce category setting keys
     */
    protected static function getKnownSettingKeys(): array
    {
        return [
            'display_use_global', 'product_columns', 'products_per_page', 'display_style',
            'image_width', 'image_height',
            'elements_use_global', 'show_product_price', 'show_add_to_cart', 'show_product_sku',
            'show_product_rating', 'show_product_description', 'show_stock_status',
            'default_taxprofile_id', 'default_manufacturer_id', 'default_visibility', 'default_product_type',
            'filters_use_global', 'enabled_filters', 'default_sort_order', 'show_sorting_dropdown', 'show_product_count',
            'subcategories_use_global', 'show_subcategories', 'subcategory_columns',
            'subcategory_image_width', 'subcategory_image_height', 'show_empty_categories', 'show_product_count_badge',
        ];
    }

    /**
     * Determine the use_global key based on setting key
     */
    protected static function getUseGlobalKey(string $key): string
    {
        // Map setting keys to their group prefix
        $groupMap = [
            'product_columns'          => 'display',
            'products_per_page'        => 'display',
            'display_style'            => 'display',
            'image_width'              => 'display',
            'image_height'             => 'display',
            'show_product_price'       => 'elements',
            'show_add_to_cart'         => 'elements',
            'show_product_sku'         => 'elements',
            'show_product_rating'      => 'elements',
            'show_product_description' => 'elements',
            'show_stock_status'        => 'elements',
            'enabled_filters'          => 'filters',
            'default_sort_order'       => 'filters',
            'show_sorting_dropdown'    => 'filters',
            'show_product_count'       => 'filters',
            'show_subcategories'       => 'subcategories',
            'subcategory_columns'      => 'subcategories',
            'subcategory_image_width'  => 'subcategories',
            'subcategory_image_height' => 'subcategories',
            'show_empty_categories'    => 'subcategories',
            'show_product_count_badge' => 'subcategories',
        ];

        $group = $groupMap[$key] ?? 'display';

        return $group . '_use_global';
    }

    /**
     * Clear the cache for a specific category or all categories
     */
    public static function clearCache(?int $categoryId = null): void
    {
        if ($categoryId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$categoryId]);
        }
    }
}
