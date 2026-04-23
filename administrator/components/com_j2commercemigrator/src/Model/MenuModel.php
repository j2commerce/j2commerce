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

use J2Commerce\Component\J2commercemigrator\Administrator\Helper\MigrationLogger;
use J2Commerce\Component\J2commercemigrator\Administrator\Service\MenuMigrator;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Menu model — wraps MenuMigrator for the Finalize wizard step.
 */
class MenuModel extends BaseDatabaseModel
{
    private function migrator(): MenuMigrator
    {
        return new MenuMigrator($this->getDatabase(), new MigrationLogger());
    }

    /** Lists all frontend menu items that currently point to J2Store routes. */
    public function getJ2StoreMenuItems(): array
    {
        return $this->migrator()->getJ2StoreMenuItems();
    }

    /** Migrates only the user-selected menu items to J2Commerce routes. */
    public function migrateSelected(array $menuIds): array
    {
        return $this->migrator()->migrateSelected($menuIds);
    }

    /** Auto-migrates every safe J2Store menu item to J2Commerce. */
    public function migrate(): array
    {
        return $this->migrator()->migrate();
    }

    /** Rolls back previously migrated menu items. */
    public function rollback(): array
    {
        return $this->migrator()->rollback();
    }

    /** Creates canonical J2Commerce menu items in the default menu. */
    public function createMenuItems(): array
    {
        return $this->migrator()->createMenuItems();
    }
}
