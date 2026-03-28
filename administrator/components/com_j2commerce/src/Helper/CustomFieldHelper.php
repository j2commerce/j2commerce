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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use J2Commerce\Component\J2commerce\Administrator\Helper\PhoneHelper;

class CustomFieldHelper
{
    private static array $fieldCache = [];

    /** Core checkout area column names (use SMALLINT columns, not JSON). */
    private const CORE_AREAS = ['billing', 'register', 'shipping', 'guest', 'guest_shipping', 'payment'];

    /**
     * Get enabled custom fields for a checkout area or plugin area.
     *
     * Core areas (billing, register, shipping, guest, guest_shipping, payment) query the
     * dedicated SMALLINT columns. Plugin areas query the field_display JSON column.
     */
    public static function getFieldsByArea(string $area, string $type = 'address'): array
    {
        $cacheKey = $area . '.' . $type;

        if (isset(self::$fieldCache[$cacheKey])) {
            return self::$fieldCache[$cacheKey];
        }

        if (\in_array($area, self::CORE_AREAS, true)) {
            $fields = self::getCoreAreaFields($area, $type);
        } else {
            $fields = self::getPluginAreaFields($area);
        }

        self::$fieldCache[$cacheKey] = $fields;

        return $fields;
    }

    /**
     * Get fields for a core checkout area (SMALLINT column query).
     */
    private static function getCoreAreaFields(string $area, string $type): array
    {
        $column = 'field_display_' . $area;

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName($column) . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('j2commerce_customfield_id') . ' ASC');

        if ($type === 'address') {
            $query->where(
                '(' . $db->quoteName('field_table') . ' = :table OR '
                . $db->quoteName('field_table') . ' IS NULL OR '
                . $db->quoteName('field_table') . ' = ' . $db->quote('') . ')'
            )->bind(':table', $type);
        }

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Get fields for a plugin-registered area (field_display JSON column query).
     *
     * PHP-side filtering is used for broad MySQL compatibility.
     */
    private static function getPluginAreaFields(string $area): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('field_display') . ' != ' . $db->quote(''))
            ->where($db->quoteName('field_display') . ' IS NOT NULL');

        $db->setQuery($query);
        $allFields = $db->loadObjectList() ?: [];

        $areaFields = [];

        foreach ($allFields as $field) {
            $displayData = json_decode($field->field_display, true);

            if (!\is_array($displayData) || empty($displayData[$area]['enabled'])) {
                continue;
            }

            $field->area_ordering = (int) ($displayData[$area]['ordering'] ?? 0);
            $areaFields[]         = $field;
        }

        usort($areaFields, static fn($a, $b) => $a->area_ordering <=> $b->area_ordering);

        return $areaFields;
    }

    /**
     * Get plugin-registered display areas by dispatching onJ2CommerceGetCustomFieldDisplayAreas.
     *
     * @return  array  Array of area definitions, each with keys: key, label, description, plugin
     *
     * @since   6.1.5
     */
    public static function getRegisteredAreas(): array
    {
        $areas = [];
        J2CommerceHelper::plugin()->event('GetCustomFieldDisplayAreas', [&$areas]);
        return $areas;
    }

    /** Core field types handled by the built-in switch statement. */
    private const CORE_FIELD_TYPES = [
        'text', 'email', 'tel', 'number', 'telephone', 'textarea',
        'checkbox', 'radio', 'select', 'singledropdown', 'zone',
        'date', 'time', 'datetime', 'wysiwyg', 'customtext',
    ];

    /**
     * Render a single custom field as Bootstrap 5 HTML.
     */
    public static function renderField(object $field, string $value = '', array $attrs = []): string
    {
        $fieldType = $field->field_type ?? 'text';

        // Let plugins render non-core field types first
        if (!\in_array($fieldType, self::CORE_FIELD_TYPES, true)) {
            $renderEvent = J2CommerceHelper::plugin()->event('RenderCustomField', [
                'field' => $field,
                'value' => $value,
                'attrs' => $attrs,
            ]);
            $rendered = $renderEvent->getEventResult();

            if (!empty($rendered)) {
                return \is_array($rendered) ? implode('', $rendered) : (string) $rendered;
            }
        }

        $namekey = htmlspecialchars($field->field_namekey, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(Text::_($field->field_name), ENT_QUOTES, 'UTF-8');
        $required = (int) $field->field_required;
        $default = $field->field_default ?? '';
        $fieldValue = $value ?: $default;
        $id = htmlspecialchars($attrs['id'] ?? $field->field_namekey, ENT_QUOTES, 'UTF-8');
        $extraClass = $attrs['class'] ?? '';

        // Config-driven rendering options
        $config = J2CommerceHelper::config();
        $requiredIndicator = $attrs['requiredIndicator'] ?? $config->get('checkout_required_indicator', 'asterisk');
        $fieldStyle = $attrs['fieldStyle'] ?? $config->get('checkout_field_style', 'normal');
        $isFloating = ($fieldStyle === 'floating');

        // Placeholder and autocomplete attributes (stored as language string keys)
        $placeholderRaw = $field->field_placeholder ?? '';
        $placeholderAttr = $placeholderRaw !== '' ? ' placeholder="' . htmlspecialchars(Text::_($placeholderRaw), ENT_QUOTES, 'UTF-8') . '"' : '';
        $autocompleteRaw = $field->field_autocomplete ?? '';
        $autocompleteAttr = $autocompleteRaw !== '' ? ' autocomplete="' . htmlspecialchars($autocompleteRaw, ENT_QUOTES, 'UTF-8') . '"' : '';

        // Column width: use field_width if set, otherwise auto-detect
        $fieldWidth = trim($field->field_width ?? '');
        $colClass = $fieldWidth !== '' ? $fieldWidth : self::getColClass($namekey, $fieldType);
        $requiredAttr = $required ? ' required' : '';

        // Required indicator in label
        $labelHtml = $label;
        if ($required && $requiredIndicator === 'asterisk') {
            $labelHtml .= '<span class="text-danger ms-1" aria-hidden="true">*</span>';
        } elseif (!$required && $requiredIndicator === 'optional') {
            $labelHtml .= ' <small class="text-muted">(' . Text::_('COM_J2COMMERCE_OPTIONAL') . ')</small>';
        }

        $html = '<div class="' . $colClass . ' mb-3">';

        switch ($fieldType) {
            case 'text':
            case 'email':
            case 'tel':
            case 'number':
                $inputType = $fieldType;
                if ($isFloating) {
                    $html .= '<div class="form-floating">'
                        . '<input type="' . $inputType . '" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= '<div class="form-normal">'
                        . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                        . '<input type="' . $inputType . '" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '</div>';
                }
                break;

            case 'telephone':
                $html .= self::renderTelephoneField(
                    $field, $fieldValue, $id, $namekey, $requiredAttr,
                    $labelHtml, $isFloating, $extraClass, $autocompleteAttr
                );
                break;

            case 'textarea':
                if ($isFloating) {
                    $html .= '<div class="form-floating">'
                        . '<textarea name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" rows="3"' . $requiredAttr . $placeholderAttr . '>' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '</textarea>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= '<div class="form-normal">'
                        . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                        . '<textarea name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" rows="3"' . $requiredAttr . $placeholderAttr . '>' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '</textarea>'
                        . '</div>';
                }
                break;

            case 'checkbox':
                $checked = $fieldValue ? ' checked' : '';
                $html .= '<div class="form-check">'
                    . '<input type="checkbox" name="' . $namekey . '" id="' . $id . '" class="form-check-input' . ($extraClass ? ' ' . $extraClass : '') . '" value="1"' . $checked . $requiredAttr . '>'
                    . '<label for="' . $id . '" class="form-check-label">' . $labelHtml . '</label>'
                    . '</div>';
                break;

            case 'radio':
                $options = self::parseOptions($field->field_value ?? '');
                $html .= '<div class="form-normal"><label class="form-label">' . $labelHtml . '</label>';
                foreach ($options as $i => $opt) {
                    $optId = $id . '_' . $i;
                    $checked = ($fieldValue === $opt['value']) ? ' checked' : '';
                    $html .= '<div class="form-check">'
                        . '<input type="radio" name="' . $namekey . '" id="' . $optId . '" class="form-check-input" value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $checked . $requiredAttr . '>'
                        . '<label for="' . $optId . '" class="form-check-label">' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</label>'
                        . '</div>';
                }
                $html .= '</div>';
                break;

            case 'select':
                $options = self::parseOptions($field->field_value ?? '');
                if ($isFloating) {
                    $html .= '<div class="form-floating">'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="form-select' . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>'
                        . '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $label) . '</option>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= '<div class="form-normal">'
                        . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="form-select' . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>'
                        . '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $label) . '</option>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select></div>';
                }
                break;

            case 'zone':
                $html .= self::renderZoneField($field, $fieldValue, $id, $requiredAttr, $labelHtml, $label, $isFloating);
                break;

            case 'singledropdown':
                $options = self::parseOptions($field->field_value ?? '');
                if ($isFloating) {
                    $html .= '<div class="form-floating">'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="form-select' . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>'
                        . '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $label) . '</option>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= '<div class="form-normal">'
                        . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="form-select' . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select></div>';
                }
                break;

            case 'customtext':
                $html .= '<div class="form-text">' . $field->field_default . '</div>';
                break;

            default:
                // Fallback to text input
                if ($isFloating) {
                    $html .= '<div class="form-floating">'
                        . '<input type="text" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= '<div class="form-normal">'
                        . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                        . '<input type="text" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '</div>';
                }
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Parse field options from stored format.
     */
    private static function parseOptions(string $optionsStr): array
    {
        if ($optionsStr === '') {
            return [];
        }

        // Try JSON first (new format from subform editor)
        $decoded = json_decode($optionsStr, true);

        if (\is_array($decoded)) {
            $options = [];

            foreach ($decoded as $item) {
                if (\is_array($item) && isset($item['value'])) {
                    $options[] = ['value' => (string) $item['value'], 'name' => (string) ($item['name'] ?? $item['value'])];
                }
            }

            return $options;
        }

        // Legacy format: newline-separated value=name pairs
        $options = [];
        $lines = explode("\n", $optionsStr);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (\count($parts) === 2) {
                $options[] = ['value' => trim($parts[0]), 'name' => trim($parts[1])];
            } else {
                $options[] = ['value' => trim($line), 'name' => trim($line)];
            }
        }

        return $options;
    }

    /**
     * Validate custom field data.
     */
    public static function validateFields(array $fields, array $data): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $namekey  = $field->field_namekey;
            $value    = $data[$namekey] ?? '';
            $required = (int) $field->field_required;
            $label    = Text::_($field->field_name);

            // Core required check applies to all field types
            if ($required && trim((string) $value) === '') {
                $errors[$namekey] = Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', $label);
                continue;
            }

            if ($field->field_type === 'telephone' && trim($value) !== '') {
                $parsed   = PhoneHelper::parseE164($value);
                $national = $parsed['national'];
                $iso      = $parsed['iso2'];

                if (!preg_match('/^\d+$/', $national)) {
                    $errors[$namekey] = Text::sprintf('COM_J2COMMERCE_ERR_PHONE_DIGITS_ONLY', $label);
                    continue;
                }

                $lengths = PhoneHelper::getNationalLengths($iso);
                $len     = strlen($national);
                if ($len < $lengths['min'] || $len > $lengths['max']) {
                    $errors[$namekey] = Text::sprintf(
                        'COM_J2COMMERCE_ERR_PHONE_LENGTH',
                        $label,
                        $lengths['min'],
                        $lengths['max']
                    );
                }

                continue;
            }

            // Dispatch plugin validation for non-core field types
            if (!\in_array($field->field_type, self::CORE_FIELD_TYPES, true)) {
                J2CommerceHelper::plugin()->event('ValidateCustomField', [
                    &$errors,
                    'field' => $field,
                    'value' => $value,
                ]);
            }
        }

        return $errors;
    }

    /**
     * Collect address data from form submission for a specific area.
     */
    public static function collectAddressData(array $fields, array $formData): array
    {
        $data = [];

        foreach ($fields as $field) {
            $namekey = $field->field_namekey;

            // Handle checkbox (not submitted if unchecked)
            if ($field->field_type === 'checkbox') {
                $data[$namekey] = isset($formData[$namekey]) ? 1 : 0;
                continue;
            }

            // Hidden input already contains the assembled E.164 value
            if ($field->field_type === 'telephone') {
                $data[$namekey] = $formData[$namekey] ?? '';
                continue;
            }

            $data[$namekey] = $formData[$namekey] ?? '';
        }

        return $data;
    }

    private static function renderTelephoneField(
        object $field,
        string $value,
        string $id,
        string $namekey,
        string $requiredAttr,
        string $labelHtml,
        bool $isFloating,
        string $extraClass,
        string $autocompleteAttr
    ): string {
        // Register telephone field assets once per request
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.telephone.css', 'media/com_j2commerce/css/site/telephone-field.css');
        $wa->registerAndUseScript('com_j2commerce.telephone', 'media/com_j2commerce/js/site/telephone-field.js', [], ['defer' => true]);

        $defaultCountry = J2CommerceHelper::config()->get('default_country', '223');
        $defaultIso     = self::getCountryIso2((int) $defaultCountry) ?: 'US';
        $parsed         = PhoneHelper::parseE164($value, $defaultIso);
        $selectedIso    = $parsed['iso2'];
        $nationalValue  = $parsed['national'];
        $dialCode       = $parsed['code'];

        // Determine which countries to show based on field settings stored in field_options
        $allowedIso2  = null;
        $fieldOptions = [];
        if (!empty($field->field_options)) {
            $decoded = json_decode($field->field_options, true);
            if (\is_array($decoded)) {
                $fieldOptions = $decoded;
            }
        }

        // Resolve phone_country_mode with backward compat for legacy phone_all_countries
        if (isset($fieldOptions['phone_country_mode'])) {
            $phoneMode = $fieldOptions['phone_country_mode'];
        } elseif (isset($fieldOptions['phone_all_countries'])) {
            $phoneMode = ((int) $fieldOptions['phone_all_countries'] === 1) ? 'all' : 'selected';
        } else {
            $phoneMode = 'all';
        }

        // Handle "none" mode: plain tel input, no country dropdown
        if ($phoneMode === 'none') {
            $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $escapedAc    = htmlspecialchars($field->field_autocomplete ?? 'tel', ENT_QUOTES, 'UTF-8');
            $plainInput   = '<input type="tel" class="form-control" '
                . 'name="' . $namekey . '" id="' . $id . '" '
                . 'value="' . $escapedValue . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'data-mode="none"'
                . $requiredAttr . '>';

            if ($isFloating) {
                return '<div class="form-floating">'
                    . $plainInput
                    . '<label for="' . $id . '">' . $labelHtml . '</label>'
                    . '</div>';
            }

            return '<div class="form-normal">'
                . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                . $plainInput
                . '</div>';
        }

        if ($phoneMode === 'selected' && !empty($fieldOptions['phone_countries'])) {
            $allowedIso2 = (array) $fieldOptions['phone_countries'];
        }

        $countries = PhoneHelper::getCountryListForDropdown($allowedIso2);

        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $escapedIso   = htmlspecialchars($selectedIso, ENT_QUOTES, 'UTF-8');
        $escapedCode  = htmlspecialchars($dialCode, ENT_QUOTES, 'UTF-8');
        $escapedNat   = htmlspecialchars($nationalValue, ENT_QUOTES, 'UTF-8');
        $escapedAc    = htmlspecialchars($field->field_autocomplete ?? 'tel-national', ENT_QUOTES, 'UTF-8');
        $flagUrl      = PhoneHelper::getFlagUrl($selectedIso);
        $flagHtml     = $flagUrl ? '<img src="' . htmlspecialchars($flagUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $escapedIso . '" class="j2c-phone-flag">' : '<span class="j2c-phone-flag">' . $escapedIso . '</span>';

        $isSingleCountry = \count($countries) === 1;

        if ($isSingleCountry) {
            // Static display: no dropdown button/menu
            $singleCountry = $countries[0];
            $singleFlagUrl = htmlspecialchars($singleCountry['flagUrl'] ?? '', ENT_QUOTES, 'UTF-8');
            $singleIso     = htmlspecialchars($singleCountry['iso2'], ENT_QUOTES, 'UTF-8');
            $singleCode    = htmlspecialchars($singleCountry['code'], ENT_QUOTES, 'UTF-8');
            $singleFlag    = $singleFlagUrl
                ? '<img src="' . $singleFlagUrl . '" alt="' . $singleIso . '" class="j2c-phone-flag">'
                : '<span class="j2c-phone-flag">' . $singleIso . '</span>';

            $countryStatic = '<span class="input-group-text j2c-phone-static-prefix">'
                . $singleFlag . ' '
                . '<span class="j2c-phone-code">+' . $singleCode . '</span>'
                . '</span>';
        } else {
            $countryStatic = null;
        }

        if (!$isSingleCountry) {
            $countryBtn = '<button type="button" class="btn btn-outline-secondary dropdown-toggle j2c-phone-country-btn" '
                . 'data-bs-toggle="dropdown" aria-expanded="false" '
                . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_SELECT_COUNTRY') . '">'
                . $flagHtml . ' '
                . '<span class="j2c-phone-code">+' . $escapedCode . '</span>'
                . '</button>'
                . '<ul class="dropdown-menu j2c-phone-country-dropdown" style="max-height:300px;overflow-y:auto;">'
                . '<li class="px-2 py-1 sticky-top bg-body">'
                . '<input type="text" class="form-control form-control-sm j2c-phone-search" '
                . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_SEARCH_COUNTRY') . '" autocomplete="off">'
                . '</li>'
                . '</ul>';
        } else {
            $countryBtn = null;
        }

        $countriesJson = htmlspecialchars(json_encode($countries, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        $hiddenInput = '<input type="hidden" name="' . $namekey . '" id="' . $id . '" '
            . 'value="' . $escapedValue . '"' . $requiredAttr . '>';

        // For single-country, pre-assemble the E.164 value if national number entered
        if ($isSingleCountry) {
            $singleEntry = $countries[0];
            $maxLen      = (int) $singleEntry['max'];
            $nationalInput = '<input type="tel" class="form-control j2c-phone-national" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'maxlength="' . $maxLen . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'data-dial-code="' . htmlspecialchars($singleEntry['code'], ENT_QUOTES, 'UTF-8') . '" '
                . 'data-hidden-target="' . $id . '">';
        } else {
            $nationalInput = '<input type="tel" class="form-control j2c-phone-national" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '">';
        }

        $groupAttrs = ' data-field-id="' . $id . '" data-default-iso="' . $escapedIso . '" data-countries="' . $countriesJson . '"';
        $cls = 'j2c-telephone-field input-group' . ($extraClass ? ' ' . $extraClass : '');

        if ($isSingleCountry) {
            $groupAttrs .= ' data-single-country="1"';
        }

        if ($isFloating) {
            return '<div class="' . $cls . '"' . $groupAttrs . '>'
                . $hiddenInput
                . ($isSingleCountry ? $countryStatic : $countryBtn)
                . '<div class="form-floating flex-grow-1">'
                . $nationalInput
                . '<label>' . $labelHtml . '</label>'
                . '</div>'
                . '</div>';
        }

        return '<div class="form-normal">'
            . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
            . '<div class="' . $cls . '"' . $groupAttrs . '>'
            . $hiddenInput
            . ($isSingleCountry ? $countryStatic : $countryBtn)
            . $nationalInput
            . '</div>'
            . '</div>';
    }

    private static function getCountryIso2(int $countryId): ?string
    {
        if ($countryId <= 0) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('country_isocode_2'))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('j2commerce_country_id') . ' = :id')
            ->bind(':id', $countryId, ParameterType::INTEGER);

        $db->setQuery($query);
        return $db->loadResult() ?: null;
    }

    /**
     * Render country or zone select field.
     */
    private static function renderZoneField(
        object $field,
        string $value,
        string $id,
        string $requiredAttr,
        string $labelHtml,
        string $label,
        bool $isFloating = false
    ): string {
        $name = ($field->field_namekey === 'country_id') ? 'country_id' : 'zone_id';
        $entityLabel = ($name === 'country_id') ? Text::_('COM_J2COMMERCE_COUNTRY') : Text::_('COM_J2COMMERCE_ZONE');
        $placeholder = Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $entityLabel);

        $select = '<select name="' . $name . '" id="' . $id . '" class="form-select"' . $requiredAttr . '>';

        if ($value !== '') {
            $select .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" selected></option>';
        }

        $select .= '<option value="">' . $placeholder . '</option>';
        $select .= '</select>';

        if ($isFloating) {
            return '<div class="form-floating">' . $select
                . '<label for="' . $id . '">' . $labelHtml . '</label></div>';
        }

        return '<div class="form-normal"><label for="' . $id . '" class="form-label">' . $labelHtml . '</label>' . $select.'</div>';
    }

    /**
     * Determine the Bootstrap column class for a field.
     */
    private static function getColClass(string $namekey, string $fieldType): string
    {
        $fullWidthFields = ['address_1', 'address_2', 'company'];

        if (\in_array($namekey, $fullWidthFields, true)) {
            return 'col-12';
        }

        if ($fieldType === 'textarea' || $fieldType === 'customtext') {
            return 'col-12';
        }

        return 'col-md-6';
    }

    /**
     * Get a plugin's params from an address record, namespaced by plugin name.
     *
     * @param  int     $addressId   Address record ID
     * @param  string  $pluginName  Plugin element name (e.g., 'app_vendormanagement')
     * @return array   The plugin's params (empty array if none)
     *
     * @since  6.1.5
     */
    public static function getAddressParams(int $addressId, string $pluginName): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('j2commerce_address_id') . ' = :id')
            ->bind(':id', $addressId, ParameterType::INTEGER);
        $db->setQuery($query);
        $raw = $db->loadResult();

        if (empty($raw)) {
            return [];
        }

        $allParams = json_decode($raw, true);

        if (!\is_array($allParams)) {
            return [];
        }

        return $allParams[$pluginName] ?? [];
    }

    /**
     * Set a plugin's params on an address record (replaces that plugin's namespace only).
     *
     * Other plugins' namespaces are preserved.
     *
     * @param  int     $addressId   Address record ID
     * @param  string  $pluginName  Plugin element name
     * @param  array   $params      The plugin's params to store
     *
     * @since  6.1.5
     */
    public static function setAddressParams(int $addressId, string $pluginName, array $params): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('j2commerce_address_id') . ' = :id')
            ->bind(':id', $addressId, ParameterType::INTEGER);
        $db->setQuery($query);
        $raw = $db->loadResult();

        $allParams              = (!empty($raw)) ? (json_decode($raw, true) ?: []) : [];
        $allParams[$pluginName] = $params;

        $json   = json_encode($allParams, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_addresses'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('j2commerce_address_id') . ' = :id')
            ->bind(':params', $json)
            ->bind(':id', $addressId, ParameterType::INTEGER);
        $db->setQuery($update)->execute();
    }

    /**
     * Merge values into a plugin's params namespace (preserves existing keys in that namespace).
     *
     * @param  int     $addressId   Address record ID
     * @param  string  $pluginName  Plugin element name
     * @param  array   $merge       Key-value pairs to merge
     *
     * @since  6.1.5
     */
    public static function mergeAddressParams(int $addressId, string $pluginName, array $merge): void
    {
        $existing = self::getAddressParams($addressId, $pluginName);
        self::setAddressParams($addressId, $pluginName, array_merge($existing, $merge));
    }

    /**
     * Get ALL params from an address record (all plugin namespaces).
     *
     * @param  int  $addressId  Address record ID
     * @return array  Full params array keyed by plugin name
     *
     * @since  6.1.5
     */
    public static function getAllAddressParams(int $addressId): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__j2commerce_addresses'))
            ->where($db->quoteName('j2commerce_address_id') . ' = :id')
            ->bind(':id', $addressId, ParameterType::INTEGER);
        $db->setQuery($query);
        $raw = $db->loadResult();

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Get custom field by namekey.
     */
    public static function getFieldByNamekey(string $namekey): ?object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('field_namekey') . ' = :namekey')
            ->bind(':namekey', $namekey);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
