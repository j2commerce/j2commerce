<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingStandard
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ShippingStandard\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use J2Commerce\Plugin\J2Commerce\ShippingStandard\Table\ShippingMethodTable;
use J2Commerce\Plugin\J2Commerce\ShippingStandard\Table\ShippingRateTable;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * Standard Shipping Plugin for J2Commerce
 *
 * Provides 7 shipping rate calculation types:
 *   0 = Per Order Flat Rate
 *   1 = Per Order Quantity Based
 *   2 = Per Order Price Based
 *   3 = Per Item Flat Rate
 *   4 = Per Item Weight Based
 *   5 = Per Order Weight Based
 *   6 = Per Item Price Based (Percentage)
 *
 * Also handles admin CRUD via the shipping plugin bridge events:
 *   - onShippingPluginView: renders list/edit views for methods
 *   - onShippingPluginTask: handles save/delete/publish/unpublish
 *   - onShippingPluginAjax: handles AJAX rate management
 *   - Toolbar buttons added via ShippingToolbarField on plugin config page
 *
 * @since  6.0.0
 */
final class ShippingStandard extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

    protected $_name = 'shipping_standard';

    protected $_type = 'j2commerce';

    private const TYPE_LABELS = [
        0 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_FLAT',
        1 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_QUANTITY',
        2 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_PRICE',
        3 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_FLAT',
        4 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_WEIGHT',
        5 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ORDER_WEIGHT',
        6 => 'COM_J2COMMERCE_SHIPPING_TYPE_PER_ITEM_PERCENTAGE',
    ];

    public function __construct(
        DispatcherInterface $dispatcher,
        array $config,
        DatabaseInterface $db
    ) {
        parent::__construct($dispatcher, $config);
        $this->setDatabase($db);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onJ2CommerceGetShippingRates'   => 'onGetShippingRates',
            'onJ2CommerceShippingPluginView' => 'onShippingPluginView',
            'onJ2CommerceShippingPluginTask' => 'onShippingPluginTask',
            'onJ2CommerceShippingPluginAjax' => 'onShippingPluginAjax',
        ];
    }

    // =========================================================================
    // Shipping Rate Calculation (onJ2CommerceGetShippingRates)
    // =========================================================================

    /**
     * Provide shipping rates for all matching methods.
     *
     * Loads enabled shipping methods from the DB, filters by subtotal range,
     * resolves geozones for the shipping address, and calculates rates based
     * on each method's calculation type.
     */
    public function onGetShippingRates(Event $event): void
    {
        $args  = $event->getArguments();
        $order = $args[0] ?? null;

        if ($order === null) {
            return;
        }

        // Get order subtotal for method filtering
        $orderSubtotal = (float) ($order->order_subtotal ?? 0);

        // Load enabled methods matching subtotal range
        $methods = $this->loadEnabledMethods($orderSubtotal);

        if (empty($methods)) {
            return;
        }

        // Resolve geozones for the shipping address
        $geozones = $this->getShippingGeozones();

        if (empty($geozones)) {
            return;
        }

        $result = $event->getArgument('result', []);

        foreach ($methods as $method) {
            $methodId   = (int) $method->j2commerce_shippingmethod_id;
            $methodType = (int) $method->shipping_method_type;
            $params     = new Registry($method->params ?? '');

            // Calculate total for this method
            $rateData = $this->getTotal($methodId, $methodType, $geozones, $order, $method);

            if ($rateData === null) {
                continue;
            }

            // Calculate tax if tax class is assigned
            $taxAmount = 0.0;

            if ((int) $method->tax_class_id > 0) {
                $taxAmount = $this->calculateShippingTax(
                    $rateData->price,
                    (int) $method->tax_class_id,
                    $geozones
                );
            }

            $total = $rateData->price + $rateData->handling + $taxAmount;

            $selectText  = $params->get('shipping_select_text', '');
            $displayName = !empty($selectText) ? $selectText : $method->shipping_method_name;

            $result[] = [
                'element' => $this->_name,
                'name'    => $displayName,
                'code'    => (string) $methodId,
                'price'   => $rateData->price + $rateData->handling,
                'tax'     => $taxAmount,
                'extra'   => 0,
                'total'   => $total,
            ];
        }

        $event->setArgument('result', $result);
    }

    /**
     * Load enabled shipping methods that match the given subtotal range.
     *
     * @param   float  $subtotal  The order subtotal.
     *
     * @return  array  Array of method objects.
     */
    private function loadEnabledMethods(float $subtotal): array
    {
        $db        = $this->getDatabase();
        $query     = $db->getQuery(true);
        $published = 1;

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('published') . ' = :published')
            ->bind(':published', $published, ParameterType::INTEGER);

        // Subtotal minimum filter
        $query->where(
            '(' . $db->quoteName('subtotal_minimum') . ' <= :subtotalMin'
            . ' OR ' . $db->quoteName('subtotal_minimum') . ' = 0)'
        );
        $query->bind(':subtotalMin', $subtotal);

        // Subtotal maximum filter (0 = no limit)
        $query->where(
            '(' . $db->quoteName('subtotal_maximum') . ' >= :subtotalMax'
            . ' OR ' . $db->quoteName('subtotal_maximum') . ' = 0)'
        );
        $query->bind(':subtotalMax', $subtotal);

        $query->order($db->quoteName('shipping_method_name') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get geozones matching the current shipping address.
     *
     * Reads the shipping address from session, loads it from the addresses
     * table, and queries geozonerules for matching geozone IDs.
     *
     * @return  int[]  Array of matching geozone IDs.
     */
    private function getShippingGeozones(): array
    {
        $session   = Factory::getApplication()->getSession();
        $addressId = (int) $session->get('shipping_address_id', 0, 'j2commerce');

        // Also check for guest shipping data in session
        $guestShipping = $session->get('guest_shipping', [], 'j2commerce');
        $countryId     = 0;
        $zoneId        = 0;

        if ($addressId > 0) {
            // Load address from DB
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);

            $query->select([$db->quoteName('country_id'), $db->quoteName('zone_id')])
                ->from($db->quoteName('#__j2commerce_addresses'))
                ->where($db->quoteName('j2commerce_address_id') . ' = :addrId')
                ->bind(':addrId', $addressId, ParameterType::INTEGER);

            $db->setQuery($query);
            $address = $db->loadObject();

            if ($address) {
                $countryId = (int) ($address->country_id ?? 0);
                $zoneId    = (int) ($address->zone_id ?? 0);
            }
        } elseif (!empty($guestShipping) && \is_array($guestShipping)) {
            // Guest checkout — address data stored in session
            $countryId = (int) ($guestShipping['country_id'] ?? 0);
            $zoneId    = (int) ($guestShipping['zone_id'] ?? 0);
        }

        // Estimate flow — address stored as flat session keys by estimateAjax()
        if ($countryId === 0) {
            $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
            $zoneId    = (int) $session->get('shipping_zone_id', 0, 'j2commerce');
        }

        if ($countryId === 0) {
            return $this->getAllGeozoneIds();
        }

        return $this->findGeozonesForAddress($countryId, $zoneId);
    }

    /**
     * Find all geozone IDs matching a country/zone pair.
     *
     * @param   int  $countryId  Country ID.
     * @param   int  $zoneId     Zone/State ID.
     *
     * @return  int[]  Matching geozone IDs.
     */
    private function findGeozonesForAddress(int $countryId, int $zoneId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('DISTINCT ' . $db->quoteName('geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozonerules'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where(
                '(' . $db->quoteName('zone_id') . ' = 0 OR '
                . $db->quoteName('zone_id') . ' = :zoneId)'
            )
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];

        return array_map('intval', $rows);
    }

    /**
     * Get all geozone IDs (fallback when no address is available).
     *
     * @return  int[]
     */
    private function getAllGeozoneIds(): array
    {
        $db      = $this->getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName('j2commerce_geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozones'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER);

        $db->setQuery($query);
        $rows = $db->loadColumn() ?: [];

        return array_map('intval', $rows);
    }

    /**
     * Calculate shipping total for a method based on its type.
     *
     * @param   int     $methodId    Shipping method ID.
     * @param   int     $methodType  Calculation type (0-6).
     * @param   int[]   $geozones    Array of geozone IDs to search.
     * @param   object  $order       CartOrder object.
     * @param   object  $method      Shipping method row from DB.
     *
     * @return  object|null  Object with ->price and ->handling, or null if no rate found.
     */
    private function getTotal(
        int $methodId,
        int $methodType,
        array $geozones,
        object $order,
        object $method
    ): ?object {
        $items = method_exists($order, 'getItems') ? $order->getItems() : [];

        if (empty($items)) {
            return null;
        }

        switch ($methodType) {
            case 0:
                return $this->calculatePerOrderFlat($methodId, $geozones);

            case 1:
                return $this->calculatePerOrderQuantity($methodId, $geozones, $items);

            case 2:
                return $this->calculatePerOrderPrice($methodId, $geozones, $items, $order, $method);

            case 3:
                return $this->calculatePerItemFlat($methodId, $geozones, $items);

            case 4:
                return $this->calculatePerItemWeight($methodId, $geozones, $items);

            case 5:
                return $this->calculatePerOrderWeight($methodId, $geozones, $items);

            case 6:
                return $this->calculatePerItemPercentage($methodId, $geozones, $items);

            default:
                return null;
        }
    }

    // =========================================================================
    // TYPE 0: Per Order Flat Rate
    // =========================================================================

    /**
     * Per order flat rate -- single rate lookup per geozone.
     * Only items that require shipping are checked.
     */
    private function calculatePerOrderFlat(int $methodId, array $geozones): ?object
    {
        foreach ($geozones as $geozoneId) {
            $rate = $this->getRate($methodId, $geozoneId);

            if ($rate !== null) {
                return (object) [
                    'price'    => (float) $rate->shipping_rate_price,
                    'handling' => (float) $rate->shipping_rate_handling,
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // TYPE 1: Per Order Quantity Based
    // =========================================================================

    /**
     * Per order quantity based -- uses total shipped quantity as the weight filter.
     */
    private function calculatePerOrderQuantity(int $methodId, array $geozones, array $items): ?object
    {
        $totalQty = 0;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $totalQty += (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);
        }

        if ($totalQty === 0) {
            return null;
        }

        foreach ($geozones as $geozoneId) {
            $rate = $this->getRate($methodId, $geozoneId, (float) $totalQty);

            if ($rate !== null) {
                return (object) [
                    'price'    => (float) $rate->shipping_rate_price,
                    'handling' => (float) $rate->shipping_rate_handling,
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // TYPE 2: Per Order Price Based
    // =========================================================================

    /**
     * Per order price based -- uses order subtotal (before or after tax) as the range filter.
     *
     * Fixed from J2Store: uses $item->product_subtotal instead of dynamic property access.
     */
    private function calculatePerOrderPrice(
        int $methodId,
        array $geozones,
        array $items,
        object $order,
        object $method
    ): ?object {
        $params       = new Registry($method->params ?? '');
        $priceBasedOn = (int) $params->get('shipping_price_based_on', 0);

        // 0 = before tax, 1 = after tax, 2 = after discount
        $orderTotal = 0.0;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $qty          = (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);
            $itemSubtotal = (float) ($item->product_subtotal ?? 0);

            if ($priceBasedOn === 1) {
                // After tax: add item tax
                $itemTax = (float) ($item->pricing->tax ?? $item->orderitem_tax ?? 0);
                $orderTotal += $itemSubtotal + ($itemTax * $qty);
            } else {
                $orderTotal += $itemSubtotal;
            }
        }

        if ($priceBasedOn === 2) {
            // After discount: subtract order-level discounts
            $orderDiscount = (float) ($order->order_discount ?? 0);
            $orderTotal -= $orderDiscount;

            if ($orderTotal < 0) {
                $orderTotal = 0.0;
            }
        }

        if ($orderTotal <= 0) {
            return null;
        }

        foreach ($geozones as $geozoneId) {
            $rate = $this->getRate($methodId, $geozoneId, $orderTotal);

            if ($rate !== null) {
                return (object) [
                    'price'    => (float) $rate->shipping_rate_price,
                    'handling' => (float) $rate->shipping_rate_handling,
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // TYPE 3: Per Item Flat Rate
    // =========================================================================

    /**
     * Per item flat rate -- each shippable item gets its own rate lookup.
     * Total is summed across all items (rate * quantity).
     */
    private function calculatePerItemFlat(int $methodId, array $geozones, array $items): ?object
    {
        $totalPrice    = 0.0;
        $totalHandling = 0.0;
        $hasRate       = false;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $qty = (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);

            foreach ($geozones as $geozoneId) {
                $rate = $this->getRate($methodId, $geozoneId);

                if ($rate !== null) {
                    $totalPrice += (float) $rate->shipping_rate_price * $qty;
                    $totalHandling += (float) $rate->shipping_rate_handling * $qty;
                    $hasRate = true;
                    break; // Found rate for this geozone, move to next item
                }
            }
        }

        if (!$hasRate) {
            return null;
        }

        return (object) [
            'price'    => $totalPrice,
            'handling' => $totalHandling,
        ];
    }

    // =========================================================================
    // TYPE 4: Per Item Weight Based
    // =========================================================================

    /**
     * Per item weight based -- each item uses its weight for range matching.
     */
    private function calculatePerItemWeight(int $methodId, array $geozones, array $items): ?object
    {
        $totalPrice    = 0.0;
        $totalHandling = 0.0;
        $hasRate       = false;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $qty    = (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);
            $weight = (float) ($item->weight ?? $item->orderitem_weight ?? 0);

            foreach ($geozones as $geozoneId) {
                $rate = $this->getRate($methodId, $geozoneId, $weight);

                if ($rate !== null) {
                    $totalPrice += (float) $rate->shipping_rate_price * $qty;
                    $totalHandling += (float) $rate->shipping_rate_handling * $qty;
                    $hasRate = true;
                    break; // Found rate for this geozone
                }
            }
        }

        if (!$hasRate) {
            return null;
        }

        return (object) [
            'price'    => $totalPrice,
            'handling' => $totalHandling,
        ];
    }

    // =========================================================================
    // TYPE 5: Per Order Weight Based
    // =========================================================================

    /**
     * Per order weight based -- total weight of all shippable items is used for range matching.
     */
    private function calculatePerOrderWeight(int $methodId, array $geozones, array $items): ?object
    {
        $totalWeight = 0.0;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $weightTotal = (float) ($item->weight_total ?? 0);

            if ($weightTotal > 0) {
                $totalWeight += $weightTotal;
            } else {
                // Fallback: calculate from unit weight * quantity
                $weight = (float) ($item->weight ?? $item->orderitem_weight ?? 0);
                $qty    = (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);
                $totalWeight += $weight * $qty;
            }
        }

        if ($totalWeight <= 0) {
            return null;
        }

        foreach ($geozones as $geozoneId) {
            $rate = $this->getRate($methodId, $geozoneId, $totalWeight);

            if ($rate !== null) {
                return (object) [
                    'price'    => (float) $rate->shipping_rate_price,
                    'handling' => (float) $rate->shipping_rate_handling,
                ];
            }
        }

        return null;
    }

    // =========================================================================
    // TYPE 6: Per Item Price Based (Percentage)
    // =========================================================================

    /**
     * Per item price based (percentage) -- rate price is treated as a percentage
     * of the item's subtotal.
     *
     * FIXED from J2Store: Range filter is now correctly applied when ranges
     * are configured. In J2Store, getRate() was called with use_weight=6,
     * but it only filtered ranges when use_weight==1, so ranges were ignored.
     */
    private function calculatePerItemPercentage(int $methodId, array $geozones, array $items): ?object
    {
        $totalPrice    = 0.0;
        $totalHandling = 0.0;
        $hasRate       = false;

        foreach ($items as $item) {
            if (!$this->isShippable($item)) {
                continue;
            }

            $qty          = (int) ($item->product_qty ?? $item->orderitem_quantity ?? 1);
            $itemSubtotal = (float) ($item->product_subtotal ?? 0);

            // Use item subtotal as the range filter value for price-based percentage
            $rangeValue = $itemSubtotal;

            foreach ($geozones as $geozoneId) {
                $rate = $this->getRate($methodId, $geozoneId, $rangeValue);

                if ($rate !== null) {
                    // Rate price is a percentage (e.g., 5.0 = 5%)
                    $percentPrice = ($itemSubtotal * (float) $rate->shipping_rate_price) / 100.0;
                    $totalPrice += $percentPrice;
                    $totalHandling += (float) $rate->shipping_rate_handling * $qty;
                    $hasRate = true;
                    break; // Found rate for this geozone
                }
            }
        }

        if (!$hasRate) {
            return null;
        }

        return (object) [
            'price'    => $totalPrice,
            'handling' => $totalHandling,
        ];
    }

    // =========================================================================
    // Rate Lookup
    // =========================================================================

    /**
     * Look up a shipping rate for a method + geozone combination.
     *
     * When $weight > 0, the rate is filtered by the weight/range columns.
     * When $weight == 0, no range filtering is applied (flat rate lookup).
     *
     * All queries use parameterized bindings (fixing J2Store SQL injection).
     *
     * @param   int    $methodId   Shipping method ID.
     * @param   int    $geozoneId  Geozone ID.
     * @param   float  $weight     Weight/quantity/price value for range matching.
     *
     * @return  object|null  Rate row or null if no match.
     */
    private function getRate(int $methodId, int $geozoneId, float $weight = 0.0): ?object
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_shippingrates'))
            ->where($db->quoteName('shipping_method_id') . ' = :methodId')
            ->where($db->quoteName('geozone_id') . ' = :geozoneId')
            ->bind(':methodId', $methodId, ParameterType::INTEGER)
            ->bind(':geozoneId', $geozoneId, ParameterType::INTEGER);

        // Apply range filter when a weight/value is provided
        if ($weight > 0.0) {
            $query->where($db->quoteName('shipping_rate_weight_start') . ' <= :weightStart')
                ->where(
                    '(' . $db->quoteName('shipping_rate_weight_end') . ' >= :weightEnd'
                    . ' OR ' . $db->quoteName('shipping_rate_weight_end') . ' = 0)'
                )
                ->bind(':weightStart', $weight)
                ->bind(':weightEnd', $weight);
        }

        $query->order($db->quoteName('shipping_rate_price') . ' ASC')
            ->setLimit(1);

        $db->setQuery($query);
        $rate = $db->loadObject();

        return $rate ?: null;
    }

    // =========================================================================
    // Tax Calculation
    // =========================================================================

    /**
     * Calculate shipping tax based on the shipping method's tax class.
     *
     * Looks up tax rules for the given taxprofile_id and finds matching
     * tax rates for the shipping geozones.
     *
     * @param   float  $shippingAmount  The shipping amount (price + handling).
     * @param   int    $taxClassId      Tax profile/class ID.
     * @param   int[]  $geozones        Geozone IDs for the shipping address.
     *
     * @return  float  Tax amount.
     */
    private function calculateShippingTax(float $shippingAmount, int $taxClassId, array $geozones): float
    {
        if ($shippingAmount <= 0 || $taxClassId <= 0 || empty($geozones)) {
            return 0.0;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Find tax rates for this tax class that match shipping geozones
        $query->select($db->quoteName('tr.tax_percent'))
            ->from($db->quoteName('#__j2commerce_taxrules', 'tru'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_taxrates', 'tr')
                . ' ON ' . $db->quoteName('tr.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tru.taxrate_id')
            )
            ->where($db->quoteName('tru.taxprofile_id') . ' = :taxClassId')
            ->where($db->quoteName('tr.enabled') . ' = 1')
            ->bind(':taxClassId', $taxClassId, ParameterType::INTEGER);

        // Filter by geozones
        if (\count($geozones) === 1) {
            $gzId = $geozones[0];
            $query->where($db->quoteName('tr.geozone_id') . ' = :geozoneId')
                ->bind(':geozoneId', $gzId, ParameterType::INTEGER);
        } else {
            $placeholders = [];

            foreach ($geozones as $idx => $gzId) {
                $paramName      = ':gzId' . $idx;
                $placeholders[] = $paramName;
                $query->bind($paramName, $geozones[$idx], ParameterType::INTEGER);
            }

            $query->where($db->quoteName('tr.geozone_id') . ' IN (' . implode(', ', $placeholders) . ')');
        }

        $query->order($db->quoteName('tru.ordering') . ' ASC')
            ->setLimit(1);

        $db->setQuery($query);
        $taxPercent = $db->loadResult();

        if ($taxPercent === null || (float) $taxPercent <= 0) {
            return 0.0;
        }

        return ($shippingAmount * (float) $taxPercent) / 100.0;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Check if a cart item requires shipping.
     *
     * @param   object  $item  Cart/order item.
     *
     * @return  bool
     */
    private function isShippable(object $item): bool
    {
        // Direct shipping flag
        if (isset($item->shipping)) {
            return (bool) $item->shipping;
        }

        // Check nested cartitem
        if (isset($item->cartitem, $item->cartitem->shipping)) {
            return (bool) $item->cartitem->shipping;
        }

        // Default: assume shippable
        return true;
    }

    // =========================================================================
    // Admin View Rendering (onJ2CommerceShippingPluginView)
    // =========================================================================

    /**
     * Render admin views for managing shipping methods (list) and
     * editing a single shipping method (edit with rates tab).
     */
    public function onShippingPluginView(Event $event): void
    {
        if ($event->getArgument('plugin') !== 'shipping_standard') {
            return;
        }

        $pluginview = $event->getArgument('pluginview', 'methods');
        $id         = (int) $event->getArgument('id', 0);
        $toolbar    = $event->getArgument('toolbar');

        if ($pluginview === 'methods') {
            $this->renderMethodsList($event, $toolbar);
        } elseif ($pluginview === 'setrates') {
            $this->renderSetRates($event, $toolbar, $id);
        } else {
            $this->renderMethodEdit($event, $toolbar, $id);
        }
    }

    /**
     * Render the methods list view with filters, pagination, and toolbar.
     */
    private function renderMethodsList(Event $event, object $toolbar): void
    {
        $event->setArgument('title', Text::_('PLG_J2COMMERCE_SHIPPING_STANDARD_MANAGE_METHODS'));

        // Toolbar: New, Publish, Unpublish, Delete
        $toolbar->linkButton('new-method')
            ->text('JTOOLBAR_NEW')
            ->url(Route::_('index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method'))
            ->icon('icon-plus');

        $toolbar->publish('shippingplugin.publish')->listCheck(true);
        $toolbar->unpublish('shippingplugin.unpublish')->listCheck(true);
        $toolbar->delete('shippingplugin.delete')
            ->text('JTOOLBAR_DELETE')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        // Build the query
        $db  = $this->getDatabase();
        $app = Factory::getApplication();

        $query = $db->getQuery(true)
            ->select('a.*, tp.taxprofile_name')
            ->from($db->quoteName('#__j2commerce_shippingmethods', 'a'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_taxprofiles', 'tp')
                . ' ON ' . $db->quoteName('tp.j2commerce_taxprofile_id')
                . ' = ' . $db->quoteName('a.tax_class_id')
            );

        // Apply filters from user state
        $search = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.filter.search',
            'filter_search',
            '',
            'string'
        );
        $published = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.filter.published',
            'filter_published',
            '',
            'string'
        );
        $methodType = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.filter.shipping_method_type',
            'filter_shipping_method_type',
            '',
            'string'
        );

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.j2commerce_shippingmethod_id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $search = '%' . trim($search) . '%';
                $query->where($db->quoteName('a.shipping_method_name') . ' LIKE :search')
                    ->bind(':search', $search);
            }
        }

        if (is_numeric($published)) {
            $pubVal = (int) $published;
            $query->where($db->quoteName('a.published') . ' = :published')
                ->bind(':published', $pubVal, ParameterType::INTEGER);
        }

        if ($methodType !== '') {
            $typeVal = (int) $methodType;
            $query->where($db->quoteName('a.shipping_method_type') . ' = :type')
                ->bind(':type', $typeVal, ParameterType::INTEGER);
        }

        // Ordering
        $fullOrdering = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.list.fullordering',
            'list_fullordering',
            'a.shipping_method_name ASC',
            'string'
        );

        $allowedCols = [
            'a.shipping_method_name',
            'a.shipping_method_type',
            'a.j2commerce_shippingmethod_id',
            'a.published',
        ];

        $orderParts = explode(' ', trim($fullOrdering));
        $orderCol   = $orderParts[0] ?? 'a.shipping_method_name';
        $orderDir   = strtoupper($orderParts[1] ?? 'ASC');

        if (!\in_array($orderCol, $allowedCols, true)) {
            $orderCol = 'a.shipping_method_name';
        }

        if (!\in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        $ordering = $db->quoteName($orderCol) . ' ' . $orderDir;

        // Pagination
        $limitstart = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.list.limitstart',
            'limitstart',
            0,
            'int'
        );
        $limit = $app->getUserStateFromRequest(
            'plg_shipping_standard.methods.list.limit',
            'list_limit',
            25,
            'int'
        );

        // Count total
        $countQuery = clone $query;
        $countQuery->clear('select')->select('COUNT(*)');
        $db->setQuery($countQuery);
        $total = (int) $db->loadResult();

        // Fetch items
        $query->order($ordering);
        $db->setQuery($query, $limitstart, $limit);
        $items = $db->loadObjectList() ?: [];

        $pagination = new Pagination($total, $limitstart, $limit);

        // Load filter form from plugin forms directory
        $filterForm     = null;
        $filterFormPath = JPATH_PLUGINS . '/j2commerce/shipping_standard/forms/filter_shippingmethods.xml';

        if (file_exists($filterFormPath)) {
            $filterForm = Form::getInstance(
                'plg_j2commerce_shipping_standard.filter_methods',
                $filterFormPath,
                ['control' => '', 'load_data' => false]
            );

            // Set filter values on the form
            $filterForm->setValue('search', 'filter', $app->getUserState('plg_shipping_standard.methods.filter.search', ''));
            $filterForm->setValue('published', 'filter', $app->getUserState('plg_shipping_standard.methods.filter.published', ''));
            $filterForm->setValue('shipping_method_type', 'filter', $app->getUserState('plg_shipping_standard.methods.filter.shipping_method_type', ''));
        }

        // Build active filters array for searchtools
        $activeFilters = [];

        if (!empty($search)) {
            $activeFilters['search'] = $search;
        }

        if ($published !== '') {
            $activeFilters['published'] = $published;
        }

        if ($methodType !== '') {
            $activeFilters['shipping_method_type'] = $methodType;
        }

        // Build state object for template
        $state = (object) [
            'filter' => (object) [
                'search'               => $search,
                'published'            => $published,
                'shipping_method_type' => $methodType,
            ],
            'list' => (object) [
                'fullordering' => $fullOrdering,
                'limit'        => $limit,
            ],
        ];

        // Render template
        $html = $this->renderTemplate('methods', [
            'items'         => $items,
            'pagination'    => $pagination,
            'filterForm'    => $filterForm,
            'activeFilters' => $activeFilters,
            'typeLabels'    => self::TYPE_LABELS,
            'listOrder'     => $orderCol,
            'listDirn'      => $orderDir,
            'state'         => $state,
        ]);

        $result   = $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Render the method edit view with form fields and rates tab.
     */
    private function renderMethodEdit(Event $event, object $toolbar, int $id): void
    {
        $isNew = ($id === 0);

        $event->setArgument(
            'title',
            $isNew
            ? Text::_('PLG_J2COMMERCE_SHIPPING_STANDARD_NEW_METHOD')
            : Text::_('COM_J2COMMERCE_TOOLBAR_EDIT')
        );

        // Toolbar: Apply, Save, Cancel
        $toolbar->apply('shippingplugin.apply');
        $toolbar->save('shippingplugin.save');
        $toolbar->cancel('shippingplugin.cancel', 'JTOOLBAR_CLOSE');

        $db = $this->getDatabase();

        // Load method record
        $table = new ShippingMethodTable($db);

        if ($id > 0) {
            $table->load($id);
        }

        $item = $table;

        // Load Joomla form
        $formPath = JPATH_PLUGINS . '/j2commerce/shipping_standard/forms/shippingmethod.xml';

        if (!file_exists($formPath)) {
            // Fallback to component form
            $formPath = JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms/shippingstandard.xml';
        }

        $form = Form::getInstance(
            'plg_j2commerce_shipping_standard.method',
            $formPath,
            ['control' => 'jform', 'load_data' => true]
        );

        // Bind the item data to the form
        $formData = [];

        if ($id > 0) {
            $formData = get_object_vars($table);

            // Decode params JSON and merge into form data for params fieldset
            if (!empty($formData['params'])) {
                $params = json_decode($formData['params'], true);

                if (\is_array($params)) {
                    $formData = array_merge($formData, $params);
                }
            }
        }

        $form->bind($formData);

        // Load geozones for the rates tab
        $gQuery = $db->getQuery(true)
            ->select([$db->quoteName('j2commerce_geozone_id'), $db->quoteName('geozone_name')])
            ->from($db->quoteName('#__j2commerce_geozones'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('geozone_name'));
        $db->setQuery($gQuery);
        $geozones = $db->loadAssocList('j2commerce_geozone_id', 'geozone_name') ?: [];

        // Render template
        $html = $this->renderTemplate('method', [
            'item'     => $item,
            'form'     => $form,
            'geozones' => $geozones,
            'isNew'    => $isNew,
        ]);

        $result   = $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Render the Set Rates view for a specific shipping method.
     */
    private function renderSetRates(Event $event, object $toolbar, int $id): void
    {
        $db    = $this->getDatabase();
        $table = new ShippingMethodTable($db);

        if ($id <= 0 || !$table->load($id)) {
            $event->setArgument('title', Text::_('COM_J2COMMERCE_SHIPPING_RATES'));

            $result   = $event->getArgument('result', []);
            $result[] = '<div class="alert alert-warning">'
                . Text::_('COM_J2COMMERCE_ERR_INVALID_METHOD_ID')
                . '</div>';
            $event->setArgument('result', $result);

            return;
        }

        $event->setArgument(
            'title',
            Text::sprintf('COM_J2COMMERCE_SHIPPING_SET_RATE_FOR', $table->shipping_method_name)
        );

        // Toolbar: link to edit this method
        $toolbar->linkButton('edit-method')
            ->text('COM_J2COMMERCE_TOOLBAR_EDIT')
            ->url(Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $id
            ))
            ->icon('icon-pencil');

        // Load geozones for dropdowns
        $gQuery = $db->getQuery(true)
            ->select([$db->quoteName('j2commerce_geozone_id'), $db->quoteName('geozone_name')])
            ->from($db->quoteName('#__j2commerce_geozones'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->order($db->quoteName('geozone_name'));
        $db->setQuery($gQuery);
        $geozones = $db->loadAssocList('j2commerce_geozone_id', 'geozone_name') ?: [];

        $methodType = (int) $table->shipping_method_type;

        // Register language strings for JavaScript
        Text::script('COM_J2COMMERCE_CONFIRM_DELETE');
        Text::script('JGLOBAL_NO_MATCHING_RESULTS');
        Text::script('COM_J2COMMERCE_SHIPPING_RATE_SELECT_TO_DELETE');
        Text::script('COM_J2COMMERCE_SHIPPING_RATE_SAVED');
        Text::script('COM_J2COMMERCE_SHIPPING_RATES_SAVED_N');
        Text::script('COM_J2COMMERCE_SHIPPING_RATES_DELETED_N');
        Text::script('COM_J2COMMERCE_SHIPPING_RATES_PAGINATION');
        Text::script('COM_J2COMMERCE_SHIPPING_ERROR_LOADING_RATES');
        Text::script('COM_J2COMMERCE_SHIPPING_ERROR_CREATING_RATE');
        Text::script('COM_J2COMMERCE_SHIPPING_ERROR_SAVING_RATES');
        Text::script('COM_J2COMMERCE_SHIPPING_ERROR_DELETING_RATES');

        $html = $this->renderTemplate('setrates', [
            'item'       => $table,
            'geozones'   => $geozones,
            'methodId'   => $id,
            'methodType' => $methodType,
            'typeLabels' => self::TYPE_LABELS,
        ]);

        $result   = $event->getArgument('result', []);
        $result[] = $html;
        $event->setArgument('result', $result);
    }

    /**
     * Render a template from the plugin's tmpl/ directory.
     *
     * @param   string  $templateName  Template filename (without .php extension).
     * @param   array   $data          Variables to extract into template scope.
     *
     * @return  string  Rendered HTML.
     */
    private function renderTemplate(string $templateName, array $data = []): string
    {
        // Guard against path traversal — only allow alphanumeric + underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $templateName)) {
            return '';
        }

        $templatePath = JPATH_PLUGINS . '/j2commerce/shipping_standard/tmpl/' . $templateName . '.php';

        if (!file_exists($templatePath)) {
            return '';
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;

        return ob_get_clean();
    }

    // =========================================================================
    // Admin Task Handling (onJ2CommerceShippingPluginTask)
    // =========================================================================

    /**
     * Handle save, delete, publish, and unpublish tasks for shipping methods.
     */
    public function onShippingPluginTask(Event $event): void
    {
        if ($event->getArgument('plugin') !== 'shipping_standard') {
            return;
        }

        $task = $event->getArgument('task', '');

        match ($task) {
            'save', 'apply' => $this->handleSave($event, $task),
            'delete'    => $this->handleDelete($event),
            'publish'   => $this->handlePublish($event, 1),
            'unpublish' => $this->handlePublish($event, 0),
            'cancel'    => $this->handleCancel($event),
            default     => null,
        };
    }

    /**
     * Save or apply a shipping method.
     */
    private function handleSave(Event $event, string $task): void
    {
        $data = $event->getArgument('data', []);
        $db   = $this->getDatabase();

        // Extract params fields from jform data and encode as JSON
        $paramsFields = ['shipping_select_text', 'shipping_price_based_on'];
        $params       = [];

        foreach ($paramsFields as $field) {
            if (isset($data[$field])) {
                $params[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        if (!empty($params)) {
            $data['params'] = json_encode($params);
        } elseif (!isset($data['params'])) {
            $data['params'] = '{}';
        }

        $table = new ShippingMethodTable($db);

        // Load existing record if updating
        $id = (int) ($data['j2commerce_shippingmethod_id'] ?? 0);

        if ($id > 0) {
            $table->load($id);
        }

        if (!$table->bind($data)) {
            $event->setArgument('message', $table->getError());
            $event->setArgument('messageType', 'error');
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $id,
                false
            ));

            return;
        }

        if (!$table->check()) {
            $event->setArgument('message', $table->getError());
            $event->setArgument('messageType', 'error');
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $id,
                false
            ));

            return;
        }

        if (!$table->store()) {
            $event->setArgument('message', $table->getError());
            $event->setArgument('messageType', 'error');
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $id,
                false
            ));

            return;
        }

        $savedId = (int) $table->j2commerce_shippingmethod_id;
        $event->setArgument('id', $savedId);
        $event->setArgument('message', Text::_('COM_J2COMMERCE_MSG_SAVE_SUCCESS'));
        $event->setArgument('messageType', 'success');

        if ($task === 'apply') {
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=method&id=' . $savedId,
                false
            ));
        } else {
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
                false
            ));
        }
    }

    /**
     * Delete selected shipping methods and their associated rates.
     */
    private function handleDelete(Event $event): void
    {
        $ids = $event->getArgument('ids', []);
        $db  = $this->getDatabase();

        if (empty($ids)) {
            $event->setArgument('message', Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'));
            $event->setArgument('messageType', 'warning');
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
                false
            ));

            return;
        }

        $deleted = 0;

        foreach ($ids as $id) {
            $id = (int) $id;

            // Delete associated rates first
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__j2commerce_shippingrates'))
                ->where($db->quoteName('shipping_method_id') . ' = :methodId')
                ->bind(':methodId', $id, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();

            // Delete the method
            $table = new ShippingMethodTable($db);

            if ($table->delete($id)) {
                $deleted++;
            }
        }

        $event->setArgument('message', Text::sprintf('COM_J2COMMERCE_N_ITEMS_DELETED', $deleted));
        $event->setArgument('messageType', 'success');
        $event->setArgument('redirect', Route::_(
            'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
            false
        ));
    }

    /**
     * Publish or unpublish selected shipping methods.
     */
    private function handlePublish(Event $event, int $value): void
    {
        $ids = $event->getArgument('ids', []);
        $db  = $this->getDatabase();

        if (empty($ids)) {
            $event->setArgument('message', Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'));
            $event->setArgument('messageType', 'warning');
            $event->setArgument('redirect', Route::_(
                'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
                false
            ));

            return;
        }

        $updated = 0;

        foreach ($ids as $id) {
            $id = (int) $id;

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_shippingmethods'))
                ->set($db->quoteName('published') . ' = :published')
                ->where($db->quoteName('j2commerce_shippingmethod_id') . ' = :id')
                ->bind(':published', $value, ParameterType::INTEGER)
                ->bind(':id', $id, ParameterType::INTEGER);

            $db->setQuery($query);
            $db->execute();
            $updated += $db->getAffectedRows();
        }

        $msgKey = $value === 1 ? 'COM_J2COMMERCE_N_ITEMS_PUBLISHED' : 'COM_J2COMMERCE_N_ITEMS_UNPUBLISHED';
        $event->setArgument('message', Text::sprintf($msgKey, $updated));
        $event->setArgument('messageType', 'success');
        $event->setArgument('redirect', Route::_(
            'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
            false
        ));
    }

    /**
     * Handle cancel task -- redirect back to methods list.
     */
    private function handleCancel(Event $event): void
    {
        $event->setArgument('redirect', Route::_(
            'index.php?option=com_j2commerce&view=shippingplugin&plugin=shipping_standard&pluginview=methods',
            false
        ));
    }

    // =========================================================================
    // Admin AJAX Rate Management (onJ2CommerceShippingPluginAjax)
    // =========================================================================

    /**
     * Handle AJAX requests for rate CRUD operations (loadRates, saveRate, deleteRate).
     */
    public function onShippingPluginAjax(Event $event): void
    {
        if ($event->getArgument('plugin') !== 'shipping_standard') {
            return;
        }

        $action = $event->getArgument('action', '');

        match ($action) {
            'loadRates'   => $this->ajaxLoadRates($event),
            'saveRate'    => $this->ajaxSaveRate($event),
            'saveRates'   => $this->ajaxSaveRates($event),
            'deleteRate'  => $this->ajaxDeleteRate($event),
            'deleteRates' => $this->ajaxDeleteRates($event),
            default       => $event->setArgument('jsonError', 'Unknown action: ' . $action),
        };
    }

    /**
     * Load all rates for a shipping method, joined with geozone names.
     */
    private function ajaxLoadRates(Event $event): void
    {
        $input    = $event->getArgument('input');
        $methodId = $input->getInt('method_id', 0);

        if ($methodId <= 0) {
            $event->setArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_INVALID_METHOD_ID'));

            return;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select('r.*, g.geozone_name')
            ->from($db->quoteName('#__j2commerce_shippingrates', 'r'))
            ->leftJoin(
                $db->quoteName('#__j2commerce_geozones', 'g')
                . ' ON ' . $db->quoteName('g.j2commerce_geozone_id') . ' = ' . $db->quoteName('r.geozone_id')
            )
            ->where($db->quoteName('r.shipping_method_id') . ' = :methodId')
            ->bind(':methodId', $methodId, ParameterType::INTEGER)
            ->order($db->quoteName('g.geozone_name') . ' ASC, '
                . $db->quoteName('r.shipping_rate_weight_start') . ' ASC');

        $db->setQuery($query);
        $rates = $db->loadObjectList() ?: [];

        $event->setArgument('jsonResult', $rates);
    }

    /**
     * Save a single shipping rate (create or update).
     */
    private function ajaxSaveRate(Event $event): void
    {
        $input = $event->getArgument('input');

        $data = [
            'j2commerce_shippingrate_id' => $input->getInt('rate_id', 0),
            'shipping_method_id'         => $input->getInt('method_id', 0),
            'geozone_id'                 => $input->getInt('geozone_id', 0),
            'shipping_rate_price'        => $input->getFloat('rate_price', 0.0),
            'shipping_rate_weight_start' => $input->getFloat('weight_start', 0.0),
            'shipping_rate_weight_end'   => $input->getFloat('weight_end', 0.0),
            'shipping_rate_handling'     => $input->getFloat('rate_handling', 0.0),
        ];

        if ($data['shipping_method_id'] <= 0) {
            $event->setArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_INVALID_METHOD_ID'));

            return;
        }

        try {
            $db    = $this->getDatabase();
            $table = new ShippingRateTable($db);

            $table->bind($data);

            if (!$table->check()) {
                $event->setArgument('jsonError', $table->getError());

                return;
            }

            if (!$table->store()) {
                $event->setArgument('jsonError', $table->getError());

                return;
            }

            $event->setArgument('jsonResult', [
                'rate_id' => $table->j2commerce_shippingrate_id,
                'message' => Text::_('COM_J2COMMERCE_SHIPPING_RATE_SAVED'),
            ]);
        } catch (\Exception $e) {
            $event->setArgument('jsonError', $e->getMessage());
        }
    }

    /**
     * Delete a single shipping rate by ID.
     */
    private function ajaxDeleteRate(Event $event): void
    {
        $input  = $event->getArgument('input');
        $rateId = $input->getInt('rate_id', 0);

        if ($rateId <= 0) {
            $event->setArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_INVALID_RATE_ID'));

            return;
        }

        try {
            $db    = $this->getDatabase();
            $table = new ShippingRateTable($db);

            if (!$table->delete($rateId)) {
                $event->setArgument('jsonError', $table->getError());

                return;
            }

            $event->setArgument('jsonResult', [
                'message' => Text::_('COM_J2COMMERCE_SHIPPING_RATE_DELETED'),
            ]);
        } catch (\Exception $e) {
            $event->setArgument('jsonError', $e->getMessage());
        }
    }

    /**
     * Bulk save multiple shipping rates.
     */
    private function ajaxSaveRates(Event $event): void
    {
        $input     = $event->getArgument('input');
        $ratesJson = $input->getString('rates', '[]');
        $rates     = json_decode($ratesJson, true);

        if (!\is_array($rates) || empty($rates)) {
            $event->setArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_INVALID_REQUEST'));

            return;
        }

        $db    = $this->getDatabase();
        $saved = 0;

        try {
            foreach ($rates as $rateData) {
                $table = new ShippingRateTable($db);

                $data = [
                    'j2commerce_shippingrate_id' => (int) ($rateData['rate_id'] ?? 0),
                    'shipping_method_id'         => (int) ($rateData['method_id'] ?? 0),
                    'geozone_id'                 => (int) ($rateData['geozone_id'] ?? 0),
                    'shipping_rate_price'        => (float) ($rateData['rate_price'] ?? 0),
                    'shipping_rate_weight_start' => (float) ($rateData['weight_start'] ?? 0),
                    'shipping_rate_weight_end'   => (float) ($rateData['weight_end'] ?? 0),
                    'shipping_rate_handling'     => (float) ($rateData['rate_handling'] ?? 0),
                ];

                if ($data['shipping_method_id'] <= 0) {
                    continue;
                }

                $table->bind($data);

                if ($table->check() && $table->store()) {
                    $saved++;
                }
            }

            $event->setArgument('jsonResult', [
                'saved'   => $saved,
                'message' => Text::sprintf('COM_J2COMMERCE_SHIPPING_RATES_SAVED_N', $saved),
            ]);
        } catch (\Exception $e) {
            $event->setArgument('jsonError', $e->getMessage());
        }
    }

    /**
     * Bulk delete multiple shipping rates by ID.
     */
    private function ajaxDeleteRates(Event $event): void
    {
        $input   = $event->getArgument('input');
        $idsJson = $input->getString('rate_ids', '[]');
        $ids     = json_decode($idsJson, true);

        if (!\is_array($ids) || empty($ids)) {
            $event->setArgument('jsonError', Text::_('COM_J2COMMERCE_ERR_INVALID_REQUEST'));

            return;
        }

        $db      = $this->getDatabase();
        $deleted = 0;

        try {
            foreach ($ids as $rateId) {
                $rateId = (int) $rateId;

                if ($rateId <= 0) {
                    continue;
                }

                $table = new ShippingRateTable($db);

                if ($table->delete($rateId)) {
                    $deleted++;
                }
            }

            $event->setArgument('jsonResult', [
                'deleted' => $deleted,
                'message' => Text::sprintf('COM_J2COMMERCE_SHIPPING_RATES_DELETED_N', $deleted),
            ]);
        } catch (\Exception $e) {
            $event->setArgument('jsonError', $e->getMessage());
        }
    }

    // =========================================================================
    // Public Accessors (for templates)
    // =========================================================================

    /**
     * Get the TYPE_LABELS constant for use in templates.
     *
     * @return  array<int, string>
     */
    public function getTypeLabels(): array
    {
        return self::TYPE_LABELS;
    }
}
