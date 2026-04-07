<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\Behavior\Configurable;
use J2Commerce\Component\J2commerce\Administrator\Model\Behavior\Downloadable;
use J2Commerce\Component\J2commerce\Administrator\Model\Behavior\Flexivariable;
use J2Commerce\Component\J2commerce\Administrator\Model\Behavior\Simple;
use J2Commerce\Component\J2commerce\Administrator\Model\Behavior\Variable;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductsModel;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/**
 * Product Service Class
 *
 * Centralizes product save/delete operations using the behavior system.
 * This ensures all product types trigger appropriate lifecycle events
 * (onBeforeSave, onAfterSave, onBeforeDelete) regardless of whether
 * the save originates from the admin component or the content plugin.
 *
 * Usage:
 *   $service = new ProductService();
 *   $service->saveProduct($productData);
 *   $service->deleteProduct($productId);
 *
 * @since 6.0.0
 */
class ProductService
{
    /**
     * MVC Factory for creating models and tables
     *
     * @var MVCFactoryInterface
     * @since 6.0.0
     */
    private MVCFactoryInterface $mvcFactory;

    /**
     * Cached behavior instances by product type
     *
     * @var array<string, object>
     * @since 6.0.0
     */
    private array $behaviors = [];

    /**
     * Constructor
     *
     * @param   MVCFactoryInterface|null  $mvcFactory  MVC Factory (optional)
     *
     * @since   6.0.0
     */
    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    /**
     * Get the behavior class for a product type
     *
     * Returns the appropriate behavior class based on product type.
     * Behaviors are cached for reuse within the same request.
     *
     * @param   string  $productType  The product type (simple, downloadable, flexivariable, etc.)
     *
     * @return  object  The behavior instance
     *
     * @since   6.0.0
     */
    public function getBehavior(string $productType): object
    {
        if (!isset($this->behaviors[$productType])) {
            $this->behaviors[$productType] = match ($productType) {
                'simple'        => new Simple($this->mvcFactory),
                'configurable'  => new Configurable($this->mvcFactory),
                'downloadable'  => new Downloadable($this->mvcFactory),
                'flexivariable' => new Flexivariable($this->mvcFactory),
                'variable'      => new Variable($this->mvcFactory),
                default         => $this->getPluginBehavior($productType),
            };
        }

        return $this->behaviors[$productType];
    }

    /**
     * Save a product using the proper behavior pattern
     *
     * This method:
     * 1. Gets the appropriate behavior based on product_type
     * 2. Calls onBeforeSave to validate/transform data
     * 3. Saves the main product record via Table class
     * 4. Calls onAfterSave to save related data (variants, options, images, etc.)
     *
     * @param   object|array  $data  The product data to save
     *
     * @return  int|false  The product ID on success, false on failure
     *
     * @throws  \Exception  On database errors
     *
     * @since   6.0.0
     */
    public function saveProduct(object|array $data): int|false
    {
        // Convert object to array if needed
        if (\is_object($data)) {
            $data = $this->objectToArray($data);
        }

        $productType = $data['product_type'] ?? 'simple';
        $behavior    = $this->getBehavior($productType);

        // Extract param-type fields from flat form data into params
        // These fields are submitted as flat keys (e.g. jform[attribs][j2commerce][product_css_class])
        // but must be stored in the params JSON column, not as direct DB columns
        $paramFields   = ['product_css_class'];
        $currentParams = $data['params'] ?? [];

        if (\is_string($currentParams)) {
            $currentParams = json_decode($currentParams, true) ?? [];
        }
        if (\is_object($currentParams)) {
            $currentParams = (array) $currentParams;
        }
        foreach ($paramFields as $field) {
            if (\array_key_exists($field, $data)) {
                $currentParams[$field] = $data[$field];
                unset($data[$field]);
            }
        }
        $data['params'] = $currentParams;

        // Create a ProductsModel for the behavior
        /** @var ProductsModel $model */
        $model = $this->mvcFactory->createModel('Products', 'Administrator', ['ignore_request' => true]);

        // Call onBeforeSave to validate and transform data
        $behavior->onBeforeSave($model, $data);

        // Save the main product record via Table
        /** @var ProductTable $productTable */
        $productTable = $this->mvcFactory->createTable('Product', 'Administrator');

        // Load existing record if updating
        if (!empty($data['j2commerce_product_id'])) {
            $productTable->load($data['j2commerce_product_id']);
        }

        // Bind data to table
        if (!$productTable->bind($data)) {
            throw new \Exception($productTable->getError());
        }

        // Explicitly set nullable integer fields that should be 0 when empty
        // Joomla's bind() may not overwrite existing values with 0 for nullable int columns
        $nullableIntFields = ['manufacturer_id', 'vendor_id', 'taxprofile_id'];
        foreach ($nullableIntFields as $field) {
            if (\array_key_exists($field, $data)) {
                $productTable->$field = (int) $data[$field];
            }
        }

        // Check and store
        if (!$productTable->check()) {
            throw new \Exception($productTable->getError());
        }

        if (!$productTable->store()) {
            throw new \Exception($productTable->getError());
        }

        // Update the product ID in data if new
        $data['j2commerce_product_id'] = $productTable->j2commerce_product_id;

        // Set the raw data in behavior for onAfterSave
        // Different behaviors have different methods for this
        if (method_exists($behavior, 'setRawData')) {
            $behavior->setRawData($data);
        } else {
            // For behaviors without setRawData, use reflection
            $reflectionClass = new \ReflectionClass($behavior);
            $rawDataProperty = $reflectionClass->getProperty('_rawData');
            $rawDataProperty->setAccessible(true);
            $rawDataProperty->setValue($behavior, $data);
        }

        // Create a wrapper that provides the getTable method the behavior expects
        $modelWrapper = new class ($productTable) {
            private ProductTable $table;

            public function __construct(ProductTable $table)
            {
                $this->table = $table;
            }

            public function getTable(): ProductTable
            {
                return $this->table;
            }

            public function getState(string $property, $default = null): mixed
            {
                return $default;
            }

            public function setState(string $property, $value): void
            {
                // No-op for wrapper
            }
        };

        // Call onAfterSave to save related data (variants, options, images, filters)
        $behavior->onAfterSave($modelWrapper);

        return (int) $productTable->j2commerce_product_id;
    }

    /**
     * Delete a product using the proper behavior pattern
     *
     * This method:
     * 1. Loads the product to get its type
     * 2. Gets the appropriate behavior based on product_type
     * 3. Calls onBeforeDelete to clean up related data
     * 4. Deletes the main product record
     *
     * @param   int  $productId  The product ID to delete
     *
     * @return  bool  True on success, false on failure
     *
     * @since   6.0.0
     */
    public function deleteProduct(int $productId): bool
    {
        if (!$productId) {
            return false;
        }

        // Load the product to get its type
        /** @var ProductTable $productTable */
        $productTable = $this->mvcFactory->createTable('Product', 'Administrator');

        if (!$productTable->load($productId)) {
            return false;
        }

        $productType = $productTable->product_type ?? 'simple';
        $behavior    = $this->getBehavior($productType);

        // Create a ProductsModel and set the product ID in state
        /** @var ProductsModel $model */
        $model = $this->mvcFactory->createModel('Products', 'Administrator', ['ignore_request' => true]);
        $model->setState('product.id', $productId);

        // Call onBeforeDelete to clean up related data (variants, etc.)
        $behavior->onBeforeDelete($model);

        // Delete the product record
        if (!$productTable->delete($productId)) {
            return false;
        }

        return true;
    }

    /**
     * Recursively convert an object to array
     *
     * @param   mixed  $data  The data to convert
     *
     * @return  mixed  The converted data
     *
     * @since   6.0.0
     */
    private function objectToArray(mixed $data): mixed
    {
        if (\is_object($data)) {
            $data = (array) $data;
        }

        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->objectToArray($value);
            }
        }

        return $data;
    }

    /**
     * Get the MVC Factory
     *
     * @return  MVCFactoryInterface  The MVC Factory
     *
     * @since   6.0.0
     */
    public function getMVCFactory(): MVCFactoryInterface
    {
        return $this->mvcFactory;
    }

    private function getPluginBehavior(string $productType): object
    {
        $event   = J2CommerceHelper::plugin()->event('GetProductBehavior', [$productType, $this->mvcFactory]);
        $results = $event->getArgument('result', []);

        foreach ($results as $result) {
            if (\is_object($result)) {
                return $result;
            }
        }

        return new Simple($this->mvcFactory);
    }
}
