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
use Joomla\CMS\Language\Text;

// No direct access
\defined('_JEXEC') or die;

/**
 * Message Helper class for J2Commerce
 *
 * Provides email template tag definitions for order-related emails.
 * These tags are used in email templates and are replaced with
 * actual order data when emails are sent.
 *
 * Tag categories:
 * - Billing: Customer billing address information
 * - Shipping: Shipping address and delivery information
 * - Additional: Order details, site info, and miscellaneous tags
 *
 * @since  6.0.0
 */
class MessageHelper
{
    protected static $instance = null;

    /**
     * Get singleton instance of MessageHelper
     *
     *
     * @param array $config Optional configuration array (maintained for compatibility)
     *
     * @return MessageHelper The MessageHelper instance
     * @since 6.0.0
     */
    public static function getInstance(array $config = [])
    {
        if (static::$instance === null) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * Get all available message tags organized by category
     *
     * Returns an array of all email template tags grouped into
     * three categories: billing, shipping, and additional.
     * Each tag is paired with its human-readable description.
     *
     * @return  array<string, array<string, string>>  Array of tag categories with tag => description pairs
     *
     * @since   6.0.0
     */
    public static function getMessageTags(): array
    {
        return [
            'billing'    => self::getBillingTags(),
            'shipping'   => self::getShippingTags(),
            'additional' => self::getAdditionalTags(),
        ];
    }

    /**
     * Get additional/miscellaneous email template tags
     *
     * Returns tags for site information, order details, and other
     * miscellaneous data used in email templates.
     *
     * Available tags:
     * - [SITENAME]: Website name from Joomla configuration
     * - [SITEURL]: Website URL
     * - [INVOICE_URL]: Link to customer invoice/order page
     * - [CUSTOMER_NOTE]: Customer notes from checkout
     * - [PAYMENT_TYPE]: Payment method used
     * - [SHIPPING_TYPE]: Shipping method used
     * - [ORDERID]: Order ID number
     * - [INVOICENO]: Invoice number
     * - [ORDERDATE]: Order creation date
     * - [ORDERSTATUS]: Current order status
     * - [ORDERAMOUNT]: Order total amount (formatted)
     * - [ORDER_TOKEN]: Unique order token for verification
     * - [COUPON_CODE]: Applied coupon code(s)
     * - [ITEMS]: Order items table (HTML)
     *
     * Triggers the 'onJ2CommerceAfterAdditionalTags' event to allow
     * plugins to add custom tags.
     *
     * @return  array<string, string>  Array of tag => description pairs
     *
     * @since   6.0.0
     */
    public static function getAdditionalTags(): array
    {
        $result = [
            '[SITENAME]'        => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SITENAME'),
            '[SITEURL]'         => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SITEURL'),
            '[INVOICE_URL]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_INVOICE_URL'),
            '[CUSTOMER_NOTE]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_CUSTOMER_NOTE'),
            '[PAYMENT_TYPE]'    => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_PAYMENT_TYPE'),
            '[SHIPPING_TYPE]'   => Text::_('COM_J2COMMERCE_SHIPM_SHIPPING_TYPE'),
            '[ORDERID]'         => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERID'),
            '[INVOICENO]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_INVOICEID'),
            '[ORDERDATE]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERDATE'),
            '[ORDERSTATUS]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERSTATUS'),
            '[ORDERAMOUNT]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERAMOUNT'),
            '[ORDER_TOKEN]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDER_TOKEN'),
            '[COUPON_CODE]'     => Text::_('COM_J2COMMERCE_COUPON_CODE'),
            '[SUBTOTAL]'        => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SUBTOTAL'),
            '[TAX_AMOUNT]'      => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_AMOUNT'),
            '[SHIPPING_AMOUNT]' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_AMOUNT'),
            '[DISCOUNT_AMOUNT]' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_DISCOUNT_AMOUNT'),
            '[CURRENT_YEAR]'    => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_CURRENT_YEAR'),
            '[TAX_LINES]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_LINES'),
            '[ITEMS]'           => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_ITEMS'),
            '[PACKING_ITEMS]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_PACKING_ITEMS'),
        ];

        // Dispatch plugin event to allow adding custom tags
        try {
            $app        = Factory::getApplication();
            $dispatcher = $app->getDispatcher();
            $event      = new \Joomla\CMS\Event\GenericEvent('onJ2CommerceAfterAdditionalTags', ['result' => &$result]);
            $dispatcher->dispatch('onJ2CommerceAfterAdditionalTags', $event);
        } catch (\Exception $e) {
            // Silently continue if event dispatch fails
        }

        return $result;
    }

    /**
     * Get shipping address email template tags
     *
     * Returns tags for shipping address information used in email templates.
     *
     * Available tags:
     * - [SHIPPING_FIRSTNAME]: Recipient first name
     * - [SHIPPING_LASTNAME]: Recipient last name
     * - [SHIPPING_ADDRESS_1]: Address line 1
     * - [SHIPPING_ADDRESS_2]: Address line 2
     * - [SHIPPING_CITY]: City
     * - [SHIPPING_ZIP]: Postal/ZIP code
     * - [SHIPPING_COUNTRY]: Country name
     * - [SHIPPING_STATE]: State/Province/Zone name
     * - [SHIPPING_PHONE]: Phone number
     * - [SHIPPING_MOBILE]: Mobile phone number
     * - [SHIPPING_COMPANY]: Company name
     * - [SHIPPING_VATID]: VAT/Tax ID number
     * - [SHIPPING_TRACKING_ID]: Shipment tracking number
     *
     * @return  array<string, string>  Array of tag => description pairs
     *
     * @since   6.0.0
     */
    public static function getShippingTags(): array
    {
        return [
            '[SHIPPING_FIRSTNAME]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_FIRSTNAME'),
            '[SHIPPING_LASTNAME]'    => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_LASTNAME'),
            '[SHIPPING_ADDRESS_1]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS_1'),
            '[SHIPPING_ADDRESS_2]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS_2'),
            '[SHIPPING_CITY]'        => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_CITY'),
            '[SHIPPING_ZIP]'         => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ZIP'),
            '[SHIPPING_COUNTRY]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_COUNTRY'),
            '[SHIPPING_STATE]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_STATE'),
            '[SHIPPING_PHONE]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_PHONE'),
            '[SHIPPING_MOBILE]'      => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_MOBILE'),
            '[SHIPPING_COMPANY]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_COMPANY'),
            '[SHIPPING_VATID]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_VATID'),
            '[SHIPPING_TRACKING_ID]' => Text::_('COM_J2COMMERCE_SHIPPING_TRACKING_ID'),
        ];
    }

    /**
     * Get billing address email template tags
     *
     * Returns tags for billing address and customer information
     * used in email templates.
     *
     * Available tags:
     * - [CUSTOMER_NAME]: Full customer name (first + last)
     * - [BILLING_FIRSTNAME]: Customer first name
     * - [BILLING_LASTNAME]: Customer last name
     * - [BILLING_EMAIL]: Customer email address
     * - [BILLING_ADDRESS_1]: Address line 1
     * - [BILLING_ADDRESS_2]: Address line 2
     * - [BILLING_CITY]: City
     * - [BILLING_ZIP]: Postal/ZIP code
     * - [BILLING_COUNTRY]: Country name
     * - [BILLING_STATE]: State/Province/Zone name
     * - [BILLING_PHONE]: Phone number
     * - [BILLING_MOBILE]: Mobile phone number
     * - [BILLING_COMPANY]: Company name
     * - [BILLING_VATID]: VAT/Tax ID number
     *
     * @return  array<string, string>  Array of tag => description pairs
     *
     * @since   6.0.0
     */
    public static function getBillingTags(): array
    {
        return [
            '[CUSTOMER_NAME]'     => Text::_('COM_J2COMMERCE_CUSTOMER_NAME'),
            '[BILLING_FIRSTNAME]' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_FIRSTNAME'),
            '[BILLING_LASTNAME]'  => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_LASTNAME'),
            '[BILLING_EMAIL]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_EMAIL'),
            '[BILLING_ADDRESS_1]' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS_1'),
            '[BILLING_ADDRESS_2]' => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS_2'),
            '[BILLING_CITY]'      => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_CITY'),
            '[BILLING_ZIP]'       => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ZIP'),
            '[BILLING_COUNTRY]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_COUNTRY'),
            '[BILLING_STATE]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_STATE'),
            '[BILLING_PHONE]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_PHONE'),
            '[BILLING_MOBILE]'    => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_MOBILE'),
            '[BILLING_COMPANY]'   => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_COMPANY'),
            '[BILLING_VATID]'     => Text::_('COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_VATID'),
        ];
    }

    /**
     * Get all available tags as a flat array
     *
     * Returns all tags from all categories merged into a single array.
     * Useful for validation or when all tags are needed without categorization.
     *
     * @return  array<string, string>  Array of all tag => description pairs
     *
     * @since   6.0.0
     */
    public static function getAllTags(): array
    {
        return array_merge(
            self::getBillingTags(),
            self::getShippingTags(),
            self::getAdditionalTags()
        );
    }

    /**
     * Get tag names only (without descriptions)
     *
     * Returns just the tag names (keys) from all categories.
     * Useful for pattern matching or validation purposes.
     *
     * @return  array<int, string>  Array of tag names
     *
     * @since   6.0.0
     */
    public static function getTagNames(): array
    {
        return array_keys(self::getAllTags());
    }

    /**
     * Check if a tag exists
     *
     * @param   string  $tag  The tag to check (with or without brackets)
     *
     * @return  bool  True if the tag exists
     *
     * @since   6.0.0
     */
    public static function tagExists(string $tag): bool
    {
        // Normalize tag to include brackets
        if (!str_starts_with($tag, '[')) {
            $tag = '[' . $tag;
        }
        if (!str_ends_with($tag, ']')) {
            $tag = $tag . ']';
        }

        return \array_key_exists($tag, self::getAllTags());
    }

    /**
     * Get the description for a specific tag
     *
     * @param   string  $tag  The tag to get description for
     *
     * @return  string|null  The tag description or null if not found
     *
     * @since   6.0.0
     */
    public static function getTagDescription(string $tag): ?string
    {
        // Normalize tag to include brackets
        if (!str_starts_with($tag, '[')) {
            $tag = '[' . $tag;
        }
        if (!str_ends_with($tag, ']')) {
            $tag = $tag . ']';
        }

        $allTags = self::getAllTags();

        return $allTags[$tag] ?? null;
    }
}
