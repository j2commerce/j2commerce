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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class TemplatelistField extends ListField
{
    protected $type = 'Templatelist';

    protected function getOptions(): array
    {
        $options     = [];
        $emptyLabel  = (string) ($this->element['empty_label'] ?? 'COM_J2COMMERCE_USE_DEFAULT');
        $options[]   = HTMLHelper::_('select.option', '', Text::_($emptyLabel));
        $viewContext = (string) ($this->element['view_context'] ?? '');

        // Source 1: Plugin event
        PluginHelper::importPlugin('j2commerce');
        $app        = Factory::getApplication();
        $dispatcher = $app->getDispatcher();
        $event      = new GenericEvent('onJ2CommerceTemplateFolderList', [
            'folders'      => [],
            'view_context' => $viewContext,
        ]);
        $dispatcher->dispatch('onJ2CommerceTemplateFolderList', $event);
        $pluginFolders = $event->getArgument('folders', []);

        // Normalize plugin results to associative arrays with context info
        $subtemplates = [];
        foreach ($pluginFolders as $entry) {
            if (\is_string($entry)) {
                // Backward compat: bare string = all contexts
                $name = $entry;
                if (!isset($subtemplates[$name])) {
                    $subtemplates[$name] = ['name' => $name, 'contexts' => []];
                }
            } elseif (\is_array($entry) && isset($entry['name'])) {
                $name = $entry['name'];
                if (isset($subtemplates[$name])) {
                    $subtemplates[$name]['contexts'] = array_unique(array_merge(
                        $subtemplates[$name]['contexts'],
                        $entry['contexts'] ?? []
                    ));
                } else {
                    $subtemplates[$name] = $entry;
                }
            }
        }

        // Source 2: Template override directories
        $template = $this->getActiveSiteTemplate();
        if ($template !== null) {
            $overridePath = JPATH_SITE . '/templates/' . $template . '/html/com_j2commerce/templates';
            if (is_dir($overridePath)) {
                foreach ($this->getFoldersFromPath($overridePath) as $folder) {
                    $contexts = $this->detectContextsFromDirectory($overridePath . '/' . $folder);
                    if (!isset($subtemplates[$folder])) {
                        $subtemplates[$folder] = ['name' => $folder, 'contexts' => $contexts];
                    } else {
                        $subtemplates[$folder]['contexts'] = array_unique(array_merge(
                            $subtemplates[$folder]['contexts'],
                            $contexts
                        ));
                    }
                }
            }
        }

        // Filter by view context
        if ($viewContext !== '') {
            $subtemplates = array_filter(
                $subtemplates,
                static fn (array $entry): bool => empty($entry['contexts']) || \in_array($viewContext, $entry['contexts'], true)
            );
        }

        $stripPrefix = (string) ($this->element['strip_prefix'] ?? '') === 'true';

        // With strip_prefix, enforce strict name-based filtering
        if ($stripPrefix) {
            if ($viewContext === 'categories') {
                // Only show categories_* templates
                $subtemplates = array_filter(
                    $subtemplates,
                    static fn (array $entry): bool => str_starts_with($entry['name'], 'categories_')
                );
            } elseif ($viewContext === 'producttags') {
                // Only show tag_* templates
                $subtemplates = array_filter(
                    $subtemplates,
                    static fn (array $entry): bool => str_starts_with($entry['name'], 'tag_')
                );
            } elseif ($viewContext === 'products') {
                // Filter out categories_* and tag_* folders (not product list subtemplates)
                $subtemplates = array_filter(
                    $subtemplates,
                    static fn (array $entry): bool => !str_starts_with($entry['name'], 'categories_') && !str_starts_with($entry['name'], 'tag_')
                );
            }
        }

        // Sort and build options
        $names = array_column($subtemplates, 'name');
        sort($names);
        foreach ($names as $name) {
            $label = $name;
            if ($stripPrefix) {
                if ($viewContext === 'categories' && str_starts_with($name, 'categories_')) {
                    $label = substr($name, \strlen('categories_'));
                } elseif ($viewContext === 'producttags' && str_starts_with($name, 'tag_')) {
                    $label = substr($name, \strlen('tag_'));
                }
            }
            $options[] = HTMLHelper::_('select.option', $name, $label);
        }

        return array_merge(parent::getOptions(), $options);
    }

    private function getActiveSiteTemplate(): ?string
    {
        try {
            $db       = Factory::getContainer()->get(DatabaseInterface::class);
            $clientId = 0;
            $home     = 1;
            $query    = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = :clientId')
                ->where($db->quoteName('home') . ' = :home')
                ->bind(':clientId', $clientId, ParameterType::INTEGER)
                ->bind(':home', $home, ParameterType::INTEGER);
            $db->setQuery($query);
            return $db->loadResult() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function detectContextsFromDirectory(string $dirPath): array
    {
        $contexts = [];
        if (is_file($dirPath . '/default.php')) {
            $contexts[] = 'products';
            $contexts[] = 'producttags';
            $contexts[] = 'categories';
        }
        if (is_file($dirPath . '/view.php')) {
            $contexts[] = 'product';
        }
        return $contexts;
    }

    private function getFoldersFromPath(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $items = scandir($path);
        if ($items === false) {
            return [];
        }

        $folders = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }
            if (is_dir($path . '/' . $item)) {
                $folders[] = $item;
            }
        }

        return $folders;
    }
}
