<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model\Behavior;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductOptionsModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VariantsModel;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductfileTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductfilterTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductimageTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductoptionTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductquantityTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use J2Commerce\Component\J2commerce\Administrator\Table\VariantTable;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Registry\Registry;

/**
 * Downloadable Product Behavior Class
 *
 * Handles lifecycle events for downloadable (digital) products.
 * Downloadable products are similar to simple products with a single
 * variant but represent digital goods that don't require shipping.
 *
 * @since 6.0.0
 */
class Downloadable
{
    /**
     * Raw data storage for passing between events
     *
     * @var  array
     * @since 6.0.0
     */
    private array $_rawData = [];

    /**
     * MVC Factory for creating models and tables
     *
     * @var  MVCFactoryInterface
     * @since 6.0.0
     */
    protected MVCFactoryInterface $mvcFactory;

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
     * After get item event handler
     *
     * Called after ProductsModel retrieves a single product record.
     * Loads variant data and product options for downloadable products.
     *
     */
    public function onAfterGetItem(object &$model, object &$record): void
    {
        if ($record->product_type !== 'downloadable') {
            return;
        }

        $app = Factory::getApplication();

        if ($record->j2commerce_product_id) {
            /** @var VariantsModel $variantModel */
            $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
            $variantModel->setState('filter.product_type', $record->product_type);
            $variantModel->setState('filter.product_id', $record->j2commerce_product_id);

            // Downloadable products have only one variant (like simple products)
            try {
                $variants         = $variantModel->getItems();
                $record->variants = $variants[0] ?? new \stdClass();
            } catch (\Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'error');
                $record->variants = $this->mvcFactory->createTable('Variant', 'Administrator');
            }

            // Load product options
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator');
                $optionsModel->setState('filter.product_id', $record->j2commerce_product_id);
                $optionsModel->setState('filter.parent_id', null);
                $optionsModel->setState('list.limit', 0);
                $optionsModel->setState('list.start', 0);

                $record->product_options = $optionsModel->getItems();
            } catch (\Exception $e) {
                $app->enqueueMessage($e->getMessage(), 'error');
                $record->product_options = [];
            }

            // Process params as Registry
            $registry       = new Registry($record->params);
            $record->params = $registry;
        }
    }

    /**
     * Before save event handler
     *
     * Called before ProductModel saves a product record.
     * Validates and transforms data before persistence.
     *
     */
    public function onBeforeSave(object &$model, array &$data): void
    {
        if (!isset($data['product_type']) || $data['product_type'] !== 'downloadable') {
            return;
        }

        $app           = Factory::getApplication();
        $utilityHelper = J2CommerceHelper::utilities();

        // Set default visibility
        if (!isset($data['visibility'])) {
            $data['visibility'] = 1;
        }

        // Process cross sells (array to CSV)
        $data['cross_sells'] = isset($data['cross_sells'])
            ? $utilityHelper->to_csv($data['cross_sells'])
            : '';

        // Process up sells (array to CSV)
        $data['up_sells'] = isset($data['up_sells'])
            ? $utilityHelper->to_csv($data['up_sells'])
            : '';

        // Process shipping methods (typically not needed for downloadable but maintain compatibility)
        if (isset($data['shippingmethods']) && !empty($data['shippingmethods'])) {
            $data['shippingmethods'] = implode(',', $data['shippingmethods']);
        }

        // Process item options
        if (isset($data['item_options']) && \is_object($data['item_options'])) {
            $data['item_options'] = (array) $data['item_options'];
        }

        if (isset($data['item_options']) && \count($data['item_options']) > 0) {
            $data['has_options'] = 1;
        }

        // Cast integer fields
        $integerFields = [
            'taxprofile_id',
            'manufacturer_id',
            'vendor_id',
            'isdefault_variant',
            'length_class_id',
            'weight_class_id',
        ];
        foreach ($integerFields as $key) {
            $data[$key] = isset($data[$key]) && !empty($data[$key])
                ? (int) $data[$key]
                : 0;
        }

        // Cast float fields
        $floatFields = [
            'price',
            'length',
            'width',
            'height',
            'weight',
            'min_sale_qty',
            'max_sale_qty',
            'notify_qty',
        ];
        foreach ($floatFields as $key) {
            $data[$key] = isset($data[$key]) && !empty($data[$key])
                ? (float) $data[$key]
                : 0.0;
        }

        // Process quantity data
        if (\is_object($data['quantity'] ?? null)) {
            if (!isset($data['quantity']->product_attributes) || empty($data['quantity']->product_attributes)) {
                $data['quantity']->product_attributes = '';
            }

            $data['quantity']->quantity = isset($data['quantity']->quantity) && !empty($data['quantity']->quantity)
                ? (int) $data['quantity']->quantity
                : 0;
        }

        // Validate min/max sale quantities
        if (isset($data['max_sale_qty'], $data['min_sale_qty'])
            && !empty($data['max_sale_qty'])
            && !empty($data['min_sale_qty'])
            && ($data['max_sale_qty'] < $data['min_sale_qty'])) {
            $data['min_sale_qty'] = 0;
            $app->enqueueMessage(
                Text::_('COM_J2COMMERCE_MAX_SALE_QTY_MUST_BE_GREATER_THAN_MIN'),
                'warning'
            );
        }

        // Merge existing params - preserve existing when no new params provided
        if (!empty($data['j2commerce_product_id'])) {
            /** @var ProductTable $product */
            $product = $this->mvcFactory->createTable('Product', 'Administrator');
            $product->load($data['j2commerce_product_id']);

            if (isset($product->params)) {
                $existingParams = json_decode($product->params, true) ?? [];
                if (!isset($data['params']) || empty($data['params'])) {
                    // Preserve existing params if no new params are provided
                    $data['params'] = $existingParams;
                } else {
                    // Merge new params with existing (new values override existing)
                    $data['params'] = array_merge($existingParams, (array) $data['params']);
                }
            }
        }

        // Encode params for storage
        if (!empty($data['params'])) {
            $data['params'] = json_encode($data['params']);
        } else {
            // Ensure params is at least an empty JSON object, not null
            $data['params'] = '{}';
        }

        // Store raw data for onAfterSave
        $this->_rawData = $data;
    }

    /**
     * After save event handler
     *
     * Called after ProductModel saves a product record.
     * Saves related data (variant, options, images, inventory, filters).
     *
     * @throws \Exception
     */
    public function onAfterSave(object &$model): void
    {
        if (empty($this->_rawData)) {
            return;
        }

        $table = $model->getTable();

        if ($table->product_type !== 'downloadable') {
            return;
        }

        // Save variant data — load existing master variant first so store() does UPDATE not INSERT
        /** @var VariantTable $variant */
        $variant = $this->mvcFactory->createTable('Variant', 'Administrator');
        if (!$variant) {
            throw new \RuntimeException('Unable to create Variant table instance.');
        }

        // Try to load existing master variant by product_id
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_variant_id'))
            ->from($db->quoteName('#__j2commerce_variants'))
            ->where($db->quoteName('product_id') . ' = :productId')
            ->where($db->quoteName('is_master') . ' = 1')
            ->bind(':productId', $table->j2commerce_product_id, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query);
        $existingVariantId = (int) $db->loadResult();

        if ($existingVariantId) {
            $variant->load($existingVariantId);
        }

        $variant->bind($this->_rawData);
        $variant->is_master  = 1;
        $variant->product_id = $table->j2commerce_product_id;
        $variant->shipping   = 0;
        $variant->check();
        $variant->store();

        // Save item options
        if (isset($this->_rawData['item_options'])) {
            foreach ($this->_rawData['item_options'] as $item) {
                if (\is_array($item)) {
                    $item = (object) $item;
                }
                /** @var ProductoptionTable $poption */
                $poption = $this->mvcFactory->createTable('Productoption', 'Administrator');
                if (!$poption) {
                    throw new \RuntimeException('Unable to create Productoption table instance.');
                }
                $item->product_id = $table->j2commerce_product_id;
                try {
                    $poption->save($item);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }

        // Save inventory/quantity
        if (isset($this->_rawData['quantity'])) {
            $inventory = $this->_rawData['quantity'];
            /** @var ProductquantityTable $productQuantity */
            $productQuantity = $this->mvcFactory->createTable('Productquantity', 'Administrator');
            if (!$productQuantity) {
                throw new \RuntimeException('Unable to create Productquantity table instance.');
            }
            $productQuantity->load(['variant_id' => $variant->j2commerce_variant_id]);
            $productQuantity->variant_id = $variant->j2commerce_variant_id;
            try {
                $productQuantity->save($inventory);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        // Save product images
        /** @var ProductimageTable $images */
        $images = $this->mvcFactory->createTable('Productimage', 'Administrator');
        if (!$images) {
            throw new \RuntimeException('Unable to create Productimage table instance.');
        }

        $this->_rawData['product_id'] = $table->j2commerce_product_id;
        $images->load(['product_id' => $table->j2commerce_product_id]);
        $images->save($this->_rawData);

        // Save product filters
        if (isset($this->_rawData['productfilter_ids'])) {
            /** @var ProductfilterTable $filterTable */
            $filterTable = $this->mvcFactory->createTable('Productfilter', 'Administrator');
            if (!$filterTable) {
                throw new \RuntimeException('Unable to create Productfilter table instance.');
            }
            $filterTable->addFilterToProduct(
                $this->_rawData['productfilter_ids'],
                $table->j2commerce_product_id
            );
        }

        // Save downloadable product files
        $this->saveProductFiles((int) $table->j2commerce_product_id);
    }

    private function saveProductFiles(int $productId): void
    {
        $files = $this->_rawData['files'] ?? null;

        if ($files === null) {
            return;
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

        // Get existing file IDs for this product
        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_productfile_id'))
            ->from($db->quoteName('#__j2commerce_productfiles'))
            ->where($db->quoteName('product_id') . ' = :pid')
            ->bind(':pid', $productId, \Joomla\Database\ParameterType::INTEGER);
        $db->setQuery($query);
        $existingIds = array_map('intval', $db->loadColumn() ?: []);

        $submittedIds = [];

        foreach ($files as $fileData) {
            $fileData    = (array) $fileData;
            $fileId      = (int) ($fileData['id'] ?? 0);
            $displayName = trim($fileData['display_name'] ?? '');
            $path        = trim($fileData['path'] ?? '');

            if (empty($path)) {
                continue;
            }

            if (empty($displayName)) {
                $displayName = basename($path);
            }

            /** @var ProductfileTable $fileTable */
            $fileTable = $this->mvcFactory->createTable('Productfile', 'Administrator');

            if ($fileId > 0) {
                $fileTable->load($fileId);
                $submittedIds[] = $fileId;
            }

            $fileTable->product_id                = $productId;
            $fileTable->product_file_display_name = $displayName;
            $fileTable->product_file_save_name    = $path;
            $fileTable->download_total            = (int) ($fileData['download_total'] ?? $fileTable->download_total ?? 0);

            $fileTable->store();

            if ($fileId === 0) {
                $submittedIds[] = (int) $fileTable->j2commerce_productfile_id;
            }
        }

        // Delete files that were removed from the form
        $toDelete = array_diff($existingIds, $submittedIds);

        foreach ($toDelete as $deleteId) {
            $fileTable = $this->mvcFactory->createTable('Productfile', 'Administrator');

            if ($fileTable->load($deleteId)) {
                $fileTable->delete($deleteId);
            }
        }
    }

    /**
     * Before delete event handler
     *
     * Called before ProductModel deletes a product record.
     * Cleans up related variant data.
     *
     */
    public function onBeforeDelete(object &$model): void
    {
        $id = $model->getState('product.id');

        if (!$id) {
            return;
        }

        /** @var ProductTable $product */
        $product = $this->mvcFactory->createTable('Product', 'Administrator');

        if ($product->load($id)) {
            if ($product->product_type !== 'downloadable') {
                return;
            }

            /** @var VariantsModel $variantModel */
            $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
            $variantModel->setState('filter.product_id', $id);
            $variantModel->setState('list.limit', 0);
            $variantModel->setState('list.start', 0);

            $variants = $variantModel->getItems();
            foreach ($variants as $variant) {
                $variantModel->delete($variant->j2commerce_variant_id);
            }
        }
    }

    /**
     * After get product event handler (Frontend)
     *
     * Called after product is loaded for frontend display.
     * Processes pricing, availability, and product options.
     *
     * @param   AbstractEvent  $event  The event object
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onAfterGetProduct(AbstractEvent $event): void
    {
        // Model is optional - may be null when called from ProductHelper::getFullProduct()
        $model   = $event->getArgument('subject');
        $product = $event->getArgument('product');

        // Product is required, check product_type for downloadable products
        if (!$product || !isset($product->product_type) || $product->product_type !== 'downloadable') {
            return;
        }

        $app           = Factory::getApplication();
        $productHelper = new ProductHelper();

        // Get links
        $productHelper->getAddtocartAction($product);
        $productHelper->getCheckoutLink($product);
        $productHelper->getProductLink($product);

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);

        // Downloadable products have only one variant
        try {
            $variants          = $variantModel->getItems();
            $product->variants = $variants[0] ?? $this->mvcFactory->createTable('Variant', 'Administrator');
        } catch (\Exception $e) {
            // Only set error on model if model is available (may be null when called from ProductHelper)
            if ($model !== null && method_exists($model, 'setError')) {
                $model->setError($e->getMessage());
            }
            $product->variants = $this->mvcFactory->createTable('Variant', 'Administrator');
        }

        // Process params as Registry
        $registry        = new Registry($product->params);
        $product->params = $registry;

        // Process variant
        $product->variant = $product->variants;

        // Get quantity restrictions
        $productHelper->getQuantityRestriction($product->variant);

        // Process quantity based on restrictions
        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        } else {
            $product->quantity = 1;
        }

        // Check stock status
        if ($productHelper->checkStockStatus($product->variant, (int) $product->quantity)) {
            $product->variant->availability = 1;
        } else {
            $product->variant->availability = 0;
        }

        // Process pricing
        $product->pricing = $productHelper->getPrice($product->variant, (int) $product->quantity);

        // Load product options if applicable
        $product->options = [];
        if ($product->has_options) {
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel = $this->mvcFactory->createModel(
                    'ProductOptions',
                    'Administrator',
                    ['ignore_request' => true]
                );
                $optionsModel->setState('filter.product_id', $product->j2commerce_product_id);
                $optionsModel->setState('filter.parent_id', null);
                $optionsModel->setState('list.limit', 0);
                $optionsModel->setState('list.start', 0);

                $product->product_options = $optionsModel->getItems();
            } catch (\Exception $e) {
                $model?->setError($e->getMessage());
            }

            try {
                $product->options       = $productHelper->getProductOptions($product);
                $defaultSelectedOptions = $productHelper->getDefaultProductOptions($product->options);

                $productOptionData = $productHelper->getOptionPrice(
                    $defaultSelectedOptions,
                    $product->j2commerce_product_id
                );
                $product->pricing->base_price += $productOptionData['option_price'];
                $product->pricing->price += $productOptionData['option_price'];
            } catch (\Exception $e) {
                // Silently handle option processing errors
            }
        }
    }

    /**
     * Update product event handler (AJAX)
     *
     * Called when product options/quantity change via AJAX.
     * Returns updated pricing and availability information.
     *
     */
    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        if ($product->product_type !== 'downloadable') {
            return [];
        }

        $app           = Factory::getApplication();
        $input         = $app->getInput();
        $config        = J2CommerceHelper::config();
        $productHelper = new ProductHelper();
        $pluginHelper  = J2CommerceHelper::plugin();

        $productId = $input->getInt('product_id', 0);
        if (!$productId) {
            return [];
        }

        // Get variant
        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
        $variantModel->setState('filter.is_master', 1);
        $variants          = $variantModel->getItems();
        $product->variants = $variants[0] ?? new \stdClass();

        // Process variant
        $product->variant = $product->variants;

        // Get quantity restrictions
        $productHelper->getQuantityRestriction($product->variant);

        // Process quantity
        $product->quantity = $input->getFloat('product_qty', 1);

        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        }

        // Process pricing
        $pricing = $productHelper->getPrice($product->variant, (int) $product->quantity);

        $selectedProductOptions = $input->get('product_option', [], 'ARRAY');

        // Get the selected option price
        if (\count($selectedProductOptions)) {
            $productOptionData = $productHelper->getOptionPrice(
                $selectedProductOptions,
                $product->j2commerce_product_id
            );
            $basePrice = $pricing->base_price + $productOptionData['option_price'];
            $price     = $pricing->price + $productOptionData['option_price'];
        } else {
            $basePrice = $pricing->base_price;
            $price     = $pricing->price;
        }

        // Trigger plugin events
        $pluginHelper->event('BeforeUpdateProductReturn', [&$config, $product]);

        $return                                      = [];
        $return['pricing']                           = [];
        $return['pricing']['base_price']             = $productHelper->displayPrice($basePrice, $product, $config);
        $return['pricing']['price']                  = $productHelper->displayPrice($price, $product, $config);
        $return['pricing']['original']               = [];
        $return['pricing']['original']['base_price'] = $basePrice;
        $return['pricing']['original']['price']      = $price;

        // Trigger plugin events
        $pluginHelper->event('AfterUpdateProductReturn', [&$return, $product, $config]);

        return $return;
    }

    /**
     * Get the raw data stored during onBeforeSave
     *
     * @return  array  The raw data array
     *
     * @since   6.0.0
     */
    public function getRawData(): array
    {
        return $this->_rawData;
    }

    /**
     * Set raw data for passing between events
     *
     * @param   array  $data  The data to store
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setRawData(array $data): void
    {
        $this->_rawData = $data;
    }
}
