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

use Joomla\CMS\Event\GenericEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;

class PluginsubtemplateField extends ListField
{
    protected $type = 'Pluginsubtemplate';

    /**
     * Functional directories that are not layout subtemplates.
     */
    private const EXCLUDED_DIRS = ['admin', 'application', 'confirmation', 'dashboard', 'email'];

    public function getOptions(): array
    {
        $options = parent::getOptions();

        $group   = (string) ($this->element['plugin_group'] ?? '');
        $element = (string) ($this->element['plugin_element'] ?? '');

        if (empty($group) || empty($element)) {
            return $options;
        }

        // An empty value inherits the component's default subtemplate (see PluginLayoutTrait).
        array_unshift($options, HTMLHelper::_('select.option', '', Text::_('JDEFAULT')));

        $folders = [];

        // Enabled theme apps (bootstrap5, uikit, ...) are always selectable so a template
        // override under templates/<tpl>/html/plg_<group>_<element>/<theme>/ can be targeted
        // even when the plugin ships flat templates.
        PluginHelper::importPlugin('j2commerce');
        $event = new GenericEvent('onJ2CommerceTemplateFolderList', ['folders' => [], 'view_context' => '']);
        Factory::getApplication()->getDispatcher()->dispatch('onJ2CommerceTemplateFolderList', $event);

        foreach ($event->getArgument('folders', []) as $entry) {
            $name = \is_string($entry) ? $entry : ($entry['name'] ?? '');

            if ($name !== '' && !str_starts_with($name, 'tag_') && !str_starts_with($name, 'categories_')) {
                $folders[$name] = true;
            }
        }

        // Scan plugin tmpl/ subdirectories — only include layout subtemplates.
        $pluginTmpl = JPATH_PLUGINS . '/' . $group . '/' . $element . '/tmpl';

        if (is_dir($pluginTmpl)) {
            foreach (new \DirectoryIterator($pluginTmpl) as $entry) {
                if ($entry->isDir() && !$entry->isDot()
                    && !\in_array($entry->getFilename(), self::EXCLUDED_DIRS, true)) {
                    $folders[$entry->getFilename()] = true;
                }
            }
        }

        // Scan active template override subdirectories.
        $tpl          = Factory::getApplication()->getTemplate();
        $overrideDir  = JPATH_ROOT . '/templates/' . $tpl . '/html/plg_' . $group . '_' . $element;

        if (is_dir($overrideDir)) {
            foreach (new \DirectoryIterator($overrideDir) as $entry) {
                if ($entry->isDir() && !$entry->isDot()
                    && !\in_array($entry->getFilename(), self::EXCLUDED_DIRS, true)) {
                    $folders[$entry->getFilename()] = true;
                }
            }
        }

        ksort($folders);

        foreach (array_keys($folders) as $folder) {
            $options[] = HTMLHelper::_('select.option', $folder, ucfirst($folder));
        }

        return $options;
    }
}
