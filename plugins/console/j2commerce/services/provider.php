<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.Console.J2Commerce
 *
 * @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') || die;

use J2Commerce\Plugin\Console\J2Commerce\Command\CommandFactoryInterface;
use J2Commerce\Plugin\Console\J2Commerce\Command\CommandFactoryProvider;
use J2Commerce\Plugin\Console\J2Commerce\Extension\J2Commerce;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

// Make sure that Joomla has registered the namespace for the plugin
if (!class_exists('\J2Commerce\Plugin\Console\J2Commerce\Extension\J2Commerce')) {
    JLoader::registerNamespace('\J2Commerce\Plugin\Console\J2Commerce', realpath(__DIR__ . '/../src'));
}

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('J2Commerce\\Component\\J2commerce'));
        $container->registerServiceProvider(new CommandFactoryProvider());

        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $config  = (array) PluginHelper::getPlugin('console', 'j2commerce');
                $subject = $container->get(DispatcherInterface::class);

                $plugin = version_compare(JVERSION, '5.4.0', 'ge')
                    ? new J2Commerce($config)
                    : new J2Commerce(
                        $subject,
                        $config
                    );

                $plugin->setApplication(Factory::getApplication());
                $plugin->setCLICommandFactory($container->get(CommandFactoryInterface::class));

                return $plugin;
            }
        );
    }
};
