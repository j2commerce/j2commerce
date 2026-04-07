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

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;

trait PluginLayoutTrait
{
    protected function resolvePluginLayout(string $name, array|object $data): string
    {
        $subtemplate = (string) $this->params->get('subtemplate', '');
        $tpl         = Factory::getApplication()->getTemplate();
        $group       = $this->_type;
        $element     = $this->_name;

        $overrideRoot  = JPATH_ROOT . '/templates/' . $tpl . '/html/plg_' . $group . '_' . $element;
        $pluginTmpl    = JPATH_PLUGINS . '/' . $group . '/' . $element . '/tmpl';

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
}
