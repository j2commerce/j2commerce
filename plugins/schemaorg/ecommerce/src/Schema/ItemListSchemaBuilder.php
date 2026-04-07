<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Schema;

use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ItemListSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ItemList Schema Builder
 *
 * Generates schema.org/ItemList JSON-LD structured data
 * for category/product list pages.
 *
 * @since  6.0.0
 */
class ItemListSchemaBuilder
{
    /**
     * J2Commerce Schema Helper instance
     *
     * @var    J2CommerceSchemaHelper
     * @since  6.0.0
     */
    private J2CommerceSchemaHelper $helper;

    /**
     * Database interface
     *
     * @var    DatabaseInterface
     * @since  6.0.0
     */
    private DatabaseInterface $db;

    /**
     * Plugin parameters
     *
     * @var    Registry
     * @since  6.0.0
     */
    private Registry $params;

    /**
     * Event dispatcher for third-party integration
     *
     * @var    DispatcherInterface|null
     * @since  6.0.0
     */
    private ?DispatcherInterface $dispatcher;

    /**
     * Constructor
     *
     * @param   J2CommerceSchemaHelper       $helper      The schema helper
     * @param   DatabaseInterface         $db          Database interface
     * @param   Registry                  $params      Plugin parameters
     * @param   DispatcherInterface|null  $dispatcher  Event dispatcher
     *
     * @since   6.0.0
     */
    public function __construct(
        J2CommerceSchemaHelper $helper,
        DatabaseInterface $db,
        Registry $params,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->helper     = $helper;
        $this->db         = $db;
        $this->params     = $params;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Build ItemList schema for a J2Commerce category page
     *
     * @param   int  $categoryId  The category ID
     * @param   int  $page        Current page number (1-indexed)
     * @param   int  $limit       Items per page
     *
     * @return  array  The ItemList schema
     *
     * @since   6.0.0
     */
    public function buildForCategory(int $categoryId, int $page = 1, int $limit = 20): array
    {
        $category = $this->getCategoryData($categoryId);
        $products = $this->getCategoryProducts($categoryId, $page, $limit);

        $schema = [
            '@type'       => 'ItemList',
            'name'        => $category ? $category->productcategory_name : 'Products',
            'description' => $category ? strip_tags($category->productcategory_description ?? '') : '',
        ];

        // Build list items
        $items    = [];
        $position = (($page - 1) * $limit) + 1;

        foreach ($products as $product) {
            $items[] = $this->createProductListItem($product, $position++);
        }

        if (!empty($items)) {
            $schema['itemListElement'] = $items;
            $schema['numberOfItems']   = \count($items);
        }

        // Clean empty values
        $schema = $this->cleanSchemaData($schema);

        // Dispatch event for third-party modifications
        return $this->dispatchItemListEvent($schema, $categoryId, $category, $page, $limit);
    }

    /**
     * Build ItemList schema from an array of products
     *
     * @param   array   $products  Array of product objects
     * @param   string  $name      List name
     * @param   string  $description  List description
     *
     * @return  array  The ItemList schema
     *
     * @since   6.0.0
     */
    public function buildFromProducts(array $products, string $name = 'Products', string $description = ''): array
    {
        $schema = [
            '@type' => 'ItemList',
            'name'  => $name,
        ];

        if (!empty($description)) {
            $schema['description'] = $description;
        }

        // Build list items
        $items    = [];
        $position = 1;

        foreach ($products as $product) {
            $items[] = $this->createProductListItem($product, $position++);
        }

        if (!empty($items)) {
            $schema['itemListElement'] = $items;
            $schema['numberOfItems']   = \count($items);
        }

        return $this->cleanSchemaData($schema);
    }

    /**
     * Build ItemList for search results
     *
     * @param   array   $products     Array of product objects
     * @param   string  $searchQuery  The search query
     * @param   int     $totalResults Total number of results
     *
     * @return  array  The ItemList schema
     *
     * @since   6.0.0
     */
    public function buildForSearchResults(array $products, string $searchQuery, int $totalResults = 0): array
    {
        $schema = [
            '@type'         => 'ItemList',
            'name'          => 'Search Results for: ' . $searchQuery,
            'numberOfItems' => $totalResults > 0 ? $totalResults : \count($products),
        ];

        // Build list items
        $items    = [];
        $position = 1;

        foreach ($products as $product) {
            $items[] = $this->createProductListItem($product, $position++);
        }

        if (!empty($items)) {
            $schema['itemListElement'] = $items;
        }

        return $this->cleanSchemaData($schema);
    }

    /**
     * Create a ListItem for a product
     *
     * @param   object  $product   The product object
     * @param   int     $position  The position in the list
     *
     * @return  array  The ListItem schema
     *
     * @since   6.0.0
     */
    private function createProductListItem(object $product, int $position): array
    {
        $productSchema = [
            '@type' => 'Product',
            'name'  => $this->helper->getProductName($product),
            'url'   => $this->helper->getProductUrl($product),
        ];

        // Add image if available
        $images = $this->helper->getAllProductImages($product);

        if (!empty($images)) {
            $productSchema['image'] = $images[0];
        }

        // Add SKU if available
        if (isset($product->variant->sku) && !empty($product->variant->sku)) {
            $productSchema['sku'] = $product->variant->sku;
        }

        // Add basic offer info
        if (isset($product->variant)) {
            $productSchema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) number_format((float) ($product->variant->price ?? 0), 2, '.', ''),
                'priceCurrency' => $this->helper->getCurrencyCode(),
                'availability'  => $this->helper->mapAvailability($product->variant),
            ];
        }

        return [
            '@type'    => 'ListItem',
            'position' => $position,
            'item'     => $productSchema,
        ];
    }

    /**
     * Get category data
     *
     * @param   int  $categoryId  The category ID
     *
     * @return  object|null  The category object
     *
     * @since   6.0.0
     */
    private function getCategoryData(int $categoryId): ?object
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('c.id, c.title, c.alias, c.description, c.published, c.language')
                ->from($this->db->quoteName('#__categories', 'c'))
                ->where($this->db->quoteName('c.id') . ' = :categoryId')
                ->where($this->db->quoteName('c.extension') . ' = ' . $this->db->quote('com_content'))
                ->bind(':categoryId', $categoryId, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query);

            return $this->db->loadObject();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get products in a category
     *
     * @param   int  $categoryId  The category ID
     * @param   int  $page        Current page number
     * @param   int  $limit       Items per page
     *
     * @return  array  Array of product objects
     *
     * @since   6.0.0
     */
    private function getCategoryProducts(int $categoryId, int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;

            // Query products in category via content article catid
            $query = $this->db->getQuery(true)
                ->select('p.*')
                ->from($this->db->quoteName('#__j2commerce_products', 'p'))
                ->join(
                    'INNER',
                    $this->db->quoteName('#__content', 'a')
                    . ' ON ' . $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('p.product_source_id')
                )
                ->where($this->db->quoteName('a.catid') . ' = :categoryId')
                ->where($this->db->quoteName('p.enabled') . ' = 1')
                ->where($this->db->quoteName('p.product_source') . ' = ' . $this->db->quote('com_content'))
                ->order($this->db->quoteName('a.ordering') . ' ASC')
                ->bind(':categoryId', $categoryId, \Joomla\Database\ParameterType::INTEGER);

            $this->db->setQuery($query, $offset, $limit);
            $products = $this->db->loadObjectList();

            // Load additional data for each product
            foreach ($products as $product) {
                $this->loadProductDetails($product);
            }

            return $products;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Load additional product details
     *
     * @param   object  $product  The product object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    private function loadProductDetails(object $product): void
    {
        $productId = (int) $product->j2commerce_product_id;

        // Load master variant
        $product->variant = $this->helper->getMasterVariant($productId);

        // Load images
        $product->images = $this->helper->getProductImages($productId);

        // Load article data for name/description
        if ($product->product_source === 'com_content' && $product->product_source_id) {
            $product->source = $this->helper->getArticleData((int) $product->product_source_id);
        }
    }

    /**
     * Clean empty values from schema data
     *
     * @param   array  $data  The schema data
     *
     * @return  array  The cleaned data
     *
     * @since   6.0.0
     */
    private function cleanSchemaData(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->cleanSchemaData($value);

                if (empty($value)) {
                    continue;
                }
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }

    /**
     * Dispatch ItemList schema prepare event
     *
     * @param   array        $schema      The schema data
     * @param   int          $categoryId  The category ID
     * @param   object|null  $category    The category object
     * @param   int          $page        Current page
     * @param   int          $limit       Items per page
     *
     * @return  array  The modified schema
     *
     * @since   6.0.0
     */
    private function dispatchItemListEvent(
        array $schema,
        int $categoryId,
        ?object $category,
        int $page,
        int $limit
    ): array {
        if (!$this->dispatcher) {
            return $schema;
        }

        $event = new ItemListSchemaPrepareEvent(
            'onJ2CommerceSchemaItemListPrepare',
            [
                'subject'    => $schema,
                'categoryId' => $categoryId,
                'category'   => $category,
                'page'       => $page,
                'limit'      => $limit,
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaItemListPrepare', $event);

        return $event->getSchema();
    }
}
