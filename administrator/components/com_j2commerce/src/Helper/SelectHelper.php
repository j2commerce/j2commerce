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

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// No direct access
\defined('_JEXEC') or die;

/**
 * Select Helper class for J2Commerce
 *
 * Provides methods to generate HTML select lists and retrieve option arrays
 * for countries, currencies, tax rates, order statuses, geozones, and other entities.
 *
 * @since  6.0.0
 */
class SelectHelper
{
    /**
     * Database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    // =========================================================================
    // GENERIC LIST HELPER METHODS
    // =========================================================================

    /**
     * Generate a generic select list with Bootstrap 5 form-select class
     *
     * @param   array<int, object>  $list      Array of option objects
     * @param   string              $name      The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   mixed               $selected  The selected value
     * @param   string|null         $idTag     The HTML id attribute
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function genericlist(
        array $list,
        string $name,
        array $attribs = [],
        mixed $selected = null,
        ?string $idTag = null
    ): string {
        // Ensure Bootstrap 5 form-select class is applied
        if (!isset($attribs['class'])) {
            $attribs['class'] = 'form-select';
        } elseif (strpos($attribs['class'], 'form-select') === false) {
            $attribs['class'] = $attribs['class'] . ' form-select';
        }

        return HTMLHelper::_('select.genericlist', $list, $name, $attribs, 'value', 'text', $selected, $idTag);
    }

    /**
     * Create a select option object
     *
     * @param   mixed   $value  The option value
     * @param   string  $text   The option text
     *
     * @return  object  The option object
     *
     * @since   6.0.0
     */
    public static function option(mixed $value, string $text): object
    {
        return HTMLHelper::_('select.option', $value, $text);
    }

    // =========================================================================
    // COUNTRY METHODS
    // =========================================================================

    /**
     * Get all enabled countries as an array
     *
     * @return  array<int, string>  Array of country_id => country_name
     *
     * @since   6.0.0
     */
    public static function getCountries(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_country_id', 'country_name']))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('country_name') . ' ASC');

        $db->setQuery($query);
        $countries = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($countries as $country) {
            $options[(int) $country->j2commerce_country_id] = Text::_($country->country_name);
        }

        return $options;
    }

    /**
     * Generate a country select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function countries(
        mixed $selected = null,
        string $name = 'country_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_COUNTRY')) . ' -');
        }

        $countries = self::getCountries();
        foreach ($countries as $id => $countryName) {
            $options[] = self::option($id, $countryName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // CURRENCY METHODS
    // =========================================================================

    /**
     * Get all enabled currencies as an array
     *
     * @return  array<int, string>  Array of currency_id => currency_title
     *
     * @since   6.0.0
     */
    public static function getCurrencies(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_currency_id', 'currency_title', 'currency_code']))
            ->from($db->quoteName('#__j2commerce_currencies'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $currencies = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($currencies as $currency) {
            $options[(int) $currency->j2commerce_currency_id] = $currency->currency_title . ' (' . $currency->currency_code . ')';
        }

        return $options;
    }

    /**
     * Generate a currency select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function currencies(
        mixed $selected = null,
        string $name = 'currency_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_CURRENCY') . ' -');
        }

        $currencies = self::getCurrencies();
        foreach ($currencies as $id => $currencyName) {
            $options[] = self::option($id, $currencyName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // TAX RATE METHODS
    // =========================================================================

    /**
     * Get all enabled tax rates as an array
     *
     * @return  array<int, string>  Array of taxrate_id => taxrate_name
     *
     * @since   6.0.0
     */
    public static function getTaxRates(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_taxrate_id', 'taxrate_name']))
            ->from($db->quoteName('#__j2commerce_taxrates'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $taxrates = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($taxrates as $taxrate) {
            $options[(int) $taxrate->j2commerce_taxrate_id] = $taxrate->taxrate_name;
        }

        return $options;
    }

    /**
     * Generate a tax rate select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function taxrates(
        mixed $selected = null,
        string $name = 'taxrate_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_TAXRATE')) . ' -');
        }

        $taxrates = self::getTaxRates();
        foreach ($taxrates as $id => $taxrateName) {
            $options[] = self::option($id, $taxrateName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // TAX PROFILE METHODS
    // =========================================================================

    /**
     * Get all enabled tax profiles as an array
     *
     * @return  array<int, string>  Array of taxprofile_id => taxprofile_name
     *
     * @since   6.0.0
     */
    public static function getTaxProfiles(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_taxprofile_id', 'taxprofile_name']))
            ->from($db->quoteName('#__j2commerce_taxprofiles'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $taxprofiles = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($taxprofiles as $taxprofile) {
            $options[(int) $taxprofile->j2commerce_taxprofile_id] = $taxprofile->taxprofile_name;
        }

        return $options;
    }

    /**
     * Generate a tax profile select list (tax class)
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function taxprofiles(
        mixed $selected = null,
        string $name = 'taxprofile_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $taxprofiles = self::getTaxProfiles();
        foreach ($taxprofiles as $id => $taxprofileName) {
            $options[] = self::option($id, $taxprofileName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    /**
     * Get all enabled order states as an array
     *
     * @return  array<int, string>  Array of orderstate_id => orderstatus_name
     *
     * @since   6.0.0
     */
    public static function getOrderStates(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_orderstate_id', 'orderstatus_name']))
            ->from($db->quoteName('#__j2commerce_orderstates'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $orderstates = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($orderstates as $orderstate) {
            $options[(int) $orderstate->j2commerce_orderstate_id] = $orderstate->orderstatus_name;
        }

        return $options;
    }

    /**
     * Generate an order state select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function orderstates(
        mixed $selected = null,
        string $name = 'orderstate_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_STATE') . ' -');
        }

        $orderstates = self::getOrderStates();
        foreach ($orderstates as $id => $orderstateName) {
            $options[] = self::option($id, $orderstateName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // GEOZONE METHODS
    // =========================================================================

    /**
     * Get all enabled geozones as an array
     *
     * @return  array<int, string>  Array of geozone_id => geozone_name
     *
     * @since   6.0.0
     */
    public static function getGeozones(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_geozone_id', 'geozone_name']))
            ->from($db->quoteName('#__j2commerce_geozones'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('geozone_name') . ' ASC');

        $db->setQuery($query);
        $geozones = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($geozones as $geozone) {
            $options[(int) $geozone->j2commerce_geozone_id] = $geozone->geozone_name;
        }

        return $options;
    }

    /**
     * Generate a geozone select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function geozones(
        mixed $selected = null,
        string $name = 'geozone_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_GEOZONE') . ' -');
        }

        $geozones = self::getGeozones();
        foreach ($geozones as $id => $geozoneName) {
            $options[] = self::option($id, $geozoneName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // MANUFACTURER METHODS
    // =========================================================================

    /**
     * Get all enabled manufacturers as an array
     *
     * @return  array<int, string>  Array of manufacturer_id => manufacturer_name
     *
     * @since   6.0.0
     */
    public static function getManufacturers(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_manufacturer_id', 'manufacturer_name']))
            ->from($db->quoteName('#__j2commerce_manufacturers'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('manufacturer_name') . ' ASC');

        $db->setQuery($query);
        $manufacturers = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($manufacturers as $manufacturer) {
            $options[(int) $manufacturer->j2commerce_manufacturer_id] = $manufacturer->manufacturer_name;
        }

        return $options;
    }

    /**
     * Generate a manufacturer select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function manufacturers(
        mixed $selected = null,
        string $name = 'manufacturer_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $manufacturers = self::getManufacturers();
        foreach ($manufacturers as $id => $manufacturerName) {
            $options[] = self::option($id, $manufacturerName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // VENDOR METHODS
    // =========================================================================

    /**
     * Get all enabled vendors as an array
     *
     * @return  array<int, string>  Array of vendor_id => vendor_name
     *
     * @since   6.0.0
     */
    public static function getVendors(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_vendor_id', 'vendor_name']))
            ->from($db->quoteName('#__j2commerce_vendors'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('vendor_name') . ' ASC');

        $db->setQuery($query);
        $vendors = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($vendors as $vendor) {
            $options[(int) $vendor->j2commerce_vendor_id] = $vendor->vendor_name;
        }

        return $options;
    }

    /**
     * Generate a vendor select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function vendors(
        mixed $selected = null,
        string $name = 'vendor_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $vendors = self::getVendors();
        foreach ($vendors as $id => $vendorName) {
            $options[] = self::option($id, $vendorName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // PRODUCT TYPE METHODS
    // =========================================================================

    /**
     * Get all product types
     *
     * @return  array<string, string>  Array of product_type => label
     *
     * @since   6.0.0
     */
    public static function getProductTypes(): array
    {
        return [
            'simple'        => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_SIMPLE'),
            'variable'      => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_VARIABLE'),
            'downloadable'  => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_DOWNLOADABLE'),
            'configurable'  => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_CONFIGURABLE'),
            'flexivariable' => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_FLEXIVARIABLE'),
            'bundle'        => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_BUNDLE'),
        ];
    }

    /**
     * Generate a product type select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function producttypes(
        mixed $selected = null,
        string $name = 'product_type',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_PRODUCT_TYPE') . ' -');
        }

        $producttypes = self::getProductTypes();
        foreach ($producttypes as $value => $label) {
            $options[] = self::option($value, $label);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // LENGTH UNIT METHODS
    // =========================================================================

    /**
     * Get all enabled length units as an array
     *
     * @return  array<int, string>  Array of length_id => length_title
     *
     * @since   6.0.0
     */
    public static function getLengths(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_length_id', 'length_title', 'length_unit']))
            ->from($db->quoteName('#__j2commerce_lengths'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $lengths = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($lengths as $length) {
            $options[(int) $length->j2commerce_length_id] = $length->length_title . ' (' . $length->length_unit . ')';
        }

        return $options;
    }

    /**
     * Generate a length unit select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function lengths(
        mixed $selected = null,
        string $name = 'length_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $lengths = self::getLengths();
        foreach ($lengths as $id => $lengthName) {
            $options[] = self::option($id, $lengthName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // WEIGHT UNIT METHODS
    // =========================================================================

    /**
     * Get all enabled weight units as an array
     *
     * @return  array<int, string>  Array of weight_id => weight_title
     *
     * @since   6.0.0
     */
    public static function getWeights(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_weight_id', 'weight_title', 'weight_unit']))
            ->from($db->quoteName('#__j2commerce_weights'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $weights = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($weights as $weight) {
            $options[(int) $weight->j2commerce_weight_id] = $weight->weight_title . ' (' . $weight->weight_unit . ')';
        }

        return $options;
    }

    /**
     * Generate a weight unit select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function weights(
        mixed $selected = null,
        string $name = 'weight_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $weights = self::getWeights();
        foreach ($weights as $id => $weightName) {
            $options[] = self::option($id, $weightName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // SHIPPING METHOD METHODS
    // =========================================================================

    /**
     * Get all enabled shipping methods as an array
     *
     * @return  array<int, string>  Array of shippingmethod_id => shippingmethod_name
     *
     * @since   6.0.0
     */
    public static function getShippingMethods(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_shippingmethod_id', 'shippingmethod_name']))
            ->from($db->quoteName('#__j2commerce_shippingmethods'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $methods = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($methods as $method) {
            $options[(int) $method->j2commerce_shippingmethod_id] = $method->shippingmethod_name;
        }

        return $options;
    }

    /**
     * Generate a shipping method select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function shippingmethods(
        mixed $selected = null,
        string $name = 'shippingmethod_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $methods = self::getShippingMethods();
        foreach ($methods as $id => $methodName) {
            $options[] = self::option($id, $methodName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    // =========================================================================
    // PAYMENT METHOD METHODS
    // =========================================================================

    /**
     * Get all enabled payment methods as an array
     *
     * @return  array<int, string>  Array of paymentmethod_id => paymentmethod_name
     *
     * @since   6.0.0
     */
    public static function getPaymentMethods(): array
    {
        $db      = self::getDatabase();
        $query   = $db->getQuery(true);
        $enabled = 1;

        $query->select($db->quoteName(['j2commerce_paymentmethod_id', 'paymentmethod_name']))
            ->from($db->quoteName('#__j2commerce_paymentmethods'))
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $methods = $db->loadObjectList() ?: [];

        $options = [];
        foreach ($methods as $method) {
            $options[(int) $method->j2commerce_paymentmethod_id] = $method->paymentmethod_name;
        }

        return $options;
    }

    /**
     * Generate a payment method select list
     *
     * @param   mixed              $selected   The selected value
     * @param   string             $name       The HTML name attribute
     * @param   array<string, mixed> $attribs  HTML attributes for the select element
     * @param   string|null        $idTag      The HTML id attribute
     * @param   bool               $allowEmpty Include an empty "Select" option
     *
     * @return  string  The HTML for the select list
     *
     * @since   6.0.0
     */
    public static function paymentmethods(
        mixed $selected = null,
        string $name = 'paymentmethod_id',
        array $attribs = [],
        ?string $idTag = null,
        bool $allowEmpty = true
    ): string {
        $options = [];

        if ($allowEmpty) {
            $options[] = self::option('', '- ' . Text::_('COM_J2COMMERCE_SELECT_OPTION') . ' -');
        }

        $methods = self::getPaymentMethods();
        foreach ($methods as $id => $methodName) {
            $options[] = self::option($id, $methodName);
        }

        return self::genericlist($options, $name, $attribs, $selected, $idTag ?? $name);
    }

    public static function getParentOption(int $productId, array $defaults, int $excludeOptionId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('j2commerce_option_id'), $db->quoteName('option_name')])
            ->from($db->quoteName('#__j2commerce_options'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('j2commerce_option_id') . ' != :excludeId')
            ->bind(':excludeId', $excludeOptionId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $options = [0 => Text::_('COM_J2COMMERCE_SELECT_PARENT_OPTION')];

        foreach ($rows as $row) {
            $options[(int) $row->j2commerce_option_id] = $row->option_name;
        }

        return $options;
    }
}
