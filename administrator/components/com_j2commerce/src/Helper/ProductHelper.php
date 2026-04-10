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

use J2Commerce\Component\J2commerce\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Product helper class.
 *
 * Provides static methods for product-related operations including
 * variants, options, pricing, stock management, and display functions.
 *
 * @since  6.0.3
 */
class ProductHelper
{
    /**
     * Singleton instance
     *
     * @var ProductHelper|null
     * @since 6.0.0
     */
    protected static ?ProductHelper $instance = null;

    /**
     * Cached database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.3
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Tax info text for price display
     *
     * @var   string
     * @since 6.0.3
     */
    private static string $taxInfo = '';

    /**
     * State object for instance-based operations
     *
     * @var   \stdClass
     * @since 6.0.3
     */
    protected \stdClass $state;

    /**
     * Properties storage for backward compatibility
     *
     * @var   array
     * @since 6.0.3
     */
    protected array $properties = [];

    /**
     * Includes tax flag
     *
     * @var   bool
     * @since 6.0.3
     */
    public bool $_includes_tax = false;

    /**
     * Tax info text for instance
     *
     * @var   string
     * @since 6.0.3
     */
    public string $_tax_info = '';

    /**
     * Constructor
     *
     * @param   array|null  $properties  Optional properties
     *
     * @since   6.0.3
     */
    public function __construct(?array $properties = null)
    {
        $this->state = new \stdClass();

        if ($properties !== null) {
            $this->properties = $properties;
        }
    }

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.3
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties
     *
     * @return  ProductHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): ProductHelper
    {
        if (self::$instance === null) {
            self::$instance = new self($properties);
        }

        return self::$instance;
    }

    // =========================================================================
    // STATE MANAGEMENT METHODS (Instance-based)
    // =========================================================================

    /**
     * Magic getter for state properties
     *
     * @param   string  $name  The name of the variable to get
     *
     * @return  mixed  The value of the variable
     *
     * @since   6.0.3
     */
    public function __get(string $name): mixed
    {
        return $this->getState($name);
    }

    /**
     * Magic setter for state properties
     *
     * @param   string  $name   The name of the variable
     * @param   mixed   $value  The value to set
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setState($name, $value);
    }

    /**
     * Magic isset for state properties
     *
     * @param   string  $name  The name of the variable
     *
     * @return  bool  True if set
     *
     * @since   6.0.3
     */
    public function __isset(string $name): bool
    {
        return $this->getState($name) !== null;
    }

    /**
     * Magic caller for state properties
     *
     * @param   string  $name       The name of the state variable to set
     * @param   array   $arguments  The value to set the state variable to
     *
     * @return  ProductHelper  Reference to self
     *
     * @since   6.0.3
     */
    public function __call(string $name, array $arguments): ProductHelper
    {
        $arg1 = array_shift($arguments);
        $this->setState($name, $arg1);

        return $this;
    }

    /**
     * Method to set model state variables
     *
     * @param   string  $property  The name of the property
     * @param   mixed   $value     The value of the property to set or null
     *
     * @return  mixed  The previous value of the property or null if not set
     *
     * @since   6.0.3
     */
    public function setState(string $property, mixed $value = null): mixed
    {
        $previous               = $this->state->$property ?? null;
        $this->state->$property = $value;

        return $previous;
    }

    /**
     * Method to get model state variables
     *
     * @param   string|null  $property  The name of the property
     * @param   mixed        $default   Default value
     *
     * @return  mixed  The value of the property or state object
     *
     * @since   6.0.3
     */
    public function getState(?string $property = null, mixed $default = null): mixed
    {
        return $property === null ? $this->state : ($this->state->$property ?? $default);
    }

    /**
     * Clear state
     *
     * @return  ProductHelper
     *
     * @since   6.0.3
     */
    public function clearState(): ProductHelper
    {
        $this->state = new \stdClass();

        return $this;
    }

    /**
     * Set product ID
     *
     * @param   int  $productId  Product ID
     *
     * @return  ProductHelper
     *
     * @since   6.0.3
     */
    public function setId(int $productId): ProductHelper
    {
        $this->setState('product_id', $productId);

        return $this;
    }

    /**
     * Get product ID
     *
     * @return  int|null
     *
     * @since   6.0.3
     */
    public function getId(): ?int
    {
        return $this->getState('product_id');
    }

    /**
     * Get product for the current product_id.
     *
     * Uses the product ID set via setId() to fetch the product.
     * This is the instance-based product loading method that was in the shop version.
     *
     * @return  object|null  The product object or null if not found.
     *
     * @since   6.0.3
     */
    public function getProduct(): ?object
    {
        return $this->loadProduct();
    }

    /**
     * Load product with caching.
     *
     * Private method that loads and caches product data for the current product_id.
     * Triggers the onJ2CommerceAfterGetProduct event for plugin integration.
     *
     * @return  object|null  The product object or null if not found.
     *
     * @since   6.0.3
     */
    private function loadProduct(): ?object
    {
        static $cache = [];

        $productId = $this->getState('product_id');

        if (!$productId) {
            return null;
        }

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        try {
            $app = Factory::getApplication();

            // Import catalog plugins for event handling
            if (class_exists(J2CommerceHelper::class) && method_exists(J2CommerceHelper::class, 'plugin')) {
                J2CommerceHelper::plugin()->importCatalogPlugins();
            }

            // Use the MVC factory to create and load the product table
            $component  = $app->bootComponent('com_j2commerce');
            $mvcFactory = $component->getMVCFactory();
            $product    = $mvcFactory->createTable('Product', 'Administrator');

            if ($product && $product->load($productId)) {
                // Trigger event for plugins to modify product data
                $app->triggerEvent('onJ2CommerceAfterGetProduct', [&$product]);
                $cache[$productId] = $product;
            } else {
                // Return empty table if not found
                $cache[$productId] = $mvcFactory->createTable('Product', 'Administrator');
            }
        } catch (\Exception $e) {
            // Fallback to static method if MVC factory is not available
            $cache[$productId] = self::getProductById($productId);
        }

        return $cache[$productId];
    }

    /**
     * Check if the current product exists and is enabled.
     *
     * Instance-based method using the product ID set via setId().
     *
     * @return  bool  True if product exists and is enabled.
     *
     * @since   6.0.3
     */
    public function exists(): bool
    {
        $product = $this->getProduct();

        return $product && !empty($product->enabled);
    }

    // =========================================================================
    // PROPERTY METHODS (JObject backward compatibility)
    // =========================================================================

    /**
     * Get property for backward compatibility with JObject
     *
     * @param   string  $property  Property name
     * @param   mixed   $default   Default value
     *
     * @return  mixed
     *
     * @since   6.0.3
     */
    public function get(string $property, mixed $default = null): mixed
    {
        return $this->properties[$property] ?? $default;
    }

    /**
     * Set property for backward compatibility with JObject
     *
     * @param   string  $property  Property name
     * @param   mixed   $value     Property value
     *
     * @return  mixed  Previous value
     *
     * @since   6.0.3
     */
    public function set(string $property, mixed $value = null): mixed
    {
        $previous                    = $this->properties[$property] ?? null;
        $this->properties[$property] = $value;

        return $previous;
    }

    /**
     * Set properties for backward compatibility with JObject
     *
     * @param   mixed  $properties  Properties array or object
     *
     * @return  bool
     *
     * @since   6.0.3
     */
    public function setProperties(mixed $properties): bool
    {
        if (\is_array($properties) || \is_object($properties)) {
            foreach ((array) $properties as $k => $v) {
                $this->set($k, $v);
            }

            return true;
        }

        return false;
    }

    /**
     * Get properties for backward compatibility with JObject
     *
     * @param   bool  $public  If true, returns only public properties
     *
     * @return  array
     *
     * @since   6.0.3
     */
    public function getProperties(bool $public = true): array
    {
        return $this->properties;
    }

    // =========================================================================
    // PRODUCT RETRIEVAL METHODS
    // =========================================================================

    /**
     * Get all variants for a product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  array  Array of variant objects.
     *
     * @since   6.0.3
     */
    public static function getVariants(int $productId): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        // Select all variant fields plus quantity data from productquantities table
        // Include weight/length class titles and units for display
        // Include variant_name from product_variant_optionvalues
        $query->select([
                'v.*',
                $db->quoteName('pq.j2commerce_productquantity_id'),
                $db->quoteName('pq.quantity'),
                $db->quoteName('pq.on_hold'),
                $db->quoteName('pq.sold', 'qty_sold'),
                $db->quoteName('w.weight_title'),
                $db->quoteName('w.weight_unit'),
                $db->quoteName('l.length_title'),
                $db->quoteName('l.length_unit'),
                $db->quoteName('pvo.product_optionvalue_ids', 'variant_name'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_productquantities', 'pq'),
                $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_weights', 'w'),
                $db->quoteName('v.weight_class_id') . ' = ' . $db->quoteName('w.j2commerce_weight_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_lengths', 'l'),
                $db->quoteName('v.length_class_id') . ' = ' . $db->quoteName('l.j2commerce_length_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_product_variant_optionvalues', 'pvo'),
                $db->quoteName('v.j2commerce_variant_id') . ' = ' . $db->quoteName('pvo.variant_id')
            )
            ->where($db->quoteName('v.product_id') . ' = :productId')
            ->order($db->quoteName('v.is_master') . ' DESC, ' . $db->quoteName('v.j2commerce_variant_id') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $variants = $db->loadObjectList() ?: [];

        // Preserve original CSV IDs for behavior processing, then convert to human-readable names
        foreach ($variants as $variant) {
            if (!empty($variant->variant_name)) {
                // Preserve original CSV IDs for Flexivariable behavior processing
                $variant->variant_name_ids = $variant->variant_name;
                // Convert to human-readable names for display
                $variant->variant_name = self::getVariantNamesByCSV($variant->variant_name);
            }
        }

        return $variants;
    }

    /**
     * Get a single product by ID (static version).
     *
     * @param   int  $productId  The product ID.
     *
     * @return  object|null  Product object or null if not found.
     *
     * @since   6.0.3
     */
    public static function getProductById(int $productId): ?object
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('j2commerce_product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $product = $db->loadObject();

        $cache[$productId] = $product ?: null;

        return $cache[$productId];
    }

    /**
     * Check if a product exists and is enabled (static version).
     *
     * @param   int  $productId  The product ID.
     *
     * @return  bool  True if product exists and is enabled.
     *
     * @since   6.0.3
     */
    public static function productExists(int $productId): bool
    {
        $product = self::getProductById($productId);

        return $product && !empty($product->enabled);
    }

    // =========================================================================
    // FULL PRODUCT METHODS
    // =========================================================================

    /**
     * Get a full product object with all related data.
     *
     * Returns a product object including:
     * - Base product fields
     * - manufacturer (company name)
     * - product_name, product_short_desc, product_long_desc (from article)
     * - source (article object)
     * - variants (array)
     * - product_options (array)
     * - main_image, thumb_image, additional_images
     * - product_edit_url, product_view_url
     *
     * @param   int   $productId      The product ID
     * @param   bool  $loadVariants   Whether to load variants (default: true)
     * @param   bool  $loadOptions    Whether to load options (default: true)
     *
     * @return  object|null  Full product object or null
     *
     * @since   6.0.8
     */
    public static function getFullProduct(
        int $productId,
        bool $loadVariants = true,
        bool $loadOptions = true
    ): ?object {
        // Load base product
        $product = self::getProductById($productId);

        if (!$product) {
            return null;
        }

        // Product exists flag
        $product->exists = 1;

        // Convert params JSON string to Registry object for $product->params->get() access
        $product->params = new Registry($product->params ?? '{}');

        // Add product images (includes j2commerce_productimage_id, brand_desc_id)
        $images                              = self::getProductImages($productId);
        $product->j2commerce_productimage_id = $images->j2commerce_productimage_id ?? 0;
        $product->main_image                 = $images->main_image ?? '';
        $product->main_image_alt             = $images->main_image_alt ?? '';
        $product->thumb_image                = $images->thumb_image ?? '';
        $product->thumb_image_alt            = $images->thumb_image_alt ?? '';
        $product->additional_images          = $images->additional_images ?? '';
        $product->additional_images_alt      = $images->additional_images_alt ?? '';
        $product->brand_desc_id              = $images->brand_desc_id ?? 0;

        // Add manufacturer data (company name + first/last name from address)
        $manufacturerData                 = self::getManufacturerData((int) ($product->manufacturer_id ?? 0));
        $product->manufacturer            = $manufacturerData['company'] ?? '';
        $product->manufacturer_first_name = $manufacturerData['first_name'] ?? null;
        $product->manufacturer_last_name  = $manufacturerData['last_name'] ?? null;

        // Add article data (for com_content source products)
        $articleData = self::getArticleData(
            $product->product_source ?? '',
            (int) ($product->product_source_id ?? 0)
        );

        $product->source             = $articleData;
        $product->product_name       = $articleData->title ?? '';
        $product->product_short_desc = $articleData->introtext ?? '';
        $product->product_long_desc  = $articleData->fulltext ?? '';

        // Expose catid and alias at top level for routing
        // Required by RouteHelper::getProductRoute() for canonical URLs
        $product->catid = $articleData->catid ?? null;
        $product->alias = $articleData->alias ?? null;

        // Add URLs
        $product->product_edit_url = '';
        $product->product_view_url = '';

        if ($articleData && !empty($articleData->id)) {
            $return                    = base64_encode(Uri::getInstance()->toString());
            $product->product_edit_url = 'index.php?option=com_content&task=article.edit&id=' . (int) $articleData->id . '&return=' . $return;

            // Build frontend view URL using J2Commerce RouteHelper for proper SEF routing
            // This ensures URL priority: single product menu > category menu > categories menu
            $product->product_view_url = RouteHelper::getProductRoute(
                $productId,
                $articleData->alias ?? null,
                !empty($articleData->catid) ? (int) $articleData->catid : null
            );
        }

        // Add master variant (single object) and all variants (array)
        $allVariants   = [];
        $masterVariant = null;

        if ($loadVariants) {
            $allVariants = self::getVariants($productId);

            // Find master variant (is_master = 1) or first variant
            foreach ($allVariants as $v) {
                if (!empty($v->is_master)) {
                    $masterVariant = $v;
                    break;
                }
            }

            // Fallback to first variant if no master found
            if (!$masterVariant && !empty($allVariants)) {
                $masterVariant = reset($allVariants);
            }
        } else {
            // Always load the master variant (lightweight) for stock checks and pricing
            $masterVariant = self::getMasterVariant($productId);
        }

        $product->variant  = $masterVariant;
        $product->variants = $allVariants;

        // Add variant pagination (count non-master variants for display)
        $variantCount = 0;
        foreach ($allVariants as $v) {
            if (empty($v->is_master)) {
                $variantCount++;
            }
        }
        $product->variant_pagination = new \Joomla\CMS\Pagination\Pagination($variantCount, 0, 20);

        // Add pricing (based on master variant and default quantity of 1)
        // Default display quantity used for pricing calculations
        $product->pricing  = null;
        $product->quantity = 1;

        if ($masterVariant) {
            $pricing          = (new self())->getPrice($masterVariant, 1);
            $product->pricing = $pricing !== false ? $pricing : null;
        }

        // Add product options (raw objects for admin use)
        if ($loadOptions) {
            $product->product_options = self::getTraits((int) $productId);
        } else {
            $product->product_options = [];
        }

        // Add processed options array (for frontend price calculations)
        $product->options = self::getProductOptions($product);

        // Add lengths and weights arrays
        $product->lengths = self::getLengthUnits();
        $product->weights = self::getWeightUnits();

        // App detail (null for now, can be extended)
        $product->app_detail = null;

        // Product filter pagination (for product filter listings)
        $filterCount                       = self::getProductFilterCount($productId);
        $product->productfilter_pagination = new \Joomla\CMS\Pagination\Pagination($filterCount, 0, 10);

        // Populate productfilter_ids from junction table (not from deprecated products column)
        $product->productfilter_ids = implode(',', self::getProductFilterIds($productId));

        // Populate full product filters grouped by filter group (for frontend display)
        $product->productfilters = self::getProductFilters([$productId]);

        // Add cart form action URL (for add to cart forms)
        $product->cart_form_action = J2CommerceHelper::platform()->getCartUrl(['task' => 'addItem']);

        // Add checkout link URL — respects addtocart_checkout_link component parameter
        $product->checkout_link = self::getCheckoutLinkUrl();

        // Apply product-type-specific behavior enhancements
        // Each behavior loads its own variants via VariantsModel independently,
        // so this must always run — behaviors like Flexivariable need it for
        // option deduplication, default variant, pricing, and variant_json.
        self::applyBehaviorEnhancements($product);

        return $product;
    }

    /**
     * Apply product-type-specific behavior enhancements to a product.
     *
     * This method calls the appropriate behavior's onAfterGetProduct method
     * to enhance the product with type-specific data like:
     * - Flexivariable: variant_name, variant_json, min_price, max_price, filtered options
     * - Simple: basic product enhancements
     * - Downloadable: download file information
     *
     * @param   object  $product  The product object to enhance (modified by reference)
     *
     * @return  void
     *
     * @since   6.0.8
     */
    private static function applyBehaviorEnhancements(object $product): void
    {
        // Get the product type
        $productType = $product->product_type ?? 'simple';

        // Import catalog plugins for event handling (some behaviors trigger events)
        J2CommerceHelper::plugin()->importCatalogPlugins();

        // Use ProductService to get the appropriate behavior
        try {
            $productService = new \J2Commerce\Component\J2commerce\Administrator\Service\ProductService();
            $behavior       = $productService->getBehavior($productType);

            // Check if the behavior has the onAfterGetProduct method
            if (method_exists($behavior, 'onAfterGetProduct')) {
                // Create a PluginEvent with the expected arguments
                $event = new \J2Commerce\Component\J2commerce\Administrator\Event\PluginEvent(
                    'onJ2CommerceAfterGetProduct',
                    [
                        'product' => $product,
                        'subject' => null,  // No model available in static context
                    ]
                );

                // Call the behavior's enhancement method
                $behavior->onAfterGetProduct($event);
            }
        } catch (\Exception $e) {
            // Log error but don't fail - base product data is still valid
            Factory::getApplication()->enqueueMessage(
                'Behavior enhancement failed: ' . $e->getMessage(),
                'warning'
            );
        }
    }

    /**
     * Get full product by source (e.g., com_content article).
     *
     * @param   string  $source    Product source (e.g., 'com_content')
     * @param   int     $sourceId  Source ID (e.g., article ID)
     * @param   bool    $loadVariants   Whether to load variants (default: true)
     * @param   bool    $loadOptions    Whether to load options (default: true)
     *
     * @return  object|null  Full product object or null
     *
     * @since   6.0.8
     */
    public static function getFullProductBySource(
        string $source,
        int $sourceId,
        bool $loadVariants = true,
        bool $loadOptions = true
    ): ?object {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('j2commerce_product_id'))
            ->from($db->quoteName('#__j2commerce_products'))
            ->where($db->quoteName('product_source') . ' = :source')
            ->where($db->quoteName('product_source_id') . ' = :sourceId')
            ->where($db->quoteName('enabled') . ' = 1')
            ->bind(':source', $source)
            ->bind(':sourceId', $sourceId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productId = (int) $db->loadResult();

        if (!$productId) {
            return null;
        }

        return self::getFullProduct($productId, $loadVariants, $loadOptions);
    }

    /**
     * Get product images.
     *
     * @param   int  $productId  The product ID
     *
     * @return  object|null  Product images object or null
     *
     * @since   6.0.8
     */
    public static function getProductImages(int $productId): ?object
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_productimages'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $images = $db->loadObject();

        $cache[$productId] = $images ?: null;

        return $cache[$productId];
    }

    /**
     * Get the count of product filters for a product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  int  Number of filters assigned to the product.
     *
     * @since   6.0.8
     */
    public static function getProductFilterCount(int $productId): int
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $count = (int) $db->loadResult();

        $cache[$productId] = $count;

        return $count;
    }

    /**
     * Get product filter IDs for a product from the junction table.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  array  Array of filter IDs assigned to the product.
     *
     * @since   6.0.8
     */
    public static function getProductFilterIds(int $productId): array
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('filter_id'))
            ->from($db->quoteName('#__j2commerce_product_filters'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $filterIds = $db->loadColumn();

        $cache[$productId] = $filterIds ?: [];

        return $cache[$productId];
    }

    /**
     * Get article data for a product source.
     *
     * @param   string  $source    Product source (e.g., 'com_content')
     * @param   int     $sourceId  Source ID (e.g., article ID)
     *
     * @return  object|null  Article object or null
     *
     * @since   6.0.8
     */
    public static function getArticleData(string $source, int $sourceId): ?object
    {
        if (empty($source) || $sourceId <= 0) {
            return null;
        }

        // Only support com_content for now
        if ($source !== 'com_content') {
            return null;
        }

        static $cache = [];
        $cacheKey     = $source . ':' . $sourceId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        // Select all article fields for the product source object
        $query->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.asset_id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.alias'),
                $db->quoteName('a.introtext'),
                $db->quoteName('a.fulltext'),
                $db->quoteName('a.state'),
                $db->quoteName('a.catid'),
                $db->quoteName('a.created'),
                $db->quoteName('a.created_by'),
                $db->quoteName('a.created_by_alias'),
                $db->quoteName('a.modified'),
                $db->quoteName('a.modified_by'),
                $db->quoteName('a.checked_out'),
                $db->quoteName('a.checked_out_time'),
                $db->quoteName('a.publish_up'),
                $db->quoteName('a.publish_down'),
                $db->quoteName('a.images'),
                $db->quoteName('a.urls'),
                $db->quoteName('a.attribs'),
                $db->quoteName('a.version'),
                $db->quoteName('a.ordering'),
                $db->quoteName('a.metakey'),
                $db->quoteName('a.metadesc'),
                $db->quoteName('a.access'),
                $db->quoteName('a.hits'),
                $db->quoteName('a.metadata'),
                $db->quoteName('a.featured'),
                $db->quoteName('a.language'),
                $db->quoteName('a.note'),
                $db->quoteName('c.title', 'category_title'),
                $db->quoteName('c.alias', 'category_alias'),
                $db->quoteName('c.path', 'category_path'),
            ])
            ->from($db->quoteName('#__content', 'a'))
            ->leftJoin(
                $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
            )
            ->where($db->quoteName('a.id') . ' = :articleId')
            ->bind(':articleId', $sourceId, ParameterType::INTEGER);

        $db->setQuery($query);
        $article = $db->loadObject();

        $cache[$cacheKey] = $article ?: null;

        return $cache[$cacheKey];
    }

    /**
     * Get manufacturer company name.
     *
     * @param   int  $manufacturerId  The manufacturer ID
     *
     * @return  string  Company name or empty string
     *
     * @since   6.0.8
     */
    public static function getManufacturerName(int $manufacturerId): string
    {
        if ($manufacturerId <= 0) {
            return '';
        }

        static $cache = [];

        if (isset($cache[$manufacturerId])) {
            return $cache[$manufacturerId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('a.company'))
            ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_addresses', 'a') . ' ON '
                . $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id')
            )
            ->where($db->quoteName('m.j2commerce_manufacturer_id') . ' = :mfgId')
            ->bind(':mfgId', $manufacturerId, ParameterType::INTEGER);

        $db->setQuery($query);
        $company = $db->loadResult();

        $cache[$manufacturerId] = $company ?: '';

        return $cache[$manufacturerId];
    }

    /**
     * Get full manufacturer data including company and name fields.
     *
     * @param   int  $manufacturerId  The manufacturer ID
     *
     * @return  array  Array with company, first_name, last_name keys
     *
     * @since   6.0.8
     */
    public static function getManufacturerData(int $manufacturerId): array
    {
        $default = [
            'company'    => '',
            'first_name' => null,
            'last_name'  => null,
        ];

        if ($manufacturerId <= 0) {
            return $default;
        }

        static $cache = [];

        if (isset($cache[$manufacturerId])) {
            return $cache[$manufacturerId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('a.company'),
                $db->quoteName('a.first_name'),
                $db->quoteName('a.last_name'),
            ])
            ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_addresses', 'a') . ' ON '
                . $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id')
            )
            ->where($db->quoteName('m.j2commerce_manufacturer_id') . ' = :mfgId')
            ->bind(':mfgId', $manufacturerId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result) {
            $cache[$manufacturerId] = [
                'company'    => $result->company ?? '',
                'first_name' => $result->first_name ?? null,
                'last_name'  => $result->last_name ?? null,
            ];
        } else {
            $cache[$manufacturerId] = $default;
        }

        return $cache[$manufacturerId];
    }

    /**
     * Get all length units as associative array [id => title].
     *
     * Returns format expected by form templates: [j2commerce_length_id => length_title]
     *
     * @return  array  Associative array of length units [id => title]
     *
     * @since   6.0.8
     */
    public static function getLengthUnits(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('j2commerce_length_id'),
                $db->quoteName('length_title'),
            ])
            ->from($db->quoteName('#__j2commerce_lengths'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('length_title') . ' ASC');

        $db->setQuery($query);
        $results = $db->loadObjectList() ?: [];

        // Convert to [id => title] format for template compatibility
        $cache = [];
        foreach ($results as $row) {
            $cache[$row->j2commerce_length_id] = $row->length_title;
        }

        return $cache;
    }

    /**
     * Get all weight units as associative array [id => title].
     *
     * Returns format expected by form templates: [j2commerce_weight_id => weight_title]
     *
     * @return  array  Associative array of weight units [id => title]
     *
     * @since   6.0.8
     */
    public static function getWeightUnits(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('j2commerce_weight_id'),
                $db->quoteName('weight_title'),
            ])
            ->from($db->quoteName('#__j2commerce_weights'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('weight_title') . ' ASC');

        $db->setQuery($query);
        $results = $db->loadObjectList() ?: [];

        // Convert to [id => title] format for template compatibility
        $cache = [];
        foreach ($results as $row) {
            $cache[$row->j2commerce_weight_id] = $row->weight_title;
        }

        return $cache;
    }

    // =========================================================================
    // VARIANT METHODS
    // =========================================================================

    /**
     * Get master variant for a product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  object|null  Master variant object or null.
     *
     * @since   6.0.3
     */
    public static function getMasterVariant(int $productId): ?object
    {
        static $cache = [];

        if (\array_key_exists($productId, $cache)) {
            return $cache[$productId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                'v.*',
                $db->quoteName('pq.j2commerce_productquantity_id'),
                $db->quoteName('pq.quantity'),
                $db->quoteName('pq.on_hold'),
                $db->quoteName('pq.sold', 'qty_sold'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_productquantities', 'pq'),
                $db->quoteName('pq.variant_id') . ' = ' . $db->quoteName('v.j2commerce_variant_id')
            )
            ->where($db->quoteName('v.product_id') . ' = :productId')
            ->where($db->quoteName('v.is_master') . ' = 1')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $cache[$productId] = $db->loadObject() ?: null;

        return $cache[$productId];
    }

    /**
     * Get the default variant from an array of variants.
     *
     * @param   array  $variants  Array of variant objects.
     *
     * @return  object|null  Default variant object or first variant.
     *
     * @since   6.0.3
     */
    public static function getDefaultVariant(array $variants): ?object
    {
        if (empty($variants)) {
            return null;
        }

        $defaultVariant = reset($variants);

        foreach ($variants as $variant) {
            if (!empty($variant->isdefault_variant) && $variant->isdefault_variant == 1) {
                $defaultVariant = $variant;
                break;
            }
        }

        return $defaultVariant;
    }

    /**
     * Get variant by selected options.
     *
     * @param   array  $productOptions  Associative array of productoption_id => optionvalue_id.
     * @param   int    $productId       The product ID.
     *
     * @return  object|null  Variant object or null if not found.
     *
     * @since   6.0.3
     */
    public static function getVariantByOptions(array $productOptions, int $productId): ?object
    {
        if (empty($productOptions)) {
            return null;
        }

        $db = self::getDatabase();

        // Build CSV of option values sorted numerically
        $optionValues = [];

        foreach ($productOptions as $optionValue) {
            $optionValues[] = (int) $optionValue;
        }

        sort($optionValues);
        $values = implode(',', $optionValues);

        $query = $db->getQuery(true)
            ->select($db->quoteName('variant_id'))
            ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
            ->where($db->quoteName('product_optionvalue_ids') . ' = :values')
            ->bind(':values', $values);

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        if (empty($rows)) {
            return null;
        }

        // Load and return the first matching variant
        foreach ($rows as $row) {
            $variantQuery = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__j2commerce_variants'))
                ->where($db->quoteName('j2commerce_variant_id') . ' = :variantId')
                ->bind(':variantId', $row->variant_id, ParameterType::INTEGER);

            $db->setQuery($variantQuery);
            $variant = $db->loadObject();

            if ($variant && $variant->j2commerce_variant_id == $row->variant_id) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Generate SKU for a variant.
     *
     * @param   object  $variant  The variant object.
     * @param   object  $product  The product object.
     *
     * @return  string  Generated SKU.
     *
     * @since   6.0.3
     */
    public static function generateSKU(object $variant, object $product): string
    {
        if (empty($variant->product_id)) {
            return '';
        }

        $sku = '';

        // Get product name (prefer product_name, fall back to product_source_id)
        $productName = $product->product_name ?? $product->product_source_id ?? '';

        // If product name has no valid characters, generate based on product ID
        $test = preg_replace('#[^a-z0-9_-]#i', '', (string) $productName);

        if (empty($test)) {
            static $lastPid = null;

            if ($lastPid === null) {
                $db    = self::getDatabase();
                $query = $db->getQuery(true)
                    ->select('MAX(' . $db->quoteName('j2commerce_product_id') . ')')
                    ->from($db->quoteName('#__j2commerce_products'));
                $db->setQuery($query);
                $lastPid = (int) $db->loadResult();
            }

            $lastPid++;
            $sku = 'product_' . $lastPid;
        } else {
            $sku = preg_replace('#[^a-z0-9_-]#i', '_', (string) $productName);
        }

        // For variable products, append variant ID for non-master variants
        $variableTypes = self::getVariableProductTypes();

        if (\in_array($product->product_type ?? '', $variableTypes)) {
            if (empty($variant->is_master) || $variant->is_master == 0) {
                $sku = $sku . '_' . ($variant->j2commerce_variant_id ?? '');
            } else {
                // Master variant of variable type doesn't need SKU
                $sku = '';
            }
        }

        // Allow plugins to modify the SKU
        if (class_exists(J2CommerceHelper::class) && method_exists(J2CommerceHelper::class, 'plugin')) {
            J2CommerceHelper::plugin()->event('CheckSku', [&$sku]);
        }

        return $sku;
    }

    /**
     * Get variant option value names from CSV.
     *
     * @param   string  $csv  Comma-separated product option value IDs.
     *
     * @return  string  Comma-separated option value names.
     *
     * @since   6.0.3
     */
    public static function getVariantNamesByCSV(string $csv): string
    {
        if (empty($csv)) {
            return '';
        }

        $productOptionValues = explode(',', $csv);
        $names               = [];

        foreach ($productOptionValues as $productOptionValueId) {
            $optionValueName = self::getOptionValueName((int) $productOptionValueId);

            if (empty($optionValueName)) {
                $optionValueName = Text::_('COM_J2COMMERCE_ALL_OPTIONVALUE');
            }

            $names[] = $optionValueName;
        }

        return implode(',', $names);
    }

    /**
     * Get option value name by ID.
     *
     * @param   int  $productOptionValueId  The product option value ID.
     *
     * @return  string  Option value name or empty string.
     *
     * @since   6.0.3
     */
    public static function getOptionValueName(int $productOptionValueId): string
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('ov.optionvalue_name'))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' .
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :povId')
            ->bind(':povId', $productOptionValueId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadResult() ?: '';
    }

    // =========================================================================
    // PRODUCT OPTIONS METHODS
    // =========================================================================

    /**
     * Get product options (traits) for a configurable product.
     *
     * @param   int  $productId  The product ID.
     *
     * @return  array  Array of option groups.
     *
     * @since   6.0.3
     */
    public static function getTraits(int $productId): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'po.j2commerce_productoption_id',
                'po.option_id',
                'po.parent_id',
                'po.ordering',
                'po.required',
                'po.is_variant',
            ]))
            ->select($db->quoteName([
                'o.option_name',
                'o.option_unique_name',
                'o.type',
                'o.option_params',
            ]))
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_options', 'o') . ' ON ' .
                $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id')
            )
            ->where($db->quoteName('po.product_id') . ' = :productId')
            ->order($db->quoteName('po.ordering') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $options = $db->loadObjectList() ?: [];

        // For each option, get its values
        foreach ($options as $option) {
            // Load BASE option values from #__j2commerce_optionvalues (Small, Medium, Large)
            // This is what the flexivariable admin template expects for the dropdown
            $option->option_values = self::getBaseOptionValues((int) $option->option_id);

            // Also load product-specific option values from #__j2commerce_product_optionvalues
            // These contain price/weight overrides for this specific product
            $option->product_optionvalues = self::getProductOptionValues((int) $option->j2commerce_productoption_id);

            // Keep 'values' alias for backwards compatibility
            $option->values = $option->product_optionvalues;
        }

        return $options;
    }

    /**
     * Get base option values by option ID.
     *
     * Returns values from #__j2commerce_optionvalues (e.g., Small, Medium, Large)
     * for use in admin dropdowns when selecting variants.
     *
     * @param   int  $optionId  The option ID.
     *
     * @return  array  Array of option value objects.
     *
     * @since   6.0.8
     */
    public static function getBaseOptionValues(int $optionId): array
    {
        static $cache = [];

        if (isset($cache[$optionId])) {
            return $cache[$optionId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'j2commerce_optionvalue_id',
                'option_id',
                'optionvalue_name',
                'optionvalue_image',
                'ordering',
            ]))
            ->from($db->quoteName('#__j2commerce_optionvalues'))
            ->where($db->quoteName('option_id') . ' = :optionId')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':optionId', $optionId, ParameterType::INTEGER);

        $db->setQuery($query);

        $cache[$optionId] = $db->loadObjectList() ?: [];

        return $cache[$optionId];
    }

    /**
     * Get product options with full data for display.
     *
     * @param   object  $product  Product object with product_options property.
     *
     * @return  array  Processed product options data.
     *
     * @since   6.0.3
     */
    public static function getProductOptions(object $product): array
    {
        static $cache = [];

        $productId = $product->j2commerce_product_id ?? 0;

        if (!$productId) {
            return [];
        }

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $productOptionData = [];

        // Get product options from database
        $options = self::getTraits($productId);

        foreach ($options as $productOption) {
            $type = $productOption->type ?? '';

            // If multiple choices available
            if (\in_array($type, ['select', 'radio', 'checkbox', 'color'])) {
                $productOptionValueData = [];
                $productOptionValues    = self::getProductOptionValues((int) $productOption->j2commerce_productoption_id);

                foreach ($productOptionValues as $productOptionValue) {
                    $productOptionValueData[] = [
                        'product_optionvalue_id'            => $productOptionValue->j2commerce_product_optionvalue_id ?? '',
                        'optionvalue_id'                    => $productOptionValue->optionvalue_id ?? '',
                        'optionvalue_name'                  => $productOptionValue->optionvalue_name ?? '',
                        'product_optionvalue_price'         => $productOptionValue->product_optionvalue_price ?? 0,
                        'product_optionvalue_prefix'        => $productOptionValue->product_optionvalue_prefix ?? '+',
                        'product_optionvalue_weight'        => $productOptionValue->product_optionvalue_weight ?? 0,
                        'product_optionvalue_sku'           => $productOptionValue->product_optionvalue_sku ?? '',
                        'product_optionvalue_weight_prefix' => $productOptionValue->product_optionvalue_weight_prefix ?? '+',
                        'product_optionvalue_default'       => $productOptionValue->product_optionvalue_default ?? 0,
                        'optionvalue_image'                 => $productOptionValue->optionvalue_image ?? '',
                        'product_optionvalue_attribs'       => $productOptionValue->product_optionvalue_attribs ?? '',
                    ];
                }

                $productOptionData[] = [
                    'productoption_id'   => $productOption->j2commerce_productoption_id,
                    'option_id'          => $productOption->option_id,
                    'parent_id'          => (int) ($productOption->parent_id ?? 0),
                    'option_name'        => $productOption->option_name ?? '',
                    'option_unique_name' => $productOption->option_unique_name ?? '',
                    'type'               => $type,
                    'optionvalue'        => $productOptionValueData,
                    'required'           => $productOption->required ?? 0,
                    'option_params'      => $productOption->option_params ?? '',
                    'is_variant'         => $productOption->is_variant ?? 0,
                ];
            } else {
                // Text, textarea, date, datetime, time, file options
                $productOptionData[] = [
                    'productoption_id'   => $productOption->j2commerce_productoption_id,
                    'option_id'          => $productOption->option_id,
                    'parent_id'          => (int) ($productOption->parent_id ?? 0),
                    'option_name'        => $productOption->option_name ?? '',
                    'option_unique_name' => $productOption->option_unique_name ?? '',
                    'type'               => $type,
                    'optionvalue'        => '',
                    'required'           => $productOption->required ?? 0,
                    'option_params'      => $productOption->option_params ?? '',
                    'is_variant'         => $productOption->is_variant ?? 0,
                ];
            }
        }

        $cache[$productId] = $productOptionData;

        return $productOptionData;
    }

    /** Summarise all options for list views, e.g. "3 Sizes, 2 Colors" */
    public static function getOptionsSummary(array $options): string
    {
        $parts = [];

        foreach ($options as $option) {
            if (!\in_array($option['type'], ['select', 'radio', 'color', 'checkbox'], true)) {
                continue;
            }

            $count = \is_array($option['optionvalue']) ? \count($option['optionvalue']) : 0;
            if ($count > 0) {
                $optionName = Text::_($option['option_name']);
                $parts[]    = Text::plural('COM_J2COMMERCE_N_OPTION_VALUES', $count, $optionName);
            }
        }

        return !empty($parts) ? implode(', ', $parts) : Text::_('COM_J2COMMERCE_OPTIONS');
    }

    /**
     * Get option values for a product option.
     *
     * @param   int  $productOptionId  The product option ID.
     *
     * @return  array  Array of option value objects.
     *
     * @since   6.0.3
     */
    public static function getProductOptionValues(int $productOptionId): array
    {
        static $cache = [];

        if (isset($cache[$productOptionId])) {
            return $cache[$productOptionId];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'pov.j2commerce_product_optionvalue_id',
                'pov.productoption_id',
                'pov.optionvalue_id',
                'pov.product_optionvalue_price',
                'pov.product_optionvalue_prefix',
                'pov.product_optionvalue_weight',
                'pov.product_optionvalue_weight_prefix',
                'pov.product_optionvalue_sku',
                'pov.product_optionvalue_default',
                'pov.ordering',
                'pov.product_optionvalue_attribs',
            ]))
            ->select($db->quoteName([
                'ov.optionvalue_name',
                'ov.optionvalue_image',
            ]))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' .
                $db->quoteName('ov.j2commerce_optionvalue_id') . ' = ' . $db->quoteName('pov.optionvalue_id')
            )
            ->where($db->quoteName('pov.productoption_id') . ' = :productOptionId')
            ->order($db->quoteName('pov.ordering') . ' ASC')
            ->bind(':productOptionId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);

        $cache[$productOptionId] = $db->loadObjectList() ?: [];

        return $cache[$productOptionId];
    }

    /**
     * Get default/selected product options.
     *
     * @param   array  $options  Processed option data from getProductOptions().
     *
     * @return  array  Associative array of productoption_id => product_optionvalue_id.
     *
     * @since   6.0.3
     */
    public static function getDefaultProductOptions(array $options): array
    {
        $default = [];

        foreach ($options as $option) {
            $type = $option['type'] ?? '';

            if (\in_array($type, ['select', 'radio', 'checkbox', 'color'])) {
                foreach ($option['optionvalue'] as $optionValue) {
                    if (!empty($optionValue['product_optionvalue_default']) && $optionValue['product_optionvalue_default'] == 1) {
                        $default[$option['productoption_id']] = $optionValue['product_optionvalue_id'];
                    }
                }
            }
        }

        return $default;
    }

    /**
     * Get cart product options by product option ID and product ID.
     *
     * @param   int  $productOptionId  The product option ID.
     * @param   int  $productId        The product ID.
     *
     * @return  object|null  Product option object or null.
     *
     * @since   6.0.3
     */
    public static function getCartProductOptions(int $productOptionId, int $productId): ?object
    {
        static $cache = [];

        $cacheKey = $productOptionId . '_' . $productId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('po', null, 'po') . '.*')
            ->select($db->quoteName(['o.option_name', 'o.type']))
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_options', 'o') . ' ON ' .
                $db->quoteName('po.option_id') . ' = ' . $db->quoteName('o.j2commerce_option_id')
            )
            ->where($db->quoteName('po.j2commerce_productoption_id') . ' = :poId')
            ->where($db->quoteName('po.product_id') . ' = :productId')
            ->order($db->quoteName('o.ordering') . ' ASC')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER)
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);
        $cache[$cacheKey] = $db->loadObject();

        return $cache[$cacheKey];
    }

    /**
     * Get cart product option values.
     *
     * @param   int        $productOptionId  The product option ID.
     * @param   int|array  $optionValue      The option value ID or array of IDs.
     *
     * @return  object|null  Product option value object or null.
     *
     * @since   6.0.3
     */
    public static function getCartProductOptionValues(int $productOptionId, int|array $optionValue): ?object
    {
        static $cache = [];

        if (empty($optionValue)) {
            return null;
        }

        // Sanitize input
        if (\is_array($optionValue)) {
            $optionValue = array_map('intval', $optionValue);
            $optionValue = implode(',', $optionValue);
        } else {
            $optionValue = (int) $optionValue;
        }

        $cacheKey = $productOptionId . '_' . $optionValue;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('pov', null, 'pov') . '.*')
            ->select($db->quoteName(['ov.j2commerce_optionvalue_id', 'ov.optionvalue_name']))
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' .
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :ovId')
            ->where($db->quoteName('pov.productoption_id') . ' = :poId')
            ->bind(':ovId', $optionValue, ParameterType::INTEGER)
            ->bind(':poId', $productOptionId, ParameterType::INTEGER);

        $db->setQuery($query);
        $cache[$cacheKey] = $db->loadObject();

        return $cache[$cacheKey];
    }

    // =========================================================================
    // COMBINATIONS AND VALIDATION METHODS
    // =========================================================================

    /**
     * Generate all possible combinations from option arrays (Cartesian product).
     *
     * @param   array  $traits  Array of option arrays.
     *
     * @return  array  Array of all combinations.
     *
     * @since   6.0.3
     */
    public static function getCombinations(array $traits): array
    {
        if (empty($traits)) {
            return [];
        }

        // Base case: one trait left
        if (\count($traits) === 1) {
            $result = [];

            foreach (reset($traits) as $value) {
                $result[] = [$value];
            }

            return $result;
        }

        // Recursive case: get combinations of remaining traits
        $first                 = array_shift($traits);
        $remainingCombinations = self::getCombinations($traits);
        $result                = [];

        foreach ($first as $value) {
            foreach ($remainingCombinations as $combination) {
                $result[] = array_merge([$value], $combination);
            }
        }

        return $result;
    }

    /**
     * Validate variants against options.
     *
     * @param   array  $variants  Array of variant objects.
     * @param   array  $options   Processed options data.
     *
     * @return  bool  True if variants match all option combinations.
     *
     * @since   6.0.3
     */
    public static function validateVariants(array $variants, array $options): bool
    {
        $traits = [];

        foreach ($options as $option) {
            if (!empty($option['optionvalue'])) {
                $attributes = [];

                foreach ($option['optionvalue'] as $pov) {
                    $attributes[] = $pov['product_optionvalue_id'];
                }

                $traits[] = $attributes;
            }
        }

        $csvArray = self::getCombinations($traits);

        return \count($variants) === \count($csvArray);
    }

    /**
     * Validate flexible variants.
     *
     * @param   array  $variants  Array of variant objects.
     * @param   array  $options   Processed options data.
     *
     * @return  bool  True if all variants have valid names.
     *
     * @since   6.0.3
     */
    public static function validateFlexivariants(array $variants, array $options): bool
    {
        $traits = [];

        foreach ($options as $option) {
            if (!empty($option['optionvalue'])) {
                $attributes = [];

                foreach ($option['optionvalue'] as $pov) {
                    $attributes[] = $pov['product_optionvalue_id'];
                }

                $traits[] = $attributes;
            }
        }

        // getCombinations() returns array of arrays — convert to CSV strings for comparison
        $csvArray = array_map(fn (array $combo) => implode(',', $combo), self::getCombinations($traits));

        foreach ($variants as $variant) {
            // Use variant_name_ids (original CSV) if available, fall back to variant_name
            $variantCsv = $variant->variant_name_ids ?? $variant->variant_name ?? '';
            if (!\in_array($variantCsv, $csvArray)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // PRICING METHODS
    // =========================================================================

    /**
     * Get price modifiers.
     *
     * @return  array  Associative array of price modifiers.
     *
     * @since   6.0.3
     */
    public static function getPriceModifiers(): array
    {
        return [
            '+' => '+',
            '-' => '-',
        ];
    }

    /**
     * Get pricing calculators.
     *
     * @return  array  Associative array of pricing calculator types.
     *
     * @since   6.0.3
     */
    public static function getPricingCalculators(): array
    {
        $calculators = ['standard' => Text::_('COM_J2COMMERCE_PRODUCT_PRICING_CALCULATOR_STANDARD')];

        $event = J2CommerceHelper::plugin()->event('GetPricingCalculators', ['calculators' => $calculators]);

        // Collect all results from plugins
        $results = $event->getEventResult();

        if (!empty($results) && \is_array($results)) {
            // Filter out non-array items, then merge all at once
            $validResults = array_filter($results, 'is_array');
            if (!empty($validResults)) {
                $calculators = array_merge($calculators, ...$validResults);
            }
        }

        return (array) $calculators;
    }
    /* public static function getPricingCalculators(): array
     {
         return [
             'standard' => Text::_('COM_J2COMMERCE_PRODUCT_PRICING_CALCULATOR_STANDARD'),
         ];
     }*/

    /**
     * Calculate option price and weight adjustments.
     *
     * @param   array  $options    Selected options (productoption_id => option_value).
     * @param   int    $productId  The product ID.
     *
     * @return  array  Array with keys: option_price, option_weight, option_data.
     *
     * @since   6.0.3
     */
    public static function getOptionPrice(array $options, int $productId): array
    {
        $optionPrice  = 0.0;
        $optionWeight = 0.0;
        $optionData   = [];

        foreach ($options as $productOptionId => $optionValue) {
            $productOption = self::getCartProductOptions((int) $productOptionId, $productId);

            if (!$productOption) {
                continue;
            }

            $type = $productOption->type ?? '';

            if (\in_array($type, ['select', 'radio', 'color'])) {
                $productOptionValue = self::getCartProductOptionValues((int) $productOptionId, (int) $optionValue);

                if ($productOptionValue) {
                    // Calculate option price
                    $prefix = $productOptionValue->product_optionvalue_prefix ?? '+';
                    $price  = (float) ($productOptionValue->product_optionvalue_price ?? 0);

                    if ($prefix === '+') {
                        $optionPrice += $price;
                    } elseif ($prefix === '-') {
                        $optionPrice -= $price;
                    }

                    // Calculate option weight
                    $weightPrefix = $productOptionValue->product_optionvalue_weight_prefix ?? '+';
                    $weight       = (float) ($productOptionValue->product_optionvalue_weight ?? 0);

                    if ($weightPrefix === '+') {
                        $optionWeight += $weight;
                    } elseif ($weightPrefix === '-') {
                        $optionWeight -= $weight;
                    }

                    $optionData[] = [
                        'product_option_id'      => $productOptionId,
                        'product_optionvalue_id' => $optionValue,
                        'option_id'              => $productOption->option_id ?? '',
                        'optionvalue_id'         => $productOptionValue->optionvalue_id ?? '',
                        'name'                   => $productOption->option_name ?? '',
                        'option_value'           => $productOptionValue->optionvalue_name ?? '',
                        'type'                   => $type,
                        'price'                  => $price,
                        'price_prefix'           => $prefix,
                        'weight'                 => $weight,
                        'option_sku'             => $productOptionValue->product_optionvalue_sku ?? '',
                        'weight_prefix'          => $weightPrefix,
                    ];
                }
            } elseif ($type === 'checkbox' && \is_array($optionValue)) {
                foreach ($optionValue as $productOptionValueId) {
                    $productOptionValue = self::getCartProductOptionValues(
                        (int) $productOption->j2commerce_productoption_id,
                        (int) $productOptionValueId
                    );

                    if ($productOptionValue) {
                        // Calculate option price
                        $prefix = $productOptionValue->product_optionvalue_prefix ?? '+';
                        $price  = (float) ($productOptionValue->product_optionvalue_price ?? 0);

                        if ($prefix === '+') {
                            $optionPrice += $price;
                        } elseif ($prefix === '-') {
                            $optionPrice -= $price;
                        }

                        // Calculate option weight
                        $weightPrefix = $productOptionValue->product_optionvalue_weight_prefix ?? '+';
                        $weight       = (float) ($productOptionValue->product_optionvalue_weight ?? 0);

                        if ($weightPrefix === '+') {
                            $optionWeight += $weight;
                        } elseif ($weightPrefix === '-') {
                            $optionWeight -= $weight;
                        }

                        $optionData[] = [
                            'product_option_id'      => $productOptionId,
                            'product_optionvalue_id' => $productOptionValueId,
                            'option_id'              => $productOption->option_id ?? '',
                            'optionvalue_id'         => $productOptionValue->optionvalue_id ?? '',
                            'name'                   => $productOption->option_name ?? '',
                            'option_value'           => $productOptionValue->optionvalue_name ?? '',
                            'type'                   => $type,
                            'price'                  => $price,
                            'price_prefix'           => $prefix,
                            'weight'                 => $weight,
                            'option_sku'             => $productOptionValue->product_optionvalue_sku ?? '',
                            'weight_prefix'          => $weightPrefix,
                        ];
                    }
                }
            } elseif (\in_array($type, ['text', 'textarea', 'date', 'datetime', 'time', 'file'])) {
                $optionData[] = [
                    'product_option_id'      => $productOptionId,
                    'product_optionvalue_id' => '',
                    'option_id'              => $productOption->option_id ?? '',
                    'optionvalue_id'         => '',
                    'name'                   => $productOption->option_name ?? '',
                    'option_value'           => htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8'),
                    'type'                   => $type,
                    'price'                  => '',
                    'price_prefix'           => '',
                    'weight'                 => '',
                    'weight_prefix'          => '',
                ];
            }
        }

        return [
            'option_price'  => $optionPrice,
            'option_weight' => $optionWeight,
            'option_data'   => $optionData,
        ];
    }

    // =========================================================================
    // STOCK MANAGEMENT METHODS
    // =========================================================================

    /**
     * Check if inventory is being managed for a variant.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if managing stock.
     *
     * @since   6.0.3
     */
    public static function managingStock(object $variant): bool
    {
        $enableInventory = (bool) J2CommerceHelper::config()->get('enable_inventory', 0);

        if (!$enableInventory || empty($variant->manage_stock) || $variant->manage_stock != 1) {
            return false;
        }

        return true;
    }

    /**
     * Check if backorders are allowed.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if backorders are allowed.
     *
     * @since   6.0.3
     */
    public static function backordersAllowed(object $variant): bool
    {
        return isset($variant->allow_backorder) && $variant->allow_backorder >= 1;
    }

    /**
     * Check if backorders require notification.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if backorder notification is required.
     *
     * @since   6.0.3
     */
    public static function backordersRequireNotification(object $variant): bool
    {
        return self::managingStock($variant) && !empty($variant->allow_backorder) && $variant->allow_backorder == 2;
    }

    /**
     * Check stock status for a variant.
     *
     * @param   object  $variant   The variant object.
     * @param   int     $quantity  The requested quantity.
     *
     * @return  bool  True if stock is available.
     *
     * @since   6.0.3
     */
    public static function checkStockStatus(object $variant, int $quantity): bool
    {
        if (self::managingStock($variant) && !self::backordersAllowed($variant)) {
            return self::validateStock($variant, $quantity);
        }

        return true;
    }

    /**
     * Validate stock quantity.
     *
     * @param   object  $variant   The variant object.
     * @param   int     $quantity  The requested quantity.
     *
     * @return  bool  True if stock is valid.
     *
     * @since   6.0.3
     */
    public static function validateStock(object $variant, int $quantity = 1): bool
    {
        $variantQty = (int) ($variant->quantity ?? 0);

        // Check if stock is available
        if ($variantQty <= 0) {
            return false;
        }

        // Check if requested quantity exceeds available stock
        if ($quantity > $variantQty) {
            return false;
        }

        // Check availability flag
        if (empty($variant->availability)) {
            return false;
        }

        return true;
    }

    /**
     * Get stock quantity from product quantity table.
     *
     * @param   int  $variantId  The variant ID.
     *
     * @return  int  Stock quantity.
     *
     * @since   6.0.3
     */
    public static function getStockQuantity(int $variantId): int
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('quantity'))
            ->from($db->quoteName('#__j2commerce_productquantities'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Display stock status text.
     *
     * @param   object    $variant  The variant object.
     * @param   Registry  $params   Component parameters.
     *
     * @return  string  Stock status text.
     *
     * @since   6.0.3
     */
    public static function displayStock(object $variant, Registry $params): string
    {
        $text          = '';
        $displayFormat = $params->get('stock_display_format', 'always_show');
        $quantity      = (int) ($variant->quantity ?? 0);

        switch ($displayFormat) {
            case 'always_show':
            default:
                if ($quantity > 0) {
                    $text = Text::sprintf('COM_J2COMMERCE_IN_STOCK_WITH_QUANTITY', $quantity);
                } else {
                    $text = Text::_('COM_J2COMMERCE_IN_STOCK');
                }

                if (self::backordersAllowed($variant) && self::backordersRequireNotification($variant) && $quantity < 1) {
                    $text = Text::_('COM_J2COMMERCE_BACKORDER_NOTIFICATION');
                }
                break;

            case 'low_stock':
                $notifyQty = (int) ($variant->notify_qty ?? 0);

                if ($quantity > 0 && $quantity <= $notifyQty) {
                    $text = Text::sprintf('COM_J2COMMERCE_LOW_STOCK_WITH_QUANTITY', $quantity);
                }

                if (self::backordersAllowed($variant) && self::backordersRequireNotification($variant) && $quantity < 1) {
                    $text = Text::_('COM_J2COMMERCE_BACKORDER_NOTIFICATION');
                }
                break;

            case 'no_display':
                $text = '';
                break;
        }

        return $text;
    }

    /**
     * Apply quantity restrictions from store config.
     *
     * @param   object  $variant  The variant object (modified by reference).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public static function applyQuantityRestriction(object &$variant): void
    {
        $config          = J2CommerceHelper::config();
        $storeMinSaleQty = (float) $config->get('store_min_sale_qty', 1);
        $storeMaxSaleQty = (float) $config->get('store_max_sale_qty', 0);
        $storeNotifyQty  = (float) $config->get('store_notify_qty', 0);

        if (!empty($variant->use_store_config_min_sale_qty) && $variant->use_store_config_min_sale_qty > 0) {
            $variant->min_sale_qty = $storeMinSaleQty;
        }

        if (!empty($variant->use_store_config_max_sale_qty) && $variant->use_store_config_max_sale_qty > 0) {
            $variant->max_sale_qty = $storeMaxSaleQty;
        }

        if (!empty($variant->use_store_config_notify_qty) && $variant->use_store_config_notify_qty > 0) {
            $variant->notify_qty = $storeNotifyQty;
        }
    }

    /**
     * Validate quantity against restrictions.
     *
     * @param   object  $variant       The variant object.
     * @param   float   $cartTotalQty  Total quantity already in cart.
     * @param   float   $addToQty      Quantity being added.
     *
     * @return  string  Error message or empty string if valid.
     *
     * @since   6.0.3
     */
    public static function validateQuantityRestriction(object $variant, float $cartTotalQty, float $addToQty = 0): string
    {
        $error = '';

        if (empty($variant->quantity_restriction)) {
            return $error;
        }

        $quantityTotal = $cartTotalQty + $addToQty;
        $min           = (float) ($variant->min_sale_qty ?? 0);
        $max           = (float) ($variant->max_sale_qty ?? 0);

        if ($max > 0 && $quantityTotal > $max) {
            $error = Text::sprintf('COM_J2COMMERCE_MAX_QUANTITY_FOR_PRODUCT', $max, $cartTotalQty);
        }

        if ($min > 0 && $quantityTotal < $min) {
            $error = Text::sprintf('COM_J2COMMERCE_MIN_QUANTITY_FOR_PRODUCT', $min);
        }

        return $error;
    }

    /**
     * Get total quantity in cart for a variant.
     *
     * @param   int  $variantId  The variant ID.
     * @param   int  $cartId     Optional cart ID.
     *
     * @return  int  Total quantity in cart.
     *
     * @since   6.0.3
     */
    public static function getTotalCartQuantity(int $variantId, int $cartId = 0): int
    {
        if ($variantId < 1) {
            return 0;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true)
            ->select('SUM(' . $db->quoteName('product_qty') . ') AS total_cart_qty')
            ->from($db->quoteName('#__j2commerce_cartitems'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        if ($cartId > 0) {
            $query->where($db->quoteName('cart_id') . ' = :cartId')
                ->bind(':cartId', $cartId, ParameterType::INTEGER);
        }

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    // =========================================================================
    // DISPLAY HELPER METHODS
    // =========================================================================

    /**
     * Set tax info text.
     *
     * @param   float  $price         The price.
     * @param   int    $taxProfileId  The tax profile ID.
     * @param   array  $rates         Tax rates array.
     * @param   bool   $includesTax   Whether price includes tax.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public static function setTaxText(float $price, int $taxProfileId, array $rates = [], bool $includesTax = false): void
    {
        $text  = '';
        $total = 0.0;

        foreach ($rates as $rate) {
            $total += (float) ($rate['rate'] ?? 0);
        }

        if (!$includesTax) {
            if ($total > 0) {
                $text = Text::sprintf('COM_J2COMMERCE_PRICE_EXCLUDING_TAX_WITH_PERCENTAGE', round($total, 2) . '%');
            } else {
                $text = Text::_('COM_J2COMMERCE_PRICE_EXCLUDING_TAX');
            }
        } else {
            if ($total > 0) {
                $text = Text::sprintf('COM_J2COMMERCE_PRICE_INCLUDING_TAX', round($total, 2) . '%');
            } else {
                $text = Text::_('COM_J2COMMERCE_PRICE_EXCLUDING_TAX');
            }
        }

        self::$taxInfo = $text;
    }

    /**
     * Get tax info text.
     *
     * @return  string  Tax info text.
     *
     * @since   6.0.3
     */
    public static function getTaxText(): string
    {
        return self::$taxInfo;
    }

    /**
     * Reset tax info text.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public static function resetTaxText(): void
    {
        self::$taxInfo = '';
    }

    /**
     * Validate variable product for display.
     *
     * @param   object  $product  The product object.
     *
     * @return  bool  True if product should be displayed.
     *
     * @since   6.0.3
     */
    public static function validateVariableProduct(object $product): bool
    {
        if (!isset($product->variant) || !isset($product->product_type)) {
            if (($product->product_type ?? '') === 'flexivariable') {
                return true;
            }

            return false;
        }

        // Variable product types have their own sold-out logic
        $variableTypes = self::getVariableProductTypes();

        if (\in_array($product->product_type, $variableTypes)) {
            return empty($product->all_sold_out);
        }

        // For non-variable products: if stock management is disabled, always in stock
        if (!self::managingStock($product->variant)) {
            return true;
        }

        // Stock is managed: check availability flag or backorder allowance
        return !empty($product->variant->availability) || self::backordersAllowed($product->variant);
    }

    /**
     * Check if product type is allowed.
     *
     * @param   string        $productType          The product type.
     * @param   string|array  $allowedProductTypes  Allowed product types.
     * @param   string        $context              Context identifier.
     *
     * @return  bool  True if product type is allowed.
     *
     * @since   6.0.3
     */
    public static function isProductTypeAllowed(string $productType, string|array $allowedProductTypes, string $context): bool
    {
        if (empty($allowedProductTypes) || empty($productType)) {
            return false;
        }

        if (!\is_array($allowedProductTypes)) {
            $allowedProductTypes = [$allowedProductTypes];
        }

        return \in_array($productType, $allowedProductTypes);
    }

    // =========================================================================
    // RELATED PRODUCTS METHODS
    // =========================================================================

    /**
     * Get upsell products.
     *
     * @param   object  $sourceProduct  The source product object.
     *
     * @return  array  Array of upsell product objects.
     *
     * @since   6.0.3
     */
    public static function getUpsells(object $sourceProduct): array
    {
        $products  = [];
        $upSellCsv = $sourceProduct->up_sells ?? '';

        if (empty($upSellCsv)) {
            return $products;
        }

        $upSells = array_filter(explode(',', $upSellCsv));

        foreach ($upSells as $upSellId) {
            $product = self::getFullProduct((int) $upSellId);

            if (!$product) {
                continue;
            }

            // Skip if product is not visible, not enabled
            if (empty($product->visibility) || empty($product->enabled)) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }
    /*public static function getUpsells(object $sourceProduct): array
    {
        $products = [];
        $upSellCsv = $sourceProduct->up_sells ?? '';

        if (empty($upSellCsv)) {
            return $products;
        }

        $upSells = array_filter(explode(',', $upSellCsv));

        foreach ($upSells as $upSellId) {
            $product = self::getProductById((int) $upSellId);

            if (!$product) {
                continue;
            }

            // Skip if product is not visible, not enabled, or not published
            if (empty($product->visibility) || empty($product->enabled)) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }*/

    /**
     * Get cross-sell products.
     *
     * @param   object  $sourceProduct  The source product object.
     *
     * @return  array  Array of cross-sell product objects.
     *
     * @since   6.0.3
     */
    public static function getCrossSells(object $sourceProduct): array
    {
        $products     = [];
        $crossSellCsv = $sourceProduct->cross_sells ?? '';

        if (empty($crossSellCsv)) {
            return $products;
        }

        $crossSells = array_filter(explode(',', $crossSellCsv));

        foreach ($crossSells as $crossSellId) {
            $product = self::getFullProduct((int) $crossSellId);

            if (!$product) {
                continue;
            }

            // Skip if product is not visible, not enabled
            if (empty($product->visibility) || empty($product->enabled)) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Get related products by IDs.
     *
     * Retrieves product data including the product name from the source table
     * (e.g., #__content for com_content products) and generates edit URLs.
     *
     * @param   string  $items  Comma-separated product IDs.
     *
     * @return  array  Array of related product objects with product_name and product_edit_url.
     *
     * @since   6.0.3
     */
    public static function getRelatedProducts(string $items): array
    {
        if (empty($items)) {
            return [];
        }

        // Sanitize: only allow integers
        $ids = array_filter(array_map('intval', explode(',', $items)));

        if (empty($ids)) {
            return [];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        // Select product fields, variant fields, and content title as product_name
        $query->select($db->quoteName('p') . '.*')
            ->select($db->quoteName(['v.sku', 'v.upc']))
            ->select($db->quoteName('c.title') . ' AS ' . $db->quoteName('product_name'))
            ->from($db->quoteName('#__j2commerce_products', 'p'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_variants', 'v') . ' ON ' .
                $db->quoteName('v.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id')
            )
            ->join(
                'LEFT',
                $db->quoteName('#__content', 'c') . ' ON ' .
                $db->quoteName('p.product_source') . ' = ' . $db->quote('com_content') . ' AND ' .
                $db->quoteName('c.id') . ' = ' . $db->quoteName('p.product_source_id')
            )
            ->where($db->quoteName('v.is_master') . ' = 1')
            ->whereIn($db->quoteName('p.j2commerce_product_id'), $ids)
            ->group($db->quoteName('p.j2commerce_product_id'));

        $db->setQuery($query);

        $products = $db->loadObjectList() ?: [];

        // Add product_edit_url and ensure product_name for each product
        foreach ($products as $product) {
            // Ensure product_name is set (fallback to SKU or ID if content join failed)
            if (empty($product->product_name)) {
                $product->product_name = !empty($product->sku)
                    ? $product->sku
                    : Text::_('COM_J2COMMERCE_PRODUCT_ID') . ' ' . $product->j2commerce_product_id;
            }

            // Set product_edit_url with return parameter so user comes back after editing
            $return = base64_encode(Uri::getInstance()->toString());
            if ($product->product_source === 'com_content' && !empty($product->product_source_id)) {
                $product->product_edit_url = 'index.php?option=com_content&task=article.edit&id=' . (int) $product->product_source_id . '&return=' . $return;
            } else {
                $product->product_edit_url = 'index.php?option=com_j2commerce&task=product.edit&id=' . (int) $product->j2commerce_product_id . '&return=' . $return;
            }
        }

        return $products;
    }

    // =========================================================================
    // TYPE AND CONFIGURATION METHODS
    // =========================================================================

    /**
     * Get valid product types.
     *
     * @return  array  Associative array of product types.
     *
     * @since   6.0.3
     */
    public static function getProductTypes(): array
    {
        return [
            'simple'       => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_SIMPLE'),
            'configurable' => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_CONFIGURABLE'),
            'downloadable' => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_DOWNLOADABLE'),
            'bundle'       => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_BUNDLE'),
        ];
    }

    /**
     * Get variable product types.
     *
     * @return  array  Array of variable product type names.
     *
     * @since   6.0.3
     */
    public static function getVariableProductTypes(): array
    {
        return [
            'variable',
            'advancedvariable',
            'flexivariable',
            'variablesubscriptionproduct',
        ];
    }

    /**
     * Get J2Commerce base URL.
     *
     * @return  string  Base URL for J2Commerce.
     *
     * @since   6.0.3
     */
    public static function getBaseUrl(): string
    {
        return 'index.php?option=com_j2commerce';
    }

    // =========================================================================
    // VISIBILITY/PERMISSION METHODS
    // =========================================================================

    /**
     * Check if cart should be shown based on params.
     *
     * @param   Registry  $params  Component parameters.
     *
     * @return  bool  True if cart should be shown.
     *
     * @since   6.0.3
     */
    public static function canShowCart(Registry $params): bool
    {
        $isRegister   = (int) $params->get('isregister', 0);
        $allowDisplay = true;

        if ($isRegister && !Factory::getApplication()->getIdentity()->id) {
            $allowDisplay = false;
        }

        // TODO: Get catalog_mode from J2Commerce config when available
        $catalogMode = 0;

        return $catalogMode == 0 && $allowDisplay;
    }

    /**
     * Check if price should be shown based on params.
     *
     * @param   Registry  $params  Component parameters.
     *
     * @return  bool  True if price should be shown.
     *
     * @since   6.0.3
     */
    public static function canShowPrice(Registry $params): bool
    {
        $showPriceForRegistered = (int) $params->get('show_product_price_for_register_user', 0);
        $userId                 = Factory::getApplication()->getIdentity()->id;

        if ($showPriceForRegistered && empty($userId)) {
            return false;
        }

        return true;
    }

    /**
     * Check if SKU should be shown based on params.
     *
     * @param   Registry  $params  Component parameters.
     *
     * @return  bool  True if SKU should be shown.
     *
     * @since   6.0.3
     */
    public static function canShowSku(Registry $params): bool
    {
        $showSkuForRegistered = (int) $params->get('show_product_sku_for_register_user', 0);
        $userId               = Factory::getApplication()->getIdentity()->id;

        if ($showSkuForRegistered && empty($userId)) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // FILTER DROPDOWN METHODS
    // =========================================================================

    /**
     * Get categories for products filter.
     *
     * @return  array  Array of category objects.
     *
     * @since   6.0.3
     */
    public static function getCategories(): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'title', 'level']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get vendors for filter dropdown.
     *
     * @return  array  Array of vendor objects.
     *
     * @since   6.0.3
     */
    public static function getVendors(): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'v.j2commerce_vendor_id',
                'a.company',
            ]))
            ->from($db->quoteName('#__j2commerce_vendors', 'v'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('v.address_id')
            )
            ->where($db->quoteName('v.enabled') . ' = 1')
            ->order($db->quoteName('a.company') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get manufacturers for filter dropdown.
     *
     * @return  array  Array of manufacturer objects.
     *
     * @since   6.0.3
     */
    public static function getManufacturers(): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'm.j2commerce_manufacturer_id',
                'a.company',
            ]))
            ->from($db->quoteName('#__j2commerce_manufacturers', 'm'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('m.address_id')
            )
            ->where($db->quoteName('m.enabled') . ' = 1')
            ->order($db->quoteName('a.company') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    // =========================================================================
    // CHILD PRODUCT OPTIONS METHODS
    // =========================================================================

    /**
     * Get child product options with parent filtering.
     *
     * @param   int  $productId              Product ID
     * @param   int  $parentId               Parent ID
     * @param   int  $parentOptionvalueId    Parent option value ID
     *
     * @return  array  Array of child product option data.
     *
     * @since   6.0.3
     */
    public static function getChildProductOptions(int $productId, int $parentId = 0, int $parentOptionvalueId = 0): array
    {
        static $cache = [];

        $cacheKey = $productId . '_' . $parentId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db                = self::getDatabase();
        $productOptionData = [];

        $query = $db->getQuery(true);
        $query->select($db->quoteName([
                'po.j2commerce_productoption_id',
                'po.option_id',
                'po.parent_id',
                'po.ordering',
                'po.required',
            ]))
            ->select($db->quoteName([
                'o.option_name',
                'o.type',
                'o.option_params',
            ]))
            ->from($db->quoteName('#__j2commerce_product_options', 'po'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_options', 'o') . ' ON ' .
                $db->quoteName('o.j2commerce_option_id') . ' = ' . $db->quoteName('po.option_id')
            )
            ->where($db->quoteName('po.product_id') . ' = :productId')
            ->where($db->quoteName('po.parent_id') . ' = :parentId')
            ->order($db->quoteName('po.ordering') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER)
            ->bind(':parentId', $parentId, ParameterType::INTEGER);

        $db->setQuery($query);
        $productOptions = $db->loadObjectList() ?: [];

        foreach ($productOptions as $productOption) {
            $type = $productOption->type ?? '';

            if (\in_array($type, ['select', 'radio', 'checkbox', 'color'])) {
                $productOptionValueData = [];
                $productOptionValues    = self::getChildProductOptionValues(
                    (int) $productOption->j2commerce_productoption_id,
                    $productId,
                    $parentOptionvalueId
                );

                foreach ($productOptionValues as $productOptionValue) {
                    $productOptionValueData[] = [
                        'product_optionvalue_id'            => $productOptionValue->j2commerce_product_optionvalue_id ?? '',
                        'optionvalue_id'                    => $productOptionValue->optionvalue_id ?? '',
                        'optionvalue_name'                  => $productOptionValue->optionvalue_name ?? '',
                        'optionvalue_image'                 => $productOptionValue->optionvalue_image ?? '',
                        'product_optionvalue_price'         => $productOptionValue->product_optionvalue_price ?? 0,
                        'product_optionvalue_prefix'        => $productOptionValue->product_optionvalue_prefix ?? '+',
                        'product_optionvalue_weight'        => $productOptionValue->product_optionvalue_weight ?? 0,
                        'product_optionvalue_sku'           => $productOptionValue->product_optionvalue_sku ?? '',
                        'product_optionvalue_weight_prefix' => $productOptionValue->product_optionvalue_weight_prefix ?? '+',
                        'product_optionvalue_default'       => $productOptionValue->product_optionvalue_default ?? 0,
                    ];
                }

                $productOptionData[] = [
                    'productoption_id' => $productOption->j2commerce_productoption_id,
                    'option_id'        => $productOption->option_id,
                    'option_name'      => $productOption->option_name ?? '',
                    'type'             => $type,
                    'optionvalue'      => $productOptionValueData,
                    'option_params'    => $productOption->option_params ?? '',
                    'required'         => $productOption->required ?? 0,
                ];
            } else {
                $productOptionValues = self::getChildProductOptionValues(
                    (int) $productOption->j2commerce_productoption_id,
                    $productId,
                    $parentOptionvalueId
                );

                if (!empty($productOptionValues)) {
                    $productOptionData[] = [
                        'productoption_id' => $productOption->j2commerce_productoption_id,
                        'option_id'        => $productOption->option_id,
                        'option_name'      => $productOption->option_name ?? '',
                        'type'             => $type,
                        'optionvalue'      => '',
                        'option_params'    => $productOption->option_params ?? '',
                        'required'         => $productOption->required ?? 0,
                    ];
                }
            }
        }

        $cache[$cacheKey] = $productOptionData;

        return $productOptionData;
    }

    /**
     * Get child product option values with parent filtering.
     *
     * @param   int  $productOptionId       Product option ID
     * @param   int  $productId             Product ID
     * @param   int  $parentOptionvalueId   Parent option value ID
     *
     * @return  array  Array of option value objects.
     *
     * @since   6.0.3
     */
    public static function getChildProductOptionValues(int $productOptionId, int $productId, int $parentOptionvalueId): array
    {
        static $cache = [];

        $cacheKey = $productOptionId . '_' . $productId . '_' . $parentOptionvalueId;

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $parentStr  = (string) $parentOptionvalueId;
        $parentId1  = $parentStr;                 // exact match
        $parentId2  = $parentStr . ',%';          // starts with
        $parentId3  = '%,' . $parentStr . ',%';   // contains in middle
        $parentId4  = '%,' . $parentStr;          // ends with

        $query->select($db->quoteName('pov') . '.*')
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->where($db->quoteName('pov.productoption_id') . ' = :poId')
            ->where('(' .
                $db->quoteName('parent_optionvalue') . ' = :parentId1' .
                ' OR ' . $db->quoteName('parent_optionvalue') . ' LIKE :parentId2' .
                ' OR ' . $db->quoteName('parent_optionvalue') . ' LIKE :parentId3' .
                ' OR ' . $db->quoteName('parent_optionvalue') . ' LIKE :parentId4' .
                ')')
            ->bind(':poId', $productOptionId, ParameterType::INTEGER)
            ->bind(':parentId1', $parentId1, ParameterType::STRING)
            ->bind(':parentId2', $parentId2, ParameterType::STRING)
            ->bind(':parentId3', $parentId3, ParameterType::STRING)
            ->bind(':parentId4', $parentId4, ParameterType::STRING);

        $query->select($db->quoteName(['ov.j2commerce_optionvalue_id', 'ov.optionvalue_name', 'ov.optionvalue_image']))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov') . ' ON ' .
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            )
            ->order($db->quoteName('pov.ordering') . ' ASC');

        $db->setQuery($query);
        $cache[$cacheKey] = $db->loadObjectList() ?: [];

        return $cache[$cacheKey];
    }

    // =========================================================================
    // PRODUCT OPTION LIST METHODS
    // =========================================================================

    /**
     * Get product option list for a product type.
     *
     * @param   string  $productType  The product type.
     *
     * @return  array  Array of option objects.
     *
     * @since   6.0.3
     */
    public static function getProductOptionList(?string $productType): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['j2commerce_option_id', 'option_unique_name', 'option_name']))
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('enabled') . ' = 1');

        // Filter by product type
        if (!empty($productType) && \in_array($productType, ['variable', 'flexivariable'])) {
            $query->whereIn($db->quoteName('type'), ['select', 'radio', 'checkbox', 'color'], ParameterType::STRING);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get manufacturer details by product.
     *
     * @param   object  $product  The product object.
     *
     * @return  object|null  Manufacturer object or null.
     *
     * @since   6.0.3
     */
    public static function getManufacturerDetails(object $product): ?object
    {
        $manufacturerId = $product->manufacturer_id ?? 0;

        if ($manufacturerId === 0) {
            return null;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_manufacturers'))
            ->where($db->quoteName('j2commerce_manufacturer_id') . ' = :mfgId')
            ->bind(':mfgId', $manufacturerId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    // =========================================================================
    // PRICE MODIFIER HTML METHODS
    // =========================================================================

    /**
     * Get price modifier HTML select field.
     *
     * @param   string  $name     Field name.
     * @param   string  $value    Current value.
     * @param   string  $default  Default value.
     *
     * @return  string  HTML select element.
     *
     * @since   6.0.3
     */
    public static function getPriceModifierHtml(string $name, string $value = '', string $default = '+'): string
    {
        if (empty($value)) {
            $value = $default;
        }

        $modifiers = self::getPriceModifiers();
        $options   = [];

        foreach ($modifiers as $key => $text) {
            $selected  = ($key === $value) ? ' selected="selected"' : '';
            $options[] = '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        return '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" class="form-select form-select-sm">' . implode('', $options) . '</select>';
    }

    // =========================================================================
    // INSTANCE-BASED PRICING METHODS (Non-static for backward compatibility)
    // =========================================================================

    /**
     * Get product price with full calculation including advanced pricing.
     *
     * Checks the product_prices table for:
     * - Quantity-based pricing (quantity_from, quantity_to)
     * - Date-based pricing (date_from, date_to)
     * - Customer group pricing (customer_group_id)
     *
     * @param   object       $variant   Variant object.
     * @param   int          $quantity  Quantity.
     * @param   string       $groupId   User group ID (empty = current user's groups).
     * @param   string       $date      Date for price calculation (empty = now).
     *
     * @return  object|false  Pricing object or false on failure.
     *
     * @since   6.0.3
     */
    public function getPrice(object $variant, int $quantity = 1, string $groupId = '', string $date = ''): object|false
    {
        if (!$variant) {
            return false;
        }

        $pricingCalculator = $variant->pricing_calculator ?? 'standard';
        $basePrice         = (float) ($variant->price ?? 0);

        // Initialize pricing object with base values
        $pricing                = new \stdClass();
        $pricing->base_price    = $basePrice;
        $pricing->price         = $basePrice;
        $pricing->special_price = null;
        $pricing->is_sale_price = false;
        $pricing->calculator    = $pricingCalculator;
        $pricing->variant_id    = (int) ($variant->j2commerce_variant_id ?? 0);
        $pricing->sku           = $variant->sku ?? '';
        $pricing->quantity      = $quantity;
        $pricing->group_id      = $groupId;
        $pricing->date          = $date;

        $variantId = (int) ($variant->j2commerce_variant_id ?? 0);

        if ($variantId <= 0) {
            return $pricing;
        }

        // Get current user's groups if not specified
        if (empty($groupId)) {
            $user       = Factory::getApplication()->getIdentity();
            $userGroups = $user ? $user->getAuthorisedGroups() : [1]; // Default to public group

            if (!\in_array(1, $userGroups)) {
                $userGroups[] = 1;
            }
        } else {
            $userGroups = [(int) $groupId];
        }

        // Use current date/time if not specified
        if (empty($date)) {
            $date = Factory::getDate()->toSql();
        }

        // Check for advanced pricing in product_prices table
        $advancedPrice = $this->getAdvancedPrice($variantId, $quantity, $userGroups, $date);

        if ($advancedPrice !== false && $advancedPrice < $basePrice) {
            $pricing->special_price = $advancedPrice;
            $pricing->price         = $advancedPrice;
            $pricing->is_sale_price = true;
        }

        $pricing->is_discount_pricing_available = ($pricing->base_price > $pricing->price);

        return $pricing;
    }

    /**
     * Get advanced price from product_prices table.
     *
     * Searches for the best applicable price based on quantity, date range,
     * and customer group. Returns the lowest applicable price found.
     *
     * @param   int     $variantId   Variant ID.
     * @param   int     $quantity    Quantity being purchased.
     * @param   array   $groupIds    Array of user group IDs.
     * @param   string  $date        Date to check against (SQL format).
     *
     * @return  float|false  The advanced price or false if none found.
     *
     * @since   6.0.3
     */
    protected function getAdvancedPrice(int $variantId, int $quantity, array $groupIds, string $date): float|false
    {
        $db = self::getDatabase();

        // Build query to find all applicable prices
        $query = $db->getQuery(true)
            ->select($db->quoteName('price'))
            ->from($db->quoteName('#__j2commerce_product_prices'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->bind(':variantId', $variantId, ParameterType::INTEGER);

        // Quantity range check (null/0 means no restriction)
        $query->where(
            '(' .
            $db->quoteName('quantity_from') . ' IS NULL OR ' .
            $db->quoteName('quantity_from') . ' = 0 OR ' .
            $db->quoteName('quantity_from') . ' <= :qtyFrom' .
            ')'
        );
        $query->where(
            '(' .
            $db->quoteName('quantity_to') . ' IS NULL OR ' .
            $db->quoteName('quantity_to') . ' = 0 OR ' .
            $db->quoteName('quantity_to') . ' >= :qtyTo' .
            ')'
        );
        $query->bind(':qtyFrom', $quantity, ParameterType::INTEGER);
        $query->bind(':qtyTo', $quantity, ParameterType::INTEGER);

        // Date range check (null or zero-date means no restriction)
        $nullDate = $db->getNullDate();

        $query->where(
            '(' .
            $db->quoteName('date_from') . ' IS NULL OR ' .
            $db->quoteName('date_from') . ' = :nullDate1 OR ' .
            $db->quoteName('date_from') . ' <= :dateFrom' .
            ')'
        );
        $query->where(
            '(' .
            $db->quoteName('date_to') . ' IS NULL OR ' .
            $db->quoteName('date_to') . ' = :nullDate2 OR ' .
            $db->quoteName('date_to') . ' >= :dateTo' .
            ')'
        );
        $query->bind(':nullDate1', $nullDate);
        $query->bind(':nullDate2', $nullDate);
        $query->bind(':dateFrom', $date);
        $query->bind(':dateTo', $date);

        // Customer group check (null means applies to all groups)
        if (!empty($groupIds)) {
            $groupIdList = implode(',', array_map('intval', $groupIds));
            $query->where(
                '(' .
                $db->quoteName('customer_group_id') . ' IS NULL OR ' .
                $db->quoteName('customer_group_id') . ' IN (' . $groupIdList . ')' .
                ')'
            );
        }

        // Order by price ascending to get the lowest price first
        $query->order($db->quoteName('price') . ' ASC');

        $db->setQuery($query, 0, 1);
        $price = $db->loadResult();

        return $price !== null ? (float) $price : false;
    }

    /**
     * Get quantity-based price.
     *
     * @param   object       $product   Product object.
     * @param   object|null  $variant   Variant object.
     * @param   float        $qty       Quantity.
     *
     * @return  float|false  Price or false if not found.
     *
     * @since   6.0.3
     */
    public function getQuantityBasedPrice(object $product, ?object $variant = null, float $qty = 1): float|false
    {
        $db = self::getDatabase();

        $variantId = 0;

        if ($variant && isset($variant->j2commerce_variant_id)) {
            $variantId = (int) $variant->j2commerce_variant_id;
        }

        $productId = (int) ($product->j2commerce_product_id ?? 0);
        $qtyFloat  = (float) $qty;

        $query = $db->getQuery(true)
            ->select($db->quoteName('price'))
            ->from($db->quoteName('#__j2commerce_product_prices'))
            ->where($db->quoteName('variant_id') . ' = :variantId')
            ->where($db->quoteName('quantity_from') . ' <= :qtyFrom')
            ->where('(' . $db->quoteName('quantity_to') . ' >= :qtyTo OR ' . $db->quoteName('quantity_to') . ' = 0 OR ' . $db->quoteName('quantity_to') . ' IS NULL)')
            ->order($db->quoteName('quantity_from') . ' DESC')
            ->bind(':variantId', $variantId, ParameterType::INTEGER)
            ->bind(':qtyFrom', $qtyFloat)
            ->bind(':qtyTo', $qtyFloat);

        $db->setQuery($query, 0, 1);
        $price = $db->loadResult();

        return $price !== null ? (float) $price : false;
    }

    /**
     * Instance-based set tax text method.
     *
     * @param   float  $price         The price.
     * @param   int    $taxProfileId  Tax profile ID.
     * @param   array  $rates         Tax rates.
     * @param   bool   $includesTax   Whether price includes tax.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function set_tax_text(float $price, int $taxProfileId, array $rates = [], bool $includesTax = false): void
    {
        $text  = '';
        $total = 0.0;

        foreach ($rates as $rate) {
            $total += (float) ($rate['rate'] ?? 0);
        }

        if (!$includesTax) {
            if ($total > 0) {
                $text = Text::sprintf('COM_J2COMMERCE_PRICE_EXCLUDING_TAX_WITH_PERCENTAGE', round($total, 2) . '%');
            } else {
                $text = Text::_('COM_J2COMMERCE_PRICE_EXCLUDING_TAX');
            }
        } else {
            if ($total > 0) {
                $text = Text::sprintf('COM_J2COMMERCE_PRICE_INCLUDING_TAX', round($total, 2) . '%');
            } else {
                $text = Text::_('COM_J2COMMERCE_PRICE_EXCLUDING_TAX');
            }
        }

        $this->_tax_info = $text;
    }

    /**
     * Instance-based get tax text method.
     *
     * @return  string  Tax info text.
     *
     * @since   6.0.3
     */
    public function get_tax_text(): string
    {
        return $this->_tax_info;
    }

    /**
     * Instance-based reset tax text method.
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function reset_tax_text(): void
    {
        $this->_tax_info = '';
    }

    /**
     * Instance-based managing stock check.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if managing stock.
     *
     * @since   6.0.3
     */
    public function managing_stock(object $variant): bool
    {
        // TODO: Get from J2Commerce config when available
        $enableInventory = true;

        if (!$enableInventory || empty($variant->manage_stock) || $variant->manage_stock != 1) {
            return false;
        }

        return true;
    }

    /**
     * Instance-based backorders allowed check.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if backorders are allowed.
     *
     * @since   6.0.3
     */
    public function backorders_allowed(object $variant): bool
    {
        return isset($variant->allow_backorder) && $variant->allow_backorder >= 1;
    }

    /**
     * Instance-based backorders require notification check.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  bool  True if notification required.
     *
     * @since   6.0.3
     */
    public function backorders_require_notification(object $variant): bool
    {
        return $this->managing_stock($variant) && !empty($variant->allow_backorder) && $variant->allow_backorder == 2;
    }

    /**
     * Instance-based check stock status.
     *
     * @param   object  $variant   The variant object.
     * @param   int     $quantity  Quantity to check.
     *
     * @return  bool  True if stock is available.
     *
     * @since   6.0.3
     */
    public function check_stock_status(object $variant, int $quantity): bool
    {
        if ($this->managing_stock($variant) && !$this->backorders_allowed($variant)) {
            return self::validateStock($variant, $quantity);
        }

        return true;
    }

    /**
     * Get stock quantity from product quantity table.
     *
     * @param   object  $productQuantityTable  The quantity table object.
     *
     * @return  int  Stock quantity.
     *
     * @since   6.0.3
     */
    public function get_stock_quantity(object $productQuantityTable): int
    {
        return (int) ($productQuantityTable->quantity ?? 0);
    }

    /**
     * Apply quantity restrictions from store config (modifies variant by reference).
     *
     * @param   object  $variant  The variant object (modified by reference).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getQuantityRestriction(object &$variant): void
    {
        // TODO: Get from J2Commerce store profile when available
        $storeMinSaleQty = 1.0;
        $storeMaxSaleQty = 0.0;
        $storeNotifyQty  = 5.0;

        if (!empty($variant->use_store_config_min_sale_qty) && $variant->use_store_config_min_sale_qty > 0) {
            $variant->min_sale_qty = $storeMinSaleQty;
        }

        if (!empty($variant->use_store_config_max_sale_qty) && $variant->use_store_config_max_sale_qty > 0) {
            $variant->max_sale_qty = $storeMaxSaleQty;
        }

        if (!empty($variant->use_store_config_notify_qty) && $variant->use_store_config_notify_qty > 0) {
            $variant->notify_qty = $storeNotifyQty;
        }
    }

    /**
     * Get add to cart form action URL.
     *
     * Uses proper Joomla routing via PlatformHelper::getCartUrl() to ensure
     * SEF URLs and proper Itemid handling.
     *
     * @param   object  $product  The product object (modified by reference).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getAddtocartAction(object &$product): void
    {
        // Use PlatformHelper for proper SEF routing with Itemid
        // The task 'addItem' is automatically prefixed with 'carts.' by getCartUrl()
        $product->cart_form_action = J2CommerceHelper::platform()->getCartUrl(['task' => 'addItem']);
    }

    /**
     * Get checkout link.
     *
     * Uses proper Joomla routing via PlatformHelper::getCartUrl() to ensure
     * SEF URLs and proper Itemid handling.
     *
     * @param   object  $product  The product object (modified by reference).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getCheckoutLink(object &$product): void
    {
        $product->checkout_link = self::getCheckoutLinkUrl();
    }

    public static function getCheckoutLinkUrl(): string
    {
        $platform = J2CommerceHelper::platform();

        return match (J2CommerceHelper::config()->get('addtocart_checkout_link', 'cart')) {
            'checkout' => $platform->getCheckoutUrl(),
            default    => $platform->getCartUrl(),
        };
    }

    /**
     * Get the product link.
     *
     * Uses proper Joomla routing via PlatformHelper::getProductUrl() to ensure
     * SEF URLs and proper Itemid handling.
     *
     * @param   object  $product  The product object (modified by reference).
     *
     * @return  void
     *
     * @since   6.0.3
     */
    public function getProductLink(object &$product): void
    {
        $productId             = $product->j2commerce_product_id ?? 0;
        $product->product_link = J2CommerceHelper::platform()->getProductUrl(['task' => 'view','id' => (int) $productId]);
    }

    /**
     * Display formatted price.
     *
     * @param   float|string  $price    The price to display (string from DB decimal columns is accepted).
     * @param   object|null   $product  The product object.
     * @param   Registry|null $params   Component parameters.
     * @param   string        $context  Display context.
     *
     * @return  string  Formatted price HTML.
     *
     * @since   6.0.3
     */
    public function displayPrice(float|string $price, ?object $product = null, ?Registry $params = null, string $context = ''): string
    {
        $price = (float) $price;
        $html  = '';

        // Format the price using currency helper
        $formattedPrice = J2CommerceHelper::currency()->format($price);

        $html = '<span class="j2commerce-product-price">' . $formattedPrice . '</span>';

        // Add tax info if available
        if (!empty($this->_tax_info)) {
            $html .= '<span class="j2commerce-product-tax-info">' . $this->_tax_info . '</span>';
        }

        return $html;
    }

    /**
     * Display quantity input.
     *
     * @param   string       $context  Display context.
     * @param   object       $product  The product object.
     * @param   object|null  $params   Component parameters (Registry or ConfigHelper).
     * @param   array        $options  Additional options.
     *
     * @return  string  Quantity input HTML.
     *
     * @since   6.0.3
     */
    public function displayQuantity(string $context, object $product, ?object $params = null, array $options = []): string
    {
        $inputClass = $options['class'] ?? $options['input_class'] ?? 'j2commerce-qty-input form-control';
        $iconMinus  = $options['icon-minus'] ?? 'icon-minus fs-sm';
        $iconPlus   = $options['icon-plus'] ?? 'icon-plus fs-sm';
        $minQty     = (int) ($product->min_sale_qty ?? 1);
        $maxQty     = (int) ($product->max_sale_qty ?? 0);
        $isCart     = str_contains($context, 'cart');

        if ($isCart) {
            $currentQty = (int) ($product->orderitem_quantity ?? $product->product_qty ?? 1);
            $defaultQty = $currentQty > 0 ? $currentQty : 1;
            $inputName  = 'qty[' . ($product->cartitem_id ?? $product->j2commerce_cartitem_id ?? 0) . ']';
        } else {
            $defaultQty = $minQty > 0 ? $minQty : 1;
            $inputName  = 'product_qty';
        }

        $minVal = $minQty > 0 ? $minQty : 1;

        $showButtons = $options['show_buttons'] ?? true;

        // Build the input element
        $inputType = $showButtons && !$isCart ? 'text' : 'number';
        $input  = '<input type="' . $inputType . '"';
        $input .= ' name="' . htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') . '"';
        $input .= ' value="' . $defaultQty . '"';
        $input .= ' min="' . $minVal . '"';
        if ($showButtons && !$isCart) {
            $input .= ' pattern="[0-9]*"';
            $input .= ' inputmode="numeric"';
        }
        if ($maxQty > 0) {
            $input .= ' max="' . $maxQty . '"';
        }
        $input .= ' step="1"';
        if ($showButtons) {
            $input .= ' readonly';
        }
        $input .= ' class="' . htmlspecialchars($inputClass, ENT_QUOTES, 'UTF-8') . '"';
        $input .= ' />';

        if ($isCart || !$showButtons) {
            return $input;
        }

        // Wrap with increment/decrement buttons
        $decrementDisabled = $defaultQty <= $minVal ? ' disabled' : '';
        $incrementDisabled = $maxQty > 0 && $defaultQty >= $maxQty ? ' disabled' : '';

        $html  = '<div class="count-input flex-shrink-0">';
        $html .= '<button type="button" class="btn btn-icon btn-lg" data-decrement aria-label="' . Text::_('COM_J2COMMERCE_DECREASE_QUANTITY') . '"' . $decrementDisabled . '>';
        $html .= '<span class="' . htmlspecialchars($iconMinus, ENT_QUOTES, 'UTF-8') . '"></span>';
        $html .= '</button>';
        $html .= $input;
        $html .= '<button type="button" class="btn btn-icon btn-lg" data-increment aria-label="' . Text::_('COM_J2COMMERCE_INCREASE_QUANTITY') . '"' . $incrementDisabled . '>';
        $html .= '<span class="' . htmlspecialchars($iconPlus, ENT_QUOTES, 'UTF-8') . '"></span>';
        $html .= '</button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get price including tax.
     *
     * @param   float  $price         The base price.
     * @param   int    $taxProfileId  The tax profile ID.
     *
     * @return  float  Price including tax.
     *
     * @since   6.0.3
     */
    public function get_price_including_tax(float $price, int $taxProfileId): float
    {
        if ($taxProfileId <= 0) {
            return $price;
        }

        // Get tax rate for product
        $taxRate = $this->getTaxRateForProfile($taxProfileId);

        if ($taxRate > 0) {
            $price = $price * (1 + ($taxRate / 100));
        }

        return $price;
    }

    /**
     * Get price excluding tax.
     *
     * @param   float  $price         The price with tax.
     * @param   int    $taxProfileId  The tax profile ID.
     *
     * @return  float  Price excluding tax.
     *
     * @since   6.0.3
     */
    public function get_price_excluding_tax(float $price, int $taxProfileId): float
    {
        if ($taxProfileId <= 0) {
            return $price;
        }

        // Get tax rate for product
        $taxRate = $this->getTaxRateForProfile($taxProfileId);

        if ($taxRate > 0) {
            $price = $price / (1 + ($taxRate / 100));
        }

        return $price;
    }

    /**
     * Get tax rate for a tax profile.
     *
     * @param   int  $taxProfileId  The tax profile ID.
     *
     * @return  float  The tax rate percentage.
     *
     * @since   6.0.3
     */
    protected function getTaxRateForProfile(int $taxProfileId): float
    {
        if ($taxProfileId <= 0) {
            return 0.0;
        }

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('tr.tax_percent'))
            ->from($db->quoteName('#__j2commerce_taxrules', 'tpr'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_taxrates', 'tr') . ' ON ' .
                $db->quoteName('tr.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tpr.taxrate_id')
            )
            ->where($db->quoteName('tpr.taxprofile_id') . ' = :taxProfileId')
            ->bind(':taxProfileId', $taxProfileId, ParameterType::INTEGER);

        $db->setQuery($query);
        $result = $db->loadResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * Display product image with plugin event.
     *
     * @param   object  $product      The product object.
     * @param   array   $productData  Product data including 'type' and 'params'.
     *
     * @return  string  Image HTML.
     *
     * @since   6.0.3
     */
    public function displayImage(object $product, array $productData): string
    {
        $html = '';

        if (!isset($productData['type']) || !isset($productData['params'])) {
            return $html;
        }

        // Generate default image HTML
        $imageUrl = $product->main_image ?? '';

        if (!empty($imageUrl)) {
            $alt  = htmlspecialchars($product->product_name ?? '', ENT_QUOTES, 'UTF-8');
            $html = '<img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8')
                . '" alt="' . $alt . '" class="j2commerce-product-image" />';
        }

        return $html;
    }

    /**
     * Get filters for product listing.
     *
     * @param   array  $items   Array of product items.
     * @param   array  $catids  Optional category IDs to filter by.
     *
     * @return  array  Filter configuration array.
     *
     * @since   6.0.3
     */
    public static function getFilters(array $items = [], array $catids = []): array
    {
        $filters                      = [];
        $filters['filter_categories'] = [];
        $filters['sorting']           = [];
        $filters['productfilters']    = [];
        $filters['manufacturers']     = [];
        $filters['vendors']           = [];
        $filters['pricefilters']      = [];

        // Get product IDs from items
        $productIds = [];

        foreach ($items as $item) {
            if (isset($item->j2commerce_product_id)) {
                $productIds[] = (int) $item->j2commerce_product_id;
            }
        }

        // Get manufacturers
        $filters['manufacturers'] = self::getManufacturers();

        // Get vendors with first_name and last_name
        $filters['vendors'] = self::getVendorsWithNames();

        // Get categories (filtered by parent if catids provided)
        $filters['filter_categories'] = self::getCategoriesForFilter($catids);

        // Get price range
        $filters['pricefilters'] = self::getPriceFilters($catids);

        // Get product filters (custom attribute filters)
        $filters['productfilters'] = self::getProductFilters($productIds);

        // Get sorting options
        $filters['sorting'] = self::getSortingOptions();

        return $filters;
    }

    /**
     * Get vendors with first_name and last_name for filter display.
     *
     * @return  array  Array of vendor objects with name fields.
     *
     * @since   6.0.3
     */
    public static function getVendorsWithNames(): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName([
                'v.j2commerce_vendor_id',
                'a.company',
                'a.first_name',
                'a.last_name',
            ]))
            ->from($db->quoteName('#__j2commerce_vendors', 'v'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_addresses', 'a') . ' ON ' .
                $db->quoteName('a.j2commerce_address_id') . ' = ' . $db->quoteName('v.address_id')
            )
            ->where($db->quoteName('v.enabled') . ' = 1')
            ->order($db->quoteName('a.company') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get categories for filter (optionally filtered by parent categories).
     *
     * @param   array  $catids  Parent category IDs to filter by.
     *
     * @return  array  Array of category objects.
     *
     * @since   6.0.3
     */
    public static function getCategoriesForFilter(array $catids = []): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['id', 'title', 'level', 'parent_id']))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('lft') . ' ASC');

        // If parent categories specified, filter to those and their children
        if (!empty($catids)) {
            $sanitizedCatids = array_map('intval', $catids);
            $catidList       = implode(',', $sanitizedCatids);

            // Get the parent categories and all their children
            $subQuery = $db->getQuery(true);
            $subQuery->select('DISTINCT ' . $db->quoteName('sub.id'))
                ->from($db->quoteName('#__categories', 'sub'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__categories', 'parent'),
                    $db->quoteName('sub.lft') . ' >= ' . $db->quoteName('parent.lft')
                        . ' AND ' . $db->quoteName('sub.lft') . ' <= ' . $db->quoteName('parent.rgt')
                )
                ->where($db->quoteName('parent.id') . ' IN (' . $catidList . ')');

            $query->where($db->quoteName('id') . ' IN (' . $subQuery . ')');
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get price range for filter slider.
     *
     * @param   array  $catids  Optional category IDs to filter by.
     *
     * @return  array  Array with min_price and max_price.
     *
     * @since   6.0.3
     */
    public static function getPriceFilters(array $catids = []): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        // Use min child variant price for variable/flexi products where master price is $0.
        // COALESCE(vc.min_child_price, v.price) matches applyPriceRangeFilter logic.
        $effectivePrice = 'COALESCE(' . $db->quoteName('vc.min_child_price') . ', ' . $db->quoteName('v.price') . ')';

        $query->select([
                'MIN(' . $effectivePrice . ') AS min_price',
                'MAX(' . $effectivePrice . ') AS max_price',
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'v'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_products', 'p') . ' ON ' .
                $db->quoteName('p.j2commerce_product_id') . ' = ' . $db->quoteName('v.product_id')
            )
            ->where($db->quoteName('v.is_master') . ' = 1')
            ->where($db->quoteName('p.enabled') . ' = 1')
            ->where($db->quoteName('p.visibility') . ' = 1');

        // Subquery: min child variant price per product
        $vcSub = $db->getQuery(true)
            ->select([
                $db->quoteName('vc.product_id'),
                'MIN(' . $db->quoteName('vc.price') . ') AS ' . $db->quoteName('min_child_price'),
            ])
            ->from($db->quoteName('#__j2commerce_variants', 'vc'))
            ->where($db->quoteName('vc.is_master') . ' = 0')
            ->where($db->quoteName('vc.price') . ' > 0')
            ->group($db->quoteName('vc.product_id'));

        $query->join('LEFT', '(' . $vcSub . ') AS ' . $db->quoteName('vc') . ' ON ' . $db->quoteName('vc.product_id') . ' = ' . $db->quoteName('p.j2commerce_product_id'));

        // Filter by category if provided
        if (!empty($catids)) {
            $query->join(
                'INNER',
                $db->quoteName('#__content', 'a') . ' ON ' .
                $db->quoteName('a.id') . ' = ' . $db->quoteName('p.product_source_id')
            );

            $sanitizedCatids = array_map('intval', $catids);
            $query->whereIn($db->quoteName('a.catid'), $sanitizedCatids);
        }

        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result && $result->min_price !== null && $result->max_price !== null) {
            return [
                'min_price' => (float) $result->min_price,
                'max_price' => (float) $result->max_price,
            ];
        }

        return [
            'min_price' => 0.00,
            'max_price' => 0.00,
        ];
    }

    /**
     * Get product filters (custom attribute filters) grouped by filter group.
     *
     * @param   array  $productIds  Optional product IDs to filter available filters.
     *
     * @return  array  Array of filter groups with their filters.
     *
     * @since   6.0.3
     */
    public static function getProductFilters(array $productIds = []): array
    {
        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        // Get filter groups with their filters
        $query->select($db->quoteName([
                'fg.j2commerce_filtergroup_id',
                'fg.group_name',
                'f.j2commerce_filter_id',
                'f.filter_name',
            ]))
            ->from($db->quoteName('#__j2commerce_filtergroups', 'fg'))
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_filters', 'f') . ' ON ' .
                $db->quoteName('f.group_id') . ' = ' . $db->quoteName('fg.j2commerce_filtergroup_id')
            )
            ->where($db->quoteName('fg.enabled') . ' = 1')
            ->order([
                $db->quoteName('fg.ordering') . ' ASC',
                $db->quoteName('f.ordering') . ' ASC',
            ]);

        // If product IDs provided, only get filters that are assigned to those products
        if (!empty($productIds)) {
            $sanitizedIds = array_map('intval', $productIds);
            $idList       = implode(',', $sanitizedIds);

            $query->join(
                'INNER',
                $db->quoteName('#__j2commerce_product_filters', 'pf') . ' ON ' .
                $db->quoteName('pf.filter_id') . ' = ' . $db->quoteName('f.j2commerce_filter_id')
            )
            ->where($db->quoteName('pf.product_id') . ' IN (' . $idList . ')')
            // Group by filter to avoid duplicates when multiple products share the same filter
            ->group([
                $db->quoteName('fg.j2commerce_filtergroup_id'),
                $db->quoteName('fg.group_name'),
                $db->quoteName('f.j2commerce_filter_id'),
                $db->quoteName('f.filter_name'),
            ]);
        }

        $db->setQuery($query);
        $results = $db->loadObjectList() ?: [];

        // Group results by filter group
        $grouped = [];

        foreach ($results as $row) {
            $groupId = (int) $row->j2commerce_filtergroup_id;

            if (!isset($grouped[$groupId])) {
                $grouped[$groupId] = [
                    'group_name' => $row->group_name,
                    'filters'    => [],
                ];
            }

            if ($row->j2commerce_filter_id) {
                $grouped[$groupId]['filters'][] = (object) [
                    'filter_id'   => (int) $row->j2commerce_filter_id,
                    'filter_name' => $row->filter_name,
                ];
            }
        }

        // Remove groups with no filters
        return array_filter($grouped, fn ($group) => !empty($group['filters']));
    }

    /**
     * Get sorting options for product listing.
     *
     * @return  array  Array of sorting options [value => label].
     *
     * @since   6.0.3
     */
    public static function getSortingOptions(): array
    {
        // Ensure frontend language file is loaded (this helper is in Administrator
        // but may be called from frontend where site language strings are needed)
        $lang = \Joomla\CMS\Factory::getLanguage();
        $lang->load('com_j2commerce', JPATH_SITE);

        return [
            'a.ordering'     => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_DEFAULT'),
            'a.title ASC'    => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_NAME_ASC'),
            'a.title DESC'   => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_NAME_DESC'),
            'v.price ASC'    => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_PRICE_ASC'),
            'v.price DESC'   => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_PRICE_DESC'),
            'a.created DESC' => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_NEWEST'),
            'p.hits DESC'    => \Joomla\CMS\Language\Text::_('COM_J2COMMERCE_SORT_POPULAR'),
        ];
    }

    /**
     * Instance-based get total cart quantity (alias for static method).
     *
     * @param   int  $variantId  The variant ID.
     *
     * @return  int  Total quantity in cart for this variant.
     *
     * @since   6.0.3
     */
    public function getCartQuantityForVariant(int $variantId): int
    {
        if ($variantId < 1) {
            return 0;
        }

        return self::getTotalCartQuantity($variantId);
    }

    /**
     * Instance-based method to load product and get data.
     *
     * Uses the product ID set via setId() to fetch the product.
     *
     * @return  object|null  The product object or null.
     *
     * @since   6.0.3
     */
    public function loadCurrentProduct(): ?object
    {
        $productId = $this->getId();

        if (!$productId) {
            return null;
        }

        return self::getProductById($productId);
    }

    /**
     * Instance-based method to check if current product exists.
     *
     * Uses the product ID set via setId() to check existence.
     *
     * @return  bool  True if product exists.
     *
     * @since   6.0.3
     */
    public function currentProductExists(): bool
    {
        $productId = $this->getId();

        if (!$productId) {
            return false;
        }

        return self::productExists($productId);
    }

    /**
     * Instance-based generate SKU for current product.
     *
     * @param   object  $variant  The variant object.
     *
     * @return  string  Generated SKU.
     *
     * @since   6.0.3
     */
    public function generateVariantSKU(object $variant): string
    {
        $productId = $this->getId();
        $product   = $productId ? self::getProductById($productId) : new \stdClass();

        return self::generateSKU($variant, $product);
    }
}
