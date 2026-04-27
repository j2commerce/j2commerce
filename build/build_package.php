<?php

declare(strict_types=1);

/**
 * J2Commerce Package Builder
 *
 * Produces a Joomla 6 type="package" ZIP containing inner ZIPs for each
 * sub-extension. Joomla's native package installer handles installation.
 *
 * @package     J2Commerce
 * @subpackage  build
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// ── Configuration ──────────────────────────────────────────────────────────────

$joomlaRoot = dirname(__DIR__);
$outputDir  = $joomlaRoot . '/docs/packages';
$buildDir   = __DIR__;
$tempDir    = sys_get_temp_dir() . '/j2commerce_pkg_build_' . time();

// Read base version from j2commerce.xml manifest
$manifestPath = $joomlaRoot . '/administrator/components/com_j2commerce/j2commerce.xml';
$manifestContent = file_get_contents($manifestPath);
if (!preg_match('/<version>([^<]+)<\/version>/', $manifestContent, $matches)) {
    die("ERROR: Could not find <version> in j2commerce.xml\n");
}
$baseVersion = $matches[1];

// Determine build number — scan for existing packages with same base version
$baseVersionDashed = str_replace('.', '-', $baseVersion);
$buildNum = 1;

// Check both old com_ and new pkg_ patterns
foreach (['com_j2commerce_', 'pkg_j2commerce_'] as $prefix) {
    $existingFiles = glob($outputDir . '/' . $prefix . $baseVersionDashed . '_beta_*.zip');
    if ($existingFiles) {
        foreach ($existingFiles as $f) {
            if (preg_match('/_beta_(\d+)\.zip$/', $f, $m)) {
                $buildNum = max($buildNum, (int) $m[1] + 1);
            }
        }
    }
}

// Version used in manifests — NO build number (per PRD)
$version = $baseVersion;

$excludePatterns = [
    '.git', '.gitignore', '.github', '.claude',
    'node_modules', 'tests', '__tests__',
    '.DS_Store', 'Thumbs.db',
    'composer.json', 'composer.lock',
    'package.json', 'package-lock.json',
    '.editorconfig', '.php-cs-fixer.php', 'phpunit.xml', 'phpcs.xml',
    '.env', 'docs', 'build',
    'vendorapply',
];

// ── Sub-extension definitions ─────────────────────────────────────────────────

$plugins = [
    ['group' => 'system',      'element' => 'j2commerce'],
    ['group' => 'actionlog',   'element' => 'j2commerce'],
    ['group' => 'console',     'element' => 'j2commerce'],
    ['group' => 'content',     'element' => 'j2commerce'],
    ['group' => 'finder',      'element' => 'j2commerce'],
    ['group' => 'task',        'element' => 'j2commerce'],
    ['group' => 'user',        'element' => 'j2commerce'],
    ['group' => 'webservices', 'element' => 'j2commerce'],
    ['group' => 'schemaorg',   'element' => 'ecommerce'],
    ['group' => 'j2commerce', 'element' => 'app_bootstrap5'],
    ['group' => 'j2commerce', 'element' => 'app_flexivariable'],
    ['group' => 'j2commerce', 'element' => 'app_diagnostics'],
    ['group' => 'j2commerce', 'element' => 'app_localization_data'],
    ['group' => 'j2commerce', 'element' => 'app_currencyupdater'],
    ['group' => 'j2commerce', 'element' => 'app_uikit'],
    ['group' => 'j2commerce', 'element' => 'payment_cash'],
    ['group' => 'j2commerce', 'element' => 'payment_moneyorder'],
    ['group' => 'j2commerce', 'element' => 'payment_banktransfer'],
    ['group' => 'j2commerce', 'element' => 'payment_paypal'],
    ['group' => 'j2commerce', 'element' => 'shipping_standard'],
    ['group' => 'j2commerce', 'element' => 'shipping_free'],
    ['group' => 'j2commerce', 'element' => 'report_itemised'],
    ['group' => 'j2commerce', 'element' => 'report_products'],
    ['group' => 'sampledata', 'element' => 'j2commerce'],
];

$adminModules = [
    'mod_j2commerce_menu',
    'mod_j2commerce_orders',
    'mod_j2commerce_quickicons',
    'mod_j2commerce_stats',
];

$siteModules = [
    'mod_j2commerce_cart',
    'mod_j2commerce_currency',
    'mod_j2commerce_products',
    'mod_j2commerce_relatedproducts',
];

// ── Helper Functions ───────────────────────────────────────────────────────────

function shouldExclude(string $path, array $patterns): bool
{
    $parts = explode('/', str_replace('\\', '/', $path));
    foreach ($parts as $part) {
        if (in_array($part, $patterns, true)) {
            return true;
        }
    }
    return false;
}

function collectFiles(string $baseDir, array $excludePatterns, bool $excludeZips = false): array
{
    $files = [];
    $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');

    if (!is_dir($baseDir)) {
        echo "  WARNING: Directory not found: {$baseDir}\n";
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        $filePath = str_replace('\\', '/', $file->getPathname());
        $relativePath = substr($filePath, strlen($baseDir) + 1);

        if (shouldExclude($relativePath, $excludePatterns)) {
            continue;
        }

        if ($excludeZips && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip') {
            continue;
        }

        $files[$relativePath] = $filePath;
    }

    return $files;
}

function stampVersion(string $xmlContent, string $version): string
{
    return preg_replace(
        '/<version>[^<]*<\/version>/',
        '<version>' . $version . '</version>',
        $xmlContent
    );
}

function stampVersionPhp(string $phpContent, string $version): string
{
    return preg_replace(
        "/define\s*\(\s*['\"]J2COMMERCE_VERSION['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)/",
        "define('J2COMMERCE_VERSION', '" . $version . "')",
        $phpContent
    );
}

function stripUpdateServers(string $xmlContent): string
{
    return preg_replace('/<updateservers>.*?<\/updateservers>/s', '', $xmlContent);
}

function fixMediaFolder(string $xmlContent): string
{
    return str_replace(
        '<media destination="com_j2commerce" folder="media">',
        '<media destination="com_j2commerce" folder="media/com_j2commerce">',
        $xmlContent
    );
}

function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return sprintf('%.2f MB', $bytes / 1048576);
    }
    return sprintf('%.1f KB', $bytes / 1024);
}

function removeDir(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// ── SQL Alignment Validation ──────────────────────────────────────────────────

function validateSqlAlignment(string $sqlDir, string $manifestVersion): void
{
    $files = glob($sqlDir . '/*.sql');
    $highest = '0.0.0';

    foreach ($files as $file) {
        $ver = pathinfo($file, PATHINFO_FILENAME);
        if (version_compare($ver, $highest, '>')) {
            $highest = $ver;
        }
    }

    // Extract base MAJOR.MINOR.PATCH from the highest SQL filename.
    // Handles both old sub-version pattern (6.1.3.6) and new date
    // pattern (6.1.5-2026-04-04). Strip date suffix first, then
    // take only the first 3 numeric segments.
    $highestBase = explode('-', $highest, 2)[0];
    $highestParts = explode('.', $highestBase);
    $highestBase = implode('.', array_slice($highestParts, 0, 3));

    if (version_compare($highestBase, $manifestVersion, '>')) {
        die(
            "\nERROR: SQL alignment failure!\n" .
            "  Highest SQL update file: {$highest}\n" .
            "  Manifest version:        {$manifestVersion}\n" .
            "  The highest SQL base version ({$highestBase}) exceeds the manifest version.\n" .
            "  Bump the manifest version or remove/rename the SQL file.\n\n"
        );
    }

    echo "SQL alignment OK: highest={$highest}, manifest={$manifestVersion}\n";
}

// ── Inner ZIP Builders ────────────────────────────────────────────────────────

function buildComponentZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $zipPath = $tempDir . '/com_j2commerce.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $adminDir = $joomlaRoot . '/administrator/components/com_j2commerce';
    $count = 0;

    // 1. Manifest at root (version-stamped, update servers stripped, media folder fixed)
    $manifestContent = file_get_contents($adminDir . '/j2commerce.xml');
    $manifestContent = stampVersion($manifestContent, $version);
    $manifestContent = stripUpdateServers($manifestContent);
    $manifestContent = fixMediaFolder($manifestContent);
    $zip->addFromString('j2commerce.xml', $manifestContent);
    $count++;

    // 2. Script at root
    $scriptPath = $adminDir . '/script.j2commerce.php';
    if (file_exists($scriptPath)) {
        $zip->addFile($scriptPath, 'script.j2commerce.php');
        $count++;
    }

    // 3. Admin component files (excluding manifest and script already at root)
    $skipRootFiles = ['j2commerce.xml', 'script.j2commerce.php'];
    $adminFiles = collectFiles($adminDir, $excludePatterns);

    foreach ($adminFiles as $rel => $absPath) {
        if (in_array($rel, $skipRootFiles, true)) {
            continue;
        }
        if ($rel === 'version.php') {
            $content = file_get_contents($absPath);
            $content = stampVersionPhp($content, $version);
            $zip->addFromString('administrator/components/com_j2commerce/' . $rel, $content);
        } else {
            $zip->addFile($absPath, 'administrator/components/com_j2commerce/' . $rel);
        }
        $count++;
    }

    // 4. Site component files
    $siteDir = $joomlaRoot . '/components/com_j2commerce';
    $siteFiles = collectFiles($siteDir, $excludePatterns);
    foreach ($siteFiles as $rel => $absPath) {
        $zip->addFile($absPath, 'components/com_j2commerce/' . $rel);
        $count++;
    }

    // 5. Media files
    $mediaDir = $joomlaRoot . '/media/com_j2commerce';
    $mediaFiles = collectFiles($mediaDir, $excludePatterns);
    foreach ($mediaFiles as $rel => $absPath) {
        $zip->addFile($absPath, 'media/com_j2commerce/' . $rel);
        $count++;
    }

    // 6. Admin language files (referenced by <administration><languages> in manifest)
    //    Bundles com_j2commerce.{ini,sys.ini} into administrator/language/{tag}/ so
    //    plugin/module forms and CLI/dispatchers calling
    //    $lang->load('com_j2commerce', JPATH_ADMINISTRATOR) find the file (fixes #851).
    foreach (['en-US', 'en-GB'] as $tag) {
        foreach (['com_j2commerce.ini', 'com_j2commerce.sys.ini'] as $file) {
            $absPath = $joomlaRoot . '/administrator/language/' . $tag . '/' . $file;
            if (file_exists($absPath)) {
                $zip->addFile($absPath, 'administrator/language/' . $tag . '/' . $file);
                $count++;
            }
        }
    }

    $zip->close();
    echo "  com_j2commerce.zip ({$count} files)\n";
    return $zipPath;
}

function buildPluginZip(string $joomlaRoot, string $tempDir, string $group, string $element, string $version, array $excludePatterns): ?string
{
    $sourceDir = $joomlaRoot . '/plugins/' . $group . '/' . $element;
    if (!is_dir($sourceDir)) {
        echo "  WARNING: Plugin not found: {$group}/{$element}\n";
        return null;
    }

    $zipName = 'plg_' . $group . '_' . $element . '.zip';
    $zipPath = $tempDir . '/' . $zipName;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $files = collectFiles($sourceDir, $excludePatterns, true);
    $count = 0;

    foreach ($files as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = stampVersion($content, $version);
            $content = stripUpdateServers($content);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    $zip->close();
    echo "  {$zipName} ({$count} files)\n";
    return $zipPath;
}

function buildModuleZip(string $joomlaRoot, string $tempDir, string $module, string $client, string $version, array $excludePatterns): ?string
{
    $sourceDir = ($client === 'administrator')
        ? $joomlaRoot . '/administrator/modules/' . $module
        : $joomlaRoot . '/modules/' . $module;

    if (!is_dir($sourceDir)) {
        echo "  WARNING: Module not found: {$module} (client={$client})\n";
        return null;
    }

    $zipPath = $tempDir . '/' . $module . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $files = collectFiles($sourceDir, $excludePatterns);
    $count = 0;

    foreach ($files as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = stampVersion($content, $version);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    $zip->close();
    echo "  {$module}.zip ({$count} files)\n";
    return $zipPath;
}

function buildLibraryZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $sourceDir = $joomlaRoot . '/libraries/j2commerce';
    $zipPath = $tempDir . '/lib_j2commerce.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $files = collectFiles($sourceDir, $excludePatterns);
    $count = 0;

    foreach ($files as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = stampVersion($content, $version);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    $zip->close();
    echo "  lib_j2commerce.zip ({$count} files)\n";
    return $zipPath;
}

// ── Package Manifest Generator ────────────────────────────────────────────────

function createPackageManifest(string $version, array $plugins, array $adminModules, array $siteModules): string
{
    $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
    $xml .= '<extension type="package" method="upgrade">' . "\n";
    $xml .= '    <name>J2Commerce</name>' . "\n";
    $xml .= '    <packagename>j2commerce</packagename>' . "\n";
    $xml .= '    <author>J2Commerce, LLC</author>' . "\n";
    $xml .= '    <creationDate>' . date('Y-m') . '</creationDate>' . "\n";
    $xml .= '    <copyright>(C)2024-2026 J2Commerce, LLC. All rights reserved.</copyright>' . "\n";
    $xml .= '    <authorEmail>support@j2commerce.com</authorEmail>' . "\n";
    $xml .= '    <authorUrl>https://www.j2commerce.com</authorUrl>' . "\n";
    $xml .= '    <version>' . $version . '</version>' . "\n";
    $xml .= '    <license>GNU General Public License version 2 or later; see LICENSE.txt</license>' . "\n";
    $xml .= '    <description>PKG_J2COMMERCE_DESCRIPTION</description>' . "\n";
    $xml .= "\n";
    $xml .= '    <blockChildUninstall>true</blockChildUninstall>' . "\n";
    $xml .= '    <scriptfile>script.pkg_j2commerce.php</scriptfile>' . "\n";
    $xml .= "\n";
    $xml .= '    <files>' . "\n";

    // Component first
    $xml .= '        <file type="component" id="com_j2commerce">com_j2commerce.zip</file>' . "\n";

    // Library
    $xml .= '        <file type="library" id="j2commerce">lib_j2commerce.zip</file>' . "\n";

    // Plugins
    foreach ($plugins as $p) {
        $group = $p['group'];
        $element = $p['element'];
        $zipName = 'plg_' . $group . '_' . $element . '.zip';
        $xml .= '        <file type="plugin" group="' . $group . '" id="' . $element . '">' . $zipName . '</file>' . "\n";
    }

    // Admin modules
    foreach ($adminModules as $mod) {
        $xml .= '        <file type="module" id="' . $mod . '" client="administrator">' . $mod . '.zip</file>' . "\n";
    }

    // Site modules
    foreach ($siteModules as $mod) {
        $xml .= '        <file type="module" id="' . $mod . '" client="site">' . $mod . '.zip</file>' . "\n";
    }

    $xml .= '    </files>' . "\n";
    $xml .= "\n";
    $xml .= '    <languages folder="language">' . "\n";
    $xml .= '        <language tag="en-GB">en-GB/pkg_j2commerce.sys.ini</language>' . "\n";
    $xml .= '    </languages>' . "\n";
    $xml .= "\n";
    $xml .= '    <updateservers>' . "\n";
    $xml .= '        <server type="extension" priority="1" name="J2Commerce Package Updates">' . "\n";
    $xml .= '            https://updates.j2commerce.com/pkg_j2commerce.xml' . "\n";
    $xml .= '        </server>' . "\n";
    $xml .= '    </updateservers>' . "\n";
    $xml .= '</extension>' . "\n";

    return $xml;
}

// ── Main Build ─────────────────────────────────────────────────────────────────

echo "\n=== J2Commerce Package Builder (type=package) ===\n";
echo "Version: {$version}\n";
echo "Build Number: {$buildNum}\n";
echo "Joomla Root: {$joomlaRoot}\n\n";

passthru('php ' . __DIR__ . '/check_vendors.php --build-check');

@mkdir($outputDir, 0777, true);
@mkdir($tempDir, 0777, true);

// Validate SQL alignment
$sqlDir = $joomlaRoot . '/administrator/components/com_j2commerce/sql/updates/mysql';
validateSqlAlignment($sqlDir, $version);

// Check for unresolved merge conflict markers in all PHP source files
$conflictDirs = [
    $joomlaRoot . '/administrator/components/com_j2commerce',
    $joomlaRoot . '/components/com_j2commerce',
    $joomlaRoot . '/libraries/j2commerce',
];

foreach ($plugins as $p) {
    $conflictDirs[] = $joomlaRoot . '/plugins/' . $p['group'] . '/' . $p['element'];
}

foreach (array_merge($adminModules, $siteModules) as $mod) {
    $clientDir = in_array($mod, $adminModules) ? 'administrator/modules' : 'modules';
    $conflictDirs[] = $joomlaRoot . '/' . $clientDir . '/' . $mod;
}

$conflictFiles = [];

foreach ($conflictDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (preg_match('/^<{7}\s/m', $contents) || preg_match('/^>{7}\s/m', $contents)) {
            $conflictFiles[] = str_replace($joomlaRoot . '/', '', $file->getPathname());
        }
    }
}

if (!empty($conflictFiles)) {
    die(
        "\nERROR: Unresolved merge conflict markers found!\n" .
        "  The following files contain <<<<<<< or >>>>>>> markers:\n" .
        implode("\n", array_map(fn($f) => "    - {$f}", $conflictFiles)) . "\n" .
        "  Resolve all conflicts before building.\n\n"
    );
}

echo "Conflict marker check OK\n";

$finalZipName = "pkg_j2commerce_{$baseVersionDashed}_beta_{$buildNum}.zip";
$finalZipPath = $outputDir . '/' . $finalZipName;
echo "\nBuilding: {$finalZipName}\n\n";

$innerZips = [];
$totalFiles = 0;

// ── 1. Build inner ZIPs ──────────────────────────────────────────────────────

echo "Building component ZIP...\n";
$innerZips[] = buildComponentZip($joomlaRoot, $tempDir, $version, $excludePatterns);

echo "\nBuilding library ZIP...\n";
$innerZips[] = buildLibraryZip($joomlaRoot, $tempDir, $version, $excludePatterns);

echo "\nBuilding plugin ZIPs...\n";
$pluginZipCount = 0;
foreach ($plugins as $plugin) {
    $result = buildPluginZip($joomlaRoot, $tempDir, $plugin['group'], $plugin['element'], $version, $excludePatterns);
    if ($result) {
        $innerZips[] = $result;
        $pluginZipCount++;
    }
}

echo "\nBuilding admin module ZIPs...\n";
foreach ($adminModules as $module) {
    $result = buildModuleZip($joomlaRoot, $tempDir, $module, 'administrator', $version, $excludePatterns);
    if ($result) {
        $innerZips[] = $result;
    }
}

echo "\nBuilding site module ZIPs...\n";
foreach ($siteModules as $module) {
    $result = buildModuleZip($joomlaRoot, $tempDir, $module, 'site', $version, $excludePatterns);
    if ($result) {
        $innerZips[] = $result;
    }
}

// ── 2. Assemble outer package ZIP ─────────────────────────────────────────────

echo "\nAssembling package ZIP...\n";

$outerZip = new ZipArchive();
if ($outerZip->open($finalZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("ERROR: Failed to create {$finalZipPath}\n");
}

// Package manifest (generated)
$pkgManifest = createPackageManifest($version, $plugins, $adminModules, $siteModules);
$outerZip->addFromString('pkg_j2commerce.xml', $pkgManifest);

// Package install script
$pkgScript = $buildDir . '/script.pkg_j2commerce.php';
if (file_exists($pkgScript)) {
    $outerZip->addFile($pkgScript, 'script.pkg_j2commerce.php');
} else {
    echo "  WARNING: Package script not found at {$pkgScript}\n";
}

// Package language file (add directory entries so all extractors create the folder)
$outerZip->addEmptyDir('language');
$outerZip->addEmptyDir('language/en-GB');
$pkgLang = $buildDir . '/language/en-GB/pkg_j2commerce.sys.ini';
if (file_exists($pkgLang)) {
    $outerZip->addFile($pkgLang, 'language/en-GB/pkg_j2commerce.sys.ini');
    // Also add at root en-GB/ for Joomla 6 package installer compatibility
    $outerZip->addEmptyDir('en-GB');
    $outerZip->addFile($pkgLang, 'en-GB/pkg_j2commerce.sys.ini');
} else {
    echo "  WARNING: Package language file not found at {$pkgLang}\n";
}

// Add all inner ZIPs (flat, at package root)
foreach ($innerZips as $innerZipPath) {
    $name = basename($innerZipPath);
    $outerZip->addFile($innerZipPath, $name);
}

$outerZip->close();

// ── 3. Summary ────────────────────────────────────────────────────────────────

$totalSize = filesize($finalZipPath);
$innerCount = count($innerZips);
$extTotal = 1 + 1 + $pluginZipCount + count($adminModules) + count($siteModules); // component + library + plugins + modules

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                  PACKAGE BUILD SUMMARY                      ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printf("║  Package Type:    %-40s ║\n", "type=\"package\" (Joomla native)");
printf("║  Build Number:    %-40s ║\n", $buildNum);
printf("║  Version:         %-40s ║\n", $version);
printf("║  Extensions:      %-40s ║\n", $extTotal . " (1 component + 1 library + " . $pluginZipCount . " plugins + " . (count($adminModules) + count($siteModules)) . " modules)");
printf("║  Inner ZIPs:      %-40s ║\n", $innerCount);
printf("║  Total Size:      %-40s ║\n", formatSize($totalSize));
printf("║  Output:          %-40s ║\n", $finalZipName);
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Inner ZIP Breakdown:                                       ║\n";
printf("║    %-42s %10s ║\n", "Component (com_j2commerce.zip)", "1");
printf("║    %-42s %10s ║\n", "Library (lib_j2commerce.zip)", "1");
printf("║    %-42s %10s ║\n", "Plugin ZIPs", $pluginZipCount);
printf("║    %-42s %10s ║\n", "Admin module ZIPs", count($adminModules));
printf("║    %-42s %10s ║\n", "Site module ZIPs", count($siteModules));
echo "╚══════════════════════════════════════════════════════════════╝\n";

// Clean up temp directory
removeDir($tempDir);

echo "\nPackage ready: {$finalZipPath}\n";
