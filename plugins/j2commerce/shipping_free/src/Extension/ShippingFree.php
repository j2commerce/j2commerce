<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.ShippingFree
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\J2Commerce\ShippingFree\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use J2Commerce\Component\J2commerce\Administrator\Library\Plugins\PluginLayoutTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Free Shipping Plugin for J2Commerce
 *
 * Provides a free shipping option with geozone restrictions,
 * subtotal limits, coupon requirements, and exclusion rules.
 *
 * @since  6.0.0
 */
final class ShippingFree extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use PluginLayoutTrait;

    protected $autoloadLanguage = true;

    /**
     * Plugin element name
     *
     * @var string
     */
    protected $_name = 'shipping_free';

    /**
     * Plugin type
     *
     * @var string
     */
    protected $_type = 'j2commerce';

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
            'onJ2CommerceGetShippingRates'    => 'onGetShippingRates',
            'onJ2CommerceFilterShippingRates' => 'onFilterShippingRates',
        ];
    }

    /**
     * Provide free shipping rate if all conditions are met.
     *
     * Checks: geozone, coupon requirement, min/max subtotal, shipping-only product subtotal.
     */
    public function onGetShippingRates(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $args  = $event->getArguments();
        $order = $args[0] ?? null;

        if ($order === null) {
            return;
        }

        // Check geozone availability
        if (!$this->checkGeozones($order)) {
            return;
        }

        // Check free shipping coupon requirement
        if ((int) $this->params->get('requires_coupon', 0) === 1) {
            if (!method_exists($order, 'has_free_shipping') || !$order->has_free_shipping()) {
                return;
            }
        }

        // Determine subtotal for threshold checks
        $subtotal = $this->getEffectiveSubtotal($order);

        // Check min/max subtotal limits
        if (!$this->checkSubtotalLimits($subtotal)) {
            return;
        }

        // All checks passed — append free shipping rate
        $result   = $event->getArgument('result', []);
        $result[] = [
            'element' => $this->_name,
            'name'    => Text::_($this->params->get('display_name', 'PLG_J2COMMERCE_SHIPPING_FREE')),
            'code'    => '',
            'price'   => 0,
            'tax'     => 0,
            'extra'   => 0,
            'total'   => 0,
        ];
        $event->setArgument('result', $result);
    }

    /**
     * Filter shipping rates — remove free shipping if excluded by user group or other shipping methods.
     */
    public function onFilterShippingRates(Event $event): void
    {
        if (!($event instanceof EventInterface)) {
            return;
        }

        $rates = $event->getArgument('rates', []);

        if (empty($rates)) {
            return;
        }

        // Check if free shipping is even in the rates
        $hasFreeShipping = false;

        foreach ($rates as $rate) {
            if (($rate['element'] ?? '') === $this->_name) {
                $hasFreeShipping = true;
                break;
            }
        }

        if (!$hasFreeShipping) {
            return;
        }

        $exclude = false;

        // Check user group exclusion
        $excludedGroups = $this->params->get('usergroup', []);

        if (!empty($excludedGroups)) {
            if (\is_string($excludedGroups)) {
                $excludedGroups = explode(',', $excludedGroups);
            }

            $user = Factory::getApplication()->getIdentity();

            if ($user) {
                $userGroups = $user->getAuthorisedGroups();

                foreach ($excludedGroups as $groupId) {
                    if (\in_array((int) $groupId, $userGroups, true)) {
                        $exclude = true;
                        break;
                    }
                }
            }
        }

        // Check shipping method exclusion
        if (!$exclude) {
            $excludedMethods = $this->params->get('shipping_method', []);

            if (!empty($excludedMethods)) {
                if (\is_string($excludedMethods)) {
                    $excludedMethods = explode(',', $excludedMethods);
                }

                foreach ($rates as $rate) {
                    $rateElement = $rate['element'] ?? '';
                    $rateName    = $rate['name'] ?? '';

                    // Skip free shipping itself
                    if ($rateElement === $this->_name) {
                        continue;
                    }

                    // Check by element name or by shipping method name (for sub-method plugins)
                    if (\in_array($rateElement, $excludedMethods, true) || \in_array($rateName, $excludedMethods, true)) {
                        $exclude = true;
                        break;
                    }
                }
            }
        }

        if ($exclude) {
            // Remove free shipping from rates
            $filteredRates = [];

            foreach ($rates as $rate) {
                if (($rate['element'] ?? '') !== $this->_name) {
                    $filteredRates[] = $rate;
                }
            }

            $event->setArgument('rates', $filteredRates);
        }
    }

    /**
     * Check if shipping address matches any configured geozone.
     */
    private function checkGeozones(object $order): bool
    {
        $geozones = $this->params->get('geozones', []);

        // No geozones configured = available everywhere
        if (empty($geozones)) {
            return true;
        }

        if (\is_string($geozones)) {
            $geozones = explode(',', $geozones);
        }

        // Check for "all geozones" wildcard before normalizing to integers
        if (\in_array('*', $geozones, true)) {
            return true;
        }

        // Normalize to integer IDs for strict comparison
        $geozones = array_map('intval', array_filter($geozones, 'strlen'));

        if (empty($geozones)) {
            return true;
        }

        // Get shipping address from order
        $address = [];

        if (method_exists($order, 'setAddress')) {
            $order->setAddress();
        }

        if (method_exists($order, 'getShippingAddress')) {
            $address = $order->getShippingAddress();
        } elseif (method_exists($order, 'getShippingGeoZones')) {
            // Legacy fallback: check order geozones directly
            $orderGeoZones = $order->getShippingGeoZones();

            foreach ($orderGeoZones as $orderGeoZone) {
                $geozoneId = (int) ($orderGeoZone->geozone_id ?? ($orderGeoZone->j2commerce_geozone_id ?? 0));

                if (\in_array($geozoneId, $geozones, true)) {
                    return true;
                }
            }

            return false;
        }

        if (empty($address)) {
            return true;
        }

        // Check each configured geozone against the shipping address
        foreach ($geozones as $geozoneId) {
            $geozoneId = (int) $geozoneId;

            if ($geozoneId > 0 && $this->checkGeozone($geozoneId, $address)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an address matches a specific geozone.
     */
    private function checkGeozone(int $geozoneId, array $address): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $countryId = (int) ($address['country_id'] ?? 0);
        $zoneId    = (int) ($address['zone_id'] ?? 0);

        $query->select($db->quoteName('gz.j2commerce_geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozones', 'gz'))
            ->innerJoin(
                $db->quoteName('#__j2commerce_geozonerules', 'gzr')
                . ' ON ' . $db->quoteName('gzr.geozone_id') . ' = ' . $db->quoteName('gz.j2commerce_geozone_id')
            )
            ->where($db->quoteName('gz.j2commerce_geozone_id') . ' = :geozoneId')
            ->where($db->quoteName('gzr.country_id') . ' = :countryId')
            ->where('(' . $db->quoteName('gzr.zone_id') . ' = 0 OR ' . $db->quoteName('gzr.zone_id') . ' = :zoneId)')
            ->bind(':geozoneId', $geozoneId, ParameterType::INTEGER)
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return !empty($db->loadResult());
    }

    /**
     * Check subtotal against min/max limits.
     */
    private function checkSubtotalLimits(float $subtotal): bool
    {
        $minSubtotal = (float) $this->params->get('min_subtotal', 0);
        $maxSubtotal = (float) $this->params->get('max_subtotal', -1);

        if ($minSubtotal > 0 && $subtotal < $minSubtotal) {
            return false;
        }

        if ($maxSubtotal >= 0 && $subtotal > $maxSubtotal) {
            return false;
        }

        return true;
    }

    /**
     * Get the effective subtotal for threshold checks.
     *
     * If check_shipping_product is enabled, only count products that require shipping.
     */
    private function getEffectiveSubtotal(object $order): float
    {
        $checkShippingProduct = (int) $this->params->get('check_shipping_product', 0);

        if (!$checkShippingProduct) {
            return (float) ($order->order_subtotal ?? 0);
        }

        // Calculate subtotal from shipping-enabled products only
        if (!method_exists($order, 'getItems')) {
            return (float) ($order->order_subtotal ?? 0);
        }

        $products           = $order->getItems();
        $subtotal           = 0.0;
        $hasShippingProduct = false;

        foreach ($products as $product) {
            $cartitem   = $product->cartitem ?? $product;
            $isShipping = $cartitem->shipping ?? false;

            if ($isShipping) {
                $price    = $cartitem->pricing->price ?? ($cartitem->price ?? 0);
                $quantity = $cartitem->product_qty ?? ($cartitem->quantity ?? 1);
                $subtotal += (float) $price * (int) $quantity;
                $hasShippingProduct = true;
            }
        }

        // If no shipping products found, free shipping is not applicable
        if (!$hasShippingProduct) {
            return -1.0;
        }

        return $subtotal;
    }
}
