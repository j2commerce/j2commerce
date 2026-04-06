<?php

declare(strict_types=1);

/**
 * J2Commerce Unused Language String Checker
 *
 * Scans all J2Commerce language .ini files for a given language tag,
 * searches the entire codebase for references, and generates a Markdown
 * report of unused keys.
 *
 * Usage:
 *   php build/check_unused_language_strings.php [--lang=en-GB]
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

// Parse --lang argument (default: en-GB)
$lang = 'en-GB';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--lang=')) {
        $lang = substr($arg, 7);
    }
}

// Validate language tag format
if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $lang)) {
    fwrite(STDERR, "ERROR: Invalid language tag '{$lang}'. Expected format: xx-XX (e.g., en-GB, de-DE)\n");
    exit(1);
}

echo "J2Commerce Unused Language String Checker\n";
echo "==========================================\n";
echo "Language: {$lang}\n";
echo "Root:     {$joomlaRoot}\n\n";

// ── Step 1: Discover Language Files ────────────────────────────────────────────

echo "Step 1: Discovering language files...\n";

$languageDirs = [
    'com_j2commerce (admin)'    => "administrator/components/com_j2commerce/language/{$lang}",
    'com_j2commerce (site)'     => "components/com_j2commerce/language/{$lang}",
    'lib_j2commerce'            => "libraries/j2commerce/language/{$lang}",
];

// Discover plugin language dirs
$pluginGroups = glob($joomlaRoot . '/plugins/*', GLOB_ONLYDIR);
foreach ($pluginGroups as $groupDir) {
    $groupName = basename($groupDir);
    $pluginDirs = glob($groupDir . '/*', GLOB_ONLYDIR);
    foreach ($pluginDirs as $pluginDir) {
        $pluginName = basename($pluginDir);
        $langDir = "plugins/{$groupName}/{$pluginName}/language/{$lang}";
        if (is_dir($joomlaRoot . '/' . $langDir)) {
            $label = "plg_{$groupName}_{$pluginName}";
            $languageDirs[$label] = $langDir;
        }
    }
}

// Discover module language dirs (site + admin)
foreach (['modules', 'administrator/modules'] as $moduleBase) {
    $moduleDirs = glob($joomlaRoot . '/' . $moduleBase . '/mod_j2commerce_*', GLOB_ONLYDIR);
    foreach ($moduleDirs as $moduleDir) {
        $moduleName = basename($moduleDir);
        $langDir = "{$moduleBase}/{$moduleName}/language/{$lang}";
        if (is_dir($joomlaRoot . '/' . $langDir)) {
            $side = str_starts_with($moduleBase, 'administrator') ? 'admin' : 'site';
            $label = "{$moduleName} ({$side})";
            $languageDirs[$label] = $langDir;
        }
    }
}

// ── Step 2: Parse Keys ─────────────────────────────────────────────────────────

echo "Step 2: Parsing language keys...\n";

$allKeys = []; // [extension => [file => [key => value]]]
$totalKeyCount = 0;

foreach ($languageDirs as $extension => $relDir) {
    $absDir = $joomlaRoot . '/' . $relDir;
    if (!is_dir($absDir)) {
        continue;
    }

    $iniFiles = glob($absDir . '/*.ini');
    foreach ($iniFiles as $iniFile) {
        $fileName = basename($iniFile);
        $keys = parseIniKeys($iniFile);
        if (!empty($keys)) {
            $allKeys[$extension][$fileName] = $keys;
            $totalKeyCount += count($keys);
        }
    }
}

echo "  Found {$totalKeyCount} keys across " . count($allKeys) . " extensions\n\n";

if ($totalKeyCount === 0) {
    fwrite(STDERR, "ERROR: No language keys found for language '{$lang}'. Check that the language directory exists.\n");
    exit(1);
}

// ── Step 3: Build Search Corpus ────────────────────────────────────────────────

echo "Step 3: Building search corpus...\n";

$excludeDirs = [
    '/language/',
    '/node_modules/',
    '/.git/',
    '/build/',
    '/docs/',
    '/tmp/',
    '/cache/',
    '/logs/',
];

$sourceExtensions = ['php', 'xml', 'js'];
$corpusFiles = [];
$corpus = '';
$phpCorpus = ''; // Separate PHP corpus for dynamic prefix detection

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($joomlaRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $filePath = str_replace('\\', '/', $file->getPathname());
    $relPath = str_replace('\\', '/', substr($filePath, strlen($joomlaRoot)));

    // Skip excluded directories
    $skip = false;
    foreach ($excludeDirs as $excludeDir) {
        if (str_contains($relPath, $excludeDir)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $sourceExtensions, true)) {
        continue;
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    $corpus .= $content . "\n";
    $corpusFiles[] = $relPath;

    if ($ext === 'php') {
        $phpCorpus .= $content . "\n";
    }
}

echo "  Indexed " . count($corpusFiles) . " source files (" . formatBytes(strlen($corpus)) . ")\n\n";

// ── Step 4: Detect Dynamic Key Prefixes ────────────────────────────────────────

echo "Step 4: Detecting dynamic key prefixes...\n";

$dynamicPrefixes = [];
$dynamicSources = []; // prefix => [source context]

// Match Text::_('PREFIX' . and Text::sprintf('PREFIX' .
$patterns = [
    '/Text::_\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"][\s]*\./m',
    '/Text::sprintf\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"][\s]*\./m',
];

foreach ($patterns as $pattern) {
    if (preg_match_all($pattern, $phpCorpus, $matches)) {
        foreach ($matches[1] as $prefix) {
            if (!in_array($prefix, $dynamicPrefixes, true)) {
                $dynamicPrefixes[] = $prefix;
            }
        }
    }
}

// Also check for array-based lookups like $statuses = ['KEY1', 'KEY2']; Text::_($statuses[$x])
// These are harder to detect, so we focus on the concatenation pattern above.

echo "  Found " . count($dynamicPrefixes) . " dynamic prefixes\n";
foreach ($dynamicPrefixes as $prefix) {
    echo "    - {$prefix}\n";
}
echo "\n";

// ── Step 5: Check Usage ────────────────────────────────────────────────────────

echo "Step 5: Checking key usage...\n";

$unusedKeys = [];      // [extension => [file => [key => value]]]
$dynamicKeys = [];     // [extension => [file => [key => value]]]
$usedCount = 0;
$unusedCount = 0;
$dynamicCount = 0;

foreach ($allKeys as $extension => $files) {
    foreach ($files as $fileName => $keys) {
        foreach ($keys as $key => $value) {
            if (strpos($corpus, $key) !== false) {
                $usedCount++;
                continue;
            }

            // Check if key matches a dynamic prefix
            $isDynamic = false;
            foreach ($dynamicPrefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $isDynamic = true;
                    $dynamicKeys[$extension][$fileName][$key] = $value;
                    $dynamicCount++;
                    break;
                }
            }

            if (!$isDynamic) {
                $unusedKeys[$extension][$fileName][$key] = $value;
                $unusedCount++;
            }
        }
    }
}

echo "  Used: {$usedCount} | Unused: {$unusedCount} | Dynamic: {$dynamicCount}\n\n";

// ── Step 6: Generate Report ────────────────────────────────────────────────────

echo "Step 6: Generating report...\n";

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$date = date('Y-m-d');
$timestamp = date('Y-m-d H:i:s');
$reportFile = "{$outputDir}/unused-language-strings-{$lang}-{$date}.md";

$report = "# Unused Language Strings Report\n\n";
$report .= "- **Generated:** {$timestamp}\n";
$report .= "- **Language:** {$lang}\n";
$report .= "- **Joomla Root:** {$joomlaRoot}\n";
$report .= "- **Source Files Scanned:** " . count($corpusFiles) . "\n\n";

// Summary table
$report .= "## Summary\n\n";
$report .= "| Extension | File | Total | Used | Unused | Dynamic | Unused % |\n";
$report .= "|-----------|------|------:|-----:|-------:|--------:|---------:|\n";

foreach ($allKeys as $extension => $files) {
    foreach ($files as $fileName => $keys) {
        $total = count($keys);
        $unused = isset($unusedKeys[$extension][$fileName]) ? count($unusedKeys[$extension][$fileName]) : 0;
        $dynamic = isset($dynamicKeys[$extension][$fileName]) ? count($dynamicKeys[$extension][$fileName]) : 0;
        $used = $total - $unused - $dynamic;
        $pct = $total > 0 ? round(($unused / $total) * 100, 1) : 0;

        $report .= "| {$extension} | {$fileName} | {$total} | {$used} | {$unused} | {$dynamic} | {$pct}% |\n";
    }
}

$report .= "\n**Totals:** {$totalKeyCount} keys defined, {$usedCount} used, {$unusedCount} unused, {$dynamicCount} potentially dynamic\n\n";

// Unused keys detail
if ($unusedCount > 0) {
    $report .= "## Unused Keys by Extension\n\n";
    $report .= "These keys are defined in language files but not referenced in any PHP, XML, or JS file.\n\n";

    foreach ($unusedKeys as $extension => $files) {
        foreach ($files as $fileName => $keys) {
            $report .= "### {$extension} (`{$fileName}`)\n\n";
            foreach ($keys as $key => $value) {
                $escapedValue = str_replace('|', '\\|', $value);
                $report .= "- `{$key}` = \"{$escapedValue}\"\n";
            }
            $report .= "\n";
        }
    }
}

// Dynamic keys section
if ($dynamicCount > 0) {
    $report .= "## Potentially Dynamic Keys\n\n";
    $report .= "These keys may be constructed at runtime via string concatenation (e.g., `Text::_('PREFIX_' . \$var)`). Review manually before removing.\n\n";

    $report .= "### Detected Prefixes\n\n";
    foreach ($dynamicPrefixes as $prefix) {
        $report .= "- `{$prefix}`\n";
    }
    $report .= "\n";

    $report .= "### Keys Matching Dynamic Prefixes\n\n";
    foreach ($dynamicKeys as $extension => $files) {
        foreach ($files as $fileName => $keys) {
            $report .= "#### {$extension} (`{$fileName}`)\n\n";
            foreach ($keys as $key => $value) {
                $escapedValue = str_replace('|', '\\|', $value);
                $report .= "- `{$key}` = \"{$escapedValue}\"\n";
            }
            $report .= "\n";
        }
    }
}

// No unused keys
if ($unusedCount === 0 && $dynamicCount === 0) {
    $report .= "## Result\n\nAll language keys are in use. No unused keys found.\n";
}

file_put_contents($reportFile, $report);

echo "Report saved to: {$reportFile}\n";
echo "Done.\n";

// ── Helper Functions ───────────────────────────────────────────────────────────

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

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}
