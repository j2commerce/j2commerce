#!/usr/bin/env php
<?php

/**
 * J2Commerce en-US canonical-source consistency checker.
 *
 * en-US is canonical here. en-GB is GENERATED from it by running
 * build/language/us_to_gb_dictionary.php over the source strings, and the other
 * locales are translated with en-US as their context.
 *
 * That makes a Britishism typed directly into an en-US string worse than a
 * cosmetic slip. The US→GB replacement table is keyed on the AMERICAN form, so
 * it cannot fire on a word that is already British — "Colour" is not a key, so
 * nothing rewrites it, nothing flags it, and it survives untouched into the
 * generated en-GB file *and* into the source context every other locale is
 * translated from. One wrong word in one canonical string propagates outward
 * silently, across 19 locales, with no error anywhere.
 *
 * This runs the dictionary in reverse: it flags an en-US value that contains a
 * British TARGET form.
 *
 * Usage:
 *   php build/check_enus_consistency.php
 *   php build/check_enus_consistency.php --json
 *   php build/check_enus_consistency.php --strict     # exit 1 on ambiguous hits too
 *   php build/check_enus_consistency.php --path=plugins/j2commerce/payment_stripe
 *
 * Exit codes:
 *   0  clean (or only ambiguous hits, without --strict)
 *   1  at least one unambiguous Britishism in an en-US source string
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

$root   = str_replace('\\', '/', $root);
$json   = in_array('--json', $argv, true);
$strict = in_array('--strict', $argv, true);
$scope  = null;

foreach ($argv as $a) {
    if (str_starts_with($a, '--path=')) {
        $scope = trim(str_replace('\\', '/', substr($a, 7)), '/');
    }
}

/**
 * British forms that are ALSO standard American English.
 *
 * Flagging these is how a checker like this gets switched off. Each is reported
 * as ambiguous — visible, but never a failure without `--strict`.
 *
 * - dialogue / analogue / prologue / monologue: standard US for the *discourse*
 *   sense. Only the computing "dialog box" sense is US-clipped.
 * - grey, ageing, judgement, acknowledgement: accepted US variants.
 * - centre, theatre, calibre, labour: appear in proper nouns and brand names.
 * - basket, current account: ordinary US words outside the retail sense.
 */
const AMBIGUOUS = [
    'dialogue', 'analogue', 'prologue', 'monologue',
    'grey', 'ageing', 'judgement', 'acknowledgement',
    'centre', 'theatre', 'calibre', 'labour', 'sombre',
    'basket', 'current account', 'metre', 'litre', 'fibre',
];

$dict = require $root . '/build/language/us_to_gb_dictionary.php';

if (!is_array($dict) || empty($dict['exact'])) {
    fwrite(STDERR, "ERROR: could not read build/language/us_to_gb_dictionary.php\n");
    exit(2);
}

$skip = array_map('strtolower', $dict['skip'] ?? []);

/**
 * Reverse map: british form => the American form it should have been.
 *
 * Built from the dictionary itself, so it can never drift from the table that
 * generates en-GB. An entry whose British form is identical to the American one
 * (a `skip`-style no-op) carries no information and is dropped.
 */
$reverse = [];

foreach ($dict['exact'] as $us => $gb) {
    if (strcasecmp($us, $gb) === 0) {
        continue;
    }

    $key = strtolower($gb);

    if (in_array($key, $skip, true)) {
        continue;
    }

    // First US spelling wins; the table lists case variants of the same word.
    $reverse[$key] ??= ['gb' => $gb, 'us' => $us];
}

// Longest first, so "shopping basket" is reported instead of bare "basket".
uksort($reverse, static fn($a, $b) => strlen($b) <=> strlen($a));

// --- scan -------------------------------------------------------------------

$cmd  = 'git -C ' . escapeshellarg($root) . ' ls-files "*.ini" 2>&1';
$out  = [];
$code = 0;
exec($cmd, $out, $code);

if ($code !== 0) {
    fwrite(STDERR, "ERROR: git ls-files failed\n");
    exit(2);
}

$files = [];

foreach ($out as $line) {
    $rel = trim(str_replace('\\', '/', $line));

    if (!str_contains($rel, '/language/en-US/')) {
        continue;
    }

    if ($scope !== null && !str_starts_with($rel, $scope)) {
        continue;
    }

    $files[] = $rel;
}

sort($files);

$findings = [];
$scanned  = 0;
$strings  = 0;

foreach ($files as $rel) {
    $abs = $root . '/' . $rel;

    if (!is_file($abs)) {
        continue;
    }

    $scanned++;
    $lines = preg_split('/\R/', (string) file_get_contents($abs)) ?: [];

    foreach ($lines as $i => $line) {
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, ';') || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!preg_match('/^([A-Z0-9_]+)\s*=\s*"(.*)"\s*$/', $trimmed, $m)) {
            continue;
        }

        [, $key, $value] = $m;
        $strings++;

        $seen = [];

        foreach ($reverse as $needle => $meta) {
            // Word-boundary match so "colour" fires but "Colours" inside a URL
            // segment or a longer token does not double-report.
            if (!preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $value, $hit, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // A longer phrase already covering this offset wins.
            $offset = $hit[0][1];
            foreach ($seen as [$s, $e]) {
                if ($offset >= $s && $offset < $e) {
                    continue 2;
                }
            }

            $seen[] = [$offset, $offset + strlen($needle)];

            $findings[] = [
                'ambiguous' => in_array($needle, AMBIGUOUS, true),
                'file'      => $rel,
                'line'      => $i + 1,
                'key'       => $key,
                'found'     => $hit[0][0],
                'expected'  => $meta['us'],
                'value'     => mb_substr($value, 0, 110),
            ];
        }
    }
}

$hard = array_values(array_filter($findings, static fn($f) => !$f['ambiguous']));
$soft = array_values(array_filter($findings, static fn($f) => $f['ambiguous']));

$fail = $hard !== [] || ($strict && $soft !== []);

if ($json) {
    echo json_encode([
        'files_scanned'   => $scanned,
        'strings_scanned' => $strings,
        'britishisms'     => $hard,
        'ambiguous'       => $soft,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit($fail ? 1 : 0);
}

echo "\nen-US canonical-source consistency\n";
echo str_repeat('─', 74) . "\n";
echo "Files scanned:   {$scanned}\n";
echo "Strings scanned: {$strings}\n";
echo 'Britishisms:     ' . count($hard) . ' (plus ' . count($soft) . " ambiguous)\n\n";

if ($hard === [] && $soft === []) {
    echo "No British spellings found in en-US source strings.\n\n";
    exit(0);
}

if ($hard !== []) {
    echo "BRITISHISM IN CANONICAL SOURCE\n";
    echo str_repeat('─', 30) . "\n";

    foreach ($hard as $f) {
        echo "  {$f['file']}:{$f['line']}\n";
        echo "    {$f['key']}\n";
        echo "    found \"{$f['found']}\" — en-US is \"{$f['expected']}\"\n";
        echo "    \"{$f['value']}\"\n\n";
    }

    echo "  Each of these is invisible to the US→GB generator: the replacement table is\n";
    echo "  keyed on the American form, so nothing rewrites a word that is already\n";
    echo "  British. Fix the en-US source, then regenerate en-GB.\n\n";
}

if ($soft !== []) {
    echo "AMBIGUOUS — valid in American English too, listed for eyes only\n";
    echo str_repeat('─', 62) . "\n";

    foreach ($soft as $f) {
        echo "  {$f['file']}:{$f['line']}  {$f['key']}  \"{$f['found']}\"\n";
    }

    echo "\n";
}

exit($fail ? 1 : 0);
