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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Zone field - provides a dropdown of enabled zones from the database.
 *
 * Supports AJAX linking to a country field via the `country_field` attribute.
 * When specified, the zone dropdown will dynamically update based on the selected country.
 *
 * Usage in XML:
 * <field name="zone_id" type="zone" country_field="country_id" />
 *
 * @since  6.0.7
 */
class ZoneField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $type = 'Zone';

    /**
     * The country field name to link to for AJAX filtering.
     *
     * @var    string
     * @since  6.0.7
     */
    protected $countryField;

    /**
     * Method to attach a Form object to the field.
     *
     * @param   \SimpleXMLElement  $element  The SimpleXMLElement object representing the field tag.
     * @param   mixed              $value    The form field value to validate.
     * @param   string             $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @since   6.0.7
     */
    public function setup(\SimpleXMLElement $element, $value, $group = null): bool
    {
        $result = parent::setup($element, $value, $group);

        if ($result) {
            $this->countryField = (string) $this->element['country_field'];
        }

        return $result;
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   6.0.7
     */
    protected function getInput(): string
    {
        $html = parent::getInput();

        // If a country field is specified, add AJAX linking JavaScript
        if (!empty($this->countryField)) {
            $html .= $this->getAjaxScript();
        }

        return $html;
    }

    /**
     * Method to get the list of zones as options.
     *
     * If a country_field is specified and we can determine the current country value,
     * only zones for that country are returned. Otherwise, all enabled zones are returned.
     *
     * @return  array  The field options.
     *
     * @since   6.0.7
     */
    public function getOptions(): array
    {
        $options = parent::getOptions();

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('j2commerce_zone_id', 'value'),
                    $db->quoteName('zone_name', 'text'),
                ])
                ->from($db->quoteName('#__j2commerce_zones'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('zone_name') . ' ASC');

            // If country_field is set, try to filter by the current country value
            if (!empty($this->countryField) && $this->form) {
                $countryValue = $this->form->getValue($this->countryField);

                if (!empty($countryValue) && is_numeric($countryValue)) {
                    $countryId = (int) $countryValue;
                    $query->where($db->quoteName('country_id') . ' = :country_id')
                        ->bind(':country_id', $countryId, ParameterType::INTEGER);
                }
            }

            $db->setQuery($query);
            $zones = $db->loadObjectList();

            if ($zones) {
                foreach ($zones as $zone) {
                    $options[] = HTMLHelper::_('select.option', $zone->value, $zone->text);
                }
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCE_ERROR_LOADING_ZONES', $e->getMessage()),
                'error'
            );
        }

        return $options;
    }

    /**
     * Generate the AJAX JavaScript for country/zone linking.
     *
     * @return  string  The inline JavaScript.
     *
     * @since   6.0.7
     */
    protected function getAjaxScript(): string
    {
        // Determine the form control prefix for field IDs
        $formControl = $this->formControl ?: 'jform';
        $group       = $this->group ? $this->group . '_' : '';

        // Build the field IDs
        // For config forms, the ID format is: jform_fieldname
        // For edit forms with groups, it might be: jform_group_fieldname
        $countryFieldId = $formControl . '_' . $group . $this->countryField;
        $zoneFieldId    = $this->id;

        // Get language strings (JS-safe via htmlspecialchars)
        $loadingText    = htmlspecialchars(Text::_('COM_J2COMMERCE_LOADING'), ENT_QUOTES, 'UTF-8');
        $selectZoneText = htmlspecialchars(Text::sprintf('COM_J2COMMERCE_SELECT_PLACEHOLDER', Text::_('COM_J2COMMERCE_ZONE')), ENT_QUOTES, 'UTF-8');

        $script = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const countrySelect = document.getElementById('{$countryFieldId}');
    const zoneSelect = document.getElementById('{$zoneFieldId}');

    if (!countrySelect || !zoneSelect) {
        // Try alternative ID patterns for different form contexts
        return;
    }

    /**
     * Load zones for the selected country via AJAX
     *
     * @param {number} countryId - The selected country ID
     * @param {number} selectedZoneId - The zone ID to pre-select (optional)
     */
    async function loadZones(countryId, selectedZoneId = 0) {
        // Show loading state
        zoneSelect.innerHTML = '<option value="">{$loadingText}</option>';
        zoneSelect.disabled = true;

        if (!countryId || countryId === '0' || countryId === '') {
            zoneSelect.innerHTML = '<option value="">{$selectZoneText}</option>';
            zoneSelect.disabled = false;
            return;
        }

        try {
            const url = 'index.php?option=com_j2commerce&task=ajax.getZones&country_id=' + countryId + '&zone_id=' + selectedZoneId;
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const html = await response.text();
            zoneSelect.innerHTML = html;
            zoneSelect.disabled = false;
        } catch (error) {
            console.error('Error loading zones:', error);
            zoneSelect.innerHTML = '<option value="">{$selectZoneText}</option>';
            zoneSelect.disabled = false;
        }
    }

    // Listen for country changes
    countrySelect.addEventListener('change', function() {
        loadZones(this.value, 0);
    });

    // On page load, if country is selected, load zones with the current zone value
    const initialCountryId = countrySelect.value;
    const initialZoneId = zoneSelect.value || 0;

    if (initialCountryId && initialCountryId !== '0' && initialCountryId !== '') {
        loadZones(initialCountryId, initialZoneId);
    }
});
</script>
JS;

        return $script;
    }
}
