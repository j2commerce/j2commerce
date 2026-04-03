<?php

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

class Pkg_J2commerceInstallerScript extends InstallerScript
{
    protected $minimumJoomlaVersion = '6.0';
    protected $minimumPhpVersion = '8.1';
    private string $debugLogFile = '';

    /**
     * Plugin enable/disable configuration.
     * Joomla installs all plugins as disabled by default.
     * [group, element, enableOnFreshInstall]
     */
    private array $pluginConfig = [
        ['system',      'j2commerce',           true],
        ['actionlog',   'j2commerce',           true],
        ['console',     'j2commerce',           true],
        ['content',     'j2commerce',           true],
        ['finder',      'j2commerce',           true],
        ['task',        'j2commerce',           true],
        ['user',        'j2commerce',           false],
        ['webservices', 'j2commerce',           true],
        ['schemaorg',   'ecommerce',            true],
        ['j2commerce',  'app_bootstrap5',       true],
        ['j2commerce',  'app_flexivariable',    true],
        ['j2commerce',  'app_diagnostics',      true],
        ['j2commerce',  'app_localization_data', true],
        ['j2commerce',  'app_currencyupdater',  true],
        ['j2commerce',  'app_uikit',            true],
        ['j2commerce',  'payment_cash',         true],
        ['j2commerce',  'payment_moneyorder',   true],
        ['j2commerce',  'payment_banktransfer', true],
        ['j2commerce',  'payment_paypal',       true],
        ['j2commerce',  'shipping_standard',    true],
        ['j2commerce',  'shipping_free',        true],
        ['j2commerce',  'report_itemised',      true],
        ['j2commerce',  'report_products',      true],
        ['sampledata',  'j2commerce',           true],
    ];

    /**
     * Module configuration for fresh installs.
     * [element, client_id (1=admin, 0=site), position, published, params]
     */
    private array $moduleConfig = [
        ['mod_j2commerce_menu',       1, 'status', 1, []],
        ['mod_j2commerce_orders',     1, 'j2commerce-dashboard-module-side-tab', 1, [
            'limit' => 5,
            'filter_status' => ['1'],
        ]],
        ['mod_j2commerce_quickicons', 1, 'icon', 1, [
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
        ]],
        ['mod_j2commerce_stats',      1, 'j2commerce-dashboard-main-tab', 1, [
            'order_status' => ['1', '2', '7'],
        ]],
        ['mod_j2commerce_cart',              0, '', 0, []],
        ['mod_j2commerce_currency',          0, '', 0, []],
        ['mod_j2commerce_products',          0, '', 0, []],
        ['mod_j2commerce_relatedproducts',   0, '', 0, []],
    ];

    /**
     * Additional module instances for fresh installs (second quickicons on J2Commerce dashboard).
     */
    private array $additionalModuleInstances = [
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

    private function debugLog(string $message): void
    {
        if (!$this->debugLogFile) {
            $this->debugLogFile = JPATH_ROOT . '/tmp/j2commerce_install_debug.log';
        }
        file_put_contents($this->debugLogFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    }

    public function preflight($route, $parent)
    {
        $this->debugLog("=== PKG PREFLIGHT START (route={$route}) ===");

        if (version_compare(JVERSION, '6.0.0', '<')) {
            Log::add('J2Commerce requires Joomla 6.0.0 or later. Your version: ' . JVERSION, Log::WARNING, 'jerror');
            return false;
        }

        if (!\function_exists('curl_init') || !\is_callable('curl_init')) {
            Log::add('cURL extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        if (!\function_exists('json_encode')) {
            Log::add('JSON extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        $this->debugLog("PKG PREFLIGHT: passed all checks");
        return true;
    }

    public function postflight($route, $parent)
    {
        $this->debugLog("=== PKG POSTFLIGHT START (route={$route}) ===");

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if ($route === 'install') {
            $this->debugLog("PKG POSTFLIGHT: fresh install");
            $this->enablePlugins($db);
            $this->configureModules($db);
            $this->createAdditionalModuleInstances($db);
        } elseif ($route === 'update') {
            $this->enableNewPlugins($db);
            $this->migrateLeafletParams($db);
            $this->migrateUppymediaParams($db);
        }

        // Clear autoload cache so new namespaces are discovered
        $cacheFile = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        $this->debugLog("=== PKG POSTFLIGHT END ===");
    }

    /**
     * Enable all plugins marked for auto-enable on fresh install.
     */
    private function enablePlugins(DatabaseInterface $db): void
    {
        foreach ($this->pluginConfig as [$group, $element, $enable]) {
            if (!$enable) {
                continue;
            }

            $this->enablePlugin($db, $group, $element);
        }
    }

    /**
     * Enable only plugins that are freshly discovered (enabled = -1).
     * Does not override user's manual disable.
     */
    private function enableNewPlugins(DatabaseInterface $db): void
    {
        foreach ($this->pluginConfig as [$group, $element, $enable]) {
            if (!$enable) {
                continue;
            }

            $this->enablePluginIfNew($db, $group, $element);
        }
    }

    private function enablePlugin(DatabaseInterface $db, string $group, string $element): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = :group')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':group', $group)
            ->bind(':element', $element);

        $db->setQuery($query)->execute();
        $this->debugLog("ENABLE PLUGIN: {$group}/{$element}");
    }

    /**
     * Enable a plugin only if it is in the newly-discovered state (enabled = -1).
     * Plugins the user has explicitly disabled (enabled = 0) are left alone.
     */
    private function enablePluginIfNew(DatabaseInterface $db, string $group, string $element): void
    {
        $disabled = -1;
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = :group')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('enabled') . ' = :disabled')
            ->bind(':group', $group)
            ->bind(':element', $element)
            ->bind(':disabled', $disabled, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() > 0) {
            $this->debugLog("ENABLE NEW PLUGIN: {$group}/{$element}");
        }
    }

    /**
     * Configure module positions, published state, and params on fresh install.
     */
    private function configureModules(DatabaseInterface $db): void
    {
        foreach ($this->moduleConfig as [$element, $clientId, $position, $published, $params]) {
            $this->configureModule($db, $element, $clientId, $position, $published, $params);
        }
    }

    private function configureModule(
        DatabaseInterface $db,
        string $element,
        int $clientId,
        string $position,
        int $published,
        array $params
    ): void {
        // Find the module
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = :element')
            ->where($db->quoteName('client_id') . ' = :client')
            ->bind(':element', $element)
            ->bind(':client', $clientId, ParameterType::INTEGER)
            ->setLimit(1);

        $moduleId = (int) $db->setQuery($query)->loadResult();

        if ($moduleId === 0) {
            $this->debugLog("CONFIGURE MODULE: {$element} not found, skipping");
            return;
        }

        // Update position, published, params
        $paramsJson = !empty($params) ? json_encode($params) : '{}';

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $moduleId, ParameterType::INTEGER);

        if ($position !== '') {
            $update->set($db->quoteName('position') . ' = ' . $db->quote($position));
        }

        $update->set($db->quoteName('published') . ' = ' . (int) $published);

        if (!empty($params)) {
            $update->set($db->quoteName('params') . ' = ' . $db->quote($paramsJson));
        }

        $db->setQuery($update)->execute();
        $this->debugLog("CONFIGURE MODULE: {$element} → position={$position}, published={$published}");

        // Assign to all pages
        $this->assignModuleToAllPages($db, $moduleId);
    }

    private function assignModuleToAllPages(DatabaseInterface $db, int $moduleId): void
    {
        // Check if already assigned
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules_menu'))
            ->where($db->quoteName('moduleid') . ' = :id')
            ->bind(':id', $moduleId, ParameterType::INTEGER);

        if ((int) $db->setQuery($query)->loadResult() > 0) {
            return;
        }

        // Assign to all pages (menuid = 0)
        $obj = (object) [
            'moduleid' => $moduleId,
            'menuid'   => 0,
        ];
        $db->insertObject('#__modules_menu', $obj);
        $this->debugLog("ASSIGN MODULE: id={$moduleId} to all pages");
    }

    /**
     * Create additional module instances (e.g., second quickicons for J2Commerce dashboard).
     */
    private function createAdditionalModuleInstances(DatabaseInterface $db): void
    {
        foreach ($this->additionalModuleInstances as $config) {
            $module   = $config['module'];
            $position = $config['position'];
            $params   = $config['params'];

            // Check if an instance with this position already exists
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = :module')
                ->where($db->quoteName('position') . ' = :position')
                ->bind(':module', $module)
                ->bind(':position', $position);

            if ((int) $db->setQuery($query)->loadResult() > 0) {
                $this->debugLog("ADDITIONAL MODULE: {$module} at {$position} already exists, skipping");
                continue;
            }

            // Find the original module to clone its basic properties
            $clientId = 1;
            $query = $db->getQuery(true)
                ->select([$db->quoteName('title'), $db->quoteName('access'), $db->quoteName('language')])
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = :module')
                ->where($db->quoteName('client_id') . ' = :client')
                ->bind(':module', $module)
                ->bind(':client', $clientId, ParameterType::INTEGER)
                ->setLimit(1);

            $original = $db->setQuery($query)->loadObject();

            if (!$original) {
                $this->debugLog("ADDITIONAL MODULE: original {$module} not found, skipping");
                continue;
            }

            // Create the new instance
            $obj = (object) [
                'title'      => $original->title,
                'module'     => $module,
                'position'   => $position,
                'published'  => 1,
                'client_id'  => $clientId,
                'access'     => $original->access ?? 1,
                'ordering'   => 0,
                'language'   => $original->language ?? '*',
                'params'     => json_encode($params),
            ];

            $db->insertObject('#__modules', $obj, 'id');
            $newId = (int) $obj->id;
            $this->debugLog("ADDITIONAL MODULE: created {$module} at {$position} (id={$newId})");

            // Assign to all pages
            $this->assignModuleToAllPages($db, $newId);
        }
    }

    /**
     * Migrate leaflet plugin params to system/j2commerce plugin.
     */
    private function migrateLeafletParams(DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('leafletmap'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        try {
            $leaflet = $db->setQuery($query)->loadObject();
        } catch (\Throwable $e) {
            return;
        }

        if (!$leaflet) {
            return;
        }

        $oldParams = new Registry($leaflet->params ?? '{}');

        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('j2commerce'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        $target = $db->setQuery($query)->loadObject();

        if (!$target) {
            return;
        }

        $targetParams = new Registry($target->params ?? '{}');
        $migrated = false;

        foreach (['leaflet_enabled', 'leaflet_provider', 'leaflet_custom_url'] as $key) {
            $val = $oldParams->get($key);

            if ($val !== null && $targetParams->get($key) === null) {
                $targetParams->set($key, $val);
                $migrated = true;
            }
        }

        if ($migrated) {
            $paramsJson = $targetParams->toString();
            $extId      = (int) $target->extension_id;

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':params', $paramsJson)
                ->bind(':id', $extId, ParameterType::INTEGER);

            $db->setQuery($update)->execute();
            $this->debugLog("MIGRATE LEAFLET: params migrated to system/j2commerce");
        }
    }

    /**
     * Migrate uppymedia filesystem plugin params to component config.
     */
    private function migrateUppymediaParams(DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('uppymedia'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('filesystem'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

        try {
            $plugin = $db->setQuery($query)->loadObject();
        } catch (\Throwable $e) {
            return;
        }

        if (!$plugin) {
            return;
        }

        $pluginParams = new Registry($plugin->params ?? '{}');
        $maxSize = $pluginParams->get('max_file_size');

        if ($maxSize === null) {
            return;
        }

        // Load component config
        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));

        $component = $db->setQuery($query)->loadObject();

        if (!$component) {
            return;
        }

        $compParams = new Registry($component->params ?? '{}');

        if ($compParams->get('upload_max_file_size') !== null) {
            return;
        }

        $compParams->set('upload_max_file_size', $maxSize);
        $paramsJson = $compParams->toString();
        $extId      = (int) $component->extension_id;

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->bind(':params', $paramsJson)
            ->bind(':id', $extId, ParameterType::INTEGER);

        $db->setQuery($update)->execute();
        $this->debugLog("MIGRATE UPPYMEDIA: max_file_size migrated to component config");
    }
}
