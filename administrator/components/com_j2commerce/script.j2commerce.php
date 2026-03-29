<?php
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

class Com_J2commerceInstallerScript extends InstallerScript
{
    protected $minimumJoomlaVersion = '6.0';
    protected $maximumJoomlaVersion = '6.99.99';
    protected $minimumPhpVersion = '8.1';
    private string $debugLogFile = '';

    private function debugLog(string $message): void
    {
        if (!$this->debugLogFile) {
            $this->debugLogFile = JPATH_ROOT . '/tmp/j2commerce_install_debug.log';
        }
        file_put_contents($this->debugLogFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    }

    /**
     * Sub-extensions bundled inside the component zip.
     * Installed by postflight() from the extracted temp directory.
     *
     * plugins => group => element => enabled (1=yes, 0=no)
     * modules => admin|site => element => [position, published]
     */
    protected array $installationQueue = [
        'plugins' => [
            'system'      => ['j2commerce' => 1],
            'actionlog'   => ['j2commerce' => 1],
            'content'     => ['j2commerce' => 1],
            'finder'      => ['j2commerce' => 1],
            'task'        => ['j2commerce' => 1],
            'webservices' => ['j2commerce' => 1],
            'schemaorg'   => ['ecommerce' => 1],
            'user'        => ['j2commerce' => 0],
            'j2commerce'  => [
                'app_bootstrap5'       => 1,
                'app_flexivariable'    => 1,
                'app_diagnostics'      => 1,
                'app_localization_data' => 1,
                'app_currencyupdater'  => 1,
                'app_uikit'            => 1,
                'payment_cash'         => 1,
                'payment_moneyorder'   => 1,
                'payment_banktransfer' => 1,
                'payment_paypal'       => 0,
                'shipping_standard'    => 1,
                'shipping_free'        => 1,
                'report_itemised'      => 1,
                'report_products'      => 1,
            ],
        ],
        'modules' => [
            'admin' => [
                'mod_j2commerce_menu'       => ['status', 1],
                'mod_j2commerce_orders'     => ['j2commerce-dashboard-module-side-tab', 1],
                'mod_j2commerce_quickicons'  => ['icon', 1],
                'mod_j2commerce_stats'       => ['j2commerce-dashboard-main-tab', 1],
            ],
            'site' => [
                'mod_j2commerce_cart'             => ['', 0],
                'mod_j2commerce_currency'         => ['', 0],
                'mod_j2commerce_products'         => ['', 0],
                'mod_j2commerce_relatedproducts'  => ['', 0],
            ],
        ],
    ];

    protected array $moduleParams = [
        'mod_j2commerce_menu' => [],
        'mod_j2commerce_orders' => [
            'limit' => 5,
            'filter_status' => ['1'],
        ],
        'mod_j2commerce_quickicons' => [
            'show_dashboard'    => 1,
            'show_orders'       => 1,
            'show_products'     => 1,
            'show_customers'    => 0,
            'show_apps'         => 1,
            'show_payment'      => 0,
            'show_shipping'     => 0,
            'show_statistics'   => 1,
            'show_reports'      => 0,
            'show_config'       => 1,
            'show_plugin_icons' => 1,
        ],
        'mod_j2commerce_stats' => [
            'order_status' => ['1', '2', '7'],
        ],
    ];

    protected array $additionalModuleInstances = [
        [
            'module'   => 'mod_j2commerce_quickicons',
            'position' => 'j2commerce-dashboard-module-main-tab',
            'params'   => [
                'show_dashboard'    => 0,
                'show_orders'       => 1,
                'show_products'     => 1,
                'show_customers'    => 1,
                'show_apps'         => 1,
                'show_payment'      => 1,
                'show_shipping'     => 1,
                'show_statistics'   => 1,
                'show_reports'      => 1,
                'show_config'       => 1,
                'show_plugin_icons' => 0,
            ],
        ],
    ];

    public function preflight($route, $parent)
    {
        $this->debugLog("=== PREFLIGHT START (route={$route}) ===");

        // Skip parent::preflight() — it blocks version downgrades, which we allow
        // so users can roll back if a newer release introduces issues.
        // We enforce Joomla minimum version ourselves below.

        if (version_compare(JVERSION, '6.0.0', '<')) {
            Log::add('J2Commerce requires Joomla 6.0.0 or later. Your version: ' . JVERSION, Log::WARNING, 'jerror');
            return false;
        }

        if (!function_exists('curl_init') || !is_callable('curl_init')) {
            Log::add('cURL extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        if (!function_exists('json_encode')) {
            Log::add('JSON extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        $this->debugLog("PREFLIGHT: passed all checks");
        return true;
    }

    public function install($parent)
    {
        $this->debugLog("=== INSTALL START ===");
        $this->installLocalisation($parent);
        $this->debugLog("INSTALL: localisation complete");

        $this->setDefaultParams();
        $this->debugLog("INSTALL: default params set");

        $this->setDefaultAcl();
        $this->debugLog("INSTALL: default ACL rules set");

        Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_INSTALL_SUCCESS'), 'success');

        $this->debugLog("=== INSTALL END ===");
        return true;
    }

    public function update($parent)
    {
        $this->debugLog("=== UPDATE START ===");

        $this->setDefaultAcl();
        $this->debugLog("UPDATE: default ACL rules set");

        Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_UPDATE_SUCCESS'), 'success');

        $this->debugLog("=== UPDATE END ===");
        return true;
    }

    public function uninstall($parent)
    {
        $this->debugLog("=== UNINSTALL START ===");
        $this->uninstallSubextensions();

        Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_UNINSTALL_SUCCESS'), 'success');

        $this->debugLog("=== UNINSTALL END ===");
        return true;
    }

    public function postflight($route, $parent)
    {
        $this->debugLog("=== POSTFLIGHT START (route={$route}) ===");

        if ($route === 'uninstall') {
            $this->debugLog("POSTFLIGHT: uninstall route, skipping sub-extensions");
            return;
        }

        $source = $parent->getParent()->getPath('source');
        $this->debugLog("POSTFLIGHT: source dir = {$source}");

        $this->debugLog("POSTFLIGHT: installing library...");
        $this->installLibrary($source);
        $this->debugLog("POSTFLIGHT: installing sub-extensions...");
        $this->installSubextensions($source, $route);

        // Migrate plugin params on update
        if ($route === 'update') {
            $this->migrateLeafletParams();
            $this->migrateUppymediaParams();
        }

        // Clear autoload cache so new namespaces are discovered
        $cacheFile = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
        $this->debugLog("=== POSTFLIGHT END ===");
    }

    // ── Default component params on fresh install ──────────────────────────────

    /**
     * Populate #__extensions params with config.xml defaults on fresh install.
     * Prevents empty-params edge case where the frontend renders the wrong layout.
     */
    private function setDefaultParams(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Read current params — only set defaults if truly empty
        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query);
        $currentParams = (string) $db->loadResult();

        $registry = new Registry($currentParams);

        if ($registry->count() > 0) {
            return;
        }

        // Parse config.xml and extract every field's default attribute
        $configFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/config.xml';

        if (!file_exists($configFile)) {
            return;
        }

        $xml = simplexml_load_file($configFile);

        if ($xml === false) {
            return;
        }

        $skipTypes = ['spacer', 'button', 'note', 'cronlasthit', 'queuekey', 'currencymanager'];
        $defaults = [];

        foreach ($xml->xpath('//field[@name and @default]') as $field) {
            $name = (string) $field['name'];
            $type = strtolower((string) ($field['type'] ?? ''));
            $default = (string) $field['default'];

            if (\in_array($type, $skipTypes, true) || $default === '') {
                continue;
            }

            $defaults[$name] = $default;
        }

        if (empty($defaults)) {
            return;
        }

        $registry = new Registry($defaults);
        $params = $registry->toString();

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = ' . $db->quote($params))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($update);
        $db->execute();
    }

    // ── Default ACL rules ──────────────────────────────────────────────────────

    /**
     * Set sensible default ACL rules for com_j2commerce if rules are empty.
     *
     * Matches Joomla core pattern: Administrator (7) gets full access except
     * Super Admin, Manager (6) gets core.manage + view/edit permissions.
     * Only sets rules if currently empty — does not overwrite admin customisation.
     *
     * @since  6.2.0
     */
    private function setDefaultAcl(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('rules')])
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('com_j2commerce'));
        $db->setQuery($query);
        $asset = $db->loadObject();

        if (!$asset) {
            return;
        }

        // Only set defaults if rules are empty (not yet configured by admin)
        $currentRules = trim($asset->rules ?? '');

        if ($currentRules !== '' && $currentRules !== '{}') {
            return;
        }

        // Default rules matching the issue requirements:
        // Super User (8): inherits all (no explicit rules needed)
        // Administrator (7): everything except core.admin/core.options
        // Manager (6): core.manage + view orders + view products + edit orders
        $rules = json_encode([
            'core.admin'              => ['7' => 1],
            'core.options'            => ['7' => 1],
            'core.manage'             => ['6' => 1],
            'core.create'             => ['6' => 1],
            'core.delete'             => ['7' => 1],
            'core.edit'               => ['6' => 1],
            'core.edit.state'         => ['6' => 1],
            'core.edit.own'           => ['6' => 1],
            'j2commerce.vieworders'   => ['6' => 1],
            'j2commerce.editorders'   => ['7' => 1],
            'j2commerce.viewproducts' => ['6' => 1],
            'j2commerce.viewreports'  => ['7' => 1],
            'j2commerce.viewsetup'    => ['7' => 1],
        ]);

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__assets'))
            ->set($db->quoteName('rules') . ' = ' . $db->quote($rules))
            ->where($db->quoteName('id') . ' = ' . (int) $asset->id);
        $db->setQuery($update);
        $db->execute();
    }

    // ── Leaflet param migration ─────────────────────────────────────────────────

    private function migrateLeafletParams(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Load old leafletmap plugin params
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('leafletmap'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $db->setQuery($query);

        try {
            $leaflet = $db->loadObject();
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE LEAFLET: query failed: " . $e->getMessage());
            return;
        }

        if (!$leaflet) {
            $this->debugLog("MIGRATE LEAFLET: old plugin not found, skipping");
            return;
        }

        $oldParams = new Registry($leaflet->params ?? '{}');

        // Load j2commerce system plugin params
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $db->setQuery($query);
        $j2c = $db->loadObject();

        if (!$j2c) {
            $this->debugLog("MIGRATE LEAFLET: j2commerce system plugin not found");
            return;
        }

        $j2cParams = new Registry($j2c->params ?? '{}');

        // Skip if already migrated (any leaflet_ param already set)
        if ($j2cParams->get('leaflet_map_zoom') !== null) {
            $this->debugLog("MIGRATE LEAFLET: already migrated, skipping");
            return;
        }

        // Map old param names to new prefixed names
        $paramMap = [
            'show_on_order_confirm' => 'leaflet_show_on_order_confirm',
            'map_height'            => 'leaflet_map_height',
            'map_zoom'              => 'leaflet_map_zoom',
            'tile_provider'         => 'leaflet_tile_provider',
            'nominatim_email'       => 'leaflet_nominatim_email',
        ];

        foreach ($paramMap as $old => $new) {
            $value = $oldParams->get($old);
            if ($value !== null) {
                $j2cParams->set($new, $value);
            }
        }

        // Save updated params
        $newParams = $j2cParams->toString();
        $extId = (int) $j2c->extension_id;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $newParams)
            ->bind(':id', $extId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();
            $this->debugLog("MIGRATE LEAFLET: params migrated successfully");
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE LEAFLET: save failed: " . $e->getMessage());
        }

        // Disable old leafletmap plugin (don't uninstall to preserve geocode cache table)
        $leafletId = (int) $leaflet->extension_id;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 0')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':id', $leafletId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();
            $this->debugLog("MIGRATE LEAFLET: old plugin disabled");
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE LEAFLET: disable failed: " . $e->getMessage());
        }
    }

    // ── Uppymedia param migration ───────────────────────────────────────────────

    private function migrateUppymediaParams(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Load old uppymedia plugin params
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('uppymedia'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('filesystem'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $db->setQuery($query);

        try {
            $plugin = $db->loadObject();
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE UPPYMEDIA: query failed: " . $e->getMessage());
            return;
        }

        if (!$plugin) {
            $this->debugLog("MIGRATE UPPYMEDIA: old plugin not found, skipping");
            return;
        }

        $oldParams = new Registry($plugin->params ?? '{}');

        // Load component params
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $db->setQuery($query);
        $component = $db->loadObject();

        if (!$component) {
            $this->debugLog("MIGRATE UPPYMEDIA: component not found");
            return;
        }

        $componentParams = new Registry($component->params ?? '{}');

        // Skip if already migrated
        if ($componentParams->get('image_enable_webp') !== null) {
            $this->debugLog("MIGRATE UPPYMEDIA: already migrated, skipping");
            return;
        }

        // Map old plugin param names to new component param names
        $paramMap = [
            'enable_webp'        => 'image_enable_webp',
            'webp_quality'       => 'image_webp_quality',
            'keep_original'      => 'image_keep_original',
            'auto_thumbnail'     => 'image_auto_thumbnail',
            'thumb_width'        => 'image_thumb_width',
            'thumb_height'       => 'image_thumb_height',
            'thumb_quality'      => 'image_thumb_quality',
            'tiny_width'         => 'image_tiny_width',
            'tiny_height'        => 'image_tiny_height',
            'tiny_quality'       => 'image_tiny_quality',
            'max_file_size'      => 'image_max_file_size',
            'allowed_extensions' => 'image_allowed_extensions',
            'client_compression' => 'image_client_compression',
        ];

        foreach ($paramMap as $old => $new) {
            $value = $oldParams->get($old);
            if ($value !== null) {
                $componentParams->set($new, $value);
            }
        }

        // Migrate directories subform if present
        $directories = $oldParams->get('directories');
        if ($directories !== null) {
            $componentParams->set('image_directories', $directories);
        }

        // Save updated component params
        $newParams = $componentParams->toString();
        $extId = (int) $component->extension_id;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $newParams)
            ->bind(':id', $extId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();
            $this->debugLog("MIGRATE UPPYMEDIA: params migrated successfully");
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE UPPYMEDIA: save failed: " . $e->getMessage());
        }

        // Disable old uppymedia plugin
        $pluginId = (int) $plugin->extension_id;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 0')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':id', $pluginId, ParameterType::INTEGER);

        try {
            $db->setQuery($query)->execute();
            $this->debugLog("MIGRATE UPPYMEDIA: old plugin disabled");
        } catch (\Throwable $e) {
            $this->debugLog("MIGRATE UPPYMEDIA: disable failed: " . $e->getMessage());
        }
    }

    // ── Sub-extension installation ───────────────────────────────────────────────

    private function installLibrary(string $source): void
    {
        $libPath = $source . '/libraries/j2commerce';

        if (!is_dir($libPath)) {
            $this->debugLog("LIBRARY: directory not found: {$libPath}");
            Log::add('Library directory not found in package: ' . $libPath, Log::WARNING, 'j2commerce');
            return;
        }

        $this->debugLog("LIBRARY: installing from {$libPath}");
        $installer = new Installer();
        if (method_exists($installer, 'setDatabase')) {
            $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
        }

        if ($installer->install($libPath)) {
            $this->debugLog("LIBRARY: j2commerce installed OK");
            Log::add('Installed library: j2commerce', Log::INFO, 'j2commerce');
        } else {
            $this->debugLog("LIBRARY: j2commerce FAILED");
            Log::add('Failed to install library: j2commerce', Log::WARNING, 'j2commerce');
        }
    }

    private function installSubextensions(string $source, string $route): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Install plugins
        foreach ($this->installationQueue['plugins'] as $group => $plugins) {
            foreach ($plugins as $element => $enabled) {
                $path = $source . '/plugins/' . $group . '/' . $element;

                if (!is_dir($path)) {
                    $this->debugLog("PLUGIN: NOT FOUND plugins/{$group}/{$element} at {$path}");
                    Log::add("Plugin directory not found: plugins/{$group}/{$element}", Log::WARNING, 'j2commerce');
                    continue;
                }

                $this->debugLog("PLUGIN: installing {$group}/{$element} from {$path}");
                $installer = new Installer();
                if (method_exists($installer, 'setDatabase')) {
                    $installer->setDatabase($db);
                }

                try {
                    if ($installer->install($path)) {
                        $this->debugLog("PLUGIN: {$group}/{$element} installed OK");
                        Log::add("Installed plugin: {$group}/{$element}", Log::INFO, 'j2commerce');

                        if ($route === 'install' && $enabled) {
                            $this->enablePlugin($db, $group, $element);
                        } elseif ($route === 'update' && $enabled) {
                            $this->enablePluginIfNew($db, $group, $element);
                        }
                    } else {
                        $this->debugLog("PLUGIN: {$group}/{$element} FAILED (returned false)");
                        Log::add("Failed to install plugin: {$group}/{$element}", Log::WARNING, 'j2commerce');
                    }
                } catch (\Throwable $e) {
                    $this->debugLog("PLUGIN: {$group}/{$element} EXCEPTION: " . $e->getMessage());
                    Log::add("Failed to install plugin: {$group}/{$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            }
        }

        // Install modules
        foreach ($this->installationQueue['modules'] as $client => $modules) {
            $clientId = ($client === 'admin') ? 1 : 0;
            $subdir = ($client === 'admin') ? 'modules/admin' : 'modules/site';

            foreach ($modules as $element => $config) {
                [$position, $published] = $config;
                $path = $source . '/' . $subdir . '/' . $element;

                if (!is_dir($path)) {
                    $this->debugLog("MODULE: NOT FOUND {$subdir}/{$element} at {$path}");
                    Log::add("Module directory not found: {$subdir}/{$element}", Log::WARNING, 'j2commerce');
                    continue;
                }

                $this->debugLog("MODULE: installing {$element} (client={$client}) from {$path}");
                $installer = new Installer();
                if (method_exists($installer, 'setDatabase')) {
                    $installer->setDatabase($db);
                }

                try {
                    if ($installer->install($path)) {
                        $this->debugLog("MODULE: {$element} installed OK");
                        Log::add("Installed module: {$element} (client={$client})", Log::INFO, 'j2commerce');

                        if ($route === 'install' && ($published || !empty($position))) {
                            $this->configureModule($db, $element, $clientId, $position, $published);
                        }
                    } else {
                        $this->debugLog("MODULE: {$element} FAILED (returned false)");
                        Log::add("Failed to install module: {$element}", Log::WARNING, 'j2commerce');
                    }
                } catch (\Throwable $e) {
                    $this->debugLog("MODULE: {$element} EXCEPTION: " . $e->getMessage());
                    Log::add("Failed to install module: {$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            }
        }

        // Create additional module instances (e.g., second Quick Icons for J2Commerce dashboard)
        if ($route === 'install') {
            $this->debugLog("POSTFLIGHT: creating additional module instances...");
            $this->createAdditionalModuleInstances($db);
        }
    }

    private function enablePlugin(DatabaseInterface $db, string $group, string $element): void
    {
        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = :group')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':group', $group)
                ->bind(':element', $element);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Log::add("Could not enable plugin {$group}/{$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function enablePluginIfNew(DatabaseInterface $db, string $group, string $element): void
    {
        try {
            // enabled = -1 means freshly discovered but never enabled/disabled by user
            $negOne = -1;
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = :group')
                ->where($db->quoteName('element') . ' = :element')
                ->where($db->quoteName('enabled') . ' = :negone')
                ->bind(':group', $group)
                ->bind(':element', $element)
                ->bind(':negone', $negOne, ParameterType::INTEGER);
            $db->setQuery($query);
            $db->execute();
        } catch (\Exception $e) {
            Log::add("Could not enable new plugin {$group}/{$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function configureModule(DatabaseInterface $db, string $element, int $clientId, string $position, int $published): void
    {
        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = :element')
                ->where($db->quoteName('client_id') . ' = :clientId')
                ->bind(':element', $element)
                ->bind(':clientId', $clientId, ParameterType::INTEGER);

            if ($published) {
                $query->set($db->quoteName('published') . ' = 1');
            }
            if (!empty($position)) {
                $query->set($db->quoteName('position') . ' = ' . $db->quote($position));
            }

            // Status bar modules need Special access so they don't show on the login page
            if ($position === 'status') {
                $query->set($db->quoteName('access') . ' = 3');
                $query->set($db->quoteName('ordering') . ' = 2');
            }

            // Apply module-specific parameters
            if (isset($this->moduleParams[$element]) && !empty($this->moduleParams[$element])) {
                $params = json_encode($this->moduleParams[$element]);
                $query->set($db->quoteName('params') . ' = ' . $db->quote($params));
            }

            $db->setQuery($query);
            $db->execute();

            // Ensure module is assigned to all pages
            $this->assignModuleToAllPages($db, $element, $clientId);
        } catch (\Exception $e) {
            Log::add("Could not configure module {$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function assignModuleToAllPages(DatabaseInterface $db, string $element, int $clientId): void
    {
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = :element')
                ->where($db->quoteName('client_id') . ' = :clientId')
                ->bind(':element', $element)
                ->bind(':clientId', $clientId, ParameterType::INTEGER);
            $db->setQuery($query);
            $moduleId = (int) $db->loadResult();

            if (!$moduleId) {
                return;
            }

            // Check if menuid=0 (all pages) assignment exists
            $zero = 0;
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__modules_menu'))
                ->where($db->quoteName('moduleid') . ' = :moduleId')
                ->where($db->quoteName('menuid') . ' = :menuid')
                ->bind(':moduleId', $moduleId, ParameterType::INTEGER)
                ->bind(':menuid', $zero, ParameterType::INTEGER);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                // Remove any existing page-specific assignments and set to all pages
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__modules_menu'))
                    ->where($db->quoteName('moduleid') . ' = :moduleId')
                    ->bind(':moduleId', $moduleId, ParameterType::INTEGER);
                $db->setQuery($query);
                $db->execute();

                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__modules_menu'))
                    ->columns([$db->quoteName('moduleid'), $db->quoteName('menuid')])
                    ->values($moduleId . ', 0');
                $db->setQuery($query);
                $db->execute();
            }
        } catch (\Exception $e) {
            $this->debugLog("ASSIGN MODULE: {$element} error: " . $e->getMessage());
        }
    }

    private function createAdditionalModuleInstances(DatabaseInterface $db): void
    {
        foreach ($this->additionalModuleInstances as $instance) {
            $element = $instance['module'];
            $position = $instance['position'];
            $params = $instance['params'] ?? [];

            try {
                // Find the original module to get its title and other settings
                $clientId = 1;
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('module') . ' = :element')
                    ->where($db->quoteName('client_id') . ' = :clientId')
                    ->bind(':element', $element)
                    ->bind(':clientId', $clientId, ParameterType::INTEGER)
                    ->setLimit(1);
                $db->setQuery($query);
                $original = $db->loadObject();

                if (!$original) {
                    $this->debugLog("MULTI-INSTANCE: original module not found for {$element}");
                    continue;
                }

                // Check if a module already exists in this position
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('module') . ' = :element')
                    ->where($db->quoteName('position') . ' = :position')
                    ->where($db->quoteName('client_id') . ' = :clientId')
                    ->bind(':element', $element)
                    ->bind(':position', $position)
                    ->bind(':clientId', $clientId, ParameterType::INTEGER);
                $db->setQuery($query);

                if ((int) $db->loadResult() > 0) {
                    $this->debugLog("MULTI-INSTANCE: {$element} already exists in {$position}, skipping");
                    continue;
                }

                // Insert the new module instance
                $columns = [
                    'title', 'module', 'position', 'published', 'client_id',
                    'access', 'showtitle', 'language', 'params', 'ordering',
                ];
                $values = [
                    $db->quote($original->title),
                    $db->quote($element),
                    $db->quote($position),
                    '1',
                    (string) $clientId,
                    (string) ($original->access ?: 1),
                    '0',
                    $db->quote('*'),
                    $db->quote(json_encode($params)),
                    '0',
                ];

                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__modules'))
                    ->columns(array_map([$db, 'quoteName'], $columns))
                    ->values(implode(', ', $values));
                $db->setQuery($query);
                $db->execute();

                $newModuleId = (int) $db->insertid();
                $this->debugLog("MULTI-INSTANCE: created {$element} in {$position} (id={$newModuleId})");

                // Assign to all pages
                if ($newModuleId) {
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__modules_menu'))
                        ->columns([$db->quoteName('moduleid'), $db->quoteName('menuid')])
                        ->values($newModuleId . ', 0');
                    $db->setQuery($query);
                    $db->execute();
                }
            } catch (\Exception $e) {
                $this->debugLog("MULTI-INSTANCE: {$element} error: " . $e->getMessage());
                Log::add("Could not create additional module instance {$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
            }
        }
    }

    // ── Uninstall sub-extensions ─────────────────────────────────────────────────

    private function uninstallSubextensions(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Uninstall plugins
        foreach ($this->installationQueue['plugins'] as $group => $plugins) {
            foreach ($plugins as $element => $enabled) {
                $this->uninstallExtension($db, 'plugin', $element, $group);
            }
        }

        // Uninstall modules
        foreach ($this->installationQueue['modules'] as $client => $modules) {
            foreach ($modules as $element => $config) {
                $this->uninstallExtension($db, 'module', $element);
            }
        }

        // Uninstall library
        $this->uninstallExtension($db, 'library', 'j2commerce');
    }

    private function uninstallExtension(DatabaseInterface $db, string $type, string $element, string $folder = ''): void
    {
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':type', $type)
                ->bind(':element', $element);

            if ($type === 'plugin' && $folder !== '') {
                $query->where($db->quoteName('folder') . ' = :folder')
                    ->bind(':folder', $folder);
            }

            $db->setQuery($query);
            $extId = (int) $db->loadResult();

            if ($extId) {
                $installer = new Installer();
                if (method_exists($installer, 'setDatabase')) {
                    $installer->setDatabase($db);
                }
                $installer->uninstall($type, $extId);
            }
        } catch (\Exception $e) {
            Log::add("Could not uninstall {$type}/{$element}: " . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    // ── Localisation data install ────────────────────────────────────────────────

    private function installLocalisation($parent): void
    {
        $this->debugLog("LOCALISATION: start");
        $installer = $parent->getParent();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $alltables = $db->getTableList();
        $prefix = $db->getPrefix();

        // Install countries if needed
        try {
            $needsCountries = !in_array($prefix . 'j2commerce_countries', $alltables);

            if (!$needsCountries) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_countries'));
                $db->setQuery($query);
                $needsCountries = ((int) $db->loadResult()) < 1;
            }

            if ($needsCountries) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/countries.sql');
            }
        } catch (\Exception $e) {
            Log::add('Error installing countries: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install zones if needed
        try {
            $needsZones = !in_array($prefix . 'j2commerce_zones', $alltables);

            if (!$needsZones) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_zones'));
                $db->setQuery($query);
                $needsZones = ((int) $db->loadResult()) < 1;
            }

            if ($needsZones) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/zones.sql');
            }
        } catch (\Exception $e) {
            Log::add('Error installing zones: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install metrics (lengths and weights)
        try {
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/lengths.sql');
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/weights.sql');
        } catch (\Exception $e) {
            Log::add('Error installing metrics: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install email templates if needed
        try {
            $needsEmails = !in_array($prefix . 'j2commerce_emailtemplates', $alltables);

            if (!$needsEmails) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_emailtemplates'));
                $db->setQuery($query);
                $needsEmails = ((int) $db->loadResult()) < 1;
            }

            if ($needsEmails) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/emailtemplates.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: email templates error: " . $e->getMessage());
            Log::add('Error installing email templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Ensure emailtype_tags have default data (install SQL may have already inserted these)
        try {
            if (in_array($prefix . 'j2commerce_emailtype_tags', $alltables)) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_emailtype_tags'));
                $db->setQuery($query);

                if (((int) $db->loadResult()) < 1) {
                    $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/updates/mysql/6.1.0.sql');
                    $this->debugLog("LOCALISATION: emailtype tags/contexts loaded from 6.1.0.sql");
                }
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: emailtype data error: " . $e->getMessage());
            Log::add('Error installing emailtype data: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install invoice templates if needed
        try {
            $needsInvoices = !in_array($prefix . 'j2commerce_invoicetemplates', $alltables);

            if (!$needsInvoices) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_invoicetemplates'));
                $db->setQuery($query);
                $needsInvoices = ((int) $db->loadResult()) < 1;
            }

            if ($needsInvoices) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/invoicetemplates.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: invoice templates error: " . $e->getMessage());
            Log::add('Error installing invoice templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Add unique index to productquantities table if not exists
        try {
            $db->setQuery('SHOW INDEX FROM `#__j2commerce_productquantities`');
            $indexes = $db->loadObjectList();
            $hasIndex = false;

            foreach ($indexes as $index) {
                if ($index->Key_name === 'variantidx') {
                    $hasIndex = true;
                    break;
                }
            }

            if (!$hasIndex) {
                $db->setQuery('ALTER TABLE #__j2commerce_productquantities ADD UNIQUE INDEX variantidx (variant_id)');
                $db->execute();
            }
        } catch (\Exception $e) {
            Log::add('Could not add index: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function executeSqlFileDirect(string $sqlPath): void
    {
        if (!File::exists($sqlPath)) {
            $this->debugLog("SQL DIRECT: not found: {$sqlPath}");
            return;
        }

        $this->debugLog("SQL DIRECT: executing {$sqlPath} (" . filesize($sqlPath) . " bytes)");
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $sql = trim(file_get_contents($sqlPath));

        if ($sql === '') {
            $this->debugLog("SQL DIRECT: file is empty");
            return;
        }

        try {
            $db->setQuery($sql);
            $db->execute();
            $this->debugLog("SQL DIRECT: success");
        } catch (\Exception $e) {
            $this->debugLog("SQL DIRECT ERROR: " . $e->getMessage());
            Log::add('SQL Direct Error in ' . basename($sqlPath) . ': ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function executeSqlFile(string $sqlPath): void
    {
        if (!File::exists($sqlPath)) {
            $this->debugLog("SQL FILE: not found: {$sqlPath}");
            return;
        }

        $this->debugLog("SQL FILE: executing {$sqlPath}");
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $queries = DatabaseDriver::splitSql(file_get_contents($sqlPath));
        $this->debugLog("SQL FILE: " . count($queries) . " queries found");

        $executed = 0;
        $skipped = 0;
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '' && $query[0] !== '#') {
                try {
                    $db->setQuery($query);
                    $db->execute();
                    $executed++;
                } catch (\Exception $e) {
                    $this->debugLog("SQL ERROR in {$sqlPath}: " . $e->getMessage() . " | Query: " . substr($query, 0, 100));
                    Log::add('SQL Error: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            } else {
                $skipped++;
            }
        }
        $this->debugLog("SQL FILE: {$executed} executed, {$skipped} skipped");
    }
}
