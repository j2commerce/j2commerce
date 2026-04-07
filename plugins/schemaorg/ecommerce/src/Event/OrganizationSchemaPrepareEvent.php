<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Schemaorg.ecommerce
 *
 * @copyright   (C) 2024-2026 J2Commerce, LLC All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Schemaorg\Ecommerce\Event;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Event triggered when preparing Organization schema data.
 *
 * This event allows plugins to modify the organization/store schema,
 * add additional contact points, modify address information, or add
 * custom properties.
 *
 * Event name: onJ2CommerceSchemaOrganizationPrepare
 *
 * Example usage in a plugin:
 * ```php
 * public function onOrganizationPrepare(OrganizationSchemaPrepareEvent $event): void
 * {
 *     // Add additional social profile
 *     $event->addSocialProfile('https://facebook.com/mystore');
 *
 *     // Add a department
 *     $event->addDepartment([
 *         '@type' => 'Organization',
 *         'name'  => 'Customer Support',
 *         'email' => 'support@example.com'
 *     ]);
 *
 *     // Set founding date
 *     $schema = $event->getSchema();
 *     $schema['foundingDate'] = '2010-01-15';
 *     $event->setSchema($schema);
 * }
 * ```
 *
 * @since  6.0.0
 */
class OrganizationSchemaPrepareEvent extends AbstractSchemaEvent
{
    /**
     * Setter for the subject argument (organization schema data).
     *
     * @param   array  $value  The value to set
     *
     * @return  array
     *
     * @since   6.0.0
     */
    protected function onSetSubject(array $value): array
    {
        return $value;
    }

    /**
     * Get the organization type (Organization, LocalBusiness, etc.).
     *
     * @return  string  The schema type
     *
     * @since   6.0.0
     */
    public function getOrganizationType(): string
    {
        return $this->getSchemaProperty('@type', 'Organization');
    }

    /**
     * Set the organization type.
     *
     * @param   string  $type  The schema type (e.g., 'LocalBusiness', 'Store')
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setOrganizationType(string $type): void
    {
        $this->setSchemaProperty('@type', $type);
    }

    /**
     * Check if this is a LocalBusiness schema.
     *
     * @return  bool  True if LocalBusiness type
     *
     * @since   6.0.0
     */
    public function isLocalBusiness(): bool
    {
        $type = $this->getOrganizationType();

        // LocalBusiness and its subtypes
        $localBusinessTypes = [
            'LocalBusiness',
            'Store',
            'AutoPartsStore',
            'BikeStore',
            'BookStore',
            'ClothingStore',
            'ComputerStore',
            'ConvenienceStore',
            'DepartmentStore',
            'ElectronicsStore',
            'Florist',
            'FurnitureStore',
            'GardenStore',
            'GroceryStore',
            'HardwareStore',
            'HobbyShop',
            'HomeGoodsStore',
            'JewelryStore',
            'LiquorStore',
            'MensClothingStore',
            'MobilePhoneStore',
            'MovieRentalStore',
            'MusicStore',
            'OfficeEquipmentStore',
            'OutletStore',
            'PawnShop',
            'PetStore',
            'ShoeStore',
            'SportingGoodsStore',
            'TireShop',
            'ToyStore',
            'WholesaleStore',
        ];

        return \in_array($type, $localBusinessTypes, true);
    }

    /**
     * Get the organization name.
     *
     * @return  string  The name
     *
     * @since   6.0.0
     */
    public function getName(): string
    {
        return $this->getSchemaProperty('name', '');
    }

    /**
     * Set the organization name.
     *
     * @param   string  $name  The name
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setName(string $name): void
    {
        $this->setSchemaProperty('name', $name);
    }

    /**
     * Set the organization logo.
     *
     * @param   string|array  $logo  Logo URL or ImageObject schema
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setLogo($logo): void
    {
        $this->setSchemaProperty('logo', $logo);
    }

    /**
     * Get the logo.
     *
     * @return  string|array|null  The logo
     *
     * @since   6.0.0
     */
    public function getLogo()
    {
        return $this->getSchemaProperty('logo', null);
    }

    /**
     * Set the organization URL.
     *
     * @param   string  $url  The URL
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setUrl(string $url): void
    {
        $this->setSchemaProperty('url', $url);
    }

    /**
     * Set the description.
     *
     * @param   string  $description  The description
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setDescription(string $description): void
    {
        $this->setSchemaProperty('description', $description);
    }

    /**
     * Get contact points.
     *
     * @return  array  Array of ContactPoint schemas
     *
     * @since   6.0.0
     */
    public function getContactPoints(): array
    {
        $contactPoint = $this->getSchemaProperty('contactPoint', []);

        // Normalize to array of contact points
        if (!empty($contactPoint) && isset($contactPoint['@type'])) {
            return [$contactPoint];
        }

        return $contactPoint;
    }

    /**
     * Set contact points.
     *
     * @param   array  $contactPoints  Array of ContactPoint schemas
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setContactPoints(array $contactPoints): void
    {
        if (\count($contactPoints) === 1) {
            $this->setSchemaProperty('contactPoint', $contactPoints[0]);
        } else {
            $this->setSchemaProperty('contactPoint', $contactPoints);
        }
    }

    /**
     * Add a contact point.
     *
     * @param   string       $contactType  The contact type (e.g., 'customer service')
     * @param   string|null  $telephone    The phone number
     * @param   string|null  $email        The email address
     * @param   array        $extra        Additional properties
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addContactPoint(
        string $contactType,
        ?string $telephone = null,
        ?string $email = null,
        array $extra = []
    ): void {
        $contactPoints = $this->getContactPoints();

        $newContact = array_merge([
            '@type'       => 'ContactPoint',
            'contactType' => $contactType,
        ], $extra);

        if ($telephone) {
            $newContact['telephone'] = $telephone;
        }

        if ($email) {
            $newContact['email'] = $email;
        }

        $contactPoints[] = $newContact;
        $this->setContactPoints($contactPoints);
    }

    /**
     * Get the address.
     *
     * @return  array|null  The PostalAddress schema
     *
     * @since   6.0.0
     */
    public function getAddress(): ?array
    {
        return $this->getSchemaProperty('address', null);
    }

    /**
     * Set the address.
     *
     * @param   array  $address  The PostalAddress schema
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setAddress(array $address): void
    {
        if (!isset($address['@type'])) {
            $address['@type'] = 'PostalAddress';
        }

        $this->setSchemaProperty('address', $address);
    }

    /**
     * Get social profile URLs (sameAs).
     *
     * @return  array  Array of profile URLs
     *
     * @since   6.0.0
     */
    public function getSocialProfiles(): array
    {
        return $this->getSchemaProperty('sameAs', []);
    }

    /**
     * Set social profile URLs.
     *
     * @param   array  $profiles  Array of profile URLs
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setSocialProfiles(array $profiles): void
    {
        $this->setSchemaProperty('sameAs', array_values(array_unique($profiles)));
    }

    /**
     * Add a social profile URL.
     *
     * @param   string  $url  The profile URL
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addSocialProfile(string $url): void
    {
        $profiles   = $this->getSocialProfiles();
        $profiles[] = $url;
        $this->setSocialProfiles($profiles);
    }

    /**
     * Set the price range (for LocalBusiness).
     *
     * @param   string  $priceRange  The price range (e.g., '$$', '$$$')
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setPriceRange(string $priceRange): void
    {
        $this->setSchemaProperty('priceRange', $priceRange);
    }

    /**
     * Set opening hours specification.
     *
     * @param   array  $hours  Array of OpeningHoursSpecification
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setOpeningHours(array $hours): void
    {
        $this->setSchemaProperty('openingHoursSpecification', $hours);
    }

    /**
     * Add opening hours for specific days.
     *
     * @param   string|array  $dayOfWeek  Day(s) of week
     * @param   string        $opens      Opening time (e.g., '09:00')
     * @param   string        $closes     Closing time (e.g., '17:00')
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addOpeningHours($dayOfWeek, string $opens, string $closes): void
    {
        $hours = $this->getSchemaProperty('openingHoursSpecification', []);

        $hours[] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => $dayOfWeek,
            'opens'     => $opens,
            'closes'    => $closes,
        ];

        $this->setOpeningHours($hours);
    }

    /**
     * Set geo coordinates (for LocalBusiness).
     *
     * @param   float  $latitude   The latitude
     * @param   float  $longitude  The longitude
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setGeoCoordinates(float $latitude, float $longitude): void
    {
        $this->setSchemaProperty('geo', [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Get departments.
     *
     * @return  array  Array of Organization schemas
     *
     * @since   6.0.0
     */
    public function getDepartments(): array
    {
        return $this->getSchemaProperty('department', []);
    }

    /**
     * Add a department.
     *
     * @param   array  $department  The department Organization schema
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function addDepartment(array $department): void
    {
        $departments = $this->getDepartments();

        if (!isset($department['@type'])) {
            $department['@type'] = 'Organization';
        }

        $departments[] = $department;
        $this->setSchemaProperty('department', $departments);
    }

    /**
     * Set the founding date.
     *
     * @param   string  $date  The founding date (ISO 8601 format)
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setFoundingDate(string $date): void
    {
        $this->setSchemaProperty('foundingDate', $date);
    }

    /**
     * Set the number of employees.
     *
     * @param   int  $count  The number of employees
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setNumberOfEmployees(int $count): void
    {
        $this->setSchemaProperty('numberOfEmployees', [
            '@type' => 'QuantitativeValue',
            'value' => $count,
        ]);
    }

    /**
     * Set the parent organization.
     *
     * @param   array  $parent  The parent Organization schema
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public function setParentOrganization(array $parent): void
    {
        if (!isset($parent['@type'])) {
            $parent['@type'] = 'Organization';
        }

        $this->setSchemaProperty('parentOrganization', $parent);
    }
}
