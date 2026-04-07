<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Schema;

use Joomla\CMS\Uri\Uri;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\OffersSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ProductSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\ReviewsSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Event\VariantSchemaPrepareEvent;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Product Schema Builder
 *
 * Generates schema.org/Product JSON-LD structured data from J2Commerce product data.
 * Supports both simple products and variable products (ProductGroup).
 *
 * @since  6.0.0
 */
class ProductSchemaBuilder
{
    /**
     * J2Commerce Schema Helper instance
     *
     * @var    J2CommerceSchemaHelper
     * @since  6.0.0
     */
    private J2CommerceSchemaHelper $helper;

    /**
     * Event dispatcher for third-party integration
     *
     * @var    DispatcherInterface|null
     * @since  6.0.0
     */
    private ?DispatcherInterface $dispatcher;

    /**
     * Plugin parameters
     *
     * @var    Registry
     * @since  6.0.0
     */
    private Registry $params;

    /**
     * Constructor
     *
     * @param   J2CommerceSchemaHelper       $helper      The schema helper
     * @param   Registry                  $params      Plugin parameters
     * @param   DispatcherInterface|null  $dispatcher  Event dispatcher for hooks
     *
     * @since   6.0.0
     */
    public function __construct(
        J2CommerceSchemaHelper $helper,
        Registry $params,
        ?DispatcherInterface $dispatcher = null
    ) {
        $this->helper     = $helper;
        $this->params     = $params;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Build a Product schema from product data and form overrides.
     *
     * @param   object|null  $product    The J2Commerce product object
     * @param   array        $overrides  Form override values
     * @param   int|null     $articleId  The article ID for context
     *
     * @return  array  The Product or ProductGroup schema
     *
     * @since   6.0.0
     */
    public function build(?object $product, array $overrides = [], ?int $articleId = null): array
    {
        if (!$product) {
            return $this->buildFromOverridesOnly($overrides);
        }

        // Determine if this is a variable product
        $isVariable = $this->helper->isVariableProduct($product);

        if ($isVariable && \count($product->variants ?? []) > 1) {
            return $this->buildProductGroup($product, $overrides, $articleId);
        }

        return $this->buildSimpleProduct($product, $overrides, $articleId);
    }

    /**
     * Build a simple Product schema
     *
     * @param   object    $product    The J2Commerce product object
     * @param   array     $overrides  Form override values
     * @param   int|null  $articleId  The article ID
     *
     * @return  array  The Product schema
     *
     * @since   6.0.0
     */
    public function buildSimpleProduct(object $product, array $overrides = [], ?int $articleId = null): array
    {
        $schema = [
            '@type' => 'Product',
        ];

        // Name - override takes priority
        $schema['name'] = !empty($overrides['name'])
            ? $overrides['name']
            : $this->helper->getProductName($product);

        // Description
        if (!empty($overrides['description'])) {
            $schema['description'] = strip_tags($overrides['description']);
        } else {
            $description = $this->helper->getProductDescription($product, 5000);

            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }

        // Images
        $images = $this->buildImagesArray($overrides, $product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        // SKU
        $schema['sku'] = !empty($overrides['sku'])
            ? $overrides['sku']
            : ($product->variant->sku ?? '');

        // GTIN/UPC
        if (!empty($overrides['gtin'])) {
            $schema['gtin'] = $overrides['gtin'];
        } elseif (!empty($product->variant->upc)) {
            $schema['gtin'] = $product->variant->upc;
        }

        // MPN
        if (!empty($overrides['mpn'])) {
            $schema['mpn'] = $overrides['mpn'];
        }

        // Brand
        $brand = $this->buildBrandSchema($overrides, $product);

        if ($brand) {
            $schema['brand'] = $brand;
        }

        // Offers
        $schema['offers'] = $this->buildOffersSchema($overrides, $product);

        // URL
        $schema['url'] = $this->helper->getProductUrl($product);

        // Clean empty values
        $schema = $this->cleanSchemaData($schema);

        // Dispatch events for third-party modifications
        $schema = $this->dispatchProductEvent($schema, $product, $articleId);
        $schema = $this->dispatchReviewsEvent($schema, (int) $product->j2commerce_product_id, $articleId);

        return $schema;
    }

    /**
     * Build a ProductGroup schema for variable products
     *
     * @param   object    $product    The J2Commerce product object
     * @param   array     $overrides  Form override values
     * @param   int|null  $articleId  The article ID
     *
     * @return  array  The ProductGroup schema
     *
     * @since   6.0.0
     */
    public function buildProductGroup(object $product, array $overrides = [], ?int $articleId = null): array
    {
        $schema = [
            '@type'          => 'ProductGroup',
            'productGroupID' => 'pg-' . $product->j2commerce_product_id,
        ];

        // Name
        $schema['name'] = !empty($overrides['name'])
            ? $overrides['name']
            : $this->helper->getProductName($product);

        // Description
        if (!empty($overrides['description'])) {
            $schema['description'] = strip_tags($overrides['description']);
        } else {
            $description = $this->helper->getProductDescription($product, 5000);

            if (!empty($description)) {
                $schema['description'] = $description;
            }
        }

        // Images
        $images = $this->buildImagesArray($overrides, $product);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        // Brand
        $brand = $this->buildBrandSchema($overrides, $product);

        if ($brand) {
            $schema['brand'] = $brand;
        }

        // URL
        $schema['url'] = $this->helper->getProductUrl($product);

        // VariesBy - determine what properties vary
        $variesBy = $this->buildVariesByArray($product);

        if (!empty($variesBy)) {
            $schema['variesBy'] = $variesBy;
        }

        // HasVariant - build variant products
        $variants = $this->buildVariantSchemas($product, $overrides);

        if (!empty($variants)) {
            $schema['hasVariant'] = $variants;
        }

        // Clean and dispatch events
        $schema = $this->cleanSchemaData($schema);
        $schema = $this->dispatchProductEvent($schema, $product, $articleId);
        $schema = $this->dispatchReviewsEvent($schema, (int) $product->j2commerce_product_id, $articleId);

        return $schema;
    }

    /**
     * Build schema from overrides only (no product data)
     *
     * @param   array  $overrides  Form override values
     *
     * @return  array  The Product schema
     *
     * @since   6.0.0
     */
    private function buildFromOverridesOnly(array $overrides): array
    {
        $schema = [
            '@type' => 'Product',
        ];

        if (!empty($overrides['name'])) {
            $schema['name'] = $overrides['name'];
        }

        if (!empty($overrides['description'])) {
            $schema['description'] = strip_tags($overrides['description']);
        }

        if (!empty($overrides['sku'])) {
            $schema['sku'] = $overrides['sku'];
        }

        if (!empty($overrides['gtin'])) {
            $schema['gtin'] = $overrides['gtin'];
        }

        if (!empty($overrides['mpn'])) {
            $schema['mpn'] = $overrides['mpn'];
        }

        // Images from overrides
        $images = $this->buildImagesArray($overrides, null);

        if (!empty($images)) {
            $schema['image'] = \count($images) === 1 ? $images[0] : $images;
        }

        // Brand from overrides
        if (!empty($overrides['brand']['name'])) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $overrides['brand']['name'],
            ];
        }

        // Offers from overrides
        if (!empty($overrides['offers'])) {
            $schema['offers'] = $this->buildOffersFromOverrides($overrides['offers']);
        }

        return $this->cleanSchemaData($schema);
    }

    /**
     * Build array of image URLs
     *
     * @param   array        $overrides  Form override values
     * @param   object|null  $product    The product object
     *
     * @return  array  Array of image URLs
     *
     * @since   6.0.0
     */
    private function buildImagesArray(array $overrides, ?object $product): array
    {
        $images = [];

        // Main image from overrides
        if (!empty($overrides['image'])) {
            $images[] = $this->prepareImageUrl($overrides['image']);
        }

        // Additional images from overrides
        if (!empty($overrides['additionalImages']) && \is_array($overrides['additionalImages'])) {
            foreach ($overrides['additionalImages'] as $img) {
                if (!empty($img['image'])) {
                    $images[] = $this->prepareImageUrl($img['image']);
                }
            }
        }

        // If no override images, get from product
        if (empty($images) && $product) {
            $productImages = $this->helper->getAllProductImages($product);
            $images        = array_merge($images, $productImages);
        }

        return array_unique(array_filter($images));
    }

    /**
     * Prepare an image URL (ensure absolute)
     *
     * @param   string  $imagePath  The image path
     *
     * @return  string  The full image URL
     *
     * @since   6.0.0
     */
    private function prepareImageUrl(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        // Already absolute — encode spaces
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return str_replace(' ', '%20', $imagePath);
        }

        return Uri::root() . str_replace(' ', '%20', ltrim($imagePath, '/'));
    }

    /**
     * Build Brand schema
     *
     * @param   array        $overrides  Form override values
     * @param   object|null  $product    The product object
     *
     * @return  array|null  The Brand schema or null
     *
     * @since   6.0.0
     */
    private function buildBrandSchema(array $overrides, ?object $product): ?array
    {
        // Check override first
        if (!empty($overrides['brand']['name'])) {
            return [
                '@type' => 'Brand',
                'name'  => $overrides['brand']['name'],
            ];
        }

        // Check product manufacturer
        if ($product && isset($product->manufacturer) && !empty($product->manufacturer->company)) {
            return [
                '@type' => 'Brand',
                'name'  => $product->manufacturer->company,
            ];
        }

        // Use default from params, fall back to store name
        $defaultBrand = $this->params->get('default_brand', '');

        if (empty($defaultBrand)) {
            $defaultBrand = $this->helper->getStoreName();
        }

        if (!empty($defaultBrand)) {
            return [
                '@type' => 'Brand',
                'name'  => $defaultBrand,
            ];
        }

        return null;
    }

    /**
     * Build Offers schema
     *
     * @param   array        $overrides  Form override values
     * @param   object|null  $product    The product object
     *
     * @return  array  The Offer schema
     *
     * @since   6.0.0
     */
    private function buildOffersSchema(array $overrides, ?object $product): array
    {
        $offer = [
            '@type' => 'Offer',
        ];

        // Price - override takes priority
        if (!empty($overrides['offers']['price'])) {
            $offer['price'] = (string) number_format((float) $overrides['offers']['price'], 2, '.', '');
        } elseif ($product && isset($product->variant)) {
            $price          = $this->helper->getProductPrice($product->variant);
            $offer['price'] = (string) number_format($price, 2, '.', '');
        }

        // Currency
        if (!empty($overrides['offers']['priceCurrency'])) {
            $offer['priceCurrency'] = $overrides['offers']['priceCurrency'];
        } else {
            $offer['priceCurrency'] = $this->helper->getCurrencyCode();
        }

        // Availability
        if (!empty($overrides['offers']['availability'])) {
            $offer['availability'] = $overrides['offers']['availability'];
        } elseif ($product && isset($product->variant)) {
            $offer['availability'] = $this->helper->mapAvailability($product->variant);
        } else {
            $offer['availability'] = 'https://schema.org/InStock';
        }

        // URL
        if (!empty($overrides['offers']['url'])) {
            $offer['url'] = $overrides['offers']['url'];
        } elseif ($product) {
            $offer['url'] = $this->helper->getProductUrl($product);
        }

        // Price Valid Until
        if (!empty($overrides['offers']['priceValidUntil'])) {
            $offer['priceValidUntil'] = $this->prepareDate($overrides['offers']['priceValidUntil']);
        }

        // Seller
        $seller = $this->buildSellerSchema($overrides);

        if ($seller) {
            $offer['seller'] = $seller;
        }

        // Dispatch offers event for third-party modifications
        if ($product && isset($product->variant)) {
            $offer = $this->dispatchOffersEvent($offer, $product->variant, (int) $product->j2commerce_product_id);
        }

        return $offer;
    }

    /**
     * Build Offers schema from overrides only
     *
     * @param   array  $offersData  The offers override data
     *
     * @return  array  The Offer schema
     *
     * @since   6.0.0
     */
    private function buildOffersFromOverrides(array $offersData): array
    {
        $offer = [
            '@type' => 'Offer',
        ];

        if (!empty($offersData['price'])) {
            $offer['price'] = (string) number_format((float) $offersData['price'], 2, '.', '');
        }

        if (!empty($offersData['priceCurrency'])) {
            $offer['priceCurrency'] = $offersData['priceCurrency'];
        } else {
            $offer['priceCurrency'] = $this->helper->getCurrencyCode();
        }

        if (!empty($offersData['availability'])) {
            $offer['availability'] = $offersData['availability'];
        }

        if (!empty($offersData['url'])) {
            $offer['url'] = $offersData['url'];
        }

        if (!empty($offersData['priceValidUntil'])) {
            $offer['priceValidUntil'] = $this->prepareDate($offersData['priceValidUntil']);
        }

        return $offer;
    }

    /**
     * Build Seller schema
     *
     * @param   array  $overrides  Form override values
     *
     * @return  array|null  The Seller schema or null
     *
     * @since   6.0.0
     */
    private function buildSellerSchema(array $overrides): ?array
    {
        // Check override
        if (!empty($overrides['offers']['seller']['name'])) {
            return [
                '@type' => 'Organization',
                'name'  => $overrides['offers']['seller']['name'],
            ];
        }

        // Use default from params or J2Commerce config
        $sellerName = $this->params->get('organization_name', '');

        if (empty($sellerName)) {
            $sellerName = $this->helper->getStoreName();
        }

        if (!empty($sellerName)) {
            return [
                '@type' => 'Organization',
                'name'  => $sellerName,
            ];
        }

        return null;
    }

    /**
     * Build VariesBy array for ProductGroup
     *
     * @param   object  $product  The product object
     *
     * @return  array  Array of schema.org property URLs
     *
     * @since   6.0.0
     */
    private function buildVariesByArray(object $product): array
    {
        $options  = $this->helper->getVariantOptions((int) $product->j2commerce_product_id);
        $variesBy = [];

        foreach ($options as $optionName) {
            $variesBy[] = $this->helper->mapOptionToSchemaProperty($optionName);
        }

        return array_unique($variesBy);
    }

    /**
     * Build variant Product schemas
     *
     * @param   object  $product    The product object
     * @param   array   $overrides  Form override values
     *
     * @return  array  Array of variant Product schemas
     *
     * @since   6.0.0
     */
    private function buildVariantSchemas(object $product, array $overrides): array
    {
        $variants  = [];
        $productId = (int) $product->j2commerce_product_id;

        foreach ($product->variants as $variant) {
            // Skip master variant for variable products
            if ((int) $variant->is_master === 1) {
                continue;
            }

            $variantSchema = [
                '@type' => 'Product',
            ];

            // SKU
            if (!empty($variant->sku)) {
                $variantSchema['sku'] = $variant->sku;
            }

            // Name with variant identifier
            $variantSchema['name'] = $this->helper->getProductName($product);

            if (!empty($variant->variant_name)) {
                $variantSchema['name'] .= ' - ' . $variant->variant_name;
            }

            // GTIN/UPC
            if (!empty($variant->upc)) {
                $variantSchema['gtin'] = $variant->upc;
            }

            // Offers for this variant
            $variantOffer = [
                '@type'         => 'Offer',
                'price'         => (string) number_format((float) $variant->price, 2, '.', ''),
                'priceCurrency' => $this->helper->getCurrencyCode(),
                'availability'  => $this->helper->mapAvailability($variant),
            ];

            // Add seller if configured
            $seller = $this->buildSellerSchema($overrides);

            if ($seller) {
                $variantOffer['seller'] = $seller;
            }

            // Dispatch offers event for this variant
            $variantSchema['offers'] = $this->dispatchOffersEvent($variantOffer, $variant, $productId);

            // Dispatch variant event for third-party modifications
            $variantSchema = $this->dispatchVariantEvent($variantSchema, $variant, $productId, $product);

            $variants[] = $variantSchema;
        }

        return $variants;
    }

    /**
     * Prepare a date for schema output
     *
     * @param   string  $date  The date string
     *
     * @return  string  ISO 8601 formatted date
     *
     * @since   6.0.0
     */
    private function prepareDate(string $date): string
    {
        try {
            $dateTime = new \DateTime($date);

            return $dateTime->format('Y-m-d');
        } catch (\Exception $e) {
            return $date;
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
     * Dispatch product schema prepare event
     *
     * @param   array        $schema     The schema data
     * @param   object|null  $product    The product object
     * @param   int|null     $articleId  The article ID
     *
     * @return  array  The modified schema
     *
     * @since   6.0.0
     */
    private function dispatchProductEvent(array $schema, ?object $product, ?int $articleId): array
    {
        if (!$this->dispatcher) {
            return $schema;
        }

        $event = new ProductSchemaPrepareEvent(
            'onJ2CommerceSchemaProductPrepare',
            [
                'subject'   => $schema,
                'product'   => $product,
                'articleId' => $articleId,
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaProductPrepare', $event);

        return $event->getSchema();
    }

    /**
     * Dispatch reviews schema prepare event
     *
     * @param   array     $schema     The schema data
     * @param   int       $productId  The product ID
     * @param   int|null  $articleId  The article ID
     *
     * @return  array  The modified schema
     *
     * @since   6.0.0
     */
    private function dispatchReviewsEvent(array $schema, int $productId, ?int $articleId): array
    {
        if (!$this->dispatcher) {
            return $schema;
        }

        $event = new ReviewsSchemaPrepareEvent(
            'onJ2CommerceSchemaReviewsPrepare',
            [
                'subject'   => $schema,
                'productId' => $productId,
                'articleId' => $articleId,
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaReviewsPrepare', $event);

        return $event->getSchema();
    }

    /**
     * Dispatch offers schema prepare event
     *
     * @param   array   $offerSchema  The offer schema
     * @param   object  $variant      The variant object
     * @param   int     $productId    The product ID
     *
     * @return  array  The modified offer schema
     *
     * @since   6.0.0
     */
    private function dispatchOffersEvent(array $offerSchema, object $variant, int $productId): array
    {
        if (!$this->dispatcher) {
            return $offerSchema;
        }

        $event = new OffersSchemaPrepareEvent(
            'onJ2CommerceSchemaOffersPrepare',
            [
                'subject'   => $offerSchema,
                'variant'   => $variant,
                'productId' => $productId,
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaOffersPrepare', $event);

        return $event->getSchema();
    }

    /**
     * Dispatch variant schema prepare event
     *
     * @param   array   $variantSchema  The variant schema
     * @param   object  $variant        The variant object
     * @param   int     $productId      The product ID
     * @param   object  $parentProduct  The parent product
     *
     * @return  array  The modified variant schema
     *
     * @since   6.0.0
     */
    private function dispatchVariantEvent(array $variantSchema, object $variant, int $productId, object $parentProduct): array
    {
        if (!$this->dispatcher) {
            return $variantSchema;
        }

        $event = new VariantSchemaPrepareEvent(
            'onJ2CommerceSchemaVariantPrepare',
            [
                'subject'        => $variantSchema,
                'variant'        => $variant,
                'productId'      => $productId,
                'parentProduct'  => $parentProduct,
                'variantOptions' => [],
            ]
        );

        $this->dispatcher->dispatch('onJ2CommerceSchemaVariantPrepare', $event);

        return $event->getSchema();
    }
}
