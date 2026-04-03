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
use J2Commerce\Component\J2commerce\Administrator\Table\ProductfilterTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductimageTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductoptionTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductquantityTable;
use J2Commerce\Component\J2commerce\Administrator\Table\ProductTable;
use J2Commerce\Component\J2commerce\Administrator\Table\VariantTable;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class Configurable
{
    private array $_rawData = [];

    protected MVCFactoryInterface $mvcFactory;

    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    public function onAfterGetItem(object &$model, object &$record): void
    {
        if (($record->product_type ?? '') !== 'configurable') {
            return;
        }

        $app = Factory::getApplication();

        if (!$record->j2commerce_product_id) {
            return;
        }

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variantModel->setState('filter.product_type', $record->product_type);
        $variantModel->setState('filter.product_id', $record->j2commerce_product_id);

        try {
            $variants = $variantModel->getItems();
            $record->variants = $variants[0] ?? new \stdClass();
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $record->variants = $this->mvcFactory->createTable('Variant', 'Administrator');
        }

        try {
            /** @var ProductOptionsModel $optionsModel */
            $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator');
            $optionsModel->setState('filter.product_id', $record->j2commerce_product_id);
            $optionsModel->setState('list.limit', 0);
            $optionsModel->setState('list.start', 0);

            // On site frontend, only load top-level (parent) options initially
            $view = $app->getInput()->getCmd('view', '');
            if ($app->isClient('site') && $view !== 'form') {
                $optionsModel->setState('filter.parent_id', 0);
            }

            $record->product_options = $optionsModel->getItems();
        } catch (\Exception $e) {
            $app->enqueueMessage($e->getMessage(), 'error');
            $record->product_options = [];
        }

        $registry = new Registry($record->params);
        $record->params = $registry;
    }

    public function onBeforeSave(object &$model, array &$data): void
    {
        if (!isset($data['product_type']) || $data['product_type'] !== 'configurable') {
            return;
        }

        $app = Factory::getApplication();
        $utilityHelper = J2CommerceHelper::utilities();

        if (!isset($data['visibility'])) {
            $data['visibility'] = 1;
        }

        $data['cross_sells'] = isset($data['cross_sells'])
            ? $utilityHelper->to_csv($data['cross_sells'])
            : '';

        $data['up_sells'] = isset($data['up_sells'])
            ? $utilityHelper->to_csv($data['up_sells'])
            : '';

        if (isset($data['shippingmethods']) && !empty($data['shippingmethods'])) {
            $data['shippingmethods'] = implode(',', $data['shippingmethods']);
        }

        if (isset($data['item_options']) && is_object($data['item_options'])) {
            $data['item_options'] = (array) $data['item_options'];
        }

        if (isset($data['item_options']) && count($data['item_options']) > 0) {
            $data['has_options'] = 1;
        }

        $integerFields = ['taxprofile_id', 'manufacturer_id', 'vendor_id', 'isdefault_variant', 'length_class_id', 'weight_class_id'];
        foreach ($integerFields as $key) {
            $data[$key] = !empty($data[$key]) ? (int) $data[$key] : 0;
        }

        $floatFields = ['price', 'length', 'width', 'height', 'weight', 'min_sale_qty', 'max_sale_qty', 'notify_qty'];
        foreach ($floatFields as $key) {
            $data[$key] = !empty($data[$key]) ? (float) $data[$key] : 0;
        }

        if (is_object($data['quantity'] ?? null)
            && (!isset($data['quantity']->product_attributes) || empty($data['quantity']->product_attributes))) {
            $data['quantity']->product_attributes = '';
        }

        if (isset($data['quantity']) && is_object($data['quantity'])) {
            $data['quantity']->quantity = !empty($data['quantity']->quantity)
                ? (int) $data['quantity']->quantity
                : 0;
        }

        if (!empty($data['max_sale_qty']) && !empty($data['min_sale_qty'])
            && ($data['max_sale_qty'] < $data['min_sale_qty'])) {
            $data['min_sale_qty'] = 0;
            $app->enqueueMessage(Text::_('COM_J2COMMERCE_MAX_SALE_QTY_NEED_TO_GRATER_THEN_MIN_SALE_QTY'), 'warning');
        }

        // Merge existing params
        if (!empty($data['j2commerce_product_id'])) {
            /** @var ProductTable $product */
            $product = $this->mvcFactory->createTable('Product', 'Administrator');
            $product->load($data['j2commerce_product_id']);

            if (isset($product->params)) {
                $existingParams = json_decode($product->params, true) ?? [];
                if (!isset($data['params']) || empty($data['params'])) {
                    $data['params'] = $existingParams;
                } else {
                    $data['params'] = array_merge($existingParams, (array) $data['params']);
                }
            }
        }

        $data['params'] = !empty($data['params']) ? json_encode($data['params']) : '{}';

        $this->_rawData = $data;
    }

    public function onAfterSave(object &$model): void
    {
        if (!$this->_rawData) {
            return;
        }

        $table = $model->getTable();
        if ($table->product_type !== 'configurable') {
            return;
        }

        // Save variant data — load existing master variant first so store() does UPDATE not INSERT
        /** @var VariantTable $variant */
        $variant = $this->mvcFactory->createTable('Variant', 'Administrator');
        if (!$variant) {
            throw new \RuntimeException('Unable to create Variant table instance.');
        }

        // Try to load existing master variant by product_id
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
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
        $variant->is_master = 1;
        $variant->product_id = $table->j2commerce_product_id;
        $variant->check();
        $variant->store();

        // Delete removed product options
        if (!empty($this->_rawData['deleted_options'])) {
            $deletedIds = array_filter(array_map('intval', explode(',', $this->_rawData['deleted_options'])));
            if (!empty($deletedIds)) {
                $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                $productId = $table->j2commerce_product_id;

                foreach ($deletedIds as $deletedId) {
                    try {
                        $query = $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_options'))
                            ->where($db->quoteName('j2commerce_productoption_id') . ' = :optionId')
                            ->where($db->quoteName('product_id') . ' = :productId')
                            ->bind(':optionId', $deletedId, ParameterType::INTEGER)
                            ->bind(':productId', $productId, ParameterType::INTEGER);
                        $db->setQuery($query)->execute();

                        $query = $db->getQuery(true)
                            ->delete($db->quoteName('#__j2commerce_product_optionvalues'))
                            ->where($db->quoteName('productoption_id') . ' = :optionId')
                            ->bind(':optionId', $deletedId, ParameterType::INTEGER);
                        $db->setQuery($query)->execute();
                    } catch (\Exception $e) {
                        Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
                    }
                }
            }
        }

        // Save item options
        if (isset($this->_rawData['item_options'])) {
            foreach ($this->_rawData['item_options'] as $item) {
                /** @var ProductoptionTable $poption */
                $poption = $this->mvcFactory->createTable('Productoption', 'Administrator');
                if (!$poption) {
                    throw new \RuntimeException('Unable to create Productoption table instance.');
                }
                $itemData = \is_object($item) ? (array) $item : $item;
                $itemData['product_id'] = $table->j2commerce_product_id;
                try {
                    $poption->save($itemData);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
            }
        }

        // Save inventory/quantity
        if (isset($this->_rawData['quantity'])) {
            /** @var ProductquantityTable $productQuantity */
            $productQuantity = $this->mvcFactory->createTable('Productquantity', 'Administrator');
            if (!$productQuantity) {
                throw new \RuntimeException('Unable to create Productquantity table instance.');
            }
            $productQuantity->load(['variant_id' => $variant->j2commerce_variant_id]);
            $productQuantity->variant_id = $variant->j2commerce_variant_id;
            try {
                $productQuantity->save($this->_rawData['quantity']);
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
        if (!$product->load($id) || $product->product_type !== 'configurable') {
            return;
        }

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variantModel->setState('filter.product_id', $id);
        $variantModel->setState('list.limit', 0);
        $variantModel->setState('list.start', 0);

        foreach ($variantModel->getItems() as $variant) {
            $variantModel->delete($variant->j2commerce_variant_id);
        }
    }

    public function onAfterGetProduct(AbstractEvent $event): void
    {
        $model = $event->getArgument('subject');
        $product = $event->getArgument('product');

        if (!$product || ($product->product_type ?? '') !== 'configurable') {
            return;
        }

        $app = Factory::getApplication();
        $productHelper = new ProductHelper();

        $productHelper->getAddtocartAction($product);
        $productHelper->getCheckoutLink($product);
        $productHelper->getProductLink($product);

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);

        try {
            $variants = $variantModel->getItems();
            $product->variants = $variants[0] ?? $this->mvcFactory->createTable('Variant', 'Administrator');
        } catch (\Exception $e) {
            if ($model !== null && method_exists($model, 'setError')) {
                $model->setError($e->getMessage());
            }
            $product->variants = $this->mvcFactory->createTable('Variant', 'Administrator');
        }

        $registry = new Registry($product->params);
        $product->params = $registry;

        $product->variant = $product->variants;

        $productHelper->getQuantityRestriction($product->variant);

        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        } else {
            $product->quantity = 1;
        }

        if ($productHelper->checkStockStatus($product->variant, $product->quantity)) {
            $product->variant->availability = 1;
        } else {
            $product->variant->availability = 0;
        }

        $product->pricing = $productHelper->getPrice($product->variant, $product->quantity);

        $product->options = [];
        if ($product->has_options) {
            try {
                /** @var ProductOptionsModel $optionsModel */
                $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
                $optionsModel->setState('filter.product_id', $product->j2commerce_product_id);
                $optionsModel->setState('list.limit', 0);
                $optionsModel->setState('list.start', 0);

                // On site frontend, only load parent (top-level) options
                if ($app->isClient('site')) {
                    $optionsModel->setState('filter.parent_id', 0);
                }

                $product->product_options = $optionsModel->getItems();
            } catch (\Exception $e) {
                if ($model !== null && method_exists($model, 'setError')) {
                    $model->setError($e->getMessage());
                }
            }

            try {
                $product->options = $productHelper->getProductOptions($product);
                $defaultSelectedOptions = $productHelper->getDefaultProductOptions($product->options);

                $productOptionData = $productHelper->getOptionPrice(
                    $defaultSelectedOptions,
                    $product->j2commerce_product_id
                );
                $product->pricing->base_price += $productOptionData['option_price'];
                $product->pricing->price += $productOptionData['option_price'];
            } catch (\Exception $e) {
                // Do nothing
            }
        }
    }

    public function onUpdateProduct(object &$model, object &$product): array|false
    {
        if (($product->product_type ?? '') !== 'configurable') {
            return [];
        }

        $app = Factory::getApplication();
        $input = $app->getInput();
        $config = J2CommerceHelper::config()->getParams();
        $productHelper = new ProductHelper();
        $pluginHelper = J2CommerceHelper::plugin();

        $productId = $input->getInt('product_id', 0);
        if (!$productId) {
            return [];
        }

        // Handle cascading child options
        $poId = $input->getInt('po_id', 0);
        $povId = $input->getInt('pov_id', 0);

        $html = '';
        $responseOption = [];

        if ($poId && $povId) {
            // Fetch the parent option's option_id
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName(['j2commerce_productoption_id', 'option_id']))
                ->from($db->quoteName('#__j2commerce_product_options'))
                ->where($db->quoteName('j2commerce_productoption_id') . ' = :poId')
                ->bind(':poId', $poId, ParameterType::INTEGER);
            $db->setQuery($query);
            $parentOption = $db->loadObject();

            if ($parentOption) {
                $childOpts = ProductHelper::getChildProductOptions(
                    $productId,
                    (int) $parentOption->option_id,
                    $povId
                );

                if (!empty($childOpts)) {
                    $options = [];
                    $childOptionIds = [];
                    foreach ($childOpts as $attr) {
                        if (isset($attr['optionvalue'])) {
                            $options[] = $attr;
                            $childOptionIds[] = (int) $attr['productoption_id'];
                        }
                    }
                    $product->options = $options;
                    $responseOption = $options;

                    // Render child options using FileLayout
                    $layout = new FileLayout('product.configurablechildoptions');
                    $layout->addIncludePaths([
                        JPATH_SITE . '/components/com_j2commerce/layouts/app_bootstrap5',
                    ]);

                    $html = $layout->render([
                        'product' => $product,
                        'params' => $config,
                        'options' => $options,
                    ]);
                }
            }
        }

        // Get variant — trigger populateState() first, then override filters
        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);
        $variantModel->getState();
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id);
        $variantModel->setState('filter.is_master', 1);
        $variants = $variantModel->getItems();
        $product->variants = $variants[0] ?? new \stdClass();
        $product->variant = $product->variants;

        $productHelper->getQuantityRestriction($product->variant);

        $product->quantity = $input->getInt('product_qty', 1);
        if ($product->variant->quantity_restriction && $product->variant->min_sale_qty > 0) {
            $product->quantity = $product->variant->min_sale_qty;
        }

        $pricing = $productHelper->getPrice($product->variant, $product->quantity);

        $parentProductOptions = $input->get('product_option', [], 'ARRAY');

        if (count($parentProductOptions)) {
            $productOptionData = $productHelper->getOptionPrice($parentProductOptions, $product->j2commerce_product_id);
            $basePrice = $pricing->base_price + $productOptionData['option_price'];
            $price = $pricing->price + $productOptionData['option_price'];
        } else {
            $basePrice = $pricing->base_price;
            $price = $pricing->price;
        }

        $pluginHelper->event('BeforeUpdateProductReturn', [&$config, $product]);

        $return = [];
        $return['pricing'] = [];
        $return['pricing']['base_price'] = $productHelper->displayPrice($basePrice, $product, $config);
        $return['pricing']['price'] = $productHelper->displayPrice($price, $product, $config);
        $return['child_options'] = $responseOption;
        $return['child_option_ids'] = $childOptionIds ?? [];
        $return['optionhtml'] = $html;
        $return['pricing']['original'] = [
            'base_price' => $basePrice,
            'price' => $price,
        ];

        $pluginHelper->event('AfterUpdateProductReturn', [&$return, $product, $config]);

        return $return;
    }
}