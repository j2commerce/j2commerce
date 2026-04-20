#!/usr/bin/env php
<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Vendor library version checker and updater.
 *
 * Usage:
 *   php build/check_vendors.php            — check current vs latest
 *   php build/check_vendors.php --update   — download latest files from unpkg
 *   php build/check_vendors.php --pin      — update vendors.json to match latest versions
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

$update = in_array('--update', $argv);
$pin    = in_array('--pin', $argv);

$manifest = json_decode(file_get_contents(__DIR__ . '/vendors.json'), true);

if (!$manifest || empty($manifest['vendors'])) {
    echo "ERROR: Could not read build/vendors.json\n";
    exit(1);
}

$col = [
    'reset'  => "\033[0m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'red'    => "\033[31m",
    'cyan'   => "\033[36m",
    'bold'   => "\033[1m",
    'dim'    => "\033[2m",
];

$hasUpdates = false;
$errors     = [];
$latestMap  = [];

echo "\n{$col['bold']}Checking vendor libraries...{$col['reset']}\n\n";
printf("%-35s %-12s %-12s %s\n", 'Library', 'Current', 'Latest', 'Status');
echo str_repeat('─', 80) . "\n";

foreach ($manifest['vendors'] as $i => $vendor) {
    $name    = $vendor['name'];
    $npm     = $vendor['npm'];
    $current = $vendor['version'];

    $latest        = fetchLatestVersion($npm);
    $latestMap[$i] = $latest;

    if ($latest === null) {
        $status = "{$col['dim']}registry error{$col['reset']}";
        $errors[] = $npm;
    } elseif ($current === null) {
        $status = "{$col['yellow']}untracked — pin to: {$latest}{$col['reset']}";
        $hasUpdates = true;
    } elseif ($current === $latest) {
        $status = "{$col['green']}up to date{$col['reset']}";
    } else {
        [$cMaj, $cMin] = explode('.', $current . '.0.0');
        [$lMaj, $lMin] = explode('.', $latest . '.0.0');
        if ($lMaj > $cMaj) {
            $status = "{$col['red']}MAJOR update available{$col['reset']}";
        } elseif ($lMin > $cMin) {
            $status = "{$col['yellow']}minor update available{$col['reset']}";
        } else {
            $status = "{$col['cyan']}patch available{$col['reset']}";
        }
        $hasUpdates = true;
    }

    printf(
        "%-35s %-12s %-12s %s\n",
        $name,
        $current ?? ($col['dim'] . 'unknown' . $col['reset']),
        $latest ?? 'n/a',
        $status
    );
}

echo "\n";

if (!empty($errors)) {
    echo "{$col['yellow']}Registry lookup failed for: " . implode(', ', $errors) . "{$col['reset']}\n\n";
}

if ($pin || $update) {
    foreach ($manifest['vendors'] as $i => $vendor) {
        $latest = $latestMap[$i] ?? null;
        if ($latest === null) {
            continue;
        }

        if ($pin && $vendor['version'] !== $latest) {
            echo "Pinning {$vendor['name']} {$vendor['version']} → {$latest}\n";
            $manifest['vendors'][$i]['version'] = $latest;
        }

        if ($update) {
            $vendorForDownload = $pin ? $manifest['vendors'][$i] : $vendor;
            downloadVendorFiles($vendorForDownload, $col);
        }
    }

    if ($pin) {
        file_put_contents(
            __DIR__ . '/vendors.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        echo "{$col['green']}vendors.json updated.{$col['reset']}\n";
    }
}

if (!$hasUpdates && empty($errors)) {
    echo "{$col['green']}All vendors are up to date.{$col['reset']}\n";
} elseif ($hasUpdates && !$update) {
    echo "Run {$col['bold']}php build/check_vendors.php --update --pin{$col['reset']} to download latest files and update vendors.json.\n";
}

echo "\n";

// ─── Helpers ────────────────────────────────────────────────────────────────

function fetchLatestVersion(string $npm): ?string
{
    $url = 'https://registry.npmjs.org/' . rawurlencode($npm) . '/latest';
    $ctx = stream_context_create(['http' => [
        'timeout' => 10,
        'header'  => "User-Agent: j2commerce-vendor-checker\r\n",
    ]]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }

    $data = json_decode($body, true);
    return $data['version'] ?? null;
}

function downloadVendorFiles(array $vendor, array $col): void
{
    $version = $vendor['version'];
    if ($version === null) {
        echo "{$col['dim']}Skipping {$vendor['name']} — version not pinned{$col['reset']}\n";
        return;
    }

    foreach ($vendor['files'] as $file) {
        $url  = "https://unpkg.com/{$vendor['npm']}@{$version}/{$file['src']}";
        $dest = ROOT . '/' . $file['dest'];

        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ctx = stream_context_create(['http' => [
            'timeout'          => 30,
            'follow_location'  => true,
            'header'           => "User-Agent: j2commerce-vendor-checker\r\n",
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || strlen($body) < 100) {
            echo "{$col['red']}FAILED{$col['reset']} {$vendor['name']}: {$file['src']}\n";
            continue;
        }

        file_put_contents($dest, $body);
        $kb = round(strlen($body) / 1024);
        echo "{$col['green']}OK{$col['reset']}     {$vendor['name']}: {$file['dest']} ({$kb}KB)\n";
    }
}
