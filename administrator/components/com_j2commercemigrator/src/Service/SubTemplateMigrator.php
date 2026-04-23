<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Service;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;

class SubTemplateMigrator
{
    // Files that moved from tmpl/bootstrap5/default_*.php to layouts/list/*/item_*.php
    private const FILE_RELOCATION_MAP = [
        // bootstrap5/ directory → list/category/
        'bootstrap5/default_simple.php'               => 'list/category/item_simple.php',
        'bootstrap5/default_variable.php'             => 'list/category/item_variable.php',
        'bootstrap5/default_configurable.php'         => 'list/category/item_configurable.php',
        'bootstrap5/default_flexivariable.php'        => 'list/category/item_flexivariable.php',
        'bootstrap5/default_downloadable.php'         => 'list/category/item_downloadable.php',
        'bootstrap5/default_images.php'               => 'list/category/item_images.php',
        'bootstrap5/default_title.php'                => 'list/category/item_title.php',
        'bootstrap5/default_description.php'          => 'list/category/item_description.php',
        'bootstrap5/default_price.php'                => 'list/category/item_price.php',
        'bootstrap5/default_cart.php'                 => 'list/category/item_cart.php',
        'bootstrap5/default_sku.php'                  => 'list/category/item_sku.php',
        'bootstrap5/default_stock.php'                => 'list/category/item_stock.php',
        'bootstrap5/default_options.php'              => 'list/category/item_options.php',
        'bootstrap5/default_configurableoptions.php'  => 'list/category/item_configurableoptions.php',
        'bootstrap5/default_variableoptions.php'      => 'list/category/item_variableoptions.php',
        'bootstrap5/default_flexivariableoptions.php' => 'list/category/item_flexivariableoptions.php',
        'bootstrap5/default_flexiprice.php'           => 'list/category/item_flexiprice.php',
        // tag_bootstrap5/ directory → list/tag/
        'tag_bootstrap5/default_simple.php'               => 'list/tag/item_simple.php',
        'tag_bootstrap5/default_variable.php'             => 'list/tag/item_variable.php',
        'tag_bootstrap5/default_configurable.php'         => 'list/tag/item_configurable.php',
        'tag_bootstrap5/default_flexivariable.php'        => 'list/tag/item_flexivariable.php',
        'tag_bootstrap5/default_downloadable.php'         => 'list/tag/item_downloadable.php',
        'tag_bootstrap5/default_images.php'               => 'list/tag/item_images.php',
        'tag_bootstrap5/default_title.php'                => 'list/tag/item_title.php',
        'tag_bootstrap5/default_description.php'          => 'list/tag/item_description.php',
        'tag_bootstrap5/default_price.php'                => 'list/tag/item_price.php',
        'tag_bootstrap5/default_cart.php'                 => 'list/tag/item_cart.php',
        'tag_bootstrap5/default_sku.php'                  => 'list/tag/item_sku.php',
        'tag_bootstrap5/default_stock.php'                => 'list/tag/item_stock.php',
        'tag_bootstrap5/default_options.php'              => 'list/tag/item_options.php',
        'tag_bootstrap5/default_configurableoptions.php'  => 'list/tag/item_configurableoptions.php',
        'tag_bootstrap5/default_variableoptions.php'      => 'list/tag/item_variableoptions.php',
        'tag_bootstrap5/default_flexivariableoptions.php' => 'list/tag/item_flexivariableoptions.php',
        'tag_bootstrap5/default_flexiprice.php'           => 'list/tag/item_flexiprice.php',
    ];

    // Files that stay in tmpl/ (detail view + list container)
    private const TMPL_FILES = [
        'bootstrap5/default.php', 'bootstrap5/view.php', 'bootstrap5/price.php',
        'bootstrap5/cart.php', 'bootstrap5/view_simple.php', 'bootstrap5/view_variable.php',
        'bootstrap5/view_configurable.php', 'bootstrap5/view_flexivariable.php',
        'bootstrap5/view_advancedvariable.php', 'bootstrap5/view_downloadable.php',
        'bootstrap5/view_images.php', 'bootstrap5/view_title.php', 'bootstrap5/view_brand.php',
        'bootstrap5/view_sdesc.php', 'bootstrap5/view_ldesc.php', 'bootstrap5/view_price.php',
        'bootstrap5/view_sku.php', 'bootstrap5/view_stock.php', 'bootstrap5/view_specs.php',
        'bootstrap5/view_options.php', 'bootstrap5/view_configurableoptions.php',
        'bootstrap5/view_variableoptions.php', 'bootstrap5/view_flexivariableoptions.php',
        'bootstrap5/view_advancedvariableoptions.php', 'bootstrap5/view_tabs.php',
        'bootstrap5/view_notabs.php', 'bootstrap5/view_cart.php', 'bootstrap5/view_crosssells.php',
        'bootstrap5/view_upsells.php', 'bootstrap5/view_flexiprice.php',
        'bootstrap5/default_filters.php', 'bootstrap5/default_sortfilter.php',
        'tag_bootstrap5/default.php', 'tag_bootstrap5/view.php', 'tag_bootstrap5/price.php',
        'tag_bootstrap5/cart.php', 'tag_bootstrap5/view_simple.php', 'tag_bootstrap5/view_variable.php',
        'tag_bootstrap5/view_configurable.php', 'tag_bootstrap5/view_flexivariable.php',
        'tag_bootstrap5/view_advancedvariable.php', 'tag_bootstrap5/view_downloadable.php',
        'tag_bootstrap5/view_images.php', 'tag_bootstrap5/view_title.php', 'tag_bootstrap5/view_brand.php',
        'tag_bootstrap5/view_sdesc.php', 'tag_bootstrap5/view_ldesc.php', 'tag_bootstrap5/view_price.php',
        'tag_bootstrap5/view_sku.php', 'tag_bootstrap5/view_stock.php', 'tag_bootstrap5/view_specs.php',
        'tag_bootstrap5/view_options.php', 'tag_bootstrap5/view_configurableoptions.php',
        'tag_bootstrap5/view_variableoptions.php', 'tag_bootstrap5/view_flexivariableoptions.php',
        'tag_bootstrap5/view_advancedvariableoptions.php', 'tag_bootstrap5/view_tabs.php',
        'tag_bootstrap5/view_notabs.php', 'tag_bootstrap5/view_cart.php', 'tag_bootstrap5/view_crosssells.php',
        'tag_bootstrap5/view_upsells.php', 'tag_bootstrap5/view_flexiprice.php',
        'tag_bootstrap5/default_filters.php', 'tag_bootstrap5/default_sortfilter.php',
        'backend.php',
    ];

    // Files removed in J2Commerce (no migration target)
    private const REMOVED_FILES = [
        'bootstrap5/view_specs_helgen.php',
    ];

    // Files to SKIP from source j2store templates — these are always replaced
    // with fresh copies from the bootstrap5 plugin, so migrating them is pointless.
    private const SKIP_FROM_SOURCE = [
        'default.php',
        'default_filters.php',
        'default_price.php',
        'default_sortfilter.php',
        'view_cart.php',
        'view_price.php',
    ];

    // Files always copied from bootstrap5 plugin into every migrated subtemplate folder.
    private const BOOTSTRAP5_DEFAULT_FILES = [
        'default.php',
        'default_categoryfilter.php',
        'default_filters.php',
        'default_sortfilter.php',
        'view_cart.php',
        'view_price.php',
    ];

    // Layout files always copied fresh from bootstrap5 plugin (contain Joomla 6 specific code)
    private const BOOTSTRAP5_DEFAULT_LAYOUTS = [
        'list/category/item_price.php',
        'list/tag/item_price.php',
    ];

    private const BOOTSTRAP5_PLUGIN_TMPL       = 'plugins/j2commerce/app_bootstrap5/tmpl/bootstrap5/';
    private const CATEGORIES_BOOTSTRAP5_TMPL   = 'plugins/j2commerce/app_bootstrap5/tmpl/categories_bootstrap5/';

    // J2Store override base paths to scan (relative to JPATH_ROOT/templates/{template}/)
    // Only scan the templates/ subfolder — NOT checkout, cart, myprofile etc.
    private const J2STORE_OVERRIDE_BASE_PATHS = [
        'html/com_j2store/templates/',
    ];

    public function __construct(
        private MigrationLogger $logger
    ) {}

    public function discover(): array
    {
        $templates = $this->getInstalledTemplates();
        $result    = ['success' => true, 'templates' => []];

        foreach ($templates as $template) {
            $foundFiles = $this->scanTemplateForJ2StoreFiles($template);

            if (empty($foundFiles)) {
                continue;
            }

            $mapped  = $this->classifyFiles($foundFiles, $template);
            $layout  = 0;
            $tmpl    = 0;
            $removed = 0;

            foreach ($mapped as $fileInfo) {
                match ($fileInfo['type']) {
                    'LAYOUT'  => $layout++,
                    'TMPL'    => $tmpl++,
                    'REMOVED' => $removed++,
                    default   => null,
                };
            }

            $result['templates'][$template] = [
                'paths'   => $this->getOverridePaths($template),
                'files'   => $mapped,
                'total'   => count($mapped),
                'layout'  => $layout,
                'tmpl'    => $tmpl,
                'removed' => $removed,
            ];
        }

        return $result;
    }

    public function analyze(string $template): array
    {
        $template = $this->validateTemplateName($template);

        if ($template === '') {
            return ['success' => false, 'error' => 'Invalid template name'];
        }

        $foundFiles = $this->scanTemplateForJ2StoreFiles($template);
        $mapped     = $this->classifyFiles($foundFiles, $template);

        $files          = [];
        $totalReplace   = 0;
        $jqueryWarnings = 0;
        $removedCount   = 0;

        foreach ($mapped as $relative => $fileInfo) {
            if ($fileInfo['type'] === 'REMOVED') {
                $removedCount++;
                $files[] = [
                    'source'            => $relative,
                    'target'            => null,
                    'type'              => 'REMOVED',
                    'replacements'      => 0,
                    'layout_transforms' => 0,
                    'jquery_warnings'   => 0,
                    'syntax_valid'      => null,
                    'unmatched'         => [],
                ];
                continue;
            }

            $content     = file_get_contents($fileInfo['source']);
            $isLayout    = $fileInfo['type'] === 'LAYOUT';
            $transformer = new TemplateCodeTransformer();

            // Check file replacement (Category 12)
            if ($transformer->shouldReplaceFile($fileInfo['source'])) {
                $files[] = [
                    'source'            => $relative,
                    'target'            => $fileInfo['targetRelative'] ?? null,
                    'type'              => $fileInfo['type'],
                    'replacements'      => 1,
                    'layout_transforms' => 0,
                    'jquery_warnings'   => 0,
                    'jquery_patterns'   => [],
                    'syntax_valid'      => true,
                    'unmatched'         => [],
                    'code_transform'    => ['fileReplaced' => true],
                ];
                $totalReplace++;
                continue;
            }

            // Deep code transformation (Categories 1-11)
            $transformResult  = $transformer->transform($content, basename($relative));
            $replaced         = $transformResult['content'];
            $layoutTransforms = 0;

            if ($isLayout) {
                $before           = $replaced;
                $replaced         = $this->applyLayoutTransforms($replaced, $fileInfo['context'] ?? 'category');
                $layoutTransforms = substr_count($replaced, '$product') - substr_count($before, '$product');
            }

            $jqPatterns = array_map(fn($r) => [
                'type'     => 'jquery',
                'line'     => $r['line'],
                'pattern'  => $r['pattern'],
                'guidance' => $r['description'],
            ], $transformResult['manualReviews']);

            $unmatched    = $this->findUnmatchedPatterns($replaced);
            $replaceCount = $transformResult['totalChanges'] + $this->countReplacements($content, $replaced);

            $jqueryWarnings += count($jqPatterns);
            $totalReplace   += $replaceCount;

            $files[] = [
                'source'            => $relative,
                'target'            => $fileInfo['targetRelative'] ?? null,
                'type'              => $fileInfo['type'],
                'replacements'      => $replaceCount,
                'layout_transforms' => $layoutTransforms,
                'jquery_warnings'   => count($jqPatterns),
                'jquery_patterns'   => $jqPatterns,
                'syntax_valid'      => true, // dry run — can't lint without writing
                'unmatched'         => $unmatched,
            ];
        }

        // Report which bootstrap5 defaults and categories would be copied
        $subtemplates   = $this->getSubtemplateNames($foundFiles);
        $bootstrap5Info = [];

        foreach ($subtemplates as $sub) {
            $bootstrap5Info[$sub] = [
                'defaults'   => self::BOOTSTRAP5_DEFAULT_FILES,
                'categories' => "categories_{$sub}/",
                'layouts'    => self::BOOTSTRAP5_DEFAULT_LAYOUTS,
            ];
        }

        return [
            'success'  => true,
            'template' => $template,
            'files'    => $files,
            'summary'  => [
                'total_files'         => count($files),
                'layout_files'        => count(array_filter($files, fn($f) => $f['type'] === 'LAYOUT')),
                'tmpl_files'          => count(array_filter($files, fn($f) => $f['type'] === 'TMPL')),
                'removed_files'       => $removedCount,
                'total_replacements'  => $totalReplace,
                'jquery_warnings'     => $jqueryWarnings,
                'bootstrap5_copies'   => $bootstrap5Info,
                'skipped_from_source' => self::SKIP_FROM_SOURCE,
            ],
        ];
    }

    public function migrate(string $template, bool $backup = true): array
    {
        $template = $this->validateTemplateName($template);

        if ($template === '') {
            return ['success' => false, 'error' => 'Invalid template name'];
        }

        $foundFiles = $this->scanTemplateForJ2StoreFiles($template);
        $mapped     = $this->classifyFiles($foundFiles, $template);

        $migrated  = 0;
        $backedUp  = 0;
        $warnings  = [];
        $errors    = [];
        $files     = [];

        foreach ($mapped as $relative => $fileInfo) {
            if ($fileInfo['type'] === 'REMOVED') {
                $files[] = [
                    'source' => $relative,
                    'target' => null,
                    'type'   => 'REMOVED',
                    'status' => 'skipped',
                    'note'   => 'File removed in J2Commerce — no migration target',
                ];
                $this->logger->info("SUBTEMPLATE: Skipping removed file: {$relative}");
                continue;
            }

            $sourcePath = $fileInfo['source'];
            $targetPath = $fileInfo['target'];
            $isLayout   = $fileInfo['type'] === 'LAYOUT';
            $context    = $fileInfo['context'] ?? 'category';

            // Create target directory
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Backup existing target
            if ($backup && file_exists($targetPath)) {
                $backupPath = $targetPath . '.j2store-backup';
                copy($targetPath, $backupPath);
                $backedUp++;
            }

            $fileResult = $this->migrateFile($sourcePath, $targetPath, $isLayout, $context);
            $migrated++;

            foreach ($fileResult['jquery_warnings'] as $warn) {
                $warnings[] = array_merge($warn, ['file' => $relative]);
            }

            if (!$fileResult['syntax_valid']) {
                foreach ($fileResult['syntax_errors'] as $err) {
                    $errors[]   = array_merge($err, ['file' => $relative]);
                    $warnings[] = array_merge($err, ['file' => $relative, 'type' => 'syntax']);
                }
            }

            $files[] = [
                'source'       => $relative,
                'target'       => $fileInfo['targetRelative'] ?? null,
                'type'         => $fileInfo['type'],
                'status'       => $fileResult['syntax_valid'] ? (count($fileResult['jquery_warnings']) > 0 ? 'warning' : 'ok') : 'error',
                'replacements' => $fileResult['replacements'],
                'warnings'     => array_merge($fileResult['jquery_warnings'], $fileResult['syntax_errors']),
                'unmatched'    => $fileResult['unmatched'],
            ];

            $this->logger->info("SUBTEMPLATE: Migrated {$relative} → " . basename($targetPath) . " ({$fileResult['replacements']} replacements)");
        }

        // Copy bootstrap5 defaults + categories folder for each discovered subtemplate
        $subtemplates     = $this->getSubtemplateNames($foundFiles);
        $copiedDefaults   = [];
        $copiedCategories = [];

        foreach ($subtemplates as $sub) {
            $copiedDefaults[$sub]   = $this->copyBootstrap5DefaultFiles($template, $sub);
            $copiedCategories[$sub] = $this->copyCategoriesFolder($template, $sub);
        }

        // Copy price layout files fresh from bootstrap5 plugin (Joomla 6 specific code)
        $copiedLayouts = $this->copyBootstrap5DefaultLayouts($template);

        return [
            'success'           => true,
            'migrated'          => $migrated,
            'backed_up'         => $backedUp,
            'warnings'          => $warnings,
            'errors'            => $errors,
            'files'             => $files,
            'copied_defaults'   => $copiedDefaults,
            'copied_categories' => $copiedCategories,
            'copied_layouts'    => $copiedLayouts,
        ];
    }

    public function migrateFile(string $sourcePath, string $targetPath, bool $isLayoutFile, string $context = 'category'): array
    {
        $content     = file_get_contents($sourcePath);
        $transformer = new TemplateCodeTransformer();

        // Check if this file should be entirely replaced (Category 12)
        if ($transformer->shouldReplaceFile($sourcePath)) {
            $replacementPath = $transformer->getReplacementFilePath($sourcePath);
            if ($replacementPath && file_exists($replacementPath)) {
                $content = file_get_contents($replacementPath);
                file_put_contents($targetPath, $content);
                return [
                    'replacements'    => 1,
                    'jquery_warnings' => [],
                    'syntax_valid'    => true,
                    'syntax_errors'   => [],
                    'unmatched'       => [],
                    'code_transform'  => ['totalChanges' => 0, 'changes' => [], 'manualReviews' => [], 'fileReplaced' => true],
                ];
            }
        }

        // Apply deep code transformation (Categories 1-11)
        $transformResult = $transformer->transform($content, basename($sourcePath));
        $content         = $transformResult['content'];

        // Layout-specific transforms (Section 4.9)
        if ($isLayoutFile) {
            $content = $this->applyLayoutTransforms($content, $context);
        }

        // jQuery warnings come from the transformer's MANUAL_REVIEW flags
        $jqueryWarnings = array_map(fn($r) => [
            'type'     => 'jquery',
            'line'     => $r['line'],
            'pattern'  => $r['pattern'],
            'guidance' => $r['description'],
        ], $transformResult['manualReviews']);

        // Update/add copyright header
        $content = $this->updateCopyrightHeader($content);

        // Count replacements
        $replacements = $this->countReplacements(file_get_contents($sourcePath), $content);

        // Find unmatched patterns
        $unmatched = $this->findUnmatchedPatterns($content);

        // Write file
        file_put_contents($targetPath, $content);

        // PHP syntax check
        $syntaxValid  = true;
        $syntaxErrors = [];
        $lintResult   = $this->runSyntaxCheck($targetPath);

        if (!$lintResult['valid']) {
            $syntaxValid    = false;
            $syntaxErrors[] = [
                'type'    => 'syntax',
                'line'    => $lintResult['line'],
                'message' => $lintResult['message'],
            ];

            // Restore backup if syntax error
            $backupPath = $targetPath . '.j2store-backup';
            if (file_exists($backupPath)) {
                // Keep the errored file as .migrated-error for review
                rename($targetPath, $targetPath . '.migrated-error');
                copy($backupPath, $targetPath);
            }

            $this->logger->error("SUBTEMPLATE SYNTAX ERROR: {$targetPath} — {$lintResult['message']}");
        }

        return [
            'replacements'    => $replacements,
            'jquery_warnings' => $jqueryWarnings,
            'syntax_valid'    => $syntaxValid,
            'syntax_errors'   => $syntaxErrors,
            'unmatched'       => $unmatched,
            'code_transform'  => [
                'totalChanges'  => $transformResult['totalChanges'],
                'changes'       => $transformResult['changes'],
                'manualReviews' => $transformResult['manualReviews'],
            ],
        ];
    }

    public function discoverTemplateOverrides(): array
    {
        $templates = $this->getInstalledTemplates();
        $overrides = [];

        foreach ($templates as $template) {
            $foundFiles = $this->scanTemplateForJ2StoreFiles($template);

            if (empty($foundFiles)) {
                continue;
            }

            $mapped = $this->classifyFiles($foundFiles, $template);

            $overrides[$template] = [
                'paths'      => $this->getOverridePaths($template),
                'files'      => array_values($mapped),
                'file_count' => count($mapped),
            ];
        }

        return ['success' => true, 'overrides' => $overrides];
    }

    public function migrateTemplateOverrides(string $template, array $selectedFiles, bool $backup = true): array
    {
        $template = $this->validateTemplateName($template);

        if ($template === '') {
            return ['success' => false, 'error' => 'Invalid template name'];
        }

        $foundFiles = $this->scanTemplateForJ2StoreFiles($template);
        $mapped     = $this->classifyFiles($foundFiles, $template);

        // Filter to only selected files
        if (!empty($selectedFiles)) {
            $mapped = array_filter($mapped, fn($info, $rel) => in_array($rel, $selectedFiles, true), ARRAY_FILTER_USE_BOTH);
        }

        $migrated = 0;
        $files    = [];

        foreach ($mapped as $relative => $fileInfo) {
            if ($fileInfo['type'] === 'REMOVED') {
                $files[] = ['source' => $relative, 'type' => 'REMOVED', 'status' => 'skipped'];
                continue;
            }

            $targetPath = $fileInfo['target'];
            $targetDir  = dirname($targetPath);

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if ($backup && file_exists($targetPath)) {
                copy($targetPath, $targetPath . '.pre-override-backup');
            }

            $fileResult = $this->migrateFile(
                $fileInfo['source'],
                $targetPath,
                $fileInfo['type'] === 'LAYOUT',
                $fileInfo['context'] ?? 'category'
            );

            $migrated++;
            $files[] = [
                'source'       => $relative,
                'target'       => $fileInfo['targetRelative'] ?? null,
                'type'         => $fileInfo['type'],
                'status'       => $fileResult['syntax_valid'] ? 'ok' : 'error',
                'replacements' => $fileResult['replacements'],
                'warnings'     => $fileResult['jquery_warnings'],
                'unmatched'    => $fileResult['unmatched'],
            ];
        }

        // Copy bootstrap5 defaults + categories folder for each discovered subtemplate
        $subtemplates     = $this->getSubtemplateNames($foundFiles);
        $copiedDefaults   = [];
        $copiedCategories = [];

        foreach ($subtemplates as $sub) {
            $copiedDefaults[$sub]   = $this->copyBootstrap5DefaultFiles($template, $sub);
            $copiedCategories[$sub] = $this->copyCategoriesFolder($template, $sub);
        }

        return [
            'success'           => true,
            'migrated'          => $migrated,
            'files'             => $files,
            'copied_defaults'   => $copiedDefaults,
            'copied_categories' => $copiedCategories,
        ];
    }

    public function applyManualReplacement(string $filePath, int $lineNumber, string $oldPattern, string $newPattern): array
    {
        // Security: strip PHP tags from replacement
        $newPattern = str_replace(['<?php', '<?=', '?>'], '', $newPattern);
        $filePath   = realpath($filePath) ?: '';

        // Validate path is within templates/
        if (!str_starts_with($filePath, realpath(JPATH_ROOT . '/templates') . DIRECTORY_SEPARATOR)) {
            return ['success' => false, 'error' => 'Invalid file path'];
        }

        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        $content = file_get_contents($filePath);
        $new     = str_replace($oldPattern, $newPattern, $content, $count);

        if ($count === 0) {
            return ['success' => false, 'error' => 'Pattern not found in file'];
        }

        file_put_contents($filePath, $new);

        $lint = $this->runSyntaxCheck($filePath);

        return [
            'success'      => true,
            'replacements' => $count,
            'syntax_valid' => $lint['valid'],
            'syntax_error' => $lint['message'] ?? null,
        ];
    }

    public function remigrateFromPlugin(string $targetPath, bool $isLayoutFile): array
    {
        $pluginBase = JPATH_ROOT . '/plugins/j2commerce/app_bootstrap5';

        if (!is_dir($pluginBase)) {
            return ['success' => false, 'error' => 'J2Commerce app_bootstrap5 plugin not found'];
        }

        $targetPath = realpath($targetPath) ?: '';

        // Determine source from target path
        $sourcePath = $isLayoutFile
            ? $pluginBase . '/layouts/' . $this->targetToLayoutRelative($targetPath)
            : $pluginBase . '/tmpl/' . $this->targetToTmplRelative($targetPath);

        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => "Plugin source file not found: {$sourcePath}"];
        }

        $fileResult = $this->migrateFile($sourcePath, $targetPath, $isLayoutFile);

        return [
            'success'      => true,
            'replacements' => $fileResult['replacements'],
            'syntax_valid' => $fileResult['syntax_valid'],
            'warnings'     => $fileResult['jquery_warnings'],
            'unmatched'    => $fileResult['unmatched'],
        ];
    }

    // ── Private Methods ──

    private function getSubtemplateNames(array $foundFiles): array
    {
        $subtemplates = [];

        foreach (array_keys($foundFiles) as $relative) {
            $parts = explode('/', str_replace('\\', '/', $relative));
            if (count($parts) >= 2) {
                $subtemplates[$parts[0]] = true;
            }
        }

        return array_keys($subtemplates);
    }

    private function copyBootstrap5DefaultFiles(string $template, string $subtemplate): array
    {
        $sourceBase = JPATH_ROOT . '/' . self::BOOTSTRAP5_PLUGIN_TMPL;
        $targetBase = JPATH_ROOT . "/templates/{$template}/html/com_j2commerce/templates/{$subtemplate}/";
        $copied     = [];

        if (!is_dir($sourceBase)) {
            $this->logger->warning("SUBTEMPLATE: Bootstrap5 plugin tmpl not found: {$sourceBase}");
            return $copied;
        }

        if (!is_dir($targetBase)) {
            mkdir($targetBase, 0755, true);
        }

        foreach (self::BOOTSTRAP5_DEFAULT_FILES as $filename) {
            $source = $sourceBase . $filename;
            $target = $targetBase . $filename;

            if (!file_exists($source)) {
                $this->logger->warning("SUBTEMPLATE: Bootstrap5 source not found: {$source}");
                continue;
            }

            copy($source, $target);
            $copied[] = $filename;
            $this->logger->info("SUBTEMPLATE: Copied bootstrap5 default {$filename} → {$subtemplate}/{$filename}");
        }

        return $copied;
    }

    private function copyBootstrap5DefaultLayouts(string $template): array
    {
        $sourceBase = JPATH_ROOT . '/plugins/j2commerce/app_bootstrap5/layouts/';
        $targetBase = JPATH_ROOT . "/templates/{$template}/html/layouts/com_j2commerce/app_bootstrap5/";
        $copied     = [];

        if (!is_dir($sourceBase)) {
            $this->logger->warning("SUBTEMPLATE: Bootstrap5 plugin layouts not found: {$sourceBase}");
            return $copied;
        }

        foreach (self::BOOTSTRAP5_DEFAULT_LAYOUTS as $layoutFile) {
            $source = $sourceBase . $layoutFile;
            $target = $targetBase . $layoutFile;

            if (!file_exists($source)) {
                $this->logger->warning("SUBTEMPLATE: Bootstrap5 layout source not found: {$source}");
                continue;
            }

            $dir = dirname($target);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($source, $target);
            $copied[] = $layoutFile;
            $this->logger->info("SUBTEMPLATE: Copied bootstrap5 layout {$layoutFile}");
        }

        return $copied;
    }

    private function copyCategoriesFolder(string $template, string $subtemplate): array
    {
        $sourceBase = JPATH_ROOT . '/' . self::CATEGORIES_BOOTSTRAP5_TMPL;
        $targetBase = JPATH_ROOT . "/templates/{$template}/html/com_j2commerce/templates/categories_{$subtemplate}/";
        $copied     = [];

        if (!is_dir($sourceBase)) {
            $this->logger->warning("SUBTEMPLATE: categories_bootstrap5 folder not found: {$sourceBase}");
            return $copied;
        }

        if (!is_dir($targetBase)) {
            mkdir($targetBase, 0755, true);
        }

        $files = $this->findPhpFiles($sourceBase);

        foreach ($files as $file) {
            $relative = ltrim(str_replace(str_replace('\\', '/', $sourceBase), '', str_replace('\\', '/', $file)), '/');
            $target   = $targetBase . $relative;
            $dir      = dirname($target);

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($file, $target);
            $copied[] = $relative;
            $this->logger->info("SUBTEMPLATE: Copied categories_bootstrap5/{$relative} → categories_{$subtemplate}/{$relative}");
        }

        return $copied;
    }

    private function getInstalledTemplates(): array
    {
        $templatesDir = JPATH_ROOT . '/templates';

        if (!is_dir($templatesDir)) {
            return [];
        }

        return array_filter(
            scandir($templatesDir),
            fn($entry) => $entry !== '.' && $entry !== '..' && is_dir($templatesDir . '/' . $entry)
        );
    }

    private function getOverridePaths(string $template): array
    {
        return array_map(
            fn($path) => "templates/{$template}/{$path}",
            self::J2STORE_OVERRIDE_BASE_PATHS
        );
    }

    private function scanTemplateForJ2StoreFiles(string $template): array
    {
        $foundFiles = [];

        foreach (self::J2STORE_OVERRIDE_BASE_PATHS as $basePath) {
            $fullBase = str_replace('\\', '/', JPATH_ROOT . "/templates/{$template}/{$basePath}");

            if (!is_dir($fullBase)) {
                continue;
            }

            $files = $this->findPhpFiles($fullBase);

            foreach ($files as $file) {
                $relative = ltrim(str_replace($fullBase, '', $file), '/\\');

                // Skip files that are always replaced by bootstrap5 defaults
                if (in_array(basename($relative), self::SKIP_FROM_SOURCE, true)) {
                    continue;
                }

                // Higher-priority path wins (first found)
                if (!isset($foundFiles[$relative])) {
                    $foundFiles[$relative] = $file;
                }
            }
        }

        return $foundFiles;
    }

    private function findPhpFiles(string $dir): array
    {
        $result = [];

        if (!is_dir($dir)) {
            return $result;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $result[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        return $result;
    }

    private function classifyFiles(array $foundFiles, string $template): array
    {
        $mapped = [];

        foreach ($foundFiles as $relative => $sourcePath) {
            $normalized = str_replace('\\', '/', $relative);

            if (in_array($normalized, self::REMOVED_FILES, true)) {
                $mapped[$normalized] = [
                    'source'         => $sourcePath,
                    'target'         => null,
                    'targetRelative' => null,
                    'type'           => 'REMOVED',
                    'context'        => null,
                ];
                continue;
            }

            if (isset(self::FILE_RELOCATION_MAP[$normalized])) {
                $layoutRelative = self::FILE_RELOCATION_MAP[$normalized];
                $context        = str_contains($layoutRelative, 'list/tag/') ? 'tag' : 'category';
                $targetPath     = JPATH_ROOT . "/templates/{$template}/html/layouts/com_j2commerce/app_bootstrap5/{$layoutRelative}";

                $mapped[$normalized] = [
                    'source'         => $sourcePath,
                    'target'         => $targetPath,
                    'targetRelative' => "layouts/com_j2commerce/app_bootstrap5/{$layoutRelative}",
                    'type'           => 'LAYOUT',
                    'context'        => $context,
                    'target_exists'  => file_exists($targetPath),
                ];
                continue;
            }

            if (in_array($normalized, self::TMPL_FILES, true)) {
                $targetPath = JPATH_ROOT . "/templates/{$template}/html/com_j2commerce/templates/{$normalized}";

                $mapped[$normalized] = [
                    'source'         => $sourcePath,
                    'target'         => $targetPath,
                    'targetRelative' => "com_j2commerce/templates/{$normalized}",
                    'type'           => 'TMPL',
                    'context'        => null,
                    'target_exists'  => file_exists($targetPath),
                ];
                continue;
            }

            // Unmapped custom file — apply variable replacement and auto-detect type
            $isCustomLayout = preg_match('#^(bootstrap5|tag_bootstrap5)/default_\w+\.php$#', $normalized)
                && !in_array($normalized, self::TMPL_FILES, true);

            if ($isCustomLayout) {
                $context        = str_starts_with($normalized, 'tag_') ? 'tag' : 'category';
                $itemName       = preg_replace('#^(tag_)?bootstrap5/default_#', 'item_', $normalized);
                $layoutRelative = "list/{$context}/{$itemName}";
                $targetPath     = JPATH_ROOT . "/templates/{$template}/html/layouts/com_j2commerce/app_bootstrap5/{$layoutRelative}";

                $mapped[$normalized] = [
                    'source'         => $sourcePath,
                    'target'         => $targetPath,
                    'targetRelative' => "layouts/com_j2commerce/app_bootstrap5/{$layoutRelative}",
                    'type'           => 'LAYOUT',
                    'context'        => $context,
                    'target_exists'  => file_exists($targetPath),
                    'custom'         => true,
                ];
            } else {
                $targetPath = JPATH_ROOT . "/templates/{$template}/html/com_j2commerce/templates/{$normalized}";

                $mapped[$normalized] = [
                    'source'         => $sourcePath,
                    'target'         => $targetPath,
                    'targetRelative' => "com_j2commerce/templates/{$normalized}",
                    'type'           => 'TMPL',
                    'context'        => null,
                    'target_exists'  => file_exists($targetPath),
                    'custom'         => true,
                ];
            }
        }

        return $mapped;
    }

    private function applyLayoutTransforms(string $content, string $context): string
    {
        // 4.9 Template Data Passing — only for layout files

        // Replace $this->product->xxx with $product->xxx
        $content = preg_replace('/\$this->product->/', '$product->', $content);

        // Replace $this->product (not followed by ->) with $product
        $content = preg_replace('/\$this->product(?!->)/', '$product', $content);

        // Replace $this->params
        $content = preg_replace('/\$this->params/', '$params', $content);

        // Replace $this->escape( with htmlspecialchars(
        $content = str_replace('$this->escape(', 'htmlspecialchars(', $content);

        // Replace $this->product->product_link and $this->product_link
        $content = str_replace('$product->product_link', '$productLink', $content);
        $content = str_replace('$this->product_link', '$productLink', $content);

        // Replace $this->loadTemplate('name') with ProductLayoutService::renderLayout(...)
        $content = preg_replace_callback(
            '/\$this->loadTemplate\(\'([^\']+)\'\)/',
            function ($matches) use ($context) {
                $sub = $matches[1];
                return "ProductLayoutService::renderLayout('list.{$context}.item_{$sub}', \$displayData)";
            },
            $content
        );

        // Flag unknown $this-> patterns with a TODO comment
        $content = preg_replace(
            '/(\$this->(?!loadTemplate|escape)(\w+))(?![\'"\w])/',
            '$1 /* TODO: Manual review — $this->$2 not available in layout context */',
            $content
        );

        // Add ProductLayoutService use statement if needed
        if (str_contains($content, 'ProductLayoutService::') && !str_contains($content, 'use J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService')) {
            $content = $this->addUseStatement($content, 'J2Commerce\Component\J2commerce\Site\Service\ProductLayoutService');
        }

        // Inject extract($displayData) after defined('_JEXEC') line if not present
        if (!str_contains($content, 'extract($displayData)')) {
            $content = preg_replace(
                "/(defined\('_JEXEC'\) or die;?\s*\n)/",
                "$1\nextract(\$displayData);\n",
                $content,
                1
            );
        }

        return $content;
    }

    private function runSyntaxCheck(string $filePath): array
    {
        $output   = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);

        if ($exitCode === 0) {
            return ['valid' => true];
        }

        $message = implode("\n", $output);
        preg_match('/on line (\d+)/', $message, $matches);

        return [
            'valid'   => false,
            'line'    => (int) ($matches[1] ?? 0),
            'message' => $message,
        ];
    }

    private function findUnmatchedPatterns(string $content): array
    {
        $unmatched = [];
        $lines     = explode("\n", $content);
        $inComment = false;

        foreach ($lines as $lineNum => $line) {
            $trimmed = trim($line);

            // Track multi-line comment blocks (/* ... */ and /** ... */)
            if (!$inComment && (str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '/**'))) {
                $inComment = true;
            }
            if ($inComment) {
                if (str_contains($trimmed, '*/')) {
                    $inComment = false;
                }
                continue;
            }

            // Skip single-line comments
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Skip MANUAL_REVIEW comment lines
            if (str_contains($line, '@J2COMMERCE_MIGRATION: MANUAL_REVIEW')) {
                continue;
            }

            if (preg_match('/\bJ2Store\b/', $line)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => trim($line), 'type' => 'class_ref'];
            }
            if (preg_match('/\$j2store_\w+/', $line, $m)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => $m[0], 'type' => 'variable'];
            }
            if (preg_match('/J2STORE_\w+/', $line, $m)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => $m[0], 'type' => 'lang_key'];
            }
            if (preg_match('/onJ2Store\w+/', $line, $m)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => $m[0], 'type' => 'event'];
            }
            if (preg_match('/J2Html::\w+/', $line, $m)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => $m[0], 'type' => 'helper'];
            }
            if (preg_match('/\b(JText|JRoute|JFactory|JUri|JHtml|JHTML)\b::/', $line, $m)) {
                $unmatched[] = ['line' => $lineNum + 1, 'pattern' => $m[0], 'type' => 'legacy_joomla'];
            }
        }

        return $unmatched;
    }

    private function addUseStatement(string $content, string $class): string
    {
        // Find the position after <?php and any existing use statements
        if (preg_match('/^((?:.*\n)*?)(use [A-Z\\\\][^;]+;\n)/m', $content)) {
            // Append after last use statement
            return preg_replace(
                '/^(use [A-Z\\\\][^;]+;\n)(?!use )/m',
                "$1use {$class};\n",
                $content,
                1
            );
        }

        // No use statements — insert after <?php line
        return preg_replace('/^(<\?php[^\n]*\n)/m', "$1use {$class};\n", $content, 1);
    }

    private function updateCopyrightHeader(string $content): string
    {
        // Per PRD: DO NOT replace existing copyright headers — preserve third-party copyright.
        // Only add J2Commerce copyright if NO copyright/docblock header exists at all.
        if (preg_match('/\/\*\*.*?\*\//s', $content)) {
            return $content;
        }

        $copyright = <<<'EOT'
/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
EOT;

        // Insert after <?php line
        return preg_replace('/^(<\?php\s*\n)/', "$1{$copyright}\n\n", $content, 1);
    }

    private function countReplacements(string $original, string $migrated): int
    {
        // Count significant changes by line diff
        $origLines = explode("\n", $original);
        $migLines  = explode("\n", $migrated);
        $changes   = 0;

        $max = max(count($origLines), count($migLines));

        for ($i = 0; $i < $max; $i++) {
            if (($origLines[$i] ?? '') !== ($migLines[$i] ?? '')) {
                $changes++;
            }
        }

        return $changes;
    }

    private function validateTemplateName(string $template): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
            return '';
        }

        $templatesDir = JPATH_ROOT . '/templates/' . $template;

        if (!is_dir($templatesDir)) {
            return '';
        }

        return $template;
    }

    private function targetToLayoutRelative(string $targetPath): string
    {
        // Extract relative path from layouts/com_j2commerce/app_bootstrap5/ onwards
        $marker = 'html/layouts/com_j2commerce/app_bootstrap5/';
        $pos    = strpos(str_replace('\\', '/', $targetPath), $marker);

        return $pos !== false ? substr($targetPath, $pos + strlen($marker)) : basename($targetPath);
    }

    private function targetToTmplRelative(string $targetPath): string
    {
        $marker = 'html/com_j2commerce/templates/';
        $path   = str_replace('\\', '/', $targetPath);
        $pos    = strpos($path, $marker);

        return $pos !== false ? substr($path, $pos + strlen($marker)) : basename($targetPath);
    }
}
