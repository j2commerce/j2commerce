<?php

/**
 * @package     J2Commerce
 * @subpackage  Plugin.J2Commerce.PaymentMoneyorder
 *
 * @copyright   Copyright (C) 2024-2026 J2Commerce, LLC. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use J2Commerce\Plugin\J2Commerce\PaymentMoneyorder\Extension\PaymentMoneyorder;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = PluginHelper::getPlugin('j2commerce', 'payment_moneyorder');

                return new PaymentMoneyorder(
                    $dispatcher,
                    (array) $plugin,
                    Factory::getApplication()->getLanguage(),
                    $container->get('DatabaseDriver')
                );
            }
        );
    }
};
