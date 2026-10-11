<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\ProductTypeHelper;

/**
 * Event triggered when preparing Product schema data.
 *
 * This event allows third-party plugins to modify the Product schema
 * before it is output to the page. Plugins can add, modify, or remove
 * properties from the schema.
 *
 * Event name: onJ2CommerceSchemaProductPrepare
 *
 * Example usage in a plugin:
 * ```php
 * public static function getSubscribedEvents(): array
 * {
 *     return ['onJ2CommerceSchemaProductPrepare' => 'onProductPrepare'];
 * }
 *
 * public function onProductPrepare(ProductSchemaPrepareEvent $event): void
 * {
 *     $schema = $event->getSchema();
 *     $schema['customProperty'] = 'value';
 *     $event->setSchema($schema);
 * }
 * ```
 *
 * @since  6.0.0
 */
class ProductSchemaPrepareEvent extends AbstractSchemaEvent
{
    /**
     * Constructor.
     *
     * @param   string  $name       The event name.
     * @param   array   $arguments  The event arguments.
     *
     * @throws  \BadMethodCallException
     *
     * @since   6.0.0
     */
    public function __construct(string $name, array $arguments = [])
    {
        if (!\array_key_exists('product', $arguments)) {
            throw new \BadMethodCallException("Argument 'product' of event {$name} is required but has not been provided");
        }

        parent::__construct($name, $arguments);
    }

    /**
     * Setter for the subject argument (schema data).
     *
     * @param   array  $value  The value to set
     *
     * @return  array
     *
     * @since   6.0.0
     */
    protected function onSetSubject(array $value): array
    {
        return $value;
    }

    /**
     * Setter for the product argument.
     *
     * @param   object|null  $value  The product object
     *
     * @return  object|null
     *
     * @since   6.0.0
     */
    protected function onSetProduct(?object $value): ?object
    {
        return $value;
    }

    /**
     * Get the J2Commerce product object.
     *
     * @return  object|null  The product object or null
     *
     * @since   6.0.0
     */
    public function getProduct(): ?object
    {
        return $this->arguments['product'] ?? null;
    }

    /**
     * Get the article ID if available.
     *
     * @return  int|null  The article ID or null
     *
     * @since   6.0.0
     */
    public function getArticleId(): ?int
    {
        return $this->arguments['articleId'] ?? null;
    }

    /**
     * Check if the product is a variable product.
     *
     * @return  bool  True if variable product
     *
     * @since   6.0.0
     */
    public function isVariableProduct(): bool
    {
        $product = $this->getProduct();

        if (!$product) {
            return false;
        }

        return ProductTypeHelper::has($product->product_type ?? '', ProductTypeHelper::VARIANTS);
    }
}
