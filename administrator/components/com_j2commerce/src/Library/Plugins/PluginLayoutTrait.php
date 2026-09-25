<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Library\Plugins;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;

trait PluginLayoutTrait
{
    /**
     * Functional directories that are not layout subtemplates.
     */
    private const SUBTEMPLATE_EXCLUDED_DIRS = ['admin', 'application', 'confirmation', 'dashboard', 'email'];

    protected function resolvePluginLayout(string $name, array|object $data): string
    {
        // Core's tpl:layout prefix and its tmpl/default.php fallback are deliberately not reproduced.
        $layout = new FileLayout($name);
        $layout->setIncludePaths($this->pluginLayoutIncludePaths());

        return $layout->render((array) $data);
    }

    /** setIncludePaths, not add: FileLayout's defaults search html/layouts, never html/plg_<group>_<element>. */
    private function pluginLayoutIncludePaths(): array
    {
        $app = Factory::getApplication();

        // Same guard as PluginHelper::getLayoutPath() — ConsoleApplication has no getTemplate().
        $template = ($app->isClient('site') || $app->isClient('administrator'))
            ? $app->getTemplate(true)
            : (object) ['template' => '', 'parent' => ''];

        $pluginTmpl = JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/tmpl';

        // Rung order per PluginHelper::getLayoutPath(): child template, parent template, plugin tmpl.
        $roots = [];

        foreach ([$template->template, $template->parent] as $tpl) {
            if ((string) $tpl !== '') {
                $roots[] = JPATH_ROOT . '/templates/' . $tpl . '/html/plg_' . $this->_type . '_' . $this->_name;
            }
        }

        $roots[] = $pluginTmpl;

        $subtemplate = $this->resolveSubtemplate($roots, $pluginTmpl);
        $paths       = [];

        // The subtemplate folder is an inner preference within each rung, so a site
        // override outranks the plugin's own copy at every level.
        foreach ($roots as $root) {
            if ($subtemplate !== '') {
                $paths[] = $root . '/' . $subtemplate;
            }

            $paths[] = $root;
        }

        return $paths;
    }

    /** Any rung may ship the folder, so a site can introduce a subtemplate the plugin does not. */
    private function resolveSubtemplate(array $roots, string $pluginTmpl): string
    {
        $subtemplate = (string) $this->params->get('subtemplate', '');

        if ($subtemplate === '') {
            $subtemplate = (string) ComponentHelper::getParams('com_j2commerce')->get('subtemplate', '');
        }

        if ($subtemplate !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $subtemplate) === 1) {
            foreach ($roots as $root) {
                if (is_dir($root . '/' . $subtemplate)) {
                    return $subtemplate;
                }
            }
        }

        return $this->defaultSubtemplate($pluginTmpl);
    }

    /**
     * Resolve the layout subtemplate to use when none is configured: prefer
     * bootstrap5, otherwise the first non-functional subfolder, else none.
     */
    private function defaultSubtemplate(string $pluginTmpl): string
    {
        if (is_dir($pluginTmpl . '/bootstrap5')) {
            return 'bootstrap5';
        }

        if (!is_dir($pluginTmpl)) {
            return '';
        }

        $folders = [];

        foreach (new \DirectoryIterator($pluginTmpl) as $entry) {
            if ($entry->isDir() && !$entry->isDot()
                && !\in_array($entry->getFilename(), self::SUBTEMPLATE_EXCLUDED_DIRS, true)) {
                $folders[] = $entry->getFilename();
            }
        }

        sort($folders);

        return $folders[0] ?? '';
    }
}
