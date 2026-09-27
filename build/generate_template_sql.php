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
 * Regenerates the `body` column of every row in emailtemplates.sql / invoicetemplates.sql
 * from the canonical .html presets under administrator/components/com_j2commerce/layouts/templates/,
 * so the install seed never drifts from what CoreTemplateSyncHelper resolves at runtime again.
 *
 * The row -> preset-file mapping is read directly out of CoreTemplateSyncHelper's own
 * EMAIL_TEMPLATES / INVOICE_TEMPLATES registries by splitting each registry block into its
 * per-entry `<id> => [ ... ]` sub-blocks and reading `'file'` out of the matching sub-block,
 * so a future registry edit cannot silently desync the generator from the class it mirrors.
 *
 * The row identity column is located by name (matching the registry's own PK column, e.g.
 * `j2commerce_emailtemplate_id`) rather than by position, the same way the `body` column is.
 * When a preset carries a leading `<!--@subject: ... -->` directive and the target table's
 * INSERT column list includes `subject`, that column is regenerated too, mirroring
 * CoreTemplateSyncHelper::syncTemplates()'s own subject-directive handling exactly; tables with
 * no `subject` column (invoicetemplates) are left untouched even if a preset carried one.
 *
 * Every other column, and every other UPDATE statement in either file, is left byte-for-byte
 * untouched: only the quoted `body` (and, where applicable, `subject`) literal for each row is
 * located and replaced in place.
 *
 * The tool fails loudly (non-zero exit) rather than silently reporting "already current" when:
 *   - zero `INSERT ... VALUES` rows were found/processed for a target file, or
 *   - a registry id never turned up as a row in that file's SQL (reverse coverage).
 *
 * Usage:
 *   php build/generate_template_sql.php          — regenerate both files in place
 *   php build/generate_template_sql.php --check  — exit 1 if the committed SQL would change
 *
 * Test-only override (never set outside a scratchpad test harness): setting the
 * GENERATE_TEMPLATE_SQL_TARGET_OVERRIDE environment variable to "<path>|<const>|<pk-column>"
 * replaces the normal two-file target list with a single arbitrary file, so the zero-row and
 * reverse-coverage failure paths can be exercised against a deliberately mutated SQL copy
 * without touching the committed seed files. The registry (HELPER_PATH) and presets
 * (PRESETS_DIR) stay pointed at the real project files.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/cli_guard.php';
requireCli();

\define('ROOT', \dirname(__DIR__));

const HELPER_PATH   = ROOT . '/administrator/components/com_j2commerce/src/Helper/CoreTemplateSyncHelper.php';
const PRESETS_DIR   = ROOT . '/administrator/components/com_j2commerce/layouts/templates/';
const SUBJECT_REGEX = '/<!--@subject:\s*(.*?)\s*-->[ \t]*\r?\n?/';
const UTF8_BOM      = "\xEF\xBB\xBF";

$check = \in_array('--check', $argv, true);

$col = [
    'reset'  => "\033[0m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'red'    => "\033[31m",
];

$targets = [
    ROOT . '/administrator/components/com_j2commerce/sql/install/mysql/emailtemplates.sql' => [
        'const' => 'EMAIL_TEMPLATES',
        'pk'    => 'j2commerce_emailtemplate_id',
    ],
    ROOT . '/administrator/components/com_j2commerce/sql/install/mysql/invoicetemplates.sql' => [
        'const' => 'INVOICE_TEMPLATES',
        'pk'    => 'j2commerce_invoicetemplate_id',
    ],
];

$targetOverride = getenv('GENERATE_TEMPLATE_SQL_TARGET_OVERRIDE');

if ($targetOverride !== false) {
    $parts = explode('|', $targetOverride, 3);

    if (\count($parts) !== 3) {
        fwrite(STDERR, "GENERATE_TEMPLATE_SQL_TARGET_OVERRIDE must be \"<path>|<const>|<pk-column>\"\n");
        exit(1);
    }

    [$overridePath, $overrideConst, $overridePk] = $parts;
    $targets                                     = [$overridePath => ['const' => $overrideConst, 'pk' => $overridePk]];
}

$helperSource = file_get_contents(HELPER_PATH);

if ($helperSource === false) {
    fwrite(STDERR, "Cannot read " . HELPER_PATH . "\n");
    exit(1);
}

/**
 * Pulls the `<int> => [ ... 'file' => '<path>', ... ],` entries out of one of the Helper's
 * private const registries, in declaration order, keyed by their registry id. Each entry is
 * isolated into its own sub-block first, and `'file'` is read from within that sub-block --
 * never zipped together from two independent flat scans -- so a registry edit that adds,
 * removes, or reorders an entry (or a key within one) can never misalign ids with files.
 */
function extractFileMapping(string $helperSource, string $constName): array
{
    if (!preg_match('/private const ' . $constName . ' = \[\n(.*?)\n    \];/s', $helperSource, $blockMatch)) {
        fwrite(STDERR, "Could not locate {$constName} in " . HELPER_PATH . "\n");
        exit(1);
    }

    $block = $blockMatch[1];

    if (!preg_match_all('/^ {8}(\d+) => \[\n(.*?)\n {8}\],?\s*$/ms', $block, $entryMatches, PREG_SET_ORDER)) {
        fwrite(STDERR, "No registry entries found for {$constName} in " . HELPER_PATH . "\n");
        exit(1);
    }

    $mapping = [];

    foreach ($entryMatches as $entryMatch) {
        $id        = (int) $entryMatch[1];
        $entryBody = $entryMatch[2];

        if (!preg_match("/'file'\\s*=>\\s*'([^']+)'/", $entryBody, $fileMatch)) {
            fwrite(STDERR, "Entry {$id} in {$constName} has no 'file' key\n");
            exit(1);
        }

        $mapping[$id] = $fileMatch[1];
    }

    return $mapping;
}

/**
 * Escapes text for embedding as a single-quoted MySQL string literal, matching the
 * backslash-escaped, single-line convention already used by these seed files (backslash
 * first, then quote, then the control-character tokens the escape introduces).
 */
function mysqlEscape(string $content): string
{
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = str_replace('\\', '\\\\', $content);
    $content = str_replace("'", "\\'", $content);
    $content = str_replace("\0", '\\0', $content);
    $content = str_replace("\x1a", '\\Z', $content);
    $content = str_replace("\t", '\\t', $content);
    $content = str_replace("\n", '\\n', $content);

    return $content;
}

/** Strips a leading UTF-8 byte-order mark from preset content, if present. */
function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, UTF8_BOM) ? substr($content, \strlen(UTF8_BOM)) : $content;
}

/**
 * Splits the tuples following a VALUES clause into per-row field offsets, stopping at the
 * statement-terminating `;`. Each field is [start, end) into $sql; quoted literals are
 * skipped atomically (backslash-escape aware) so embedded commas/parens never split a field.
 *
 * @return array{0: list<list<array{start:int,end:int}>>, 1: int} rows, and the offset just past the `;`
 */
function parseValueTuples(string $sql, int $pos, int $columnCount): array
{
    $len  = \strlen($sql);
    $rows = [];

    while ($pos < $len) {
        while ($pos < $len && (strpos(" \n\r\t,", $sql[$pos]) !== false)) {
            $pos++;
        }

        if ($pos >= $len) {
            throw new RuntimeException("Unterminated VALUES clause at offset {$pos}");
        }

        if ($sql[$pos] === ';') {
            $pos++;
            break;
        }

        if ($sql[$pos] !== '(') {
            throw new RuntimeException("Expected '(' or ';' at offset {$pos}, found '{$sql[$pos]}'");
        }

        $pos++;
        $fields     = [];
        $fieldStart = $pos;

        while (true) {
            if ($pos >= $len) {
                throw new RuntimeException('Unexpected end of file inside a value tuple');
            }

            $ch = $sql[$pos];

            if ($ch === "'") {
                $pos++;
                while ($pos < $len) {
                    if ($sql[$pos] === '\\') {
                        $pos += 2;
                        continue;
                    }
                    if ($sql[$pos] === "'") {
                        $pos++;
                        break;
                    }
                    $pos++;
                }
                continue;
            }

            if ($ch === ',') {
                $fields[]   = ['start' => $fieldStart, 'end' => $pos];
                $pos++;
                $fieldStart = $pos;
                continue;
            }

            if ($ch === ')') {
                $fields[] = ['start' => $fieldStart, 'end' => $pos];
                $pos++;
                break;
            }

            $pos++;
        }

        if (\count($fields) !== $columnCount) {
            throw new RuntimeException(
                'Row has ' . \count($fields) . " field(s), expected {$columnCount} (offset {$fieldStart})"
            );
        }

        $rows[] = $fields;
    }

    return [$rows, $pos];
}

/** Finds the [start, endInclusive] offsets of the first quoted literal inside a field slice. */
function locateQuotedLiteral(string $sql, int $fieldStart, int $fieldEnd): array
{
    $pos = $fieldStart;

    while ($pos < $fieldEnd && $sql[$pos] !== "'") {
        $pos++;
    }

    if ($pos >= $fieldEnd) {
        throw new RuntimeException("No quoted literal found in field at offset {$fieldStart}");
    }

    $quoteStart = $pos;
    $pos++;

    while ($pos < $fieldEnd) {
        if ($sql[$pos] === '\\') {
            $pos += 2;
            continue;
        }
        if ($sql[$pos] === "'") {
            return [$quoteStart, $pos];
        }
        $pos++;
    }

    throw new RuntimeException("Unterminated quoted literal in field at offset {$fieldStart}");
}

/**
 * Regenerates every `body` literal (and, where the table has one, the `subject` literal for
 * rows whose preset carries a `@subject` directive) in every `INSERT ... VALUES` statement
 * found in $sql. Throws when no rows were found at all, when a row's id has no registry
 * mapping, or when a registry id never showed up as a row -- so a mangled statement or a
 * deleted row is a hard failure, never a silent "nothing to do".
 */
function regenerateBodies(string $sql, array $mapping, string $pkColumn): string
{
    $pattern      = '/INSERT\s+IGNORE\s+INTO\s+`[^`]+`\s*\(([^)]+)\)\s*VALUES\s*/i';
    $offset       = 0;
    $replacements = [];
    $seenIds      = [];

    while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $columns = array_map(
            static fn (string $c): string => trim($c, " `\t\n\r\0\x0B"),
            explode(',', $m[1][0])
        );

        $pkIndex = array_search($pkColumn, $columns, true);

        if ($pkIndex === false) {
            throw new RuntimeException("No `{$pkColumn}` column in INSERT column list: " . $m[1][0]);
        }

        $bodyIndex = array_search('body', $columns, true);

        if ($bodyIndex === false) {
            throw new RuntimeException('No `body` column in INSERT column list: ' . $m[1][0]);
        }

        // Only present on emailtemplates -- absent (false) means the subject directive is
        // never applied to invoicetemplates rows, matching CoreTemplateSyncHelper exactly.
        $subjectIndex = array_search('subject', $columns, true);

        $valuesStart      = $m[0][1] + \strlen($m[0][0]);
        [$rows, $stmtEnd] = parseValueTuples($sql, $valuesStart, \count($columns));

        foreach ($rows as $row) {
            $idField = trim(substr($sql, $row[$pkIndex]['start'], $row[$pkIndex]['end'] - $row[$pkIndex]['start']));
            $id      = (int) $idField;

            if (!isset($mapping[$id])) {
                throw new RuntimeException("No preset mapping for row id {$id}");
            }

            $seenIds[$id] = true;

            $presetPath = PRESETS_DIR . $mapping[$id];

            if (!is_readable($presetPath)) {
                throw new RuntimeException("Preset file not readable: {$presetPath}");
            }

            $content = stripUtf8Bom((string) file_get_contents($presetPath));
            $subject = null;

            // Optional `<!--@subject: ... -->` directive at the top of the preset is the single
            // source for the row's subject; extract it and strip it from the saved body.
            if (preg_match(SUBJECT_REGEX, $content, $subjectMatch)) {
                $subject = $subjectMatch[1];
            }

            $content = (string) preg_replace(SUBJECT_REGEX, '', $content, 1);

            $bodyField                = $row[$bodyIndex];
            [$quoteStart, $quoteEnd]  = locateQuotedLiteral($sql, $bodyField['start'], $bodyField['end']);

            $replacements[] = [
                'start' => $quoteStart,
                'end'   => $quoteEnd + 1,
                'text'  => "'" . mysqlEscape($content) . "'",
            ];

            if ($subject !== null && $subjectIndex !== false) {
                $subjectField               = $row[$subjectIndex];
                [$sQuoteStart, $sQuoteEnd]  = locateQuotedLiteral($sql, $subjectField['start'], $subjectField['end']);

                $replacements[] = [
                    'start' => $sQuoteStart,
                    'end'   => $sQuoteEnd + 1,
                    'text'  => "'" . mysqlEscape($subject) . "'",
                ];
            }
        }

        $offset = $stmtEnd;
    }

    if ($seenIds === []) {
        throw new RuntimeException('No `INSERT ... VALUES` rows were processed -- check the INSERT pattern and table name');
    }

    $missingIds = array_diff(array_keys($mapping), array_keys($seenIds));

    if ($missingIds !== []) {
        sort($missingIds);
        throw new RuntimeException('Registry id(s) never appeared as a row: ' . implode(', ', $missingIds));
    }

    usort($replacements, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

    foreach ($replacements as $r) {
        $sql = substr_replace($sql, $r['text'], $r['start'], $r['end'] - $r['start']);
    }

    return $sql;
}

$hasDiff = false;
$exitErr = false;

foreach ($targets as $sqlPath => $targetInfo) {
    $mapping  = extractFileMapping($helperSource, $targetInfo['const']);
    $original = file_get_contents($sqlPath);

    if ($original === false) {
        fwrite(STDERR, "Cannot read {$sqlPath}\n");
        $exitErr = true;
        continue;
    }

    try {
        $updated = regenerateBodies($original, $mapping, $targetInfo['pk']);
    } catch (\Throwable $e) {
        fwrite(STDERR, "{$sqlPath}: " . $e->getMessage() . "\n");
        $exitErr = true;
        continue;
    }

    $rel = str_replace('\\', '/', substr($sqlPath, \strlen(ROOT) + 1));

    if ($updated === $original) {
        echo "{$col['green']}OK{$col['reset']}      {$rel} — already current\n";
        continue;
    }

    $hasDiff = true;

    if ($check) {
        echo "{$col['yellow']}DIFF{$col['reset']}    {$rel} — committed SQL is stale\n";
        continue;
    }

    if (file_put_contents($sqlPath, $updated) === false) {
        fwrite(STDERR, "Failed to write {$sqlPath}\n");
        $exitErr = true;
        continue;
    }

    echo "{$col['green']}WROTE{$col['reset']}   {$rel}\n";
}

if ($exitErr) {
    exit(1);
}

if ($check) {
    exit($hasDiff ? 1 : 0);
}

exit(0);
