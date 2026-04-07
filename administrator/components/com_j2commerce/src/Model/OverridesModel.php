<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Model;

\defined('_JEXEC') or die;

use J2Commerce\Component\J2commerce\Administrator\Builder\Service\BlockPreviewService;
use J2Commerce\Component\J2commerce\Administrator\Service\OverrideRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

class OverridesModel extends BaseDatabaseModel
{
    public function getSubtemplates(): array
    {
        $subtemplates       = OverrideRegistry::getInstalledSubtemplates();
        $subtemplates       = array_filter($subtemplates, static fn (array $sub): bool => $sub['enabled']);
        $layoutOverridePath = $this->getBaseTemplateOverridePath();
        $tmplOverridePath   = $this->getTmplBaseOverridePath();

        foreach ($subtemplates as &$subtemplate) {
            $layoutFiles = OverrideRegistry::getLayoutFiles($subtemplate['element'], $layoutOverridePath);
            $tmplFiles   = OverrideRegistry::getAllTmplFiles($subtemplate['element'], $layoutOverridePath, $tmplOverridePath);

            $allFiles                           = array_merge($layoutFiles, $tmplFiles);
            $subtemplate['groupedFiles']        = OverrideRegistry::groupFilesByType($allFiles);
            $subtemplate['layoutCount']         = \count($allFiles);
            $subtemplate['activeOverrideCount'] = OverrideRegistry::countActiveOverrides($subtemplate['element'], $layoutOverridePath, $tmplOverridePath);
        }

        return $subtemplates;
    }

    public function getLayoutFilesForSubtemplate(string $pluginElement): array
    {
        $templatePath = $this->getBaseTemplateOverridePath();

        return OverrideRegistry::getLayoutFiles($pluginElement, $templatePath);
    }

    public function createOverride(string $pluginElement, string $encodedFile): bool
    {
        $decoded                            = base64_decode($encodedFile);
        [$type, $tmplFolder, $relativePath] = $this->parseFileId($decoded);

        if (empty($relativePath) || empty($pluginElement)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_FILE_OR_PLUGIN'));
            return false;
        }

        $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $relativePath, $type, $tmplFolder);

        if (!is_file($sourcePath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_SOURCE_FILE_NOT_FOUND'));
            return false;
        }

        $overridePath = Path::clean($this->getTemplateOverridePath($pluginElement, $type, $tmplFolder) . '/' . $relativePath);
        $overrideDir  = \dirname($overridePath);

        if (!is_dir($overrideDir) && !Folder::create($overrideDir)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_OVERRIDE_DIR_CREATE'));
            return false;
        }

        if (is_file($overridePath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_OVERRIDE_EXISTS'));
            return false;
        }

        if (!@copy($sourcePath, $overridePath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_FILE_COPY_FAILED'));
            return false;
        }

        return true;
    }

    public function revertOverride(string $pluginElement, string $encodedFile): bool
    {
        $decoded                            = base64_decode($encodedFile);
        [$type, $tmplFolder, $relativePath] = $this->parseFileId($decoded);

        if (empty($relativePath) || empty($pluginElement)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_FILE_OR_PLUGIN'));
            return false;
        }

        $overridePath = Path::clean($this->getTemplateOverridePath($pluginElement, $type, $tmplFolder) . '/' . $relativePath);

        if (!is_file($overridePath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_NO_OVERRIDE'));
            return false;
        }

        return File::delete($overridePath);
    }

    public function getSource(string $pluginElement, string $encodedFile): ?\stdClass
    {
        $decoded                            = base64_decode($encodedFile);
        [$type, $tmplFolder, $relativePath] = $this->parseFileId($decoded);

        if (empty($relativePath) || empty($pluginElement)) {
            return null;
        }

        $overridePath = Path::clean($this->getTemplateOverridePath($pluginElement, $type, $tmplFolder) . '/' . $relativePath);

        if (!is_file($overridePath)) {
            return null;
        }

        $layoutBase    = Path::clean($this->getBaseTemplateOverridePath());
        $tmplBase      = Path::clean($this->getTmplBaseOverridePath());
        $cleanOverride = Path::clean($overridePath);

        if (strpos($cleanOverride, $layoutBase) !== 0 && strpos($cleanOverride, $tmplBase) !== 0) {
            return null;
        }

        $item                = new \stdClass();
        $item->pluginElement = $pluginElement;
        $item->fileId        = $encodedFile;
        $item->fileType      = $type;
        $item->tmplFolder    = $tmplFolder;
        $item->filename      = $relativePath;
        $item->builderFileId = $relativePath;
        $item->filePath      = $overridePath;
        $item->source        = file_get_contents($overridePath);
        $item->label         = basename($relativePath);

        $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $relativePath, $type, $tmplFolder);
        if (is_file($sourcePath)) {
            $item->coreFile = $sourcePath;
            $item->core     = file_get_contents($sourcePath);
        }

        return $item;
    }

    public function saveSource(string $pluginElement, string $encodedFile, string $source): bool
    {
        $decoded                            = base64_decode($encodedFile);
        [$type, $tmplFolder, $relativePath] = $this->parseFileId($decoded);

        if (empty($relativePath) || empty($pluginElement)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_FILE_OR_PLUGIN'));
            return false;
        }

        $overridePath = Path::clean($this->getTemplateOverridePath($pluginElement, $type, $tmplFolder) . '/' . $relativePath);

        if (!is_file($overridePath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_OVERRIDE_NOT_FOUND'));
            return false;
        }

        $layoutBase    = Path::clean($this->getBaseTemplateOverridePath());
        $tmplBase      = Path::clean($this->getTmplBaseOverridePath());
        $cleanOverride = Path::clean($overridePath);

        if (strpos($cleanOverride, $layoutBase) !== 0 && strpos($cleanOverride, $tmplBase) !== 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_FILE_PATH'));
            return false;
        }

        $source = str_replace(["\r\n", "\r"], "\n", $source);

        return File::write($overridePath, $source);
    }

    public function getOverrideFiles(): array
    {
        $files = [];

        $layoutPath = $this->getBaseTemplateOverridePath();
        if (is_dir($layoutPath)) {
            $files = array_merge($files, $this->buildDirectoryTree($layoutPath, $layoutPath));
        }

        $tmplPath = $this->getTmplBaseOverridePath();
        if (is_dir($tmplPath)) {
            $files = array_merge($files, $this->buildDirectoryTree($tmplPath, $tmplPath));
        }

        return $files;
    }

    public function getSourceByPath(string $encodedFile): ?\stdClass
    {
        $relativePath = base64_decode($encodedFile);

        // Try layout base first, then tmpl base
        $layoutBase = $this->getBaseTemplateOverridePath();
        $tmplBase   = $this->getTmplBaseOverridePath();
        $fullPath   = Path::clean($layoutBase . '/' . $relativePath);

        if (!is_file($fullPath)) {
            $fullPath = Path::clean($tmplBase . '/' . $relativePath);
        }

        if (!is_file($fullPath)) {
            return null;
        }

        $cleanFull = Path::clean($fullPath);
        if (strpos($cleanFull, Path::clean($layoutBase)) !== 0 && strpos($cleanFull, Path::clean($tmplBase)) !== 0) {
            return null;
        }

        // Normalize to forward slashes (Windows Path::clean uses backslashes)
        $relativePath = str_replace('\\', '/', $relativePath);

        // Extract plugin element from path (first folder after base path)
        $pathParts     = explode('/', $relativePath);
        $pluginElement = $pathParts[0] ?? '';

        // Detect file type from path structure
        $isTmpl     = isset($pathParts[1]) && $pathParts[1] === 'tmpl';
        $fileType   = $isTmpl ? 'tmpl' : 'layouts';
        $tmplFolder = ($isTmpl && isset($pathParts[2])) ? $pathParts[2] : '';

        // Strip plugin element prefix so filename matches builder select values
        // relativePath = "app_bootstrap5/list/category/item.php"
        // filename should be "list/category/item.php" (without plugin element prefix)
        $filenameWithoutPlugin = implode('/', \array_slice($pathParts, 1));

        $item                = new \stdClass();
        $item->pluginElement = $pluginElement;
        $item->fileId        = $encodedFile;
        $item->fileType      = $fileType;
        $item->tmplFolder    = $tmplFolder;
        $item->filename      = $relativePath;
        $item->builderFileId = $filenameWithoutPlugin;
        $item->filePath      = $fullPath;
        $item->source        = file_get_contents($fullPath);
        $item->label         = basename($relativePath);

        // Try to find the core source file
        if (!empty($pluginElement) && \count($pathParts) > 1) {
            $subPath    = implode('/', \array_slice($pathParts, 1));
            $sourcePath = OverrideRegistry::getSourcePath($pluginElement, $subPath);
            if (is_file($sourcePath)) {
                $item->coreFile = $sourcePath;
                $item->core     = file_get_contents($sourcePath);
            }
        }

        return $item;
    }

    public function saveSourceByPath(string $encodedFile, string $source): bool
    {
        $relativePath = base64_decode($encodedFile);

        $layoutBase = $this->getBaseTemplateOverridePath();
        $tmplBase   = $this->getTmplBaseOverridePath();
        $fullPath   = Path::clean($layoutBase . '/' . $relativePath);

        if (!is_file($fullPath)) {
            $fullPath = Path::clean($tmplBase . '/' . $relativePath);
        }

        if (!is_file($fullPath)) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_FILE_NOT_FOUND'));
            return false;
        }

        $cleanFull = Path::clean($fullPath);
        if (strpos($cleanFull, Path::clean($layoutBase)) !== 0 && strpos($cleanFull, Path::clean($tmplBase)) !== 0) {
            $this->setError(Text::_('COM_J2COMMERCE_ERR_INVALID_FILE_PATH'));
            return false;
        }

        $source = str_replace(["\r\n", "\r"], "\n", $source);

        return File::write($fullPath, $source);
    }

    public function getEditorForm(): Form|false
    {
        $form = Form::getInstance(
            'com_j2commerce.override_source',
            JPATH_COMPONENT_ADMINISTRATOR . '/forms/override_source.xml',
            ['control' => 'jform']
        );

        return $form ?: false;
    }

    public function getBaseTemplateOverridePath(): string
    {
        $template = $this->getActiveTemplate();

        return Path::clean(JPATH_ROOT . '/templates/' . $template . '/html/layouts/com_j2commerce');
    }

    public function getTmplBaseOverridePath(): string
    {
        $template = $this->getActiveTemplate();

        return Path::clean(JPATH_ROOT . '/templates/' . $template . '/html/com_j2commerce/templates');
    }

    public function getTemplateOverridePath(string $pluginElement, string $type = 'layouts', string $tmplFolder = ''): string
    {
        if ($type === 'tmpl' && !empty($tmplFolder)) {
            return Path::clean($this->getTmplBaseOverridePath() . '/' . $tmplFolder);
        }

        if ($type === 'tmpl') {
            return Path::clean($this->getTmplBaseOverridePath());
        }

        return Path::clean($this->getBaseTemplateOverridePath() . '/' . $pluginElement);
    }

    /**
     * Parse file ID into type, tmplFolder, and relativePath
     *
     * Format: type:tmplFolder:relativePath
     * - For layouts: layouts::j2commerce/product/list/item.php
     * - For tmpl: tmpl:bootstrap5:default.php
     *
     * @param   string  $decoded  The decoded file ID
     *
     * @return  array  [type, tmplFolder, relativePath]
     */
    private function parseFileId(string $decoded): array
    {
        $parts = explode(':', $decoded, 3);

        if (\count($parts) === 3) {
            return $parts; // [type, tmplFolder, relativePath]
        }

        if (\count($parts) === 2) {
            // Old format: type:relativePath - assume empty tmplFolder
            return [$parts[0], '', $parts[1]];
        }

        // Fallback for very old format
        return ['layouts', '', $decoded];
    }

    public function getPreviewProducts(): array
    {
        $db      = Factory::getContainer()->get('DatabaseDriver');
        $service = new BlockPreviewService($db);

        return array_map(
            static fn (object $row): array => ['id' => (int) $row->id, 'title' => (string) $row->name],
            $service->getPreviewProducts()
        );
    }

    public function getActiveTemplate(): string
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('template'))
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
        $db->setQuery($query);

        return $db->loadResult() ?: 'cassiopeia';
    }

    private function buildDirectoryTree(string $dir, string $basePath, string $relativeDirPath = ''): array
    {
        $result = [];
        $items  = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath         = $dir . \DIRECTORY_SEPARATOR . $item;
            $itemRelativePath = $relativeDirPath !== '' ? $relativeDirPath . '/' . $item : $item;

            if (is_dir($fullPath)) {
                $children = $this->buildDirectoryTree($fullPath, $basePath, $itemRelativePath);
                if (!empty($children)) {
                    $result[] = [
                        'type'     => 'folder',
                        'name'     => $item,
                        'path'     => $itemRelativePath,
                        'children' => $children,
                    ];
                }
            } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $relativePath = str_replace(Path::clean($basePath) . \DIRECTORY_SEPARATOR, '', Path::clean($fullPath));
                $result[]     = [
                    'type' => 'file',
                    'name' => $item,
                    'id'   => base64_encode($relativePath),
                    'path' => $itemRelativePath,
                ];
            }
        }

        usort($result, static function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'folder' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }
}
