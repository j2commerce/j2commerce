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
use Joomla\CMS\Router\Route;

class ConfigSubtemplateField extends ListField
{
    protected $type = 'ConfigSubtemplate';

    private bool $hasPluginOptions = false;

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        PluginHelper::importPlugin('j2commerce');
        $dispatcher = Factory::getApplication()->getDispatcher();
        $event      = new GenericEvent('onJ2CommerceTemplateFolderList', [
            'folders'      => [],
            'view_context' => '',
        ]);
        $dispatcher->dispatch('onJ2CommerceTemplateFolderList', $event);
        $pluginFolders = $event->getArgument('folders', []);

        $seen = [];
        foreach ($pluginFolders as $entry) {
            $name = \is_string($entry) ? $entry : ($entry['name'] ?? '');
            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            // Skip view-specific subtemplates — only show primary themes
            if (str_starts_with($name, 'tag_') || str_starts_with($name, 'categories_')) {
                continue;
            }

            $seen[$name] = true;
            $label       = ucfirst(str_replace(['app_', '_'], ['', ' '], $name));
            $options[]   = HTMLHelper::_('select.option', $name, $label);
        }

        $this->hasPluginOptions = \count($seen) > 0;

        return $options;
    }

    protected function getInput(): string
    {
        // Ensure getOptions() has run so hasPluginOptions is set
        if (!$this->hasPluginOptions) {
            $this->getOptions();
        }

        if (!$this->hasPluginOptions) {
            $appsUrl = Route::_('index.php?option=com_j2commerce&view=apps', false);

            return '<div class="alert alert-danger">'
                . Text::_('COM_J2COMMERCE_CONFIG_SUBTEMPLATE_NO_APPS')
                . ' <a href="' . $appsUrl . '" class="alert-link">'
                . Text::_('COM_J2COMMERCE_CONFIG_SUBTEMPLATE_MANAGE_APPS')
                . '</a></div>';
        }

        return parent::getInput();
    }
}
