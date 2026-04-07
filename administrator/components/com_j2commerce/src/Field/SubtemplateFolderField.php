<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commerce\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

class SubtemplateFolderField extends ListField
{
    protected $type = 'SubtemplateFolder';

    public function getOptions(): array
    {
        $options  = parent::getOptions();
        $tmplPath = (string) ($this->element['tmpl_path'] ?? '');

        if ($tmplPath === '') {
            return $options;
        }

        $fullPath = Path::clean(JPATH_ROOT . '/' . $tmplPath);

        if (!is_dir($fullPath)) {
            return $options;
        }

        $folders = Folder::folders($fullPath, '.', false, false);

        foreach ($folders as $folder) {
            $options[] = HTMLHelper::_('select.option', $folder, $this->folderToDisplayName($folder));
        }

        $overrideFolders = $this->getTemplateOverrideFolders($tmplPath);

        foreach ($overrideFolders as $folder => $displayName) {
            $exists = false;

            foreach ($options as $opt) {
                if ($opt->value === $folder) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $options[] = HTMLHelper::_('select.option', $folder, $displayName . ' (Override)');
            }
        }

        return $options;
    }

    private function folderToDisplayName(string $folder): string
    {
        $map = [
            'bootstrap5'  => 'Bootstrap 5',
            'bootstrap_5' => 'Bootstrap 5',
            'uikit'       => 'UIkit',
            'uikit3'      => 'UIkit 3',
        ];

        if (isset($map[strtolower($folder)])) {
            return $map[strtolower($folder)];
        }

        $name = str_replace('_', ' ', $folder);
        $name = preg_replace('/([a-z])(\d)/', '$1 $2', $name);

        return ucwords($name);
    }

    private function getTemplateOverrideFolders(string $tmplPath): array
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
            $db->setQuery($query);
            $template = (string) $db->loadResult();
        } else {
            $template = $app->getTemplate();
        }

        if ($template === '') {
            return [];
        }

        // Derive the plugin override folder name from the tmpl_path.
        // e.g. "plugins/system/j2commerce_advancedcart/tmpl" → "plg_system_j2commerce_advancedcart"
        $pathParts = explode('/', str_replace('\\', '/', $tmplPath));
        $pluginKey = array_search('plugins', $pathParts, true);

        if ($pluginKey === false || !isset($pathParts[$pluginKey + 1], $pathParts[$pluginKey + 2])) {
            return [];
        }

        $group        = $pathParts[$pluginKey + 1];
        $element      = $pathParts[$pluginKey + 2];
        $overrideBase = JPATH_ROOT . '/templates/' . $template . '/html/plg_' . $group . '_' . $element;

        if (!is_dir($overrideBase)) {
            return [];
        }

        $folders = Folder::folders($overrideBase, '.', false, false);
        $result  = [];

        foreach ($folders as $folder) {
            $result[$folder] = $this->folderToDisplayName($folder);
        }

        return $result;
    }
}
