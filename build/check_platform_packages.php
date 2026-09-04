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
 * Pre-package check: does the PLATFORM still provide the namespaces our code uses?
 *
 * First-party code deliberately does not vendor packages the platform already
 * ships — a duplicate can never win a class lookup, because the platform
 * registers a classmap at bootstrap and a classmap is consulted ahead of any
 * PSR-4 prefix. The cost of that decision is a dependency nothing declares: the
 * platform can stop shipping a package in any release, and nothing in its
 * changelog would call that a breaking change.
 *
 * So this asks the platform itself, through its own CLI, rather than reading
 * files out of one particular installation. `cli/joomla.php` boots the real
 * application with the real autoloader; a path probe only tells you what is on
 * one disk, which is not the same question and goes stale the moment the
 * packaging host differs from the machine the answer was gathered on.
 *
 * The namespaces checked are DISCOVERED from first-party `use` statements, not
 * hardcoded, so a new platform dependency starts being verified the moment
 * somebody imports it.
 *
 * Usage:
 *   php build/check_platform_packages.php
 *   php build/check_platform_packages.php --build-check   # compact; exit 1 on a miss
 *   php build/check_platform_packages.php --json
 *   php build/check_platform_packages.php --cli=PATH      # non-default CLI location
 *
 * Exit codes:
 *   0  every discovered platform namespace resolves
 *   1  at least one does not resolve, or the CLI could not be run
 *   2  bad invocation
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

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    fwrite(STDERR, "ERROR: cannot resolve repository root\n");
    exit(2);
}

$root       = str_replace('\\', '/', $root);
$json       = in_array('--json', $argv, true);
$buildCheck = in_array('--build-check', $argv, true);
$cli        = $root . '/cli/joomla.php';

foreach ($argv as $a) {
    if (str_starts_with($a, '--cli=')) {
        $cli = str_replace('\\', '/', substr($a, 6));
    }
}

/**
 * Roots holding first-party code. A namespace imported anywhere in here and not
 * shipped by us is, by definition, expected from the platform.
 */
const FIRST_PARTY = [
    'administrator/components/com_j2commerce',
    'components/com_j2commerce',
    'api/components/com_j2commerce',
    'libraries/j2commerce',
    'libraries/j2commerceflow',
    'plugins',
    'modules',
    'administrator/modules',
];

/**
 * Namespace roots the platform owns.
 *
 * Joomla's own `Joomla\` tree is excluded — it is the framework itself, and its
 * absence is not a dependency question but a broken install. What matters here
 * is the third-party packages Joomla happens to ship and our code leans on.
 */
const PLATFORM_ROOTS = ['phpseclib3', 'ParagonIE', 'Psr', 'GuzzleHttp', 'Symfony', 'Brick', 'Defuse'];

/**
 * Every platform-owned class first-party code imports.
 *
 * @return array<string, list<string>>  namespace root => importing files
 */
function discoverPlatformImports(string $root): array
{
    $found = [];

    foreach (FIRST_PARTY as $rel) {
        $abs = $root . '/' . $rel;

        if (!is_dir($abs)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            // Bundled dependencies live under these; our own code does not. A
            // `/vendor/` test alone is not enough — Mollie's Guzzle HTTP adapter
            // sits at lib/src/HttpAdapter/ and was reported as eight missing
            // platform namespaces until lib/ and library/ were excluded too.
            if (preg_match('#/(vendor|lib|library|node_modules)/#', $path)) {
                continue;
            }

            $body = (string) file_get_contents($path);

            // Second gate: the file's own namespace. First-party extensions use
            // either the J2Commerce namespace or Joomla's own extension
            // convention — plugins/filesystem/ultimatedownloads is ours but
            // declares Joomla\Plugin\..., so a J2Commerce-only test would drop
            // the very phpseclib consumers this check exists to cover.
            if (!preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $body, $ns)) {
                continue;
            }

            $isFirstParty = str_contains($ns[1], 'J2Commerce')
                || str_starts_with($ns[1], 'Joomla\\Plugin\\')
                || str_starts_with($ns[1], 'Joomla\\Module\\')
                || str_starts_with($ns[1], 'Joomla\\Component\\');

            if (!$isFirstParty) {
                continue;
            }

            $hits = [];

            // `use Foo\Bar;`
            if (preg_match_all('/^use\s+\\\\?([A-Za-z0-9_]+)\\\\([A-Za-z0-9_\\\\]+?)\s*(?:as\s|;)/m', $body, $m, PREG_SET_ORDER)) {
                $hits = $m;
            }

            // Fully-qualified inline references, e.g. `\phpseclib3\Crypt\RSA::class`.
            // A dependency asserted this way is exactly the kind a guard uses, so
            // missing it would blind the check to the code written to protect it.
            if (preg_match_all('/\\\\([A-Za-z0-9_]+)\\\\([A-Za-z0-9_\\\\]+?)::/m', $body, $m2, PREG_SET_ORDER)) {
                $hits = array_merge($hits, $m2);
            }

            foreach ($hits as $hit) {
                if (!in_array($hit[1], PLATFORM_ROOTS, true)) {
                    continue;
                }

                $class = $hit[1] . '\\' . rtrim($hit[2], '\\');
                $rel   = substr($path, strlen($root) + 1);

                if (!in_array($rel, $found[$class] ?? [], true)) {
                    $found[$class][] = $rel;
                }
            }
        }
    }

    ksort($found);

    return $found;
}

// --- ask the platform -------------------------------------------------------

if (!is_file($cli)) {
    fwrite(STDERR, "ERROR: Joomla CLI not found at {$cli}\n\n");
    fwrite(STDERR, "       cli/joomla.php belongs to Joomla, not to this repository, so a\n");
    fwrite(STDERR, "       standalone checkout does not contain it — the same is true of\n");
    fwrite(STDERR, "       libraries/vendor, which is where the packages this check asks about\n");
    fwrite(STDERR, "       live. Run this where the extensions are deployed into a Joomla\n");
    fwrite(STDERR, "       install, which is also where packaging happens.\n\n");
    fwrite(STDERR, "       Or point it at an install:\n");
    fwrite(STDERR, "         php build/check_platform_packages.php --cli=/path/to/joomla/cli/joomla.php\n\n");
    fwrite(STDERR, "       Exiting non-zero on purpose: a check that could not run has not\n");
    fwrite(STDERR, "       found anything, and must not read as a pass.\n");
    exit(1);
}

$version = [];
$code    = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cli) . ' --version 2>&1', $version, $code);

if ($code !== 0) {
    fwrite(STDERR, "ERROR: the Joomla CLI did not run. Nothing can be verified against a platform that will not boot.\n");
    fwrite(STDERR, implode("\n", $version) . "\n");
    exit(1);
}

$versionLine = trim(preg_replace('/\033\[[0-9;]*m/', '', implode(' ', $version)) ?? '');

$imports = discoverPlatformImports($root);

// Discovering nothing is a broken check, not a clean result. First-party code
// imports platform namespaces in double figures; zero means a root was renamed,
// or the namespace/use regexes stopped matching after a refactor. Without this
// the script would print "every namespace is available" having verified nothing
// — the exact false clean it exists to prevent, and the shape its sibling
// already guards with a floor on parsed ship roots.
if ($imports === []) {
    fwrite(STDERR, "ERROR: discovered no platform namespace imports in first-party code.\n\n");
    fwrite(STDERR, "       That is not a clean result — it means this check found nothing to\n");
    fwrite(STDERR, "       verify. Expect a FIRST_PARTY root to have been renamed, or the\n");
    fwrite(STDERR, "       namespace/use patterns to have stopped matching.\n\n");
    fwrite(STDERR, "       Refusing to report a pass on an empty set.\n");
    exit(1);
}

/**
 * Probes each class through Joomla's own bootstrap.
 *
 * Run as a child process off the CLI's own entry point, so the autoloader under
 * test is the one a real request would use — not one this script assembled.
 *
 * @param  list<string> $classes
 * @return array<string, bool>
 */
function probeThroughJoomla(string $root, string $cli, array $classes): array
{
    // The class list travels by FILE, not by argv. Class names are full of
    // backslashes and the JSON adds quotes; escapeshellarg quotes differently on
    // Windows than on POSIX and mangles both, which makes the probe silently
    // return nothing — indistinguishable from "the platform has none of them".
    $listFile = dirname($cli) . '/j2c-platform-probe-list.json';
    $file     = dirname($cli) . '/j2c-platform-probe.php';

    $probe = <<<'PHP'
<?php
define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
$out = [];
foreach (json_decode(file_get_contents(__DIR__ . '/j2c-platform-probe-list.json'), true) as $class) {
    $out[$class] = class_exists($class) || interface_exists($class) || trait_exists($class);
}
echo "\n__J2C_PROBE__" . json_encode($out) . "__J2C_PROBE__\n";
PHP;

    // Cleanup is registered BEFORE the files are written, so an early return, an
    // interrupt, a CI timeout or a fatal cannot leave an executable .php behind
    // in cli/. A build gate that can litter the tree it audits is its own defect,
    // and the `||` short-circuit below makes the early-return case reachable on
    // the very first failure.
    $cleanup = static function () use ($file, $listFile): void {
        @unlink($file);
        @unlink($listFile);
    };

    register_shutdown_function($cleanup);

    if (@file_put_contents($file, $probe) === false
        || @file_put_contents($listFile, json_encode(array_values($classes))) === false) {
        $cleanup();

        return [];
    }

    $raw  = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $raw, $code);

    $cleanup();

    // Joomla's bootstrap emits deprecation notices on some PHP builds, so the
    // payload is fenced rather than assumed to be the whole of stdout.
    if (!preg_match('/__J2C_PROBE__(.*)__J2C_PROBE__/s', implode("\n", $raw), $m)) {
        return [];
    }

    $decoded = json_decode(trim($m[1]), true);

    return is_array($decoded) ? $decoded : [];
}

$results = $imports === [] ? [] : probeThroughJoomla($root, $cli, array_keys($imports));

$missing = [];
$present = [];

foreach ($imports as $class => $files) {
    if (($results[$class] ?? false) === true) {
        $present[$class] = $files;
    } else {
        $missing[$class] = $files;
    }
}

$probeFailed = $imports !== [] && $results === [];

// --- report -----------------------------------------------------------------

if ($json) {
    echo json_encode([
        'joomla'       => $versionLine,
        'probe_failed' => $probeFailed,
        'present'      => array_map('count', $present),
        'missing'      => $missing,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit($missing !== [] || $probeFailed ? 1 : 0);
}

if ($probeFailed) {
    fwrite(STDERR, "ERROR: could not probe classes through the Joomla bootstrap. Treat this as a failure, not a pass —\n");
    fwrite(STDERR, "       a check that did not run has not found anything.\n");
    exit(1);
}

if ($buildCheck) {
    if ($missing === []) {
        echo 'Platform package check OK — ' . count($present) . " namespace(s) resolve on {$versionLine}\n";
        exit(0);
    }

    echo "\nERROR: the platform no longer provides " . count($missing) . " namespace(s) first-party code imports:\n";

    foreach ($missing as $class => $files) {
        echo "  {$class}  (used by " . count($files) . " file(s), e.g. {$files[0]})\n";
    }

    echo "\n  Checked against: {$versionLine}\n";
    echo "  Refusing to package: this fails at runtime, not at install time.\n\n";
    exit(1);
}

echo "\nPlatform package check\n";
echo str_repeat('─', 74) . "\n";
echo "Platform:  {$versionLine}\n";
echo "Verified through the platform CLI's own bootstrap, not a filesystem probe.\n\n";

if ($present !== []) {
    echo 'RESOLVES (' . count($present) . ")\n";
    foreach ($present as $class => $files) {
        printf("  %-46s %d importing file(s)\n", $class, count($files));
    }
    echo "\n";
}

if ($missing === []) {
    echo "Every platform namespace first-party code imports is available.\n\n";
    exit(0);
}

echo 'MISSING (' . count($missing) . ") — first-party code imports these and the platform does not supply them\n";

foreach ($missing as $class => $files) {
    echo "  {$class}\n";
    foreach (array_slice($files, 0, 4) as $f) {
        echo "      {$f}\n";
    }
}

echo "\n  These fail at runtime, not at install time. Either the platform dropped the\n";
echo "  package, or the importing code needs its own copy with a loader that runs.\n\n";

exit(1);
