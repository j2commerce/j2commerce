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

/**
 * Cart Downloadable Product Behavior Class
 *
 * Handles cart lifecycle events for downloadable products including add to cart,
 * cart item retrieval, and cart validation. Downloadable products typically
 * represent digital goods that don't require shipping.
 *
 * @since 6.0.0
 */
class CartDownloadable
{
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
     * Before add cart item event handler
     *
     * Validates product options, quantity restrictions, and stock availability
     * before adding a downloadable product to the cart. Creates the cart item
     * object and triggers plugin events.
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
        $app           = Factory::getApplication();
        $productHelper = new ProductHelper();
        $values        = $app->getInput()->getArray();
        $errors        = [];

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

                // Check if required option is empty
                if (!empty($productOption->required) && empty($options[$optionId])) {
                    $optionName                           = Text::_($productOption->option_name ?? '');
                    $errors['error']['option'][$optionId] = Text::sprintf(
                        'COM_J2COMMERCE_ADDTOCART_PRODUCT_OPTION_REQUIRED',
                        $optionName
                    );
                }

                // Validate option rules if value provided
                if (!empty($options[$optionId])) {
                    $this->validateOptionRules($options[$optionId], $productOption, $errors);
                }
            }
        }

        // Get cart and validate stock (skip for wishlist)
        $cart = $model->getCart();

        if (empty($errors) && isset($cart->cart_type) && $cart->cart_type !== 'wishlist') {
            $variantId = (int) ($product->variants->j2commerce_variant_id ?? 0);

            // Get total quantity of this variant already in cart
            $cartTotalQty = ProductHelper::getTotalCartQuantity($variantId);

            // Validate minimum/maximum quantity restrictions
            $quantityError = ProductHelper::validateQuantityRestriction(
                $product->variants,
                (float) $cartTotalQty,
                (float) $quantity
            );

            if (!empty($quantityError)) {
                $errors['error']['stock'] = $quantityError;
            }

            // Validate inventory/stock status
            if (empty($errors) && !ProductHelper::checkStockStatus($product->variants, (int) ($cartTotalQty + $quantity))) {
                $variantQty = (int) ($product->variants->quantity ?? 0);

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
        if (empty($errors)) {
            $utilityHelper = J2CommerceHelper::utilities();

            // Create cart item object
            $item                  = new CMSObject();
            $item->user_id         = Factory::getApplication()->getIdentity()->id;
            $item->product_id      = (int) $product->j2commerce_product_id;
            $item->variant_id      = (int) ($product->variants->j2commerce_variant_id ?? 0);
            $item->product_qty     = $utilityHelper->stock_qty($quantity);
            $item->product_options = base64_encode(serialize($options));
            $item->product_type    = $product->product_type ?? 'downloadable';
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
     * Get cart items event handler
     *
     * Processes a cart item for downloadable products, loading product data,
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
        // Only process downloadable products
        if (($item->product_type ?? '') !== 'downloadable') {
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

        // Load full product data including images and variants
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

        // Get option price data
        $productOptionData = ProductHelper::getOptionPrice(
            \is_array($options) ? $options : [],
            (int) $product->j2commerce_product_id
        );

        // Set item properties (preserve existing values if product doesn't have them)
        $item->product_name     = $product->product_name ?? $item->product_name ?? '';
        $item->product_view_url = $product->product_view_url ?? $item->product_view_url ?? '';
        $item->options          = $productOptionData['option_data'] ?? [];
        $item->option_price     = $productOptionData['option_price'] ?? 0.0;

        // Build cartitem_params with thumb_image for template display
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

        // Resolve variant from full product
        $variant = $product->variant ?? null;
        if ($variant === null && !empty($product->variants) && \is_array($product->variants)) {
            $variant = reset($product->variants);
        }
        if ($variant !== null && \is_object($variant)) {
            $allowBackorders = (int) ($variant->allow_backorder ?? 0);
            if ($allowBackorders > 0) {
                $variantQty = (int) ($variant->quantity ?? 0);
                $cartQty    = (int) ($item->product_qty ?? 1);
                if ($cartQty > $variantQty) {
                    $existingParams['back_order_item'] = 'COM_J2COMMERCE_CART_BACKORDER_ITEM';
                }
            }
            $item->taxprofile_id = (int) ($product->taxprofile_id ?? 0);
        }

        $item->cartitem_params = json_encode($existingParams);

        // Calculate weight (downloadable products typically have no weight but support it for consistency)
        $baseWeight         = (float) ($item->weight ?? 0);
        $optionWeight       = (float) ($productOptionData['option_weight'] ?? 0);
        $item->weight       = $baseWeight + $optionWeight;
        $item->weight_total = $item->weight * (float) ($item->product_qty ?? 1);

        // Get group ID for pricing
        $groupId = '';
        if (!empty($item->group_id)) {
            $groupId = $item->group_id;
        }

        // Calculate pricing using the variant object (getPrice expects variant with price, pricing_calculator etc.)
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
     * updating cart quantities for downloadable products.
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
        // Only process downloadable products
        if (($cartitem->product_type ?? '') !== 'downloadable') {
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

    /**
     * Validate option rules for a product option
     *
     * Validates option-specific rules such as min/max length for text fields,
     * required selections, etc.
     *
     * @param   mixed    $optionValue    The submitted option value
     * @param   object   $productOption  The product option configuration
     * @param   array    $errors         The errors array (by reference)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    protected function validateOptionRules($optionValue, object $productOption, array &$errors): void
    {
        $optionId   = $productOption->j2commerce_productoption_id ?? 0;
        $optionType = $productOption->type ?? '';
        $optionName = Text::_($productOption->option_name ?? '');

        // Text/Textarea validation
        if (\in_array($optionType, ['text', 'textarea'])) {
            $value  = \is_string($optionValue) ? trim($optionValue) : '';
            $length = \strlen($value);

            // Check minimum length
            $minLength = (int) ($productOption->option_min_length ?? 0);
            if ($minLength > 0 && $length < $minLength) {
                $errors['error']['option'][$optionId] = Text::sprintf(
                    'COM_J2COMMERCE_OPTION_MIN_LENGTH_ERROR',
                    $optionName,
                    $minLength
                );
            }

            // Check maximum length
            $maxLength = (int) ($productOption->option_max_length ?? 0);
            if ($maxLength > 0 && $length > $maxLength) {
                $errors['error']['option'][$optionId] = Text::sprintf(
                    'COM_J2COMMERCE_OPTION_MAX_LENGTH_ERROR',
                    $optionName,
                    $maxLength
                );
            }
        }

        // Checkbox/multi-select validation (minimum/maximum selections)
        if (\in_array($optionType, ['checkbox', 'multiselect'])) {
            $selected = \is_array($optionValue) ? \count($optionValue) : 0;

            // Check minimum selections
            $minSelect = (int) ($productOption->option_min_selections ?? 0);
            if ($minSelect > 0 && $selected < $minSelect) {
                $errors['error']['option'][$optionId] = Text::sprintf(
                    'COM_J2COMMERCE_OPTION_MIN_SELECTIONS_ERROR',
                    $optionName,
                    $minSelect
                );
            }

            // Check maximum selections
            $maxSelect = (int) ($productOption->option_max_selections ?? 0);
            if ($maxSelect > 0 && $selected > $maxSelect) {
                $errors['error']['option'][$optionId] = Text::sprintf(
                    'COM_J2COMMERCE_OPTION_MAX_SELECTIONS_ERROR',
                    $optionName,
                    $maxSelect
                );
            }
        }
    }
}
