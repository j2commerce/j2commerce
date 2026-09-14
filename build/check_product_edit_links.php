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
 * Standalone product edit link check.
 *
 * A J2Commerce product is edited only in its com_content article. The standalone product form
 * is gone, and ProductController / View\Product\HtmlView redirect every request for it to the
 * article editor, so a link to it is always one redirect away from the right screen at best and
 * a checked-out row at worst. Link to the article editor instead:
 *
 *   ProductHelper::getArticleEditRoute($productId)
 *   index.php?option=com_content&task=article.edit&id=<product_source_id>
 *
 * Usage:
 *   php build/check_product_edit_links.php                 — scan the core ship set, exit 1 on any hit
 *   php build/check_product_edit_links.php --path=<dir> …  — scan the given directories instead
 *                                                              (e.g. an extensions repository)
 */

declare(strict_types=1);

$root = \dirname(__DIR__);

$paths = [];
foreach (\array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $paths[] = rtrim(str_replace('\\', '/', substr($arg, 7)), '/');
    }
}

if ($paths === []) {
    $paths = array_map(static fn (string $p): string => $root . '/' . $p, coreShipRoots($root));
}

$patterns = [
    '/task=products?\.(?:edit|add|editProduct)\b/'                => 'links a standalone product edit task',
    '/view=product(?:&|&amp;)layout=edit\b/'                      => 'links the standalone product edit view',
    '/[\'"]product\.(?:edit|add|apply|save2?(?:new|copy)?)[\'"]/' => 'names a standalone product form task',
];

$self = str_replace('\\', '/', __FILE__);
$hits = [];

foreach ($paths as $path) {
    if (!is_dir($path)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $name = str_replace('\\', '/', $file->getPathname());

        if (
            $name === $self
            || !preg_match('/\.(?:php|js|xml|ini)$/', $name)
            || preg_match('#/(?:vendor|node_modules)/|\.min\.js$#', $name)
        ) {
            continue;
        }

        foreach (file($name) ?: [] as $index => $line) {
            foreach ($patterns as $regex => $what) {
                if (preg_match($regex, $line)) {
                    $hits[] = \sprintf('%s:%d  %s', ltrim(str_replace($root, '', $name), '/'), $index + 1, $what);
                }
            }
        }
    }
}

if ($hits === []) {
    echo "Product edit links: OK — nothing links the standalone product form.\n";
    exit(0);
}

echo "Product edit links: " . \count($hits) . " hit(s). Link the article editor instead"
    . " (ProductHelper::getArticleEditRoute()).\n";

foreach ($hits as $hit) {
    echo "  {$hit}\n";
}

exit(1);

/**
 * The directories build_package.php ships, read from its source rather than restated here so the
 * two lists cannot drift. Mirrors check_package_surface.php::shipDefinitions().
 *
 * @return list<string>
 */
function coreShipRoots(string $root): array
{
    $src = @file_get_contents($root . '/build/build_package.php');

    if ($src === false) {
        fwrite(STDERR, "ERROR: build/build_package.php not readable — cannot determine the ship set\n");
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
        preg_match_all('/[\'"]group[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]element[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $m[1], $p, PREG_SET_ORDER);

        foreach ($p as $hit) {
            $roots[] = 'plugins/' . $hit[1] . '/' . $hit[2];
        }
    }

    foreach (['adminModules' => 'administrator/modules', 'siteModules' => 'modules'] as $var => $dir) {
        if (preg_match('/\$' . $var . '\s*=\s*\[(.*?)\n\];/s', $src, $m)) {
            preg_match_all('/[\'"](mod_[a-z0-9_]+)[\'"]/', $m[1], $mm);

            foreach ($mm[1] as $module) {
                $roots[] = $dir . '/' . $module;
            }
        }
    }

    if (\count($roots) < 20) {
        fwrite(STDERR, "ERROR: parsed only " . \count($roots) . " ship roots — build_package.php shape changed\n");
        exit(2);
    }

    return $roots;
}
