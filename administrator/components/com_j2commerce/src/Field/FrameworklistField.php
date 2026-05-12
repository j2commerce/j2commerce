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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class FrameworklistField extends ListField
{
    protected $type = 'Frameworklist';

    protected function getOptions(): array
    {
        $viewContext = (string) ($this->element['view_context'] ?? '');
        $options     = [];

        if ($viewContext === '') {
            return $options;
        }

        $folders = [];

        $compPath = JPATH_ROOT . '/components/com_j2commerce/tmpl/' . $viewContext;
        foreach ($this->getFrameworkFolders($compPath) as $folder) {
            $folders[$folder] = true;
        }

        $template = $this->getActiveSiteTemplate();
        if ($template !== null) {
            $tplPath = JPATH_THEMES . '/' . $template . '/html/com_j2commerce/' . $viewContext;
            foreach ($this->getFrameworkFolders($tplPath) as $folder) {
                $folders[$folder] = true;
            }
        }

        $names = array_keys($folders);
        sort($names);

        foreach ($names as $name) {
            $options[] = HTMLHelper::_('select.option', $name, self::humanize($name));
        }

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * Derive a display label from the folder name. No hardcoded framework
     * list — works for any folder a third party drops in.
     *
     * Examples:
     *   bootstrap5  -> "Bootstrap 5"
     *   uikit3      -> "Uikit 3"
     *   tailwind_4  -> "Tailwind 4"
     *   custom-grid -> "Custom Grid"
     */
    private static function humanize(string $folder): string
    {
        $spaced = str_replace(['_', '-'], ' ', $folder);
        $spaced = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $spaced) ?? $spaced;
        $spaced = preg_replace('/(\d)([A-Za-z])/', '$1 $2', $spaced) ?? $spaced;
        $spaced = trim((string) preg_replace('/\s+/', ' ', $spaced));

        return ucwords($spaced);
    }

    private function getFrameworkFolders(string $path): array
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
            $full = $path . '/' . $item;
            if (is_dir($full) && is_file($full . '/default.php')) {
                $folders[] = $item;
            }
        }

        return $folders;
    }

    private function getActiveSiteTemplate(): ?string
    {
        try {
            $db       = Factory::getContainer()->get(DatabaseInterface::class);
            $clientId = 0;
            $home     = '1';
            $query    = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = :clientId')
                ->where($db->quoteName('home') . ' = :home')
                ->bind(':clientId', $clientId, ParameterType::INTEGER)
                ->bind(':home', $home, ParameterType::STRING);
            $db->setQuery($query);
            return $db->loadResult() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
