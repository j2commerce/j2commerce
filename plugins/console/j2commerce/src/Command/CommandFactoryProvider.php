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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

class CommandFactoryProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(
            CommandFactoryInterface::class,
            function (Container $container) {
                $factory = new CommandFactory();

                $factory->setMVCFactory($container->get(MVCFactoryInterface::class));
                $factory->setDatabase($container->get(DatabaseInterface::class));
                $factory->setApplication(Factory::getApplication());

                return $factory;
            }
        );
    }
}
