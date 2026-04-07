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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Plugin\Schemaorg\Ecommerce\Helper\J2CommerceSchemaHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

class OrganizationSchemaBuilder
{
    public function __construct(
        private J2CommerceSchemaHelper $helper,
        private DatabaseInterface $db,
        private Registry $params,
    ) {
    }

    public function build(): array
    {
        $schema = [
            '@type' => 'Organization',
            'name'  => $this->helper->getStoreName(),
            'url'   => Uri::root(),
        ];

        $logo = $this->getStoreLogo();

        if (!empty($logo)) {
            $schema['logo'] = $logo;
        }

        $description = $this->getStoreDescription();

        if (!empty($description)) {
            $schema['description'] = $description;
        }

        $contactPoint = $this->buildContactPoint();

        if (!empty($contactPoint)) {
            $schema['contactPoint'] = $contactPoint;
        }

        $address = $this->buildPostalAddress();

        if (!empty($address)) {
            $schema['address'] = $address;
        }

        $sameAs = $this->getSocialProfiles();

        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        $this->addParamOverrides($schema);

        return $this->cleanSchemaData($schema);
    }

    public function buildLocalBusiness(?string $businessType = null): array
    {
        $schema          = $this->build();
        $schema['@type'] = $businessType ?: 'LocalBusiness';

        $priceRange = $this->params->get('price_range', '');

        if (!empty($priceRange)) {
            $schema['priceRange'] = $priceRange;
        }

        $openingHours = $this->getOpeningHours();

        if (!empty($openingHours)) {
            $schema['openingHoursSpecification'] = $openingHours;
        }

        $geo = $this->getGeoCoordinates();

        if (!empty($geo)) {
            $schema['geo'] = $geo;
        }

        $paymentAccepted = $this->getPaymentAccepted();

        if (!empty($paymentAccepted)) {
            $schema['paymentAccepted'] = $paymentAccepted;
        }

        $currenciesAccepted = $this->helper->getCurrencyCode();

        if (!empty($currenciesAccepted)) {
            $schema['currenciesAccepted'] = $currenciesAccepted;
        }

        return $this->cleanSchemaData($schema);
    }

    private function getStoreLogo(): ?string
    {
        $logo = $this->params->get('organization_logo', '');

        if (!empty($logo)) {
            return $this->cleanImageUrl($logo);
        }

        $storeLogo = ComponentHelper::getParams('com_j2commerce')->get('store_logo', '');

        if (!empty($storeLogo)) {
            return $this->cleanImageUrl($storeLogo);
        }

        return null;
    }

    private function cleanImageUrl(string $imagePath): string
    {
        if (empty($imagePath)) {
            return '';
        }

        $cleanedImage = HTMLHelper::_('cleanImageURL', $imagePath);

        $url = \is_object($cleanedImage) && isset($cleanedImage->url)
            ? $cleanedImage->url
            : $imagePath;

        return $this->makeAbsoluteUrl($url);
    }

    private function makeAbsoluteUrl(string $url): string
    {
        if (empty($url) || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($url, '/');
    }

    private function getStoreDescription(): ?string
    {
        $description = $this->params->get('organization_description', '');

        return !empty($description) ? strip_tags($description) : null;
    }

    private function buildContactPoint(): array|null
    {
        $contactPoints = [];

        $phone = $this->getStorePhone();
        $email = $this->getStoreEmail();

        if (!empty($phone) || !empty($email)) {
            $contactPoint = [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer service',
            ];

            if (!empty($phone)) {
                $contactPoint['telephone'] = $phone;
            }

            if (!empty($email)) {
                $contactPoint['email'] = $email;
            }

            $language = $this->params->get('contact_language', 'en');

            if (!empty($language)) {
                $contactPoint['availableLanguage'] = $language;
            }

            $contactPoints[] = $contactPoint;
        }

        $salesPhone = $this->params->get('sales_phone', '');

        if (!empty($salesPhone)) {
            $contactPoints[] = [
                '@type'       => 'ContactPoint',
                'contactType' => 'sales',
                'telephone'   => $salesPhone,
            ];
        }

        $supportPhone = $this->params->get('support_phone', '');

        if (!empty($supportPhone)) {
            $contactPoints[] = [
                '@type'       => 'ContactPoint',
                'contactType' => 'technical support',
                'telephone'   => $supportPhone,
            ];
        }

        if (\count($contactPoints) === 1) {
            return $contactPoints[0];
        }

        return !empty($contactPoints) ? $contactPoints : null;
    }

    private function getStorePhone(): ?string
    {
        $phone = $this->params->get('organization_phone', '');

        return !empty($phone) ? $phone : null;
    }

    private function getStoreEmail(): ?string
    {
        $email = $this->params->get('organization_email', '');

        return !empty($email)
            ? $email
            : (\Joomla\CMS\Factory::getApplication()->get('mailfrom', '') ?: null);
    }

    private function buildPostalAddress(): ?array
    {
        $storeAddress = $this->getStoreAddress();

        if (!$storeAddress) {
            return null;
        }

        $address = ['@type' => 'PostalAddress'];

        $streetAddress = trim(($storeAddress->address_1 ?? '') . ' ' . ($storeAddress->address_2 ?? ''));

        if (!empty($streetAddress)) {
            $address['streetAddress'] = $streetAddress;
        }

        if (!empty($storeAddress->city)) {
            $address['addressLocality'] = $storeAddress->city;
        }

        if (!empty($storeAddress->zone_name)) {
            $address['addressRegion'] = $storeAddress->zone_name;
        }

        if (!empty($storeAddress->zip)) {
            $address['postalCode'] = $storeAddress->zip;
        }

        if (!empty($storeAddress->country_name)) {
            $address['addressCountry'] = $storeAddress->country_name;
        } elseif (!empty($storeAddress->country_code)) {
            $address['addressCountry'] = $storeAddress->country_code;
        }

        return \count($address) > 1 ? $address : null;
    }

    private function getStoreAddress(): ?object
    {
        static $address = null;
        static $fetched = false;

        if ($fetched) {
            return $address;
        }

        $fetched = true;
        $params  = ComponentHelper::getParams('com_j2commerce');

        $address1 = $params->get('store_address_1', '');
        $city     = $params->get('store_city', '');

        if (empty($address1) && empty($city)) {
            return null;
        }

        $address = (object) [
            'address_1'    => $address1,
            'address_2'    => $params->get('store_address_2', ''),
            'city'         => $city,
            'zip'          => $params->get('store_zip', ''),
            'zone_name'    => null,
            'country_name' => null,
            'country_code' => null,
        ];

        $countryId = (int) $params->get('country_id', 0);
        $zoneId    = (int) $params->get('zone_id', 0);

        try {
            if ($countryId > 0) {
                $query = $this->db->getQuery(true)
                    ->select('country_name, country_isocode_2')
                    ->from($this->db->quoteName('#__j2commerce_countries'))
                    ->where($this->db->quoteName('j2commerce_country_id') . ' = :countryId')
                    ->bind(':countryId', $countryId, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $country = $this->db->loadObject();

                if ($country) {
                    $address->country_name = $country->country_name;
                    $address->country_code = $country->country_isocode_2;
                }
            }

            if ($zoneId > 0) {
                $query = $this->db->getQuery(true)
                    ->select('zone_name')
                    ->from($this->db->quoteName('#__j2commerce_zones'))
                    ->where($this->db->quoteName('j2commerce_zone_id') . ' = :zoneId')
                    ->bind(':zoneId', $zoneId, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $address->zone_name = $this->db->loadResult();
            }
        } catch (\Exception $e) {
            // Country/zone lookups are optional
        }

        return $address;
    }

    private function getSocialProfiles(): array
    {
        $profiles = [];

        $socialFields = [
            'social_facebook',
            'social_twitter',
            'social_instagram',
            'social_linkedin',
            'social_youtube',
            'social_pinterest',
            'social_tiktok',
        ];

        foreach ($socialFields as $field) {
            $url = $this->params->get($field, '');

            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $profiles[] = $url;
            }
        }

        return $profiles;
    }

    private function getOpeningHours(): ?array
    {
        $openingHoursJson = $this->params->get('opening_hours', '');

        if (empty($openingHoursJson)) {
            return null;
        }

        $openingHours = \is_string($openingHoursJson)
            ? json_decode($openingHoursJson, true)
            : (array) $openingHoursJson;

        if (empty($openingHours)) {
            return null;
        }

        $specs = [];

        foreach ($openingHours as $entry) {
            if (!isset($entry['dayOfWeek'], $entry['opens'], $entry['closes'])) {
                continue;
            }

            $spec = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $entry['dayOfWeek'],
                'opens'     => $entry['opens'],
                'closes'    => $entry['closes'],
            ];

            if (!empty($entry['validFrom'])) {
                $spec['validFrom'] = $entry['validFrom'];
            }

            if (!empty($entry['validThrough'])) {
                $spec['validThrough'] = $entry['validThrough'];
            }

            $specs[] = $spec;
        }

        return !empty($specs) ? $specs : null;
    }

    private function getGeoCoordinates(): ?array
    {
        $latitude  = $this->params->get('geo_latitude', '');
        $longitude = $this->params->get('geo_longitude', '');

        if (empty($latitude) || empty($longitude)) {
            return null;
        }

        return [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }

    private function getPaymentAccepted(): ?string
    {
        $paymentMethods = $this->params->get('payment_accepted', '');

        if (!empty($paymentMethods)) {
            return $paymentMethods;
        }

        if (!ComponentHelper::isEnabled('com_j2commerce')) {
            return null;
        }

        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('element'))
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('j2commerce'))
                ->where($this->db->quoteName('enabled') . ' = 1')
                ->where($this->db->quoteName('element') . ' LIKE ' . $this->db->quote('payment_%'));

            $this->db->setQuery($query);
            $plugins = $this->db->loadColumn();

            if (empty($plugins)) {
                return null;
            }

            $lang    = \Joomla\CMS\Factory::getApplication()->getLanguage();
            $methods = [];

            foreach ($plugins as $plugin) {
                $lang->load('plg_j2commerce_' . $plugin, JPATH_PLUGINS . '/j2commerce/' . $plugin);

                $methodName = Text::_('PLG_J2COMMERCE_PAYMENT_METHOD');

                $methods[] = $methodName !== 'PLG_J2COMMERCE_PAYMENT_METHOD'
                    ? $methodName
                    : 'Credit Card';
            }

            return !empty($methods) ? implode(', ', array_unique($methods)) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function addParamOverrides(array &$schema): void
    {
        $overrides = [
            'organization_legal_name'    => 'legalName',
            'organization_tax_id'        => 'taxID',
            'organization_duns'          => 'duns',
            'organization_founding_date' => 'foundingDate',
        ];

        foreach ($overrides as $paramKey => $schemaKey) {
            $value = $this->params->get($paramKey, '');

            if (!empty($value)) {
                $schema[$schemaKey] = $value;
            }
        }

        $employees = $this->params->get('organization_employees', '');

        if (!empty($employees)) {
            $schema['numberOfEmployees'] = [
                '@type' => 'QuantitativeValue',
                'value' => (int) $employees,
            ];
        }
    }

    private function cleanSchemaData(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (\is_array($value)) {
                $value = $this->cleanSchemaData($value);

                if (empty($value)) {
                    continue;
                }
            }

            $cleaned[$key] = $value;
        }

        return $cleaned;
    }
}
