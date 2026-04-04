/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const config = Joomla.getOptions('com_j2commerce.leafletmap');

    if (!config || !config.maps) {
        return;
    }

    // Set default marker icon path to self-hosted images
    const leafletScript = document.querySelector('script[src*="com_j2commerce"][src*="leaflet"]');
    const basePath = leafletScript
        ? leafletScript.src.replace(/\/vendor\/leaflet\/js\/leaflet\.js.*$/, '')
        : '/media/com_j2commerce';
    L.Icon.Default.imagePath = basePath + '/images/leaflet/';

    const tileProviders = {
        osm: {
            url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        },
        osmhot: {
            url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles by <a href="https://www.hotosm.org/">HOT</a>',
            maxZoom: 19
        }
    };

    const escapeHtml = function (str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    Object.keys(config.maps).forEach(function (mapId) {
        const mapConfig = config.maps[mapId];
        const container = document.getElementById(mapId);

        if (!container) {
            console.warn('[LeafletMap] Container not found: #' + mapId);
            return;
        }

        // Ensure container has a height
        const height = parseInt(mapConfig.height, 10) || 250;
        if (!container.style.height && !container.offsetHeight) {
            container.style.height = height + 'px';
        }

        const lat  = parseFloat(mapConfig.lat);
        const lng  = parseFloat(mapConfig.lng);
        const zoom = parseInt(mapConfig.zoom, 10) || 15;

        if (isNaN(lat) || isNaN(lng)) {
            console.warn('[LeafletMap] Invalid coordinates for: #' + mapId);
            return;
        }

        const providerKey = mapConfig.tileProvider || 'osm';
        const provider    = tileProviders[providerKey] || tileProviders.osm;

        const map = L.map(mapId, {
            scrollWheelZoom: false,
            dragging: !L.Browser.mobile
        }).setView([lat, lng], zoom);

        L.tileLayer(provider.url, {
            attribution: provider.attribution,
            maxZoom: provider.maxZoom
        }).addTo(map);

        const marker = L.marker([lat, lng]).addTo(map);

        if (mapConfig.address) {
            const escapedAddress = escapeHtml(mapConfig.address).replace(/,\s*/g, '<br>');
            marker.bindPopup('<strong>Delivery Address</strong><br>' + escapedAddress);
        }

        // Fix rendering issues in hidden/tabbed containers
        setTimeout(function () {
            map.invalidateSize();
        }, 250);

        // Store references on the container for external access
        container.leafletMap    = map;
        container.leafletMarker = marker;
    });

    // Handle Bootstrap 5 tab events -- invalidate map size when tab becomes visible
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            document.querySelectorAll('.leaflet-map-container').forEach(function (el) {
                if (el.leafletMap) {
                    el.leafletMap.invalidateSize();
                }
            });
        });
    });
});
