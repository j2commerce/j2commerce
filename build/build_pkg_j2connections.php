<?php

declare(strict_types=1);

/**
 * J2Connections Package Builder
 *
 * Produces a Joomla 6 type="package" ZIP containing inner ZIPs for:
 *   - com_j2connections
 *   - plg_webservices_j2connections
 *   - plg_j2connections_google_merchant
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
$tempDir    = sys_get_temp_dir() . '/j2connections_pkg_build_' . time();
$version    = '6.0.0';

$excludePatterns = [
    '.git', '.gitignore', '.github', '.claude',
    'node_modules', 'tests', '__tests__',
    '.DS_Store', 'Thumbs.db',
    'composer.json', 'composer.lock',
    'package.json', 'package-lock.json',
    '.editorconfig', '.php-cs-fixer.php', 'phpunit.xml', 'phpcs.xml',
    '.env', 'docs', 'build',
];

// ── Helper Functions ───────────────────────────────────────────────────────────

function j2c_shouldExclude(string $path, array $patterns): bool
{
    $parts = explode('/', str_replace('\\', '/', $path));
    foreach ($parts as $part) {
        if (in_array($part, $patterns, true)) {
            return true;
        }
    }
    return false;
}

function j2c_collectFiles(string $baseDir, array $excludePatterns, bool $excludeZips = false): array
{
    $files   = [];
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
        $filePath     = str_replace('\\', '/', $file->getPathname());
        $relativePath = substr($filePath, strlen($baseDir) + 1);

        if (j2c_shouldExclude($relativePath, $excludePatterns)) {
            continue;
        }
        if ($excludeZips && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'zip') {
            continue;
        }

        $files[$relativePath] = $filePath;
    }

    return $files;
}

function j2c_stampVersion(string $xmlContent, string $version): string
{
    return preg_replace(
        '/<version>[^<]*<\/version>/',
        '<version>' . $version . '</version>',
        $xmlContent
    );
}

function j2c_formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return sprintf('%.2f MB', $bytes / 1048576);
    }
    return sprintf('%.1f KB', $bytes / 1024);
}

function j2c_removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// ── Inner ZIP: com_j2connections ──────────────────────────────────────────────

function buildComponentZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $zipPath = $tempDir . '/com_j2connections.zip';
    $zip     = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $adminDir = $joomlaRoot . '/administrator/components/com_j2connections';
    $count    = 0;

    // Manifest at root (version-stamped)
    $manifestContent = file_get_contents($adminDir . '/com_j2connections.xml');
    $manifestContent = j2c_stampVersion($manifestContent, $version);
    $zip->addFromString('com_j2connections.xml', $manifestContent);
    $count++;

    // Install script at root
    $scriptPath = $adminDir . '/script.com_j2connections.php';
    if (file_exists($scriptPath)) {
        $zip->addFile($scriptPath, 'script.com_j2connections.php');
        $count++;
    }

    // Admin component files (skip root manifest and script)
    $skipRootFiles = ['com_j2connections.xml', 'script.com_j2connections.php'];
    foreach (j2c_collectFiles($adminDir, $excludePatterns) as $rel => $absPath) {
        if (in_array($rel, $skipRootFiles, true)) {
            continue;
        }
        $zip->addFile($absPath, 'administrator/components/com_j2connections/' . $rel);
        $count++;
    }

    // Site component files
    $siteDir = $joomlaRoot . '/components/com_j2connections';
    foreach (j2c_collectFiles($siteDir, $excludePatterns) as $rel => $absPath) {
        $zip->addFile($absPath, 'components/com_j2connections/' . $rel);
        $count++;
    }

    // Media files
    $mediaDir = $joomlaRoot . '/media/com_j2connections';
    foreach (j2c_collectFiles($mediaDir, $excludePatterns) as $rel => $absPath) {
        $zip->addFile($absPath, 'media/com_j2connections/' . $rel);
        $count++;
    }

    // Admin language sys.ini mirrors
    foreach (['en-US', 'en-GB'] as $tag) {
        $src = $joomlaRoot . '/administrator/language/' . $tag . '/com_j2connections.sys.ini';
        if (file_exists($src)) {
            $zip->addFile($src, 'administrator/language/' . $tag . '/com_j2connections.sys.ini');
            $count++;
        }
    }

    $zip->close();
    echo "  com_j2connections.zip ({$count} files)\n";
    return $zipPath;
}

// ── Inner ZIP: plg_webservices_j2connections ──────────────────────────────────

function buildWebservicesPluginZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $sourceDir = $joomlaRoot . '/plugins/webservices/j2connections';
    $zipPath   = $tempDir . '/plg_webservices_j2connections.zip';
    $zip       = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $count = 0;
    foreach (j2c_collectFiles($sourceDir, $excludePatterns, true) as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = j2c_stampVersion($content, $version);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    // Admin language sys.ini mirrors
    foreach (['en-US', 'en-GB'] as $tag) {
        $src = $joomlaRoot . '/administrator/language/' . $tag . '/plg_webservices_j2connections.sys.ini';
        if (file_exists($src)) {
            $zip->addFile($src, 'administrator/language/' . $tag . '/plg_webservices_j2connections.sys.ini');
            $count++;
        }
    }

    $zip->close();
    echo "  plg_webservices_j2connections.zip ({$count} files)\n";
    return $zipPath;
}

// ── Inner ZIP: plg_task_j2connections ────────────────────────────────────────

function buildTaskPluginZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $sourceDir = $joomlaRoot . '/plugins/task/j2connections';
    $zipPath   = $tempDir . '/plg_task_j2connections.zip';
    $zip       = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $count = 0;
    foreach (j2c_collectFiles($sourceDir, $excludePatterns, true) as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = j2c_stampVersion($content, $version);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    // Admin language sys.ini mirrors
    foreach (['en-US', 'en-GB'] as $tag) {
        $src = $joomlaRoot . '/administrator/language/' . $tag . '/plg_task_j2connections.sys.ini';
        if (file_exists($src)) {
            $zip->addFile($src, 'administrator/language/' . $tag . '/plg_task_j2connections.sys.ini');
            $count++;
        }
    }

    $zip->close();
    echo "  plg_task_j2connections.zip ({$count} files)\n";
    return $zipPath;
}

// ── Inner ZIP: plg_j2connections_google_merchant ─────────────────────────────

function buildGoogleMerchantPluginZip(string $joomlaRoot, string $tempDir, string $version, array $excludePatterns): string
{
    $sourceDir = $joomlaRoot . '/plugins/j2connections/google_merchant';
    $zipPath   = $tempDir . '/plg_j2connections_google_merchant.zip';
    $zip       = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        die("ERROR: Failed to create {$zipPath}\n");
    }

    $count = 0;
    foreach (j2c_collectFiles($sourceDir, $excludePatterns, true) as $rel => $absPath) {
        if (preg_match('/^[^\/]+\.xml$/', $rel)) {
            $content = file_get_contents($absPath);
            $content = j2c_stampVersion($content, $version);
            $zip->addFromString($rel, $content);
        } else {
            $zip->addFile($absPath, $rel);
        }
        $count++;
    }

    // Plugin media (images)
    $mediaDir = $joomlaRoot . '/media/plg_j2connections_google_merchant';
    foreach (j2c_collectFiles($mediaDir, $excludePatterns) as $rel => $absPath) {
        $zip->addFile($absPath, 'media/plg_j2connections_google_merchant/' . $rel);
        $count++;
    }

    // Admin language sys.ini mirrors
    foreach (['en-US', 'en-GB'] as $tag) {
        $src = $joomlaRoot . '/administrator/language/' . $tag . '/plg_j2connections_google_merchant.sys.ini';
        if (file_exists($src)) {
            $zip->addFile($src, 'administrator/language/' . $tag . '/plg_j2connections_google_merchant.sys.ini');
            $count++;
        }
    }

    $zip->close();
    echo "  plg_j2connections_google_merchant.zip ({$count} files)\n";
    return $zipPath;
}

// ── Main Build ─────────────────────────────────────────────────────────────────

echo "\n=== J2Connections Package Builder ===\n";
echo "Version: {$version}\n";
echo "Joomla Root: {$joomlaRoot}\n\n";

@mkdir($outputDir, 0777, true);
@mkdir($tempDir, 0777, true);

$finalZipName = 'pkg_j2connections_' . str_replace('.', '-', $version) . '.zip';
$finalZipPath = $outputDir . '/' . $finalZipName;

echo "Building: {$finalZipName}\n\n";

// ── 1. Build inner ZIPs ───────────────────────────────────────────────────────

echo "Building component ZIP...\n";
$comZip = buildComponentZip($joomlaRoot, $tempDir, $version, $excludePatterns);

echo "\nBuilding webservices plugin ZIP...\n";
$wsZip = buildWebservicesPluginZip($joomlaRoot, $tempDir, $version, $excludePatterns);

echo "\nBuilding task plugin ZIP...\n";
$taskZip = buildTaskPluginZip($joomlaRoot, $tempDir, $version, $excludePatterns);

echo "\nBuilding google_merchant plugin ZIP...\n";
$gmZip = buildGoogleMerchantPluginZip($joomlaRoot, $tempDir, $version, $excludePatterns);

// ── 2. Assemble outer package ZIP ─────────────────────────────────────────────

echo "\nAssembling outer package ZIP...\n";

// Read the package manifest (already written to docs/packages/)
$pkgManifestPath = $outputDir . '/pkg_j2connections.xml';
if (!file_exists($pkgManifestPath)) {
    die("ERROR: Package manifest not found at {$pkgManifestPath}\n");
}
$pkgManifestContent = j2c_stampVersion(file_get_contents($pkgManifestPath), $version);

$outerZip = new ZipArchive();
if ($outerZip->open($finalZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("ERROR: Failed to create {$finalZipPath}\n");
}

// Manifest at root
$outerZip->addFromString('pkg_j2connections.xml', $pkgManifestContent);

// Inner ZIPs in packages/ subfolder
$outerZip->addEmptyDir('packages');
foreach ([$comZip, $wsZip, $taskZip, $gmZip] as $innerZipPath) {
    $outerZip->addFile($innerZipPath, 'packages/' . basename($innerZipPath));
}

$outerZip->close();

// ── 3. Cleanup & Summary ──────────────────────────────────────────────────────

j2c_removeDir($tempDir);

$totalSize  = filesize($finalZipPath);

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║              J2CONNECTIONS PACKAGE BUILD SUMMARY            ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";
printf("║  Version:       %-42s ║\n", $version);
printf("║  Inner ZIPs:    %-42s ║\n", '4 (component + 3 plugins)');
printf("║  Total Size:    %-42s ║\n", j2c_formatSize($totalSize));
printf("║  Output:        %-42s ║\n", $finalZipName);
echo "╠══════════════════════════════════════════════════════════════╣\n";
echo "║  Inner ZIP Breakdown:                                        ║\n";
printf("║    %-55s ║\n", 'com_j2connections.zip');
printf("║    %-55s ║\n", 'plg_webservices_j2connections.zip');
printf("║    %-55s ║\n", 'plg_task_j2connections.zip');
printf("║    %-55s ║\n", 'plg_j2connections_google_merchant.zip');
echo "╚══════════════════════════════════════════════════════════════╝\n";

echo "\nPackage ready: {$finalZipPath}\n";
