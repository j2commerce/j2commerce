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

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\AdapterHelper;
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
     * Returns adapter plugins merged with live adapter metadata (source info, runtime status).
     *
     * Each item shape matches what tmpl/plugins/default.php expects:
     *   extensionId, key, title, description, icon, author,
     *   status, enabled, prerequisiteErrors, lastRunStatus
     */
    public function getMergedAdapterList(): array
    {
        $plugins  = $this->getAdapterPlugins();
        $registry = new AdapterRegistry();
        $adapters = $registry->getAll();

        // Index plugins by element key for O(1) lookup.
        $pluginMap = [];

        foreach ($plugins as $plugin) {
            $pluginMap[$plugin->element] = $plugin;
        }

        $merged = [];

        foreach ($adapters as $key => $adapter) {
            $plugin      = $pluginMap[$key] ?? null;
            $extensionId = $plugin !== null ? (int) $plugin->extension_id : 0;
            $enabled     = $plugin !== null && (bool) $plugin->enabled;

            $merged[] = AdapterHelper::enrichAdapter($adapter, $extensionId, $enabled);
        }

        return $merged;
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
