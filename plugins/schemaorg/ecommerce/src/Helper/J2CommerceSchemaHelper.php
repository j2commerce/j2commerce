<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Helper;

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper class for extracting J2Commerce product data for schema generation.
 *
 * This class provides methods to detect J2Commerce products, extract product data,
 * and map J2Commerce fields to schema.org properties.
 *
 * @since  6.0.0
 */
class J2CommerceSchemaHelper
{
    /**
     * Singleton instance
     *
     * @var    J2CommerceSchemaHelper|null
     * @since  6.0.0
     */
    private static ?J2CommerceSchemaHelper $instance = null;

    /**
     * Database interface
     *
     * @var    DatabaseInterface
     * @since  6.0.0
     */
    private DatabaseInterface $db;

    /**
     * J2Commerce configuration cache
     *
     * @var    array|null
     * @since  6.0.0
     */
    private ?array $j2commerceConfig = null;

    /**
     * Product cache to avoid repeated queries
     *
     * @var    array
     * @since  6.0.0
     */
    private array $productCache = [];

    /**
     * Constructor
     *
     * @since  6.0.0
     */
    public function __construct()
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Get the singleton instance
     *
     * @return  J2CommerceSchemaHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(): J2CommerceSchemaHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Check if J2Commerce is installed and enabled
     *
     * @return  boolean  True if J2Commerce is available
     *
     * @since   6.0.0
     */
    public function isJ2CommerceAvailable(): bool
    {
        // Check if the component is enabled
        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return false;
        }

        return class_exists(J2CommerceHelper::class);
    }

    /**
     * Get J2Commerce product by article ID
     *
     * @param   int  $articleId  The Joomla article ID
     *
     * @return  object|null  The product object or null if not found
     *
     * @since   6.0.0
     */
    public function getProductByArticleId(int $articleId): ?object
    {
        $cacheKey = 'article_' . $articleId;

        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }

        if (!$this->isJ2CommerceAvailable()) {
            return null;
        }

        try {
            // Query the product table
            $query = $this->db->getQuery(true)
                ->select('p.*')
                ->from($this->db->quoteName('#__j2commerce_products', 'p'))
                ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
                ->where($this->db->quoteName('p.product_source_id') . ' = :articleId')
                ->where($this->db->quoteName('p.enabled') . ' = 1')
                ->bind(':articleId', $articleId, ParameterType::INTEGER);

            $this->db->setQuery($query);
            $product = $this->db->loadObject();

            if (!$product) {
                $this->productCache[$cacheKey] = null;
                return null;
            }

            // Load additional product data
            $this->loadProductDetails($product);

            $this->productCache[$cacheKey] = $product;

            return $product;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get J2Commerce product by product ID
     *
     * @param   int  $productId  The J2Commerce product ID
     *
     * @return  object|null  The product object or null if not found
     *
     * @since   6.0.0
     */
    public function getProductById(int $productId): ?object
    {
        $cacheKey = 'product_' . $productId;

        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }

        if (!$this->isJ2CommerceAvailable()) {
            return null;
        }

        try {
            $productHelper = J2CommerceHelper::product();
            $product = $productHelper->getFullProduct($productId);


            if (!$product || !isset($product->j2commerce_product_id)) {
                // Fallback to direct database query
                $query = $this->db->getQuery(true)
                    ->select('p.*')
                    ->from($this->db->quoteName('#__j2commerce_products', 'p'))
                    ->where($this->db->quoteName('p.j2commerce_product_id') . ' = :productId')
                    ->where($this->db->quoteName('p.enabled') . ' = 1')
                    ->bind(':productId', $productId, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $product = $this->db->loadObject();

                if (!$product) {
                    $this->productCache[$cacheKey] = null;
                    return null;
                }
            }

            $this->loadProductDetails($product);

            $this->productCache[$cacheKey] = $product;

            return $product;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Load additional product details (variants, images, etc.)
     *
     * @param   object  $product  The product object to populate
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function loadProductDetails(object $product): void
    {
        $productId = (int) $product->j2commerce_product_id;

        // Load the master variant if not already loaded
        if (!isset($product->variant) || empty($product->variant)) {
            $product->variant = $this->getMasterVariant($productId);
        }

        // Load all variants - always check the database for variable products
        $variableTypes = ['variable', 'advancedvariable', 'flexivariable', 'variablesubscriptionproduct'];

        if (\in_array($product->product_type ?? '', $variableTypes, true)) {
            // Only load if it is not already populated
            if (!isset($product->variants) || empty($product->variants)) {
                $product->variants = $this->getAllVariants($productId);
            }
        } else {
            // For simple products, use teh master variant only
            $product->variants = $product->variant ? [$product->variant] : [];
        }

        // Load product images
        $product->images = $this->getProductImages($productId);

        // Load article data for name/description
        if ($product->product_source === 'com_content' && $product->product_source_id) {
            $product->source = $this->getArticleData((int) $product->product_source_id);
        }

        // Load manufacturer if set
        if (!empty($product->manufacturer_id)) {
            $product->manufacturer = $this->getManufacturer((int) $product->manufacturer_id);
        }
    }

    /**
     * Get the master variant for a product
     *
     * @param   int  $productId  The product ID
     *
     * @return  object|null  The variant object or null
     *
     * @since   6.0.0
     */
    public function getMasterVariant(int $productId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('v.*')
            ->from($this->db->quoteName('#__j2commerce_variants', 'v'))
            ->where($this->db->quoteName('v.product_id') . ' = :productId')
            ->where($this->db->quoteName('v.is_master') . ' = 1')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $variant = $this->db->loadObject();

        if ($variant) {
            // Load quantity data
            $this->loadVariantQuantity($variant);
        }

        return $variant;
    }

    /**
     * Get all variants for a product
     *
     * @param   int  $productId  The product ID
     *
     * @return  array  Array of variant objects
     *
     * @since   6.0.0
     */
    public function getAllVariants(int $productId): array
    {
        // Join with product_variant_optionvalues to get the variant_name (option value IDs)
        $query = $this->db->getQuery(true)
            ->select('v.*')
            ->select($this->db->quoteName('pvo.product_optionvalue_ids', 'variant_name'))
            ->from($this->db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo')
                . ' ON ' . $this->db->quoteName('v.j2commerce_variant_id') . ' = ' . $this->db->quoteName('pvo.variant_id')
            )
            ->where($this->db->quoteName('v.product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $variants = $this->db->loadObjectList();

        foreach ($variants as $variant) {
            $this->loadVariantQuantity($variant);
        }

        return $variants ?: [];
    }

    /**
     * Load quantity data for a variant
     *
     * @param   object  $variant  The variant object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function loadVariantQuantity(object $variant): void
    {
        $variantId = (int) $variant->j2commerce_variant_id;

        $query = $this->db->getQuery(true)
            ->select('pq.quantity, pq.sold')
            ->from($this->db->quoteName('#__j2commerce_productquantities', 'pq'))
            ->where($this->db->quoteName('pq.variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $qty = $this->db->loadObject();

        $variant->quantity = $qty->quantity ?? 0;
        $variant->sold_qty = $qty->sold ?? 0;
    }

    /**
     * Get product images
     *
     * @param   int  $productId  The product ID
     *
     * @return  object|null  The images object or null
     *
     * @since   6.0.0
     */
    public function getProductImages(int $productId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_productimages'))
            ->where($this->db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    /**
     * Get article data
     *
     * @param   int  $articleId  The article ID
     *
     * @return  object|null  The article object or null
     *
     * @since   6.0.0
     */
    public function getArticleData(int $articleId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('c.id, c.title, c.alias, c.introtext, c.fulltext, c.state, c.catid, c.images, c.access')
            ->from($this->db->quoteName('#__content', 'c'))
            ->where($this->db->quoteName('c.id') . ' = :articleId')
            ->bind(':articleId', $articleId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    /**
     * Get manufacturer data
     *
     * @param   int  $manufacturerId  The manufacturer ID
     *
     * @return  object|null  The manufacturer object or null
     *
     * @since   6.0.0
     */
    public function getManufacturer(int $manufacturerId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__j2commerce_manufacturers'))
            ->where($this->db->quoteName('j2commerce_manufacturer_id') . ' = :manufacturerId')
            ->bind(':manufacturerId', $manufacturerId, ParameterType::INTEGER);

        $this->db->setQuery($query);

        return $this->db->loadObject();
    }

    /**
     * Get J2Commerce configuration value
     *
     * @param   string  $key      The configuration key
     * @param   mixed   $default  The default value
     *
     * @return  mixed  The configuration value
     *
     * @since   6.0.0
     */
    public function getConfig(string $key, $default = null)
    {
        return ComponentHelper::getParams('com_j2commerce')->get($key, $default);
    }




    /**
     * Get the store currency code
     *
     * @return  string  The currency code (e.g., 'USD')
     *
     * @since   6.0.0
     */
    public function getCurrencyCode(): string
    {
        return $this->getConfig('config_currency', 'USD');
    }

    /**
     * Get the store name
     *
     * @return  string  The store name
     *
     * @since   6.0.0
     */
    public function getStoreName(): string
    {
        return $this->getConfig('store_name', '');
    }

    /**
     * Map product availability to schema.org availability URL
     *
     * @param   object  $variant  The variant object
     *
     * @return  string  The schema.org availability URL
     *
     * @since   6.0.0
     */
    public function mapAvailability(object $variant): string
    {
        $manageStock = (int) ($variant->manage_stock ?? 0);
        $availability = (int) ($variant->availability ?? 0);
        $quantity = (int) ($variant->quantity ?? 0);
        $allowBackorder = (int) ($variant->allow_backorder ?? 0);

        // If not managing stock, always in stock
        if ($manageStock === 0) {
            return 'https://schema.org/InStock';
        }

        // Check availability flag
        if ($availability === 0) {
            return 'https://schema.org/OutOfStock';
        }

        // Check quantity
        if ($quantity > 0) {
            return 'https://schema.org/InStock';
        }

        // Out of stock but backorders allowed
        if ($allowBackorder >= 1) {
            return 'https://schema.org/BackOrder';
        }

        return 'https://schema.org/OutOfStock';
    }

    /**
     * Get the product price (base price without tax modifications)
     *
     * @param   object  $variant  The variant object
     *
     * @return  float  The product price
     *
     * @since   6.0.0
     */
    public function getProductPrice(object $variant): float
    {
        return (float) ($variant->price ?? 0.00);
    }

    /**
     * Build full URL for product
     *
     * @param   object  $product  The product object
     *
     * @return  string  The full product URL
     *
     * @since   6.0.0
     */
    public function getProductUrl(object $product): string
    {
        // Use J2Commerce's RouteHelper for proper SEF URL generation
        if ($this->isJ2CommerceAvailable() && isset($product->j2commerce_product_id)) {
            try {
                $productId = (int) $product->j2commerce_product_id;
                $alias = $product->alias ?? null;
                $catid = (int) ($product->catid ?? 0) ?: null;

                $url = Route::_(RouteHelper::getProductRoute($productId, $alias, $catid));

                return Uri::root() . ltrim($url, '/');
            } catch (\Exception $e) {
                // Fall through to article URL
            }
        }

        return Uri::root();
    }

    /**
     * Get full image URL
     *
     * @param   string  $imagePath  The relative image path
     *
     * @return  string  The full image URL
     *
     * @since   6.0.0
     */
    public function getImageUrl(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        // If already a full URL, return as-is
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }

        $imagePath = ltrim($imagePath, '/');

        return Uri::root() . $imagePath;
    }

    /**
     * Get all product images as an array of URLs
     *
     * @param   object  $product  The product object
     *
     * @return  array  Array of image URLs
     *
     * @since   6.0.0
     */
    public function getAllProductImages(object $product): array
    {
        $images = [];

        if (!isset($product->images) || !$product->images) {
            // Try to get from article images
            if (isset($product->source->images)) {
                $articleImages = json_decode($product->source->images, true);

                if (!empty($articleImages['image_intro'])) {
                    $images[] = $this->getImageUrl($articleImages['image_intro']);
                }

                if (!empty($articleImages['image_fulltext'])) {
                    $images[] = $this->getImageUrl($articleImages['image_fulltext']);
                }
            }

            return $images;
        }

        // Main image
        if (!empty($product->images->main_image)) {
            $images[] = $this->getImageUrl($product->images->main_image);
        }

        // Thumbnail (if different from main)
        if (!empty($product->images->thumb_image) && $product->images->thumb_image !== $product->images->main_image) {
            $images[] = $this->getImageUrl($product->images->thumb_image);
        }

        // Additional images
        if (!empty($product->images->additional_images)) {
            $additionalImages = json_decode($product->images->additional_images, true);

            if (\is_array($additionalImages)) {
                foreach ($additionalImages as $img) {
                    if (!empty($img)) {
                        $images[] = $this->getImageUrl($img);
                    }
                }
            }
        }

        return array_unique($images);
    }

    /**
     * Get the product name
     *
     * @param   object  $product  The product object
     *
     * @return  string  The product name
     *
     * @since   6.0.0
     */
    public function getProductName(object $product): string
    {
        // Check for product_name first (from J2Commerce)
        if (!empty($product->product_name)) {
            return $product->product_name;
        }

        if (isset($product->source->title)) {
            return $product->source->title;
        }

        return '';
    }

    /**
     * Get product description
     *
     * @param   object  $product  The product object
     * @param   int     $maxLength  Maximum description length (0 = unlimited)
     *
     * @return  string  The product description
     *
     * @since   6.0.0
     */
    public function getProductDescription(object $product, int $maxLength = 0): string
    {
        $description = '';

        // Try to get from article introtext
        if (isset($product->source->introtext)) {
            $description = strip_tags($product->source->introtext);
        }

        // Clean up whitespace
        $description = preg_replace('/\s+/', ' ', trim($description));

        // Truncate if needed
        if ($maxLength > 0 && \strlen($description) > $maxLength) {
            $description = substr($description, 0, $maxLength - 3) . '...';
        }

        return $description;
    }

    /**
     * Check if product is a variable product type
     *
     * @param   object  $product  The product object
     *
     * @return  boolean  True if variable product
     *
     * @since   6.0.0
     */
    public function isVariableProduct(object $product): bool
    {
        $variableTypes = ['variable', 'advancedvariable', 'flexivariable', 'variablesubscriptionproduct'];

        return \in_array($product->product_type ?? '', $variableTypes, true);
    }

    /**
     * Get variant option names for variesBy property
     *
     * @param   int  $productId  The product ID
     *
     * @return  array  Array of option names
     *
     * @since   6.0.0
     */
    public function getVariantOptions(int $productId): array
    {
        $query = $this->db->getQuery(true)
            ->select('DISTINCT o.option_name')
            ->from($this->db->quoteName('#__j2commerce_product_options', 'po'))
            ->join('LEFT', $this->db->quoteName('#__j2commerce_options', 'o') . ' ON po.option_id = o.j2commerce_option_id')
            ->where($this->db->quoteName('po.product_id') . ' = :productId')
            ->where($this->db->quoteName('po.is_variant') . ' = 1')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $this->db->setQuery($query);
        $results = $this->db->loadColumn();

        return $results ?: [];
    }

    /**
     * Map option name to schema.org property URL
     *
     * @param   string  $optionName  The option name
     *
     * @return  string  The schema.org property URL
     *
     * @since   6.0.0
     */
    public function mapOptionToSchemaProperty(string $optionName): string
    {
        $optionLower = strtolower($optionName);

        $mapping = [
            'color'    => 'https://schema.org/color',
            'colour'   => 'https://schema.org/color',
            'size'     => 'https://schema.org/size',
            'material' => 'https://schema.org/material',
            'pattern'  => 'https://schema.org/pattern',
            'width'    => 'https://schema.org/width',
            'height'   => 'https://schema.org/height',
            'depth'    => 'https://schema.org/depth',
            'weight'   => 'https://schema.org/weight',
        ];

        return $mapping[$optionLower] ?? 'https://schema.org/' . ucfirst($optionLower);
    }

    /**
     * Clear the product cache
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function clearCache(): void
    {
        $this->productCache = [];
    }
}
