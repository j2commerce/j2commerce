<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

final class OverrideRegistry
{
    // File groups
    public const GROUP_LIST_LAYOUTS         = 'list_layouts';
    public const GROUP_TAG_LAYOUTS          = 'tag_layouts';
    public const GROUP_CATEGORIES_TEMPLATES = 'categories_templates';
    public const GROUP_LIST_VIEW_TEMPLATES  = 'list_view_templates';
    public const GROUP_TAG_VIEW_TEMPLATES   = 'tag_view_templates';
    public const GROUP_PRODUCT_DETAIL       = 'product_detail';
    public const GROUP_OTHER                = 'other';

    // View contexts
    public const CONTEXT_SHARED     = 'shared';
    public const CONTEXT_LIST       = 'list';
    public const CONTEXT_TAG        = 'tag';
    public const CONTEXT_CATEGORIES = 'categories';

    private static array $subtemplateCache = [];

    /**
     * Files that are context-specific (different for list vs tag views)
     * All other item_* files are shared between both contexts
     */
    private static array $contextSpecificLayoutFiles = [
        'item_sortfilter.php',
        'item_filters.php',
    ];

    public static function getFileGroups(): array
    {
        return [
            self::GROUP_LIST_LAYOUTS => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_LIST_LAYOUTS',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_LIST_LAYOUTS_DESC',
            ],
            self::GROUP_TAG_LAYOUTS => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_TAG_LAYOUTS',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_TAG_LAYOUTS_DESC',
            ],
            self::GROUP_CATEGORIES_TEMPLATES => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_CATEGORIES',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_CATEGORIES_DESC',
            ],
            self::GROUP_LIST_VIEW_TEMPLATES => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_LIST_VIEW',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_LIST_VIEW_DESC',
            ],
            self::GROUP_TAG_VIEW_TEMPLATES => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_TAG_VIEW',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_TAG_VIEW_DESC',
            ],
            self::GROUP_PRODUCT_DETAIL => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_PRODUCT_DETAIL',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_PRODUCT_DETAIL_DESC',
            ],
            self::GROUP_OTHER => [
                'label'       => 'COM_J2COMMERCE_OVERRIDE_GROUP_OTHER',
                'description' => 'COM_J2COMMERCE_OVERRIDE_GROUP_OTHER_DESC',
            ],
        ];
    }

    public static function getInstalledSubtemplates(): array
    {
        if (!empty(self::$subtemplateCache)) {
            return self::$subtemplateCache;
        }

        $layoutsPath = Path::clean(JPATH_ROOT . '/components/com_j2commerce/layouts');

        if (!is_dir($layoutsPath)) {
            return [];
        }

        $folders      = Folder::folders($layoutsPath, '.', false, false);
        $subtemplates = [];

        foreach ($folders as $folder) {
            if (strpos($folder, 'app_') !== 0) {
                continue;
            }

            $info = self::getSubtemplateInfo($folder);

            if ($info === null) {
                continue;
            }

            $layoutFiles  = self::getLayoutFiles($folder);
            $tmplFiles    = self::getAllTmplFiles($folder);
            $allFiles     = array_merge($layoutFiles, $tmplFiles);
            $groupedFiles = self::groupFilesByType($allFiles);

            $subtemplates[] = [
                'element'      => $folder,
                'name'         => $info['name'],
                'description'  => $info['description'],
                'version'      => $info['version'],
                'imagePath'    => $info['imagePath'],
                'enabled'      => $info['enabled'],
                'fileCount'    => \count($allFiles),
                'groupedFiles' => $groupedFiles,
            ];
        }

        usort($subtemplates, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        self::$subtemplateCache = $subtemplates;

        return $subtemplates;
    }

    public static function getSubtemplateInfo(string $pluginElement): ?array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['name', 'manifest_cache', 'enabled']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('element') . ' = ' . $db->quote($pluginElement));
        $db->setQuery($query);

        $extension = $db->loadObject();

        if (!$extension) {
            return null;
        }

        $manifest  = new Registry($extension->manifest_cache);
        $imagePath = self::resolveImagePath($pluginElement);

        return [
            'name'        => $manifest->get('name', $extension->name),
            'description' => $manifest->get('description', ''),
            'version'     => $manifest->get('version', '1.0.0'),
            'imagePath'   => $imagePath,
            'enabled'     => (bool) $extension->enabled,
        ];
    }

    public static function getLayoutFiles(string $pluginElement, string $templateOverridePath = ''): array
    {
        $basePath = Path::clean(JPATH_ROOT . '/components/com_j2commerce/layouts/' . $pluginElement);

        if (!is_dir($basePath)) {
            return [];
        }

        $files = self::scanFilesRecursive($basePath, $basePath, 'layouts', self::CONTEXT_SHARED);

        if (!empty($templateOverridePath)) {
            $overridePath = Path::clean($templateOverridePath . '/' . $pluginElement);
            foreach ($files as &$file) {
                $overrideFilePath     = Path::clean($overridePath . '/' . $file['relativePath']);
                $file['hasOverride']  = is_file($overrideFilePath);
                $file['overridePath'] = $overrideFilePath;
            }
        }

        return $files;
    }

    /**
     * Get tmpl files from ALL tmpl subfolders (list + tag contexts)
     */
    public static function getAllTmplFiles(string $pluginElement, string $templateOverridePath = '', string $tmplOverridePath = ''): array
    {
        $tmplBasePath = Path::clean(JPATH_PLUGINS . '/j2commerce/' . $pluginElement . '/tmpl');

        if (!is_dir($tmplBasePath)) {
            return [];
        }

        $allFiles    = [];
        $tmplFolders = self::getTmplFolders($pluginElement);

        foreach ($tmplFolders as $folderName => $context) {
            $folderPath = Path::clean($tmplBasePath . '/' . $folderName);

            if (!is_dir($folderPath)) {
                continue;
            }

            $files = self::scanFilesRecursive($folderPath, $folderPath, 'tmpl', $context, $folderName);

            // Check for overrides in the tmpl override path (templates/<tpl>/html/com_j2commerce/templates/<folder>/)
            $checkPath = !empty($tmplOverridePath) ? $tmplOverridePath : $templateOverridePath;
            if (!empty($checkPath)) {
                $overridePath = !empty($tmplOverridePath)
                    ? Path::clean($tmplOverridePath . '/' . $folderName)
                    : Path::clean($templateOverridePath . '/' . $pluginElement . '/tmpl/' . $folderName);

                foreach ($files as &$file) {
                    $overrideFilePath     = Path::clean($overridePath . '/' . $file['relativePath']);
                    $file['hasOverride']  = is_file($overrideFilePath);
                    $file['overridePath'] = $overrideFilePath;
                }
            }

            $allFiles = array_merge($allFiles, $files);
        }

        return $allFiles;
    }

    /**
     * Get single tmpl folder files (for backward compatibility)
     */
    public static function getTmplFiles(string $pluginElement, string $templateOverridePath = ''): array
    {
        $tmplFolder = self::getPrimaryTmplFolder($pluginElement);
        $basePath   = Path::clean(JPATH_PLUGINS . '/j2commerce/' . $pluginElement . '/tmpl/' . $tmplFolder);

        if (!is_dir($basePath)) {
            return [];
        }

        $files = self::scanFilesRecursive($basePath, $basePath, 'tmpl', self::CONTEXT_LIST, $tmplFolder);

        if (!empty($templateOverridePath)) {
            $overridePath = Path::clean($templateOverridePath . '/' . $pluginElement . '/tmpl/' . $tmplFolder);
            foreach ($files as &$file) {
                $overrideFilePath     = Path::clean($overridePath . '/' . $file['relativePath']);
                $file['hasOverride']  = is_file($overrideFilePath);
                $file['overridePath'] = $overrideFilePath;
            }
        }

        return $files;
    }

    public static function countActiveOverrides(string $pluginElement, string $templateOverridePath, string $tmplOverridePath = ''): int
    {
        $layoutFiles = self::getLayoutFiles($pluginElement, $templateOverridePath);
        $tmplFiles   = self::getAllTmplFiles($pluginElement, $templateOverridePath, $tmplOverridePath);
        $allFiles    = array_merge($layoutFiles, $tmplFiles);
        $count       = 0;

        foreach ($allFiles as $file) {
            if ($file['hasOverride'] ?? false) {
                $count++;
            }
        }

        return $count;
    }

    public static function getSourcePath(string $pluginElement, string $relativePath, string $type = 'layouts', string $tmplFolder = ''): string
    {
        if ($type === 'tmpl') {
            if (empty($tmplFolder)) {
                $tmplFolder = self::getPrimaryTmplFolder($pluginElement);
            }

            return Path::clean(JPATH_PLUGINS . '/j2commerce/' . $pluginElement . '/tmpl/' . $tmplFolder . '/' . $relativePath);
        }

        return Path::clean(JPATH_ROOT . '/components/com_j2commerce/layouts/' . $pluginElement . '/' . $relativePath);
    }

    public static function clearCache(): void
    {
        self::$subtemplateCache = [];
    }

    public static function getAvailableSubtemplates(): array
    {
        $subtemplates = [];

        foreach (self::getInstalledSubtemplates() as $sub) {
            if (!$sub['enabled']) {
                continue;
            }

            foreach (self::getTmplFolders($sub['element']) as $folderName => $context) {
                $viewContexts = match ($context) {
                    self::CONTEXT_LIST       => ['products', 'product', 'producttags', 'categories'],
                    self::CONTEXT_TAG        => ['producttags'],
                    self::CONTEXT_CATEGORIES => ['categories'],
                    default                  => [],
                };

                $subtemplates[$folderName] = [
                    'name'     => $folderName,
                    'contexts' => $viewContexts,
                    'source'   => 'plugin',
                    'element'  => $sub['element'],
                ];
            }
        }

        return $subtemplates;
    }

    /**
     * Get all tmpl subfolders and their view contexts for a plugin
     */
    public static function getTmplFolders(string $pluginElement): array
    {
        return match ($pluginElement) {
            'app_bootstrap5' => [
                'bootstrap5'            => self::CONTEXT_LIST,
                'tag_bootstrap5'        => self::CONTEXT_TAG,
                'categories_bootstrap5' => self::CONTEXT_CATEGORIES,
            ],
            'app_uikit' => [
                'uikit'            => self::CONTEXT_LIST,
                'tag_uikit'        => self::CONTEXT_TAG,
                'categories_uikit' => self::CONTEXT_CATEGORIES,
            ],
            default => [
                str_replace('app_', '', $pluginElement)                 => self::CONTEXT_LIST,
                'tag_' . str_replace('app_', '', $pluginElement)        => self::CONTEXT_TAG,
                'categories_' . str_replace('app_', '', $pluginElement) => self::CONTEXT_CATEGORIES,
            ],
        };
    }

    /**
     * Get the primary (list view) tmpl folder name
     */
    private static function getPrimaryTmplFolder(string $pluginElement): string
    {
        return match ($pluginElement) {
            'app_bootstrap5' => 'bootstrap5',
            'app_uikit'      => 'uikit',
            default          => str_replace('app_', '', $pluginElement),
        };
    }

    /**
     * Get the tag view tmpl folder name
     */
    private static function getTagTmplFolder(string $pluginElement): string
    {
        return match ($pluginElement) {
            'app_bootstrap5' => 'tag_bootstrap5',
            'app_uikit'      => 'tag_uikit',
            default          => 'tag_' . str_replace('app_', '', $pluginElement),
        };
    }

    public static function groupFilesByType(array $files): array
    {
        $groups = [
            self::GROUP_LIST_LAYOUTS         => [],
            self::GROUP_TAG_LAYOUTS          => [],
            self::GROUP_CATEGORIES_TEMPLATES => [],
            self::GROUP_LIST_VIEW_TEMPLATES  => [],
            self::GROUP_TAG_VIEW_TEMPLATES   => [],
            self::GROUP_PRODUCT_DETAIL       => [],
            self::GROUP_OTHER                => [],
        ];

        foreach ($files as $file) {
            $filename     = $file['filename'];
            $type         = $file['type'];
            $context      = $file['context'] ?? self::CONTEXT_SHARED;
            $relativePath = $file['relativePath'] ?? '';

            // Layout files (item_*) - separate by folder path (list/tag vs list/category)
            if ($type === 'layouts' && strpos($filename, 'item') !== false) {
                // Check if this is a tag layout (in list/tag/ folder)
                if (strpos($relativePath, 'list/tag/') !== false) {
                    $groups[self::GROUP_TAG_LAYOUTS][] = $file;
                } else {
                    // Category layouts or shared layouts
                    $groups[self::GROUP_LIST_LAYOUTS][] = $file;
                }
                continue;
            }

            // Product detail view templates (view_*)
            if (strpos($filename, 'view_') === 0 || $filename === 'view.php') {
                $groups[self::GROUP_PRODUCT_DETAIL][] = $file;
                continue;
            }

            // Tmpl files go to their respective context groups
            if ($type === 'tmpl') {
                if ($context === self::CONTEXT_TAG) {
                    $groups[self::GROUP_TAG_VIEW_TEMPLATES][] = $file;
                } elseif ($context === self::CONTEXT_CATEGORIES) {
                    $groups[self::GROUP_CATEGORIES_TEMPLATES][] = $file;
                } else {
                    $groups[self::GROUP_LIST_VIEW_TEMPLATES][] = $file;
                }
                continue;
            }

            // Everything else
            $groups[self::GROUP_OTHER][] = $file;
        }

        // Sort files within each group
        foreach ($groups as &$groupFiles) {
            usort($groupFiles, static fn (array $a, array $b): int => strcasecmp($a['filename'], $b['filename']));
        }

        // Remove empty groups
        return array_filter($groups, static fn (array $groupFiles): bool => !empty($groupFiles));
    }

    private static function scanFilesRecursive(
        string $dir,
        string $basePath,
        string $type = 'layouts',
        string $context = self::CONTEXT_SHARED,
        string $tmplFolder = ''
    ): array {
        $result = [];

        if (!is_dir($dir)) {
            return $result;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath     = Path::clean($dir . '/' . $item);
            $relativePath = str_replace(Path::clean($basePath) . \DIRECTORY_SEPARATOR, '', Path::clean($fullPath));
            $relativePath = str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath);

            if (is_dir($fullPath)) {
                $result = array_merge($result, self::scanFilesRecursive($fullPath, $basePath, $type, $context, $tmplFolder));
            } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                // Build file ID: type:tmplFolder:relativePath (for tmpl) or type::relativePath (for layouts)
                $fileId = $type . ':' . $tmplFolder . ':' . $relativePath;

                $result[] = [
                    'id'           => base64_encode($fileId),
                    'relativePath' => $relativePath,
                    'filename'     => $item,
                    'displayName'  => self::generateDisplayName($item, $context),
                    'type'         => $type,
                    'context'      => $context,
                    'tmplFolder'   => $tmplFolder,
                    'hasOverride'  => false,
                    'overridePath' => '',
                ];
            }
        }

        return $result;
    }

    private static function generateDisplayName(string $filename, string $context = self::CONTEXT_SHARED): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = str_replace('_', ' ', $name);

        return ucwords($name);
    }

    private static function resolveImagePath(string $pluginElement): string
    {
        $extensions = ['webp', 'png', 'jpg'];
        $baseName   = $pluginElement;

        // Check media folder first
        foreach ($extensions as $ext) {
            $mediaPath = JPATH_ROOT . '/media/plg_j2commerce_' . $pluginElement . '/images/' . $baseName . '.' . $ext;
            if (is_file($mediaPath)) {
                return Uri::root(true) . '/media/plg_j2commerce_' . $pluginElement . '/images/' . $baseName . '.' . $ext;
            }
        }

        // Check plugin images folder
        foreach ($extensions as $ext) {
            $pluginPath = JPATH_PLUGINS . '/j2commerce/' . $pluginElement . '/images/' . $baseName . '.' . $ext;
            if (is_file($pluginPath)) {
                return Uri::root(true) . '/plugins/j2commerce/' . $pluginElement . '/images/' . $baseName . '.' . $ext;
            }
        }

        // Check j2commerce media folder
        foreach ($extensions as $ext) {
            $j2cPath = JPATH_ROOT . '/media/j2commerce/images/' . $baseName . '.' . $ext;
            if (is_file($j2cPath)) {
                return Uri::root(true) . '/media/j2commerce/images/' . $baseName . '.' . $ext;
            }
        }

        // Return default plugin image
        return Uri::root(true) . '/media/com_j2commerce/images/default_app_j2commerce.webp';
    }

    public const FILE_TYPE_DISPATCHER   = 'dispatcher';
    public const FILE_TYPE_BLOCK_LAYOUT = 'block-layout';
    public const FILE_TYPE_OTHER        = 'other';

    public static function classifyLayoutFile(string $sourcePath): string
    {
        if (!is_file($sourcePath)) {
            return self::FILE_TYPE_OTHER;
        }

        $content = file_get_contents($sourcePath);

        if ($content === false) {
            return self::FILE_TYPE_OTHER;
        }

        // Dispatcher: calls ProductLayoutService::renderLayout() with a variable argument
        if (preg_match('/ProductLayoutService::renderLayout\s*\(\s*\$/', $content)) {
            return self::FILE_TYPE_DISPATCHER;
        }

        // Block-layout: contains literal renderLayout('list.*.item_*') calls
        if (preg_match('/renderLayout\s*\(\s*[\'"]list\.[a-z]+\.item_/', $content)) {
            return self::FILE_TYPE_BLOCK_LAYOUT;
        }

        return self::FILE_TYPE_OTHER;
    }

    public static function isCompositionFile(string $fileId): bool
    {
        static $compositionFiles = [
            'item_simple.php',
            'item_variable.php',
            'item_configurable.php',
            'item_flexivariable.php',
            'item_flexiprice.php',
            'item_downloadable.php',
            'item.php',
        ];

        return \in_array(basename($fileId), $compositionFiles, true);
    }

    public static function isSubLayoutFile(string $fileId): bool
    {
        static $subLayoutFiles = [
            'item_title.php',
            'item_images.php',
            'item_price.php',
            'item_sku.php',
            'item_stock.php',
            'item_cart.php',
            'item_description.php',
            'item_quickview.php',
            'item_options.php',
            'item_variableoptions.php',
            'item_configurableoptions.php',
            'item_flexivariableoptions.php',
        ];

        return \in_array(basename($fileId), $subLayoutFiles, true);
    }

    public static function getEditorType(string $fileId): string
    {
        if (self::isSubLayoutFile($fileId)) {
            return 'sublayout';
        }

        if (self::isCompositionFile($fileId)) {
            return 'composition';
        }

        return 'code';
    }
}
