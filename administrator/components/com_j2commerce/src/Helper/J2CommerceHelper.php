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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * J2Commerce helper.
 *
 * @since  6.0.0
 */
class J2CommerceHelper extends ContentHelper
{
    /**
     * Configure the Linkbar (current working implementation)
     *
     * @param   string  $vName  The name of the active view.
     *
     * @return  void
     *
     * @since   6.0.0
     */
    public static function addSubmenu($vName)
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            Sidebar::addEntry(
                Text::_('COM_J2COMMERCE_DASHBOARD'),
                'index.php?option=com_j2commerce&view=dashboard',
                $vName == 'dashboard'
            );

            Sidebar::addEntry(
                Text::_('COM_J2COMMERCE_CURRENCIES'),
                'index.php?option=com_j2commerce&view=currencies',
                $vName == 'currencies'
            );

            Sidebar::addEntry(
                Text::_('COM_J2COMMERCE_COUNTRIES'),
                'index.php?option=com_j2commerce&view=countries',
                $vName == 'countries'
            );

            Sidebar::addEntry(
                Text::_('COM_J2COMMERCE_ZONES'),
                'index.php?option=com_j2commerce&view=zones',
                $vName == 'zones'
            );

            Sidebar::addEntry(
                Text::_('COM_J2COMMERCE_GEOZONES'),
                'index.php?option=com_j2commerce&view=geozones',
                $vName == 'geozones'
            );
        }
    }


    /**
     * Load a subtemplate
     *
     * @param   string  $templateName   Template name (without 'form_' prefix)
     * @param   array   $data           Data to pass to the template
     * @param   string  $parentLayout   Template parent name ('form' prefix)
     * @param   string  $dir            Folder location
     *
     * @return  string  Rendered template
     *
     * @since   6.0.0
     */
    public static function loadSubTemplate(string $templateName, array $data, string $parentLayout, string $dir): string
    {
        $layout = new FileLayout($parentLayout . '_' . $templateName, $dir);

        return $layout->render($data);
    }
    /**
     * Gets a list of the actions that can be performed.
     *
     * @param   string   $component  The component name.
     * @param   string   $section    The access section name.
     * @param   integer  $id         The item ID.
     *
     * @return  \JObject
     *
     * @since   6.0.0
     */
    public static function getActions($component = 'com_j2commerce', $section = '', $id = 0)
    {
        return parent::getActions($component, $section, $id);
    }

    /**
     * Check a J2Commerce custom ACL action with core.manage fallback.
     *
     * Custom actions (j2commerce.vieworders, etc.) default to "Inherited" which
     * resolves to "Not Allowed" for non-Super User groups unless explicitly set.
     * This method treats core.manage as sufficient base access — the custom actions
     * serve as additional restrictions, not gates.
     *
     * @param   string  $action  The action to check (e.g. 'j2commerce.vieworders').
     *
     * @return  bool
     *
     * @since   6.2.0
     */
    public static function canAccess(string $action): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user->authorise($action, 'com_j2commerce')
            || $user->authorise('core.manage', 'com_j2commerce');
    }

    // ========================================================================
    // Helper Class Alias Methods
    // ========================================================================

    /**
     * Get a Weight Helper instance for weight conversions and formatting
     *
     * This method provides access to the WeightHelper class for weight-related operations
     * such as conversions between different weight units, formatting weight values,
     * and retrieving weight unit information.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  WeightHelper  The WeightHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Convert 10 kg to grams (assuming weight class IDs)
     * $weightHelper = J2CommerceHelper::weight();
     * $converted = $weightHelper->convert(10, 1, 2);
     *
     * // Format weight with unit
     * $formatted = $weightHelper->format(5.5, 1);
     */
    public static function weight($config = [])
    {
        return WeightHelper::getInstance();
    }

    /**
     * Get Currency Helper instance for currency operations
     *
     * This method provides access to currency-related functionality such as
     * currency conversions, formatting currency values, and currency management.
     * Uses the modern CurrencyHelper.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  CurrencyHelper  The CurrencyHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get CurrencyHelper instance
     * $currency = J2CommerceHelper::currency();
     *
     * // Set current currency
     * $currency->setCurrency('USD');
     *
     * // Format currency value
     * $formatted = $currency->format(99.99, 'USD');
     *
     * // Convert between currencies
     * $converted = $currency->convert(100.00, 'USD', 'EUR');
     *
     * // Get currency information
     * $symbol = $currency->getSymbol('USD');
     * $decimals = $currency->getDecimalPlace('EUR');
     */
    public static function currency($config = [])
    {
        return CurrencyHelper::getInstance($config);
    }

    /**
     * Get Length Helper instance for length conversions and formatting
     *
     * This method provides access to the LengthHelper class for length-related operations
     * such as conversions between different length units (meters, feet, inches, etc.),
     * formatting length values, and retrieving length unit information.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  LengthHelper  The LengthHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Convert 10 meters to feet (assuming length class IDs)
     * $lengthHelper = J2CommerceHelper::length();
     * $converted = $lengthHelper->convert(10, 1, 2);
     *
     * // Format length with unit
     * $formatted = $lengthHelper->format(5.5, 1);
     */
    public static function length($config = [])
    {
        return LengthHelper::getInstance();
    }

    /**
     * Get Version Helper instance for version management and constants
     *
     * This method provides access to the VersionHelper class for version-related operations
     * such as loading version constants, checking Pro status, retrieving version information,
     * and maintaining version pattern compatibility.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  VersionHelper  The VersionHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Load version constants
     * $versionHelper = J2CommerceHelper::version();
     * $versionHelper->load_version_defines();
     *
     * // Get current version information
     * $version = $versionHelper->getVersion();
     * $isPro = $versionHelper->isPro();
     *
     * // Get complete version info array
     * $info = $versionHelper->getVersionInfo();
     */
    public static function version($config = [])
    {
        return VersionHelper::getInstance();
    }

    /**
     * Get Product Helper instance for product-related operations
     *
     * This method provides access to comprehensive product management functionality
     * including pricing, variants, stock management, and product options.
     * Uses the modern ProductHelper.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  ProductHelper  The ProductHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ProductHelper instance
     * $productHelper = J2CommerceHelper::product();
     *
     * // Set product ID and get product
     * $product = $productHelper->setId(123)->getProduct();
     *
     * // Get product price
     * $price = $productHelper->getPrice($product, $variant, 1);
     *
     * // Display formatted price
     * echo $productHelper->displayPrice($product, $variant);
     *
     * // Check stock status
     * if ($productHelper->check_stock_status($product, $variant, 2)) {
     *     // Product is in stock
     * }
     *
     * // Validate product options
     * $options = $productHelper->getProductOptions($product);
     */
    public static function product($config = [])
    {
        return ProductHelper::getInstance($config);
    }

    /**
     * Get Configuration Helper instance for configuration access
     *
     * This method provides access to J2Commerce configuration settings,
     * allowing retrieval and management of component configuration values.
     * Uses the modern ConfigHelper.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  ConfigHelper  The ConfigHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ConfigHelper instance
     * $config = J2CommerceHelper::config();
     *
     * // Get configuration value with default
     * $storeName = $config->get('store_name', 'Default Store');
     *
     * // Set configuration value in memory
     * $config->set('store_name', 'My New Store');
     *
     * // Save configuration to database
     * $config->saveOne('store_name', 'My New Store');
     *
     * // Get all configurations as array
     * $allConfigs = $config->toArray();
     */
    public static function config(): ConfigHelper
    {
        return ConfigHelper::getInstance();
    }

    /**
     * Get Store Profile configuration instance (backward compatibility alias for config)
     *
     * Get the store profile configuration.
     * It returns a J2Config instance for accessing store configuration settings
     * such as store name, address, tax settings, and other core configuration options.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  J2Config  The J2Config instance for backward compatibility
     *
     * @since   6.0.0
     *
     * @example
     * // Get store profile configuration
     * $storeConfig = J2CommerceHelper::storeProfile();
     *
     * // Access store configuration values
     * $storeName = $storeConfig->get('store_name', 'Default Store');
     * $storeAddress = $storeConfig->get('store_address');
     *
     * // Save store configuration
     * $storeConfig->saveOne('store_name', 'My Store Name');
     */
    public static function storeProfile(): ConfigHelper
    {
        return ConfigHelper::getInstance();
    }

    /**
     * Get Cart Helper instance for shopping cart operations
     *
     * This method provides access to shopping cart functionality such as
     * calculating totals, managing cart sessions, and handling cart-related operations.
     * Uses the modern CartHelper.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  CartHelper  The CartHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get CartHelper instance
     * $cart = J2CommerceHelper::cart();
     *
     * // Calculate cart subtotal
     * $subtotal = $cart->getSubtotal($items);
     *
     * // Calculate total tax
     * $totalTax = $cart->getCartTaxTotal($items);
     *
     * // Get detailed tax breakdown
     * $taxes = $cart->getTaxes($items);
     *
     * // Calculate total weight
     * $totalWeight = $cart->getCartTotalWeight($items);
     *
     * // Reset user cart
     * $cart->resetCart($sessionId, $userId);
     *
     * // Delete cart item
     * $cart->deleteCartItem($cartItemId);
     */
    public static function cart($config = [])
    {
        return CartHelper::getInstance($config);
    }

    /**
     * Get User Helper instance for user operations
     *
     * This method provides access to user-related functionality such as
     * user data retrieval, user group management, customer information,
     * and user-specific operations within the commerce system.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  UserHelper  The UserHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get UserHelper instance
     * $userHelper = J2CommerceHelper::user();
     *
     * // Check if username exists
     * if ($userHelper->usernameExists('john_doe')) {
     * Username already taken
     * }
     *
     * // Create new user
     * $details = ['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'secure123'];
     * $user = $userHelper->createNewUser($details, $msg);
     *
     * // Validate password
     * $json = [];
     * $userHelper->validatePassword('password123', 'password123', $json);
     */
    public static function user($config = [])
    {
        return UserHelper::getInstance();
    }

    /**
     * Get Queue Helper instance for queue management operations
     *
     * This method provides access to queue-related functionality such as
     * queue item deletion, resetting queue items with new expiry dates,
     * and managing background processing tasks.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  QueueHelper  The QueueHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get QueueHelper instance
     * $queueHelper = J2CommerceHelper::queue();
     *
     * // Delete a queue item
     * $queueHelper->deleteQueue($queueItem);
     *
     * // Reset a queue item with 7-day expiry
     * $queueHelper->resetQueue($queueItem, 7);
     */
    public static function queue($config = [])
    {
        return QueueHelper::getInstance($config);
    }

    /**
     * Get Toolbar Helper instance for toolbar operations
     *
     * This method provides access to toolbar-related functionality such as
     * toolbar button management, submenu rendering, view-specific toolbar configuration,
     * and toolbar action handling within the commerce system.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  ToolbarHelper  The ToolbarHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ToolbarHelper instance
     * $toolbarHelper = J2CommerceHelper::toolbar();
     *
     * // Render submenu for current view
     * $toolbarHelper->renderSubmenu('products');
     *
     * // Configure toolbar for products browse view
     * $toolbarHelper->onProductsBrowse();
     *
     * // Add export button for a view
     * $toolbarHelper->exportButton('customers');
     */
    public static function toolbar($config = [])
    {
        return ToolbarHelper::getInstance();
    }

    /**
     * Get Plugin Helper instance for plugin operations
     *
     * This method provides access to plugin management functionality such as
     * loading plugins, triggering plugin events, managing plugin configurations,
     * and handling plugin-specific operations using the modern PluginHelper.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  PluginHelper  The PluginHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get PluginHelper instance
     * $pluginHelper = J2CommerceHelper::plugin();
     *
     * // Get plugins with specific event
     * $plugins = $pluginHelper->getPluginsWithEvent('onJ2CommerceCheckout');
     *
     * // Get all J2Commerce plugins
     * $allPlugins = $pluginHelper->getPlugins('j2commerce');
     *
     * // Trigger plugin event
     * $results = $pluginHelper->event('BeforeDisplay', [$data]);
     */
    public static function plugin($config = [])
    {
        return PluginHelper::getInstance($config);
    }

    /**
     * Get Email Helper instance for email operations
     *
     * This method provides access to email functionality such as
     * sending order confirmations, invoice emails, customer notifications,
     * and managing email templates and configurations.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  EmailHelper  The EmailHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get EmailHelper instance
     * $emailHelper = J2CommerceHelper::email();
     *
     * // Send order emails to customer
     * $templates = $emailHelper->getOrderEmails($order, 'customer');
     *
     * // Process email template with tags
     * $processed = $emailHelper->processTemplate($order, $template, 'admin');
     *
     * // Send error notification
     * $emailHelper->sendErrorEmails('admin@example.com', 'Error', 'Error message');
     */
    public static function email($config = [])
    {
        return EmailHelper::getInstance($config);
    }

    /**
     * Get Message Helper instance
     *
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  MessageHelper  The MessageHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get MessageHelper instance
     * $emailHelper = J2CommerceHelper::email();
     *
     * // Send order emails to customer
     * $templates = $emailHelper->getOrderEmails($order, 'customer');
     *
     * // Process email template with tags
     * $processed = $emailHelper->processTemplate($order, $template, 'admin');
     *
     * // Send error notification
     * $emailHelper->sendErrorEmails('admin@example.com', 'Error', 'Error message');
     */
    public static function message($config = [])
    {
        return MessageHelper::getInstance($config);
    }

    /**
     * Get Invoice Helper instance for invoice operations
     *
     * This method provides access to invoice functionality such as
     * loading invoice templates, generating formatted invoices,
     * handling invoice data, and processing inline images.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  InvoiceHelper  The InvoiceHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get the invoice helper instance
     * $invoiceHelper = J2CommerceHelper::invoice();
     * // Load an invoice template for an order
     * $template = $invoiceHelper->loadInvoiceTemplate($order);
     */
    public static function invoice($config = [])
    {
        return InvoiceHelper::getInstance();
    }

    /**
     * Get Utilities Helper instance for utility operations
     *
     * This method provides access to various utility functions such as
     * data formatting, validation helpers, string manipulation,
     * and other common utility operations used throughout the component.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  UtilitiesHelper  The UtilitiesHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get UtilitiesHelper instance
     * $utilities = J2CommerceHelper::utilities();
     * $utilities->clear_cache();
     *
     * // Convert array to CSV
     * $csv = $utilities->to_csv(['a', 'b', 'c']);
     *
     * // Check if string is valid JSON
     * $isJson = $utilities->isJson('{"key":"value"}');
     */
    public static function utilities($config = [])
    {
        return UtilitiesHelper::getInstance();
    }

    /**
     * Get Image Helper instance for image operations
     *
     * This method provides access to image-related functionality such as
     * image handling.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  ImageHelper  The ImageHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ImageHelper instance
     * $imageUtil = J2CommerceHelper::image();
     *
     * // Get the image full URL
     * $fullUrl = $imageUtil->getImageUrl($imagePath);
     */
    public static function image($config = [])
    {
        return ImageHelper::getInstance($config);
    }

    /**
     * Get Article Helper instance for article operations
     *
     * This method provides access to article-related functionality such as
     * article retrieval, content display, language associations, and integration
     * with Joomla's content system and Falang multilingual support.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  ArticleHelper  The ArticleHelper instance
     *
     * @since   6.0.0
     */
    public static function article($config = [])
    {
        return ArticleHelper::getInstance($config);
    }

    /**
     * Get Modules Helper instance for module operations
     *
     * This method provides access to module-related functionality such as
     * module loading, module position management, module configuration,
     * and integration with Joomla's module system.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  ModulesHelper  The ModulesHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ModulesHelper instance
     * $modulesHelper = J2CommerceHelper::modules();
     *
     * // Load modules from a specific position
     * $content = $modulesHelper->loadposition('sidebar-right');
     *
     * // Load modules with custom style
     * $content = $modulesHelper->loadposition('top', 'rounded');
     *
     * // Check if position has modules
     * if ($modulesHelper->hasModules('footer')) {
     *     echo $modulesHelper->loadposition('footer');
     * }
     */
    public static function modules($config = [])
    {
        return ModulesHelper::getInstance($config);
    }

    /**
     * Get View Helper instance for view operations
     *
     * This method provides access to view-related functionality such as
     * view rendering, template management, layout helpers,
     * and view-specific utility functions for the component interface.
     *
     * @param   array  $config  Optional configuration parameters (for future use)
     *
     * @return  ViewHelper  The ViewHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get ViewHelper instance
     * $viewHelper = J2CommerceHelper::view();
     * $output = $viewHelper->getOutput('default');
     */
    public static function view($config = [])
    {
        return ViewHelper::getInstance();
    }

    /**
     * Get Strapper Helper instance for Bootstrap operations
     *
     * This method provides access to Bootstrap/UI framework functionality such as
     * Bootstrap component generation, UI element management, responsive utilities,
     * CSS/JS loading, date/time pickers, and frontend framework integration features.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  StrapperHelper  The StrapperHelper instance
     *
     * @since   6.0.0
     *
     * @example
     * // Get StrapperHelper instance
     * $strapper = J2CommerceHelper::strapper();
     *
     * // Add JavaScript and CSS files
     * $strapper->addJS();
     * $strapper->addCSS();
     *
     * // Render a native Joomla 6 calendar (date) field
     * echo $strapper->addDatePicker('my_date', 'my_date_id', '2026-01-15');
     *
     * // Format file size
     * $formatted = $strapper->sizeFormat(1024000);
     */
    public static function strapper($config = [])
    {
        return StrapperHelper::getInstance($config);
    }

    /**
     * Get PlatformHelper instance for cross-platform compatibility
     *
     * This method provides access to platform abstraction layer functionality
     * that handles cross-version compatibility, asset management, routing,
     * and platform-specific operations.
     *
     * @param   array  $config  Optional configuration parameters
     *
     * @return  PlatformHelper  The PlatformHelper instance
     *
     * @since   6.0.0
     */
    public static function platform($config = [])
    {
        return PlatformHelper::getInstance($config);
    }

    /**
     * Check if J2Commerce Pro version is installed (backward compatibility)
     *
     * Check whether the
     * if the Pro version of the component is installed.
     *
     * @return  bool  True if Pro version is installed, false otherwise
     *
     * @since   6.0.0
     */
    public static function isPro()
    {
        // Check if J2COMMERCE_PRO constant is defined and set to true
        $isPro = \defined('J2COMMERCE_PRO') ? J2COMMERCE_PRO : false;
        return (bool) $isPro;
    }

    /**
     * Build help documentation URL with UTM tracking (backward compatibility)
     *
     * This method builds URLs to the J2Commerce documentation with appropriate
     * UTM tracking parameters based on the component version (free/pro).
     *
     * @param   string  $url      The documentation path/slug
     * @param   string  $content  The UTM content parameter (default: 'app')
     *
     * @return  string  The complete documentation URL with UTM parameters
     *
     * @since   6.0.0
     */
    public static function buildHelpLink($url, $content = 'app')
    {
        $source   = self::isPro() ? 'pro' : 'free';
        $utmQuery = '?utm_source=' . $source . '&utm_medium=component&utm_campaign=inline&utm_content=' . $content;
        $domain   = 'https://docs.j2commerce.com/j2commerce';

        return $domain . '/' . ltrim($url, '/') . $utmQuery;
    }

    /**
     * Build J2Commerce website URL with UTM tracking (backward compatibility)
     *
     * This method builds URLs to the J2Commerce website with appropriate
     * UTM tracking parameters based on the component version (free/pro).
     *
     * @param   string  $url      The website path/slug
     * @param   string  $content  The UTM content parameter (default: 'app')
     *
     * @return  string  The complete website URL with UTM parameters
     *
     * @since   6.0.0
     */
    public static function buildSiteLink($url, $content = 'app')
    {
        $source   = self::isPro() ? 'pro' : 'free';
        $utmQuery = '?utm_source=' . $source . '&utm_medium=component&utm_campaign=inline&utm_content=' . $content;
        $domain   = 'https://www.j2commerce.com';

        return $domain . '/' . ltrim($url, '/') . $utmQuery;
    }

    /**
     * Get variant form objects from plugins for a specific product type
     *
     * Triggers the onJ2CommerceGetVariantForms event which allows plugins to provide
     * Form objects for rendering variant edit forms.
     *
     * @param   string  $productType  The product type (e.g., 'flexivariable')
     * @param   object  $variant      The variant object with data to bind
     * @param   string  $prefix       The form control prefix for field names
     *
     * @return  array  Associative array of Form objects keyed by section name
     *
     * @since   6.0.0
     */
    public static function getVariantForms(string $productType, object $variant, string $prefix): array
    {
        $pluginHelper = self::plugin();

        $event = $pluginHelper->event('GetVariantForms', [
            'product_type' => $productType,
            'variant'      => $variant,
            'prefix'       => $prefix,
            'forms'        => [],
        ]);

        $forms = $event->getArgument('forms', []);

        // Fallback: load from component forms directory if no plugin provided forms
        if (empty($forms)) {
            $forms = self::loadComponentVariantForms($variant, $prefix);
        }

        return $forms;
    }

    /**
     * Load variant forms from the component's forms directory (fallback for product types
     * without a dedicated plugin handler, e.g. 'variable').
     */
    protected static function loadComponentVariantForms(object $variant, string $prefix): array
    {
        $formsDir = JPATH_ADMINISTRATOR . '/components/com_j2commerce/forms';
        $sections = [
            'general'   => ['file' => 'variant_general',   'isParams' => false],
            'shipping'  => ['file' => 'variant_shipping',   'isParams' => false],
            'inventory' => ['file' => 'variant_inventory',  'isParams' => false],
            'image'     => ['file' => 'variant_image',      'isParams' => true],
        ];

        $forms = [];

        foreach ($sections as $key => $info) {
            $formPath = $formsDir . '/' . $info['file'] . '.xml';

            if (!file_exists($formPath)) {
                continue;
            }

            $variantId = (int) ($variant->j2commerce_variant_id ?? 0);

            $form = Form::getInstance(
                'com_j2commerce.variant.' . $info['file'] . '.' . $variantId,
                $formPath,
                ['control' => $prefix]
            );

            if (!$form) {
                continue;
            }

            $data = self::prepareVariantFormData($variant, $info['file'], $info['isParams']);
            $form->bind($data);

            $forms[$key] = $form;
        }

        return $forms;
    }

    protected static function prepareVariantFormData(object $variant, string $formName, bool $isParams): array
    {
        if ($isParams) {
            $params   = $variant->params ?? '{}';
            $registry = new Registry($params);

            return [
                'is_main_as_thum' => (int) $registry->get('is_main_as_thum', 0),
            ];
        }

        if ($formName === 'variant_inventory') {
            return [
                'manage_stock'                  => (int) ($variant->manage_stock ?? 0),
                'j2commerce_productquantity_id' => (int) ($variant->j2commerce_productquantity_id ?? 0),
                'quantity'                      => (int) ($variant->quantity ?? 0),
                'allow_backorder'               => (int) ($variant->allow_backorder ?? 0),
                'availability'                  => (int) ($variant->availability ?? 1),
                'notify_qty'                    => $variant->notify_qty ?? '',
                'use_store_config_notify_qty'   => (int) ($variant->use_store_config_notify_qty ?? 0),
                'quantity_restriction'          => (int) ($variant->quantity_restriction ?? 0),
                'max_sale_qty'                  => $variant->max_sale_qty ?? '',
                'use_store_config_max_sale_qty' => (int) ($variant->use_store_config_max_sale_qty ?? 0),
                'min_sale_qty'                  => $variant->min_sale_qty ?? '',
                'use_store_config_min_sale_qty' => (int) ($variant->use_store_config_min_sale_qty ?? 0),
            ];
        }

        return (array) $variant;
    }

    public static function getPaymentCardIcons(string $element): string
    {
        if (empty($element)) {
            return '';
        }

        $plugin = static::plugin()->getPlugin($element, 'j2commerce');
        if (!$plugin) {
            return '';
        }

        $params    = new Registry($plugin->params ?? '{}');
        $cardTypes = $params->get('card_types', []);

        if (empty($cardTypes)) {
            return '';
        }

        if (\is_string($cardTypes)) {
            $cardTypes = explode(',', $cardTypes);
        }

        $cardTypes = array_map('trim', (array) $cardTypes);
        $cardTypes = array_filter($cardTypes);

        if (empty($cardTypes)) {
            return '';
        }

        $iconsDir = JPATH_SITE . '/media/com_j2commerce/images/payment-methods';
        $matched  = [];

        foreach ($cardTypes as $type) {
            $type = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
            if (empty($type)) {
                continue;
            }

            $svgPath = $iconsDir . '/' . $type . '.svg';
            if (file_exists($svgPath)) {
                $matched[] = [
                    'type' => $type,
                    'url'  => Uri::root(true) . '/media/com_j2commerce/images/payment-methods/' . $type . '.svg',
                ];
            }
        }

        if (empty($matched)) {
            return '';
        }

        $layout = new FileLayout('checkout.payment_icons', JPATH_SITE . '/components/com_j2commerce/layouts');

        return $layout->render([
            'element'    => $element,
            'card_types' => $matched,
        ]);
    }

    /**
     * Get app image path with fallback logic
     *
     * Searches for app images in the following order:
     * 1. media/plg_j2commerce_{element}/images/{element}.(webp|png|jpg)
     * 2. plugins/j2commerce/{element}/images/{element}.png
     * 3. media/com_j2commerce/images/{element}.png
     * 4. media/com_j2commerce/images/default_app_j2commerce.webp (fallback)
     *
     * @param   string  $element  The plugin element name (e.g., 'app_bulkdiscount')
     *
     * @return  string  The URL to the app image
     *
     * @since   6.0.0
     */
    public static function getAppImagePath(string $element): string
    {
        $extensions = ['webp', 'png', 'jpg'];

        // Check media folder for plugin (modern location)
        foreach ($extensions as $ext) {
            $mediaPath = JPATH_SITE . '/media/plg_j2commerce_' . $element . '/images/' . $element . '.' . $ext;
            if (file_exists($mediaPath)) {
                return Uri::root(true) . '/media/plg_j2commerce_' . $element . '/images/' . $element . '.' . $ext;
            }
        }

        // Check plugin folder (legacy location)
        $pluginPath = JPATH_SITE . '/plugins/j2commerce/' . $element . '/images/' . $element . '.png';
        if (file_exists($pluginPath)) {
            return Uri::root(true) . '/plugins/j2commerce/' . $element . '/images/' . $element . '.png';
        }

        // Check shared component images folder
        $sharedPath = JPATH_SITE . '/media/com_j2commerce/images/' . $element . '.png';
        if (file_exists($sharedPath)) {
            return Uri::root(true) . '/media/com_j2commerce/images/' . $element . '.png';
        }

        // Return default J2Commerce placeholder image
        return Uri::root(true) . '/media/com_j2commerce/images/default_app_j2commerce.webp';
    }

    public static function sanitizeHistoryIconClass(string $class): string
    {
        $class = trim($class);
        if ($class === '' || preg_match('/[<>"\'&]/', $class)) {
            return '';
        }
        if (!preg_match('/^(fa-solid|fa-regular|fa-brands|fa-light|fa-thin|fa-duotone)( fa-[a-z0-9-]+)+( fa-fw)?( text-[a-z]+)?$/', $class)) {
            return '';
        }
        return $class;
    }
}
