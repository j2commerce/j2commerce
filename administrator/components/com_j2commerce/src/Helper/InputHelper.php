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

use Joomla\CMS\Language\Text;

// No direct access
\defined('_JEXEC') or die;

/**
 * Input Helper class for J2Commerce
 *
 * Provides static methods for generating HTML form elements.
 * This helper generates accessible, Bootstrap 5 compatible form inputs
 * with proper XSS protection through HTML escaping.
 *
 * @since  6.0.0
 */
class InputHelper
{
    /**
     * Generate an HTML text input element
     *
     * Creates a text, password, email, or other single-line input field
     * with proper escaping to prevent XSS attacks.
     *
     * @param   string  $label        The field label (not used, kept for API compatibility)
     * @param   string  $name         The input name attribute
     * @param   string  $value        The input value (will be HTML escaped)
     * @param   string  $type         The input type (text, password, email, number, etc.)
     * @param   string  $placeholder  The placeholder text
     * @param   array   $options      Additional options (class, required, id, disabled, readonly, etc.)
     *
     * @return  string  The HTML input element
     *
     * @since   6.0.0
     */
    public static function getText(
        string $label,
        string $name,
        string $value,
        string $type,
        string $placeholder,
        array $options = []
    ): string {
        $class        = htmlspecialchars($options['class'] ?? '', ENT_QUOTES, 'UTF-8');
        $required     = !empty($options['required']) ? ' required' : '';
        $id           = !empty($options['id']) ? ' id="' . htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $disabled     = !empty($options['disabled']) ? ' disabled' : '';
        $readonly     = !empty($options['readonly']) ? ' readonly' : '';
        $maxlength    = isset($options['maxlength']) ? ' maxlength="' . (int) $options['maxlength'] . '"' : '';
        $min          = isset($options['min']) ? ' min="' . htmlspecialchars((string) $options['min'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $max          = isset($options['max']) ? ' max="' . htmlspecialchars((string) $options['max'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $step         = isset($options['step']) ? ' step="' . htmlspecialchars((string) $options['step'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $autocomplete = isset($options['autocomplete']) ? ' autocomplete="' . htmlspecialchars($options['autocomplete'], ENT_QUOTES, 'UTF-8') . '"' : '';

        // Build additional attributes string
        $additionalAttrs = '';

        if (!empty($options['data']) && \is_array($options['data'])) {
            foreach ($options['data'] as $dataKey => $dataValue) {
                $additionalAttrs .= ' data-' . htmlspecialchars($dataKey, ENT_QUOTES, 'UTF-8')
                    . '="' . htmlspecialchars((string) $dataValue, ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<input type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
            . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . $class . '"'
            . ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
            . $id . $maxlength . $min . $max . $step . $autocomplete
            . $required . $disabled . $readonly . $additionalAttrs . '>';
    }

    /**
     * Generate an HTML label element
     *
     * Creates an accessible label element with proper escaping.
     * The name parameter is used as a language string key.
     *
     * @param   string  $name     The language string key for the label text
     * @param   array   $options  Additional options (for, class, id)
     *
     * @return  string  The HTML label element
     *
     * @since   6.0.0
     */
    public static function getLabel(string $name, array $options = []): string
    {
        $text  = Text::_($name);
        $class = htmlspecialchars($options['class'] ?? 'form-label', ENT_QUOTES, 'UTF-8');
        $for   = !empty($options['for']) ? ' for="' . htmlspecialchars($options['for'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $id    = !empty($options['id']) ? ' id="' . htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8') . '"' : '';

        return '<label class="' . $class . '"' . $for . $id . '>'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</label>';
    }

    /**
     * Generate an HTML textarea element
     *
     * Creates a multi-line text input with proper escaping.
     *
     * @param   string  $label    The field label (not used, kept for API compatibility)
     * @param   string  $name     The textarea name attribute
     * @param   string  $value    The textarea content (will be HTML escaped)
     * @param   string  $type     The type (not used for textarea, kept for API compatibility)
     * @param   array   $options  Additional options (class, required, id, rows, cols, disabled, readonly)
     *
     * @return  string  The HTML textarea element
     *
     * @since   6.0.0
     */
    public static function getTextarea(
        string $label,
        string $name,
        string $value,
        string $type,
        array $options = []
    ): string {
        $class       = htmlspecialchars($options['class'] ?? '', ENT_QUOTES, 'UTF-8');
        $required    = !empty($options['required']) ? ' required' : '';
        $id          = !empty($options['id']) ? ' id="' . htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $disabled    = !empty($options['disabled']) ? ' disabled' : '';
        $readonly    = !empty($options['readonly']) ? ' readonly' : '';
        $rows        = isset($options['rows']) ? ' rows="' . (int) $options['rows'] . '"' : '';
        $cols        = isset($options['cols']) ? ' cols="' . (int) $options['cols'] . '"' : '';
        $maxlength   = isset($options['maxlength']) ? ' maxlength="' . (int) $options['maxlength'] . '"' : '';
        $placeholder = !empty($options['placeholder'])
            ? ' placeholder="' . htmlspecialchars($options['placeholder'], ENT_QUOTES, 'UTF-8') . '"'
            : '';

        return '<div class="controls"><textarea'
            . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . $class . '"'
            . $id . $rows . $cols . $maxlength . $placeholder
            . $required . $disabled . $readonly . '>'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            . '</textarea></div>';
    }

    /**
     * Generate a Bootstrap form control group with label and input
     *
     * Creates a complete form group with label and text input,
     * following Bootstrap 5 form structure patterns.
     *
     * @param   string  $label        The language string key for the label
     * @param   string  $name         The input name attribute
     * @param   string  $value        The input value (will be HTML escaped)
     * @param   string  $type         The input type (text, password, email, etc.)
     * @param   string  $placeholder  The placeholder text
     * @param   array   $options      Additional options (class, required, id, groupClass, labelClass)
     *
     * @return  string  The HTML form group with label and input
     *
     * @since   6.0.0
     */
    public static function getControlGroup(
        string $label,
        string $name,
        string $value,
        string $type,
        string $placeholder,
        array $options = []
    ): string {
        $groupClass = htmlspecialchars($options['groupClass'] ?? 'mb-3', ENT_QUOTES, 'UTF-8');
        $labelClass = htmlspecialchars($options['labelClass'] ?? 'form-label', ENT_QUOTES, 'UTF-8');
        $inputClass = htmlspecialchars($options['class'] ?? 'form-control', ENT_QUOTES, 'UTF-8');
        $required   = !empty($options['required']) ? ' required' : '';
        $id         = !empty($options['id']) ? $options['id'] : 'input_' . preg_replace('/[^a-z0-9_]/i', '_', $name);

        // Escape values for HTML attributes
        $escapedId          = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $escapedName        = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue       = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $escapedType        = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $escapedPlaceholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');
        $labelText          = htmlspecialchars(Text::_($label), ENT_QUOTES, 'UTF-8');

        return '<div class="' . $groupClass . '">'
            . '<label class="' . $labelClass . '" for="' . $escapedId . '">' . $labelText . '</label>'
            . '<input type="' . $escapedType . '"'
            . ' name="' . $escapedName . '"'
            . ' id="' . $escapedId . '"'
            . ' placeholder="' . $escapedPlaceholder . '"'
            . ' class="' . $inputClass . '"'
            . ' value="' . $escapedValue . '"'
            . $required . '>'
            . '</div>';
    }

    /**
     * Generate a Bootstrap form control group with label and textarea
     *
     * Creates a complete form group with label and textarea,
     * following Bootstrap 5 form structure patterns.
     *
     * @param   string  $label    The language string key for the label
     * @param   string  $name     The textarea name attribute
     * @param   string  $value    The textarea content (will be HTML escaped)
     * @param   array   $options  Additional options (class, required, id, rows, cols, groupClass, labelClass)
     *
     * @return  string  The HTML form group with label and textarea
     *
     * @since   6.0.0
     */
    public static function getTextareaGroup(
        string $label,
        string $name,
        string $value,
        array $options = []
    ): string {
        $groupClass    = htmlspecialchars($options['groupClass'] ?? 'mb-3', ENT_QUOTES, 'UTF-8');
        $labelClass    = htmlspecialchars($options['labelClass'] ?? 'form-label', ENT_QUOTES, 'UTF-8');
        $textareaClass = htmlspecialchars($options['class'] ?? 'form-control', ENT_QUOTES, 'UTF-8');
        $required      = !empty($options['required']) ? ' required' : '';
        $id            = !empty($options['id']) ? $options['id'] : 'textarea_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $rows          = isset($options['rows']) ? ' rows="' . (int) $options['rows'] . '"' : ' rows="3"';
        $cols          = isset($options['cols']) ? ' cols="' . (int) $options['cols'] . '"' : '';
        $placeholder   = !empty($options['placeholder'])
            ? ' placeholder="' . htmlspecialchars($options['placeholder'], ENT_QUOTES, 'UTF-8') . '"'
            : '';

        // Escape values for HTML attributes
        $escapedId    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $escapedName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $labelText    = htmlspecialchars(Text::_($label), ENT_QUOTES, 'UTF-8');

        return '<div class="' . $groupClass . '">'
            . '<label class="' . $labelClass . '" for="' . $escapedId . '">' . $labelText . '</label>'
            . '<textarea'
            . ' name="' . $escapedName . '"'
            . ' id="' . $escapedId . '"'
            . ' class="' . $textareaClass . '"'
            . $rows . $cols . $placeholder . $required . '>'
            . $escapedValue
            . '</textarea>'
            . '</div>';
    }

    /**
     * Generate a Bootstrap select dropdown element
     *
     * Creates a select element with options, following Bootstrap 5 patterns.
     *
     * @param   string  $name      The select name attribute
     * @param   array   $options   Array of options (value => text pairs or objects with value/text properties)
     * @param   mixed   $selected  The selected value(s)
     * @param   array   $attribs   Additional attributes (class, required, id, disabled, multiple)
     *
     * @return  string  The HTML select element
     *
     * @since   6.0.0
     */
    public static function getSelect(
        string $name,
        array $options,
        mixed $selected = null,
        array $attribs = []
    ): string {
        $class    = htmlspecialchars($attribs['class'] ?? 'form-select', ENT_QUOTES, 'UTF-8');
        $required = !empty($attribs['required']) ? ' required' : '';
        $id       = !empty($attribs['id'])
            ? ' id="' . htmlspecialchars($attribs['id'], ENT_QUOTES, 'UTF-8') . '"'
            : '';
        $disabled = !empty($attribs['disabled']) ? ' disabled' : '';
        $multiple = !empty($attribs['multiple']) ? ' multiple' : '';

        // Handle array of selected values for multiple selects
        $selectedValues = [];

        if (\is_array($selected)) {
            $selectedValues = array_map('strval', $selected);
        } elseif ($selected !== null) {
            $selectedValues = [(string) $selected];
        }

        $html = '<select'
            . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . $class . '"'
            . $id . $required . $disabled . $multiple . '>';

        // Add placeholder option if specified
        if (!empty($attribs['placeholder'])) {
            $html .= '<option value="">' . htmlspecialchars($attribs['placeholder'], ENT_QUOTES, 'UTF-8') . '</option>';
        }

        foreach ($options as $key => $option) {
            if (\is_object($option)) {
                $optValue    = (string) ($option->value ?? $key);
                $optText     = (string) ($option->text ?? $option->title ?? $optValue);
                $optDisabled = !empty($option->disabled);
            } elseif (\is_array($option)) {
                $optValue    = (string) ($option['value'] ?? $key);
                $optText     = (string) ($option['text'] ?? $option['title'] ?? $optValue);
                $optDisabled = !empty($option['disabled']);
            } else {
                $optValue    = (string) $key;
                $optText     = (string) $option;
                $optDisabled = false;
            }

            $isSelected      = \in_array($optValue, $selectedValues, true) ? ' selected' : '';
            $optDisabledAttr = $optDisabled ? ' disabled' : '';

            $html .= '<option value="' . htmlspecialchars($optValue, ENT_QUOTES, 'UTF-8') . '"'
                . $isSelected . $optDisabledAttr . '>'
                . htmlspecialchars($optText, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        $html .= '</select>';

        return $html;
    }

    /**
     * Generate a Bootstrap checkbox input
     *
     * Creates a checkbox element with label, following Bootstrap 5 form-check patterns.
     *
     * @param   string  $name     The checkbox name attribute
     * @param   string  $value    The checkbox value
     * @param   string  $label    The language string key for the label
     * @param   bool    $checked  Whether the checkbox is checked
     * @param   array   $options  Additional options (class, id, disabled)
     *
     * @return  string  The HTML checkbox with label
     *
     * @since   6.0.0
     */
    public static function getCheckbox(
        string $name,
        string $value,
        string $label,
        bool $checked = false,
        array $options = []
    ): string {
        $id          = !empty($options['id']) ? $options['id'] : 'check_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $class       = htmlspecialchars($options['class'] ?? 'form-check-input', ENT_QUOTES, 'UTF-8');
        $disabled    = !empty($options['disabled']) ? ' disabled' : '';
        $checkedAttr = $checked ? ' checked' : '';

        // Escape values
        $escapedId    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $escapedName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $labelText    = htmlspecialchars(Text::_($label), ENT_QUOTES, 'UTF-8');

        return '<div class="form-check">'
            . '<input type="checkbox"'
            . ' class="' . $class . '"'
            . ' id="' . $escapedId . '"'
            . ' name="' . $escapedName . '"'
            . ' value="' . $escapedValue . '"'
            . $checkedAttr . $disabled . '>'
            . '<label class="form-check-label" for="' . $escapedId . '">'
            . $labelText
            . '</label>'
            . '</div>';
    }

    /**
     * Generate a Bootstrap radio input
     *
     * Creates a radio element with label, following Bootstrap 5 form-check patterns.
     *
     * @param   string  $name     The radio name attribute
     * @param   string  $value    The radio value
     * @param   string  $label    The language string key for the label
     * @param   bool    $checked  Whether the radio is checked
     * @param   array   $options  Additional options (class, id, disabled)
     *
     * @return  string  The HTML radio with label
     *
     * @since   6.0.0
     */
    public static function getRadio(
        string $name,
        string $value,
        string $label,
        bool $checked = false,
        array $options = []
    ): string {
        $id          = !empty($options['id']) ? $options['id'] : 'radio_' . preg_replace('/[^a-z0-9_]/i', '_', $name . '_' . $value);
        $class       = htmlspecialchars($options['class'] ?? 'form-check-input', ENT_QUOTES, 'UTF-8');
        $disabled    = !empty($options['disabled']) ? ' disabled' : '';
        $checkedAttr = $checked ? ' checked' : '';

        // Escape values
        $escapedId    = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $escapedName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $labelText    = htmlspecialchars(Text::_($label), ENT_QUOTES, 'UTF-8');

        return '<div class="form-check">'
            . '<input type="radio"'
            . ' class="' . $class . '"'
            . ' id="' . $escapedId . '"'
            . ' name="' . $escapedName . '"'
            . ' value="' . $escapedValue . '"'
            . $checkedAttr . $disabled . '>'
            . '<label class="form-check-label" for="' . $escapedId . '">'
            . $labelText
            . '</label>'
            . '</div>';
    }

    /**
     * Generate a hidden input element
     *
     * Creates a hidden input field with proper escaping.
     *
     * @param   string  $name   The input name attribute
     * @param   string  $value  The input value
     * @param   array   $options  Additional options (id)
     *
     * @return  string  The HTML hidden input element
     *
     * @since   6.0.0
     */
    public static function getHidden(string $name, string $value, array $options = []): string
    {
        $id = !empty($options['id']) ? ' id="' . htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8') . '"' : '';

        return '<input type="hidden"'
            . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"'
            . $id . '>';
    }

    /**
     * Generate a Bootstrap button element
     *
     * Creates a button element with proper escaping and Bootstrap 5 styling.
     *
     * @param   string  $text     The button text (language string key)
     * @param   string  $type     The button type (button, submit, reset)
     * @param   array   $options  Additional options (class, id, disabled, name, value, icon)
     *
     * @return  string  The HTML button element
     *
     * @since   6.0.0
     */
    public static function getButton(string $text, string $type = 'button', array $options = []): string
    {
        $class    = htmlspecialchars($options['class'] ?? 'btn btn-primary', ENT_QUOTES, 'UTF-8');
        $id       = !empty($options['id']) ? ' id="' . htmlspecialchars($options['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $disabled = !empty($options['disabled']) ? ' disabled' : '';
        $name     = !empty($options['name']) ? ' name="' . htmlspecialchars($options['name'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $value    = !empty($options['value']) ? ' value="' . htmlspecialchars($options['value'], ENT_QUOTES, 'UTF-8') . '"' : '';

        // Optional icon
        $iconHtml = '';

        if (!empty($options['icon'])) {
            $iconHtml = '<span class="' . htmlspecialchars($options['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></span> ';
        }

        $buttonText = htmlspecialchars(Text::_($text), ENT_QUOTES, 'UTF-8');

        return '<button type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . $class . '"'
            . $id . $name . $value . $disabled . '>'
            . $iconHtml . $buttonText
            . '</button>';
    }
}
