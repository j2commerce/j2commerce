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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

/**
 * Strapper Helper class for J2Commerce
 *
 * Handles loading of JavaScript and CSS assets for J2Commerce component
 * using Joomla 6 Web Asset Manager.
 *
 * @since  6.0.0
 */
class StrapperHelper
{
    /**
     * Singleton instance
     *
     * @var static|null
     * @since 6.0.0
     */
    protected static ?self $instance = null;

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
     * Web Asset Manager instance
     *
     * @var \Joomla\CMS\WebAsset\WebAssetManager|null
     * @since 6.0.0
     */
    protected $wa = null;

    /**
     * Component parameters (from config.xml)
     *
     * @var Registry|null
     * @since 6.0.0
     */
    protected ?Registry $params = null;

    /**
     * Template cache for performance
     *
     * @var array
     * @since 6.0.0
     */
    protected array $templateCache = [];

    /**
     * Scripts loaded cache to prevent duplicates
     *
     * @var array
     * @since 6.0.0
     */
    protected array $scriptsLoaded = [];

    /**
     * View context constants for asset loading
     *
     * @since 6.0.0
     */
    public const CONTEXT_PRODUCT_DETAIL = 'product_detail';
    public const CONTEXT_PRODUCT_LIST   = 'product_list';
    public const CONTEXT_CART           = 'cart';
    public const CONTEXT_CHECKOUT       = 'checkout';
    public const CONTEXT_MYPROFILE      = 'myprofile';
    public const CONTEXT_DEFAULT        = 'default';

    /**
     * Constructor
     *
     * @param array|null $properties Optional properties (unused, maintained for compatibility)
     * @since 6.0.0
     */
    public function __construct($properties = null)
    {
        try {
            $this->db     = Factory::getContainer()->get(DatabaseInterface::class);
            $this->app    = Factory::getApplication();
            $this->wa     = $this->app->getDocument()->getWebAssetManager();
            $this->params = ComponentHelper::getParams('com_j2commerce');
        } catch (\Exception $e) {
            // Log error and continue with null instances
            if ($this->app !== null) {
                $this->app->enqueueMessage(
                    'Error initializing StrapperHelper: ' . $e->getMessage(),
                    'error'
                );
            }
        }
    }

    /**
     * Get singleton instance of StrapperHelper
     *
     * @param array $config Optional configuration array (maintained for compatibility)
     *
     * @return self The StrapperHelper instance
     * @since 6.0.0
     */
    public static function getInstance(array $config = []): self
    {
        if (static::$instance === null) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * Get component parameter value
     *
     * Retrieves configuration values from component's config.xml parameters
     *
     * @param string $key     The parameter key
     * @param mixed  $default Default value if parameter not found
     *
     * @return mixed The parameter value or default
     * @since 6.0.0
     */
    protected function getParam(string $key, $default = null)
    {
        if ($this->params instanceof Registry) {
            return $this->params->get($key, $default);
        }

        return $default;
    }

    /**
     * Detect the current view context for asset loading
     *
     * Determines which type of page is being viewed to load appropriate assets.
     * Product detail pages need zoom/fancybox, list pages don't, etc.
     *
     * @return string One of the CONTEXT_* constants
     * @since 6.0.0
     */
    public function getViewContext(): string
    {
        if ($this->app === null) {
            return self::CONTEXT_DEFAULT;
        }

        try {
            $input  = $this->app->getInput();
            $option = $input->getCmd('option', '');

            // Only apply context detection for J2Commerce component
            if ($option !== 'com_j2commerce') {
                return self::CONTEXT_DEFAULT;
            }

            $view   = $input->getCmd('view', '');
            $task   = $input->getCmd('task', '');
            $layout = $input->getCmd('layout', '');

            // Detect view context
            return match (strtolower($view)) {
                'product' => self::CONTEXT_PRODUCT_DETAIL,
                'products', 'category', 'categories', 'producttags' => self::CONTEXT_PRODUCT_LIST,
                'cart', 'carts' => self::CONTEXT_CART,
                'checkout', 'checkouts' => self::CONTEXT_CHECKOUT,
                'myprofile', 'orders', 'order', 'addresses', 'address' => self::CONTEXT_MYPROFILE,
                default => self::CONTEXT_DEFAULT,
            };
        } catch (\Exception $e) {
            return self::CONTEXT_DEFAULT;
        }
    }

    /**
     * Check if current view is a product detail page
     *
     * @return bool True if on product detail page
     * @since 6.0.0
     */
    public function isProductDetailView(): bool
    {
        return $this->getViewContext() === self::CONTEXT_PRODUCT_DETAIL;
    }

    /**
     * Check if current view is a product list/category page
     *
     * @return bool True if on product list page
     * @since 6.0.0
     */
    public function isProductListView(): bool
    {
        return $this->getViewContext() === self::CONTEXT_PRODUCT_LIST;
    }

    /**
     * Check if current view is the cart page
     *
     * @return bool True if on cart page
     * @since 6.0.0
     */
    public function isCartView(): bool
    {
        return $this->getViewContext() === self::CONTEXT_CART;
    }

    /**
     * Check if current view is the checkout page
     *
     * @return bool True if on checkout page
     * @since 6.0.0
     */
    public function isCheckoutView(): bool
    {
        return $this->getViewContext() === self::CONTEXT_CHECKOUT;
    }

    /**
     * Add JavaScript files and inline scripts to the document
     *
     * Loads J2Commerce JavaScript files including validation,
     * admin scripts, and frontend scripts based on application context.
     *
     * @return void
     * @since 6.0.0
     */
    public function addJavaScript(): void
    {
        if ($this->wa === null || $this->app === null) {
            return;
        }

        try {
            // Load application-specific scripts
            if ($this->app->isClient('administrator')) {
                $this->loadAdminScripts();
            } else {
                $this->loadFrontendScripts();
            }

            // Trigger after JS event for plugins using Joomla 6 dispatcher
            $this->triggerPluginEvent('AfterAddJS');

        } catch (\Exception $e) {
            $this->logError('Error in addJS: ' . $e->getMessage());
        }
    }

    /**
     * Add CSS files to the document
     *
     * Loads J2Commerce CSS files including admin styles and frontend themes
     * based on application context using Web Asset Manager.
     *
     * @return void
     * @since 6.0.0
     */
    public function addCSS(): void
    {
        if ($this->wa === null || $this->app === null) {
            return;
        }

        try {
            // Load application-specific styles
            if ($this->app->isClient('administrator')) {
                $this->loadAdminCSS();
            } else {
                $this->loadFrontendCSS();
            }

            // Trigger after CSS event for plugins using Joomla 6 dispatcher
            $this->triggerPluginEvent('AfterAddCSS');

        } catch (\Exception $e) {
            $this->logError('Error in addCSS: ' . $e->getMessage());
        }
    }

    /**
     * Add JavaScript files (public method for compatibility)
     *
     * @return void
     * @since 6.0.0
     */
    public function addJS(): void
    {
        $this->addJavaScript();
    }

    /**
     * Add CSS files (public method for compatibility)
     *
     * @return void
     * @since 6.0.0
     */
    public function addStyleSheets(): void
    {
        $this->addCSS();
    }

    /**
     * Get the default template name for the current site
     *
     * Retrieves the default frontend template name from the database,
     * with caching to improve performance on subsequent calls.
     *
     * @return string The default template name
     * @since 6.0.0
     */
    public function getDefaultTemplate(): string
    {
        if ($this->db === null) {
            return '';
        }

        $cacheKey = 'default_template_1';

        if (!isset($this->templateCache[$cacheKey])) {
            try {
                $clientId = 0;
                $home     = 1;

                $query = $this->db->getQuery(true)
                    ->select($this->db->quoteName('template'))
                    ->from($this->db->quoteName('#__template_styles'))
                    ->where($this->db->quoteName('client_id') . ' = :clientId')
                    ->where($this->db->quoteName('home') . ' = :home')
                    ->bind(':clientId', $clientId, ParameterType::INTEGER)
                    ->bind(':home', $home, ParameterType::INTEGER);

                $this->db->setQuery($query);
                $this->templateCache[$cacheKey] = $this->db->loadResult() ?: '';
            } catch (\Exception $e) {
                $this->logError('Error getting default template: ' . $e->getMessage());
                $this->templateCache[$cacheKey] = '';
            }
        }

        return $this->templateCache[$cacheKey];
    }

    /**
     * Format file size in human readable format
     *
     * Converts a file size in bytes to a human-readable format
     * (bytes, Kb, Mb, Gb) with appropriate precision.
     *
     * @param int|float $filesize File size in bytes
     *
     * @return string Formatted file size string
     * @since 6.0.0
     */
    public function sizeFormat($filesize): string
    {
        $filesize = (float) $filesize;

        if ($filesize > 1073741824) {
            return number_format($filesize / 1073741824, 2) . " Gb";
        } elseif ($filesize >= 1048576) {
            return number_format($filesize / 1048576, 2) . " Mb";
        } elseif ($filesize >= 1024) {
            return number_format($filesize / 1024, 2) . " Kb";
        }
        return $filesize . " bytes";

    }

    /**
     * Load admin-specific JavaScript files
     *
     * Uses pre-registered assets from joomla.asset.json via Web Asset Manager.
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadAdminScripts(): void
    {
        if ($this->app === null) {
            return;
        }

        try {
            $wa = $this->app->getDocument()->getWebAssetManager();

            $wa->registerAndUseScript('com_j2commerce.admin', 'media/com_j2commerce/js/administrator/j2commerce.js', [], ['defer' => true]);
            $wa->registerAndUseScript('com_j2commerce.vendor.dual-listbox', 'media/com_j2commerce/vendor/dual-listbox/js/dual-listbox.js', [], ['defer' => true]);
            $wa->registerAndUseScript('com_j2commerce.vendor.chartjs', 'media/com_j2commerce/vendor/chartjs/js/chart.umd.min.js', [], ['defer' => true]);
            $wa->registerAndUseScript('com_j2commerce.admin.modal-products', 'media/com_j2commerce/js/administrator/modal-products.js', [], ['defer' => true]);

        } catch (\Exception $e) {
            $this->logError('Error loading admin scripts: ' . $e->getMessage());
        }
    }

    /**
     * Load frontend-specific JavaScript files
     *
     * Loads JavaScript files based on the current view context using pre-registered
     * assets from joomla.asset.json. Product detail pages load fancybox/swiper,
     * list pages load only core scripts.
     *
     * @param string|null $forceContext Optional context to force (for testing or manual override)
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadFrontendScripts(?string $forceContext = null): void
    {
        try {
            $wa      = Factory::getApplication()->getDocument()->getWebAssetManager();
            $context = $forceContext ?? $this->getViewContext();

            $wa->registerAndUseScript('com_j2commerce.site', 'media/com_j2commerce/js/site/j2commerce.js', [], ['defer' => true]);
            $wa->registerAndUseScript('plg_j2commerce_app_flexivariable.flexivariable', 'media/plg_j2commerce_app_flexivariable/js/flexivariable.js', [], ['defer' => true]);

            // Load context-specific scripts
            match ($context) {
                self::CONTEXT_PRODUCT_DETAIL => $this->loadProductDetailScripts($wa),
                self::CONTEXT_PRODUCT_LIST   => $this->loadProductListScripts($wa),
                self::CONTEXT_CART           => $this->loadCartScripts($wa),
                self::CONTEXT_CHECKOUT       => $this->loadCheckoutScripts($wa),
                default                      => null,
            };

        } catch (\Exception $e) {
            $this->logError('Error loading frontend scripts: ' . $e->getMessage());
        }
    }

    /**
     * Load scripts specific to product detail view
     *
     * Includes image zoom, fancybox lightbox, and any other detail-specific functionality.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadProductDetailScripts($wa): void
    {
        // Fancybox lightbox for image gallery (if enabled in config)
        $loadFancybox = (int) $this->getParam('load_fancybox', 1);
        if ($loadFancybox) {
            $wa->registerAndUseScript('com_j2commerce.vendor.fancybox', 'media/com_j2commerce/vendor/fancybox/js/fancybox.umd.js', [], ['defer' => true]);
        }

        // Swiper for product image carousel (if enabled in config)
        $loadSwiper = (int) $this->getParam('load_swiper', 1);
        if ($loadSwiper) {
            $wa->registerAndUseScript('com_j2commerce.vendor.swiper', 'media/com_j2commerce/vendor/swiper/js/swiper-bundle.min.js', [], ['defer' => true]);
        }
    }

    /**
     * Load scripts specific to product list/category view
     *
     * Lighter weight scripts for browsing products.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadProductListScripts($wa): void
    {
        // Product list pages only need core scripts (already loaded)
        // Add list-specific scripts here if needed in the future
    }

    /**
     * Load scripts specific to cart view
     *
     * Cart manipulation, quantity updates, etc.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadCartScripts($wa): void
    {
        // Cart-specific scripts can be added here
        // Core j2commerce.js handles basic cart functionality
    }

    /**
     * Load scripts specific to checkout view
     *
     * Form validation, payment processing, address handling.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadCheckoutScripts($wa): void
    {
        // Load Joomla form validation
        $wa->useScript('form.validate');

        // Checkout-specific scripts can be added here
    }

    /**
     * Load admin-specific CSS files
     *
     * Uses pre-registered assets from joomla.asset.json via Web Asset Manager.
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadAdminCSS(): void
    {
        if ($this->app === null) {
            return;
        }

        try {
            $wa = $this->app->getDocument()->getWebAssetManager();
            $wa->registerAndUseStyle('com_j2commerce.vendor.dual-listbox.css', 'media/com_j2commerce/vendor/dual-listbox/css/dual-listbox.css');
            $wa->registerAndUseStyle('com_j2commerce.editview', 'media/com_j2commerce/css/administrator/editview.css');
            $wa->registerAndUseStyle('com_j2commerce.admin.css', 'media/com_j2commerce/css/administrator/j2commerce_admin.css');

        } catch (\Exception $e) {
            $this->logError('Error loading admin CSS: ' . $e->getMessage());
        }
    }

    /**
     * Load frontend-specific CSS files
     *
     * Loads CSS files based on the current view context.
     * Core styles load on all pages, detail-specific styles only on product pages.
     *
     * @param string|null $forceContext Optional context to force (for testing or manual override)
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadFrontendCSS(?string $forceContext = null): void
    {
        try {
            $app     = Factory::getApplication();
            $wa      = $app->getDocument()->getWebAssetManager();
            $context = $forceContext ?? $this->getViewContext();

            // Only load CSS for HTML documents
            if ($app->getDocument()->getType() !== 'html') {
                return;
            }

            // Load core J2Commerce CSS if enabled in config
            $enableCss = (int) $this->getParam('j2commerce_enable_css', 1);

            if ($enableCss) {
                $this->loadCoreCSS($wa);
            }

            // Load context-specific styles
            match ($context) {
                self::CONTEXT_PRODUCT_DETAIL => $this->loadProductDetailCSS($wa),
                self::CONTEXT_PRODUCT_LIST   => $this->loadProductListCSS($wa),
                self::CONTEXT_CART           => $this->loadCartCSS($wa),
                self::CONTEXT_CHECKOUT       => $this->loadCheckoutCSS($wa),
                default                      => null,
            };

        } catch (\Exception $e) {
            $this->logError('Error loading frontend CSS: ' . $e->getMessage());
        }
    }

    /**
     * Load core CSS with template override support
     *
     * Checks for template overrides before falling back to component CSS.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadCoreCSS($wa): void
    {
        $template = $this->getDefaultTemplate();

        // Check for template override first (Joomla 4+ media folder pattern)
        if (file_exists(JPATH_SITE . '/media/templates/site/' . $template . '/css/j2commerce.css')) {
            // Template override - register and use dynamically
            $wa->registerAndUseStyle(
                'com_j2commerce.site',
                'media/templates/site/' . $template . '/css/j2commerce.css'
            );
        } elseif (file_exists(JPATH_SITE . '/templates/' . $template . '/css/j2commerce.css')) {
            // Legacy template folder pattern - register and use dynamically
            $wa->registerAndUseStyle(
                'com_j2commerce.site',
                'templates/' . $template . '/css/j2commerce.css'
            );
        } else {
            $wa->registerAndUseStyle('com_j2commerce.site', 'media/com_j2commerce/css/site/j2commerce.css');
        }
    }

    /**
     * Load CSS specific to product detail view
     *
     * Includes fancybox lightbox styles and any other detail-specific styles.
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadProductDetailCSS($wa): void
    {
        // Fancybox CSS for image gallery (if enabled in config)
        $loadFancybox = (int) $this->getParam('load_fancybox', 1);
        if ($loadFancybox) {
            $wa->registerAndUseStyle('com_j2commerce.vendor.fancybox.css', 'media/com_j2commerce/vendor/fancybox/css/fancybox.css');
        }

        // Swiper CSS for product image carousel (if enabled in config)
        $loadSwiper = (int) $this->getParam('load_swiper', 1);
        if ($loadSwiper) {
            $wa->registerAndUseStyle('com_j2commerce.vendor.swiper.css', 'media/com_j2commerce/vendor/swiper/css/swiper-bundle.min.css');
        }
    }

    /**
     * Load CSS specific to product list/category view
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadProductListCSS($wa): void
    {
        // Product list pages only need core styles (already loaded)
        // Add list-specific styles here if needed in the future
    }

    /**
     * Load CSS specific to cart view
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadCartCSS($wa): void
    {
        // Cart-specific styles can be added here
        // Core j2commerce.css handles basic cart styling
    }

    /**
     * Load CSS specific to checkout view
     *
     * @param \Joomla\CMS\WebAsset\WebAssetManager $wa Web Asset Manager instance
     *
     * @return void
     * @since 6.0.0
     */
    protected function loadCheckoutCSS($wa): void
    {
        // Checkout-specific styles can be added here
    }

    /**
     * Trigger plugin event using Joomla 6 Dispatcher/Event pattern
     *
     * @param string $event Event name (without 'onJ2Commerce' prefix)
     *
     * @return void
     * @since 6.0.0
     */
    protected function triggerPluginEvent(string $event): void
    {
        if ($this->app === null) {
            return;
        }

        try {
            // Ensure j2commerce plugins are loaded so their event subscribers are registered
            PluginHelper::importPlugin('j2commerce');

            // Get the event dispatcher from the container
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);

            // Create the event name
            $eventName = 'onJ2Commerce' . $event;

            // Create the event object with context
            $eventObject = new GenericEvent($eventName, [
                'subject' => $this,
                'params'  => $this->params,
            ]);

            // Dispatch the event
            $dispatcher->dispatch($eventName, $eventObject);

        } catch (\Exception $e) {
            $this->logError('Error triggering plugin event ' . $event . ': ' . $e->getMessage());
        }
    }

    /**
     * Log error message
     *
     * Uses Joomla's logging system instead of displaying to users.
     *
     * @param string $message Error message
     *
     * @return void
     * @since 6.0.0
     */
    protected function logError(string $message): void
    {
        try {
            \Joomla\CMS\Log\Log::add(
                $message,
                \Joomla\CMS\Log\Log::ERROR,
                'com_j2commerce'
            );
        } catch (\Exception $e) {
            // Fallback to error message if logging fails
            if ($this->app !== null) {
                $this->app->enqueueMessage($message, 'error');
            }
        }
    }

    /**
     * Load assets for a specific view context
     *
     * Allows plugins and modules to manually load context-specific assets.
     * Useful when rendering J2Commerce content outside the component context.
     *
     * @param string $context One of the CONTEXT_* constants
     *
     * @return void
     * @since 6.0.0
     */
    public function loadAssetsForContext(string $context): void
    {
        $this->loadFrontendScripts($context);
        $this->loadFrontendCSS($context);
    }

    /**
     * Load product detail assets explicitly
     *
     * Call this when displaying product detail content outside the normal
     * component view (e.g., in a module or custom plugin).
     *
     * @return void
     * @since 6.0.0
     */
    public function loadProductDetailAssets(): void
    {
        $this->loadAssetsForContext(self::CONTEXT_PRODUCT_DETAIL);
    }

    /**
     * Load product list assets explicitly
     *
     * Call this when displaying product list content outside the normal
     * component view (e.g., in a module or custom plugin).
     *
     * @return void
     * @since 6.0.0
     */
    public function loadProductListAssets(): void
    {
        $this->loadAssetsForContext(self::CONTEXT_PRODUCT_LIST);
    }

    /**
     * Load only the core J2Commerce assets (JS and CSS)
     *
     * Useful for minimal asset loading when only basic functionality is needed.
     *
     * @return void
     * @since 6.0.0
     */
    public function loadCoreAssets(): void
    {
        try {
            $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

            $wa->registerAndUseScript('com_j2commerce.site', 'media/com_j2commerce/js/site/j2commerce.js', [], ['defer' => true]);

            // Load core CSS (handles template overrides)
            $this->loadCoreCSS($wa);

        } catch (\Exception $e) {
            $this->logError('Error loading core assets: ' . $e->getMessage());
        }
    }
}
