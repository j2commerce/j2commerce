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

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class GeocodingHelper
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /** Cache duration: 90 days in seconds. Set to 0 for permanent. */
    private const CACHE_TTL = 7776000;

    /** @return array{lat: float, lng: float}|null */
    public static function geocode(string $address, string $nominatimEmail = ''): ?array
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        $cacheKey = self::normalizeAddress($address);
        $cached   = self::getFromCache($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $result = self::callNominatim($address, $nominatimEmail);

        if ($result !== null) {
            self::saveToCache($cacheKey, $address, $result['lat'], $result['lng']);
        }

        return $result;
    }

    /** @return array{lat: float, lng: float}|null */
    public static function geocodeStructured(
        string $street,
        string $city,
        string $state = '',
        string $zip = '',
        string $country = '',
        string $nominatimEmail = ''
    ): ?array {
        $parts       = array_filter([$street, $city, $state, $zip, $country]);
        $fullAddress = implode(', ', $parts);

        $result = self::geocode($fullAddress, $nominatimEmail);

        if ($result !== null) {
            return $result;
        }

        // Fallback: city, state, zip, country (skip street)
        $cityParts   = array_filter([$city, $state, $zip, $country]);
        $cityAddress = implode(', ', $cityParts);

        if ($cityAddress !== '' && $cityAddress !== $fullAddress) {
            return self::geocode($cityAddress, $nominatimEmail);
        }

        return null;
    }

    private static function normalizeAddress(string $address): string
    {
        $normalized = mb_strtolower($address, 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = preg_replace('/[.,]+$/', '', $normalized);

        return md5(trim($normalized));
    }

    /** @return array{lat: float, lng: float}|null */
    private static function getFromCache(string $cacheKey): ?array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select($db->quoteName(['latitude', 'longitude', 'created']))
            ->from($db->quoteName('#__j2commerce_geocode_cache'))
            ->where($db->quoteName('address_hash') . ' = :hash')
            ->bind(':hash', $cacheKey);

        $db->setQuery($query);
        $row = $db->loadObject();

        if ($row === null) {
            return null;
        }

        if (self::CACHE_TTL > 0) {
            $created = strtotime($row->created);

            if ((time() - $created) > self::CACHE_TTL) {
                self::deleteFromCache($cacheKey);

                return null;
            }
        }

        return [
            'lat' => (float) $row->latitude,
            'lng' => (float) $row->longitude,
        ];
    }

    /** @return array{lat: float, lng: float}|null */
    private static function callNominatim(string $address, string $nominatimEmail): ?array
    {
        self::enforceRateLimit();

        $userAgent = 'J2Commerce-LeafletMap/6.0 (' . ($nominatimEmail ?: 'admin@example.com') . ')';

        $params = [
            'q'              => $address,
            'format'         => 'jsonv2',
            'addressdetails' => 0,
            'limit'          => 1,
        ];

        $url = self::NOMINATIM_URL . '?' . http_build_query($params);

        try {
            $http     = HttpFactory::getHttp([
                'transport.curl' => [
                    \CURLOPT_SSL_VERIFYPEER => false,
                    \CURLOPT_SSL_VERIFYHOST => 0,
                ],
            ]);
            $response = $http->get($url, [
                'User-Agent' => $userAgent,
                'Accept'     => 'application/json',
            ]);

            if ($response->code !== 200) {
                Log::add(
                    'Nominatim API returned HTTP ' . $response->code . ' for: ' . $address,
                    Log::WARNING,
                    'plg_system_j2commerce'
                );

                return null;
            }

            $data = json_decode($response->body, true);

            if (empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) {
                Log::add(
                    'Nominatim returned no results for: ' . $address,
                    Log::INFO,
                    'plg_system_j2commerce'
                );

                return null;
            }

            return [
                'lat' => (float) $data[0]['lat'],
                'lng' => (float) $data[0]['lon'],
            ];
        } catch (\Exception $e) {
            Log::add(
                'Nominatim geocoding error: ' . $e->getMessage(),
                Log::ERROR,
                'plg_system_j2commerce'
            );

            return null;
        }
    }

    private static function saveToCache(string $cacheKey, string $address, float $lat, float $lng): void
    {
        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $now = Factory::getDate()->toSql();

        $record = (object) [
            'address_hash' => $cacheKey,
            'address_text' => mb_substr($address, 0, 500),
            'latitude'     => $lat,
            'longitude'    => $lng,
            'created'      => $now,
        ];

        try {
            $db->insertObject('#__j2commerce_geocode_cache', $record);
        } catch (\Exception $e) {
            Log::add(
                'Geocode cache insert skipped (likely duplicate): ' . $e->getMessage(),
                Log::DEBUG,
                'plg_system_j2commerce'
            );
        }
    }

    private static function deleteFromCache(string $cacheKey): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->delete($db->quoteName('#__j2commerce_geocode_cache'))
            ->where($db->quoteName('address_hash') . ' = :hash')
            ->bind(':hash', $cacheKey);

        $db->setQuery($query);
        $db->execute();
    }

    private static function enforceRateLimit(): void
    {
        $lockFile = Factory::getApplication()->get('tmp_path') . '/nominatim_last_call.lock';
        $lastCall = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;
        $now      = time();

        if (($now - $lastCall) < 1) {
            usleep(1100000);
        }

        file_put_contents($lockFile, (string) time());
    }
}
