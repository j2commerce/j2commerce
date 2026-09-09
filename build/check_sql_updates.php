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
 * Schema update file linter.
 *
 * Joomla's schema checker (libraries/src/Schema/ChangeItem/MysqlChangeItem.php) parses delta SQL by
 * splitting on whitespace and reading fixed word offsets. Two shapes MySQL accepts break it, and a
 * broken delta is permanent: Joomla never deletes retired update files from a site, so the bad row
 * is reported by Extensions -> Manage -> Database forever, and Fix re-executes its DDL each click.
 *
 *   1. `MODIFY COLUMN x` — the optional COLUMN keyword shifts every token by one, so the checker
 *      looks for a column literally named COLUMN. Write bare `MODIFY x`.
 *   2. Comma-chained clauses — splitSql() splits on `;` only, so `MODIFY a, MODIFY b` is one change
 *      item and only the first clause is ever checked. Give each column its own ALTER TABLE.
 *
 * A third rule is about the filenames rather than their contents. Joomla decides "the newest
 * delta" twice, with two sorts that do not agree:
 *
 *   - Installer::parseSchemaUpdates() sorts with usort($files, 'version_compare') and stamps
 *     the last one into #__schemas.
 *   - ChangeSet::getSchema(), which Extensions -> Manage -> Database reports against, takes
 *     the last entry of Folder::files(..., $naturalSort = true).
 *
 * version_compare canonicalises `-` to `.`, so `X-3` ranks after `X`. Natural sort compares
 * bytes, where `-` (0x2D) precedes `.` (0x2E), so `X-3.sql` ranks *before* `X.sql`. Ship a bare
 * `X.sql` alongside an `X-2.sql` on the newest date and the two answers differ permanently:
 * every site reports "Database version (X-3) does not match manifest version (X)". Give a
 * fourth same-day delta the next date instead of another suffix.
 *
 * Usage:
 *   php build/check_sql_updates.php              — lint, exit 1 on any violation
 *   php build/check_sql_updates.php --build-check — compact warning for build scripts (always exits 0)
 */

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

$buildCheck = in_array('--build-check', $argv, true);

$col = [
    'reset'  => "\033[0m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'red'    => "\033[31m",
];

$dirs = glob(ROOT . '/administrator/components/com_j2commerce/sql/updates/*', GLOB_ONLYDIR) ?: [];

$violations = [];
$fileCount  = 0;

foreach ($dirs as $dir) {
    foreach (glob($dir . '/*.sql') ?: [] as $path) {
        $fileCount++;
        $rel = str_replace('\\', '/', substr($path, strlen(ROOT) + 1));

        foreach (splitStatements((string) file_get_contents($path)) as $statement) {
            if (!preg_match('/^\s*ALTER\s+TABLE\b/i', $statement['sql'])) {
                continue;
            }

            if (preg_match('/\b(MODIFY|CHANGE)\s+COLUMN\b/i', $statement['sql'], $m)) {
                $violations[] = [
                    'file' => $rel,
                    'line' => $statement['line'],
                    'rule' => strtoupper($m[1]) . ' COLUMN',
                    'hint' => 'drop the COLUMN keyword — write ' . strtoupper($m[1]) . ' `col` <type>',
                ];
            }

            if (countClauses($statement['sql']) > 1) {
                $violations[] = [
                    'file' => $rel,
                    'line' => $statement['line'],
                    'rule' => 'multi-clause ALTER',
                    'hint' => 'split into one ALTER TABLE statement per column',
                ];
            }
        }
    }

    $names = array_map('basename', glob($dir . '/*.sql') ?: []);

    if (!$names) {
        continue;
    }

    $natural = $names;
    natsort($natural);
    $natural = end($natural);

    $semantic = $names;
    usort($semantic, 'version_compare');
    $semantic = end($semantic);

    if ($natural !== $semantic) {
        $violations[] = [
            'file' => str_replace(DIRECTORY_SEPARATOR, '/', substr($dir, strlen(ROOT) + 1)),
            'line' => null,
            'rule' => 'newest-delta sort disagreement',
            'hint' => 'the installer stamps ' . basename($semantic, '.sql') . ' but the Database view'
                . ' reports ' . basename($natural, '.sql')
                . ' — give the suffixed delta the next date instead',
        ];
    }
}

/**
 * Split on `;` the way Joomla's DatabaseDriver::splitSql() does, tracking the starting line of each
 * statement and ignoring semicolons inside quotes, backticks and comments.
 */
function splitStatements(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $line       = 1;
    $startLine  = 1;
    $quote      = '';
    $inLine     = false;
    $inBlock    = false;
    $length     = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($char === "\n") {
            $line++;
            $inLine = false;
        }

        if ($inLine) {
            continue;
        }

        if ($inBlock) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $inBlock = false;
            }
            continue;
        }

        if ($quote === '') {
            if ($char === '-' && $next === '-') {
                $inLine = true;
                continue;
            }

            // `#` opens a MySQL comment, but `#__` is Joomla's table prefix — treating an
            // unbackticked `#__table` as a comment would swallow the statement and pass silently.
            if ($char === '#' && substr($sql, $i + 1, 2) !== '__') {
                $inLine = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $buffer .= $char . $next;
                $i++;
                $inBlock = true;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = ['sql' => $buffer, 'line' => $startLine];
                }
                $buffer    = '';
                $startLine = $line;
                continue;
            }
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            if ($quote === '') {
                $quote = $char;
            } elseif ($quote === $char && ($i > 0 ? $sql[$i - 1] : '') !== '\\') {
                $quote = '';
            }
        }

        if (trim($buffer) === '' && trim($char) !== '') {
            $startLine = $line;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = ['sql' => $buffer, 'line' => $startLine];
    }

    return $statements;
}

/** Count top-level comma-separated clauses in an ALTER TABLE, ignoring commas inside quotes and parens. */
function countClauses(string $sql): int
{
    $clauses = 1;
    $depth   = 0;
    $quote   = '';
    $length  = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($quote !== '') {
            if ($char === $quote && ($i > 0 ? $sql[$i - 1] : '') !== '\\') {
                $quote = '';
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
        } elseif ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $clauses++;
        }
    }

    return $clauses;
}

if ($buildCheck) {
    if ($violations) {
        echo "{$col['yellow']}WARNING{$col['reset']} " . count($violations)
            . " schema update SQL violation(s) — run php build/check_sql_updates.php\n";
    }
    exit(0);
}

echo "Schema update SQL lint — {$fileCount} file(s) checked\n\n";

if (!$violations) {
    echo "{$col['green']}OK{$col['reset']}     no violations\n";
    exit(0);
}

foreach ($violations as $v) {
    $where = $v['line'] === null ? $v['file'] : $v['file'] . ':' . $v['line'];
    echo "{$col['red']}FAIL{$col['reset']}   {$where}\n";
    echo "       {$v['rule']} — {$v['hint']}\n";
}

echo "\n" . count($violations) . " violation(s). Joomla either cannot parse these or\n";
echo "cannot agree which is newest; a shipped delta reports a false mismatch on every site, forever.
";

exit(1);
