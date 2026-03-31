<?php

declare(strict_types=1);

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Component\J2commerce\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class OnboardingHelper
{
    /**
     * Standard conversion: grams per 1 unit.
     * Matches WeightsController::syncValues().
     */
    private static array $gramsPerUnit = [
        'kg' => 1000.0,
        'g'  => 1.0,
        'mg' => 0.001,
        'oz' => 28.3495231,
        'lb' => 453.59237,
        't'  => 1000000.0,
    ];

    /**
     * Standard conversion: millimetres per 1 unit.
     * Matches LengthsController::syncValues().
     */
    private static array $mmPerUnit = [
        'mm' => 1.0,
        'cm' => 10.0,
        'm'  => 1000.0,
        'km' => 1000000.0,
        'in' => 25.4,
        'ft' => 304.8,
        'yd' => 914.4,
        'mi' => 1609344.0,
    ];

    /**
     * Country ID → recommended store defaults.
     */
    private static array $countryDefaults = [
        223 => ['currency' => 'USD', 'weight_id' => 4, 'length_id' => 2, 'tax_style' => 'excluding'],
        222 => ['currency' => 'GBP', 'weight_id' => 1, 'length_id' => 1, 'tax_style' => 'including'],
        38  => ['currency' => 'CAD', 'weight_id' => 4, 'length_id' => 1, 'tax_style' => 'excluding'],
        13  => ['currency' => 'AUD', 'weight_id' => 1, 'length_id' => 1, 'tax_style' => 'including'],
        101 => ['currency' => 'INR', 'weight_id' => 1, 'length_id' => 1, 'tax_style' => 'including'],
    ];

    /** EU country IDs that default to EUR/kg/cm/including. */
    private static array $euCountryIds = [
        14, 21, 33, 53, 55, 56, 57, 67, 72, 73, 81, 84, 97,
        103, 105, 117, 123, 124, 132, 150, 170, 171, 175, 189, 190, 195, 203,
    ];

    /**
     * Get recommended defaults for a country.
     *
     * @return array{currency: string, weight_id: int, length_id: int, tax_style: string}
     */
    public static function getCountryDefaults(int $countryId): array
    {
        if (isset(self::$countryDefaults[$countryId])) {
            return self::$countryDefaults[$countryId];
        }

        if (\in_array($countryId, self::$euCountryIds, true)) {
            return ['currency' => 'EUR', 'weight_id' => 1, 'length_id' => 1, 'tax_style' => 'including'];
        }

        // Fallback
        return ['currency' => 'USD', 'weight_id' => 1, 'length_id' => 1, 'tax_style' => 'excluding'];
    }

    /**
     * Recalculate all weight values relative to a new default weight ID.
     * Logic extracted from WeightsController::syncValues().
     */
    public static function syncWeights(int $defaultId, DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_weight_id'),
                $db->quoteName('weight_unit'),
                $db->quoteName('weight_value'),
            ])
            ->from($db->quoteName('#__j2commerce_weights'));
        $weights = $db->setQuery($query)->loadObjectList();

        if (empty($weights)) {
            return;
        }

        $defaultWeight = null;

        foreach ($weights as $w) {
            if ((int) $w->j2commerce_weight_id === $defaultId) {
                $defaultWeight = $w;
                break;
            }
        }

        if ($defaultWeight === null) {
            return;
        }

        $defaultUnit = strtolower(trim($defaultWeight->weight_unit));

        if (!isset(self::$gramsPerUnit[$defaultUnit])) {
            $oldVal = (float) $defaultWeight->weight_value;

            if ($oldVal <= 0.0) {
                return;
            }

            foreach ($weights as $w) {
                $newValue       = (float) $w->weight_value / $oldVal;
                $formattedValue = number_format($newValue, 8, '.', '');
                $wId            = (int) $w->j2commerce_weight_id;
                $update         = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_weights'))
                    ->set($db->quoteName('weight_value') . ' = :value')
                    ->where($db->quoteName('j2commerce_weight_id') . ' = :id')
                    ->bind(':value', $formattedValue)
                    ->bind(':id', $wId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            }

            return;
        }

        $gramsPerDefault = self::$gramsPerUnit[$defaultUnit];

        foreach ($weights as $w) {
            $unit = strtolower(trim($w->weight_unit));

            if (isset(self::$gramsPerUnit[$unit])) {
                $newValue = $gramsPerDefault / self::$gramsPerUnit[$unit];
            } else {
                $oldVal   = (float) $defaultWeight->weight_value;
                $newValue = ($oldVal > 0.0) ? (float) $w->weight_value / $oldVal : 1.0;
            }

            $formattedValue = number_format($newValue, 8, '.', '');
            $wId            = (int) $w->j2commerce_weight_id;
            $update         = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_weights'))
                ->set($db->quoteName('weight_value') . ' = :value')
                ->where($db->quoteName('j2commerce_weight_id') . ' = :id')
                ->bind(':value', $formattedValue)
                ->bind(':id', $wId, ParameterType::INTEGER);
            $db->setQuery($update)->execute();
        }
    }

    /**
     * Recalculate all length values relative to a new default length ID.
     * Logic extracted from LengthsController::syncValues().
     */
    public static function syncLengths(int $defaultId, DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('j2commerce_length_id'),
                $db->quoteName('length_unit'),
                $db->quoteName('length_value'),
            ])
            ->from($db->quoteName('#__j2commerce_lengths'));
        $lengths = $db->setQuery($query)->loadObjectList();

        if (empty($lengths)) {
            return;
        }

        $defaultLength = null;

        foreach ($lengths as $l) {
            if ((int) $l->j2commerce_length_id === $defaultId) {
                $defaultLength = $l;
                break;
            }
        }

        if ($defaultLength === null) {
            return;
        }

        $defaultUnit = strtolower(trim($defaultLength->length_unit));

        if (!isset(self::$mmPerUnit[$defaultUnit])) {
            $oldVal = (float) $defaultLength->length_value;

            if ($oldVal <= 0.0) {
                return;
            }

            foreach ($lengths as $l) {
                $newValue       = (float) $l->length_value / $oldVal;
                $formattedValue = number_format($newValue, 8, '.', '');
                $lId            = (int) $l->j2commerce_length_id;
                $update         = $db->getQuery(true)
                    ->update($db->quoteName('#__j2commerce_lengths'))
                    ->set($db->quoteName('length_value') . ' = :value')
                    ->where($db->quoteName('j2commerce_length_id') . ' = :id')
                    ->bind(':value', $formattedValue)
                    ->bind(':id', $lId, ParameterType::INTEGER);
                $db->setQuery($update)->execute();
            }

            return;
        }

        $mmPerDefault = self::$mmPerUnit[$defaultUnit];

        foreach ($lengths as $l) {
            $unit = strtolower(trim($l->length_unit));

            if (isset(self::$mmPerUnit[$unit])) {
                $newValue = $mmPerDefault / self::$mmPerUnit[$unit];
            } else {
                $oldVal   = (float) $defaultLength->length_value;
                $newValue = ($oldVal > 0.0) ? (float) $l->length_value / $oldVal : 1.0;
            }

            $formattedValue = number_format($newValue, 8, '.', '');
            $lId            = (int) $l->j2commerce_length_id;
            $update         = $db->getQuery(true)
                ->update($db->quoteName('#__j2commerce_lengths'))
                ->set($db->quoteName('length_value') . ' = :value')
                ->where($db->quoteName('j2commerce_length_id') . ' = :id')
                ->bind(':value', $formattedValue)
                ->bind(':id', $lId, ParameterType::INTEGER);
            $db->setQuery($update)->execute();
        }
    }

    /**
     * Create a default tax setup: geozone → geozone rule → tax rate → tax profile → tax rule.
     *
     * @return array{geozone_id: int, taxrate_id: int, taxprofile_id: int, taxrule_id: int}
     */
    public static function createDefaultTax(
        int $countryId,
        int $zoneId,
        float $taxPercent,
        DatabaseInterface $db,
    ): array {
        $countryName = self::getCountryName($countryId, $db);
        $isUS        = ($countryId === 223);

        if ($isUS && $zoneId > 0) {
            $zoneName    = self::getZoneName($zoneId, $db);
            $geoName     = $zoneName;
            $ruleZoneId  = $zoneId;
            $rateName    = "$zoneName Sales Tax";
        } else {
            $geoName     = $countryName;
            $ruleZoneId  = 0;
            $rateName    = "$countryName Tax";
        }

        $geozone = (object) [
            'geozone_name' => $geoName,
            'enabled'      => 1,
            'ordering'     => 1,
        ];
        $db->insertObject('#__j2commerce_geozones', $geozone, 'j2commerce_geozone_id');
        $geozoneId = (int) $geozone->j2commerce_geozone_id;

        $geoRule = (object) [
            'geozone_id' => $geozoneId,
            'country_id' => $countryId,
            'zone_id'    => $ruleZoneId,
        ];
        $db->insertObject('#__j2commerce_geozonerules', $geoRule, 'j2commerce_geozonerule_id');
        $taxRate  = (object) [
            'geozone_id'   => $geozoneId,
            'taxrate_name' => $rateName,
            'tax_percent'  => number_format($taxPercent, 3, '.', ''),
            'enabled'      => 1,
            'ordering'     => 1,
        ];
        $db->insertObject('#__j2commerce_taxrates', $taxRate, 'j2commerce_taxrate_id');
        $taxRateId = (int) $taxRate->j2commerce_taxrate_id;

        $taxProfile = (object) [
            'taxprofile_name' => 'Tax',
            'enabled'         => 1,
            'ordering'        => 1,
        ];
        $db->insertObject('#__j2commerce_taxprofiles', $taxProfile, 'j2commerce_taxprofile_id');
        $taxProfileId = (int) $taxProfile->j2commerce_taxprofile_id;

        $taxRule = (object) [
            'taxprofile_id' => $taxProfileId,
            'taxrate_id'    => $taxRateId,
            'address'       => 'shipping',
            'ordering'      => 1,
        ];
        $db->insertObject('#__j2commerce_taxrules', $taxRule, 'j2commerce_taxrule_id');
        $taxRuleId = (int) $taxRule->j2commerce_taxrule_id;

        return [
            'geozone_id'    => $geozoneId,
            'taxrate_id'    => $taxRateId,
            'taxprofile_id' => $taxProfileId,
            'taxrule_id'    => $taxRuleId,
        ];
    }

    /**
     * Check if a Joomla language tag is installed and/or the default.
     *
     * @return array{site_installed: bool, admin_installed: bool, is_default: bool}
     */
    public static function isLanguageInstalled(string $tag): array
    {
        $siteInstalled  = false;
        $adminInstalled = false;

        foreach (LanguageHelper::getInstalledLanguages(0) as $lang) {
            if ($lang->element === $tag) {
                $siteInstalled = true;
                break;
            }
        }

        foreach (LanguageHelper::getInstalledLanguages(1) as $lang) {
            if ($lang->element === $tag) {
                $adminInstalled = true;
                break;
            }
        }

        $defaultLang = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');

        return [
            'site_installed'  => $siteInstalled,
            'admin_installed' => $adminInstalled,
            'is_default'      => ($defaultLang === $tag),
        ];
    }

    /**
     * Persist an array of key/value pairs into com_j2commerce component params.
     */
    public static function persistConfig(array $values): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $ext = $db->setQuery($query)->loadObject();

        if (!$ext) {
            throw new \RuntimeException('com_j2commerce extension record not found');
        }

        $params = new Registry($ext->params);

        foreach ($values as $key => $value) {
            $params->set($key, $value);
        }

        $paramsJson = $params->toString();
        $extId      = (int) $ext->extension_id;

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $extId, ParameterType::INTEGER);

        $db->setQuery($update)->execute();

        ConfigHelper::reset();
    }

    /**
     * Get the step to resume from (1-based). Returns 1 if no progress saved.
     */
    public static function getResumeStep(): int
    {
        $lastStep = (int) ConfigHelper::get('onboarding_last_step', 0);

        return min($lastStep + 1, 5);
    }

    /**
     * Disable all currencies except the selected default.
     */
    public static function setSingleCurrency(string $currencyCode, DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('enabled') . ' = 0');
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->set($db->quoteName('currency_value') . ' = ' . $db->quote('1.00000000'))
            ->where($db->quoteName('currency_code') . ' = :code')
            ->bind(':code', $currencyCode);
        $db->setQuery($query)->execute();
    }

    /**
     * Set the base currency value to 1.00000000 (for multi-currency mode).
     */
    public static function setBaseCurrencyValue(string $currencyCode, DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_currencies'))
            ->set($db->quoteName('currency_value') . ' = ' . $db->quote('1.00000000'))
            ->where($db->quoteName('currency_code') . ' = :code')
            ->bind(':code', $currencyCode);
        $db->setQuery($query)->execute();
    }

    private static function getCountryName(int $countryId, DatabaseInterface $db): string
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('country_name'))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('j2commerce_country_id') . ' = :id')
            ->bind(':id', $countryId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    private static function getZoneName(int $zoneId, DatabaseInterface $db): string
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('zone_name'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $zoneId, ParameterType::INTEGER);

        return (string) $db->setQuery($query)->loadResult();
    }

    /**
     * Swap address_1 / address_2 ordering based on country convention.
     *
     * US (country_id=223): street first  → address_1=4, address_2=5
     * Non-US:              unit/flat first → address_2=4, address_1=5
     *
     * Should only be called once during onboarding when the country is first set.
     */
    public static function reorderAddressFields(int $countryId, DatabaseInterface $db): void
    {
        if ($countryId === 223) {
            $addr1Order = 4;
            $addr2Order = 5;
        } else {
            $addr1Order = 5;
            $addr2Order = 4;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_customfields'))
            ->set($db->quoteName('ordering') . ' = ' . $addr1Order)
            ->where($db->quoteName('field_namekey') . ' = ' . $db->quote('address_1'))
            ->where($db->quoteName('field_core') . ' = 1');
        $db->setQuery($query)->execute();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_customfields'))
            ->set($db->quoteName('ordering') . ' = ' . $addr2Order)
            ->where($db->quoteName('field_namekey') . ' = ' . $db->quote('address_2'))
            ->where($db->quoteName('field_core') . ' = 1');
        $db->setQuery($query)->execute();
    }
}
