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

use Joomla\CMS\Form\Field\FolderlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

class ImageDirectoryField extends FolderlistField
{
    protected $type = 'ImageDirectory';

    protected function getOptions(): array
    {
        $this->recursive = true;
        $this->hideNone  = true;

        $options = [];
        $seen    = [];

        foreach ($this->getLocalRoots() as $root) {
            if (isset($seen[$root])) {
                continue;
            }

            $seen[$root] = true;
            $options[]   = HTMLHelper::_('select.option', $root, $root);

            $this->directory = $root;

            foreach (parent::getOptions() as $option) {
                if ($option->value === '' || $option->value === '-1') {
                    continue;
                }

                $fullValue = $root . '/' . ltrim((string) $option->value, '/');

                if (isset($seen[$fullValue])) {
                    continue;
                }

                $seen[$fullValue] = true;
                $option->value    = $fullValue;
                $option->text     = $root . '/' . ltrim((string) $option->text, '/');
                $options[]        = $option;
            }
        }

        return $options;
    }

    /**
     * Get available local filesystem roots from the Joomla local filesystem plugin.
     */
    private function getLocalRoots(): array
    {
        $roots = ['images', 'files'];

        $plugin = PluginHelper::getPlugin('filesystem', 'local');

        if (empty($plugin) || empty($plugin->params)) {
            return $roots;
        }

        $params = new Registry($plugin->params);

        $directories = $params->get('directories');

        if (is_string($directories)) {
            $decoded = json_decode($directories, true);
            $directories = is_array($decoded) ? $decoded : $directories;
        }

        if (is_array($directories) || is_object($directories)) {
            foreach ((array) $directories as $value) {
                $this->appendRoot($roots, $value);
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * Normalize and append a root path if it points to an existing local directory.
     */
    private function appendRoot(array &$roots, mixed $value): void
    {
        if (is_array($value) || is_object($value)) {
            $items = (array) $value;

            if (isset($items['directory'])) {
                $this->appendRoot($roots, $items['directory']);
            }

            foreach ($items as $item) {
                if ($item === $value) {
                    continue;
                }

                $this->appendRoot($roots, $item);
            }

            return;
        }

        if (!is_string($value) || trim($value) === '') {
            return;
        }

        $candidate = str_replace('\\', '/', trim($value));

        if (preg_match('#^[A-Za-z]:[/\\\\]#', $candidate) || str_starts_with($candidate, '/')) {
            $rootPath   = str_replace('\\', '/', Path::clean((string) JPATH_ROOT));
            $cleanValue = str_replace('\\', '/', Path::clean($candidate));

            if (!str_starts_with($cleanValue, $rootPath . '/')) {
                return;
            }

            $candidate = ltrim(substr($cleanValue, strlen($rootPath)), '/');
        }

        $candidate = trim($candidate, '/');

        if ($candidate === '') {
            return;
        }

        if (!is_dir(JPATH_ROOT . '/' . $candidate)) {
            return;
        }

        $roots[] = $candidate;
    }
}
