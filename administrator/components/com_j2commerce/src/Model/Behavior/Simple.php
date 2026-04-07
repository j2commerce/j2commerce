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
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Registry\Registry;

class Simple
{
    protected MVCFactoryInterface $mvcFactory;

    private array $_rawData = [];

    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    public function onAfterGetItem(object &$model, object &$record): void
    {
        if ($record->product_type !== 'simple') {
            return;
        }

        $app = Factory::getApplication();

        $platform = J2CommerceHelper::platform();

        // Get the variants for simple products
        if ($record->j2commerce_product_id) {
            /** @var VariantsModel $variantModel */
            $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
            $variantModel->setState('filter.product_type', $record->product_type);
            $variantModel->setState('filter.product_id', $record->j2commerce_product_id);

            // Simple products have only one variant
            try {
                $variants         = $variantModel->getItems();
                $record->variants = isset($variants[0]) ? $variants[0] : new \stdClass();
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

            // Process params
            $registry       = new Registry($record->params);
            $record->params = $registry;
        }
    }

    public function onBeforeSave(object &$model, array &$data): void
    {
        if (!isset($data['product_type']) || $data['product_type'] !== 'simple') {
            return;
        }

        $app           = Factory::getApplication();
        $utilityHelper = J2CommerceHelper::utilities();

        // Set default visibility
        if (!isset($data['visibility'])) {
            $data['visibility'] = 1;
        }

        // Process cross sells
        if (isset($data['cross_sells'])) {
            $data['cross_sells'] = $utilityHelper->to_csv($data['cross_sells']);
        } else {
            $data['cross_sells'] = '';
        }

        // Process up sells
        if (isset($data['up_sells'])) {
            $data['up_sells'] = $utilityHelper->to_csv($data['up_sells']);
        } else {
            $data['up_sells'] = '';
        }

        // Process shipping methods
        if (isset($data['shippingmethods']) && !empty($data['shippingmethods'])) {
            $data['shippingmethods'] = implode(',', $data['shippingmethods']);
        }

        // Process item options
        if (isset($data['item_options']) && \is_object($data['item_options'])) {
            $data['item_options'] = (array)$data['item_options'];
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
            if (isset($data[$key]) && !empty($data[$key])) {
                $data[$key] = (int)$data[$key];
            } else {
                $data[$key] = 0;
            }
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
            if (isset($data[$key]) && !empty($data[$key])) {
                $data[$key] = (float)$data[$key];
            } else {
                $data[$key] = 0;
            }
        }

        // Process quantity data
        if (\is_object($data['quantity']) &&
            (!isset($data['quantity']->product_attributes) || empty($data['quantity']->product_attributes))) {
            $data['quantity']->product_attributes = '';
        }

        $quantityIntegerFields = ['quantity'];
        foreach ($quantityIntegerFields as $key) {
            if (isset($data['quantity']) && \is_object($data['quantity']) &&
                isset($data['quantity']->$key) && !empty($data['quantity']->$key)) {
                $data['quantity']->$key = (int)$data['quantity']->$key;
            } elseif (isset($data['quantity']) && \is_object($data['quantity'])) {
                $data['quantity']->$key = 0;
            }
        }

        // Validate min/max sale quantities
        if (isset($data['max_sale_qty']) && !empty($data['max_sale_qty']) &&
            isset($data['min_sale_qty']) && !empty($data['min_sale_qty']) &&
            ($data['max_sale_qty'] < $data['min_sale_qty'])) {
            $data['min_sale_qty'] = 0;
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_MAX_SALE_QTY_NEED_TO_GRATER_THEN_MIN_SALE_QTY'), 'warning');
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

        $this->_rawData = $data;
    }

    public function onAfterSave(object &$model): void
    {
        if (!$this->_rawData) {
            return;
        }

        $table = $model->getTable();
        if ($table->product_type !== 'simple') {
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
        $variant->check();
        $variant->store();

        // Delete removed product options first (before saving new/updated ones)
        if (isset($this->_rawData['deleted_options']) && !empty($this->_rawData['deleted_options'])) {
            $deletedIds = array_filter(array_map('intval', explode(',', $this->_rawData['deleted_options'])));
            if (!empty($deletedIds)) {
                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

                foreach ($deletedIds as $deletedId) {
                    // Delete the product option record
                    $query = $db->getQuery(true)
                        ->delete($db->quoteName('#__j2commerce_product_options'))
                        ->where($db->quoteName('j2commerce_productoption_id') . ' = :optionId')
                        ->where($db->quoteName('product_id') . ' = :productId')
                        ->bind(':optionId', $deletedId, \Joomla\Database\ParameterType::INTEGER)
                        ->bind(':productId', $table->j2commerce_product_id, \Joomla\Database\ParameterType::INTEGER);

                    try {
                        $db->setQuery($query);
                        $db->execute();

                        // Also delete any associated product option values
                        $query = $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                            ->where($db->quoteName('productoption_id') . ' = :optionId')
                            ->bind(':optionId', $deletedId, \Joomla\Database\ParameterType::INTEGER);

                        $db->setQuery($query);
                        $db->execute();
                    } catch (\Exception $e) {
                        // Log the error but continue processing
                        Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
                    }
                }
            }
        }

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
            $filterTable->addFilterToProduct($this->_rawData['productfilter_ids'], $table->j2commerce_product_id);
        }
    }

    public function onBeforeDelete(object &$model): void
    {
        $id = $model->getState('product.id');
        if (!$id) {
            return;
        }

        /** @var ProductTable $product */
        $product = $this->mvcFactory->createTable('Product', 'Administrator');
        if (!$product) {
            throw new \RuntimeException('Unable to create Product table instance.');
        }
        if ($product->load($id)) {
            if ($product->product_type !== 'simple') {
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

    public function onAfterGetProduct(AbstractEvent $event): void
    {
        // Model is optional - may be null when called from ProductHelper::getFullProduct()
        $model   = $event->getArgument('subject');
        $product = $event->getArgument('product');

        // Product is required, check product_type for simple products
        if (!$product || !isset($product->product_type) || $product->product_type !== 'simple') {
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

        // Simple products have only one variant
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

        // Process params
        $registry        = new Registry($product->params);
        $product->params = $registry;

        // Process variant
        $product->variant = $product->variants;

        // Get quantity restrictions
        $productHelper->getQuantityRestriction($product->variant);

        // Process quantity
        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        } else {
            $product->quantity = 1;
        }

        // Check stock status
        if ($productHelper->checkStockStatus($product->variant, $product->quantity)) {
            $product->variant->availability = 1;
        } else {
            $product->variant->availability = 0;
        }

        // Process pricing
        $product->pricing = $productHelper->getPrice($product->variant, $product->quantity);

        $product->options = [];
        if ($product->has_options) {
            // Load product options
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
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

                // Get option price
                $productOptionData            = $productHelper->getOptionPrice(
                    $defaultSelectedOptions,
                    $product->j2commerce_product_id
                );
                $product->pricing->base_price = $product->pricing->base_price + $productOptionData['option_price'];
                $product->pricing->price      = $product->pricing->price + $productOptionData['option_price'];
            } catch (\Exception $e) {
                // Do nothing
            }
        }
    }

    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        if ($product->product_type !== 'simple') {
            return false;
        }

        $app           = Factory::getApplication();
        $config        = J2CommerceHelper::config();
        $productHelper = new ProductHelper();
        $pluginHelper  = J2CommerceHelper::plugin();

        $productId = $app->getInput()->getInt('product_id', 0);
        if (!$productId) {
            return false;
        }

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
        $variantModel->setState('filter.is_master', 1);
        $variants          = $variantModel->getItems();
        $product->variants = $variants[0];

        $product->variant = $product->variants;

        $productHelper->getQuantityRestriction($product->variant);

        $product->quantity = $app->getInput()->getFloat('product_qty', 1);

        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        }

        $pricing = $productHelper->getPrice($product->variant, $product->quantity);

        $selectedProductOptions = $app->getInput()->get('product_option', [], 'ARRAY');

        if (\count($selectedProductOptions)) {
            $productOptionData = $productHelper->getOptionPrice($selectedProductOptions, $product->j2commerce_product_id);
            $basePrice         = $pricing->base_price + $productOptionData['option_price'];
            $price             = $pricing->price + $productOptionData['option_price'];
        } else {
            $basePrice = $pricing->base_price;
            $price     = $pricing->price;
        }

        $pluginHelper->event('BeforeUpdateProductReturn', [&$config, $product]);

        $return                                      = [];
        $return['pricing']                           = [];
        $return['pricing']['base_price']             = $productHelper->displayPrice($basePrice, $product, $config);
        $return['pricing']['price']                  = $productHelper->displayPrice($price, $product, $config);
        $return['pricing']['original']               = [];
        $return['pricing']['original']['base_price'] = $basePrice;
        $return['pricing']['original']['price']      = $price;

        $pluginHelper->event('AfterUpdateProductReturn', [&$return, $product, $config]);

        return $return;
    }
}
