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

class TemplateCodeTransformer
{
    private const JOOMLA_LEGACY_REPLACEMENTS = [
        'JFactory::getDbo()'         => 'Factory::getContainer()->get(DatabaseInterface::class)',
        'JFactory::getApplication()' => 'Factory::getApplication()',
        'JFactory::getUser()'        => 'Factory::getApplication()->getIdentity()',
        'JFactory::getSession()'     => 'Factory::getApplication()->getSession()',
        'JFactory::getDocument()'    => 'Factory::getApplication()->getDocument()',
        'JFactory::getConfig()'      => 'Factory::getApplication()->getConfig()',
        'Factory::getUser()'         => 'Factory::getApplication()->getIdentity()',
        'Factory::getDocument()'     => 'Factory::getApplication()->getDocument()',
        'Factory::getConfig()'       => 'Factory::getApplication()->getConfig()',
        'Factory::getDbo()'          => 'Factory::getContainer()->get(DatabaseInterface::class)',
        'Factory::getSession()'      => 'Factory::getApplication()->getSession()',
        'JText::_('                  => 'Text::_(',
        'JText::sprintf('            => 'Text::sprintf(',
        'JRoute::_('                 => 'Route::_(',
        'JUri::root()'               => 'Uri::root()',
        'JUri::base()'               => 'Uri::base()',
        'JUri::current()'            => 'Uri::current()',
        'JUri::getInstance('         => 'Uri::getInstance(',
        'JURI::root()'               => 'Uri::root()',
        'JURI::base()'               => 'Uri::base()',
        'JURI::current()'            => 'Uri::current()',
        'JURI::getInstance('         => 'Uri::getInstance(',
        'URI::root()'                => 'Uri::root()',
        'URI::current()'             => 'Uri::current()',
        'JHTML::_('                  => 'HTMLHelper::_(',
        'JHtml::_('                  => 'HTMLHelper::_(',
        'JFile::exists('             => 'File::exists(',
        'JFile::copy('               => 'File::copy(',
        'JFile::delete('             => 'File::delete(',
        'JPath::clean('              => 'Path::clean(',
        'JPath::check('              => 'Path::check(',
        'new JRegistry'              => 'new Registry',
        'JRegistry::'                => 'Registry::',
        'JPagination'                => 'Pagination',
    ];

    private const USE_STATEMENT_MAP = [
        'Text::'                   => 'Joomla\CMS\Language\Text',
        'Route::'                  => 'Joomla\CMS\Router\Route',
        'Uri::'                    => 'Joomla\CMS\Uri\Uri',
        'HTMLHelper::'             => 'Joomla\CMS\HTML\HTMLHelper',
        'Factory::'                => 'Joomla\CMS\Factory',
        'DatabaseInterface::'      => 'Joomla\Database\DatabaseInterface',
        'DatabaseInterface::class' => 'Joomla\Database\DatabaseInterface',
        'File::'                   => 'Joomla\Filesystem\File',
        'Path::'                   => 'Joomla\Filesystem\Path',
        'J2CommerceHelper::'       => 'J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper',
        'StrapperHelper::'         => 'J2Commerce\Component\J2commerce\Administrator\Helper\StrapperHelper',
        'ProductHelper::'          => 'J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper',
        'FieldsHelper::'           => 'Joomla\Component\Fields\Administrator\Helper\FieldsHelper',
    ];

    private const USE_STATEMENT_CLASS_MAP = [
        'new Registry'   => 'Joomla\Registry\Registry',
        'Registry '      => 'Joomla\Registry\Registry',
        'new Pagination' => 'Joomla\CMS\Pagination\Pagination',
        'Pagination '    => 'Joomla\CMS\Pagination\Pagination',
    ];

    private const J2STORE_FACTORY_METHODS = [
        'product', 'currency', 'config', 'plugin', 'platform', 'utilities',
        'modules', 'article', 'cart', 'user', 'email', 'invoice', 'weight',
        'length', 'version', 'j2html', 'strapper', 'view', 'toolbar',
        'fof', 'queue', 'message', 'image', 'help',
    ];

    private const CSS_EXACT_REPLACEMENTS = [
        'j2store-addtocart-form-' => 'j2commerce-addtocart-form-',
        'j2store-addtocart-form'  => 'j2commerce-addtocart-form',
        'j2store-single-product'  => 'j2commerce-product-item',
        'j2store-product-list-'   => 'j2commerce-product-list-',
        'j2store-product-list'    => 'j2commerce-product-list',
        'j2store-products-row'    => 'j2commerce-products-row',
        'j2store-pagination'      => 'j2commerce-pagination',
        'j2store-sidebar-filters' => 'j2commerce-sidebar-filters',
        'j2store-filter-'         => 'j2commerce-filter-',
    ];

    private const COMPONENT_EXACT_REPLACEMENTS = [
        'option=com_j2store'  => 'option=com_j2commerce',
        'com_j2store.'        => 'com_j2commerce.',
        'view=mycart'         => 'view=cart',
        'task=carts.additem'  => 'task=carts.addItem',
    ];

    private const LANGUAGE_EXACT_REPLACEMENTS = [
        "'J2STORE_"    => "'COM_J2COMMERCE_",
        '"J2STORE_'    => '"COM_J2COMMERCE_',
        "'PLG_J2STORE_" => "'PLG_J2COMMERCE_",
        '"PLG_J2STORE_' => '"PLG_J2COMMERCE_',
    ];

    private const JQUERY_PATTERNS = [
        [
            'regex'   => '/j2store\.jQuery/',
            'message' => "Rewrite IIFE wrapper to: document.addEventListener('DOMContentLoaded', () => { ... })",
        ],
        [
            'regex'   => '/\(function\s*\(\$\)/',
            'message' => "Rewrite jQuery IIFE wrapper to: document.addEventListener('DOMContentLoaded', () => { ... })",
        ],
        [
            'regex'   => '/\$\.ajax\s*\(/',
            'message' => "Replace with: fetch(url, { method: 'POST', body: formData }).then(r => r.json())",
        ],
        [
            'regex'   => '/\$\.post\s*\(/',
            'message' => "Replace with: fetch(url, { method: 'POST', body: formData })",
        ],
        [
            'regex'   => '/\$\.get\s*\(/',
            'message' => "Replace with: fetch(url).then(r => r.json())",
        ],
        [
            'regex'   => '/\$\(document\)\.ready\s*\(/',
            'message' => "Replace with DOMContentLoaded event or defer attribute on script tag",
        ],
        [
            'regex'   => '/\$\.each\s*\(/',
            'message' => "Replace with: arr.forEach((value, index) => { })",
        ],
        [
            'regex'   => '/\$\.extend\s*\(/',
            'message' => "Replace with: Object.assign() or spread operator",
        ],
        [
            'regex'   => '/\$\.trim\s*\(/',
            'message' => "Replace with: String.prototype.trim()",
        ],
        [
            'regex'   => '/\$\.inArray\s*\(/',
            'message' => "Replace with: Array.prototype.includes()",
        ],
        [
            'regex'   => '/\.trigger\s*\(/',
            'message' => "Replace with: element.dispatchEvent(new CustomEvent(...))",
        ],
        [
            'regex'   => '/\$doc->addScript\s*\(/',
            'message' => "Migrate to \$wa->registerAndUseScript() per WAM pattern in CLAUDE.md",
        ],
    ];

    private const FILE_REPLACEMENTS = [
        'default_filters.php'    => null,
        'default_sortfilter.php' => null,
    ];

    private const REPLACEMENT_SOURCES = [
        'bootstrap5'     => 'plugins/j2commerce/app_bootstrap5/tmpl/bootstrap5/',
        'tag_bootstrap5' => 'plugins/j2commerce/app_bootstrap5/tmpl/tag_bootstrap5/',
    ];

    private array $pendingUseStatements = [];
    private array $changes              = [];
    private array $manualReviews        = [];

    public function transform(string $content, string $filename = ''): array
    {
        $this->pendingUseStatements = [];
        $this->changes              = [];
        $this->manualReviews        = [];

        $content = $this->replaceJoomlaLegacyClasses($content);
        $content = $this->replaceJ2StoreFactory($content);
        $content = $this->replaceJ2Html($content);
        $content = $this->replaceComponentReferences($content);
        $content = $this->replaceLanguageStrings($content);
        $content = $this->replaceFofFramework($content);
        $content = $this->replaceDatabasePrefixes($content);
        $content = $this->replaceSessionNamespace($content);
        $content = $this->replaceCssSelectors($content);
        $content = $this->replaceProductProperties($content);
        $content = $this->injectUseStatements($content);
        $content = $this->flagJQueryPatterns($content);

        $totalChanges = array_sum(array_column($this->changes, 'count'));

        return [
            'content'       => $content,
            'changes'       => $this->changes,
            'manualReviews' => $this->manualReviews,
            'totalChanges'  => $totalChanges,
        ];
    }

    public function shouldReplaceFile(string $filename): bool
    {
        return isset(self::FILE_REPLACEMENTS[basename($filename)]);
    }

    public function getReplacementFilePath(string $filename): ?string
    {
        if (!$this->shouldReplaceFile($filename)) {
            return null;
        }

        $base      = basename($filename);
        $parentDir = basename(dirname($filename));
        $sourceDir = self::REPLACEMENT_SOURCES[$parentDir] ?? self::REPLACEMENT_SOURCES['bootstrap5'];
        $joomlaRoot = defined('JPATH_ROOT') ? JPATH_ROOT : dirname(__DIR__, 5);

        return $joomlaRoot . '/' . $sourceDir . $base;
    }

    private function replaceJoomlaLegacyClasses(string $content): string
    {
        $before = $content;

        // Exact str_replace for patterns without optional whitespace
        $content = str_replace(
            array_keys(self::JOOMLA_LEGACY_REPLACEMENTS),
            array_values(self::JOOMLA_LEGACY_REPLACEMENTS),
            $content
        );

        // Regex pass for legacy calls with optional whitespace before parentheses
        $spacePatterns = [
            '/\bJText\s*::\s*_\s*\(/'             => 'Text::_(',
            '/\bJText\s*::\s*sprintf\s*\(/'        => 'Text::sprintf(',
            '/\bJRoute\s*::\s*_\s*\(/'             => 'Route::_(',
            '/\bJUri\s*::\s*root\s*\(/'            => 'Uri::root(',
            '/\bJUri\s*::\s*base\s*\(/'            => 'Uri::base(',
            '/\bJUri\s*::\s*current\s*\(/'         => 'Uri::current(',
            '/\bJUri\s*::\s*getInstance\s*\(/'     => 'Uri::getInstance(',
            '/\bJURI\s*::\s*root\s*\(/'            => 'Uri::root(',
            '/\bJURI\s*::\s*base\s*\(/'            => 'Uri::base(',
            '/\bJURI\s*::\s*current\s*\(/'         => 'Uri::current(',
            '/\bJURI\s*::\s*getInstance\s*\(/'     => 'Uri::getInstance(',
            '/\bURI\s*::\s*root\s*\(/'             => 'Uri::root(',
            '/\bURI\s*::\s*current\s*\(/'          => 'Uri::current(',
            '/\bJHTML\s*::\s*_\s*\(/'              => 'HTMLHelper::_(',
            '/\bJHtml\s*::\s*_\s*\(/'              => 'HTMLHelper::_(',
            '/\bJFile\s*::\s*exists\s*\(/'         => 'File::exists(',
            '/\bJFile\s*::\s*copy\s*\(/'           => 'File::copy(',
            '/\bJFile\s*::\s*delete\s*\(/'         => 'File::delete(',
            '/\bJPath\s*::\s*clean\s*\(/'          => 'Path::clean(',
            '/\bJPath\s*::\s*check\s*\(/'          => 'Path::check(',
            '/\bJFactory\s*::\s*getDbo\s*\(/'      => 'Factory::getContainer()->get(DatabaseInterface::class',
            '/\bJFactory\s*::\s*getApplication\s*\(/' => 'Factory::getApplication(',
            '/\bJFactory\s*::\s*getUser\s*\(/'     => 'Factory::getApplication()->getIdentity(',
            '/\bJFactory\s*::\s*getSession\s*\(/'  => 'Factory::getApplication()->getSession(',
            '/\bJFactory\s*::\s*getDocument\s*\(/' => 'Factory::getApplication()->getDocument(',
            '/\bJFactory\s*::\s*getConfig\s*\(/'   => 'Factory::getApplication()->getConfig(',
        ];

        foreach ($spacePatterns as $regex => $replacement) {
            $content = preg_replace($regex, $replacement, $content);
        }

        // Catch-all for any remaining JUri/JURI methods not in the exact map
        $content = preg_replace('/\bJUri\s*::/', 'Uri::', $content);
        $content = preg_replace('/\bJURI\s*::/', 'Uri::', $content);

        // Replace already-migrated Factory:: calls that still use deprecated methods
        $factoryDeprecated = [
            '/\bFactory\s*::\s*getUser\s*\(/'     => 'Factory::getApplication()->getIdentity(',
            '/\bFactory\s*::\s*getDocument\s*\(/' => 'Factory::getApplication()->getDocument(',
            '/\bFactory\s*::\s*getConfig\s*\(/'   => 'Factory::getApplication()->getConfig(',
            '/\bFactory\s*::\s*getDbo\s*\(/'      => 'Factory::getContainer()->get(DatabaseInterface::class',
            '/\bFactory\s*::\s*getSession\s*\(/'  => 'Factory::getApplication()->getSession(',
        ];
        foreach ($factoryDeprecated as $regex => $replacement) {
            $content = preg_replace($regex, $replacement, $content);
        }

        // Handle JLoader::register('FieldsHelper', ...) specifically — schedule use statement
        if (preg_match('/JLoader\s*::\s*register\s*\(\s*[\'"]FieldsHelper[\'"]\s*,/', $content)) {
            $this->pendingUseStatements['Joomla\Component\Fields\Administrator\Helper\FieldsHelper'] = true;
        }

        // Remove JLoader::register() lines
        $content = preg_replace('/^\s*JLoader\s*::\s*register\s*\([^)]+\)\s*;?\s*\n/m', '', $content);

        // Normalize `use \Joomla\` → `use Joomla\`
        $content = preg_replace('/^use \\\\(Joomla\\\\)/m', 'use $1', $content);

        if ($content !== $before) {
            $this->recordChange(1, 'Joomla legacy class replacements');
            $this->scheduleUseStatements($content);
        }

        return $content;
    }

    private function replaceJ2StoreFactory(string $content): string
    {
        $before = $content;

        // Special case: J2Store::product()->validateVariableProduct(...)
        // → ProductHelper::validateVariableProduct(...) (static method in J2Commerce)
        $content = preg_replace(
            '/\bJ2Store\s*::\s*product\s*\(\s*\)\s*->\s*validateVariableProduct\s*\(/',
            'ProductHelper::validateVariableProduct(',
            $content
        );

        // Exact str_replace for common no-space patterns first
        $search  = [];
        $replace = [];

        foreach (self::J2STORE_FACTORY_METHODS as $method) {
            $search[]  = "J2Store::{$method}()";
            $replace[] = "J2CommerceHelper::{$method}()";
        }

        $search[]  = 'J2StoreStrapper::';
        $replace[] = 'StrapperHelper::';

        $content = str_replace($search, $replace, $content);

        // Regex pass for J2Store:: calls with optional whitespace
        foreach (self::J2STORE_FACTORY_METHODS as $method) {
            $content = preg_replace(
                '/\bJ2Store\s*::\s*' . preg_quote($method, '/') . '\s*\(\s*\)/',
                "J2CommerceHelper::{$method}()",
                $content
            );
        }

        // Also handle J2StoreStrapper with optional whitespace
        $content = preg_replace('/\bJ2StoreStrapper\s*::/', 'StrapperHelper::', $content);

        // Normalize extra whitespace in chained method calls after replacement
        $content = preg_replace('/(->\s*\w+)\s+\(/', '$1(', $content);

        if ($content !== $before) {
            $this->recordChange(2, 'J2Store factory method replacements');
            if (str_contains($content, 'J2CommerceHelper::')) {
                $this->pendingUseStatements['J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper'] = true;
            }
            if (str_contains($content, 'StrapperHelper::')) {
                $this->pendingUseStatements['J2Commerce\Component\J2commerce\Administrator\Helper\StrapperHelper'] = true;
            }
            if (str_contains($content, 'ProductHelper::')) {
                $this->pendingUseStatements['J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper'] = true;
            }
        }

        return $content;
    }

    private function replaceJ2Html(string $content): string
    {
        $before = $content;
        $open   = '<' . '?php';
        $close  = '?' . '>';

        // 3-arg: J2Html::hidden('name','value',array('attr'=>'val'))
        $pat3 = '/' . preg_quote($open, '/') . '\s+echo\s+J2Html::hidden\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*array\(([^)]+)\)\s*\)\s*;?\s*' . preg_quote($close, '/') . '/';
        $content = preg_replace_callback($pat3, static function (array $m): string {
            $attrs = '';
            if (preg_match_all("/['\"]([^'\"]+)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $m[3], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $pair) {
                    $attrs .= ' ' . $pair[1] . '="' . $pair[2] . '"';
                }
            }
            return '<input type="hidden" name="' . $m[1] . '" value="' . $m[2] . '"' . $attrs . ' />';
        }, $content);

        // 2-arg static: J2Html::hidden('name','value')
        $pat2    = '/' . preg_quote($open, '/') . '\s+echo\s+J2Html::hidden\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)\s*;?\s*' . preg_quote($close, '/') . '/';
        $content = preg_replace($pat2, '<input type="hidden" name="$1" value="$2" />', $content);

        // 2-arg variable: J2Html::hidden('name', $var)
        $patVar  = '/' . preg_quote($open, '/') . '\s+echo\s+J2Html::hidden\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(\$[a-zA-Z_]\w*(?:->\w+)*)\s*\)\s*;?\s*' . preg_quote($close, '/') . '/';
        $content = preg_replace_callback($patVar, static function (array $m) use ($open, $close): string {
            return '<input type="hidden" name="' . $m[1] . '" value="' . $open . ' echo htmlspecialchars((string) ' . $m[2] . ", ENT_QUOTES, 'UTF-8'); " . $close . '" />';
        }, $content);

        $content = str_replace('value="com_j2store"', 'value="com_j2commerce"', $content);

        if ($content !== $before) {
            $this->recordChange(3, 'J2Html hidden field replacements');
        }

        return $content;
    }

    private function replaceComponentReferences(string $content): string
    {
        $before  = $content;
        $content = str_replace(
            array_keys(self::COMPONENT_EXACT_REPLACEMENTS),
            array_values(self::COMPONENT_EXACT_REPLACEMENTS),
            $content
        );

        $content = preg_replace("/'com_j2store'/", "'com_j2commerce'", $content);
        $content = preg_replace('/"com_j2store"/', '"com_j2commerce"', $content);

        if ($content !== $before) {
            $this->recordChange(4, 'Component reference replacements');
        }

        return $content;
    }

    private function replaceLanguageStrings(string $content): string
    {
        $before  = $content;
        $content = str_replace(
            array_keys(self::LANGUAGE_EXACT_REPLACEMENTS),
            array_values(self::LANGUAGE_EXACT_REPLACEMENTS),
            $content
        );

        $content = preg_replace("/(['\"])J2STORE_/", '${1}COM_J2COMMERCE_', $content);
        $content = preg_replace("/(['\"])PLG_J2STORE_/", '${1}PLG_J2COMMERCE_', $content);

        if ($content !== $before) {
            $this->recordChange(5, 'Language string prefix replacements');
        }

        return $content;
    }

    private function replaceFofFramework(string $content): string
    {
        $before = $content;

        $content = preg_replace(
            "/F0FModel::getTmpInstance\(\s*'([A-Za-z]+)'\s*,\s*'J2StoreModel'\s*\)/",
            "Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()->createModel('\$1', 'Administrator')",
            $content
        );

        $content = preg_replace(
            "/F0FTable::getAnInstance\(\s*'([A-Za-z]+)'\s*,\s*'J2StoreTable'\s*\)/",
            "Factory::getApplication()->bootComponent('com_j2commerce')->getMVCFactory()->createTable('\$1', 'Administrator')",
            $content
        );

        // Remove jimport() lines
        $content = preg_replace('/^\s*jimport\s*\(\s*\'[^\']+\'\s*\)\s*;?\s*\n/m', '', $content);

        if ($content !== $before) {
            $this->recordChange(6, 'FOF 2 framework replacements');
            $this->pendingUseStatements['Joomla\CMS\Factory'] = true;
        }

        return $content;
    }

    private function replaceDatabasePrefixes(string $content): string
    {
        $before = $content;

        $content = str_replace('#__j2store_', '#__j2commerce_', $content);

        // PK column replacements — sort by key length descending to prevent partial matches
        $pkMap = DataTransformer::PK_REPLACEMENTS;
        uksort($pkMap, static fn($a, $b) => strlen($b) - strlen($a));

        $content = str_replace(array_keys($pkMap), array_values($pkMap), $content);

        if ($content !== $before) {
            $this->recordChange(7, 'Database prefix and primary key replacements');
        }

        return $content;
    }

    private function replaceSessionNamespace(string $content): string
    {
        $before = $content;

        foreach (['get', 'set', 'has', 'clear'] as $method) {
            if ($method === 'has' || $method === 'clear') {
                $content = preg_replace(
                    "/(\\\$session->{$method}\s*\([^,)]+),\s*'j2store'\s*\)/",
                    "$1, 'j2commerce')",
                    $content
                );
            } else {
                $content = preg_replace(
                    "/(\\\$session->{$method}\s*\([^,]+,[^,]+),\s*'j2store'\s*\)/",
                    "$1, 'j2commerce')",
                    $content
                );
            }
        }

        if ($content !== $before) {
            $this->recordChange(8, 'Session namespace replacements');
        }

        return $content;
    }

    private function replaceCssSelectors(string $content): string
    {
        $before  = $content;
        $content = str_replace(
            array_keys(self::CSS_EXACT_REPLACEMENTS),
            array_values(self::CSS_EXACT_REPLACEMENTS),
            $content
        );

        // Generic j2store- prefix catch-all
        $content = preg_replace('/\bj2store-([a-z][a-z0-9-]*)/', 'j2commerce-$1', $content);
        $content = str_replace('#j2store-', '#j2commerce-', $content);

        // Bootstrap 4 → Bootstrap 5 data attributes
        $content = str_replace('data-toggle=', 'data-bs-toggle=', $content);
        $content = str_replace('data-dismiss=', 'data-bs-dismiss=', $content);
        $content = str_replace('data-target=', 'data-bs-target=', $content);
        $content = str_replace('data-placement=', 'data-bs-placement=', $content);
        $content = str_replace('data-original-title=', 'data-bs-original-title=', $content);

        if ($content !== $before) {
            $this->recordChange(9, 'CSS class and Bootstrap data attribute replacements');
        }

        return $content;
    }

    private function replaceProductProperties(string $content): string
    {
        $before = $content;

        $content = str_replace('data-product_id=', 'data-product-id=', $content);
        $content = str_replace('data-variant_id=', 'data-variant-id=', $content);
        $content = str_replace('data-option_id=', 'data-option-id=', $content);
        $content = str_replace('data-cart_id=', 'data-cart-id=', $content);
        $content = str_replace('onClick=', 'onclick=', $content);

        if ($content !== $before) {
            $this->recordChange(11, 'Product property and data attribute normalization');
        }

        return $content;
    }

    private function injectUseStatements(string $content): string
    {
        $this->scheduleUseStatements($content);
        $this->ensureUseStatementsForNamespacedClasses($content);

        if (empty($this->pendingUseStatements)) {
            return $content;
        }

        // Find existing use statements
        preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $matches);
        $existing = array_map('trim', $matches[0]);

        $existingClasses = [];
        foreach ($existing as $stmt) {
            if (preg_match('/^use\s+(.+?)(?:\s+as\s+\w+)?;/', $stmt, $m)) {
                $existingClasses[] = trim($m[1]);
            }
        }

        $toInject = [];
        foreach (array_keys($this->pendingUseStatements) as $fqcn) {
            if (!in_array($fqcn, $existingClasses, true)) {
                $toInject[] = "use {$fqcn};";
            }
        }

        if (empty($toInject)) {
            return $content;
        }

        sort($toInject);
        $newUseBlock = implode("\n", $toInject) . "\n";

        if (!empty($existing)) {
            $lastUse = end($existing);
            $pos     = strrpos($content, $lastUse);
            if ($pos !== false) {
                $endOfLine = strpos($content, "\n", $pos);
                if ($endOfLine !== false) {
                    $content = substr($content, 0, $endOfLine + 1) . $newUseBlock . substr($content, $endOfLine + 1);
                }
            }
        } else {
            $content = preg_replace(
                '/(^<\?php\s*\n(?:defined\([^)]+\)[^;]*;\s*\n)?)/s',
                "$1{$newUseBlock}\n",
                $content,
                1
            );
        }

        return $content;
    }

    private function flagJQueryPatterns(string $content): string
    {
        $lines   = explode("\n", $content);
        $output  = [];
        $changed = false;

        foreach ($lines as $lineNum => $line) {
            if (str_contains($line, '@J2COMMERCE_MIGRATION: MANUAL_REVIEW')) {
                $output[] = $line;
                continue;
            }

            // Skip allowed patterns
            if (
                str_contains($line, 'doAjaxFilter()')
                || str_contains($line, 'doAjaxPrice()')
                || str_contains($line, 'doFlexiAjaxPrice()')
                || str_contains($line, '$doc->addCustomTag(')
            ) {
                $output[] = $line;
                continue;
            }

            $flagged = false;
            foreach (self::JQUERY_PATTERNS as $pattern) {
                if (preg_match($pattern['regex'], $line)) {
                    $output[]  = "// @J2COMMERCE_MIGRATION: MANUAL_REVIEW - {$pattern['message']}";
                    $flagged   = true;
                    $changed   = true;

                    $this->manualReviews[] = [
                        'line'        => $lineNum + 1,
                        'pattern'     => $pattern['regex'],
                        'description' => $pattern['message'],
                    ];

                    break;
                }
            }

            $output[] = $line;
        }

        if ($changed) {
            $this->recordChange(11, 'jQuery MANUAL_REVIEW flags inserted');
        }

        return implode("\n", $output);
    }

    private function scheduleUseStatements(string $content): void
    {
        foreach (self::USE_STATEMENT_MAP as $token => $fqcn) {
            if (str_contains($content, $token)) {
                $this->pendingUseStatements[$fqcn] = true;
            }
        }

        foreach (self::USE_STATEMENT_CLASS_MAP as $token => $fqcn) {
            if (str_contains($content, $token)) {
                $this->pendingUseStatements[$fqcn] = true;
            }
        }
    }

    private function ensureUseStatementsForNamespacedClasses(string $content): void
    {
        $classToFqcn = [
            'Text'              => 'Joomla\CMS\Language\Text',
            'Route'             => 'Joomla\CMS\Router\Route',
            'Uri'               => 'Joomla\CMS\Uri\Uri',
            'HTMLHelper'        => 'Joomla\CMS\HTML\HTMLHelper',
            'Factory'           => 'Joomla\CMS\Factory',
            'DatabaseInterface' => 'Joomla\Database\DatabaseInterface',
            'File'              => 'Joomla\Filesystem\File',
            'Path'              => 'Joomla\Filesystem\Path',
            'Registry'          => 'Joomla\Registry\Registry',
            'Pagination'        => 'Joomla\CMS\Pagination\Pagination',
            'Session'           => 'Joomla\CMS\Session\Session',
            'Table'             => 'Joomla\CMS\Table\Table',
            'LayoutHelper'      => 'Joomla\CMS\Layout\LayoutHelper',
            'ComponentHelper'   => 'Joomla\CMS\Component\ComponentHelper',
            'PluginHelper'      => 'Joomla\CMS\Plugin\PluginHelper',
            'Input'             => 'Joomla\CMS\Input\Input',
            'Log'               => 'Joomla\CMS\Log\Log',
            'FieldsHelper'      => 'Joomla\Component\Fields\Administrator\Helper\FieldsHelper',
            'J2CommerceHelper'  => 'J2Commerce\Component\J2commerce\Administrator\Helper\J2CommerceHelper',
            'StrapperHelper'    => 'J2Commerce\Component\J2commerce\Administrator\Helper\StrapperHelper',
            'ProductHelper'     => 'J2Commerce\Component\J2commerce\Administrator\Helper\ProductHelper',
        ];

        // Collect existing use statements
        preg_match_all('/^use\s+(.+?)(?:\s+as\s+\w+)?;\s*$/m', $content, $matches);
        $existingFqcns = array_map('trim', $matches[1]);

        foreach ($classToFqcn as $shortName => $fqcn) {
            if (isset($this->pendingUseStatements[$fqcn])) {
                continue;
            }
            if (in_array($fqcn, $existingFqcns, true)) {
                continue;
            }

            if (preg_match('/\b' . preg_quote($shortName, '/') . '\s*(?:::|::class\b)/', $content)
                || preg_match('/\bnew\s+' . preg_quote($shortName, '/') . '\b/', $content)) {
                $this->pendingUseStatements[$fqcn] = true;
            }
        }
    }

    private function recordChange(int $category, string $description): void
    {
        foreach ($this->changes as &$change) {
            if ($change['category'] === $category) {
                $change['count']++;
                return;
            }
        }
        unset($change);

        $this->changes[] = ['category' => $category, 'description' => $description, 'count' => 1];
    }
}
