#!/usr/bin/env php
<?php

/**
 * J2Commerce translated-locale integrity checker.
 *
 * Language values are rendered straight into markup — some into element text,
 * some into `aria-label` and `title` attributes, some into a `<script>` block
 * via `Text::script()`. en-US is written here and reviewed; every other locale
 * is machine-translated from it and lands 40 files at a time. That is the gap
 * this closes: a translated value is untrusted input that happens to have been
 * produced upstream, and nothing until now compared it against its source.
 *
 * The invariant is NOT "no markup in language files" — core en-US legitimately
 * ships `<br>` and `<strong>`. It is narrower and checkable:
 *
 *   a translated locale may not carry markup, or format specifiers, that its
 *   en-US counterpart does not.
 *
 * Plus a small set of patterns that are never legitimate in any locale,
 * including en-US.
 *
 * What it checks, per key, against the en-US value for the same key:
 *   1. never-legitimate patterns: <script, javascript:, data:text/html,
 *      an on*= event handler, </ inside a value bound for Text::script()
 *   2. HTML tag escalation — a tag name the source does not use
 *   3. format-specifier count (%s, %d, %1$s …) — a locale with more specifiers
 *      than arguments is an ArgumentCountError on PHP 8, not a cosmetic slip
 *   4. structural: parse failure, embedded newline, BOM, invalid UTF-8
 *
 * Keys absent from en-US are reported but not failed: a locale legitimately
 * lags the source between a string being added and the next translation run.
 *
 * Usage:
 *   php build/check_language_integrity.php
 *   php build/check_language_integrity.php --json
 *   php build/check_language_integrity.php --path=plugins/j2commerce/app_socialmedia
 *   php build/check_language_integrity.php --all      # core Joomla locales too
 *
 * Exit codes:
 *   0  clean
 *   1  at least one integrity violation
 *   2  bad invocation
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/cli_guard.php';
requireCli();

$root = realpath(__DIR__ . '/..');

if ($root === false) {
    fwrite(STDERR, "ERROR: cannot resolve repository root\n");
    exit(2);
}

$root  = str_replace('\\', '/', $root);
$json  = \in_array('--json', $argv, true);
$scope = null;
$all   = \in_array('--all', $argv, true);

foreach ($argv as $a) {
    if (str_starts_with($a, '--path=')) {
        $scope = trim(str_replace('\\', '/', substr($a, 7)), '/');
    }
}

// --- discovery: every en-US .ini on disk is a source; its siblings are targets
//
// A filesystem walk, NOT `git ls-files`: non-core extensions are gitignored in
// the Joomla working tree, so a git-based scan silently sees none of them and
// reports a clean run over zero files. That false green is the exact failure
// this script exists to prevent, so it must not be the way the script finds its
// own input.

// Nested working copies of this same repo (tooling scratch checkouts) must be
// skipped: scanning them reports every finding N+1 times and pins the run to
// whatever half-finished state the nested copy is in.
$skip = ['/node_modules/', '/vendor/', '/.git/', '/.claude/', '/docs/', '/tmp/', '/cache/', '/build/'];

$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function ($file, $key, $iter) use ($skip): bool {
            $p = str_replace('\\', '/', $file->getPathname()) . ($file->isDir() ? '/' : '');

            foreach ($skip as $s) {
                if (str_contains($p, $s)) {
                    return false;
                }
            }

            return true;
        }
    ),
    RecursiveIteratorIterator::SELF_FIRST
);

$sources = [];

foreach ($it as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'ini') {
        continue;
    }

    $abs = str_replace('\\', '/', $file->getPathname());
    $rel = ltrim(substr($abs, \strlen($root)), '/');

    if (!str_contains($rel, '/language/en-US/')) {
        continue;
    }

    if ($scope !== null && !str_starts_with($rel, $scope)) {
        continue;
    }

    // Default to the J2Commerce footprint. The tree this runs in is a whole
    // Joomla install, and core Joomla's own locales are neither ours to fix nor
    // ours to gate on - com_banners' plural forms alone produce hundreds of
    // legitimate specifier differences, which would bury a real finding.
    // --all opts into the entire install.
    if ($scope === null && !$all && !preg_match('#(^|/)(com_j2commerce|lib_j2commerce|j2commerce[a-z0-9_]*|plg_j2commerce[a-z0-9_]*|mod_j2commerce[a-z0-9_]*)[./]#', $rel)) {
        continue;
    }

    $sources[] = $rel;
}

sort($sources);

// An empty scan is a broken invocation, never a pass. Without this the script's
// happiest output is the one it produces when it is looking in the wrong place.
if ($sources === []) {
    fwrite(STDERR, "ERROR: no en-US language files found"
        . ($scope !== null ? " under '{$scope}'" : ' in ' . $root)
        . " - check the path
");
    exit(2);
}

// --- helpers ----------------------------------------------------------------

/** Parse an INI the way Joomla does, so a file that fails here fails there too. */
$parse = static function (string $abs): array|false {
    $raw = @file_get_contents($abs);

    if ($raw === false) {
        return false;
    }

    return @parse_ini_string($raw, false, INI_SCANNER_RAW) ?: false;
};

// Only real HTML tag names count as markup. Language strings legitimately use
// angle brackets for illustrative placeholders - app_marketplace's en-US says
// "Sold by <seller>", which translators correctly localise to <verkoper>,
// <vendeur>, <saelger>. Treating any <word> as a tag reported all of those as
// injected markup. Worse, a tag-name pattern of [a-zA-Z][a-zA-Z0-9]* stops at
// the first non-ASCII letter, so <saelger> with its ae ligature matched as the
// strikethrough tag <s> - a finding invented entirely by the regex.
const HTML_TAGS = [
    'a', 'abbr', 'audio', 'b', 'base', 'blockquote', 'br', 'button', 'code',
    'col', 'div', 'em', 'embed', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'hr', 'i', 'iframe', 'img', 'input', 'label', 'li', 'link', 'meta',
    'object', 'ol', 'option', 'p', 'pre', 's', 'script', 'select', 'small',
    'source', 'span', 'strong', 'style', 'sub', 'sup', 'svg', 'table', 'tbody',
    'td', 'textarea', 'th', 'thead', 'tr', 'u', 'ul', 'video',
];

/** Lowercased real HTML tag names used in a value. */
$tags = static function (string $v): array {
    // \pL keeps a non-ASCII tag name whole so it fails the allow-list as one
    // token, instead of being truncated into a short name that passes it.
    preg_match_all('#</?\s*([\pL][\pL\pN]*)#u', $v, $m);

    $found = array_map('strtolower', $m[1]);

    return array_values(array_unique(array_intersect($found, HTML_TAGS)));
};

/** printf-style specifier count, numbered or not. */
$specs = static function (string $v): int {
    return preg_match_all('/%(?:\d+\$)?[bcdeEfFgGosuxX]/', $v);
};

$never = [
    '<script'        => 'contains <script',
    'javascript:'    => 'contains a javascript: URL',
    'data:text/html' => 'contains a data:text/html URL',
];

// --- scan -------------------------------------------------------------------

$violations = [];
$filesRead  = 0;
$pairs      = 0;

foreach ($sources as $srcRel) {
    $srcAbs = $root . '/' . $srcRel;
    $srcMap = $parse($srcAbs);

    if ($srcMap === false) {
        $violations[] = ['file' => $srcRel, 'key' => '', 'rule' => 'parse', 'detail' => 'en-US source does not parse'];
        continue;
    }

    $filesRead++;
    $langDir = \dirname(\dirname($srcRel));            // …/language
    $base    = basename($srcRel);

    foreach (glob($root . '/' . $langDir . '/*', GLOB_ONLYDIR) ?: [] as $localeDir) {
        $tag = basename($localeDir);

        if ($tag === 'en-US') {
            continue;
        }

        $tgtRel = $langDir . '/' . $tag . '/' . $base;
        $tgtAbs = $root . '/' . $tgtRel;

        if (!is_file($tgtAbs)) {
            continue;
        }

        $pairs++;
        $raw = (string) @file_get_contents($tgtAbs);

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $violations[] = ['file' => $tgtRel, 'key' => '', 'rule' => 'bom', 'detail' => 'file starts with a UTF-8 BOM'];
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $violations[] = ['file' => $tgtRel, 'key' => '', 'rule' => 'encoding', 'detail' => 'not valid UTF-8'];
        }

        $tgtMap = $parse($tgtAbs);

        if ($tgtMap === false) {
            $violations[] = ['file' => $tgtRel, 'key' => '', 'rule' => 'parse', 'detail' => 'does not parse as INI'];
            continue;
        }

        foreach ($tgtMap as $key => $value) {
            $value = (string) $value;

            // A key the source no longer has is stale, not dangerous — but it is
            // also not checkable against anything, so it is reported and skipped.
            if (!\array_key_exists($key, $srcMap)) {
                $violations[] = ['file' => $tgtRel, 'key' => $key, 'rule' => 'orphan', 'detail' => 'key not present in en-US', 'warn' => true];
                continue;
            }

            $srcValue = (string) $srcMap[$key];
            $lower    = strtolower($value);

            foreach ($never as $needle => $label) {
                if (str_contains($lower, $needle)) {
                    $violations[] = ['file' => $tgtRel, 'key' => $key, 'rule' => 'unsafe', 'detail' => $label];
                }
            }

            if (preg_match('/\son[a-z]+\s*=/i', $value)) {
                $violations[] = ['file' => $tgtRel, 'key' => $key, 'rule' => 'unsafe', 'detail' => 'contains an on*= event handler'];
            }

            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                $violations[] = ['file' => $tgtRel, 'key' => $key, 'rule' => 'newline', 'detail' => 'value contains a line break'];
            }

            $extraTags = array_diff($tags($value), $tags($srcValue));

            if ($extraTags !== []) {
                $violations[] = [
                    'file'   => $tgtRel,
                    'key'    => $key,
                    'rule'   => 'markup',
                    'detail' => 'introduces markup the en-US source does not have: <' . implode('>, <', $extraTags) . '>',
                ];
            }

            $sN = $specs($srcValue);
            $tN = $specs($value);

            if ($sN !== $tN) {
                $violations[] = [
                    'file'   => $tgtRel,
                    'key'    => $key,
                    'rule'   => 'placeholder',
                    'detail' => "format specifiers: en-US has {$sN}, this locale has {$tN}",
                ];
            }
        }
    }
}

// --- report -----------------------------------------------------------------

$hard = array_values(array_filter($violations, static fn (array $v): bool => empty($v['warn'])));
$warn = array_values(array_filter($violations, static fn (array $v): bool => !empty($v['warn'])));

if ($json) {
    echo json_encode([
        'sources'    => $filesRead,
        'pairs'      => $pairs,
        'violations' => $hard,
        'warnings'   => $warn,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

    exit($hard === [] ? 0 : 1);
}

printf("scanned %d en-US source file(s), %d locale file(s) against them\n\n", $filesRead, $pairs);

if ($hard === []) {
    echo "OK: no locale introduces markup, specifiers or unsafe content beyond its en-US source\n";
} else {
    foreach ($hard as $v) {
        printf("  %-10s %s%s\n             %s\n", $v['rule'], $v['file'], $v['key'] !== '' ? ' :: ' . $v['key'] : '', $v['detail']);
    }

    printf("\n%d violation(s)\n", \count($hard));
}

if ($warn !== []) {
    printf("\n%d warning(s) (not failing the build):\n", \count($warn));

    foreach (\array_slice($warn, 0, 20) as $v) {
        printf("  %-10s %s :: %s — %s\n", $v['rule'], $v['file'], $v['key'], $v['detail']);
    }

    if (\count($warn) > 20) {
        printf("  … and %d more\n", \count($warn) - 20);
    }
}

exit($hard === [] ? 0 : 1);
