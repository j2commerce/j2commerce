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
use J2Commerce\Component\J2commercemigrator\Administrator\Service\ConfigurationMigrator;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Config model — wraps ConfigurationMigrator for the Config wizard step.
 */
class ConfigModel extends BaseDatabaseModel
{
    private function migrator(): ConfigurationMigrator
    {
        return new ConfigurationMigrator($this->getDatabase(), new MigrationLogger());
    }

    /**
     * Analyzes J2Store component config vs J2Commerce defaults.
     * Returns per-key comparison with recommended action.
     */
    public function analyze(): array
    {
        return $this->migrator()->analyze();
    }

    /**
     * Applies the user's per-key migration choices.
     *
     * @param array $choices  Associative array of key → chosen value
     */
    public function migrate(array $choices): array
    {
        return $this->migrator()->migrate($choices);
    }
}
