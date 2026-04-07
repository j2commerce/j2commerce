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

use Exception;
use J2Commerce\Component\J2commerce\Administrator\Event\PluginEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper as JoomlaPluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;

/**
 * Plugin Helper class for J2Commerce
 *
 * Provides plugin management, event triggering, and plugin data retrieval functionality.
 * This class is migrated from the J2Commerce plugin helper with modern Joomla 5 patterns
 * and includes backward compatibility with the original J2Plugins class.
 *
 * Key improvements:
 * - Uses modern Joomla 5 database access patterns
 * - Proper exception handling and error logging
 * - Enhanced type safety with parameter casting
 * - Modern dependency injection patterns
 * - Comprehensive PHPDoc documentation
 *
 * @since  6.0.0
 */
class PluginHelper
{
    /**
     * Singleton instance
     *
     * @var PluginHelper|null
     * @since 6.0.0
     */
    protected static ?PluginHelper $instance = null;

    /**
     * Database instance
     *
     * @var DatabaseInterface|null
     * @since 6.0.0
     */
    protected ?DatabaseInterface $db = null;

    /**
     * Application instance
     *
     * @var \Joomla\CMS\Application\CMSApplication|null
     * @since 6.0.0
     */
    protected $app = null;

    /**
     * Constructor
     *
     * @param array|null $properties Optional properties (unused, maintained for compatibility)
     * @since 6.0.0
     */
    public function __construct($properties = null)
    {
        try {
            $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
            $this->app = Factory::getApplication();
        } catch (\Exception $e) {
            // Log error and continue with null instances
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error initializing PluginHelper: ' . $e->getMessage(),
                    'error'
                );
            }
        }
    }

    /**
     * Get singleton instance of PluginHelper
     *
     * @param array $config Optional configuration array (maintained for compatibility)
     *
     * @return PluginHelper The PluginHelper instance
     * @since 6.0.0
     */
    public static function getInstance(array $config = []): PluginHelper
    {
        if (static::$instance === null) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * Only returns plugins that have a specific event
     *
     * @param string $eventName The event name to check for
     * @param string $folder    The plugin folder (default: 'J2Commerce')
     *
     * @return array Array of plugin objects that have the specified event
     * @since 6.0.0
     */
    public function getPluginsWithEvent($eventName, $folder = 'J2Commerce'): array
    {
        $return = [];

        if ($plugins = $this->getPlugins($folder)) {
            foreach ($plugins as $plugin) {
                if ($this->hasEvent($plugin, $eventName)) {
                    $return[] = $plugin;
                }
            }
        }

        // Import J2Commerce plugins and trigger after event
        JoomlaPluginHelper::importPlugin('j2commerce');
        if ($this->app !== null) {
            $this->app->triggerEvent('onJ2CommerceAfterGetPluginsWithEvent', [&$return]);
        }

        return $return;
    }

    /**
     * Returns array of active plugins
     *
     * @param string $folder The plugin folder (default: 'J2Commerce')
     *
     * @return array Array of active plugin objects
     * @since 6.0.0
     */
    public function getPlugins($folder = 'J2Commerce'): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $folder = strtolower((string) $folder);

            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('enabled') . ' = 1')
                ->where($this->db->quoteName('folder') . ' = :folder')
                ->order($this->db->quoteName('ordering') . ' ASC')
                ->bind(':folder', $folder);

            $this->db->setQuery($query);
            $data = $this->db->loadObjectList();

            return $data ?: [];
        } catch (\Exception $e) {
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error retrieving plugins: ' . $e->getMessage(),
                    'error'
                );
            }
            return [];
        }
    }

    /**
     * Returns an active plugin by element name
     *
     * @param string $element The plugin element name
     * @param string $folder  The plugin folder (default: 'j2commerce')
     *
     * @return object|false Plugin object or false if not found
     * @since 6.0.0
     */
    public function getPlugin($element, $folder = 'j2commerce')
    {
        if (empty($element) || $this->db === null) {
            return false;
        }

        try {
            $element = (string) $element;
            $folder  = strtolower((string) $folder);

            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('enabled') . ' = 1')
                ->where($this->db->quoteName('folder') . ' = :folder')
                ->where($this->db->quoteName('element') . ' = :element')
                ->bind(':folder', $folder)
                ->bind(':element', $element);

            $this->db->setQuery($query);
            $row = $this->db->loadObject();

            return $row ?: false;
        } catch (\Exception $e) {
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error retrieving plugin: ' . $e->getMessage(),
                    'error'
                );
            }
            return false;
        }
    }

    /**
     * Returns HTML content from plugin events
     *
     * @param string $event   The event name to trigger
     * @param array  $options Array of options to pass to the event
     * @param string $method  Display method ('vertical' or 'tabs', default: 'vertical')
     *
     * @return string HTML content from plugins
     * @since 6.0.0
     */
    public function getPluginsContent($event, $options, $method = 'vertical'): string
    {
        $text = "";

        if (!$event || $this->app === null) {
            return $text;
        }

        $results = $this->app->triggerEvent($event, $options);

        if ((!\count($results)) > 0) {
            return $text;
        }

        // Grab content based on method
        switch (strtolower((string) $method)) {
            case "vertical":
                for ($i = 0, $iMax = \count($results); $i < $iMax; $i++) {
                    $result  = $results[$i];
                    $title   = $result[1] ? Text::_($result[1]) : Text::_('Info');
                    $content = $result[0];

                    // Vertical display
                    $text .= '<p>' . $content . '</p>';
                }
                break;
            case "tabs":
                // Tabs implementation could be added here if needed
                break;
        }

        return $text;
    }

    /**
     * Checks if a plugin has a specific event
     *
     * @param object $element   The plugin object
     * @param string $eventName The event name to check for
     *
     * @return bool True if plugin has the event, false otherwise
     * @since 6.0.0
     */
    public function hasEvent($element, $eventName): bool
    {
        $success = false;

        if (!$element || !\is_object($element)) {
            return $success;
        }

        if (!$eventName || !\is_string($eventName)) {
            return $success;
        }

        if ($this->app === null) {
            return $success;
        }

        try {
            // Import the plugin
            JoomlaPluginHelper::importPlugin(strtolower('J2Commerce'), $element->element);

            // Trigger the event to check if plugin responds
            $result = $this->app->triggerEvent($eventName, [$element]);
            if (\in_array(true, $result, true)) {
                $success = true;
            }
        } catch (\Exception $e) {
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error checking plugin event: ' . $e->getMessage(),
                    'error'
                );
            }
        }

        return $success;
    }

    /**
     * Enable the J2Commerce system plugin
     *
     * @return bool True on success, false on failure
     * @since 6.0.0
     */
    public function enableJ2CommercePlugin(): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__extensions'))
                ->set($this->db->quoteName('enabled') . ' = 1')
                ->where($this->db->quoteName('folder') . ' = ' . $this->db->quote('system'))
                ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('j2commerce'));

            $this->db->setQuery($query);
            $this->db->execute();

            return true;
        } catch (\Exception $e) {
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error enabling J2Commerce plugin: ' . $e->getMessage(),
                    'error'
                );
            }
            return false;
        }
    }

    /**
     * Import catalog plugins (content plugins)
     *
     * @return void
     * @since 6.0.0
     */
    public function importCatalogPlugins(): void
    {
        JoomlaPluginHelper::importPlugin('content');
    }

    /**
     * Trigger a plugin event using modern Joomla 5 event dispatcher
     *
     * This method uses the modern PSR-14 event dispatcher pattern with a custom PluginEvent class.
     * It replaces the legacy trigger-based approach and provides type-safe event handling.
     *
     * The method returns a PluginEvent object which contains all event data and results.
     * Plugins can modify the event by calling methods on the event object:
     * - $event->setEventResult($data) to set/replace the result
     * - $event->addResult($data) to append to the result array
     * - $event->getArgument('name') to access event arguments
     *
     * Example usage:
     * ```php
     * $icons = [];
     * $event = J2CommerceHelper::plugin()->event('GetQuickIcons', ['icons' => &$icons]);
     * $result = $event->getEventResult();
     * ```
     *
     * @param string $event  The event name (without prefix)
     * @param array  $args   Arguments to pass to the event
     * @param string $prefix Event prefix (default: 'onJ2Commerce')
     *
     * @return PluginEvent The event object containing all results and data
     * @since 6.0.0
     */
    public function event(string $event, array $args = [], string $prefix = 'onJ2Commerce'): PluginEvent
    {
        // Return empty event if no event name provided
        if (empty($event)) {
            return new PluginEvent($prefix, $args);
        }

        // Import J2Commerce plugins to ensure they're available
        JoomlaPluginHelper::importPlugin('j2commerce');
        $this->importCatalogPlugins();

        // Get application and dispatcher
        $app        = Factory::getApplication();
        $dispatcher = $app->getDispatcher();

        // Build event name with prefix
        $eventName = $prefix . $event;

        $eventObject = new PluginEvent($eventName, $args);

        // Dispatch event to all registered plugins
        $dispatcher->dispatch($eventName, $eventObject);

        return $eventObject;
    }


    /**
     * Method to get the HTML output of an event
     *
     * @param string $event  The event name (without prefix)
     * @param array  $args   Arguments to pass to the event
     * @param string $prefix Event prefix (default: 'onJ2Commerce')
     *
     * @return PluginEvent Event object containing results
     * @since 6.0.0
     */
    public function eventWithHtml(string $event, array $args = [], string $prefix = 'onJ2Commerce'): PluginEvent
    {
        // Return empty event if no event name provided
        if (empty($event)) {
            return new PluginEvent($prefix, $args);
        }

        // Import J2Commerce plugins to ensure they're available
        JoomlaPluginHelper::importPlugin('j2commerce');
        $this->importCatalogPlugins();

        $app        = Factory::getApplication();
        $dispatcher = $app->getDispatcher();

        $eventName   = $prefix . $event;
        $eventObject = new PluginEvent($eventName, $args);

        // Dispatch the event
        $dispatcher->dispatch($eventName, $eventObject);

        $results = $eventObject->getArgument('result', []);

        $html = '';
        foreach ($results as $result) {
            if (\is_string($result)) {
                $html .= $result;
            }
        }

        $eventObject->setArgument('html', $html);

        return $eventObject;
    }

    /**
     * Method to get array output from an event using modern Joomla 5 event dispatcher
     *
     * This method uses the modern PSR-14 event dispatcher pattern with a custom PluginEvent class
     * and returns the event result as an array. It's a convenience wrapper around the event() method
     * for cases where you need array output directly.
     *
     * The method dispatches the event and returns the event result as an array.
     * If no result is set or the result is not an array, an empty array is returned.
     *
     * Example usage:
     * ```php
     * $icons = J2CommerceHelper::plugin()->eventWithArray('GetQuickIcons', ['context' => 'dashboard']);
     * ```
     *
     * @param string $event  The event name (without prefix)
     * @param array  $args   Arguments to pass to the event
     * @param string $prefix Event prefix (default: 'onJ2Commerce')
     *
     * @return array Array from event result
     * @since 6.0.0
     */
    public function eventWithArray(string $event, array $args = [], string $prefix = 'onJ2Commerce'): array
    {
        // Return empty array if no event name provided
        if (empty($event)) {
            return [];
        }

        // Use the modern event method to dispatch the event
        $eventObject = $this->event($event, $args, $prefix);

        // Get the event result
        $result = $eventObject->getEventResult();

        // Ensure we return an array
        if (!\is_array($result)) {
            return [];
        }

        return $result;
    }

    /**
     * Method to get structured app data from plugin events
     *
     * Collects structured arrays from plugins for rendering in accordion-style UI.
     * Each plugin should return an array with 'element', 'name', 'html' or 'form_xml' keys.
     *
     * Expected return structure from plugins:
     * ```php
     * [
     *     'element' => 'app_bulkdiscount',   // Plugin element name
     *     'name' => 'PLG_J2COMMERCE_APP_*',  // Language string for title
     *     'html' => null,                     // Raw HTML (null if using form_xml)
     *     'form_xml' => '/path/to/form.xml', // Path to forms XML (null if using html)
     *     'data' => [],                       // Data to bind to form
     *     'form_prefix' => 'jform[attribs]', // Form field prefix
     * ]
     * ```
     *
     * @param string $event  The event name (without prefix)
     * @param array  $args   Arguments to pass to the event
     * @param string $prefix Event prefix (default: 'onJ2Commerce')
     *
     * @return array Array of structured app data from plugins
     * @since 6.0.0
     */
    public function eventWithAppData(string $event, array $args = [], string $prefix = 'onJ2Commerce'): array
    {
        if (empty($event)) {
            return [];
        }

        JoomlaPluginHelper::importPlugin('j2commerce');
        $this->importCatalogPlugins();

        $app         = Factory::getApplication();
        $dispatcher  = $app->getDispatcher();
        $eventName   = $prefix . $event;
        $eventObject = new PluginEvent($eventName, $args);
        $dispatcher->dispatch($eventName, $eventObject);

        $results = $eventObject->getArgument('result', []);

        $apps = [];
        foreach ($results as $result) {
            if (\is_array($result) && isset($result['element'])) {
                $apps[] = $result;
            }
        }

        return $apps;
    }

}
