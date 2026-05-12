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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class CustomFieldHelper
{
    private static array $fieldCache = [];

    private static ?string $resolvedFramework = null;

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

        usort($areaFields, static fn ($a, $b) => $a->area_ordering <=> $b->area_ordering);

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
        'date', 'time', 'datetime', 'wysiwyg', 'customtext', 'multiuploader',
    ];

    /** Returns 'bootstrap5' or 'uikit'; cached per-request for auto-resolve. */
    private static function resolveFramework(?string $explicit): string
    {
        if ($explicit !== null && $explicit !== '') {
            $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $explicit) ?? '';
            return self::mapSubtemplateToFramework($sanitized);
        }

        if (self::$resolvedFramework !== null) {
            return self::$resolvedFramework;
        }

        try {
            $subtemplate = (string) J2CommerceHelper::config()->get('subtemplate', 'bootstrap5');
        } catch (\Throwable) {
            $subtemplate = 'bootstrap5';
        }

        return self::$resolvedFramework = self::mapSubtemplateToFramework($subtemplate);
    }

    private static function mapSubtemplateToFramework(string $subtemplate): string
    {
        if (str_starts_with($subtemplate, 'app_')) {
            $subtemplate = substr($subtemplate, 4);
        }

        return match ($subtemplate) {
            'uikit', 'tag_uikit' => 'uikit',
            default              => 'bootstrap5',
        };
    }

    /**
     * Translate a field_width class-string to the active grid dialect.
     * Grid tokens (col-* / uk-width-*) are rewritten; non-grid tokens pass through.
     */
    private static function translateFieldWidth(string $raw, bool $isUikit): string
    {
        if ($raw === '') {
            return '';
        }

        $tokens    = preg_split('/\s+/', trim($raw)) ?: [];
        $rewritten = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (self::isGridToken($token)) {
                $translated  = $isUikit ? self::bs5ToUikitGrid($token) : self::uikitToBs5Grid($token);
                $rewritten[] = $translated;
                continue;
            }

            $rewritten[] = $token;
        }

        return implode(' ', $rewritten);
    }

    private static function isGridToken(string $token): bool
    {
        return (bool) preg_match('/^(col(-\w+)?|uk-width-[\w-]+(@[smlx]+)?)$/', $token);
    }

    private static function bs5ToUikitGrid(string $token): string
    {
        // Map breakpoint suffix: -sm → @s, -md → @m, -lg → @l, -xl/-xxl → @xl
        static $bpMap = ['sm' => '@s', 'md' => '@m', 'lg' => '@l', 'xl' => '@xl', 'xxl' => '@xl'];
        // Map column fraction numerator to UIkit fraction
        static $colMap = [
            '1'  => '1-12', '2' => '1-6',  '3' => '1-4',  '4' => '1-3',
            '5'  => '5-12', '6' => '1-2',  '7' => '7-12', '8' => '2-3',
            '9'  => '3-4', '10' => '5-6', '11' => '11-12', '12' => '1-1',
        ];

        if ($token === 'col') {
            return 'uk-width-expand';
        }

        // col-{bp} (auto at breakpoint)
        if (preg_match('/^col-(sm|md|lg|xl|xxl)$/', $token, $m)) {
            return 'uk-width-expand' . ($bpMap[$m[1]] ?? '');
        }

        // col-{n} (no breakpoint)
        if (preg_match('/^col-(\d+)$/', $token, $m)) {
            $frac = $colMap[$m[1]] ?? null;
            if ($frac === null) {
                \Joomla\CMS\Log\Log::add('CustomFieldHelper: unknown col-' . $m[1], \Joomla\CMS\Log\Log::WARNING, 'customfieldhelper');
                return $token;
            }
            // LOSSY: 5/12 rounds to 5-12, 7/12 rounds to 7-12 (see plan §4 table)
            return 'uk-width-' . $frac;
        }

        // col-{bp}-{n}
        if (preg_match('/^col-(sm|md|lg|xl|xxl)-(\d+)$/', $token, $m)) {
            $bpSuffix = $bpMap[$m[1]] ?? '';
            $frac     = $colMap[$m[2]] ?? null;
            if ($frac === null) {
                \Joomla\CMS\Log\Log::add('CustomFieldHelper: unknown col-' . $m[1] . '-' . $m[2], \Joomla\CMS\Log\Log::WARNING, 'customfieldhelper');
                return $token;
            }
            return 'uk-width-' . $frac . $bpSuffix;
        }

        return $token;
    }

    private static function uikitToBs5Grid(string $token): string
    {
        static $fracMap = [
            '1-1' => '12', '1-2' => '6',  '1-3' => '4',  '1-4' => '3',
            '1-5' => '2',  '1-6' => '2',  '2-3' => '8',  '3-4' => '9',
            '2-5' => '5',  '3-5' => '7',  '4-5' => '10', '5-6' => '10',
        ];
        static $bpMap = ['s' => 'sm', 'm' => 'md', 'l' => 'lg', 'xl' => 'xl'];

        if ($token === 'uk-width-expand') {
            return 'col';
        }

        if ($token === 'uk-width-auto') {
            return 'col-auto';
        }

        // uk-width-expand@{bp}
        if (preg_match('/^uk-width-expand@([sml]|xl)$/', $token, $m)) {
            $bs5bp = $bpMap[$m[1]] ?? 'md';
            return 'col-' . $bs5bp;
        }

        // uk-width-{frac}@{bp}
        if (preg_match('/^uk-width-([\d-]+)@([sml]|xl)$/', $token, $m)) {
            $n     = $fracMap[$m[1]] ?? null;
            $bs5bp = $bpMap[$m[2]] ?? 'md';
            if ($n === null) {
                \Joomla\CMS\Log\Log::add('CustomFieldHelper: unknown uk-width-' . $m[1], \Joomla\CMS\Log\Log::WARNING, 'customfieldhelper');
                return $token;
            }
            return 'col-' . $bs5bp . '-' . $n;
        }

        // uk-width-{frac} (no breakpoint)
        if (preg_match('/^uk-width-([\d-]+)$/', $token, $m)) {
            $n = $fracMap[$m[1]] ?? null;
            if ($n === null) {
                \Joomla\CMS\Log\Log::add('CustomFieldHelper: unknown uk-width-' . $m[1], \Joomla\CMS\Log\Log::WARNING, 'customfieldhelper');
                return $token;
            }
            return 'col-' . $n;
        }

        return $token;
    }

    /**
     * Render a single custom field as HTML for the active framework.
     *
     * @since 6.x.x  Added $framework param; null auto-resolves from component subtemplate config.
     */
    public static function renderField(
        object  $field,
        string  $value = '',
        array   $attrs = [],
        ?string $framework = null
    ): string {
        $framework = self::resolveFramework($framework);
        $isUikit   = ($framework === 'uikit');
        $fieldType = $field->field_type ?? 'text';

        // Let plugins render non-core field types first
        if (!\in_array($fieldType, self::CORE_FIELD_TYPES, true)) {
            $renderEvent = J2CommerceHelper::plugin()->event('RenderCustomField', [
                'field'     => $field,
                'value'     => $value,
                'attrs'     => $attrs,
                'framework' => $framework,
            ]);
            $rendered = $renderEvent->getEventResult();

            if (!empty($rendered)) {
                return \is_array($rendered) ? implode('', $rendered) : (string) $rendered;
            }
        }

        $namekey    = htmlspecialchars($field->field_namekey, ENT_QUOTES, 'UTF-8');
        $label      = htmlspecialchars(Text::_($field->field_name), ENT_QUOTES, 'UTF-8');
        $required   = (int) $field->field_required;
        $default    = $field->field_default ?? '';
        $fieldValue = $value ?: $default;
        $id         = htmlspecialchars($attrs['id'] ?? $field->field_namekey, ENT_QUOTES, 'UTF-8');
        $extraClass = $attrs['class'] ?? '';

        // Config-driven rendering options
        $config            = J2CommerceHelper::config();
        $requiredIndicator = $attrs['requiredIndicator'] ?? $config->get('checkout_required_indicator', 'asterisk');
        $fieldStyle        = $attrs['fieldStyle'] ?? $config->get('checkout_field_style', 'normal');
        $isFloating        = ($fieldStyle === 'floating');

        // Placeholder and autocomplete attributes (stored as language string keys)
        $placeholderRaw   = $field->field_placeholder ?? '';
        $placeholderAttr  = $placeholderRaw !== '' ? ' placeholder="' . htmlspecialchars(Text::_($placeholderRaw), ENT_QUOTES, 'UTF-8') . '"' : '';
        $autocompleteRaw  = $field->field_autocomplete ?? '';
        $autocompleteAttr = $autocompleteRaw !== '' ? ' autocomplete="' . htmlspecialchars($autocompleteRaw, ENT_QUOTES, 'UTF-8') . '"' : '';

        // Column width: use field_width if set (translated to active dialect), otherwise auto-detect
        $fieldWidth   = trim($field->field_width ?? '');
        $colClass     = $fieldWidth !== ''
            ? self::translateFieldWidth($fieldWidth, $isUikit)
            : self::getColClass($namekey, $fieldType, $isUikit);
        $requiredAttr = $required ? ' required' : '';

        // Required indicator in label
        $labelHtml = $label;
        if ($required && $requiredIndicator === 'asterisk') {
            if ($isUikit) {
                $labelHtml .= '<span class="uk-text-danger uk-margin-small-left" aria-hidden="true">*</span>';
            } else {
                $labelHtml .= '<span class="text-danger ms-1" aria-hidden="true">*</span>';
            }
        } elseif (!$required && $requiredIndicator === 'optional') {
            if ($isUikit) {
                $labelHtml .= ' <small class="uk-text-muted">(' . Text::_('COM_J2COMMERCE_OPTIONAL') . ')</small>';
            } else {
                $labelHtml .= ' <small class="text-body-secondary">(' . Text::_('COM_J2COMMERCE_OPTIONAL') . ')</small>';
            }
        }

        $wrapperClass = $isUikit ? ($colClass . ' uk-margin-bottom') : ($colClass . ' mb-3');
        $html         = '<div class="' . $wrapperClass . '">';

        if ($isUikit) {
            $inputClass    = 'uk-input';
            $selectClass   = 'uk-select';
            $labelClass    = 'uk-form-label';
            $wrapperNormal = '';
        } else {
            $inputClass    = 'form-control';
            $selectClass   = 'form-select';
            $labelClass    = 'form-label';
            $wrapperNormal = 'form-normal';
        }

        switch ($fieldType) {
            case 'text':
            case 'email':
            case 'tel':
            case 'number':
                $inputType = $fieldType;
                // UIkit has no form-floating; stacked-label fallback.
                if ($isFloating && !$isUikit) {
                    $html .= '<div class="form-floating">'
                        . '<input type="' . $inputType . '" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= ($wrapperNormal !== '' ? '<div class="' . $wrapperNormal . '">' : '')
                        . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
                        . '<input type="' . $inputType . '" name="' . $namekey . '" id="' . $id . '" class="' . $inputClass . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . ($wrapperNormal !== '' ? '</div>' : '');
                }
                break;

            case 'telephone':
                $html .= self::renderTelephoneField(
                    $field,
                    $fieldValue,
                    $id,
                    $namekey,
                    $requiredAttr,
                    $labelHtml,
                    $isFloating,
                    $extraClass,
                    $autocompleteAttr,
                    $isUikit
                );
                break;

            case 'textarea':
                // UIkit has no form-floating; stacked-label fallback.
                if ($isFloating && !$isUikit) {
                    $html .= '<div class="form-floating">'
                        . '<textarea name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" rows="3"' . $requiredAttr . $placeholderAttr . '>' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '</textarea>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= ($wrapperNormal !== '' ? '<div class="' . $wrapperNormal . '">' : '')
                        . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
                        . '<textarea name="' . $namekey . '" id="' . $id . '" class="' . $inputClass . ($extraClass ? ' ' . $extraClass : '') . '" rows="3"' . $requiredAttr . $placeholderAttr . '>' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '</textarea>'
                        . ($wrapperNormal !== '' ? '</div>' : '');
                }
                break;

            case 'checkbox':
                $checked = $fieldValue ? ' checked' : '';
                if ($isUikit) {
                    $html .= '<label class="uk-flex uk-flex-middle">'
                        . '<input type="checkbox" name="' . $namekey . '" id="' . $id . '" class="uk-checkbox' . ($extraClass ? ' ' . $extraClass : '') . '" value="1"' . $checked . $requiredAttr . '>'
                        . '<span class="uk-margin-small-left">' . $labelHtml . '</span>'
                        . '</label>';
                } else {
                    $html .= '<div class="form-check">'
                        . '<input type="checkbox" name="' . $namekey . '" id="' . $id . '" class="form-check-input' . ($extraClass ? ' ' . $extraClass : '') . '" value="1"' . $checked . $requiredAttr . '>'
                        . '<label for="' . $id . '" class="form-check-label">' . $labelHtml . '</label>'
                        . '</div>';
                }
                break;

            case 'radio':
                $options = self::parseOptions($field->field_value ?? '');
                if ($isUikit) {
                    $html .= '<div class="uk-margin-small-bottom"><label class="uk-form-label">' . $labelHtml . '</label>';
                    foreach ($options as $i => $opt) {
                        $optId   = $id . '_' . $i;
                        $checked = ($fieldValue === $opt['value']) ? ' checked' : '';
                        $html .= '<label class="uk-flex uk-flex-middle">'
                            . '<input type="radio" name="' . $namekey . '" id="' . $optId . '" class="uk-radio uk-margin-small-right" value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $checked . $requiredAttr . '>'
                            . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8')
                            . '</label>';
                    }
                    $html .= '</div>';
                } else {
                    $html .= '<div class="form-normal"><label class="form-label">' . $labelHtml . '</label>';
                    foreach ($options as $i => $opt) {
                        $optId   = $id . '_' . $i;
                        $checked = ($fieldValue === $opt['value']) ? ' checked' : '';
                        $html .= '<div class="form-check">'
                            . '<input type="radio" name="' . $namekey . '" id="' . $optId . '" class="form-check-input" value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $checked . $requiredAttr . '>'
                            . '<label for="' . $optId . '" class="form-check-label">' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</label>'
                            . '</div>';
                    }
                    $html .= '</div>';
                }
                break;

            case 'select':
                $options = self::parseOptions($field->field_value ?? '');
                // UIkit has no form-floating; stacked-label fallback.
                if ($isFloating && !$isUikit) {
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
                    $html .= ($wrapperNormal !== '' ? '<div class="' . $wrapperNormal . '">' : '')
                        . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="' . $selectClass . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>'
                        . '<option value="">' . Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $label) . '</option>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>' . ($wrapperNormal !== '' ? '</div>' : '');
                }
                break;

            case 'zone':
                $html .= self::renderZoneField($field, $fieldValue, $id, $requiredAttr, $labelHtml, $label, $isFloating, $isUikit);
                break;

            case 'singledropdown':
                $options = self::parseOptions($field->field_value ?? '');
                // UIkit has no form-floating; stacked-label fallback.
                if ($isFloating && !$isUikit) {
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
                    $html .= ($wrapperNormal !== '' ? '<div class="' . $wrapperNormal . '">' : '')
                        . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
                        . '<select name="' . $namekey . '" id="' . $id . '" class="' . $selectClass . ($extraClass ? ' ' . $extraClass : '') . '"' . $requiredAttr . '>';
                    foreach ($options as $opt) {
                        $selected = ($fieldValue === $opt['value']) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($opt['value'], ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($opt['name'], ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    $html .= '</select>' . ($wrapperNormal !== '' ? '</div>' : '');
                }
                break;

            case 'customtext':
                $html .= $isUikit
                    ? '<div class="uk-text-meta">' . $field->field_default . '</div>'
                    : '<div class="form-text">' . $field->field_default . '</div>';
                break;

            case 'multiuploader':
                $html .= self::renderMultiuploaderField(
                    $field,
                    $fieldValue,
                    $id,
                    $namekey,
                    $requiredAttr,
                    $labelHtml,
                    $colClass,
                    $extraClass,
                    $isUikit
                );
                break;

            default:
                // UIkit has no form-floating; stacked-label fallback.
                if ($isFloating && !$isUikit) {
                    $html .= '<div class="form-floating">'
                        . '<input type="text" name="' . $namekey . '" id="' . $id . '" class="form-control' . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . '<label for="' . $id . '">' . $labelHtml . '</label>'
                        . '</div>';
                } else {
                    $html .= ($wrapperNormal !== '' ? '<div class="' . $wrapperNormal . '">' : '')
                        . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
                        . '<input type="text" name="' . $namekey . '" id="' . $id . '" class="' . $inputClass . ($extraClass ? ' ' . $extraClass : '') . '" value="' . htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . $placeholderAttr . $autocompleteAttr . '>'
                        . ($wrapperNormal !== '' ? '</div>' : '');
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
        $lines   = explode("\n", $optionsStr);

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
                // Normalize separators (space, dash, paren, dot) before
                // validating so legacy values entered via admin forms don't
                // trip digit-only checks.
                $normalized = PhoneHelper::normalize((string) $value);
                $parsed     = PhoneHelper::parseE164($normalized);
                $national   = $parsed['national'];
                $iso        = $parsed['iso2'];

                if (!preg_match('/^\d+$/', $national)) {
                    $errors[$namekey] = Text::sprintf('COM_J2COMMERCE_ERR_PHONE_DIGITS_ONLY', $label);
                    continue;
                }

                $lengths = PhoneHelper::getNationalLengths($iso);
                $len     = \strlen($national);
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

            // Multiuploader: validate JSON array has at least one file when required
            if ($field->field_type === 'multiuploader') {
                if ($required) {
                    $files = json_decode((string) $value, true);
                    if (!\is_array($files) || \count($files) === 0) {
                        $errors[$namekey] = Text::sprintf('COM_J2COMMERCE_ERR_FIELD_REQUIRED', $label);
                    }
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
                $data[$namekey] = PhoneHelper::normalize((string) ($formData[$namekey] ?? ''));
                continue;
            }

            // Multiuploader: preserve JSON array value as-is
            if ($field->field_type === 'multiuploader') {
                $data[$namekey] = $formData[$namekey] ?? '[]';
                continue;
            }

            $data[$namekey] = $formData[$namekey] ?? '';
        }

        return $data;
    }

    /** Register telephone widget assets. Skip bootstrap.dropdown for UIkit. */
    public static function ensureTelephoneAssets(bool $isUikit = false): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_j2commerce.telephone.css', 'media/com_j2commerce/css/site/telephone-field.css');
        $wa->registerAndUseScript('com_j2commerce.telephone', 'media/com_j2commerce/js/site/telephone-field.js', [], ['defer' => true]);

        if (!$isUikit) {
            HTMLHelper::_('bootstrap.dropdown', '.j2c-phone-country-btn', ['autoclose' => true]);
        }

        self::registerPhoneCountryMap();
    }

    /**
     * Render the standalone phone widget (hidden input + country selector +
     * national number input) with no label or form-group wrapper. Shared by
     * the frontend custom-field renderer and the admin Phone form field so
     * both render identical markup and share all assets/behavior.
     *
     * @param  string  $value  Stored value (E.164 "+xxx..." or digits, may be empty).
     * @param  string  $id     DOM id for the hidden input.
     * @param  string  $name   Form input name (e.g. "jform[phone_1]" in admin).
     * @param  array   $opts   Optional: required (bool), autocomplete (string),
     *                         placeholder (string), mode ('all'|'selected'|'none'),
     *                         allowedIso2 (string[]), extraClass (string),
     *                         defaultIso (string — overrides config default).
     */
    public static function renderPhoneWidget(string $value, string $id, string $name, array $opts = []): string
    {
        $isUikit = self::resolveFramework($opts['framework'] ?? null) === 'uikit';
        self::ensureTelephoneAssets($isUikit);

        $required     = !empty($opts['required']);
        $requiredAttr = $required ? ' required' : '';
        $autocomplete = (string) ($opts['autocomplete'] ?? 'tel-national');
        $placeholder  = (string) ($opts['placeholder'] ?? Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER'));
        $phoneMode    = (string) ($opts['mode'] ?? 'all');
        $allowedIso2  = $opts['allowedIso2'] ?? null;
        $extraClass   = (string) ($opts['extraClass'] ?? '');
        $defaultIso   = (string) ($opts['defaultIso'] ?? '');

        if ($defaultIso === '') {
            $defaultCountry = J2CommerceHelper::config()->get('default_country', '223');
            $defaultIso     = self::getCountryIso2((int) $defaultCountry) ?: 'US';
        }

        $parsed        = PhoneHelper::parseE164($value, $defaultIso);
        $selectedIso   = $parsed['iso2'];
        $nationalValue = $parsed['national'];
        $dialCode      = $parsed['code'];

        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $escapedAc    = htmlspecialchars($autocomplete, ENT_QUOTES, 'UTF-8');
        $escapedPh    = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');

        // "none" mode: plain tel input, no country dropdown / no widget
        if ($phoneMode === 'none') {
            $inputCls = $isUikit ? 'uk-input' : 'form-control';
            return '<input type="tel" class="' . $inputCls . '" '
                . 'name="' . $name . '" id="' . $id . '" '
                . 'value="' . $escapedValue . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . $escapedPh . '" '
                . 'data-mode="none"'
                . $requiredAttr . '>';
        }

        if ($phoneMode === 'selected' && !empty($allowedIso2)) {
            $countries = PhoneHelper::getCountryListForDropdown((array) $allowedIso2);
        } else {
            $countries = PhoneHelper::getCountryListForDropdown(null);
        }

        // Fallback: if filter yielded nothing, show all enabled countries
        if (empty($countries)) {
            $countries = PhoneHelper::getCountryListForDropdown(null);
        }

        $escapedIso       = htmlspecialchars($selectedIso, ENT_QUOTES, 'UTF-8');
        $escapedCode      = htmlspecialchars($dialCode, ENT_QUOTES, 'UTF-8');
        $escapedNat       = htmlspecialchars($nationalValue, ENT_QUOTES, 'UTF-8');
        $flagUrl          = PhoneHelper::getFlagUrl($selectedIso);
        $flagHtml         = $flagUrl
            ? '<img src="' . htmlspecialchars($flagUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $escapedIso . '" class="j2c-phone-flag">'
            : '<span class="j2c-phone-flag">' . $escapedIso . '</span>';
        $isSingleCountry  = \count($countries) === 1;
        $nationalInputCls = $isUikit ? 'uk-input j2c-phone-national' : 'form-control j2c-phone-national';

        if ($isSingleCountry) {
            $singleCountry = $countries[0];
            $singleFlagUrl = htmlspecialchars($singleCountry['flagUrl'] ?? '', ENT_QUOTES, 'UTF-8');
            $singleIso     = htmlspecialchars($singleCountry['iso2'], ENT_QUOTES, 'UTF-8');
            $singleCode    = htmlspecialchars($singleCountry['code'], ENT_QUOTES, 'UTF-8');
            $singleFlag    = $singleFlagUrl
                ? '<img src="' . $singleFlagUrl . '" alt="' . $singleIso . '" class="j2c-phone-flag">'
                : '<span class="j2c-phone-flag">' . $singleIso . '</span>';

            $prefixCls     = $isUikit
                ? 'j2c-phone-static-prefix uk-padding-small uk-background-muted'
                : 'input-group-text j2c-phone-static-prefix';
            $countryPrefix = '<span class="' . $prefixCls . '">'
                . $singleFlag . ' '
                . '<span class="j2c-phone-code">+' . $singleCode . '</span>'
                . '</span>';

            $maxLen        = (int) $singleCountry['max'];
            $nationalInput = '<input type="tel" class="' . $nationalInputCls . '" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'maxlength="' . $maxLen . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . $escapedPh . '" '
                . 'aria-label="' . $escapedPh . '" '
                . 'data-dial-code="' . htmlspecialchars($singleCountry['code'], ENT_QUOTES, 'UTF-8') . '" '
                . 'data-hidden-target="' . $id . '">';
        } else {
            if ($isUikit) {
                // data-bs-toggle stripped; UIkit dropdown JS handles uk-dropdown attr.
                $countryPrefix = '<button type="button" class="uk-button uk-button-default j2c-phone-country-btn" '
                    . 'aria-expanded="false" '
                    . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_SELECT_COUNTRY') . '">'
                    . $flagHtml . ' '
                    . '<span class="j2c-phone-code">+' . $escapedCode . '</span>'
                    . '</button>'
                    . '<div uk-dropdown="mode: click" class="uk-dropdown j2c-phone-country-dropdown">'
                    . '<ul class="uk-nav uk-dropdown-nav j2c-phone-country-list" style="max-height:300px;overflow-y:auto;">'
                    . '<li class="j2c-phone-search-sticky uk-padding-small">'
                    . '<input type="text" class="uk-input uk-form-small j2c-phone-search" '
                    . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_SEARCH_COUNTRY') . '" autocomplete="off">'
                    . '</li>'
                    . '</ul></div>';
            } else {
                $countryPrefix = '<button type="button" class="btn btn-outline-secondary dropdown-toggle j2c-phone-country-btn" '
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
            }

            $nationalInput = '<input type="tel" class="' . $nationalInputCls . '" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . $escapedPh . '" '
                . 'aria-label="' . $escapedPh . '">';
        }

        $countriesJson = htmlspecialchars(json_encode($countries, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $hiddenInput   = '<input type="hidden" name="' . $name . '" id="' . $id . '" '
            . 'value="' . $escapedValue . '"' . $requiredAttr . '>';

        $groupAttrs = ' data-field-id="' . $id . '" data-default-iso="' . $escapedIso . '" data-countries="' . $countriesJson . '"'
            . ' data-framework="' . ($isUikit ? 'uikit' : 'bootstrap5') . '"';
        if ($isSingleCountry) {
            $groupAttrs .= ' data-single-country="1"';
        }

        $cls = $isUikit
            ? 'j2c-telephone-field uk-flex uk-flex-middle uk-flex-nowrap' . ($extraClass !== '' ? ' ' . $extraClass : '')
            : 'j2c-telephone-field input-group' . ($extraClass !== '' ? ' ' . $extraClass : '');

        return '<div class="' . $cls . '"' . $groupAttrs . '>'
            . $hiddenInput
            . $countryPrefix
            . $nationalInput
            . '</div>';
    }

    private static function renderTelephoneField(
        object $field,
        string $value,
        string $id,
        string $namekey,
        string $requiredAttr,
        string $labelHtml,
        bool   $isFloating,
        string $extraClass,
        string $autocompleteAttr,
        bool   $isUikit = false
    ): string {
        self::ensureTelephoneAssets($isUikit);

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
            $inputCls     = $isUikit ? 'uk-input' : 'form-control';
            $plainInput   = '<input type="tel" class="' . $inputCls . '" '
                . 'name="' . $namekey . '" id="' . $id . '" '
                . 'value="' . $escapedValue . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'data-mode="none"'
                . $requiredAttr . '>';

            // UIkit has no form-floating; stacked-label fallback.
            if ($isFloating && !$isUikit) {
                return '<div class="form-floating">'
                    . $plainInput
                    . '<label for="' . $id . '">' . $labelHtml . '</label>'
                    . '</div>';
            }

            if ($isUikit) {
                return '<label for="' . $id . '" class="uk-form-label">' . $labelHtml . '</label>'
                    . $plainInput;
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

        $nationalInputCls = $isUikit ? 'uk-input j2c-phone-national' : 'form-control j2c-phone-national';

        if ($isSingleCountry) {
            // Static display: no dropdown button/menu
            $singleCountry = $countries[0];
            $singleFlagUrl = htmlspecialchars($singleCountry['flagUrl'] ?? '', ENT_QUOTES, 'UTF-8');
            $singleIso     = htmlspecialchars($singleCountry['iso2'], ENT_QUOTES, 'UTF-8');
            $singleCode    = htmlspecialchars($singleCountry['code'], ENT_QUOTES, 'UTF-8');
            $singleFlag    = $singleFlagUrl
                ? '<img src="' . $singleFlagUrl . '" alt="' . $singleIso . '" class="j2c-phone-flag">'
                : '<span class="j2c-phone-flag">' . $singleIso . '</span>';

            $prefixCls     = $isUikit
                ? 'j2c-phone-static-prefix uk-padding-small uk-background-muted'
                : 'input-group-text j2c-phone-static-prefix';
            $countryStatic = '<span class="' . $prefixCls . '">'
                . $singleFlag . ' '
                . '<span class="j2c-phone-code">+' . $singleCode . '</span>'
                . '</span>';
        } else {
            $countryStatic = null;
        }

        if (!$isSingleCountry) {
            if ($isUikit) {
                // data-bs-toggle stripped; UIkit dropdown JS handles uk-dropdown attr.
                $countryBtn = '<button type="button" class="uk-button uk-button-default j2c-phone-country-btn" '
                    . 'aria-expanded="false" '
                    . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_SELECT_COUNTRY') . '">'
                    . $flagHtml . ' '
                    . '<span class="j2c-phone-code">+' . $escapedCode . '</span>'
                    . '</button>'
                    . '<div uk-dropdown="mode: click" class="uk-dropdown j2c-phone-country-dropdown">'
                    . '<ul class="uk-nav uk-dropdown-nav j2c-phone-country-list" style="max-height:300px;overflow-y:auto;">'
                    . '<li class="j2c-phone-search-sticky uk-padding-small">'
                    . '<input type="text" class="uk-input uk-form-small j2c-phone-search" '
                    . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_SEARCH_COUNTRY') . '" autocomplete="off">'
                    . '</li>'
                    . '</ul></div>';
            } else {
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
            }
        } else {
            $countryBtn = null;
        }

        $countriesJson = htmlspecialchars(json_encode($countries, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

        $hiddenInput = '<input type="hidden" name="' . $namekey . '" id="' . $id . '" '
            . 'value="' . $escapedValue . '"' . $requiredAttr . '>';

        if ($isSingleCountry) {
            $singleEntry   = $countries[0];
            $maxLen        = (int) $singleEntry['max'];
            $nationalInput = '<input type="tel" class="' . $nationalInputCls . '" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'maxlength="' . $maxLen . '" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'data-dial-code="' . htmlspecialchars($singleEntry['code'], ENT_QUOTES, 'UTF-8') . '" '
                . 'data-hidden-target="' . $id . '">';
        } else {
            $nationalInput = '<input type="tel" class="' . $nationalInputCls . '" '
                . 'value="' . $escapedNat . '" '
                . 'inputmode="numeric" pattern="[0-9]*" '
                . 'autocomplete="' . $escapedAc . '" '
                . 'placeholder="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '" '
                . 'aria-label="' . Text::_('COM_J2COMMERCE_PHONE_NATIONAL_NUMBER') . '">';
        }

        $groupAttrs = ' data-field-id="' . $id . '" data-default-iso="' . $escapedIso . '" data-countries="' . $countriesJson . '"'
            . ' data-framework="' . ($isUikit ? 'uikit' : 'bootstrap5') . '"';
        $cls        = $isUikit
            ? 'j2c-telephone-field uk-flex uk-flex-middle uk-flex-nowrap' . ($extraClass ? ' ' . $extraClass : '')
            : 'j2c-telephone-field input-group' . ($extraClass ? ' ' . $extraClass : '');

        if ($isSingleCountry) {
            $groupAttrs .= ' data-single-country="1"';
        }

        // UIkit has no form-floating; stacked-label fallback.
        if ($isFloating && !$isUikit) {
            return '<div class="form-normal">'
                . '<label for="' . $id . '" class="form-label">' . $labelHtml . '</label>'
                . '<div class="' . $cls . '"' . $groupAttrs . '>'
                . $hiddenInput
                . ($isSingleCountry ? $countryStatic : $countryBtn)
                . '<div class="form-floating flex-grow-1">'
                . $nationalInput
                . '<label>' . $labelHtml . '</label>'
                . '</div>'
                . '</div>'
                . '</div>';
        }

        if ($isUikit) {
            return '<label for="' . $id . '" class="uk-form-label">' . $labelHtml . '</label>'
                . '<div class="' . $cls . '"' . $groupAttrs . '>'
                . $hiddenInput
                . ($isSingleCountry ? $countryStatic : $countryBtn)
                . $nationalInput
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

    /**
     * Emit a script option mapping country_id (DB primary key) to its ISO2 code
     * so the telephone-field JS can sync the phone country when the address
     * country_id select changes. Runs at most once per request.
     */
    private static function registerPhoneCountryMap(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }
        $registered = true;

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['j2commerce_country_id', 'country_isocode_2']))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->where($db->quoteName('enabled') . ' = 1');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $iso = strtoupper((string) $row->country_isocode_2);
            if ($iso !== '') {
                $map[(int) $row->j2commerce_country_id] = $iso;
            }
        }

        Factory::getApplication()->getDocument()->addScriptOptions('com_j2commerce.phoneCountryMap', $map);
    }

    private static function renderMultiuploaderField(
        object $field,
        string $value,
        string $id,
        string $namekey,
        string $requiredAttr,
        string $labelHtml,
        string $colClass,
        string $extraClass,
        bool   $isUikit = false
    ): string {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        // Register Uppy library (same assets as admin, loaded from administrator/ dir)
        $wa->registerAndUseStyle('com_j2commerce.vendor.uppy.css', 'media/com_j2commerce/vendor/uppy/css/uppy.min.css');
        $wa->registerAndUseScript('com_j2commerce.vendor.uppy', 'media/com_j2commerce/vendor/uppy/js/uppy.min.js', [], ['defer' => true]);

        // Register checkout uploader assets
        $wa->registerAndUseStyle('com_j2commerce.checkout-uploader.css', 'media/com_j2commerce/css/site/checkout-uploader.css');
        $wa->registerAndUseScript('com_j2commerce.checkout-uploader', 'media/com_j2commerce/js/site/checkout-uploader.js', [], ['defer' => true]);

        // Parse upload configuration from field_options
        $options = [];
        if (!empty($field->field_options)) {
            $decoded = json_decode($field->field_options, true);
            if (\is_array($decoded)) {
                $options = $decoded;
            }
        }

        $maxFiles      = (int) ($options['upload_max_files'] ?? 5);
        $maxFileSizeMB = (float) ($options['upload_max_file_size'] ?? 0);
        if ($maxFileSizeMB <= 0) {
            $maxFileSizeMB = 10.0; // Default 10 MB when not set or zero
        }
        $maxFileSize  = (int) ($maxFileSizeMB * 1024 * 1024); // Convert MB to bytes
        $allowedTypes = trim($options['upload_allowed_types'] ?? '');
        $directory    = trim($options['upload_directory'] ?? 'images/checkout-uploads');

        // Build the upload endpoint URL
        $token     = Session::getFormToken();
        $uploadUrl = Uri::root() . 'index.php?option=com_j2commerce&task=checkoutuploader.upload&format=json&' . $token . '=1';

        // Register frontend language strings for JS
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_DROP_OR_BROWSE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_BROWSE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_NOTE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_REMOVE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_UPLOADING');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_COMPLETE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_ERROR');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_MAX_FILES');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_FILE_TOO_LARGE');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_TYPE_NOT_ALLOWED');
        Text::script('COM_J2COMMERCE_CHECKOUT_UPLOAD_REQUIRED');

        $wrapperOpen    = $isUikit ? '' : '<div class="form-normal">';
        $wrapperClose   = $isUikit ? '' : '</div>';
        $labelClass     = $isUikit ? 'uk-form-label' : 'form-label';
        $constraintCls  = $isUikit
            ? 'j2c-upload-constraints uk-text-muted uk-text-small uk-margin-small-top'
            : 'j2c-upload-constraints text-body-secondary small mt-1';

        return $wrapperOpen
            . '<label for="' . $id . '" class="' . $labelClass . '">' . $labelHtml . '</label>'
            . '<div class="j2c-checkout-uploader' . ($extraClass ? ' ' . $extraClass : '') . '"'
            . ' data-field-id="' . $id . '"'
            . ' data-field-name="' . $namekey . '"'
            . ' data-max-files="' . $maxFiles . '"'
            . ' data-max-file-size="' . $maxFileSize . '"'
            . ' data-allowed-types="' . htmlspecialchars($allowedTypes, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-upload-url="' . htmlspecialchars($uploadUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-directory="' . htmlspecialchars($directory, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-required="' . ((int) $field->field_required) . '"'
            . '>'
            . '<input type="hidden" name="' . $namekey . '" id="' . $id . '" value="' . htmlspecialchars($value ?: '[]', ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . '>'
            . '<div class="j2c-uppy-dashboard" id="uppy-dashboard-' . $id . '"></div>'
            . '<div class="' . $constraintCls . '">'
            . Text::_('COM_J2COMMERCE_CHECKOUT_UPLOAD_MAX_SIZE') . ': ' . rtrim(rtrim((string) $maxFileSizeMB, '0'), '.') . ' MB'
            . ($allowedTypes ? ' &middot; ' . Text::_('COM_J2COMMERCE_CHECKOUT_UPLOAD_ACCEPTED_TYPES') . ': ' . htmlspecialchars(strtoupper($allowedTypes), ENT_QUOTES, 'UTF-8') : '')
            . '</div>'
            . '<div class="j2c-upload-file-list" id="file-list-' . $id . '"></div>'
            . '</div>'
            . $wrapperClose;
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
        bool   $isFloating = false,
        bool   $isUikit = false
    ): string {
        $name        = ($field->field_namekey === 'country_id') ? 'country_id' : 'zone_id';
        $entityLabel = ($name === 'country_id') ? Text::_('COM_J2COMMERCE_COUNTRY') : Text::_('COM_J2COMMERCE_ZONE');
        $placeholder = Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', $entityLabel);

        $selectCls = $isUikit ? 'uk-select' : 'form-select';
        $select    = '<select name="' . $name . '" id="' . $id . '" class="' . $selectCls . '"' . $requiredAttr . '>';

        if ($value !== '') {
            $select .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" selected></option>';
        }

        $select .= '<option value="">' . $placeholder . '</option>';
        $select .= '</select>';

        // UIkit has no form-floating; stacked-label fallback.
        if ($isFloating && !$isUikit) {
            return '<div class="form-floating">' . $select
                . '<label for="' . $id . '">' . $labelHtml . '</label></div>';
        }

        if ($isUikit) {
            return '<label for="' . $id . '" class="uk-form-label">' . $labelHtml . '</label>' . $select;
        }

        return '<div class="form-normal"><label for="' . $id . '" class="form-label">' . $labelHtml . '</label>' . $select . '</div>';
    }

    private static function getColClass(string $namekey, string $fieldType, bool $isUikit = false): string
    {
        $fullWidthFields = ['address_1', 'address_2', 'company'];

        if (\in_array($namekey, $fullWidthFields, true) || $fieldType === 'textarea' || $fieldType === 'customtext') {
            return $isUikit ? 'uk-width-1-1' : 'col-12';
        }

        return $isUikit ? 'uk-width-1-2@m' : 'col-md-6';
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
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('*')
            ->from($db->quoteName('#__j2commerce_customfields'))
            ->where($db->quoteName('field_namekey') . ' = :namekey')
            ->bind(':namekey', $namekey);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
