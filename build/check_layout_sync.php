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
 * Shadowed layout drift check.
 *
 * ProductLayoutService::renderLayout() builds its search path per folder in the subtemplate
 * chain, and within each folder the order is fixed:
 *
 *   1. templates/<template>/html/layouts/com_j2commerce/<folder>/   site override
 *   2. components/com_j2commerce/layouts/<folder>/                  component
 *   3. plugins/j2commerce/<folder>/layouts/                         plugin
 *
 * The component rung is unconditional for app_bootstrap5 and app_uikit, because the component
 * ships those folders. So whenever a layout id exists in both places the plugin copy is
 * searched only after a directory that already matched, and can never execute.
 *
 * That is fine as long as the two stay identical. They did not: by 6.6.3, 64 of the 94 plugin
 * list layouts had drifted from the copies that run, and the drift was invisible because the
 * wrong file still renders correctly — it renders the right file. The costs land on everyone
 * reading or changing the code: a fix applied to the plugin copy looks applied and does
 * nothing, a fix applied only to the component copy looks incomplete, and the pair reads as a
 * source/deployed relationship whose direction is the opposite of what the search order says.
 *
 * The plugin rung is not dead weight — it is the only rung a custom subtemplate plugin has,
 * since a custom folder has no component directory to shadow it. So the shipped framework
 * folders are kept as the reference implementation a third-party subtemplate is modelled on,
 * and this check is what keeps them honest.
 *
 * A plugin layout with no component twin is skipped, not reported: those are the ids where
 * the plugin rung genuinely resolves (layouts for product types whose own plugin deploys the
 * component copy at install time, or ships none at all).
 *
 * Only git-tracked files are compared. Files an extension's installer writes into the
 * component layouts directory are not part of the repo and are owned by that extension.
 *
 * Usage:  php build/check_layout_sync.php
 * Exits 1 when a shadowed plugin layout diverges from the component copy that executes.
 */

declare(strict_types=1);

// CLI only. build/ is excluded from every package so these never reach an
// install, but on a dev or CI box whose docroot is the Joomla root they are
// served like any other file. Without this the only thing stopping a web hit is
// register_argc_argv being off — an accident, not a control.
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit(1);
}

$repoRoot = \dirname(__DIR__);

/** @return string[] repo-relative paths, forward slashes */
function trackedFiles(string $repoRoot, string $pathspec): array
{
    $cmd = 'git -c core.quotepath=off -C ' . escapeshellarg($repoRoot) . ' ls-files -- ' . escapeshellarg($pathspec);
    exec($cmd, $out, $status);

    if ($status !== 0) {
        fwrite(STDERR, "ERROR: git ls-files failed for {$pathspec}. Run this from inside the repository.\n");
        exit(1);
    }

    // git ls-files emits forward slashes on every platform.
    return array_map(static fn (string $p): string => trim($p), $out);
}

// Listed without a glob pathspec: escapeshellarg() quoting makes `app_*` unreliable across
// platforms, and filtering the full list here costs nothing.
$pluginLayouts = array_values(array_filter(
    trackedFiles($repoRoot, 'plugins/j2commerce'),
    static fn (string $p): bool => (bool) preg_match('#^plugins/j2commerce/app_[a-z0-9]+/layouts/#', $p)
));

if ($pluginLayouts === []) {
    fwrite(STDERR, "ERROR: no tracked plugin layouts found — git could not answer, or the tree moved.\n");
    exit(1);
}

$tracked = array_flip(trackedFiles($repoRoot, 'components/com_j2commerce/layouts'));

$diverged = [];
$compared = 0;
$skipped  = 0;

foreach ($pluginLayouts as $pluginPath) {
    $twin = preg_replace(
        '#^plugins/j2commerce/(app_[a-z0-9]+)/layouts/#',
        'components/com_j2commerce/layouts/$1/',
        $pluginPath
    );

    // No tracked component twin: this id is one the plugin rung really resolves.
    if ($twin === null || !isset($tracked[$twin])) {
        $skipped++;
        continue;
    }

    $compared++;

    $a = @file_get_contents($repoRoot . '/' . $pluginPath);
    $b = @file_get_contents($repoRoot . '/' . $twin);

    if ($a === false || $b === false) {
        $diverged[] = [$pluginPath, $twin, 'unreadable'];
        continue;
    }

    if ($a !== $b) {
        $diverged[] = [$pluginPath, $twin, 'content differs'];
    }
}

echo "Shadowed layout drift check\n";
echo str_repeat('=', 60), "\n";
printf("compared : %d shadowed plugin layout(s)\n", $compared);
printf("skipped  : %d with no tracked component twin (plugin rung resolves)\n", $skipped);

if ($diverged === []) {
    echo "\nOK — every shadowed plugin layout matches the component copy that executes.\n";
    exit(0);
}

printf("\nFAIL — %d shadowed layout(s) differ from the copy that actually renders:\n\n", \count($diverged));

foreach ($diverged as [$pluginPath, $twin, $why]) {
    echo "  {$pluginPath}\n";
    echo "    twin : {$twin}  ({$why})\n";
    echo "    fix  : cp {$twin} {$pluginPath}\n\n";
}

echo "The component copy is the one that renders. Copy it over the plugin copy — never the\n";
echo "other way round — then re-run this check.\n";

exit(1);
