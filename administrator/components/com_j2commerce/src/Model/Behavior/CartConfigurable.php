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
use J2Commerce\Component\J2commerce\Administrator\Model\ProductOptionsModel;
use J2Commerce\Component\J2commerce\Administrator\Model\VariantsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Object\CMSObject;

class CartConfigurable
{
    protected MVCFactoryInterface $mvcFactory;

    public function __construct(?MVCFactoryInterface $mvcFactory = null)
    {
        $this->mvcFactory = $mvcFactory
            ?: Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory();
    }

    public function onBeforeAddCartItem(CartModel &$model, object $product, \stdClass &$json): void
    {
        $app           = Factory::getApplication();
        $productHelper = new ProductHelper();
        $values        = $app->getInput()->getArray();
        $errors        = [];

        $quantity = $app->getInput()->getFloat('product_qty', 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Get submitted options
        $options     = $app->getInput()->get('product_option', [], 'ARRAY');
        $checkOption = $options;
        $options     = \is_array($options) ? array_filter($options) : [];

        // Reload product options WITHOUT parent_id filter (need all for validation)
        /** @var ProductOptionsModel $optionsModel */
        $optionsModel = $this->mvcFactory->createModel('ProductOptions', 'Administrator', ['ignore_request' => true]);
        $optionsModel->setState('filter.product_id', (int) $product->j2commerce_product_id);
        $optionsModel->setState('list.limit', 0);
        $optionsModel->setState('list.start', 0);
        // No parent_id filter — load ALL options including children
        $reloadedProductOptions = $optionsModel->getItems();

        unset($product->product_options);
        $product->product_options = $reloadedProductOptions;

        $productOptions = $productHelper->getProductOptions($product);
        $omitCheck      = [];

        // Index the raw product option records by productoption_id for parent_id lookups
        $optionIndex = [];
        foreach ($reloadedProductOptions as $po) {
            $optionIndex[(int) $po->j2commerce_productoption_id] = $po;
        }

        // Validate required options considering parent/child hierarchy
        foreach ($productOptions as $productOption) {
            $productOptionId = $productOption['productoption_id'];

            // Look up the raw product option record for parent_id
            $checkRequire = $optionIndex[(int) $productOptionId] ?? null;
            if (!$checkRequire) {
                continue;
            }

            // Required parent option not selected, OR option is in omit list (child of unselected parent)
            if ($checkRequire->required
                && empty($checkOption[$productOptionId])
                && $checkRequire->parent_id == 0
                && !\in_array($productOptionId, $omitCheck)) {
                $errors['error']['option'][$productOptionId] = Text::sprintf(
                    'COM_J2COMMERCE_ADDTOCART_PRODUCT_OPTION_REQUIRED',
                    Text::_($productOption['option_name'])
                );
            } elseif (\array_key_exists($productOptionId, $options)) {
                // Parent option is selected — check if it has children that should be tracked
                $selectedValues = $options[$productOptionId];

                if (\is_array($selectedValues)) {
                    foreach ($selectedValues as $optionvalue) {
                        $childOpts = ProductHelper::getChildProductOptions(
                            (int) $product->j2commerce_product_id,
                            (int) $checkRequire->option_id,
                            (int) $optionvalue
                        );

                        if (!empty($childOpts)) {
                            foreach ($childOpts as $attr) {
                                if (\is_array($attr['optionvalue'] ?? null)
                                    && \count($attr['optionvalue']) > 0
                                    && ($attr['required'] ?? false)
                                    && !\array_key_exists($attr['productoption_id'], $options)) {
                                    $omitCheck[] = $attr['productoption_id'];
                                }
                            }
                        }
                    }
                } else {
                    $childOpts = ProductHelper::getChildProductOptions(
                        (int) $product->j2commerce_product_id,
                        (int) $checkRequire->option_id,
                        (int) $selectedValues
                    );

                    if (!empty($childOpts)) {
                        foreach ($childOpts as $attr) {
                            if (\is_array($attr['optionvalue'] ?? null)
                                && \count($attr['optionvalue']) > 0
                                && ($attr['required'] ?? false)
                                && !\array_key_exists($attr['productoption_id'], $options)) {
                                $omitCheck[] = $attr['productoption_id'];
                            }
                        }
                    }
                }
            }
        }

        // Validate stock (skip for wishlist)
        $cart = $model->getCart();

        if (empty($errors) && isset($cart->cart_type) && $cart->cart_type !== 'wishlist') {
            $variant = $product->variant ?? null;
            if ($variant === null && !empty($product->variants) && \is_array($product->variants)) {
                $variant = reset($product->variants);
            }

            if ($variant !== null && \is_object($variant)) {
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
                    $variantQty               = (int) ($variant->quantity ?? 0);
                    $errors['error']['stock'] = $variantQty > 0
                        ? Text::sprintf('COM_J2COMMERCE_LOW_STOCK_WITH_QUANTITY', $variantQty)
                        : Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
                }
            }
        }

        // Create cart item if no errors
        if (empty($errors)) {
            $item             = new CMSObject();
            $item->user_id    = Factory::getApplication()->getIdentity()->id;
            $item->product_id = (int) $product->j2commerce_product_id;

            $variantId = 0;
            if (!empty($product->variant->j2commerce_variant_id)) {
                $variantId = (int) $product->variant->j2commerce_variant_id;
            } elseif (!empty($product->variants) && \is_array($product->variants)) {
                $firstVariant = reset($product->variants);
                $variantId    = (int) ($firstVariant->j2commerce_variant_id ?? 0);
            }

            $item->variant_id      = $variantId;
            $item->product_qty     = (int) $quantity;
            $item->product_options = base64_encode(serialize($options));
            $item->product_type    = 'configurable';
            $item->vendor_id       = isset($product->vendor_id) ? (int) $product->vendor_id : 0;

            // Trigger plugin events
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

    public function onGetCartItems(CartModel &$model, object &$item): void
    {
        if (($item->product_type ?? '') !== 'configurable') {
            return;
        }

        $productHelper = new ProductHelper();

        $options = [];
        if (!empty($item->product_options)) {
            $decoded = @unserialize(base64_decode($item->product_options));
            if ($decoded !== false) {
                $options = $decoded;
            }
        }

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

        $productOptionData = ProductHelper::getOptionPrice(
            \is_array($options) ? $options : [],
            (int) $product->j2commerce_product_id
        );

        $item->product_name     = $product->product_name ?? $item->product_name ?? '';
        $item->product_view_url = $product->product_view_url ?? $item->product_view_url ?? '';
        $item->options          = $productOptionData['option_data'] ?? [];
        $item->option_price     = $productOptionData['option_price'] ?? 0.0;

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

        // Back order status
        $variant = $product->variant ?? null;
        if ($variant === null && !empty($product->variants) && \is_array($product->variants)) {
            $variant = reset($product->variants);
        }
        if ($variant !== null && \is_object($variant)) {
            if ((int) ($variant->allow_backorder ?? 0) > 0) {
                $variantQty = (int) ($variant->quantity ?? 0);
                $cartQty    = (int) ($item->product_qty ?? 1);
                if ($cartQty > $variantQty) {
                    $existingParams['back_order_item'] = 'COM_J2COMMERCE_CART_BACKORDER_ITEM';
                }
            }
            $item->taxprofile_id = (int) ($product->taxprofile_id ?? 0);
        }

        $item->cartitem_params = json_encode($existingParams);

        // Calculate weight
        $baseWeight         = (float) ($item->weight ?? 0);
        $optionWeight       = (float) ($productOptionData['option_weight'] ?? 0);
        $item->weight       = $baseWeight + $optionWeight;
        $item->weight_total = $item->weight * (float) ($item->product_qty ?? 1);

        $groupId = !empty($item->group_id) ? $item->group_id : '';

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

    public function onValidateCart(CartModel &$model, object $cartitem, float $quantity): bool
    {
        if (($cartitem->product_type ?? '') !== 'configurable') {
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
