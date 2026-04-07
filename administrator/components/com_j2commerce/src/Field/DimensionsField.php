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

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Dimensions field - displays Length, Width, Height inputs in a single row.
 *
 * This field renders three dimension inputs (length, width, height) combined
 * into a single Bootstrap input-group, reducing visual clutter in forms.
 *
 * Field XML attributes:
 * - length_name: Field name for length (default: "length")
 * - width_name: Field name for width (default: "width")
 * - height_name: Field name for height (default: "height")
 *
 * The field names will be combined with the form's field prefix automatically.
 * For example, if the prefix is "jform[attribs][j2commerce][variable][123]",
 * the fields become:
 *   - jform[attribs][j2commerce][variable][123][length]
 *   - jform[attribs][j2commerce][variable][123][width]
 *   - jform[attribs][j2commerce][variable][123][height]
 *
 * @since  6.0.0
 */
class DimensionsField extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.0
     */
    protected $type = 'Dimensions';

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.0
     */
    protected function getInput(): string
    {
        // Get field name attributes (defaults to standard dimension names)
        $lengthFieldName = (string) ($this->element['length_name'] ?? 'length');
        $widthFieldName  = (string) ($this->element['width_name'] ?? 'width');
        $heightFieldName = (string) ($this->element['height_name'] ?? 'height');

        // Build full field names using the parent form's naming pattern
        // The $this->name is something like "jform[attribs][j2commerce][variable][123][dimensions]"
        // We need to replace "dimensions" with the actual field names
        $baseName = $this->name;

        // If the name ends with [fieldname], replace it; otherwise append
        if (preg_match('/^(.+)\[[^\]]+\]$/', $baseName, $matches)) {
            // Replace last bracket group with our field names
            $prefix     = $matches[1];
            $lengthName = $prefix . '[' . $lengthFieldName . ']';
            $widthName  = $prefix . '[' . $widthFieldName . ']';
            $heightName = $prefix . '[' . $heightFieldName . ']';
        } else {
            // Simple field name - append field names
            $lengthName = $lengthFieldName;
            $widthName  = $widthFieldName;
            $heightName = $heightFieldName;
        }

        // Get values from the form's bound data
        // The form is bound to variant data which has length, width, height as separate properties
        $lengthValue = '';
        $widthValue  = '';
        $heightValue = '';

        if (\is_array($this->value)) {
            // Value is an array
            $lengthValue = $this->value['length'] ?? '';
            $widthValue  = $this->value['width'] ?? '';
            $heightValue = $this->value['height'] ?? '';
        } elseif ($this->form) {
            // Access the raw bound data from the form's Registry
            $formData = $this->form->getData();

            if ($formData instanceof \Joomla\Registry\Registry) {
                // Registry access - use get() method
                $lengthValue = $formData->get($lengthFieldName, '');
                $widthValue  = $formData->get($widthFieldName, '');
                $heightValue = $formData->get($heightFieldName, '');
            } else {
                // Fallback for array or object data
                $data        = (array) $formData;
                $lengthValue = $data[$lengthFieldName] ?? '';
                $widthValue  = $data[$widthFieldName] ?? '';
                $heightValue = $data[$heightFieldName] ?? '';
            }
        }

        // Get labels
        $lengthLabel = Text::_('COM_J2COMMERCE_PRODUCT_LENGTH');
        $widthLabel  = Text::_('COM_J2COMMERCE_PRODUCT_WIDTH');
        $heightLabel = Text::_('COM_J2COMMERCE_PRODUCT_HEIGHT');

        // Build unique IDs
        $baseId   = htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8');
        $lengthId = $baseId . '_length';
        $widthId  = $baseId . '_width';
        $heightId = $baseId . '_height';

        // Common input attributes
        $readonly   = $this->readonly ? ' readonly' : '';
        $disabled   = $this->disabled ? ' disabled' : '';
        $inputClass = 'form-control';

        // Build the combined input-group HTML
        $html = '<div class="input-group">';

        // Length
        $html .= '<span class="input-group-text fs-6 px-2"><small>' . htmlspecialchars($lengthLabel, ENT_COMPAT, 'UTF-8') . '</small></span>';
        $html .= '<input type="text"'
            . ' name="' . htmlspecialchars($lengthName, ENT_COMPAT, 'UTF-8') . '"'
            . ' id="' . $lengthId . '"'
            . ' value="' . htmlspecialchars((string) $lengthValue, ENT_COMPAT, 'UTF-8') . '"'
            . ' class="' . $inputClass . ' fs-6 px-2"'
            . ' inputmode="decimal"'
            . $readonly . $disabled
            . ' aria-label="' . htmlspecialchars($lengthLabel, ENT_COMPAT, 'UTF-8') . '"'
            . '>';

        // Width
        $html .= '<span class="input-group-text fs-6 px-2"><small>' . htmlspecialchars($widthLabel, ENT_COMPAT, 'UTF-8') . '</small></span>';
        $html .= '<input type="text"'
            . ' name="' . htmlspecialchars($widthName, ENT_COMPAT, 'UTF-8') . '"'
            . ' id="' . $widthId . '"'
            . ' value="' . htmlspecialchars((string) $widthValue, ENT_COMPAT, 'UTF-8') . '"'
            . ' class="' . $inputClass . ' fs-6 px-2"'
            . ' inputmode="decimal"'
            . $readonly . $disabled
            . ' aria-label="' . htmlspecialchars($widthLabel, ENT_COMPAT, 'UTF-8') . '"'
            . '>';

        // Height
        $html .= '<span class="input-group-text fs-6 px-2"><small>' . htmlspecialchars($heightLabel, ENT_COMPAT, 'UTF-8') . '</small></span>';
        $html .= '<input type="text"'
            . ' name="' . htmlspecialchars($heightName, ENT_COMPAT, 'UTF-8') . '"'
            . ' id="' . $heightId . '"'
            . ' value="' . htmlspecialchars((string) $heightValue, ENT_COMPAT, 'UTF-8') . '"'
            . ' class="' . $inputClass . ' fs-6 px-2"'
            . ' inputmode="decimal"'
            . $readonly . $disabled
            . ' aria-label="' . htmlspecialchars($heightLabel, ENT_COMPAT, 'UTF-8') . '"'
            . '>';

        $html .= '</div>';

        return $html;
    }
}
