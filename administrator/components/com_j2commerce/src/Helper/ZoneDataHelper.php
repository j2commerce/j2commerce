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

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Zone corrections for stores seeded by an older release; rows are never deleted or renumbered, because addresses, order infos and geozone rules reference them by id. */
final class ZoneDataHelper
{
    // Keyed by the first release that ships the entries, so each runs on one update only and a
    // zone the store later deletes or re-enables stays that way.
    //   rename: [zone id, country id, zone code, names shipped for the row, corrected name]
    //   retire: [zone id, country id, zone code, names shipped for the row] — disabled, never deleted
    //   add:    [seed id, country id, zone code, name]
    private const CORRECTIONS = [
        '6.6.6' => [
            'rename' => [
                [3878, 105, 'FC', ['Forl'], 'Forlì-Cesena'],
                [3863, 105, 'CI', ['Carbonia-Iglesias'], 'Sulcis Iglesiente'],
                [3904, 105, 'OT', ['Olbia-Tempio'], 'Gallura Nord-Est Sardegna'],
            ],
            'retire' => [],
            'add'    => [
                [4016, 101, 'ALB', 'Alborz'],
            ],
        ],
    ];

    /** @return array{renamed: int, retired: int, added: int, kept: int} — an empty $previousVersion applies every release's entries. */
    public static function apply(DatabaseInterface $db, string $previousVersion): array
    {
        $result = ['renamed' => 0, 'retired' => 0, 'added' => 0, 'kept' => 0];

        foreach (self::CORRECTIONS as $since => $entries) {
            if ($previousVersion !== '' && version_compare($previousVersion, $since, '>=')) {
                continue;
            }

            foreach ($entries['rename'] as [$id, $countryId, $code, $shipped, $name]) {
                $row = self::loadZone($db, $id);

                if ($row === null || $row->zone_name === $name) {
                    continue;
                }

                if (self::isShipped($row, $id, $countryId, $code, $shipped)) {
                    self::setZoneName($db, $id, $name);
                    $result['renamed']++;
                } else {
                    $result['kept']++;
                }
            }

            foreach ($entries['retire'] as [$id, $countryId, $code, $shipped]) {
                $row = self::loadZone($db, $id);

                if ($row === null || (int) $row->enabled === 0) {
                    continue;
                }

                if (self::isShipped($row, $id, $countryId, $code, $shipped)) {
                    self::disableZone($db, $id);
                    $result['retired']++;
                } else {
                    $result['kept']++;
                }
            }

            foreach ($entries['add'] as [$id, $countryId, $code, $name]) {
                $result['added'] += self::addZone($db, $id, $countryId, $code, $name);
            }
        }

        return $result;
    }

    private static function loadZone(DatabaseInterface $db, int $id): ?object
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName(['country_id', 'zone_code', 'zone_name', 'enabled']))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /** @param list<string> $shipped */
    private static function isShipped(object $row, int $id, int $countryId, string $code, array $shipped): bool
    {
        // Compared in PHP: the column collation would treat a case or accent edit as unchanged.
        if ((int) $row->country_id === $countryId && $row->zone_code === $code && \in_array($row->zone_name, $shipped, true)) {
            return true;
        }

        Log::add(
            \sprintf('Zone %d (%s, "%s") no longer matches the shipped row; its correction was not applied.', $id, $row->zone_code, $row->zone_name),
            Log::INFO,
            'com_j2commerce'
        );

        return false;
    }

    private static function setZoneName(DatabaseInterface $db, int $id, string $name): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_zones'))
            ->set($db->quoteName('zone_name') . ' = :name')
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':name', $name)
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    private static function disableZone(DatabaseInterface $db, int $id): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__j2commerce_zones'))
            ->set($db->quoteName('enabled') . ' = 0')
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }

    private static function addZone(DatabaseInterface $db, int $id, int $countryId, string $code, string $name): int
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('country_id') . ' = :country')
            ->extendWhere('AND', [
                $db->quoteName('zone_code') . ' = :code',
                $db->quoteName('zone_name') . ' = :name',
            ], 'OR')
            ->bind(':country', $countryId, ParameterType::INTEGER)
            ->bind(':code', $code)
            ->bind(':name', $name);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('j2commerce_zone_id'))
            ->from($db->quoteName('#__j2commerce_zones'))
            ->where($db->quoteName('j2commerce_zone_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $row = (object) [
            'country_id' => $countryId,
            'zone_code'  => $code,
            'zone_name'  => $name,
            'enabled'    => 1,
            'ordering'   => 0,
        ];

        // Keep the seed's id where it is free so the row matches a fresh install; otherwise let
        // auto-increment pick one rather than skip the zone.
        if ($db->setQuery($query)->loadResult() === null) {
            $row->j2commerce_zone_id = $id;
        }

        $db->insertObject('#__j2commerce_zones', $row);

        return 1;
    }
}
