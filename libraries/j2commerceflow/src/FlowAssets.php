<?php

/**
 * @package     J2Commerce
 * @subpackage  lib_j2commerceflow
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Library\Flow;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;

/**
 * Registers the vendored AntV X6 canvas engine, its opt-in official 2.x plugin set, and the
 * J2CFlow wrapper (window.J2CFlow) via the Web Asset Manager. Consumers call
 * FlowAssets::register() once per flow-shaped admin screen, optionally naming which official
 * X6 plugins that screen wants loaded (they only WIRE themselves inside j2c-flow.js when their
 * UMD global is actually present — see j2c-flow.js's registerPlugins()).
 */
final class FlowAssets
{
    private const ASSET_PREFIX = 'lib_j2commerceflow';

    /** name => [js filename (without .min.js), has css] */
    private const PLUGIN_ALLOWLIST = [
        'history'   => ['file' => 'x6-plugin-history', 'css' => false],
        'snapline'  => ['file' => 'x6-plugin-snapline', 'css' => true],
        'selection' => ['file' => 'x6-plugin-selection', 'css' => true],
        'keyboard'  => ['file' => 'x6-plugin-keyboard', 'css' => false],
        'minimap'   => ['file' => 'x6-plugin-minimap', 'css' => true],
        'scroller'  => ['file' => 'x6-plugin-scroller', 'css' => true],
        'stencil'   => ['file' => 'x6-plugin-stencil', 'css' => true],
        'dnd'       => ['file' => 'x6-plugin-dnd', 'css' => true],
        'export'    => ['file' => 'x6-plugin-export', 'css' => false],
        'transform' => ['file' => 'x6-plugin-transform', 'css' => true],
        'dagre'     => ['file' => null, 'css' => false],
    ];

    private static bool $coreRegistered = false;

    /** @var array<string, bool> */
    private static array $pluginsRegistered = [];

    /**
     * @param string[] $plugins Official X6 2.x plugin names to opt into (see PLUGIN_ALLOWLIST keys).
     *
     * @throws \InvalidArgumentException When a name is not in the allowlist.
     */
    public static function register(array $plugins = []): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        self::registerCore($wa);

        foreach ($plugins as $plugin) {
            self::registerPlugin($wa, $plugin);
        }
    }

    private static function registerCore(WebAssetManager $wa): void
    {
        if (self::$coreRegistered) {
            return;
        }

        // The library's LIB_J2COMMERCEFLOW_* strings are used both by Text::script() below and
        // by consumer templates (e.g. toolbar button labels), so load them here — falling back
        // to the library's own language folder because a discover-install does not copy
        // manifest <languages> files into the site language directory.
        $language = Factory::getApplication()->getLanguage();
        $language->load('lib_j2commerceflow', JPATH_SITE)
            || $language->load('lib_j2commerceflow', JPATH_LIBRARIES . '/j2commerceflow');

        $wa->registerAndUseStyle(
            self::ASSET_PREFIX . '.x6',
            'media/lib_j2commerceflow/css/vendor/x6.css'
        );
        $wa->registerAndUseScript(
            self::ASSET_PREFIX . '.x6',
            'media/lib_j2commerceflow/js/vendor/x6/x6.min.js',
            [],
            ['defer' => true]
        );
        $wa->registerAndUseStyle(
            self::ASSET_PREFIX . '.j2c-flow',
            'media/lib_j2commerceflow/css/j2c-flow.css'
        );
        $wa->registerAndUseScript(
            self::ASSET_PREFIX . '.j2c-flow',
            'media/lib_j2commerceflow/js/j2c-flow.js',
            [],
            ['defer' => true],
            [self::ASSET_PREFIX . '.x6']
        );

        Text::script('LIB_J2COMMERCEFLOW_UNDO');
        Text::script('LIB_J2COMMERCEFLOW_REDO');
        Text::script('LIB_J2COMMERCEFLOW_TIDY_LAYOUT');

        self::$coreRegistered = true;
    }

    private static function registerPlugin(WebAssetManager $wa, string $plugin): void
    {
        if (!isset(self::PLUGIN_ALLOWLIST[$plugin])) {
            throw new \InvalidArgumentException(
                \sprintf('Unknown X6 plugin "%s" requested via FlowAssets::register().', $plugin)
            );
        }

        if (!empty(self::$pluginsRegistered[$plugin])) {
            return;
        }

        if ($plugin === 'dagre') {
            $wa->registerAndUseScript(
                self::ASSET_PREFIX . '.dagre',
                'media/lib_j2commerceflow/js/vendor/dagre/dagre.min.js',
                [],
                ['defer' => true]
            );

            self::$pluginsRegistered[$plugin] = true;

            return;
        }

        $file = self::PLUGIN_ALLOWLIST[$plugin]['file'];

        if (self::PLUGIN_ALLOWLIST[$plugin]['css']) {
            $wa->registerAndUseStyle(
                self::ASSET_PREFIX . '.' . $plugin,
                'media/lib_j2commerceflow/js/vendor/x6/plugins/' . $file . '.css'
            );
        }

        $wa->registerAndUseScript(
            self::ASSET_PREFIX . '.' . $plugin,
            'media/lib_j2commerceflow/js/vendor/x6/plugins/' . $file . '.min.js',
            [],
            ['defer' => true],
            [self::ASSET_PREFIX . '.x6']
        );

        self::$pluginsRegistered[$plugin] = true;
    }
}
