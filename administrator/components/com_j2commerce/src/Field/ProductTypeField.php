<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * ProductType field - provides a dropdown of product types.
 *
 * Core product types are defined with language strings.
 * Plugins can extend the list via the onJ2CommerceGetProductTypes event.
 *
 * @since  6.0.7
 */
class ProductTypeField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'ProductType';

    /**
     * Cached product types array
     *
     * @var    array|null
     * @since  6.0.7
     */
    protected static ?array $cachedTypes = null;

    /**
     * Get the core product types with their language strings.
     *
     * This method returns an array of core product types. Plugins can extend
     * this list by listening to the onJ2CommerceGetProductTypes event.
     *
     * @return  array  Associative array of product_type_key => translated_label
     *
     * @since   6.0.7
     */
    public static function getProductTypes(): array
    {
        // Define core product types with language string keys
        $types = [
            'simple'       => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_SIMPLE'),
            'variable'     => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_VARIABLE'),
            'configurable' => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_CONFIGURABLE'),
            'downloadable' => Text::_('COM_J2COMMERCE_PRODUCT_TYPE_DOWNLOADABLE'),
        ];

        // Allow plugins to add product types via the onJ2CommerceGetProductTypes event
        // Plugins should add to the $types array by reference:
        // $types['mytype'] = Text::_('PLG_J2COMMERCE_MYAPP_PRODUCT_TYPE_MYTYPE');
        J2CommerceHelper::plugin()->event('GetProductTypes', [&$types]);

        return $types;
    }

    /**
     * Method to get the field options.
     *
     * Returns options from both core product types and database-stored types
     * (for backward compatibility with types that may not be in the core list).
     *
     * @return  array  Array of HTMLHelper options
     *
     * @since   6.0.7
     */
    public function getOptions(): array
    {
        $options = parent::getOptions();

        // Use cached types if available
        if (self::$cachedTypes !== null) {
            foreach (self::$cachedTypes as $value => $label) {
                $options[] = HTMLHelper::_('select.option', $value, $label);
            }
            return $options;
        }

        // Get product types from getProductTypes() method (includes plugin-added types)
        $productTypes = self::getProductTypes();

        // Sort alphabetically by label
        asort($productTypes);

        // Cache the results
        self::$cachedTypes = $productTypes;

        // Build options array
        foreach ($productTypes as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, $label);
        }

        return $options;
    }

    /**
     * Clear the cached product types.
     *
     * Call this method when product types have been modified
     * (e.g., after plugin installation or type changes).
     *
     * @return  void
     *
     * @since   6.0.7
     */
    public static function clearCache(): void
    {
        self::$cachedTypes = null;
    }
}
