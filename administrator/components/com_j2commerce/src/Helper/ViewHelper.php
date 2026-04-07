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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * View helper class for J2Commerce.
 *
 * Provides static methods for layout file discovery and rendering,
 * template override path management, and template name retrieval.
 *
 * @since  6.0.0
 */
class ViewHelper
{
    /**
     * Singleton instance
     *
     * @var   ViewHelper|null
     * @since 6.0.0
     */
    protected static ?ViewHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties (unused, for interface compatibility)
     *
     * @return  ViewHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): ViewHelper
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Cached database instance
     *
     * @var   DatabaseInterface|null
     * @since 6.0.0
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Template override path
     *
     * @var   string
     * @since 6.0.0
     */
    private static string $templatePath = '';

    /**
     * Default view path
     *
     * @var   string
     * @since 6.0.0
     */
    private static string $defaultPath = '';

    /**
     * Cached default template names per client
     *
     * @var   array<string, string>
     * @since 6.0.0
     */
    private static array $defaultTemplates = [];

    /**
     * Get the database instance
     *
     * @return  DatabaseInterface
     *
     * @since   6.0.0
     */
    private static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            self::$db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return self::$db;
    }

    // =========================================================================
    // PATH MANAGEMENT METHODS
    // =========================================================================

    /**
     * Set the template override path.
     *
     * The template override path is where template-specific layout overrides
     * are searched for (e.g., templates/mytemplate/html/com_j2commerce/).
     *
     * @param   string  $path  The full path to the template override directory.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function setTemplateOverridePath(string $path): void
    {
        self::$templatePath = $path;
    }

    /**
     * Get the template override path.
     *
     * @return  string  The template override path.
     *
     * @since   6.0.0
     */
    public static function getTemplateOverridePath(): string
    {
        return self::$templatePath;
    }

    /**
     * Set the default view path.
     *
     * The default view path is where the component's default layout files
     * are located (e.g., components/com_j2commerce/tmpl/).
     *
     * @param   string  $path  The full path to the default view directory.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function setDefaultViewPath(string $path): void
    {
        self::$defaultPath = $path;
    }

    /**
     * Get the default view path.
     *
     * @return  string  The default view path.
     *
     * @since   6.0.0
     */
    public static function getDefaultViewPath(): string
    {
        return self::$defaultPath;
    }

    /**
     * Reset all path settings.
     *
     * Clears template override and default view paths. Useful for testing
     * or when switching between different layout contexts.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function resetPaths(): void
    {
        self::$templatePath = '';
        self::$defaultPath  = '';
    }

    // =========================================================================
    // LAYOUT RENDERING METHODS
    // =========================================================================

    /**
     * Get the rendered output from a layout file.
     *
     * This method locates the layout file (checking template overrides first)
     * and renders it, returning the output as a string. Variables can be
     * passed to the layout via the $data parameter.
     *
     * @param   string  $layout  The layout name (without .php extension).
     * @param   array   $data    Optional data to pass to the layout.
     *
     * @return  string  The rendered output or empty string if layout not found.
     *
     * @since   6.0.0
     */
    public static function getOutput(string $layout, array $data = []): string
    {
        $layoutPath = self::getLayoutPath($layout);

        if (empty($layoutPath)) {
            return '';
        }

        // Extract data array to variables for use in layout
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        ob_start();
        include $layoutPath;
        $html = ob_get_contents();
        ob_end_clean();

        return $html ?: '';
    }

    /**
     * Get the path to a layout file.
     *
     * Searches for the layout file in the following order:
     * 1. Template override path (if set)
     * 2. Default view path (if set)
     *
     * @param   string  $layout  The layout name (without .php extension).
     *
     * @return  string  The full path to the layout file, or empty string if not found.
     *
     * @since   6.0.0
     */
    public static function getLayoutPath(string $layout = 'default'): string
    {
        // Sanitize the layout name to prevent directory traversal
        $layout = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $layout);

        if (empty($layout)) {
            $layout = 'default';
        }

        // Build potential paths
        $templatePath = self::getTemplateOverridePath();
        $defaultPath  = self::getDefaultViewPath();

        // Check template override path first
        if (!empty($templatePath)) {
            $overridePath = $templatePath . '/' . $layout . '.php';

            if (File::exists($overridePath)) {
                return $overridePath;
            }
        }

        // Check default path
        if (!empty($defaultPath)) {
            $defaultFilePath = $defaultPath . '/' . $layout . '.php';

            if (File::exists($defaultFilePath)) {
                return $defaultFilePath;
            }
        }

        return '';
    }

    /**
     * Check if a layout file exists.
     *
     * @param   string  $layout  The layout name (without .php extension).
     *
     * @return  bool  True if the layout exists, false otherwise.
     *
     * @since   6.0.0
     */
    public static function layoutExists(string $layout): bool
    {
        return !empty(self::getLayoutPath($layout));
    }

    // =========================================================================
    // TEMPLATE METHODS
    // =========================================================================

    /**
     * Get the default template name for a client (site or admin).
     *
     * Queries the database for the currently active default template
     * for the specified client. Results are cached per client.
     *
     * @param   string  $client  The client type: 'site' (default) or 'admin'.
     *
     * @return  string  The template name or empty string if not found.
     *
     * @since   6.0.0
     */
    public static function getTemplate(string $client = 'site'): string
    {
        // Normalize client value
        $client = ($client === 'admin') ? 'admin' : 'site';

        // Return cached value if available
        if (isset(self::$defaultTemplates[$client])) {
            return self::$defaultTemplates[$client];
        }

        // Determine client_id (0 = site, 1 = admin)
        $clientId = ($client === 'admin') ? 1 : 0;

        $db    = self::getDatabase();
        $query = $db->getQuery(true);

        $query->select($db->quoteName('template'))
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->where($db->quoteName('home') . ' = :home')
            ->bind(':clientId', $clientId, ParameterType::INTEGER)
            ->bind(':home', '1', ParameterType::STRING);

        $db->setQuery($query);
        $template = $db->loadResult();

        self::$defaultTemplates[$client] = $template ?: '';

        return self::$defaultTemplates[$client];
    }

    /**
     * Clear the cached template names.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function clearTemplateCache(): void
    {
        self::$defaultTemplates = [];
    }

    // =========================================================================
    // PATH BUILDING HELPERS
    // =========================================================================

    /**
     * Build the template override path for a component view.
     *
     * Creates the standard Joomla template override path for a given
     * component and view, using the specified or default template.
     *
     * @param   string       $component  The component name (e.g., 'com_j2commerce').
     * @param   string       $view       The view name.
     * @param   string|null  $template   Optional template name (uses default if null).
     * @param   string       $client     The client type: 'site' (default) or 'admin'.
     *
     * @return  string  The full path to the template override directory.
     *
     * @since   6.0.0
     */
    public static function buildTemplateOverridePath(
        string $component,
        string $view,
        ?string $template = null,
        string $client = 'site'
    ): string {
        if ($template === null) {
            $template = self::getTemplate($client);
        }

        if (empty($template)) {
            return '';
        }

        // Determine the base path
        if ($client === 'admin') {
            $basePath = JPATH_ADMINISTRATOR . '/templates/' . $template . '/html';
        } else {
            $basePath = JPATH_SITE . '/templates/' . $template . '/html';
        }

        return $basePath . '/' . $component . '/' . $view;
    }

    /**
     * Build the default component view path.
     *
     * Creates the standard Joomla component view path for a given
     * component and view.
     *
     * @param   string  $component  The component name (e.g., 'com_j2commerce').
     * @param   string  $view       The view name.
     * @param   string  $client     The client type: 'site' (default) or 'admin'.
     *
     * @return  string  The full path to the component view directory.
     *
     * @since   6.0.0
     */
    public static function buildDefaultViewPath(
        string $component,
        string $view,
        string $client = 'site'
    ): string {
        // Determine the base path
        if ($client === 'admin') {
            $basePath = JPATH_ADMINISTRATOR . '/components/' . $component . '/tmpl';
        } else {
            $basePath = JPATH_SITE . '/components/' . $component . '/tmpl';
        }

        return $basePath . '/' . $view;
    }

    /**
     * Configure paths for a specific component view.
     *
     * Convenience method that sets both the template override and default
     * view paths for a given component and view combination.
     *
     * @param   string       $component  The component name (e.g., 'com_j2commerce').
     * @param   string       $view       The view name.
     * @param   string|null  $template   Optional template name (uses default if null).
     * @param   string       $client     The client type: 'site' (default) or 'admin'.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function configurePaths(
        string $component,
        string $view,
        ?string $template = null,
        string $client = 'site'
    ): void {
        $overridePath = self::buildTemplateOverridePath($component, $view, $template, $client);
        $defaultPath  = self::buildDefaultViewPath($component, $view, $client);

        if (!empty($overridePath)) {
            self::setTemplateOverridePath($overridePath);
        }

        self::setDefaultViewPath($defaultPath);
    }

    // =========================================================================
    // PLUGIN/MODULE LAYOUT HELPERS
    // =========================================================================

    /**
     * Get the layout path for a plugin.
     *
     * Searches for the layout file in the following order:
     * 1. Current template override
     * 2. Plugin's tmpl folder
     *
     * @param   string  $pluginGroup  The plugin group (e.g., 'j2commerce').
     * @param   string  $pluginName   The plugin name.
     * @param   string  $layout       The layout name (without .php extension).
     * @param   string  $client       The client type: 'site' (default) or 'admin'.
     *
     * @return  string  The full path to the layout file, or empty string if not found.
     *
     * @since   6.0.0
     */
    public static function getPluginLayoutPath(
        string $pluginGroup,
        string $pluginName,
        string $layout = 'default',
        string $client = 'site'
    ): string {
        $template = self::getTemplate($client);

        // Check template override first
        if (!empty($template)) {
            if ($client === 'admin') {
                $overridePath = JPATH_ADMINISTRATOR . '/templates/' . $template . '/html';
            } else {
                $overridePath = JPATH_SITE . '/templates/' . $template . '/html';
            }

            $templateLayoutPath = $overridePath . '/plg_' . $pluginGroup . '_' . $pluginName . '/' . $layout . '.php';

            if (File::exists($templateLayoutPath)) {
                return $templateLayoutPath;
            }
        }

        // Check plugin's tmpl folder
        $pluginPath = JPATH_PLUGINS . '/' . $pluginGroup . '/' . $pluginName . '/tmpl/' . $layout . '.php';

        if (File::exists($pluginPath)) {
            return $pluginPath;
        }

        return '';
    }

    /**
     * Get the layout path for a module.
     *
     * Searches for the layout file in the following order:
     * 1. Current template override
     * 2. Module's tmpl folder
     *
     * @param   string  $moduleName  The module name (e.g., 'mod_j2commerce_cart').
     * @param   string  $layout      The layout name (without .php extension).
     * @param   string  $client      The client type: 'site' (default) or 'admin'.
     *
     * @return  string  The full path to the layout file, or empty string if not found.
     *
     * @since   6.0.0
     */
    public static function getModuleLayoutPath(
        string $moduleName,
        string $layout = 'default',
        string $client = 'site'
    ): string {
        $template = self::getTemplate($client);

        // Determine base paths
        if ($client === 'admin') {
            $moduleBasePath   = JPATH_ADMINISTRATOR . '/modules/' . $moduleName;
            $templateBasePath = !empty($template)
                ? JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/' . $moduleName
                : '';
        } else {
            $moduleBasePath   = JPATH_SITE . '/modules/' . $moduleName;
            $templateBasePath = !empty($template)
                ? JPATH_SITE . '/templates/' . $template . '/html/' . $moduleName
                : '';
        }

        // Check template override first
        if (!empty($templateBasePath)) {
            $templateLayoutPath = $templateBasePath . '/' . $layout . '.php';

            if (File::exists($templateLayoutPath)) {
                return $templateLayoutPath;
            }
        }

        // Check module's tmpl folder
        $moduleLayoutPath = $moduleBasePath . '/tmpl/' . $layout . '.php';

        if (File::exists($moduleLayoutPath)) {
            return $moduleLayoutPath;
        }

        return '';
    }
}
