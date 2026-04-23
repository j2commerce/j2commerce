<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Model;

defined('_JEXEC') or die;

use J2Commerce\Component\J2commercemigrator\Administrator\Service\AdapterRegistry;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Plugins model — lists j2commercemigrator-group plugins and their publish state.
 * Delegates actual publish/unpublish to the standard com_plugins model.
 */
class PluginsModel extends BaseDatabaseModel
{
    /** Returns all plugins in the j2commercemigrator group with their enabled state. */
    public function getAdapterPlugins(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'name', 'element', 'enabled', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->bind(':type', 'plugin')
            ->bind(':folder', 'j2commercemigrator')
            ->order($db->quoteName('name') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Returns adapter plugins merged with live adapter metadata (source info, tier count).
     * Plugins that are not yet activated appear in the list as disabled.
     */
    public function getMergedAdapterList(): array
    {
        $plugins  = $this->getAdapterPlugins();
        $registry = new AdapterRegistry();
        $adapters = $registry->getAll();

        $merged = [];

        foreach ($plugins as $plugin) {
            $key    = $plugin->element;
            $merged[$key] = [
                'extension_id' => (int) $plugin->extension_id,
                'name'         => $plugin->name,
                'element'      => $key,
                'enabled'      => (bool) $plugin->enabled,
                'adapter'      => $adapters[$key] ?? null,
            ];
        }

        return array_values($merged);
    }

    /** Returns all j2commercemigrator plugins that are currently enabled. */
    public function getEnabledPluginIds(): array
    {
        $db     = $this->getDatabase();
        $folder = 'j2commercemigrator';
        $type   = 'plugin';

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('enabled') . ' = :enabled')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':enabled', 1, ParameterType::INTEGER);

        return array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
    }
}
