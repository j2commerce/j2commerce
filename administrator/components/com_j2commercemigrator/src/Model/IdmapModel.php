<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\IdmapRepository;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class IdmapModel extends BaseDatabaseModel
{
    private function repo(): IdmapRepository
    {
        return new IdmapRepository($this->getDatabase());
    }

    public function lookupTarget(string $adapter, string $entity, int $sourceId): ?int
    {
        return $this->repo()->lookupTarget($adapter, $entity, $sourceId);
    }

    public function lookupSource(string $adapter, string $entity, int $targetId): ?int
    {
        return $this->repo()->lookupSource($adapter, $entity, $targetId);
    }

    public function record(string $adapter, string $entity, int $sourceId, int $targetId): void
    {
        $this->repo()->record($adapter, $entity, $sourceId, $targetId);
    }

    public function dropForAdapter(string $adapter): void
    {
        $this->repo()->dropForAdapter($adapter);
    }

    public function dropAll(): void
    {
        $this->repo()->dropAll();
    }
}
