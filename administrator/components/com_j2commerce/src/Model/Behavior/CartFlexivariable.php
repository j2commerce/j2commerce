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
use J2Commerce\Component\J2commerce\Administrator\Model\CartModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VariantsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Object\CMSObject;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Cart FlexiVariable Product Behavior Class
 *
 * Handles cart lifecycle events for flexi-variable products. FlexiVariable products
 * are similar to variable products but allow more flexible option matching including
 * "any option" wildcards (optionvalue_id = 0) in variant definitions.
 *
 * @since 6.0.0
 */
class CartFlexivariable
{
    /**
     * MVC Factory for creating models and tables
     *
     * @var  MVCFactoryInterface
     * @since 6.0.0
     */
    protected MVCFactoryInterface $mvcFactory;

    /**
     * Static cache for product option values
     *
     * @var  array
     * @since 6.0.0
     */
    protected static array $optionValueCache = [];

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
     * Get variant by matching product options
     *
     * FlexiVariable products support wildcard matching where optionvalue_id = 0
     * means "any option" is accepted for that option slot.
     *
     * @param   array   $options  Selected product options [productoption_id => optionvalue_id]
     * @param   object  $product  The product object
     *
     * @return  object|null  Matching variant or null if not found
     *
     * @since   6.0.0
     */
    public function getVariantByOptions(array $options, object $product): ?object
    {
        // Load all non-master variants for this product.
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator', ['ignore_request' => true]);

        // Trigger populateState() FIRST, then override — prevents session state from overwriting our filters
        $variantModel->getState();
        $variantModel->setState('filter.product_type', $product->product_type ?? 'flexivariable');
        $variantModel->setState('filter.product_id', $product->j2commerce_product_id ?? 0);
        $variantModel->setState('list.limit', 0);
        $variantModel->setState('list.start', 0);

        $variants = $variantModel->getItems();

        // $options is [productoption_id => submitted_product_optionvalue_id] from form.
        // After dedup, the submitted POV ID may differ from the variant's POV ID but represent
        // the same optionvalue_id. Resolve submitted POVs to optionvalue_ids once upfront.
        $submittedOvIds = [];
        foreach ($options as $poId => $submittedPovId) {
            $povTable = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
            if ($povTable->load((int) $submittedPovId)) {
                $submittedOvIds[(int) $poId] = (int) $povTable->optionvalue_id;
            }
        }

        foreach ($variants as $checkVariant) {
            $variantPovIds = explode(',', $checkVariant->variant_name_ids ?? $checkVariant->variant_name ?? '');
            $matchStatus   = [];

            foreach ($variantPovIds as $variantPovId) {
                $variantPovId = (int) trim($variantPovId);
                if ($variantPovId <= 0) {
                    continue;
                }

                $povTable = $this->mvcFactory->createTable('Productoptionvalue', 'Administrator');
                $povTable->load($variantPovId);

                $productOptionId = (int) ($povTable->productoption_id ?? 0);
                $variantOvId     = (int) ($povTable->optionvalue_id ?? 0);

                $optionStatus = false;

                if (\array_key_exists($productOptionId, $submittedOvIds)) {
                    // Compare by optionvalue_id — survives dedup where different POV rows share same OV
                    if ($submittedOvIds[$productOptionId] === $variantOvId) {
                        $optionStatus = true;
                    } elseif ($variantOvId === 0) {
                        // Wildcard: variant accepts any value for this option
                        $optionStatus = true;
                    }
                }

                $matchStatus[] = $optionStatus;
            }

            if (!empty($matchStatus) && !\in_array(false, $matchStatus, true)) {
                return $checkVariant;
            }
        }

        return null;
    }

    /**
     * Before add cart item event handler
     *
     * Validates product options, finds matching variant, validates quantity
     * restrictions and stock availability before adding to cart.
     *
     * @param   CartModel   $model    The cart model instance
     * @param   object      $product  The product object being added
     * @param   \stdClass   $json     The JSON response object (by reference)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onBeforeAddCartItem(CartModel &$model, object $product, \stdClass &$json): void
    {
        $app    = Factory::getApplication();
        $values = $app->getInput()->getArray();
        $errors = [];

        // Get and validate quantity
        $quantity = $app->getInput()->getFloat('product_qty', 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Get product options
        $options = $app->getInput()->get('product_option', [], 'ARRAY');
        $options = \is_array($options) ? array_filter($options) : [];

        // Validate required product options
        if (!empty($product->product_options)) {
            foreach ($product->product_options as $productOption) {
                $optionId = $productOption->j2commerce_productoption_id ?? 0;

                // Check if option is empty
                if (empty($options[$optionId])) {
                    $optionName                           = Text::_($productOption->option_name ?? '');
                    $errors['error']['option'][$optionId] = Text::sprintf(
                        'COM_J2COMMERCE_ADDTOCART_PRODUCT_OPTION_REQUIRED',
                        $optionName
                    );
                }

                // Check if wildcard (*) was selected - not allowed for add to cart
                if (isset($options[$optionId]) && $options[$optionId] === '*') {
                    $optionName                           = Text::_($productOption->option_name ?? '');
                    $errors['error']['option'][$optionId] = Text::sprintf(
                        'COM_J2COMMERCE_ADDTOCART_PRODUCT_OPTION_REQUIRED',
                        $optionName
                    );
                }
            }
        }

        // Find matching variant if no option errors
        $variant = null;
        if (empty($errors)) {
            $variant = $this->getVariantByOptions($options, $product);

            if (empty($variant)) {
                $errors['error']['stock'] = Text::_('COM_J2COMMERCE_FLEXI_VARIABLE_VARIANT_NOT_FOUND');
            }
        }

        // Validate stock and quantity (skip for wishlist)
        $cart = $model->getCart();

        if (empty($errors) && $variant && isset($cart->cart_type) && $cart->cart_type !== 'wishlist') {
            $variantId = (int) ($variant->j2commerce_variant_id ?? 0);

            // Get total quantity of this variant already in cart
            $cartTotalQty = ProductHelper::getTotalCartQuantity($variantId);

            // Validate minimum/maximum quantity restrictions
            $quantityError = ProductHelper::validateQuantityRestriction(
                $variant,
                (float) $cartTotalQty,
                (float) $quantity
            );

            if (!empty($quantityError)) {
                $errors['error']['stock'] = $quantityError;
            }

            // Validate inventory/stock status
            if (empty($errors) && !ProductHelper::checkStockStatus($variant, (int) ($cartTotalQty + $quantity))) {
                $variantQty = (int) ($variant->quantity ?? 0);

                if ($variantQty > 0) {
                    $errors['error']['stock'] = Text::sprintf(
                        'COM_J2COMMERCE_LOW_STOCK_WITH_QUANTITY',
                        $variantQty
                    );
                } else {
                    $errors['error']['stock'] = Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
                }
            }
        }

        // If no errors, create cart item and add to cart
        if (empty($errors) && $variant) {
            $utilityHelper = J2CommerceHelper::utilities();

            // Create cart item object
            $item                  = new CMSObject();
            $item->user_id         = Factory::getApplication()->getIdentity()->id;
            $item->product_id      = (int) $product->j2commerce_product_id;
            $item->variant_id      = (int) ($variant->j2commerce_variant_id ?? 0);
            $item->product_qty     = $utilityHelper->stock_qty($quantity);
            $item->product_options = base64_encode(serialize($options));
            $item->product_type    = $product->product_type ?? 'flexivariable';
            $item->vendor_id       = isset($product->vendor_id) ? (int) $product->vendor_id : 0;

            // Trigger plugin event for custom item modifications
            $pluginHelper = J2CommerceHelper::plugin();
            $results      = $pluginHelper->event('AfterCreateItemForAddToCart', [$item, $values]);

            foreach ($results as $result) {
                if (\is_array($result)) {
                    foreach ($result as $key => $value) {
                        $item->set($key, $value);
                    }
                }
            }

            // Trigger validation plugin event
            $validationResults = $pluginHelper->event('BeforeAddToCart', [
                $item,
                $values,
                $product,
                $product->product_options ?? [],
            ]);

            foreach ($validationResults as $result) {
                if (!empty($result['error'])) {
                    $errors['error']['general'] = $result['error'];
                }
            }

            // Add item to cart if no plugin errors
            if (empty($errors)) {
                $cartTable = $model->addItem($item);

                if ($cartTable === false) {
                    $errors['success'] = 0;
                } else {
                    $errors['success'] = 1;
                    $errors['cart_id'] = $cartTable->j2commerce_cart_id ?? 0;
                }
            }
        }

        $json->result = $errors;
    }

    /**
     * Get cart product option values for flexi-variable products
     *
     * Handles the special case where optionvalue_id = 0 (any option) needs
     * to retrieve the actual selected option's name.
     *
     * @param   int  $productOptionId  The product option ID
     * @param   int  $optionValue      The selected option value ID
     *
     * @return  object|null  Product option value data or null
     *
     * @since   6.0.0
     */
    public function getCartProductOptionValues(int $productOptionId, int $productOptionValueId): ?object
    {
        if (empty($productOptionValueId)) {
            return null;
        }

        $cacheKey = $productOptionId . '_' . $productOptionValueId;

        if (!isset(self::$optionValueCache[$cacheKey])) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);

            // Direct lookup by product_optionvalue_id (form now submits this ID)
            $query->select([
                $db->quoteName('pov.j2commerce_product_optionvalue_id'),
                $db->quoteName('pov.productoption_id'),
                $db->quoteName('pov.optionvalue_id'),
                $db->quoteName('pov.product_optionvalue_price'),
                $db->quoteName('pov.product_optionvalue_prefix'),
                $db->quoteName('pov.product_optionvalue_weight'),
                $db->quoteName('pov.product_optionvalue_weight_prefix'),
                $db->quoteName('pov.product_optionvalue_sku'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :pov_id')
            ->bind(':pov_id', $productOptionValueId, ParameterType::INTEGER);

            // Join option values table to get the name
            $query->select([
                $db->quoteName('ov.j2commerce_optionvalue_id'),
                $db->quoteName('ov.optionvalue_name'),
            ])
            ->join(
                'LEFT',
                $db->quoteName('#__j2commerce_optionvalues', 'ov'),
                $db->quoteName('pov.optionvalue_id') . ' = ' . $db->quoteName('ov.j2commerce_optionvalue_id')
            );

            $db->setQuery($query);
            self::$optionValueCache[$cacheKey] = $db->loadObject() ?: null;
        }

        return self::$optionValueCache[$cacheKey];
    }

    /**
     * Get cart items event handler
     *
     * Processes a cart item for flexi-variable products, loading variant data,
     * options, pricing, and weight information.
     *
     * @param   CartModel   $model  The cart model instance
     * @param   object      $item   The cart item (by reference)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function onGetCartItems(CartModel &$model, object &$item): void
    {
        // Only process flexivariable products
        if (($item->product_type ?? '') !== 'flexivariable') {
            return;
        }

        $productHelper = new ProductHelper();

        // Decode product options
        $options = [];
        if (!empty($item->product_options)) {
            $decoded = @unserialize(base64_decode($item->product_options));
            if ($decoded !== false) {
                $options = $decoded;
            }
        }

        // Load variant data
        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variant      = $variantModel->getItem((int) $item->variant_id);

        $optionPrice  = 0.0;
        $optionWeight = 0.0;
        $optionData   = [];

        // Process each selected option
        foreach ($options as $productOptionId => $optionValue) {
            $productOptionId = (int) $productOptionId;
            $optionValueInt  = (int) $optionValue;

            // Get product option configuration
            $productOption = ProductHelper::getCartProductOptions($productOptionId, (int) $item->product_id);

            if (!$productOption) {
                continue;
            }

            // Get product option value (with flexi-variable special handling)
            $productOptionValue = $this->getCartProductOptionValues($productOptionId, $optionValueInt);

            if (!$productOptionValue) {
                continue;
            }

            // Calculate option price adjustment
            $prefix      = $productOptionValue->product_optionvalue_prefix ?? '+';
            $priceAdjust = (float) ($productOptionValue->product_optionvalue_price ?? 0);

            if ($prefix === '+') {
                $optionPrice += $priceAdjust;
            } elseif ($prefix === '-') {
                $optionPrice -= $priceAdjust;
            }

            // Calculate option weight adjustment
            $weightPrefix = $productOptionValue->product_optionvalue_weight_prefix ?? '+';
            $weightAdjust = (float) ($productOptionValue->product_optionvalue_weight ?? 0);

            if ($weightPrefix === '+') {
                $optionWeight += $weightAdjust;
            } elseif ($weightPrefix === '-') {
                $optionWeight -= $weightAdjust;
            }

            // Build option data array
            $optionData[] = [
                'product_option_id'      => $productOptionId,
                'product_optionvalue_id' => $optionValueInt,
                'option_id'              => $productOption->option_id ?? 0,
                'optionvalue_id'         => $productOptionValue->optionvalue_id ?? 0,
                'name'                   => $productOption->option_name ?? '',
                'option_value'           => $productOptionValue->optionvalue_name ?? '',
                'type'                   => $productOption->type ?? '',
                'price'                  => $priceAdjust,
                'price_prefix'           => $prefix,
                'weight'                 => $weightAdjust,
                'option_sku'             => $productOptionValue->product_optionvalue_sku ?? '',
                'weight_prefix'          => $weightPrefix,
            ];
        }

        // Load full product data (article name, images, variants, URL)
        $product = ProductHelper::getFullProduct((int) $item->product_id, true, true);

        if (!$product) {
            return;
        }

        // Transfer product-level properties needed by discount/app plugins
        $item->product_source    = $product->product_source ?? '';
        $item->product_source_id = (int) ($product->product_source_id ?? 0);
        $item->product_params    = $product->params instanceof \Joomla\Registry\Registry
            ? $product->params->toString()
            : ($product->params ?? '{}');

        // Set item properties from full product
        $item->product_name     = $product->product_name ?? $item->product_name ?? '';
        $item->product_view_url = $product->product_view_url ?? $item->product_view_url ?? '';
        $item->options          = $optionData;
        $item->option_price     = 0.0; // Option price already included in variant price for flexivariable

        // Build cartitem_params with thumb_image (same pattern as CartSimple)
        $existingParams = [];
        if (!empty($item->cartitem_params)) {
            $existingParams = (array) json_decode($item->cartitem_params, true);
        }

        $imageUrl = $product->thumb_image ?? $product->main_image ?? '';
        if (!empty($imageUrl)) {
            if (strpos($imageUrl, '#') !== false) {
                $imageUrl = substr($imageUrl, 0, strpos($imageUrl, '#'));
            }
            $existingParams['thumb_image'] = $imageUrl;
        }

        // Handle variant-specific images (override product image if variant has one)
        if ($variant) {
            $variantParams = new Registry($variant->params ?? '{}');
            $mainImage     = $variantParams->get('variant_main_image', '');
            $isMainAsThumb = (int) $variantParams->get('is_main_as_thum', 0);

            // Fall back to first variant_images entry when variant_main_image is not set
            if (empty($mainImage)) {
                $variantImages = $variantParams->get('variant_images', []);
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

            if (!empty($mainImage)) {
                $item->main_image = $mainImage;

                if ($isMainAsThumb) {
                    $existingParams['thumb_image'] = $mainImage;
                }
            }

            // Check back_order_item status
            $allowBackorders = (int) ($variant->allow_backorder ?? 0);
            if ($allowBackorders > 0) {
                $variantQty = (int) ($variant->quantity ?? 0);
                $cartQty    = (int) ($item->product_qty ?? 1);
                if ($cartQty > $variantQty) {
                    $existingParams['back_order_item'] = 'COM_J2COMMERCE_CART_BACKORDER_ITEM';
                }
            }
        }

        $item->taxprofile_id   = (int) ($product->taxprofile_id ?? 0);
        $item->cartitem_params = json_encode($existingParams);

        // Calculate weight from variant
        $variantWeight      = (float) ($variant->weight ?? 0);
        $item->weight       = $variantWeight;
        $item->weight_total = $variantWeight * (float) ($item->product_qty ?? 1);

        // Get group ID for pricing
        $groupId = '';
        if (!empty($item->group_id)) {
            $groupId = $item->group_id;
        }

        // Calculate pricing using the variant object (has price, pricing_calculator fields)
        if ($variant !== null && \is_object($variant)) {
            $item->pricing = $productHelper->getPrice($variant, (int) ($item->product_qty ?? 1), $groupId);
        } else {
            $item->pricing = (object) [
                'base_price'    => (float) ($item->variant_price ?? 0),
                'price'         => (float) ($item->variant_price ?? 0),
                'special_price' => null,
                'is_sale_price' => false,
            ];
        }
    }

    /**
     * Validate cart event handler
     *
     * Validates quantity restrictions and stock availability when
     * updating cart quantities for flexi-variable products.
     *
     * @param   CartModel   $model     The cart model instance
     * @param   object      $cartitem  The cart item being validated
     * @param   float       $quantity  The new quantity
     *
     * @return  bool  True if validation passes
     *
     * @throws  \Exception  When validation fails
     *
     * @since   6.0.0
     */
    public function onValidateCart(CartModel &$model, object $cartitem, float $quantity): bool
    {
        // Only process flexivariable products
        if (($cartitem->product_type ?? '') !== 'flexivariable') {
            return true;
        }

        $errors = [];

        // Load variant data
        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variant      = $variantModel->getItem((int) $cartitem->variant_id);

        if (!$variant) {
            throw new \Exception(Text::_('COM_J2COMMERCE_VARIANT_NOT_FOUND'));
        }

        // Get total quantity of this variant in cart
        $cartTotalQty = ProductHelper::getTotalCartQuantity(
            (int) ($variant->j2commerce_variant_id ?? 0)
        );

        // Calculate quantity difference (new total vs current)
        $currentQty    = (float) ($cartitem->product_qty ?? 0);
        $differenceQty = $quantity - $currentQty;

        // Validate minimum/maximum quantity restrictions
        $quantityError = ProductHelper::validateQuantityRestriction(
            $variant,
            (float) $cartTotalQty,
            $differenceQty
        );

        if (!empty($quantityError)) {
            $errors[] = $quantityError;
        }

        // Validate stock availability
        if (!ProductHelper::checkStockStatus($variant, (int) ($cartTotalQty + $differenceQty))) {
            $errors[] = Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
        }

        if (\count($errors) > 0) {
            throw new \Exception(implode("\n", $errors));
        }

        return true;
    }
}
