<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.Console.J2Commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace J2Commerce\Plugin\Console\J2Commerce\Command;

\defined('_JEXEC') || die;

use Joomla\Console\Command\AbstractCommand;

interface CommandFactoryInterface
{
    public function getCLICommand(string $commandName): AbstractCommand;
}
