<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Controller;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageCopyService;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageManifestService;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ImageRebuildService;
use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Image pipeline: copySourceImages, listImageDirectories, createImageDirectory,
 * scanImages, rebuildCategoryImages, updateCategoryImagePaths, getImageSettings,
 * writeRebuildLog, getLatestRebuildLog, saveImageFolder, getSavedImageFolder,
 * deleteImageDirectories, scanOptimizeDirectory, optimizeBatch,
 * scanImagePathTables, updateImagePathTables.
 */
class ImageController extends BaseController
{
    protected $default_view = 'images';

    public function display($cachable = false, $urlparams = []): static
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'error');
            $this->setRedirect(Route::_('index.php', false));
            return $this;
        }

        return parent::display($cachable, $urlparams);
    }

    /**
     * POST: bulk-copies images from the source store's image root into the J2Commerce tree.
     */
    public function copySourceImages(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input   = Factory::getApplication()->getInput();
            $srcPath = $input->getString('source_path', '');

            $logger  = new MigrationLogger();
            $service = new ImageCopyService($logger);
            $this->sendJson(['success' => true, 'data' => $service->copyFromSource($srcPath)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::copySourceImages', $e);
        }
    }

    /**
     * GET: returns the folder tree under images/ for UI directory picker.
     */
    public function listImageDirectories(): void
    {
        $this->enforceAcl();

        try {
            $input  = Factory::getApplication()->getInput();
            $root   = $input->getString('root', '');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->listImageDirectories($root)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::listImageDirectories', $e);
        }
    }

    /**
     * POST: creates a new directory inside the image tree.
     */
    public function createImageDirectory(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $parent = $input->getString('parent', '');
            $name   = $input->getString('name', '');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->createDirectory($parent, $name)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::createImageDirectory', $e);
        }
    }

    /**
     * GET: scans all products and builds an image manifest for the rebuild pipeline.
     */
    public function scanImages(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->scanProducts()]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::scanImages', $e);
        }
    }

    /**
     * POST: per-category batched image rebuild pass.
     */
    public function rebuildCategoryImages(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input    = Factory::getApplication()->getInput();
            $category = $input->getInt('category', 0);
            $dir      = $input->getString('dir', '');
            $offset   = $input->getInt('offset', 0);
            $batch    = $input->getInt('batch', 20);

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->rebuildBatch($category, $dir, $offset, $batch)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::rebuildCategoryImages', $e);
        }
    }

    /**
     * POST: rewrites DB image paths for a given category and target directory.
     */
    public function updateCategoryImagePaths(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input    = Factory::getApplication()->getInput();
            $category = $input->getInt('category', 0);
            $dir      = $input->getString('dir', '');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->updateImagePaths($category, $dir)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::updateCategoryImagePaths', $e);
        }
    }

    /**
     * GET: returns thumbnail and tiny dimension defaults from component config.
     */
    public function getImageSettings(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->getImageSettings()]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::getImageSettings', $e);
        }
    }

    /**
     * POST: persists a rebuild report to disk.
     */
    public function writeRebuildLog(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input              = Factory::getApplication()->getInput();
            $category           = $input->getInt('category', 0);
            $baseDir            = $input->getString('baseDir', '');
            $totalProcessed     = $input->getInt('totalProcessed', 0);
            $totalAlreadyOptimized = $input->getInt('totalAlreadyOptimized', 0);
            $totalSkipped       = $input->getInt('totalSkipped', 0);
            $totalErrors        = $input->getInt('totalErrors', 0);
            $allSkipDetails     = $input->get('allSkipDetails', [], 'array');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->writeRebuildLog(
                $category,
                $baseDir,
                $totalProcessed,
                $totalAlreadyOptimized,
                $totalSkipped,
                $totalErrors,
                $allSkipDetails
            )]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::writeRebuildLog', $e);
        }
    }

    /**
     * GET: recalls the most recent rebuild report for a category.
     */
    public function getLatestRebuildLog(): void
    {
        $this->enforceAcl();

        try {
            $input    = Factory::getApplication()->getInput();
            $category = $input->getInt('category', 0);

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->getLatestRebuildLog($category)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::getLatestRebuildLog', $e);
        }
    }

    /**
     * POST: persists the user-selected image folder to session/config.
     */
    public function saveImageFolder(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $folder = $input->getString('folder', '');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->saveImageFolder($folder)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::saveImageFolder', $e);
        }
    }

    /**
     * GET: recalls the user-selected image folder.
     */
    public function getSavedImageFolder(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->getSavedImageFolder()]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::getSavedImageFolder', $e);
        }
    }

    /**
     * POST: bulk-deletes a list of image directories.
     */
    public function deleteImageDirectories(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input = Factory::getApplication()->getInput();
            $dirs  = $input->get('dirs', [], 'array');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->deleteImageDirectories($dirs)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::deleteImageDirectories', $e);
        }
    }

    /**
     * GET: pre-optimisation scan — counts and previews images in the directory.
     */
    public function scanOptimizeDirectory(): void
    {
        $this->enforceAcl();

        try {
            $input  = Factory::getApplication()->getInput();
            $dir    = $input->getString('dir', '');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->scanOptimizeDirectory($dir)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::scanOptimizeDirectory', $e);
        }
    }

    /**
     * POST: optimisation pass — resizes/compresses a batch of images.
     */
    public function optimizeBatch(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $dir    = $input->getString('dir', '');
            $offset = $input->getInt('offset', 0);
            $batch  = $input->getInt('batch', 20);
            $dims   = $input->get('dims', [], 'array');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->optimizeBatch($dir, $offset, $batch, $dims)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::optimizeBatch', $e);
        }
    }

    /**
     * GET: discovers every table that stores image paths for cross-table path rewrite.
     */
    public function scanImagePathTables(): void
    {
        $this->enforceAcl();

        try {
            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->scanImagePathTables()]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::scanImagePathTables', $e);
        }
    }

    /**
     * POST: rewrites image paths across all specified tables.
     */
    public function updateImagePathTables(): void
    {
        $this->enforceAcl();
        $this->enforceToken();

        try {
            $input  = Factory::getApplication()->getInput();
            $tables = $input->get('tables', [], 'array');

            $logger  = new MigrationLogger();
            $service = new ImageRebuildService($this->getDatabase(), $logger);
            $this->sendJson(['success' => true, 'data' => $service->updateImagePathTables($tables)]);
        } catch (\Throwable $e) {
            $this->handleError('ImageController::updateImagePathTables', $e);
        }
    }

    private function enforceAcl(): void
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || !$user->authorise('core.manage', 'com_j2commercemigrator')) {
            $this->sendJson(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 'category' => 'forbidden']);
        }
    }

    private function enforceToken(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(['success' => false, 'error' => Text::_('JINVALID_TOKEN'), 'category' => 'csrf']);
        }
    }

    private function handleError(string $context, \Throwable $e): void
    {
        (new MigrationLogger())->error($context . ': ' . $e->getMessage());

        if (\defined('JDEBUG') && JDEBUG) {
            $this->sendJson(['success' => false, 'error' => $e->getMessage()]);
        } else {
            $this->sendJson(['success' => false, 'error' => Text::_('COM_J2COMMERCEMIGRATOR_ERR_GENERIC')]);
        }
    }

    private function sendJson(array $data): void
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
