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

use Joomla\CMS\Document\DocumentInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

/**
 * Modules helper class for J2Commerce.
 *
 * Provides static methods for loading and rendering Joomla modules
 * from specified positions within J2Commerce templates and views.
 *
 * @since  6.0.0
 */
class ModulesHelper
{
    /**
     * Singleton instance
     *
     * @var ModulesHelper|null
     * @since 6.0.0
     */
    protected static ?ModulesHelper $instance = null;

    /**
     * Get the singleton instance
     *
     * @param   array|null  $properties  Optional properties
     *
     * @return  ModulesHelper
     *
     * @since   6.0.0
     */
    public static function getInstance(?array $properties = null): ModulesHelper
    {
        if (self::$instance === null) {
            self::$instance = new self($properties);
        }

        return self::$instance;
    }

    /**
     * Load and render all modules from a specific position.
     *
     * Retrieves all published modules assigned to the given position
     * and renders them using the specified style.
     *
     * @param   string  $position  The module position name (e.g., 'j2commerce-product-top').
     * @param   string  $style     The module chrome style (default: 'xhtml').
     *                             Common styles: 'none', 'xhtml', 'html5', 'card', 'outline'.
     *
     * @return  string  The rendered HTML output of all modules in the position.
     *
     * @since   6.0.0
     *
     * @example
     * // Load modules from 'j2commerce-cart-below' position with default xhtml style
     * $cartModules = ModulesHelper::loadPosition('j2commerce-cart-below');
     *
     * // Load modules with card chrome style
     * $sidebarModules = ModulesHelper::loadPosition('j2commerce-sidebar', 'card');
     */
    public static function loadPosition(string $position, string $style = 'xhtml'): string
    {
        if (empty($position)) {
            return '';
        }

        $app      = Factory::getApplication();
        $document = $app->getDocument();

        // Only process for HTML documents
        if (!$document instanceof DocumentInterface || $document->getType() !== 'html') {
            return '';
        }

        $renderer = $document->loadRenderer('module');
        $params   = ['style' => $style];
        $contents = '';

        $modules = ModuleHelper::getModules($position);

        foreach ($modules as $module) {
            $contents .= $renderer->render($module, $params);
        }

        return $contents;
    }

    /**
     * Check if a module position has any published modules.
     *
     * Useful for conditionally showing container elements only when
     * modules exist in the position.
     *
     * @param   string  $position  The module position name to check.
     *
     * @return  bool  True if position has one or more published modules.
     *
     * @since   6.0.0
     *
     * @example
     * // Only render wrapper div if position has modules
     * if (ModulesHelper::hasModules('j2commerce-product-below')) {
     *     echo '<div class="product-modules">';
     *     echo ModulesHelper::loadPosition('j2commerce-product-below');
     *     echo '</div>';
     * }
     */
    public static function hasModules(string $position): bool
    {
        if (empty($position)) {
            return false;
        }

        $modules = ModuleHelper::getModules($position);

        return !empty($modules);
    }

    /**
     * Count the number of modules in a position.
     *
     * Returns the count of published modules assigned to the specified position.
     * Useful for determining grid layouts or conditional rendering.
     *
     * @param   string  $position  The module position name to count.
     *
     * @return  int  Number of published modules in the position.
     *
     * @since   6.0.0
     *
     * @example
     * // Determine grid column class based on module count
     * $count = ModulesHelper::countModules('j2commerce-footer');
     * $colClass = match(true) {
     *     $count >= 4 => 'col-md-3',
     *     $count === 3 => 'col-md-4',
     *     $count === 2 => 'col-md-6',
     *     default => 'col-12',
     * };
     */
    public static function countModules(string $position): int
    {
        if (empty($position)) {
            return 0;
        }

        $modules = ModuleHelper::getModules($position);

        return \count($modules);
    }

    /**
     * Load and render a single module by name.
     *
     * Finds and renders a specific module by its title/name.
     * This is useful when you need to display a specific module
     * regardless of its position assignment.
     *
     * @param   string  $name   The module name/title to load.
     * @param   string  $style  The module chrome style (default: 'xhtml').
     *
     * @return  string  The rendered HTML output of the module, or empty string if not found.
     *
     * @since   6.0.0
     *
     * @example
     * // Load a specific module by name
     * $miniCart = ModulesHelper::loadModule('J2Commerce Mini Cart', 'none');
     */
    public static function loadModule(string $name, string $style = 'xhtml'): string
    {
        if (empty($name)) {
            return '';
        }

        $app      = Factory::getApplication();
        $document = $app->getDocument();

        // Only process for HTML documents
        if (!$document instanceof DocumentInterface || $document->getType() !== 'html') {
            return '';
        }

        $module = ModuleHelper::getModule('mod_custom', $name);

        if (!$module || empty($module->id)) {
            // Try to find any module type with this title
            $module = self::findModuleByTitle($name);

            if (!$module) {
                return '';
            }
        }

        $renderer = $document->loadRenderer('module');
        $params   = ['style' => $style];

        return $renderer->render($module, $params);
    }

    /**
     * Find a module by its title.
     *
     * Searches for a published module matching the given title.
     * This is a helper method used internally by loadModule().
     *
     * @param   string  $title  The module title to search for.
     *
     * @return  object|null  The module object if found, null otherwise.
     *
     * @since   6.0.0
     */
    private static function findModuleByTitle(string $title): ?object
    {
        if (empty($title)) {
            return null;
        }

        // Get all loaded modules and search by title
        // Note: This iterates through all positions to find the module
        $app = Factory::getApplication();

        // Get all module positions from the document
        $positions = [];

        try {
            $document = $app->getDocument();

            if ($document instanceof \Joomla\CMS\Document\HtmlDocument) {
                // Get buffer to find module positions - this is a simplified approach
                // In practice, we rely on getModule() working correctly
            }
        } catch (\Exception $e) {
            return null;
        }

        // The most reliable way is to query the database directly
        $db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);

        $clientId = $app->isClient('administrator') ? 1 : 0;

        $query->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('title') . ' = :title')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = :clientId')
            ->bind(':title', $title)
            ->bind(':clientId', $clientId, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);
        $module = $db->loadObject();

        return $module ?: null;
    }

    /**
     * Get all module positions used by J2Commerce.
     *
     * Returns an array of recommended module position names
     * for use within J2Commerce templates and layouts.
     *
     * @return  array<string, string>  Associative array of position names and descriptions.
     *
     * @since   6.0.0
     *
     * @example
     * // Get all J2Commerce module positions for template configuration
     * $positions = ModulesHelper::getJ2CommercePositions();
     * foreach ($positions as $position => $description) {
     *     echo "$position: $description\n";
     * }
     */
    public static function getJ2CommercePositions(): array
    {
        return [
            'j2commerce-product-top'     => 'Above product details',
            'j2commerce-product-bottom'  => 'Below product details',
            'j2commerce-product-sidebar' => 'Product page sidebar',
            'j2commerce-cart-top'        => 'Above cart contents',
            'j2commerce-cart-bottom'     => 'Below cart contents',
            'j2commerce-checkout-top'    => 'Above checkout form',
            'j2commerce-checkout-bottom' => 'Below checkout form',
            'j2commerce-category-top'    => 'Above category listing',
            'j2commerce-category-bottom' => 'Below category listing',
            'j2commerce-order-top'       => 'Above order details',
            'j2commerce-order-bottom'    => 'Below order details',
        ];
    }
}
