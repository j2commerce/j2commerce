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
use Joomla\Registry\Registry;

/**
 * Cart Variable Product Behavior Class
 *
 * Handles cart lifecycle events for variable products including add to cart
 * validation, cart item retrieval with pricing, and cart update validation.
 *
 * Variable products require the buyer to select an option combination (e.g.
 * size + color) before adding to cart. The selected combination is resolved
 * to a specific variant via getVariantByOptions() / getVariantById().
 *
 * @since 6.0.0
 */
class CartVariable
{
    protected MVCFactoryInterface $mvcFactory;

    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    /**
     * Before add cart item event handler
     *
     * Validates required option selections, resolves the matching variant,
     * validates quantity restrictions and stock before adding to cart.
     */
    public function onBeforeAddCartItem(CartModel &$model, object $product, \stdClass &$json): void
    {
        $app    = Factory::getApplication();
        $values = $app->getInput()->getArray();
        $errors = [];

        $quantity = $app->getInput()->getFloat('product_qty', 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $options = $app->getInput()->get('product_option', [], 'ARRAY');
        $options = \is_array($options) ? array_filter($options) : [];

        // Validate that all required product options are selected
        if (!empty($product->product_options)) {
            foreach ($product->product_options as $productOption) {
                $optionId = $productOption->j2commerce_productoption_id ?? 0;
                if (empty($options[$optionId])) {
                    $optionName                           = Text::_($productOption->option_name ?? '');
                    $errors['error']['option'][$optionId] = Text::sprintf(
                        'COM_J2COMMERCE_ADDTOCART_PRODUCT_OPTION_REQUIRED',
                        $optionName
                    );
                }
            }
        }

        // Resolve variant from submitted options
        $variant = null;
        if (empty($errors)) {
            // Try variant_id from JS first (faster), then verify/fallback to option-based lookup
            $variantId = $app->getInput()->getInt('variant_id', 0);

            if ($variantId) {
                /** @var VariantsModel $variantModel */
                $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
                $variant      = $variantModel->getItem($variantId);

                if (!$variant || $variant->j2commerce_variant_id != $variantId
                    || $variant->product_id != $product->j2commerce_product_id) {
                    $errors['error']['general'] = Text::_('COM_J2COMMERCE_VARIANT_NOT_FOUND');
                    $variant                    = null;
                }

                // Double-check via options (JS selection can be stale)
                if ($variant) {
                    $verifyVariant = ProductHelper::getVariantByOptions($options, (int) $product->j2commerce_product_id);
                    if ($verifyVariant && $verifyVariant->j2commerce_variant_id != $variantId) {
                        $variant = $verifyVariant;
                    }
                }
            } else {
                $variant = ProductHelper::getVariantByOptions($options, (int) $product->j2commerce_product_id);
                if ($variant === null) {
                    $errors['error']['general'] = Text::_('COM_J2COMMERCE_VARIANT_NOT_FOUND');
                }
            }
        }

        // Validate stock and quantity (skip for wishlist)
        $cart = $model->getCart();

        if (empty($errors) && $variant && isset($cart->cart_type) && $cart->cart_type !== 'wishlist') {
            $variantId    = (int) ($variant->j2commerce_variant_id ?? 0);
            $cartTotalQty = ProductHelper::getTotalCartQuantity($variantId);

            $quantityError = ProductHelper::validateQuantityRestriction(
                $variant,
                (float) $cartTotalQty,
                (float) $quantity
            );

            if (!empty($quantityError)) {
                $errors['error']['stock'] = $quantityError;
            }

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

        // Add to cart if no errors
        if (empty($errors) && $variant) {
            $utilityHelper = J2CommerceHelper::utilities();

            $item                  = new CMSObject();
            $item->user_id         = Factory::getApplication()->getIdentity()->id;
            $item->product_id      = (int) $product->j2commerce_product_id;
            $item->variant_id      = (int) ($variant->j2commerce_variant_id ?? 0);
            $item->product_qty     = $utilityHelper->stock_qty($quantity);
            $item->product_type    = $product->product_type ?? 'variable';
            $item->product_options = base64_encode(serialize($options));
            $item->vendor_id       = isset($product->vendor_id) ? (int) $product->vendor_id : 0;

            $pluginHelper = J2CommerceHelper::plugin();
            $results      = $pluginHelper->event('AfterCreateItemForAddToCart', [$item, $values]);

            foreach ($results as $result) {
                if (\is_array($result)) {
                    foreach ($result as $key => $value) {
                        $item->set($key, $value);
                    }
                }
            }

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
     * Loads variant data, resolves option display names, calculates pricing
     * and weight for variable products in the cart.
     */
    public function onGetCartItems(CartModel &$model, object &$item): void
    {
        if (($item->product_type ?? '') !== 'variable') {
            return;
        }

        $productHelper = new ProductHelper();

        // Decode stored options
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

        // Build option display data
        foreach ($options as $productOptionId => $optionValue) {
            $productOptionId = (int) $productOptionId;
            $optionValueInt  = (int) $optionValue;

            $productOption = ProductHelper::getCartProductOptions($productOptionId, (int) $item->product_id);

            if (!$productOption) {
                continue;
            }

            if ($productOption->type === 'select' || $productOption->type === 'radio') {
                $productOptionValue = ProductHelper::getCartProductOptionValues($productOptionId, $optionValueInt);

                if (!$productOptionValue) {
                    continue;
                }

                // Price adjustment
                $prefix      = $productOptionValue->product_optionvalue_prefix ?? '+';
                $priceAdjust = (float) ($productOptionValue->product_optionvalue_price ?? 0);
                if ($prefix === '+') {
                    $optionPrice += $priceAdjust;
                } elseif ($prefix === '-') {
                    $optionPrice -= $priceAdjust;
                }

                // Weight adjustment
                $weightPrefix = $productOptionValue->product_optionvalue_weight_prefix ?? '+';
                $weightAdjust = (float) ($productOptionValue->product_optionvalue_weight ?? 0);
                if ($weightPrefix === '+') {
                    $optionWeight += $weightAdjust;
                } elseif ($weightPrefix === '-') {
                    $optionWeight -= $weightAdjust;
                }

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
        }

        // Load full product data for name, images, URL
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

        $item->product_name     = $product->product_name ?? $item->product_name ?? '';
        $item->product_view_url = $product->product_view_url ?? $item->product_view_url ?? '';
        $item->options          = $optionData;
        $item->option_price     = 0.0; // Option price already in variant price for variable

        // Build cartitem_params with thumb_image
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

        // Override with variant-specific image if present
        if ($variant) {
            $variantParams = new Registry($variant->params ?? '{}');
            $mainImage     = $variantParams->get('variant_main_image', '');
            $isMainAsThumb = (int) $variantParams->get('is_main_as_thum', 0);

            if (!empty($mainImage)) {
                $item->main_image = $mainImage;
                if ($isMainAsThumb) {
                    $existingParams['thumb_image'] = $mainImage;
                }
            }

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

        $variantWeight      = (float) ($variant->weight ?? 0);
        $item->weight       = $variantWeight;
        $item->weight_total = $variantWeight * (float) ($item->product_qty ?? 1);

        $groupId = !empty($item->group_id) ? $item->group_id : '';

        // Calculate pricing using the variant (has price + pricing_calculator fields)
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
     * Validates quantity restrictions and stock availability when the buyer
     * updates the quantity of a variable product in the cart.
     *
     * @throws  \Exception  When validation fails
     */
    public function onValidateCart(CartModel &$model, object $cartitem, float $quantity): bool
    {
        if (($cartitem->product_type ?? '') !== 'variable') {
            return true;
        }

        $errors = [];

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variant      = $variantModel->getItem((int) $cartitem->variant_id);

        if (!$variant) {
            throw new \Exception(Text::_('COM_J2COMMERCE_VARIANT_NOT_FOUND'));
        }

        $cartTotalQty = ProductHelper::getTotalCartQuantity(
            (int) ($variant->j2commerce_variant_id ?? 0)
        );

        $currentQty    = (float) ($cartitem->product_qty ?? 0);
        $differenceQty = $quantity - $currentQty;

        $quantityError = ProductHelper::validateQuantityRestriction(
            $variant,
            (float) $cartTotalQty,
            $differenceQty
        );

        if (!empty($quantityError)) {
            $errors[] = $quantityError;
        }

        if (!ProductHelper::checkStockStatus($variant, (int) ($cartTotalQty + $differenceQty))) {
            $errors[] = Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
        }

        if (\count($errors) > 0) {
            throw new \Exception(implode("\n", $errors));
        }

        return true;
    }
}
