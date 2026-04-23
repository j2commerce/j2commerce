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

/**
 * Model for the source-PK → target-PK cross-reference (idmap) table.
 */
class IdmapModel extends BaseDatabaseModel
{
    private function repo(): IdmapRepository
    {
        return new IdmapRepository($this->getDatabase());
    }

    public function lookupTarget(string $adapter, string $sourceTable, string $sourcePk): ?string
    {
        return $this->repo()->lookupTarget($adapter, $sourceTable, $sourcePk);
    }

    public function lookupSource(string $adapter, string $targetTable, string $targetPk): ?string
    {
        return $this->repo()->lookupSource($adapter, $targetTable, $targetPk);
    }

    public function record(string $adapter, string $sourceTable, string $sourcePk, string $targetTable, string $targetPk): void
    {
        $this->repo()->record($adapter, $sourceTable, $sourcePk, $targetTable, $targetPk);
    }

    public function dropForAdapter(string $adapter): void
    {
        $this->repo()->dropForAdapter($adapter);
    }

    public function dropAll(): void
    {
        $this->repo()->dropAll();
    }

    /** Migrates rows from the legacy plugin idmap table (one-time import). */
    public function migrateFromLegacy(): int
    {
        return $this->repo()->migrateFromLegacy();
    }
}
