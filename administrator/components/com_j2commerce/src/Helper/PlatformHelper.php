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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * J2Commerce Platform Helper Class
 *
 * Provides platform-specific functionality for J2Commerce, including application management,
 * URL generation, asset management, and event handling. This class maintains backward
 * Platform abstraction layer using modern Joomla 6 patterns.
 *
 * @since 6.0.0
 */
class PlatformHelper
{
    /**
     * Static instance for singleton pattern
     *
     * @var PlatformHelper|null
     * @since 6.0.0
     */
    private static ?PlatformHelper $instance = null;

    /**
     * Application instance
     *
     * @var CMSApplication
     * @since 6.0.0
     */
    private CMSApplication $app;

    /**
     * Database instance
     *
     * @var DatabaseInterface
     * @since 6.0.0
     */
    private DatabaseInterface $db;

    /**
     * Web Asset Manager instance
     *
     * @var WebAssetManager
     * @since 6.0.0
     */
    private WebAssetManager $webAssetManager;

    /**
     * Constructor - Initialize platform helper
     *
     * @since 6.0.0
     */
    private function __construct()
    {
        $this->app             = Factory::getApplication();
        $this->db              = Factory::getContainer()->get(DatabaseInterface::class);
        $this->webAssetManager = Factory::getApplication()->getDocument()->getWebAssetManager();
    }

    /**
     * Get singleton instance of PlatformHelper
     *
     * @return PlatformHelper The platform helper instance
     * @since 6.0.0
     */
    public static function getInstance(): PlatformHelper
    {
        return self::$instance ??= new self();
    }

    /**
     * Get the application instance
     *
     * @return CMSApplication The application instance
     * @since 6.0.0
     */
    public function application(): CMSApplication
    {
        return $this->app;
    }

    /**
     * Redirect to a URL with optional message
     *
     * @param string $url     The URL to redirect to
     * @param string $message Optional message to display
     * @param string $notice  Message type (info, warning, error, success)
     * @return void
     * @since 6.0.0
     */
    public function redirect(string $url, string $message = '', string $notice = 'info'): void
    {
        if (!empty($message)) {
            $this->app->enqueueMessage($message, $notice);
        }

        $this->app->redirect($url);
    }

    /**
     * Check if we're running in a specific client
     *
     * @param string $identifier Client identifier ('site', 'administrator', 'cli')
     * @return bool True if running in the specified client
     * @since 6.0.0
     */
    public function isClient(string $identifier = 'site'): bool
    {
        return $this->app->isClient($identifier);
    }

    /**
     * Convert input to integer with default value
     *
     * @param mixed $input   Input value to convert
     * @param int|null $default Default value if conversion fails
     * @return int|null The converted integer or default value
     * @since 6.0.0
     */
    public function toInteger($input, ?int $default = null): ?int
    {
        if ($input === null) {
            return $default;
        }

        $converted = filter_var($input, FILTER_VALIDATE_INT);
        return $converted !== false ? $converted : $default;
    }

    /**
     * Convert object to array recursively
     *
     * @param object $source  Source object to convert
     * @param bool   $recurse Whether to recurse into sub-objects
     * @param string|null $regex Optional regex to filter property names
     * @return array The converted array
     * @since 6.0.0
     */
    public function fromObject(object $source, bool $recurse = true, ?string $regex = null): array
    {
        $result = [];

        foreach (get_object_vars($source) as $key => $value) {
            if ($regex !== null && !preg_match($regex, $key)) {
                continue;
            }

            if ($recurse && \is_object($value)) {
                $result[$key] = $this->fromObject($value, $recurse, $regex);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert array to object recursively
     *
     * @param array  $array     Source array to convert
     * @param string $class     Class name for the resulting object
     * @param bool   $recursive Whether to recurse into sub-arrays
     * @return object The converted object
     * @since 6.0.0
     */
    public function toObject(array $array, string $class = 'stdClass', bool $recursive = true): object
    {
        $object = new $class();

        foreach ($array as $key => $value) {
            if ($recursive && \is_array($value)) {
                $object->$key = $this->toObject($value, $class, $recursive);
            } else {
                $object->$key = $value;
            }
        }

        return $object;
    }

    /**
     * Convert array to string representation
     *
     * @param array  $array        Source array
     * @param string $innerGlue    Glue for key=value pairs
     * @param string $outerGlue    Glue between pairs
     * @param bool   $keepOuterKey Whether to keep outer keys
     * @return string The string representation
     * @since 6.0.0
     */
    public function toString(array $array, string $innerGlue = '=', string $outerGlue = ' ', bool $keepOuterKey = false): string
    {
        $pairs = [];

        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $value = $this->toString($value, $innerGlue, $outerGlue, $keepOuterKey);
            }

            if ($keepOuterKey) {
                $pairs[] = $key . $innerGlue . $value;
            } else {
                $pairs[] = $value;
            }
        }

        return implode($outerGlue, $pairs);
    }

    /**
     * Get value from array with default and type filtering
     *
     * @param array  $array   Source array
     * @param string $name    Key name to retrieve
     * @param mixed  $default Default value if key doesn't exist
     * @param string $type    Type filter to apply
     * @return mixed The retrieved value
     * @since 6.0.0
     */
    public function getValue(array $array, string $name, $default = null, string $type = '')
    {
        $value = $array[$name] ?? $default;

        if (empty($type)) {
            return $value;
        }

        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return \is_array($value) ? $value : [$value];
            default:
                return $value;
        }
    }

    /**
     * Load and execute extra behavior/functionality
     *
     * @param string $behaviour  The behavior to load
     * @param mixed  ...$methodArgs Arguments to pass to the behavior
     * @return mixed Result of the behavior execution
     * @since 6.0.0
     */
    public function loadExtra(string $behaviour, ...$methodArgs)
    {
        // This method would typically load additional behaviors or plugins
        // For now, we'll implement a basic plugin trigger mechanism
        return PluginHelper::importPlugin($behaviour) ? true : false;
    }

    /**
     * Add path to include path
     *
     * @param string $path Path to add
     * @return bool True on success
     * @since 6.0.0
     */
    public function addIncludePath(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $currentPath = get_include_path();
        if (strpos($currentPath, $path) === false) {
            set_include_path($currentPath . PATH_SEPARATOR . $path);
        }

        return true;
    }

    /**
     * Check if admin menu module is available
     *
     * @return bool True if admin menu module is available
     * @since 6.0.0
     */
    public function checkAdminMenuModule(): bool
    {
        if (!$this->isClient('administrator')) {
            return false;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_menu'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 1');

        $db->setQuery($query);
        $count = (int) $db->loadResult();

        return $count > 0;
    }

    /**
     * Add script asset using WebAssetManager
     *
     * @param string $asset        Asset name
     * @param string $uri          Asset URI
     * @param array  $options      Asset options
     * @param array  $attributes   Asset attributes
     * @param array  $dependencies Asset dependencies
     * @return self For method chaining
     * @since 6.0.0
     */
    public function addScript(string $asset, string $uri, array $options = [], array $attributes = [], array $dependencies = []): self
    {
        try {
            // Register the asset if it doesn't exist
            if (!$this->webAssetManager->assetExists('script', $asset)) {
                $this->webAssetManager->registerScript($asset, $uri, $options, $attributes, $dependencies);
            }

            // Use the asset
            $this->webAssetManager->useScript($asset);
        } catch (\RuntimeException $e) {
            // Fallback to HTMLHelper for compatibility
            HTMLHelper::_('script', $uri, $options, $attributes);
        }

        return $this;
    }

    /**
     * Add style asset using WebAssetManager
     *
     * @param string $asset        Asset name
     * @param string $uri          Asset URI
     * @param array  $options      Asset options
     * @param array  $attributes   Asset attributes
     * @param array  $dependencies Asset dependencies
     * @return self For method chaining
     * @since 6.0.0
     */
    public function addStyle(string $asset, string $uri, array $options = [], array $attributes = [], array $dependencies = []): self
    {
        try {
            // Register the asset if it doesn't exist
            if (!$this->webAssetManager->assetExists('style', $asset)) {
                $this->webAssetManager->registerStyle($asset, $uri, $options, $attributes, $dependencies);
            }

            // Use the asset
            $this->webAssetManager->useStyle($asset);
        } catch (\RuntimeException $e) {
            // Fallback to HTMLHelper for compatibility
            HTMLHelper::_('stylesheet', $uri, $options, $attributes);
        }

        return $this;
    }

    /**
     * Add inline script using WebAssetManager
     *
     * @param string $content      Script content
     * @param array  $options      Script options
     * @param array  $attributes   Script attributes
     * @param array  $dependencies Script dependencies
     * @return self For method chaining
     * @since 6.0.0
     */
    public function addInlineScript(string $content, array $options = [], array $attributes = [], array $dependencies = []): self
    {
        try {
            $this->webAssetManager->addInlineScript($content, $options, $attributes, $dependencies);
        } catch (\RuntimeException $e) {
            // Fallback: add to document directly
            $doc = Factory::getApplication()->getDocument();
            $doc->addScriptDeclaration($content);
        }

        return $this;
    }

    /**
     * Add inline style using WebAssetManager
     *
     * @param string $content      Style content
     * @param array  $options      Style options
     * @param array  $attributes   Style attributes
     * @param array  $dependencies Style dependencies
     * @return self For method chaining
     * @since 6.0.0
     */
    public function addInlineStyle(string $content, array $options = [], array $attributes = [], array $dependencies = []): self
    {
        try {
            $this->webAssetManager->addInlineStyle($content, $options, $attributes, $dependencies);
        } catch (\RuntimeException $e) {
            // Fallback: add to document directly
            $doc = Factory::getApplication()->getDocument();
            $doc->addStyleDeclaration($content);
        }

        return $this;
    }

    /**
     * Raise an application error
     *
     * @param int    $code    Error code
     * @param string $message Error message
     * @return void
     * @throws \RuntimeException
     * @since 6.0.0
     */
    public function raiseError(int $code, string $message): void
    {
        throw new \RuntimeException($message, $code);
    }

    /**
     * Get user profile URL for J2Commerce
     *
     * @param array $params  URL parameters
     * @param bool  $is_xml  Whether to return XML-safe URL
     * @param bool  $no_sef  Whether to disable SEF routing
     * @return string The profile URL
     * @since 6.0.0
     */
    public function getMyprofileUrl(array $params = [], bool $is_xml = false, bool $no_sef = false): string
    {
        $defaultParams = [
            'option' => 'com_j2commerce',
            'view'   => 'myprofile',
        ];

        $urlParams = array_merge($defaultParams, $params);
        $url       = 'index.php?' . http_build_query($urlParams);

        if (!$no_sef) {
            $url = $this->routeUrl($url, $is_xml);
        }

        return $url;
    }

    /**
     * Get checkout URL for J2Commerce
     *
     * @param array $params URL parameters
     * @return string The checkout URL
     * @since 6.0.0
     */
    public function getCheckoutUrl(array $params = []): string
    {
        $defaultParams = [
            'option' => 'com_j2commerce',
            'view'   => 'checkout',
        ];

        $urlParams = array_merge($defaultParams, $params);
        $url       = 'index.php?' . http_build_query($urlParams);

        return $this->routeUrl($url);
    }

    /**
     * Get thank you page URL for J2Commerce
     *
     * @param array $params URL parameters
     * @return string The thank you page URL
     * @since 6.0.0
     */
    public function getThankyouPageUrl(array $params = []): string
    {
        $defaultParams = [
            'option' => 'com_j2commerce',
            'view'   => 'confirmation',
        ];

        // Include order_id from user state if not already provided
        if (!isset($params['order_id'])) {
            $orderId = Factory::getApplication()->getUserState('j2commerce.order_id', '');

            if (!empty($orderId)) {
                $defaultParams['order_id'] = $orderId;
            }
        }

        $urlParams = array_merge($defaultParams, $params);
        $url       = 'index.php?' . http_build_query($urlParams);

        // xhtml=false: URL is used in JSON for JS redirects, not in HTML attributes
        return $this->routeUrl($url, false);
    }

    /**
     * Get cart URL for J2Commerce
     *
     * @param array $params URL parameters
     * @return string The cart URL
     * @since 6.0.0
     */
    public function getCartUrl(array $params = []): string
    {
        $defaultParams = [
            'option' => 'com_j2commerce',
            'view'   => 'carts',
        ];

        // If task is provided without controller prefix, add 'carts.' prefix
        // Joomla 6 requires task format: controller.method (e.g., carts.addItem)
        if (isset($params['task']) && strpos($params['task'], '.') === false) {
            $params['task'] = 'carts.' . $params['task'];
        }

        $urlParams = array_merge($defaultParams, $params);
        $url       = 'index.php?' . http_build_query($urlParams);

        return $this->routeUrl($url);
    }

    /**
     * Get product URL for J2Commerce
     *
     * Handles both single product view and product list view URLs.
     * When 'id' is provided (or legacy 'task' => 'view'), returns single product URL.
     * Otherwise returns product list URL.
     *
     * @param array $params      URL parameters
     * @param bool  $is_tag_view Whether this is for tag view
     * @return string The product URL
     * @since 6.0.0
     */
    public function getProductUrl(array $params = [], bool $is_tag_view = false): string
    {
        // Handle legacy FOF 'task' => 'view' pattern - convert to native MVC 'view' => 'product'
        // FOF 2 used task=view&id=X, native Joomla uses view=product&id=X
        $isSingleProduct = isset($params['id']) || (isset($params['task']) && $params['task'] === 'view');

        // Remove legacy 'task' => 'view' - not used in native Joomla MVC for product display
        if (isset($params['task']) && $params['task'] === 'view') {
            unset($params['task']);
        }

        // Determine the view based on context
        // Single product view always takes priority over tag/list context
        if ($isSingleProduct && isset($params['id'])) {
            $view = 'product';
        } elseif ($is_tag_view) {
            $view = 'producttags';
        } else {
            $view = 'products';
        }

        $defaultParams = [
            'option' => 'com_j2commerce',
            'view'   => $view,
        ];

        $urlParams = array_merge($defaultParams, $params);
        $url       = 'index.php?' . http_build_query($urlParams);

        return $this->routeUrl($url);
    }

    /**
     * Get root URL of the site
     *
     * @return string The root URL
     * @since 6.0.0
     */
    public function getRootUrl(): string
    {
        return Uri::root();
    }

    /**
     * Get Registry instance from JSON data
     *
     * @param string $json     JSON data
     * @param bool   $is_array Whether to return as array
     * @return Registry|array The registry instance or array
     * @since 6.0.0
     */
    public function getRegistry(string|Registry $json, bool $is_array = false)
    {
        $registry = $json instanceof Registry ? $json : new Registry($json);

        return $is_array ? $registry->toArray() : $registry;
    }

    /**
     * Get image path with proper URL handling
     *
     * @param string $path Image path
     * @return string The full image URL
     * @since 6.0.0
     */
    public function getImagePath(string $path): string
    {
        if (empty($path)) {
            return '';
        }

        // If it's already a full URL, return as is
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Remove leading slash if present
        $path = ltrim($path, '/');

        return Uri::root() . $path;
    }

    /**
     * Get translated label with fallback
     *
     * @param string $label_info Label information or key
     * @return string The translated label
     * @since 6.0.0
     */
    public function getLabel(string $label_info = ''): string
    {
        if (empty($label_info)) {
            return '';
        }

        // If it starts with COM_, treat as language key
        if (strpos($label_info, 'COM_') === 0 || strpos($label_info, 'J2') === 0) {
            return Text::_($label_info);
        }

        // Otherwise return as is
        return $label_info;
    }

    /**
     * Get menu links for J2Commerce
     *
     * @return array Array of menu links
     * @since 6.0.0
     */
    public function getMenuLinks(): array
    {
        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select(['id', 'title', 'alias', 'link', 'type'])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_j2commerce%'))
            ->order($db->quoteName('lft') . ' ASC');

        $db->setQuery($query);

        try {
            return $db->loadObjectList() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Trigger J2Commerce events
     *
     * @param string $event_name Event name to trigger
     * @param array  $args       Arguments to pass to event handlers
     * @return array Array of results from event handlers
     * @since 6.0.0
     */
    public function eventTrigger(string $event_name, array $args = []): array
    {
        // Import all plugins to ensure event handlers are loaded
        PluginHelper::importPlugin('j2commerce');
        PluginHelper::importPlugin('j2commerce'); // For backward compatibility

        // Trigger the event
        $dispatcher = Factory::getApplication()->getDispatcher();
        $results    = $dispatcher->trigger($event_name, $args);

        return $results ?: [];
    }

    /** Route::_() requires SiteRouter — return raw URL in API context. */
    private function routeUrl(string $url, bool $xhtml = true): string
    {
        if (Factory::getApplication() instanceof \Joomla\CMS\Application\ApiApplication) {
            return $url;
        }

        return Route::_($url, $xhtml);
    }
}
