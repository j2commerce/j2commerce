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
 * Shipped-artifact surface audit + dependency advisory scan.
 *
 * The existing pre-build gates answer "is the code right". This one answers a
 * question nothing else in the pipeline asks: *what is actually in the box*.
 *
 * `build_package.php` assembles every zip by walking the filesystem against a
 * curated `$excludePatterns` deny-list. A deny-list only removes names it
 * already knows, so any dev artifact committed — or merely left — inside an
 * extension's own directory under a name nobody anticipated ships silently to
 * every merchant. This gate closes that by diffing the predicted ship set
 * against `git ls-files`: git is the allow-list the build never had.
 *
 * The dependency half answers the other question the build never asked: what
 * version of each vendored package is actually in the box, and does any
 * published advisory range cover it.
 *
 * Usage:
 *   php build/check_package_surface.php                 — full report
 *   php build/check_package_surface.php --build-check   — compact; exit 1 on CRITICAL
 *   php build/check_package_surface.php --strict        — exit 1 on HIGH as well
 *   php build/check_package_surface.php --deps          — dependency advisories only
 *   php build/check_package_surface.php --surface       — shipped-file audit only
 *   php build/check_package_surface.php --offline       — never touch the network
 *   php build/check_package_surface.php --refresh       — ignore the advisory cache
 *   php build/check_package_surface.php --json          — machine-readable
 *
 * Exit codes:
 *   0  clean, or findings below the abort threshold
 *   1  abort — CRITICAL (or HIGH under --strict)
 *   2  bad invocation / the gate could not determine the ship set
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

const ROOT_DIR  = __DIR__ . '/..';
const CACHE_TTL = 86400;

/**
 * Where the advisory cache lives.
 *
 * `sys_get_temp_dir()` so it works on every checkout — a machine-specific path
 * makes the cache a silent no-op for everyone else, and every CI run then pays
 * a live round-trip for data that changes daily at most. Override with
 * J2C_SURFACE_CACHE when a build wants the cache somewhere it persists between
 * runs.
 */
function cacheFile(): string
{
    $override = getenv('J2C_SURFACE_CACHE');

    if (is_string($override) && $override !== '') {
        return $override;
    }

    // NOT a predictable name directly in the shared temp root. On POSIX that is
    // /tmp, mode 1777, so any local user could pre-create the file with an empty
    // advisory set and a fresh timestamp — the scan would read it, report
    // "advisories: cache (date)", and suppress a finding that would otherwise
    // abort the build.
    $dir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/j2c-surface-' . (string) getmyuid();

    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    // Windows ignores the mode and gives every user their own temp root anyway,
    // so this only bites on POSIX — which is exactly where a shared /tmp does.
    // A pre-existing directory we do not own, or one anybody can write to, is
    // not trustworthy: return an unwritable sentinel so both the read and the
    // write miss and the scan falls back to a live fetch. Degrading to slower
    // beats trusting a cache somebody else can seed.
    if (DIRECTORY_SEPARATOR === '/' && is_dir($dir)) {
        $stat = @stat($dir);

        if ($stat === false || $stat['uid'] !== getmyuid() || ($stat['mode'] & 0022) !== 0) {
            return '';
        }
    }

    return $dir . '/advisories.json';
}

/**
 * Names that must never ship, matched on the WHOLE basename or a path segment.
 *
 * Deliberately NOT the same list as build_package.php's `$excludePatterns`.
 * That list is what the build already removes; this one is what should never
 * have been in the tree to begin with, so an overlap is a redundant safety net
 * and a non-overlap is the actual finding.
 *
 * @var array<string, array{0:string, 1:string, 2:string}>  regex => [severity, class, why]
 */
const ARTIFACT_SIGNATURES = [
    // Source-control and editor residue.
    '#\.(bak|orig|rej|swp|swo|save|tmp|old)$#i' =>
        ['HIGH', 'editor/merge residue', 'a .bak of a PHP file is served as PHP source or as plain text — either way it publishes code the release removed'],
    '#(^|/)[^/]+~$#' =>
        ['HIGH', 'editor backup', 'same exposure as a .bak, under a name the deny-list does not carry'],
    '#\.(diff|patch)$#i' =>
        ['MEDIUM', 'patch residue', 'publishes the shape of a change, and often the pre-fix code'],

    // Database material — but NOT the schema an extension is required to ship.
    // `sql/install/**` and `sql/updates/**` are how Joomla installs and upgrades
    // a component; flagging them would fire 41 times on a clean tree and teach
    // everyone to ignore this gate. Only a .sql somewhere it has no business
    // being is a finding.
    '#^(?!.*(^|/)sql/).*\.(sql|sqlite|db|dump)$#i' =>
        ['CRITICAL', 'database artifact', 'a dump outside the extension\'s sql/ schema directory is a whole-database read for anyone who guesses the name'],
    '#\.(log|err)$#i' =>
        ['HIGH', 'log artifact', 'application logs carry request parameters, ids and stack traces'],

    // Credentials and keys.
    '#(^|/)\.env#i' =>
        ['CRITICAL', 'credential file', 'ships live credentials to every install'],
    '#\.(pem|key|p12|pfx|jks|keystore)$#i' =>
        ['CRITICAL', 'private key material', 'ships a private key to every install'],
    '#(^|/)(id_rsa|id_ed25519|\.htpasswd|\.netrc|credentials\.json|service-account.*\.json)$#i' =>
        ['CRITICAL', 'credential file', 'ships live credentials to every install'],

    // Debug tooling — the classic drop-a-shell-by-accident class.
    '#(^|/)(phpinfo|info|test|debug|shell|adminer|phpunit|_test|tmp)\.php$#i' =>
        ['CRITICAL', 'debug/diagnostic endpoint', 'an unauthenticated diagnostic endpoint under a web root; phpinfo alone discloses paths, extensions and env'],
    '#(^|/)(xdebug|profiler|cachegrind)[^/]*$#i' =>
        ['HIGH', 'profiler output', 'profiler output carries absolute paths and often request data'],

    // Test material that the deny-list only catches under exactly "tests".
    '#(^|/)(test|tests|spec|specs|fixtures?|__fixtures__|cypress|e2e)/#i' =>
        ['MEDIUM', 'test material', 'fixtures routinely carry seeded credentials and sample customer data'],

    // Archives inside an extension directory.
    '#\.(zip|tar|gz|tgz|rar|7z)$#i' =>
        ['MEDIUM', 'nested archive', 'an archive inside a shipped directory is content nothing has reviewed'],

    // OS residue that carries path/user information.
    '#(^|/)(\.DS_Store|Thumbs\.db|desktop\.ini)$#i' =>
        ['LOW', 'OS residue', 'leaks local directory structure and usernames'],
];

/**
 * Vendored libraries that carry no composer metadata.
 *
 * Without a machine-readable version there is nothing for an advisory range to
 * be compared against, so these are reported as unpinned rather than silently
 * treated as fine. Recording a version here brings the entry into the normal
 * comparison.
 *
 * @var array<string, array{packagist:?string, path:string, version:?string, note:string}>
 */
const UNMANAGED_VENDORED = [
    'boxpacker' => [
        'packagist' => 'dvdoug/boxpacker',
        'path'      => 'libraries/j2commerce/vendor/dvdoug/boxpacker',
        'version'   => null,
        'note'      => 'vendored by hand; carries its own autoload.php and is absent from installed.json',
    ],
    'phpass' => [
        'packagist' => null,
        'path'      => 'libraries/phpass',
        'version'   => null,
        'note'      => 'single-file drop-in, no package metadata',
    ],
    'php-encryption' => [
        'packagist' => 'defuse/php-encryption',
        'path'      => 'libraries/php-encryption',
        'version'   => null,
        'note'      => 'single-file drop-in; not the composer package of the same name',
    ],
    'f0f' => [
        'packagist' => null,
        'path'      => 'libraries/f0f',
        'version'   => null,
        'note'      => 'FOF 2, retained for the migration; no package metadata',
    ],
];

// --- invocation -------------------------------------------------------------

$opt = static fn(string $flag): bool => in_array('--' . $flag, $argv, true);

$buildCheck = $opt('build-check');
$strict     = $opt('strict');
$json       = $opt('json');
$offline    = $opt('offline');
$refresh    = $opt('refresh');
$onlyDeps   = $opt('deps');
$onlySurf   = $opt('surface');

$root = realpath(ROOT_DIR);

if ($root === false) {
    fwrite(STDERR, "ERROR: cannot resolve repository root\n");
    exit(2);
}

$root = str_replace('\\', '/', $root);

$findings = [];

/**
 * @param string $sev  CRITICAL | HIGH | MEDIUM | LOW
 */
function finding(array &$out, string $sev, string $class, string $where, string $what, string $fix): void
{
    $out[] = [
        'severity' => $sev,
        'class'    => $class,
        'where'    => $where,
        'what'     => $what,
        'fix'      => $fix,
    ];
}

// --- ship set ---------------------------------------------------------------

/**
 * Reads the ship roots out of build_package.php rather than restating them.
 *
 * Parsed from the source, never `include`d: that file executes a whole build at
 * top level. A restated copy here would be a second list to keep in sync, and
 * the first time it drifted this gate would audit a surface the build does not
 * actually produce — silently clean on exactly the files that ship.
 *
 * @return array{roots:list<string>, excludes:list<string>}
 */
function shipDefinitions(string $root): array
{
    $src = @file_get_contents($root . '/build/build_package.php');

    if ($src === false) {
        fwrite(STDERR, "ERROR: build/build_package.php not readable — cannot determine the ship set\n");
        exit(2);
    }

    $excludes = [];
    if (preg_match('/\$excludePatterns\s*=\s*\[(.*?)\n\];/s', $src, $m)) {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $e);
        $excludes = $e[1];
    }

    if ($excludes === []) {
        fwrite(STDERR, "ERROR: could not parse \$excludePatterns from build_package.php\n");
        exit(2);
    }

    $roots = [
        'administrator/components/com_j2commerce',
        'components/com_j2commerce',
        'api/components/com_j2commerce',
        'media/com_j2commerce',
        'libraries/j2commerce',
        'libraries/j2commerceflow',
    ];

    if (preg_match('/\$plugins\s*=\s*\[(.*?)\n\];/s', $src, $m)) {
        preg_match_all('/\[\s*[\'"]group[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]element[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $p, PREG_SET_ORDER);
        foreach ($p as $hit) {
            $roots[] = 'plugins/' . $hit[1] . '/' . $hit[2];
        }
    }

    foreach (['adminModules' => 'administrator/modules', 'siteModules' => 'modules'] as $var => $dir) {
        if (preg_match('/\$' . $var . '\s*=\s*\[(.*?)\n\];/s', $src, $m)) {
            preg_match_all('/[\'"](mod_[a-z0-9_]+)[\'"]/', $m[1], $mm);
            foreach ($mm[1] as $mod) {
                $roots[] = $dir . '/' . $mod;
            }
        }
    }

    if (count($roots) < 20) {
        fwrite(STDERR, "ERROR: parsed only " . count($roots) . " ship roots — build_package.php shape changed\n");
        exit(2);
    }

    return ['roots' => $roots, 'excludes' => $excludes];
}

/** Mirrors build_package.php::shouldExclude() — same segment-wise semantics. */
function shipExcluded(string $relative, array $patterns): bool
{
    $parts = explode('/', $relative);

    foreach ($patterns as $pattern) {
        if (in_array($pattern, $parts, true) || str_contains($relative, $pattern)) {
            return true;
        }
    }

    return false;
}

/**
 * Every file the build would place in a zip, repo-relative.
 *
 * @return list<string>
 */
function predictShipSet(string $root, array $def): array
{
    $files = [];

    foreach ($def['roots'] as $rel) {
        $abs = $root . '/' . $rel;

        if (!is_dir($abs)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        // buildPluginZip() passes $excludeZips — a plugin that ships its own
        // installer payload (plugins/installer/j2commerce/j2commerce.zip) is
        // already dropped by the build, so flagging it here would be a finding
        // about a file that never reaches a package.
        $dropZips = str_starts_with($rel, 'plugins/');

        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $repo = substr($path, strlen($root) + 1);

            if (shipExcluded(substr($path, strlen($abs) + 1), $def['excludes'])) {
                continue;
            }

            if ($dropZips && strtolower($file->getExtension()) === 'zip') {
                continue;
            }

            $files[] = $repo;
        }
    }

    sort($files);

    return $files;
}

/**
 * Which of $paths .gitignore deliberately excludes.
 *
 * The distinction matters more than the untracked flag itself. A file nobody
 * tracked *and* nobody ignored is an accident — it reached an extension
 * directory and no one has ever looked at it. A file ignored ON PURPOSE that
 * the build would nonetheless bundle is a different problem: the local tree
 * carries something (a paid product type, a generated cache) that was never
 * meant to leave this machine, and the build has no idea.
 *
 * One batched `--stdin` call; a per-file `check-ignore` would be one process
 * spawn per file and this list runs to the hundreds.
 *
 * @param  list<string> $paths
 * @return array<string, true>
 */
function ignoredFiles(string $root, array $paths): array
{
    if ($paths === []) {
        return [];
    }

    $tmp = tempnam(sys_get_temp_dir(), 'j2csurf');

    if ($tmp === false) {
        return [];
    }

    // Same guard as the probe file in check_platform_packages.php: registered
    // before the write, so a fatal during exec cannot leak it. tempnam() is
    // already O_EXCL and 0600, so the stakes are lower — this is consistency,
    // not a hole.
    register_shutdown_function(static function () use ($tmp): void {
        @unlink($tmp);
    });

    file_put_contents($tmp, implode("\n", $paths) . "\n");

    $cmd = 'git -C ' . escapeshellarg($root) . ' check-ignore --stdin < ' . escapeshellarg($tmp) . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);

    @unlink($tmp);

    // Exit 1 simply means "nothing matched"; only >1 is a real failure.
    if ($code > 1) {
        return [];
    }

    $ignored = [];
    foreach ($out as $line) {
        $ignored[trim(str_replace('\\', '/', $line))] = true;
    }

    return $ignored;
}

/** @return array<string, true> */
function trackedFiles(string $root): array
{
    $out  = [];
    $cmd  = 'git -C ' . escapeshellarg($root) . ' ls-files 2>&1';
    $lines = [];
    $code  = 0;
    exec($cmd, $lines, $code);

    if ($code !== 0) {
        fwrite(STDERR, "ERROR: git ls-files failed — the surface audit needs a git worktree\n");
        exit(2);
    }

    foreach ($lines as $line) {
        $out[trim($line)] = true;
    }

    return $out;
}

// --- dependency inventory ---------------------------------------------------

/**
 * Every dependency that ships, with its version.
 *
 * Reads `vendor/composer/installed.json` rather than `composer.lock`: the lock
 * records what was *resolved*, installed.json records what is *on disk*, and it
 * is the bytes on disk that go into the zip. They disagree whenever someone
 * hand-edits a vendor tree — which is precisely the case this gate exists for.
 *
 * @return array<string, array{version:string, source:string}>
 */
function dependencyInventory(string $root, array $shipRoots): array
{
    $inventory = [];

    // Joomla's own libraries/vendor and the repo-root vendor/ are the CMS's and
    // the dev toolchain's — neither ships in a J2Commerce package.
    $skip = ['#^libraries/vendor/#', '#^vendor/#', '#/node_modules/#', '#^\.claude/#', '#^administrator/components/com_(?!j2commerce)#'];

    foreach ($shipRoots as $rel) {
        $abs = $root . '/' . $rel;

        if (!is_dir($abs)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->getFilename() !== 'installed.json') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (!str_contains($path, '/vendor/composer/')) {
                continue;
            }

            $repo = substr($path, strlen($root) + 1);

            foreach ($skip as $re) {
                if (preg_match($re, $repo)) {
                    continue 2;
                }
            }

            $data = json_decode((string) file_get_contents($path), true);

            foreach (($data['packages'] ?? $data ?? []) as $pkg) {
                if (!isset($pkg['name'], $pkg['version'])) {
                    continue;
                }

                // First writer wins, so the report names one representative
                // path per package rather than N copies of the same advisory.
                $inventory[$pkg['name']] ??= ['version' => $pkg['version'], 'source' => $repo];
            }
        }
    }

    ksort($inventory);

    return $inventory;
}

// --- advisory matching ------------------------------------------------------

/** Strips the decoration Packagist and composer disagree about. */
function normaliseVersion(string $v): string
{
    $v = ltrim(trim($v), 'vV');
    $v = preg_replace('/[+@].*$/', '', $v) ?? $v;

    return $v;
}

/**
 * True when $version satisfies one Composer-style constraint clause.
 *
 * Packagist's `affectedVersions` is `|`-separated OR of `,`-separated AND, with
 * plain `<`, `<=`, `>`, `>=`, `!=`, `==` operators — no carets, no tildes. That
 * is the entire grammar this has to handle, so it handles exactly that and
 * nothing more.
 */
function satisfiesClause(string $version, string $clause): bool
{
    $clause = trim($clause);

    if ($clause === '' || $clause === '*') {
        return true;
    }

    if (!preg_match('/^(>=|<=|!=|==|=|>|<)?\s*(.+)$/', $clause, $m)) {
        return false;
    }

    $op     = $m[1] === '' || $m[1] === '=' ? '==' : $m[1];
    $target = normaliseVersion($m[2]);

    return version_compare($version, $target, $op);
}

function versionIsAffected(string $version, string $affected): bool
{
    $version  = normaliseVersion($version);
    $affected = trim($affected);

    // No range at all is missing data, not "affects everything". Treating it as
    // a match would raise a finding on every package the moment Packagist
    // omitted the field.
    if ($affected === '') {
        return false;
    }

    // A branch alias (dev-main, dev-master) has no comparable ordering. Treating
    // it as unaffected would be a silent pass on the least-pinned dependency in
    // the tree, so it is reported separately as unpinned instead.
    if (str_starts_with($version, 'dev-') || $version === '') {
        return false;
    }

    foreach (explode('|', $affected) as $orGroup) {
        $all = true;

        foreach (explode(',', $orGroup) as $clause) {
            if (!satisfiesClause($version, $clause)) {
                $all = false;
                break;
            }
        }

        if ($all) {
            return true;
        }
    }

    return false;
}

/**
 * Fetches advisories for the whole package set in one request, cached by the
 * sha256 of that set.
 *
 * One batched call, not one per package: the gate runs on every build, and N
 * sequential HTTPS round-trips is the difference between a gate people keep and
 * a gate people pass `--offline` to forever.
 *
 * @param  list<string> $packages
 * @return array{advisories:array<string, array>, source:string}
 */
function fetchAdvisories(array $packages, bool $offline, bool $refresh): array
{
    sort($packages);
    $key = hash('sha256', implode("\n", $packages));

    $cache = [];
    if (is_file(cacheFile())) {
        $cache = json_decode((string) file_get_contents(cacheFile()), true) ?: [];
    }

    if (!$refresh && isset($cache[$key]) && (time() - ($cache[$key]['fetched'] ?? 0)) < CACHE_TTL) {
        return ['advisories' => $cache[$key]['advisories'], 'source' => 'cache (' . date('Y-m-d H:i', $cache[$key]['fetched']) . ')'];
    }

    if ($offline) {
        // A stale cache still beats no data — say so rather than reporting clean.
        if (isset($cache[$key])) {
            return ['advisories' => $cache[$key]['advisories'], 'source' => 'STALE cache (' . date('Y-m-d', $cache[$key]['fetched']) . '), --offline'];
        }

        return ['advisories' => [], 'source' => 'UNAVAILABLE — --offline and no cache'];
    }

    $query = [];
    foreach ($packages as $p) {
        $query[] = 'packages%5B%5D=' . rawurlencode($p);
    }

    $url = 'https://packagist.org/api/security-advisories/?' . implode('&', $query);
    $ctx = stream_context_create(['http' => [
        'timeout'         => 20,
        'follow_location' => true,
        'header'          => "User-Agent: j2commerce-package-surface-check\r\n",
    ]]);

    $body = @file_get_contents($url, false, $ctx);

    if ($body === false) {
        if (isset($cache[$key])) {
            return ['advisories' => $cache[$key]['advisories'], 'source' => 'STALE cache — packagist.org unreachable'];
        }

        return ['advisories' => [], 'source' => 'UNAVAILABLE — packagist.org unreachable'];
    }

    $decoded   = json_decode($body, true);
    $advisories = $decoded['advisories'] ?? [];

    $cache[$key] = ['fetched' => time(), 'advisories' => $advisories];

    $dir = dirname(cacheFile());
    if (is_dir($dir)) {
        @file_put_contents(cacheFile(), json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    return ['advisories' => $advisories, 'source' => 'packagist.org (live)'];
}

// --- duplicates of core-provided packages -----------------------------------

/**
 * Packages Joomla core itself ships and registers at boot.
 *
 * Core's Composer autoloader is registered during framework bootstrap and
 * carries a full CLASSMAP as well as PSR-4 prefixes. A classmap is consulted
 * before any prefix, so for any package in this list core's copy wins the whole
 * request — no matter what an extension registers afterwards.
 *
 * A second copy of one of these therefore cannot execute. It is shipped bytes
 * that never run, and worse, it is a version number that looks live: it shows up
 * in audits, it drifts, and someone eventually "fixes" a defect by patching the
 * copy nothing loads. That is not hypothetical — it is exactly how phpseclib
 * came to sit in three places in this project at three versions.
 *
 * @return array<string, string>  package name => core's version
 */
function coreProvidedPackages(string $root): array
{
    $file = $root . '/libraries/vendor/composer/installed.json';

    if (!is_file($file)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($file), true);
    $out  = [];

    foreach (($data['packages'] ?? $data ?? []) as $pkg) {
        if (isset($pkg['name'], $pkg['version'])) {
            $out[$pkg['name']] = $pkg['version'];
        }
    }

    return $out;
}

/**
 * Every package vendored anywhere under first-party code.
 *
 * Deliberately NOT limited to the core ship set: a duplicate in a non-core
 * plugin is the same defect and is the case that actually occurred. Detection is
 * by path shape — `**​/vendor/<vendor>/<package>/` — because the offending copies
 * are precisely the ones with no composer metadata to read.
 *
 * @return array<string, string>  package name => repo-relative path
 */
function vendoredPackages(string $root): array
{
    // Walked on DISK, not through `git ls-files`, for two reasons: a duplicate
    // in a non-core plugin is not tracked by this repository at all (that is the
    // case that actually occurred, in payment_amazonpay), and git keeps listing
    // a tracked file after it has been deleted, which would keep reporting a
    // duplicate that was correctly removed. This check answers "what is
    // installed", and only the filesystem knows that.
    // Fixed-depth globs rather than a recursive walk. Walking every extension
    // tree took ~19s because it descends into each vendored package's own
    // sources; these patterns stop at `vendor/<vendor>/<package>` and run in
    // well under a second. The shapes cover where extensions actually put a
    // vendor tree in this project: at the extension root, under lib/, and under
    // library/.
    $patterns = [
        'plugins/*/*/vendor/*/*',
        'plugins/*/*/lib/vendor/*/*',
        'plugins/*/*/library/vendor/*/*',
        'plugins/*/*/lib/*/vendor/*/*',
        'modules/*/vendor/*/*',
        'modules/*/lib/vendor/*/*',
        'administrator/modules/*/vendor/*/*',
        'administrator/components/*/vendor/*/*',
        'components/*/vendor/*/*',
        'libraries/*/vendor/*/*',
        'libraries/*/*/vendor/*/*',
    ];

    $found = [];

    foreach ($patterns as $pattern) {
        foreach (glob($root . '/' . $pattern, GLOB_ONLYDIR | GLOB_NOSORT) ?: [] as $abs) {
            $rel = substr(str_replace('\\', '/', $abs), strlen($root) + 1);

            // Core's own vendor tree is the baseline, never a duplicate of itself.
            if (str_starts_with($rel, 'libraries/vendor/')) {
                continue;
            }

            if (!preg_match('#(?:^|/)vendor/([a-z0-9._-]+)/([a-z0-9._-]+)$#i', $rel, $m)) {
                continue;
            }

            // composer/ and bin/ under a vendor dir are scaffolding, not packages.
            if ($m[1] === 'composer' || $m[1] === 'bin') {
                continue;
            }

            $found[$m[1] . '/' . $m[2]] ??= $rel;
        }
    }

    ksort($found);

    return $found;
}

// --- self-test --------------------------------------------------------------

/**
 * Asserts the advisory range matcher against real Packagist strings.
 *
 * This is the only piece of logic here that can fail silently in the dangerous
 * direction: a range it fails to parse reports the dependency CLEAN. The cases
 * below are copied verbatim from live packagist.org responses for the packages
 * this repository actually vendors.
 */
if ($opt('self-test')) {
    $cases = [
        // [version, affectedVersions, expected]
        ['3.0.50', '>=0.1.1,<=1.0.28|>=3.0.0,<=3.0.51|>=2.0.0,<=2.0.53', true],
        ['3.0.52', '>=0.1.1,<=1.0.28|>=3.0.0,<=3.0.51|>=2.0.0,<=2.0.53', false],
        ['3.0.50', '>=3.0.0,<=3.0.53|>=2.0.0,<=2.0.54|>=0.1.1,<=1.0.29', true],
        ['v3.1.5', '<3.1.6', true],
        ['v3.1.6', '<3.1.6', false],
        ['3.1.7', '<3.1.6', false],
        // A leading `v` on either side must not change the verdict.
        ['2.7.0', '<=v2.7.0', true],
        // An OR group where only the second clause matches.
        ['1.0.29', '>=3.0.0,<=3.0.53|>=0.1.1,<=1.0.29', true],
        // Branch refs are never "unaffected" by accident — they are reported
        // separately as unpinned, so the matcher must return false here.
        ['dev-main', '<99.0.0', false],
        // An empty range must not match everything.
        ['1.0.0', '', false],
    ];

    $failed = 0;

    foreach ($cases as [$version, $range, $expected]) {
        $got = versionIsAffected($version, $range);

        if ($got !== $expected) {
            $failed++;
            echo "FAIL  {$version} vs '{$range}' — expected " . var_export($expected, true) . ", got " . var_export($got, true) . "\n";
        }
    }

    echo $failed === 0
        ? 'self-test OK (' . count($cases) . " cases)\n"
        : "self-test FAILED ({$failed} of " . count($cases) . ")\n";

    exit($failed === 0 ? 0 : 1);
}

// --- run --------------------------------------------------------------------

$def       = shipDefinitions($root);
$advSource = 'not run';

if (!$onlyDeps) {
    $shipSet = predictShipSet($root, $def);
    $tracked = trackedFiles($root);

    $untracked = array_values(array_filter($shipSet, static fn($rel) => !isset($tracked[$rel])));
    $ignored   = ignoredFiles($root, $untracked);

    // Ignored-but-shipping rolls up by directory. Reporting 158 separate lines
    // for one gitignore rule buries every other finding in the report, and the
    // decision an operator makes is per-rule anyway, never per-file.
    $ignoredDirs = [];

    foreach ($untracked as $rel) {
        if (isset($ignored[$rel])) {
            $ignoredDirs[dirname($rel)][] = $rel;
            continue;
        }

        finding(
            $findings,
            'HIGH',
            'untracked file in the ship set',
            $rel,
            'the build would place this in a zip, but git neither tracks nor ignores it — nothing has ever reviewed it, and it is in no release the repository can reproduce',
            'commit it if it belongs in the release, delete it if it does not, or add its name to $excludePatterns in build/build_package.php'
        );
    }

    ksort($ignoredDirs);

    foreach ($ignoredDirs as $dir => $members) {
        finding(
            $findings,
            'MEDIUM',
            'gitignored file inside the ship set',
            $dir . '/  (' . count($members) . ' file' . (count($members) === 1 ? '' : 's') . ', e.g. ' . basename($members[0]) . ')',
            'deliberately excluded from the repository, yet the build walks the filesystem and would bundle it — the package would carry content the repository has no record of',
            'if this is generated or machine-local, add it to $excludePatterns in build/build_package.php; if it is a separately-licensed extension, confirm it belongs in THIS package before shipping'
        );
    }

    foreach ($shipSet as $rel) {
        foreach (ARTIFACT_SIGNATURES as $re => [$sev, $class, $why]) {
            if (preg_match($re, $rel)) {
                finding($findings, $sev, $class, $rel, $why, 'remove it from the extension directory, or exclude it in build/build_package.php');
                break;
            }
        }

        // Executable PHP under a media root. media/ is the one tree Joomla
        // serves directly and the one an upload can land in, so a .php there is
        // both a served endpoint and a plausible drop target.
        if (str_starts_with($rel, 'media/') && str_ends_with($rel, '.php') && basename($rel) !== 'index.php') {
            finding(
                $findings,
                'CRITICAL',
                'executable PHP under a web-served media root',
                $rel,
                'media/ is served directly and is the tree uploads land in; a PHP file here is a reachable endpoint, and its presence proves the directory executes PHP at all',
                'move the logic into the extension\'s src/ tree and leave media/ to assets, or confirm this is an intentional entry point and record why'
            );
        }
    }
}

// Duplicates of packages Joomla core already provides. Runs on both modes: this
// is a shipped-artifact defect AND a dependency-hygiene one, and skipping it
// under --surface would hide the case it was written for.
$coreProvided = coreProvidedPackages($root);

// libraries/vendor is Joomla's, not ours, so a standalone checkout of this
// repository has no platform inventory to compare against. Say so: a silent skip
// here reads exactly like "no duplicates found", which is the one wrong
// conclusion this check exists to prevent.
if ($coreProvided === []) {
    finding(
        $findings,
        'MEDIUM',
        'duplicate check did not run',
        'libraries/vendor/composer/installed.json',
        'no platform package inventory was found, so nothing could be compared against it. That file is Joomla\'s and is absent from a standalone checkout — expected there, but it means this check found nothing rather than confirming nothing is wrong',
        'run this where the extensions are deployed into a Joomla install, which is also where packaging happens'
    );
}

foreach (vendoredPackages($root) as $pkg => $where) {
    if (!isset($coreProvided[$pkg])) {
        continue;
    }

    finding(
        $findings,
        'HIGH',
        'duplicate of a core-provided package',
        $where . ' — ' . $pkg,
        'Joomla core ships ' . $pkg . ' ' . $coreProvided[$pkg] . ' and registers it at boot with a classmap, which is consulted before any PSR-4 prefix. Core\'s copy therefore wins the whole request and this one can never execute — it ships to every merchant as dead bytes, while presenting a version number that looks live and drifts',
        'remove this copy and use core\'s. If a specific version is genuinely required, that constraint cannot be met by vendoring a second copy — it has to be raised with Joomla core, or the consumer has to load its copy under a different namespace'
    );
}

$inventory = [];

if (!$onlySurf) {
    $inventory = dependencyInventory($root, $def['roots']);

    foreach (UNMANAGED_VENDORED as $name => $meta) {
        if (!is_dir($root . '/' . $meta['path'])) {
            continue;
        }

        if ($meta['version'] === null) {
            finding(
                $findings,
                'HIGH',
                'unpinned vendored dependency',
                $meta['path'],
                'ships with no readable version (' . $meta['note'] . '), so there is nothing for an advisory range to be compared against and this entry cannot be evaluated either way',
                'pin the exact upstream version in UNMANAGED_VENDORED in this file (or re-vendor it through composer so installed.json carries it)'
            );

            continue;
        }

        if ($meta['packagist'] !== null) {
            $inventory[$meta['packagist']] ??= ['version' => $meta['version'], 'source' => $meta['path'] . ' (pinned by hand)'];
        }
    }

    foreach ($inventory as $name => $meta) {
        if (str_starts_with(normaliseVersion($meta['version']), 'dev-')) {
            finding(
                $findings,
                'MEDIUM',
                'unpinned dependency version',
                $meta['source'] . ' — ' . $name,
                'installed at "' . $meta['version'] . '", a moving branch reference with no comparable ordering, so no advisory range can ever match it',
                'pin to a released tag so the advisory scan can evaluate it'
            );
        }
    }

    if ($inventory !== []) {
        $result    = fetchAdvisories(array_keys($inventory), $offline, $refresh);
        $advSource = $result['source'];

        if (str_contains($advSource, 'UNAVAILABLE')) {
            finding(
                $findings,
                'MEDIUM',
                'advisory feed unavailable',
                'packagist.org',
                'the dependency scan could not run — ' . $advSource . '. A clean dependency section in this report proves nothing',
                're-run with network access, or accept the build knowing dependencies went unchecked and say so in the release notes'
            );
        }

        foreach ($result['advisories'] as $pkg => $list) {
            if (!isset($inventory[$pkg])) {
                continue;
            }

            $version = $inventory[$pkg]['version'];

            foreach ($list as $adv) {
                if (!versionIsAffected($version, (string) ($adv['affectedVersions'] ?? ''))) {
                    continue;
                }

                $sev = match (strtolower((string) ($adv['severity'] ?? ''))) {
                    'critical' => 'CRITICAL',
                    'high'     => 'HIGH',
                    'medium'   => 'MEDIUM',
                    default    => 'LOW',
                };

                finding(
                    $findings,
                    $sev,
                    'known-vulnerable dependency',
                    $inventory[$pkg]['source'] . ' — ' . $pkg . ' ' . $version,
                    ($adv['cve'] ?: $adv['advisoryId'] ?? '?') . ': ' . ($adv['title'] ?? 'no title') . ' (affects ' . ($adv['affectedVersions'] ?? '?') . ')',
                    'update ' . $pkg . ' past the affected range and re-run this gate'
                );
            }
        }
    }
}

// --- report -----------------------------------------------------------------

$order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
usort($findings, static fn($a, $b) => [$order[$a['severity']], $a['where']] <=> [$order[$b['severity']], $b['where']]);

$counts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
foreach ($findings as $f) {
    $counts[$f['severity']]++;
}

$abort = $counts['CRITICAL'] > 0 || ($strict && $counts['HIGH'] > 0);

if ($json) {
    echo json_encode([
        'findings'        => $findings,
        'counts'          => $counts,
        'dependencies'    => $inventory,
        'advisory_source' => $advSource,
        'abort'           => $abort,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit($abort ? 1 : 0);
}

if ($buildCheck) {
    if ($findings === []) {
        echo "Package surface check OK (" . count($inventory) . " deps, advisories: {$advSource})\n";
        exit(0);
    }

    echo "\nPackage surface check: {$counts['CRITICAL']} CRITICAL, {$counts['HIGH']} HIGH, {$counts['MEDIUM']} MEDIUM, {$counts['LOW']} LOW\n";
    echo "  advisories: {$advSource}\n";

    foreach ($findings as $f) {
        if ($f['severity'] === 'CRITICAL' || $f['severity'] === 'HIGH') {
            echo "  [{$f['severity']}] {$f['where']} — {$f['class']}\n";
        }
    }

    if ($counts['MEDIUM'] + $counts['LOW'] > 0) {
        echo "  (" . ($counts['MEDIUM'] + $counts['LOW']) . " lower-severity — run `php build/check_package_surface.php` for the full report)\n";
    }

    if ($abort) {
        echo "\nERROR: refusing to build. Resolve the above, or re-run without --strict if only HIGH.\n\n";
        exit(1);
    }

    echo "\n";
    exit(0);
}

echo "\nPackage Surface Audit\n";
echo str_repeat('─', 78) . "\n";
echo "Dependencies inventoried: " . count($inventory) . "\n";
echo "Advisory source:          {$advSource}\n";
echo "Findings:                 {$counts['CRITICAL']} CRITICAL, {$counts['HIGH']} HIGH, {$counts['MEDIUM']} MEDIUM, {$counts['LOW']} LOW\n\n";

if ($findings === []) {
    echo "Nothing shipped that git does not track, no artifact signatures, no matching advisories.\n\n";
    exit(0);
}

$current = null;
foreach ($findings as $f) {
    if ($f['severity'] !== $current) {
        $current = $f['severity'];
        echo "\n" . $current . "\n" . str_repeat('─', strlen($current)) . "\n";
    }

    echo "  {$f['where']}\n";
    echo "    class: {$f['class']}\n";
    echo "    why:   {$f['what']}\n";
    echo "    fix:   {$f['fix']}\n\n";
}

if ($abort) {
    echo "ABORT: CRITICAL findings present.\n\n";
}

exit($abort ? 1 : 0);
