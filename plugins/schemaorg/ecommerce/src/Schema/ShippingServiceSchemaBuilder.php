<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  plg_schemaorg_ecommerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Schema;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/** Builds Organization.hasShippingService from the standard shipping methods and their geozone rates. */
class ShippingServiceSchemaBuilder
{
    private const TYPE_FLAT         = 0;
    private const TYPE_ORDER_VALUE  = 2;
    private const TYPE_ORDER_WEIGHT = 5;

    /** Google reads DefinedRegion.addressRegion for these countries only. */
    private const REGION_COUNTRIES = ['US', 'AU', 'JP'];

    private const WEIGHT_UNITS = ['kg' => 'KGM', 'g' => 'GRM', 'lb' => 'LBR', 'oz' => 'ONZ'];

    private ?string $weightUnit = null;

    public function __construct(
        private DatabaseInterface $db,
        private Registry $params,
        private Registry $config,
        private string $timezone,
    ) {
    }

    /** @return array<int, array> one ShippingService per published method that has an expressible rate */
    public function build(): array
    {
        $destinations = $this->loadDestinations();
        $handlingTime = $this->buildHandlingTime();
        $transitTime  = $this->buildTransitTime();
        $services     = [];

        foreach ($this->loadMethods() as $method) {
            $conditions = [];

            foreach ($method->rates as $rate) {
                $condition = $this->buildCondition($method, $rate, $destinations[(int) $rate->geozone_id] ?? []);

                if ($condition === null) {
                    continue;
                }

                if ($transitTime !== null) {
                    $condition['transitTime'] = $transitTime;
                }

                $conditions[] = $condition;
            }

            if ($conditions === []) {
                continue;
            }

            $service = [
                '@type'              => 'ShippingService',
                'name'               => $method->shipping_method_name,
                'fulfillmentType'    => 'https://schema.org/FulfillmentTypeDelivery',
                'shippingConditions' => $conditions,
            ];

            if ($handlingTime !== null) {
                $service['handlingTime'] = $handlingTime;
            }

            $services[] = $service;
        }

        return $services;
    }

    private function buildCondition(object $method, object $rate, array $destinations): ?array
    {
        $type  = (int) $method->shipping_method_type;
        $start = (float) $rate->shipping_rate_weight_start;
        $end   = (float) $rate->shipping_rate_weight_end;
        $min   = (float) $method->subtotal_minimum;
        $max   = (float) $method->subtotal_maximum;

        $condition = ['@type' => 'ShippingConditions'];

        if ($destinations !== []) {
            $condition['shippingDestination'] = $destinations;
        }

        if ($type === self::TYPE_ORDER_VALUE) {
            // The rate bracket is an order-value range; intersect it with the method's subtotal bounds.
            $min = max($min, $start);
            $max = $end > 0 && ($max <= 0 || $end < $max) ? $end : $max;
        } elseif ($type === self::TYPE_ORDER_WEIGHT && ($start > 0 || $end > 0)) {
            $condition['weight'] = ['@type' => 'QuantitativeValue', 'unitCode' => $this->getWeightUnit()] + $this->range($start, $end);
        } elseif ($type !== self::TYPE_FLAT && $type !== self::TYPE_ORDER_WEIGHT) {
            // Per-item and per-quantity pricing has no ShippingConditions equivalent.
            return null;
        }

        $currency = (string) $this->config->get('config_currency', 'USD');

        if ($min > 0 || $max > 0) {
            $condition['orderValue'] = ['@type' => 'MonetaryAmount', 'currency' => $currency] + $this->range($min, $max);
        }

        $condition['shippingRate'] = [
            '@type'    => 'MonetaryAmount',
            'value'    => round((float) $rate->shipping_rate_price + (float) $rate->shipping_rate_handling, 2),
            'currency' => $currency,
        ];

        return $condition;
    }

    /** Zero max means open-ended, matching how shipping_standard reads the rate tables. */
    private function range(float $min, float $max): array
    {
        return ['minValue' => $min] + ($max > 0 ? ['maxValue' => $max] : []);
    }

    private function buildHandlingTime(): ?array
    {
        $period = ['@type' => 'ServicePeriod'];
        $days   = array_filter((array) $this->params->get('handling_business_days', []));
        $cutoff = trim((string) $this->params->get('handling_cutoff_time', ''));
        $max    = $this->params->get('handling_days_max', '');

        if ($days !== []) {
            $period['businessDays'] = array_map(static fn (string $day): string => 'https://schema.org/' . $day, array_values($days));
        }

        if (preg_match('/^\d{2}:\d{2}$/', $cutoff) === 1) {
            $period['cutoffTime'] = $cutoff . ':00' . (new \DateTimeImmutable('now', new \DateTimeZone($this->timezone)))->format('P');
        }

        if ($max !== '' && $max !== null) {
            $period['duration'] = ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => (int) $max, 'unitCode' => 'DAY'];
        }

        return \count($period) > 1 ? $period : null;
    }

    private function buildTransitTime(): ?array
    {
        $max = $this->params->get('transit_days_max', '');

        if ($max === '' || $max === null) {
            return null;
        }

        return [
            '@type'    => 'ServicePeriod',
            'duration' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => (int) $max, 'unitCode' => 'DAY'],
        ];
    }

    /** @return object[] published methods, each carrying its rate rows in ->rates */
    private function loadMethods(): array
    {
        $db        = $this->db;
        $published = 1;

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'j2commerce_shippingmethod_id',
                'shipping_method_name',
                'shipping_method_type',
                'subtotal_minimum',
                'subtotal_maximum',
            ]))
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('published') . ' = :published')
            ->bind(':published', $published, ParameterType::INTEGER)
            ->order($db->quoteName('shipping_method_name'));

        $methods = $db->setQuery($query)->loadObjectList('j2commerce_shippingmethod_id');

        if (!$methods) {
            return [];
        }

        foreach ($methods as $method) {
            $method->rates = [];
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'shipping_method_id',
                'geozone_id',
                'shipping_rate_price',
                'shipping_rate_handling',
                'shipping_rate_weight_start',
                'shipping_rate_weight_end',
            ]))
            ->from($db->quoteName('#__j2commerce_shippingrates'))
            ->whereIn($db->quoteName('shipping_method_id'), array_map('intval', array_keys($methods)))
            ->order($db->quoteName(['shipping_rate_weight_start', 'shipping_rate_price']));

        foreach ($db->setQuery($query)->loadObjectList() as $rate) {
            $methods[$rate->shipping_method_id]->rates[] = $rate;
        }

        return array_values($methods);
    }

    /** @return array<int, array<int, array>> DefinedRegion list keyed by geozone id */
    private function loadDestinations(): array
    {
        $db      = $this->db;
        $enabled = 1;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('r.geozone_id'),
                $db->quoteName('c.country_isocode_2', 'country'),
                $db->quoteName('z.zone_code', 'region'),
            ])
            ->from($db->quoteName('#__j2commerce_geozonerules', 'r'))
            ->join('INNER', $db->quoteName('#__j2commerce_geozones', 'g'), $db->quoteName('g.j2commerce_geozone_id') . ' = ' . $db->quoteName('r.geozone_id'))
            ->join('INNER', $db->quoteName('#__j2commerce_countries', 'c'), $db->quoteName('c.j2commerce_country_id') . ' = ' . $db->quoteName('r.country_id'))
            ->join('LEFT', $db->quoteName('#__j2commerce_zones', 'z'), $db->quoteName('z.j2commerce_zone_id') . ' = ' . $db->quoteName('r.zone_id'))
            ->where($db->quoteName('g.enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER);

        $regions = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $country = strtoupper(trim((string) $row->country));

            if ($country === '') {
                continue;
            }

            $region = ['@type' => 'DefinedRegion', 'addressCountry' => $country];

            if (!empty($row->region) && \in_array($country, self::REGION_COUNTRIES, true)) {
                $region['addressRegion'] = $row->region;
            }

            $regions[(int) $row->geozone_id][$country . '/' . ($region['addressRegion'] ?? '')] = $region;
        }

        return array_map('array_values', $regions);
    }

    private function getWeightUnit(): string
    {
        if ($this->weightUnit === null) {
            $db    = $this->db;
            $id    = (int) $this->config->get('config_weight_class_id', 0);
            $query = $db->getQuery(true)
                ->select($db->quoteName('weight_unit'))
                ->from($db->quoteName('#__j2commerce_weights'))
                ->where($db->quoteName('j2commerce_weight_id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);

            $unit             = strtolower((string) $db->setQuery($query)->loadResult());
            $this->weightUnit = self::WEIGHT_UNITS[$unit] ?? 'KGM';
        }

        return $this->weightUnit;
    }
}
