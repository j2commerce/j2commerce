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

use J2Commerce\Component\J2commerce\Administrator\CliCommands\SeedOrderLedgerCommand;
use J2Commerce\Component\J2commerce\Administrator\Helper\AclSeedHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\CoreTemplateSyncHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\InventoryHelper;
use J2Commerce\Component\J2commerce\Administrator\Helper\StockCommittedSeedHelper;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

class Com_J2commerceInstallerScript extends InstallerScript
{
    /** Params flag: the asset rules have been settled once, so no later update re-seeds them. */
    private const DEFAULT_ACL_FLAG = 'default_acl_seeded';

    /** Marker both deny payloads carry, identifying a file as one J2Commerce wrote. */
    private const DENY_FILE_MARKER = 'J2Commerce file storage';

    protected $minimumJoomlaVersion = '6.0';
    protected $maximumJoomlaVersion = '6.99.99';
    protected $minimumPhpVersion    = '8.1';
    private string $debugLogFile    = '';

    /**
     * Always-on install trace. Written as .php behind Joomla's own die guard, because
     * the log directory ships no .htaccess and a plain .log is served verbatim.
     * Never pass exception text here — use Log::add() for that.
     */
    private function debugLog(string $message): void
    {
        if (!$this->debugLogFile) {
            $logPath            = Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs');
            $this->debugLogFile = $logPath . '/j2commerce_install_debug.php';

            if (!is_file($this->debugLogFile)) {
                file_put_contents($this->debugLogFile, "#\n#<?php die('Forbidden.'); ?>\n");
            }
        }

        file_put_contents($this->debugLogFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    }

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

        if (!\function_exists('curl_init') || !\is_callable('curl_init')) {
            Log::add('cURL extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        if (!\function_exists('json_encode')) {
            Log::add('JSON extension is not enabled in your PHP installation.', Log::WARNING, 'jerror');
            return false;
        }

        // Detect broken previous install: extension record exists (route=update)
        // but core database tables are missing. Run install SQL to create them
        // before Joomla attempts schema updates on non-existent tables.
        if ($route === 'update') {
            $this->repairMissingTables($parent);
        }

        $this->debugLog("PREFLIGHT: passed all checks");
        return true;
    }

    private function repairMissingTables($parent): void
    {
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $allTables = $db->getTableList();
        $prefix    = $db->getPrefix();

        $coreTables = [
            'j2commerce_products',
            'j2commerce_variants',
            'j2commerce_orders',
            'j2commerce_countries',
        ];

        $missing = 0;

        foreach ($coreTables as $table) {
            if (!\in_array($prefix . $table, $allTables)) {
                $missing++;
            }
        }

        if ($missing === 0) {
            return;
        }

        $this->debugLog("REPAIR: {$missing} core tables missing — running install SQL");

        $installer = $parent->getParent();
        $sqlFile   = $installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install.mysql.utf8.sql';

        if (!file_exists($sqlFile)) {
            $this->debugLog("REPAIR: install SQL file not found at {$sqlFile}");
            return;
        }

        $this->executeSqlFile($sqlFile);
        $this->debugLog("REPAIR: install SQL executed — tables created");
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

        $this->ensureFilesFolder();

        Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_INSTALL_SUCCESS'), 'success');

        $this->debugLog("=== INSTALL END ===");
        return true;
    }

    public function update($parent)
    {
        $this->debugLog("=== UPDATE START ===");

        $this->installLocalisation($parent);
        $this->debugLog("UPDATE: localisation seeded (idempotent)");

        $this->setDefaultAcl();
        $this->debugLog("UPDATE: default ACL rules set (if empty)");

        $this->ensureFilesFolder();

        $this->cleanupStaleCheckoutTemplates();

        $this->seedOrderLedgerOnce();

        Factory::getApplication()->enqueueMessage(Text::_('COM_J2COMMERCE_UPDATE_SUCCESS'), 'success');

        $this->debugLog("=== UPDATE END ===");
        return true;
    }

    /** Remove pre-subfolder checkout templates left behind on existing sites; harmless if absent. */
    private function cleanupStaleCheckoutTemplates(): void
    {
        $stale = [
            'default.php', 'default_billing.php', 'default_cartsummary.php',
            'default_confirm.php', 'default_guest.php', 'default_login.php',
            'default_register.php', 'default_shipping.php',
            'default_shipping_payment.php', 'default_sidecart.php',
        ];
        $dir = JPATH_SITE . '/components/com_j2commerce/tmpl/checkout/';

        foreach ($stale as $file) {
            $path = $dir . $file;
            if (is_file($path) && @unlink($path)) {
                $this->debugLog("UPDATE: removed stale checkout template {$file}");
            }
        }
    }

    /**
     * One-time backfill of the order-transaction ledger (issue j2commerce#1184).
     * Guarded by a `#__extensions.params` flag so it only runs once per site; per-order
     * failures are already logged and skipped inside SeedOrderLedgerCommand::run(), and a
     * top-level failure here (e.g. autoload not yet refreshed) is caught so it never aborts
     * the update — it simply retries on the next update.
     */
    private function seedOrderLedgerOnce(): void
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = 'com_j2commerce';
        $type    = 'component';

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);
        $db->setQuery($query);
        $registry = new Registry((string) ($db->loadResult() ?: ''));

        if ($registry->get('order_ledger_seeded', false)) {
            return;
        }

        $commandFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/CliCommands/SeedOrderLedgerCommand.php';

        if (!class_exists(SeedOrderLedgerCommand::class) && file_exists($commandFile)) {
            require_once $commandFile;
        }

        try {
            $result = SeedOrderLedgerCommand::run();
            $this->debugLog(\sprintf(
                'ORDER LEDGER SEED: seeded=%d skipped=%d failed=%d',
                $result['seeded'] ?? 0,
                $result['skipped'] ?? 0,
                $result['failed'] ?? 0
            ));
        } catch (\Throwable $e) {
            $this->debugLog('ORDER LEDGER SEED: aborted with error (see the j2commerce log)');
            Log::add('Order ledger seed failed: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
            return;
        }

        $registry->set('order_ledger_seeded', true);
        $params = $registry->toString();

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':params', $params)
            ->bind(':element', $element)
            ->bind(':type', $type);
        $db->setQuery($update);
        $db->execute();
    }

    public function uninstall($parent)
    {
        // Sub-extension uninstallation is handled by the package.
        return true;
    }

    public function postflight($route, $parent)
    {
        $this->debugLog("=== POSTFLIGHT START (route={$route}) ===");

        if ($route === 'uninstall') {
            return;
        }

        // Clear autoload cache so new namespaces are discovered
        $cacheFile = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        // Ensure plg_finder_j2commerce runs after all other finder plugins
        // so purgeLinkedArticlesFromIndex() catches articles indexed in the same batch
        $this->setFinderPluginOrdering();

        $this->seedCustomAclActions();
        $this->seedStockCommitted();
        $this->removeLegacyDebugLog();

        $this->debugLog("=== POSTFLIGHT END ===");
    }

    /** Retire the pre-6.x unguarded .log left behind on upgraded sites. */
    private function removeLegacyDebugLog(): void
    {
        $legacy = Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs')
            . '/j2commerce_install_debug.log';

        if (is_file($legacy)) {
            @unlink($legacy);
            $this->debugLog('CLEANUP: removed the legacy unguarded install debug log');
        }
    }

    // ── Finder plugin ordering ─────────────────────────────────────────────────

    /**
     * Set plg_finder_j2commerce ordering to 99 so it runs after all other
     * finder plugins. This ensures purgeLinkedArticlesFromIndex() catches
     * content articles indexed in the same batch request.
     */
    private function setFinderPluginOrdering(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('ordering') . ' = 99')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('finder'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('j2commerce'));

            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            $this->debugLog('setFinderPluginOrdering failed (see the j2commerce log)');
            Log::add('setFinderPluginOrdering failed: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    // ── Stock commitment seeding ─────────────────────────────────────────────

    /**
     * Seed stock_committed on existing orders, once. The component boot runs the same helper,
     * so a site whose column arrived through Database -> Fix still converges.
     */
    private function seedStockCommitted(): void
    {
        // On a fresh install the PSR-4 map for this namespace is built at the start of the
        // request, before the component exists, so neither helper autoloads here. The seed
        // reads its status set from InventoryHelper, so that one has to be present too.
        $helpers = [
            'StockCommittedSeedHelper' => StockCommittedSeedHelper::class,
            'InventoryHelper'          => InventoryHelper::class,
        ];

        foreach ($helpers as $file => $class) {
            $path = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Helper/' . $file . '.php';

            if (!class_exists($class) && file_exists($path)) {
                require_once $path;
            }
        }

        StockCommittedSeedHelper::ensureSeeded(fn (string $message) => $this->debugLog($message));
    }

    // ── Custom ACL action seeding ────────────────────────────────────────────

    /**
     * Seed the custom actions declared in access.xml. The component boot runs the same helper,
     * so a site that never executes this postflight still converges.
     */
    private function seedCustomAclActions(): void
    {
        // On a fresh install the PSR-4 map for this namespace is built at the start of the
        // request, before the component exists, so the helper will not autoload here.
        $helperFile = JPATH_ADMINISTRATOR . '/components/com_j2commerce/src/Helper/AclSeedHelper.php';

        if (!class_exists(AclSeedHelper::class) && file_exists($helperFile)) {
            require_once $helperFile;
        }

        try {
            AclSeedHelper::ensureSeeded(fn (string $message) => $this->debugLog($message));
        } catch (\Throwable $e) {
            // Log the failure: the flag stays unset, so canAccess() keeps its core.manage fallback.
            $this->debugLog('seedCustomAclActions failed (see the j2commerce log)');
            Log::add('seedCustomAclActions failed: ' . $e->getMessage(), Log::WARNING, 'j2commerce');

            Factory::getApplication()->enqueueMessage(
                Text::_('COM_J2COMMERCE_INSTALL_ACL_SEED_FAILED'),
                'warning'
            );
        }
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
        $defaults  = [];

        foreach ($xml->xpath('//field[@name and @default]') as $field) {
            $name    = (string) $field['name'];
            $type    = strtolower((string) ($field['type'] ?? ''));
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
        $params   = $registry->toString();

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
     * @since  6.2.0
     */
    private function setDefaultAcl(): void
    {
        try {
            $this->seedDefaultAcl();
        } catch (\Throwable $e) {
            // Seeding defaults must never abort a package update. The flag stays unset, so the
            // next update retries, and canAccess() keeps its core.manage fallback meanwhile.
            $this->debugLog('setDefaultAcl failed (see the j2commerce log)');
            Log::add('setDefaultAcl failed: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    /**
     * Runs from both install() and update(), so it is guarded by a one-time params flag:
     * '{}' is what an administrator who clears every permission leaves behind, and it is
     * byte-identical to the row com_config writes on the first Options save. Without the flag
     * a deliberate reset would be re-populated by every subsequent update.
     */
    private function seedDefaultAcl(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('extension_id'), $db->quoteName('params')])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query);
        $extension = $db->loadObject();

        if (!$extension) {
            return;
        }

        $storedParams = (string) $extension->params;

        if ((int) (new Registry($storedParams))->get(self::DEFAULT_ACL_FLAG, 0) === 1) {
            return;
        }

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('rules')])
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('com_j2commerce'));
        $db->setQuery($query);
        $asset = $db->loadObject();

        if (!$asset) {
            return;
        }

        $storedRules = (string) $asset->rules;

        // Already configured: record that the defaults are settled so a later reset stands.
        if (trim($storedRules) !== '' && trim($storedRules) !== '{}') {
            $this->writeDefaultAclFlag($db, (int) $extension->extension_id, $storedParams);

            return;
        }

        // Super User (8): inherits everything, so it needs no rule of its own.
        // Administrator (7): full component control, including Options and every edit-tier action.
        // Manager (6): core.manage plus the read and create tier of day-to-day work.
        // FORCE_OBJECT so a single-group map can never serialise as a JSON array, which
        // Access would then read back with the wrong keys.
        $rules = json_encode([
            'core.admin'              => ['7' => 1],
            'core.options'            => ['7' => 1],
            'core.manage'             => ['6' => 1],
            'core.create'             => ['6' => 1],
            'core.delete'             => ['7' => 1],
            'core.edit'               => ['6' => 1],
            'core.edit.state'         => ['6' => 1],
            'core.edit.own'           => ['6' => 1],
            'core.fulfilment'         => ['7' => 1],
            'j2commerce.vieworders'   => ['6' => 1],
            'j2commerce.editorders'   => ['7' => 1],
            'j2commerce.exportorders' => ['7' => 1],
            'j2commerce.viewproducts' => ['6' => 1],
            'j2commerce.editproducts' => ['7' => 1],
            'j2commerce.viewreports'  => ['7' => 1],
            'j2commerce.viewsetup'    => ['7' => 1],
            'j2commerce.editsetup'    => ['7' => 1],
        ], JSON_FORCE_OBJECT);

        $assetId = (int) $asset->id;

        // Compare and swap on the bytes this ran against: an administrator may have saved
        // Options → Permissions since the read, and that save must win. CAST to BINARY because
        // the default utf8mb4_unicode_ci is case-insensitive, accent-insensitive and PAD SPACE.
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__assets'))
            ->set($db->quoteName('rules') . ' = :rules')
            ->where($db->quoteName('id') . ' = :id')
            ->where('CAST(' . $db->quoteName('rules') . ' AS BINARY) = :expected')
            ->bind(':rules', $rules)
            ->bind(':expected', $storedRules)
            ->bind(':id', $assetId, ParameterType::INTEGER);

        $db->setQuery($update)->execute();

        if ($db->getAffectedRows() === 0) {
            $this->debugLog('ACL DEFAULTS: asset rules changed while seeding — left as saved');

            return;
        }

        Access::clearStatics();

        $this->writeDefaultAclFlag($db, (int) $extension->extension_id, $storedParams);
    }

    /** A lost compare here is harmless: the rules are written, and the next update re-reads them. */
    private function writeDefaultAclFlag(DatabaseInterface $db, int $extensionId, string $expected): void
    {
        $params = new Registry($expected);
        $params->set(self::DEFAULT_ACL_FLAG, 1);

        $paramsJson = $params->toString();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('extension_id') . ' = :id')
            ->where('CAST(' . $db->quoteName('params') . ' AS BINARY) = :expected')
            ->bind(':params', $paramsJson)
            ->bind(':expected', $expected)
            ->bind(':id', $extensionId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() === 0) {
            $this->debugLog('ACL DEFAULTS: extension params changed while seeding — flag left unset');
        }
    }

    // ── Localisation data install ────────────────────────────────────────────────

    private function installLocalisation($parent): void
    {
        $this->debugLog("LOCALISATION: start");
        $installer = $parent->getParent();
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $alltables = $db->getTableList();
        $prefix    = $db->getPrefix();

        // Install countries if needed
        try {
            $needsCountries = !\in_array($prefix . 'j2commerce_countries', $alltables);

            if (!$needsCountries) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_countries'));
                $db->setQuery($query);
                $needsCountries = ((int) $db->loadResult()) < 1;
            }

            if ($needsCountries) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/countries.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: countries error (see the j2commerce log)');
            Log::add('Error installing countries: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install zones if needed
        try {
            $needsZones = !\in_array($prefix . 'j2commerce_zones', $alltables);

            if (!$needsZones) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_zones'));
                $db->setQuery($query);
                $needsZones = ((int) $db->loadResult()) < 1;
            }

            if ($needsZones) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/zones.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: zones error (see the j2commerce log)');
            Log::add('Error installing zones: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install metrics (lengths and weights)
        try {
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/lengths.sql');
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/weights.sql');
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: metrics error (see the j2commerce log)');
            Log::add('Error installing metrics: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install email templates if needed
        try {
            $needsEmails = !\in_array($prefix . 'j2commerce_emailtemplates', $alltables);

            if (!$needsEmails) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_emailtemplates'));
                $db->setQuery($query);
                $needsEmails = ((int) $db->loadResult()) < 1;
            }

            if ($needsEmails) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/emailtemplates.sql');

                // Freshly-seeded rows may lag the currently-installed .html presets — overwrite immediately.
                try {
                    (new CoreTemplateSyncHelper())->syncEmailTemplates();
                } catch (\Throwable $e) {
                    $this->debugLog('LOCALISATION: email templates sync error (see the j2commerce log)');
                    Log::add('Error syncing core email templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            }
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: email templates error (see the j2commerce log)');
            Log::add('Error installing email templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install invoice templates if needed
        try {
            $needsInvoices = !\in_array($prefix . 'j2commerce_invoicetemplates', $alltables);

            if (!$needsInvoices) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__j2commerce_invoicetemplates'));
                $db->setQuery($query);
                $needsInvoices = ((int) $db->loadResult()) < 1;
            }

            if ($needsInvoices) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/invoicetemplates.sql');

                // Freshly-seeded rows may lag the currently-installed .html presets — overwrite immediately.
                try {
                    (new CoreTemplateSyncHelper())->syncInvoiceTemplates();
                } catch (\Throwable $e) {
                    $this->debugLog('LOCALISATION: invoice templates sync error (see the j2commerce log)');
                    Log::add('Error syncing core invoice templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            }
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: invoice templates error (see the j2commerce log)');
            Log::add('Error installing invoice templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install guided tours if guided tours exist
        try {
            $guidedToursExist = (\in_array($prefix . 'guidedtours', $alltables) && \in_array($prefix . 'guidedtour_steps', $alltables));

            if ($guidedToursExist) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/guidedtours.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog('LOCALISATION: guided tours error (see the j2commerce log)');
            Log::add('Error installing guided tours: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }
    }

    private function executeSqlFile(string $sqlPath): void
    {
        if (!File::exists($sqlPath)) {
            $this->debugLog("SQL FILE: not found: {$sqlPath}");
            return;
        }

        $this->debugLog("SQL FILE: executing {$sqlPath}");
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $queries = DatabaseDriver::splitSql(file_get_contents($sqlPath));
        $this->debugLog("SQL FILE: " . \count($queries) . " queries found");

        $executed = 0;
        $skipped  = 0;
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '' && $query[0] !== '#') {
                try {
                    $db->setQuery($query);
                    $db->execute();
                    $executed++;
                } catch (\Exception $e) {
                    $this->debugLog("SQL ERROR in {$sqlPath} (see the j2commerce log)");
                    Log::add('SQL Error: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
                }
            } else {
                $skipped++;
            }
        }
        $this->debugLog("SQL FILE: {$executed} executed, {$skipped} skipped");
    }

    // ── Customer-upload storage tree (Issue #1056) ─────────────────────────────

    /**
     * Create the files/com_j2commerce/ storage tree with .htaccess protection.
     * Idempotent — re-runs safely on every install + update.
     *
     * @since  6.3.0
     */
    private function ensureFilesFolder(): void
    {
        $root = $this->resolveAttachmentRoot();

        // Nothing is written for a path that will not confine. Returning here leaves the tree
        // unmade rather than dropping a deny-all ruleset somewhere it was never meant to go.
        if ($root === null) {
            $message = 'The configured attachment folder path could not be created or does not resolve '
                . 'inside the site root. The upload storage tree was not created and no deny files were '
                . 'written. Correct the attachment folder path in the J2Commerce options.';

            // Enqueued as well as logged: category j2commerce matches no logger on a default
            // site, and an unprotected upload tree must not be announced only where nobody reads.
            $this->debugLog('ENSURE FILES FOLDER: configured path could not be created or does not resolve inside the site root — skipped');
            Log::add($message, Log::WARNING, 'j2commerce');
            Factory::getApplication()->enqueueMessage($message, 'warning');

            return;
        }

        foreach (['', '/tmp', '/orders'] as $sub) {
            $dir = $root . $sub;

            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $this->debugLog("ENSURE FILES FOLDER: failed to create {$dir}");
                continue;
            }

            $this->writeFileIfMissing($dir . '/index.html', '<!DOCTYPE html><title></title>');
        }

        // Deny the whole tree: every legitimate read is streamed by PHP, nothing links to a URL here.
        $htaccess = <<<'HTACCESS'
# J2Commerce file storage
# Disable directory browsing
Options -Indexes

# Deny direct web access to every file in this tree. Downloads are streamed by PHP.
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>

# Belt and braces: never hand off an executable here, even if the rules above are
# overridden by a vhost that disallows this directive scope.
<FilesMatch "\.(php|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi|exe|bat)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>

    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
HTACCESS;

        $this->writeDenyFile($root . '/.htaccess', $htaccess);

        $webConfig = <<<'WEBCONFIG'
<?xml version="1.0" encoding="utf-8"?>
<!-- J2Commerce file storage: deny direct web access. Downloads are streamed by PHP. -->
<configuration>
    <system.webServer>
        <directoryBrowse enabled="false" />
        <handlers accessPolicy="None" />
        <security>
            <authorization>
                <remove users="*" roles="" verbs="" />
                <add accessType="Deny" users="*" />
            </authorization>
        </security>
    </system.webServer>
</configuration>
WEBCONFIG;

        $this->writeDenyFile($root . '/web.config', $webConfig);

        $readme = <<<'README'
# J2Commerce Customer Upload Storage

This directory holds customer-supplied files attached to orders (product-option uploads and checkout uploads).

- `tmp/{cart_id}/` — uploads bound to in-progress carts; cleaned by the `j2commerce.cleanupOrderUploads` scheduled task once `expires_on` passes.
- `orders/{order_id}/` — uploads attached to a placed order; cleaned by the same task per configured retention.

## Web access

Nothing in this tree is meant to be fetched by URL. Files are streamed by PHP after an
authorisation check — `OrderfileController` for admin order attachments, `MyprofileController`
for a customer's own downloads.

- `.htaccess` denies every request under this tree on Apache (`Require all denied`, with the
  pre-2.4 `Order allow,deny` form for older servers), and separately blocks executable
  extensions in case the blanket rule is overridden by the vhost.
- `web.config` denies every request under this tree on IIS and disables handlers.

Both files only take effect if the web server is configured to honour them — Apache needs
`AllowOverride` to permit `Limit`/`AuthConfig` in this path, and IIS needs the URL
Authorization feature installed. Verify by requesting a known filename in a browser: you
should get 403, not the file.

## Nginx equivalent

Nginx reads neither file. If your site is served by Nginx, add this to your server block:

```nginx
location ~ ^/files/com_j2commerce { deny all; return 403; }
```

Do not store anything in this tree manually — admin order views look up files by
`#__j2commerce_uploads` row, not by filesystem scan.
README;

        $this->writeFileIfMissing($root . '/README.md', $readme);

        $this->debugLog("ENSURE FILES FOLDER: tree at {$root} ready");
    }

    /**
     * Absolute, confined on-disk root for the upload tree, or null when the configured value
     * will not resolve inside the site.
     *
     * trim($value, '/') strips slash characters from the ends only — it never removes a '..'
     * segment — so the path has to be confined rather than merely trimmed. The prefix test
     * appends a separator so a sibling directory whose name starts with the root's is not
     * accepted, and the root itself is rejected: denying it would 403 the whole site.
     */
    private function resolveAttachmentRoot(): ?string
    {
        $relative = trim(str_replace('\\', '/', $this->readAttachmentFolderPath()), '/');

        if (
            $relative === ''
            || $relative === '.'
            || \in_array('..', explode('/', $relative), true)
            || preg_match('#^[a-zA-Z]:#', $relative)
        ) {
            return null;
        }

        $absolute = JPATH_ROOT . '/' . $relative;

        if (!is_dir($absolute) && !@mkdir($absolute, 0755, true) && !is_dir($absolute)) {
            $this->debugLog("ENSURE FILES FOLDER: could not create {$absolute}");

            return null;
        }

        $real     = realpath($absolute);
        $rootReal = realpath(JPATH_ROOT);

        if ($real === false || $rootReal === false) {
            return null;
        }

        $real     = rtrim(str_replace('\\', '/', $real), '/');
        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');

        if ($real === $rootReal || !str_starts_with($real . '/', $rootReal . '/')) {
            return null;
        }

        return $real;
    }

    /** Read attachmentfolderpath from com_j2commerce params with safe fallback. */
    private function readAttachmentFolderPath(): string
    {
        $default = 'files/com_j2commerce';
        $db      = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_j2commerce'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
        $db->setQuery($query);

        $params = (string) ($db->loadResult() ?: '');
        $value  = trim((string) (new Registry($params))->get('attachmentfolderpath', ''));

        return $value !== '' ? $value : $default;
    }

    /**
     * Write a deny file only over one J2Commerce wrote. Confinement still allows a configured
     * path we may reach but do not own, so refusing to replace a foreign file is what makes
     * clobbering a site's own ruleset impossible whatever the path resolves to.
     */
    private function writeDenyFile(string $path, string $contents): void
    {
        if (file_exists($path) && !str_contains((string) @file_get_contents($path), self::DENY_FILE_MARKER)) {
            $this->debugLog("ENSURE FILES FOLDER: left existing non-J2Commerce file in place: {$path}");
            Log::add(
                'An existing ' . basename($path) . ' that J2Commerce did not write was left untouched at '
                . $path . '. The upload storage tree may not be protected from direct web access.',
                Log::WARNING,
                'j2commerce'
            );

            return;
        }

        $this->writeFileOverwrite($path, $contents);
    }

    /** Write file only if it doesn't already exist. Logs and continues on failure. */
    private function writeFileIfMissing(string $path, string $contents): void
    {
        if (file_exists($path)) {
            return;
        }

        $this->writeFileOverwrite($path, $contents);
    }

    /** Write file, overwriting any existing copy. Logs and continues on failure. */
    private function writeFileOverwrite(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) === false) {
            // Surfaced to the configured logger as well as the trace: a failed deny-file write
            // leaves the tree readable over HTTP, which the trace alone would never announce.
            $this->debugLog("ENSURE FILES FOLDER: failed to write {$path}");
            Log::add('Failed to write ' . $path, Log::WARNING, 'j2commerce');
        }
    }
}
