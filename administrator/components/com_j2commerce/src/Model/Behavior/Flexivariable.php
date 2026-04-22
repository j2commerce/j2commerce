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

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\LengthHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\WeightHelper;
use J2Commerce\Component\J2commerce\Administrator\Model\OptionvaluesModel;
use J2Commerce\Component\J2commerce\Administrator\Model\ProductOptionsModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VariantsModel;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use J2Commerce\Component\J2commerce\Administrator\Table\VariantTable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

/**
 * Flexivariable Product Behavior Class
 *
 * Handles lifecycle events for flexivariable products including variant management,
 * price indexing, and option-based variant selection.
 *
 * @since 6.0.0
 */
class Flexivariable
{
    private array $_rawData = [];

    protected MVCFactoryInterface $mvcFactory;

    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    /**
     * After get item event handler - loads variant and option data for admin editing
     */
    public function onAfterGetItem(object &$model, object &$record): void
    {
        if ($record->product_type !== 'flexivariable') {
            return;
        }

        $app = Factory::getApplication();

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);

        // Trigger populateState() FIRST, then override — prevents session state from overwriting our filters
        $variantModel->getState();
        $variantModel->setState('filter.product_type', $record->product_type);

        // Load dimensions (doesn't trigger populateState)
        $record->lengths = $variantModel->getDimensions('lengths', 'j2commerce_length_id', 'length_title');
        $record->weights = $variantModel->getDimensions('weights', 'j2commerce_weight_id', 'weight_title');

        try {
            // Load master variant
            $variantTable = $this->mvcFactory->createTable('Variant', 'Administrator');
            $variantTable->load(['product_id' => $record->j2commerce_product_id, 'is_master' => 1]);
            $record->variant = $variantTable;

            // Load child variants with pagination
            $config = Factory::getApplication()->getConfig();
            $limit  = $config->get('list_limit', 20);

            // Set variant filters AFTER populateState() has been triggered above
            $variantModel->setState('filter.product_id', $record->j2commerce_product_id);
            $variantModel->setState('filter.is_master', 0);
            $variantModel->setState('list.limit', $limit);
            $variantModel->setState('list.start', 0);

            $record->variants           = $variantModel->getItems();
            $record->variant_pagination = $variantModel->getPagination();
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $record->variants = [];
        }

        // Load product options with option values
        try {
            /** @var ProductOptionsModel $optionsModel */
            $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator');
            // Use direct query method to avoid session state interference
            $record->product_options = $optionsModel->getOptionsByProductId((int) $record->j2commerce_product_id);

            if (!empty($record->product_options)) {
                foreach ($record->product_options as &$productOption) {
                    /** @var OptionvaluesModel $optionValuesModel */
                    $optionValuesModel = $this->mvcFactory->createModel('Optionvalues', 'Administrator');
                    // Use dedicated method that properly handles state initialization
                    $productOption->option_values = $optionValuesModel->getValuesByOptionId((int) $productOption->option_id);

                    $productOptionValuesModel = $this->mvcFactory->createModel('Productoptionvalues', 'Administrator');
                    // Use dedicated method that properly handles state initialization
                    $productOption->product_optionvalues = $productOptionValuesModel->getValuesByProductOptionId((int) $productOption->j2commerce_productoption_id);
                }
            }
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $record->product_options = [];
        }

        $registry           = new Registry($record->params);
        $record->params     = $registry;
        $record->app_detail = $this->getAppDetails();
    }

    /**
     * Get FlexiVariable app plugin details
     */
    public function getAppDetails(): ?object
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':folder', $folder = 'j2commerce')
            ->bind(':element', $element = 'app_flexivariable')
            ->bind(':type', $type = 'plugin');
        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Before save event handler - validates and processes form data
     */
    public function onBeforeSave(object &$model, array &$data): void
    {
        if (!isset($data['product_type']) || $data['product_type'] !== 'flexivariable') {
            return;
        }

        $app           = Factory::getApplication();
        $utilityHelper = J2CommerceHelper::utilities();

        if (!isset($data['visibility'])) {
            $data['visibility'] = 1;
        }

        // Process cross sells and up sells
        $data['cross_sells'] = isset($data['cross_sells']) ? $utilityHelper->to_csv($data['cross_sells']) : '';
        $data['up_sells']    = isset($data['up_sells']) ? $utilityHelper->to_csv($data['up_sells']) : '';

        if (isset($data['shippingmethods']) && !empty($data['shippingmethods'])) {
            $data['shippingmethods'] = implode(',', $data['shippingmethods']);
        }

        if (isset($data['item_options']) && \is_object($data['item_options'])) {
            $data['item_options'] = (array) $data['item_options'];
        }

        if (isset($data['item_options']) && \count($data['item_options']) > 0) {
            $data['has_options'] = 1;
        }

        // Cast integer fields
        $integerFields = ['taxprofile_id', 'manufacturer_id', 'vendor_id', 'isdefault_variant', 'length_class_id', 'weight_class_id'];
        foreach ($integerFields as $key) {
            $data[$key] = isset($data[$key]) && !empty($data[$key]) ? (int) $data[$key] : 0;
        }

        // Cast float fields
        $floatFields = ['price', 'length', 'width', 'height', 'weight', 'min_sale_qty', 'max_sale_qty', 'notify_qty'];
        foreach ($floatFields as $key) {
            $data[$key] = isset($data[$key]) && !empty($data[$key]) ? (float) $data[$key] : 0;
        }

        // Process quantity data
        if (isset($data['quantity']) && \is_object($data['quantity'])) {
            if (!isset($data['quantity']->product_attributes) || empty($data['quantity']->product_attributes)) {
                $data['quantity']->product_attributes = '';
            }
            $data['quantity']->quantity = isset($data['quantity']->quantity) && !empty($data['quantity']->quantity)
                ? (int) $data['quantity']->quantity : 0;
        }

        // Validate min/max qty for each variable
        if (isset($data['variable'])) {
            foreach ($data['variable'] as $variable) {
                if (isset($variable->max_sale_qty) && !empty($variable->max_sale_qty)
                    && isset($variable->min_sale_qty) && !empty($variable->min_sale_qty)
                    && $variable->max_sale_qty < $variable->min_sale_qty) {
                    $variable->min_sale_qty = 0;
                    $app->enqueueMessage(Text::_('COM_J2COMMERCE_MAX_SALE_QTY_NEED_TO_GRATER_THEN_MIN_SALE_QTY'), 'warning');
                }
            }
        }

        // Merge existing params - preserve existing when no new params provided
        if (!empty($data['j2commerce_product_id'])) {
            /** @var ProductTable $product */
            $product = $this->mvcFactory->createTable('Product', 'Administrator');
            $product->load($data['j2commerce_product_id']);

            if (!empty($product->params)) {
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

    /**
     * After save event handler - saves variants, options, images, and runs price indexing
     *
     * @param   ProductsModel|object  $model  The model or wrapper with getTable() method
     */
    public function onAfterSave(object &$model): void
    {
        if (empty($this->_rawData)) {
            return;
        }

        $table = $model->getTable();

        if ($table->product_type !== 'flexivariable') {
            return;
        }

        // Save master variant — load existing first to preserve columns not in form data
        /** @var VariantTable $variant */
        $variant = $this->mvcFactory->createTable('Variant', 'Administrator');
        if (!$variant) {
            throw new \RuntimeException('Unable to create Variant table instance.');
        }
        if (!empty($this->_rawData['j2commerce_variant_id'])) {
            $variant->load((int) $this->_rawData['j2commerce_variant_id']);
        } else {
            $variant->load(['product_id' => $table->j2commerce_product_id, 'is_master' => 1]);
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

        // Save product options
        if (isset($this->_rawData['item_options'])) {
            foreach ($this->_rawData['item_options'] as $item) {
                // Convert to object if array
                if (\is_array($item)) {
                    $item = (object) $item;
                }
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

        // Save child variants
        if (isset($this->_rawData['variable'])) {
            foreach ($this->_rawData['variable'] as $variantKey => $item) {
                // Skip entries with only image fields (orphaned MultiImageUploader sync inputs)
                $imageOnlyFields = ['main_image', 'main_image_alt', 'thumb_image', 'thumb_image_alt',
                    'tiny_image', 'tiny_image_alt', 'additional_images', 'additional_images_alt',
                    'additional_thumb_images', 'additional_thumb_images_alt',
                    'additional_tiny_images', 'additional_tiny_images_alt', 'variant_images', 'is_main_as_thum'];
                $itemArray    = \is_array($item) ? $item : (array) $item;
                $nonImageKeys = array_diff(array_keys($itemArray), $imageOnlyFields);
                if (empty($nonImageKeys)) {
                    continue;
                }
                if (\is_array($item)) {
                    $item = (object) $item;
                }

                // Set variant ID from array key — form uses variant ID as the array key
                // but doesn't include it as a form field. Without this, VariantTable::save()
                // cannot find the existing record and INSERTs a duplicate instead of UPDATing.
                $item->j2commerce_variant_id = (int) $variantKey;
                $item->is_master             = 0;

                // Cast integer fields
                $intFields = ['taxprofile_id', 'manufacturer_id', 'vendor_id', 'isdefault_variant', 'length_class_id', 'weight_class_id'];
                foreach ($intFields as $key) {
                    $item->$key = isset($item->$key) && !empty($item->$key) && $item->$key > 0 ? (int) $item->$key : 0;
                }

                // Cast float fields
                $floatFields = ['price', 'length', 'width', 'height', 'weight', 'min_sale_qty', 'max_sale_qty', 'notify_qty'];
                foreach ($floatFields as $key) {
                    $item->$key = isset($item->$key) && !empty($item->$key) && $item->$key > 0 ? (float) $item->$key : 0;
                }

                // Process store config checkboxes (XML checkbox value="1", absent when unchecked)
                $item->use_store_config_max_sale_qty = !empty($item->use_store_config_max_sale_qty) ? 1 : 0;
                $item->use_store_config_min_sale_qty = !empty($item->use_store_config_min_sale_qty) ? 1 : 0;
                $item->use_store_config_notify_qty   = !empty($item->use_store_config_notify_qty) ? 1 : 0;

                // Build variant params: collect image data from top-level properties into params
                $variantParams = [];
                if (isset($item->params) && !empty($item->params)) {
                    if (\is_string($item->params)) {
                        $variantParams = json_decode($item->params, true) ?? [];
                    } elseif (\is_array($item->params) || \is_object($item->params)) {
                        $variantParams = (array) $item->params;
                    }
                }

                // Uppy gallery data (JSON string from hidden input)
                if (!empty($item->variant_images)) {
                    $gallery = \is_string($item->variant_images)
                        ? (json_decode($item->variant_images, true) ?? [])
                        : (array) $item->variant_images;
                    $variantParams['variant_images'] = $gallery;
                    unset($item->variant_images);
                }

                // is_main_as_thum from XML form field
                if (isset($item->is_main_as_thum)) {
                    $variantParams['is_main_as_thum'] = (int) $item->is_main_as_thum;
                    unset($item->is_main_as_thum);
                }

                $item->params = json_encode($variantParams);

                // Build quantity data from flat form fields (XML form submits flat, not nested)
                $quantityValue = null;
                $quantityPkId  = 0;

                if (isset($item->quantity) && (\is_object($item->quantity) || \is_array($item->quantity))) {
                    // Legacy nested format: quantity[quantity], quantity[j2commerce_productquantity_id]
                    $qObj          = (object) $item->quantity;
                    $quantityValue = (int) ($qObj->quantity ?? 0);
                    $quantityPkId  = (int) ($qObj->j2commerce_productquantity_id ?? 0);
                } else {
                    // Flat format from XML form fields
                    $quantityValue = (int) ($item->quantity ?? 0);
                    $quantityPkId  = (int) ($item->j2commerce_productquantity_id ?? 0);
                }

                // Remove quantity fields from variant item (they go to productquantities table)
                unset($item->j2commerce_productquantity_id);

                $variantChild = $this->mvcFactory->createTable('Variant', 'Administrator');
                if (!$variantChild) {
                    throw new \RuntimeException('Unable to create Variant table instance for child variant.');
                }
                $variantChild->is_master = 0;
                $item->product_id        = $table->j2commerce_product_id;

                $quantityData = (object) [
                    'j2commerce_productquantity_id' => $quantityPkId,
                    'variant_id'                    => $variantKey,
                    'quantity'                      => $quantityValue,
                    'product_attributes'            => '',
                ];

                $quantityTable = $this->mvcFactory->createTable('Productquantity', 'Administrator');
                if (!$quantityTable) {
                    throw new \RuntimeException('Unable to create Productquantity table instance.');
                }
                $quantityTable->load(['variant_id' => $variantKey]);

                try {
                    if ($variantChild->save($item)) {
                        if (!$quantityTable->save($quantityData)) {
                            $quantityTable->getError();
                        }
                    }
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }

        // Save product images
        $images = $this->mvcFactory->createTable('Productimage', 'Administrator');
        if (!$images) {
            throw new \RuntimeException('Unable to create Productimage table instance.');
        }

        $this->_rawData['product_id'] = $table->j2commerce_product_id;
        $images->load(['product_id' => $table->j2commerce_product_id]);
        $images->save($this->_rawData);

        // Run price indexes
        $this->runIndexes($table);

        // Save product filters
        if (isset($this->_rawData['productfilter_ids'])) {
            $filterTable = $this->mvcFactory->createTable('Productfilter', 'Administrator');
            if (!$filterTable) {
                throw new \RuntimeException('Unable to create Productfilter table instance.');
            }
            $filterTable->addFilterToProduct($this->_rawData['productfilter_ids'], $table->j2commerce_product_id);
        }
    }

    /**
     * Calculate and store min/max price indexes for the product
     */
    public function runIndexes(object $table): void
    {
        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);
        $variantModel->getState();
        $variantModel->setState('filter.product_id', $table->j2commerce_product_id);
        $variantModel->setState('filter.is_master', 0);
        $variantModel->setState('list.limit', 0);

        $variants = $variantModel->getItems();

        $minPrice = null;
        $maxPrice = null;

        foreach ($variants as $variant) {
            if ($variant->price === '' || $variant->price == 0) {
                continue;
            }

            if ($minPrice === null || $variant->price < $minPrice) {
                $minPrice = $variant->price;
            }

            if ($variant->price > $maxPrice) {
                $maxPrice = $variant->price;
            }
        }

        $db        = Factory::getContainer()->get('DatabaseDriver');
        $productId = (int) $table->j2commerce_product_id;

        $priceIndex = $this->mvcFactory->createTable('Productpriceindex', 'Administrator');
        $values     = (object) [
            'product_id' => $productId,
            'min_price'  => $minPrice ?? 0,
            'max_price'  => $maxPrice ?? 0,
        ];

        if ($priceIndex->load($productId)) {
            $db->updateObject('#__j2commerce_productprice_index', $values, 'product_id');
        } else {
            $db->insertObject('#__j2commerce_productprice_index', $values);
        }
    }

    /**
     * Before delete event handler - removes all associated variants
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
            if ($product->product_type !== 'flexivariable') {
                return;
            }

            /** @var VariantsModel $variantModel */
            $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
            $variantModel->setState('filter.ignore_request', true);
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
     * After get product event handler - loads product data for frontend display
     *
     * This method enhances flexivariable products with:
     * - variant_name: Human-readable variant names
     * - variant_json: JSON data for frontend JavaScript variant selection
     * - min_price / max_price: Price range from variants
     * - options: Filtered option values based on available variants
     * - product_options with option_values and product_optionvalues populated
     * - Default variant selection with pricing
     *
     * @param   AbstractEvent  $event  Event with 'product' argument (required) and 'subject' (optional model)
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

        // Product is required
        if (!$product || !isset($product->product_type) || $product->product_type !== 'flexivariable') {
            return;
        }

        $productHelper = new ProductHelper();
        $pluginHelper  = J2CommerceHelper::plugin();

        // Set product links
        $productHelper->getAddtocartAction($product);
        $productHelper->getCheckoutLink($product);
        $productHelper->getProductLink($product);

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);

        // Trigger populateState() FIRST, then override — prevents session state from overwriting our filters
        $variantModel->getState();
        $variantModel->setState('filter.product_type', $product->product_type);

        // Load dimensions (doesn't trigger populateState)
        $product->lengths = $variantModel->getDimensions('lengths', 'j2commerce_length_id', 'length_title');
        $product->weights = $variantModel->getDimensions('weights', 'j2commerce_weight_id', 'weight_title');

        try {
            // Set variant filters AFTER populateState() has been triggered above
            // Filter by is_master=0 to get only child variants (master has is_master=1)
            $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
            $variantModel->setState('filter.is_master', 0);
            $variantModel->setState('list.limit', 0);

            $product->variants           = $variantModel->getItems();
            $product->variant_pagination = $variantModel->getPagination();
        } catch (\Exception $e) {
            // Only set error on model if model is available
            if ($model !== null && method_exists($model, 'setError')) {
                $model->setError($e->getMessage());
            }
        }

        if (empty($product->variants)) {
            return;
        }

        // Calculate min/max price
        $minPrice = null;
        $maxPrice = null;

        foreach ($product->variants as $variant) {
            if ($minPrice === null || $variant->price < $minPrice) {
                $minPrice = $variant->price;
            }
            if ($variant->price > $maxPrice) {
                $maxPrice = $variant->price;
            }
        }

        $product->min_price = $minPrice !== null ? (float) $minPrice : null;
        $product->max_price = $maxPrice !== null ? (float) $maxPrice : null;

        // Set show_price_range flag from plugin params
        $plugin                    = PluginHelper::getPlugin('j2commerce', 'app_flexivariable');
        $pluginParams              = new Registry($plugin->params ?? '{}');
        $product->show_price_range = (int) $pluginParams->get('show_price_range', 0);

        $product->options = [];

        // Load options and variants
        if ($product->has_options && $product->variants) {
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
                // Use direct query method to avoid session state interference
                $product->product_options = $optionsModel->getOptionsByProductId((int) $product->j2commerce_product_id);

                if (!empty($product->product_options)) {
                    foreach ($product->product_options as &$productOption) {
                        /** @var OptionvaluesModel $optionValuesModel */
                        $optionValuesModel            = $this->mvcFactory->createModel('Optionvalues', 'Administrator');
                        $productOption->option_values = $optionValuesModel->getValuesByOptionId((int) $productOption->option_id);

                        $productOptionValuesModel = $this->mvcFactory->createModel('Productoptionvalues', 'Administrator');
                        // Use dedicated method that properly handles state initialization
                        $productOption->product_optionvalues = $productOptionValuesModel->getValuesByProductOptionId((int) $productOption->j2commerce_productoption_id);
                    }
                }

                $product->options = $productHelper->getProductOptions($product);

                // Validate flexivariants BEFORE dedup — the pre-dedup options contain all original
                // product_optionvalue_ids so the Cartesian product correctly includes every variant CSV.
                // Note: validation failure does NOT set visibility=0. Visibility is a DB column for
                // category list display, not a runtime error flag. Invalid config is handled by the
                // cart behavior's onBeforeAddCartItem which returns a descriptive error.
                $productHelper->validateFlexivariants($product->variants, $product->options);

                // Filter available option values based on variants
                // Use variant_name_ids (original CSV of product_optionvalue_ids) if available,
                // fall back to variant_name for backward compatibility
                $availableOptionValues = [];
                foreach ($product->variants as $pVariant) {
                    $variantCsv       = $pVariant->variant_name_ids ?? $pVariant->variant_name ?? '';
                    $variantNameParts = explode(',', $variantCsv);
                    if (!empty($variantNameParts) && \is_array($variantNameParts)) {
                        foreach ($variantNameParts as $proOptionValue) {
                            $productOptionValue = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
                            $productOptionValue->load((int) $proOptionValue);

                            if (!isset($availableOptionValues[$productOptionValue->productoption_id])
                                || !\in_array('*', $availableOptionValues[$productOptionValue->productoption_id])) {
                                if ($productOptionValue->optionvalue_id == 0) {
                                    $availableOptionValues[$productOptionValue->productoption_id][] = '*';
                                } else {
                                    $availableOptionValues[$productOptionValue->productoption_id][] = $productOptionValue->optionvalue_id;
                                }
                            }
                        }
                    }
                }

                foreach ($product->options as &$pOption) {
                    $prodOptionId = $pOption['productoption_id'];

                    // If this productoption has "Any" (*) available, keep all optionvalues but deduplicate
                    if (isset($availableOptionValues[$prodOptionId]) && \in_array('*', $availableOptionValues[$prodOptionId])) {
                        if (!empty($pOption['optionvalue'])) {
                            $seenIds     = [];
                            $dedupValues = [];
                            foreach ($pOption['optionvalue'] as $ov) {
                                $ovId = $ov['optionvalue_id'];
                                if (!isset($seenIds[$ovId])) {
                                    $dedupValues[]  = $ov;
                                    $seenIds[$ovId] = true;
                                }
                            }
                            $pOption['optionvalue'] = $dedupValues;
                        }
                        continue;
                    }

                    // Filter to only available values and deduplicate by optionvalue_id
                    if (isset($availableOptionValues[$prodOptionId]) && !empty($pOption['optionvalue'])) {
                        $filteredOptionValues = [];
                        $seenOptionValueIds   = [];
                        foreach ($pOption['optionvalue'] as $optionValueData) {
                            $ovId = $optionValueData['optionvalue_id'];
                            if (\in_array($ovId, $availableOptionValues[$prodOptionId]) && !isset($seenOptionValueIds[$ovId])) {
                                $filteredOptionValues[]    = $optionValueData;
                                $seenOptionValueIds[$ovId] = true;
                            }
                        }
                        $pOption['optionvalue'] = $filteredOptionValues;
                    }
                }

            } catch (\Exception $e) {
                // Log but don't set visibility=0 — visibility is a DB column for display, not a runtime flag
                return;
            }
        }

        $registry        = new Registry($product->params);
        $product->params = $registry;

        // Get variant IDs and process default variant
        $variantIds = [];
        foreach ($product->variants as $oneVariant) {
            $variantIds[] = $oneVariant->j2commerce_variant_id;
        }

        $defaultVariant    = $this->getDefaultVariant($product->variants);
        $product->quantity = 1;

        if (isset($defaultVariant->j2commerce_variant_id) && !empty($defaultVariant->j2commerce_variant_id)) {
            $product->variant = $defaultVariant;

            if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
                $product->quantity = $product->variant->min_sale_qty;
            }

            $product->pricing = $productHelper->getPrice($product->variant, (int) $product->quantity);

            $paramData     = new Registry($product->variant->params);
            $mainImage     = $paramData->get('variant_main_image', '');
            $isMainAsThumb = (int) $paramData->get('is_main_as_thum', 0);

            // Fall back to first variant_images entry when variant_main_image is not set
            if (empty($mainImage)) {
                $variantImages = $paramData->get('variant_images', []);
                if (!empty($variantImages)) {
                    $gallery = \is_string($variantImages)
                        ? (json_decode($variantImages, true) ?? [])
                        : (array) json_decode(json_encode($variantImages), true);
                    $firstImage = reset($gallery);
                    if (!empty($firstImage['path'])) {
                        $mainImage = $firstImage['path'];
                    }
                }
            }

            $product->main_image = !empty($mainImage) ? $mainImage : ($product->main_image ?? '');
            if ($isMainAsThumb) {
                $product->thumb_image = !empty($mainImage) ? $mainImage : ($product->thumb_image ?? '');
            }
        }

        // Pre-compute default option selections for templates.
        // After dedup, the product_optionvalue_ids in the default variant's CSV may not match the
        // deduplicated set (different POV rows can share the same optionvalue_id).
        // Resolve via optionvalue_id so templates can reliably pre-select defaults.
        $product->default_option_selections = [];
        if (!empty($product->variant) && !empty($product->options)) {
            $defaultCsv = $product->variant->variant_name_ids ?? $product->variant->variant_name ?? '';
            if ($defaultCsv !== '') {
                foreach (explode(',', $defaultCsv) as $povId) {
                    $povTable = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
                    if (!$povTable->load((int) $povId)) {
                        continue;
                    }
                    $targetOvId = (int) $povTable->optionvalue_id;
                    $targetPoId = (int) $povTable->productoption_id;

                    // Find the matching deduped product_optionvalue_id by optionvalue_id
                    foreach ($product->options as $opt) {
                        if ((int) $opt['productoption_id'] === $targetPoId) {
                            foreach ($opt['optionvalue'] as $ov) {
                                if ((int) $ov['optionvalue_id'] === $targetOvId) {
                                    $product->default_option_selections[$targetPoId] = $ov['product_optionvalue_id'];
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Build variant JSON for frontend JavaScript
        if ($product->has_options && $product->variants && !empty($variantIds)) {
            try {
                $db    = Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('variant_id'),
                        $db->quoteName('product_optionvalue_ids'),
                    ])
                    ->from($db->quoteName('#__j2commerce_product_variant_optionvalues'))
                    ->whereIn($db->quoteName('variant_id'), $variantIds);

                $db->setQuery($query);
                $csvs = $db->loadAssocList('variant_id');

                $variantCsvs = [];
                foreach ($csvs as $variantId => $csv) {
                    $variantCsvs[$variantId] = $csv['product_optionvalue_ids'];
                }

                $product->variant_json = json_encode($variantCsvs);
            } catch (\Exception $e) {
                // Silent fail
            }
        }
    }

    /**
     * Get the default variant from a list of variants
     */
    protected function getDefaultVariant(array $variants): object
    {
        $variant = reset($variants) ?: new \stdClass();
        foreach ($variants as $oneVariant) {
            if ($oneVariant->isdefault_variant == 1) {
                $variant = $oneVariant;
                break;
            }
        }
        return $variant;
    }

    /**
     * Update product event handler - handles AJAX option changes
     */
    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        if ($product->product_type !== 'flexivariable') {
            return [];
        }

        $app           = Factory::getApplication();
        $input         = $app->getInput();
        $config        = ComponentHelper::getParams('com_j2commerce');
        $productHelper = new ProductHelper();
        $imageHelper   = J2CommerceHelper::image();
        $pluginHelper  = J2CommerceHelper::plugin();

        $options = $input->get('product_option', [], 'ARRAY');

        if (empty($options) || \in_array('*', $options)) {
            return [];
        }

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variantModel->setState('filter.ignore_request', true);
        $variantModel->setState('filter.product_type', $product->product_type);
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
        $variantModel->setState('list.limit', 0);

        $chkVariants = $variantModel->getItems();

        // Resolve submitted POV IDs to optionvalue_ids (form sends deduped POV IDs)
        $submittedOvIds = [];
        foreach ($options as $poId => $submittedPovId) {
            $povTable = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
            if ($povTable->load((int) $submittedPovId)) {
                $submittedOvIds[(int) $poId] = (int) $povTable->optionvalue_id;
            }
        }

        // Find matching variant by optionvalue_id comparison
        $variant = null;
        foreach ($chkVariants as $chkVariant) {
            $csvIds = $chkVariant->variant_name_ids ?? $chkVariant->variant_name ?? '';
            if ($csvIds === '' || $csvIds === null) {
                continue;
            }
            $productOptionValues = explode(',', $csvIds);
            $status              = [];
            foreach ($productOptionValues as $proOptionValue) {
                $proOptionValue = (int) trim($proOptionValue);
                if ($proOptionValue <= 0) {
                    continue;
                }
                $povTable = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
                $povTable->load($proOptionValue);

                $poId = (int) ($povTable->productoption_id ?? 0);
                $ovId = (int) ($povTable->optionvalue_id ?? 0);

                $optionStatus = false;
                if (\array_key_exists($poId, $submittedOvIds)) {
                    if ($submittedOvIds[$poId] === $ovId) {
                        $optionStatus = true;
                    } elseif ($ovId === 0) {
                        $optionStatus = true;
                    }
                }
                $status[] = $optionStatus;
            }

            if (!empty($status) && !\in_array(false, $status, true)) {
                $variant = $chkVariant;
                break;
            }
        }

        if (empty($variant)) {
            return ['error' => Text::_('COM_J2COMMERCE_FLEXI_VARIABLE_VARIANT_NOT_FOUND')];
        }

        // Process variant
        $productHelper->getQuantityRestriction($variant);

        $actualQuantity = $quantity = $input->getFloat('product_qty', 1);

        if ($variant->quantity_restriction && $variant->min_sale_qty > 0) {
            $quantity = ($variant->min_sale_qty > $quantity) ? $variant->min_sale_qty : $quantity;
            $quantity = ($quantity > $variant->max_sale_qty) ? $variant->max_sale_qty : $quantity;
            if ($quantity == 0 || !$quantity) {
                $quantity = $actualQuantity;
            }
        }

        // Check stock status
        $variant->availability = ProductHelper::checkStockStatus($variant, (int) $quantity) ? 1 : 0;

        // Process pricing
        $variant->pricing = $productHelper->getPrice($variant, (int) $quantity);

        $pluginHelper->event('BeforeUpdateProductReturn', [&$config, $product]);

        // Build return data
        $return               = [];
        $return['variant_id'] = $variant->j2commerce_variant_id;

        $paramData     = new Registry($variant->params);
        $isMainAsThumb = (int) $paramData->get('is_main_as_thum', 0);
        $mainImage     = $paramData->get('variant_main_image', '');

        // Variant gallery for Swiper image swap
        $variantImages = $paramData->get('variant_images', []);
        if (!empty($variantImages)) {
            if (\is_string($variantImages)) {
                $variantImages = json_decode($variantImages, true) ?? [];
            }
            if (\is_object($variantImages)) {
                $variantImages = (array) json_decode(json_encode($variantImages), true);
            }

            $gallery = [];
            foreach ((array) $variantImages as $img) {
                $img  = (array) $img;
                $path = $img['path'] ?? '';
                if (empty($path)) {
                    continue;
                }
                $gallery[] = [
                    'src'       => ImageHelper::getImageUrl($path),
                    'thumb_src' => ImageHelper::getProductImage($path, 100, 'raw'),
                    'alt'       => htmlspecialchars($img['alt_text'] ?? '', ENT_QUOTES, 'UTF-8'),
                ];
            }
            if (!empty($gallery)) {
                $return['variant_gallery'] = $gallery;
            }

            // Fall back to first variant_images entry when variant_main_image is not set
            if (empty($mainImage)) {
                $firstImage = reset($variantImages);
                if (\is_array($firstImage) && !empty($firstImage['path'])) {
                    $mainImage = $firstImage['path'];
                }
            }
        }

        $thumbImage = ($isMainAsThumb && !empty($mainImage)) ? $mainImage : '';

        $return['thumb_image']     = !empty($thumbImage) ? $imageHelper->getImageUrl($thumbImage) : $imageHelper->getImageUrl($product->thumb_image);
        $return['main_image']      = !empty($mainImage) ? $imageHelper->getImageUrl($mainImage) : $imageHelper->getImageUrl($product->main_image);
        $return['is_main_as_thum'] = $isMainAsThumb;

        $return['sku']             = $variant->sku;
        $return['quantity']        = (float) $quantity;
        $return['price']           = $variant->price;
        $return['availability']    = $variant->availability;
        $return['manage_stock']    = $variant->manage_stock;
        $return['allow_backorder'] = $variant->allow_backorder;

        if ($productHelper->managingStock($variant)) {
            if ($variant->availability) {
                $displayStock           = $productHelper->displayStock($variant, $config);
                $return['stock_status'] = $displayStock ?: 'Available';
            } else {
                $return['stock_status'] = Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
            }
        } else {
            $return['stock_status'] = '';
        }

        $return['pricing']                           = [];
        $return['pricing']['base_price']             = J2CommerceHelper::currency()->format((float) $variant->pricing->base_price);
        $return['pricing']['price']                  = J2CommerceHelper::currency()->format((float) $variant->pricing->price);
        $return['pricing']['original']               = [];
        $return['pricing']['original']['base_price'] = number_format((float) $variant->pricing->base_price, 5, '.', '');
        $return['pricing']['original']['price']      = number_format((float) $variant->pricing->price, 5, '.', '');

        $return['pricing']['class']         = ($variant->pricing->base_price != $variant->pricing->price) ? 'show' : 'hide';
        $return['pricing']['discount_text'] = '';

        if (isset($variant->pricing->is_discount_pricing_available) && $variant->pricing->base_price > 0) {
            $discount = (1 - ($variant->pricing->price / $variant->pricing->base_price)) * 100;
            if ($discount > 0) {
                $return['pricing']['discount_text'] = Text::sprintf('COM_J2COMMERCE_PRODUCT_OFFER', round($discount) . '%');
            }
        }

        $return['afterDisplayPrice'] = '';

        // Dimensions - use helper methods to ensure proper float casting and configurable decimal places
        $return['dimensions']      = LengthHelper::formatDimensions($variant->length, $variant->width, $variant->height, $variant->length_title);
        $return['weight']          = WeightHelper::formatValue($variant->weight, $variant->weight_title);
        $return['length']          = LengthHelper::formatValue($variant->length, $variant->length_title);
        $return['width']           = LengthHelper::formatValue($variant->width, $variant->length_title);
        $return['height']          = LengthHelper::formatValue($variant->height, $variant->length_title);
        $return['weight_raw']      = (float) $variant->weight;
        $return['length_raw']      = (float) $variant->length;
        $return['width_raw']       = (float) $variant->width;
        $return['height_raw']      = (float) $variant->height;
        $return['dimensions_unit'] = $variant->length_title;
        $return['length_title']    = $variant->length_title . ((float) $variant->length > 1 ? ($variant->length_title === 'Inch' ? 'es' : 's') : '');
        $return['width_title']     = $variant->length_title . ((float) $variant->width > 1 ? ($variant->length_title === 'Inch' ? 'es' : 's') : '');
        $return['height_title']    = $variant->length_title . ((float) $variant->height > 1 ? ($variant->length_title === 'Inch' ? 'es' : 's') : '');
        $return['weight_unit']     = $variant->weight_unit;
        $return['weight_title']    = $variant->weight_title . ((float) $variant->weight > 1 ? 's' : '');

        $pluginHelper->event('AfterUpdateProductReturn', [&$return, $product, $config]);

        return $return;
    }
}
