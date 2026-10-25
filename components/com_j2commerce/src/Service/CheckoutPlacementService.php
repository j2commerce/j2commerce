<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Site\Service;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\CartHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CartOrder;
use J2Commerce\Component\J2commerce\Administrator\Helper\ConfigHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CurrencyHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use J2Commerce\Component\J2commerce\Site\Helper\CheckoutContextHelper;
use J2Commerce\Component\J2commerce\Site\Helper\CheckoutStepStateHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\Exception\ConnectionFailureException;
use Joomla\Database\Exception\ExecutionFailureException;
use Joomla\Database\Exception\PrepareStatementFailureException;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Order placement and the rules that gate it, shared by core checkout and any checkout app.
 *
 * Callers resolve it from the container, assert instanceof, and dispatch their own
 * ChangeShowPaymentOnTotalZero and BeforeCheckoutConfirm: those belong to the path, not to placement.
 */
final class CheckoutPlacementService
{
    /** Order id of the row the last placeOrder() call had saved or reused when it threw; '' otherwise. */
    private string $lastOrderId = '';

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly MVCFactoryInterface $factory,
    ) {
    }

    /**
     * Everything checkout.confirm does between "errors are empty" and "render".
     *
     * onJ2CommerceBeforePlaceOrder (named args order, paymentElement, result) is veto-only: a listener
     * appends translated strings to `result` and only non-empty string entries count. The order is
     * snapshotted around the event, so a listener that changes the totals, owner, payment method,
     * lines, addresses, email or fees is refused as out of date rather than placed.
     *
     * onJ2CommerceAfterPlaceOrder (named args order, placement) is informational. It fires once per
     * placement, including when an earlier unpaid row was reused (`placement->reused`), and the order
     * is unpaid (state 5) at that point: it is not a "paid" signal. `order` is a read-only snapshot
     * (a detached copy), so a listener cannot change the placed order or what confirm renders.
     *
     * @throws  \Exception  What saveOrder() and PrePayment plugins throw; the caller logs it.
     */
    public function placeOrder(CartOrder $order, string $paymentElement, bool $showPayment): PlacementResult
    {
        $this->lastOrderId = '';

        $orderpaymentType = $paymentElement;
        $freeRedirect     = '';
        $pluginHtml       = '';

        $this->applyPaymentTo($order, $orderpaymentType);

        // A listener appends translated messages to `result`; any message stops the placement.
        $before = $this->orderSnapshot($order);
        $veto   = J2CommerceHelper::plugin()->event('BeforePlaceOrder', [
            'order'          => $order,
            'paymentElement' => $paymentElement,
            'result'         => [],
        ]);
        $errors = [];

        foreach ((array) $veto->getArgument('result', []) as $message) {
            if (\is_string($message) && $message !== '') {
                $errors[] = $message;
            }
        }

        $drift = $errors === []
            ? array_keys(array_filter($this->orderSnapshot($order), static fn ($value, $group) => $value !== $before[$group], ARRAY_FILTER_USE_BOTH))
            : [];

        if ($drift) {
            Log::add('checkout.placeOrder BeforePlaceOrder listener changed the order (' . implode(', ', $drift) . '); placement refused', Log::ERROR, 'com_j2commerce');

            $errors[] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_ORDER_OUT_OF_DATE');
        }

        if ($errors) {
            return new PlacementResult($order, $order, false, $showPayment, 0.0, '', '', $errors);
        }

        // Idempotency guard: a prior confirm() for this cart in this session
        // (double-click, page reload, or concurrent request race) already
        // persisted an order, and repeating the save would insert a duplicate.
        //
        // The prior row is only reusable while it still describes the same
        // purchase. cart_id identifies the cart rather than the purchase: it
        // is unchanged by a quantity edit, an added or removed line, a coupon
        // or a different shipping rate, all of which are reachable from the
        // confirm step's Modify links. Matching on it alone charged the
        // shopper from the earlier row.
        //
        // So the prior row is compared against what is being confirmed now,
        // and reused only when the two agree. Anything else falls through to
        // a fresh order: the earlier row is never altered, which keeps this
        // decision away from any amount a payment plugin may already have
        // handed a gateway for it.
        //
        // Addresses, email, payment method and shipping rate are held to the
        // fingerprint recorded at save; a row without one is not reused.
        $savedOrder   = null;
        $priorOrderId = $this->app->getUserState('j2commerce.order_id');
        $fingerprint  = $order->getSaveFingerprint();

        if (!empty($priorOrderId)) {
            $priorTable = $this->factory->createTable('Order', 'Administrator');

            if ($priorTable && $priorTable->load(['order_id' => $priorOrderId])) {
                $currentCart   = CartHelper::getInstance()->getCart();
                $currentCartId = (int) ($currentCart->j2commerce_cart_id ?? 0);
                $currentUserId = (int) ($this->app->getIdentity()->id ?? 0);

                $isSameCart  = $currentCartId > 0 && (int) ($priorTable->cart_id ?? -1) === $currentCartId;
                $isSameOwner = (int) ($priorTable->user_id ?? -1) === $currentUserId;
                $isUnpaid    = (int) ($priorTable->order_state_id ?? 0) === 5;
                $isSameOrder = $isSameCart
                    && $isSameOwner
                    && $isUnpaid
                    && hash_equals(
                        (string) $this->app->getUserState('j2commerce.order_fingerprint', ''),
                        $priorOrderId . ':' . $fingerprint
                    )
                    && $this->orderMatchesCart($priorTable, $order);

                if ($isSameOrder) {
                    $savedOrder = $priorTable;
                }
            }
        }

        $reused = $savedOrder !== null;

        if ($savedOrder === null) {
            $savedOrder = $order->saveOrder();
        }

        $this->lastOrderId = (string) ($savedOrder->order_id ?? '');

        $this->app->setUserState('j2commerce.order_id', $savedOrder->order_id ?? null);
        $this->app->setUserState('j2commerce.orderpayment_id', $savedOrder->j2commerce_order_id ?? null);
        $this->app->setUserState('j2commerce.order_token', $savedOrder->token ?? null);
        $this->app->setUserState(
            'j2commerce.order_fingerprint',
            empty($savedOrder->order_id) ? null : $savedOrder->order_id . ':' . $fingerprint
        );

        // The in-memory CartOrder does not carry the order_params column
        // (where amount_due_now lives), so reload the persisted row to
        // resolve the true charge-now amount. A fully deferred deposit plan
        // resolves to zero — nothing to charge now.
        $chargeOrder = $this->factory->createTable('Order', 'Administrator');

        if (empty($savedOrder->j2commerce_order_id) || !$chargeOrder->load((int) $savedOrder->j2commerce_order_id)) {
            $chargeOrder = $savedOrder;
        }

        $chargeAmount = CurrencyHelper::baseChargeAmount($chargeOrder);

        if ($showPayment && $chargeAmount === 0.0) {
            $showPayment  = false;
            $freeRedirect = Route::_('index.php?option=com_j2commerce&task=checkout.confirmPayment&' . Session::getFormToken() . '=1');
        }

        if ($showPayment && !empty($orderpaymentType)) {
            $paymentValues = [
                'order_id'            => $savedOrder->order_id ?? '',
                'orderpayment_id'     => $savedOrder->j2commerce_order_id ?? '',
                'orderpayment_amount' => $chargeAmount,
                'order'               => $savedOrder,
            ];

            $prePaymentResults = J2CommerceHelper::plugin()->eventWithArray('PrePayment', [$orderpaymentType, $paymentValues]);

            foreach ($prePaymentResults as $result) {
                $pluginHtml .= $result;
            }
        }

        $placement = new PlacementResult(
            $savedOrder,
            $chargeOrder,
            $reused,
            $showPayment,
            $chargeAmount,
            $pluginHtml,
            $freeRedirect
        );

        // Informational: nothing a listener sets changes the placement, and a listener fault must not undo it.
        // A table row is handed over as a plain object, which has no store(); a CartOrder as a clone.
        try {
            $snapshot = $savedOrder instanceof Table ? (object) get_object_vars($savedOrder) : clone $savedOrder;

            J2CommerceHelper::plugin()->event('AfterPlaceOrder', ['order' => $snapshot, 'placement' => $placement]);
        } catch (\Throwable $e) {
            Log::add('checkout.placeOrder AfterPlaceOrder failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');
        }

        return $placement;
    }

    /** Signature per group of what the placement checks have vouched for; the keys name a drifted group in the log. */
    private function orderSnapshot(CartOrder $order): array
    {
        $lines = [];

        foreach ($order->getItems() as $item) {
            $lines[] = (int) ($item->variant_id ?? 0) . ':' . (float) ($item->product_qty ?? 1) . ':' . (float) ($item->pricing->price ?? 0);
        }

        sort($lines);

        return [
            'totals' => [
                $order->order_total,
                $order->order_subtotal,
                $order->order_tax,
                $order->order_shipping,
                $order->order_shipping_tax,
                $order->order_discount,
                $order->order_surcharge,
            ],
            'owner'     => $order->user_id,
            'payment'   => $order->orderpayment_type,
            'lines'     => $lines,
            'addresses' => $order->getSaveFingerprint(),
            'email'     => $order->resolveCheckoutEmail(),
            'fees'      => md5((string) json_encode($order->get_fees(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)),
        ];
    }

    /** Order id of the row the last placeOrder() saved or reused, for the caller's failure log. */
    public function lastOrderId(): string
    {
        return $this->lastOrderId;
    }

    /**
     * The order-level rules: shipping reprice, stock, and the AfterOrderValidate veto.
     *
     * @param   CartOrder|null  $order  Replaced when the shipping reprice or a listener supplies a new one.
     *
     * @return  string[]  Translated errors.
     */
    public function validateOrder(?CartOrder &$order): array
    {
        $errors = [];

        try {
            $this->repriceShippingSelection($order, $errors);

            // Stock is enforced at add-to-cart and quantity-update, but time passes
            // before confirm — re-check here so two shoppers cannot both buy the last
            // unit. (The former guard called a validateOrder() that exists nowhere.)
            if ($order && !$order->validate_order_stock()) {
                // The boolean is the verdict, the messages only explain it. A listener that
                // re-keyed the line collection it was handed could leave the two disagreeing,
                // and confirm is the last stock gate on this path — so a refusal blocks even
                // when nothing came back to say why.
                $errors = array_merge(
                    $errors,
                    $order->getStockErrors() ?: [Text::_('COM_J2COMMERCE_ERR_GENERIC')]
                );
            }
        } catch (\Throwable $e) {
            Log::add('checkout.confirm order build failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            $errors[] = Text::_('COM_J2COMMERCE_ERR_GENERIC');
        }

        $hadOrder = $order !== null;

        try {
            J2CommerceHelper::plugin()->event('AfterOrderValidate', [&$order]);
        } catch (ExecutionFailureException | ConnectionFailureException | PrepareStatementFailureException $e) {
            // Database faults also extend RuntimeException, so catch them before the plugin-veto arm.
            Log::add('checkout.confirm AfterOrderValidate database failure: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            $errors[] = Text::_('COM_J2COMMERCE_ERR_GENERIC');
        } catch (\RuntimeException $e) {
            // App plugins veto the order here (opening hours, additional terms,
            // subscription payment-method rules) by throwing an already-translated
            // shopper-facing message — it must reach the confirm step verbatim.
            $errors[] = $e->getMessage();
        } catch (\Throwable $e) {
            Log::add('checkout.confirm AfterOrderValidate failed: ' . $e->getMessage(), Log::ERROR, 'com_j2commerce');

            $errors[] = Text::_('COM_J2COMMERCE_ERR_GENERIC');
        }

        // The listener got $order by reference: anything it left that is not a CartOrder would be a
        // TypeError further down, past every catch, so it is treated as a failed build instead.
        if (!$order instanceof CartOrder) {
            $order = null;

            if ($hadOrder) {
                Log::add('checkout.confirm order build failed: AfterOrderValidate left no usable order', Log::ERROR, 'com_j2commerce');

                $errors[] = Text::_('COM_J2COMMERCE_ERR_GENERIC');
            }
        }

        return $errors;
    }

    /**
     * The step-state and payment rules, applied once the caller has settled $showPayment
     * (after ChangeShowPaymentOnTotalZero). Pair it with validateOrder().
     *
     * @return  string[]  Translated errors.
     */
    public function validateForPlacement(?CartOrder $order, string $paymentElement, bool $showPayment): array
    {
        $errors           = [];
        $orderpaymentType = $paymentElement;

        // The step order otherwise lives only in the template JS, so every task is independently
        // dispatchable and a step that never runs is a step whose rules never run. The required set
        // is read from the cart here rather than cached at step time: the Modify links can add a
        // shippable line to a cart whose shipping step was legitimately skipped. An admin-created
        // (context) order has no session step history, so callers do not pass one here.
        //
        // $showPayment must be the settled value, after the plugins that may overturn it have run,
        // so the payment step is required on the same notion of payable as the rest of checkout.
        if ($order) {
            $required = [CheckoutStepStateHelper::BILLING];

            if ($this->showShippingMethods($order)) {
                $required[] = CheckoutStepStateHelper::SHIPPING;
            }

            if ($showPayment) {
                $required[] = CheckoutStepStateHelper::PAYMENT;
            }

            foreach (CheckoutStepStateHelper::missing($required) as $step) {
                $errors[] = Text::sprintf(
                    'COM_J2COMMERCE_CHECKOUT_ERROR_STEP_INCOMPLETE',
                    Text::_(CheckoutStepStateHelper::label($step))
                );
            }
        }

        if ($showPayment && empty(trim($orderpaymentType))) {
            $errors[] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PAYMENT_METHOD_NOT_SELECTED');
        }

        // The selected gateway must survive the same availability pruning the render-time list applied.
        if ($showPayment && !empty(trim($orderpaymentType)) && $order && !$this->isPaymentMethodAllowed($orderpaymentType, $order)) {
            $errors[] = Text::_('COM_J2COMMERCE_CHECKOUT_ERROR_PAYMENT_METHOD');
        }

        return $errors;
    }

    /** Attach the payment method and fold in its surcharge. */
    public function applyPaymentTo(CartOrder $order, string $orderpaymentType): void
    {
        $order->orderpayment_type = $orderpaymentType;
        $order->applyPaymentSurcharge();
    }

    /**
     * Drop payment methods whose plugin restricts them out of range.
     *
     * Applies the geozone (billing address) and subtotal-range restrictions
     * advertised by each payment plugin's params. Centralized here because the
     * per-plugin onJ2CommerceGetPaymentOptions hook is never dispatched.
     *
     * @param   array<int, array<string, mixed>>  $methods
     *
     * @return  array<int, array<string, mixed>>
     */
    public function filterUnavailablePaymentMethods(array $methods, ?object $order): array
    {
        if (empty($methods)) {
            return $methods;
        }

        // null = billing address not resolvable yet → do not restrict by geozone.
        $billingGeozones = $this->getBillingGeozones();
        $subtotal        = (float) ($order->order_subtotal ?? 0);

        $filtered = array_filter($methods, function (array $method) use ($billingGeozones, $subtotal): bool {
            $element = (string) ($method['element'] ?? '');

            if ($element === '') {
                return true;
            }

            $plugin = PluginHelper::getPlugin('j2commerce', $element);

            if (!$plugin) {
                return true;
            }

            $params = new Registry($plugin->params ?? '');

            $geozoneId = (int) $params->get('geozone_id', 0);

            // geozone_id 0 = available everywhere. Otherwise it must match the
            // buyer's billing geozone(s); an empty set means "outside all zones".
            if ($geozoneId > 0 && $billingGeozones !== null && !\in_array($geozoneId, $billingGeozones, true)) {
                return false;
            }

            $minRaw = $params->get('min_subtotal', '');
            $maxRaw = $params->get('max_subtotal', '');

            // A blank field means "no limit" — only the -1 sentinel or a real
            // number constrains. Without this, a saved-empty max_subtotal casts
            // to 0.0 and (0 >= 0) silently hides the method for any non-zero cart.
            $minSubtotal = ($minRaw === '' || $minRaw === null) ? 0.0 : (float) $minRaw;
            $maxSubtotal = ($maxRaw === '' || $maxRaw === null) ? -1.0 : (float) $maxRaw;

            if ($minSubtotal > 0 && $subtotal < $minSubtotal) {
                return false;
            }

            if ($maxSubtotal >= 0 && $subtotal > $maxSubtotal) {
                return false;
            }

            return true;
        });

        return array_values($filtered);
    }

    /**
     * Re-check a chosen gateway against the plugin-declared availability rules
     * (geozone, subtotal range). The render-time filter only pruned the UI list —
     * a shopper can still POST any installed gateway, so validate and confirm must
     * re-run the same pruning against the submitted selection.
     */
    public function isPaymentMethodAllowed(string $element, ?object $order): bool
    {
        if ($element === '') {
            return false;
        }

        $methods = [];
        $results = J2CommerceHelper::plugin()->eventWithArray('GetPaymentPlugins', [$order]);

        foreach ($results as $result) {
            if (\is_array($result) && isset($result['element'])) {
                $methods[] = $result;
            } elseif (\is_array($result)) {
                $methods = array_merge($methods, $result);
            }
        }

        $methods = $this->filterUnavailablePaymentMethods($methods, $order);

        foreach ($methods as $method) {
            if (($method['element'] ?? '') === $element) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the geozone IDs matching the buyer's billing address.
     *
     * @return  int[]|null  Matching geozone IDs, or null when no billing
     *                      address is available (caller skips geozone filtering).
     */
    private function getBillingGeozones(): ?array
    {
        $session   = $this->app->getSession();
        $countryId = 0;
        $zoneId    = 0;

        $addressId = (int) $session->get('billing_address_id', 0, 'j2commerce');
        $userId    = (int) ($this->app->getIdentity()?->id ?? 0);

        // Constrain to the current user: the session id is only ever written for a logged-in
        // shopper, so a row that does not belong to them must not resolve. Guests never hold this
        // key and fall through to the flat billing_country_id/billing_zone_id keys below.
        if ($addressId > 0 && $userId > 0) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);

            $query->select([$db->quoteName('country_id'), $db->quoteName('zone_id')])
                ->from($db->quoteName('#__j2commerce_addresses'))
                ->where($db->quoteName('j2commerce_address_id') . ' = :addrId')
                ->where($db->quoteName('user_id') . ' = :userId')
                ->bind(':addrId', $addressId, ParameterType::INTEGER)
                ->bind(':userId', $userId, ParameterType::INTEGER);

            $db->setQuery($query);
            $address = $db->loadObject();

            if ($address) {
                $countryId = (int) $address->country_id;
                $zoneId    = (int) $address->zone_id;
            }
        }

        if ($countryId === 0) {
            $countryId = (int) $session->get('billing_country_id', 0, 'j2commerce');
            $zoneId    = (int) $session->get('billing_zone_id', 0, 'j2commerce');
        }

        if ($countryId === 0) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('DISTINCT ' . $db->quoteName('geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozonerules'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where('(' . $db->quoteName('zone_id') . ' = 0 OR ' . $db->quoteName('zone_id') . ' = :zoneId)')
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return array_map('intval', $db->loadColumn() ?: []);
    }

    /**
     * Whether a persisted order still describes the cart being confirmed.
     *
     * Compares the money the shopper would be charged and the lines that money is
     * for. The total alone would miss a swap of one line for another at the same
     * price, and the lines alone would miss a coupon or a change of shipping rate,
     * so both are required.
     */
    public function orderMatchesCart(object $priorOrder, CartOrder $cartOrder): bool
    {
        $scale  = CurrencyHelper::getDecimalPlace(ConfigHelper::getDefaultCurrency());
        $factor = 10 ** $scale;

        // Compared in the currency's own minor units. The stored side was rounded to
        // this scale by saveOrder() and the cart's carries whatever precision the tax
        // and fee arithmetic produced (an 8.25% tax on 6.75 is 0.556875), so rounding
        // both to scale is what puts them on the same footing — the cart total is not
        // compared raw.
        $storedTotal  = (int) round(((float) ($priorOrder->order_total ?? 0)) * $factor);
        $currentTotal = (int) round(((float) ($cartOrder->order_total ?? 0)) * $factor);

        if ($storedTotal !== $currentTotal) {
            return false;
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $orderId = (string) ($priorOrder->order_id ?? '');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['variant_id', 'orderitem_quantity']))
            ->from($db->quoteName('#__j2commerce_orderitems'))
            ->where($db->quoteName('order_id') . ' = :orderId')
            ->bind(':orderId', $orderId);

        $db->setQuery($query);

        $storedLines = [];

        foreach ($db->loadObjectList() ?: [] as $row) {
            $storedLines[] = (int) $row->variant_id . ':' . (float) $row->orderitem_quantity;
        }

        $currentLines = [];

        foreach ($cartOrder->getItems() as $item) {
            $currentLines[] = (int) ($item->variant_id ?? 0) . ':' . (float) ($item->product_qty ?? 1);
        }

        sort($storedLines);
        sort($currentLines);

        return $storedLines === $currentLines;
    }

    public function showShippingAddress(?object $order = null): bool
    {
        $params = J2CommerceHelper::config();

        if ($params->get('show_shipping_address', 1)) {
            return true;
        }

        if ($order && method_exists($order, 'getItems')) {
            foreach ($order->getItems() as $item) {
                if (!empty($item->shipping)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function showShippingMethods(?object $order = null): bool
    {
        if ($order && method_exists($order, 'getItems')) {
            foreach ($order->getItems() as $item) {
                if (!empty($item->shipping)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Drop the order this session had in flight, so a later step cannot resume or rewrite it. */
    public function clearPrimedOrder(): void
    {
        $this->app->setUserState('j2commerce.order_id', null);
        $this->app->setUserState('j2commerce.orderpayment_id', null);
        $this->app->setUserState('j2commerce.order_token', null);
        $this->app->setUserState('j2commerce.order_fingerprint', null);
    }

    /**
     * Drop the shipping selection along with the offer list it was made from. A destination
     * change invalidates both, and a selection that outlives its list is priced for an address
     * the order is no longer going to.
     */
    public function clearShippingSelection(): void
    {
        $session = $this->app->getSession();

        $session->clear('shipping_method', 'j2commerce');
        $session->clear('shipping_methods', 'j2commerce');
        $session->clear('shipping_values', 'j2commerce');
    }

    /** The order for the current cart, or the context order when a checkout context owns the request. */
    public function loadCartOrder(): ?object
    {
        // Single predicate: context activated + resolved + valid → serve existing order.
        if (CheckoutContextHelper::isOwningRequest()) {
            return CheckoutContextHelper::resolveContext()?->getOrder();
        }

        $cartsModel = $this->factory->createModel('Carts', 'Site', ['ignore_request' => true]);

        if (!$cartsModel) {
            return null;
        }

        $cartsModel->getState();

        $user = $this->app->getIdentity();

        if ($user && $user->id) {
            $cartsModel->setState('filter.user_id', (int) $user->id);
        }

        return $cartsModel->getOrder();
    }

    /**
     * Re-price the stored shipping selection against a fresh dispatch before the order is built
     * from it. The shipping step is where a selection is normally re-made, but nothing sequences
     * the checkout tasks, so confirm cannot assume it was the last step to run.
     *
     * @param   CartOrder|null  $order   Replaced when the fresh rate differs from the stored one.
     * @param   array           $errors  Appended to when the selection cannot stand and the
     *                                   store requires one — see COM_J2COMMERCE_CONFIG_SHIPPING_REQUIRED.
     */
    private function repriceShippingSelection(?CartOrder &$order, array &$errors): void
    {
        if (!$order instanceof CartOrder || !$this->showShippingMethods($order)) {
            return;
        }

        $session = $this->app->getSession();
        $stored  = $session->get('shipping_values', [], 'j2commerce');
        $stored  = \is_array($stored) ? $stored : [];
        $plugin  = (string) ($stored['shipping_plugin'] ?? '');

        // One dispatch answers both questions this method asks — what is on offer for the
        // destination, and whether the stored selection is still among it. A carrier plugin
        // bills for the answer, so it is asked once.
        $rates = array_values(array_filter(
            CartOrder::collectShippingRates($order),
            [CartOrder::class, 'rateChargesAreValid']
        ));

        // Money comes from the rate the plugins offer now, never from what the session carried.
        $resolved = $plugin === ''
            ? null
            : CartOrder::matchShippingRate(
                $rates,
                $plugin,
                (string) ($stored['shipping_name'] ?? ''),
                (string) ($stored['shipping_code'] ?? '')
            );

        if ($resolved === null) {
            // Nothing to bind: the shipping step was never reached, an address change cleared
            // the selection and it was never re-made, or the rate it named is no longer offered
            // here. A destination the plugins quote for demands a selection out of what they
            // quoted. Where they quote nothing there is nothing to select, and the order stands
            // on the store's own answer to whether shipping is required of it at all.
            if ($rates !== [] || ConfigHelper::isShippingMandatory()) {
                $errors[] = Text::_('COM_J2COMMERCE_CHECKOUT_SELECT_A_SHIPPING_METHOD');

                return;
            }

            $this->clearShippingSelection();

            $resolved = CartOrder::emptyShippingValues();
        }

        // Re-pricing answers for the destination, not for the tax: where a tax source has
        // already answered for this line, its figure stands, because the rate's own tax is
        // the estimate that figure exists to replace. It stands only over the charge it was
        // given for, though — a rate that re-quotes to a different amount invalidates the
        // tax on it as surely as a different destination would, so the answer is dropped and
        // the source is left to give one for the new charge.
        $sameCharge = (float) ($stored['shipping_price'] ?? 0) === (float) ($resolved['shipping_price'] ?? 0)
            && (float) ($stored['shipping_extra'] ?? 0) === (float) ($resolved['shipping_extra'] ?? 0);

        if (!empty($stored['shipping_tax_resolved']) && $sameCharge) {
            $resolved['shipping_tax']          = (string) (float) ($stored['shipping_tax'] ?? 0);
            $resolved['shipping_tax_resolved'] = true;
        }

        $session->set('shipping_values', $resolved, 'j2commerce');

        $charges = static fn (array $values): array => [
            (float) ($values['shipping_price'] ?? 0),
            (float) ($values['shipping_tax'] ?? 0),
            (float) ($values['shipping_extra'] ?? 0),
            (int) ($values['shipping_tax_class_id'] ?? 0),
            !empty($values['shipping_tax_resolved']),
        ];

        if ($charges($stored) === $charges($resolved)) {
            return;
        }

        // The order in hand was built from the superseded figures — build it again from these.
        $rebuilt = $this->loadCartOrder();

        if ($rebuilt instanceof CartOrder) {
            $order = $rebuilt;
        }
    }
}
