<?php

declare(strict_types=1);

/**
 * J2Commerce Language Sync Checker
 *
 * Compares language key sets between en-US and en-GB for all J2Commerce
 * extensions. Reports keys present in one locale but missing from the other,
 * and detects global Joomla strings that shouldn't be in extension files.
 *
 * Usage:
 *   php build/check_language_sync.php
 *
 * @package     J2Commerce
 * @subpackage  build
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// ── Configuration ──────────────────────────────────────────────────────────────

$joomlaRoot = dirname(__DIR__);
$outputDir  = $joomlaRoot . '/docs/reports';
$localeA    = 'en-US';
$localeB    = 'en-GB';

echo "J2Commerce Language Sync Checker\n";
echo "=================================\n";
echo "Comparing: {$localeA} ↔ {$localeB}\n";
echo "Root:      {$joomlaRoot}\n\n";

// ── Step 1: Discover Extension Language Directories ──────────────────────────

echo "Step 1: Discovering language directories...\n";

$extensions = [];

// Component (admin + site)
$extensions['com_j2commerce (admin)'] = 'administrator/components/com_j2commerce/language';
$extensions['com_j2commerce (site)']  = 'components/com_j2commerce/language';

// Library
$extensions['lib_j2commerce'] = 'libraries/j2commerce/language';

// Plugins
$pluginGroups = glob($joomlaRoot . '/plugins/*', GLOB_ONLYDIR);
foreach ($pluginGroups as $groupDir) {
    $groupName = basename($groupDir);
    $pluginDirs = glob($groupDir . '/*', GLOB_ONLYDIR);
    foreach ($pluginDirs as $pluginDir) {
        $pluginName = basename($pluginDir);
        $langBase = "plugins/{$groupName}/{$pluginName}/language";
        // Only include if at least one locale exists
        if (is_dir($joomlaRoot . '/' . $langBase . '/' . $localeA)
            || is_dir($joomlaRoot . '/' . $langBase . '/' . $localeB)) {
            $label = "plg_{$groupName}_{$pluginName}";
            $extensions[$label] = $langBase;
        }
    }
}

// Modules (site + admin)
foreach (['modules', 'administrator/modules'] as $moduleBase) {
    $moduleDirs = glob($joomlaRoot . '/' . $moduleBase . '/mod_j2commerce_*', GLOB_ONLYDIR);
    foreach ($moduleDirs as $moduleDir) {
        $moduleName = basename($moduleDir);
        $langBase = "{$moduleBase}/{$moduleName}/language";
        if (is_dir($joomlaRoot . '/' . $langBase . '/' . $localeA)
            || is_dir($joomlaRoot . '/' . $langBase . '/' . $localeB)) {
            $side = str_starts_with($moduleBase, 'administrator') ? 'admin' : 'site';
            $label = "{$moduleName} ({$side})";
            $extensions[$label] = $langBase;
        }
    }
}

echo "  Found " . count($extensions) . " extensions with language directories\n\n";

// ── Step 2: Compare Key Sets ─────────────────────────────────────────────────

echo "Step 2: Comparing key sets...\n";

// Global Joomla key prefixes that should NOT appear in extension files
$globalPrefixes = [
    'JGLOBAL_', 'JGRID_', 'JLIB_', 'JERROR_', 'JACTION_', 'JFIELD_',
    'JOPTION_', 'JTOOLBAR_',
];
// Whitelisted global keys intentionally overridden in extension files
$globalWhitelist = ['JGRID_HEADING_ID_ASC', 'JGRID_HEADING_ID_DESC'];
// Exact global keys
$globalExact = ['JNO', 'JYES', 'JALL', 'JNONE', 'JCLOSE', 'JSAVE', 'JAPPLY', 'JCANCEL',
    'JDELETE', 'JEDIT', 'JNEW', 'JPUBLISHED', 'JUNPUBLISHED', 'JTRASHED', 'JARCHIVED',
    'JENABLED', 'JDISABLED', 'JDETAILS', 'JOPTIONS', 'JSTATUS', 'JCATEGORY',
];

$findings = []; // [extension => [file => ['onlyA' => [...], 'onlyB' => [...], 'global' => [...]]]]
$totalOnlyA = 0;
$totalOnlyB = 0;
$totalGlobal = 0;
$totalFilesCompared = 0;
$missingFiles = []; // Files that exist in one locale but not the other

foreach ($extensions as $extension => $langBase) {
    $dirA = $joomlaRoot . '/' . $langBase . '/' . $localeA;
    $dirB = $joomlaRoot . '/' . $langBase . '/' . $localeB;

    // Collect all INI filenames from both locales
    $filesA = is_dir($dirA) ? array_map('basename', glob($dirA . '/*.ini')) : [];
    $filesB = is_dir($dirB) ? array_map('basename', glob($dirB . '/*.ini')) : [];
    $allFiles = array_unique(array_merge($filesA, $filesB));
    sort($allFiles);

    foreach ($allFiles as $fileName) {
        $pathA = $dirA . '/' . $fileName;
        $pathB = $dirB . '/' . $fileName;

        $existsA = file_exists($pathA);
        $existsB = file_exists($pathB);

        // Track files that exist in one locale but not the other
        if ($existsA && !$existsB) {
            $missingFiles[] = [
                'extension' => $extension,
                'file'      => $fileName,
                'missing'   => $localeB,
                'present'   => $localeA,
            ];
            continue;
        }
        if (!$existsA && $existsB) {
            $missingFiles[] = [
                'extension' => $extension,
                'file'      => $fileName,
                'missing'   => $localeA,
                'present'   => $localeB,
            ];
            continue;
        }

        $keysA = parseIniKeys($pathA);
        $keysB = parseIniKeys($pathB);

        $onlyInA = array_diff_key($keysA, $keysB);
        $onlyInB = array_diff_key($keysB, $keysA);

        // Check for global Joomla strings in both files
        $globalInA = filterGlobalKeys($keysA, $globalPrefixes, $globalExact, $globalWhitelist);
        $globalInB = filterGlobalKeys($keysB, $globalPrefixes, $globalExact);
        $globalKeys = array_merge($globalInA, $globalInB);

        if (!empty($onlyInA) || !empty($onlyInB) || !empty($globalKeys)) {
            $findings[$extension][$fileName] = [
                'onlyA'  => $onlyInA,
                'onlyB'  => $onlyInB,
                'global' => $globalKeys,
            ];
            $totalOnlyA += count($onlyInA);
            $totalOnlyB += count($onlyInB);
            $totalGlobal += count($globalKeys);
        }

        $totalFilesCompared++;
    }
}

echo "  Compared {$totalFilesCompared} file pairs\n";
echo "  Only in {$localeA}: {$totalOnlyA} | Only in {$localeB}: {$totalOnlyB} | Global: {$totalGlobal}\n";
echo "  Missing files: " . count($missingFiles) . "\n\n";

// ── Step 3: Generate Report ──────────────────────────────────────────────────

echo "Step 3: Generating report...\n";

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$date = date('Y-m-d');
$timestamp = date('Y-m-d H:i:s');
$reportFile = "{$outputDir}/language-sync-{$date}.md";

$report = "# Language Sync Report\n\n";
$report .= "- **Generated:** {$timestamp}\n";
$report .= "- **Comparing:** {$localeA} ↔ {$localeB}\n";
$report .= "- **File Pairs Compared:** {$totalFilesCompared}\n\n";

$totalIssues = $totalOnlyA + $totalOnlyB + $totalGlobal + count($missingFiles);

if ($totalIssues === 0) {
    $report .= "## Result\n\nAll language files are in sync. No discrepancies found.\n";
} else {
    // Summary
    $report .= "## Summary\n\n";
    $report .= "| Issue Type | Count |\n";
    $report .= "|------------|------:|\n";
    $report .= "| Keys only in {$localeA} | {$totalOnlyA} |\n";
    $report .= "| Keys only in {$localeB} | {$totalOnlyB} |\n";
    $report .= "| Global Joomla keys in extension files | {$totalGlobal} |\n";
    $report .= "| Missing locale files | " . count($missingFiles) . " |\n";
    $report .= "| **Total Issues** | **{$totalIssues}** |\n\n";

    // Missing files
    if (!empty($missingFiles)) {
        $report .= "## Missing Locale Files\n\n";
        $report .= "These files exist in one locale but have no equivalent in the other.\n\n";
        $report .= "| Extension | File | Present In | Missing From |\n";
        $report .= "|-----------|------|------------|-------------|\n";
        foreach ($missingFiles as $mf) {
            $report .= "| {$mf['extension']} | `{$mf['file']}` | {$mf['present']} | {$mf['missing']} |\n";
        }
        $report .= "\n";
    }

    // Key discrepancies
    if ($totalOnlyA > 0 || $totalOnlyB > 0) {
        $report .= "## Key Discrepancies\n\n";

        foreach ($findings as $extension => $files) {
            foreach ($files as $fileName => $data) {
                if (empty($data['onlyA']) && empty($data['onlyB'])) {
                    continue;
                }

                $report .= "### {$extension} (`{$fileName}`)\n\n";

                if (!empty($data['onlyA'])) {
                    $report .= "**Only in {$localeA}** (" . count($data['onlyA']) . " keys):\n\n";
                    foreach ($data['onlyA'] as $key => $value) {
                        $escapedValue = str_replace('|', '\\|', $value);
                        $report .= "- `{$key}` = \"{$escapedValue}\"\n";
                    }
                    $report .= "\n";
                }

                if (!empty($data['onlyB'])) {
                    $report .= "**Only in {$localeB}** (" . count($data['onlyB']) . " keys):\n\n";
                    foreach ($data['onlyB'] as $key => $value) {
                        $escapedValue = str_replace('|', '\\|', $value);
                        $report .= "- `{$key}` = \"{$escapedValue}\"\n";
                    }
                    $report .= "\n";
                }
            }
        }
    }

    // Global keys
    if ($totalGlobal > 0) {
        $report .= "## Global Joomla Strings in Extension Files\n\n";
        $report .= "These keys belong to Joomla core and should not be defined in extension language files.\n\n";

        foreach ($findings as $extension => $files) {
            foreach ($files as $fileName => $data) {
                if (empty($data['global'])) {
                    continue;
                }

                $report .= "### {$extension} (`{$fileName}`)\n\n";
                foreach ($data['global'] as $key => $value) {
                    $escapedValue = str_replace('|', '\\|', $value);
                    $report .= "- `{$key}` = \"{$escapedValue}\"\n";
                }
                $report .= "\n";
            }
        }
    }
}

file_put_contents($reportFile, $report);

echo "Report saved to: {$reportFile}\n";

if ($totalIssues > 0) {
    echo "\nWARNING: {$totalIssues} issue(s) found. Review the report for details.\n";
    exit(1);
} else {
    echo "\nAll language files are in sync.\n";
    exit(0);
}

// ── Helper Functions ─────────────────────────────────────────────────────────

function parseIniKeys(string $filePath): array
{
    $keys = [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return $keys;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and blank lines
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        // Extract KEY=value
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        // Remove surrounding quotes from value
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }

        // Validate key format (uppercase with underscores and digits)
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            $keys[$key] = $value;
        }
    }

    return $keys;
}

function filterGlobalKeys(array $keys, array $prefixes, array $exactKeys, array $whitelist = []): array
{
    $global = [];

    foreach ($keys as $key => $value) {
        // Skip whitelisted keys (intentional overrides)
        if (in_array($key, $whitelist, true)) {
            continue;
        }

        // Check exact matches
        if (in_array($key, $exactKeys, true)) {
            $global[$key] = $value;
            continue;
        }

        // Check prefix matches (but skip COM_J2COMMERCE_ keys — those are ours)
        if (str_starts_with($key, 'COM_') || str_starts_with($key, 'PLG_')
            || str_starts_with($key, 'MOD_') || str_starts_with($key, 'LIB_')
            || str_starts_with($key, 'J2COMMERCE_')) {
            continue;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                $global[$key] = $value;
                break;
            }
        }
    }

    return $global;
}
