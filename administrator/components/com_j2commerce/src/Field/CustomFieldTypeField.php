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

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * CustomFieldType field — provides a dropdown of custom field types.
 *
 * Core types are defined here. Plugins can extend the list via onJ2CommerceGetCustomFieldTypes.
 *
 * @since  6.1.5
 */
class CustomFieldTypeField extends ListField
{
    protected $type = 'CustomFieldType';

    protected static ?array $cachedTypes = null;

    /**
     * Get all available field types (core + plugin-registered).
     *
     * @return  array  Associative array of type_key => translated_label
     *
     * @since   6.1.5
     */
    public static function getFieldTypes(): array
    {
        $types = [
            'text'           => Text::_('COM_J2COMMERCE_FIELDTYPE_TEXT'),
            'email'          => Text::_('COM_J2COMMERCE_FIELDTYPE_EMAIL'),
            'textarea'       => Text::_('COM_J2COMMERCE_FIELDTYPE_TEXTAREA'),
            'wysiwyg'        => Text::_('COM_J2COMMERCE_FIELDTYPE_WYSIWYG'),
            'radio'          => Text::_('COM_J2COMMERCE_FIELDTYPE_RADIO'),
            'checkbox'       => Text::_('COM_J2COMMERCE_FIELDTYPE_CHECKBOX'),
            'singledropdown' => Text::_('COM_J2COMMERCE_FIELDTYPE_SINGLEDROPDOWN'),
            'zone'           => Text::_('COM_J2COMMERCE_FIELDTYPE_ZONE'),
            'date'           => Text::_('COM_J2COMMERCE_FIELDTYPE_DATE'),
            'time'           => Text::_('COM_J2COMMERCE_FIELDTYPE_TIME'),
            'datetime'       => Text::_('COM_J2COMMERCE_FIELDTYPE_DATETIME'),
            'customtext'     => Text::_('COM_J2COMMERCE_FIELDTYPE_CUSTOMTEXT'),
            'telephone'      => Text::_('COM_J2COMMERCE_FIELDTYPE_TELEPHONE'),
            'multiuploader'  => Text::_('COM_J2COMMERCE_FIELDTYPE_MULTIUPLOADER'),
        ];

        // Allow plugins to register additional field types
        J2CommerceHelper::plugin()->event('GetCustomFieldTypes', [&$types]);

        return $types;
    }

    public function getOptions(): array
    {
        $options = parent::getOptions();

        if (self::$cachedTypes !== null) {
            foreach (self::$cachedTypes as $value => $label) {
                $options[] = HTMLHelper::_('select.option', $value, $label);
            }

            return $options;
        }

        $fieldTypes = self::getFieldTypes();
        asort($fieldTypes);
        self::$cachedTypes = $fieldTypes;

        foreach ($fieldTypes as $value => $label) {
            $options[] = HTMLHelper::_('select.option', $value, $label);
        }

        return $options;
    }

    public static function clearCache(): void
    {
        self::$cachedTypes = null;
    }
}
