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
use J2Commerce\Component\J2commerce\Administrator\Model\ProductOptionsModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VariantsModel;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use J2Commerce\Component\J2commerce\Administrator\Table\VariantTable;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Registry\Registry;

/**
 * Variable Product Behavior Class
 *
 * Handles lifecycle events for variable products including variant management,
 * price indexing, and option-based variant selection.
 *
 * Variable products use option combinations (e.g. size + color) that generate
 * a grid of variants. Each variant's identity is stored as a CSV of
 * product_optionvalue_ids in variant_name.
 *
 * @since 6.0.0
 */
class Variable
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
        if ($record->product_type !== 'variable') {
            return;
        }

        $app = Factory::getApplication();

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);

        // Trigger populateState() FIRST, then override — prevents session state from overwriting our filters
        $variantModel->getState();
        $variantModel->setState('filter.product_type', $record->product_type);

        $record->lengths = $variantModel->getDimensions('lengths', 'j2commerce_length_id', 'length_title');
        $record->weights = $variantModel->getDimensions('weights', 'j2commerce_weight_id', 'weight_title');

        try {
            // Load master variant
            $variantTable = $this->mvcFactory->createTable('Variant', 'Administrator');
            $variantTable->load(['product_id' => $record->j2commerce_product_id, 'is_master' => 1]);
            $record->variant = $variantTable;

            // Load child variants with pagination
            $limit = Factory::getApplication()->getConfig()->get('list_limit', 20);

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

        // Load product options
        try {
            /** @var ProductOptionsModel $optionsModel */
            $optionsModel            = $this->mvcFactory->createModel('ProductOptions', 'Administrator');
            $record->product_options = $optionsModel->getOptionsByProductId((int) $record->j2commerce_product_id);
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $record->product_options = [];
        }

        $record->params = new Registry($record->params);
    }

    /**
     * Before save event handler - validates and processes form data
     */
    public function onBeforeSave(object &$model, array &$data): void
    {
        if (!isset($data['product_type']) || $data['product_type'] !== 'variable') {
            return;
        }

        $app           = Factory::getApplication();
        $utilityHelper = J2CommerceHelper::utilities();

        if (!isset($data['visibility'])) {
            $data['visibility'] = 1;
        }

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

        // Validate min/max qty per variant
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

        // Merge existing params
        if (!empty($data['j2commerce_product_id'])) {
            /** @var ProductTable $product */
            $product = $this->mvcFactory->createTable('Product', 'Administrator');
            $product->load($data['j2commerce_product_id']);

            if (!empty($product->params)) {
                $existingParams = json_decode($product->params, true) ?? [];
                if (!isset($data['params']) || empty($data['params'])) {
                    $data['params'] = $existingParams;
                } else {
                    $data['params'] = array_merge($existingParams, (array) $data['params']);
                }
            }
        }

        if (!empty($data['params'])) {
            $data['params'] = json_encode($data['params']);
        } else {
            $data['params'] = '{}';
        }

        $this->_rawData = $data;
    }

    /**
     * After save event handler - saves variants, options, images, and runs price indexing
     */
    public function onAfterSave(object &$model): void
    {
        if (empty($this->_rawData)) {
            return;
        }

        $table = $model->getTable();

        if ($table->product_type !== 'variable') {
            return;
        }

        // Save master variant — load by product_id + is_master to avoid
        // overwriting a child variant if a stale ID leaks through form data
        /** @var VariantTable $variant */
        $variant = $this->mvcFactory->createTable('Variant', 'Administrator');
        if (!$variant) {
            throw new \RuntimeException('Unable to create Variant table instance.');
        }
        $variant->load(['product_id' => $table->j2commerce_product_id, 'is_master' => 1]);
        $variant->bind($this->_rawData, ['j2commerce_variant_id', 'variable']);
        $variant->is_master  = 1;
        $variant->product_id = $table->j2commerce_product_id;
        $variant->check();
        $variant->store();

        // Save product options
        if (isset($this->_rawData['item_options'])) {
            foreach ($this->_rawData['item_options'] as $item) {
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
                // Skip non-array/object entries (e.g. stray scalars from form parsing)
                if (!\is_array($item) && !\is_object($item)) {
                    continue;
                }

                if (\is_array($item)) {
                    $item = (object) $item;
                }

                // Resolve the variant ID: prefer item's own j2commerce_variant_id, fall back to array key
                $resolvedVariantId = (int) ($item->j2commerce_variant_id ?? 0);

                if ($resolvedVariantId === 0 && (int) $variantKey > 0) {
                    $resolvedVariantId = (int) $variantKey;
                }

                // Skip entries with no resolvable variant ID — these would cause a ghost INSERT
                if ($resolvedVariantId === 0) {
                    continue;
                }

                $item->j2commerce_variant_id = $resolvedVariantId;

                $intFields = ['taxprofile_id', 'manufacturer_id', 'vendor_id', 'isdefault_variant', 'length_class_id', 'weight_class_id'];
                foreach ($intFields as $key) {
                    $item->$key = isset($item->$key) && !empty($item->$key) && $item->$key > 0 ? (int) $item->$key : 0;
                }

                $floatFields = ['price', 'length', 'width', 'height', 'weight', 'min_sale_qty', 'max_sale_qty', 'notify_qty'];
                foreach ($floatFields as $key) {
                    $item->$key = isset($item->$key) && !empty($item->$key) && $item->$key > 0 ? (float) $item->$key : 0;
                }

                $item->use_store_config_max_sale_qty = !empty($item->use_store_config_max_sale_qty) ? 1 : 0;
                $item->use_store_config_min_sale_qty = !empty($item->use_store_config_min_sale_qty) ? 1 : 0;
                $item->use_store_config_notify_qty   = !empty($item->use_store_config_notify_qty) ? 1 : 0;

                // Build variant params: collect image data from top-level properties into params
                // This matches Flexivariable.php lines 369-394
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

                // Extract quantity data
                $quantityValue = 0;
                $quantityPkId  = 0;

                if (isset($item->quantity) && (\is_object($item->quantity) || \is_array($item->quantity))) {
                    $qObj          = (object) $item->quantity;
                    $quantityValue = (int) ($qObj->quantity ?? 0);
                    $quantityPkId  = (int) ($qObj->j2commerce_productquantity_id ?? 0);
                } else {
                    $quantityValue = (int) ($item->quantity ?? 0);
                    $quantityPkId  = (int) ($item->j2commerce_productquantity_id ?? 0);
                }

                unset($item->j2commerce_productquantity_id);

                $variantChild = $this->mvcFactory->createTable('Variant', 'Administrator');
                if (!$variantChild) {
                    throw new \RuntimeException('Unable to create Variant table instance for child variant.');
                }
                $item->product_id = $table->j2commerce_product_id;

                $quantityData = (object) [
                    'j2commerce_productquantity_id' => $quantityPkId,
                    'variant_id'                    => $resolvedVariantId,
                    'quantity'                      => $quantityValue,
                    'product_attributes'            => '',
                ];

                $quantityTable = $this->mvcFactory->createTable('Productquantity', 'Administrator');
                if (!$quantityTable) {
                    throw new \RuntimeException('Unable to create Productquantity table instance.');
                }
                $quantityTable->load(['variant_id' => $resolvedVariantId]);

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
            if ($product->product_type !== 'variable') {
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
     * Enhances variable products with variant data, option combinations,
     * stock availability, default variant selection, and pricing.
     */
    public function onAfterGetProduct(AbstractEvent $event): void
    {
        $model   = $event->getArgument('subject');
        $product = $event->getArgument('product');

        if (!$product || !isset($product->product_type) || $product->product_type !== 'variable') {
            return;
        }

        $productHelper = new ProductHelper();
        $pluginHelper  = J2CommerceHelper::plugin();

        $productHelper->getAddtocartAction($product);
        $productHelper->getCheckoutLink($product);
        $productHelper->getProductLink($product);

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);
        $variantModel->getState();
        $variantModel->setState('filter.product_type', $product->product_type);

        $product->lengths = $variantModel->getDimensions('lengths', 'j2commerce_length_id', 'length_title');
        $product->weights = $variantModel->getDimensions('weights', 'j2commerce_weight_id', 'weight_title');

        try {
            $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
            $variantModel->setState('filter.is_master', 0);
            $variantModel->setState('list.limit', 0);

            $product->variants = $variantModel->getItems();
        } catch (\Exception $e) {
            if ($model !== null && method_exists($model, 'setError')) {
                $model->setError($e->getMessage());
            }
        }

        if (empty($product->variants)) {
            return;
        }

        // Resolve human-readable variant names from CSV of optionvalue IDs
        foreach ($product->variants as &$variant) {
            if (!empty($variant->variant_name)) {
                $variant->variant_name = ProductHelper::getVariantNamesByCSV($variant->variant_name);
            }
        }
        unset($variant);

        // Check stock availability per variant
        foreach ($product->variants as &$variant) {
            $quantity = ($variant->quantity_restriction && $variant->min_sale_qty > 0)
                ? $variant->min_sale_qty
                : 1;

            $variant->availability = ProductHelper::checkStockStatus($variant, (int) $quantity) ? 1 : 0;
        }
        unset($variant);

        $allSoldOut = true;
        foreach ($product->variants as $singleVariant) {
            if ($singleVariant->availability == 1) {
                $allSoldOut = false;
                break;
            }
        }

        $product->all_sold_out = $allSoldOut;
        $product->options      = [];

        // Load product options for option-combination matching
        if ($product->has_options && $product->variants) {
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel             = $this->mvcFactory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
                $product->product_options = $optionsModel->getOptionsByProductId((int) $product->j2commerce_product_id);
                $product->options         = $productHelper->getProductOptions($product);

                // Filter out variants with no option mapping (ghost variants)
                $product->variants = array_values(array_filter(
                    $product->variants,
                    static fn ($v) => isset($v->variant_name_ids) && $v->variant_name_ids !== ''
                ));

                if (empty($product->variants)) {
                    return;
                }
            } catch (\Exception $e) {
                return;
            }
        }

        $product->params = new Registry($product->params);

        // Load master variant params (child variants may have empty params,
        // so we fall back to the master for images)
        $masterVariantTable = $this->mvcFactory->createTable('Variant', 'Administrator');
        $masterVariantTable->load(['product_id' => $product->j2commerce_product_id, 'is_master' => 1]);
        $masterParamData = new Registry($masterVariantTable->params ?? '{}');

        // Get all variant IDs for query
        $variantIds = [];
        foreach ($product->variants as $oneVariant) {
            $productHelper->getQuantityRestriction($oneVariant);
            $variantIds[] = $oneVariant->j2commerce_variant_id;
        }

        // Select default variant
        $product->variant = ProductHelper::getDefaultVariant($product->variants) ?? reset($product->variants);

        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        } else {
            $product->quantity = 1;
        }

        // Process pricing for default variant
        $product->pricing = $productHelper->getPrice($product->variant, (int) $product->quantity);

        $paramData     = new Registry($product->variant->params);
        $mainImage     = $paramData->get('variant_main_image', '');
        $isMainAsThumb = (int) $paramData->get('is_main_as_thum', 0);

        // Fall back to first variant_images entry when variant_main_image is not set
        // Check child variant's images first, then fall back to master variant's images
        $variantImages = $paramData->get('variant_images', []);
        if (empty($variantImages)) {
            $variantImages = $masterParamData->get('variant_images', []);
            if (empty($isMainAsThumb)) {
                $isMainAsThumb = (int) $masterParamData->get('is_main_as_thum', 0);
            }
        }

        if (empty($mainImage) && !empty($variantImages)) {
            $gallery = \is_string($variantImages)
                ? (json_decode($variantImages, true) ?? [])
                : (array) json_decode(json_encode($variantImages), true);
            $firstImage = reset($gallery);
            if (!empty($firstImage['path'])) {
                $mainImage = $firstImage['path'];
            }
        }

        $product->main_image = !empty($mainImage) ? $mainImage : ($product->main_image ?? '');
        if ($isMainAsThumb) {
            $product->thumb_image = !empty($mainImage) ? $mainImage : ($product->thumb_image ?? '');
        }

        // Build variant JSON for frontend option-combination selection
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

                // Pre-select default option values and build default_option_selections map
                $defaultOptionValueIds = $variantCsvs[$product->variant->j2commerce_variant_id] ?? '';
                $valueArray            = $defaultOptionValueIds !== '' ? explode(',', $defaultOptionValueIds) : [];

                $product->default_option_selections = [];

                foreach ($product->options as &$option) {
                    if (\in_array($option['type'], ['select', 'radio', 'color'], true)) {
                        foreach ($option['optionvalue'] as &$optionvalue) {
                            if (\in_array($optionvalue['product_optionvalue_id'], $valueArray)) {
                                $optionvalue['product_optionvalue_default']                            = 1;
                                $product->default_option_selections[(int) $option['productoption_id']] = $optionvalue['product_optionvalue_id'];
                            }
                        }
                        unset($optionvalue);
                    }
                }
                unset($option);
            } catch (\Exception $e) {
                // Silent fail
            }
        }
    }

    /**
     * Update product event handler - handles AJAX option changes for frontend
     */
    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        if ($product->product_type !== 'variable') {
            return [];
        }

        $app           = Factory::getApplication();
        $input         = $app->getInput();
        $config        = ComponentHelper::getParams('com_j2commerce');
        $productHelper = new ProductHelper();
        $imageHelper   = J2CommerceHelper::image();
        $pluginHelper  = J2CommerceHelper::plugin();

        $options = $input->get('product_option', [], 'ARRAY');
        $options = \is_array($options) ? array_filter($options) : [];

        if (empty($options)) {
            return [];
        }

        // Find the matching variant by option selection
        $variant = ProductHelper::getVariantByOptions($options, $product->j2commerce_product_id);

        if ($variant === null) {
            return ['error' => Text::_('COM_J2COMMERCE_VARIANT_NOT_FOUND')];
        }

        $productHelper->getQuantityRestriction($variant);

        $actualQuantity = $quantity = $input->getFloat('product_qty', 1);

        if ($variant->quantity_restriction && $variant->min_sale_qty > 0) {
            $quantity = ($variant->min_sale_qty > $quantity) ? $variant->min_sale_qty : $quantity;
            $quantity = ($quantity > $variant->max_sale_qty) ? $variant->max_sale_qty : $quantity;
            if ($quantity == 0 || !$quantity) {
                $quantity = $actualQuantity;
            }
        }

        $variant->availability = ProductHelper::checkStockStatus($variant, (int) $quantity) ? 1 : 0;
        $variant->pricing      = $productHelper->getPrice($variant, (int) $quantity);

        $pluginHelper->event('BeforeUpdateProductReturn', [&$config, $product]);

        $return               = [];
        $return['variant_id'] = $variant->j2commerce_variant_id;

        $paramData     = new Registry($variant->params);
        $isMainAsThumb = (int) $paramData->get('is_main_as_thum', 0);
        $mainImage     = $paramData->get('variant_main_image', '');

        // Variant gallery for Swiper image swap
        // Child variants may have empty params — fall back to master variant's images
        $variantImages = $paramData->get('variant_images', []);
        if (empty($variantImages)) {
            $masterVariant = $this->mvcFactory->createTable('Variant', 'Administrator');
            $masterVariant->load(['product_id' => $product->j2commerce_product_id, 'is_master' => 1]);
            $masterParams  = new Registry($masterVariant->params ?? '{}');
            $variantImages = $masterParams->get('variant_images', []);
            if (empty($mainImage)) {
                $mainImage = $masterParams->get('variant_main_image', '');
            }
            if (empty($isMainAsThumb)) {
                $isMainAsThumb = (int) $masterParams->get('is_main_as_thum', 0);
            }
        }
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

        $return['thumb_image']      = !empty($thumbImage) ? $imageHelper->getImageUrl($thumbImage) : $imageHelper->getImageUrl($product->thumb_image);
        $return['main_image']       = !empty($mainImage) ? $imageHelper->getImageUrl($mainImage) : $imageHelper->getImageUrl($product->main_image);
        $return['is_main_as_thum']  = $isMainAsThumb;

        $return['sku']              = $variant->sku;
        $return['quantity']         = (float) $quantity;
        $return['price']            = $variant->price;
        $return['availability']     = $variant->availability;
        $return['manage_stock']     = $variant->manage_stock;
        $return['allow_backorder']  = $variant->allow_backorder;

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

        $return['pricing']               = [];
        $return['pricing']['base_price'] = J2CommerceHelper::currency()->format((float) $variant->pricing->base_price);
        $return['pricing']['price']      = J2CommerceHelper::currency()->format((float) $variant->pricing->price);
        $return['pricing']['original']   = [
            'base_price' => number_format((float) $variant->pricing->base_price, 5, '.', ''),
            'price'      => number_format((float) $variant->pricing->price, 5, '.', ''),
        ];

        $return['pricing']['class']         = ($variant->pricing->base_price != $variant->pricing->price) ? 'show' : 'hide';
        $return['pricing']['discount_text'] = '';

        if (isset($variant->pricing->is_discount_pricing_available) && $variant->pricing->base_price > 0) {
            $discount = (1 - ($variant->pricing->price / $variant->pricing->base_price)) * 100;
            if ($discount > 0) {
                $return['pricing']['discount_text'] = Text::sprintf('COM_J2COMMERCE_PRODUCT_OFFER', round($discount) . '%');
            }
        }

        $return['afterDisplayPrice'] = '';

        $lengthTitle = $variant->length_title ?? '';
        $weightTitle = $variant->weight_title ?? '';

        $return['dimensions']      = LengthHelper::formatDimensions($variant->length, $variant->width, $variant->height, $lengthTitle);
        $return['weight']          = WeightHelper::formatValue($variant->weight, $weightTitle);
        $return['length']          = LengthHelper::formatValue($variant->length, $lengthTitle);
        $return['width']           = LengthHelper::formatValue($variant->width, $lengthTitle);
        $return['height']          = LengthHelper::formatValue($variant->height, $lengthTitle);
        $return['weight_raw']      = (float) $variant->weight;
        $return['length_raw']      = (float) $variant->length;
        $return['width_raw']       = (float) $variant->width;
        $return['height_raw']      = (float) $variant->height;
        $return['dimensions_unit'] = $lengthTitle;
        $return['length_title']    = $lengthTitle . ((float) $variant->length > 1 ? ($lengthTitle === 'Inch' ? 'es' : 's') : '');
        $return['width_title']     = $lengthTitle . ((float) $variant->width > 1 ? ($lengthTitle === 'Inch' ? 'es' : 's') : '');
        $return['height_title']    = $lengthTitle . ((float) $variant->height > 1 ? ($lengthTitle === 'Inch' ? 'es' : 's') : '');
        $return['weight_unit']     = $variant->weight_unit ?? '';
        $return['weight_title']    = $weightTitle . ((float) $variant->weight > 1 ? 's' : '');

        $pluginHelper->event('AfterUpdateProductReturn', [&$return, $product, $config]);

        return $return;
    }
}
