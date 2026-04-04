<?php

/**
 * @package     J2Commerce
 * @subpackage  plg_system_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Plugin\System\J2Commerce\Helper;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

class LeafletMapHelper
{
    public static function loadMap(string $address, string $mapId = 'leaflet-map'): bool
    {
        $address = trim($address);

        if ($address === '') {
            return false;
        }

        $params = self::getPluginParams();
        $email  = self::getNominatimEmail($params);
        $coords = GeocodingHelper::geocode($address, $email);

        if ($coords === null) {
            return false;
        }

        return self::registerMapAssets($coords, $address, $mapId, $params);
    }

    public static function loadMapStructured(
        string $street,
        string $city,
        string $state = '',
        string $zip = '',
        string $country = '',
        string $mapId = 'leaflet-map'
    ): bool {
        $params = self::getPluginParams();
        $email  = self::getNominatimEmail($params);
        $coords = GeocodingHelper::geocodeStructured($street, $city, $state, $zip, $country, $email);

        if ($coords === null) {
            return false;
        }

        $displayAddress = implode(', ', array_filter([$street, $city, $state, $zip, $country]));

        return self::registerMapAssets($coords, $displayAddress, $mapId, $params);
    }

    private static function registerMapAssets(array $coords, string $address, string $mapId, Registry $params): bool
    {
        $document = Factory::getApplication()->getDocument();
        $wa       = $document->getWebAssetManager();

        $wa->registerAndUseStyle('com_j2commerce.vendor.leaflet.css', 'media/com_j2commerce/vendor/leaflet/css/leaflet.css');
        $wa->registerAndUseStyle('com_j2commerce.leaflet-custom.css', 'media/com_j2commerce/css/site/leaflet-custom.css');
        $wa->registerAndUseScript('com_j2commerce.vendor.leaflet', 'media/com_j2commerce/vendor/leaflet/js/leaflet.js', [], ['defer' => false]);
        $wa->registerAndUseScript('com_j2commerce.map-init', 'media/com_j2commerce/js/site/map-init.js', [], ['defer' => true]);

        $document->addScriptOptions('com_j2commerce.leafletmap', [
            'maps' => [
                $mapId => [
                    'lat'          => $coords['lat'],
                    'lng'          => $coords['lng'],
                    'zoom'         => (int) $params->get('leaflet_map_zoom', 15),
                    'height'       => (int) $params->get('leaflet_map_height', 250),
                    'address'      => $address,
                    'tileProvider' => $params->get('leaflet_tile_provider', 'osm'),
                ],
            ],
        ]);

        return true;
    }

    private static function getPluginParams(): Registry
    {
        $plugin = PluginHelper::getPlugin('system', 'j2commerce');

        return new Registry($plugin->params ?? '{}');
    }

    /** Falls back to J2Commerce admin email if leaflet-specific email is empty. */
    private static function getNominatimEmail(Registry $params): string
    {
        $email = trim((string) $params->get('leaflet_nominatim_email', ''));

        if ($email !== '') {
            return $email;
        }

        try {
            $adminEmail = trim((string) J2CommerceHelper::config()->get('admin_email', ''));
            $firstEmail = trim(explode(',', $adminEmail)[0]);

            return filter_var($firstEmail, FILTER_VALIDATE_EMAIL) ? $firstEmail : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
