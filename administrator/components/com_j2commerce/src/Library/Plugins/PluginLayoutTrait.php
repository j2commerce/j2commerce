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
        $tpl     = Factory::getApplication()->getTemplate();
        $group   = $this->_type;
        $element = $this->_name;

        $overrideRoot = JPATH_ROOT . '/templates/' . $tpl . '/html/plg_' . $group . '_' . $element;
        $pluginTmpl   = JPATH_PLUGINS . '/' . $group . '/' . $element . '/tmpl';

        // Resolution order: per-plugin override -> global component default -> bootstrap5/first folder.
        $subtemplate = (string) $this->params->get('subtemplate', '');

        if ($subtemplate === '') {
            $subtemplate = (string) ComponentHelper::getParams('com_j2commerce')->get('subtemplate', '');
        }

        if ($subtemplate === ''
            || (!is_dir($overrideRoot . '/' . $subtemplate) && !is_dir($pluginTmpl . '/' . $subtemplate))) {
            $subtemplate = $this->defaultSubtemplate($pluginTmpl);
        }

        $paths = [];

        if ($subtemplate !== '') {
            $paths[] = $overrideRoot . '/' . $subtemplate;
            $paths[] = $pluginTmpl . '/' . $subtemplate;
        }

        $paths[] = $overrideRoot;
        $paths[] = $pluginTmpl;

        $layout = new FileLayout($name);
        $layout->setIncludePaths($paths);

        return $layout->render((array) $data);
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
