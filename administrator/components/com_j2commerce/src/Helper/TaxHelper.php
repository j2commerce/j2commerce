<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Public tax-calculation API for com_j2commerce.
 *
 * Centralises the tax-compute math that CartOrder uses during checkout so that
 * code paths outside the cart/checkout flow (plugins applying discounts, custom
 * report generators, third-party integrations) can resolve the same rates,
 * fire the same dispatcher event, and produce the same totals as core.
 *
 * @since  6.3.0
 */
final class TaxHelper
{
    /**
     * Resolve the active customer shipping address from the J2Commerce session.
     *
     * Lookup order mirrors CartOrder:
     *   1. saved shipping_address_id      → `#__j2commerce_addresses`
     *   2. guest_shipping array           → session
     *   3. flat shipping_country_id keys  → estimate-shipping flow
     *
     * @return  \stdClass  { country_id:int, zone_id:int, postcode:string }
     *
     * @since   6.3.0
     */
    public static function getCustomerAddress(): \stdClass
    {
        $session   = Factory::getApplication()->getSession();
        $countryId = 0;
        $zoneId    = 0;
        $postcode  = '';

        $addressId = (int) $session->get('shipping_address_id', 0, 'j2commerce');

        if ($addressId > 0) {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('country_id'), $db->quoteName('zone_id'), $db->quoteName('zip')])
                ->from($db->quoteName('#__j2commerce_addresses'))
                ->where($db->quoteName('j2commerce_address_id') . ' = :addrId')
                ->bind(':addrId', $addressId, ParameterType::INTEGER);

            $db->setQuery($query);
            $address = $db->loadObject();

            if ($address) {
                $countryId = (int) ($address->country_id ?? 0);
                $zoneId    = (int) ($address->zone_id ?? 0);
                $postcode  = (string) ($address->zip ?? '');
            }
        }

        if ($countryId === 0) {
            $guestShipping = $session->get('guest_shipping', [], 'j2commerce');

            if (!empty($guestShipping) && \is_array($guestShipping)) {
                $countryId = (int) ($guestShipping['country_id'] ?? 0);
                $zoneId    = (int) ($guestShipping['zone_id'] ?? 0);
                $postcode  = (string) ($guestShipping['zip'] ?? $guestShipping['postcode'] ?? '');
            }
        }

        if ($countryId === 0) {
            $countryId = (int) $session->get('shipping_country_id', 0, 'j2commerce');
            $zoneId    = (int) $session->get('shipping_zone_id', 0, 'j2commerce');
            $postcode  = (string) $session->get('shipping_postcode', '', 'j2commerce');
        }

        return (object) [
            'country_id' => $countryId,
            'zone_id'    => $zoneId,
            'postcode'   => $postcode,
        ];
    }

    /**
     * Resolve the geozone IDs that match the given customer address.
     *
     * @param   \stdClass|null  $address  Pre-resolved address; null → use getCustomerAddress().
     *
     * @return  array  Geozone IDs (may be empty).
     *
     * @since   6.3.0
     */
    public static function getCustomerGeozones(?\stdClass $address = null): array
    {
        $address ??= self::getCustomerAddress();

        $countryId = (int) ($address->country_id ?? 0);
        $zoneId    = (int) ($address->zone_id ?? 0);

        if ($countryId === 0) {
            return [];
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('geozone_id'))
            ->from($db->quoteName('#__j2commerce_geozonerules'))
            ->where($db->quoteName('country_id') . ' = :countryId')
            ->where(
                '(' . $db->quoteName('zone_id') . ' = 0 OR '
                . $db->quoteName('zone_id') . ' = :zoneId)'
            )
            ->bind(':countryId', $countryId, ParameterType::INTEGER)
            ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

        $db->setQuery($query);

        return $db->loadColumn() ?: [];
    }

    /**
     * Load the matching `#__j2commerce_taxrates` row for a profile + geozone set.
     *
     * @return  \stdClass|null  Raw row or null when nothing matches.
     *
     * @since   6.3.0
     */
    public static function getTaxRateForGeozone(int $taxprofileId, array $geozoneIds): ?\stdClass
    {
        if ($taxprofileId <= 0 || empty($geozoneIds)) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('rt.j2commerce_taxrate_id'),
                $db->quoteName('rt.taxrate_name'),
                $db->quoteName('rt.tax_percent'),
                $db->quoteName('rt.geozone_id'),
                $db->quoteName('tp.taxprofile_name'),
            ])
            ->from($db->quoteName('#__j2commerce_taxrules', 'tr'))
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_taxrates', 'rt'),
                $db->quoteName('rt.j2commerce_taxrate_id') . ' = ' . $db->quoteName('tr.taxrate_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__j2commerce_taxprofiles', 'tp'),
                $db->quoteName('tp.j2commerce_taxprofile_id') . ' = ' . $db->quoteName('tr.taxprofile_id')
            )
            ->where($db->quoteName('tr.taxprofile_id') . ' = :profileId')
            ->whereIn($db->quoteName('rt.geozone_id'), array_map('intval', $geozoneIds))
            ->bind(':profileId', $taxprofileId, ParameterType::INTEGER)
            ->order($db->quoteName('tr.ordering') . ' ASC');

        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    /**
     * Resolve rate objects for a profile + geozone set, then dispatch
     * `onJ2CommerceAfterGetTaxRateItems` so tax-engine plugins (Avalara,
     * app_taxrate, EU VAT, etc.) can append, override, or replace the rates.
     *
     * @param   \stdClass|null  $address  Optional pre-resolved address used for the event payload.
     *
     * @return  array  Array of rate stdClass objects.
     *
     * @since   6.3.0
     */
    public static function getTaxRatesForProfile(
        int $taxprofileId,
        array $geozoneIds,
        ?\stdClass $address = null
    ): array {
        $address ??= self::getCustomerAddress();
        $ratesets = [];

        $taxInfo = self::getTaxRateForGeozone($taxprofileId, $geozoneIds);

        if ($taxInfo !== null) {
            $rate                         = new \stdClass();
            $rate->j2commerce_taxrate_id  = (int) ($taxInfo->j2commerce_taxrate_id ?? 0);
            $rate->name                   = (string) ($taxInfo->taxrate_name ?? '');
            $rate->taxrate_name           = $rate->name;
            $rate->rate                   = (float) ($taxInfo->tax_percent ?? 0);
            $rate->tax_percent            = $rate->rate;
            $rate->taxprofile_name        = (string) ($taxInfo->taxprofile_name ?? '');
            $ratesets[]                   = $rate;
        }

        $event = J2CommerceHelper::plugin()->event('AfterGetTaxRateItems', [
            'result'        => $ratesets,
            'address_type'  => 'shipping',
            'country_id'    => (int) ($address->country_id ?? 0),
            'zone_id'       => (int) ($address->zone_id ?? 0),
            'postcode'      => (string) ($address->postcode ?? ''),
            'taxprofile_id' => $taxprofileId,
        ]);

        $merged = $event->getEventResult();

        return \is_array($merged) ? $merged : $ratesets;
    }

    /**
     * Compute tax for an arbitrary amount under a given tax profile.
     *
     * Honours both J2Commerce pricing modes:
     *   - exclusive (config_including_tax=0): tax_amount = amount × pct / 100
     *   - inclusive (config_including_tax=1): tax_amount = amount − amount / (1 + pct/100)
     *
     * Multi-rate carts: each entry in `rates[]` carries its own `tax_amount` so
     * compound VAT, GST+PST, and EU multi-rate stores reconcile per-rate totals.
     *
     * @param   float        $amount         Taxable amount (per-unit or per-line; caller decides).
     * @param   int          $taxprofileId   `#__j2commerce_taxprofiles.j2commerce_taxprofile_id`.
     * @param   array|null   $geozoneIds     Pre-resolved geozones; null → resolve from session.
     * @param   bool         $taxInclusive   Whether `$amount` is already tax-inclusive.
     *
     * @return  \stdClass  { taxtotal:float, rates:array<int,\stdClass> }
     *                    rates[i] = { j2commerce_taxrate_id, name, taxrate_name, rate,
     *                                 tax_percent, taxprofile_name, tax_amount }
     *
     * @since   6.3.0
     */
    public static function computeTax(
        float $amount,
        int $taxprofileId,
        ?array $geozoneIds = null,
        bool $taxInclusive = false
    ): \stdClass {
        $result           = new \stdClass();
        $result->taxtotal = 0.0;
        $result->rates    = [];

        if ($taxprofileId <= 0 || $amount === 0.0) {
            return $result;
        }

        $address     = self::getCustomerAddress();
        $geozoneIds ??= self::getCustomerGeozones($address);

        if (empty($geozoneIds)) {
            return $result;
        }

        $ratesets = self::getTaxRatesForProfile($taxprofileId, $geozoneIds, $address);

        if (empty($ratesets)) {
            return $result;
        }

        $totalPercent = 0.0;

        foreach ($ratesets as $rate) {
            $totalPercent += (float) ($rate->rate ?? $rate->tax_percent ?? 0);
        }

        if ($totalPercent <= 0.0) {
            $result->rates = $ratesets;
            return $result;
        }

        $totalTax = $taxInclusive
            ? $amount - ($amount / (1 + ($totalPercent / 100)))
            : $amount * ($totalPercent / 100);

        foreach ($ratesets as $rate) {
            $ratePercent      = (float) ($rate->rate ?? $rate->tax_percent ?? 0);
            $rate->tax_amount = $totalTax * ($ratePercent / $totalPercent);
        }

        $result->taxtotal = $totalTax;
        $result->rates    = $ratesets;

        return $result;
    }
}
