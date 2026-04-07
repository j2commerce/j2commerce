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

use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\CMS\Language\Text;
use J2Commerce\Component\J2commerce\Administrator\Helper\PhoneHelper;

/**
 * Renders continent-grouped checkboxes for selecting allowed phone countries.
 * Layout matches Joomla core Menu Assignment pattern: bordered cards with
 * Toggle Selection buttons, 2-column grid of continent cards.
 */
class PhoneCountriesField extends CheckboxesField
{
    protected $type = 'PhoneCountries';

    protected function getChecked(): array
    {
        $value = $this->value;

        if (\is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        if (\is_array($value)) {
            if (isset($value[0]) && \is_array($value[0])) {
                return $value[0];
            }
            return $value;
        }

        return [];
    }

    protected function getInput(): string
    {
        $checked      = $this->getChecked();
        $dialData     = PhoneHelper::getDialData();
        $continentMap = PhoneHelper::getContinentMap();
        $name         = $this->name;
        $id           = $this->id;

        // Build name map from ALL countries in the DB (not just enabled) so that
        // every country in the picker shows its full name and is searchable by name.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['country_name', 'country_isocode_2']))
            ->from($db->quoteName('#__j2commerce_countries'))
            ->order($db->quoteName('country_name') . ' ASC');
        $db->setQuery($query);
        $nameMap = [];
        foreach ($db->loadObjectList() as $row) {
            $nameMap[$row->country_isocode_2] = $row->country_name;
        }

        // Build ISO2 → dial code map
        $dialCodeMap = [];
        foreach ($dialData as $iso2 => $data) {
            $dialCodeMap[$iso2] = $data['code'];
        }

        // Track which ISO2 codes have been placed in a continent
        $placed = [];
        foreach ($continentMap as $codes) {
            foreach ($codes as $iso2) {
                $placed[$iso2] = true;
            }
        }

        // Collect any dial-data countries not in the continent map into "Other"
        $other = [];
        foreach ($dialData as $iso2 => $data) {
            if (!isset($placed[$iso2])) {
                $other[] = $iso2;
            }
        }

        $allContinents = $continentMap;
        if ($other) {
            $allContinents['Other'] = $other;
        }

        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $searchLabel      = Text::_('COM_J2COMMERCE_PHONE_COUNTRIES_SEARCH');
        $toggleAllLabel   = Text::_('COM_J2COMMERCE_PHONE_COUNTRIES_SELECT_ALL');
        $toggleLabel      = Text::_('COM_J2COMMERCE_PHONE_COUNTRIES_TOGGLE');

        $masterCheckId = $id . '_master';

        $html  = '<div class="j2c-phone-countries-picker" data-field-id="' . $esc($id) . '">';

        // Search input
        $html .= '<div class="mb-3">'
            . '<input type="text" class="form-control j2c-phone-countries-search" '
            . 'placeholder="' . $esc($searchLabel) . '" autocomplete="off">'
            . '</div>';

        // Master Toggle All Selections button (matches Joomla Menu Assignment style)
        $html .= '<div class="mb-3">'
            . '<button type="button" class="btn btn-outline-primary btn-sm j2c-phone-master-toggle" id="' . $esc($masterCheckId) . '">'
            . '<span class="icon-checkbox me-1" aria-hidden="true"></span>'
            . $esc($toggleAllLabel)
            . '</button>'
            . '</div>';

        // 2-column grid of continent cards (matching Menu Assignment layout)
        $html .= '<div class="row g-3">';

        $checkIndex = 0;

        foreach ($allContinents as $continentName => $isoCodes) {
            $validCodes = array_filter($isoCodes, static fn(string $iso) => isset($dialCodeMap[$iso]));

            if (empty($validCodes)) {
                continue;
            }

            usort($validCodes, static function (string $a, string $b) use ($nameMap): int {
                return strcmp($nameMap[$a] ?? $a, $nameMap[$b] ?? $b);
            });

            $continentId = $id . '_continent_' . strtolower(preg_replace('/\W+/', '_', $continentName));

            // Continent card
            $html .= '<div class="col-md-6 j2c-phone-continent" data-continent="' . $esc($continentName) . '">';
            $html .= '<div class="card border">';

            // Card body with Toggle Selection button + continent name
            $html .= '<div class="card-body">';
            $html .= '<button type="button" class="btn btn-outline-primary btn-sm mb-2 j2c-phone-continent-toggle" '
                . 'data-continent="' . $esc($continentName) . '">'
                . '<span class="icon-checkbox me-1" aria-hidden="true"></span>'
                . $esc($toggleLabel ?? 'Toggle Selection')
                . '</button>';
            $html .= '<div class="fw-bold mb-2">' . $esc($continentName) . '</div>';

            // Single-column list of country checkboxes
            foreach ($validCodes as $iso2) {
                $dialCode    = $dialCodeMap[$iso2];
                $countryName = $nameMap[$iso2] ?? $iso2;
                $isChecked   = \in_array($iso2, $checked, true) ? ' checked' : '';
                $optId       = $id . '_' . $checkIndex;

                $html .= '<div class="form-check j2c-phone-country-item" '
                    . 'data-name="' . $esc(strtolower($countryName)) . '" '
                    . 'data-code="' . $esc($dialCode) . '" '
                    . 'data-iso="' . $esc(strtolower($iso2)) . '">'
                    . '<input type="checkbox" class="form-check-input j2c-phone-country-check" '
                    . 'name="' . $esc($name) . '" '
                    . 'id="' . $esc($optId) . '" '
                    . 'value="' . $esc($iso2) . '"'
                    . $isChecked . ' '
                    . 'data-continent="' . $esc($continentName) . '">'
                    . '<label class="form-check-label" for="' . $esc($optId) . '">'
                    . $esc($countryName) . ' <span class="text-muted">+' . $esc($dialCode) . '</span>'
                    . '</label>'
                    . '</div>';

                $checkIndex++;
            }

            $html .= '</div>'; // card-body
            $html .= '</div>'; // card
            $html .= '</div>'; // col-md-6
        }

        $html .= '</div>'; // row
        $html .= '</div>'; // picker

        // Inline script for search, toggle-all, and continent-toggle logic
        $html .= <<<'SCRIPT'
<script>
(function(){
    const picker = document.currentScript.previousElementSibling;
    if (!picker) return;

    const search = picker.querySelector('.j2c-phone-countries-search');
    const masterBtn = picker.querySelector('.j2c-phone-master-toggle');
    const continentBtns = picker.querySelectorAll('.j2c-phone-continent-toggle');
    const countryChecks = picker.querySelectorAll('.j2c-phone-country-check');

    // Search filter
    if (search) {
        search.addEventListener('input', function() {
            const q = search.value.toLowerCase();
            picker.querySelectorAll('.j2c-phone-continent').forEach(function(cont) {
                let anyVisible = false;
                cont.querySelectorAll('.j2c-phone-country-item').forEach(function(item) {
                    const match = !q || item.dataset.name.indexOf(q) !== -1 || item.dataset.code.indexOf(q) !== -1 || item.dataset.iso.indexOf(q) !== -1;
                    item.style.display = match ? '' : 'none';
                    if (match) anyVisible = true;
                });
                cont.style.display = anyVisible ? '' : 'none';
            });
        });
    }

    // Master toggle all
    if (masterBtn) {
        masterBtn.addEventListener('click', function() {
            const allChecked = Array.from(countryChecks).every(function(c) { return c.checked; });
            countryChecks.forEach(function(c) { c.checked = !allChecked; });
        });
    }

    // Continent toggle
    continentBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const continent = btn.dataset.continent;
            const items = picker.querySelectorAll('.j2c-phone-country-check[data-continent="' + continent + '"]');
            const allChecked = Array.from(items).every(function(c) { return c.checked; });
            items.forEach(function(c) { c.checked = !allChecked; });
        });
    });
})();
</script>
SCRIPT;

        return $html;
    }
}
