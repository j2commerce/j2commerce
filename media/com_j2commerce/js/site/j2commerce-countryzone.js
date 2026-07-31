/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

(function (window, document) {
    'use strict';

    const DEFAULTS = {
        countryName: 'country_id',
        zoneName: 'zone_id',
        baseUrl: 'index.php?option=com_j2commerce',
        countryId: '',
        zoneId: '',
    };

    function text(key) {
        return window.Joomla && window.Joomla.Text ? window.Joomla.Text._(key) : key;
    }

    /** Server returns <option> markup; J2CommerceDom.parse strips any script before adoption. */
    function adoptOptions(select, html) {
        select.replaceChildren(window.J2CommerceDom.parse(html));
    }

    function fetchOptions(url) {
        return fetch(url).then(response => {
            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            return response.text();
        });
    }

    /**
     * Wire a country select to a zone select inside `container`.
     *
     * Options are read from the container's data attributes (data-country-name,
     * data-zone-name, data-base-url, data-country-id, data-zone-id) and may be
     * overridden by the `overrides` argument.
     */
    function init(container, overrides) {
        if (!container) {
            return;
        }

        const data = container.dataset;
        const config = Object.assign({}, DEFAULTS, {
            countryName: data.countryName || DEFAULTS.countryName,
            zoneName: data.zoneName || DEFAULTS.zoneName,
            baseUrl: data.baseUrl || DEFAULTS.baseUrl,
            countryId: data.countryId || '',
            zoneId: data.zoneId || '',
        }, overrides || {});

        const countrySelect = container.querySelector(`select[name="${config.countryName}"]`);
        const zoneSelect = container.querySelector(`select[name="${config.zoneName}"]`);

        if (!countrySelect) {
            return;
        }

        const separator = config.baseUrl.includes('?') ? '&' : '?';
        // Fall back to the rendered selection so a failed form submission keeps its values.
        const savedCountryId = config.countryId || countrySelect.value || '';
        const savedZoneId = config.zoneId || (zoneSelect ? zoneSelect.value : '') || '';

        function placeholder(select, key) {
            select.replaceChildren(new Option(text(key), ''));
        }

        function loadZones(countryId, selectedZoneId) {
            placeholder(zoneSelect, 'COM_J2COMMERCE_LOADING');
            zoneSelect.disabled = true;

            if (!countryId || countryId === '0') {
                placeholder(zoneSelect, 'COM_J2COMMERCE_SELECT_ZONE');
                zoneSelect.disabled = false;

                return;
            }

            let url = `${config.baseUrl}${separator}task=ajax.getZones&country_id=${encodeURIComponent(countryId)}`;

            if (selectedZoneId && selectedZoneId !== '0') {
                url += `&zone_id=${encodeURIComponent(selectedZoneId)}`;
            }

            fetchOptions(url)
                .then(html => {
                    adoptOptions(zoneSelect, html);
                    zoneSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading zones:', error);
                    placeholder(zoneSelect, 'COM_J2COMMERCE_SELECT_ZONE');
                    zoneSelect.disabled = false;
                });
        }

        let countryUrl = `${config.baseUrl}${separator}task=ajax.getCountries`;

        if (savedCountryId && savedCountryId !== '0') {
            countryUrl += `&country_id=${encodeURIComponent(savedCountryId)}`;
        }

        fetchOptions(countryUrl)
            .then(html => {
                adoptOptions(countrySelect, html);

                if (countrySelect.value && zoneSelect) {
                    loadZones(countrySelect.value, savedZoneId);
                }
            })
            .catch(error => console.error('Error loading countries:', error));

        if (!zoneSelect) {
            return;
        }

        countrySelect.addEventListener('change', () => loadZones(countrySelect.value, ''));
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-j2c-countryzone]').forEach(container => init(container));
    });

    window.J2CommerceCountryZone = { init };
})(window, document);
