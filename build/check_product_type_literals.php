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
 * Product-type literal linter.
 *
 * Core asks "which product types share this behaviour?" through ProductTypeHelper, whose map plugins
 * extend via onJ2CommerceGetProductTypeCapabilities. A hand-typed list of type tokens answers the
 * same question for the types that existed when it was typed, and silently skips every type a
 * plugin adds later — the failure is an absence, so no diff review ever shows it.
 *
 * Flags, in git-tracked core PHP/JS:
 *   1. a list literal — `[…]` or `array(…)`, on one line or spread over several — holding two or
 *      more known type tokens, unless it is a keyed map (`'simple' => …`, a label or count table);
 *   2. two or more comparisons of anything against type-token literals on one line, either order
 *      (`$t === 'variable' || $t === 'flexivariable'`, `'variable' === $t || …`);
 *   3. an SQL IN list of type tokens, as two or more `quote('<type>')` calls on one line or a
 *      literal `IN ('variable', 'flexivariable')` inside a string.
 *
 * An intentional allow-list (a wizard that only knows how to build three types) carries the marker
 * `product-type-literal-ok` with its reason on the line that opens the list.
 *
 * Usage: php build/check_product_type_literals.php   (exit 1 on any finding)
 */

declare(strict_types=1);

$root = \dirname(__DIR__);

const TYPE_TOKENS = [
    'simple', 'variable', 'configurable', 'downloadable', 'flexivariable', 'advancedvariable',
    'variablesubscriptionproduct', 'subscriptionproduct', 'bundleproduct', 'boxbuilderproduct',
    'guidedbuilder', 'groupproducts', 'booking', 'bundle',
];

const SCAN_PATHS = [
    'administrator/components/com_j2commerce',
    'administrator/modules/mod_j2commerce_menu',
    'administrator/modules/mod_j2commerce_orders',
    'administrator/modules/mod_j2commerce_quickicons',
    'administrator/modules/mod_j2commerce_stats',
    'components/com_j2commerce',
    'libraries/j2commerce',
    'libraries/j2commerceflow',
    'media/com_j2commerce/js',
    'modules/mod_j2commerce_cart',
    'modules/mod_j2commerce_currency',
    'modules/mod_j2commerce_products',
    'modules/mod_j2commerce_relatedproducts',
    'plugins/actionlog/j2commerce',
    'plugins/console/j2commerce',
    'plugins/content/j2commerce',
    'plugins/filesystem/uppymedia',
    'plugins/finder/j2commerce',
    'plugins/schemaorg/ecommerce',
    'plugins/system/j2commerce',
    'plugins/task/j2commerce',
    'plugins/user/j2commerce',
    'plugins/webservices/j2commerce',
    'plugins/j2commerce/app_bootstrap5',
    'plugins/j2commerce/app_currencyupdater',
    'plugins/j2commerce/app_diagnostics',
    'plugins/j2commerce/app_flexivariable',
    'plugins/j2commerce/app_localization_data',
    'plugins/j2commerce/app_uikit',
    'plugins/j2commerce/payment_banktransfer',
    'plugins/j2commerce/payment_cash',
    'plugins/j2commerce/payment_moneyorder',
    'plugins/j2commerce/payment_paypal',
    'plugins/j2commerce/report_itemised',
    'plugins/j2commerce/report_products',
    'plugins/j2commerce/shipping_free',
    'plugins/j2commerce/shipping_standard',
];

const OWNER_FILES = [
    'administrator/components/com_j2commerce/src/Helper/ProductTypeHelper.php',
];

$cmd   = 'git -C ' . escapeshellarg($root) . ' ls-files -- ' . implode(' ', array_map('escapeshellarg', SCAN_PATHS));
$files = array_filter(explode("\n", (string) shell_exec($cmd)), static fn (string $f): bool => preg_match('/\.(php|js)$/', $f) === 1
    && !preg_match('#(/vendor/|\.min\.js$|/language/|/sql/)#', $f)
    && !\in_array($f, OWNER_FILES, true));

if ($files === []) {
    fwrite(STDERR, "No files listed by git ls-files under the scan paths — run from the repository.\n");
    exit(2);
}

$tokenAlt = implode('|', TYPE_TOKENS);
$quoted   = "['\"](?:{$tokenAlt})['\"]";
$findings = [];

foreach ($files as $file) {
    $source = @file_get_contents($root . '/' . $file);

    if ($source === false) {
        continue;
    }

    $lines = explode("
", $source);

    // Blank comment-only lines (keeping the newline) so docblocks never match and offsets still map to lines.
    $code = implode("
", array_map(
        static fn (string $line): string => preg_match('#^\s*(\*|//|/\*|<!--)#', $line) ? '' : $line,
        $lines
    ));

    $flag = static function (int $line, string $reason) use (&$findings, $file, $lines): void {
        if (str_contains($lines[$line - 1] ?? '', 'product-type-literal-ok')) {
            return;
        }

        $findings[$file . ':' . $line] = \sprintf('%s:%d  %s  %s', $file, $line, $reason, mb_strimwidth(trim($lines[$line - 1] ?? ''), 0, 140, '…'));
    };

    // Two passes so a short-array span can never swallow an array() list; neither crosses a statement end.
    foreach (['/\[[^\[\];]*\]/s', '/\barray\s*\([^();]*\)/s'] as $listPattern) {
        if (!preg_match_all($listPattern, $code, $lists, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($lists[0] as [$list, $offset]) {
            if (!str_contains($list, '=>') && preg_match_all("/{$quoted}/", $list) >= 2) {
                $flag(substr_count($code, "
", 0, $offset) + 1, 'type-token list');
            }
        }
    }

    foreach (explode("
", $code) as $i => $line) {
        if ($line === '') {
            continue;
        }

        if (preg_match_all("/[!=]==?\s*{$quoted}/", $line) >= 2 || preg_match_all("/{$quoted}\s*[!=]==?/", $line) >= 2) {
            $flag($i + 1, 'chained type comparisons');
        } elseif (preg_match_all("/quote\(\s*{$quoted}\s*\)/", $line) >= 2 || preg_match("/\bIN\s*\(\s*{$quoted}\s*,\s*{$quoted}/i", $line)) {
            $flag($i + 1, 'type tokens in an SQL IN list');
        }
    }
}

if ($findings === []) {
    printf("OK  %d file(s) clean — no hard-coded product-type lists outside ProductTypeHelper.\n", \count($files));
    exit(0);
}

echo "Hard-coded product-type lists found. Ask ProductTypeHelper::has(\$type, ProductTypeHelper::<CAPABILITY>)\n";
echo "instead, or mark a deliberate allow-list with `product-type-literal-ok: <reason>`.\n\n";
echo implode("\n", $findings), "\n";
exit(1);
