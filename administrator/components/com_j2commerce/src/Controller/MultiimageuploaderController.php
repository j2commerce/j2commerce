<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Controller;

use J2Commerce\Component\J2commerce\Administrator\Helper\ImageProcessorHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

\defined('_JEXEC') or die;

class MultiimageuploaderController extends BaseController
{
    /** Default task — handle file upload. */
    public function upload(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $input = $this->app->getInput();
        $file  = $input->files->get('file', [], 'array');

        if (empty($file['name'])) {
            $this->sendJson(false, 'No file uploaded');
            return;
        }

        if (!(new MediaHelper())->canUpload($file)) {
            $this->sendJson(false, 'File type not allowed');
            return;
        }

        $componentParams = ComponentHelper::getParams('com_j2commerce');
        $directory  = $this->sanitizePath($input->getString('path', 'images'));
        $uploadPath = JPATH_ROOT . '/' . $directory;

        if (!is_dir($uploadPath)) {
            Folder::create($uploadPath);
        }

        if (!$this->isPathWithinRoot($uploadPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension = strtolower(File::getExt($file['name']));
        $safeName  = File::makeSafe($file['name']);
        $baseName  = File::stripExt($safeName);
        $fileName  = $baseName . '_' . uniqid() . '.' . $extension;
        $filePath  = $uploadPath . '/' . $fileName;

        if (!File::upload($file['tmp_name'], $filePath)) {
            $this->sendJson(false, 'Failed to save file');
            return;
        }

        // Process main image: resize + convert to WebP if enabled
        if (\in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filePath = $this->processMainImage($filePath, $extension, $componentParams);
            $fileName = basename($filePath);
        }

        $width  = 0;
        $height = 0;

        if (\in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo) {
                $width  = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        $relativePath = $directory . '/' . $fileName;
        $siteRoot     = Uri::root();

        $result = [
            'name'      => $fileName,
            'path'      => $relativePath,
            'url'       => $siteRoot . $relativePath,
            'thumb_url' => $siteRoot . $relativePath,
            'width'     => $width,
            'height'    => $height,
        ];

        $autoThumbnail = $input->getString('autoThumbnail', '1');

        if ($autoThumbnail === '1' && \in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $thumbTiny = $this->generateThumbAndTiny($filePath, $directory, $fileName, $siteRoot, $componentParams);
            $result    = array_merge($result, $thumbTiny);
        }

        $this->sendJson(true, '', $result);
    }

    /** List files and folders in a directory. */
    public function listFiles(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $input     = $this->app->getInput();
        $directory = $this->sanitizePath($input->getString('path', 'images'));
        $fullPath  = JPATH_ROOT . '/' . $directory;

        if (!is_dir($fullPath)) {
            $this->sendJson(true, '', ['folders' => [], 'files' => [], 'current_path' => $directory]);
            return;
        }

        if (!$this->isPathWithinRoot($fullPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $folders = [];
        foreach (Folder::folders($fullPath) as $folder) {
            $folders[] = $folder;
        }

        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
        $fileMode  = $input->getInt('fileMode', 0);
        $files     = [];

        foreach (Folder::files($fullPath) as $fileName) {
            $ext = strtolower(File::getExt($fileName));
            if (!$fileMode && !\in_array($ext, $imageExts)) {
                continue;
            }

            $filePath     = $fullPath . '/' . $fileName;
            $relativePath = $directory . '/' . $fileName;
            $url          = Uri::root() . $relativePath;
            $width        = 0;
            $height       = 0;

            if (\in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $imageInfo = @getimagesize($filePath);
                if ($imageInfo) {
                    $width  = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }

            $nameNoExt = File::stripExt($fileName);
            $thumbFile = $fullPath . '/thumbs/' . $nameNoExt . '.webp';
            $thumbUrl  = $url;

            if (is_file($thumbFile)) {
                $thumbUrl = Uri::root() . $directory . '/thumbs/' . $nameNoExt . '.webp';
            }

            $files[] = [
                'name'      => $fileName,
                'path'      => $relativePath,
                'url'       => $url,
                'thumb_url' => $thumbUrl,
                'width'     => $width,
                'height'    => $height,
                'size'      => filesize($filePath),
                'extension' => $ext,
            ];
        }

        $this->sendJson(true, '', [
            'folders'      => $folders,
            'files'        => $files,
            'current_path' => $directory,
        ]);
    }

    /** List configured image directories. */
    public function folders(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $componentParams = ComponentHelper::getParams('com_j2commerce');
        $directories     = $componentParams->get('image_directories', [(object) ['directory' => 'images']]);

        if (\is_string($directories)) {
            $directories = json_decode($directories);
        }

        $paths = [];
        foreach ($directories as $dir) {
            if (!empty($dir->directory)) {
                $paths[] = $dir->directory;
            }
        }

        if (empty($paths)) {
            $paths[] = 'images';
        }

        $this->sendJson(true, '', $paths);
    }

    /** Delete a file and its thumb/tiny variants. */
    public function delete(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.delete', 'com_media')) {
            $this->sendJson(false, 'Not authorized to delete files');
            return;
        }

        $input = $this->app->getInput();
        $path  = $this->sanitizePath($input->getString('path', ''));

        if (empty($path) || $path === 'images') {
            $this->sendJson(false, 'No file path provided');
            return;
        }

        $fullPath = JPATH_ROOT . '/' . $path;

        if (!is_file($fullPath)) {
            $this->sendJson(false, 'File not found');
            return;
        }

        if (!$this->isPathWithinRoot($fullPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $dir       = \dirname($path);
        $fileName  = basename($path);
        $nameNoExt = File::stripExt($fileName);

        $thumbPath = JPATH_ROOT . '/' . $dir . '/thumbs/' . $nameNoExt . '.webp';
        $tinyPath  = JPATH_ROOT . '/' . $dir . '/tiny/' . $nameNoExt . '.webp';

        $deleted = File::delete($fullPath);

        if (is_file($thumbPath) && $this->isPathWithinRoot($thumbPath)) {
            File::delete($thumbPath);
        }

        if (is_file($tinyPath) && $this->isPathWithinRoot($tinyPath)) {
            File::delete($tinyPath);
        }

        $this->sendJson($deleted, $deleted ? '' : 'Failed to delete file');
    }

    /** Generate thumb and tiny for an existing image. */
    public function thumbnail(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $input = $this->app->getInput();
        $path  = $this->sanitizePath($input->getString('path', ''));

        if (empty($path) || $path === 'images') {
            $this->sendJson(false, 'No file path provided');
            return;
        }

        $fullPath = JPATH_ROOT . '/' . $path;

        if (!is_file($fullPath)) {
            $this->sendJson(false, 'File not found');
            return;
        }

        if (!$this->isPathWithinRoot($fullPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension = strtolower(File::getExt($path));

        if (!\in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $this->sendJson(false, 'Not a supported image type');
            return;
        }

        $directory       = \dirname($path);
        $fileName        = basename($path);
        $siteRoot        = Uri::root();
        $componentParams = ComponentHelper::getParams('com_j2commerce');

        $result = $this->generateThumbAndTiny($fullPath, $directory, $fileName, $siteRoot, $componentParams);

        $this->sendJson(true, '', $result);
    }

    /** Check how many product images reference a given path. */
    public function checkUsage(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $input = $this->app->getInput();
        $path  = $this->sanitizePath($input->getString('path', ''));

        if (empty($path) || $path === 'images') {
            $this->sendJson(true, '', ['count' => 0]);
            return;
        }

        try {
            $db       = $this->getDatabase();
            $query    = $db->getQuery(true);
            $likePath = '%' . $db->escape($path, true) . '%';

            $query->select('COUNT(*)')
                ->from($db->quoteName('#__j2commerce_productimages'))
                ->extendWhere('AND', [
                    $db->quoteName('main_image') . ' LIKE :path1',
                    $db->quoteName('additional_images') . ' LIKE :path2',
                ], 'OR')
                ->bind(':path1', $likePath)
                ->bind(':path2', $likePath);

            $count = (int) $db->setQuery($query)->loadResult();

            $this->sendJson(true, '', ['count' => $count]);
        } catch (\Exception $e) {
            $this->sendJson(true, '', ['count' => 0]);
        }
    }

    /** Create a new folder inside a configured directory. */
    public function createFolder(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.create', 'com_media')) {
            $this->sendJson(false, 'Not authorized to create folders');
            return;
        }

        $input      = $this->app->getInput();
        $parentDir  = $this->sanitizePath($input->getString('path', 'images'));
        $folderName = $input->getString('name', '');
        $folderName = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($folderName));

        if (empty($folderName)) {
            $this->sendJson(false, 'Invalid folder name');
            return;
        }

        $fullParent = JPATH_ROOT . '/' . $parentDir;

        if (!is_dir($fullParent) || !$this->isPathWithinRoot($fullParent)) {
            $this->sendJson(false, 'Parent directory not found');
            return;
        }

        $newFolderPath = $fullParent . '/' . $folderName;

        if (is_dir($newFolderPath)) {
            $this->sendJson(false, 'Folder already exists');
            return;
        }

        if (!Folder::create($newFolderPath)) {
            $this->sendJson(false, 'Failed to create folder');
            return;
        }

        $this->sendJson(true, '', ['path' => $parentDir . '/' . $folderName]);
    }

    /** Delete an empty folder (ignores thumbs/ and tiny/ subdirectories). */
    public function deleteFolder(): void
    {
        if (!$this->authorize()) {
            return;
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.delete', 'com_media')) {
            $this->sendJson(false, 'Not authorized to delete folders');
            return;
        }

        $input = $this->app->getInput();
        $path  = $this->sanitizePath($input->getString('path', ''));

        if (empty($path) || $path === 'images') {
            $this->sendJson(false, 'Cannot delete this directory');
            return;
        }

        // Protect configured root directories
        $componentParams = ComponentHelper::getParams('com_j2commerce');
        $directories     = $componentParams->get('image_directories', [(object) ['directory' => 'images']]);

        if (\is_string($directories)) {
            $directories = json_decode($directories);
        }

        foreach ($directories as $dir) {
            if (trim($dir->directory ?? '', '/') === $path) {
                $this->sendJson(false, 'Cannot delete a configured root directory');
                return;
            }
        }

        $fullPath = JPATH_ROOT . '/' . $path;

        if (!is_dir($fullPath)) {
            $this->sendJson(false, 'Directory not found');
            return;
        }

        if (!$this->isPathWithinRoot($fullPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $contents = array_diff(scandir($fullPath), ['.', '..', 'thumbs', 'tiny']);

        if (!empty($contents)) {
            $this->sendJson(false, 'Directory is not empty. Remove all files first.');
            return;
        }

        $thumbsDir = $fullPath . '/thumbs';
        $tinyDir   = $fullPath . '/tiny';

        if (is_dir($thumbsDir)) {
            $thumbContents = array_diff(scandir($thumbsDir), ['.', '..']);
            if (!empty($thumbContents)) {
                $this->sendJson(false, 'Directory contains thumbnails. Remove all files first.');
                return;
            }
            Folder::delete($thumbsDir);
        }

        if (is_dir($tinyDir)) {
            $tinyContents = array_diff(scandir($tinyDir), ['.', '..']);
            if (!empty($tinyContents)) {
                $this->sendJson(false, 'Directory contains tiny images. Remove all files first.');
                return;
            }
            Folder::delete($tinyDir);
        }

        $deleted = Folder::delete($fullPath);

        $this->sendJson($deleted, $deleted ? '' : 'Failed to delete directory');
    }

    /**
     * Handle file upload from frontend checkout — allows guest users with active cart session.
     *
     * @since  6.2.0
     */
    public function uploadCheckout(): void
    {
        if (!$this->authorizeCheckout()) {
            return;
        }

        $input = $this->app->getInput();
        $file  = $input->files->get('file', [], 'array');

        if (empty($file['name'])) {
            $this->sendJson(false, 'No file uploaded');
            return;
        }

        if (!(new MediaHelper())->canUpload($file)) {
            $this->sendJson(false, 'File type not allowed');
            return;
        }

        // Force directory to checkout-uploads only (security: prevent path manipulation)
        $directory = $this->sanitizePath($input->getString('path', 'images/checkout-uploads'));

        if (!str_starts_with($directory, 'images/checkout-uploads')) {
            $directory = 'images/checkout-uploads';
        }

        $uploadPath = JPATH_ROOT . '/' . $directory;

        if (!is_dir($uploadPath)) {
            Folder::create($uploadPath);
        }

        if (!$this->isPathWithinRoot($uploadPath)) {
            $this->sendJson(false, 'Access denied');
            return;
        }

        $extension = strtolower(File::getExt($file['name']));
        $safeName  = File::makeSafe($file['name']);
        $baseName  = File::stripExt($safeName);
        $fileName  = $baseName . '_' . uniqid() . '.' . $extension;
        $filePath  = $uploadPath . '/' . $fileName;

        if (!File::upload($file['tmp_name'], $filePath)) {
            $this->sendJson(false, 'Failed to save file');
            return;
        }

        $relativePath = $directory . '/' . $fileName;
        $fileSize     = filesize($filePath) ?: 0;

        $this->sendJson(true, '', [
            'name' => $file['name'],
            'path' => $relativePath,
            'url'  => Uri::root() . $relativePath,
            'size' => $fileSize,
        ]);
    }

    /**
     * Authorize checkout upload — requires CSRF token and active cart session.
     * Does NOT require authenticated user (guests can upload during checkout).
     *
     * @since  6.2.0
     */
    private function authorizeCheckout(): bool
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token');
            return false;
        }

        // Verify active checkout session (cart with items)
        $session = $this->app->getSession();
        $cartId  = $session->get('j2commerce.cart_id', 0);

        if (empty($cartId)) {
            $this->sendJson(false, 'No active checkout session');
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** Validate CSRF token and user authentication. Returns false and sends error response on failure. */
    private function authorize(): bool
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token');
            return false;
        }

        $user = $this->app->getIdentity();

        if (!$user || $user->guest) {
            $this->sendJson(false, 'Not authorized');
            return false;
        }

        return true;
    }

    /** Generate thumb and tiny WebP versions for a source image. */
    private function generateThumbAndTiny(
        string $filePath,
        string $directory,
        string $fileName,
        string $siteRoot,
        \Joomla\Registry\Registry $componentParams
    ): array {
        $result       = [];
        $uploadPath   = \dirname($filePath);
        $webpBaseName = File::stripExt($fileName) . '.webp';

        $processor = new ImageProcessorHelper(
            (int) $componentParams->get('image_webp_quality', 80),
            (int) $componentParams->get('image_thumb_quality', 80)
        );

        // --- Thumb ---
        $thumbDir  = $uploadPath . '/thumbs';
        $thumbPath = $thumbDir . '/' . $webpBaseName;

        if (!is_dir($thumbDir)) {
            Folder::create($thumbDir);
        }

        $thumbWidth  = (int) $componentParams->get('image_thumb_width', 300);
        $thumbHeight = (int) $componentParams->get('image_thumb_height', 300);

        if (!is_file($thumbPath)) {
            $processor->createThumbnail($filePath, $thumbPath, $thumbWidth, $thumbHeight);
        }

        if (is_file($thumbPath)) {
            $thumbRelative         = $directory . '/thumbs/' . $webpBaseName;
            $result['thumb_path']  = $thumbRelative;
            $result['thumb_url']   = $siteRoot . $thumbRelative;
            $thumbInfo             = @getimagesize($thumbPath);
            $result['thumb_width']  = $thumbInfo ? $thumbInfo[0] : $thumbWidth;
            $result['thumb_height'] = $thumbInfo ? $thumbInfo[1] : $thumbHeight;
        }

        // --- Tiny ---
        $tinyDir  = $uploadPath . '/tiny';
        $tinyPath = $tinyDir . '/' . $webpBaseName;

        if (!is_dir($tinyDir)) {
            Folder::create($tinyDir);
        }

        $tinyWidth  = (int) $componentParams->get('image_tiny_width', 100);
        $tinyHeight = (int) $componentParams->get('image_tiny_height', 100);

        $tinyProcessor = new ImageProcessorHelper(
            (int) $componentParams->get('image_webp_quality', 80),
            (int) $componentParams->get('image_tiny_quality', 80)
        );

        if (!is_file($tinyPath)) {
            $tinyProcessor->createThumbnail($filePath, $tinyPath, $tinyWidth, $tinyHeight);
        }

        if (is_file($tinyPath)) {
            $tinyRelative         = $directory . '/tiny/' . $webpBaseName;
            $result['tiny_path']  = $tinyRelative;
            $result['tiny_url']   = $siteRoot . $tinyRelative;
            $tinyInfo             = @getimagesize($tinyPath);
            $result['tiny_width']  = $tinyInfo ? $tinyInfo[0] : $tinyWidth;
            $result['tiny_height'] = $tinyInfo ? $tinyInfo[1] : $tinyHeight;
        }

        return $result;
    }

    /** Resize and convert the main image to WebP based on config. Returns the (possibly new) file path. */
    private function processMainImage(string $filePath, string $extension, \Joomla\Registry\Registry $params): string
    {
        $enableWebP    = (int) $params->get('image_enable_webp', 1);
        $maxDimension  = (int) $params->get('image_max_dimension', 1200);
        $maintainRatio = (bool) $params->get('image_maintain_ratio', 1);
        $keepOriginal  = (bool) $params->get('image_keep_original', 0);

        if (!$enableWebP || $maxDimension < 1) {
            return $filePath;
        }

        // Skip if already WebP and within dimension limits
        if ($extension === 'webp') {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo && $imageInfo[0] <= $maxDimension && $imageInfo[1] <= $maxDimension) {
                return $filePath;
            }
        }

        $processor = new ImageProcessorHelper(
            (int) $params->get('image_webp_quality', 80)
        );

        $webpData = $processor->processMainImage($filePath, $maxDimension, $maintainRatio);

        if ($webpData === false) {
            return $filePath;
        }

        $dir     = \dirname($filePath);
        $webpPath = $dir . '/' . File::stripExt(basename($filePath)) . '.webp';

        if ($keepOriginal && $extension !== 'webp') {
            // Keep the original, write WebP as a new file
            File::write($webpPath, $webpData);
        } else {
            // Replace original with WebP
            if ($extension !== 'webp') {
                File::delete($filePath);
            }
            File::write($webpPath, $webpData);
        }

        return $webpPath;
    }

    private function sanitizePath(string $path): string
    {
        $path = trim($path, '/');
        $path = str_replace('\\', '/', $path);

        // Recursively remove '..' sequences to prevent bypass via '..../' → '../'
        do {
            $cleaned = str_replace('..', '', $path);
            if ($cleaned === $path) {
                break;
            }
            $path = $cleaned;
        } while (true);

        $path = preg_replace('#/+#', '/', $path);
        $path = trim($path, '/');

        if (!str_starts_with($path, 'images')) {
            return 'images';
        }

        return $path;
    }

    /** Verify a resolved path is within JPATH_ROOT to prevent traversal attacks. */
    private function isPathWithinRoot(string $fullPath): bool
    {
        $realPath = realpath($fullPath);

        if ($realPath === false) {
            return false;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', JPATH_ROOT), '/');
        $normalizedPath = str_replace('\\', '/', $realPath);

        return str_starts_with($normalizedPath, $normalizedRoot);
    }

    private function sendJson(bool $success, string $message = '', mixed $data = null): void
    {
        $this->app->getDocument()->setMimeEncoding('application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ]);
        $this->app->close();
    }

    protected function getDatabase(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
