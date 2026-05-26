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
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

class Com_J2commerceInstallerScript extends InstallerScript
{
    protected $minimumJoomlaVersion = '6.0';
    protected $maximumJoomlaVersion = '6.99.99';
    protected $minimumPhpVersion    = '8.1';
    private string $debugLogFile    = '';

    private function debugLog(string $message): void
    {
        if (!$this->debugLogFile) {
            $this->debugLogFile = Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs') . '/j2commerce_install_debug.log';
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
        $this->debugLog("INSTALL: files/com_j2commerce/ tree ensured");

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
        $this->debugLog("UPDATE: files/com_j2commerce/ tree ensured");

        $this->cleanupStaleCheckoutTemplates();

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

        $this->debugLog("=== POSTFLIGHT END ===");
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
            $this->debugLog('setFinderPluginOrdering failed: ' . $e->getMessage());
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
            $this->debugLog("LOCALISATION: countries error: " . $e->getMessage());
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
            $this->debugLog("LOCALISATION: zones error: " . $e->getMessage());
            Log::add('Error installing zones: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install metrics (lengths and weights)
        try {
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/lengths.sql');
            $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/weights.sql');
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: metrics error: " . $e->getMessage());
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
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: email templates error: " . $e->getMessage());
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
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: invoice templates error: " . $e->getMessage());
            Log::add('Error installing invoice templates: ' . $e->getMessage(), Log::WARNING, 'j2commerce');
        }

        // Install guided tours if guided tours exist
        try {
            $guidedToursExist = (\in_array($prefix . 'guidedtours', $alltables) && \in_array($prefix . 'guidedtour_steps', $alltables));

            if ($guidedToursExist) {
                $this->executeSqlFile($installer->getPath('source') . '/administrator/components/com_j2commerce/sql/install/mysql/guidedtours.sql');
            }
        } catch (\Exception $e) {
            $this->debugLog("LOCALISATION: guided tours error: " . $e->getMessage());
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
                    $this->debugLog("SQL ERROR in {$sqlPath}: " . $e->getMessage() . " | Query: " . substr($query, 0, 100));
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
        $configuredPath = $this->readAttachmentFolderPath();
        $root           = JPATH_ROOT . '/' . trim($configuredPath, '/');

        foreach (['', '/tmp', '/orders'] as $sub) {
            $dir = $root . $sub;

            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $this->debugLog("ENSURE FILES FOLDER: failed to create {$dir}");
                continue;
            }

            $this->writeFileIfMissing($dir . '/index.html', '<!DOCTYPE html><title></title>');
        }

        $htaccess = <<<'HTACCESS'
# J2Commerce file storage
# Disable directory browsing
Options -Indexes

# Block executable/script files
<FilesMatch "\.(php|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi|exe|bat)$">
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>

    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>
HTACCESS;

        $this->writeFileOverwrite($root . '/.htaccess', $htaccess);

        $readme = <<<'README'
# J2Commerce Customer Upload Storage

This directory holds customer-supplied files attached to orders (product-option uploads and checkout uploads).

- `tmp/{cart_id}/` — uploads bound to in-progress carts; cleaned by the `j2commerce.cleanupOrderUploads` scheduled task once `expires_on` passes.
- `orders/{order_id}/` — uploads attached to a placed order; cleaned by the same task per configured retention.

Direct web access is denied via `.htaccess`. Downloads must go through `OrderfileController` (admin-only, CSRF + ACL gated).

## Nginx equivalent

If your site is served by Nginx instead of Apache, add this to your server block:

```nginx
location ~ ^/files/com_j2commerce { deny all; return 403; }
```

Do not store anything in this tree manually — admin order views look up files by `#__j2commerce_uploads` row, not by filesystem scan.
README;

        $this->writeFileIfMissing($root . '/README.md', $readme);

        $this->debugLog("ENSURE FILES FOLDER: tree at {$root} ready");
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

    /** Write file only if it doesn't already exist. Logs and continues on failure. */
    private function writeFileIfMissing(string $path, string $contents): void
    {
        if (file_exists($path)) {
            return;
        }

        if (@file_put_contents($path, $contents) === false) {
            $this->debugLog("ENSURE FILES FOLDER: failed to write {$path}");
        }
    }

    /** Write file, overwriting any existing copy. Logs and continues on failure. */
    private function writeFileOverwrite(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) === false) {
            $this->debugLog("ENSURE FILES FOLDER: failed to write {$path}");
        }
    }
}
