<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Service;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class IdmapRepository
{
    public function __construct(private DatabaseInterface $db) {}

    public function lookupTarget(string $adapter, string $entity, int $sourceId): ?int
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('target_id'))
            ->from($this->db->quoteName('#__j2commerce_migrator_idmap'))
            ->where($this->db->quoteName('adapter') . ' = :adapter')
            ->where($this->db->quoteName('entity') . ' = :entity')
            ->where($this->db->quoteName('source_id') . ' = :source_id')
            ->bind(':adapter', $adapter)
            ->bind(':entity', $entity)
            ->bind(':source_id', $sourceId, ParameterType::INTEGER);

        $result = $this->db->setQuery($query)->loadResult();
        return $result !== null ? (int) $result : null;
    }

    public function lookupSource(string $adapter, string $entity, int $targetId): ?int
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('source_id'))
            ->from($this->db->quoteName('#__j2commerce_migrator_idmap'))
            ->where($this->db->quoteName('adapter') . ' = :adapter')
            ->where($this->db->quoteName('entity') . ' = :entity')
            ->where($this->db->quoteName('target_id') . ' = :target_id')
            ->bind(':adapter', $adapter)
            ->bind(':entity', $entity)
            ->bind(':target_id', $targetId, ParameterType::INTEGER);

        $result = $this->db->setQuery($query)->loadResult();
        return $result !== null ? (int) $result : null;
    }

    public function record(string $adapter, string $entity, int $sourceId, int $targetId): void
    {
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__j2commerce_migrator_idmap'))
            ->columns($this->db->quoteName(['adapter', 'entity', 'source_id', 'target_id']))
            ->values(':adapter, :entity, :source_id, :target_id')
            ->bind(':adapter', $adapter)
            ->bind(':entity', $entity)
            ->bind(':source_id', $sourceId, ParameterType::INTEGER)
            ->bind(':target_id', $targetId, ParameterType::INTEGER);

        try {
            $this->db->setQuery($query)->execute();
        } catch (\Throwable) {
            // Duplicate — ignore (UNIQUE KEY uq_idmap_lookup covers adapter+entity+source_id)
        }
    }

    public function dropForAdapter(string $adapter): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__j2commerce_migrator_idmap'))
            ->where($this->db->quoteName('adapter') . ' = :adapter')
            ->bind(':adapter', $adapter);

        $this->db->setQuery($query)->execute();
    }

    public function dropAll(): void
    {
        $this->db->truncateTable('#__j2commerce_migrator_idmap');
    }
}
