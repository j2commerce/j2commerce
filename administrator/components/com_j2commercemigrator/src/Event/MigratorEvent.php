<?php

/**
 * @package     J2Commerce
 * @subpackage  com_j2commercemigrator
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace J2Commerce\Component\J2commercemigrator\Administrator\Event;

defined('_JEXEC') or die;

use Joomla\Event\Event;

/**
 * Abstract base for all J2CommerceMigrator events.
 * Provides typed getter helpers for the arguments common to every migrator event.
 */
abstract class MigratorEvent extends Event
{
    public function getAdapter(): string
    {
        return (string) $this->getArgument('adapter', '');
    }
}
