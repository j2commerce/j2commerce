<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class Com_J2commercemigratorInstallerScript implements InstallerScriptInterface
{
    private $minimumJoomla = '5.0';
    private $minimumPhp    = '8.2.0';

    public function install(InstallerAdapter $adapter): bool
    {
        $this->migrateLegacyIdmap();
        $this->emitMigrationToolNotice();

        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        $this->migrateLegacyIdmap();

        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCEMIGRATOR_ERR_REQUIRES_PHP', $this->minimumPhp),
                'error'
            );

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('COM_J2COMMERCEMIGRATOR_ERR_REQUIRES_JOOMLA', $this->minimumJoomla),
                'error'
            );

            return false;
        }

        if (!$this->isJ2CommerceInstalled()) {
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_J2COMMERCEMIGRATOR_ERR_REQUIRES_J2COMMERCE'),
                'error'
            );

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    private function isJ2CommerceInstalled(): bool
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = 'com_j2commerce';
        $type    = 'component';
        $query   = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->bind(':element', $element)
            ->bind(':type', $type);

        $db->setQuery($query);

        return (bool) $db->loadResult();
    }

    /**
     * Emit a notice when the legacy system plugin is still present, directing
     * admins to the new component.  Read-only probe only — the plugin is never
     * modified, disabled, or uninstalled.
     */
    private function emitMigrationToolNotice(): void
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = 'j2commerce_migration_tool';
        $type    = 'plugin';
        $folder  = 'system';

        $query = $db->getQuery(true)
            ->select($db->quoteName('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . ' = :element')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->bind(':element', $element)
            ->bind(':type', $type)
            ->bind(':folder', $folder);

        $db->setQuery($query);
        $enabled = $db->loadResult();

        if ($enabled === null) {
            // Old plugin not installed — no notice needed.
            return;
        }

        Factory::getApplication()->enqueueMessage(
            Text::_('COM_J2COMMERCEMIGRATOR_MSG_LEGACY_PLUGIN_DETECTED')
                ?: 'The old migration system plugin is still installed. Use the new J2Commerce Migrator component (Components → J2Commerce Migrator) for all future migrations.',
            'notice'
        );
    }

    /**
     * Copies rows from the legacy #__j2commerce_migration_idmap table (created by the
     * old system plugin) into the new #__j2commerce_migrator_idmap table so that
     * previously migrated records are not re-migrated on next run.
     */
    private function migrateLegacyIdmap(): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Check whether the legacy table exists.
        $tables = $db->getTableList();
        $prefix = $db->getPrefix();
        $legacy = $prefix . 'j2commerce_migration_idmap';

        if (!in_array($legacy, $tables, true)) {
            return;
        }

        $adapter = 'j2store4';

        // Only copy rows that have not already been imported.
        $insert = 'INSERT IGNORE INTO ' . $db->quoteName('#__j2commerce_migrator_idmap')
            . ' (' . $db->quoteName('adapter') . ', '
            . $db->quoteName('entity') . ', '
            . $db->quoteName('source_id') . ', '
            . $db->quoteName('target_id') . ')'
            . ' SELECT ' . $db->quote($adapter) . ', '
            . $db->quoteName('entity') . ', '
            . $db->quoteName('source_id') . ', '
            . $db->quoteName('target_id')
            . ' FROM ' . $db->quoteName('#__j2commerce_migration_idmap');

        try {
            $db->setQuery($insert)->execute();
        } catch (\Throwable) {
            // Non-fatal — legacy table schema may differ; log and continue.
            Factory::getApplication()->enqueueMessage(
                Text::_('COM_J2COMMERCEMIGRATOR_MSG_LEGACY_IDMAP_SKIPPED'),
                'notice'
            );
        }
    }
}
