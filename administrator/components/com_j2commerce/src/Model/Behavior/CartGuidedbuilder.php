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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class CartGuidedbuilder
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

        $plugin    = PluginHelper::getPlugin('j2commerce', 'app_guidedbuilder');
        $appParams = new Registry($plugin->params ?? '');

        $productParams = $product->params instanceof Registry
            ? $product->params
            : new Registry($product->params ?? '{}');

        // Get selections from POST (JSON-encoded step selections)
        $selectionsJson = $app->getInput()->getString('gb_selections', '');
        $selections     = !empty($selectionsJson) ? (json_decode($selectionsJson, true) ?: []) : [];
        $options        = [];

        if (empty($selections)) {
            $errors['error']['selections'] = Text::_('PLG_J2COMMERCE_APP_GUIDEDBUILDER_ERR_NO_SELECTIONS');
        } else {
            // Validate all required steps have selections
            $steps = $this->getStepsForProduct((int) $product->j2commerce_product_id);

            foreach ($steps as $step) {
                if ((int) $step->required && empty($selections[$step->step_number])) {
                    $label                                         = $step->step_label ?: ($step->option_name ?? 'Step ' . $step->step_number);
                    $errors['error']['step_' . $step->step_number] = Text::sprintf(
                        'PLG_J2COMMERCE_APP_GUIDEDBUILDER_ERR_STEP_REQUIRED',
                        $label
                    );
                }
            }

            if (!$errors) {
                // Price verification: option prices come from DB via getProductOptionValue(),
                // not from client input. Client-side PriceCalculator is display-only.

                // Allow third-party plugins to intercept or block add-to-cart
                $allow = true;
                J2CommerceHelper::plugin()->event('GBBeforeAddToCart', [
                    $product,
                    $selections,
                    &$allow,
                    &$errors,
                ]);

                if (!$allow) {
                    $json->result = $errors;
                    return;
                }

                // Load step labels from DB for cart display
                $stepData   = $this->getStepsForProduct((int) $product->j2commerce_product_id);
                $stepLabels = [];
                foreach ($stepData as $s) {
                    $stepLabels[(int) $s->step_number] = $s->step_label ?: ($s->option_name ?? 'Step ' . $s->step_number);
                }

                // Build cart options from step selections
                foreach ($selections as $stepNum => $selection) {
                    // Self-contained types (builder_card, builder_slider) send rich objects
                    if (\is_array($selection) && isset($selection['display_type'])) {
                        $displayType = $selection['display_type'];
                        $stepLabel   = $stepLabels[(int) $stepNum] ?? 'Step ' . $stepNum;
                        $valueLabel  = $selection['value_label'] ?? '';
                        $prefix      = $selection['price_prefix'] ?? '+';

                        if ($displayType === 'builder_slider' && !empty($selection['sliders'])) {
                            // Build visible slider IDs (per-slider visibility rules)
                            $stepCfg = null;
                            foreach ($stepData as $s) {
                                if ((int) $s->step_number === (int) $stepNum) {
                                    $stepCfg = $s;
                                    break;
                                }
                            }
                            $visibleIds = [];
                            foreach ($stepCfg->sliders ?? [] as $cfgSl) {
                                $rules = [];
                                foreach ((array) ($cfgSl->visibility_rules ?? []) as $r) {
                                    $rules[] = (array) $r;
                                }
                                if ($this->evaluateSliderVisibility($rules, $selections)) {
                                    $visibleIds[] = $cfgSl->id ?? '';
                                }
                            }

                            // Create separate option entry per visible slider field
                            foreach ($selection['sliders'] as $sl) {
                                $sliderId = $sl['id'] ?? '';
                                if (!empty($visibleIds) && !\in_array($sliderId, $visibleIds, true)) {
                                    continue;
                                }
                                $slTitle = $sl['title'] ?? '';
                                $rawVal  = $sl['raw_value'] ?? 0;
                                $unit    = $sl['unit'] ?? '';
                                $valText = $rawVal . ($unit ? ' ' . $unit : '');

                                $options[] = [
                                    'product_option_id'      => 0,
                                    'product_optionvalue_id' => 0,
                                    'name'                   => $slTitle ?: $stepLabel,
                                    'option_value'           => $valText,
                                    'price'                  => (string) ((float) ($sl['price_modifier'] ?? 0)),
                                    'price_prefix'           => '+',
                                    'type'                   => 'guidedbuilder',
                                    'option_sku'             => '',
                                    'step_number'            => (int) $stepNum,
                                ];
                            }
                            continue;
                        }

                        // builder_card and other self-contained types
                        $options[] = [
                            'product_option_id'      => 0,
                            'product_optionvalue_id' => 0,
                            'name'                   => $stepLabel,
                            'option_value'           => $valueLabel,
                            'price'                  => (string) ((float) ($selection['price_modifier'] ?? 0)),
                            'price_prefix'           => $prefix,
                            'type'                   => 'guidedbuilder',
                            'option_sku'             => '',
                            'step_number'            => (int) $stepNum,
                        ];
                        continue;
                    }

                    // Array selections: multi-group objects, checkbox arrays, or mixed
                    // After json_decode, both {0: val, 1: val} and [val, val] become PHP lists,
                    // so we handle all cases uniformly by inspecting each element.
                    if (\is_array($selection)) {
                        $stepLabel = $stepLabels[(int) $stepNum] ?? 'Step ' . $stepNum;

                        foreach ($selection as $groupSelection) {
                            if (\is_array($groupSelection) && isset($groupSelection['display_type'])) {
                                // Self-contained type within a group (builder_card)
                                $options[] = [
                                    'product_option_id'      => 0,
                                    'product_optionvalue_id' => 0,
                                    'name'                   => $stepLabel,
                                    'option_value'           => $groupSelection['value_label'] ?? '',
                                    'price'                  => (string) ((float) ($groupSelection['price_modifier'] ?? 0)),
                                    'price_prefix'           => $groupSelection['price_prefix'] ?? '+',
                                    'type'                   => 'guidedbuilder',
                                    'option_sku'             => '',
                                    'step_number'            => (int) $stepNum,
                                ];
                            } elseif (\is_array($groupSelection)) {
                                // Checkbox array within a group: [valueId, valueId, ...]
                                foreach ($groupSelection as $checkValueId) {
                                    $checkValueId = (int) $checkValueId;
                                    if (!$checkValueId) {
                                        continue;
                                    }
                                    $optVal = $this->getProductOptionValue($checkValueId);
                                    if (!$optVal) {
                                        continue;
                                    }
                                    $options[] = [
                                        'product_option_id'      => (int) ($optVal->productoption_id ?? 0),
                                        'product_optionvalue_id' => $checkValueId,
                                        'name'                   => $optVal->optionvalue_name ?? '',
                                        'option_value'           => $optVal->optionvalue_name ?? '',
                                        'price'                  => (string) ($optVal->product_optionvalue_price ?? ''),
                                        'price_prefix'           => $optVal->product_optionvalue_prefix ?: '+',
                                        'type'                   => 'guidedbuilder',
                                        'option_sku'             => $optVal->product_optionvalue_sku ?? '',
                                        'step_number'            => (int) $stepNum,
                                    ];
                                }
                            } else {
                                // Single integer valueId within a group
                                $gvId = (int) $groupSelection;
                                if (!$gvId) {
                                    continue;
                                }
                                $optVal = $this->getProductOptionValue($gvId);
                                if (!$optVal) {
                                    continue;
                                }
                                $options[] = [
                                    'product_option_id'      => (int) ($optVal->productoption_id ?? 0),
                                    'product_optionvalue_id' => $gvId,
                                    'name'                   => $optVal->optionvalue_name ?? '',
                                    'option_value'           => $optVal->optionvalue_name ?? '',
                                    'price'                  => (string) ($optVal->product_optionvalue_price ?? ''),
                                    'price_prefix'           => $optVal->product_optionvalue_prefix ?: '+',
                                    'type'                   => 'guidedbuilder',
                                    'option_sku'             => $optVal->product_optionvalue_sku ?? '',
                                    'step_number'            => (int) $stepNum,
                                ];
                            }
                        }
                        continue;
                    }

                    // Standard option-value-based types (integer value ID)
                    $valueId = (int) $selection;

                    if (!$valueId) {
                        continue;
                    }

                    $optionValue = $this->getProductOptionValue($valueId);

                    if (!$optionValue) {
                        continue;
                    }

                    $options[] = [
                        'product_option_id'      => (int) ($optionValue->productoption_id ?? 0),
                        'product_optionvalue_id' => $valueId,
                        'name'                   => $optionValue->optionvalue_name ?? '',
                        'option_value'           => $optionValue->optionvalue_name ?? '',
                        'price'                  => (string) ($optionValue->product_optionvalue_price ?? ''),
                        'price_prefix'           => $optionValue->product_optionvalue_prefix ?: '+',
                        'type'                   => 'guidedbuilder',
                        'option_sku'             => $optionValue->product_optionvalue_sku ?? '',
                        'step_number'            => (int) $stepNum,
                    ];
                }

                // Store full selections JSON for order processing
                $options[] = [
                    'product_option_id'      => 0,
                    'product_optionvalue_id' => 0,
                    'name'                   => 'gb_selections',
                    'option_value'           => $selectionsJson,
                    'price'                  => '',
                    'price_prefix'           => '',
                    'type'                   => 'gb_selections',
                    'option_sku'             => '',
                ];
            }
        }

        $product->product_options = $options;
        $product->options         = $options;

        $cart = $model->getCart();

        // Stock validation
        if (!$errors && ($cart->cart_type ?? '') !== 'wishlist') {
            $variantId    = (int) ($product->variants->j2commerce_variant_id ?? 0);
            $cartTotalQty = ProductHelper::getTotalCartQuantity($variantId);

            $error = ProductHelper::validateQuantityRestriction($product->variants, (float) $cartTotalQty, (float) $quantity);
            if (!empty($error)) {
                $errors['error']['stock'] = $error;
            }

            if (!ProductHelper::checkStockStatus($product->variants, (int) ($cartTotalQty + $quantity))) {
                $variantQty               = (int) ($product->variants->quantity ?? 0);
                $errors['error']['stock'] = $variantQty > 0
                    ? Text::sprintf('COM_J2COMMERCE_LOW_STOCK_WITH_QUANTITY', $variantQty)
                    : Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
            }
        }

        if (!$errors) {
            $utilityHelper = J2CommerceHelper::utilities();

            $item                  = new CMSObject();
            $item->user_id         = Factory::getApplication()->getIdentity()->id;
            $item->product_id      = (int) $product->j2commerce_product_id;
            $item->variant_id      = (int) ($product->variants->j2commerce_variant_id ?? 0);
            $item->product_qty     = $utilityHelper->stock_qty($quantity);
            $item->product_options = base64_encode(serialize($options));
            $item->product_type    = $product->product_type;
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
                $product->product_options,
            ]);

            foreach ($validationResults as $result) {
                if (!empty($result['error'])) {
                    $errors['error']['general'] = $result['error'];
                }
            }

            if (!$errors) {
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
        if ($item->product_type !== 'guidedbuilder') {
            return;
        }

        $productHelper = new ProductHelper();

        // Deserialize stored options
        $options = [];
        if (!empty($item->product_options)) {
            $decoded = @unserialize(base64_decode($item->product_options));
            if ($decoded !== false) {
                $options = $decoded;
            }
        }

        $optionData   = [];
        $optionPrice  = 0.0;

        foreach ($options as $option) {
            // Skip internal selections storage
            if (($option['type'] ?? '') === 'gb_selections') {
                continue;
            }

            $price       = (float) ($option['price'] ?? 0);
            $pricePrefix = $option['price_prefix'] ?? '+';

            $optionData[] = [
                'product_option_id'      => $option['product_option_id'],
                'product_optionvalue_id' => $option['product_optionvalue_id'],
                'option_id'              => 0,
                'optionvalue_id'         => 0,
                'name'                   => $option['name'],
                'option_value'           => $option['option_value'] ?? $option['name'],
                'type'                   => $option['type'],
                'price'                  => $option['price'],
                'price_prefix'           => $pricePrefix,
                'weight'                 => 0,
                'weight_prefix'          => '',
            ];

            // Accumulate option price modifiers
            $optionPrice += match ($pricePrefix) {
                '+'     => $price,
                '-'     => -$price,
                default => 0,
            };
        }

        // Load full product data
        $product = ProductHelper::getFullProduct((int) $item->product_id, true, true);

        if (!$product) {
            return;
        }

        $item->product_name     = $product->product_name ?? $item->product_name ?? '';
        $item->product_view_url = $product->product_view_url ?? $item->product_view_url ?? '';
        $item->options          = $optionData;
        $item->option_price     = $optionPrice;
        $item->weight_total     = (float) ($item->weight ?? 0) * (float) ($item->product_qty ?? 1);

        // Store thumb_image in cartitem_params (same pattern as CartSimple)
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
        $item->cartitem_params = json_encode($existingParams);

        // Pricing must use the variant object (has base price), not the cart item
        $variant = $product->variants ?? null;
        $groupId = !empty($item->group_id) ? $item->group_id : '';
        if ($variant !== null && \is_object($variant)) {
            $item->pricing = $productHelper->getPrice($variant, (int) ($item->product_qty ?? 1), $groupId);
        } else {
            $item->pricing = $productHelper->getPrice($item, (int) ($item->product_qty ?? 1), $groupId);
        }
    }

    /** @throws \Exception */
    public function onValidateCart(CartModel &$model, object $cartitem, float $quantity): bool
    {
        if ($cartitem->product_type !== 'guidedbuilder') {
            return true;
        }

        $errors = [];

        /** @var VariantsModel $variantModel */
        $variantModel = $this->mvcFactory->createModel('Variants', 'Administrator');
        $variant      = $variantModel->getItem((int) $cartitem->variant_id);

        $variantId    = (int) ($variant->j2commerce_variant_id ?? 0);
        $cartTotalQty = ProductHelper::getTotalCartQuantity($variantId);

        $currentQty    = (float) ($cartitem->product_qty ?? 0);
        $differenceQty = $quantity - $currentQty;

        $error = ProductHelper::validateQuantityRestriction($variant, (float) $cartTotalQty, $differenceQty);
        if (!empty($error)) {
            $errors[] = $error;
        }

        if (!ProductHelper::checkStockStatus($variant, (int) ($cartTotalQty + $differenceQty))) {
            $errors[] = Text::_('COM_J2COMMERCE_STOCK_OUT_OF_STOCK');
        }

        if (\count($errors) > 0) {
            throw new \Exception(implode("\n", $errors));
        }

        return true;
    }

    private function getStepsForProduct(int $productId): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s') . '.*',
                $db->quoteName('o.option_name'),
            ])
            ->from($db->quoteName('#__j2commerce_appguidedbuilder_steps', 's'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_product_options', 'po')
                . ' ON ' . $db->quoteName('po.j2commerce_productoption_id')
                . ' = ' . $db->quoteName('s.productoption_id')
            )
            ->leftJoin(
                $db->quoteName('#__j2commerce_options', 'o')
                . ' ON ' . $db->quoteName('o.j2commerce_option_id')
                . ' = ' . $db->quoteName('po.option_id')
            )
            ->where($db->quoteName('s.product_id') . ' = :productId')
            ->where($db->quoteName('s.enabled') . ' = 1')
            ->order($db->quoteName('s.step_number') . ' ASC')
            ->bind(':productId', $productId, ParameterType::INTEGER);

        $db->setQuery($query);

        $steps = $db->loadObjectList() ?: [];

        foreach ($steps as $step) {
            if (!empty($step->params)) {
                $decoded = json_decode($step->params, false);
                if ($decoded) {
                    foreach ($decoded as $key => $value) {
                        $step->$key = $value;
                    }
                }
            }
        }

        return $steps;
    }

    private function evaluateSliderVisibility(array $rules, array $selections): bool
    {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $condStep  = (int) ($rule['step'] ?? 0);
            $condValue = (string) ($rule['value'] ?? '');
            $operator  = $rule['operator'] ?? 'equals';
            $stepSel   = $selections[$condStep] ?? null;

            $selectedVal = (\is_array($stepSel) && isset($stepSel['display_type']))
                ? (string) ($stepSel['value_id'] ?? '')
                : (string) ($stepSel ?? '');

            $match = match ($operator) {
                'not_equals' => $selectedVal !== $condValue,
                default      => $selectedVal === $condValue,
            };

            if (!$match) {
                return false;
            }
        }

        return true;
    }

    private function getProductOptionValue(int $productOptionValueId): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('pov') . '.*',
                $db->quoteName('ov.optionvalue_name'),
                $db->quoteName('ov.optionvalue_image'),
            ])
            ->from($db->quoteName('#__j2commerce_product_optionvalues', 'pov'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_optionvalues', 'ov')
                . ' ON ' . $db->quoteName('ov.j2commerce_optionvalue_id')
                . ' = ' . $db->quoteName('pov.optionvalue_id')
            )
            ->where($db->quoteName('pov.j2commerce_product_optionvalue_id') . ' = :id')
            ->bind(':id', $productOptionValueId, ParameterType::INTEGER);

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }
}
