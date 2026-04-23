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

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageCopyService;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageRebuildService;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Image pipeline model — wraps the image services (copy, rebuild) behind a single
 * model boundary for the Images wizard step.
 */
class ImageModel extends BaseDatabaseModel
{
    private function rebuildService(): ImageRebuildService
    {
        return new ImageRebuildService($this->getDatabase(), new MigrationLogger());
    }

    // — Directory helpers —

    public function listImageDirectories(string $root = 'images'): array
    {
        return $this->rebuildService()->listImageDirectories($root);
    }

    public function createDirectory(string $parentDir, string $newDirName): array
    {
        return $this->rebuildService()->createDirectory($parentDir, $newDirName);
    }

    public function deleteImageDirectories(array $folders): array
    {
        return $this->rebuildService()->deleteImageDirectories($folders);
    }

    // — Folder preference —

    public function getSavedImageFolder(): array
    {
        return $this->rebuildService()->getSavedImageFolder();
    }

    public function saveImageFolder(string $folder): array
    {
        return $this->rebuildService()->saveImageFolder($folder);
    }

    // — Source copy (mode B/C) —

    public function copyFromSource(string $sourcePath): array
    {
        return (new ImageCopyService(new MigrationLogger()))->copyFromSource($sourcePath);
    }

    // — Manifest + scan —

    public function scanProducts(): array
    {
        return $this->rebuildService()->scanProducts();
    }

    // — Rebuild pipeline —

    public function rebuildBatch(int $categoryId, string $baseDir, int $offset = 0, int $batchSize = 10): array
    {
        return $this->rebuildService()->rebuildBatch($categoryId, $baseDir, $offset, $batchSize);
    }

    public function updateImagePaths(int $categoryId, string $baseDir): array
    {
        return $this->rebuildService()->updateImagePaths($categoryId, $baseDir);
    }

    public function getImageSettings(): array
    {
        return $this->rebuildService()->getImageSettings();
    }

    // — Rebuild log —

    public function writeRebuildLog(
        int $categoryId,
        string $baseDir,
        int $totalProcessed,
        int $totalAlreadyOptimized,
        int $totalSkipped,
        int $totalErrors,
        array $allSkipDetails
    ): array {
        return $this->rebuildService()->writeRebuildLog(
            $categoryId,
            $baseDir,
            $totalProcessed,
            $totalAlreadyOptimized,
            $totalSkipped,
            $totalErrors,
            $allSkipDetails
        );
    }

    public function getLatestRebuildLog(int $categoryId): array
    {
        return $this->rebuildService()->getLatestRebuildLog($categoryId);
    }

    // — Optimisation pass —

    public function scanOptimizeDirectory(string $directory): array
    {
        return $this->rebuildService()->scanOptimizeDirectory($directory);
    }

    public function optimizeBatch(string $directory, int $offset, int $batchSize, array $dimensions): array
    {
        return $this->rebuildService()->optimizeBatch($directory, $offset, $batchSize, $dimensions);
    }

    // — Cross-table path rewrite —

    public function scanImagePathTables(): array
    {
        return $this->rebuildService()->scanImagePathTables();
    }

    public function updateImagePathTables(array $tables): array
    {
        return $this->rebuildService()->updateImagePathTables($tables);
    }
}
